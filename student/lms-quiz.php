<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);
$uid  = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';

    if ($action === 'start') {
        $topicId = ($_POST['topic_id'] ?? '') !== '' ? (int) $_POST['topic_id'] : null;
        $result  = LmsQuiz::start($uid, (int) ($_POST['level_id'] ?? 0), (string) ($_POST['phase'] ?? ''), $topicId);
        if (!$result['ok']) {
            flash_set('err', $result['error'] ?? 'เกิดข้อผิดพลาด');
            header('Location: ' . url('student/lms.php'));
            exit;
        }
        header('Location: ' . url('student/lms-quiz.php') . '?attempt=' . (int) $result['attempt_id']);
        exit;
    }

    if ($action === 'submit') {
        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $result    = LmsQuiz::submit($uid, $attemptId, (array) ($_POST['q'] ?? []));
        if (!$result['ok']) {
            flash_set('err', $result['error'] ?? 'เกิดข้อผิดพลาด');
        }
        header('Location: ' . url('student/lms-quiz.php') . '?attempt=' . $attemptId);
        exit;
    }

    header('Location: ' . url('student/lms.php'));
    exit;
}

$attemptId = (int) ($_GET['attempt'] ?? 0);
$attempt   = LmsQuiz::attempt($attemptId, $uid);
if (!$attempt) {
    flash_set('err', 'ไม่พบแบบทดสอบนี้');
    header('Location: ' . url('student/lms.php'));
    exit;
}

$levelId  = (int) $attempt['level_id'];
$topicId  = $attempt['topic_id'] !== null ? (int) $attempt['topic_id'] : null;
$phase    = (string) $attempt['phase'];
$done     = $attempt['submitted_at'] !== null;
$color    = (string) $attempt['accent_color'];
$backUrl  = $topicId !== null
    ? url('student/lms-topic.php') . '?id=' . $topicId
    : url('student/lms-level.php') . '?id=' . $levelId;

$phaseLabel = match ($phase) {
    'pre'   => 'แบบทดสอบก่อนเรียน',
    'post'  => 'แบบทดสอบหลังเรียน',
    default => 'แบบทดสอบทบทวน',
};

$questions = $done ? LmsQuiz::questionsForResult($attemptId) : LmsQuiz::questionsForTaking($attemptId);

$activeNav = 'lms';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--bs-secondary-color);margin-bottom:12px;flex-wrap:wrap">
  <a href="<?= e($backUrl) ?>" style="color:#2563EB;text-decoration:none"><i class="bi bi-arrow-left me-1"></i>กลับ</a>
  <span>/</span><span><?= e($attempt['level_title']) ?></span>
  <?php if ($attempt['topic_title']): ?><span>/</span><span><?= e($attempt['topic_title']) ?></span><?php endif; ?>
</div>

