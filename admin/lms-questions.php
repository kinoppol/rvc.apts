<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action  = $_POST['action'] ?? '';
    $id      = (int) ($_POST['id'] ?? 0);
    $levelId = (int) ($_POST['level_id'] ?? 0);
    $phase   = in_array($_POST['phase'] ?? '', ['pre', 'post'], true) ? $_POST['phase'] : 'pre';

    if ($action === 'save_question') {
        $result = LmsQuestion::save($_POST, $id > 0 ? $id : null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกคำถามเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'toggle_question') {
        LmsQuestion::toggleActive($id);
        flash_set('ok', 'อัปเดตสถานะคำถามเรียบร้อยแล้ว');
    } elseif ($action === 'delete_question') {
        $result = LmsQuestion::delete($id);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ลบคำถามเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    }
    header('Location: ' . url('admin/lms-questions.php') . '?level=' . $levelId . '&phase=' . $phase);
    exit;
}

$levelId = (int) ($_GET['level'] ?? 0);
$level   = LmsLevel::find($levelId);
if (!$level) {
    flash_set('err', 'ไม่พบระดับที่เลือก');
    header('Location: ' . url('admin/lms.php'));
    exit;
}
$phase = in_array($_GET['phase'] ?? '', ['pre', 'post'], true) ? $_GET['phase'] : 'pre';

$bank      = LmsQuestion::bank($levelId, $phase);
$readiness = LmsLevel::readiness($levelId);
$needed    = (int) ($phase === 'pre' ? $level['pre_question_count'] : $level['post_question_count']);
$active    = LmsQuestion::activeCount($levelId, $phase);

$activeNav = 'lms-content';
require __DIR__ . '/../includes/header.php';

function lq_form(string $action, int $id, int $levelId, string $phase, string $btnCls, string $icon, string $label, ?array $modal = null): string
{
    $attrs = '';
    if ($modal) {
        $attrs = ' data-confirm-modal'
            . ' data-confirm-title="' . e($modal['title'] ?? '') . '"'
            . ' data-confirm-msg="' . e($modal['msg'] ?? '') . '"'
            . ' data-confirm-icon="' . e($modal['icon'] ?? 'bi-question-circle') . '"'
            . ' data-confirm-color="' . e($modal['color'] ?? '#DC2626') . '"'
            . ' data-confirm-btn="' . e($modal['btn'] ?? 'ยืนยัน') . '"'
            . ' data-confirm-cls="btn-danger"';
    }
    return "<form method='post' style='display:inline;margin:0'>" . Csrf::field()
        . "<input type='hidden' name='action' value='" . e($action) . "'>"
        . "<input type='hidden' name='id' value='{$id}'>"
        . "<input type='hidden' name='level_id' value='{$levelId}'>"
        . "<input type='hidden' name='phase' value='" . e($phase) . "'>"
        . "<button type='submit' class='{$btnCls}'{$attrs}><i class='bi {$icon}'></i>" . ($label !== '' ? " {$label}" : '') . "</button></form>";
}
?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--bs-secondary-color);margin-bottom:10px;flex-wrap:wrap">
  <a href="<?= url('admin/lms.php') ?>" style="color:#2563EB;text-decoration:none"><i class="bi bi-arrow-left me-1"></i>จัดการบทเรียน</a>
  <span>/</span>
  <span style="font-weight:600;color:var(--bs-body-color)"><?= e($level['title']) ?></span>
  <span>/</span><span>คลังข้อสอบ</span>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px">
  <h5 style="font-weight:700;margin:0">คลังข้อสอบ — <?= e($level['title']) ?></h5>
  <a href="<?= url('admin/lms-topics.php') ?>?level=<?= $levelId ?>" class="btn btn-sm"
     style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:13px">
    <i class="bi bi-list-ul me-1"></i>หัวข้อและเนื้อหา
  </a>
</div>

