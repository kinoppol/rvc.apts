<?php

/**
 * ภารกิจพิสูจน์ทักษะ — the skill mission a student submits after passing a level's
 * post-test, and the admin review that moves them into the group configured on
 * lms_levels.promo_group_id.
 *
 * The table is append-only: a "please revise" outcome is answered by inserting a new
 * row, never by editing the old one, so the newest row for (user_id, level_id) is the
 * live request and everything before it is a free audit trail.
 *
 * Approving is the one place the LMS reaches into the booking side of the app: it calls
 * Member::assignGroup(), which changes the student's weekly quota, advance window,
 * max_concurrent and AI-pool access the moment it commits.
 */
final class LmsPromotion
{
    public const MAX_FILES = 5;

    /**
     * @param array $files raw $_FILES['files'] (multi-file shape)
     * @return array{ok:bool,error?:string}
     */
    public static function submit(int $userId, int $levelId, string $missionText, ?array $files): array
    {
        if (is_impersonating()) {
            return ['ok' => false, 'error' => 'ไม่สามารถส่งภารกิจขณะดูในฐานะผู้ใช้อื่น'];
        }

        $level = LmsLevel::find($levelId);
        if (!$level || (int) $level['is_published'] !== 1) {
            return ['ok' => false, 'error' => 'ไม่พบระดับนี้ หรือยังไม่เปิดให้เรียน'];
        }
        if ($level['promo_group_id'] === null) {
            return ['ok' => false, 'error' => 'ระดับนี้ยังไม่ได้กำหนดกลุ่มปลายทาง กรุณาติดต่อผู้ดูแลระบบ'];
        }

        // Every eligibility rule is re-derived here — the UI is never trusted.
        if (!LmsProgress::canRequestPromotion($userId, $levelId)) {
            $latest = self::latestFor($userId, $levelId);
            if ($latest && $latest['status'] === 'pending') {
                return ['ok' => false, 'error' => 'คุณมีคำขอของระดับนี้รอผู้ดูแลตรวจอยู่แล้ว'];
            }
            if ($latest && $latest['status'] === 'approved') {
                return ['ok' => false, 'error' => 'คุณผ่านการเลื่อนระดับนี้ไปแล้ว'];
            }
            return ['ok' => false, 'error' => 'ต้องผ่านแบบทดสอบหลังเรียนของระดับนี้ก่อนจึงจะส่งภารกิจได้'];
        }

        $missionText = trim($missionText);
        if ($missionText === '') {
            return ['ok' => false, 'error' => 'กรุณาอธิบายผลงาน/ภารกิจของคุณ'];
        }

        $entries = LmsFile::normalizeMultiple($files);
        if (count($entries) > self::MAX_FILES) {
            return ['ok' => false, 'error' => 'แนบไฟล์ได้สูงสุด ' . self::MAX_FILES . ' ไฟล์'];
        }

        // Files are moved before the transaction opens (move_uploaded_file is not
        // transactional), so any later failure has to unwind them by hand.
        $stored = [];
        foreach ($entries as $entry) {
            $res = LmsFile::store($entry, 'lms/missions', 'mission', $userId, LmsFile::DOC_TYPES);
            if (!$res['ok']) {
                foreach ($stored as $s) {
                    LmsFile::remove('lms/missions', $s['file']);
                }
                return $res;
            }
            $stored[] = ['file' => $res['file'], 'original' => mb_substr($entry['name'], 0, 255)];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO lms_promotions (user_id, level_id, mission_text) VALUES (?, ?, ?)')
                ->execute([$userId, $levelId, mb_substr($missionText, 0, 5000)]);
            $promotionId = (int) $pdo->lastInsertId();

            $ins = $pdo->prepare('INSERT INTO lms_promotion_files (promotion_id, filename, original_name) VALUES (?, ?, ?)');
            foreach ($stored as $s) {
                $ins->execute([$promotionId, $s['file'], $s['original']]);
            }
            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            foreach ($stored as $s) {
                LmsFile::remove('lms/missions', $s['file']);
            }
            return ['ok' => false, 'error' => 'ส่งภารกิจไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
        }
    }

    /**
     * Admin decision. 'approve' also moves the student into the level's target group.
     * @param string $decision approve | reject | revise
     * @return array{ok:bool,error?:string,group?:string}
     */
    public static function review(int $id, int $adminId, string $decision, string $feedback): array
    {
        if (!in_array($decision, ['approve', 'reject', 'revise'], true)) {
            return ['ok' => false, 'error' => 'คำสั่งไม่ถูกต้อง'];
        }
        $row = self::find($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'ไม่พบคำขอนี้'];
        }
        if ($row['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'คำขอนี้ถูกตรวจสอบไปแล้ว'];
        }

        $feedback = trim($feedback);
        if ($decision !== 'approve' && $feedback === '') {
            return ['ok' => false, 'error' => 'กรุณาระบุเหตุผล/สิ่งที่ต้องแก้ไข'];
        }
        $feedback = $feedback === '' ? null : mb_substr($feedback, 0, 2000);

        $pdo = Database::pdo();

        if ($decision !== 'approve') {
            $status = $decision === 'reject' ? 'rejected' : 'revise';
            $stmt = $pdo->prepare(
                'UPDATE lms_promotions SET status = ?, admin_feedback = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ? AND status = \'pending\''
            );
            $stmt->execute([$status, $feedback, $adminId, $id]);
            if ($stmt->rowCount() !== 1) {
                return ['ok' => false, 'error' => 'คำขอนี้ถูกตรวจสอบไปแล้ว'];
            }
            return ['ok' => true];
        }

        // Read the target group fresh at approval time, not as a submit-time snapshot.
        $level = LmsLevel::find((int) $row['level_id']);
        $groupId = $level['promo_group_id'] ?? null;
        if ($groupId === null) {
            return ['ok' => false, 'error' => 'ยังไม่ได้กำหนดกลุ่มปลายทางของระดับนี้ กรุณาตั้งค่าก่อนอนุมัติ'];
        }
        $groupId = (int) $groupId;

        $pdo->beginTransaction();
        try {
            $assign = Member::assignGroup((int) $row['user_id'], $groupId);
            if (!$assign['ok']) {
                $pdo->rollBack();
                return $assign;
            }

            $stmt = $pdo->prepare(
                'UPDATE lms_promotions
                 SET status = \'approved\', granted_group_id = ?, admin_feedback = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ? AND status = \'pending\''
            );
            $stmt->execute([$groupId, $feedback, $adminId, $id]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'คำขอนี้ถูกตรวจสอบไปแล้ว'];
            }

            $pdo->commit();
            $group = UserGroup::find($groupId);
            return ['ok' => true, 'group' => (string) ($group['name'] ?? '')];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'อนุมัติไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
        }
    }

