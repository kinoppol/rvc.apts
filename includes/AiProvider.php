<?php

/** Admin-managed list of AI account types (Claude Pro, ChatGPT Plus, ...). */
final class AiProvider
{
    /** @return array<int,array{id:int,name:string,usage:int}> All types with how many accounts use each. */
    public static function listWithUsage(): array
    {
        $sql = 'SELECT p.id, p.name, COUNT(a.id) AS usage_count
                FROM ai_providers p
                LEFT JOIN ai_accounts a ON a.provider_id = p.id
                GROUP BY p.id, p.name
                ORDER BY p.name';
        $rows = Database::pdo()->query($sql)->fetchAll();
        return array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'usage' => (int) $r['usage_count'],
        ], $rows);
    }

    /** @return array<int,array{id:int,name:string}> */
    public static function all(): array
    {
        return Database::pdo()->query('SELECT id, name FROM ai_providers ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ai_providers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function add(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อประเภท'];
        }
        if (self::nameExists($name, null)) {
            return ['ok' => false, 'error' => 'มีประเภทนี้อยู่แล้ว'];
        }
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO ai_providers (name) VALUES (?)')->execute([$name]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /** @return array{ok:bool,error?:string} */
    public static function rename(int $id, string $name): array
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
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE ai_providers SET name = ? WHERE id = ?')->execute([$name, $id]);
        // keep the denormalized copy on ai_accounts in sync
        $pdo->prepare('UPDATE ai_accounts SET provider = ? WHERE provider_id = ?')->execute([$name, $id]);
        return ['ok' => true];
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
