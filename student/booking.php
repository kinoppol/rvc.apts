<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('student');

$week = isset($_GET['week']) ? (int) $_GET['week'] : 0;
$week = max(0, min(8, $week));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $date = trim($_POST['booking_date'] ?? '');
    $slotIndex = (int) ($_POST['slot_index'] ?? -1);
    $accountIds = array_map('intval', (array) ($_POST['ai_account_id'] ?? []));
    $result = Booking::create($user['id'], $date, $slotIndex, $accountIds, $_POST['purpose'] ?? '');
    flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'จองคิวสำเร็จ!' : ($result['error'] ?? 'ไม่สามารถจองได้'));
    header('Location: ' . url('student/booking.php') . '?week=' . $week);
    exit;
}

$settings = Booking::limitsFor($user['id']);
$maxConcurrent = (int) $settings['max_concurrent'];
$restricted = Booking::isRestricted($user['id']);
$pendingReports = Booking::pendingReportsForUser($user['id']);
$allowedPools = Booking::allowedAccountsFor($user['id']);
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
<?php if (!$allowedPools): ?>
  <h5 style="font-weight:700;margin:0 0 16px">จองคิว AI Pro</h5>
  <div style="background:var(--bs-secondary-bg);border:1px solid var(--bs-border-color);border-radius:12px;padding:28px;text-align:center;max-width:560px">
    <i class="bi bi-lock" style="font-size:30px;color:var(--bs-tertiary-color);display:block;margin-bottom:10px"></i>
    <div style="font-weight:700;margin-bottom:4px">ยังไม่มี Pool ที่จองได้</div>
    <p style="color:var(--bs-secondary-color);font-size:13px;margin:0">กลุ่มของคุณยังไม่ได้รับสิทธิ์เข้าถึง AI Pool ใด ๆ กรุณาติดต่อผู้ดูแลระบบเพื่อขอสิทธิ์การจอง</p>
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
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">คลิกช่วงเวลาที่ต้องการจอง แล้วเลือก AI Pool จากแบบฟอร์ม · โควต้าคงเหลือ <?= (int) $quotaRemaining ?>/<?= (int) $settings['weekly_quota'] ?> รอบ/สัปดาห์ · จองพร้อมกันได้ <?= $maxConcurrent ?> Pool/ช่วงเวลา</p>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <a href="<?= url('student/booking.php') ?>?week=<?= max(0, $week - 1) ?>" class="btn btn-outline-secondary btn-sm<?= $week <= 0 ? ' disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
    <span style="font-size:13px;font-weight:600;white-space:nowrap"><?= e($weekLabel) ?></span>
    <a href="<?= url('student/booking.php') ?>?week=<?= min(8, $week + 1) ?>" class="btn btn-outline-secondary btn-sm<?= $week >= 8 ? ' disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
  </div>
</div>

<?php
/** Joins pool names, truncated to $max items with a trailing "…" if more remain. */
function pool_names_preview(array $pools, int $max = 2): string
{
    $names = array_map(fn ($p) => $p['name'], $pools);
    if (count($names) <= $max) {
        return implode(', ', $names);
    }
    return implode(', ', array_slice($names, 0, $max)) . ', …';
}

