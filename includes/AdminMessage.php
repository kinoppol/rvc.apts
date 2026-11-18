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

    public const SUBDIR   = 'messages';
    private const MAX_FILES = 5;

    /**
     * @param array|null $rawFiles the raw $_FILES['attachments'] multi-file array (or null)
     * @return array{ok:bool,error?:string}
     */
    public static function send(int $adminId, int $userId, string $subject, string $body, ?array $rawFiles = null): array
    {
        $v = self::validate($subject, $body);
        if (!$v['ok']) {
            return $v;
        }
        if (!self::isMember($userId)) {
            return ['ok' => false, 'error' => 'ไม่พบสมาชิกผู้รับ'];
        }
        $stored = self::storeFiles($rawFiles, $adminId);
        if (!$stored['ok']) {
            return $stored;
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            "INSERT INTO admin_messages (user_id, admin_id, subject, body) VALUES (?, ?, ?, ?)"
        )->execute([$userId, $adminId, $v['subject'], $v['body']]);
        self::attachFiles((int) $pdo->lastInsertId(), $stored['files']);
        return ['ok' => true];
    }

    /**
     * Validates and moves message attachments (images + PDF, ≤5 files).
     * @return array{ok:bool,error?:string,files?:array<int,array{filename:string,original_name:string}>}
     */
    private static function storeFiles(?array $rawFiles, int $adminId): array
    {
        $uploads = LmsFile::normalizeMultiple($rawFiles);
        if (!$uploads) {
            return ['ok' => true, 'files' => []];
        }
        if (count($uploads) > self::MAX_FILES) {
            return ['ok' => false, 'error' => 'แนบไฟล์ได้ไม่เกิน ' . self::MAX_FILES . ' ไฟล์'];
        }
        $files = [];
        foreach ($uploads as $u) {
            $res = LmsFile::store($u, self::SUBDIR, 'msg', $adminId, LmsFile::DOC_TYPES);
            if (!$res['ok']) {
                foreach ($files as $f) {   // roll back files already moved
                    LmsFile::remove(self::SUBDIR, $f['filename']);
                }
                return ['ok' => false, 'error' => $res['error'] ?? 'อัปโหลดไฟล์ไม่สำเร็จ'];
            }
            $files[] = ['filename' => $res['file'], 'original_name' => $u['name']];
        }
        return ['ok' => true, 'files' => $files];
    }

    /** @param array<int,array{filename:string,original_name:string}> $files */
    private static function attachFiles(int $messageId, array $files): void
    {
        if (!$files) {
            return;
        }
        $ins = Database::pdo()->prepare(
            "INSERT INTO admin_message_files (message_id, filename, original_name) VALUES (?, ?, ?)"
        );
        foreach ($files as $f) {
            $ins->execute([$messageId, $f['filename'], $f['original_name']]);
        }
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
        $rows = $stmt->fetchAll();
        if ($rows) {
            $ids = array_map(fn ($r) => (int) $r['id'], $rows);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $fstmt = Database::pdo()->prepare(
                "SELECT message_id, filename, original_name FROM admin_message_files
                 WHERE message_id IN ({$ph}) ORDER BY uploaded_at ASC, id ASC"
            );
            $fstmt->execute($ids);
            $byMsg = [];
            foreach ($fstmt->fetchAll() as $f) {
                $byMsg[(int) $f['message_id']][] = $f;
            }
            foreach ($rows as &$r) {
                $r['files'] = $byMsg[(int) $r['id']] ?? [];
            }
            unset($r);
        }
        return $rows;
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
