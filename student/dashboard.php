<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('student');

$settings = Booking::limitsFor($user['id']);
$next = Booking::nextUpcomingForUser($user['id']);
$hoursUsed = Booking::weeklyHoursUsed($user['id']);
$weeklyMaxHours = $settings['weekly_quota'] * $settings['slot_hours'];
$quotaRemaining = Booking::weeklyQuotaRemaining($user['id']);

$allBookings = Booking::listForUser($user['id']);
$totalCount = count(array_filter($allBookings, fn ($b) => $b['displayStatus'] !== 'cancelled'));
$cancelledCount = count(array_filter($allBookings, fn ($b) => $b['displayStatus'] === 'cancelled'));
$totalHours = array_sum(array_map(fn ($b) => $b['displayStatus'] === 'completed' ? $settings['slot_hours'] : 0, $allBookings));
$utilization = count($allBookings) > 0 ? round($totalCount / count($allBookings) * 100, 1) : 0;
$recent = array_slice($allBookings, 0, 5);
$restricted = Booking::isRestricted($user['id']);
$pendingReports = Booking::pendingReportsForUser($user['id']);
$earlyAccess = Booking::earlyAccessForUser($user['id']);

$activeNav = 'student-dashboard';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($earlyAccess as $ea): ?>
<div style="background:#ECFDF5;border:1.5px solid #6EE7B7;border-radius:10px;padding:14px 16px;margin-bottom:16px">
  <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px">
    <i class="bi bi-lightning-charge-fill" style="color:#059669;font-size:17px;margin-top:1px;flex-shrink:0"></i>
    <div style="flex:1;min-width:0">
      <div style="font-size:14px;font-weight:700;color:#065F46">ใช้งาน <?= e($ea['ai_name']) ?> ได้เลยล่วงหน้า!</div>
      <div style="font-size:12px;color:#059669;margin-top:2px">ช่วง <?= e($ea['prevSlotLabel']) ?> ไม่มีผู้ใช้งาน · คุณสามารถเริ่มได้ทันทีจนถึงสิ้นสุด<?= e($ea['slotLabel']) ?></div>
    </div>
    <span class="badge-ok" style="flex-shrink:0">ช่วงก่อนหน้าว่าง</span>
  </div>
  <div style="background:white;border-radius:8px;padding:10px 14px;display:flex;gap:20px;align-items:center;flex-wrap:wrap;font-size:13px">
    <div><span style="font-size:12px;color:var(--bs-secondary-color)">อีเมลเข้าใช้: </span><strong><?= e($ea['ai_email']) ?></strong></div>
    <div style="display:flex;align-items:center;gap:6px">
      <span style="font-size:12px;color:var(--bs-secondary-color)">รหัสผ่าน:</span>
      <code id="eaPw<?= (int) $ea['id'] ?>" style="font-size:13px;letter-spacing:0.12em;background:transparent">••••••••</code>
      <button type="button" class="btn btn-sm" style="font-size:11px;padding:2px 8px;border:1px solid var(--bs-border-color);border-radius:5px"
        onclick="(function(b,id,pw){var el=document.getElementById(id);if(el.textContent==='••••••••'){el.textContent=pw;b.textContent='ซ่อน';}else{el.textContent='••••••••';b.textContent='แสดง';}})(this,'eaPw<?= (int) $ea['id'] ?>',<?= json_encode($ea['account_password'], JSON_UNESCAPED_UNICODE) ?>)">แสดง</button>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if ($restricted): ?>
  <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:14px 16px;margin-bottom:18px;font-size:13px;color:#991B1B;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <i class="bi bi-slash-circle-fill" style="flex-shrink:0"></i>
    <span><strong>ถูกระงับการจองชั่วคราว</strong> — มีรายงานการใช้งานค้างเกินกำหนด กรุณา<a href="<?= url('student/my-bookings.php') ?>" style="color:#991B1B;font-weight:700">รายงานการใช้งาน</a>ให้ครบก่อน</span>
  </div>
