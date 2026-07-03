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
    public static function update(int $slotHours, int $slotsPerDay, int $weeklyQuota, int $maxAdvanceDays): array
    {
        if ($slotHours < 1 || $slotsPerDay < 1 || $weeklyQuota < 1 || $maxAdvanceDays < 1) {
            return ['ok' => false, 'error' => 'ค่าที่กรอกต้องเป็นจำนวนเต็มบวก'];
        }
        if ($slotHours * $slotsPerDay > 24) {
            return ['ok' => false, 'error' => 'ความยาวช่วงเวลา x จำนวน slots/วัน ต้องไม่เกิน 24 ชั่วโมง'];
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE slot_settings SET slot_hours = ?, slots_per_day = ?, weekly_quota = ?, max_advance_days = ? WHERE id = 1'
        );
        $stmt->execute([$slotHours, $slotsPerDay, $weeklyQuota, $maxAdvanceDays]);

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
