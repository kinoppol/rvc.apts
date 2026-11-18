-- File/image attachments for admin messages and for suspension reasons.
-- Only a bare filename is stored; URLs are built with url('uploads/<subdir>/' . filename).
CREATE TABLE IF NOT EXISTS admin_message_files (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id    INT UNSIGNED NOT NULL,
    filename      VARCHAR(200) NOT NULL,
    original_name VARCHAR(255) NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_amf_message FOREIGN KEY (message_id) REFERENCES admin_messages(id) ON DELETE CASCADE,
    INDEX idx_amf_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attachments for the current suspension of a member (replaced on each new suspension,
-- cleared when the member is reactivated).
CREATE TABLE IF NOT EXISTS user_suspension_files (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    filename      VARCHAR(200) NOT NULL,
    original_name VARCHAR(255) NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_usf_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
