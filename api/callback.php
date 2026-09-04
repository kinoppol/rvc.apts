<?php
/**
 * ONE-RVC SSO callback. This file's URL must match the redirect_uri ONE-RVC was
 * registered with exactly — https://apts.rvc.ac.th/web/api/callback.php — including
 * scheme and trailing characters.
 *
 * Two request shapes reach it, both the user's own browser navigating here (this app
 * never calls out to start the flow itself):
 *
 *   GET  ?error=...                  the user declined ONE-RVC's consent screen
 *   POST token_id, token_key, state  the user approved; ONE-RVC's page auto-submits
 *                                     an HTML form to this URL
 *
 * Only the POST path can create a session or a link, and only after two independent
 * checks: `state` must match what sso-login.php stashed in this session (the anti-CSRF
 * guard — this is precisely why bootstrap.php sets the session cookie SameSite=None
 * on HTTPS; a cross-site POST navigation would otherwise never carry the cookie, and
 * `state` would never be visible here to check), and the token itself must
 * independently verify against ONE-RVC's own endpoint via SsoAuth::verifyToken() —
 * token_id is never trusted just because it looks well-formed.
 *
 * The whole POST branch runs inside one try/catch: this endpoint only ever receives
 * genuinely new, previously-unexercised traffic (each request carries a one-time
 * token from a real external system we don't control), so nothing here is allowed to
 * surface as a raw PHP fatal — every failure degrades to a Thai flash message plus a
 * logged exception (class + message only, never $tokenId/$tokenKey/$ssoUser) instead.
 */
require_once __DIR__ . '/../bootstrap.php';

/** admin/profile.php for an admin session, student/profile.php otherwise. */
function sso_profile_path(?array $user): string
{
    return ($user && $user['role'] === 'admin') ? 'admin/profile.php' : 'student/profile.php';
}

/** Logs an SSO failure server-side without ever touching token or user data. */
function sso_log_failure(string $where, Throwable $e): void
{
    error_log('[SsoAuth callback] ' . $where . ': ' . get_class($e) . ': ' . $e->getMessage()
        . ' at ' . $e->getFile() . ':' . $e->getLine());
}

// A PHP fatal error (not everything is a catchable Throwable — e.g. exhausting the
// memory limit) would otherwise leave Apache to serve a bare, unlogged 500. This is
// the last line of defense: log whatever PHP's own error handler recorded.
register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[SsoAuth callback] fatal: ' . $err['message'] . ' at ' . $err['file'] . ':' . $err['line']);
    }
});

// ---- GET: the user declined at ONE-RVC, or otherwise arrived with no token ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $linkUserId = isset($_SESSION['sso_link_user_id']) ? (int) $_SESSION['sso_link_user_id'] : null;
        unset($_SESSION['sso_state'], $_SESSION['sso_link_user_id']);

        if ($linkUserId !== null) {
            flash_set('err', 'คุณยกเลิกการผูกบัญชีกับ ONE-RVC');
            header('Location: ' . url(sso_profile_path(Auth::findById($linkUserId))));
        } else {
            flash_set('err', 'คุณยกเลิกการเข้าสู่ระบบผ่าน ONE-RVC');
            header('Location: ' . url('login.php'));
        }
    } catch (Throwable $e) {
        sso_log_failure('GET/decline', $e);
        header('Location: ' . url('login.php'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Method Not Allowed');
}

try {
    // ---- state must match this session (CSRF guard) ----
    $state         = (string) ($_POST['state'] ?? '');
    $expectedState = (string) ($_SESSION['sso_state'] ?? '');
    $linkUserId    = isset($_SESSION['sso_link_user_id']) ? (int) $_SESSION['sso_link_user_id'] : null;
    unset($_SESSION['sso_state'], $_SESSION['sso_link_user_id']);

    if ($expectedState === '' || !hash_equals($expectedState, $state)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Invalid or missing state.');
    }

    // token_id / token_key must never be logged — not here, not inside SsoAuth::verifyToken().
    $tokenId  = (string) ($_POST['token_id'] ?? '');
    $tokenKey = (string) ($_POST['token_key'] ?? '');

    $result = SsoAuth::verifyToken($tokenId, $tokenKey);
    if (!$result['ok']) {
        // Logged (never shown — this endpoint is reachable by anyone completing an SSO
        // round trip, not just an authenticated admin) so a real failure still leaves a
        // trail distinguishing "gateway unreachable" from "token rejected" from
        // "malformed response" without needing to guess blind again.
        error_log('[SsoAuth callback] verify failed: ' . ($result['error'] ?? '(no reason)'));
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        exit('SSO token verification failed.');
    }
    $ssoUser = $result['user'];

    // ---- Linking mode: attach this ONE-RVC identity to the account that started the flow ----
    if ($linkUserId !== null) {
        $current = current_user();

        if (!$current || (int) $current['id'] !== $linkUserId) {
            // Session changed mid-flow (e.g. logged out in another tab) — don't link blindly.
            flash_set('err', 'เซสชันของคุณเปลี่ยนแปลงระหว่างการผูกบัญชี กรุณาเข้าสู่ระบบแล้วลองใหม่อีกครั้ง');
            header('Location: ' . url('login.php'));
            exit;
        }

        $link = SsoAuth::link($linkUserId, (string) ($ssoUser['id'] ?? ''));
        flash_set($link['ok'] ? 'ok' : 'err',
            $link['ok'] ? 'ผูกบัญชี ONE-RVC เรียบร้อยแล้ว' : ($link['error'] ?? 'ผูกบัญชีไม่สำเร็จ'));
        header('Location: ' . url(sso_profile_path($current)));
        exit;
    }

    // ---- Login mode: resolve the ONE-RVC identity to a local account and sign in ----
    $resolved = SsoAuth::resolveLogin($ssoUser);
    if (!$resolved['ok']) {
        flash_set('err', $resolved['error'] ?? 'เข้าสู่ระบบผ่าน ONE-RVC ไม่สำเร็จ');
        header('Location: ' . url('login.php'));
        exit;
    }

    $user = $resolved['user'];
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    if (!empty($resolved['justLinked'])) {
        flash_set('ok', 'เข้าสู่ระบบสำเร็จ และผูกบัญชี ONE-RVC ให้อัตโนมัติแล้ว (พบอีเมลที่ตรงกับบัญชีเดิมของคุณ)');
    }

    header('Location: ' . url($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
} catch (Throwable $e) {
    sso_log_failure('POST', $e);
    // The wider on-screen diagnostic (class + message + file:line) that lived here
    // during debugging is gone now that it did its job (found the missing ext-curl on
    // production) — login-mode reaches this same branch and is open to anyone, so back
    // to a generic message; sso_log_failure() above still has the exact class/message/
    // file:line for whoever has server log access if this ever needs revisiting.
    flash_set('err', 'เกิดข้อผิดพลาดขณะเข้าสู่ระบบผ่าน ONE-RVC กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบหากยังไม่สำเร็จ');
    header('Location: ' . url(($linkUserId ?? null) !== null ? sso_profile_path(current_user()) : 'login.php'));
    exit;
}
