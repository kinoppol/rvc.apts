<?php
// Load local environment overrides first (written by install.php on production servers).
// This file is git-ignored so the WAMP dev defaults below always stay as the repo baseline.
$_localCfg = file_exists(__DIR__ . '/config.local.php') ? (require __DIR__ . '/config.local.php') : [];

// WAMP dev defaults — overridden by config.local.php on production.
// Note: WAMP's MariaDB listens on port 3307; standard Linux MySQL/MariaDB uses 3306.
define('DB_HOST',    $_localCfg['host']    ?? '127.0.0.1');
define('DB_PORT',    $_localCfg['port']    ?? '3307');
define('DB_NAME',    $_localCfg['name']    ?? 'rvc_apts');
define('DB_USER',    $_localCfg['user']    ?? 'root');
define('DB_PASS',    $_localCfg['pass']    ?? '');
define('DB_CHARSET', 'utf8mb4');

// Optional explicit URL base (e.g. '/web'). When set, bootstrap.php skips auto-detection.
// Needed when Apache/Nginx serves the app via an Alias or symlink that doesn't match __DIR__.
define('APP_BASE_OVERRIDE', $_localCfg['app_base'] ?? '');

// ONE-RVC single sign-on (the college's external SSO gateway). These four values are
// fixed by ONE-RVC's own client registration for this app (client_id "apts" and the
// exact redirect_uri it was registered with) — kept in one place per the house rule of
// never hardcoding an endpoint in more than one file. config.local.php can still
// override them (e.g. a genuinely different deployment), but there is normally no
// reason to: unlike DB_*, these are not environment-specific dev/prod values.
define('ONE_RVC_AUTH_URL',     $_localCfg['one_rvc_auth_url']     ?? 'http://workspace.rvc.ac.th/oa/index.php');
define('ONE_RVC_VERIFY_URL',   $_localCfg['one_rvc_verify_url']   ?? 'http://workspace.rvc.ac.th/oa/api/verify_token.php');
define('ONE_RVC_CLIENT_ID',    $_localCfg['one_rvc_client_id']    ?? 'apts');
define('ONE_RVC_REDIRECT_URI', $_localCfg['one_rvc_redirect_uri'] ?? 'https://apts.rvc.ac.th/web/api/callback.php');

// Optional: pin the *server-to-server* verify-token call to a private IP instead of
// resolving ONE_RVC_VERIFY_URL's hostname. This app's production box and the SSO
// gateway sit on the same internal bridge network, where the public
// workspace.rvc.ac.th domain is not reachable from server-side code (only from a
// visitor's own browser, which is what actually resolves it for the auth-redirect
// step). This is purely environment-specific network topology, so it has no
// hardcoded default here — set it in production's config.local.php
// ('one_rvc_verify_ip' => '192.168.10.121'). Leave unset for local dev, where no
// such override is needed (or possible to test against).
define('ONE_RVC_VERIFY_IP', $_localCfg['one_rvc_verify_ip'] ?? '');

// ONE-RVC User-sync API (server-to-server directory pull, see includes/OaUserSync.php).
// client_id is the same registered app id as the SSO client above. The secret is never
// committed: an admin sets it on admin/user-sync.php (stored in slot_settings, which
// wins), else 'one_rvc_client_secret' in config.local.php, else the OA_CLIENT_SECRET
// environment variable. An empty secret simply leaves the sync disabled.
// 'one_rvc_sync_type' = staff | student | all | '' ('' = every type this app is allowed).
define('ONE_RVC_USERS_URL',     $_localCfg['one_rvc_users_url']     ?? 'http://workspace.rvc.ac.th/oa/api/users.php');
define('ONE_RVC_CLIENT_SECRET', $_localCfg['one_rvc_client_secret'] ?? ((string) getenv('OA_CLIENT_SECRET')));
define('ONE_RVC_SYNC_TYPE',     $_localCfg['one_rvc_sync_type']     ?? '');

unset($_localCfg);
