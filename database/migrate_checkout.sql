USE rvc_apts;
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS checked_out_at DATETIME NULL AFTER checked_in_at;
