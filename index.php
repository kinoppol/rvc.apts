<?php
require_once __DIR__ . '/bootstrap.php';

// sso-login.php stashes a `state` in session right before sending the browser to
// ONE-RVC. If that state is still sitting here unused, the SSO round trip started but
// never reached api/callback.php with a token — in practice this is ONE-RVC bouncing
// straight back without ever showing its login screen (e.g. no live ONE-RVC session),
// which lands the browser on this app's own root with nothing to show for it. Without
// this check that looks exactly like "the button did nothing"; say so instead.
if (!empty($_SESSION['sso_state'])) {
    $wasLinking = !empty($_SESSION['sso_link_user_id']);
    $linkUserId = $wasLinking ? (int) $_SESSION['sso_link_user_id'] : null;
    unset($_SESSION['sso_state'], $_SESSION['sso_link_user_id']);

    flash_set('err', 'ดูเหมือนคุณยังไม่ได้ลงชื่อเข้าใช้งานระบบ ONE-RVC หรือการยืนยันตัวตนไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');

    $activeUser = current_user();
    if ($wasLinking && $activeUser && (int) $activeUser['id'] === $linkUserId) {
        header('Location: ' . url($activeUser['role'] === 'admin' ? 'admin/profile.php' : 'student/profile.php'));
    } else {
        header('Location: ' . url('login.php'));
    }
    exit;
}

