<?php

/**
 * ONE-RVC User-sync API client — pulls the college directory (staff and/or students)
 * server-to-server and upserts it into the `oa_users` mirror, keyed by ONE-RVC's own
 * user id (the same id SsoAuth gets back from verify_token, stored on our side as
 * users.sso_user_id).
 *
 * What a sync does to this app's own accounts, deliberately limited:
 *   - never creates a login: registration here needs admin approval plus a
 *     major/subject that ONE-RVC doesn't send (same reasoning as SsoAuth::resolveLogin);
 *   - links an existing *unlinked* account whose email matches (same rule as
 *     login-time auto-link), so the next SSO login just works;
 *   - "suspended" upstream blocks the linked account — not by copying a flag into
 *     users.status, but by isSuspended() reading the mirror at login/request time
 *     (house rule: derive state at read time). Re-activation upstream therefore
 *     unblocks automatically, and an admin's own suspend/approve stays untouched.
 *
 * Incremental runs use the `server_time` of the newest *successful* run as `since`
 * (always the API's clock, never ours); a run that fails mid-way records ok = 0 and
 * so never advances it. Users changed in the same second as `since` may come back
 * twice — the upsert is idempotent, so that's harmless.
 *
 * Never logs or stores the client secret.
 */
final class OaUserSync
{
    private const HTTP_TIMEOUT    = 30;
    private const CONNECT_TIMEOUT = 5;
    private const PAGE_LIMIT      = 500;
    private const MAX_ATTEMPTS    = 3;      // per page, for network errors / 5xx only
    private const MAX_PAGES       = 1000;   // hard stop against a cursor that never ends
    private const LOCK_NAME       = 'rvc_apts_oa_user_sync';

    public static function isConfigured(): bool
    {
        return ONE_RVC_USERS_URL !== '' && ONE_RVC_CLIENT_ID !== '' && self::secret() !== '';
    }

    /** Where the active secret comes from: 'db' (admin UI), 'config' (config.local.php / env) or null. */
    public static function secretSource(): ?string
    {
        if (self::dbSecret() !== '') {
            return 'db';
        }
        return ONE_RVC_CLIENT_SECRET !== '' ? 'config' : null;
    }

    /** Saves (or, with '', clears) the admin-set secret. Never echoed back to any page. */
    public static function saveSecret(string $secret): array
    {
        $secret = trim($secret);
        if (strlen($secret) > 255) {
            return ['ok' => false, 'error' => 'client_secret ยาวเกินไป'];
        }
        Database::pdo()->prepare('UPDATE slot_settings SET oa_client_secret = ? WHERE id = 1')
            ->execute([$secret !== '' ? $secret : null]);
        return ['ok' => true];
    }

    /**
     * Admin-set secret wins over config.local.php / env. Public because SsoAuth::verifyToken()
     * authenticates with the same client_id:client_secret pair. Never log or echo it.
     */
    public static function secret(): string
    {
        $db = self::dbSecret();
        return $db !== '' ? $db : ONE_RVC_CLIENT_SECRET;
    }

    private static function dbSecret(): string
    {
        try {
            $v = Database::pdo()->query('SELECT oa_client_secret FROM slot_settings WHERE id = 1')->fetchColumn();
            return is_string($v) ? $v : '';
        } catch (PDOException $e) {
            return '';   // column not migrated yet
        }
    }

    /** `since` for the next incremental run, or null = never synced successfully (full sync). */
    public static function lastSince(): ?string
    {
        $v = Database::pdo()
            ->query('SELECT server_time FROM oa_sync_runs WHERE ok = 1 AND server_time IS NOT NULL ORDER BY id DESC LIMIT 1')
            ->fetchColumn();
        return $v !== false ? (string) $v : null;
    }

