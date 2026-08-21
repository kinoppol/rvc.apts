-- Add allow_current_slot to slot_settings so admins can open/close booking of the slot that is
-- currently in progress. 0 (the default) keeps the previous behaviour: the live slot is closed.
ALTER TABLE slot_settings ADD COLUMN IF NOT EXISTS allow_current_slot TINYINT(1) NOT NULL DEFAULT 0 AFTER day_start_time;
