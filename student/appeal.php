<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    if (($_POST['action'] ?? '') === 'submit_appeal') {
        $result = SuspensionAppeal::submit((int) $user['id'], $_POST['message'] ?? '', $_FILES['attachments'] ?? null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ยื่นอุทธรณ์เรียบร้อยแล้ว กรุณารอผลการพิจารณา' : ($result['error'] ?? 'ยื่นอุทธรณ์ไม่สำเร็จ'));
    }
    header('Location: ' . url('student/appeal.php'));
    exit;
}

$suspension = Member::suspensionInfo((int) $user['id']);
$appeals    = SuspensionAppeal::forUser((int) $user['id']);
$hasOpen    = SuspensionAppeal::hasOpen((int) $user['id']);
$canAppeal  = $suspension !== null && !$hasOpen;

$statusStyle = [
    'pending'   => ['#D97706', '#FFFBEB', '#FDE68A', 'bi-hourglass-split'],
    'reviewing' => ['#2563EB', '#EFF6FF', '#BFDBFE', 'bi-search'],
    'accepted'  => ['#059669', '#ECFDF5', '#6EE7B7', 'bi-check-circle'],
    'rejected'  => ['#DC2626', '#FEF2F2', '#FECACA', 'bi-x-circle'],
];

$activeNav = 'appeal';
require __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom:20px">
  <h5 style="font-weight:700;margin:0"><i class="bi bi-megaphone me-2" style="color:#2563EB"></i>ยื่นอุทธรณ์การระงับสิทธิ์</h5>
  <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">ยื่นคำอุทธรณ์ต่อผู้ดูแลระบบ และติดตามผลการพิจารณาได้ที่นี่</p>
</div>

<?php if ($suspension): ?>
  <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:16px 18px;margin-bottom:16px;max-width:720px">
    <div style="font-weight:700;color:#991B1B;font-size:14px;margin-bottom:4px"><i class="bi bi-slash-circle-fill me-1"></i>บัญชีของคุณถูกระงับสิทธิ์การจองอยู่</div>
    <?php if (!empty($suspension['reason'])): ?>
      <div style="font-size:13px;color:#7F1D1D;white-space:pre-wrap;line-height:1.5"><strong>เหตุผล:</strong> <?= e($suspension['reason']) ?></div>
    <?php endif; ?>
    <?php if (!empty($suspension['untilLabel'])): ?>
      <div style="font-size:12.5px;color:#991B1B;margin-top:4px"><i class="bi bi-clock me-1"></i>จะปลดระงับอัตโนมัติในวันที่ <strong><?= e($suspension['untilLabel']) ?></strong></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($canAppeal): ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);max-width:720px;margin-bottom:16px">
    <div class="card-body" style="padding:20px">
      <h6 style="font-weight:700;margin:0 0 12px"><i class="bi bi-pencil-square me-2" style="color:#2563EB"></i>ยื่นคำอุทธรณ์</h6>
      <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="submit_appeal">
        <div style="margin-bottom:14px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">เหตุผล / คำชี้แจง <span style="color:#DC2626">*</span></label>
          <textarea name="message" required maxlength="3000" rows="5" class="form-control" placeholder="อธิบายเหตุผลที่ขออุทธรณ์การระงับสิทธิ์" style="font-size:13px"></textarea>
        </div>
        <div style="margin-bottom:16px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">แนบหลักฐาน/รูปภาพ <span style="font-weight:400;color:var(--bs-tertiary-color)">(ไม่บังคับ · รูปภาพหรือ PDF · ไม่เกิน 5 ไฟล์)</span></label>
          <input type="file" name="attachments[]" multiple accept="image/*,.pdf" class="form-control" style="font-size:13px">
        </div>
        <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px"><i class="bi bi-send me-1"></i>ยื่นอุทธรณ์</button>
      </form>
    </div>
  </div>
