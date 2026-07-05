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
             WHERE status = 'upcoming' AND reported_at IS NULL
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
                'title' => 'ใช้งาน ' . $ea['ai_name'] . ' ได้เลยล่วงหน้า',
                'detail' => $ea['dateLabel'] . ' · ' . $ea['slotLabel'] . ' · ช่วงก่อนหน้าว่าง',
                'url' => url('student/dashboard.php'),
            ];
        }

        foreach (Booking::listForUser($userId, 'upcoming') as $b) {
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

        return $items;
    }
}
