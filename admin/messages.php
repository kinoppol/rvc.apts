<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    if (($_POST['action'] ?? '') === 'reply') {
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $result = AdminMessage::send((int) $user['id'], $memberId, $_POST['subject'] ?? '', $_POST['body'] ?? '', $_FILES['attachments'] ?? null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ตอบกลับข้อความเรียบร้อยแล้ว' : ($result['error'] ?? 'ตอบกลับไม่สำเร็จ'));
    }
    header('Location: ' . url('admin/messages.php'));
    exit;
}

$inbox = AdminMessage::inboxForAdmin();
// Opening the inbox marks all incoming messages read.
AdminMessage::markAdminInboxRead();

$activeNav = 'admin-messages';
require __DIR__ . '/../includes/header.php';
?>
<div style="margin-bottom:20px">
  <h5 style="font-weight:700;margin:0"><i class="bi bi-inbox me-2" style="color:#2563EB"></i>ข้อความจากสมาชิก</h5>
  <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">ข้อความที่สมาชิกส่งถึงผู้ดูแลระบบ · ตอบกลับได้จากที่นี่</p>
</div>

<?php if (!$inbox): ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:40px;text-align:center;color:var(--bs-tertiary-color)">
      <i class="bi bi-inbox" style="font-size:32px;display:block;margin-bottom:10px"></i>
      ยังไม่มีข้อความจากสมาชิก
    </div>
  </div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($inbox as $m): ?>
      <?php $wasUnread = empty($m['read_at']); ?>
      <div class="card" style="border:1px solid <?= $wasUnread ? '#2563EB' : 'var(--bs-border-color)' ?>;box-shadow:0 1px 4px rgba(0,0,0,.04)">
        <div class="card-body" style="padding:16px 18px">
          <div style="display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap">
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#EFF6FF,#DBEAFE);display:flex;align-items:center;justify-content:center;font-weight:700;color:#2563EB;font-size:14px;flex-shrink:0"><?= e(mb_substr((string) $m['sender_name'], 0, 1)) ?></div>
            <div style="flex:1;min-width:200px">
              <div style="font-weight:700;font-size:15px;display:flex;align-items:center;gap:8px">
                <?php if ($wasUnread): ?><span style="width:8px;height:8px;border-radius:50%;background:#2563EB;flex-shrink:0" title="ยังไม่ได้อ่าน"></span><?php endif; ?>
                <?= e($m['subject']) ?>
              </div>
              <div style="font-size:12px;color:var(--bs-tertiary-color);margin-top:3px">
                <?= e($m['sender_name']) ?> (<?= $m['sender_role'] === 'teacher' ? 'ครูผู้สอน' : 'นักศึกษา' ?>) · <?= e($m['sender_email']) ?>
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
                  <a href="<?= e($furl) ?>" target="_blank" rel="noopener" title="<?= e($f['original_name'] ?? '') ?>"><img src="<?= e($furl) ?>" alt="<?= e($f['original_name'] ?? '') ?>" style="max-width:150px;max-height:150px;border-radius:8px;border:1px solid var(--bs-border-color);object-fit:cover"></a>
                <?php else: ?>
                  <a href="<?= e($furl) ?>" target="_blank" rel="noopener" style="font-size:13px;text-decoration:none;color:#2563EB;border:1px solid var(--bs-border-color);border-radius:8px;padding:8px 12px;display:inline-flex;align-items:center;gap:6px"><i class="bi bi-file-earmark-pdf" style="color:#DC2626"></i><?= e($f['original_name'] ?? 'ไฟล์แนบ') ?></a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div style="margin-top:14px;border-top:1px solid var(--bs-border-color);padding-top:12px">
            <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:12px"
                    data-reply-msg data-member="<?= (int) $m['user_id'] ?>" data-name="<?= e($m['sender_name']) ?>" data-subject="<?= e($m['subject']) ?>">
              <i class="bi bi-reply me-1"></i>ตอบกลับ
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Reply modal -->
<div class="modal fade" id="replyMsgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="member_id" id="replyMemberId">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-reply me-2" style="color:#2563EB"></i>ตอบกลับ <span id="replyMemberName"></span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">หัวข้อ <span style="color:#DC2626">*</span></label>
            <input type="text" name="subject" id="replySubject" required maxlength="200" class="form-control" style="font-size:13px">
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">เนื้อหา <span style="color:#DC2626">*</span></label>
            <textarea name="body" required maxlength="5000" rows="5" class="form-control" placeholder="พิมพ์ข้อความตอบกลับ" style="font-size:13px"></textarea>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">แนบไฟล์/รูปภาพ <span style="font-weight:400;color:var(--bs-tertiary-color)">(ไม่บังคับ · รูปภาพหรือ PDF · ไม่เกิน 5 ไฟล์)</span></label>
            <input type="file" name="attachments[]" multiple accept="image/*,.pdf" class="form-control" style="font-size:13px">
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none"><i class="bi bi-send me-1"></i>ส่งคำตอบ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
