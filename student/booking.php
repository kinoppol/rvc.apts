<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('student');

$week = isset($_GET['week']) ? (int) $_GET['week'] : 0;
$week = max(0, min(8, $week));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $date = trim($_POST['booking_date'] ?? '');
    $slotIndex = (int) ($_POST['slot_index'] ?? -1);
    $result = Booking::create($user['id'], $date, $slotIndex, $_POST['purpose'] ?? '');
    if ($result['ok']) {
        flash_set('ok', 'จองคิวสำเร็จ! ระบบได้จัดสรร AI Account ให้เรียบร้อยแล้ว');
    } else {
        flash_set('err', $result['error'] ?? 'ไม่สามารถจองได้');
    }
    header('Location: ' . url('student/booking.php') . '?week=' . $week);
    exit;
}

$settings = Booking::limitsFor($user['id']);
$restricted = Booking::isRestricted($user['id']);
$pendingReports = Booking::pendingReportsForUser($user['id']);
$grid = Booking::getWeekGrid($user['id'], $week);
$weekLabel = Booking::getWeekLabel($week);
$quotaRemaining = Booking::weeklyQuotaRemaining($user['id']);

$activeNav = 'booking';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($restricted): ?>
  <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:20px;display:flex;gap:14px;align-items:flex-start;max-width:640px">
    <i class="bi bi-slash-circle-fill" style="color:#DC2626;font-size:22px;flex-shrink:0;margin-top:2px"></i>
    <div>
      <div style="font-weight:700;color:#991B1B;font-size:15px;margin-bottom:4px">บัญชีถูกระงับการจองชั่วคราว</div>
      <p style="color:#991B1B;font-size:13px;margin:0 0 12px">คุณมีรายงานการใช้งานค้างเกินกำหนด <?= Booking::REPORT_DEADLINE_DAYS ?> วัน จำนวน <?= (int) Booking::overdueCountForUser($user['id']) ?> รายการ กรุณารายงานการใช้งานที่ค้างให้ครบก่อน หรือแจ้งผู้ดูแลระบบ จึงจะจองได้อีกครั้ง</p>
      <a href="<?= url('student/my-bookings.php') ?>" class="btn btn-primary btn-sm" style="background:#DC2626;border:none"><i class="bi bi-journal-text me-1"></i>ไปรายงานการใช้งาน</a>
    </div>
  </div>
<?php require __DIR__ . '/../includes/footer.php'; exit; ?>
<?php endif; ?>
<?php if ($pendingReports): ?>
  <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#92400E;display:flex;gap:8px;align-items:center">
    <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0"></i>
    <span>คุณมีการใช้งานที่ยังไม่ได้รายงาน <strong><?= count($pendingReports) ?></strong> รายการ — <a href="<?= url('student/my-bookings.php') ?>" style="color:#92400E;font-weight:700">รายงานตอนนี้</a> ก่อนครบกำหนด <?= Booking::REPORT_DEADLINE_DAYS ?> วัน ไม่เช่นนั้นจะถูกระงับการจอง</span>
  </div>
<?php endif; ?>
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <h5 style="font-weight:700;margin:0">จองคิว AI Pro</h5>
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">คลิกช่วงเวลาสีฟ้าเพื่อจอง · โควต้าคงเหลือ <?= (int) $quotaRemaining ?>/<?= (int) $settings['weekly_quota'] ?> รอบ/สัปดาห์</p>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <a href="<?= url('student/booking.php') ?>?week=<?= max(0, $week - 1) ?>" class="btn btn-outline-secondary btn-sm<?= $week <= 0 ? ' disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
    <span style="font-size:13px;font-weight:600;white-space:nowrap"><?= e($weekLabel) ?></span>
    <a href="<?= url('student/booking.php') ?>?week=<?= min(8, $week + 1) ?>" class="btn btn-outline-secondary btn-sm<?= $week >= 8 ? ' disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
  </div>
</div>

