-- Let each AI pool restrict which days of the week it can be booked on (default: every day)
ALTER TABLE ai_accounts ADD COLUMN IF NOT EXISTS available_days CHAR(7) NOT NULL DEFAULT '1111111' AFTER capacity;