<!-- Phase tabs -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--bs-border-color);margin-bottom:18px">
  <a href="<?= url('admin/lms-questions.php') ?>?level=<?= $levelId ?>&phase=pre"
     style="text-decoration:none;padding:9px 20px;font-size:13px;font-weight:600;<?= $phase === 'pre' ? 'color:#2563EB;border-bottom:2px solid #2563EB;margin-bottom:-2px' : 'color:var(--bs-secondary-color)' ?>">
    <i class="bi bi-clipboard-check me-2"></i>ก่อนเรียน (<?= (int) $readiness['preCount'] ?>)
  </a>
  <a href="<?= url('admin/lms-questions.php') ?>?level=<?= $levelId ?>&phase=post"
     style="text-decoration:none;padding:9px 20px;font-size:13px;font-weight:600;<?= $phase === 'post' ? 'color:#059669;border-bottom:2px solid #059669;margin-bottom:-2px' : 'color:var(--bs-secondary-color)' ?>">
    <i class="bi bi-patch-check me-2"></i>หลังเรียน (<?= (int) $readiness['postCount'] ?>)
  </a>
</div>

<?php $short = $active < $needed; ?>
<div style="background:<?= $short ? '#FEF2F2' : '#ECFDF5' ?>;border-left:3px solid <?= $short ? '#DC2626' : '#059669' ?>;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;line-height:1.8;color:<?= $short ? '#991B1B' : '#065F46' ?>">
  <i class="bi <?= $short ? 'bi-exclamation-triangle' : 'bi-check-circle' ?> me-1"></i>
  แบบทดสอบ<?= $phase === 'pre' ? 'ก่อนเรียน' : 'หลังเรียน' ?>สุ่มออก <strong><?= $needed ?> ข้อ</strong> ต่อครั้ง
  — ขณะนี้คลังมีคำถามที่พร้อมใช้ <strong><?= $active ?> ข้อ</strong>
  <?php if ($short): ?>
    <div style="margin-top:4px">ยังไม่พอ — นักศึกษาจะเริ่มทำแบบทดสอบนี้ไม่ได้จนกว่าจะมีอย่างน้อย <?= $needed ?> ข้อ</div>
  <?php else: ?>
    <div style="margin-top:4px">คลังใหญ่กว่าจำนวนที่สุ่มออก ทำให้ผู้เรียนได้ชุดข้อสอบต่างกันในแต่ละครั้ง</div>
  <?php endif; ?>
</div>

