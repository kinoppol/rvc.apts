<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';

    if ($action === 'run_all') {
        $results = Migration::runPending();
        if (empty($results)) {
            flash_set('ok', 'ไม่มี Migration ที่ต้องรัน — ฐานข้อมูลเป็นเวอร์ชันล่าสุดแล้ว');
        } else {
            $failed = array_filter($results, fn ($r) => !$r['ok']);
            if ($failed) {
                $f = reset($failed);
                flash_set('err', 'รัน ' . count($results) - count($failed) . ' รายการสำเร็จ แล้วเกิดข้อผิดพลาดที่ ' . e($f['file']) . ': ' . e($f['error']));
            } else {
                flash_set('ok', 'รัน Migration เสร็จแล้ว ' . count($results) . ' รายการ');
            }
        }
    } elseif ($action === 'run_one') {
        $r = Migration::runOne($_POST['filename'] ?? '');
        flash_set($r['ok'] ? 'ok' : 'err', $r['ok']
            ? 'รัน ' . e($r['file']) . ' เสร็จแล้ว'
            : e($r['error'] ?? 'รันไม่สำเร็จ'));
    } elseif ($action === 'mark_applied') {
        $r = Migration::markApplied($_POST['filename'] ?? '');
        flash_set($r['ok'] ? 'ok' : 'err', $r['ok']
            ? 'ทำเครื่องหมาย applied แล้ว'
            : e($r['error'] ?? 'ไม่สำเร็จ'));
    }

    header('Location: ' . url('admin/migrations.php'));
    exit;
}

$migrations = Migration::status();
$pendingCount = count(array_filter($migrations, fn ($m) => !$m['applied'] && $m['exists']));
$appliedCount = count(array_filter($migrations, fn ($m) => $m['applied']));

$activeNav = 'migrations';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <h5 style="font-weight:700;margin:0">Database Migrations</h5>
    <div style="font-size:13px;color:var(--bs-secondary-color);margin-top:3px">
      จัดการการอัปเดต schema ฐานข้อมูล
    </div>
  </div>
  <?php if ($pendingCount > 0): ?>
    <form method="post" onsubmit="return confirm('รัน Migration ที่ยังค้างอยู่ทั้งหมด <?= $pendingCount ?> รายการ?')">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="run_all">
      <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px">
        <i class="bi bi-play-fill me-1"></i>รันทั้งหมดที่ค้างอยู่ (<?= $pendingCount ?>)
      </button>
    </form>
  <?php endif; ?>
</div>

<!-- Status summary -->
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <div class="card" style="border:1px solid var(--bs-border-color);flex:1;min-width:140px">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:12px">
      <div style="width:36px;height:36px;border-radius:8px;background:#DCFCE7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="bi bi-check-circle-fill" style="color:#059669;font-size:16px"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;line-height:1"><?= $appliedCount ?></div>
        <div style="font-size:11px;color:var(--bs-secondary-color)">Applied แล้ว</div>
      </div>
    </div>
  </div>
  <div class="card" style="border:1px solid <?= $pendingCount ? '#FDE68A' : 'var(--bs-border-color)' ?>;flex:1;min-width:140px">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:12px">
      <div style="width:36px;height:36px;border-radius:8px;background:<?= $pendingCount ? '#FEF3C7' : 'var(--bs-secondary-bg)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="bi bi-<?= $pendingCount ? 'exclamation-triangle-fill' : 'check2-all' ?>" style="color:<?= $pendingCount ? '#D97706' : 'var(--bs-secondary-color)' ?>;font-size:16px"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;line-height:1;<?= $pendingCount ? 'color:#D97706' : '' ?>"><?= $pendingCount ?></div>
        <div style="font-size:11px;color:var(--bs-secondary-color)"><?= $pendingCount ? 'รอ Apply' : 'ทุกอย่างอัปเดตแล้ว' ?></div>
      </div>
    </div>
  </div>
  <div class="card" style="border:1px solid var(--bs-border-color);flex:1;min-width:140px">
    <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:12px">
      <div style="width:36px;height:36px;border-radius:8px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="bi bi-stack" style="color:#2563EB;font-size:16px"></i>
      </div>
      <div>
        <div style="font-size:22px;font-weight:700;line-height:1"><?= count($migrations) ?></div>
        <div style="font-size:11px;color:var(--bs-secondary-color)">Migration ทั้งหมด</div>
      </div>
    </div>
  </div>
