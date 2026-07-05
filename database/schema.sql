-- AI Pro Time-Sharing schema (MariaDB 10.3+)
CREATE DATABASE IF NOT EXISTS rvc_apts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rvc_apts;

-- Admin-managed user groups; per-group usage limits override the global slot_settings
-- (NULL on a limit column means "fall back to the global default").
CREATE TABLE user_groups (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL UNIQUE,
    description      VARCHAR(255) NULL,
    weekly_quota     TINYINT UNSIGNED NULL,
    max_advance_days SMALLINT UNSIGNED NULL,
    max_concurrent   TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- how many pools a member may book in the same slot
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role          ENUM('student','admin') NOT NULL DEFAULT 'student',
    name          VARCHAR(150) NOT NULL,
    student_id    VARCHAR(20) NULL UNIQUE,
    major         VARCHAR(100) NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(20) NULL,
    group_id      INT UNSIGNED NULL,
    password_hash VARCHAR(255) NOT NULL,
    status        ENUM('pending','approved','suspended') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_group FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin-managed list of AI account types (Claude Pro, ChatGPT Plus, ...).
CREATE TABLE ai_providers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_accounts (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                 VARCHAR(100) NOT NULL,
    provider_id          INT UNSIGNED NULL,
    provider             VARCHAR(100) NOT NULL,               -- denormalized type name (kept in sync with ai_providers)
    email                VARCHAR(190) NULL,                   -- shared login email for the AI account
    account_password     VARCHAR(255) NULL,                   -- shared login password, stored readable so admins can share it
    status               ENUM('active','maintenance') NOT NULL DEFAULT 'active',
    expires_at           DATETIME NULL,                       -- when reached, the account is treated as disabled (derived at read time)
    password_updated_at  DATETIME NULL,                       -- last time the shared password was changed
    password_reminder    ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_accounts_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE SET NULL,
    KEY idx_ai_accounts_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which AI pools each group's members may book (no row for a group = that group can't book anything).
CREATE TABLE group_ai_accounts (
    group_id      INT UNSIGNED NOT NULL,
    ai_account_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, ai_account_id),
    CONSTRAINT fk_gaa_group   FOREIGN KEY (group_id)      REFERENCES user_groups(id)  ON DELETE CASCADE,
    CONSTRAINT fk_gaa_account FOREIGN KEY (ai_account_id) REFERENCES ai_accounts(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE slot_settings (
    id                TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    slot_hours        TINYINT UNSIGNED NOT NULL DEFAULT 5,
    slots_per_day     TINYINT UNSIGNED NOT NULL DEFAULT 3,
    weekly_quota      TINYINT UNSIGNED NOT NULL DEFAULT 3,
    max_advance_days  SMALLINT UNSIGNED NOT NULL DEFAULT 14,
    day_start_time    TIME NOT NULL DEFAULT '08:00:00',
    CONSTRAINT chk_slot_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO slot_settings (id, slot_hours, slots_per_day, weekly_quota, max_advance_days, day_start_time)
VALUES (1, 5, 3, 3, 14, '08:00:00');

CREATE TABLE bookings (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    ai_account_id  INT UNSIGNED NOT NULL,
    booking_date   DATE NOT NULL,
    slot_index     TINYINT UNSIGNED NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime   DATETIME NOT NULL,
    status         ENUM('upcoming','cancelled') NOT NULL DEFAULT 'upcoming',
    purpose        VARCHAR(500) NOT NULL DEFAULT '',   -- why the student booked the slot (required at booking time)
    report_text    TEXT NULL,                          -- post-use report body
    report_file    VARCHAR(255) NULL,                  -- optional uploaded evidence (image/PDF) filename
    reported_at    DATETIME NULL,                      -- when the usage report was submitted (NULL = not yet reported)
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at   DATETIME NULL,
    checked_in_at  DATETIME NULL,                        -- when the student pressed check-in (NULL = not yet checked in)
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_bookings_ai_account FOREIGN KEY (ai_account_id) REFERENCES ai_accounts(id),
    UNIQUE KEY uniq_account_slot (ai_account_id, booking_date, slot_index),
    KEY idx_user_date (user_id, booking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
