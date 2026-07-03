<?php

final class Member
{
    private const BADGE_MAP = ['approved' => 'badge-ok', 'pending' => 'badge-pend', 'suspended' => 'badge-susp'];
    private const LABEL_MAP = ['approved' => 'อนุมัติแล้ว', 'pending' => 'รออนุมัติ', 'suspended' => 'ระงับสิทธิ์'];

    private static function hoursFor(int $userId): int
    {
        $settings = SlotSettings::get();
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'upcoming' AND end_datetime < NOW()"
        );
        $stmt->execute([$userId]);
        return ((int) $stmt->fetchColumn()) * $settings['slot_hours'];
    }

    private static function decorate(array $row): array
    {
        $row['initial'] = mb_substr($row['name'], 0, 1);
        $row['badgeCls'] = self::BADGE_MAP[$row['status']];
        $row['statusLabel'] = self::LABEL_MAP[$row['status']];
        $row['isPending'] = $row['status'] === 'pending';
        $row['isApproved'] = $row['status'] === 'approved';
        $row['isSuspended'] = $row['status'] === 'suspended';
        $row['hours'] = self::hoursFor((int) $row['id']);
        $created = new DateTimeImmutable($row['created_at']);
        $row['joinDate'] = Booking::thaiDate($created);
        return $row;
    }

    /** @return array{rows:array,total:int,totalMembers:int,approvedCount:int,pendingCount:int,suspendedCount:int} */
    public static function list(string $search, string $status, int $page, int $perPage): array
    {
        $pdo = Database::pdo();

        $counts = $pdo->query("SELECT status, COUNT(*) c FROM users WHERE role = 'student' GROUP BY status")->fetchAll();
        $countMap = ['approved' => 0, 'pending' => 0, 'suspended' => 0];
        foreach ($counts as $c) {
            $countMap[$c['status']] = (int) $c['c'];
        }
        $totalMembers = array_sum($countMap);

        $where = ["role = 'student'"];
        $params = [];
        if ($status !== 'all') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(name LIKE ? OR student_id LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $whereSql = implode(' AND ', $where);

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$whereSql}");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $pdo->prepare(
            "SELECT * FROM users WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $rows = array_map([self::class, 'decorate'], $stmt->fetchAll());

        return [
            'rows' => $rows,
            'total' => $total,
            'totalMembers' => $totalMembers,
            'approvedCount' => $countMap['approved'],
            'pendingCount' => $countMap['pending'],
            'suspendedCount' => $countMap['suspended'],
        ];
    }

    public static function pendingCount(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'pending'")->fetchColumn();
    }

    public static function pending(int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM users WHERE role = 'student' AND status = 'pending' ORDER BY created_at ASC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([self::class, 'decorate'], $stmt->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? self::decorate($row) : null;
    }

    public static function approve(int $id): void
    {
        Database::pdo()->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'student'")->execute([$id]);
    }

    public static function reject(int $id): void
    {
        Database::pdo()->prepare("DELETE FROM users WHERE id = ? AND role = 'student' AND status = 'pending'")->execute([$id]);
    }

    public static function suspend(int $id): void
    {
        Database::pdo()->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'student'")->execute([$id]);
    }

    public static function activate(int $id): void
    {
        Database::pdo()->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'student'")->execute([$id]);
    }

    /** Only suspended members may be hard-deleted, mirroring the prototype's member table actions. */
    public static function delete(int $id): void
    {
        Database::pdo()->prepare("DELETE FROM users WHERE id = ? AND role = 'student' AND status = 'suspended'")->execute([$id]);
    }

    /** @return array{ok:bool,error?:string} */
    public static function add(array $data): array
    {
        $name = trim($data['name'] ?? '');
        $studentId = trim($data['student_id'] ?? '');
        $major = trim($data['major'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '') ?: null;
        $password = $data['password'] ?? '';

        if ($name === '' || $studentId === '' || $major === '' || $email === '' || $password === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'รูปแบบอีเมลไม่ถูกต้อง'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'];
        }
        if (Auth::findByEmail($email)) {
            return ['ok' => false, 'error' => 'อีเมลนี้ถูกใช้งานแล้ว'];
        }

        $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE student_id = ?');
        $stmt->execute([$studentId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'รหัสนักศึกษานี้ถูกใช้งานแล้ว'];
        }

        $stmt = Database::pdo()->prepare(
            "INSERT INTO users (role, name, student_id, major, email, phone, password_hash, status)
             VALUES ('student', ?, ?, ?, ?, ?, ?, 'approved')"
        );
        $stmt->execute([$name, $studentId, $major, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);

        return ['ok' => true];
    }
}
