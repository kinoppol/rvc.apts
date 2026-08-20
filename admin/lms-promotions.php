<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';

    if (in_array($action, ['approve', 'reject', 'revise'], true)) {
        $result = LmsPromotion::review($id, (int) $user['id'], $action, (string) ($_POST['feedback'] ?? ''));
        if ($result['ok']) {
            $msg = match ($action) {
                'approve' => 'อนุมัติภารกิจแล้ว — ย้ายผู้เรียนเข้ากลุ่ม "' . ($result['group'] ?? '') . '" เรียบร้อย',
                'revise'  => 'ส่งกลับให้ผู้เรียนแก้ไขเรียบร้อยแล้ว',
                default   => 'บันทึกผลการตรวจ (ไม่ผ่าน) เรียบร้อยแล้ว',
            };
            flash_set('ok', $msg);
        } else {
            flash_set('err', $result['error'] ?? 'เกิดข้อผิดพลาด');
        }
    }
    header('Location: ' . url('admin/lms-promotions.php') . '?status=' . urlencode((string) $status));
    exit;
}

$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'revise', 'all'], true)
    ? (string) $_GET['status'] : 'pending';

$rows  = LmsPromotion::listForAdmin($status);
$files = LmsPromotion::filesFor(array_map(fn ($r) => (int) $r['id'], $rows));

$tabs = [
    'pending'  => ['รอตรวจ',   LmsPromotion::pendingCount()],
    'revise'   => ['ขอให้แก้ไข', null],
    'approved' => ['อนุมัติแล้ว', null],
    'rejected' => ['ไม่ผ่าน',   null],
    'all'      => ['ทั้งหมด',   null],
];

$statusChip = [
    'pending'  => ['#D97706', '#FFFBEB', 'bi-hourglass-split',        'รอตรวจ'],
    'approved' => ['#059669', '#ECFDF5', 'bi-patch-check-fill',       'อนุมัติแล้ว'],
    'revise'   => ['#D97706', '#FFFBEB', 'bi-arrow-counterclockwise', 'ขอให้แก้ไข'],
    'rejected' => ['#DC2626', '#FEF2F2', 'bi-x-circle',               'ไม่ผ่าน'],
];

$activeNav = 'lms-promotions';
require __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:16px">
  <h5 style="font-weight:700;margin:0">คำขอเลื่อนระดับ</h5>
  <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:3px">
    ตรวจภารกิจพิสูจน์ทักษะ — การอนุมัติจะย้ายผู้เรียนเข้ากลุ่มปลายทางของระดับนั้นและเปลี่ยนสิทธิ์การจอง AI ทันที
  </div>
</div>

