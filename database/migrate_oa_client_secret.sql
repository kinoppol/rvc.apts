-- Admin-settable client_secret for the ONE-RVC User-sync API (admin/user-sync.php).
-- Takes precedence over config.local.php 'one_rvc_client_secret' / env OA_CLIENT_SECRET.
-- Write-only from the UI: the page never echoes it back. See includes/OaUserSync.php.
ALTER TABLE slot_settings ADD COLUMN IF NOT EXISTS oa_client_secret VARCHAR(255) NULL DEFAULT NULL AFTER sso_verify_ip;
