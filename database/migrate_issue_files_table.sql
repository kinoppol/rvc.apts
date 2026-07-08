-- Separate table for multiple file attachments per issue report
CREATE TABLE IF NOT EXISTS booking_issue_files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id    INT NOT NULL,
  filename      VARCHAR(200) NOT NULL,
  original_name VARCHAR(255) NULL,
  uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
