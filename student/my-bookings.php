<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    if (($_POST['action'] ?? '') === 'cancel') {
        $result = Booking::cancel($user['id'], (int) ($_POST['id'] ?? 0));
        if ($result['ok']) {
            flash_set('warn', 'ยกเลิกการจองเรียบร้อยแล้ว');
        } else {
            flash_set('err', $result['error'] ?? 'ไม่สามารถยกเลิกได้');
        }
    }
    header('Location: ' . url('student/my-bookings.php'));
    exit;
}

$filters = ['all' => 'ทั้งหมด', 'upcoming' => 'กำลังจะมาถึง', 'completed' => 'เสร็จสิ้น', 'cancelled' => 'ยกเลิก'];
$filter = $_GET['filter'] ?? 'all';
if (!isset($filters[$filter])) {
    $filter = 'all';
}
$bookings = Booking::listForUser($user['id'], $filter);

$activeNav = 'my-bookings';
require __DIR__ . '/../includes/header.php';
?>
<h5 style="font-weight:700;margin:0 0 20px">การจองของฉัน</h5>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:20px">
    <div style="display:flex;gap:0;border-bottom:2px solid var(--bs-border-color);margin-bottom:20px;flex-wrap:wrap">
      <?php foreach ($filters as $key => $label): ?>
        <a href="<?= url('student/my-bookings.php') ?>?filter=<?= $key ?>" style="text-decoration:none;padding:8px 16px;font-size:13px;font-weight:600;<?= $filter === $key ? 'color:#2563EB;border-bottom:2px solid #2563EB;margin-bottom:-2px' : 'color:var(--bs-secondary-color)' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($bookings as $bk): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px;border:1px solid var(--bs-border-color);border-radius:10px">
          <div style="width:44px;height:44px;border-radius:10px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-calendar3" style="color:#2563EB;font-size:18px"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:14px"><?= e($bk['slotLabel']) ?></div>
            <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:2px"><?= e($bk['dateLabel']) ?> · <?= e($bk['ai_name']) ?></div>
          </div>
          <span class="<?= $bk['badgeCls'] ?>"><?= e($bk['statusLabel']) ?></span>
          <?php if ($bk['canCancel']): ?>
            <form method="post" action="<?= url('student/my-bookings.php') ?>" style="margin:0" onsubmit="return confirm('ยืนยันการยกเลิกการจองนี้?')">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="id" value="<?= (int) $bk['id'] ?>">
              <button type="submit" class="action-btn-err"><i class="bi bi-x-circle me-1"></i>ยกเลิก</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$bookings): ?>
        <div style="text-align:center;padding:40px 16px;color:var(--bs-tertiary-color)">
          <i class="bi bi-calendar-x" style="font-size:32px;display:block;margin-bottom:10px"></i>
          <div style="font-size:14px">ไม่มีรายการจองในหมวดนี้</div>
          <a href="<?= url('student/booking.php') ?>" style="font-size:13px;color:#2563EB;text-decoration:none;font-weight:600;display:inline-block;margin-top:10px">จองคิวตอนนี้ <i class="bi bi-arrow-right"></i></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
