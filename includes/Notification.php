<?php

/**
 * Builds the in-app notification list for the header bell. Everything is derived live from the
 * same data the app already stores (no notifications table) — admins see AI-account expiry /
 * password-reminder / pending-approval alerts; students see their soon-upcoming bookings.
 */
final class Notification
{
    /** @return array<int,array{level:string,icon:string,title:string,detail:string,url:string}> */
    public static function forUser(array $user): array
    {
        return $user['role'] === 'admin'
            ? self::forAdmin()
            : self::forStudent((int) $user['id']);
    }

    private static function forAdmin(): array
    {
        $items = [];

        $pending = Member::pendingCount();
        if ($pending > 0) {
            $items[] = [
                'level' => 'info',
                'icon' => 'bi-person-plus',
                'title' => "มีสมาชิกรออนุมัติ {$pending} คน",
                'detail' => 'แตะเพื่อตรวจสอบและอนุมัติ',
                'url' => url('admin/members.php') . '?status=pending',
            ];
        }

        $restrictedCount = (int) Database::pdo()->query(
            "SELECT COUNT(DISTINCT user_id) FROM bookings
             WHERE status = 'upcoming' AND reported_at IS NULL AND checked_in_at IS NOT NULL
               AND end_datetime < DATE_SUB(NOW(), INTERVAL " . Booking::REPORT_DEADLINE_DAYS . " DAY)"
        )->fetchColumn();
        if ($restrictedCount > 0) {
            $items[] = [
                'level' => 'err',
                'icon' => 'bi-slash-circle',
                'title' => "สมาชิกถูกระงับการจอง {$restrictedCount} คน",
                'detail' => 'มีรายงานการใช้งานค้างเกินกำหนด',
                'url' => url('admin/members.php'),
            ];
        }

        $issueCount = (int) Database::pdo()->query("SELECT COUNT(*) FROM bookings WHERE issue_text IS NOT NULL")->fetchColumn();
        if ($issueCount > 0) {
            $items[] = [
                'level' => 'warn',
                'icon' => 'bi-bug',
                'title' => "มีการแจ้งปัญหาการใช้งาน {$issueCount} รายการ",
                'detail' => 'ผู้ใช้งานพบปัญหาระหว่างใช้ AI · ตรวจสอบในจัดการการจอง',
                'url' => url('admin/bookings.php'),
            ];
        }

        $aiUrl = url('admin/ai-accounts.php');
        foreach (AiAccount::listWithUsage() as $ac) {
            if ($ac['isExpired']) {
                $items[] = [
                    'level' => 'err',
                    'icon' => 'bi-calendar-x',
                    'title' => $ac['name'] . ' หมดอายุแล้ว',
                    'detail' => 'ถูกปิดใช้งานอัตโนมัติ · หมดอายุ ' . $ac['expiresLabel'],
                    'url' => $aiUrl,
                ];
            } elseif (!empty($ac['expiryWarn'])) {
                $items[] = [
                    'level' => 'warn',
                    'icon' => 'bi-calendar-x',
                    'title' => $ac['name'] . ' ใกล้หมดอายุ',
                    'detail' => $ac['expiryText'] . ' · ' . $ac['expiresLabel'],
                    'url' => $aiUrl,
                ];
            }

            if (!empty($ac['pwdReminderOn']) && !empty($ac['pwdWarn'])) {
                $items[] = [
                    'level' => 'warn',
                    'icon' => 'bi-shield-lock',
                    'title' => $ac['name'] . ' ถึงกำหนดเปลี่ยนรหัสผ่าน',
                    'detail' => $ac['reminderLabel'] . ' · ' . $ac['pwdText'],
                    'url' => $aiUrl,
                ];
            }
        }

        return array_merge($items, self::lmsForAdmin());
    }

    /**
     * LMS items for the admin bell.
     *
     * Both are shaped so the admin's own next action makes the condition false —
     * unlike the issue counter above, which counts every issue ever reported and so
     * never clears. Count unhandled work, not all work.
     *
     * @return array<int,array{level:string,icon:string,title:string,detail:string,url:string}>
     */
    private static function lmsForAdmin(): array
    {
        try {
            return self::lmsForAdminItems();
        } catch (PDOException $e) {
            // Deploy resets the working tree before running migrations, so for a few
            // seconds the LMS tables may not exist yet. The bell is not worth a fatal.
            return [];
        }
    }

