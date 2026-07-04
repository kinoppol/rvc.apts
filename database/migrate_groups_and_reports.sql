-- Migration: user groups (per-group limits), booking purpose, and post-use reports.
-- Safe to run on an existing rvc_apts database WITHOUT losing data. Idempotent on MariaDB 10.x
-- (ADD COLUMN IF NOT EXISTS). Fresh installs get all of this from schema.sql instead.
USE rvc_apts;

-- 1) User groups with per-group usage limits (NULL = fall back to global slot_settings)
CREATE TABLE IF NOT EXISTS user_groups (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL UNIQUE,
    description      VARCHAR(255) NULL,
    weekly_quota     TINYINT UNSIGNED NULL,
    max_advance_days SMALLINT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) users.group_id
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS group_id INT UNSIGNED NULL AFTER phone;

-- 3) bookings: purpose + post-use report fields
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS purpose     VARCHAR(500) NOT NULL DEFAULT '' AFTER status,
    ADD COLUMN IF NOT EXISTS report_text TEXT NULL AFTER purpose,
    ADD COLUMN IF NOT EXISTS report_file VARCHAR(255) NULL AFTER report_text,
    ADD COLUMN IF NOT EXISTS reported_at DATETIME NULL AFTER report_file;

-- 4) FK for users.group_id. No IF NOT EXISTS for constraints in MariaDB, so a
--    "Duplicate key name fk_users_group" error on re-run is safe to ignore.
ALTER TABLE users
    ADD CONSTRAINT fk_users_group FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE SET NULL;
