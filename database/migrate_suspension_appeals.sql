-- Appeals a suspended member files against their suspension, and the admin's review.
-- status: pending (just filed) → reviewing (optional) → accepted / rejected.
CREATE TABLE IF NOT EXISTS suspension_appeals (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    message        TEXT NOT NULL,
    status         ENUM('pending','reviewing','accepted','rejected') NOT NULL DEFAULT 'pending',
    admin_response TEXT NULL,
    reviewed_by    INT UNSIGNED NULL,
    reviewed_at    DATETIME NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_appeal_user     FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_appeal_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_appeal_user (user_id),
    INDEX idx_appeal_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suspension_appeal_files (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appeal_id     INT UNSIGNED NOT NULL,
    filename      VARCHAR(200) NOT NULL,
    original_name VARCHAR(255) NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_appeal_file FOREIGN KEY (appeal_id) REFERENCES suspension_appeals(id) ON DELETE CASCADE,
    INDEX idx_appeal_file (appeal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
