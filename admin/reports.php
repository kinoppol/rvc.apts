<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if (($_GET['export'] ?? '') === 'csv') {
    Report::streamCsv(); // sets headers, writes CSV, exits
}

$rows = Report::rows();

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
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
