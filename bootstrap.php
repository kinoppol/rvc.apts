<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

// ONE-RVC's callback is the user's own browser doing a top-level cross-site POST
// (an auto-submitted form on workspace.rvc.ac.th, action=our callback URL) back to
// api/callback.php. Browsers only attach a SameSite=Lax cookie (PHP's default) to a
// cross-site *GET* navigation, never a POST — so without this, our session cookie
// (and the CSRF-equivalent `state` value stored in it) would silently not arrive at
// the callback in production, and every SSO login would fail state validation.
// SameSite=None requires Secure, hence the HTTPS check; local HTTP dev falls back to
// the normal Lax default (a real round trip to the external gateway can't be tested
// from localhost anyway, since ONE-RVC only ever redirects back to the registered
// production redirect_uri).
$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $__isHttps,
    'httponly' => true,
    'samesite' => $__isHttps ? 'None' : 'Lax',
]);
unset($__isHttps);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Csrf.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SsoAuth.php';
require_once __DIR__ . '/includes/SlotSettings.php';
require_once __DIR__ . '/includes/Booking.php';
require_once __DIR__ . '/includes/Member.php';
require_once __DIR__ . '/includes/UserGroup.php';
require_once __DIR__ . '/includes/AiProvider.php';
require_once __DIR__ . '/includes/AiAccount.php';
require_once __DIR__ . '/includes/Report.php';
require_once __DIR__ . '/includes/Major.php';
require_once __DIR__ . '/includes/Subject.php';
// LMS subsystem — ordered after its dependencies (UserGroup, Member) and before
// Notification, which derives its student/admin items from LmsProgress + LmsPromotion.
require_once __DIR__ . '/includes/LmsFile.php';
require_once __DIR__ . '/includes/LmsOrder.php';
require_once __DIR__ . '/includes/LmsLevel.php';
require_once __DIR__ . '/includes/LmsContent.php';
require_once __DIR__ . '/includes/LmsQuestion.php';
require_once __DIR__ . '/includes/LmsQuiz.php';
require_once __DIR__ . '/includes/LmsProgress.php';
require_once __DIR__ . '/includes/LmsPromotion.php';
require_once __DIR__ . '/includes/LmsSeeder.php';
require_once __DIR__ . '/includes/Notification.php';
require_once __DIR__ . '/includes/Migration.php';

// Compute APP_BASE (URL prefix of the project root) — three-level fallback:
//   1. Explicit override from config.local.php (set via install.php for unusual setups)
//   2. REQUEST_URI vs SCRIPT_FILENAME — most reliable: REQUEST_URI is the URL exactly as
//      the browser sent it (before any server rewrite/alias), so it always reflects the
//      real external path even when Apache Alias / RewriteRule hides the internal structure.
//   3. DOCUMENT_ROOT comparison — last resort (plain WAMP, no alias).
// Compute APP_BASE (URL prefix of the project root).
//
// The server uses a symlink:  /var/www/web -> /var/www/rvc.apts
// Apache resolves symlinks for SCRIPT_FILENAME → /var/www/web/login.php
// PHP realpath() resolves them for __DIR__     → /var/www/rvc.apts
//
// Strategy:
//   1. Explicit override from config.local.php (escape hatch for unusual setups).
//   2. Use SCRIPT_FILENAME (symlink-aware URL path) relative to DOCUMENT_ROOT to get
//      the URL directory, then ascend the same number of levels the current script sits
//      below the project root (computed via realpath to match __DIR__).
//   3. DOCUMENT_ROOT vs __DIR__ fallback (works on plain WAMP with no symlinks).
if (defined('APP_BASE_OVERRIDE') && APP_BASE_OVERRIDE !== '') {
    define('APP_BASE', rtrim(APP_BASE_OVERRIDE, '/'));
} else {
    $docRoot         = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $scriptFilename  = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $realProjectRoot = str_replace('\\', '/', __DIR__);

    // realpath() on SCRIPT_FILENAME resolves symlinks → comparable to __DIR__ convention.
    $realEntryScript = str_replace('\\', '/', (string) realpath($scriptFilename));

    // Depth of the entry script within the project (e.g. 'admin/slots.php' → depth 1).
    $relInProject = ($realEntryScript !== '' && str_starts_with($realEntryScript, $realProjectRoot . '/'))
        ? substr($realEntryScript, strlen($realProjectRoot) + 1)
        : '';

    // URL-relative path of the entry script from the document root (preserves symlink name).
    $relFromDocRoot = ($docRoot !== '' && str_starts_with($scriptFilename, $docRoot . '/'))
        ? substr($scriptFilename, strlen($docRoot) + 1)
        : '';

    if ($relInProject !== '' && $relFromDocRoot !== '') {
        // Start from the URL directory of the entry script, e.g. /web/admin.
        $base = dirname('/' . $relFromDocRoot);
        // Ascend one level per subdirectory the script is below the project root.
        $depth = count(array_filter(explode('/', dirname($relInProject)), fn($p) => $p !== '' && $p !== '.'));
        for ($i = 0; $i < $depth; $i++) {
            $base = dirname($base);
        }
        define('APP_BASE', $base === '/' ? '' : $base);
    } else {
        // Fallback: direct __DIR__ vs DOCUMENT_ROOT (correct when no symlinks involved).
        define('APP_BASE', rtrim(substr($realProjectRoot, strlen($docRoot)), '/'));
    }

    unset($docRoot, $scriptFilename, $realProjectRoot, $realEntryScript,
          $relInProject, $relFromDocRoot, $base, $depth, $i);
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

function require_role(string|array $role): array
{
    $user = require_login();
    $allowed = is_array($role) ? $role : [$role];
    if (!in_array($user['role'], $allowed, true)) {
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
