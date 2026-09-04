<?php

/**
 * ONE-RVC single sign-on — the college's external SSO gateway.
 *
 * Flow: sso-login.php redirects the browser to ONE_RVC_AUTH_URL with a random state
 * stashed in session; ONE-RVC authenticates the user (password + OTP if enabled + a
 * first-time consent screen) and sends the browser back to api/callback.php — either
 * a GET with ?error=... if the user declined, or a top-level POST carrying
 * token_id/token_key/state if they approved. The callback verifies state against
 * session, then verifies the token against ONE-RVC's own endpoint (never trusted
 * as-is, even though token_id embeds the user id) before any session is created.
 *
 * See api/callback.php for the request-level orchestration; this class holds the
 * two things that belong in a domain class — the outbound HTTP call and the
 * (login vs. explicit-link) account-resolution rules.
 */
final class SsoAuth
{
    private const HTTP_TIMEOUT    = 10;
    private const CONNECT_TIMEOUT = 5;

    /** The URL to send the browser to at ONE-RVC to start the flow. */
    public static function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id'    => ONE_RVC_CLIENT_ID,
            'redirect_uri' => ONE_RVC_REDIRECT_URI,
            'state'        => $state,
        ]);
        return ONE_RVC_AUTH_URL . '?' . $query;
    }

    /**
     * Verifies a token_id/token_key pair against ONE-RVC's own verify endpoint —
     * the only source of truth; token_id is never trusted on its own even though it
     * embeds the user id ({user_id}_{expiry_unix}). A down, slow, or malformed verify
     * endpoint is treated as a plain failure rather than an exception, so a flaky
     * upstream never 500s the callback.
     *
     * Deliberately never logs $tokenId/$tokenKey, on this call path or the response
     * body (which could echo them back) — callers must not log them either.
     *
     * @return array{ok:bool,error?:string,user?:array,org?:array}
     */
    public static function verifyToken(string $tokenId, string $tokenKey): array
    {
        if ($tokenId === '' || $tokenKey === '') {
            return ['ok' => false, 'error' => 'ไม่พบโทเคนจากระบบ ONE-RVC'];
        }

        $ch = curl_init(ONE_RVC_VERIFY_URL);
        $opts = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['token_id' => $tokenId, 'token_key' => $tokenKey]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
        ];
        // Production's app server and the SSO gateway share an internal bridge network
        // where the public hostname doesn't resolve/route server-side (only a visitor's
        // own browser reaches it, for the auth-redirect step) — CURLOPT_RESOLVE pins
        // just the TCP connect to a private IP while leaving the URL and Host header
        // untouched, so name-based virtual hosting on the gateway still routes
        // correctly. Admin-configurable (admin/settings.php -> SlotSettings), since this
        // is production network topology, not something to hardcode or redeploy for.
        // ONE_RVC_VERIFY_IP (config.local.php) is a fallback for when nobody has set it
        // in the UI yet.
        $verifyIp = SlotSettings::getSsoVerifyIp() ?? (ONE_RVC_VERIFY_IP !== '' ? ONE_RVC_VERIFY_IP : null);
        if ($verifyIp !== null) {
            $host = parse_url(ONE_RVC_VERIFY_URL, PHP_URL_HOST) ?: '';
            $port = parse_url(ONE_RVC_VERIFY_URL, PHP_URL_PORT) ?: (parse_url(ONE_RVC_VERIFY_URL, PHP_URL_SCHEME) === 'https' ? 443 : 80);
            if ($host !== '') {
                $opts[CURLOPT_RESOLVE] = ["{$host}:{$port}:" . $verifyIp];
            }
        }
        curl_setopt_array($ch, $opts);
        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            return ['ok' => false, 'error' => 'ไม่สามารถติดต่อระบบ ONE-RVC ได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง'];
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['valid']) || empty($data['user']['id'])) {
            $reason = is_array($data) ? (string) ($data['error'] ?? '') : '';
            return ['ok' => false, 'error' => $reason !== ''
                ? 'ยืนยันตัวตนกับ ONE-RVC ไม่สำเร็จ: ' . $reason
                : 'โทเคนไม่ถูกต้องหรือหมดอายุ กรุณาเข้าสู่ระบบใหม่'];
        }

        return ['ok' => true, 'user' => $data['user'], 'org' => $data['org'] ?? null];
    }

    /** The local account already linked to this ONE-RVC identity, or null. */
    public static function findByExternalId(string $ssoUserId): ?array
    {
        if ($ssoUserId === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT u.*, g.name AS group_name FROM users u
             LEFT JOIN user_groups g ON g.id = u.group_id WHERE u.sso_user_id = ?'
        );
        $stmt->execute([$ssoUserId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Links a ONE-RVC identity to a local account (used both by the explicit
     * "ผูกบัญชี" action, and internally by resolveLogin()'s email auto-link).
     * Refuses to steal an identity already linked to a different account.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function link(int $userId, string $ssoUserId): array
    {
        if ($ssoUserId === '') {
            return ['ok' => false, 'error' => 'ข้อมูลผู้ใช้จาก ONE-RVC ไม่ถูกต้อง'];
        }
        $existing = self::findByExternalId($ssoUserId);
        if ($existing && (int) $existing['id'] !== $userId) {
            return ['ok' => false, 'error' => 'บัญชี ONE-RVC นี้ถูกผูกกับบัญชีผู้ใช้อื่นในระบบไปแล้ว'];
        }
        Database::pdo()
            ->prepare('UPDATE users SET sso_user_id = ?, sso_linked_at = NOW() WHERE id = ?')
            ->execute([$ssoUserId, $userId]);
        return ['ok' => true];
    }

    /** Removes the ONE-RVC link; the account's password keeps working as before. */
    public static function unlink(int $userId): void
    {
        Database::pdo()
            ->prepare('UPDATE users SET sso_user_id = NULL, sso_linked_at = NULL WHERE id = ?')
            ->execute([$userId]);
    }

    /**
     * Resolves a verified ONE-RVC identity to a local account for the *login* flow
     * (not the explicit "link my account" flow while already signed in — see
     * link() for that, called directly from the callback for that mode).
     *
     * Order: an account already linked to this ONE-RVC id, else auto-link an
     * existing *unlinked* account whose email matches the one ONE-RVC returned —
     * this is what lets someone who registered themselves before SSO existed sign
     * in with ONE-RVC with no extra step, as long as the email matches. Never
     * creates a new account: registration here requires admin approval and a
     * major/subject selection that ONE-RVC does not provide, so an unmatched
     * identity is turned away with guidance instead of being auto-provisioned.
     *
     * @return array{ok:bool,error?:string,user?:array,justLinked?:bool}
     */
    public static function resolveLogin(array $ssoUser): array
    {
        $ssoUserId = (string) ($ssoUser['id'] ?? '');
        if ($ssoUserId === '') {
            return ['ok' => false, 'error' => 'ข้อมูลผู้ใช้จาก ONE-RVC ไม่ถูกต้อง'];
        }

        $user = self::findByExternalId($ssoUserId);
        $justLinked = false;

        if (!$user) {
            $email = trim((string) ($ssoUser['email'] ?? ''));
            if ($email !== '') {
                $candidate = Auth::findByEmail($email);
                if ($candidate && empty($candidate['sso_user_id'])) {
                    $linked = self::link((int) $candidate['id'], $ssoUserId);
                    if ($linked['ok']) {
                        $user = self::findByExternalId($ssoUserId);
                        $justLinked = true;
                    }
                }
            }
        }

        if (!$user) {
            return ['ok' => false, 'error' =>
                'ไม่พบบัญชีในระบบนี้ที่ตรงกับบัญชี ONE-RVC ของคุณ ' .
                'กรุณาเข้าสู่ระบบด้วยอีเมล/รหัสผ่านที่เคยสมัครไว้ แล้วผูกบัญชีจากหน้าโปรไฟล์ หรือสมัครสมาชิกใหม่'];
        }
        if ($user['status'] === 'pending') {
            return ['ok' => false, 'error' => 'บัญชีของคุณยังรอการอนุมัติจากผู้ดูแลระบบ'];
        }
        if ($user['status'] === 'suspended') {
            return ['ok' => false, 'error' => 'บัญชีของคุณถูกระงับสิทธิ์การใช้งาน'];
        }

        return ['ok' => true, 'user' => $user, 'justLinked' => $justLinked];
    }
}
