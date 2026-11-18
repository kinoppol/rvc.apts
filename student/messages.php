<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    if (($_POST['action'] ?? '') === 'send_to_admin') {
        $result = AdminMessage::sendToAdmin((int) $user['id'], $_POST['subject'] ?? '', $_POST['body'] ?? '', $_FILES['attachments'] ?? null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ส่งข้อความถึงผู้ดูแลระบบเรียบร้อยแล้ว' : ($result['error'] ?? 'ส่งข้อความไม่สำเร็จ'));
    }
    header('Location: ' . url('student/messages.php'));
    exit;
}

$messages = AdminMessage::forUser((int) $user['id']);
// Opening this page is the read action — mark messages addressed to the member read (after
// we've loaded the list, so the "new" highlight still shows on this first view).
AdminMessage::markAllRead((int) $user['id']);

$activeNav = 'messages';
require __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px">
  <div>
    <h5 style="font-weight:700;margin:0"><i class="bi bi-envelope me-2" style="color:#2563EB"></i>ข้อความ</h5>
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">ข้อความระหว่างคุณกับผู้ดูแลระบบ</p>
  </div>
  <button type="button" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px;white-space:nowrap" data-bs-toggle="modal" data-bs-target="#contactAdminModal"><i class="bi bi-send me-1"></i>ส่งข้อความถึงผู้ดูแลระบบ</button>
</div>

<?php if (!$messages): ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:40px;text-align:center;color:var(--bs-tertiary-color)">
      <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:10px"></i>
      ยังไม่มีข้อความ — เริ่มต้นส่งข้อความถึงผู้ดูแลระบบได้เลย
    </div>
  </div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($messages as $m): ?>
      <?php
        $toAdmin = ($m['direction'] ?? 'to_member') === 'to_admin';
        $unread  = !$toAdmin && empty($m['read_at']);     // "new" highlight only for messages received
        $accent  = $toAdmin ? '#059669' : '#2563EB';
      ?>
      <div class="card" style="border:1px solid <?= $unread ? '#2563EB' : 'var(--bs-border-color)' ?>;box-shadow:0 1px 4px rgba(0,0,0,.04)">
        <div class="card-body" style="padding:16px 18px;border-left:3px solid <?= $accent ?>">
          <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">
            <div style="flex:1;min-width:200px">
              <div style="font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px">
                <?php if ($unread): ?><span style="width:8px;height:8px;border-radius:50%;background:#2563EB;flex-shrink:0" title="ยังไม่ได้อ่าน"></span><?php endif; ?>
                <?= e($m['subject']) ?>
              </div>
              <div style="font-size:12px;color:var(--bs-tertiary-color);margin-top:3px">
                <?php if ($toAdmin): ?>
                  <span style="color:#059669;font-weight:600"><i class="bi bi-send me-1"></i>คุณส่งถึงผู้ดูแลระบบ</span>
                  <?php if (!empty($m['read_at'])): ?><span title="ผู้ดูแลระบบอ่านแล้ว"> · <i class="bi bi-check2-all"></i> อ่านแล้ว</span><?php else: ?> · <span>รอผู้ดูแลระบบอ่าน</span><?php endif; ?>
                <?php else: ?>
                  <i class="bi bi-person-badge me-1"></i><?= e($m['admin_name'] ?? 'ผู้ดูแลระบบ') ?>
                <?php endif; ?>
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

<!-- Contact-admin compose modal -->
<div class="modal fade" id="contactAdminModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="send_to_admin">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-send me-2" style="color:#2563EB"></i>ส่งข้อความถึงผู้ดูแลระบบ</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">หัวข้อ <span style="color:#DC2626">*</span></label>
            <input type="text" name="subject" required maxlength="200" class="form-control" placeholder="เรื่องที่ต้องการติดต่อ" style="font-size:13px">
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">เนื้อหา <span style="color:#DC2626">*</span></label>
            <textarea name="body" required maxlength="5000" rows="5" class="form-control" placeholder="พิมพ์ข้อความถึงผู้ดูแลระบบ" style="font-size:13px"></textarea>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">แนบไฟล์/รูปภาพ <span style="font-weight:400;color:var(--bs-tertiary-color)">(ไม่บังคับ · รูปภาพหรือ PDF · ไม่เกิน 5 ไฟล์)</span></label>
            <input type="file" name="attachments[]" multiple accept="image/*,.pdf" class="form-control" style="font-size:13px">
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none"><i class="bi bi-send me-1"></i>ส่งข้อความ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