<?php if (!$done): ?>
  <?php
  /* Taking view. The answer key is not in this page at all — questionsForTaking()
     never selects the choices' correctness flag or the explanation, so there is
     nothing to reveal in view-source. Kept as a PHP comment on purpose: internal
     reasoning should not ship to the client either. */
  ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
    <div class="card-body" style="padding:18px 22px">
      <h5 style="font-weight:700;margin:0;font-size:16px"><?= e($phaseLabel) ?> — <?= e($attempt['level_title']) ?></h5>
      <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:5px;line-height:1.75">
        ทั้งหมด <?= (int) $attempt['question_count'] ?> ข้อ · เลือกคำตอบให้ครบแล้วกดส่ง
        <?php if ($phase === 'post'): ?>
          · เกณฑ์ผ่าน <strong><?= (int) $attempt['pass_percent'] ?>%</strong>
        <?php elseif ($phase === 'review'): ?>
          · ต้องถูกอย่างน้อย <strong><?= (int) $attempt['review_pass_correct'] ?> ข้อ</strong>
        <?php endif; ?>
        <br><span style="color:var(--bs-tertiary-color)">ลำดับข้อและตัวเลือกถูกสุ่มใหม่สำหรับการทำครั้งนี้ — ออกจากหน้านี้แล้วกลับมาทำต่อได้ ชุดข้อสอบจะเหมือนเดิม</span>
      </div>
    </div>
  </div>

  <form method="post" id="quizForm">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="submit">
    <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">

    <?php foreach ($questions as $i => $q): ?>
      <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:12px">
        <div class="card-body" style="padding:18px 22px">
          <div style="display:flex;gap:11px;margin-bottom:13px">
            <span style="flex-shrink:0;width:26px;height:26px;border-radius:50%;background:<?= e($color) ?>1a;color:<?= e($color) ?>;display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700"><?= $i + 1 ?></span>
            <span style="font-size:14.5px;font-weight:600;line-height:1.75;min-width:0"><?= nl2br(e($q['question_text'])) ?></span>
          </div>
          <?php foreach ($q['choices'] as $c): $inputId = 'q' . (int) $q['id'] . 'c' . (int) $c['id']; ?>
            <label for="<?= $inputId ?>" class="lms-choice"
                   style="display:flex;align-items:flex-start;gap:10px;padding:10px 13px;border:1px solid var(--bs-border-color);border-radius:9px;margin-bottom:7px;cursor:pointer;font-size:13.5px;line-height:1.7">
              <input type="radio" id="<?= $inputId ?>" name="q[<?= (int) $q['id'] ?>]" value="<?= (int) $c['id'] ?>"
                     class="form-check-input" style="flex-shrink:0;margin:2px 0 0;width:17px;height:17px"
                     <?= (int) ($q['selected_choice_id'] ?? 0) === (int) $c['id'] ? 'checked' : '' ?>>
              <span style="min-width:0"><?= e($c['choice_text']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="position:sticky;bottom:0;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:12px;padding:13px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;box-shadow:0 -2px 10px rgba(0,0,0,.05)">
      <span style="font-size:13px;color:var(--bs-secondary-color)">
        ตอบแล้ว <strong id="answeredCount">0</strong>/<?= count($questions) ?> ข้อ
      </span>
      <button type="submit" class="btn btn-sm" style="background:<?= e($color) ?>;border:none;color:white;font-size:13.5px;padding:7px 22px"
              data-confirm-modal data-confirm-title="ส่งคำตอบ"
              data-confirm-msg="ส่งคำตอบแล้วจะแก้ไขไม่ได้ ต้องการส่งใช่หรือไม่?"
              data-confirm-icon="bi-send" data-confirm-color="<?= e($color) ?>"
              data-confirm-btn="ส่งคำตอบ" data-confirm-cls="btn-primary">
        <i class="bi bi-send me-1"></i>ส่งคำตอบ
      </button>
    </div>
  </form>

  <script>
  // Progressive enhancement only — the form submits fine with JS disabled.
  (function () {
    var form = document.getElementById('quizForm');
    var out  = document.getElementById('answeredCount');
    function refresh() {
      var names = {};
      form.querySelectorAll('input[type=radio]:checked').forEach(function (r) { names[r.name] = 1; });
      out.textContent = Object.keys(names).length;
    }
    form.addEventListener('change', refresh);
    refresh();
  })();
  </script>

<?php else: ?>
  <!-- ── Result view ────────────────────────────────────────────────────── -->
  <?php
    $pct     = LmsQuiz::percent($attempt);
    $passed  = LmsQuiz::passed($attempt);
    $barColor = $passed ? '#059669' : '#DC2626';
  ?>
  <div class="card" style="border:1px solid <?= $barColor ?>;box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
    <div class="card-body" style="padding:22px;text-align:center">
      <i class="bi <?= $passed ? 'bi-patch-check-fill' : 'bi-x-circle' ?>" style="font-size:34px;color:<?= $barColor ?>"></i>
      <div style="font-weight:700;font-size:17px;margin-top:9px"><?= $passed ? 'ผ่านเกณฑ์' : 'ยังไม่ผ่านเกณฑ์' ?></div>
      <div style="font-size:26px;font-weight:700;color:<?= $barColor ?>;margin-top:6px">
        <?= (int) $attempt['correct_count'] ?>/<?= (int) $attempt['question_count'] ?>
        <span style="font-size:16px;font-weight:600">(<?= $pct ?>%)</span>
      </div>
      <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:6px">
        <?= e($phaseLabel) ?> — <?= e($attempt['level_title']) ?>
        <?php if ($phase === 'review'): ?>
          · เกณฑ์ผ่าน <?= (int) $attempt['review_pass_correct'] ?>/<?= (int) $attempt['question_count'] ?> ข้อ
        <?php elseif ($phase === 'post'): ?>
          · เกณฑ์ผ่าน <?= (int) $attempt['pass_percent'] ?>%
        <?php endif; ?>
      </div>

      <?php if ($phase === 'post' && $passed): ?>
        <div style="background:#ECFDF5;border-radius:9px;padding:11px 14px;margin-top:14px;font-size:13px;color:#065F46;line-height:1.75">
          <i class="bi bi-unlock me-1"></i>ปลดล็อกระดับถัดไปแล้ว
          <?php if (!empty(LmsLevel::find($levelId)['mission_brief'])): ?>
            — และส่ง<strong>ภารกิจพิสูจน์ทักษะ</strong>เพื่อขอเลื่อนกลุ่มสิทธิ์การใช้งาน AI ได้แล้ว
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div style="display:flex;gap:9px;justify-content:center;margin-top:16px;flex-wrap:wrap">
        <form method="post" style="margin:0">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="start">
          <input type="hidden" name="level_id" value="<?= $levelId ?>">
          <input type="hidden" name="phase" value="<?= e($phase) ?>">
          <?php if ($topicId !== null): ?><input type="hidden" name="topic_id" value="<?= $topicId ?>"><?php endif; ?>
          <button type="submit" class="btn btn-sm" style="background:<?= e($color) ?>;border:none;color:white;font-size:13px">
            <i class="bi bi-arrow-repeat me-1"></i>ทำอีกครั้ง
          </button>
        </form>
        <a href="<?= e($backUrl) ?>" class="btn btn-sm"
           style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:13px;text-decoration:none">
          กลับไปบทเรียน
        </a>
      </div>
    </div>
  </div>

  <?php foreach ($questions as $i => $q):
      $picked  = (int) ($q['selected_choice_id'] ?? 0);
      $wasRight = (int) $q['is_correct'] === 1;
  ?>
    <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:12px">
      <div class="card-body" style="padding:18px 22px">
        <div style="display:flex;gap:11px;margin-bottom:12px">
          <span style="flex-shrink:0;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;
                       background:<?= $wasRight ? '#ECFDF5' : '#FEF2F2' ?>;color:<?= $wasRight ? '#059669' : '#DC2626' ?>">
            <i class="bi <?= $wasRight ? 'bi-check-lg' : 'bi-x-lg' ?>"></i>
          </span>
          <span style="font-size:14.5px;font-weight:600;line-height:1.75;min-width:0"><?= $i + 1 ?>. <?= nl2br(e($q['question_text'])) ?></span>
        </div>

        <?php foreach ($q['choices'] as $c):
            $cid       = (int) $c['id'];
            $isCorrect = (int) $c['is_correct'] === 1;
            $isPicked  = $cid === $picked;
            $bg = $isCorrect ? '#ECFDF5' : ($isPicked ? '#FEF2F2' : 'transparent');
            $bd = $isCorrect ? '#059669' : ($isPicked ? '#DC2626' : 'var(--bs-border-color)');
        ?>
          <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 13px;border:1px solid <?= $bd ?>;border-radius:9px;margin-bottom:7px;background:<?= $bg ?>;font-size:13.5px;line-height:1.7">
            <i class="bi <?= $isCorrect ? 'bi-check-circle-fill' : ($isPicked ? 'bi-x-circle-fill' : 'bi-circle') ?>"
               style="flex-shrink:0;margin-top:2px;color:<?= $isCorrect ? '#059669' : ($isPicked ? '#DC2626' : 'var(--bs-tertiary-color)') ?>"></i>
            <span style="min-width:0;color:<?= $isCorrect || $isPicked ? '#1F2937' : 'inherit' ?>">
              <?= e($c['choice_text']) ?>
              <?php if ($isPicked): ?><span style="font-size:11.5px;color:var(--bs-secondary-color)"> — คำตอบของคุณ</span><?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>

        <?php if ($picked === 0): ?>
          <div style="font-size:12px;color:#D97706;margin-top:6px"><i class="bi bi-dash-circle me-1"></i>ข้อนี้ไม่ได้ตอบ</div>
        <?php elseif (!array_filter($q['choices'], fn ($c) => (int) $c['id'] === $picked)): ?>
          <div style="font-size:12px;color:var(--bs-tertiary-color);margin-top:6px">(ตัวเลือกที่คุณเลือกถูกแก้ไขไปแล้ว)</div>
        <?php endif; ?>

        <?php if (!empty($q['explanation'])): ?>
          <div style="background:var(--bs-secondary-bg);border-radius:9px;padding:11px 13px;margin-top:10px;font-size:13px;line-height:1.8;color:var(--bs-secondary-color)">
            <i class="bi bi-lightbulb me-1" style="color:#D97706"></i><?= nl2br(e($q['explanation'])) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
