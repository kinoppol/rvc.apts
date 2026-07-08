<?php

final class Subject
{
    /** Active subjects for teacher registration/profile dropdowns. */
    public static function listActive(): array
    {
        return Database::pdo()
            ->query("SELECT id, name FROM subjects WHERE is_active = 1 ORDER BY sort_order, name")
            ->fetchAll();
    }

    /** All subjects for admin management. */
    public static function listAll(): array
    {
        return Database::pdo()
            ->query("SELECT s.*, (SELECT COUNT(*) FROM users WHERE subject_id = s.id) AS user_count
                     FROM subjects s ORDER BY s.sort_order, s.name")
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find an existing subject by name (case-sensitive) or insert a new one.
     * Used when a teacher types a subject that isn't in the list during registration.
     */
    public static function addAndGetId(string $name): int
    {
        $name = trim($name);
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }
        $pdo->prepare(
            "INSERT INTO subjects (name, is_active, sort_order)
             VALUES (?, 1, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM subjects s2))"
        )->execute([$name]);
        return (int) $pdo->lastInsertId();
    }

    /** @return array{ok:bool,error?:string} */
    public static function add(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณาระบุชื่อวิชาสอน'];
        }
        try {
            $stmt = Database::pdo()->prepare(
                "INSERT INTO subjects (name, sort_order) VALUES (?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM subjects s2))"
            );
            $stmt->execute([$name]);
            return ['ok' => true];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'ชื่อวิชาสอนนี้มีอยู่แล้ว'];
            }
            throw $e;
        }
    }

    /** @return array{ok:bool,error?:string} */
    public static function update(int $id, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณาระบุชื่อวิชาสอน'];
        }
        try {
            Database::pdo()->prepare("UPDATE subjects SET name = ? WHERE id = ?")->execute([$name, $id]);
            return ['ok' => true];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'ชื่อวิชาสอนนี้มีอยู่แล้ว'];
            }
            throw $e;
        }
    }

    public static function toggleActive(int $id): void
    {
        Database::pdo()->prepare("UPDATE subjects SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    }

    /** @return array{ok:bool,error?:string} */
    public static function delete(int $id): array
    {
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM users WHERE subject_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'ไม่สามารถลบได้ เนื่องจากมีครูที่ใช้วิชาสอนนี้อยู่'];
        }
        Database::pdo()->prepare("DELETE FROM subjects WHERE id = ?")->execute([$id]);
        return ['ok' => true];
    }
}
