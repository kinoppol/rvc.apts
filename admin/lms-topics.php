<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

// A body larger than post_max_size arrives with $_POST wiped, which would look like a
// CSRF failure. Detect it before Csrf::check() — same guard as admin/slots.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash_set('err', 'ไฟล์ที่อัปโหลดมีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รับได้');
    header('Location: ' . url('admin/lms.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action  = $_POST['action'] ?? '';
    $id      = (int) ($_POST['id'] ?? 0);
    $levelId = (int) ($_POST['level_id'] ?? 0);
    $topicId = (int) ($_POST['topic_id'] ?? 0);

    if ($action === 'add_topic') {
        $result = LmsContent::addTopic($levelId, (string) ($_POST['title'] ?? ''), (string) ($_POST['summary'] ?? ''));
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'เพิ่มหัวข้อเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'save_topic') {
        $result = LmsContent::updateTopic($id, (string) ($_POST['title'] ?? ''), (string) ($_POST['summary'] ?? ''));
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกหัวข้อเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'toggle_topic') {
        LmsContent::toggleTopicPublished($id);
        flash_set('ok', 'อัปเดตสถานะหัวข้อเรียบร้อยแล้ว');
    } elseif ($action === 'move_topic') {
        LmsContent::moveTopic($id, (string) ($_POST['dir'] ?? ''));
        flash_set('ok', 'จัดลำดับหัวข้อเรียบร้อยแล้ว');
    } elseif ($action === 'delete_topic') {
        $result = LmsContent::deleteTopic($id);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ลบหัวข้อเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
        if ($result['ok']) {
            $topicId = 0;
        }
    } elseif ($action === 'save_block') {
        // The modal shows one meta control per block type (meta_heading, meta_list, …);
        // collapse whichever applies into the single `meta` field the domain expects.
        $data = $_POST;
        $data['meta']  = $_POST['meta_' . ($_POST['block_type'] ?? '')] ?? '';
        $data['image'] = $_FILES['image'] ?? null;
        $result = LmsContent::saveBlock($data, $id > 0 ? $id : null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกบล็อกเนื้อหาเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'move_block') {
        LmsContent::moveBlock($id, (string) ($_POST['dir'] ?? ''));
        flash_set('ok', 'จัดลำดับบล็อกเรียบร้อยแล้ว');
    } elseif ($action === 'delete_block') {
        $result = LmsContent::deleteBlock($id);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ลบบล็อกเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'save_question') {
        $data = $_POST;
        $data['phase'] = 'review';
        $result = LmsQuestion::save($data, $id > 0 ? $id : null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกคำถามทบทวนเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'toggle_question') {
        LmsQuestion::toggleActive($id);
        flash_set('ok', 'อัปเดตสถานะคำถามเรียบร้อยแล้ว');
    } elseif ($action === 'delete_question') {
        $result = LmsQuestion::delete($id);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ลบคำถามเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    }

    $back = url('admin/lms-topics.php') . '?level=' . $levelId . ($topicId > 0 ? '&topic=' . $topicId : '');
    header('Location: ' . $back);
    exit;
}

$levelId = (int) ($_GET['level'] ?? 0);
$level   = LmsLevel::find($levelId);
if (!$level) {
    flash_set('err', 'ไม่พบระดับที่เลือก');
    header('Location: ' . url('admin/lms.php'));
    exit;
}

$topics = LmsContent::topics($levelId);
$topic  = null;
if (!empty($_GET['topic'])) {
    $candidate = LmsContent::findTopic((int) $_GET['topic']);
    if ($candidate && (int) $candidate['level_id'] === $levelId) {
        $topic = $candidate;
    }
}
$blocks    = $topic ? LmsContent::blocks((int) $topic['id']) : [];
$questions = $topic ? LmsQuestion::bank($levelId, 'review', (int) $topic['id']) : [];

$activeNav = 'lms-content';
require __DIR__ . '/../includes/header.php';

function lt_form(string $action, int $id, int $levelId, int $topicId, string $btnCls, string $icon, string $label, array $extra = [], ?array $modal = null): string
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
    $hidden = '';
    foreach ($extra as $k => $v) {
        $hidden .= "<input type='hidden' name='" . e($k) . "' value='" . e((string) $v) . "'>";
    }
    return "<form method='post' style='display:inline;margin:0'>" . Csrf::field()
        . "<input type='hidden' name='action' value='" . e($action) . "'>"
        . "<input type='hidden' name='id' value='{$id}'>"
        . "<input type='hidden' name='level_id' value='{$levelId}'>"
        . "<input type='hidden' name='topic_id' value='{$topicId}'>{$hidden}"
        . "<button type='submit' class='{$btnCls}'{$attrs}><i class='bi {$icon}'></i>" . ($label !== '' ? " {$label}" : '') . "</button></form>";
}
?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--bs-secondary-color);margin-bottom:10px;flex-wrap:wrap">
  <a href="<?= url('admin/lms.php') ?>" style="color:#2563EB;text-decoration:none"><i class="bi bi-arrow-left me-1"></i>จัดการบทเรียน</a>
  <span>/</span>
  <span style="font-weight:600;color:var(--bs-body-color)"><?= e($level['title']) ?></span>
  <?php if ($topic): ?><span>/</span><span><?= e($topic['title']) ?></span><?php endif; ?>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:12px">
  <h5 style="font-weight:700;margin:0">หัวข้อและเนื้อหา — <?= e($level['title']) ?></h5>
  <a href="<?= url('admin/lms-questions.php') ?>?level=<?= $levelId ?>&phase=pre" class="btn btn-sm"
     style="background:transparent;border:1px solid var(--bs-border-color);color:var(--bs-secondary-color);font-size:13px">
    <i class="bi bi-patch-question me-1"></i>คลังข้อสอบก่อน/หลังเรียน
  </a>
</div>

<div class="row g-3">
  <!-- ── Topic list ── -->
  <div class="col-12 col-lg-4">
    <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <div class="card-body" style="padding:0">
        <div style="padding:14px 16px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between">
          <span style="font-weight:700;font-size:13.5px">หัวข้อย่อย (<?= count($topics) ?>)</span>
          <button class="btn btn-sm" style="background:#2563EB;border:none;color:white;font-size:12px" data-bs-toggle="modal" data-bs-target="#addTopicModal">
            <i class="bi bi-plus-lg"></i> เพิ่ม
          </button>
        </div>
        <?php if (!$topics): ?>
          <div style="padding:28px 16px;text-align:center;color:var(--bs-secondary-color);font-size:13px">ยังไม่มีหัวข้อในระดับนี้</div>
        <?php else: ?>
          <?php foreach ($topics as $i => $t): $tid = (int) $t['id']; $isActive = $topic && (int) $topic['id'] === $tid; ?>
            <div style="border-top:1px solid var(--bs-border-color);padding:11px 14px;<?= $isActive ? 'background:rgba(37,99,235,.06);border-left:3px solid #2563EB' : '' ?>">
              <div style="display:flex;align-items:flex-start;gap:8px">
                <div style="min-width:0;flex:1">
                  <a href="<?= url('admin/lms-topics.php') ?>?level=<?= $levelId ?>&topic=<?= $tid ?>"
                     style="font-weight:600;font-size:13.5px;text-decoration:none;color:inherit;display:block"><?= e($t['title']) ?></a>
                  <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:3px">
                    <?= (int) $t['block_count'] ?> บล็อก ·
                    <span style="color:<?= (int) $t['review_count'] >= 3 ? '#059669' : '#D97706' ?>">ข้อทบทวน <?= (int) $t['review_count'] ?>/3</span>
                    <?php if ((int) $t['is_published'] !== 1): ?> · <span style="color:#DC2626">ไม่เผยแพร่</span><?php endif; ?>
                  </div>
                </div>
              </div>
              <div style="display:flex;gap:4px;margin-top:8px;flex-wrap:wrap">
                <?= $i > 0 ? lt_form('move_topic', $tid, $levelId, $topic ? (int) $topic['id'] : 0, 'action-btn-blue', 'bi-arrow-up', '', ['dir' => 'up']) : '' ?>
                <?= $i < count($topics) - 1 ? lt_form('move_topic', $tid, $levelId, $topic ? (int) $topic['id'] : 0, 'action-btn-blue', 'bi-arrow-down', '', ['dir' => 'down']) : '' ?>
                <button class="action-btn-blue" style="font-size:11.5px" data-bs-toggle="modal" data-bs-target="#editTopicModal"
                        data-id="<?= $tid ?>" data-title="<?= e($t['title']) ?>" data-summary="<?= e((string) ($t['summary'] ?? '')) ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <?= lt_form('toggle_topic', $tid, $levelId, $topic ? (int) $topic['id'] : 0,
                      (int) $t['is_published'] === 1 ? 'action-btn-warn' : 'action-btn-ok',
                      (int) $t['is_published'] === 1 ? 'bi-eye-slash' : 'bi-eye', '') ?>
                <?= lt_form('delete_topic', $tid, $levelId, $topic && (int) $topic['id'] === $tid ? 0 : ($topic ? (int) $topic['id'] : 0),
                      'action-btn-err', 'bi-trash3', '', [], [
                        'title' => 'ลบหัวข้อ',
                        'msg'   => 'ต้องการลบหัวข้อ "' . $t['title'] . '" พร้อมเนื้อหาและข้อทบทวนทั้งหมดใช่หรือไม่?',
                        'icon'  => 'bi-trash3', 'color' => '#DC2626', 'btn' => 'ลบ',
                      ]) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Block editor + preview ── -->
  <div class="col-12 col-lg-8">
    <?php if (!$topic): ?>
      <div class="card" style="border:1px solid var(--bs-border-color)">
        <div class="card-body" style="padding:44px;text-align:center;color:var(--bs-secondary-color)">
          <i class="bi bi-hand-index-thumb" style="font-size:26px;display:block;margin-bottom:10px"></i>
          เลือกหัวข้อทางซ้ายเพื่อแก้ไขเนื้อหาและข้อทบทวน
        </div>
      </div>
    <?php else: $tid = (int) $topic['id']; ?>

      <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
        <div class="card-body" style="padding:0">
          <div style="padding:14px 18px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <span style="font-weight:700;font-size:13.5px">เนื้อหา — <?= e($topic['title']) ?> (<?= count($blocks) ?> บล็อก)</span>
            <button class="btn btn-sm" style="background:#2563EB;border:none;color:white;font-size:12.5px"
                    data-bs-toggle="modal" data-bs-target="#blockModal" data-mode="add">
              <i class="bi bi-plus-lg me-1"></i>เพิ่มบล็อก
            </button>
          </div>

          <?php if (!$blocks): ?>
            <div style="padding:28px;text-align:center;color:var(--bs-secondary-color);font-size:13px">ยังไม่มีบล็อกเนื้อหา</div>
          <?php else: ?>
            <?php foreach ($blocks as $i => $b): $bid = (int) $b['id']; ?>
              <div style="border-top:1px solid var(--bs-border-color);padding:11px 18px;display:flex;align-items:center;gap:12px">
                <span style="flex-shrink:0;font-size:11px;font-weight:700;color:var(--bs-tertiary-color);width:20px"><?= $i + 1 ?></span>
                <span style="flex-shrink:0;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)">
                  <?= e(LmsContent::blockTypeLabel((string) $b['block_type'])) ?>
                </span>
                <span style="flex:1;min-width:0;font-size:12.5px;color:var(--bs-secondary-color);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                  <?php
                    $peek = (string) ($b['text_content'] ?? '');
                    if ($peek === '') { $peek = (string) ($b['image_url'] ?? $b['link_url'] ?? $b['image_file'] ?? ''); }
                    echo e(mb_strimwidth(trim(preg_replace('/\s+/u', ' ', $peek)), 0, 70, '…'));
                  ?>
                </span>
                <span style="display:flex;gap:4px;flex-shrink:0">
                  <?= $i > 0 ? lt_form('move_block', $bid, $levelId, $tid, 'action-btn-blue', 'bi-arrow-up', '', ['dir' => 'up']) : '' ?>
                  <?= $i < count($blocks) - 1 ? lt_form('move_block', $bid, $levelId, $tid, 'action-btn-blue', 'bi-arrow-down', '', ['dir' => 'down']) : '' ?>
                  <button class="action-btn-blue" style="font-size:11.5px" data-bs-toggle="modal" data-bs-target="#blockModal"
                          data-mode="edit" data-id="<?= $bid ?>" data-type="<?= e($b['block_type']) ?>"
                          data-text="<?= e((string) ($b['text_content'] ?? '')) ?>"
                          data-image-url="<?= e((string) ($b['image_url'] ?? '')) ?>"
                          data-image-file="<?= e((string) ($b['image_file'] ?? '')) ?>"
                          data-link-url="<?= e((string) ($b['link_url'] ?? '')) ?>"
                          data-source-url="<?= e((string) ($b['source_url'] ?? '')) ?>"
                          data-source-label="<?= e((string) ($b['source_label'] ?? '')) ?>"
                          data-meta="<?= e((string) ($b['meta'] ?? '')) ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <?= lt_form('delete_block', $bid, $levelId, $tid, 'action-btn-err', 'bi-trash3', '', [], [
                        'title' => 'ลบบล็อก', 'msg' => 'ต้องการลบบล็อกนี้ใช่หรือไม่?',
                        'icon' => 'bi-trash3', 'color' => '#DC2626', 'btn' => 'ลบ',
                      ]) ?>
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Live preview: the same partial the student page renders, so what you see is what they get -->
      <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:16px">
        <div class="card-body" style="padding:0">
          <div style="padding:12px 18px;border-bottom:1px solid var(--bs-border-color);font-weight:700;font-size:13px;color:var(--bs-secondary-color)">
            <i class="bi bi-eye me-1"></i>ตัวอย่างที่นักศึกษาจะเห็น
          </div>
          <div style="padding:18px 22px"><?php require __DIR__ . '/../includes/lms-blocks.php'; ?></div>
        </div>
      </div>

      <!-- Review questions -->
      <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
        <div class="card-body" style="padding:0">
          <div style="padding:14px 18px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <span style="font-weight:700;font-size:13.5px">
              แบบทดสอบทบทวน
              <span style="font-weight:500;color:<?= count($questions) >= 3 ? '#059669' : '#D97706' ?>">(<?= count($questions) ?>/3 ข้อ)</span>
            </span>
            <button class="btn btn-sm" style="background:#059669;border:none;color:white;font-size:12.5px"
                    data-bs-toggle="modal" data-bs-target="#questionModal" data-mode="add">
              <i class="bi bi-plus-lg me-1"></i>เพิ่มคำถาม
            </button>
          </div>
          <?php if (!$questions): ?>
            <div style="padding:24px;text-align:center;color:var(--bs-secondary-color);font-size:13px">
              ยังไม่มีคำถามทบทวน — หัวข้อนี้ควรมี 3 ข้อจึงจะพร้อมเผยแพร่
            </div>
          <?php else: ?>
            <?php foreach ($questions as $i => $q): $qid = (int) $q['id']; $qChoices = LmsQuestion::choices($qid); ?>
              <div style="border-top:1px solid var(--bs-border-color);padding:13px 18px">
                <div style="display:flex;align-items:flex-start;gap:10px">
                  <span style="flex-shrink:0;font-size:11px;font-weight:700;color:var(--bs-tertiary-color);margin-top:2px"><?= $i + 1 ?>.</span>
                  <div style="flex:1;min-width:0">
                    <div style="font-size:13.5px;font-weight:600;line-height:1.6"><?= e($q['question_text']) ?></div>
                    <div style="font-size:12px;color:#059669;margin-top:4px">
                      <i class="bi bi-check-circle me-1"></i><?= e((string) ($q['correct_text'] ?? '—')) ?>
                    </div>
                    <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:3px">
                      <?= (int) $q['choice_count'] ?> ตัวเลือก · ใช้ไปแล้ว <?= (int) $q['used_count'] ?> ครั้ง
                      <?php if ((int) $q['is_active'] !== 1): ?> · <span style="color:#DC2626">ปิดใช้งาน</span><?php endif; ?>
                    </div>
                  </div>
                  <span style="display:flex;gap:4px;flex-shrink:0">
                    <button class="action-btn-blue" style="font-size:11.5px" data-bs-toggle="modal" data-bs-target="#questionModal"
                            data-mode="edit" data-id="<?= $qid ?>" data-text="<?= e($q['question_text']) ?>"
                            data-explanation="<?= e((string) ($q['explanation'] ?? '')) ?>"
                            data-choices="<?= e(json_encode(array_map(fn ($c) => ['t' => $c['choice_text'], 'c' => (int) $c['is_correct']], $qChoices), JSON_UNESCAPED_UNICODE)) ?>">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <?= lt_form('toggle_question', $qid, $levelId, $tid,
                          (int) $q['is_active'] === 1 ? 'action-btn-warn' : 'action-btn-ok',
                          (int) $q['is_active'] === 1 ? 'bi-pause-circle' : 'bi-play-circle', '') ?>
                    <?= lt_form('delete_question', $qid, $levelId, $tid, 'action-btn-err', 'bi-trash3', '', [], [
                          'title' => 'ลบคำถาม', 'msg' => 'ต้องการลบคำถามข้อนี้ใช่หรือไม่?',
                          'icon' => 'bi-trash3', 'color' => '#DC2626', 'btn' => 'ลบ',
                        ]) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    <?php endif; ?>
  </div>
