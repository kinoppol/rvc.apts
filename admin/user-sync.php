<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $full = ($_POST['action'] ?? '') === 'sync_full';
    @set_time_limit(300);
    $r = OaUserSync::run($full);
    $summary = sprintf('เพิ่ม %d · อัปเดต %d · ถูกระงับ %d · ผูกบัญชีอัตโนมัติ %d (%d หน้า)',
        $r['added'], $r['updated'], $r['suspended'], $r['linked'], $r['pages']);
    flash_set($r['ok'] ? 'ok' : 'err', $r['ok']
        ? ($r['mode'] === 'full' ? 'ซิงค์ทั้งหมดเรียบร้อย — ' : 'ซิงค์ส่วนที่เปลี่ยนแปลงเรียบร้อย — ') . $summary
        : 'ซิงค์ไม่สำเร็จ: ' . $r['error']);
    header('Location: ' . url('admin/user-sync.php'));
    exit;
}

$configured = OaUserSync::isConfigured();
$since      = OaUserSync::lastSince();
$stats      = OaUserSync::stats();
$runs       = OaUserSync::recentRuns(20);
$blocked    = OaUserSync::blockedAccounts();

$isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$startUrl = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('sso-login.php') . '?auto=1';

$card = 'border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:24px;margin-bottom:16px';
$activeNav = 'user-sync';
require __DIR__ . '/../includes/header.php';
?>
<h5 style="font-weight:700;margin:0 0 20px">ซิงค์ผู้ใช้จาก ONE-RVC</h5>

<div class="card" style="<?= $card ?>">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px">
    <?php foreach ([
        ['ผู้ใช้ใน ONE-RVC', $stats['total'], '#2563EB'],
        ['ใช้งานอยู่', $stats['active'], '#16A34A'],
        ['ถูกระงับ', $stats['suspended'], '#DC2626'],
        ['ผูกกับบัญชีในระบบนี้', $stats['linked'], '#7C3AED'],
    ] as [$label, $n, $color]): ?>
      <div style="border:1px solid var(--bs-border-color);border-radius:10px;padding:12px 14px">
        <div style="font-size:11.5px;color:var(--bs-secondary-color)"><?= e($label) ?></div>
        <div style="font-size:22px;font-weight:700;color:<?= $color ?>"><?= number_format($n) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div style="font-size:13px;margin-bottom:14px;line-height:1.9">
    <div>สถานะการตั้งค่า:
      <?php if ($configured): ?>
        <span class="badge bg-success-subtle text-success-emphasis">พร้อมใช้งาน</span>
      <?php else: ?>
        <span class="badge bg-danger-subtle text-danger-emphasis">ยังไม่ได้ตั้งค่า client_secret</span>
      <?php endif; ?>
      <span style="color:var(--bs-secondary-color)">· client_id <code><?= e(ONE_RVC_CLIENT_ID) ?></code> · ประเภท <code><?= e(ONE_RVC_SYNC_TYPE !== '' ? ONE_RVC_SYNC_TYPE : 'ทุกประเภทที่ได้รับอนุญาต') ?></code></span>
    </div>
    <div>ซิงค์สำเร็จล่าสุด (since รอบถัดไป): <strong><?= $since !== null ? e($since) : 'ยังไม่เคย — รอบถัดไปจะดึงทั้งหมด' ?></strong></div>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <form method="post" style="margin:0">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="sync_incremental">
      <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px" <?= $configured ? '' : 'disabled' ?>>
        <i class="bi bi-arrow-repeat me-1"></i>ซิงค์ส่วนที่เปลี่ยนแปลง
      </button>
    </form>
    <form method="post" style="margin:0" onsubmit="return confirm('ดึงรายชื่อผู้ใช้ทั้งหมดจาก ONE-RVC ใหม่อีกครั้ง?')">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="sync_full">
      <button type="submit" class="btn btn-outline-secondary" style="font-size:13px" <?= $configured ? '' : 'disabled' ?>>
        <i class="bi bi-cloud-download me-1"></i>ซิงค์ทั้งหมดใหม่
      </button>
    </form>
  </div>

  <div style="font-size:11.5px;color:var(--bs-secondary-color);margin-top:14px;line-height:1.8">
    <i class="bi bi-info-circle me-1"></i>การซิงค์ไม่สร้างบัญชีใหม่ในระบบนี้ (การสมัครยังต้องได้รับอนุมัติและเลือกสาขา/วิชา) —
    จะผูกบัญชีเดิมที่อีเมลตรงกันให้อัตโนมัติ และบัญชีที่ผูกไว้ซึ่งถูกระงับใน ONE-RVC จะเข้าสู่ระบบไม่ได้จนกว่าจะเปิดใช้งานอีกครั้ง
    (ยกเว้นผู้ดูแลระบบ) · ตั้งเวลาอัตโนมัติด้วย cron: <code>php sync-users.php</code> (เพิ่ม <code>--full</code> เพื่อดึงทั้งหมด)
  </div>
