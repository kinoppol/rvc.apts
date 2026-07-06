-- Allow re-booking a slot after an early checkout.
--
-- Problem: the plain UNIQUE KEY on (ai_account_id, booking_date, slot_index) blocks a new
-- INSERT even when the existing booking has checked_out_at set (the user released the pool).
--
-- Fix: replace the plain unique key with one that uses a virtual column which resolves to NULL
-- for checked-out (or cancelled) bookings. MariaDB excludes NULLs from unique-key enforcement,
-- so a released slot can be re-booked without touching any existing data.
USE rvc_apts;

ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS slot_uniq_guard TINYINT UNSIGNED AS (
    IF(checked_out_at IS NULL AND status = 'upcoming', slot_index, NULL)
  ) VIRTUAL;

-- The old uniq_account_slot index was also serving as the supporting index for
-- fk_bookings_ai_account.  Create a plain index on ai_account_id first so the FK
-- has something to use before we drop the unique key.
ALTER TABLE bookings ADD INDEX IF NOT EXISTS idx_bookings_ai_account (ai_account_id);

ALTER TABLE bookings DROP INDEX IF EXISTS uniq_account_slot;
ALTER TABLE bookings ADD UNIQUE KEY uniq_account_slot (ai_account_id, booking_date, slot_uniq_guard);
