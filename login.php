<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    header('Location: ' . url('index.php'));
    exit;
}

$error = null;
$notice = null;

$allowPassword = SlotSettings::allowsPasswordLogin();
$allowSso      = SlotSettings::allowsSsoLogin();
// SSO-only mode hides the password form, except behind ?admin=1 — the admin's way in
// when ONE-RVC is down (Auth::attempt() rejects non-admins in that mode regardless).
$adminFallback = !$allowPassword && isset($_GET['admin']);
$showPassword  = $allowPassword || $adminFallback;

// api/callback.php redirects failures/successes here via flash — this guest shell has
// no toast system (that's authenticated-only, see includes/footer.php), so fold the
// flash into the same inline alerts the password form already uses.
$ssoFlash = flash_get();
if ($ssoFlash) {
    if ($ssoFlash['type'] === 'err') {
        $error = $ssoFlash['msg'];
    } else {
        $notice = $ssoFlash['msg'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showPassword) {
    Csrf::check();
    $result = Auth::attempt(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
    if ($result['ok']) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $result['user']['id'];
        header('Location: ' . url($result['user']['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
        exit;
    }
    $error = $result['error'];
}

require __DIR__ . '/includes/guest-header.php';
?>
<div class="auth-card page-anim">
  <div style="text-align:center;margin-bottom:28px">
    <div class="logo-icon" style="width:56px;height:56px;border-radius:14px;margin:0 auto 12px">
      <i class="bi bi-robot" style="color:white;font-size:26px"></i>
    </div>
    <h4 style="font-weight:700;color:#0F172A;margin:0">AI Pro Time-Sharing</h4>
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:6px 0 0">ระบบจองคิวใช้งาน AI Pro — <?= e($__institutionName) ?></p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger" style="font-size:13px"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($notice): ?>
    <div class="alert alert-success" style="font-size:13px"><?= e($notice) ?></div>
  <?php endif; ?>

  <?php if ($allowSso): ?>
  <a href="<?= url('sso-login.php') ?>" class="btn w-100" style="background:#0F172A;color:#fff;font-weight:600;padding:11px;margin-bottom:14px;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none">
    <i class="bi bi-shield-lock"></i>ลงชื่อเข้าใช้ผ่านระบบ RVC-ONE
  </a>
  <?php endif; ?>
  <?php if ($allowSso && $showPassword): ?>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
    <hr style="flex:1;margin:0;border-color:var(--bs-border-color)"><span style="font-size:12px;color:var(--bs-secondary-color)"><?= $adminFallback ? 'ผู้ดูแลระบบ' : 'หรือ' ?></span><hr style="flex:1;margin:0;border-color:var(--bs-border-color)">
  </div>
  <?php endif; ?>

  <?php if ($showPassword): ?>
  <form method="post">
    <?= Csrf::field() ?>
    <div style="margin-bottom:14px">
      <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px">อีเมล</label>
      <input type="email" name="email" required class="form-control" placeholder="student@rvc.ac.th" style="font-size:14px">
    </div>
    <div style="margin-bottom:22px">
      <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px">รหัสผ่าน</label>
      <input type="password" name="password" required class="form-control" placeholder="••••••••" style="font-size:14px">
    </div>
    <button type="submit" class="btn btn-primary w-100" style="background:linear-gradient(90deg,#2563EB,#0EA5E9);border:none;font-weight:600;padding:11px;margin-bottom:18px">
      <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
    </button>
  </form>
  <?php endif; ?>
  <?php if ($allowPassword): ?>
  <p style="text-align:center;font-size:13px;color:var(--bs-secondary-color);margin:0">
    ยังไม่มีบัญชี? <a href="<?= url('register.php') ?>" style="color:#2563EB;font-weight:600;text-decoration:none">สมัครสมาชิก</a>
  </p>
  <?php elseif ($adminFallback): ?>
  <p style="text-align:center;font-size:12px;margin:0">
    <a href="<?= url('login.php') ?>" style="color:var(--bs-secondary-color);text-decoration:none"><i class="bi bi-arrow-left me-1"></i>กลับไปเข้าสู่ระบบผ่าน ONE-RVC</a>
  </p>
  <?php else: ?>
  <p style="text-align:center;font-size:12px;margin:4px 0 0">
    <a href="<?= url('login.php') ?>?admin=1" style="color:var(--bs-secondary-color);text-decoration:none"><i class="bi bi-key me-1"></i>ผู้ดูแลระบบ: เข้าสู่ระบบด้วยรหัสผ่าน</a>
  </p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/guest-footer.php'; ?>
