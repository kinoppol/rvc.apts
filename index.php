<?php
require_once __DIR__ . '/bootstrap.php';

$user = current_user();
if (!$user) {
    header('Location: ' . url('login.php'));
    exit;
}
header('Location: ' . url($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
exit;
