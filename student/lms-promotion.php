<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);
$uid  = (int) $user['id'];

// $_POST is wiped when the body exceeds post_max_size, which would otherwise look
// like a CSRF failure — catch it before Csrf::check(), as admin/slots.php does.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash_set('err', 'ไฟล์ที่แนบมามีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รับได้');
    header('Location: ' . url('student/lms.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $levelId = (int) ($_POST['level_id'] ?? 0);

    if (($_POST['action'] ?? '') === 'submit_mission') {
        $result = LmsPromotion::submit($uid, $levelId, (string) ($_POST['mission_text'] ?? ''), $_FILES['files'] ?? null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok']
            ? 'ส่งภารกิจเรียบร้อยแล้ว รอผู้ดูแลตรวจสอบ'
            : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    }
    header('Location: ' . url('student/lms-promotion.php') . '?level=' . $levelId);
    exit;
}

$levelId = (int) ($_GET['level'] ?? 0);
$level   = LmsLevel::find($levelId);
if (!$level || (int) $level['is_published'] !== 1) {
    flash_set('err', 'ไม่พบระดับนี้ หรือยังไม่เปิดให้เรียน');
    header('Location: ' . url('student/lms.php'));
    exit;
}

$state   = LmsProgress::levelState($uid, $levelId);
$canSend = LmsProgress::canRequestPromotion($uid, $levelId);
$history = LmsPromotion::historyFor($uid, $levelId);
$files   = LmsPromotion::filesFor(array_map(fn ($h) => (int) $h['id'], $history));
$target  = $level['promo_group_id'] !== null ? UserGroup::find((int) $level['promo_group_id']) : null;

$activeNav = 'lms';
require __DIR__ . '/../includes/header.php';

$statusChip = [
    'pending'  => ['#D97706', '#FFFBEB', 'bi-hourglass-split',        'รอผู้ดูแลตรวจ'],
    'approved' => ['#059669', '#ECFDF5', 'bi-patch-check-fill',       'อนุมัติแล้ว'],
    'revise'   => ['#D97706', '#FFFBEB', 'bi-arrow-counterclockwise', 'ขอให้แก้ไข'],
    'rejected' => ['#DC2626', '#FEF2F2', 'bi-x-circle',               'ไม่ผ่าน'],
];
?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--bs-secondary-color);margin-bottom:12px;flex-wrap:wrap">
  <a href="<?= url('student/lms.php') ?>" style="color:#2563EB;text-decoration:none">บทเรียน</a>
  <span>/</span>
  <a href="<?= url('student/lms-level.php') ?>?id=<?= $levelId ?>" style="color:#2563EB;text-decoration:none"><?= e($level['title']) ?></a>
  <span>/</span><span style="font-weight:600;color:var(--bs-body-color)">ภารกิจพิสูจน์ทักษะ</span>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
  <div class="card-body" style="padding:20px 22px">
    <h5 style="font-weight:700;margin:0 0 4px;font-size:16.5px">
      <i class="bi bi-patch-check me-2" style="color:#059669"></i>ภารกิจพิสูจน์ทักษะ — <?= e($level['title']) ?>
    </h5>
    <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-bottom:14px">
      ส่งผลงานให้ผู้ดูแลตรวจ เมื่อผ่านแล้วระบบจะย้ายคุณเข้ากลุ่มสิทธิ์ใหม่โดยอัตโนมัติ
    </div>

    <?php if (!empty($level['mission_brief'])): ?>
      <div style="background:var(--bs-secondary-bg);border-radius:10px;padding:14px 16px;font-size:13.5px;line-height:1.85">
        <?= nl2br(e($level['mission_brief'])) ?>
      </div>
    <?php else: ?>
      <div style="background:#FFFBEB;border-left:3px solid #D97706;border-radius:8px;padding:11px 13px;font-size:13px;color:#92400E">
        <i class="bi bi-exclamation-triangle me-1"></i>ผู้ดูแลยังไม่ได้กำหนดโจทย์ภารกิจของระดับนี้
      </div>
    <?php endif; ?>

    <?php if ($target): ?>
      <div style="margin-top:12px;font-size:12.5px;color:var(--bs-secondary-color)">
        <i class="bi bi-diagram-3 me-1"></i>กลุ่มปลายทางเมื่อภารกิจผ่าน:
        <strong style="color:#059669"><?= e($target['name']) ?></strong>
        <?php if ($target['weekly_quota'] !== null || $target['max_advance_days'] !== null): ?>
          <span style="color:var(--bs-tertiary-color)">
            (<?= $target['weekly_quota'] !== null ? 'โควตา ' . (int) $target['weekly_quota'] . ' ครั้ง/สัปดาห์' : 'โควตาตามค่าเริ่มต้น' ?>,
             <?= $target['max_advance_days'] !== null ? 'จองล่วงหน้า ' . (int) $target['max_advance_days'] . ' วัน' : 'จองล่วงหน้าตามค่าเริ่มต้น' ?>)
          </span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$state['postPassed']): ?>
  <div class="card" style="border:1px solid var(--bs-border-color)">
    <div class="card-body" style="padding:30px;text-align:center;color:var(--bs-secondary-color);font-size:13.5px;line-height:1.8">
      <i class="bi bi-lock" style="font-size:26px;display:block;margin-bottom:10px"></i>
      ต้องผ่านแบบทดสอบหลังเรียนของระดับนี้ที่ <?= (int) $level['pass_percent'] ?>% ขึ้นไปก่อน จึงจะส่งภารกิจได้
      <div style="margin-top:12px">
        <a href="<?= url('student/lms-level.php') ?>?id=<?= $levelId ?>" class="btn btn-sm"
           style="background:#2563EB;border:none;color:white;font-size:13px;text-decoration:none">กลับไปเรียนต่อ</a>
      </div>
    </div>
  </div>

