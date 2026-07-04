<?php

final class Booking
{
    private const THAI_WEEKDAYS = ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'];
    private const THAI_WEEKDAYS_SHORT = ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'];
    private const THAI_MONTHS_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    private const CLS_MAP = ['available' => 'slt slt-avail', 'busy' => 'slt slt-busy', 'mine' => 'slt slt-mine', 'now' => 'slt slt-now', 'off' => 'slt slt-off'];
    private const ICON_MAP = ['available' => 'bi bi-sun', 'busy' => 'bi bi-person-fill', 'mine' => 'bi bi-check-circle-fill', 'now' => 'bi bi-circle-fill', 'off' => 'bi bi-tools'];
    private const TEXT_MAP = ['available' => 'ว่าง', 'busy' => 'จองแล้ว', 'mine' => 'ของฉัน', 'now' => 'ใช้งานอยู่', 'off' => 'ปิด'];

    public static function thaiDate(DateTimeInterface $d): string
    {
        return $d->format('j') . ' ' . self::THAI_MONTHS_SHORT[(int) $d->format('n')] . ' ' . ((int) $d->format('Y') + 543);
    }

    private static function weekStart(int $weekOffset): DateTimeImmutable
    {
        $today = new DateTimeImmutable('today');
        $dow = (int) $today->format('N'); // 1 (Mon) .. 7 (Sun)
        return $today->modify('-' . ($dow - 1) . ' days')->modify(($weekOffset >= 0 ? '+' : '') . ($weekOffset * 7) . ' days');
    }

    public static function getWeekLabel(int $weekOffset): string
    {
        $start = self::weekStart($weekOffset);
        $end = $start->modify('+6 days');
        return self::thaiDate($start) . ' – ' . self::thaiDate($end);
    }

    private static function activeAccountCount(): int
    {
        static $count = null;
        if ($count === null) {
            $count = (int) Database::pdo()->query(
                "SELECT COUNT(*) FROM ai_accounts WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())"
            )->fetchColumn();
        }
        return $count;
    }

