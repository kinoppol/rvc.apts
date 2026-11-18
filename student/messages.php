<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);

$messages = AdminMessage::forUser((int) $user['id']);
// Opening this page is the read action — mark everything read (after we've loaded the
// list, so the "new" highlight still shows on this first view).
AdminMessage::markAllRead((int) $user['id']);

$activeNav = 'messages';
require __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom:20px">
  <h5 style="font-weight:700;margin:0"><i class="bi bi-envelope me-2" style="color:#2563EB"></i>ข้อความจากผู้ดูแลระบบ</h5>
  <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">ข้อความและประกาศที่ผู้ดูแลระบบส่งถึงคุณ</p>
</div>

<?php if (!$messages): ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:40px;text-align:center;color:var(--bs-tertiary-color)">
      <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:10px"></i>
      ยังไม่มีข้อความ
    </div>
  </div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($messages as $m): ?>
      <?php $unread = empty($m['read_at']); ?>
      <div class="card" style="border:1px solid <?= $unread ? '#2563EB' : 'var(--bs-border-color)' ?>;box-shadow:0 1px 4px rgba(0,0,0,.04)">
        <div class="card-body" style="padding:16px 18px">
          <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">
            <div style="flex:1;min-width:200px">
              <div style="font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px">
                <?php if ($unread): ?><span style="width:8px;height:8px;border-radius:50%;background:#2563EB;flex-shrink:0" title="ยังไม่ได้อ่าน"></span><?php endif; ?>
                <?= e($m['subject']) ?>
              </div>
              <div style="font-size:12px;color:var(--bs-tertiary-color);margin-top:3px">
                <i class="bi bi-person-badge me-1"></i><?= e($m['admin_name'] ?? 'ผู้ดูแลระบบ') ?>
                · <?= e(Booking::thaiDate(new DateTimeImmutable($m['created_at']))) ?> เวลา <?= e((new DateTimeImmutable($m['created_at']))->format('H:i')) ?> น.
              </div>
            </div>
          </div>
          <div style="font-size:14px;color:var(--bs-body-color);margin-top:12px;white-space:pre-wrap;line-height:1.6"><?= e($m['body']) ?></div>
          <?php if (!empty($m['files'])): ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:14px">
              <?php foreach ($m['files'] as $f):
                $furl = url('uploads/messages/' . $f['filename']);
                $isImg = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $f['filename']); ?>
                <?php if ($isImg): ?>
                  <a href="<?= e($furl) ?>" target="_blank" rel="noopener" title="<?= e($f['original_name'] ?? '') ?>">
                    <img src="<?= e($furl) ?>" alt="<?= e($f['original_name'] ?? 'รูปภาพแนบ') ?>" style="max-width:160px;max-height:160px;border-radius:8px;border:1px solid var(--bs-border-color);object-fit:cover">
                  </a>
                <?php else: ?>
                  <a href="<?= e($furl) ?>" target="_blank" rel="noopener" style="font-size:13px;text-decoration:none;color:#2563EB;border:1px solid var(--bs-border-color);border-radius:8px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px">
                    <i class="bi bi-file-earmark-pdf" style="color:#DC2626"></i><?= e($f['original_name'] ?? 'ไฟล์แนบ') ?>
                  </a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