<div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;font-size:12px">
  <div style="display:flex;align-items:center;gap:5px"><div style="width:12px;height:12px;border-radius:3px;background:#EFF6FF;border:1.5px solid #BFDBFE"></div>ว่าง (จองได้)</div>
  <div style="display:flex;align-items:center;gap:5px"><div style="width:12px;height:12px;border-radius:3px;background:#2563EB"></div>ของฉัน</div>
  <div style="display:flex;align-items:center;gap:5px"><div style="width:12px;height:12px;border-radius:3px;background:#059669"></div>กำลังใช้งาน</div>
  <div style="display:flex;align-items:center;gap:5px"><div style="width:12px;height:12px;border-radius:3px;background:#F1F5F9;border:1.5px solid #E2E8F0"></div>จองแล้ว</div>
  <div style="display:flex;align-items:center;gap:5px"><div style="width:12px;height:12px;border-radius:3px;border:1.5px dashed #CBD5E1"></div>ปิด</div>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:20px">
    <div style="display:flex;gap:6px;overflow-x:auto;min-width:0">
      <div style="flex-shrink:0;width:52px;padding-top:52px;display:flex;flex-direction:column;gap:6px">
        <?php for ($i = 0; $i < $settings['slots_per_day']; $i++): ?>
          <div style="height:74px;display:flex;flex-direction:column;align-items:flex-end;justify-content:center;gap:1px;padding-right:6px">
            <span style="font-size:10px;font-weight:600;color:var(--bs-secondary-color)"><?= e(SlotSettings::slotLabel($i)) ?></span>
            <span style="font-size:9px;color:var(--bs-tertiary-color)"><?= e(SlotSettings::slotStart($settings, $i)) ?></span>
            <span style="font-size:9px;color:var(--bs-tertiary-color)"><?= e(SlotSettings::slotEnd($settings, $i)) ?></span>
          </div>
        <?php endfor; ?>
      </div>
      <?php foreach ($grid as $day): ?>
        <div style="flex:1;min-width:80px;display:flex;flex-direction:column;gap:6px">
          <div style="height:48px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px">
            <span style="font-size:10px;font-weight:600;color:var(--bs-tertiary-color);letter-spacing:.05em"><?= e($day['dayName']) ?></span>
            <span class="<?= $day['todayCls'] ?>"><?= (int) $day['date'] ?></span>
          </div>
          <?php foreach ($day['slots'] as $slot): ?>
            <div class="<?= $slot['cls'] ?>"
                 <?php if ($slot['bookable']): ?>
                 data-date="<?= e($slot['date']) ?>"
                 data-slot-index="<?= (int) $slot['slotIndex'] ?>"
                 data-day-label="<?= e($slot['dateLabel']) ?>"
                 data-slot-label="<?= e($slot['label']) ?>"
                 data-slot-time="<?= e($slot['time']) ?>"
                 <?php endif; ?>>
              <i class="<?= $slot['iconCls'] ?>" style="font-size:13px;margin-bottom:2px"></i>
              <div style="font-size:10px;font-weight:600;line-height:1.2"><?= e($slot['label']) ?></div>
              <div style="font-size:9px;opacity:.7;margin-top:1px"><?= e($slot['statusText']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Booking confirmation modal -->
<div class="modal fade" id="bookSlotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
        <h6 class="modal-title" style="font-weight:700"><i class="bi bi-calendar-check me-2" style="color:#2563EB"></i>ยืนยันการจอง</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <form method="post" action="<?= url('student/booking.php') ?>?week=<?= $week ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="booking_date" id="bookModalDate">
        <input type="hidden" name="slot_index" id="bookModalSlotIndex">
        <div class="modal-body" style="padding:20px">
          <p style="font-size:13px;color:var(--bs-secondary-color);margin:0 0 14px">โปรดตรวจสอบรายละเอียดก่อนยืนยัน ระบบจะจัดสรร AI Account ที่ว่างให้โดยอัตโนมัติ</p>
          <div class="info-box">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="font-size:12px;color:var(--bs-secondary-color)">วันที่</span><span style="font-weight:600;font-size:13px" id="bookModalDayLabel">—</span></div>
            <div style="display:flex;justify-content:space-between"><span style="font-size:12px;color:var(--bs-secondary-color)">ช่วงเวลา</span><span style="font-weight:600;font-size:13px" id="bookModalSlotTime">—</span></div>
          </div>
          <div style="margin-top:14px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">วัตถุประสงค์การใช้งาน <span style="color:#DC2626">*</span></label>
            <textarea name="purpose" required rows="3" maxlength="500" class="form-control" placeholder="ระบุว่าจะใช้ AI ทำอะไร เช่น ทำโปรเจกต์รายวิชา, ค้นคว้าข้อมูลวิทยานิพนธ์, เขียนโค้ด..." style="font-size:13px"></textarea>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none"><i class="bi bi-check-circle me-1"></i>ยืนยันการจอง</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
