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
        $row['isTeacher'] = $row['role'] === 'teacher';
        $row['roleLabel'] = $row['role'] === 'teacher' ? 'ครูผู้สอน' : 'นักศึกษา';
        // Prefer the FK-joined name; fall back to the legacy varchar column for old rows
        if ($row['isTeacher']) {
            $row['displayMajor'] = $row['subject_name_db'] ?? $row['major'] ?? '—';
        } else {
            $row['displayMajor'] = $row['major_name_db'] ?? $row['major'] ?? '—';
        }
        $row['hours'] = self::hoursFor((int) $row['id']);
        $created = new DateTimeImmutable($row['created_at']);
        $row['joinDate'] = Booking::thaiDate($created);
        $row['groupName'] = $row['group_name'] ?? null;
        $row['overdueReports'] = Booking::overdueCountForUser((int) $row['id']);
        $row['restricted'] = $row['overdueReports'] > 0;
        return $row;
    }

    /** @return array{rows:array,total:int,totalMembers:int,approvedCount:int,pendingCount:int,suspendedCount:int} */
    public static function list(string $search, string $status, int $page, int $perPage, string $sort = 'created_at', string $dir = 'desc'): array
    {
        $sortMap = [
            'name'       => 'u.name',
            'student_id' => 'u.student_id',
            'status'     => 'u.status',
            'created_at' => 'u.created_at',
        ];
        $orderCol = $sortMap[$sort] ?? 'u.created_at';
        $orderDir = $dir === 'asc' ? 'ASC' : 'DESC';

        $pdo = Database::pdo();

        $counts = $pdo->query("SELECT status, COUNT(*) c FROM users WHERE role IN ('student','teacher') GROUP BY status")->fetchAll();
        $countMap = ['approved' => 0, 'pending' => 0, 'suspended' => 0];
        foreach ($counts as $c) {
            $countMap[$c['status']] = (int) $c['c'];
        }
        $totalMembers = array_sum($countMap);

        $where = ["u.role IN ('student','teacher')"];
        $params = [];
        if ($status !== 'all') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(u.name LIKE ? OR u.student_id LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        $whereSql = implode(' AND ', $where);

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $stmt = $pdo->prepare(
            "SELECT u.*, g.name AS group_name, mj.name AS major_name_db, sj.name AS subject_name_db FROM users u
             LEFT JOIN user_groups g  ON g.id  = u.group_id
             LEFT JOIN majors     mj  ON mj.id = u.major_id
             LEFT JOIN subjects   sj  ON sj.id = u.subject_id
             WHERE {$whereSql} ORDER BY {$orderCol} {$orderDir} LIMIT {$perPage} OFFSET {$offset}"
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
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM users WHERE role IN ('student','teacher') AND status = 'pending'")->fetchColumn();
    }

    public static function pending(int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT u.*, g.name AS group_name, mj.name AS major_name_db, sj.name AS subject_name_db FROM users u
             LEFT JOIN user_groups g  ON g.id  = u.group_id
             LEFT JOIN majors     mj  ON mj.id = u.major_id
             LEFT JOIN subjects   sj  ON sj.id = u.subject_id
             WHERE u.role IN ('student','teacher') AND u.status = 'pending' ORDER BY u.created_at ASC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([self::class, 'decorate'], $stmt->fetchAll());
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT u.*, g.name AS group_name, mj.name AS major_name_db, sj.name AS subject_name_db FROM users u
             LEFT JOIN user_groups g  ON g.id  = u.group_id
             LEFT JOIN majors     mj  ON mj.id = u.major_id
             LEFT JOIN subjects   sj  ON sj.id = u.subject_id
             WHERE u.id = ? AND u.role IN ('student','teacher')"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? self::decorate($row) : null;
    }

    /** @return array{ok:bool,error?:string} Admin updates a member's email and/or password. Password is optional (empty = no change). */
    public static function updateCredentials(int $id, string $email, string $newPassword): array
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'รูปแบบอีเมลไม่ถูกต้อง'];
        }
        // Uniqueness check — exclude the target user themselves
        $check = Database::pdo()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            return ['ok' => false, 'error' => 'อีเมลนี้ถูกใช้งานโดยผู้ใช้อื่นแล้ว'];
        }

        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                return ['ok' => false, 'error' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'];
            }
            $stmt = Database::pdo()->prepare("UPDATE users SET email = ?, password_hash = ? WHERE id = ? AND role IN ('student','teacher')");
            $stmt->execute([$email, password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = Database::pdo()->prepare("UPDATE users SET email = ? WHERE id = ? AND role IN ('student','teacher')");
            $stmt->execute([$email, $id]);
        }
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} Admin sets a new password for a member. */
    public static function resetPassword(int $id, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'error' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'];
        }
        $stmt = Database::pdo()->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role IN ('student','teacher')");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        return ['ok' => true];
    }

    /** Assigns a member to a group (or clears it when $groupId is null). @return array{ok:bool,error?:string} */
    public static function assignGroup(int $id, ?int $groupId): array
    {
        if ($groupId !== null && !UserGroup::find($groupId)) {
            return ['ok' => false, 'error' => 'ไม่พบกลุ่มที่เลือก'];
        }
        $stmt = Database::pdo()->prepare("UPDATE users SET group_id = ? WHERE id = ? AND role IN ('student','teacher')");
        $stmt->execute([$groupId, $id]);
        return ['ok' => true];
    }

    public static function approve(int $id): void
    {
        Database::pdo()->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role IN ('student','teacher')")->execute([$id]);
    }

    public static function reject(int $id): void
    {
        Database::pdo()->prepare("DELETE FROM users WHERE id = ? AND role IN ('student','teacher') AND status = 'pending'")->execute([$id]);
    }

    public static function suspend(int $id): void
    {
        Database::pdo()->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role IN ('student','teacher')")->execute([$id]);
    }

    public static function activate(int $id): void
    {
        Database::pdo()->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role IN ('student','teacher')")->execute([$id]);
    }

    /** Only suspended members may be hard-deleted, mirroring the prototype's member table actions. */
    public static function delete(int $id): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM bookings WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('student','teacher') AND status = 'suspended'")->execute([$id]);
        $pdo->commit();
    }

    /** @return array{ok:bool,error?:string} Admin adds a student directly (approved immediately). */
    public static function add(array $data): array
    {
        $name      = trim($data['name'] ?? '');
        $studentId = trim($data['student_id'] ?? '');
        $majorId   = (int) ($data['major_id'] ?? 0);
        $email     = trim($data['email'] ?? '');
        $phone     = trim($data['phone'] ?? '') ?: null;
        $password  = $data['password'] ?? '';

        if ($name === '' || $studentId === '' || $majorId === 0 || $email === '' || $password === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน'];
        }
        $majorRow = Major::find($majorId);
        if (!$majorRow) {
            return ['ok' => false, 'error' => 'สาขาวิชาไม่ถูกต้อง'];
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
            "INSERT INTO users (role, name, student_id, major, major_id, email, phone, password_hash, status)
             VALUES ('student', ?, ?, ?, ?, ?, ?, ?, 'approved')"
        );
        $stmt->execute([$name, $studentId, $majorRow['name'], $majorId, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);

        return ['ok' => true];
    }
}
