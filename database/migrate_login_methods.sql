-- Admin-chosen sign-in methods (admin/settings.php): both (default), password only, or
-- ONE-RVC SSO only. In SSO-only mode admins can still use password login (login.php?admin=1)
-- as a fallback for when ONE-RVC is down. See SlotSettings::loginMethods().
ALTER TABLE slot_settings ADD COLUMN IF NOT EXISTS login_methods ENUM('both','password','sso') NOT NULL DEFAULT 'both' AFTER oa_client_secret;
