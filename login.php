<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    header('Location: ' . url('index.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:6px 0 0">ระบบจองคิวใช้งาน AI Pro — วิทยาลัย RVC</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger" style="font-size:13px"><?= e($error) ?></div>
  <?php endif; ?>

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
  <p style="text-align:center;font-size:13px;color:var(--bs-secondary-color);margin:0">
    ยังไม่มีบัญชี? <a href="<?= url('register.php') ?>" style="color:#2563EB;font-weight:600;text-decoration:none">สมัครสมาชิก</a>
  </p>
</div>
<?php require __DIR__ . '/includes/guest-footer.php'; ?>