    /** @return array<int,array> newest first */
    public static function recentRuns(int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM oa_sync_runs ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array{total:int,active:int,suspended:int,linked:int,linkedSuspended:int} */
    public static function stats(): array
    {
        $row = Database::pdo()->query(
            "SELECT COUNT(*) total,
                    COALESCE(SUM(o.status = 'active'), 0) active,
                    COALESCE(SUM(o.status = 'suspended'), 0) suspended,
                    COUNT(u.id) linked,
                    COALESCE(SUM(u.id IS NOT NULL AND o.status = 'suspended'), 0) linkedSuspended
             FROM oa_users o LEFT JOIN users u ON u.sso_user_id = o.oa_user_id"
        )->fetch();
        return array_map('intval', $row);
    }

    /** Local accounts currently blocked because their linked ONE-RVC identity is suspended. */
    public static function blockedAccounts(): array
    {
        return Database::pdo()->query(
            "SELECT u.id, u.name, u.email, u.role, o.first_name, o.last_name, o.department, o.synced_at
             FROM users u JOIN oa_users o ON o.oa_user_id = u.sso_user_id
             WHERE o.status = 'suspended' AND u.role <> 'admin' ORDER BY u.name"
        )->fetchAll();
    }

    /**
     * True when this ONE-RVC identity is mirrored as suspended. Fails open (false) if
     * the mirror table doesn't exist yet — e.g. the few seconds between a deploy's
     * `git reset` and `php migrate.php` — so a missing table can never lock everyone out.
     */
    public static function isSuspended(?string $ssoUserId): bool
    {
        if ($ssoUserId === null || $ssoUserId === '') {
            return false;
        }
        try {
            $stmt = Database::pdo()->prepare('SELECT status FROM oa_users WHERE oa_user_id = ?');
            $stmt->execute([$ssoUserId]);
            return $stmt->fetchColumn() === 'suspended';
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Should this local account be refused because ONE-RVC suspended it? Admins are
     * exempt so a directory change upstream can never lock the last admin out of the
     * app that would be used to sort it out.
     */
    public static function blocksUser(array $user): bool
    {
        return ($user['role'] ?? '') !== 'admin' && self::isSuspended($user['sso_user_id'] ?? null);
    }

    /**
     * Runs one sync. $full = ignore the stored since and pull everything.
     *
     * @return array{ok:bool,error?:string,mode:string,pages:int,added:int,updated:int,suspended:int,linked:int,server_time?:?string}
     */
    public static function run(bool $full = false): array
    {
        $since = $full ? null : self::lastSince();
        $mode  = $since === null ? 'full' : 'incremental';
        $stats = ['mode' => $mode, 'pages' => 0, 'added' => 0, 'updated' => 0, 'suspended' => 0, 'linked' => 0];

        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่า client_secret ของ ONE-RVC (ตั้งได้ที่หน้าซิงค์ผู้ใช้ ONE-RVC)'] + $stats;
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'เซิร์ฟเวอร์ไม่มีส่วนขยาย PHP curl'] + $stats;
        }

        $pdo = Database::pdo();
        if ((int) $pdo->query("SELECT GET_LOCK('" . self::LOCK_NAME . "', 0)")->fetchColumn() !== 1) {
            return ['ok' => false, 'error' => 'มีการซิงค์กำลังทำงานอยู่แล้ว กรุณารอสักครู่'] + $stats;
        }

        $pdo->prepare('INSERT INTO oa_sync_runs (mode, since_used) VALUES (?, ?)')->execute([$mode, $since]);
        $runId = (int) $pdo->lastInsertId();

        try {
            $result = self::pullAll($since, $stats);
        } catch (Throwable $e) {
            error_log('[OaUserSync] ' . get_class($e) . ': ' . $e->getMessage());
            $result = ['ok' => false, 'error' => 'เกิดข้อผิดพลาดภายในระหว่างซิงค์: ' . $e->getMessage()];
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
        }

        $stats = $result['stats'] ?? $stats;
        $pdo->prepare(
            'UPDATE oa_sync_runs SET ok = ?, server_time = ?, pages = ?, added = ?, updated = ?,
                    suspended = ?, linked = ?, error = ?, finished_at = NOW() WHERE id = ?'
        )->execute([
            $result['ok'] ? 1 : 0,
            $result['ok'] ? ($result['server_time'] ?? null) : null,   // failed run must not advance since
            $stats['pages'], $stats['added'], $stats['updated'], $stats['suspended'], $stats['linked'],
            $result['ok'] ? null : mb_substr((string) ($result['error'] ?? ''), 0, 500),
            $runId,
        ]);

        error_log(sprintf('[OaUserSync] run #%d %s %s: pages=%d added=%d updated=%d suspended=%d linked=%d%s',
            $runId, $stats['mode'], $result['ok'] ? 'ok' : 'FAILED',
            $stats['pages'], $stats['added'], $stats['updated'], $stats['suspended'], $stats['linked'],
            $result['ok'] ? '' : ' error=' . ($result['error'] ?? '')));

        return ['ok' => (bool) $result['ok'], 'error' => $result['error'] ?? null,
                'server_time' => $result['server_time'] ?? null] + $stats;
    }

    /** Pages through the API until has_more is false. @return array{ok:bool,error?:string,server_time?:string,stats:array} */
    private static function pullAll(?string $since, array $stats): array
    {
        $restartedOnBadCursor = false;
        $cursor     = null;
        $serverTime = null;

        while (true) {
            if ($stats['pages'] >= self::MAX_PAGES) {
                return ['ok' => false, 'error' => 'จำนวนหน้าเกินขีดจำกัด (' . self::MAX_PAGES . ')', 'stats' => $stats];
            }

            $params = $cursor !== null
                ? ['cursor' => $cursor, 'limit' => self::PAGE_LIMIT]
                : array_filter(['type' => ONE_RVC_SYNC_TYPE, 'since' => $since, 'limit' => self::PAGE_LIMIT],
                    fn ($v) => $v !== null && $v !== '');

            $resp = self::request($params);
            if (!$resp['ok']) {
                if (($resp['code'] ?? '') === 'bad_cursor' && !$restartedOnBadCursor) {
                    // The API's documented recovery: start the round over from page one.
                    $restartedOnBadCursor = true;
                    $cursor = null;
                    $serverTime = null;
                    continue;
                }
                return ['ok' => false, 'error' => $resp['error'], 'stats' => $stats];
            }

            $data = $resp['data'];
            $serverTime ??= (string) ($data['server_time'] ?? '');
            $stats['pages']++;

            foreach ((array) ($data['users'] ?? []) as $u) {
                if (is_array($u)) {
                    self::upsert($u, $stats);
                }
            }

            if (empty($data['has_more'])) {
                break;
            }
            $cursor = (string) ($data['next_cursor'] ?? '');
            if ($cursor === '') {
                return ['ok' => false, 'error' => 'API แจ้ง has_more แต่ไม่ส่ง next_cursor มา', 'stats' => $stats];
            }
        }

        if ($serverTime === '') {
            return ['ok' => false, 'error' => 'API ไม่ส่ง server_time มา', 'stats' => $stats];
        }
        return ['ok' => true, 'server_time' => $serverTime, 'stats' => $stats];
    }

    /**
     * One GET to the users endpoint, with bounded retry on network errors / 5xx.
     * @return array{ok:bool,data?:array,error?:string,code?:string}
     */
    private static function request(array $params): array
    {
        $url  = ONE_RVC_USERS_URL . '?' . http_build_query($params);   // never the secret — that goes in Basic auth
        $last = ['ok' => false, 'error' => 'ไม่สามารถติดต่อ ONE-RVC ได้'];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 1) {
                sleep(2 ** ($attempt - 2));   // 1s, 2s
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPGET        => true,
                CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
                CURLOPT_USERPWD        => ONE_RVC_CLIENT_ID . ':' . self::secret(),
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            ] + SsoAuth::resolvePin(ONE_RVC_USERS_URL));
            $body   = curl_exec($ch);
            $errno  = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($errno !== 0 || $body === false) {
                $last = ['ok' => false, 'error' => 'ไม่สามารถติดต่อ ONE-RVC ได้ (curl error ' . $errno . ')'];
                continue;
            }
            $data = json_decode((string) $body, true);

