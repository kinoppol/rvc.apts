-- Add per-pool concurrent-user capacity (default 1 = prior behaviour).
ALTER TABLE ai_accounts
  ADD COLUMN IF NOT EXISTS capacity TINYINT UNSIGNED NOT NULL DEFAULT 1;

-- The unique key uniq_account_slot enforced one booking per (account,date,slot).
-- With capacity > 1, multiple users may share the same slot, so we drop the constraint
-- and rely on PHP transaction logic (SELECT COUNT(*) FOR UPDATE >= capacity) instead.
-- MariaDB won't drop an index that backs a FK; add a plain covering index first so the
-- FK has an alternative backing index, then the unique key can be safely removed.
ALTER TABLE bookings
  ADD KEY IF NOT EXISTS idx_bookings_ai_account (ai_account_id);
ALTER TABLE bookings DROP INDEX IF EXISTS uniq_account_slot;
ALTER TABLE bookings DROP COLUMN IF EXISTS slot_uniq_guard;
