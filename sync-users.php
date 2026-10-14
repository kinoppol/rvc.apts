<?php
/**
 * CLI-only ONE-RVC directory sync (includes/OaUserSync.php).
 *
 *   php sync-users.php          incremental — only users changed since the last successful run
 *                               (the very first run is automatically a full sync)
 *   php sync-users.php --full   ignore the stored since and pull everything again
 *
 * Meant for cron, e.g. every 15 minutes:
 *   0,15,30,45 * * * * cd /var/www/rvc.apts && php sync-users.php >> /var/log/rvc-apts-sync.log 2>&1
 * Exit code 0 = ok, 1 = failed (the stored since is not advanced on failure).
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("403 Forbidden — CLI only\n");
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/SlotSettings.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SsoAuth.php';
require_once __DIR__ . '/includes/OaUserSync.php';

$full = in_array('--full', $argv, true);
$r    = OaUserSync::run($full);

printf("[%s] ONE-RVC user sync (%s): %s — pages=%d added=%d updated=%d suspended=%d linked=%d%s\n",
    date('Y-m-d H:i:s'), $r['mode'], $r['ok'] ? 'OK' : 'FAILED',
    $r['pages'], $r['added'], $r['updated'], $r['suspended'], $r['linked'],
    $r['ok'] ? ' next since=' . $r['server_time'] : ' error=' . $r['error']);

exit($r['ok'] ? 0 : 1);
