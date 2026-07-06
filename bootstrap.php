<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Csrf.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SlotSettings.php';
require_once __DIR__ . '/includes/Booking.php';
require_once __DIR__ . '/includes/Member.php';
require_once __DIR__ . '/includes/UserGroup.php';
require_once __DIR__ . '/includes/AiProvider.php';
require_once __DIR__ . '/includes/AiAccount.php';
require_once __DIR__ . '/includes/Report.php';
require_once __DIR__ . '/includes/Notification.php';

// Compute APP_BASE (URL prefix of the project root) — three-level fallback:
//   1. Explicit override from config.local.php (set via install.php for unusual setups)
//   2. REQUEST_URI vs SCRIPT_FILENAME — most reliable: REQUEST_URI is the URL exactly as
//      the browser sent it (before any server rewrite/alias), so it always reflects the
//      real external path even when Apache Alias / RewriteRule hides the internal structure.
//   3. DOCUMENT_ROOT comparison — last resort (plain WAMP, no alias).
// Compute APP_BASE (URL prefix of the project root) — detection order:
//   1. Explicit override via config.local.php 'app_base' key (most reliable edge-case fix).
//   2. REDIRECT_URL  — set by Apache mod_rewrite to the *original* pre-rewrite URL.
//      When a RewriteRule fires it updates REQUEST_URI to the internal path but keeps the
//      original browser URL in REDIRECT_URL — exactly what we need.
//   3. REQUEST_URI   — correct when there is no rewrite (plain servers, WAMP).
//   4. DOCUMENT_ROOT — last resort fallback for WAMP-style setups.
if (defined('APP_BASE_OVERRIDE') && APP_BASE_OVERRIDE !== '') {
    define('APP_BASE', rtrim(APP_BASE_OVERRIDE, '/'));
} else {
    $projectRoot    = str_replace('\\', '/', __DIR__);
    $scriptFilename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $relScript      = str_starts_with($scriptFilename, $projectRoot . '/')
                        ? substr($scriptFilename, strlen($projectRoot) + 1)
                        : '';

    // Prefer REDIRECT_URL (original pre-rewrite URL) over REQUEST_URI (post-rewrite).
    $candidates = array_filter([
        $_SERVER['REDIRECT_URL'] ?? '',   // Apache mod_rewrite original URL
        $_SERVER['REQUEST_URI']  ?? '',   // raw request (no rewrite, or post-rewrite)
    ]);

    $detected = false;
    foreach ($candidates as $raw) {
        $p = parse_url($raw, PHP_URL_PATH) ?? '';
        if ($relScript !== '' && $p !== '' && str_ends_with($p, '/' . $relScript)) {
            define('APP_BASE', substr($p, 0, -strlen('/' . $relScript)));
            $detected = true;
            break;
        }
    }

    if (!$detected) {
        $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
        define('APP_BASE', rtrim(substr($projectRoot, strlen($docRoot)), '/'));
    }

    unset($projectRoot, $scriptFilename, $relScript, $candidates, $detected, $raw, $p, $docRoot);
}

function current_user(): ?array
{
    static $user = false; // false = not loaded yet, null = no session user
    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        $user = null;
        return $user;
    }
    $user = Auth::findById((int) $_SESSION['user_id']);
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . APP_BASE . '/login.php');
        exit;
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        header('Location: ' . APP_BASE . '/index.php');
        exit;
    }
    return $user;
}

function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function url(string $path): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

/** Versioned asset URL (?v=filemtime) so browsers re-fetch CSS/JS after every change instead of caching stale copies. */
function asset(string $path): string
{
    $full = __DIR__ . '/' . ltrim($path, '/');
    $url = url($path);
    return is_file($full) ? $url . '?v=' . filemtime($full) : $url;
}

function is_impersonating(): bool
{
    return !empty($_SESSION['impersonating']) && !empty($_SESSION['admin_id']);
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