$user = current_user();
if ($user) {
    header('Location: ' . url($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
}

$institutionName = SlotSettings::get()['institution_name'] ?? 'วิทยาลัย RVC';

$studentFeatures = [
    ['icon' => 'bi-calendar2-check', 'title' => 'จองคิวใช้งาน AI Pro', 'desc' => 'เลือกวันและช่วงเวลาที่ต้องการ แล้วเลือก AI Pool ที่กลุ่มของคุณมีสิทธิ์ใช้งาน (Claude Pro, ChatGPT Plus)'],
    ['icon' => 'bi-box-arrow-in-right', 'title' => 'เช็คอิน / เช็คเอาท์', 'desc' => 'เช็คอินก่อนเริ่มใช้งานได้ล่วงหน้า และเช็คเอาท์ก่อนเวลาเพื่อคืนสิทธิ์ให้ผู้อื่นจองต่อ'],
    ['icon' => 'bi-journal-text', 'title' => 'รายงานผลการใช้งาน', 'desc' => 'สรุปผลการใช้งานพร้อมแนบไฟล์หรือรูปภาพได้ภายใน 7 วันหลังจบคาบ'],
    ['icon' => 'bi-clock-history', 'title' => 'ติดตามประวัติการจอง', 'desc' => 'ดูสถานะการจองทั้งหมดแบบเรียลไทม์ ว่าง ไม่ว่าง หรือกำลังใช้งานอยู่'],
];
$adminFeatures = [
    ['icon' => 'bi-people', 'title' => 'จัดการสมาชิก', 'desc' => 'อนุมัติสมาชิกใหม่ กำหนดกลุ่ม และรีเซ็ตรหัสผ่านให้นักศึกษา/อาจารย์'],
    ['icon' => 'bi-diagram-3', 'title' => 'จัดการกลุ่มและสิทธิ์', 'desc' => 'กำหนด AI Pool ที่แต่ละกลุ่มเข้าถึงได้ พร้อมโควต้าและจำนวนจองพร้อมกัน'],
    ['icon' => 'bi-hdd-stack', 'title' => 'จัดการบัญชี AI Pro', 'desc' => 'เพิ่ม/แก้ไขบัญชี Claude Pro, ChatGPT Plus พร้อมวันหมดอายุและรหัสผ่านที่ใช้ร่วมกัน'],
    ['icon' => 'bi-graph-up', 'title' => 'ปฏิทินและรายงานสรุป', 'desc' => 'ดูภาพรวมการจองทั้งหมดในปฏิทิน และส่งออกรายงานการใช้งานเป็นไฟล์ CSV'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Pro Time-Sharing — <?= e($institutionName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= asset('assets/favicon.svg') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('assets/app.css') ?>" rel="stylesheet">
<style>
.landing-hero{background:linear-gradient(140deg,#1D4ED8 0%,#2563EB 50%,#0EA5E9 100%);color:white;padding:80px 24px 96px}
.landing-nav{display:flex;align-items:center;justify-content:space-between;max-width:1080px;margin:0 auto;padding:20px 24px}
.landing-hero-inner{max-width:760px;margin:0 auto;text-align:center}
.landing-section{max-width:1080px;margin:-56px auto 0;padding:0 24px 80px}
.feature-card{background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:14px;padding:22px;height:100%;box-shadow:0 4px 16px rgba(0,0,0,.04)}
.landing-footer{border-top:1px solid var(--bs-border-color);padding:24px;text-align:center;font-size:13px;color:var(--bs-secondary-color)}
</style>
</head>
<body>

<div class="landing-hero">
  <div class="landing-nav">
    <div style="display:flex;align-items:center;gap:10px">
      <div class="logo-icon" style="width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.18)">
        <i class="bi bi-robot" style="color:white;font-size:17px"></i>
      </div>
      <span style="font-weight:700;font-size:16px">AI Pro Time-Sharing</span>
    </div>
    <div style="display:flex;gap:8px">
      <a href="<?= url('login.php') ?>" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:white;font-weight:600;padding:8px 18px">เข้าสู่ระบบ</a>
      <a href="<?= url('register.php') ?>" class="btn btn-sm" style="background:white;color:#1D4ED8;font-weight:600;padding:8px 18px">สมัครสมาชิก</a>
    </div>
  </div>
  <div class="landing-hero-inner page-anim">
    <h1 style="font-weight:700;font-size:34px;margin:32px 0 12px">ระบบจองคิวใช้งาน AI Pro<br><?= e($institutionName) ?></h1>
    <p style="font-size:15px;opacity:.92;margin:0 0 28px;line-height:1.7">
      บริหารจัดการการใช้งานบัญชี AI Pro ร่วมกัน (Claude Pro, ChatGPT Plus) ระหว่างนักศึกษาและอาจารย์
      อย่างเป็นระบบ จองล่วงหน้า เช็คอิน-เช็คเอาท์ และติดตามการใช้งานได้ในที่เดียว
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="<?= url('login.php') ?>" class="btn" style="background:white;color:#1D4ED8;font-weight:600;padding:11px 26px">
        <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
      </a>
      <a href="<?= url('register.php') ?>" class="btn" style="background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.5);color:white;font-weight:600;padding:11px 26px">
        <i class="bi bi-person-plus me-2"></i>สมัครสมาชิก
      </a>
    </div>
  </div>
</div>

<div class="landing-section">
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 12px 40px rgba(0,0,0,.08);padding:32px" >
    <div style="text-align:center;margin-bottom:8px">
      <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#2563EB">สำหรับนักศึกษาและอาจารย์</div>
    </div>
    <div class="row g-3" style="margin-top:6px">
      <?php foreach ($studentFeatures as $f): ?>
        <div class="col-md-6 col-lg-3">
          <div class="feature-card">
            <div class="stat-icon" style="background:#EFF6FF;color:#2563EB;margin-bottom:12px"><i class="bi <?= $f['icon'] ?>"></i></div>
            <div style="font-weight:700;font-size:14px;margin-bottom:6px"><?= e($f['title']) ?></div>
            <div style="font-size:12.5px;color:var(--bs-secondary-color);line-height:1.6"><?= e($f['desc']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin:36px 0 8px">
      <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#059669">สำหรับผู้ดูแลระบบ</div>
    </div>
    <div class="row g-3" style="margin-top:6px">
      <?php foreach ($adminFeatures as $f): ?>
        <div class="col-md-6 col-lg-3">
          <div class="feature-card">
            <div class="stat-icon" style="background:#ECFDF5;color:#059669;margin-bottom:12px"><i class="bi <?= $f['icon'] ?>"></i></div>
            <div style="font-weight:700;font-size:14px;margin-bottom:6px"><?= e($f['title']) ?></div>
            <div style="font-size:12.5px;color:var(--bs-secondary-color);line-height:1.6"><?= e($f['desc']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="landing-footer">
  AI Pro Time-Sharing — <?= e($institutionName) ?> ·
  <a href="<?= url('login.php') ?>" style="color:#2563EB;text-decoration:none;font-weight:600">เข้าสู่ระบบ</a> ·
  <a href="<?= url('register.php') ?>" style="color:#2563EB;text-decoration:none;font-weight:600">สมัครสมาชิก</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
