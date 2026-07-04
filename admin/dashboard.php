<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'approve') {
        Member::approve($id);
        flash_set('ok', 'อนุมัติสมาชิกเรียบร้อยแล้ว');
    } elseif ($action === 'reject') {
        Member::reject($id);
        flash_set('warn', 'ปฏิเสธคำขอสมัครเรียบร้อยแล้ว');
    }
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$pdo = Database::pdo();
$settings = SlotSettings::get();

$totalMembers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$pendingCount = Member::pendingCount();
$activeAccounts = (int) $pdo->query(
    "SELECT COUNT(*) FROM ai_accounts WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())"
)->fetchColumn();

$capacityToday = $activeAccounts * $settings['slots_per_day'];
$bookedToday = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE() AND status = 'upcoming'")->fetchColumn();
$utilToday = $capacityToday > 0 ? (int) round($bookedToday / $capacityToday * 100) : 0;

// ISO week (Mon–Sun) containing today
$today = new DateTimeImmutable('today');
$weekStart = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
$weekEnd = $weekStart->modify('+6 days');
$bookingsThisWeekStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'upcoming' AND booking_date BETWEEN ? AND ?");
$bookingsThisWeekStmt->execute([$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
$bookingsThisWeek = (int) $bookingsThisWeekStmt->fetchColumn();

$accounts = AiAccount::listWithUsage();
$pendingMembers = Member::pending(5);

// Chart data: daily utilization % per provider across the current week
$providers = $pdo->query(
    "SELECT COALESCE(p.name, a.provider) AS provider, COUNT(*) c
     FROM ai_accounts a LEFT JOIN ai_providers p ON p.id = a.provider_id
     WHERE a.status = 'active' AND (a.expires_at IS NULL OR a.expires_at > NOW())
     GROUP BY COALESCE(p.name, a.provider)"
)->fetchAll();
$dayLabels = ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'];
$palette = ['rgba(37,99,235,.82)', 'rgba(14,165,233,.82)', 'rgba(124,58,237,.82)', 'rgba(5,150,105,.82)'];
$datasets = [];
$dayCountStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings b JOIN ai_accounts a ON a.id = b.ai_account_id
     LEFT JOIN ai_providers p ON p.id = a.provider_id
     WHERE b.booking_date = ? AND b.status = 'upcoming' AND COALESCE(p.name, a.provider) = ?"
);
foreach ($providers as $pi => $prov) {
    $capacity = (int) $prov['c'] * $settings['slots_per_day'];
    $data = [];
    for ($d = 0; $d < 7; $d++) {
        $date = $weekStart->modify("+{$d} days")->format('Y-m-d');
        $dayCountStmt->execute([$date, $prov['provider']]);
        $booked = (int) $dayCountStmt->fetchColumn();
        $data[] = $capacity > 0 ? (int) round($booked / $capacity * 100) : 0;
    }
    $datasets[] = [
        'label' => $prov['provider'],
        'data' => $data,
        'backgroundColor' => $palette[$pi % count($palette)],
        'borderRadius' => 5,
    ];
}

$activeNav = 'admin-dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom:22px">
  <h5 style="font-weight:700;margin:0">ภาพรวมระบบ</h5>
  <p style="color:#64748B;font-size:14px;margin:4px 0 0">อัปเดตล่าสุด: <?= e(Booking::thaiDate($today)) ?></p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:22px">
  <div class="card" style="border:none;background:linear-gradient(135deg,#2563EB,#1D4ED8);box-shadow:0 4px 16px rgba(37,99,235,.3)">
    <div class="card-body" style="padding:18px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <i class="bi bi-people-fill" style="color:rgba(255,255,255,.8);font-size:22px"></i>
      </div>
      <div style="font-size:32px;font-weight:700;color:white;line-height:1"><?= $totalMembers ?></div>
      <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">สมาชิกทั้งหมด</div>
    </div>
  </div>
  <div class="card" style="border:none;background:linear-gradient(135deg,#F59E0B,#D97706);box-shadow:0 4px 16px rgba(245,158,11,.3)">
    <div class="card-body" style="padding:18px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <i class="bi bi-hourglass-split" style="color:rgba(255,255,255,.8);font-size:22px"></i>
        <?php if ($pendingCount > 0): ?><span style="background:rgba(255,255,255,.2);color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">รีวิวด่วน</span><?php endif; ?>
      </div>
      <div style="font-size:32px;font-weight:700;color:white;line-height:1"><?= $pendingCount ?></div>
      <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">รออนุมัติ</div>
    </div>
  </div>
  <div class="card" style="border:none;background:linear-gradient(135deg,#059669,#047857);box-shadow:0 4px 16px rgba(5,150,105,.3)">
    <div class="card-body" style="padding:18px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <i class="bi bi-activity" style="color:rgba(255,255,255,.8);font-size:22px"></i>
      </div>
      <div style="font-size:32px;font-weight:700;color:white;line-height:1"><?= $utilToday ?><span style="font-size:18px">%</span></div>
      <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">อัตราการใช้งานวันนี้</div>
    </div>
  </div>
  <div class="card" style="border:none;background:linear-gradient(135deg,#7C3AED,#6D28D9);box-shadow:0 4px 16px rgba(124,58,237,.3)">
    <div class="card-body" style="padding:18px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <i class="bi bi-calendar2-check" style="color:rgba(255,255,255,.8);font-size:22px"></i>
      </div>
      <div style="font-size:32px;font-weight:700;color:white;line-height:1"><?= $bookingsThisWeek ?></div>
      <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">การจองสัปดาห์นี้</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:14px;margin-bottom:14px">
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:20px">
      <h6 style="font-weight:700;margin:0 0 4px">อัตราการใช้งาน AI (% ต่อวัน)</h6>
      <p style="color:#64748B;font-size:12px;margin:0 0 16px">สัปดาห์ปัจจุบัน เทียบรายประเภทบัญชี</p>
      <div style="height:220px;position:relative">
        <canvas id="adminUsageChart"></canvas>
      </div>
    </div>
  </div>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:20px">
      <h6 style="font-weight:700;margin:0 0 16px">สถานะ AI Account Pool</h6>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach ($accounts as $ac): ?>
          <div style="padding:12px;border:1px solid var(--bs-border-color);border-radius:10px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
              <div style="font-size:13px;font-weight:600"><?= e($ac['name']) ?></div>
              <span class="<?= $ac['statusCls'] ?>"><?= e($ac['statusLabel']) ?></span>
            </div>
            <div style="font-size:11px;color:#64748B"><?= e($ac['provider']) ?> · <?= (int) $ac['usedToday'] ?>/<?= (int) $ac['totalSlots'] ?> slots วันนี้</div>
            <div style="background:var(--bs-border-color);border-radius:3px;height:4px;margin-top:8px;overflow:hidden">
              <div style="background:#2563EB;width:<?= e($ac['usagePct']) ?>;height:100%;border-radius:3px"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div>
        <h6 style="font-weight:700;margin:0">รออนุมัติสมาชิก</h6>
        <p style="color:#64748B;font-size:12px;margin:2px 0 0"><?= (int) $pendingCount ?> รายการรอการตรวจสอบ</p>
      </div>
      <a href="<?= url('admin/members.php') ?>?status=pending" class="btn btn-sm btn-primary" style="background:#2563EB;border:none;font-size:12px">ดูทั้งหมด</a>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($pendingMembers as $pm): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--bs-secondary-bg);border-radius:10px">
          <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#0EA5E9);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:14px;flex-shrink:0"><?= e($pm['initial']) ?></div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:14px"><?= e($pm['name']) ?></div>
            <div style="font-size:12px;color:#64748B"><?= e($pm['student_id'] ?? '') ?> · <?= e($pm['major'] ?? '') ?></div>
          </div>
          <div style="display:flex;gap:6px">
            <form method="post" style="margin:0">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="id" value="<?= (int) $pm['id'] ?>">
              <button type="submit" class="action-btn-ok"><i class="bi bi-check-lg"></i> อนุมัติ</button>
            </form>
            <form method="post" style="margin:0" onsubmit="return confirm('ปฏิเสธและลบคำขอสมัครนี้?')">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="id" value="<?= (int) $pm['id'] ?>">
              <button type="submit" class="action-btn-err"><i class="bi bi-x-lg"></i></button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$pendingMembers): ?>
        <div style="text-align:center;padding:24px;color:#94A3B8;font-size:13px">ไม่มีสมาชิกรออนุมัติ</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  window.addEventListener('DOMContentLoaded', function () {
    if (window.initUsageChart) {
      window.initUsageChart('adminUsageChart', <?= json_encode($dayLabels, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($datasets, JSON_UNESCAPED_UNICODE) ?>);
    }
  });
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