</div>

<div class="card" style="<?= $card ?>">
  <h6 style="font-weight:700;margin:0 0 10px"><i class="bi bi-box-arrow-in-right me-2" style="color:#2563EB"></i>URL เริ่ม SSO ของแอป</h6>
  <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-bottom:8px">
    นำ URL นี้ไปกรอกในช่อง “URL เริ่ม SSO ของแอป” ที่หน้าแก้ไขแอปใน ONE-RVC เพื่อให้ไทล์ในหน้า Workspace พาผู้ใช้เข้าระบบได้ในคลิกเดียว
  </div>
  <input class="form-control" readonly value="<?= e($startUrl) ?>" onclick="this.select()" style="font-size:13px;font-family:monospace">
</div>

<?php if ($blocked): ?>
<div class="card" style="<?= $card ?>">
  <h6 style="font-weight:700;margin:0 0 12px"><i class="bi bi-slash-circle me-2" style="color:#DC2626"></i>บัญชีที่ถูกระงับจาก ONE-RVC (<?= count($blocked) ?>)</h6>
  <div class="table-responsive">
    <table class="table table-sm" style="font-size:13px;margin:0">
      <thead><tr><th>ชื่อในระบบนี้</th><th>อีเมล</th><th>ชื่อใน ONE-RVC</th><th>แผนก</th><th>ซิงค์เมื่อ</th></tr></thead>
      <tbody>
        <?php foreach ($blocked as $b): ?>
          <tr>
            <td><?= e($b['name']) ?></td>
            <td><?= e($b['email']) ?></td>
            <td><?= e(trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? ''))) ?></td>
            <td><?= e((string) $b['department']) ?></td>
            <td><?= e($b['synced_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card" style="<?= $card ?>">
  <h6 style="font-weight:700;margin:0 0 12px"><i class="bi bi-clock-history me-2" style="color:#2563EB"></i>ประวัติการซิงค์</h6>
  <div class="table-responsive">
    <table class="table table-sm" style="font-size:12.5px;margin:0">
      <thead><tr><th>#</th><th>เริ่ม</th><th>แบบ</th><th>ผล</th><th>หน้า</th><th>เพิ่ม</th><th>อัปเดต</th><th>ระงับ</th><th>ผูกบัญชี</th><th>server_time / ข้อผิดพลาด</th></tr></thead>
      <tbody>
        <?php foreach ($runs as $r): ?>
          <tr>
            <td><?= (int) $r['id'] ?></td>
            <td style="white-space:nowrap"><?= e($r['started_at']) ?></td>
            <td><?= $r['mode'] === 'full' ? 'ทั้งหมด' : 'ส่วนที่เปลี่ยน' ?></td>
            <td>
              <?php if ($r['finished_at'] === null): ?><span class="badge bg-secondary-subtle text-secondary-emphasis">กำลังทำงาน</span>
              <?php elseif ($r['ok']): ?><span class="badge bg-success-subtle text-success-emphasis">สำเร็จ</span>
              <?php else: ?><span class="badge bg-danger-subtle text-danger-emphasis">ล้มเหลว</span><?php endif; ?>
            </td>
            <td><?= (int) $r['pages'] ?></td>
            <td><?= (int) $r['added'] ?></td>
            <td><?= (int) $r['updated'] ?></td>
            <td><?= (int) $r['suspended'] ?></td>
            <td><?= (int) $r['linked'] ?></td>
            <td style="max-width:360px;word-break:break-word;color:<?= $r['ok'] ? 'inherit' : '#DC2626' ?>"><?= e((string) ($r['ok'] ? $r['server_time'] : $r['error'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$runs): ?>
          <tr><td colspan="10" style="text-align:center;color:var(--bs-tertiary-color);padding:14px">ยังไม่เคยซิงค์</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
