<?php
require_once __DIR__ . '/bootstrap.php';

if (is_impersonating()) {
    $adminId = (int) $_SESSION['admin_id'];
    unset($_SESSION['impersonating'], $_SESSION['admin_id']);
    $_SESSION['user_id'] = $adminId;
    session_regenerate_id(true);
    flash_set('ok', 'คืนสิทธิ์ Admin เรียบร้อยแล้ว');
    header('Location: ' . url('admin/members.php'));
    exit;
}

$_SESSION = [];
session_destroy();

header('Location: ' . url('login.php'));
exit;
