<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $result = Auth::updateProfile($user['id'], $_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['phone'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    } elseif ($action === 'password') {
        $result = Auth::changePassword($user['id'], $_POST['current'] ?? '', $_POST['new'] ?? '', $_POST['new_confirm'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว' : ($result['error'] ?? 'เปลี่ยนรหัสผ่านไม่สำเร็จ'));
    }
    header('Location: ' . url('admin/profile.php'));
    exit;
}

$activeNav = 'admin-profile';
require __DIR__ . '/../includes/header.php';
?>
<h5 style="font-weight:700;margin:0 0 20px">โปรไฟล์ผู้ดูแลระบบ</h5>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:700px">
  <div class="card" style="grid-column:span 2;border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:20px;display:flex;align-items:center;gap:16px">
      <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#0EA5E9);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:28px;flex-shrink:0"><?= e(mb_substr($user['name'], 0, 1)) ?></div>
      <div>
        <div style="font-size:20px;font-weight:700"><?= e($user['name']) ?></div>
        <div style="color:var(--bs-secondary-color);font-size:13px"><?= e($user['email']) ?></div>
        <span class="badge-up" style="margin-top:6px;display:inline-block"><i class="bi bi-shield-lock me-1"></i>ผู้ดูแลระบบ</span>
      </div>
    </div>
  </div>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:20px">
      <h6 style="font-weight:700;margin:0 0 14px">ข้อมูลส่วนตัว</h6>
      <form method="post" style="display:flex;flex-direction:column;gap:12px">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="profile">
        <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ชื่อ-นามสกุล</label><input name="name" required class="form-control" value="<?= e($user['name']) ?>" style="font-size:13px"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">อีเมล</label><input type="email" name="email" required class="form-control" value="<?= e($user['email']) ?>" style="font-size:13px"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">เบอร์โทร</label><input name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="08X-XXX-XXXX" style="font-size:13px"></div>
        <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px">บันทึกข้อมูล</button>
      </form>
    </div>
  </div>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:20px">
      <h6 style="font-weight:700;margin:0 0 14px">เปลี่ยนรหัสผ่าน</h6>
      <form method="post" style="display:flex;flex-direction:column;gap:12px">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="password">
        <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">รหัสผ่านปัจจุบัน</label><input type="password" name="current" required class="form-control" placeholder="••••••••" style="font-size:13px"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">รหัสผ่านใหม่</label><input type="password" name="new" required class="form-control" placeholder="อย่างน้อย 8 ตัวอักษร" style="font-size:13px"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ยืนยันรหัสผ่านใหม่</label><input type="password" name="new_confirm" required class="form-control" placeholder="••••••••" style="font-size:13px"></div>
        <button type="submit" class="btn btn-outline-primary" style="font-size:13px">เปลี่ยนรหัสผ่าน</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
