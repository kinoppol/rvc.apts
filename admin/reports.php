<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if (($_GET['export'] ?? '') === 'csv') {
    Report::streamCsv(); // sets headers, writes CSV, exits
}

$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));

$data = Report::pagedRows($page, $perPage);
$totalPages = max(1, (int) ceil($data['total'] / $perPage));
if ($page > $totalPages) { // e.g. a stale ?page= after members drop off the report
    $page = $totalPages;
    $data = Report::pagedRows($page, $perPage);
}
$rows = $data['rows'];
$shownFrom = $data['total'] > 0 ? ($page - 1) * $perPage + 1 : 0;
$shownTo = min($page * $perPage, $data['total']);

$costRows = Report::costRows();

/** Rebuilds this page's URL with overridden query params. */
function reports_link(array $overrides = []): string
{
    global $page;
    return url('admin/reports.php') . '?' . http_build_query($overrides + ['page' => $page]);
}

$activeNav = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <h5 style="font-weight:700;margin:0">รายงานสถิติ</h5>
  <a href="<?= url('admin/reports.php') ?>?export=csv" class="btn btn-outline-primary" style="font-size:13px;color:#2563EB;border-color:#2563EB"><i class="bi bi-download me-1"></i>Export CSV</a>
</div>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:20px">
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="border-bottom:2px solid var(--bs-border-color);background:var(--bs-secondary-bg)">
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">สมาชิก</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">รหัสนักศึกษา</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">จองทั้งหมด</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">ชม.สะสม</th>
            <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">อัตราการใช้งาน</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr style="border-bottom:1px solid var(--bs-border-color)">
              <td style="padding:10px 14px;font-weight:500"><?= e($r['name']) ?></td>
              <td style="padding:10px 14px;font-family:monospace;font-size:12px"><?= e($r['studentId'] ?? '—') ?></td>
              <td style="padding:10px 14px"><?= (int) $r['totalBookings'] ?> รอบ</td>
              <td style="padding:10px 14px;font-weight:600"><?= (int) $r['hours'] ?> ชม.</td>
              <td style="padding:10px 14px">
                <div style="display:flex;align-items:center;gap:8px">
                  <div style="flex:1;background:var(--bs-border-color);border-radius:3px;height:5px;overflow:hidden">
                    <div style="background:#2563EB;width:<?= e($r['rate']) ?>;height:100%;border-radius:3px"></div>
                  </div>
                  <span style="font-size:12px;font-weight:600;color:#2563EB;white-space:nowrap"><?= e($r['rateLabel']) ?></span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="5" style="padding:32px;text-align:center;color:var(--bs-tertiary-color)">ยังไม่มีข้อมูลการใช้งานที่เสร็จสิ้น</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($data['total'] > $perPage): ?>
    <div style="padding:14px 2px 0;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--bs-border-color);flex-wrap:wrap;gap:10px;margin-top:4px">
      <span style="font-size:12px;color:var(--bs-secondary-color)">แสดง <?= (int) $shownFrom ?>–<?= (int) $shownTo ?> จาก <?= (int) $data['total'] ?> รายการ</span>
      <div style="display:flex;gap:4px;flex-wrap:wrap">
        <a href="<?= reports_link(['page' => max(1, $page - 1)]) ?>" class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" style="font-size:12px">ก่อนหน้า</a>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="<?= reports_link(['page' => $p]) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" style="font-size:12px;<?= $p === $page ? 'background:#2563EB;border:none' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="<?= reports_link(['page' => min($totalPages, $page + 1)]) ?>" class="btn btn-sm btn-outline-secondary<?= $page >= $totalPages ? ' disabled' : '' ?>" style="font-size:12px">ถัดไป</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($costRows): ?>
