-- Demo data mirroring the original prototype. Run after schema.sql.
-- All seeded accounts use the password: Passw0rd!
USE rvc_apts;

-- User groups (per-group limits; NULL = use the global slot_settings default)
INSERT INTO user_groups (id, name, description, weekly_quota, max_advance_days) VALUES
(1, 'นักศึกษาทั่วไป', 'สิทธิ์มาตรฐานตามค่าเริ่มต้นของระบบ', NULL, NULL),
(2, 'นักวิจัย',       'โควต้าสูงและจองล่วงหน้าได้นานกว่า',   5,    30),
(3, 'ทดลองใช้',       'สิทธิ์จำกัดสำหรับผู้ใช้ทดลอง',         1,    7);

-- id 1: admin
INSERT INTO users (role, name, student_id, major, email, phone, password_hash, status) VALUES
('admin', 'ผู้ดูแลระบบ', NULL, NULL, 'admin@rvc.ac.th', NULL,
 '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'approved');

-- id 2-9: students
INSERT INTO users (role, name, student_id, major, email, phone, password_hash, status) VALUES
('student', 'สมชาย ใจดี',     '6501CS001', 'วิทยาการคอมพิวเตอร์', 'somchai@rvc.ac.th',  '081-234-5678', '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'approved'),
('student', 'สมหญิง รักเรียน', '6501IT002', 'เทคโนโลยีสารสนเทศ',   'somying@rvc.ac.th',  NULL,           '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'pending'),
('student', 'วิชัย เก่งมาก',   '6501CS003', 'วิทยาการคอมพิวเตอร์', 'wichai@rvc.ac.th',   NULL,           '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'approved'),
('student', 'อมรา สวยงาม',     '6502DS004', 'วิทยาการข้อมูล',      'amara@rvc.ac.th',    NULL,           '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'pending'),
('student', 'ธนพล มีสติ',      '6501CS005', 'วิทยาการคอมพิวเตอร์', 'thanapol@rvc.ac.th', NULL,           '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'suspended'),
('student', 'นภา แสงทอง',      '6502IT006', 'เทคโนโลยีสารสนเทศ',   'napa@rvc.ac.th',     NULL,           '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'approved'),
('student', 'ปิยะ วงษ์งาม',    '6502DS007', 'วิทยาการข้อมูล',      'piya@rvc.ac.th',     NULL,           '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'approved'),
('student', 'กนกวรรณ สุขใจ',   '6503CS008', 'วิทยาการคอมพิวเตอร์', 'kanok@rvc.ac.th',    NULL,           '$2y$10$yujc9TCzkQ7ei7G6XCxEA.lzrdc/AxkxBck5vZNef4PIkenNwA2ce', 'pending');

-- Assign a few students to groups (the rest inherit the global default)
UPDATE users SET group_id = 2 WHERE email = 'somchai@rvc.ac.th';  -- นักวิจัย (quota 5)
UPDATE users SET group_id = 3 WHERE email = 'wichai@rvc.ac.th';   -- ทดลองใช้ (quota 1)
UPDATE users SET group_id = 1 WHERE email = 'napa@rvc.ac.th';     -- นักศึกษาทั่วไป

-- AI account types (admin-managed)
INSERT INTO ai_providers (id, name) VALUES
(1, 'Anthropic Claude Pro'),
(2, 'OpenAI ChatGPT Plus'),
(3, 'Google Gemini Advanced');

-- id 1-4: AI account pool (email/password are the shared login credentials admins hand out)
-- expires_at / password_updated_at are relative to import day so demo states stay meaningful:
--   #1 far from expiry, monthly reminder     #2 expiring in a month, weekly reminder due soon
--   #3 expiring in a week, daily reminder overdue    #4 already expired (auto-disabled) + maintenance
INSERT INTO ai_accounts (name, provider_id, provider, email, account_password, status, expires_at, password_updated_at, password_reminder) VALUES
('Claude Pro #1',   1, 'Anthropic Claude Pro', 'claude.pro1@rvc.ac.th', 'Cl@ude#Pool1', 'active',
    TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 120 DAY), '23:59:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 25 DAY), '09:00:00'), 'monthly'),
('Claude Pro #2',   1, 'Anthropic Claude Pro', 'claude.pro2@rvc.ac.th', 'Cl@ude#Pool2', 'active',
    TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '23:59:00'),  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:00:00'),  'weekly'),
('ChatGPT Plus #1', 2, 'OpenAI ChatGPT Plus',  'gpt.plus1@rvc.ac.th',   'Gpt+Pool#1',   'active',
    TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '23:59:00'),   TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:00:00'),  'daily'),
('ChatGPT Plus #2', 2, 'OpenAI ChatGPT Plus',  'gpt.plus2@rvc.ac.th',   'Gpt+Pool#2',   'maintenance',
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '23:59:00'),   TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 40 DAY), '09:00:00'), 'monthly');

-- Bookings for สมชาย (user id 2), dates relative to import time so "upcoming" stays upcoming.
-- Completed ones carry a usage report so สมชาย is NOT blocked; นภา (user 7) has one overdue
-- unreported booking to demo the "report within 7 days or get blocked" rule.
INSERT INTO bookings (user_id, ai_account_id, booking_date, slot_index, start_datetime, end_datetime, status, purpose, report_text, reported_at, cancelled_at) VALUES
(2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY),  0, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00'),  TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '13:00:00'),  'upcoming',  'ทำโปรเจกต์รายวิชา AI',            NULL, NULL, NULL),
(2, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:00:00'),  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '18:00:00'),  'upcoming',  'ค้นคว้าข้อมูลวิทยานิพนธ์',        'ใช้ค้นคว้าและสรุป references ได้ 12 แหล่ง', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:00:00'), NULL),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '18:00:00'),  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '23:00:00'),  'upcoming',  'เขียนและดีบักโค้ดโปรเจกต์',      'ดีบักโมดูล backend เสร็จเรียบร้อย',        TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:00:00'), NULL),
(2, 4, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 0, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '13:00:00'), 'upcoming',  'ทดสอบโมเดลและปรับ prompt',        'ทดสอบ prompt engineering 30 รูปแบบ',        TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '14:00:00'), NULL),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 13 DAY), 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '13:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '18:00:00'), 'cancelled', 'อ่านและสรุปงานวิจัย',            NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '12:00:00')),
(7, 1, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '18:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '23:00:00'), 'upcoming',  'สรุปรายงานกลุ่มวิชาสัมมนา',      NULL, NULL, NULL);
