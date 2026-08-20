<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);

$levelId = (int) ($_GET['id'] ?? 0);
$level   = LmsLevel::find($levelId);
$uid     = (int) $user['id'];

if (!$level || (int) $level['is_published'] !== 1) {
    flash_set('err', 'ไม่พบระดับนี้ หรือยังไม่เปิดให้เรียน');
    header('Location: ' . url('student/lms.php'));
    exit;
}
if (!LmsProgress::canAccessLevel($uid, $levelId)) {
    flash_set('err', 'ยังไม่ปลดล็อกระดับนี้ กรุณาผ่านแบบทดสอบหลังเรียนของระดับก่อนหน้า');
    header('Location: ' . url('student/lms.php'));
    exit;
}

$state       = LmsProgress::levelState($uid, $levelId);
$topics      = LmsContent::topics($levelId, true);
$topicStatus = LmsProgress::topicStatus($uid, $levelId);
$color       = (string) $level['accent_color'];

$activeNav = 'lms';
require __DIR__ . '/../includes/header.php';

/** The POST button that starts (or resumes) a quiz — student/lms-quiz.php is the only handler. */
function quiz_button(int $levelId, string $phase, ?int $topicId, string $label, string $style, ?array $open = null): string
{
    $resume = $open !== null;
    return '<form method="post" action="' . url('student/lms-quiz.php') . '" style="display:inline;margin:0">'
        . Csrf::field()
        . '<input type="hidden" name="action" value="start">'
        . '<input type="hidden" name="level_id" value="' . $levelId . '">'
        . '<input type="hidden" name="phase" value="' . e($phase) . '">'
        . ($topicId !== null ? '<input type="hidden" name="topic_id" value="' . $topicId . '">' : '')
        . '<button type="submit" class="btn btn-sm" style="' . $style . '">'
        . '<i class="bi ' . ($resume ? 'bi-hourglass-split' : 'bi-pencil-square') . ' me-1"></i>'
        . ($resume ? 'ทำต่อ (ค้างอยู่)' : $label) . '</button></form>';
}
?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--bs-secondary-color);margin-bottom:12px;flex-wrap:wrap">
  <a href="<?= url('student/lms.php') ?>" style="color:#2563EB;text-decoration:none"><i class="bi bi-arrow-left me-1"></i>บทเรียนทั้งหมด</a>
  <span>/</span><span style="font-weight:600;color:var(--bs-body-color)"><?= e($level['title']) ?></span>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
  <div class="card-body" style="padding:20px 22px">
    <div style="display:flex;align-items:flex-start;gap:14px">
      <span style="flex-shrink:0;width:48px;height:48px;border-radius:13px;background:<?= e($color) ?>1a;display:flex;align-items:center;justify-content:center">
        <i class="bi <?= e($level['icon']) ?>" style="color:<?= e($color) ?>;font-size:22px"></i>
      </span>
      <div style="min-width:0">
        <h5 style="font-weight:700;margin:0;font-size:17px"><?= e($level['title']) ?></h5>
        <?php if (!empty($level['subtitle'])): ?>
          <div style="font-size:13px;color:var(--bs-secondary-color);margin-top:3px"><?= e($level['subtitle']) ?></div>
        <?php endif; ?>
        <?php if (!empty($level['description'])): ?>
          <p style="font-size:13.5px;line-height:1.85;margin:10px 0 0;color:var(--bs-secondary-color)"><?= nl2br(e($level['description'])) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Pre-test -->
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
  <div class="card-body" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
    <div style="min-width:0">
      <div style="font-weight:700;font-size:14px"><i class="bi bi-clipboard-check me-2" style="color:#2563EB"></i>แบบทดสอบก่อนเรียน</div>
      <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:4px;line-height:1.7">
        วัดพื้นฐานก่อนเริ่มเรียน (<?= (int) $level['pre_question_count'] ?> ข้อ) — ไม่มีผลต่อการปลดล็อก ทำได้ไม่จำกัดครั้ง
        <?php if ($state['bestPre'] !== null): ?>
          <br><span style="color:#2563EB;font-weight:600">คะแนนดีที่สุด <?= (int) $state['bestPre'] ?>%</span>
          <span style="color:var(--bs-tertiary-color)">· ทำไปแล้ว <?= (int) $state['preTries'] ?> ครั้ง</span>
        <?php endif; ?>
      </div>
    </div>
    <?= quiz_button($levelId, 'pre', null, $state['bestPre'] === null ? 'เริ่มทำ' : 'ทำอีกครั้ง',
          'background:#2563EB;border:none;color:white;font-size:13px', $state['openPre']) ?>
  </div>
</div>