            if ($status >= 500) {
                $last = ['ok' => false, 'error' => 'ONE-RVC ตอบกลับ HTTP ' . $status];
                continue;
            }
            if (!is_array($data)) {
                return ['ok' => false, 'error' => 'ONE-RVC ตอบกลับไม่ใช่ JSON (HTTP ' . $status . ')'];
            }
            if ($status !== 200 || empty($data['ok'])) {
                $code = (string) ($data['error'] ?? 'http_' . $status);
                $msg  = (string) ($data['message'] ?? '');
                return ['ok' => false, 'code' => $code,
                        'error' => self::errorLabel($code) . ' (HTTP ' . $status . ', ' . $code . ($msg !== '' ? ': ' . $msg : '') . ')'];
            }
            return ['ok' => true, 'data' => $data];
        }
        return $last;
    }

    private static function errorLabel(string $code): string
    {
        return match ($code) {
            'invalid_client'   => 'client_id / client_secret ไม่ถูกต้อง หรือแอปถูกปิดใช้งาน',
            'sync_disabled'    => 'ผู้ดูแล ONE-RVC ยังไม่ได้เปิดสิทธิ์ API ซิงค์ข้อมูลผู้ใช้ให้แอปนี้',
            'type_not_allowed' => 'แอปนี้ไม่ได้รับอนุญาตให้ดึงผู้ใช้ประเภทที่ขอ',
            'bad_type', 'bad_since', 'bad_cursor' => 'พารามิเตอร์ไม่ถูกต้อง',
            default            => 'ONE-RVC ปฏิเสธคำขอ',
        };
    }

    /** Idempotent upsert of one directory entry, plus email auto-link. */
    private static function upsert(array $u, array &$stats): void
    {
        $id = trim((string) ($u['id'] ?? ''));
        if ($id === '') {
            return;
        }
        $str = static fn (string $k, int $max): ?string =>
            (isset($u[$k]) && trim((string) $u[$k]) !== '') ? mb_substr(trim((string) $u[$k]), 0, $max) : null;

        $status    = ($u['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
        $updatedAt = null;
        if (!empty($u['updated_at'])) {
            try {
                $updatedAt = (new DateTimeImmutable((string) $u['updated_at']))
                    ->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $updatedAt = null;
            }
        }
        $row = [
            $id, $str('first_name', 150), $str('last_name', 150), $str('department', 200),
            $str('user_type', 20), $status, $str('email', 150), $str('google_email', 150),
            $str('username', 100), $updatedAt,
        ];

        $pdo    = Database::pdo();
        $exists = $pdo->prepare('SELECT 1 FROM oa_users WHERE oa_user_id = ?');
        $exists->execute([$id]);
        $isNew = $exists->fetchColumn() === false;

        // Optional fields keep their previous value when this response omits them
        // (the admin can narrow the app's field permissions upstream at any time).
        $pdo->prepare(
            'INSERT INTO oa_users (oa_user_id, first_name, last_name, department, user_type, status,
                                   email, google_email, username, remote_updated_at, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name), last_name = VALUES(last_name),
                department = VALUES(department), user_type = VALUES(user_type), status = VALUES(status),
                email = COALESCE(VALUES(email), email), google_email = COALESCE(VALUES(google_email), google_email),
                username = COALESCE(VALUES(username), username),
                remote_updated_at = VALUES(remote_updated_at), synced_at = NOW()'
        )->execute($row);

        $stats[$isNew ? 'added' : 'updated']++;
        if ($status === 'suspended') {
            $stats['suspended']++;
        }

        // Same auto-link rule as SsoAuth::resolveLogin: an existing, *unlinked* local
        // account with a matching email, and only if no other account holds this id.
        if (SsoAuth::findByExternalId($id) !== null) {
            return;
        }
        foreach (array_unique(array_filter([$str('email', 150), $str('google_email', 150)])) as $email) {
            $candidate = Auth::findByEmail($email);
            if ($candidate && empty($candidate['sso_user_id'])) {
                if (SsoAuth::link((int) $candidate['id'], $id)['ok']) {
                    $stats['linked']++;
                }
                return;
            }
        }
    }
}
