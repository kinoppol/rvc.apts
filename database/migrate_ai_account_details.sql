-- Migration: add AI-account details (type list, credentials, expiry, password reminder).
-- Safe to run on an existing rvc_apts database WITHOUT losing data. Idempotent on MariaDB 10.x
-- (uses ADD COLUMN IF NOT EXISTS). Fresh installs get all of this from schema.sql instead.
USE rvc_apts;

-- 1) Admin-managed AI type list
CREATE TABLE IF NOT EXISTS ai_providers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) New columns on ai_accounts
ALTER TABLE ai_accounts
    ADD COLUMN IF NOT EXISTS provider_id         INT UNSIGNED NULL AFTER name,
    ADD COLUMN IF NOT EXISTS email               VARCHAR(190) NULL AFTER provider,
    ADD COLUMN IF NOT EXISTS account_password    VARCHAR(255) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS expires_at          DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS password_updated_at DATETIME NULL AFTER expires_at,
    ADD COLUMN IF NOT EXISTS password_reminder   ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'none' AFTER password_updated_at;

-- 3) Backfill the type list from existing free-text provider values, then link accounts to it
INSERT IGNORE INTO ai_providers (name)
    SELECT DISTINCT provider FROM ai_accounts WHERE provider IS NOT NULL AND provider <> '';

UPDATE ai_accounts a
    JOIN ai_providers p ON p.name = a.provider
    SET a.provider_id = p.id
    WHERE a.provider_id IS NULL;

-- 4) Index + FK. Drop FK first so the ADD is idempotent (MariaDB has no IF NOT EXISTS for FK names).
ALTER TABLE ai_accounts
    ADD KEY IF NOT EXISTS idx_ai_accounts_expires (expires_at);
ALTER TABLE ai_accounts DROP FOREIGN KEY IF EXISTS fk_ai_accounts_provider;
ALTER TABLE ai_accounts
    ADD CONSTRAINT fk_ai_accounts_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE SET NULL;