$cellMeta = [
    'now'       => ['bg' => '#DCFCE7', 'fg' => '#059669', 'border' => '#059669', 'label' => 'กำลังใช้งาน', 'icon' => 'bi-broadcast'],
    'available' => ['bg' => '#EFF6FF', 'fg' => '#2563EB', 'border' => '#2563EB', 'label' => 'ว่าง',        'icon' => 'bi-plus-circle'],
    'mine'      => ['bg' => '#DBEAFE', 'fg' => '#1D4ED8', 'border' => '#1D4ED8', 'label' => 'ของฉัน',      'icon' => 'bi-check-circle-fill'],
    'off'       => ['bg' => 'transparent', 'fg' => '#94A3B8', 'border' => '#CBD5E1', 'label' => 'ปิด',    'icon' => 'bi-dash-circle'],
    'busy'      => ['bg' => '#F1F5F9', 'fg' => '#64748B', 'border' => '#94A3B8', 'label' => 'จองแล้ว',     'icon' => 'bi-person-fill'],
];
?>
<div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;font-size:12px">
  <?php foreach (['available' => 'ว่าง (จองได้)', 'mine' => 'ของฉัน', 'now' => 'กำลังใช้งาน', 'busy' => 'จองแล้ว', 'off' => 'ปิด'] as $k => $lbl): $m = $cellMeta[$k]; ?>
    <div style="display:flex;align-items:center;gap:5px"><div style="width:12px;height:12px;border-radius:3px;background:<?= $m['bg'] ?>;border:1.5px <?= $k === 'off' ? 'dashed' : 'solid' ?> <?= $m['border'] ?>"></div><?= e($lbl) ?></div>
  <?php endforeach; ?>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:16px;overflow-x:auto">
    <table style="border-collapse:collapse;min-width:100%">
      <thead>
        <tr>
          <th style="width:56px;border:1px solid var(--bs-border-color)"></th>
          <?php foreach ($grid as $day): ?>
            <th style="text-align:center;padding:4px;min-width:118px;border:1px solid var(--bs-border-color)">
              <div style="font-size:10px;font-weight:600;color:var(--bs-tertiary-color);letter-spacing:.05em"><?= e($day['dayName']) ?></div>
              <span class="<?= $day['todayCls'] ?>"><?= (int) $day['date'] ?></span>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php for ($i = 0; $i < $settings['slots_per_day']; $i++): ?>
          <tr>
            <td style="vertical-align:top;text-align:right;padding:6px 8px;white-space:nowrap;border:1px solid var(--bs-border-color)">
              <div style="font-size:11px;font-weight:600;color:var(--bs-secondary-color)"><?= e(SlotSettings::slotLabel($i)) ?></div>
              <div style="font-size:9px;color:var(--bs-tertiary-color)"><?= e(SlotSettings::slotStart($settings, $i)) ?></div>
              <div style="font-size:9px;color:var(--bs-tertiary-color)"><?= e(SlotSettings::slotEnd($settings, $i)) ?></div>
            </td>
            <?php foreach ($grid as $day):
                $slot = $day['slots'][$i];
                $avail = array_values(array_filter($slot['pools'], fn ($p) => $p['bookable']));
                $mine = array_values(array_filter($slot['pools'], fn ($p) => in_array($p['status'], ['mine', 'now'], true)));
                $isNow = (bool) array_filter($mine, fn ($p) => $p['status'] === 'now');
                $allOff = count(array_filter($slot['pools'], fn ($p) => $p['status'] !== 'off')) === 0;

                if ($isNow) {
                    $cellStatus = 'now';
                } elseif ($avail) {
                    $cellStatus = 'available';
                } elseif ($mine) {
                    $cellStatus = 'mine';
                } elseif ($allOff) {
                    $cellStatus = 'off';
                } else {
                    $cellStatus = 'busy';
                }
                $meta = $cellMeta[$cellStatus];
                $preview = $avail ? pool_names_preview($avail) : ($mine ? pool_names_preview($mine) : '');
                $remaining = max(0, $maxConcurrent - count($mine));
            ?>
              <td style="vertical-align:top;padding:0;border:1px solid var(--bs-border-color)">
                <?php if ($cellStatus === 'available'): ?>
                  <button type="button" class="slot-cell" style="all:unset;box-sizing:border-box;cursor:pointer;display:block;width:100%;padding:8px;background:<?= $meta['bg'] ?>;border-left:3px solid <?= $meta['border'] ?>"
                    data-date="<?= e($slot['date']) ?>" data-slot-index="<?= (int) $slot['slotIndex'] ?>"
                    data-day-label="<?= e($slot['dateLabel']) ?>" data-slot-label="<?= e($slot['label']) ?>" data-slot-time="<?= e($slot['time']) ?>"
                    data-max-select="<?= (int) $remaining ?>"
                    data-pools='<?= e(json_encode(array_map(fn ($p) => ['id' => $p['accountId'], 'name' => $p['name']], $avail), JSON_UNESCAPED_UNICODE)) ?>'>
                    <div style="font-size:10px;font-weight:700;color:<?= $meta['fg'] ?>"><i class="bi <?= $meta['icon'] ?>"></i> <?= $meta['label'] ?></div>
                    <div style="font-size:9px;color:<?= $meta['fg'] ?>;opacity:.85;margin-top:2px;word-break:break-word"><?= e($preview) ?></div>
                    <?php if ($mine): ?><div style="font-size:9px;color:#1D4ED8;margin-top:2px">ของฉัน: <?= e(pool_names_preview($mine)) ?></div><?php endif; ?>
                  </button>
                <?php else: ?>
                  <div style="padding:8px;background:<?= $meta['bg'] ?>;<?= $cellStatus === 'off' ? 'border-left:3px dashed ' . $meta['border'] . ';opacity:.6' : 'border-left:3px solid ' . $meta['border'] ?>">
                    <div style="font-size:10px;font-weight:700;color:<?= $meta['fg'] ?>"><i class="bi <?= $meta['icon'] ?>"></i> <?= $meta['label'] ?></div>
                    <?php if ($preview): ?><div style="font-size:9px;color:<?= $meta['fg'] ?>;opacity:.85;margin-top:2px;word-break:break-word"><?= e($preview) ?></div><?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
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
          <p style="font-size:13px;color:var(--bs-secondary-color);margin:0 0 14px">โปรดเลือก AI Pool ที่ต้องการจองสำหรับช่วงเวลานี้</p>
          <div class="info-box">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="font-size:12px;color:var(--bs-secondary-color)">วันที่</span><span style="font-weight:600;font-size:13px" id="bookModalDayLabel">—</span></div>
            <div style="display:flex;justify-content:space-between"><span style="font-size:12px;color:var(--bs-secondary-color)">ช่วงเวลา</span><span style="font-weight:600;font-size:13px" id="bookModalSlotTime">—</span></div>
          </div>
          <div style="margin-top:14px">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px">
              <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);margin:0">เลือก AI Pool <span style="color:#DC2626">*</span></label>
              <span style="font-size:11px;color:var(--bs-tertiary-color)" id="bookModalMaxHint"></span>
            </div>
            <div id="bookModalPoolChecks" style="display:flex;flex-direction:column;gap:6px;border:1px solid var(--bs-border-color);border-radius:8px;padding:10px;max-height:180px;overflow-y:auto"></div>
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
