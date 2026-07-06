<?php
// Temporary diagnostic — DELETE THIS FILE after identifying APP_BASE.
header('Content-Type: text/plain; charset=utf-8');
$vars = [
    'DOCUMENT_ROOT'   => $_SERVER['DOCUMENT_ROOT']   ?? '(not set)',
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? '(not set)',
    'SCRIPT_NAME'     => $_SERVER['SCRIPT_NAME']     ?? '(not set)',
    'REQUEST_URI'     => $_SERVER['REQUEST_URI']      ?? '(not set)',
    'REDIRECT_URL'    => $_SERVER['REDIRECT_URL']     ?? '(not set)',
    'PHP_SELF'        => $_SERVER['PHP_SELF']         ?? '(not set)',
    '__DIR__'         => __DIR__,
    '__FILE__'        => __FILE__,
];
foreach ($vars as $k => $v) {
    echo str_pad($k, 20) . " = $v\n";
}
echo "\nAll REDIRECT_* vars:\n";
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'REDIRECT_')) {
        echo "  $k = $v\n";
    }
}
