<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $result = SlotSettings::update(
        (int) ($_POST['slot_hours'] ?? 0),
        (int) ($_POST['slots_per_day'] ?? 0),
        (int) ($_POST['weekly_quota'] ?? 0),
        (int) ($_POST['max_advance_days'] ?? 0),
        (string) ($_POST['day_start_time'] ?? '')
    );
    flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกการตั้งค่าเรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    header('Location: ' . url('admin/slots.php'));
    exit;
}

$settings = SlotSettings::get();

$activeNav = 'slot-management';
require __DIR__ . '/../includes/header.php';
?>
<h5 style="font-weight:700;margin:0 0 20px">จัดการตารางเวลา</h5>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:24px">
  <form method="post">
    <?= Csrf::field() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:600px">
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">ความยาวช่วงเวลา (ชั่วโมง)</label><input name="slot_hours" class="form-control" value="<?= (int) $settings['slot_hours'] ?>" type="number" min="1" max="24" required style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">จำนวน Slots/วัน</label><input name="slots_per_day" class="form-control" value="<?= (int) $settings['slots_per_day'] ?>" type="number" min="1" max="24" required style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">โควต้า/สัปดาห์/คน</label><input name="weekly_quota" class="form-control" value="<?= (int) $settings['weekly_quota'] ?>" type="number" min="1" required style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">จองล่วงหน้าสูงสุด (วัน)</label><input name="max_advance_days" class="form-control" value="<?= (int) $settings['max_advance_days'] ?>" type="number" min="1" required style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">เวลาเริ่มต้นของวัน</label><input name="day_start_time" class="form-control" value="<?= e(substr($settings['day_start_time'], 0, 5)) ?>" type="time" required style="font-size:13px"></div>
    </div>
    <div style="background:#FFF7ED;border-radius:8px;padding:10px 14px;margin-top:16px;font-size:12px;color:#92400E;display:flex;gap:8px;align-items:flex-start;max-width:600px">
      <i class="bi bi-info-circle-fill" style="margin-top:1px;flex-shrink:0"></i>
      <span>เวลาเริ่มต้นของวัน + (ความยาวช่วงเวลา × จำนวน Slots/วัน) ต้องไม่เกิน 24:00 น. · ช่วงเวลาแต่ละ slot จะถูกคำนวณจากเวลาเริ่มต้นนี้ · การเปลี่ยนแปลงมีผลกับการจองใหม่เท่านั้น</span>
    </div>
    <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;margin-top:16px;font-size:13px"><i class="bi bi-save me-1"></i>บันทึกการตั้งค่า</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
