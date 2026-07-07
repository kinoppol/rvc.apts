<?php

final class Major
{
    /** Active majors for registration/profile dropdowns. */
    public static function listActive(): array
    {
        return Database::pdo()
            ->query("SELECT id, name FROM majors WHERE is_active = 1 ORDER BY sort_order, name")
            ->fetchAll();
    }

    /** All majors for admin management. */
    public static function listAll(): array
    {
        return Database::pdo()
            ->query("SELECT m.*, (SELECT COUNT(*) FROM users WHERE major_id = m.id) AS user_count
                     FROM majors m ORDER BY m.sort_order, m.name")
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM majors WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{ok:bool,error?:string} */
    public static function add(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณาระบุชื่อสาขาวิชา'];
        }
        try {
            $stmt = Database::pdo()->prepare(
                "INSERT INTO majors (name, sort_order) VALUES (?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM majors m2))"
            );
            $stmt->execute([$name]);
            return ['ok' => true];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'ชื่อสาขาวิชานี้มีอยู่แล้ว'];
            }
            throw $e;
        }
    }

    /** @return array{ok:bool,error?:string} */
    public static function update(int $id, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณาระบุชื่อสาขาวิชา'];
        }
        try {
            Database::pdo()->prepare("UPDATE majors SET name = ? WHERE id = ?")->execute([$name, $id]);
            return ['ok' => true];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'ชื่อสาขาวิชานี้มีอยู่แล้ว'];
            }
            throw $e;
        }
    }

    public static function toggleActive(int $id): void
    {
        Database::pdo()->prepare("UPDATE majors SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    }

    /** @return array{ok:bool,error?:string} */
    public static function delete(int $id): array
    {
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM users WHERE major_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'ไม่สามารถลบได้ เนื่องจากมีสมาชิกที่ใช้สาขาวิชานี้อยู่'];
        }
        Database::pdo()->prepare("DELETE FROM majors WHERE id = ?")->execute([$id]);
        return ['ok' => true];
    }
}
