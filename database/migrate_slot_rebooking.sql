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

ALTER TABLE bookings DROP INDEX IF EXISTS uniq_account_slot;
ALTER TABLE bookings ADD UNIQUE KEY uniq_account_slot (ai_account_id, booking_date, slot_uniq_guard);