    /** One request with everything the admin card needs. */
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::adminSelect() . ' WHERE p.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @param string $status one of pending|approved|rejected|revise|all */
    public static function listForAdmin(string $status = 'pending'): array
    {
        $sql = self::adminSelect();
        $params = [];
        if (in_array($status, ['pending', 'approved', 'rejected', 'revise'], true)) {
            $sql .= ' WHERE p.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY p.status = \'pending\' DESC, p.id DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Attachments for a set of requests, grouped in one query — no N+1 on the queue page.
     * @return array<int,array<int,array>> keyed by promotion_id
     */
    public static function filesFor(array $promotionIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $promotionIds)));
        if (!$ids) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM lms_promotion_files WHERE promotion_id IN ({$ph}) ORDER BY id"
        );
        $stmt->execute($ids);

        $out = [];
        foreach ($stmt->fetchAll() as $f) {
            $out[(int) $f['promotion_id']][] = $f;
        }
        return $out;
    }

    /** A student's own submissions, newest first. */
    public static function historyFor(int $userId, ?int $levelId = null): array
    {
        $sql = 'SELECT p.*, l.title AS level_title, g.name AS granted_group_name, a.name AS reviewer_name
                FROM lms_promotions p
                JOIN lms_levels l ON l.id = p.level_id
                LEFT JOIN user_groups g ON g.id = p.granted_group_id
                LEFT JOIN users a ON a.id = p.reviewed_by
                WHERE p.user_id = ?';
        $params = [$userId];
        if ($levelId !== null) {
            $sql .= ' AND p.level_id = ?';
            $params[] = $levelId;
        }
        $sql .= ' ORDER BY p.id DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** The live request for one (user, level), i.e. the newest row. */
    public static function latestFor(int $userId, int $levelId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM lms_promotions WHERE user_id = ? AND level_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $levelId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Runs on every admin page render (the sidebar badge), so it must survive the
     * brief window during a deploy where the new code is live but `php migrate.php`
     * has not created the LMS tables yet.
     */
    public static function pendingCount(): int
    {
        try {
            return (int) Database::pdo()
                ->query('SELECT COUNT(*) FROM lms_promotions WHERE status = \'pending\'')
                ->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    private static function adminSelect(): string
    {
        return 'SELECT p.*,
                       u.name AS user_name, u.email AS user_email, u.role AS user_role,
                       ug.name AS current_group_name,
                       l.title AS level_title, l.pass_percent, l.accent_color,
                       tg.name AS target_group_name,
                       gg.name AS granted_group_name,
                       (SELECT MAX(ROUND(a.correct_count * 100 / a.question_count))
                          FROM lms_attempts a
                         WHERE a.user_id = p.user_id AND a.level_id = p.level_id
                           AND a.phase = \'post\' AND a.submitted_at IS NOT NULL AND a.question_count > 0
                       ) AS best_post
                FROM lms_promotions p
                JOIN users u ON u.id = p.user_id
                JOIN lms_levels l ON l.id = p.level_id
                LEFT JOIN user_groups ug ON ug.id = u.group_id
                LEFT JOIN user_groups tg ON tg.id = l.promo_group_id
                LEFT JOIN user_groups gg ON gg.id = p.granted_group_id';
    }
}
