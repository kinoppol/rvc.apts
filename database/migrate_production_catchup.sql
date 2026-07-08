-- Production catch-up migration — run once on any existing rvc_apts DB that is
-- behind the code at commit 0ea9897.  All statements are idempotent (IF NOT EXISTS /
-- IF EXISTS / IGNORE / MODIFY) so re-running on an already-up-to-date DB is safe.
--
-- Covers four feature migrations (apply in order):
--   1. migrate_teacher_role.sql
--   2. migrate_majors_subjects.sql
--   3. migrate_booking_issues.sql
--   4. migrate_ai_account_capacity.sql

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Teacher role in users.role ENUM
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE users
    MODIFY COLUMN role ENUM('student','teacher','admin') NOT NULL DEFAULT 'student';

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. DB-managed majors (students) and subjects (teachers)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS majors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO majors (name, sort_order) VALUES
    ('วิทยาการคอมพิวเตอร์', 1),
    ('เทคโนโลยีสารสนเทศ',  2),
    ('วิทยาการข้อมูล',      3),
    ('วิศวกรรมซอฟต์แวร์',  4);

INSERT IGNORE INTO subjects (name, sort_order) VALUES
    ('วิทยาการคอมพิวเตอร์', 1),
    ('คณิตศาสตร์',          2),
    ('ภาษาอังกฤษ',          3),
    ('วิทยาศาสตร์',         4),
    ('ฟิสิกส์',              5);

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS major_id   INT UNSIGNED NULL AFTER major,
    ADD COLUMN IF NOT EXISTS subject_id INT UNSIGNED NULL AFTER major_id;

UPDATE users u
JOIN majors m ON m.name = u.major
SET u.major_id = m.id
WHERE u.role = 'student' AND u.major_id IS NULL AND u.major IS NOT NULL;

ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_users_major;
ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_users_subject;
ALTER TABLE users
    ADD CONSTRAINT fk_users_major   FOREIGN KEY (major_id)   REFERENCES majors(id)   ON DELETE SET NULL,
    ADD CONSTRAINT fk_users_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Problem-report columns on bookings
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS issue_text TEXT NULL AFTER reported_at,
    ADD COLUMN IF NOT EXISTS issue_at   DATETIME NULL AFTER issue_text;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4. Per-pool concurrent-user capacity; drop the one-booking-per-slot constraint
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE ai_accounts
    ADD COLUMN IF NOT EXISTS capacity TINYINT UNSIGNED NOT NULL DEFAULT 1;

-- Ensure a plain FK-backing index exists before dropping the unique key
ALTER TABLE bookings
    ADD KEY IF NOT EXISTS idx_bookings_ai_account (ai_account_id);

-- Drop old unique constraint (and the virtual column if still present from
-- a previous migration version)
ALTER TABLE bookings DROP INDEX  IF EXISTS uniq_account_slot;
ALTER TABLE bookings DROP COLUMN IF EXISTS slot_uniq_guard;
