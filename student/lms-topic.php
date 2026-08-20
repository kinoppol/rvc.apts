<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);

$topicId = (int) ($_GET['id'] ?? 0);
$topic   = LmsContent::findTopic($topicId);
$uid     = (int) $user['id'];

if (!$topic || (int) $topic['is_published'] !== 1 || (int) $topic['level_published'] !== 1) {
    flash_set('err', 'ไม่พบหัวข้อนี้ หรือยังไม่เปิดให้เรียน');
    header('Location: ' . url('student/lms.php'));
    exit;
}
$levelId = (int) $topic['level_id'];
if (!LmsProgress::canAccessLevel($uid, $levelId)) {
    flash_set('err', 'ยังไม่ปลดล็อกระดับนี้ กรุณาผ่านแบบทดสอบหลังเรียนของระดับก่อนหน้า');
    header('Location: ' . url('student/lms.php'));
    exit;
}

$level   = LmsLevel::find($levelId);
$blocks  = LmsContent::blocks($topicId);
$siblings = LmsContent::topics($levelId, true);
$history = LmsQuiz::historyFor($uid, $levelId, 'review', $topicId);
$open    = LmsQuiz::openAttempt($uid, $levelId, 'review', $topicId);
$need    = (int) $level['review_pass_correct'];
$color   = (string) $topic['level_color'];

// prev / next within the level, so a student can read straight through
$prev = $next = null;
foreach ($siblings as $i => $s) {
    if ((int) $s['id'] === $topicId) {
        $prev = $siblings[$i - 1] ?? null;
        $next = $siblings[$i + 1] ?? null;
        break;
    }
}

$best = 0;
foreach ($history as $h) {
    $best = max($best, (int) $h['correct_count']);
}
$passed = $history && $best >= $need;
$reviewCount = LmsQuestion::activeCount($levelId, 'review', $topicId);

$activeNav = 'lms';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--bs-secondary-color);margin-bottom:12px;flex-wrap:wrap">
  <a href="<?= url('student/lms.php') ?>" style="color:#2563EB;text-decoration:none">บทเรียน</a>
  <span>/</span>
  <a href="<?= url('student/lms-level.php') ?>?id=<?= $levelId ?>" style="color:#2563EB;text-decoration:none"><?= e($topic['level_title']) ?></a>
  <span>/</span><span style="font-weight:600;color:var(--bs-body-color)"><?= e($topic['title']) ?></span>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
  <div class="card-body" style="padding:22px 26px">
    <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:4px">
      <h5 style="font-weight:700;margin:0;font-size:18px"><?= e($topic['title']) ?></h5>
      <?php if ($passed): ?><span class="badge-ok"><i class="bi bi-check-lg me-1"></i>ผ่านแล้ว</span><?php endif; ?>
    </div>
    <?php if (!empty($topic['summary'])): ?>
      <div style="font-size:13px;color:var(--bs-secondary-color);margin-bottom:6px"><?= e($topic['summary']) ?></div>
    <?php endif; ?>
    <hr style="margin:16px 0;border-color:var(--bs-border-color)">
    <?php require __DIR__ . '/../includes/lms-blocks.php'; ?>
  </div>
</div>

<!-- Review quiz -->
<div class="card" style="border:1px solid <?= $passed ? '#059669' : 'var(--bs-border-color)' ?>;box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
  <div class="card-body" style="padding:18px 22px">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
      <div style="min-width:0">
        <div style="font-weight:700;font-size:14px">
          <i class="bi bi-patch-question me-2" style="color:<?= e($color) ?>"></i>แบบทดสอบทบทวน
        </div>
        <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:5px;line-height:1.75">
          <?php if ($reviewCount < 3): ?>
            <span style="color:#D97706"><i class="bi bi-exclamation-triangle me-1"></i>หัวข้อนี้ยังไม่มีแบบทดสอบพร้อมใช้งาน</span>
          <?php else: ?>
            ทบทวนความเข้าใจ 3 ข้อ — ต้องตอบถูกอย่างน้อย <strong><?= $need ?> ข้อ</strong> จึงจะนับว่าผ่านหัวข้อนี้
          <?php endif; ?>
          <?php if ($history): ?>
            <br>
            <span style="color:<?= $passed ? '#059669' : '#D97706' ?>;font-weight:600">ดีที่สุด <?= $best ?>/3</span>
            <span style="color:var(--bs-tertiary-color)">· ทำไปแล้ว <?= count($history) ?> ครั้ง</span>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($reviewCount >= 3): ?>
        <form method="post" action="<?= url('student/lms-quiz.php') ?>" style="margin:0">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="start">
          <input type="hidden" name="level_id" value="<?= $levelId ?>">
          <input type="hidden" name="phase" value="review">
          <input type="hidden" name="topic_id" value="<?= $topicId ?>">
          <button type="submit" class="btn btn-sm" style="background:<?= e($color) ?>;border:none;color:white;font-size:13px;white-space:nowrap">
            <i class="bi <?= $open ? 'bi-hourglass-split' : 'bi-pencil-square' ?> me-1"></i>
            <?= $open ? 'ทำต่อ (ค้างอยู่)' : ($history ? 'ทำอีกครั้ง' : 'ทำแบบทดสอบทบทวน') ?>
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($history): ?>
      <div style="margin-top:14px;border-top:1px solid var(--bs-border-color);padding-top:12px">
        <div style="font-size:11.5px;font-weight:700;color:var(--bs-tertiary-color);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">ประวัติการทำ</div>
        <?php foreach (array_slice($history, 0, 5) as $h): ?>
          <a href="<?= url('student/lms-quiz.php') ?>?attempt=<?= (int) $h['id'] ?>"
             style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:6px 0;font-size:12.5px;text-decoration:none;color:inherit">
            <span style="color:var(--bs-secondary-color)"><?= e(Booking::thaiDate(new DateTimeImmutable((string) $h['submitted_at']))) ?></span>
            <span style="font-weight:600;color:<?= (int) $h['correct_count'] >= $need ? '#059669' : '#DC2626' ?>">
              <?= (int) $h['correct_count'] ?>/<?= (int) $h['question_count'] ?>
              <i class="bi bi-chevron-right ms-1" style="font-size:10px;color:var(--bs-tertiary-color)"></i>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Prev / next -->
<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
  <?php if ($prev): ?>
    <a href="<?= url('student/lms-topic.php') ?>?id=<?= (int) $prev['id'] ?>" class="btn btn-sm"
       style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:13px;text-decoration:none">
      <i class="bi bi-arrow-left me-1"></i><?= e(mb_strimwidth((string) $prev['title'], 0, 30, '…')) ?>
    </a>
  <?php else: ?><span></span><?php endif; ?>

  <?php if ($next): ?>
    <a href="<?= url('student/lms-topic.php') ?>?id=<?= (int) $next['id'] ?>" class="btn btn-sm"
       style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:13px;text-decoration:none">
      <?= e(mb_strimwidth((string) $next['title'], 0, 30, '…')) ?><i class="bi bi-arrow-right ms-1"></i>
    </a>
  <?php else: ?>
    <a href="<?= url('student/lms-level.php') ?>?id=<?= $levelId ?>" class="btn btn-sm"
       style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:13px;text-decoration:none">
      กลับไปหน้าระดับ<i class="bi bi-arrow-right ms-1"></i>
    </a>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
