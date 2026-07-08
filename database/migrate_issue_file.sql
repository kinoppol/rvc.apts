-- Add issue_file column for attaching images/PDFs to problem reports
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS issue_file VARCHAR(200) NULL AFTER issue_text;
