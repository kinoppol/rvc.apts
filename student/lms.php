<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);

// Pure GET page: every quiz action posts to student/lms-quiz.php instead.
$progress = LmsProgress::forUser((int) $user['id']);
$levels   = LmsLevel::published();
$summary  = LmsProgress::summaryFor((int) $user['id']);

$activeNav = 'lms';
require __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:20px">
  <h5 style="font-weight:700;margin:0">บทเรียนการใช้งาน AI</h5>
  <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:3px">
    เรียนตามลำดับระดับ ผ่านแบบทดสอบหลังเรียนตามเกณฑ์เพื่อปลดล็อกระดับถัดไป
    และส่งภารกิจเพื่อขอเลื่อนกลุ่มสิทธิ์การใช้งาน AI
  </div>
</div>

<?php if (!$levels): ?>
  <div class="card" style="border:1px solid var(--bs-border-color)">
    <div class="card-body" style="padding:44px;text-align:center;color:var(--bs-secondary-color)">
      <i class="bi bi-journal-x" style="font-size:30px;display:block;margin-bottom:12px"></i>
      ยังไม่มีบทเรียนที่เปิดให้เรียนในขณะนี้
    </div>
  </div>
<?php else: ?>

  <?php $overall = $summary['totalLevels'] > 0 ? (int) round($summary['passedLevels'] * 100 / $summary['totalLevels']) : 0; ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:18px">
    <div class="card-body" style="padding:18px 22px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:10px">
        <span style="font-weight:700;font-size:14px">ความคืบหน้าโดยรวม</span>
        <span style="font-size:13px;color:var(--bs-secondary-color)">
          ผ่านแล้ว <strong style="color:#059669"><?= (int) $summary['passedLevels'] ?></strong> จาก <?= (int) $summary['totalLevels'] ?> ระดับ
        </span>
      </div>
      <div style="height:9px;border-radius:20px;background:var(--bs-secondary-bg);overflow:hidden">
        <div style="height:100%;width:<?= $overall ?>%;background:linear-gradient(90deg,#2563EB,#0EA5E9);border-radius:20px;transition:width .4s"></div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach ($levels as $idx => $lv):
        $lid = (int) $lv['id'];
        $st  = $progress['levels'][$lid] ?? null;
        if (!$st) { continue; }
        $color   = (string) $lv['accent_color'];
        $locked  = !$st['unlocked'];
        $prev    = LmsLevel::prevOf($levels, $lid);
        $topicPct = $st['topicsTotal'] > 0 ? (int) round($st['topicsDone'] * 100 / $st['topicsTotal']) : 0;
    ?>
    <div class="col-12 col-lg-6">
      <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);height:100%;<?= $locked ? 'opacity:.72' : '' ?>">
        <div class="card-body" style="padding:18px 20px;display:flex;flex-direction:column;height:100%">

          <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px">
            <span style="flex-shrink:0;width:44px;height:44px;border-radius:12px;background:<?= e($color) ?>1a;display:flex;align-items:center;justify-content:center">
              <i class="bi <?= $locked ? 'bi-lock-fill' : e($lv['icon']) ?>" style="color:<?= e($color) ?>;font-size:20px"></i>
            </span>
            <div style="min-width:0;flex:1">
              <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
                <span style="font-size:11px;font-weight:700;color:var(--bs-tertiary-color)">ระดับที่ <?= $idx + 1 ?></span>
                <?php if ($st['postPassed']): ?><span class="badge-ok">ผ่านแล้ว</span><?php endif; ?>
                <?php if ($st['promoted']): ?>
                  <span class="badge-teach"><i class="bi bi-patch-check-fill me-1"></i>เลื่อนกลุ่มแล้ว</span>
                <?php endif; ?>
              </div>
              <div style="font-weight:700;font-size:15.5px;margin-top:2px"><?= e($lv['title']) ?></div>
              <?php if (!empty($lv['subtitle'])): ?>
                <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:2px"><?= e($lv['subtitle']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($locked): ?>
            <div style="background:var(--bs-secondary-bg);border-radius:9px;padding:11px 13px;font-size:12.5px;color:var(--bs-secondary-color);line-height:1.7;margin-bottom:12px">
              <i class="bi bi-lock me-1"></i>
              <?php if ($prev): ?>
                ต้องผ่านแบบทดสอบหลังเรียนของ <strong><?= e($prev['title']) ?></strong>
                ที่ <?= (int) $prev['pass_percent'] ?>% ขึ้นไปก่อน
              <?php else: ?>
                ยังไม่เปิดให้เรียน
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div style="margin-bottom:12px">
              <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--bs-secondary-color);margin-bottom:5px">
                <span>หัวข้อที่ผ่านแล้ว</span>
                <span style="font-weight:600"><?= (int) $st['topicsDone'] ?>/<?= (int) $st['topicsTotal'] ?></span>
              </div>
              <div style="height:7px;border-radius:20px;background:var(--bs-secondary-bg);overflow:hidden">
                <div style="height:100%;width:<?= $topicPct ?>%;background:<?= e($color) ?>;border-radius:20px"></div>
              </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
              <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)">
                ก่อนเรียน: <?= $st['bestPre'] === null ? 'ยังไม่ทำ' : (int) $st['bestPre'] . '%' ?>
              </span>
              <span style="font-size:11.5px;padding:3px 10px;border-radius:20px;background:<?= $st['postPassed'] ? '#ECFDF5' : 'var(--bs-secondary-bg)' ?>;color:<?= $st['postPassed'] ? '#059669' : 'var(--bs-secondary-color)' ?>">
                หลังเรียน: <?= $st['bestPost'] === null ? 'ยังไม่ทำ' : (int) $st['bestPost'] . '%' ?>
                <span style="opacity:.7">(เกณฑ์ <?= (int) $lv['pass_percent'] ?>%)</span>
              </span>
            </div>

            <?php if ($st['promotion']): $pr = $st['promotion']; ?>
              <?php
                $chip = match ($pr['status']) {
                    'pending'  => ['#D97706', '#FFFBEB', 'bi-hourglass-split', 'ภารกิจรอผู้ดูแลตรวจ'],
                    'approved' => ['#059669', '#ECFDF5', 'bi-patch-check-fill', 'ภารกิจผ่านแล้ว — ย้ายไปกลุ่ม ' . ($pr['granted_group_name'] ?? '')],
                    'revise'   => ['#D97706', '#FFFBEB', 'bi-arrow-counterclockwise', 'ผู้ดูแลขอให้แก้ไขภารกิจ'],
                    default    => ['#DC2626', '#FEF2F2', 'bi-x-circle', 'ภารกิจยังไม่ผ่าน'],
                };
              ?>
              <div style="background:<?= $chip[1] ?>;border-left:3px solid <?= $chip[0] ?>;border-radius:8px;padding:9px 12px;font-size:12px;color:<?= $chip[0] ?>;margin-bottom:12px">
                <i class="bi <?= $chip[2] ?> me-1"></i><?= e($chip[3]) ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <div style="margin-top:auto;display:flex;gap:8px;flex-wrap:wrap">
            <?php if ($locked): ?>
              <button class="btn btn-sm" disabled style="background:var(--bs-secondary-bg);border:none;color:var(--bs-tertiary-color);font-size:13px">
                <i class="bi bi-lock me-1"></i>ยังไม่ปลดล็อก
              </button>
            <?php else: ?>
              <a href="<?= url('student/lms-level.php') ?>?id=<?= $lid ?>" class="btn btn-sm"
                 style="background:<?= e($color) ?>;border:none;color:white;font-size:13px;text-decoration:none">
                <i class="bi bi-play-fill me-1"></i><?= $st['topicsDone'] > 0 || $st['preTries'] > 0 ? 'เรียนต่อ' : 'เริ่มเรียน' ?>
              </a>
              <?php if ($st['canRequestPromotion']): ?>
                <a href="<?= url('student/lms-promotion.php') ?>?level=<?= $lid ?>" class="btn btn-sm"
                   style="background:transparent;border:1px solid #059669;color:#059669;font-size:13px;text-decoration:none">
                  <i class="bi bi-send me-1"></i>ส่งภารกิจเลื่อนระดับ
                </a>
              <?php elseif ($st['promotion']): ?>
                <a href="<?= url('student/lms-promotion.php') ?>?level=<?= $lid ?>" class="btn btn-sm"
                   style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:13px;text-decoration:none">
                  ดูสถานะภารกิจ
                </a>
              <?php endif; ?>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
