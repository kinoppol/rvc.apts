-- Make admin_messages two-way. direction = 'to_member' (admin → member, the original use)
-- or 'to_admin' (member → admins). For a 'to_admin' row, user_id is the sending member,
-- admin_id is NULL, and read_at marks when an admin has read it.
ALTER TABLE admin_messages ADD COLUMN IF NOT EXISTS direction ENUM('to_member','to_admin') NOT NULL DEFAULT 'to_member' AFTER admin_id;
