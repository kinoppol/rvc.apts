-- Add institution_name to slot_settings so admins can rename the college shown on the login/landing pages
ALTER TABLE slot_settings ADD COLUMN IF NOT EXISTS institution_name VARCHAR(200) NOT NULL DEFAULT 'วิทยาลัย RVC' AFTER terms_file;