<!-- Topics -->
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
  <div class="card-body" style="padding:0">
    <div style="padding:15px 20px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
      <span style="font-weight:700;font-size:14px">หัวข้อในระดับนี้</span>
      <span style="font-size:12.5px;color:var(--bs-secondary-color)">
        ผ่านแล้ว <strong style="color:#059669"><?= (int) $state['topicsDone'] ?></strong>/<?= (int) $state['topicsTotal'] ?> หัวข้อ
      </span>
    </div>
    <?php if (!$topics): ?>
      <div style="padding:30px;text-align:center;color:var(--bs-secondary-color);font-size:13px">ยังไม่มีหัวข้อในระดับนี้</div>
    <?php else: ?>
      <?php foreach ($topics as $i => $t): $tid = (int) $t['id']; $ts = $topicStatus[$tid] ?? null; $passed = $ts && $ts['passed']; ?>
        <a href="<?= url('student/lms-topic.php') ?>?id=<?= $tid ?>"
           style="display:flex;align-items:center;gap:13px;padding:14px 20px;border-top:1px solid var(--bs-border-color);text-decoration:none;color:inherit">
          <span style="flex-shrink:0;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700;
                       background:<?= $passed ? '#ECFDF5' : 'var(--bs-secondary-bg)' ?>;color:<?= $passed ? '#059669' : 'var(--bs-secondary-color)' ?>">
            <?php if ($passed): ?><i class="bi bi-check-lg"></i><?php else: ?><?= $i + 1 ?><?php endif; ?>
          </span>
          <span style="flex:1;min-width:0">
            <span style="display:block;font-weight:600;font-size:14px"><?= e($t['title']) ?></span>
            <?php if (!empty($t['summary'])): ?>
              <span style="display:block;font-size:12px;color:var(--bs-secondary-color);margin-top:2px"><?= e($t['summary']) ?></span>
            <?php endif; ?>
            <span style="display:block;font-size:11.5px;color:var(--bs-tertiary-color);margin-top:3px">
              <?php if ($passed): ?>
                ผ่านแบบทดสอบทบทวนแล้ว (<?= (int) $ts['best'] ?>/3)
              <?php elseif ($ts): ?>
                ยังไม่ผ่าน — ดีที่สุด <?= (int) $ts['best'] ?>/3 · ต้องได้ <?= (int) $level['review_pass_correct'] ?> ข้อขึ้นไป
              <?php else: ?>
                ยังไม่ได้ทำแบบทดสอบทบทวน
              <?php endif; ?>
            </span>
          </span>
          <i class="bi bi-chevron-right" style="color:var(--bs-tertiary-color);flex-shrink:0"></i>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Post-test -->
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
  <div class="card-body" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
    <div style="min-width:0">
      <div style="font-weight:700;font-size:14px">
        <i class="bi bi-patch-check me-2" style="color:#059669"></i>แบบทดสอบหลังเรียน
        <span style="font-weight:500;font-size:12.5px;color:var(--bs-secondary-color)">— เกณฑ์ผ่าน <?= (int) $level['pass_percent'] ?>%</span>
      </div>
      <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:4px;line-height:1.7">
        <?php if (!$state['canPostTest']): ?>
          <span style="color:#D97706">
            <i class="bi bi-lock me-1"></i>ต้องผ่านแบบทดสอบทบทวนให้ครบทุกหัวข้อก่อน
            (ตอนนี้ <?= (int) $state['topicsDone'] ?>/<?= (int) $state['topicsTotal'] ?>)
          </span>
        <?php else: ?>
          สุ่ม <?= (int) $level['post_question_count'] ?> ข้อจากคลัง — ผ่านแล้วจะปลดล็อกระดับถัดไป
        <?php endif; ?>
        <?php if ($state['bestPost'] !== null): ?>
          <br><span style="color:<?= $state['postPassed'] ? '#059669' : '#D97706' ?>;font-weight:600">
            คะแนนดีที่สุด <?= (int) $state['bestPost'] ?>% <?= $state['postPassed'] ? '(ผ่าน)' : '(ยังไม่ผ่าน)' ?>
          </span>
          <span style="color:var(--bs-tertiary-color)">· ทำไปแล้ว <?= (int) $state['postTries'] ?> ครั้ง</span>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($state['canPostTest']): ?>
      <?= quiz_button($levelId, 'post', null, $state['bestPost'] === null ? 'เริ่มทำ' : 'ทำอีกครั้ง',
            'background:#059669;border:none;color:white;font-size:13px', $state['openPost']) ?>
    <?php else: ?>
      <button class="btn btn-sm" disabled style="background:var(--bs-secondary-bg);border:none;color:var(--bs-tertiary-color);font-size:13px">
        <i class="bi bi-lock me-1"></i>ยังไม่พร้อม
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- Skill mission -->
<?php if ($state['postPassed'] && !empty($level['mission_brief'])): ?>
  <div class="card" style="border:1px solid #059669;box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:18px 20px">
      <div style="font-weight:700;font-size:14px;margin-bottom:8px">
        <i class="bi bi-patch-check-fill me-2" style="color:#059669"></i>ภารกิจพิสูจน์ทักษะ
      </div>
      <p style="font-size:13.5px;line-height:1.85;margin:0 0 12px;color:var(--bs-secondary-color)"><?= nl2br(e($level['mission_brief'])) ?></p>
      <a href="<?= url('student/lms-promotion.php') ?>?level=<?= $levelId ?>" class="btn btn-sm"
         style="background:#059669;border:none;color:white;font-size:13px;text-decoration:none">
        <i class="bi bi-send me-1"></i><?= $state['promotion'] ? 'ดูสถานะภารกิจ' : 'ส่งภารกิจเพื่อขอเลื่อนระดับ' ?>
      </a>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
