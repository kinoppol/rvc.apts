<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

$perPage = 15;

function bkgs_return_url(): string
{
    $keep = ['search', 'status_filter', 'account_id', 'date_from', 'date_to', 'page'];
    $q = array_filter(array_intersect_key($_POST + $_GET, array_flip($keep)), fn ($v) => $v !== '' && $v !== null && $v !== '0');
    return url('admin/bookings.php') . ($q ? '?' . http_build_query($q) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'cancel') {
        $result = Booking::adminCancel($id);
        flash_set($result['ok'] ? 'warn' : 'err', $result['ok'] ? 'ยกเลิกการจองเรียบร้อยแล้ว' : ($result['error'] ?? 'ยกเลิกไม่สำเร็จ'));
    } elseif ($action === 'waive_report') {
        $result = Booking::waiveReportForBooking($id);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ยกเว้นรายงานเรียบร้อยแล้ว' : ($result['error'] ?? 'ดำเนินการไม่สำเร็จ'));
    }
    header('Location: ' . bkgs_return_url());
    exit;
}

$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status_filter'] ?? 'all';
$validSt      = ['all', 'upcoming', 'checked_in', 'now', 'checked_out', 'completed', 'no_show', 'cancelled'];
if (!in_array($statusFilter, $validSt, true)) $statusFilter = 'all';
$accountId = max(0, (int) ($_GET['account_id'] ?? 0));
$dateFrom  = $_GET['date_from'] ?? '';
$dateTo    = $_GET['date_to']   ?? '';
$page      = max(1, (int) ($_GET['page'] ?? 1));

$allAccounts = Database::pdo()->query("SELECT id, name FROM ai_accounts ORDER BY name")->fetchAll();
$data        = Booking::adminList($search, $statusFilter, $accountId, $dateFrom, $dateTo, $page, $perPage);
$totalPages  = max(1, (int) ceil($data['total'] / $perPage));
$page        = min($page, $totalPages);
$shownFrom   = $data['total'] > 0 ? ($page - 1) * $perPage + 1 : 0;
$shownTo     = min($page * $perPage, $data['total']);

// Global quick-stats (ignore current filter)
$pdo = Database::pdo();
$liveCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM bookings WHERE status = 'upcoming' AND end_datetime > NOW() AND checked_in_at IS NOT NULL AND checked_out_at IS NULL"
)->fetchColumn();
$upcomingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM bookings WHERE status = 'upcoming' AND start_datetime > NOW()"
)->fetchColumn();
$overdueStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings WHERE status = 'upcoming' AND reported_at IS NULL
       AND checked_in_at IS NOT NULL AND COALESCE(checked_out_at, end_datetime) < DATE_SUB(NOW(), INTERVAL ? DAY)"
);
$overdueStmt->execute([Booking::REPORT_DEADLINE_DAYS]);
$overdueCount = (int) $overdueStmt->fetchColumn();
$issueCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE issue_text IS NOT NULL")->fetchColumn();

function bkgs_link(array $overrides = []): string
{
    global $search, $statusFilter, $accountId, $dateFrom, $dateTo, $page;
    $params = array_filter([
        'search'        => $search,
        'status_filter' => $statusFilter !== 'all' ? $statusFilter : null,
        'account_id'    => $accountId ?: null,
        'date_from'     => $dateFrom,
        'date_to'       => $dateTo,
        'page'          => $page,
    ], fn ($v) => $v !== '' && $v !== null);
    $params = array_merge($params, $overrides);
    return url('admin/bookings.php') . ($params ? '?' . http_build_query($params) : '');
}

