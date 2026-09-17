-- Add report review columns to bookings (admin can accept/reject usage reports)
ALTER TABLE bookings
  ADD COLUMN IF NOT EXISTS report_status ENUM('pending_review','accepted','rejected') DEFAULT NULL AFTER reported_at,
  ADD COLUMN IF NOT EXISTS report_review_note TEXT DEFAULT NULL AFTER report_status,
  ADD COLUMN IF NOT EXISTS report_reviewed_by INT UNSIGNED DEFAULT NULL AFTER report_review_note,
  ADD COLUMN IF NOT EXISTS report_reviewed_at DATETIME DEFAULT NULL AFTER report_reviewed_by;

-- Add minimum character settings for usage reports and LMS missions
ALTER TABLE slot_settings
  ADD COLUMN IF NOT EXISTS min_report_chars SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER hd_show_banner,
  ADD COLUMN IF NOT EXISTS min_mission_chars SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER min_report_chars;
