-- Direct messages an admin sends to a member. Real content (not derived state), so it
-- lives in its own table. read_at IS NULL = unread (drives the student's notification bell
-- via Notification::forStudent and clears itself when the member opens student/messages.php).
CREATE TABLE IF NOT EXISTS admin_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,                 -- recipient
    admin_id   INT UNSIGNED NULL,                     -- sender (NULL if the admin account was later removed)
    subject    VARCHAR(200) NOT NULL,
    body       TEXT NOT NULL,
    read_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_messages_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_messages_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_admin_messages_user (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
