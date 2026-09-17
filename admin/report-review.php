<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action    = $_POST['action'] ?? '';
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $tab       = (string) ($_POST['tab'] ?? 'pending');

    if ($action === 'review_report') {
        $verdict = $_POST['verdict'] ?? '';
        $note    = (string) ($_POST['note'] ?? '');
        $result  = Booking::reviewReport((int) $user['id'], $bookingId, $verdict, $note);
        if ($result['ok']) {
            $msg = $verdict === 'accepted' ? 'ยอมรับรายงานเรียบร้อยแล้ว (+1 คะแนน)' : 'ปฏิเสธรายงานเรียบร้อยแล้ว (−1 คะแนน)';
            flash_set('ok', $msg);
        } else {
            flash_set('err', $result['error'] ?? 'เกิดข้อผิดพลาด');
        }
    }
    header('Location: ' . url('admin/report-review.php') . '?tab=' . urlencode($tab));
    exit;
}

$tab = in_array($_GET['tab'] ?? '', ['pending', 'accepted', 'rejected', 'all'], true)
    ? (string) $_GET['tab'] : 'pending';

$filterMap = [
    'pending'  => 'report_review',
    'accepted' => 'report_accepted',
    'rejected' => 'report_rejected',
    'all'      => 'report_all',
];
$result = Booking::adminList('', $filterMap[$tab], 0, '', '', 1, 300);
$rows   = $result['rows'];

$tabs = [
    'pending'  => ['รอตรวจ',      Booking::pendingReviewCount()],
    'accepted' => ['ยอมรับแล้ว',  null],
    'rejected' => ['ปฏิเสธ',      null],
    'all'      => ['ทั้งหมด',     null],
];

$activeNav = 'report-review';
require __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:16px">
  <h5 style="font-weight:700;margin:0">ตรวจสอบรายงานการใช้งาน</h5>
  <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:3px">
    ตรวจรายงานที่สมาชิกส่งหลังใช้งาน AI — ยอมรับ (+1) หรือปฏิเสธ (−1) เพื่อปรับคะแนนความน่าเชื่อถือ
  </div>
</div>

<?php $flash = flash_get(); if ($flash): ?>
  <div style="margin-bottom:14px;padding:10px 14px;border-radius:8px;font-size:13px;
              background:<?= $flash['type'] === 'ok' ? '#ECFDF5' : '#FEF2F2' ?>;
              color:<?= $flash['type'] === 'ok' ? '#059669' : '#DC2626' ?>;
              border:1px solid <?= $flash['type'] === 'ok' ? '#6EE7B7' : '#FECACA' ?>">
    <i class="bi <?= $flash['type'] === 'ok' ? 'bi-check-circle' : 'bi-exclamation-circle' ?> me-1"></i>
    <?= e($flash['msg']) ?>
  </div>
<?php endif; ?>

