<?php

/**
 * Appeals a suspended member files against their suspension, and the admin's review.
 * Status flow: pending → reviewing (optional) → accepted / rejected. A member may have
 * only one open (pending/reviewing) appeal at a time; resolved ones stay as history so
 * they can track every decision.
 */
final class SuspensionAppeal
{
    public const SUBDIR    = 'appeals';
    private const MAX_FILES = 5;
    private const MSG_MAX   = 3000;
    private const RESP_MAX  = 3000;

    public const STATUS_LABELS = [
        'pending'   => 'รอตรวจสอบ',
        'reviewing' => 'กำลังพิจารณา',
        'accepted'  => 'อนุมัติอุทธรณ์',
        'rejected'  => 'ปฏิเสธอุทธรณ์',
    ];

    /** True when the member has an appeal that is still open (not yet decided). */
    public static function hasOpen(int $userId): bool
    {
        try {
            $stmt = Database::pdo()->prepare(
                "SELECT 1 FROM suspension_appeals WHERE user_id = ? AND status IN ('pending','reviewing') LIMIT 1"
            );
            $stmt->execute([$userId]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * A suspended member files an appeal.
     * @return array{ok:bool,error?:string}
     */
    public static function submit(int $userId, string $message, ?array $rawFiles = null): array
    {
        if (!Member::isSuspended($userId)) {
            return ['ok' => false, 'error' => 'ยื่นอุทธรณ์ได้เฉพาะเมื่อบัญชีถูกระงับสิทธิ์เท่านั้น'];
        }
        if (self::hasOpen($userId)) {
            return ['ok' => false, 'error' => 'คุณมีคำอุทธรณ์ที่กำลังรอการพิจารณาอยู่แล้ว กรุณารอผลก่อน'];
        }
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกเหตุผลในการยื่นอุทธรณ์'];
        }
        if (mb_strlen($message) > self::MSG_MAX) {
            return ['ok' => false, 'error' => 'ข้อความยาวเกินกำหนด'];
        }
        // Validate + move attachments first.
        $uploads = LmsFile::normalizeMultiple($rawFiles);
        if (count($uploads) > self::MAX_FILES) {
            return ['ok' => false, 'error' => 'แนบไฟล์ได้ไม่เกิน ' . self::MAX_FILES . ' ไฟล์'];
        }
        $stored = [];
        foreach ($uploads as $u) {
            $res = LmsFile::store($u, self::SUBDIR, 'appeal', $userId, LmsFile::DOC_TYPES);
            if (!$res['ok']) {
                foreach ($stored as $f) {
                    LmsFile::remove(self::SUBDIR, $f['filename']);
                }
                return ['ok' => false, 'error' => $res['error'] ?? 'อัปโหลดไฟล์ไม่สำเร็จ'];
            }
            $stored[] = ['filename' => $res['file'], 'original_name' => $u['name']];
        }
        $pdo = Database::pdo();
        $pdo->prepare("INSERT INTO suspension_appeals (user_id, message) VALUES (?, ?)")
            ->execute([$userId, $message]);
        $appealId = (int) $pdo->lastInsertId();
        if ($stored) {
            $ins = $pdo->prepare("INSERT INTO suspension_appeal_files (appeal_id, filename, original_name) VALUES (?, ?, ?)");
            foreach ($stored as $f) {
                $ins->execute([$appealId, $f['filename'], $f['original_name']]);
            }
        }
        return ['ok' => true];
    }

    /** The member's appeals, newest first, each with its attachments. */
    public static function forUser(int $userId): array
    {
        try {
            $stmt = Database::pdo()->prepare(
                "SELECT a.*, r.name AS reviewer_name FROM suspension_appeals a
                 LEFT JOIN users r ON r.id = a.reviewed_by
                 WHERE a.user_id = ? ORDER BY a.created_at DESC, a.id DESC"
            );
            $stmt->execute([$userId]);
            return self::withFiles($stmt->fetchAll());
        } catch (PDOException $e) {
            return [];
        }
    }

    /** Admin list, optionally filtered by status, newest first. */
    public static function listForAdmin(string $status = 'all'): array
    {
        $where  = '';
        $params = [];
        if (in_array($status, array_keys(self::STATUS_LABELS), true)) {
            $where = 'WHERE a.status = ?';
            $params[] = $status;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT a.*, u.name AS member_name, u.email AS member_email, u.role AS member_role,
                    u.status AS member_status, u.suspended_reason, r.name AS reviewer_name
             FROM suspension_appeals a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN users r ON r.id = a.reviewed_by
             {$where} ORDER BY a.created_at DESC, a.id DESC"
        );
        $stmt->execute($params);
        return self::withFiles($stmt->fetchAll());
    }

    public static function pendingCount(): int
    {
        try {
            return (int) Database::pdo()->query(
                "SELECT COUNT(*) FROM suspension_appeals WHERE status IN ('pending','reviewing')"
            )->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Admin decides an appeal. $status must be accepted / rejected / reviewing.
     * @return array{ok:bool,error?:string}
     */
    public static function review(int $appealId, int $adminId, string $status, string $response): array
    {
        if (!in_array($status, ['reviewing', 'accepted', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'สถานะไม่ถูกต้อง'];
        }
        $response = trim($response);
        if (mb_strlen($response) > self::RESP_MAX) {
            return ['ok' => false, 'error' => 'ข้อความตอบกลับยาวเกินกำหนด'];
        }
        $decided = in_array($status, ['accepted', 'rejected'], true);
        Database::pdo()->prepare(
            "UPDATE suspension_appeals
             SET status = ?, admin_response = ?, reviewed_by = ?, reviewed_at = " . ($decided ? 'NOW()' : 'NULL') . "
             WHERE id = ?"
        )->execute([$status, $response !== '' ? $response : null, $adminId, $appealId]);
        return ['ok' => true];
    }

    public static function find(int $appealId): ?array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM suspension_appeals WHERE id = ?");
        $stmt->execute([$appealId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function withFiles(array $rows): array
    {
        if (!$rows) {
            return $rows;
        }
        $ids = array_map(fn ($r) => (int) $r['id'], $rows);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $fstmt = Database::pdo()->prepare(
            "SELECT appeal_id, filename, original_name FROM suspension_appeal_files
             WHERE appeal_id IN ({$ph}) ORDER BY uploaded_at ASC, id ASC"
        );
        $fstmt->execute($ids);
        $byAppeal = [];
        foreach ($fstmt->fetchAll() as $f) {
            $byAppeal[(int) $f['appeal_id']][] = $f;
        }
        foreach ($rows as &$r) {
            $r['files'] = $byAppeal[(int) $r['id']] ?? [];
        }
        unset($r);
        return $rows;
    }
}
