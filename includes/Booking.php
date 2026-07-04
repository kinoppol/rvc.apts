<?php

final class Booking
{
    private const THAI_WEEKDAYS = ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'];
    private const THAI_WEEKDAYS_SHORT = ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'];
    private const THAI_MONTHS_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    private const CLS_MAP = ['available' => 'slt slt-avail', 'busy' => 'slt slt-busy', 'mine' => 'slt slt-mine', 'now' => 'slt slt-now', 'off' => 'slt slt-off'];
    private const ICON_MAP = ['available' => 'bi bi-sun', 'busy' => 'bi bi-person-fill', 'mine' => 'bi bi-check-circle-fill', 'now' => 'bi bi-circle-fill', 'off' => 'bi bi-tools'];
    private const TEXT_MAP = ['available' => 'ว่าง', 'busy' => 'จองแล้ว', 'mine' => 'ของฉัน', 'now' => 'ใช้งานอยู่', 'off' => 'ปิด'];

    /** Days a student has, after a slot ends, to file the usage report before booking is blocked. */
    public const REPORT_DEADLINE_DAYS = 7;
    private const REPORT_ALLOWED = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];

    public static function thaiDate(DateTimeInterface $d): string
    {
        return $d->format('j') . ' ' . self::THAI_MONTHS_SHORT[(int) $d->format('n')] . ' ' . ((int) $d->format('Y') + 543);
    }

    /** Wall-clock time of $dt expressed on $bookingDate's 30-hour clock (25:00 = 1 AM next calendar day). */
    private static function thirtyHour(string $bookingDate, string $dt): string
    {
        $base = new DateTimeImmutable($bookingDate . ' 00:00:00');
        $minutes = intdiv((new DateTimeImmutable($dt))->getTimestamp() - $base->getTimestamp(), 60);
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
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
        $settings = self::limitsFor($userId);
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
    public static function create(int $userId, string $dateStr, int $slotIndex, string $purpose = ''): array
    {
        if (self::isRestricted($userId)) {
            return ['ok' => false, 'error' => 'บัญชีของคุณถูกระงับการจองชั่วคราว เนื่องจากมีรายงานการใช้งานค้างเกิน ' . self::REPORT_DEADLINE_DAYS . ' วัน กรุณารายงานการใช้งานที่ค้างให้ครบก่อน'];
        }

        $purpose = trim($purpose);
        if ($purpose === '') {
            return ['ok' => false, 'error' => 'กรุณาระบุวัตถุประสงค์การใช้งาน'];
        }
        $purpose = mb_substr($purpose, 0, 500);

        $settings = self::limitsFor($userId);
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
                'INSERT INTO bookings (user_id, ai_account_id, booking_date, slot_index, start_datetime, end_datetime, status, purpose)
                 VALUES (?, ?, ?, ?, ?, ?, \'upcoming\', ?)'
            );
            $insert->execute([$userId, $accountId, $dateStr, $slotIndex, $slotStart->format('Y-m-d H:i:s'), $slotEnd->format('Y-m-d H:i:s'), $purpose]);

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
        $now = new DateTimeImmutable();
        foreach ($rows as &$row) {
            $status = self::displayStatus($row);
            $start = new DateTimeImmutable($row['start_datetime']);
            $end = new DateTimeImmutable($row['end_datetime']);
            $row['displayStatus'] = $status;
            $row['badgeCls'] = $badgeMap[$status];
            $row['statusLabel'] = $labelMap[$status];
            // Date/time shown on the booking's "business day" using the 30-hour clock, so a slot that
            // runs past midnight (e.g. 25:00–30:00) stays on its start day instead of a next-day date.
            $bookingDate = new DateTimeImmutable($row['booking_date']);
            $row['dateLabel'] = self::THAI_WEEKDAYS_SHORT[$bookingDate->format('N') - 1] . '. ' . self::thaiDate($bookingDate);
            $row['slotLabel'] = SlotSettings::slotLabel((int) $row['slot_index'])
                . ' (' . self::thirtyHour($row['booking_date'], $row['start_datetime'])
                . '–' . self::thirtyHour($row['booking_date'], $row['end_datetime']) . ')';
            $row['canCancel'] = $status === 'upcoming';

            // Post-use report state (only completed, non-cancelled bookings need one)
            $reported = !empty($row['reported_at']);
            $deadline = $end->modify('+' . self::REPORT_DEADLINE_DAYS . ' days');
            $row['reported'] = $reported;
            $row['needsReport'] = $status === 'completed' && !$reported;
            $row['reportOverdue'] = $row['needsReport'] && $now > $deadline;
            $row['reportDeadlineLabel'] = self::thaiDate($deadline);
            $daysLeft = (int) (new DateTimeImmutable($now->format('Y-m-d')))->diff(new DateTimeImmutable($deadline->format('Y-m-d')))->format('%r%a');
            $row['reportDaysLeft'] = $daysLeft;
            if ($reported) {
                $row['reportStatusText'] = 'รายงานแล้ว';
            } elseif ($row['needsReport']) {
                $row['reportStatusText'] = $row['reportOverdue'] ? 'เกินกำหนดรายงาน ' . abs($daysLeft) . ' วัน' : 'ต้องรายงานภายใน ' . $daysLeft . ' วัน';
            } else {
                $row['reportStatusText'] = '';
            }
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
        $settings = self::limitsFor($userId);
        return max(0, $settings['weekly_quota'] - self::quotaUsed($userId, new DateTimeImmutable('today')));
    }

    /**
     * Effective booking limits for a user: the global slot_settings, with the user's group
     * overriding weekly_quota / max_advance_days when those group columns are not NULL.
     */
    public static function limitsFor(int $userId): array
    {
        $settings = SlotSettings::get();
        $stmt = Database::pdo()->prepare(
            'SELECT g.weekly_quota, g.max_advance_days FROM users u
             JOIN user_groups g ON g.id = u.group_id WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        $group = $stmt->fetch();
        if ($group) {
            if ($group['weekly_quota'] !== null) {
                $settings['weekly_quota'] = (int) $group['weekly_quota'];
            }
            if ($group['max_advance_days'] !== null) {
                $settings['max_advance_days'] = (int) $group['max_advance_days'];
            }
        }
        return $settings;
    }

    /** @return array<int,array> Completed bookings the user still has to report on. */
    public static function pendingReportsForUser(int $userId): array
    {
        return array_values(array_filter(self::listForUser($userId), fn ($r) => $r['needsReport']));
    }

    /** Count of completed bookings whose report is past the 7-day deadline and still missing. */
    public static function overdueCountForUser(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM bookings
             WHERE user_id = ? AND status = 'upcoming' AND reported_at IS NULL
               AND end_datetime < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$userId, self::REPORT_DEADLINE_DAYS]);
        return (int) $stmt->fetchColumn();
    }

    /** A user with any overdue unreported booking is temporarily blocked from making new bookings. */
    public static function isRestricted(int $userId): bool
    {
        return self::overdueCountForUser($userId) > 0;
    }

    /**
     * Student submits the post-use report (free text and/or an image/PDF file).
     * @param array|null $file A single $_FILES entry, or null when no file was attached.
     * @return array{ok:bool,error?:string}
     */
    public static function submitReport(int $userId, int $bookingId, string $text, ?array $file): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM bookings WHERE id = ? AND user_id = ?');
        $stmt->execute([$bookingId, $userId]);
        $b = $stmt->fetch();
        if (!$b) {
            return ['ok' => false, 'error' => 'ไม่พบรายการจอง'];
        }
        if ($b['status'] === 'cancelled') {
            return ['ok' => false, 'error' => 'รายการนี้ถูกยกเลิกแล้ว ไม่ต้องรายงาน'];
        }
        if (new DateTimeImmutable() < new DateTimeImmutable($b['end_datetime'])) {
            return ['ok' => false, 'error' => 'ยังใช้งานไม่เสร็จ ยังไม่ต้องรายงาน'];
        }
        if (!empty($b['reported_at'])) {
            return ['ok' => false, 'error' => 'รายการนี้รายงานไปแล้ว'];
        }

        $text = trim($text);
        $hasFile = $file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK;
        if ($text === '' && !$hasFile) {
            return ['ok' => false, 'error' => 'กรุณากรอกรายละเอียดการใช้งาน หรือแนบไฟล์อย่างน้อยหนึ่งอย่าง'];
        }

        $storedFile = null;
        if ($hasFile) {
            $res = self::storeReportFile($file, $bookingId);
            if (!$res['ok']) {
                return $res;
            }
            $storedFile = $res['file'];
        }

        $upd = Database::pdo()->prepare('UPDATE bookings SET report_text = ?, report_file = ?, reported_at = NOW() WHERE id = ?');
        $upd->execute([$text !== '' ? mb_substr($text, 0, 2000) : null, $storedFile, $bookingId]);
        return ['ok' => true];
    }

    /** Validates and moves an uploaded report file into uploads/reports/. @return array{ok:bool,error?:string,file?:string} */
    private static function storeReportFile(array $file, int $bookingId): array
    {
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'ไฟล์มีขนาดใหญ่เกิน 5 MB'];
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!isset(self::REPORT_ALLOWED[$ext])) {
            return ['ok' => false, 'error' => 'รองรับเฉพาะไฟล์รูปภาพ (JPG/PNG/GIF/WEBP) หรือ PDF'];
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($mime !== self::REPORT_ALLOWED[$ext]) {
            return ['ok' => false, 'error' => 'ชนิดไฟล์ไม่ตรงกับนามสกุลไฟล์'];
        }
        $dir = __DIR__ . '/../uploads/reports';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = 'report_' . $bookingId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'error' => 'อัปโหลดไฟล์ไม่สำเร็จ'];
        }
        return ['ok' => true, 'file' => $name];
    }

    /** Admin clears a user's pending reports (lifts the temporary block). @return int rows waived */
    public static function waiveOverdueForUser(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE bookings SET reported_at = NOW(), report_text = COALESCE(report_text, 'ยกเว้นโดยผู้ดูแลระบบ')
             WHERE user_id = ? AND status = 'upcoming' AND reported_at IS NULL AND end_datetime < NOW()"
        );
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
}