<?php elseif ($pendingReports): ?>
  <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;color:#92400E;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0"></i>
    <span>คุณมีการใช้งานที่ยังไม่ได้รายงาน <strong><?= count($pendingReports) ?></strong> รายการ — <a href="<?= url('student/my-bookings.php') ?>" style="color:#92400E;font-weight:700">รายงานตอนนี้</a></span>
  </div>
<?php endif; ?>
<div style="margin-bottom:22px">
  <h5 style="font-weight:700;margin:0">สวัสดี, <?= e(explode(' ', $user['name'])[0]) ?> 👋</h5>
  <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0"><?= e(Booking::thaiDate(new DateTimeImmutable('today'))) ?></p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;margin-bottom:22px">
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:18px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
        <div class="stat-icon" style="background:#EFF6FF"><i class="bi bi-calendar-check" style="color:#2563EB"></i></div>
        <?php if ($next): ?><span class="badge-up">กำลังจะมาถึง</span><?php endif; ?>
      </div>
      <?php if ($next): ?>
        <div style="font-size:22px;font-weight:700;line-height:1"><?= substr($next['start_datetime'], 11, 5) ?>–<?= substr($next['end_datetime'], 11, 5) ?></div>
        <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:4px">การจองถัดไป · <?= e($next['ai_name']) ?></div>
      <?php else: ?>
        <div style="font-size:16px;font-weight:700;line-height:1;color:var(--bs-tertiary-color)">ไม่มีการจอง</div>
        <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:4px">ยังไม่มีการจองที่กำลังจะมาถึง</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:18px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
        <div class="stat-icon" style="background:#F0FDF4"><i class="bi bi-clock-history" style="color:#059669"></i></div>
      </div>
      <div style="font-size:22px;font-weight:700;line-height:1"><?= (int) $hoursUsed ?> <span style="font-size:14px;font-weight:400;color:var(--bs-secondary-color)">/ <?= (int) $weeklyMaxHours ?> ชม.</span></div>
      <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:4px">ชั่วโมงที่ใช้สัปดาห์นี้</div>
      <div style="background:#E2E8F0;border-radius:4px;height:4px;margin-top:10px;overflow:hidden">
        <div style="background:#059669;width:<?= $weeklyMaxHours > 0 ? min(100, round($hoursUsed / $weeklyMaxHours * 100)) : 0 ?>%;height:100%;border-radius:4px"></div>
      </div>
    </div>
  </div>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:18px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
        <div class="stat-icon" style="background:#FFF7ED"><i class="bi bi-ticket-perforated" style="color:#EA580C"></i></div>
      </div>
      <div style="font-size:22px;font-weight:700;line-height:1"><?= (int) $quotaRemaining ?> <span style="font-size:14px;font-weight:400;color:var(--bs-secondary-color)">/ <?= (int) $settings['weekly_quota'] ?> รอบ</span></div>
      <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:4px">โควต้าคงเหลือสัปดาห์นี้</div>
      <div style="display:flex;gap:4px;margin-top:10px">
        <?php for ($i = 0; $i < $settings['weekly_quota']; $i++): ?>
          <div style="flex:1;height:6px;border-radius:3px;background:<?= $i < $quotaRemaining ? '#2563EB' : '#E2E8F0' ?>"></div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:18px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
        <div class="stat-icon" style="background:#F5F3FF"><i class="bi bi-robot" style="color:#7C3AED"></i></div>
        <?php if ($next): ?><span class="badge-ok">ออนไลน์</span><?php endif; ?>
      </div>
      <div style="font-size:18px;font-weight:700;line-height:1"><?= $next ? e($next['ai_name']) : '—' ?></div>
      <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:4px">AI Account การจองถัดไป</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:14px;margin-bottom:22px">
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:20px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <h6 style="font-weight:700;margin:0">การจองที่กำลังจะมาถึง</h6>
        <span class="badge-up"><?= (int) $quotaRemaining ?> รอบคงเหลือ</span>
      </div>
      <?php if ($next): ?>
        <div class="info-box">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div><div style="font-size:11px;color:var(--bs-secondary-color);margin-bottom:3px">วันที่</div><div style="font-weight:600;font-size:14px"><?= e($next['dateLabel']) ?></div></div>
            <div><div style="font-size:11px;color:var(--bs-secondary-color);margin-bottom:3px">ช่วงเวลา</div><div style="font-weight:600;font-size:14px"><?= e($next['slotLabel']) ?></div></div>
            <div><div style="font-size:11px;color:var(--bs-secondary-color);margin-bottom:3px">ระยะเวลา</div><div style="font-weight:600;font-size:14px"><?= (int) $settings['slot_hours'] ?> ชั่วโมง</div></div>
            <div><div style="font-size:11px;color:var(--bs-secondary-color);margin-bottom:3px">AI Account</div><div style="font-weight:600;font-size:14px"><?= e($next['ai_name']) ?></div></div>
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:14px">
          <a href="<?= url('student/booking.php') ?>" class="btn btn-primary" style="font-size:13px;background:#2563EB;border:none"><i class="bi bi-calendar-plus me-1"></i>จองคิวเพิ่ม</a>
          <form method="post" action="<?= url('student/my-bookings.php') ?>" style="display:inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="id" value="<?= (int) $next['id'] ?>">
            <button type="submit" class="btn btn-outline-danger" style="font-size:13px"><i class="bi bi-x-circle me-1"></i>ยกเลิก</button>
          </form>
        </div>
      <?php else: ?>
        <p style="color:var(--bs-secondary-color);font-size:13px;margin:0">ยังไม่มีการจอง — <a href="<?= url('student/booking.php') ?>">จองคิวตอนนี้</a></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:20px">
      <h6 style="font-weight:700;margin:0 0 14px">สถิติการใช้งาน</h6>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px;color:var(--bs-secondary-color)">จองทั้งหมด</span><span style="font-weight:700"><?= (int) $totalCount ?> รอบ</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px;color:var(--bs-secondary-color)">ชั่วโมงสะสม</span><span style="font-weight:700"><?= (int) $totalHours ?> ชม.</span></div>
        <div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px;color:var(--bs-secondary-color)">ยกเลิก</span><span style="font-weight:700;color:#EF4444"><?= (int) $cancelledCount ?> รอบ</span></div>
        <div style="background:var(--bs-border-color);height:1px"></div>
        <div style="display:flex;justify-content:space-between;align-items:center"><span style="font-size:13px;color:var(--bs-secondary-color)">อัตราการใช้งาน</span><span style="font-weight:700;color:#059669"><?= e((string) $utilization) ?>%</span></div>
      </div>
    </div>
  </div>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <h6 style="font-weight:700;margin:0">ประวัติการจองล่าสุด</h6>
      <a href="<?= url('student/my-bookings.php') ?>" style="font-size:12px;color:#2563EB;text-decoration:none;font-weight:600">ดูทั้งหมด <i class="bi bi-arrow-right"></i></a>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="border-bottom:2px solid var(--bs-border-color)">
            <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">วันที่</th>
            <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">ช่วงเวลา</th>
            <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">AI Account</th>
            <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $b): ?>
            <tr style="border-bottom:1px solid var(--bs-border-color)">
              <td style="padding:10px"><?= e($b['dateLabel']) ?></td>
              <td style="padding:10px"><?= e($b['slotLabel']) ?></td>
              <td style="padding:10px;color:var(--bs-secondary-color)"><?= e($b['ai_name']) ?></td>
              <td style="padding:10px"><span class="<?= $b['badgeCls'] ?>"><?= e($b['statusLabel']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?>
            <tr><td colspan="4" style="padding:16px;text-align:center;color:var(--bs-tertiary-color)">ยังไม่มีประวัติการจอง</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
