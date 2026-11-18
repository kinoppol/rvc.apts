-- Scheduled (time-limited) suspension with a required reason.
-- suspended_reason : why the member was suspended (shown to the member at login).
-- suspended_until  : when the suspension auto-lifts (NULL = indefinite). Derived at read
--                    time by Member::expireScheduledSuspensions(), which flips the status
--                    back to 'approved' once NOW() passes this timestamp — no cron needed.
-- suspended_at     : when the suspension was applied.
ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended_reason VARCHAR(500) NULL AFTER status;
ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended_until  DATETIME NULL AFTER suspended_reason;
ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended_at     DATETIME NULL AFTER suspended_until;
