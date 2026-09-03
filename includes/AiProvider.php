<?php

/** Admin-managed list of AI account types (Claude Pro, ChatGPT Plus, ...). */
final class AiProvider
{
    /** @return array<int,array{id:int,name:string,login_url:?string,usage:int}> All types with how many accounts use each. */
    public static function listWithUsage(): array
    {
        $sql = 'SELECT p.id, p.name, p.login_url, COUNT(a.id) AS usage_count
                FROM ai_providers p
                LEFT JOIN ai_accounts a ON a.provider_id = p.id
                GROUP BY p.id, p.name, p.login_url
                ORDER BY p.name';
        $rows = Database::pdo()->query($sql)->fetchAll();
        return array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'login_url' => $r['login_url'],
            'usage' => (int) $r['usage_count'],
        ], $rows);
    }

    /** @return array<int,array{id:int,name:string,login_url:?string}> */
    public static function all(): array
    {
        return Database::pdo()->query('SELECT id, name, login_url FROM ai_providers ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ai_providers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function add(string $name, string $loginUrl = ''): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อประเภท'];
        }
        if (self::nameExists($name, null)) {
            return ['ok' => false, 'error' => 'มีประเภทนี้อยู่แล้ว'];
        }
        $url = self::normalizeUrl($loginUrl);
        if ($url === false) {
            return ['ok' => false, 'error' => 'ลิงก์หน้าล็อกอินต้องขึ้นต้นด้วย https:// หรือ http://'];
        }
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO ai_providers (name, login_url) VALUES (?, ?)')->execute([$name, $url]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /** @return array{ok:bool,error?:string} */
    public static function rename(int $id, string $name, string $loginUrl = ''): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อประเภท'];
        }
        if (!self::find($id)) {
            return ['ok' => false, 'error' => 'ไม่พบประเภทที่ต้องการแก้ไข'];
        }
        if (self::nameExists($name, $id)) {
            return ['ok' => false, 'error' => 'มีประเภทนี้อยู่แล้ว'];
        }
        $url = self::normalizeUrl($loginUrl);
        if ($url === false) {
            return ['ok' => false, 'error' => 'ลิงก์หน้าล็อกอินต้องขึ้นต้นด้วย https:// หรือ http://'];
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE ai_providers SET name = ?, login_url = ? WHERE id = ?')->execute([$name, $url, $id]);
        // keep the denormalized copy on ai_accounts in sync
        $pdo->prepare('UPDATE ai_accounts SET provider = ? WHERE provider_id = ?')->execute([$name, $id]);
        return ['ok' => true];
    }

    /**
     * The "open the provider's login page" button shown next to the credentials on a
     * check-in card, or '' when this type has no URL set.
     *
     * A view helper in the same spirit as Csrf::field() — it lives here so the escaping
     * and the http(s) allow-list stay next to each other, and so the three credential
     * cards (dashboard early-access, dashboard next-booking, my-bookings) cannot drift.
     *
     * The URL is re-validated on the way out: a row edited straight in the database
     * still cannot put a javascript: href on a student's page.
     */
    public static function loginButton(?string $url, ?string $providerName = null, bool $compact = false): string
    {
        $safe = self::normalizeUrl((string) $url);
        if ($safe === false || $safe === null) {
            return '';
        }
        // The card header already names the account, so the visible label stays short —
        // provider names run long ("Google Gemini Advanced") and would wrap the row.
        // The full name lives in the tooltip instead.
        $name  = trim((string) $providerName);
        $title = $name !== '' ? 'เปิดหน้าล็อกอิน ' . $name . ' ในแท็บใหม่' : 'เปิดหน้าล็อกอินในแท็บใหม่';
        $pad   = $compact ? '2px 9px' : '4px 11px';
        $fs    = $compact ? '11px' : '12px';

        return '<a href="' . e($safe) . '" target="_blank" rel="noopener noreferrer"'
            . ' title="' . e($title) . '"'
            . ' style="display:inline-flex;align-items:center;gap:5px;white-space:nowrap;text-decoration:none;'
            . 'background:#059669;color:#fff;border-radius:6px;padding:' . $pad . ';font-size:' . $fs . ';font-weight:600">'
            . '<i class="bi bi-box-arrow-up-right"></i>เปิดหน้าล็อกอิน</a>';
    }

    /**
     * '' => NULL (no button shown); a plain http(s) URL => that URL; anything else => false.
     *
     * The allow-list matters: this value ends up in an href on the student credential
     * card, so javascript: and data: must never survive.
     *
     * @return string|null|false
     */
    private static function normalizeUrl(string $raw): string|null|false
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (mb_strlen($raw) > 255 || !preg_match('#^https?://[^\s<>"\']+$#i', $raw)) {
            return false;
        }
        return $raw;
    }

    /** @return array{ok:bool,error?:string} Blocks deletion while any account still uses the type. */
    public static function delete(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM ai_accounts WHERE provider_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'ลบไม่ได้ เนื่องจากมีบัญชี AI ใช้ประเภทนี้อยู่ กรุณาย้ายบัญชีไปประเภทอื่นก่อน'];
        }
        Database::pdo()->prepare('DELETE FROM ai_providers WHERE id = ?')->execute([$id]);
        return ['ok' => true];
    }

    private static function nameExists(string $name, ?int $exceptId): bool
    {
        $sql = 'SELECT COUNT(*) FROM ai_providers WHERE name = ?';
        $params = [$name];
        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }
}
