<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Csrf.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SlotSettings.php';
require_once __DIR__ . '/includes/Booking.php';
require_once __DIR__ . '/includes/Member.php';
require_once __DIR__ . '/includes/AiAccount.php';
require_once __DIR__ . '/includes/Report.php';

// URL prefix of the project root, so links work regardless of the WAMP vhost name.
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$projectDir = str_replace('\\', '/', __DIR__);
define('APP_BASE', rtrim(substr($projectDir, strlen($docRoot)), '/'));

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

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
