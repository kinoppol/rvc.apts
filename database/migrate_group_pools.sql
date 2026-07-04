-- Migration: per-group AI-pool access + how many pools a member may book in the same slot.
-- Safe to run on an existing rvc_apts database WITHOUT losing data. Idempotent on MariaDB 10.x.
-- Fresh installs get all of this from schema.sql instead.
USE rvc_apts;

-- 1) How many pools a member of the group can book in the same time slot (default 1)
ALTER TABLE user_groups
    ADD COLUMN IF NOT EXISTS max_concurrent TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER max_advance_days;

-- 2) Which pools each group may book (no row = the group cannot book anything)
CREATE TABLE IF NOT EXISTS group_ai_accounts (
    group_id      INT UNSIGNED NOT NULL,
    ai_account_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, ai_account_id),
    CONSTRAINT fk_gaa_group   FOREIGN KEY (group_id)      REFERENCES user_groups(id)  ON DELETE CASCADE,
    CONSTRAINT fk_gaa_account FOREIGN KEY (ai_account_id) REFERENCES ai_accounts(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
