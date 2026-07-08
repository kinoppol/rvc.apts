-- Add school_name and province to users table for both students and teachers
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS school_name VARCHAR(200) NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS province    VARCHAR(100) NULL AFTER school_name;