<?php elseif ($hasOpen): ?>
  <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#92400E;max-width:720px">
    <i class="bi bi-hourglass-split me-1"></i>คุณมีคำอุทธรณ์ที่กำลังรอการพิจารณาอยู่ — ติดตามสถานะได้ด้านล่าง
  </div>
<?php elseif (!$suspension): ?>
  <div style="background:#ECFDF5;border:1px solid #6EE7B7;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#065F46;max-width:720px">
    <i class="bi bi-check-circle me-1"></i>ขณะนี้บัญชีของคุณไม่ได้ถูกระงับสิทธิ์ — ไม่จำเป็นต้องยื่นอุทธรณ์
  </div>
<?php endif; ?>

<h6 style="font-weight:700;margin:4px 0 12px;max-width:720px">ประวัติการยื่นอุทธรณ์</h6>
<?php if (!$appeals): ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);max-width:720px">
    <div class="card-body" style="padding:32px;text-align:center;color:var(--bs-tertiary-color)">
      <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px"></i>ยังไม่มีการยื่นอุทธรณ์
    </div>
  </div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:12px;max-width:720px">
    <?php foreach ($appeals as $a): ?>
      <?php [$c, $bg, $bd, $ic] = $statusStyle[$a['status']] ?? ['#6B7280', 'var(--bs-secondary-bg)', 'var(--bs-border-color)', 'bi-dot']; ?>
      <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
        <div class="card-body" style="padding:16px 18px">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px">
            <span style="font-size:12px;color:var(--bs-tertiary-color)">ยื่นเมื่อ <?= e(Booking::thaiDate(new DateTimeImmutable($a['created_at']))) ?> เวลา <?= e((new DateTimeImmutable($a['created_at']))->format('H:i')) ?> น.</span>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:<?= $c ?>;background:<?= $bg ?>;border:1px solid <?= $bd ?>;border-radius:20px;padding:3px 12px"><i class="bi <?= $ic ?>"></i><?= e(SuspensionAppeal::STATUS_LABELS[$a['status']] ?? $a['status']) ?></span>
          </div>
          <div style="font-size:13.5px;color:var(--bs-body-color);white-space:pre-wrap;line-height:1.6"><?= e($a['message']) ?></div>
          <?php if (!empty($a['files'])): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
              <?php foreach ($a['files'] as $f):
                $furl = url('uploads/appeals/' . $f['filename']);
                $isImg = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $f['filename']); ?>
                <?php if ($isImg): ?>
                  <a href="<?= e($furl) ?>" target="_blank" rel="noopener"><img src="<?= e($furl) ?>" alt="<?= e($f['original_name'] ?? '') ?>" style="max-width:120px;max-height:120px;border-radius:8px;border:1px solid var(--bs-border-color);object-fit:cover"></a>
                <?php else: ?>
                  <a href="<?= e($furl) ?>" target="_blank" rel="noopener" style="font-size:12.5px;text-decoration:none;color:#2563EB;border:1px solid var(--bs-border-color);border-radius:8px;padding:6px 10px;display:inline-flex;align-items:center;gap:6px"><i class="bi bi-file-earmark-pdf" style="color:#DC2626"></i><?= e($f['original_name'] ?? 'ไฟล์แนบ') ?></a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($a['admin_response']) || !empty($a['reviewed_at'])): ?>
            <div style="margin-top:12px;border-top:1px solid var(--bs-border-color);padding-top:12px;background:<?= $bg ?>;border-radius:8px;padding:12px">
              <div style="font-size:11px;font-weight:700;color:<?= $c ?>;margin-bottom:3px"><i class="bi bi-reply me-1"></i>คำตอบจากผู้ดูแลระบบ<?= !empty($a['reviewer_name']) ? ' (' . e($a['reviewer_name']) . ')' : '' ?></div>
              <div style="font-size:13px;color:var(--bs-body-color);white-space:pre-wrap;line-height:1.5"><?= e($a['admin_response'] ?? '—') ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
