<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    if (($_POST['action'] ?? '') === 'review') {
        $appealId = (int) ($_POST['appeal_id'] ?? 0);
        $status   = $_POST['status'] ?? '';
        $result   = SuspensionAppeal::review($appealId, (int) $user['id'], $status, $_POST['response'] ?? '');
        if ($result['ok'] && $status === 'accepted' && !empty($_POST['lift_suspension'])) {
            $appeal = SuspensionAppeal::find($appealId);
            if ($appeal) {
                Member::activate((int) $appeal['user_id']);   // accepting + lifting the suspension
            }
        }
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกผลการพิจารณาเรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    }
    header('Location: ' . url('admin/appeals.php') . (isset($_POST['status_filter']) ? '?status=' . urlencode((string) $_POST['status_filter']) : ''));
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$validFilters = array_merge(['all'], array_keys(SuspensionAppeal::STATUS_LABELS));
if (!in_array($statusFilter, $validFilters, true)) {
    $statusFilter = 'all';
}
$appeals = SuspensionAppeal::listForAdmin($statusFilter);

$statusStyle = [
    'pending'   => ['#D97706', '#FFFBEB', '#FDE68A', 'bi-hourglass-split'],
    'reviewing' => ['#2563EB', '#EFF6FF', '#BFDBFE', 'bi-search'],
    'accepted'  => ['#059669', '#ECFDF5', '#6EE7B7', 'bi-check-circle'],
    'rejected'  => ['#DC2626', '#FEF2F2', '#FECACA', 'bi-x-circle'],
];

$activeNav = 'admin-appeals';
require __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom:16px">
  <h5 style="font-weight:700;margin:0"><i class="bi bi-megaphone me-2" style="color:#2563EB"></i>คำอุทธรณ์การระงับสิทธิ์</h5>
  <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">ตรวจสอบและพิจารณาคำอุทธรณ์ที่สมาชิกยื่นเข้ามา</p>
</div>

