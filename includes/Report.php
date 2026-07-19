<?php

final class Report
{
    private const TARGET_HOURS = 50; // baseline hours representing a "100%" utilization rate

    /** @return array<int,array{name:string,studentId:string,totalBookings:int,hours:int,rate:string}> */
    public static function rows(): array
    {
        $settings = SlotSettings::get();
        $stmt = Database::pdo()->query(
            "SELECT u.name, u.student_id,
                    COUNT(b.id) AS total_bookings
             FROM users u
             JOIN bookings b ON b.user_id = u.id AND b.status = 'upcoming' AND b.end_datetime < NOW()
             WHERE u.role = 'student'
             GROUP BY u.id, u.name, u.student_id
             HAVING total_bookings > 0
             ORDER BY total_bookings DESC"
        );

        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $hours = ((int) $r['total_bookings']) * $settings['slot_hours'];
            $pct = min(100, (int) round($hours / self::TARGET_HOURS * 100));
            $rows[] = [
                'name' => $r['name'],
                'studentId' => $r['student_id'],
                'totalBookings' => (int) $r['total_bookings'],
                'hours' => $hours,
                'rate' => $pct . '%',
                'rateLabel' => $pct . '%',
            ];
        }
        return $rows;
    }

    /**
     * One page of rows() for the admin report table. The CSV export deliberately still uses
     * rows() so it always contains every member, not just the visible page.
     * @return array{rows:array<int,array>,total:int}
     */
    public static function pagedRows(int $page, int $perPage): array
    {
        $all = self::rows();
        $offset = max(0, ($page - 1) * $perPage);
        return ['rows' => array_slice($all, $offset, $perPage), 'total' => count($all)];
    }

    /**
     * Per-AI-account cost summary for the current calendar month.
     * Only returns accounts that have at least one cost field set.
     */
    public static function costRows(): array
    {
        $rows = Database::pdo()->query(
            "SELECT a.id, a.name, COALESCE(p.name, a.provider) AS provider_name,
                    a.monthly_cost, a.cost_per_slot,
                    COUNT(b.id) AS bookings_this_month
             FROM ai_accounts a
             LEFT JOIN ai_providers p ON p.id = a.provider_id
             LEFT JOIN bookings b ON b.ai_account_id = a.id
                                  AND b.status = 'upcoming'
                                  AND b.end_datetime < NOW()
                                  AND YEAR(b.booking_date) = YEAR(CURDATE())
                                  AND MONTH(b.booking_date) = MONTH(CURDATE())
             WHERE a.monthly_cost IS NOT NULL OR a.cost_per_slot IS NOT NULL
             GROUP BY a.id, a.name, a.provider, p.name, a.monthly_cost, a.cost_per_slot
             ORDER BY a.id"
        )->fetchAll();

        return array_map(function ($r) {
            $bookings = (int) $r['bookings_this_month'];
            $monthlyCost = $r['monthly_cost'] !== null ? (float) $r['monthly_cost'] : null;
            $costPerSlot = $r['cost_per_slot'] !== null ? (float) $r['cost_per_slot'] : null;
            $usageCost = $costPerSlot !== null ? round($costPerSlot * $bookings, 2) : null;
            $ratio = ($usageCost !== null && $monthlyCost !== null && $monthlyCost > 0)
                ? min(100, (int) round($usageCost / $monthlyCost * 100))
                : null;
            return [
                'name'           => $r['name'],
                'provider'       => $r['provider_name'],
                'monthly_cost'   => $monthlyCost,
                'cost_per_slot'  => $costPerSlot,
                'bookings'       => $bookings,
                'usage_cost'     => $usageCost,
                'cost_ratio'     => $ratio,
            ];
        }, $rows);
    }

    /** Streams the report as a CSV download and terminates the request. */
    public static function streamCsv(): never
    {
        $rows = self::rows();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rvc-apts-report-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens Thai text correctly
        fputcsv($out, ['สมาชิก', 'รหัสนักศึกษา', 'จองทั้งหมด', 'ชม.สะสม', 'อัตราการใช้งาน']);
        foreach ($rows as $row) {
            fputcsv($out, [$row['name'], $row['studentId'], $row['totalBookings'], $row['hours'], $row['rate']]);
        }
        fclose($out);
        exit;
    }
}