</div>

<!-- Add topic -->
<div class="modal fade" id="addTopicModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add_topic">
        <input type="hidden" name="level_id" value="<?= $levelId ?>">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-plus-circle me-2" style="color:#2563EB"></i>เพิ่มหัวข้อย่อย</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ชื่อหัวข้อ *</label>
          <input type="text" name="title" required class="form-control" style="font-size:13px" placeholder="เช่น AI คืออะไร" autocomplete="off">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin:14px 0 6px">คำโปรย</label>
          <input type="text" name="summary" class="form-control" style="font-size:13px" placeholder="อธิบายสั้น ๆ ว่าหัวข้อนี้สอนอะไร">
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">เพิ่มหัวข้อ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit topic -->
<div class="modal fade" id="editTopicModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save_topic">
        <input type="hidden" name="level_id" value="<?= $levelId ?>">
        <input type="hidden" name="topic_id" value="<?= $topic ? (int) $topic['id'] : 0 ?>">
        <input type="hidden" name="id" id="editTopicId">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-pencil me-2" style="color:#2563EB"></i>แก้ไขหัวข้อ</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ชื่อหัวข้อ *</label>
          <input type="text" name="title" id="editTopicTitle" required class="form-control" style="font-size:13px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin:14px 0 6px">คำโปรย</label>
          <input type="text" name="summary" id="editTopicSummary" class="form-control" style="font-size:13px">
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($topic): $tid = (int) $topic['id']; ?>
<!-- Add / edit content block -->
<div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:600px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save_block">
        <input type="hidden" name="level_id" value="<?= $levelId ?>">
        <input type="hidden" name="topic_id" value="<?= $tid ?>">
        <input type="hidden" name="id" id="blockId" value="">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-layers me-2" style="color:#2563EB"></i><span id="blockModalTitle">เพิ่มบล็อกเนื้อหา</span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ชนิดบล็อก *</label>
          <select name="block_type" id="blockType" class="form-select" style="font-size:13px">
            <?php foreach (LmsContent::BLOCK_TYPES as $bt): ?>
              <option value="<?= e($bt) ?>"><?= e(LmsContent::blockTypeLabel($bt)) ?></option>
            <?php endforeach; ?>
          </select>

          <div data-blk="text" style="margin-top:14px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">
              <span id="blockTextLabel">เนื้อหา</span> <span id="blockTextReq">*</span>
            </label>
            <textarea name="text_content" id="blockText" rows="5" class="form-control" style="font-size:13px"></textarea>
            <div id="blockTextHint" style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px"></div>
          </div>

          <div data-blk="meta-heading" style="margin-top:14px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ขนาดหัวข้อ</label>
            <select name="meta_heading" class="form-select" style="font-size:13px" data-meta-for="heading">
              <option value="h2">ใหญ่ (h2)</option>
              <option value="h3">เล็ก (h3)</option>
            </select>
          </div>

          <div data-blk="meta-list" style="margin-top:14px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">รูปแบบรายการ</label>
            <select name="meta_list" class="form-select" style="font-size:13px" data-meta-for="list">
              <option value="ul">จุดนำ (•)</option>
              <option value="ol">ตัวเลข (1. 2. 3.)</option>
            </select>
          </div>

          <div data-blk="meta-callout" style="margin-top:14px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">โทนของกล่อง</label>
            <select name="meta_callout" class="form-select" style="font-size:13px" data-meta-for="callout">
              <option value="info">ข้อมูล (น้ำเงิน)</option>
              <option value="tip">เคล็ดลับ (เขียว)</option>
              <option value="warn">คำเตือน (ส้ม)</option>
            </select>
          </div>

          <div data-blk="meta-code" style="margin-top:14px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ภาษาโปรแกรม</label>
            <input type="text" name="meta_code" class="form-control" style="font-size:13px" placeholder="เช่น python" data-meta-for="code">
          </div>

          <div data-blk="link" style="margin-top:14px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">
              <span id="blockLinkLabel">ลิงก์</span>
            </label>
            <input type="url" name="link_url" id="blockLinkUrl" class="form-control" style="font-size:13px" placeholder="https://…">
          </div>

          <div data-blk="image" style="margin-top:14px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ลิงก์รูปภาพ (https:// เท่านั้น)</label>
            <input type="url" name="image_url" id="blockImageUrl" class="form-control" style="font-size:13px" placeholder="https://example.com/image.png">
            <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px">
              ฝังรูปจากเว็บต้นทางโดยตรง (ไม่คัดลอกมาเก็บ) — รูป http:// จะถูกเบราว์เซอร์บล็อกบนเว็บจริง
            </div>

            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin:14px 0 6px">หรืออัปโหลดรูปของคุณเอง</label>
            <input type="file" name="image" class="form-control" style="font-size:13px" accept="image/png,image/jpeg,image/gif,image/webp">
            <div id="blockImageCurrent" style="font-size:11px;color:var(--bs-secondary-color);margin-top:4px"></div>

            <div style="background:#FFFBEB;border-left:3px solid #D97706;border-radius:8px;padding:11px 13px;margin-top:14px">
              <div style="font-size:12px;font-weight:700;color:#92400E;margin-bottom:8px">
                <i class="bi bi-c-circle me-1"></i>แหล่งที่มา (บังคับกรอกทุกรูป เพื่อให้ถูกต้องตามลิขสิทธิ์)
              </div>
              <input type="url" name="source_url" id="blockSourceUrl" class="form-control mb-2" style="font-size:13px" placeholder="ลิงก์หน้าต้นทาง เช่น https://openai.com/…">
              <input type="text" name="source_label" id="blockSourceLabel" class="form-control" style="font-size:13px" placeholder="ข้อความเครดิต เช่น ที่มา: OpenAI">
            </div>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">บันทึกบล็อก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add / edit review question -->
<div class="modal fade" id="questionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:600px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save_question">
        <input type="hidden" name="level_id" value="<?= $levelId ?>">
        <input type="hidden" name="topic_id" value="<?= $tid ?>">
        <input type="hidden" name="id" id="questionId" value="">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-patch-question me-2" style="color:#059669"></i><span id="questionModalTitle">เพิ่มคำถามทบทวน</span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <?php require __DIR__ . '/../includes/lms-question-fields.php'; ?>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-sm" style="background:#059669;border:none;color:white">บันทึกคำถาม</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('click', function (e) {
  var et = e.target.closest('[data-bs-target="#editTopicModal"]');
  if (et) {
    document.getElementById('editTopicId').value      = et.dataset.id;
    document.getElementById('editTopicTitle').value   = et.dataset.title;
    document.getElementById('editTopicSummary').value = et.dataset.summary;
  }
});
</script>

<script src="<?= asset('assets/lms-admin.js') ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
