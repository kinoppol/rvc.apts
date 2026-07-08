<?php

final class AiAccount
{
    private const REMINDER_DAYS = ['daily' => 1, 'weekly' => 7, 'monthly' => 30];
    private const REMINDER_LABEL = ['none' => 'ปิด', 'daily' => 'ทุกวัน', 'weekly' => 'ทุกสัปดาห์', 'monthly' => 'ทุกเดือน'];

    /**
     * All AI accounts with today's usage plus derived expiry / password-reminder info.
     * Expiry is applied at read time (expires_at <= NOW() => disabled) so no cron is needed.
     */
    public static function listWithUsage(): array
    {
        $settings = SlotSettings::get();
        $totalSlots = (int) $settings['slots_per_day'];
        $now = new DateTimeImmutable();

        $accounts = Database::pdo()->query(
            'SELECT a.*, COALESCE(p.name, a.provider) AS provider_name
             FROM ai_accounts a LEFT JOIN ai_providers p ON p.id = a.provider_id
             ORDER BY a.id'
        )->fetchAll();

        $usedStmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM bookings WHERE ai_account_id = ? AND booking_date = CURDATE() AND status = 'upcoming'"
        );

        foreach ($accounts as &$ac) {
            $ac['provider'] = $ac['provider_name'];

            $usedStmt->execute([$ac['id']]);
            $used = (int) $usedStmt->fetchColumn();
            $ac['usedToday'] = $used;
            $ac['totalSlots'] = $totalSlots;
            $ac['usagePct'] = $totalSlots > 0 ? round($used / $totalSlots * 100) . '%' : '0%';

            // ── Expiry (derived) ──
            $isExpired = false;
            if (!empty($ac['expires_at'])) {
                $exp = new DateTimeImmutable($ac['expires_at']);
                $isExpired = $exp <= $now;
                $daysLeft = self::daysUntil($now, $exp);
                $ac['expiresLabel'] = self::thaiDateTime($ac['expires_at']);
                if ($isExpired) {
                    $ac['expiryText'] = 'หมดอายุแล้ว';
                    $ac['expiryWarn'] = true;
                } else {
                    $ac['expiryText'] = 'เหลืออีก ' . $daysLeft . ' วัน';
                    $ac['expiryWarn'] = $daysLeft <= 7;
                }
            } else {
                $ac['expiresLabel'] = 'ไม่มีกำหนด';
                $ac['expiryText'] = 'ไม่มีกำหนด';
                $ac['expiryWarn'] = false;
            }
            $ac['isExpired'] = $isExpired;

            // ── Effective status ──
            if ($isExpired) {
                $ac['statusCls'] = 'badge-susp';
                $ac['statusLabel'] = 'ปิดใช้งาน (หมดอายุ)';
            } elseif ($ac['status'] === 'maintenance') {
                $ac['statusCls'] = 'badge-pend';
                $ac['statusLabel'] = 'บำรุงรักษา';
            } elseif ($used >= $totalSlots) {
                $ac['statusCls'] = 'badge-susp';
                $ac['statusLabel'] = 'เต็มแล้ว';
            } else {
                $ac['statusCls'] = 'badge-ok';
                $ac['statusLabel'] = 'ใช้งานได้';
            }

            // ── Password-update reminder (derived) ──
            $ac['reminderLabel'] = self::REMINDER_LABEL[$ac['password_reminder']] ?? 'ปิด';
            $ac['passwordUpdatedLabel'] = self::thaiDateTime($ac['password_updated_at']);
            if ($ac['password_reminder'] !== 'none' && !empty($ac['password_updated_at'])) {
                $intervalDays = self::REMINDER_DAYS[$ac['password_reminder']];
                $due = (new DateTimeImmutable($ac['password_updated_at']))->modify("+{$intervalDays} days");
                $overdue = $due <= $now;
                $daysLeft = self::daysUntil($now, $due);
                $ac['pwdDueLabel'] = self::thaiDateTime($due->format('Y-m-d H:i:s'));
                if ($overdue) {
                    $ac['pwdText'] = 'ถึงกำหนดอัปเดตรหัสผ่าน' . ($daysLeft < 0 ? ' (เลย ' . abs($daysLeft) . ' วัน)' : '');
                    $ac['pwdWarn'] = true;
                } else {
                    $ac['pwdText'] = 'อัปเดตอีกใน ' . $daysLeft . ' วัน';
                    $ac['pwdWarn'] = $daysLeft <= 1;
                }
                $ac['pwdReminderOn'] = true;
            } else {
                $ac['pwdDueLabel'] = '—';
                $ac['pwdText'] = 'ไม่แจ้งเตือน';
                $ac['pwdWarn'] = false;
                $ac['pwdReminderOn'] = false;
            }
        }
        unset($ac);

