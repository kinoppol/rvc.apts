<?php

final class AiAccount
{
    /** @return array<int,array> All AI accounts with today's usage stats, mirroring the prototype's aiAccounts data. */
    public static function listWithUsage(): array
    {
        $settings = SlotSettings::get();
        $totalSlots = $settings['slots_per_day'];

        $stmt = Database::pdo()->query('SELECT * FROM ai_accounts ORDER BY id');
        $accounts = $stmt->fetchAll();

        $usedStmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM bookings WHERE ai_account_id = ? AND booking_date = CURDATE() AND status = 'upcoming'"
        );

        foreach ($accounts as &$ac) {
            $usedStmt->execute([$ac['id']]);
            $used = (int) $usedStmt->fetchColumn();
            $ac['usedToday'] = $used;
            $ac['totalSlots'] = $totalSlots;
            $ac['usagePct'] = $totalSlots > 0 ? round($used / $totalSlots * 100) . '%' : '0%';

            if ($ac['status'] === 'maintenance') {
                $ac['statusCls'] = 'badge-pend';
                $ac['statusLabel'] = 'บำรุงรักษา';
            } elseif ($used >= $totalSlots) {
                $ac['statusCls'] = 'badge-susp';
                $ac['statusLabel'] = 'เต็มแล้ว';
            } else {
                $ac['statusCls'] = 'badge-ok';
                $ac['statusLabel'] = 'ใช้งานได้';
            }
        }

        return $accounts;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ai_accounts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{ok:bool,error?:string} */
    public static function add(string $name, string $provider, string $status): array
    {
        $name = trim($name);
        $provider = trim($provider);
        if ($name === '' || $provider === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อและประเภทบัญชี'];
        }
        if (!in_array($status, ['active', 'maintenance'], true)) {
            $status = 'active';
        }
        $stmt = Database::pdo()->prepare('INSERT INTO ai_accounts (name, provider, status) VALUES (?, ?, ?)');
        $stmt->execute([$name, $provider, $status]);
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function update(int $id, string $name, string $provider, string $status): array
    {
        $name = trim($name);
        $provider = trim($provider);
        if ($name === '' || $provider === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกชื่อและประเภทบัญชี'];
        }
        if (!in_array($status, ['active', 'maintenance'], true)) {
            $status = 'active';
        }
        $stmt = Database::pdo()->prepare('UPDATE ai_accounts SET name = ?, provider = ?, status = ? WHERE id = ?');
        $stmt->execute([$name, $provider, $status, $id]);
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function delete(int $id): array
    {
        try {
            Database::pdo()->prepare('DELETE FROM ai_accounts WHERE id = ?')->execute([$id]);
            return ['ok' => true];
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => 'ไม่สามารถลบบัญชีนี้ได้ เนื่องจากมีประวัติการจองผูกอยู่ ลองเปลี่ยนสถานะเป็น "บำรุงรักษา" แทน'];
        }
    }
}
