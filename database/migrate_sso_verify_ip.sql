-- Admin-configurable private-IP override for the server-to-server ONE-RVC token-verify
-- call. The production app server and the ONE-RVC gateway sit on the same internal
-- bridge network where the public workspace.rvc.ac.th domain does not resolve/route
-- from server-side code — only from a visitor's own browser. See includes/SsoAuth.php.
ALTER TABLE slot_settings ADD COLUMN IF NOT EXISTS sso_verify_ip VARCHAR(45) NULL DEFAULT NULL AFTER institution_name;
