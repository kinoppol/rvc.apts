<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? 'institution_name';
    if ($action === 'sso_verify_ip') {
        $result = SlotSettings::updateSsoVerifyIp($_POST['sso_verify_ip'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกการตั้งค่า ONE-RVC เรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    } elseif ($action === 'min_chars') {
        $result = SlotSettings::updateMinChars((int) ($_POST['min_report_chars'] ?? 0), (int) ($_POST['min_mission_chars'] ?? 0));
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกความยาวขั้นต่ำเรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    } elseif ($action === 'type_add') {
        $result = AiProvider::add($_POST['type_name'] ?? '', $_POST['type_login_url'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'เพิ่มประเภท AI เรียบร้อยแล้ว' : ($result['error'] ?? 'เพิ่มประเภทไม่สำเร็จ'));
    } elseif ($action === 'type_rename') {
        $result = AiProvider::rename((int) ($_POST['type_id'] ?? 0), $_POST['type_name'] ?? '', $_POST['type_login_url'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'แก้ไขประเภท AI เรียบร้อยแล้ว' : ($result['error'] ?? 'แก้ไขไม่สำเร็จ'));
    } elseif ($action === 'type_delete') {
        $result = AiProvider::delete((int) ($_POST['type_id'] ?? 0));
        flash_set($result['ok'] ? 'warn' : 'err', $result['ok'] ? 'ลบประเภท AI เรียบร้อยแล้ว' : ($result['error'] ?? 'ลบไม่สำเร็จ'));
    } else {
        $result = SlotSettings::updateInstitutionName($_POST['institution_name'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกชื่อสถานศึกษาเรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    }
    header('Location: ' . url('admin/settings.php'));
    exit;
}

$settings = SlotSettings::get();
$typeRows  = AiProvider::listWithUsage();

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
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:24px;max-width:700px;margin-top:16px">
  <h6 style="font-weight:700;margin:0 0 14px"><i class="bi bi-text-paragraph me-2" style="color:#2563EB"></i>ความยาวข้อความขั้นต่ำ</h6>
  <form method="post">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="min_chars">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px">
      <div>
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">รายงานการใช้งาน (ตัวอักษร)</label>
        <input type="number" name="min_report_chars" class="form-control" value="<?= (int) ($settings['min_report_chars'] ?? 0) ?>" min="0" max="2000" style="font-size:13px">
        <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px">0 = ไม่กำหนดขั้นต่ำ</div>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">คำขอเลื่อนระดับ LMS (ตัวอักษร)</label>
        <input type="number" name="min_mission_chars" class="form-control" value="<?= (int) ($settings['min_mission_chars'] ?? 0) ?>" min="0" max="5000" style="font-size:13px">
        <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px">0 = ไม่กำหนดขั้นต่ำ</div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px"><i class="bi bi-save me-1"></i>บันทึก</button>
  </form>
</div>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:24px;max-width:700px;margin-top:16px">
  <h6 style="font-weight:700;margin:0 0 14px"><i class="bi bi-tags me-2" style="color:#2563EB"></i>ประเภท AI</h6>
  <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px">
    <?php foreach ($typeRows as $t): ?>
      <div style="padding:10px;border:1px solid var(--bs-border-color);border-radius:8px">
        <div style="display:flex;align-items:flex-start;gap:8px">
          <form method="post" style="display:flex;gap:6px;flex:1;margin:0;flex-wrap:wrap">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="type_rename">
            <input type="hidden" name="type_id" value="<?= (int) $t['id'] ?>">
            <div style="display:flex;gap:6px;width:100%">
              <input name="type_name" value="<?= e($t['name']) ?>" class="form-control form-control-sm" style="font-size:13px" title="ชื่อประเภท">
              <button type="submit" class="btn btn-sm btn-outline-primary" style="font-size:12px;white-space:nowrap" title="บันทึก"><i class="bi bi-check-lg"></i></button>
            </div>
            <div class="input-group input-group-sm" style="width:100%;margin-top:6px">
              <span class="input-group-text" style="font-size:11px"><i class="bi bi-box-arrow-up-right"></i></span>
              <input name="type_login_url" type="url" value="<?= e((string) ($t['login_url'] ?? '')) ?>"
                     class="form-control form-control-sm" style="font-size:12px"
                     placeholder="ลิงก์หน้าล็อกอิน เช่น https://claude.ai/login">
            </div>
          </form>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;padding-top:2px">
            <span style="font-size:11px;color:var(--bs-tertiary-color);white-space:nowrap"><?= (int) $t['usage'] ?> บัญชี</span>
            <form method="post" style="margin:0" onsubmit="return confirm('ลบประเภทนี้?')">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="type_delete">
              <input type="hidden" name="type_id" value="<?= (int) $t['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:12px"
                      <?= $t['usage'] > 0 ? 'disabled title="มีบัญชีใช้อยู่ ลบไม่ได้"' : '' ?>><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$typeRows): ?>
      <div style="text-align:center;color:var(--bs-tertiary-color);font-size:13px;padding:12px">ยังไม่มีประเภท</div>
    <?php endif; ?>
    <div style="font-size:11px;color:var(--bs-tertiary-color);line-height:1.7">
      <i class="bi bi-info-circle me-1"></i>ลิงก์หน้าล็อกอินจะแสดงเป็นปุ่มบนการ์ดข้อมูลบัญชีของผู้เรียนหลังเช็คอิน — เว้นว่างไว้หากไม่ต้องการให้มีปุ่ม
    </div>
  </div>
  <form method="post" style="border-top:1px solid var(--bs-border-color);padding-top:16px">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="type_add">
    <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">เพิ่มประเภทใหม่</label>
    <div style="display:flex;gap:8px;margin-bottom:8px">
      <input name="type_name" required class="form-control" placeholder="เช่น Google Gemini Advanced" style="font-size:13px">
      <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px;white-space:nowrap"><i class="bi bi-plus-lg me-1"></i>เพิ่ม</button>
    </div>
    <input name="type_login_url" type="url" class="form-control" style="font-size:12px"
           placeholder="ลิงก์หน้าล็อกอิน (ไม่บังคับ) เช่น https://chatgpt.com/">
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