</div>

<!-- Migration table -->
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:0;overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="border-bottom:2px solid var(--bs-border-color)">
          <th style="padding:10px 16px;text-align:left;font-size:11px;color:var(--bs-secondary-color);font-weight:600;white-space:nowrap">#</th>
          <th style="padding:10px 16px;text-align:left;font-size:11px;color:var(--bs-secondary-color);font-weight:600">ไฟล์ Migration</th>
          <th style="padding:10px 16px;text-align:center;font-size:11px;color:var(--bs-secondary-color);font-weight:600;white-space:nowrap">สถานะ</th>
          <th style="padding:10px 16px;text-align:left;font-size:11px;color:var(--bs-secondary-color);font-weight:600;white-space:nowrap">Applied เมื่อ</th>
          <th style="padding:10px 16px;text-align:right;font-size:11px;color:var(--bs-secondary-color);font-weight:600"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($migrations as $i => $m): ?>
          <tr style="border-bottom:1px solid var(--bs-border-color)<?= !$m['exists'] ? ';opacity:.55' : '' ?>">
            <td style="padding:10px 16px;color:var(--bs-tertiary-color)"><?= $i + 1 ?></td>
            <td style="padding:10px 16px">
              <code style="font-size:12px;word-break:break-all"><?= e($m['filename']) ?></code>
              <?php if (!$m['exists']): ?>
                <div style="font-size:11px;color:#DC2626;margin-top:2px"><i class="bi bi-exclamation-circle me-1"></i>ไม่พบไฟล์บน server</div>
              <?php endif; ?>
            </td>
            <td style="padding:10px 16px;text-align:center">
              <?php if ($m['applied']): ?>
                <span class="badge-ok"><i class="bi bi-check-circle-fill me-1"></i>Applied</span>
              <?php elseif (!$m['exists']): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:3px 8px;border-radius:6px;background:#FEE2E2;color:#DC2626"><i class="bi bi-file-x me-1"></i>ไม่พบไฟล์</span>
              <?php else: ?>
                <span class="badge-pend"><i class="bi bi-clock me-1"></i>Pending</span>
              <?php endif; ?>
            </td>
            <td style="padding:10px 16px;color:var(--bs-secondary-color);white-space:nowrap;font-size:12px">
              <?= $m['applied_at'] ? e(Booking::thaiDate(new DateTimeImmutable($m['applied_at'])) . ' ' . (new DateTimeImmutable($m['applied_at']))->format('H:i')) : '—' ?>
            </td>
            <td style="padding:10px 16px;text-align:right;white-space:nowrap">
              <?php if (!$m['applied'] && $m['exists']): ?>
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <form method="post" onsubmit="return confirm('รัน <?= e($m['filename']) ?>?')" style="margin:0">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="run_one">
                    <input type="hidden" name="filename" value="<?= e($m['filename']) ?>">
                    <button type="submit" class="action-btn-blue" style="font-size:12px;padding:4px 10px">
                      <i class="bi bi-play-fill me-1"></i>Run
                    </button>
                  </form>
                  <form method="post" onsubmit="return confirm('ทำเครื่องหมายว่า applied โดยไม่รัน SQL?\n(ใช้เมื่อ schema ตรงกันอยู่แล้ว)')" style="margin:0">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="mark_applied">
                    <input type="hidden" name="filename" value="<?= e($m['filename']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" style="font-size:12px;padding:4px 10px" title="Mark as applied without running">
                      <i class="bi bi-check2"></i>
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="margin-top:12px;font-size:12px;color:var(--bs-tertiary-color)">
  <i class="bi bi-info-circle me-1"></i>
  ไฟล์ <code>migrate_production_catchup.sql</code> เป็น emergency script สำหรับใช้มือ — ไม่อยู่ในระบบ tracking อัตโนมัติ
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
