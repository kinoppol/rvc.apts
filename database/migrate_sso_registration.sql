-- Separate "new sign-ups via SSO only" from the login-method choice, and add an optional
-- cutoff date after which accounts not linked to ONE-RVC can no longer sign in with a password.
-- allow_self_register : 0 = hide the email/password registration form; new accounts are created
--                       only on first ONE-RVC login (SsoAuth::provision). Login methods are
--                       unaffected — password login can still be allowed.
-- sso_required_from   : NULL = no requirement. Otherwise, from this date a non-admin account
--                       with no linked ONE-RVC identity is blocked from password login, and a
--                       reminder banner is shown to such users beforehand.
ALTER TABLE slot_settings ADD COLUMN IF NOT EXISTS allow_self_register TINYINT(1) NOT NULL DEFAULT 1 AFTER login_methods;
ALTER TABLE slot_settings ADD COLUMN IF NOT EXISTS sso_required_from DATE NULL AFTER allow_self_register;