    /**
     * Builds the 7-day x N-slot calendar grid for a student, mirroring the prototype's
     * generateWeekSlots() but backed by real bookings.
     */
    public static function getWeekGrid(int $userId, int $weekOffset): array
    {
        $settings = SlotSettings::get();
        $start = self::weekStart($weekOffset);
        $today = new DateTimeImmutable('today');
        $now = new DateTimeImmutable();
        $maxDate = $today->modify('+' . $settings['max_advance_days'] . ' days');
        $activeAccounts = self::activeAccountCount();

        $pdo = Database::pdo();
        $bookedStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings b JOIN ai_accounts a ON a.id = b.ai_account_id
             WHERE b.booking_date = ? AND b.slot_index = ? AND b.status = 'upcoming' AND a.status = 'active'
               AND (a.expires_at IS NULL OR a.expires_at > NOW())"
        );
        $mineStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND booking_date = ? AND slot_index = ? AND status = 'upcoming'"
        );

        $days = [];
        for ($d = 0; $d < 7; $d++) {
            $date = $start->modify("+{$d} days");
            $dateStr = $date->format('Y-m-d');
            $slots = [];
            for ($i = 0; $i < $settings['slots_per_day']; $i++) {
                $slotStart = $date->setTime(...array_map('intval', explode(':', SlotSettings::slotStart($settings, $i))));
                $slotEnd = $date->setTime(...array_map('intval', explode(':', SlotSettings::slotEnd($settings, $i))));

                $bookedStmt->execute([$dateStr, $i]);
                $booked = (int) $bookedStmt->fetchColumn();
                $mineStmt->execute([$userId, $dateStr, $i]);
                $mine = (int) $mineStmt->fetchColumn();

                $isLiveNow = $now >= $slotStart && $now < $slotEnd;
                $isPast = $now >= $slotEnd;

                if ($mine > 0 && $isLiveNow) {
                    $status = 'now';
                } elseif ($mine > 0) {
                    $status = 'mine';
                } elseif ($isPast) {
                    $status = 'off';
                } elseif ($date > $maxDate) {
                    $status = 'off';
                } elseif ($isLiveNow) {
                    $status = 'busy';
                } elseif ($activeAccounts === 0 || $booked >= $activeAccounts) {
                    $status = 'busy';
                } else {
                    $status = 'available';
                }

                $slots[] = [
                    'cls' => self::CLS_MAP[$status],
                    'iconCls' => self::ICON_MAP[$status],
                    'label' => SlotSettings::slotLabel($i),
                    'time' => SlotSettings::slotStart($settings, $i) . '–' . SlotSettings::slotEnd($settings, $i),
                    'statusText' => self::TEXT_MAP[$status],
                    'bookable' => $status === 'available',
                    'date' => $dateStr,
                    'dateLabel' => self::THAI_WEEKDAYS[$d] . 'ที่ ' . $date->format('j'),
                    'slotIndex' => $i,
                ];
            }

            $days[] = [
                'dayName' => self::THAI_WEEKDAYS_SHORT[$d],
                'date' => (int) $date->format('j'),
                'todayCls' => $date == $today ? 'day-today' : 'day-normal',
                'slots' => $slots,
            ];
        }

        return $days;
    }

    private static function weekBoundsFor(DateTimeInterface $date): array
    {
        $d = DateTimeImmutable::createFromInterface($date);
        $dow = (int) $d->format('N');
        $start = $d->modify('-' . ($dow - 1) . ' days');
        $end = $start->modify('+6 days');
        return [$start, $end];
    }

    private static function quotaUsed(int $userId, DateTimeInterface $date): int
    {
        [$start, $end] = self::weekBoundsFor($date);
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'upcoming' AND booking_date BETWEEN ? AND ?"
        );
        $stmt->execute([$userId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{ok:bool,error?:string} */
    public static function create(int $userId, string $dateStr, int $slotIndex): array
    {
        $settings = SlotSettings::get();
        try {
            $date = new DateTimeImmutable($dateStr);
        } catch (Exception) {
            return ['ok' => false, 'error' => 'วันที่ไม่ถูกต้อง'];
        }

        $today = new DateTimeImmutable('today');
        $maxDate = $today->modify('+' . $settings['max_advance_days'] . ' days');
        if ($date < $today || $date > $maxDate) {
            return ['ok' => false, 'error' => 'ไม่สามารถจองวันที่นี้ได้'];
        }
        if ($slotIndex < 0 || $slotIndex >= $settings['slots_per_day']) {
            return ['ok' => false, 'error' => 'ช่วงเวลาไม่ถูกต้อง'];
        }

        $slotStart = $date->setTime(...array_map('intval', explode(':', SlotSettings::slotStart($settings, $slotIndex))));
        $slotEnd = $date->setTime(...array_map('intval', explode(':', SlotSettings::slotEnd($settings, $slotIndex))));
        if ($slotStart <= new DateTimeImmutable()) {
            return ['ok' => false, 'error' => 'ไม่สามารถจองช่วงเวลาที่ผ่านไปแล้วหรือกำลังเริ่มได้'];
        }

        if (self::quotaUsed($userId, $date) >= $settings['weekly_quota']) {
            return ['ok' => false, 'error' => 'คุณใช้โควต้าการจองของสัปดาห์นี้ครบแล้ว'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $mineStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND booking_date = ? AND slot_index = ? AND status = 'upcoming'"
            );
            $mineStmt->execute([$userId, $dateStr, $slotIndex]);
            if ((int) $mineStmt->fetchColumn() > 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'คุณจองช่วงเวลานี้ไว้แล้ว'];
            }

            $accountStmt = $pdo->prepare(
                "SELECT a.id FROM ai_accounts a
                 WHERE a.status = 'active'
                   AND (a.expires_at IS NULL OR a.expires_at > NOW())
                   AND a.id NOT IN (
                       SELECT ai_account_id FROM bookings
                       WHERE booking_date = ? AND slot_index = ? AND status = 'upcoming'
                   )
                 ORDER BY a.id LIMIT 1 FOR UPDATE"
            );
            $accountStmt->execute([$dateStr, $slotIndex]);
            $accountId = $accountStmt->fetchColumn();

            if (!$accountId) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'ช่วงเวลานี้ถูกจองเต็มแล้ว กรุณาเลือกช่วงเวลาอื่น'];
            }

            $insert = $pdo->prepare(
                'INSERT INTO bookings (user_id, ai_account_id, booking_date, slot_index, start_datetime, end_datetime, status)
                 VALUES (?, ?, ?, ?, ?, ?, \'upcoming\')'
            );
            $insert->execute([$userId, $accountId, $dateStr, $slotIndex, $slotStart->format('Y-m-d H:i:s'), $slotEnd->format('Y-m-d H:i:s')]);

            $pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'เกิดข้อผิดพลาดในการจอง กรุณาลองใหม่อีกครั้ง'];
        }
    }

    /** @return array{ok:bool,error?:string} */
    public static function cancel(int $userId, int $bookingId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM bookings WHERE id = ? AND user_id = ?');
        $stmt->execute([$bookingId, $userId]);
        $booking = $stmt->fetch();
        if (!$booking) {
            return ['ok' => false, 'error' => 'ไม่พบรายการจอง'];
        }
        if (self::displayStatus($booking) !== 'upcoming') {
            return ['ok' => false, 'error' => 'ไม่สามารถยกเลิกรายการนี้ได้'];
        }

        $upd = Database::pdo()->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
        $upd->execute([$bookingId]);
        return ['ok' => true];
    }

    /** Derives upcoming / now / completed / cancelled from stored status + timestamps. */
    public static function displayStatus(array $booking): string
    {
        if ($booking['status'] === 'cancelled') {
            return 'cancelled';
        }
        $now = new DateTimeImmutable();
        $start = new DateTimeImmutable($booking['start_datetime']);
        $end = new DateTimeImmutable($booking['end_datetime']);
        if ($now >= $end) {
            return 'completed';
        }
        if ($now >= $start) {
            return 'now';
        }
        return 'upcoming';
    }

    private static function attachDisplay(array $rows): array
    {
        $badgeMap = ['upcoming' => 'badge-up', 'now' => 'badge-up', 'completed' => 'badge-ok', 'cancelled' => 'badge-susp'];
        $labelMap = ['upcoming' => 'กำลังจะมาถึง', 'now' => 'กำลังใช้งาน', 'completed' => 'เสร็จสิ้น', 'cancelled' => 'ยกเลิกแล้ว'];
        foreach ($rows as &$row) {
            $status = self::displayStatus($row);
            $start = new DateTimeImmutable($row['start_datetime']);
            $row['displayStatus'] = $status;
            $row['badgeCls'] = $badgeMap[$status];
            $row['statusLabel'] = $labelMap[$status];
            $row['dateLabel'] = self::THAI_WEEKDAYS_SHORT[$start->format('N') - 1] . '. ' . self::thaiDate($start);
            $row['slotLabel'] = SlotSettings::slotLabel((int) $row['slot_index']) . ' (' . substr($row['start_datetime'], 11, 5) . '–' . substr($row['end_datetime'], 11, 5) . ')';
            $row['canCancel'] = $status === 'upcoming';
        }
        return $rows;
    }

    /** @return array<int,array> Bookings for a student, optionally filtered by display status. */
    public static function listForUser(int $userId, ?string $filter = null): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT b.*, a.name AS ai_name FROM bookings b
             JOIN ai_accounts a ON a.id = b.ai_account_id
             WHERE b.user_id = ? ORDER BY b.booking_date DESC, b.slot_index DESC'
        );
        $stmt->execute([$userId]);
        $rows = self::attachDisplay($stmt->fetchAll());

        if ($filter && $filter !== 'all') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['displayStatus'] === $filter));
        }
        return $rows;
    }

    public static function nextUpcomingForUser(int $userId): ?array
    {
        $rows = self::listForUser($userId, 'upcoming');
        if (!$rows) {
            return null;
        }
        usort($rows, fn ($a, $b) => strcmp($a['start_datetime'], $b['start_datetime']));
        return $rows[0];
    }

    public static function weeklyHoursUsed(int $userId): int
    {
        $settings = SlotSettings::get();
        [$start, $end] = self::weekBoundsFor(new DateTimeImmutable('today'));
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'upcoming' AND booking_date BETWEEN ? AND ?"
        );
        $stmt->execute([$userId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
        return ((int) $stmt->fetchColumn()) * $settings['slot_hours'];
    }

    public static function weeklyQuotaRemaining(int $userId): int
    {
        $settings = SlotSettings::get();
        return max(0, $settings['weekly_quota'] - self::quotaUsed($userId, new DateTimeImmutable('today')));
    }
}
