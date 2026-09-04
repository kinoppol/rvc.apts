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
 */
require_once __DIR__ . '/../bootstrap.php';

/** admin/profile.php for an admin session, student/profile.php otherwise. */
function sso_profile_path(?array $user): string
{
    return ($user && $user['role'] === 'admin') ? 'admin/profile.php' : 'student/profile.php';
}

// ---- GET: the user declined at ONE-RVC, or otherwise arrived with no token ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $linkUserId = isset($_SESSION['sso_link_user_id']) ? (int) $_SESSION['sso_link_user_id'] : null;
    unset($_SESSION['sso_state'], $_SESSION['sso_link_user_id']);

    if ($linkUserId !== null) {
        flash_set('err', 'คุณยกเลิกการผูกบัญชีกับ ONE-RVC');
        header('Location: ' . url(sso_profile_path(Auth::findById($linkUserId))));
    } else {
        flash_set('err', 'คุณยกเลิกการเข้าสู่ระบบผ่าน ONE-RVC');
        header('Location: ' . url('login.php'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Method Not Allowed');
}

// ---- POST: state must match this session (CSRF guard) ----
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
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('SSO token verification failed.');
}
$ssoUser = $result['user'];

// Everything from here on touches the database in a brand-new way (users.sso_user_id /
// sso_linked_at) that no earlier request on this server has ever exercised — this is
// the very first real, successfully-verified ONE-RVC token this app has ever received.
// If migrate_sso_login.sql hasn't actually been applied here, every query below throws
// a raw PDOException; catch that (and anything else unexpected) so a deploy/ops issue
// degrades to a Thai error message instead of a blank HTTP 500. The exception is logged
// server-side (class + message only — never $tokenId/$tokenKey, never $ssoUser) so
// whoever has log access can see exactly what broke.
try {
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
    error_log('[SsoAuth callback] ' . get_class($e) . ': ' . $e->getMessage());
    flash_set('err', 'เกิดข้อผิดพลาดขณะเข้าสู่ระบบผ่าน ONE-RVC กรุณาลองใหม่อีกครั้ง หรือติดต่อผู้ดูแลระบบหากยังไม่สำเร็จ');
    header('Location: ' . url($linkUserId !== null ? sso_profile_path(current_user()) : 'login.php'));
    exit;
}