<div style="margin-top:28px">
  <h6 style="font-weight:700;margin-bottom:14px"><i class="bi bi-cash-coin me-2" style="color:#2563EB"></i>ต้นทุนและประสิทธิภาพ — <?= date('F Y') ?></h6>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:20px">
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="border-bottom:2px solid var(--bs-border-color);background:var(--bs-secondary-bg)">
              <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">บัญชี AI</th>
              <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">ประเภท</th>
              <th style="padding:10px 14px;text-align:right;font-weight:600;color:var(--bs-secondary-color)">งบ/เดือน</th>
              <th style="padding:10px 14px;text-align:right;font-weight:600;color:var(--bs-secondary-color)">ราคา/slot</th>
              <th style="padding:10px 14px;text-align:right;font-weight:600;color:var(--bs-secondary-color)">การใช้งาน (เดือนนี้)</th>
              <th style="padding:10px 14px;text-align:right;font-weight:600;color:var(--bs-secondary-color)">ต้นทุนจริง</th>
              <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">สัดส่วนงบ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($costRows as $cr): ?>
              <?php
                $ratioWarn = $cr['cost_ratio'] !== null && $cr['cost_ratio'] >= 80;
                $ratioColor = $cr['cost_ratio'] === null ? '#94A3B8' : ($cr['cost_ratio'] >= 80 ? '#DC2626' : '#2563EB');
              ?>
              <tr style="border-bottom:1px solid var(--bs-border-color)">
                <td style="padding:10px 14px;font-weight:600"><?= e($cr['name']) ?></td>
                <td style="padding:10px 14px;color:var(--bs-secondary-color)"><?= e($cr['provider']) ?></td>
                <td style="padding:10px 14px;text-align:right">
                  <?= $cr['monthly_cost'] !== null ? '฿' . number_format($cr['monthly_cost'], 2) : '<span style="color:var(--bs-tertiary-color)">—</span>' ?>
                </td>
                <td style="padding:10px 14px;text-align:right">
                  <?= $cr['cost_per_slot'] !== null ? '฿' . number_format($cr['cost_per_slot'], 2) : '<span style="color:var(--bs-tertiary-color)">—</span>' ?>
                </td>
                <td style="padding:10px 14px;text-align:right"><?= (int) $cr['bookings'] ?> slot</td>
                <td style="padding:10px 14px;text-align:right;font-weight:600;<?= $ratioWarn ? 'color:#DC2626' : '' ?>">
                  <?= $cr['usage_cost'] !== null ? '฿' . number_format($cr['usage_cost'], 2) : '<span style="color:var(--bs-tertiary-color)">—</span>' ?>
                </td>
                <td style="padding:10px 14px;min-width:130px">
                  <?php if ($cr['cost_ratio'] !== null): ?>
                    <div style="display:flex;align-items:center;gap:8px">
                      <div style="flex:1;background:var(--bs-border-color);border-radius:3px;height:5px;overflow:hidden">
                        <div style="background:<?= $ratioColor ?>;width:<?= $cr['cost_ratio'] ?>%;height:100%;border-radius:3px"></div>
                      </div>
                      <span style="font-size:12px;font-weight:600;color:<?= $ratioColor ?>;white-space:nowrap"><?= $cr['cost_ratio'] ?>%</span>
                    </div>
                  <?php else: ?>
                    <span style="color:var(--bs-tertiary-color)">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <?php
            $totalBudget = array_sum(array_filter(array_column($costRows, 'monthly_cost'), fn($v) => $v !== null));
            $totalUsage  = array_sum(array_filter(array_column($costRows, 'usage_cost'),  fn($v) => $v !== null));
          ?>
          <?php if (count($costRows) > 1): ?>
          <tfoot>
            <tr style="background:var(--bs-secondary-bg);font-weight:700;border-top:2px solid var(--bs-border-color)">
              <td colspan="2" style="padding:10px 14px">รวม</td>
              <td style="padding:10px 14px;text-align:right"><?= $totalBudget > 0 ? '฿' . number_format($totalBudget, 2) : '—' ?></td>
              <td style="padding:10px 14px"></td>
              <td style="padding:10px 14px;text-align:right"><?= array_sum(array_column($costRows, 'bookings')) ?> slot</td>
              <td style="padding:10px 14px;text-align:right"><?= $totalUsage > 0 ? '฿' . number_format($totalUsage, 2) : '—' ?></td>
              <td style="padding:10px 14px"></td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
      <p style="font-size:11px;color:var(--bs-tertiary-color);margin:12px 0 0"><i class="bi bi-info-circle me-1"></i>นับเฉพาะ slot ที่เสร็จสิ้นในเดือนนี้ — บัญชีที่ยังไม่ได้ตั้งค่าต้นทุนจะไม่แสดงในตารางนี้</p>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
