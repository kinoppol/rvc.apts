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

unset($_localCfg);
