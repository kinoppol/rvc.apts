-- Add problem-report columns to bookings (safe to run on existing DB)
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS issue_text TEXT NULL AFTER reported_at,
  ADD COLUMN IF NOT EXISTS issue_at   DATETIME NULL AFTER issue_text;
