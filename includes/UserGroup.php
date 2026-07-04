<?php

/**
 * Admin-managed user groups. Each group can override the global booking limits
 * (weekly quota + how far ahead members may book); a NULL column means "use the
 * global slot_settings default". Members inherit the limits of the group they belong to.
 */
final class UserGroup
{
    /** @return array<int,array> All groups with member counts. */
    public static function listWithUsage(): array
    {
        $sql = 'SELECT g.*, COUNT(u.id) AS member_count
                FROM user_groups g
                LEFT JOIN users u ON u.group_id = g.id
                GROUP BY g.id
                ORDER BY g.name';
        return Database::pdo()->query($sql)->fetchAll();
    }

    /** @return array<int,array{id:int,name:string,weekly_quota:?int,max_advance_days:?int}> */
    public static function all(): array
    {
        return Database::pdo()->query('SELECT id, name, weekly_quota, max_advance_days FROM user_groups ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM user_groups WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array{ok:bool,error?:string} */
    public static function save(array $d, ?int $id = null): array
    {
        $name = trim($d['name'] ?? '');
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อกลุ่ม'];
        }
        if (self::nameExists($name, $id)) {
            return ['ok' => false, 'error' => 'มีกลุ่มชื่อนี้อยู่แล้ว'];
        }

        $quota = self::parseLimit($d['weekly_quota'] ?? '');
        if ($quota === false) {
            return ['ok' => false, 'error' => 'โควต้า/สัปดาห์ต้องเป็นจำนวนเต็มบวก หรือเว้นว่างเพื่อใช้ค่าเริ่มต้น'];
        }
        $advance = self::parseLimit($d['max_advance_days'] ?? '');
        if ($advance === false) {
            return ['ok' => false, 'error' => 'จองล่วงหน้าสูงสุดต้องเป็นจำนวนเต็มบวก หรือเว้นว่างเพื่อใช้ค่าเริ่มต้น'];
        }
        $concurrent = max(1, (int) ($d['max_concurrent'] ?? 1));
        $desc = trim($d['description'] ?? '') ?: null;
        $poolIds = array_map('intval', (array) ($d['pool_ids'] ?? []));

        $pdo = Database::pdo();
        if ($id === null) {
            $stmt = $pdo->prepare('INSERT INTO user_groups (name, description, weekly_quota, max_advance_days, max_concurrent) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $desc, $quota, $advance, $concurrent]);
            $id = (int) $pdo->lastInsertId();
        } else {
            if (!self::find($id)) {
                return ['ok' => false, 'error' => 'ไม่พบกลุ่มที่ต้องการแก้ไข'];
            }
            $stmt = $pdo->prepare('UPDATE user_groups SET name = ?, description = ?, weekly_quota = ?, max_advance_days = ?, max_concurrent = ? WHERE id = ?');
            $stmt->execute([$name, $desc, $quota, $advance, $concurrent, $id]);
        }
        self::setAccounts($id, $poolIds);
        return ['ok' => true];
    }

    /** @return int[] AI-account ids this group's members are allowed to book. */
    public static function accountIds(int $groupId): array
    {
        $stmt = Database::pdo()->prepare('SELECT ai_account_id FROM group_ai_accounts WHERE group_id = ?');
        $stmt->execute([$groupId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Replaces a group's allowed-pool set. */
    public static function setAccounts(int $groupId, array $accountIds): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM group_ai_accounts WHERE group_id = ?')->execute([$groupId]);
        $ins = $pdo->prepare('INSERT IGNORE INTO group_ai_accounts (group_id, ai_account_id) VALUES (?, ?)');
        foreach (array_unique(array_filter($accountIds, fn ($x) => (int) $x > 0)) as $aid) {
            $ins->execute([$groupId, (int) $aid]);
        }
    }

    /** Deletes a group; members in it revert to the global default (FK ON DELETE SET NULL). */
    public static function delete(int $id): array
    {
        Database::pdo()->prepare('DELETE FROM user_groups WHERE id = ?')->execute([$id]);
        return ['ok' => true];
    }

    /** '' => NULL (use global); positive int => int; anything else => false (invalid). */
    private static function parseLimit(string $raw): int|null|false
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (!ctype_digit($raw) || (int) $raw < 1) {
            return false;
        }
        return (int) $raw;
    }

    private static function nameExists(string $name, ?int $exceptId): bool
    {
        $sql = 'SELECT COUNT(*) FROM user_groups WHERE name = ?';
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
