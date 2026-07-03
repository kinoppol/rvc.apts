-- AI Pro Time-Sharing schema (MariaDB 10.3+)
CREATE DATABASE IF NOT EXISTS rvc_apts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rvc_apts;

CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role          ENUM('student','admin') NOT NULL DEFAULT 'student',
    name          VARCHAR(150) NOT NULL,
    student_id    VARCHAR(20) NULL UNIQUE,
    major         VARCHAR(100) NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status        ENUM('pending','approved','suspended') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_accounts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    provider   VARCHAR(100) NOT NULL,
    status     ENUM('active','maintenance') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at   DATETIME NULL,
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_bookings_ai_account FOREIGN KEY (ai_account_id) REFERENCES ai_accounts(id),
    UNIQUE KEY uniq_account_slot (ai_account_id, booking_date, slot_index),
    KEY idx_user_date (user_id, booking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
