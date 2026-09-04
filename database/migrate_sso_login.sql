-- Support signing in via the college's ONE-RVC SSO gateway, and let an existing
-- self-registered account link itself to a ONE-RVC identity from the profile page.
-- Nullable + unique follows the existing student_id column's pattern: MariaDB allows
-- any number of NULLs in a unique key, so unlinked accounts are unaffected.
ALTER TABLE users ADD COLUMN IF NOT EXISTS sso_user_id VARCHAR(64) NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN IF NOT EXISTS sso_linked_at DATETIME NULL AFTER sso_user_id;
ALTER TABLE users ADD UNIQUE KEY IF NOT EXISTS uniq_users_sso_user_id (sso_user_id);
