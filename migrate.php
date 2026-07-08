<?php
/**
 * CLI-only migration runner.
 * Usage: php migrate.php
 * Called automatically by the GitHub Actions deploy workflow.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("403 Forbidden — CLI only\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Migration.php';

echo "\n=== RVC APTS — Database Migrations ===\n\n";

$status  = Migration::status();
$pending = array_filter($status, fn ($m) => !$m['applied'] && $m['exists']);
$missing = array_filter($status, fn ($m) => !$m['applied'] && !$m['exists']);

if ($missing) {
    foreach ($missing as $m) {
        echo "  WARN  file not found: " . $m['filename'] . "\n";
    }
}

if (empty($pending)) {
    echo "  OK    No pending migrations — database is up to date.\n\n";
    exit(0);
}

echo "  " . count($pending) . " pending migration(s) to run:\n\n";

$results = Migration::runPending();
$exitCode = 0;

foreach ($results as $r) {
    if ($r['ok']) {
        echo "  OK    " . $r['file'] . "\n";
    } else {
        echo "  FAIL  " . $r['file'] . "\n";
        echo "        " . $r['error'] . "\n";
        $exitCode = 1;
    }
}

echo "\n";
if ($exitCode === 0) {
    echo "  All migrations applied successfully.\n\n";
} else {
    echo "  Migration failed — stopped at first error.\n\n";
}

exit($exitCode);
