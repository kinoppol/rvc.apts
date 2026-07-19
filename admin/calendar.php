<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

// Admins may look back as well as forward (students are clamped to 0..8).
$week = (int) ($_GET['week'] ?? 0);
$week = max(-12, min(12, $week));

$settings  = SlotSettings::get();
$grid      = Booking::adminWeekGrid($week);
$weekLabel = Booking::getWeekLabel($week);

$cellMeta = [
    'now'     => ['bg' => '#DCFCE7', 'fg' => '#059669', 'border' => '#059669', 'label' => 'กำลังใช้งาน', 'icon' => 'bi-broadcast'],
    'busy'    => ['bg' => '#FEF3C7', 'fg' => '#D97706', 'border' => '#D97706', 'label' => 'เต็ม',        'icon' => 'bi-people-fill'],
    'partial' => ['bg' => '#EFF6FF', 'fg' => '#2563EB', 'border' => '#2563EB', 'label' => 'มีการจอง',    'icon' => 'bi-person-fill'],
    'empty'   => ['bg' => 'transparent', 'fg' => '#94A3B8', 'border' => '#CBD5E1', 'label' => 'ว่าง',    'icon' => 'bi-dash-circle'],
    'off'     => ['bg' => 'transparent', 'fg' => '#94A3B8', 'border' => '#CBD5E1', 'label' => 'ปิด',     'icon' => 'bi-slash-circle'],
];

$activeNav = 'calendar';
require __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <div>
    <h5 style="font-weight:700;margin:0">ปฏิทินการจอง</h5>
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">ภาพรวมการจองทุก AI Pool · คลิกช่องเพื่อดูรายละเอียดของช่วงเวลานั้น</p>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <a href="<?= url('admin/calendar.php') ?>?week=<?= $week - 1 ?>" class="btn btn-outline-secondary btn-sm<?= $week <= -12 ? ' disabled' : '' ?>"><i class="bi bi-chevron-left"></i></a>
    <span style="font-size:13px;font-weight:600;white-space:nowrap"><?= e($weekLabel) ?></span>
    <a href="<?= url('admin/calendar.php') ?>?week=<?= $week + 1 ?>" class="btn btn-outline-secondary btn-sm<?= $week >= 12 ? ' disabled' : '' ?>"><i class="bi bi-chevron-right"></i></a>
    <?php if ($week !== 0): ?>
      <a href="<?= url('admin/calendar.php') ?>" class="btn btn-outline-secondary btn-sm" style="font-size:12px">สัปดาห์นี้</a>
    <?php endif; ?>
  </div>
</div>

<div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;font-size:12px">
  <?php foreach (['now', 'busy', 'partial', 'empty', 'off'] as $k): $m = $cellMeta[$k]; ?>
    <div style="display:flex;align-items:center;gap:5px">
      <div style="width:12px;height:12px;border-radius:3px;background:<?= $m['bg'] ?>;border:1.5px <?= in_array($k, ['empty', 'off'], true) ? 'dashed' : 'solid' ?> <?= $m['border'] ?>"></div><?= e($m['label']) ?>
    </div>
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
                $meta = $cellMeta[$slot['cellStatus']];
                $dashed = in_array($slot['cellStatus'], ['empty', 'off'], true);
                // Only cells with at least one usable pool are worth opening.
                $clickable = $slot['totalCapacity'] > 0;
                $detail = [
                    'date'      => $slot['date'],
                    'dayLabel'  => $slot['dateLabel'],
                    'slotLabel' => $slot['label'],
                    'slotTime'  => $slot['time'],
                    'booked'    => $slot['totalBooked'],
                    'capacity'  => $slot['totalCapacity'],
                    'pools'     => $slot['pools'],
                ];
            ?>
              <td style="vertical-align:top;padding:0;border:1px solid var(--bs-border-color)">
                <?php if ($clickable): ?>
                  <button type="button" class="cal-cell" style="all:unset;box-sizing:border-box;cursor:pointer;display:block;width:100%;padding:8px;background:<?= $meta['bg'] ?>;border-left:3px <?= $dashed ? 'dashed' : 'solid' ?> <?= $meta['border'] ?>;<?= $dashed ? 'opacity:.7' : '' ?>"
                    data-detail='<?= e(json_encode($detail, JSON_UNESCAPED_UNICODE)) ?>'>
                    <div style="font-size:10px;font-weight:700;color:<?= $meta['fg'] ?>"><i class="bi <?= $meta['icon'] ?>"></i> <?= e($meta['label']) ?></div>
                    <div style="font-size:9px;color:<?= $meta['fg'] ?>;opacity:.85;margin-top:2px"><?= (int) $slot['totalBooked'] ?>/<?= (int) $slot['totalCapacity'] ?> ที่นั่ง</div>
                  </button>
                <?php else: ?>
                  <div style="padding:8px;background:<?= $meta['bg'] ?>;border-left:3px dashed <?= $meta['border'] ?>;opacity:.6">
                    <div style="font-size:10px;font-weight:700;color:<?= $meta['fg'] ?>"><i class="bi <?= $meta['icon'] ?>"></i> <?= e($meta['label']) ?></div>
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

<!-- Slot detail modal (populated by initAdminCalendar in assets/app.js) -->
<div class="modal fade" id="calSlotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border:none;border-radius:14px">
      <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
        <div>
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-calendar3 me-2" style="color:#2563EB"></i><span id="calModalTitle"></span></h6>
          <div id="calModalSub" style="font-size:12px;color:var(--bs-secondary-color);margin-top:2px"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body" style="padding:16px 20px;max-height:65vh;overflow-y:auto">
        <div id="calModalBody"></div>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
        <a id="calModalManage" href="<?= url('admin/bookings.php') ?>" data-base="<?= url('admin/bookings.php') ?>" class="btn btn-outline-primary btn-sm" style="font-size:13px;color:#2563EB;border-color:#2563EB"><i class="bi bi-box-arrow-up-right me-1"></i>จัดการการจองวันนี้</a>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
