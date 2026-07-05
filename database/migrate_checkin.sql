USE rvc_apts;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS checked_in_at DATETIME NULL AFTER cancelled_at;
