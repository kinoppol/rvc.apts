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

unset($_localCfg);
