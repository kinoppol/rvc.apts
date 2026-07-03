<?php
/**
 * Shared app shell (navbar + sidebar) for all authenticated pages.
 * Expects $activeNav to be set by the including page and current_user() to be available.
 */
$__user = current_user();
$__isAdmin = $__user['role'] === 'admin';
$__pendingCount = $__isAdmin ? Member::pendingCount() : 0;
$__initial = mb_substr($__user['name'], 0, 1);
$__roleLabel = $__isAdmin ? 'Admin' : 'นักศึกษา';

function nav_cls(string $key, ?string $active): string
{
    return 'sb-link' . ($active === $key ? ' active' : '');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Pro Time-Sharing — RVC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/app.css') ?>" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-body">
<div id="appRoot" data-bs-theme="light" style="min-height:100vh;display:flex;flex-direction:column">

  <nav class="navbar navbar-expand bg-body" style="height:56px;padding:0 16px;position:sticky;top:0;z-index:200;border-bottom:1px solid var(--bs-border-color)">
    <div style="display:flex;align-items:center;gap:10px;flex:1">
      <button id="sidebarToggle" type="button" style="border:none;background:none;cursor:pointer;color:var(--bs-secondary-color);width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px">
        <i class="bi bi-list"></i>
      </button>
      <div class="logo-icon"><i class="bi bi-robot" style="color:white;font-size:14px"></i></div>
      <span style="font-weight:700;font-size:15px;white-space:nowrap">AI Pro <span style="color:#2563EB">Time-Sharing</span></span>
    </div>
    <div style="display:flex;align-items:center;gap:6px">
      <div style="display:flex;background:var(--bs-secondary-bg);border-radius:8px;padding:3px;gap:2px">
        <button type="button" class="theme-btn" data-theme="light" title="Light" style="border:none;cursor:pointer;width:30px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:14px"><i class="bi bi-sun-fill"></i></button>
        <button type="button" class="theme-btn" data-theme="system" title="System" style="border:none;cursor:pointer;width:30px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:14px"><i class="bi bi-display"></i></button>
        <button type="button" class="theme-btn" data-theme="dark" title="Dark" style="border:none;cursor:pointer;width:30px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:14px"><i class="bi bi-moon-fill"></i></button>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-left:4px">
        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#0EA5E9);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px"><?= e($__initial) ?></div>
        <div style="line-height:1.2">
          <div style="font-size:13px;font-weight:600"><?= e($__user['name']) ?></div>
          <div style="font-size:11px;color:#64748B"><?= e($__roleLabel) ?></div>
        </div>
        <a href="<?= url('logout.php') ?>" class="btn btn-sm" style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:12px;padding:4px 10px;margin-left:4px"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </nav>

  <div style="display:flex;flex:1;overflow:hidden">
    <aside class="sidebar" id="sidebar" style="background:var(--bs-body-bg)">
      <div style="padding:8px 0">
        <?php if (!$__isAdmin): ?>
          <div class="section-title"><span class="sb-label">เมนูนักศึกษา</span></div>
          <a class="<?= nav_cls('student-dashboard', $activeNav ?? null) ?>" href="<?= url('student/dashboard.php') ?>">
            <i class="bi bi-speedometer2" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">หน้าหลัก</span>
          </a>
          <a class="<?= nav_cls('booking', $activeNav ?? null) ?>" href="<?= url('student/booking.php') ?>">
            <i class="bi bi-calendar-plus" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">จองคิว AI</span>
          </a>
          <a class="<?= nav_cls('my-bookings', $activeNav ?? null) ?>" href="<?= url('student/my-bookings.php') ?>">
            <i class="bi bi-journal-check" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">การจองของฉัน</span>
          </a>
          <a class="<?= nav_cls('profile', $activeNav ?? null) ?>" href="<?= url('student/profile.php') ?>">
            <i class="bi bi-person-circle" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">โปรไฟล์</span>
          </a>
        <?php else: ?>
          <div class="section-title"><span class="sb-label">ผู้ดูแลระบบ</span></div>
          <a class="<?= nav_cls('admin-dashboard', $activeNav ?? null) ?>" href="<?= url('admin/dashboard.php') ?>">
            <i class="bi bi-bar-chart-line" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">ภาพรวมระบบ</span>
          </a>
          <a class="<?= nav_cls('member-management', $activeNav ?? null) ?>" href="<?= url('admin/members.php') ?>">
            <i class="bi bi-people" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">จัดการสมาชิก</span>
            <?php if ($__pendingCount > 0): ?><span style="background:#EF4444;color:white;border-radius:10px;font-size:10px;font-weight:700;padding:1px 6px;margin-left:auto"><?= (int) $__pendingCount ?></span><?php endif; ?>
          </a>
          <a class="<?= nav_cls('slot-management', $activeNav ?? null) ?>" href="<?= url('admin/slots.php') ?>">
            <i class="bi bi-calendar-range" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">จัดการตารางเวลา</span>
          </a>
          <a class="<?= nav_cls('ai-accounts', $activeNav ?? null) ?>" href="<?= url('admin/ai-accounts.php') ?>">
            <i class="bi bi-cpu" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">บัญชี AI Pool</span>
          </a>
          <a class="<?= nav_cls('reports', $activeNav ?? null) ?>" href="<?= url('admin/reports.php') ?>">
            <i class="bi bi-file-earmark-bar-graph" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">รายงานสถิติ</span>
          </a>
        <?php endif; ?>
      </div>
    </aside>

    <main style="flex:1;overflow-y:auto;padding:24px;background:var(--bs-secondary-bg)">
      <div class="page-anim">
