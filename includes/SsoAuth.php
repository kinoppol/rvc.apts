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
        if (!function_exists('curl_init')) {
            // Fails clean instead of a fatal "Call to undefined function curl_init()"
            // (a plain PHP \Error, not a Throwable subclass caught anywhere useful) —
            // the server's PHP build is missing ext-curl. Requires a server-side fix
            // (install/enable php-curl and restart the web server); nothing here can
            // work around a missing extension.
            return ['ok' => false, 'error' => 'เซิร์ฟเวอร์ไม่พร้อมใช้งานการยืนยันตัวตนผ่าน ONE-RVC (ไม่มีส่วนขยาย PHP curl) กรุณาติดต่อผู้ดูแลระบบ'];
        }

        $secret = OaUserSync::secret();
        if ($secret === '') {
            // ONE-RVC's verify_token requires HTTP Basic client auth (401 invalid_client without it).
            return ['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า client_secret ของ ONE-RVC (ตั้งได้ที่หน้าซิงค์ผู้ใช้ ONE-RVC)'];
        }

        $ch = curl_init(ONE_RVC_VERIFY_URL);
        $opts = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['token_id' => $tokenId, 'token_key' => $tokenKey]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => ONE_RVC_CLIENT_ID . ':' . $secret,   // never logged
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
        ];
        // Production's app server can't reach the gateway by its public name — see resolvePin().
        $opts += self::resolvePin(ONE_RVC_VERIFY_URL);
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

    /**
     * CURLOPT_RESOLVE pin for a server-to-server call to ONE-RVC (verify_token, the
     * User-sync API). Production's app server and the SSO gateway share an internal
     * bridge network where the public hostname doesn't resolve/route server-side (only
     * a visitor's own browser reaches it, for the auth-redirect step) — this pins just
     * the TCP connect to a private IP while leaving the URL and Host header untouched,
     * so name-based virtual hosting on the gateway still routes correctly.
     * Admin-configurable (admin/settings.php -> SlotSettings), since this is production
     * network topology, not something to hardcode or redeploy for; ONE_RVC_VERIFY_IP
     * (config.local.php) is a fallback for when nobody has set it in the UI yet.
     *
     * @return array<int,mixed> curl options to merge (empty = resolve normally)
     */
    public static function resolvePin(string $url): array
    {
        $ip = SlotSettings::getSsoVerifyIp() ?? (ONE_RVC_VERIFY_IP !== '' ? ONE_RVC_VERIFY_IP : null);
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if ($ip === null || $host === '') {
            return [];
        }
        $port = parse_url($url, PHP_URL_PORT) ?: (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);
        return [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]];
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
     * in with ONE-RVC with no extra step, as long as the email matches. Else
     * provision() creates a new, already-approved general member — a verified
     * ONE-RVC identity is trusted enough to skip the admin approval queue.
     *
     * @return array{ok:bool,error?:string,user?:array,justLinked?:bool,justCreated?:bool}
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

        $justCreated = false;
        if (!$user) {
            $created = self::provision($ssoUser);
            if (!$created['ok']) {
                return $created;
            }
            $user = $created['user'];
            $justCreated = true;
        }
        if ($user['status'] === 'pending') {
            return ['ok' => false, 'error' => 'บัญชีของคุณยังรอการอนุมัติจากผู้ดูแลระบบ'];
        }
        if ($user['status'] === 'suspended' || OaUserSync::blocksUser($user)) {
            return ['ok' => false, 'error' => 'บัญชีของคุณถูกระงับสิทธิ์การใช้งาน'];
        }

        return ['ok' => true, 'user' => $user, 'justLinked' => $justLinked, 'justCreated' => $justCreated];
    }

    /**
     * Creates an approved local account for a verified ONE-RVC identity nobody here
     * has yet. Role: ONE-RVC staff -> teacher, everyone else -> student (never admin).
     * Group: the "ทั่วไป" (general) user group if one exists, else none (an admin can
     * assign one later). Major/subject/student id are left empty — the member can fill
     * them in on the profile page. The password is a random unusable hash: this
     * account signs in through ONE-RVC until an admin resets a password for it.
     *
     * @return array{ok:bool,error?:string,user?:array}
     */
    private static function provision(array $ssoUser): array
    {
        $ssoUserId = (string) ($ssoUser['id'] ?? '');
        $email     = trim((string) ($ssoUser['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'บัญชี ONE-RVC ของคุณไม่มีอีเมล จึงสร้างบัญชีในระบบนี้ให้อัตโนมัติไม่ได้ กรุณาสมัครสมาชิกใหม่'];
        }
        if (Auth::findByEmail($email)) {
            // Same email, but already linked to a different ONE-RVC identity.
            return ['ok' => false, 'error' => 'อีเมลของบัญชี ONE-RVC นี้ถูกใช้กับบัญชีอื่นในระบบแล้ว กรุณาติดต่อผู้ดูแลระบบ'];
        }

        $name = trim((string) ($ssoUser['name'] ?? ''));
        if ($name === '') {
            $name = trim(((string) ($ssoUser['first_name'] ?? '')) . ' ' . ((string) ($ssoUser['last_name'] ?? '')));
        }
        $name = mb_substr($name !== '' ? $name : $email, 0, 150);

        $type = strtolower((string) ($ssoUser['user_type'] ?? $ssoUser['type'] ?? ''));
        if ($type === '') {
            try {
                $stmt = Database::pdo()->prepare('SELECT user_type FROM oa_users WHERE oa_user_id = ?');
                $stmt->execute([$ssoUserId]);
                $type = strtolower((string) $stmt->fetchColumn());
            } catch (PDOException $e) {
                $type = '';   // oa_users not migrated yet
            }
        }
        $role = in_array($type, ['staff', 'teacher'], true) ? 'teacher' : 'student';

        $groupId = Database::pdo()
            ->query("SELECT id FROM user_groups WHERE name LIKE '%ทั่วไป%' ORDER BY id LIMIT 1")
            ->fetchColumn();

        Database::pdo()->prepare(
            "INSERT INTO users (role, name, email, group_id, password_hash, sso_user_id, sso_linked_at, status)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), 'approved')"
        )->execute([
            $role, $name, $email, $groupId !== false ? (int) $groupId : null,
            password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $ssoUserId,
        ]);

        $user = self::findByExternalId($ssoUserId);
        return $user ? ['ok' => true, 'user' => $user] : ['ok' => false, 'error' => 'สร้างบัญชีจาก ONE-RVC ไม่สำเร็จ'];
    }
}