    private static function lmsForAdminItems(): array
    {
        $items = [];
        $published = LmsLevel::published();
        if (!$published) {
            return $items;  // LMS not in use on this install — one cached query and out
        }

        $pending = LmsPromotion::pendingCount();
        if ($pending > 0) {
            $items[] = [
                'level' => 'info',
                'icon' => 'bi-patch-question',
                'title' => "มีคำขอเลื่อนระดับรอตรวจ {$pending} รายการ",
                'detail' => 'ตรวจภารกิจพิสูจน์ทักษะเพื่ออนุมัติการย้ายกลุ่ม',
                'url' => url('admin/lms-promotions.php') . '?status=pending',
            ];
        }

        // A published level whose bank is too small silently blocks students from
        // starting that quiz at all, so it is worth surfacing.
        foreach ($published as $lv) {
            $lid = (int) $lv['id'];
            $rd  = LmsLevel::readiness($lid);
            if ($rd['preOk'] && $rd['postOk'] && !$rd['topicsMissingReview']) {
                continue;
            }
            $why = [];
            if (!$rd['preOk'])  { $why[] = 'ก่อนเรียน ' . $rd['preCount'] . '/' . (int) $lv['pre_question_count']; }
            if (!$rd['postOk']) { $why[] = 'หลังเรียน ' . $rd['postCount'] . '/' . (int) $lv['post_question_count']; }
            if ($rd['topicsMissingReview']) { $why[] = count($rd['topicsMissingReview']) . ' หัวข้อยังไม่ครบ 3 ข้อ'; }

            $items[] = [
                'level' => 'warn',
                'icon' => 'bi-exclamation-diamond',
                'title' => $lv['title'] . ': คลังคำถามไม่พอ',
                'detail' => implode(' · ', $why),
                'url' => url('admin/lms-questions.php') . '?level=' . $lid . '&phase=pre',
            ];
        }

        return $items;
    }

    private static function forStudent(int $userId): array
    {
        $items = [];
        $now = new DateTimeImmutable();

        foreach (Booking::earlyAccessForUser($userId) as $ea) {
            $items[] = [
                'level' => 'ok',
                'icon' => 'bi-lightning-charge-fill',
                'title' => $ea['hasCheckedIn']
                    ? 'ใช้งาน ' . $ea['ai_name'] . ' ล่วงหน้าได้เลย'
                    : 'เช็คอินเพื่อใช้ ' . $ea['ai_name'] . ' ล่วงหน้า',
                'detail' => $ea['dateLabel'] . ' · ' . $ea['slotLabel'] . ' · ช่วงก่อนหน้าว่าง',
                'url' => url('student/my-bookings.php'),
            ];
        }

        foreach (Booking::listForUser($userId, 'upcoming') as $b) {
            // Check-in reminder
            if ($b['canCheckIn']) {
                $items[] = [
                    'level' => 'warn',
                    'icon' => 'bi-qr-code-scan',
                    'title' => 'กรุณาเช็คอินยืนยันการใช้งาน',
                    'detail' => $b['dateLabel'] . ' · ' . $b['slotLabel'] . ' · ' . $b['ai_name'],
                    'url' => url('student/my-bookings.php'),
                ];
                continue;
            }
            if (in_array($b['displayStatus'], ['checked_in', 'now', 'no_show'])) {
                continue; // no bell noise for already-handled states
            }
            $start = new DateTimeImmutable($b['start_datetime']);
            $hours = ($start->getTimestamp() - $now->getTimestamp()) / 3600;
            if ($hours < 0 || $hours > 48) {
                continue;
            }
            $items[] = [
                'level' => $hours <= 24 ? 'warn' : 'info',
                'icon' => 'bi-calendar-check',
                'title' => $hours <= 24 ? 'การจองใกล้ถึงแล้ว' : 'การจองที่กำลังจะมาถึง',
                'detail' => $b['dateLabel'] . ' · ' . $b['slotLabel'] . ' · ' . $b['ai_name'],
                'url' => url('student/my-bookings.php'),
            ];
        }

        foreach (Booking::pendingReportsForUser($userId) as $b) {
            $items[] = [
                'level' => $b['reportOverdue'] ? 'err' : 'warn',
                'icon' => 'bi-journal-text',
                'title' => $b['reportOverdue'] ? 'เกินกำหนดรายงานการใช้งาน' : 'ต้องรายงานการใช้งาน',
                'detail' => $b['dateLabel'] . ' · ' . $b['reportStatusText'],
                'url' => url('student/my-bookings.php'),
            ];
        }

        return array_merge($items, self::lmsForStudent($userId));
    }

