<?php

final class SlotSettings
{
    /** @return array{id:int,slot_hours:int,slots_per_day:int,weekly_quota:int,max_advance_days:int,day_start_time:string} */
    public static function get(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM slot_settings WHERE id = 1');
        return $stmt->fetch();
    }

    /** @return array{ok:bool,error?:string} */
    public static function update(int $slotHours, int $slotsPerDay, int $weeklyQuota, int $maxAdvanceDays, string $dayStartTime): array
    {
        if ($slotHours < 1 || $slotsPerDay < 1 || $weeklyQuota < 1 || $maxAdvanceDays < 1) {
            return ['ok' => false, 'error' => 'ค่าที่กรอกต้องเป็นจำนวนเต็มบวก'];
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', trim($dayStartTime), $m)) {
            return ['ok' => false, 'error' => 'รูปแบบเวลาเริ่มต้นของวันไม่ถูกต้อง (HH:MM)'];
        }
        // Start time + total slot span must fit within the same day (slots don't wrap past midnight).
        $startMinutes = (int) $m[1] * 60 + (int) $m[2];
        if ($startMinutes + $slotHours * $slotsPerDay * 60 > 24 * 60) {
            return ['ok' => false, 'error' => 'เวลาเริ่มต้น + (ความยาวช่วงเวลา × จำนวน slots/วัน) ต้องไม่เกิน 24:00 น. ของวัน'];
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE slot_settings SET slot_hours = ?, slots_per_day = ?, weekly_quota = ?, max_advance_days = ?, day_start_time = ? WHERE id = 1'
        );
        $stmt->execute([$slotHours, $slotsPerDay, $weeklyQuota, $maxAdvanceDays, $m[1] . ':' . $m[2] . ':00']);

        return ['ok' => true];
    }

    /** Thai labels for the first few slots; falls back to a generic label beyond that. */
    public static function slotLabel(int $index): string
    {
        $labels = ['เช้า', 'บ่าย', 'เย็น', 'ดึก'];
        return $labels[$index] ?? ('ช่วงที่ ' . ($index + 1));
    }

    public static function slotStart(array $settings, int $index): string
    {
        $minutes = $index * $settings['slot_hours'] * 60;
        $start = strtotime($settings['day_start_time']) + $minutes * 60;
        return date('H:i', $start);
    }

    public static function slotEnd(array $settings, int $index): string
    {
        $minutes = ($index + 1) * $settings['slot_hours'] * 60;
        $end = strtotime($settings['day_start_time']) + $minutes * 60;
        return date('H:i', $end);
    }
}
