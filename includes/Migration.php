<?php

final class Migration
{
    private const TABLE = '_migrations';
    private const DIR   = __DIR__ . '/../database';

    /**
     * Explicit run order — dependency-safe regardless of filename sort.
     * Files NOT in this list (e.g. migrate_production_catchup.sql) are treated as
     * manual / emergency scripts and are never auto-tracked.
     * When adding a new migration file, append its name here.
     */
    private const ORDER = [
        'migrate_ai_account_details.sql',
        'migrate_ai_account_costs.sql',
        'migrate_groups_and_reports.sql',
        'migrate_group_pools.sql',
        'migrate_checkin.sql',
        'migrate_checkout.sql',
        'migrate_token_fields.sql',
        'migrate_terms.sql',
        'migrate_teacher_role.sql',
        'migrate_majors_subjects.sql',
        'migrate_booking_issues.sql',
        'migrate_slot_rebooking.sql',
        'migrate_ai_account_capacity.sql',
        'migrate_school_info.sql',
        'migrate_ai_avatar.sql',
    ];

    /** Create the tracking table if it doesn't exist yet. */
    public static function init(): void
    {
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename   VARCHAR(255) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * All tracked migrations with their current status.
     * @return array<array{filename:string,exists:bool,applied:bool,applied_at:string|null}>
     */
    public static function status(): array
    {
        self::init();
        $applied = self::appliedSet();
        $rows = [];
        foreach (self::ORDER as $filename) {
            $rows[] = [
                'filename'   => $filename,
                'exists'     => file_exists(self::DIR . '/' . $filename),
                'applied'    => isset($applied[$filename]),
                'applied_at' => $applied[$filename] ?? null,
            ];
        }
        return $rows;
    }

    /**
     * Run every pending migration in order. Stops at the first failure.
     * @return array<array{ok:bool,file:string,error?:string}>
     */
    public static function runPending(): array
    {
        $results = [];
        foreach (self::status() as $m) {
            if ($m['applied'] || !$m['exists']) {
                continue;
            }
            $r = self::execute($m['filename']);
            $results[] = $r;
            if (!$r['ok']) {
                break;
            }
        }
        return $results;
    }

    /**
     * Run a single migration by filename (must be in ORDER list and not yet applied).
     * @return array{ok:bool,file:string,error?:string}
     */
    public static function runOne(string $filename): array
    {
        $filename = basename($filename);
        if (!in_array($filename, self::ORDER, true)) {
            return ['ok' => false, 'file' => $filename, 'error' => 'ไฟล์นี้ไม่อยู่ในรายการ migration ที่รู้จัก'];
        }
        if (!file_exists(self::DIR . '/' . $filename)) {
            return ['ok' => false, 'file' => $filename, 'error' => 'ไม่พบไฟล์ migration บน server'];
        }
        $applied = self::appliedSet();
        if (isset($applied[$filename])) {
            return ['ok' => false, 'file' => $filename, 'error' => 'Migration นี้ถูก apply ไปแล้ว'];
        }
        return self::execute($filename);
    }

    /** Mark a migration as applied without running its SQL (useful when schema already matches). */
    public static function markApplied(string $filename): array
    {
        $filename = basename($filename);
        if (!in_array($filename, self::ORDER, true)) {
            return ['ok' => false, 'error' => 'ไฟล์ไม่อยู่ในรายการที่รู้จัก'];
        }
        Database::pdo()
            ->prepare("INSERT IGNORE INTO `" . self::TABLE . "` (filename) VALUES (?)")
            ->execute([$filename]);
        return ['ok' => true];
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function execute(string $filename): array
    {
        $path = self::DIR . '/' . $filename;
        $sql  = file_get_contents($path);
        $pdo  = Database::pdo();
        try {
            foreach (self::parseStatements($sql) as $stmt) {
                $pdo->exec($stmt);
            }
            $pdo->prepare("INSERT IGNORE INTO `" . self::TABLE . "` (filename) VALUES (?)")
                ->execute([$filename]);
            return ['ok' => true, 'file' => $filename];
        } catch (PDOException $e) {
            return ['ok' => false, 'file' => $filename, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string,string> filename => applied_at */
    private static function appliedSet(): array
    {
        try {
            $rows = Database::pdo()
                ->query("SELECT filename, applied_at FROM `" . self::TABLE . "` ORDER BY id")
                ->fetchAll();
        } catch (PDOException) {
            return [];
        }
        $set = [];
        foreach ($rows as $r) {
            $set[$r['filename']] = $r['applied_at'];
        }
        return $set;
    }

    /**
     * Split a SQL file into individual executable statements.
     * Strips -- comments and USE <db>; lines (DB name may differ across environments).
     * @return string[]
     */
    private static function parseStatements(string $sql): array
    {
        $sql = preg_replace('/--[^\n]*/', '', $sql);
        $sql = preg_replace('/^\s*USE\s+\S+\s*;/mi', '', $sql);
        $stmts = [];
        foreach (explode(';', $sql) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $stmts[] = $part;
            }
        }
        return $stmts;
    }
}