    /**
     * LMS items for the student bell, all read from the per-request-cached
     * LmsProgress::forUser(), so a page that already loaded progress pays nothing extra.
     *
     * Every item clears itself through the student's own next action. The single
     * exception is the approval notice, which has no follow-up action to perform —
     * it is time-bounded to 7 days instead, so it cannot become permanent.
     *
     * @return array<int,array{level:string,icon:string,title:string,detail:string,url:string}>
     */
    private static function lmsForStudent(int $userId): array
    {
        try {
            return self::lmsForStudentItems($userId);
        } catch (PDOException $e) {
            return [];  // same deploy-window guard as lmsForAdmin()
        }
    }

    private static function lmsForStudentItems(int $userId): array
    {
        $items = [];
        if (!LmsLevel::published()) {
            return $items;
        }

        $data     = LmsProgress::forUser($userId);
        $now      = new DateTimeImmutable();
        $announced = false;  // only ever nudge about one newly-opened level

        // An attempt left open blocks a fresh draw, so it needs to be finished.
        foreach ($data['openAttempts'] as $a) {
            $started = new DateTimeImmutable((string) $a['created_at']);
            if (($now->getTimestamp() - $started->getTimestamp()) < 1800) {
                continue;  // still plausibly mid-quiz
            }
            $items[] = [
                'level' => 'warn',
                'icon' => 'bi-hourglass-split',
                'title' => 'มีแบบทดสอบที่ยังทำค้างอยู่',
                'detail' => 'ต้องส่งคำตอบชุดเดิมก่อนจึงจะเริ่มชุดใหม่ได้',
                'url' => url('student/lms-quiz.php') . '?attempt=' . (int) $a['id'],
            ];
        }

        foreach (LmsLevel::published() as $lv) {
            $lid = (int) $lv['id'];
            $st  = $data['levels'][$lid] ?? null;
            if (!$st || !$st['unlocked']) {
                continue;
            }

            if (!$announced && $st['topicsDone'] === 0 && $st['preTries'] === 0 && $st['postTries'] === 0) {
                $items[] = [
                    'level' => 'info',
                    'icon' => 'bi-unlock',
                    'title' => 'ระดับ ' . $lv['title'] . ' พร้อมให้เรียนแล้ว',
                    'detail' => (string) ($lv['subtitle'] ?? 'เริ่มเรียนได้เลย'),
                    'url' => url('student/lms-level.php') . '?id=' . $lid,
                ];
                $announced = true;
            }

            if ($st['canRequestPromotion']) {
                $items[] = [
                    'level' => 'ok',
                    'icon' => 'bi-send',
                    'title' => 'ผ่านแบบทดสอบท้ายระดับแล้ว — ส่งภารกิจเพื่อเลื่อนกลุ่ม',
                    'detail' => $lv['title'] . ' · ส่งผลงานให้ผู้ดูแลตรวจ',
                    'url' => url('student/lms-promotion.php') . '?level=' . $lid,
                ];
            }

            $p = $st['promotion'];
            if (!$p) {
                continue;
            }
            if ($p['status'] === 'revise' || $p['status'] === 'rejected') {
                $items[] = [
                    'level' => $p['status'] === 'revise' ? 'warn' : 'err',
                    'icon' => 'bi-arrow-counterclockwise',
                    'title' => $p['status'] === 'revise' ? 'ผู้ดูแลขอให้แก้ไขภารกิจ' : 'ภารกิจยังไม่ผ่าน',
                    'detail' => $lv['title'] . ' · แตะเพื่อดูความเห็นและส่งใหม่',
                    'url' => url('student/lms-promotion.php') . '?level=' . $lid,
                ];
            } elseif ($p['status'] === 'approved' && !empty($p['reviewed_at'])) {
                // Time-bounded: there is no action that would clear this one.
                $reviewed = new DateTimeImmutable((string) $p['reviewed_at']);
                if (($now->getTimestamp() - $reviewed->getTimestamp()) <= 7 * 86400) {
                    $items[] = [
                        'level' => 'ok',
                        'icon' => 'bi-patch-check-fill',
                        'title' => 'ภารกิจได้รับการอนุมัติแล้ว',
                        'detail' => $lv['title'] . ' · ย้ายไปกลุ่ม ' . ($p['granted_group_name'] ?? '') . ' · สิทธิ์การจองอัปเดตแล้ว',
                        'url' => url('student/lms-promotion.php') . '?level=' . $lid,
                    ];
                }
            }
        }

        return $items;
    }
}