<div style="display:flex;gap:0;border-bottom:2px solid var(--bs-border-color);margin-bottom:18px;overflow-x:auto">
  <?php foreach ($tabs as $key => [$label, $count]): ?>
    <a href="<?= url('admin/lms-promotions.php') ?>?status=<?= e($key) ?>"
       style="text-decoration:none;padding:9px 18px;font-size:13px;font-weight:600;white-space:nowrap;<?= $status === $key ? 'color:#2563EB;border-bottom:2px solid #2563EB;margin-bottom:-2px' : 'color:var(--bs-secondary-color)' ?>">
      <?= e($label) ?>
      <?php if ($count): ?>
        <span style="background:#EF4444;color:white;border-radius:10px;font-size:10px;font-weight:700;padding:1px 6px;margin-left:4px"><?= (int) $count ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <div class="card" style="border:1px solid var(--bs-border-color)">
    <div class="card-body" style="padding:40px;text-align:center;color:var(--bs-secondary-color)">
      <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:10px"></i>
      <?= $status === 'pending' ? 'ไม่มีคำขอรอตรวจในขณะนี้' : 'ไม่มีรายการในหมวดนี้' ?>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($rows as $r):
      $pid     = (int) $r['id'];
      $chip    = $statusChip[$r['status']] ?? $statusChip['pending'];
      $pending = $r['status'] === 'pending';
      $target  = (string) ($r['target_group_name'] ?? '');
  ?>
    <div class="card" style="border:1px solid <?= $pending ? '#D97706' : 'var(--bs-border-color)' ?>;box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:14px">
      <div class="card-body" style="padding:0">

        <div style="padding:15px 20px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
          <div style="display:flex;align-items:center;gap:11px;min-width:0">
            <span style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#0EA5E9);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:14px">
              <?= e(mb_substr((string) $r['user_name'], 0, 1)) ?>
            </span>
            <div style="min-width:0">
              <div style="font-weight:700;font-size:14px">
                <?= e($r['user_name']) ?>
                <?php if ($r['user_role'] === 'teacher'): ?><span class="badge-teach" style="margin-left:5px">ครูผู้สอน</span><?php endif; ?>
              </div>
              <div style="font-size:11.5px;color:var(--bs-secondary-color)">
                <?= e($r['user_email']) ?> · กลุ่มปัจจุบัน: <?= e($r['current_group_name'] ?? 'ไม่มีกลุ่ม') ?>
              </div>
            </div>
          </div>
          <span style="font-size:11.5px;font-weight:700;padding:3px 11px;border-radius:20px;background:<?= $chip[1] ?>;color:<?= $chip[0] ?>">
            <i class="bi <?= $chip[2] ?> me-1"></i><?= e($chip[3]) ?>
          </span>
        </div>

        <div style="padding:16px 20px">
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)">
              <i class="bi bi-journal-richtext me-1"></i><?= e($r['level_title']) ?>
            </span>
            <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:#ECFDF5;color:#059669">
              คะแนนหลังเรียน <?= $r['best_post'] !== null ? (int) $r['best_post'] . '%' : '—' ?>
              <span style="opacity:.75">(เกณฑ์ <?= (int) $r['pass_percent'] ?>%)</span>
            </span>
            <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)">
              <i class="bi bi-clock me-1"></i><?= e(Booking::thaiDate(new DateTimeImmutable((string) $r['created_at']))) ?>
            </span>
          </div>

          <div style="font-size:13.5px;line-height:1.85;white-space:pre-wrap;background:var(--bs-secondary-bg);border-radius:10px;padding:13px 15px"><?= e($r['mission_text']) ?></div>

          <?php if (!empty($files[$pid])): ?>
            <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:11px">
              <?php foreach ($files[$pid] as $f): ?>
                <a href="<?= url('uploads/lms/missions/' . rawurlencode((string) $f['filename'])) ?>" target="_blank" rel="noopener"
                   style="font-size:12px;padding:5px 12px;border:1px solid var(--bs-border-color);border-radius:20px;text-decoration:none;color:#2563EB">
                  <i class="bi bi-paperclip me-1"></i><?= e(mb_strimwidth((string) ($f['original_name'] ?? $f['filename']), 0, 36, '…')) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="font-size:12px;color:var(--bs-tertiary-color);margin-top:9px"><i class="bi bi-paperclip me-1"></i>ไม่มีไฟล์แนบ</div>
          <?php endif; ?>

          <?php if (!empty($r['admin_feedback'])): ?>
            <div style="background:<?= $chip[1] ?>;border-left:3px solid <?= $chip[0] ?>;border-radius:8px;padding:11px 13px;margin-top:11px;font-size:13px;line-height:1.8;color:<?= $chip[0] ?>">
              <strong>ความเห็นที่บันทึกไว้:</strong><br><?= nl2br(e($r['admin_feedback'])) ?>
            </div>
          <?php endif; ?>

          <?php if ($r['status'] === 'approved' && !empty($r['granted_group_name'])): ?>
            <div style="font-size:12.5px;color:#059669;margin-top:10px">
              <i class="bi bi-diagram-3 me-1"></i>ย้ายเข้ากลุ่ม <strong><?= e($r['granted_group_name']) ?></strong> แล้ว
            </div>
          <?php endif; ?>
        </div>

        <?php if ($pending): ?>
          <div style="padding:15px 20px;border-top:1px solid var(--bs-border-color);background:var(--bs-secondary-bg)">
            <form method="post">
              <?= Csrf::field() ?>
              <input type="hidden" name="id" value="<?= $pid ?>">
              <input type="hidden" name="status" value="<?= e($status) ?>">

              <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">
                ความเห็น / สิ่งที่ต้องแก้ไข <span style="font-weight:400">(จำเป็นเมื่อไม่ผ่านหรือขอให้แก้ไข)</span>
              </label>
              <textarea name="feedback" rows="2" maxlength="2000" class="form-control" style="font-size:13px"
                        placeholder="เช่น ผลงานยังไม่ชัดเจนว่าใช้ AI ช่วยตรงไหน กรุณาแนบภาพหน้าจอการสนทนาเพิ่ม"></textarea>

              <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;flex-wrap:wrap">
                <button type="submit" name="action" value="revise" class="btn btn-sm"
                        style="background:transparent;border:1px solid #D97706;color:#D97706;font-size:13px">
                  <i class="bi bi-arrow-counterclockwise me-1"></i>ขอให้แก้ไข
                </button>
                <button type="submit" name="action" value="reject" class="btn btn-sm"
                        style="background:transparent;border:1px solid #DC2626;color:#DC2626;font-size:13px"
                        data-confirm-modal data-confirm-title="ตรวจไม่ผ่าน"
                        data-confirm-msg="บันทึกผลว่าภารกิจของ <?= e($r['user_name']) ?> ไม่ผ่านใช่หรือไม่? ผู้เรียนจะยังส่งใหม่ได้"
                        data-confirm-icon="bi-x-circle" data-confirm-color="#DC2626"
                        data-confirm-btn="บันทึกว่าไม่ผ่าน" data-confirm-cls="btn-danger">
                  <i class="bi bi-x-lg me-1"></i>ไม่ผ่าน
                </button>
                <button type="submit" name="action" value="approve" class="btn btn-sm"
                        style="background:#059669;border:none;color:white;font-size:13px"
                        <?php if ($target === ''): ?>disabled title="ระดับนี้ยังไม่ได้กำหนดกลุ่มปลายทาง"<?php else: ?>
                        data-confirm-modal data-confirm-title="อนุมัติภารกิจ"
                        data-confirm-msg="<?= e($r['user_name']) ?> จะถูกย้ายไปกลุ่ม &quot;<?= e($target) ?>&quot; ทันที ซึ่งจะเปลี่ยนโควตาการจอง จำนวนวันจองล่วงหน้า และ AI Pool ที่เข้าถึงได้ ยืนยันหรือไม่?"
                        data-confirm-icon="bi-patch-check" data-confirm-color="#059669"
                        data-confirm-btn="อนุมัติและย้ายกลุ่ม" data-confirm-cls="btn-success"
                        <?php endif; ?>>
                  <i class="bi bi-check-lg me-1"></i>อนุมัติ<?= $target !== '' ? ' → ' . e($target) : '' ?>
                </button>
              </div>
              <?php if ($target === ''): ?>
                <div style="font-size:11.5px;color:#DC2626;text-align:right;margin-top:6px">
                  ระดับนี้ยังไม่ได้กำหนดกลุ่มปลายทาง —
                  <a href="<?= url('admin/lms.php') ?>" style="color:#DC2626;font-weight:600">ตั้งค่าที่หน้าจัดการบทเรียน</a>
                </div>
              <?php endif; ?>
            </form>
          </div>
        <?php endif; ?>

      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