<?php if ($readiness['duplicateTexts']): ?>
  <div style="background:#FFFBEB;border-left:3px solid #D97706;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12.5px;line-height:1.8;color:#92400E">
    <i class="bi bi-files me-1"></i>
    พบโจทย์ที่ข้อความซ้ำกันระหว่างคลังก่อนเรียนและหลังเรียน <?= count($readiness['duplicateTexts']) ?> ข้อ
    — ควรใช้โจทย์คนละชุด เพื่อให้เปรียบเทียบผลก่อน/หลังได้อย่างมีความหมาย
    <ul style="margin:6px 0 0;padding-left:20px">
      <?php foreach (array_slice($readiness['duplicateTexts'], 0, 5) as $dup): ?>
        <li><?= e(mb_strimwidth($dup, 0, 90, '…')) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:0">
    <div style="padding:14px 18px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
      <span style="font-weight:700;font-size:13.5px">คำถามทั้งหมด <?= count($bank) ?> ข้อ</span>
      <button class="btn btn-sm" style="background:<?= $phase === 'pre' ? '#2563EB' : '#059669' ?>;border:none;color:white;font-size:12.5px"
              data-bs-toggle="modal" data-bs-target="#questionModal" data-mode="add">
        <i class="bi bi-plus-lg me-1"></i>เพิ่มคำถาม
      </button>
    </div>

    <?php if (!$bank): ?>
      <div style="padding:34px;text-align:center;color:var(--bs-secondary-color);font-size:13px">
        <i class="bi bi-inbox" style="font-size:26px;display:block;margin-bottom:9px"></i>
        ยังไม่มีคำถามในคลังนี้
      </div>
    <?php else: ?>
      <?php foreach ($bank as $i => $q): $qid = (int) $q['id']; $qChoices = LmsQuestion::choices($qid); ?>
        <div style="border-top:1px solid var(--bs-border-color);padding:13px 18px">
          <div style="display:flex;align-items:flex-start;gap:11px">
            <span style="flex-shrink:0;font-size:11.5px;font-weight:700;color:var(--bs-tertiary-color);margin-top:2px;min-width:20px"><?= $i + 1 ?>.</span>
            <div style="flex:1;min-width:0">
              <div style="font-size:13.5px;font-weight:600;line-height:1.65;<?= (int) $q['is_active'] !== 1 ? 'opacity:.55' : '' ?>">
                <?= e($q['question_text']) ?>
              </div>
              <div style="font-size:12px;color:#059669;margin-top:4px">
                <i class="bi bi-check-circle me-1"></i><?= e((string) ($q['correct_text'] ?? '— ยังไม่ได้เลือกคำตอบที่ถูก —')) ?>
              </div>
              <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:3px">
                <?= (int) $q['choice_count'] ?> ตัวเลือก · ถูกสุ่มออกไปแล้ว <?= (int) $q['used_count'] ?> ครั้ง
                <?php if ((int) $q['correct_count'] !== 1): ?>
                  · <span style="color:#DC2626;font-weight:600">ยังใช้ไม่ได้ (ต้องมีคำตอบถูกข้อเดียว)</span>
                <?php endif; ?>
                <?php if ((int) $q['is_active'] !== 1): ?> · <span style="color:#DC2626">ปิดใช้งาน</span><?php endif; ?>
              </div>
            </div>
            <span style="display:flex;gap:4px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end">
              <button class="action-btn-blue" style="font-size:11.5px" data-bs-toggle="modal" data-bs-target="#questionModal"
                      data-mode="edit" data-id="<?= $qid ?>" data-text="<?= e($q['question_text']) ?>"
                      data-explanation="<?= e((string) ($q['explanation'] ?? '')) ?>"
                      data-choices="<?= e(json_encode(array_map(fn ($c) => ['t' => $c['choice_text'], 'c' => (int) $c['is_correct']], $qChoices), JSON_UNESCAPED_UNICODE)) ?>">
                <i class="bi bi-pencil"></i> แก้ไข
              </button>
              <?= lq_form('toggle_question', $qid, $levelId, $phase,
                    (int) $q['is_active'] === 1 ? 'action-btn-warn' : 'action-btn-ok',
                    (int) $q['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle',
                    (int) $q['is_active'] === 1 ? 'ปิด' : 'เปิด') ?>
              <?= lq_form('delete_question', $qid, $levelId, $phase, 'action-btn-err', 'bi-trash3', 'ลบ', [
                    'title' => 'ลบคำถาม',
                    'msg'   => 'ต้องการลบคำถามข้อนี้ใช่หรือไม่? หากเคยถูกใช้ในแบบทดสอบแล้ว ระบบจะให้ปิดใช้งานแทน',
                    'icon'  => 'bi-trash3', 'color' => '#DC2626', 'btn' => 'ลบ',
                  ]) ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Add / edit question -->
<div class="modal fade" id="questionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:600px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save_question">
        <input type="hidden" name="level_id" value="<?= $levelId ?>">
        <input type="hidden" name="phase" value="<?= e($phase) ?>">
        <input type="hidden" name="id" id="questionId" value="">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700">
            <i class="bi bi-patch-question me-2" style="color:<?= $phase === 'pre' ? '#2563EB' : '#059669' ?>"></i>
            <span id="questionModalTitle">เพิ่มคำถาม</span>
            <span style="font-weight:500;color:var(--bs-secondary-color)">— <?= $phase === 'pre' ? 'ก่อนเรียน' : 'หลังเรียน' ?></span>
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <?php require __DIR__ . '/../includes/lms-question-fields.php'; ?>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-sm" style="background:<?= $phase === 'pre' ? '#2563EB' : '#059669' ?>;border:none;color:white">บันทึกคำถาม</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?= asset('assets/lms-admin.js') ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
