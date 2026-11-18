<?php

/**
 * Direct messages an admin sends to a member. Unlike the derived notification bell
 * (see Notification.php), these are real, stored content — one row per message in
 * `admin_messages`. A message is "unread" while read_at IS NULL; opening
 * student/messages.php marks the member's messages read.
 */
final class AdminMessage
{
    private const SUBJECT_MAX = 200;
    private const BODY_MAX    = 5000;

    /** @return array{ok:bool,error?:string} */
    public static function send(int $adminId, int $userId, string $subject, string $body): array
    {
        $v = self::validate($subject, $body);
        if (!$v['ok']) {
            return $v;
        }
        if (!self::isMember($userId)) {
            return ['ok' => false, 'error' => 'ไม่พบสมาชิกผู้รับ'];
        }
        Database::pdo()->prepare(
            "INSERT INTO admin_messages (user_id, admin_id, subject, body) VALUES (?, ?, ?, ?)"
        )->execute([$userId, $adminId, $v['subject'], $v['body']]);
        return ['ok' => true];
    }

    /**
     * Sends the same message to every listed member in one transaction.
     * @param int[] $userIds
     * @return array{ok:bool,error?:string,count?:int}
     */
    public static function sendToMany(int $adminId, array $userIds, string $subject, string $body): array
    {
        $v = self::validate($subject, $body);
        if (!$v['ok']) {
            return $v;
        }
        $ids = array_values(array_filter(array_unique(array_map('intval', $userIds)), fn ($id) => $id > 0));
        if (!$ids) {
            return ['ok' => false, 'error' => 'กรุณาเลือกสมาชิกอย่างน้อย 1 คน'];
        }
        $pdo = Database::pdo();
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        // Keep only ids that are real members.
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('student','teacher') AND id IN ({$ph})");
        $stmt->execute($ids);
        $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$valid) {
            return ['ok' => false, 'error' => 'ไม่พบสมาชิกผู้รับ'];
        }
        $ins = $pdo->prepare("INSERT INTO admin_messages (user_id, admin_id, subject, body) VALUES (?, ?, ?, ?)");
        $pdo->beginTransaction();
        foreach ($valid as $uid) {
            $ins->execute([$uid, $adminId, $v['subject'], $v['body']]);
        }
        $pdo->commit();
        return ['ok' => true, 'count' => count($valid)];
    }

    /** @return array{ok:bool,error?:string,subject?:string,body?:string} */
    private static function validate(string $subject, string $body): array
    {
        $subject = trim($subject);
        $body    = trim($body);
        if ($subject === '' || $body === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกหัวข้อและเนื้อหาข้อความ'];
        }
        if (mb_strlen($subject) > self::SUBJECT_MAX) {
            return ['ok' => false, 'error' => 'หัวข้อยาวเกินกำหนด'];
        }
        if (mb_strlen($body) > self::BODY_MAX) {
            return ['ok' => false, 'error' => 'เนื้อหาข้อความยาวเกินกำหนด'];
        }
        return ['ok' => true, 'subject' => $subject, 'body' => $body];
    }

    private static function isMember(int $userId): bool
    {
        $stmt = Database::pdo()->prepare("SELECT 1 FROM users WHERE id = ? AND role IN ('student','teacher')");
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** All messages for a member, newest first, with the sender's name joined in. */
    public static function forUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT m.*, a.name AS admin_name FROM admin_messages m
             LEFT JOIN users a ON a.id = m.admin_id
             WHERE m.user_id = ? ORDER BY m.created_at DESC, m.id DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function unreadCountForUser(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM admin_messages WHERE user_id = ? AND read_at IS NULL"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markAllRead(int $userId): void
    {
        Database::pdo()->prepare(
            "UPDATE admin_messages SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL"
        )->execute([$userId]);
    }
}