function bkgs_action_form(int $bookingId, string $action, string $btnCls, string $icon, string $label, ?string $confirm = null): string
{
    global $search, $statusFilter, $accountId, $dateFrom, $dateTo, $page;
    $oc = $confirm ? ' onsubmit="return confirm(' . htmlspecialchars(json_encode($confirm, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')"' : '';
    return '<form method="post" style="margin:0"' . $oc . '>'
        . Csrf::field()
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="id" value="' . $bookingId . '">'
        . '<input type="hidden" name="search" value="' . e($search) . '">'
        . '<input type="hidden" name="status_filter" value="' . e($statusFilter) . '">'
        . '<input type="hidden" name="account_id" value="' . $accountId . '">'
        . '<input type="hidden" name="date_from" value="' . e($dateFrom) . '">'
        . '<input type="hidden" name="date_to" value="' . e($dateTo) . '">'
        . '<input type="hidden" name="page" value="' . (int) $page . '">'
        . '<button type="submit" class="' . e($btnCls) . '"><i class="bi ' . e($icon) . ' me-1"></i>' . e($label) . '</button>'
        . '</form>';
}

$statusChips = [
    'all'         => 'ทั้งหมด',
    'upcoming'    => 'กำลังจะมาถึง',
    'checked_in'  => 'ยืนยันแล้ว',
    'now'         => 'กำลังใช้งาน',
    'checked_out' => 'เช็คเอาท์แล้ว',
    'completed'   => 'เสร็จสิ้น',
    'no_show'     => 'ไม่ได้มา',
    'cancelled'   => 'ยกเลิก',
];

$activeNav = 'booking-management';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <h5 style="font-weight:700;margin:0">จัดการการจอง</h5>
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">ดู ยกเลิก และจัดการรายการจองทั้งหมดของนักศึกษา</p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <div style="background:var(--bs-secondary-bg);border:1px solid var(--bs-border-color);border-radius:10px;padding:10px 18px;text-align:center;min-width:80px">
      <div style="font-size:20px;font-weight:700;color:#059669"><?= $liveCount ?></div>
      <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:2px">กำลังใช้งาน</div>
    </div>
    <div style="background:var(--bs-secondary-bg);border:1px solid var(--bs-border-color);border-radius:10px;padding:10px 18px;text-align:center;min-width:80px">
      <div style="font-size:20px;font-weight:700;color:#2563EB"><?= $upcomingCount ?></div>
      <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:2px">รอใช้งาน</div>
    </div>
    <?php if ($overdueCount > 0): ?>
    <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:10px 18px;text-align:center;min-width:80px">
      <div style="font-size:20px;font-weight:700;color:#DC2626"><?= $overdueCount ?></div>
      <div style="font-size:11px;color:#DC2626;margin-top:2px">ค้างรายงาน</div>
    </div>
    <?php endif; ?>
    <?php if ($issueCount > 0): ?>
    <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:10px 18px;text-align:center;min-width:80px">
      <div style="font-size:20px;font-weight:700;color:#D97706"><?= $issueCount ?></div>
      <div style="font-size:11px;color:#D97706;margin-top:2px">แจ้งปัญหา</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Filter card -->
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:14px">
  <div class="card-body" style="padding:16px">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin:0">
      <div style="flex:1;min-width:180px;position:relative">
        <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--bs-tertiary-color);font-size:14px"></i>
        <input name="search" value="<?= e($search) ?>" type="text" class="form-control" placeholder="ชื่อ / อีเมล / รหัสนักศึกษา..." style="padding-left:36px;font-size:13px">
      </div>
      <div style="min-width:160px">
        <select name="account_id" class="form-select" style="font-size:13px">
          <option value="0">— AI Pool ทั้งหมด —</option>
          <?php foreach ($allAccounts as $ac): ?>
            <option value="<?= (int) $ac['id'] ?>" <?= $accountId === (int) $ac['id'] ? 'selected' : '' ?>><?= e($ac['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:6px;align-items:center">
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control" style="font-size:13px;width:140px" title="ตั้งแต่วันที่">
        <span style="font-size:12px;color:var(--bs-secondary-color)">ถึง</span>
        <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control" style="font-size:13px;width:140px" title="จนถึงวันที่">
      </div>
      <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="font-size:13px;white-space:nowrap">กรอง</button>
      <?php if ($search !== '' || $accountId > 0 || $dateFrom !== '' || $dateTo !== ''): ?>
        <a href="<?= bkgs_link(['search' => null, 'account_id' => null, 'date_from' => null, 'date_to' => null, 'page' => 1]) ?>" class="btn btn-outline-secondary btn-sm" style="font-size:13px">ล้าง</a>
      <?php endif; ?>
    </form>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px">
      <?php foreach ($statusChips as $key => $label): ?>
        <a href="<?= bkgs_link(['status_filter' => $key, 'page' => 1]) ?>" style="text-decoration:none;border-radius:20px;font-size:12px;padding:5px 14px;border:1.5px solid <?= $statusFilter === $key ? '#2563EB' : 'var(--bs-border-color)' ?>;font-weight:600;color:<?= $statusFilter === $key ? '#2563EB' : 'var(--bs-secondary-color)' ?>;background:<?= $statusFilter === $key ? 'rgba(37,99,235,.08)' : 'transparent' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Booking table -->
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:0;overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="border-bottom:2px solid var(--bs-border-color);background:var(--bs-secondary-bg)">
          <th style="padding:12px 14px;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">นักศึกษา</th>
          <th style="padding:12px 14px;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">AI Pool</th>
          <th style="padding:12px 14px;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">วัน / ช่วงเวลา</th>
          <th style="padding:12px 14px;font-weight:600;color:var(--bs-secondary-color)">สถานะ</th>
          <th style="padding:12px 14px;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">เช็คอิน / เอาท์</th>
          <th style="padding:12px 14px;font-weight:600;color:var(--bs-secondary-color)">วัตถุประสงค์ / รายงาน</th>
          <th style="padding:12px 14px;font-weight:600;color:var(--bs-secondary-color);text-align:center;white-space:nowrap">การดำเนินการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data['rows'] as $bk): ?>
          <?php $adminCanCancel = in_array($bk['displayStatus'], ['upcoming', 'check_in_ready', 'checked_in']); ?>
          <tr style="border-bottom:1px solid var(--bs-border-color);vertical-align:top">
            <!-- Student -->
            <td style="padding:11px 14px">
              <div style="font-weight:600;white-space:nowrap"><?= e($bk['student_name']) ?></div>
              <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:1px"><?= e($bk['student_email']) ?></div>
              <?php if (!empty($bk['student_code'])): ?><div style="font-size:11px;font-family:monospace;color:var(--bs-secondary-color)"><?= e($bk['student_code']) ?></div><?php endif; ?>
            </td>
            <!-- AI Pool -->
            <td style="padding:11px 14px;white-space:nowrap;font-weight:600"><?= e($bk['ai_name']) ?></td>
            <!-- Date/Slot -->
            <td style="padding:11px 14px;white-space:nowrap">
              <div><?= e($bk['dateLabel']) ?></div>
              <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:2px"><?= e($bk['slotLabel']) ?></div>
            </td>
            <!-- Status -->
            <td style="padding:11px 14px">
              <span class="<?= $bk['badgeCls'] ?>"><?= e($bk['statusLabel']) ?></span>
              <?php if ($bk['reportOverdue']): ?>
                <div><span class="badge-susp" style="margin-top:4px;display:inline-block;font-size:11px"><i class="bi bi-exclamation-triangle me-1"></i>ค้างรายงาน <?= abs($bk['reportDaysLeft']) ?> วัน</span></div>
              <?php elseif ($bk['needsReport']): ?>
                <div><span class="badge-pend" style="margin-top:4px;display:inline-block;font-size:11px">รอรายงาน</span></div>
              <?php elseif ($bk['reported']): ?>
                <div style="font-size:11px;color:#059669;margin-top:4px"><i class="bi bi-check-circle me-1"></i>รายงานแล้ว</div>
              <?php endif; ?>
            </td>
            <!-- Check-in / out times -->
            <td style="padding:11px 14px;white-space:nowrap;font-size:12px">
              <?php if (!empty($bk['checked_in_at'])): ?>
                <div style="color:#059669"><i class="bi bi-box-arrow-in-right me-1"></i><?= e((new DateTimeImmutable($bk['checked_in_at']))->format('H:i')) ?></div>
              <?php else: ?>
                <div style="color:var(--bs-tertiary-color)">—</div>
              <?php endif; ?>
              <?php if (!empty($bk['checked_out_at'])): ?>
                <div style="color:#D97706;margin-top:2px"><i class="bi bi-box-arrow-right me-1"></i><?= e((new DateTimeImmutable($bk['checked_out_at']))->format('H:i')) ?></div>
              <?php endif; ?>
            </td>
            <!-- Purpose / Report -->
            <td style="padding:11px 14px;max-width:200px">
              <?php if (!empty($bk['purpose'])): ?>
                <div style="font-size:12px;color:var(--bs-secondary-color)"><i class="bi bi-bullseye me-1"></i><?= e(mb_strimwidth($bk['purpose'], 0, 60, '…')) ?></div>
              <?php endif; ?>
              <?php if (!empty($bk['report_text'])): ?>
                <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:3px"><i class="bi bi-journal-text me-1"></i><?= e(mb_strimwidth($bk['report_text'], 0, 60, '…')) ?></div>
              <?php endif; ?>
              <?php if (!empty($bk['report_file'])): ?>
                <div style="margin-top:3px"><a href="<?= url('uploads/reports/' . $bk['report_file']) ?>" target="_blank" style="font-size:11px;color:#2563EB;text-decoration:none"><i class="bi bi-paperclip me-1"></i>ไฟล์แนบ</a></div>
              <?php endif; ?>
              <?php if ($bk['token_start_pct'] !== null || $bk['token_end_pct'] !== null || !empty($bk['token_reset_at'])): ?>
                <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px">
                  <?php if ($bk['token_start_pct'] !== null): ?><span style="font-size:10px;background:var(--bs-secondary-bg);border-radius:4px;padding:2px 5px;color:var(--bs-secondary-color);white-space:nowrap"><i class="bi bi-speedometer2 me-1"></i>ก่อน <?= (int)$bk['token_start_pct'] ?>%</span><?php endif; ?>
                  <?php if ($bk['token_end_pct'] !== null): ?><span style="font-size:10px;background:var(--bs-secondary-bg);border-radius:4px;padding:2px 5px;color:var(--bs-secondary-color);white-space:nowrap"><i class="bi bi-speedometer2 me-1"></i>หลัง <?= (int)$bk['token_end_pct'] ?>%</span><?php endif; ?>
                  <?php if (!empty($bk['token_reset_at'])): ?><span style="font-size:10px;background:var(--bs-secondary-bg);border-radius:4px;padding:2px 5px;color:var(--bs-secondary-color);white-space:nowrap"><i class="bi bi-arrow-clockwise me-1"></i>รีเซ็ต <?= e((new DateTimeImmutable($bk['token_reset_at']))->format('d/m H:i')) ?></span><?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($bk['issue_text'])): ?>
                <div style="margin-top:4px;padding:5px 8px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;font-size:11px;color:#92400E"><i class="bi bi-bug me-1"></i><?= e(mb_strimwidth($bk['issue_text'], 0, 80, '…')) ?></div>
              <?php endif; ?>
              <?php if (empty($bk['purpose']) && empty($bk['report_text']) && empty($bk['report_file']) && $bk['token_start_pct'] === null && empty($bk['issue_text'])): ?>
                <span style="font-size:12px;color:var(--bs-tertiary-color)">—</span>
              <?php endif; ?>
            </td>
            <!-- Actions -->
            <td style="padding:11px 14px">
              <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
                <?php if ($adminCanCancel): ?>
                  <?= bkgs_action_form((int)$bk['id'], 'cancel', 'action-btn-err', 'bi-x-circle', 'ยกเลิก', 'ยกเลิกการจองของ ' . $bk['student_name'] . '?') ?>
                <?php endif; ?>
                <?php if ($bk['reportOverdue']): ?>
                  <?= bkgs_action_form((int)$bk['id'], 'waive_report', 'action-btn-warn', 'bi-unlock', 'ปลดรายงาน', 'ยกเว้นรายงานค้างของรายการนี้?') ?>
                <?php endif; ?>
                <?php if (!$adminCanCancel && !$bk['reportOverdue']): ?>
                  <span style="font-size:12px;color:var(--bs-tertiary-color)">—</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--bs-tertiary-color)">
            <i class="bi bi-calendar-x" style="font-size:28px;display:block;margin-bottom:8px"></i>
            ไม่พบรายการจองที่ตรงกับเงื่อนไข
          </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div style="padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--bs-border-color);flex-wrap:wrap;gap:10px">
      <span style="font-size:12px;color:var(--bs-secondary-color)">แสดง <?= (int) $shownFrom ?>–<?= (int) $shownTo ?> จาก <?= (int) $data['total'] ?> รายการ</span>
      <div style="display:flex;gap:4px;flex-wrap:wrap">
        <a href="<?= bkgs_link(['page' => max(1, $page - 1)]) ?>" class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" style="font-size:12px">ก่อนหน้า</a>
        <?php
          $start = max(1, $page - 2);
          $end   = min($totalPages, $start + 4);
          $start = max(1, $end - 4);
          for ($p = $start; $p <= $end; $p++):
        ?>
          <a href="<?= bkgs_link(['page' => $p]) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" style="font-size:12px;<?= $p === $page ? 'background:#2563EB;border:none' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="<?= bkgs_link(['page' => min($totalPages, $page + 1)]) ?>" class="btn btn-sm btn-outline-secondary<?= $page >= $totalPages ? ' disabled' : '' ?>" style="font-size:12px">ถัดไป</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
