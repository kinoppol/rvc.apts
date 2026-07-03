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