<div style="display:flex;gap:0;border-bottom:2px solid var(--bs-border-color);margin-bottom:18px;overflow-x:auto">
  <?php foreach ($tabs as $key => [$label, $count]): ?>
    <a href="<?= url('admin/report-review.php') ?>?tab=<?= e($key) ?>"
       style="text-decoration:none;padding:9px 18px;font-size:13px;font-weight:600;white-space:nowrap;<?= $tab === $key ? 'color:#2563EB;border-bottom:2px solid #2563EB;margin-bottom:-2px' : 'color:var(--bs-secondary-color)' ?>">
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
      <?= $tab === 'pending' ? 'ไม่มีรายงานรอตรวจในขณะนี้' : 'ไม่มีรายการในหมวดนี้' ?>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($rows as $r):
      $bid         = (int) $r['id'];
      $isPending   = ($r['reportReviewStatus'] ?? null) === 'pending_review';
      $isAccepted  = ($r['reportReviewStatus'] ?? null) === 'accepted';
      $isRejected  = ($r['reportReviewStatus'] ?? null) === 'rejected';
      $borderColor = $isPending ? '#D97706' : 'var(--bs-border-color)';
      $score       = Booking::userScore((int) $r['user_id']);
  ?>
    <div class="card" style="border:1px solid <?= $borderColor ?>;box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:14px">
      <div class="card-body" style="padding:0">

        <!-- Header: student + status badge -->
        <div style="padding:15px 20px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
          <div style="display:flex;align-items:center;gap:11px;min-width:0">
            <span style="flex-shrink:0;width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#0EA5E9);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:14px">
              <?= e(mb_substr((string) ($r['student_name'] ?? '?'), 0, 1)) ?>
            </span>
            <div style="min-width:0">
              <div style="font-weight:700;font-size:14px"><?= e($r['student_name'] ?? '') ?></div>
              <div style="font-size:11.5px;color:var(--bs-secondary-color)">
                <?= e($r['student_email'] ?? '') ?>
                <?php if (!empty($r['student_code'])): ?> · <?= e($r['student_code']) ?><?php endif; ?>
              </div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <!-- reputation score -->
            <span style="font-size:11.5px;font-weight:700;padding:3px 11px;border-radius:20px;
                         background:<?= $score >= 0 ? '#ECFDF5' : '#FEF2F2' ?>;
                         color:<?= $score >= 0 ? '#059669' : '#DC2626' ?>">
              <i class="bi bi-star-half me-1"></i>คะแนน <?= $score >= 0 ? '+' : '' ?><?= $score ?>
            </span>
            <!-- review status -->
            <?php if ($isPending): ?>
              <span style="font-size:11.5px;font-weight:700;padding:3px 11px;border-radius:20px;background:#FFFBEB;color:#D97706">
                <i class="bi bi-hourglass-split me-1"></i>รอตรวจ
              </span>
            <?php elseif ($isAccepted): ?>
              <span style="font-size:11.5px;font-weight:700;padding:3px 11px;border-radius:20px;background:#ECFDF5;color:#059669">
                <i class="bi bi-check-circle-fill me-1"></i>ยอมรับแล้ว
              </span>
            <?php elseif ($isRejected): ?>
              <span style="font-size:11.5px;font-weight:700;padding:3px 11px;border-radius:20px;background:#FEF2F2;color:#DC2626">
                <i class="bi bi-x-circle-fill me-1"></i>ปฏิเสธ
              </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Booking meta -->
        <div style="padding:14px 20px;border-bottom:1px solid var(--bs-border-color)">
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)">
              <i class="bi bi-calendar3 me-1"></i><?= e(Booking::thaiDate(new DateTimeImmutable((string) $r['booking_date']))) ?>
              &nbsp;<?= e($r['slotLabel'] ?? '') ?>
            </span>
            <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)">
              <i class="bi bi-cpu me-1"></i><?= e($r['ai_name'] ?? '') ?>
            </span>
            <?php if (isset($r['token_start_pct']) || isset($r['token_end_pct'])): ?>
              <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:#EFF6FF;color:#2563EB">
                <i class="bi bi-bar-chart me-1"></i>
                <?php
                $ts = $r['token_start_pct'] !== null ? (int) $r['token_start_pct'] . '%' : '—';
                $te = $r['token_end_pct']   !== null ? (int) $r['token_end_pct']   . '%' : '—';
                echo e("Token $ts → $te");
                ?>
              </span>
            <?php endif; ?>
            <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)">
              <i class="bi bi-clock me-1"></i>ส่งเมื่อ <?= e(Booking::thaiDate(new DateTimeImmutable((string) $r['reported_at']))) ?>
            </span>
          </div>

          <!-- Report text -->
          <?php if (!empty($r['report_text'])): ?>
            <div style="font-size:13.5px;line-height:1.85;white-space:pre-wrap;background:var(--bs-secondary-bg);border-radius:10px;padding:13px 15px"><?= e($r['report_text']) ?></div>
          <?php else: ?>
            <div style="font-size:13px;color:var(--bs-tertiary-color);padding:10px 0"><i class="bi bi-chat-left-text me-1"></i>ไม่มีข้อความรายงาน</div>
          <?php endif; ?>

          <!-- File attachment -->
          <?php if (!empty($r['report_file'])): ?>
            <div style="margin-top:10px">
              <a href="<?= url('uploads/' . rawurlencode((string) $r['report_file'])) ?>" target="_blank" rel="noopener"
                 style="font-size:12px;padding:5px 12px;border:1px solid var(--bs-border-color);border-radius:20px;text-decoration:none;color:#2563EB">
                <i class="bi bi-paperclip me-1"></i><?= e(mb_strimwidth((string) $r['report_file'], 0, 48, '…')) ?>
              </a>
            </div>
          <?php else: ?>
            <div style="font-size:12px;color:var(--bs-tertiary-color);margin-top:8px"><i class="bi bi-paperclip me-1"></i>ไม่มีไฟล์แนบ</div>
          <?php endif; ?>

          <!-- Previous rejection note shown for all tabs -->
          <?php if ($isRejected && !empty($r['reportReviewNote'])): ?>
            <div style="background:#FEF2F2;border-left:3px solid #DC2626;border-radius:8px;padding:11px 13px;margin-top:12px;font-size:13px;line-height:1.8;color:#DC2626">
              <strong>เหตุผลที่ปฏิเสธ:</strong><br><?= nl2br(e((string) $r['reportReviewNote'])) ?>
            </div>
          <?php elseif ($isAccepted && !empty($r['reportReviewNote'])): ?>
            <div style="background:#ECFDF5;border-left:3px solid #059669;border-radius:8px;padding:11px 13px;margin-top:12px;font-size:13px;line-height:1.8;color:#059669">
              <strong>หมายเหตุ:</strong><br><?= nl2br(e((string) $r['reportReviewNote'])) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Action form (pending only) -->
        <?php if ($isPending): ?>
          <div style="padding:15px 20px;background:var(--bs-secondary-bg)">
            <form method="post" id="reviewForm<?= $bid ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="review_report">
              <input type="hidden" name="booking_id" value="<?= $bid ?>">
              <input type="hidden" name="tab" value="<?= e($tab) ?>">

              <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">
                เหตุผล / ข้อเสนอแนะ <span style="font-weight:400" id="noteReq<?= $bid ?>">(จำเป็นเมื่อปฏิเสธ)</span>
              </label>
              <textarea name="note" rows="2" maxlength="1000" class="form-control" style="font-size:13px;margin-bottom:12px"
                        placeholder="เช่น รายงานไม่ตรงกับวัตถุประสงค์ที่แจ้งไว้ กรุณาเพิ่มรายละเอียดการใช้งาน"></textarea>

              <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
                <button type="submit" name="verdict" value="rejected" class="btn btn-sm"
                        style="background:transparent;border:1px solid #DC2626;color:#DC2626;font-size:13px">
                  <i class="bi bi-x-lg me-1"></i>ปฏิเสธ (−1)
                </button>
                <button type="submit" name="verdict" value="accepted" class="btn btn-sm"
                        style="background:#059669;border:none;color:white;font-size:13px">
                  <i class="bi bi-check-lg me-1"></i>ยอมรับ (+1)
                </button>
              </div>
            </form>
          </div>
        <?php endif; ?>

      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
