-- Idempotent: adds terms_file column to slot_settings for ToS PDF upload feature.
ALTER TABLE slot_settings
  ADD COLUMN IF NOT EXISTS terms_file VARCHAR(255) NULL DEFAULT NULL;
