<?php

final class Auth
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.*, g.name AS group_name FROM users u
             LEFT JOIN user_groups g ON g.id = u.group_id WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.*, g.name AS group_name FROM users u
             LEFT JOIN user_groups g ON g.id = u.group_id WHERE u.email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** @return array{ok:bool,error?:string,user?:array} */
    public static function attempt(string $email, string $password): array
    {
        $user = self::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'];
        }
        if ($user['status'] === 'pending') {
            return ['ok' => false, 'error' => 'บัญชีของคุณยังรอการอนุมัติจากผู้ดูแลระบบ'];
        }
        if ($user['status'] === 'suspended') {
            return ['ok' => false, 'error' => 'บัญชีของคุณถูกระงับสิทธิ์การใช้งาน'];
        }
        return ['ok' => true, 'user' => $user];
    }

    /** @return array{ok:bool,error?:string} */
    public static function register(array $data): array
    {
        $role     = in_array($data['role'] ?? '', ['student', 'teacher'], true) ? $data['role'] : 'student';
        $name     = trim($data['name'] ?? '');
        $staffId  = trim($data['student_id'] ?? '');
        $email    = trim($data['email'] ?? '');
        $phone    = trim($data['phone'] ?? '') ?: null;
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';
        $majorId   = (int) ($data['major_id'] ?? 0);
        $subjectId = (int) ($data['subject_id'] ?? 0);

        $needsId = $role === 'student';
        if ($name === '' || ($needsId && $staffId === '') || $email === '' || $password === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน'];
        }
        if ($role === 'student' && $majorId === 0) {
            return ['ok' => false, 'error' => 'กรุณาเลือกสาขาวิชา'];
        }
        if ($role === 'teacher' && $subjectId === 0) {
            return ['ok' => false, 'error' => 'กรุณาเลือกวิชาสอน'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'รูปแบบอีเมลไม่ถูกต้อง'];
        }
        if ($password !== $passwordConfirm) {
            return ['ok' => false, 'error' => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'];
        }
        if (self::findByEmail($email)) {
            return ['ok' => false, 'error' => 'อีเมลนี้ถูกใช้สมัครสมาชิกแล้ว'];
        }
        if ($staffId !== '') {
            $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE student_id = ?');
            $stmt->execute([$staffId]);
            if ($stmt->fetch()) {
                $label = $role === 'teacher' ? 'รหัสพนักงาน' : 'รหัสนักศึกษา';
                return ['ok' => false, 'error' => $label . 'นี้ถูกใช้สมัครสมาชิกแล้ว'];
            }
        }

        // Resolve display name and validate FK is active
        if ($role === 'student') {
            $majorRow = Major::find($majorId);
            if (!$majorRow || !$majorRow['is_active']) {
                return ['ok' => false, 'error' => 'สาขาวิชาที่เลือกไม่ถูกต้องหรือปิดใช้งานแล้ว'];
            }
            $majorName = $majorRow['name'];
            $subjectId = null;
        } else {
            $subjectRow = Subject::find($subjectId);
            if (!$subjectRow || !$subjectRow['is_active']) {
                return ['ok' => false, 'error' => 'วิชาสอนที่เลือกไม่ถูกต้องหรือปิดใช้งานแล้ว'];
            }
            $majorName  = $subjectRow['name'];   // keep major column in sync for backward compat
            $majorId    = null;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (role, name, student_id, major, major_id, subject_id, email, phone, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')'
        );
        $stmt->execute([
            $role, $name, $staffId ?: null, $majorName,
            $majorId, $subjectId,
            $email, $phone, password_hash($password, PASSWORD_DEFAULT),
        ]);

        return ['ok' => true];
    }

    /** Update major (student) or subject (teacher) from profile page. @return array{ok:bool,error?:string} */
    public static function updateMajorOrSubject(int $userId, string $role, int $itemId): array
    {
        if ($role === 'student') {
            $row = Major::find($itemId);
            if (!$row || !$row['is_active']) {
                return ['ok' => false, 'error' => 'สาขาวิชาไม่ถูกต้อง'];
            }
            Database::pdo()->prepare("UPDATE users SET major_id = ?, major = ? WHERE id = ?")
                ->execute([$itemId, $row['name'], $userId]);
        } else {
            $row = Subject::find($itemId);
            if (!$row || !$row['is_active']) {
                return ['ok' => false, 'error' => 'วิชาสอนไม่ถูกต้อง'];
            }
            Database::pdo()->prepare("UPDATE users SET subject_id = ?, major = ? WHERE id = ?")
                ->execute([$itemId, $row['name'], $userId]);
        }
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function updateProfile(int $userId, string $name, string $email, ?string $phone): array
    {
        $name = trim($name);
        $email = trim($email);
        $phone = $phone !== null ? trim($phone) : null;

        if ($name === '' || $email === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อและอีเมล'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'รูปแบบอีเมลไม่ถูกต้อง'];
        }

        $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว'];
        }

        $stmt = Database::pdo()->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
        $stmt->execute([$name, $email, $phone ?: null, $userId]);

        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function changePassword(int $userId, string $current, string $new, string $newConfirm): array
    {
        $user = self::findById($userId);
        if (!$user || !password_verify($current, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'];
        }
        if ($new !== $newConfirm) {
            return ['ok' => false, 'error' => 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน'];
        }
        if (strlen($new) < 8) {
            return ['ok' => false, 'error' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร'];
        }

        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

        return ['ok' => true];
    }
}
