<?php

final class SlotSettings
{
    /** @return array{id:int,slot_hours:int,slots_per_day:int,weekly_quota:int,max_advance_days:int,day_start_time:string,allow_current_slot:int} */
    public static function get(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM slot_settings WHERE id = 1');
        return $stmt->fetch();
    }

    /** True when admins have opened booking for the slot that is currently in progress. */
    public static function allowsCurrentSlot(?array $settings = null): bool
    {
        $settings ??= self::get();
        return !empty($settings['allow_current_slot']);
    }

    /**
     * True when admins have switched on "high demand" mode to spread limited capacity across more
     * students: no early access, no back-to-back slots, and a hard daily booking cap
     * (see Booking::HIGH_DEMAND_DAILY_LIMIT and the early-access gates in Booking).
     */
    public static function isHighDemandMode(?array $settings = null): bool
    {
        $settings ??= self::get();
        return !empty($settings['high_demand_mode']);
    }

    /**
     * The individually toggleable high-demand rules: rule key => slot_settings column.
     * no_early = no early access · no_adjacent = no back-to-back slots on one day ·
     * daily_limit = Booking::HIGH_DEMAND_DAILY_LIMIT distinct slots per day · banner = warning on student/booking.php.
     */
    public const HIGH_DEMAND_RULES = [
        'no_early'    => 'hd_no_early',
        'no_adjacent' => 'hd_no_adjacent',
        'daily_limit' => 'hd_daily_limit',
        'banner'      => 'hd_show_banner',
    ];

    /** True when one high-demand rule is in force: either its own switch is on, or the master mode (which enables every rule). */
    public static function highDemandRule(string $rule, ?array $settings = null): bool
    {
        $settings ??= self::get();
        return self::isHighDemandMode($settings) || !empty($settings[self::HIGH_DEMAND_RULES[$rule]]);
    }

    /** @return array{ok:bool,error?:string} */
    public static function update(int $slotHours, int $slotsPerDay, int $weeklyQuota, int $maxAdvanceDays, string $dayStartTime, bool $allowCurrentSlot = false, bool $highDemandMode = false, array $highDemandRules = []): array
    {
        if ($slotHours < 1 || $slotsPerDay < 1 || $weeklyQuota < 1 || $maxAdvanceDays < 1) {
            return ['ok' => false, 'error' => 'ค่าที่กรอกต้องเป็นจำนวนเต็มบวก'];
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', trim($dayStartTime), $m)) {
            return ['ok' => false, 'error' => 'รูปแบบเวลาเริ่มต้นของวันไม่ถูกต้อง (HH:MM)'];
        }
        // Slots may run past midnight (shown on a 30-hour "business day" clock: 24:00–30:00),
        // but the last slot must end by 30:00 so late-night slots still belong to the start day.
        $startMinutes = (int) $m[1] * 60 + (int) $m[2];
        if ($startMinutes + $slotHours * $slotsPerDay * 60 > 30 * 60) {
            return ['ok' => false, 'error' => 'เวลาเริ่มต้น + (ความยาวช่วงเวลา × จำนวน slots/วัน) ต้องไม่เกิน 30:00 น. (ระบบเวลา 30 ชั่วโมง)'];
        }

        // Column names come from the HIGH_DEMAND_RULES constant, never from input.
        $ruleSql = '';
        $ruleVals = [];
        foreach (self::HIGH_DEMAND_RULES as $rule => $column) {
            $ruleSql .= ', ' . $column . ' = ?';
            $ruleVals[] = !empty($highDemandRules[$rule]) ? 1 : 0;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE slot_settings SET slot_hours = ?, slots_per_day = ?, weekly_quota = ?, max_advance_days = ?, day_start_time = ?, allow_current_slot = ?, high_demand_mode = ?' . $ruleSql . ' WHERE id = 1'
        );
        $stmt->execute(array_merge([$slotHours, $slotsPerDay, $weeklyQuota, $maxAdvanceDays, $m[1] . ':' . $m[2] . ':00', $allowCurrentSlot ? 1 : 0, $highDemandMode ? 1 : 0], $ruleVals));

        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function updateInstitutionName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อสถานศึกษา'];
        }
        if (mb_strlen($name) > 200) {
            return ['ok' => false, 'error' => 'ชื่อสถานศึกษายาวเกินไป (สูงสุด 200 ตัวอักษร)'];
        }
        Database::pdo()->prepare('UPDATE slot_settings SET institution_name = ? WHERE id = 1')->execute([$name]);
        return ['ok' => true];
    }

    /** Admin-configured private-IP override for the ONE-RVC verify-token call, or null if unset. */
    public static function getSsoVerifyIp(): ?string
    {
        $row = Database::pdo()->query('SELECT sso_verify_ip FROM slot_settings WHERE id = 1')->fetch();
        return ($row && $row['sso_verify_ip'] !== null && $row['sso_verify_ip'] !== '') ? $row['sso_verify_ip'] : null;
    }

    /** @return array{ok:bool,error?:string} */
    public static function updateSsoVerifyIp(string $ip): array
    {
        $ip = trim($ip);
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'error' => 'รูปแบบ IP ไม่ถูกต้อง'];
        }
        Database::pdo()->prepare('UPDATE slot_settings SET sso_verify_ip = ? WHERE id = 1')
            ->execute([$ip !== '' ? $ip : null]);
        return ['ok' => true];
    }

    public static function getTermsFile(): ?string
    {
        $row = Database::pdo()->query('SELECT terms_file FROM slot_settings WHERE id = 1')->fetch();
        return ($row && $row['terms_file'] !== null && $row['terms_file'] !== '') ? $row['terms_file'] : null;
    }

    public static function updateTermsFile(string $filename): void
    {
        Database::pdo()->prepare('UPDATE slot_settings SET terms_file = ? WHERE id = 1')->execute([$filename]);
    }

    public static function deleteTermsFile(): void
    {
        Database::pdo()->prepare('UPDATE slot_settings SET terms_file = NULL WHERE id = 1')->execute();
    }

    /** Thai labels for the first few slots; falls back to a generic label beyond that. */
    public static function slotLabel(int $index): string
    {
        $labels = ['เช้า', 'บ่าย', 'เย็น', 'ดึก'];
        return $labels[$index] ?? ('ช่วงที่ ' . ($index + 1));
    }

    /**
     * Slot boundary as a 30-hour "business day" clock string (e.g. 25:00 = 1 AM the next calendar day).
     * Times that cross midnight keep counting up (24:00, 25:00, ...) so a late-night slot stays on its
     * start day instead of showing a confusing next-day date. Passing the returned "HH:MM" to
     * DateTimeImmutable::setTime() still resolves to the correct absolute timestamp (setTime rolls over).
     */
    public static function slotStart(array $settings, int $index): string
    {
        return self::fmtMinutes(self::dayStartMinutes($settings) + $index * $settings['slot_hours'] * 60);
    }

    public static function slotEnd(array $settings, int $index): string
    {
        return self::fmtMinutes(self::dayStartMinutes($settings) + ($index + 1) * $settings['slot_hours'] * 60);
    }

    private static function dayStartMinutes(array $settings): int
    {
        [$h, $m] = array_map('intval', explode(':', $settings['day_start_time']));
        return $h * 60 + $m;
    }

    private static function fmtMinutes(int $totalMinutes): string
    {
        return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }
}
