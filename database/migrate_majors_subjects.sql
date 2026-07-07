-- Migrate student majors and teacher subjects to DB-managed tables.
-- Safe to run on an existing rvc_apts database. Idempotent.
USE rvc_apts;

-- 1. Create majors table (for students)
CREATE TABLE IF NOT EXISTS majors (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create subjects table (for teachers)
CREATE TABLE IF NOT EXISTS subjects (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL UNIQUE,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed default majors (matching the previously hardcoded list)
INSERT IGNORE INTO majors (name, sort_order) VALUES
    ('วิทยาการคอมพิวเตอร์', 1),
    ('เทคโนโลยีสารสนเทศ',  2),
    ('วิทยาการข้อมูล',      3),
    ('วิศวกรรมซอฟต์แวร์',  4);

-- 4. Seed default subjects for teachers
INSERT IGNORE INTO subjects (name, sort_order) VALUES
    ('วิทยาการคอมพิวเตอร์', 1),
    ('คณิตศาสตร์',          2),
    ('ภาษาอังกฤษ',          3),
    ('วิทยาศาสตร์',         4),
    ('ฟิสิกส์',              5);

-- 5. Add major_id and subject_id FK columns to users
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS major_id   INT UNSIGNED NULL AFTER major,
    ADD COLUMN IF NOT EXISTS subject_id INT UNSIGNED NULL AFTER major_id;

-- 6. Back-fill major_id for existing students whose major text matches a seeded major
UPDATE users u
JOIN majors m ON m.name = u.major
SET u.major_id = m.id
WHERE u.role = 'student' AND u.major_id IS NULL AND u.major IS NOT NULL;

-- 7. Add FK constraints (idempotent via DROP IF EXISTS first)
ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_users_major;
ALTER TABLE users DROP FOREIGN KEY IF EXISTS fk_users_subject;
ALTER TABLE users
    ADD CONSTRAINT fk_users_major   FOREIGN KEY (major_id)   REFERENCES majors(id)   ON DELETE SET NULL,
    ADD CONSTRAINT fk_users_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL;
