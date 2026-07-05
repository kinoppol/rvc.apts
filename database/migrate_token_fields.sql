-- Idempotent: add token usage fields to bookings table
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS token_start_pct TINYINT UNSIGNED NULL DEFAULT NULL AFTER report_file,
  ADD COLUMN IF NOT EXISTS token_end_pct   TINYINT UNSIGNED NULL DEFAULT NULL AFTER token_start_pct,
  ADD COLUMN IF NOT EXISTS token_reset_at  DATETIME          NULL DEFAULT NULL AFTER token_end_pct;