<?php else: ?>

  <?php if ($canSend): ?>
    <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
      <div class="card-body" style="padding:0">
        <div style="padding:15px 20px;border-bottom:1px solid var(--bs-border-color);font-weight:700;font-size:14px">
          <i class="bi bi-send me-2" style="color:#059669"></i><?= $history ? 'ส่งภารกิจอีกครั้ง' : 'ส่งภารกิจ' ?>
        </div>
        <form method="post" enctype="multipart/form-data">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="submit_mission">
          <input type="hidden" name="level_id" value="<?= $levelId ?>">
          <div style="padding:20px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">อธิบายผลงานของคุณ *</label>
            <textarea name="mission_text" rows="6" required maxlength="5000" class="form-control" style="font-size:13.5px"
                      placeholder="อธิบายว่าคุณทำอะไร ใช้ AI ตัวไหน ใช้ prompt อย่างไร และปรับแก้ผลลัพธ์อย่างไรบ้าง"></textarea>

            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin:16px 0 6px">
              แนบไฟล์ผลงาน (รูปภาพหรือ PDF สูงสุด <?= LmsPromotion::MAX_FILES ?> ไฟล์ ไฟล์ละไม่เกิน 5 MB)
            </label>
            <input type="file" name="files[]" multiple class="form-control" style="font-size:13px"
                   accept="image/png,image/jpeg,image/gif,image/webp,application/pdf">
          </div>
          <div style="padding:14px 20px;border-top:1px solid var(--bs-border-color);display:flex;justify-content:flex-end">
            <button type="submit" class="btn btn-sm" style="background:#059669;border:none;color:white;font-size:13px">
              <i class="bi bi-send me-1"></i>ส่งภารกิจให้ผู้ดูแลตรวจ
            </button>
          </div>
        </form>
      </div>
    </div>
  <?php elseif (!$target): ?>
    <div class="card" style="border:1px solid var(--bs-border-color);margin-bottom:16px">
      <div class="card-body" style="padding:24px;text-align:center;color:var(--bs-secondary-color);font-size:13.5px">
        <i class="bi bi-hourglass" style="font-size:24px;display:block;margin-bottom:9px"></i>
        ระดับนี้ยังไม่ได้กำหนดกลุ่มปลายทาง — กรุณาติดต่อผู้ดูแลระบบ
      </div>
    </div>
  <?php endif; ?>

  <?php if ($history): ?>
    <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <div class="card-body" style="padding:0">
        <div style="padding:15px 20px;border-bottom:1px solid var(--bs-border-color);font-weight:700;font-size:14px">
          ประวัติการส่งภารกิจ (<?= count($history) ?> ครั้ง)
        </div>
        <?php foreach ($history as $h):
            $pid  = (int) $h['id'];
            $chip = $statusChip[$h['status']] ?? $statusChip['pending'];
        ?>
          <div style="border-top:1px solid var(--bs-border-color);padding:16px 20px">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:9px">
              <span style="font-size:11.5px;font-weight:700;padding:3px 11px;border-radius:20px;background:<?= $chip[1] ?>;color:<?= $chip[0] ?>">
                <i class="bi <?= $chip[2] ?> me-1"></i><?= e($chip[3]) ?>
              </span>
              <span style="font-size:11.5px;color:var(--bs-tertiary-color)">
                ส่งเมื่อ <?= e(Booking::thaiDate(new DateTimeImmutable((string) $h['created_at']))) ?>
              </span>
            </div>

            <div style="font-size:13.5px;line-height:1.85;white-space:pre-wrap"><?= e($h['mission_text']) ?></div>

            <?php if (!empty($files[$pid])): ?>
              <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:10px">
                <?php foreach ($files[$pid] as $f): ?>
                  <a href="<?= url('uploads/lms/missions/' . rawurlencode((string) $f['filename'])) ?>" target="_blank" rel="noopener"
                     style="font-size:12px;padding:4px 11px;border:1px solid var(--bs-border-color);border-radius:20px;text-decoration:none;color:#2563EB">
                    <i class="bi bi-paperclip me-1"></i><?= e(mb_strimwidth((string) ($f['original_name'] ?? $f['filename']), 0, 32, '…')) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($h['admin_feedback'])): ?>
              <div style="background:<?= $chip[1] ?>;border-left:3px solid <?= $chip[0] ?>;border-radius:8px;padding:11px 13px;margin-top:11px;font-size:13px;line-height:1.8;color:<?= $chip[0] ?>">
                <strong>ความเห็นจากผู้ดูแล<?= !empty($h['reviewer_name']) ? ' (' . e($h['reviewer_name']) . ')' : '' ?>:</strong><br>
                <?= nl2br(e($h['admin_feedback'])) ?>
              </div>
            <?php endif; ?>

            <?php if ($h['status'] === 'approved' && !empty($h['granted_group_name'])): ?>
              <div style="font-size:12.5px;color:#059669;margin-top:9px">
                <i class="bi bi-diagram-3 me-1"></i>ย้ายเข้ากลุ่ม <strong><?= e($h['granted_group_name']) ?></strong> เรียบร้อยแล้ว
                — สิทธิ์การจอง AI ของคุณได้รับการปรับแล้ว
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