<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
  <?php
    $chips = ['all' => 'ทั้งหมด'] + SuspensionAppeal::STATUS_LABELS;
    foreach ($chips as $key => $label):
      $active = $statusFilter === $key;
  ?>
    <a href="<?= url('admin/appeals.php') ?>?status=<?= e($key) ?>" style="text-decoration:none;border-radius:20px;font-size:12px;padding:5px 14px;border:1.5px solid <?= $active ? '#2563EB' : 'var(--bs-border-color)' ?>;font-weight:600;color:<?= $active ? '#2563EB' : 'var(--bs-secondary-color)' ?>;background:<?= $active ? 'rgba(37,99,235,.08)' : 'transparent' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$appeals): ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:40px;text-align:center;color:var(--bs-tertiary-color)">
      <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:10px"></i>ไม่มีคำอุทธรณ์ในหมวดนี้
    </div>
  </div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($appeals as $a): ?>
      <?php
        [$c, $bg, $bd, $ic] = $statusStyle[$a['status']] ?? ['#6B7280', 'var(--bs-secondary-bg)', 'var(--bs-border-color)', 'bi-dot'];
        $open = in_array($a['status'], ['pending', 'reviewing'], true);
      ?>
      <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
        <div class="card-body" style="padding:16px 18px">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:8px">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#EFF6FF,#DBEAFE);display:flex;align-items:center;justify-content:center;font-weight:700;color:#2563EB;font-size:14px;flex-shrink:0"><?= e(mb_substr((string) $a['member_name'], 0, 1)) ?></div>
              <div style="min-width:0">
                <div style="font-weight:700;font-size:14px"><?= e($a['member_name']) ?> <span style="font-size:11px;font-weight:400;color:var(--bs-tertiary-color)">(<?= $a['member_role'] === 'teacher' ? 'ครูผู้สอน' : 'นักศึกษา' ?>)</span></div>
                <div style="font-size:11px;color:var(--bs-tertiary-color)"><?= e($a['member_email']) ?> · ยื่นเมื่อ <?= e(Booking::thaiDate(new DateTimeImmutable($a['created_at']))) ?></div>
              </div>
            </div>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:<?= $c ?>;background:<?= $bg ?>;border:1px solid <?= $bd ?>;border-radius:20px;padding:3px 12px;white-space:nowrap"><i class="bi <?= $ic ?>"></i><?= e(SuspensionAppeal::STATUS_LABELS[$a['status']] ?? $a['status']) ?></span>
          </div>
          <?php if (!empty($a['suspended_reason']) && $a['member_status'] === 'suspended'): ?>
            <div style="font-size:12px;color:var(--bs-secondary-color);margin-bottom:8px"><i class="bi bi-slash-circle me-1" style="color:#DC2626"></i>เหตุผลการระงับ: <?= e($a['suspended_reason']) ?></div>
          <?php endif; ?>
          <div style="font-size:13.5px;color:var(--bs-body-color);white-space:pre-wrap;line-height:1.6;background:var(--bs-secondary-bg);border-radius:8px;padding:12px"><?= e($a['message']) ?></div>
          <?php if (!empty($a['files'])): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
              <?php foreach ($a['files'] as $f):
                $furl = url('uploads/appeals/' . $f['filename']);
                $isImg = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $f['filename']); ?>
                <?php if ($isImg): ?>
                  <a href="<?= e($furl) ?>" target="_blank" rel="noopener"><img src="<?= e($furl) ?>" alt="<?= e($f['original_name'] ?? '') ?>" style="max-width:130px;max-height:130px;border-radius:8px;border:1px solid var(--bs-border-color);object-fit:cover"></a>
                <?php else: ?>
                  <a href="<?= e($furl) ?>" target="_blank" rel="noopener" style="font-size:12.5px;text-decoration:none;color:#2563EB;border:1px solid var(--bs-border-color);border-radius:8px;padding:6px 10px;display:inline-flex;align-items:center;gap:6px"><i class="bi bi-file-earmark-pdf" style="color:#DC2626"></i><?= e($f['original_name'] ?? 'ไฟล์แนบ') ?></a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($a['admin_response'])): ?>
            <div style="margin-top:12px;background:<?= $bg ?>;border-radius:8px;padding:12px">
              <div style="font-size:11px;font-weight:700;color:<?= $c ?>;margin-bottom:3px"><i class="bi bi-reply me-1"></i>คำตอบของผู้ดูแลระบบ<?= !empty($a['reviewer_name']) ? ' (' . e($a['reviewer_name']) . ')' : '' ?></div>
              <div style="font-size:13px;color:var(--bs-body-color);white-space:pre-wrap;line-height:1.5"><?= e($a['admin_response']) ?></div>
            </div>
          <?php endif; ?>
          <div style="margin-top:14px;border-top:1px solid var(--bs-border-color);padding-top:12px">
            <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:12px"
                    data-review-appeal data-id="<?= (int) $a['id'] ?>" data-name="<?= e($a['member_name']) ?>"
                    data-status="<?= e($a['status']) ?>" data-response="<?= e($a['admin_response'] ?? '') ?>"
                    data-suspended="<?= $a['member_status'] === 'suspended' ? '1' : '0' ?>" data-filter="<?= e($statusFilter) ?>">
              <i class="bi bi-pencil-square me-1"></i><?= $open ? 'พิจารณา' : 'แก้ไขผล' ?>
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Review modal -->
<div class="modal fade" id="reviewAppealModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="review">
        <input type="hidden" name="appeal_id" id="reviewAppealId">
        <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-gavel me-2" style="color:#2563EB"></i>พิจารณาคำอุทธรณ์ — <span id="reviewAppealName"></span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ผลการพิจารณา</label>
            <select name="status" id="reviewStatus" class="form-select" style="font-size:13px">
              <option value="reviewing">กำลังพิจารณา</option>
              <option value="accepted">อนุมัติอุทธรณ์</option>
              <option value="rejected">ปฏิเสธอุทธรณ์</option>
            </select>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ข้อความถึงสมาชิก <span style="font-weight:400;color:var(--bs-tertiary-color)">(ไม่บังคับ)</span></label>
            <textarea name="response" id="reviewResponse" maxlength="3000" rows="4" class="form-control" placeholder="เหตุผลหรือคำชี้แจงการพิจารณา" style="font-size:13px"></textarea>
          </div>
          <label id="reviewLiftWrap" style="display:none;gap:8px;align-items:flex-start;padding:10px;border:1px solid #6EE7B7;background:#ECFDF5;border-radius:8px;cursor:pointer">
            <input type="checkbox" name="lift_suspension" value="1" class="form-check-input" style="margin-top:2px;flex-shrink:0">
            <span style="font-size:12.5px;color:#065F46">ปลดระงับสิทธิ์สมาชิกทันทีเมื่ออนุมัติอุทธรณ์</span>
          </label>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none"><i class="bi bi-save me-1"></i>บันทึกผล</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
