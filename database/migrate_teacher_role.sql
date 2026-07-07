-- Add 'teacher' as a selectable role on the registration form.
-- Safe to run on an existing rvc_apts database. Idempotent: MariaDB MODIFY COLUMN
-- is a no-op when the column definition is already correct.
USE rvc_apts;

ALTER TABLE users
    MODIFY COLUMN role ENUM('student','teacher','admin') NOT NULL DEFAULT 'student';