        return $accounts;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM ai_accounts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int,array{id:int,name:string,provider:string}> Lightweight list for pickers. */
    public static function allBasic(): array
    {
        return Database::pdo()->query(
            'SELECT a.id, a.name, COALESCE(p.name, a.provider) AS provider
             FROM ai_accounts a LEFT JOIN ai_providers p ON p.id = a.provider_id ORDER BY a.id'
        )->fetchAll();
    }

    /** Validate and normalise the capacity field (integer ≥ 1). */
    private static function validateCapacity(mixed $raw): int
    {
        $v = (int) $raw;
        return max(1, $v);
    }

    /** Strip anything that isn't a graphic Unicode char; cap at 8 bytes to fit one emoji. */
    private static function sanitizeEmoji(mixed $raw): string
    {
        $s = trim((string) $raw);
        // Keep only the first "grapheme cluster" (handles multi-codepoint emoji like 🏳️‍🌈)
        $first = grapheme_substr($s, 0, 1);
        return $first !== false && $first !== '' ? $first : '';
    }

    /** @return array{ok:bool,error?:string} */
    public static function add(array $d): array
    {
        $fields = self::validate($d);
        if (isset($fields['error'])) {
            return ['ok' => false, 'error' => $fields['error']];
        }

        $password    = (string) ($d['account_password'] ?? '');
        $capacity    = self::validateCapacity($d['capacity'] ?? 1);
        $avatarEmoji = self::sanitizeEmoji($d['avatar_emoji'] ?? '');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ai_accounts
                (name, avatar_emoji, provider_id, provider, email, account_password, status, capacity, expires_at, password_updated_at, password_reminder, monthly_cost, cost_per_slot)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $fields['name'], $avatarEmoji ?: null,
            $fields['provider_id'], $fields['provider'], $fields['email'],
            $password !== '' ? $password : null,
            $fields['status'], $capacity, $fields['expires_at'],
            $password !== '' ? date('Y-m-d H:i:s') : null,
            $fields['password_reminder'],
            $fields['monthly_cost'], $fields['cost_per_slot'],
        ]);
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function update(int $id, array $d): array
    {
        $existing = self::find($id);
        if (!$existing) {
            return ['ok' => false, 'error' => 'ไม่พบบัญชี AI ที่ต้องการแก้ไข'];
        }
        $fields = self::validate($d);
        if (isset($fields['error'])) {
            return ['ok' => false, 'error' => $fields['error']];
        }

        // Blank password field on edit means "keep the current password" (don't reset the reminder clock).
        $newPassword = (string) ($d['account_password'] ?? '');
        if ($newPassword !== '') {
            $password = $newPassword;
            $passwordUpdatedAt = date('Y-m-d H:i:s');
        } else {
            $password = $existing['account_password'];
            $passwordUpdatedAt = $existing['password_updated_at'];
        }

        $capacity    = self::validateCapacity($d['capacity'] ?? $existing['capacity'] ?? 1);
        $avatarEmoji = self::sanitizeEmoji($d['avatar_emoji'] ?? '');
        $stmt = Database::pdo()->prepare(
            'UPDATE ai_accounts SET
                name = ?, avatar_emoji = ?, provider_id = ?, provider = ?, email = ?, account_password = ?,
                status = ?, capacity = ?, expires_at = ?, password_updated_at = ?, password_reminder = ?,
                monthly_cost = ?, cost_per_slot = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $fields['name'], $avatarEmoji ?: null,
            $fields['provider_id'], $fields['provider'], $fields['email'], $password,
            $fields['status'], $capacity, $fields['expires_at'], $passwordUpdatedAt, $fields['password_reminder'],
            $fields['monthly_cost'], $fields['cost_per_slot'], $id,
        ]);
        return ['ok' => true];
    }

    /**
     * Reset shared passwords for every supplied account in one go.
     * $passwords is keyed by account id; any blank value is skipped.
     * @return array{ok:bool,count?:int,error?:string}
     */
    public static function bulkResetPasswords(array $passwords): array
    {
        if (empty($passwords)) {
            return ['ok' => false, 'error' => 'ไม่มีบัญชีที่ต้องการรีเซ็ต'];
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE ai_accounts SET account_password = ?, password_updated_at = NOW() WHERE id = ?'
        );
        $count = 0;
        foreach ($passwords as $id => $pw) {
            $pw = trim((string) $pw);
            if ($pw === '' || (int) $id <= 0) {
                continue;
            }
            $stmt->execute([$pw, (int) $id]);
            $count++;
        }
        return ['ok' => true, 'count' => $count];
    }

    /** Sets a new shared login password and resets the password-reminder clock. @return array{ok:bool,error?:string} */
    public static function updatePassword(int $id, string $password): array
    {
        if (!self::find($id)) {
            return ['ok' => false, 'error' => 'ไม่พบบัญชี AI ที่ต้องการเปลี่ยนรหัสผ่าน'];
        }
        $password = trim($password);
        if ($password === '') {
            return ['ok' => false, 'error' => 'กรุณาระบุรหัสผ่านใหม่'];
        }
        $stmt = Database::pdo()->prepare('UPDATE ai_accounts SET account_password = ?, password_updated_at = NOW() WHERE id = ?');
        $stmt->execute([$password, $id]);
        return ['ok' => true];
    }

    /** @return array{ok:bool,error?:string} */
    public static function delete(int $id): array
    {
        try {
            Database::pdo()->prepare('DELETE FROM ai_accounts WHERE id = ?')->execute([$id]);
            return ['ok' => true];
        } catch (PDOException) {
            return ['ok' => false, 'error' => 'ไม่สามารถลบบัญชีนี้ได้ เนื่องจากมีประวัติการจองผูกอยู่ ลองเปลี่ยนสถานะเป็น "บำรุงรักษา" แทน'];
        }
    }

    /** Shared validation/normalization for add & update. Returns fields, or ['error'=>...]. */
    private static function validate(array $d): array
    {
        $name = trim($d['name'] ?? '');
        if ($name === '') {
            return ['error' => 'กรุณากรอกชื่อบัญชี'];
        }

        $providerId = (int) ($d['provider_id'] ?? 0);
        $provider = AiProvider::find($providerId);
        if (!$provider) {
            return ['error' => 'กรุณาเลือกประเภทบัญชี'];
        }

        $email = trim($d['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'รูปแบบอีเมลไม่ถูกต้อง'];
        }

        $status = $d['status'] ?? 'active';
        if (!in_array($status, ['active', 'maintenance'], true)) {
            $status = 'active';
        }

        $reminder = $d['password_reminder'] ?? 'none';
        if (!in_array($reminder, ['none', 'daily', 'weekly', 'monthly'], true)) {
            $reminder = 'none';
        }

        $expiresRaw = trim($d['expires_at'] ?? '');
        $expiresAt = null;
        if ($expiresRaw !== '') {
            $ts = strtotime(str_replace('T', ' ', $expiresRaw));
            if ($ts === false) {
                return ['error' => 'รูปแบบวันเวลาหมดอายุไม่ถูกต้อง'];
            }
            $expiresAt = date('Y-m-d H:i:s', $ts);
        }

        $monthlyCost = isset($d['monthly_cost']) && $d['monthly_cost'] !== '' ? round((float) $d['monthly_cost'], 2) : null;
        if ($monthlyCost !== null && $monthlyCost < 0) {
            return ['error' => 'ค่าใช้จ่ายต่อเดือนต้องไม่ติดลบ'];
        }
        $costPerSlot = isset($d['cost_per_slot']) && $d['cost_per_slot'] !== '' ? round((float) $d['cost_per_slot'], 2) : null;
        if ($costPerSlot !== null && $costPerSlot < 0) {
            return ['error' => 'ค่าต่อช่วงเวลาต้องไม่ติดลบ'];
        }

        return [
            'name' => $name,
            'provider_id' => $providerId,
            'provider' => $provider['name'],
            'email' => $email !== '' ? $email : null,
            'status' => $status,
            'password_reminder' => $reminder,
            'expires_at' => $expiresAt,
            'monthly_cost' => $monthlyCost,
            'cost_per_slot' => $costPerSlot,
        ];
    }

    /** Whole calendar days from $from to $to (ignores time-of-day), so "เหลืออีก N วัน" matches intuition. */
    private static function daysUntil(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $fromDay = new DateTimeImmutable($from->format('Y-m-d'));
        $toDay = new DateTimeImmutable($to->format('Y-m-d'));
        return (int) $fromDay->diff($toDay)->format('%r%a');
    }

    private static function thaiDateTime(?string $dt): string
    {
        if (empty($dt)) {
            return '—';
        }
        $d = new DateTimeImmutable($dt);
        return Booking::thaiDate($d) . ' ' . $d->format('H:i');
    }
}
