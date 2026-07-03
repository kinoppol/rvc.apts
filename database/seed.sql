-- Demo data mirroring the original prototype. Run after schema.sql.
-- All seeded accounts use the password: Passw0rd!
USE rvc_apts;

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

-- id 1-4: AI account pool
INSERT INTO ai_accounts (name, provider, status) VALUES
('Claude Pro #1',   'Anthropic Claude Pro', 'active'),
('Claude Pro #2',   'Anthropic Claude Pro', 'active'),
('ChatGPT Plus #1', 'OpenAI ChatGPT Plus',  'active'),
('ChatGPT Plus #2', 'OpenAI ChatGPT Plus',  'maintenance');

-- 5 bookings for สมชาย (user id 2), dates relative to import time so "upcoming" stays upcoming
INSERT INTO bookings (user_id, ai_account_id, booking_date, slot_index, start_datetime, end_datetime, status, cancelled_at) VALUES
(2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY),  0, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00'),  TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), '13:00:00'),  'upcoming',  NULL),
(2, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:00:00'),  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '18:00:00'),  'upcoming',  NULL),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '18:00:00'),  TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '23:00:00'),  'upcoming',  NULL),
(2, 4, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 0, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '13:00:00'), 'upcoming',  NULL),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 13 DAY), 1, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '13:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '18:00:00'), 'cancelled', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '12:00:00'));
