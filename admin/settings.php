<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? 'institution_name';
    if ($action === 'sso_verify_ip') {
        $result = SlotSettings::updateSsoVerifyIp($_POST['sso_verify_ip'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกการตั้งค่า ONE-RVC เรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    } else {
        $result = SlotSettings::updateInstitutionName($_POST['institution_name'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกชื่อสถานศึกษาเรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    }
    header('Location: ' . url('admin/settings.php'));
    exit;
}

$settings = SlotSettings::get();

$activeNav = 'system-settings';
require __DIR__ . '/../includes/header.php';
?>
<h5 style="font-weight:700;margin:0 0 20px">ตั้งค่าระบบ</h5>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:24px;max-width:700px">
  <h6 style="font-weight:700;margin:0 0 14px">ข้อมูลสถานศึกษา</h6>
  <form method="post">
    <?= Csrf::field() ?>
    <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">ชื่อสถานศึกษา</label>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <input name="institution_name" class="form-control" value="<?= e($settings['institution_name']) ?>" required maxlength="200" placeholder="วิทยาลัย RVC" style="flex:1;min-width:260px;font-size:13px">
      <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px;white-space:nowrap"><i class="bi bi-save me-1"></i>บันทึก</button>
    </div>
    <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:6px">แสดงบนหน้าเข้าสู่ระบบและหน้าแรกของระบบ</div>
  </form>
</div>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:24px;max-width:700px;margin-top:16px">
  <h6 style="font-weight:700;margin:0 0 14px">การเชื่อมต่อ ONE-RVC SSO</h6>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="sso_verify_ip">
    <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">Private IP สำหรับติดต่อ verify-token endpoint</label>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <input name="sso_verify_ip" class="form-control" value="<?= e($settings['sso_verify_ip'] ?? '') ?>" maxlength="45" placeholder="เช่น 192.168.10.121 (เว้นว่างเพื่อใช้ชื่อโดเมนตามปกติ)" style="flex:1;min-width:260px;font-size:13px">
      <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px;white-space:nowrap"><i class="bi bi-save me-1"></i>บันทึก</button>
    </div>
    <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:6px">
      ใช้เมื่อเซิร์ฟเวอร์ของระบบนี้เข้าถึงโดเมนของ ONE-RVC โดยตรงไม่ได้ (เช่นอยู่บน internal bridge network เดียวกัน) —
      ระบบจะยังคงใช้ชื่อโดเมนเดิมใน URL/Host header ตามปกติ เพียงแต่เชื่อมต่อ TCP ไปยัง IP นี้แทนการ resolve ชื่อโดเมน
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
