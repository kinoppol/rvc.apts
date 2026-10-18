<?php
/**
 * Shared app shell (navbar + sidebar) for all authenticated pages.
 * Expects $activeNav to be set by the including page and current_user() to be available.
 */
$__user = current_user();
$__isAdmin = $__user['role'] === 'admin';
$__pendingCount          = $__isAdmin ? Member::pendingCount() : 0;
$__lmsPending            = $__isAdmin ? LmsPromotion::pendingCount() : 0;
$__reportReviewPending   = $__isAdmin ? Booking::pendingReviewCount() : 0;
$__initial = mb_substr($__user['name'], 0, 1);
$__roleLabel = $__isAdmin ? 'Admin' : ('นักศึกษา' . (!empty($__user['group_name']) ? ' (กลุ่ม: ' . $__user['group_name'] . ')' : ''));
$__notifications = Notification::forUser($__user);
$__notifLevelColor = ['err' => '#DC2626', 'warn' => '#D97706', 'info' => '#2563EB', 'ok' => '#059669'];

function nav_cls(string $key, ?string $active): string
{
    return 'sb-link' . ($active === $key ? ' active' : '');
}
$__institutionName = SlotSettings::get()['institution_name'] ?? 'วิทยาลัย RVC';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Pro Time-Sharing — <?= e($__institutionName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset('assets/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('assets/app.css') ?>" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-body">
<div id="appRoot" data-bs-theme="light" style="min-height:100vh;display:flex;flex-direction:column;color:var(--bs-body-color);background:var(--bs-body-bg)">

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

      <?php $__notifCount = count($__notifications); ?>
      <div class="dropdown">
        <button type="button" id="notifBell" class="btn" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false" title="การแจ้งเตือน"
                style="position:relative;border:1px solid var(--bs-border-color);background:transparent;color:var(--bs-secondary-color);width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;padding:0">
          <i class="bi bi-bell<?= $__notifCount > 0 ? '-fill' : '' ?>"></i>
          <?php if ($__notifCount > 0): ?>
            <span style="position:absolute;top:-5px;right:-5px;min-width:17px;height:17px;padding:0 4px;background:#EF4444;color:#fff;border-radius:9px;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;line-height:1"><?= $__notifCount > 9 ? '9+' : $__notifCount ?></span>
          <?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow" style="width:340px;max-height:400px;overflow-y:auto;padding:0;border:1px solid var(--bs-border-color)">
          <div style="padding:12px 16px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between">
            <span style="font-weight:700;font-size:14px">การแจ้งเตือน</span>
            <?php if ($__notifCount > 0): ?><span style="font-size:11px;color:var(--bs-secondary-color)"><?= (int) $__notifCount ?> รายการ</span><?php endif; ?>
          </div>
          <?php if (!$__notifications): ?>
            <div style="padding:28px 16px;text-align:center;color:var(--bs-tertiary-color);font-size:13px">
              <i class="bi bi-check2-circle" style="font-size:24px;display:block;margin-bottom:8px"></i>
              ไม่มีการแจ้งเตือน
            </div>
          <?php else: ?>
            <?php foreach ($__notifications as $n): $__c = $__notifLevelColor[$n['level']] ?? '#2563EB'; ?>
              <a href="<?= e($n['url']) ?>" style="display:flex;gap:10px;padding:11px 16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--bs-border-color);align-items:flex-start">
                <span style="flex-shrink:0;width:30px;height:30px;border-radius:8px;background:<?= $__c ?>1a;display:flex;align-items:center;justify-content:center"><i class="bi <?= e($n['icon']) ?>" style="color:<?= $__c ?>;font-size:15px"></i></span>
                <span style="min-width:0">
                  <span style="display:block;font-size:13px;font-weight:600;line-height:1.35"><?= e($n['title']) ?></span>
                  <span style="display:block;font-size:11px;color:var(--bs-secondary-color);margin-top:2px"><?= e($n['detail']) ?></span>
                </span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:8px;margin-left:4px">
        <a href="<?= url($__isAdmin ? 'admin/profile.php' : 'student/profile.php') ?>" title="โปรไฟล์ของฉัน" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit">
          <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#0EA5E9);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px"><?= e($__initial) ?></div>
          <div style="line-height:1.2">
            <div style="font-size:13px;font-weight:600"><?= e($__user['name']) ?></div>
            <div style="font-size:11px;color:var(--bs-secondary-color)"><?= e($__roleLabel) ?></div>
          </div>
        </a>
        <a href="<?= url('logout.php') ?>" data-logout-confirm class="btn btn-sm" style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:12px;padding:4px 10px;margin-left:4px"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </nav>

  <?php if (is_impersonating()): ?>
  <div style="background:#D97706;color:white;padding:8px 20px;font-size:13px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-shrink:0">
    <span><i class="bi bi-person-badge me-2"></i>คุณกำลังดูในฐานะ <strong><?= e($__user['name']) ?></strong> (สิทธิ์นักศึกษา)</span>
    <a href="<?= url('logout.php') ?>" style="color:white;font-size:12px;text-decoration:none;border:1px solid rgba(255,255,255,.55);padding:3px 12px;border-radius:6px;white-space:nowrap"><i class="bi bi-arrow-return-left me-1"></i>คืนสิทธิ์ Admin</a>
  </div>
  <?php endif; ?>

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
          <a class="<?= nav_cls('lms', $activeNav ?? null) ?>" href="<?= url('student/lms.php') ?>">
            <i class="bi bi-journal-richtext" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">บทเรียน AI</span>
          </a>
          <a class="<?= nav_cls('profile', $activeNav ?? null) ?>" href="<?= url('student/profile.php') ?>">
            <i class="bi bi-person-circle" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label">โปรไฟล์</span>
          </a>
        <?php else: ?>
          <?php
            // [nav key, page, icon, label, badge count]; each section collapses/expands, the open
            // state is kept per browser in localStorage (see the script after </aside>).
            $__adminNav = [
                'overview' => ['ภาพรวม', [
                    ['admin-dashboard', 'admin/dashboard.php', 'bi-bar-chart-line', 'ภาพรวมระบบ', 0],
                    ['reports', 'admin/reports.php', 'bi-file-earmark-bar-graph', 'รายงานสถิติ', 0],
                ]],
                'booking' => ['การจอง', [
                    ['booking-management', 'admin/bookings.php', 'bi-calendar-check', 'จัดการการจอง', 0],
                    ['calendar', 'admin/calendar.php', 'bi-calendar3', 'ปฏิทินการจอง', 0],
                    ['report-review', 'admin/report-review.php', 'bi-clipboard2-check', 'ตรวจสอบรายงาน', $__reportReviewPending],
                    ['slot-management', 'admin/slots.php', 'bi-calendar-range', 'จัดการตารางเวลา', 0],
                    ['ai-accounts', 'admin/ai-accounts.php', 'bi-cpu', 'บัญชี AI Pool', 0],
                ]],
                'members' => ['สมาชิก', [
                    ['member-management', 'admin/members.php', 'bi-people', 'จัดการสมาชิก', $__pendingCount],
                    ['groups', 'admin/groups.php', 'bi-diagram-3', 'จัดการกลุ่มผู้ใช้', 0],
                    ['majors', 'admin/majors.php', 'bi-mortarboard', 'สาขา / วิชาสอน', 0],
                    ['user-sync', 'admin/user-sync.php', 'bi-arrow-repeat', 'ซิงค์ผู้ใช้ ONE-RVC', 0],
                ]],
                'lms' => ['บทเรียน AI', [
                    ['lms-content', 'admin/lms.php', 'bi-book', 'จัดการบทเรียน', 0],
                    ['lms-promotions', 'admin/lms-promotions.php', 'bi-patch-check', 'คำขอเลื่อนระดับ', $__lmsPending],
                ]],
                'system' => ['ระบบ', [
                    ['system-settings', 'admin/settings.php', 'bi-gear', 'ตั้งค่าระบบ', 0],
                    ['migrations', 'admin/migrations.php', 'bi-database-gear', 'DB Migrations', 0],
                    ['admin-profile', 'admin/profile.php', 'bi-person-circle', 'โปรไฟล์', 0],
                ]],
            ];
          ?>
          <?php foreach ($__adminNav as $__gKey => [$__gTitle, $__gItems]):
            $__gActive = in_array($activeNav ?? null, array_column($__gItems, 0), true);
            $__gBadge  = array_sum(array_column($__gItems, 4));
          ?>
            <div class="sb-group<?= $__gActive ? ' has-active' : '' ?>" data-group="<?= e($__gKey) ?>">
              <button type="button" class="section-title sb-group-toggle" aria-expanded="true">
                <span class="sb-label"><?= e($__gTitle) ?></span>
                <?php if ($__gBadge > 0): ?><span class="sb-group-dot sb-label" title="มีรายการรอดำเนินการ"></span><?php endif; ?>
                <i class="bi bi-chevron-down sb-group-chevron sb-label"></i>
              </button>
              <div class="sb-group-items"><div>
                <?php foreach ($__gItems as [$__key, $__page, $__icon, $__label, $__badge]): ?>
                  <a class="<?= nav_cls($__key, $activeNav ?? null) ?>" href="<?= url($__page) ?>">
                    <i class="bi <?= e($__icon) ?>" style="font-size:17px;flex-shrink:0;width:20px;text-align:center"></i><span class="sb-label"><?= e($__label) ?></span>
                    <?php if ($__badge > 0): ?><span style="background:#EF4444;color:white;border-radius:10px;font-size:10px;font-weight:700;padding:1px 6px;margin-left:auto"><?= (int) $__badge ?></span><?php endif; ?>
                  </a>
                <?php endforeach; ?>
              </div></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>

    <main style="flex:1;overflow-y:auto;padding:24px;background:var(--bs-secondary-bg)">
      <div class="page-anim">
