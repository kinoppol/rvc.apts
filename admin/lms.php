<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'add_level' || $action === 'save_level') {
        $result = LmsLevel::save($_POST, $action === 'save_level' ? $id : null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok']
            ? ($action === 'save_level' ? 'บันทึกระดับเรียบร้อยแล้ว' : 'เพิ่มระดับเรียบร้อยแล้ว')
            : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'toggle_level') {
        LmsLevel::togglePublished($id);
        flash_set('ok', 'อัปเดตสถานะการเผยแพร่เรียบร้อยแล้ว');
    } elseif ($action === 'move_level') {
        LmsLevel::move($id, (string) ($_POST['dir'] ?? ''));
        flash_set('ok', 'จัดลำดับระดับเรียบร้อยแล้ว');
    } elseif ($action === 'save_mission') {
        $groupId = ($_POST['promo_group_id'] ?? '') !== '' ? (int) $_POST['promo_group_id'] : null;
        $result  = LmsLevel::updateMission($id, (string) ($_POST['mission_brief'] ?? ''), $groupId);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกภารกิจเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'run_seed') {
        $result = LmsSeeder::run(!empty($_POST['overwrite']));
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? ($result['summary'] ?? 'นำเข้าเรียบร้อย') : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    }
    header('Location: ' . url('admin/lms.php'));
    exit;
}

$levels     = LmsLevel::ladder();
$topicCount = LmsLevel::topicCounts();
$groups     = UserGroup::all();
$seedPlan   = LmsSeeder::preview();

$readiness = [];
foreach ($levels as $l) {
    $readiness[(int) $l['id']] = LmsLevel::readiness((int) $l['id']);
}

$activeNav = 'lms-content';
require __DIR__ . '/../includes/header.php';

/** Single-button POST form for a row action. */
function lms_form(string $action, int $id, string $btnCls, string $icon, string $label, array $extra = [], ?array $modal = null): string
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
        . "<input type='hidden' name='id' value='{$id}'>{$hidden}"
        . "<button type='submit' class='{$btnCls}'{$attrs}><i class='bi {$icon}'></i>" . ($label !== '' ? " {$label}" : '') . "</button></form>";
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:12px">
  <div>
    <h5 style="font-weight:700;margin:0">จัดการบทเรียน AI</h5>
    <div style="font-size:12.5px;color:var(--bs-secondary-color);margin-top:3px">
      หลักสูตร <?= count($levels) ?> ระดับ — นักศึกษาต้องผ่านแบบทดสอบหลังเรียนตามเกณฑ์จึงจะเรียนระดับถัดไปได้
    </div>
  </div>
  <button class="btn btn-primary btn-sm" style="background:#2563EB;border:none;font-size:13px" data-bs-toggle="modal" data-bs-target="#addLevelModal">
    <i class="bi bi-plus-lg me-1"></i>เพิ่มระดับ
  </button>
</div>

<?php if (!$groups): ?>
  <div class="info-box" style="margin-bottom:16px;background:#FFFBEB;border-left:3px solid #D97706;padding:12px 14px;border-radius:8px;font-size:13px;color:#92400E">
    <i class="bi bi-exclamation-triangle me-2"></i>
    ยังไม่มีกลุ่มผู้ใช้ในระบบ — กรุณาสร้างกลุ่มที่ <a href="<?= url('admin/groups.php') ?>" style="color:#92400E;font-weight:600">จัดการกลุ่มผู้ใช้</a>
    ก่อน จึงจะกำหนดกลุ่มปลายทางของแต่ละระดับได้
  </div>
<?php endif; ?>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:0">
    <?php if (!$levels): ?>
      <div style="padding:40px;text-align:center;color:var(--bs-secondary-color)">
        <i class="bi bi-journal-plus" style="font-size:28px;display:block;margin-bottom:10px"></i>
        ยังไม่มีระดับบทเรียน — กด "เพิ่มระดับ" เพื่อเริ่มสร้างหลักสูตร
      </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;min-width:900px">
      <thead>
        <tr style="background:var(--bs-secondary-bg);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--bs-tertiary-color)">
          <th style="padding:10px 20px;text-align:left">ระดับ</th>
          <th style="padding:10px 12px;text-align:center">หัวข้อ</th>
          <th style="padding:10px 12px;text-align:center">คลังข้อสอบ</th>
          <th style="padding:10px 12px;text-align:center">เกณฑ์ผ่าน</th>
          <th style="padding:10px 12px;text-align:center">กลุ่มปลายทาง</th>
          <th style="padding:10px 12px;text-align:center">สถานะ</th>
          <th style="padding:10px 16px;text-align:right">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($levels as $i => $lv):
            $lid = (int) $lv['id'];
            $rd  = $readiness[$lid];
            $tc  = $topicCount[$lid] ?? ['topics' => 0, 'published' => 0];
        ?>
        <tr style="border-top:1px solid var(--bs-border-color)">
          <td style="padding:13px 20px">
            <div style="display:flex;align-items:center;gap:10px">
              <span style="flex-shrink:0;width:32px;height:32px;border-radius:9px;background:<?= e($lv['accent_color']) ?>1a;display:flex;align-items:center;justify-content:center">
                <i class="bi <?= e($lv['icon']) ?>" style="color:<?= e($lv['accent_color']) ?>;font-size:16px"></i>
              </span>
              <div style="min-width:0">
                <div style="font-weight:600;font-size:14px"><?= e($lv['title']) ?></div>
                <?php if (!empty($lv['subtitle'])): ?>
                  <div style="font-size:12px;color:var(--bs-secondary-color)"><?= e($lv['subtitle']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="padding:13px 12px;text-align:center;font-size:13px">
            <a href="<?= url('admin/lms-topics.php') ?>?level=<?= $lid ?>" style="color:#2563EB;text-decoration:none;font-weight:600">
              <?= (int) $tc['published'] ?>/<?= (int) $tc['topics'] ?>
            </a>
            <?php if ($rd['topicsMissingReview']): ?>
              <div style="font-size:10.5px;color:#D97706;margin-top:2px" title="<?= e(implode(', ', $rd['topicsMissingReview'])) ?>">
                <i class="bi bi-exclamation-triangle"></i> <?= count($rd['topicsMissingReview']) ?> หัวข้อยังไม่ครบ 3 ข้อ
              </div>
            <?php endif; ?>
          </td>
          <td style="padding:13px 12px;text-align:center;font-size:12.5px;white-space:nowrap">
            <a href="<?= url('admin/lms-questions.php') ?>?level=<?= $lid ?>&phase=pre"
               style="text-decoration:none;color:<?= $rd['preOk'] ? '#059669' : '#DC2626' ?>;font-weight:600">
              ก่อน <?= (int) $rd['preCount'] ?>/<?= (int) $lv['pre_question_count'] ?>
            </a>
            <span style="color:var(--bs-tertiary-color);margin:0 4px">·</span>
            <a href="<?= url('admin/lms-questions.php') ?>?level=<?= $lid ?>&phase=post"
               style="text-decoration:none;color:<?= $rd['postOk'] ? '#059669' : '#DC2626' ?>;font-weight:600">
              หลัง <?= (int) $rd['postCount'] ?>/<?= (int) $lv['post_question_count'] ?>
            </a>
            <?php if ($rd['duplicateTexts']): ?>
              <div style="font-size:10.5px;color:#D97706;margin-top:2px">
                <i class="bi bi-files"></i> มีโจทย์ซ้ำกัน <?= count($rd['duplicateTexts']) ?> ข้อ
              </div>
            <?php endif; ?>
          </td>
          <td style="padding:13px 12px;text-align:center;font-size:13px;font-weight:600"><?= (int) $lv['pass_percent'] ?>%</td>
          <td style="padding:13px 12px;text-align:center;font-size:12.5px">
            <?php
              $gName = null;
              foreach ($groups as $g) {
                  if ((int) $g['id'] === (int) $lv['promo_group_id']) { $gName = $g['name']; }
              }
            ?>
            <?php if ($gName !== null): ?>
              <span class="badge-ok"><?= e($gName) ?></span>
            <?php else: ?>
              <span style="color:var(--bs-tertiary-color)">— ยังไม่กำหนด —</span>
            <?php endif; ?>
          </td>
          <td style="padding:13px 12px;text-align:center">
            <?php if ((int) $lv['is_published'] === 1): ?>
              <span class="badge-ok">เผยแพร่</span>
            <?php else: ?>
              <span class="badge-susp">ร่าง</span>
            <?php endif; ?>
          </td>
          <td style="padding:13px 16px;text-align:right">
            <div style="display:flex;gap:5px;justify-content:flex-end;align-items:center;flex-wrap:wrap">
              <?= $i > 0 ? lms_form('move_level', $lid, 'action-btn-blue', 'bi-arrow-up', '', ['dir' => 'up']) : '' ?>
              <?= $i < count($levels) - 1 ? lms_form('move_level', $lid, 'action-btn-blue', 'bi-arrow-down', '', ['dir' => 'down']) : '' ?>
              <a href="<?= url('admin/lms-topics.php') ?>?level=<?= $lid ?>" class="action-btn-blue" style="font-size:12px;text-decoration:none">
                <i class="bi bi-list-ul"></i> หัวข้อ
              </a>
              <button class="action-btn-blue" style="font-size:12px" data-bs-toggle="modal" data-bs-target="#missionModal"
                      data-id="<?= $lid ?>" data-title="<?= e($lv['title']) ?>"
                      data-brief="<?= e((string) ($lv['mission_brief'] ?? '')) ?>"
                      data-group="<?= (int) ($lv['promo_group_id'] ?? 0) ?>">
                <i class="bi bi-patch-check"></i> ภารกิจ
              </button>
              <button class="action-btn-blue" style="font-size:12px" data-bs-toggle="modal" data-bs-target="#editLevelModal"
                      data-id="<?= $lid ?>" data-title="<?= e($lv['title']) ?>"
                      data-subtitle="<?= e((string) ($lv['subtitle'] ?? '')) ?>"
                      data-description="<?= e((string) ($lv['description'] ?? '')) ?>"
                      data-icon="<?= e($lv['icon']) ?>" data-color="<?= e($lv['accent_color']) ?>"
                      data-pass="<?= (int) $lv['pass_percent'] ?>"
                      data-pre="<?= (int) $lv['pre_question_count'] ?>"
                      data-post="<?= (int) $lv['post_question_count'] ?>"
                      data-review="<?= (int) $lv['review_pass_correct'] ?>">
                <i class="bi bi-pencil"></i> แก้ไข
              </button>
              <?php
                $pubModal = (int) $lv['is_published'] === 1 ? [
                    'title' => 'ปิดเผยแพร่ระดับ',
                    'msg'   => 'นักศึกษาจะไม่เห็นระดับ "' . $lv['title'] . '" และจะทำแบบทดสอบของระดับนี้ไม่ได้ ต้องการดำเนินการต่อหรือไม่?',
                    'icon'  => 'bi-eye-slash', 'color' => '#D97706', 'btn' => 'ปิดเผยแพร่',
                ] : null;
              ?>
              <?= lms_form('toggle_level', $lid,
                    (int) $lv['is_published'] === 1 ? 'action-btn-warn' : 'action-btn-ok',
                    (int) $lv['is_published'] === 1 ? 'bi-eye-slash' : 'bi-eye',
                    (int) $lv['is_published'] === 1 ? 'ปิด' : 'เผยแพร่', [], $pubModal) ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($seedPlan): ?>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-top:16px">
  <div class="card-body" style="padding:0">
    <div style="padding:15px 20px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div style="min-width:0">
        <span style="font-weight:700;font-size:14px"><i class="bi bi-download me-2" style="color:#2563EB"></i>นำเข้าหลักสูตรตัวอย่างที่มากับระบบ</span>
        <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:3px">
          เนื้อหาภาษาไทยจาก <code>database/seed_lms/</code> — นำเข้าซ้ำได้ ระบบจะอัปเดตของเดิมแทนการสร้างซ้ำ
        </div>
      </div>
      <button class="btn btn-sm" style="background:#2563EB;border:none;color:white;font-size:12.5px"
              data-bs-toggle="modal" data-bs-target="#seedModal">
        <i class="bi bi-box-arrow-in-down me-1"></i>นำเข้าเนื้อหา
      </button>
    </div>
    <div style="padding:12px 20px;display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($seedPlan as $p): ?>
        <span style="font-size:11.5px;padding:4px 11px;border-radius:20px;background:var(--bs-secondary-bg);color:var(--bs-secondary-color)">
          <?= e($p['title']) ?> — <?= e($p['state']) ?> · <?= (int) $p['topics'] ?> หัวข้อ · <?= (int) $p['questions'] ?> คำถาม
        </span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="modal fade" id="seedModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="run_seed">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-box-arrow-in-down me-2" style="color:#2563EB"></i>นำเข้าหลักสูตรตัวอย่าง</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <div style="font-size:13px;color:var(--bs-secondary-color);margin-bottom:12px">ระบบจะดำเนินการดังนี้:</div>
          <ul style="font-size:13px;line-height:1.9;padding-left:20px;margin:0">
            <?php foreach ($seedPlan as $p): ?>
              <li>
                <strong><?= e($p['title']) ?></strong> — <?= e($p['state']) ?>
                (<?= (int) $p['topics'] ?> หัวข้อ, <?= (int) $p['questions'] ?> คำถาม)
                <?php if ($p['blocksSkipped'] > 0): ?>
                  <br><span style="color:#D97706;font-size:12px">ข้ามเนื้อหาของ <?= (int) $p['blocksSkipped'] ?> หัวข้อที่มีบล็อกอยู่แล้ว</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>

          <div style="background:var(--bs-secondary-bg);border-radius:9px;padding:12px 14px;margin-top:14px">
            <label style="display:flex;align-items:flex-start;gap:9px;cursor:pointer;font-size:13px;line-height:1.7;margin:0">
              <input type="checkbox" name="overwrite" value="1" class="form-check-input" style="flex-shrink:0;margin:2px 0 0">
              <span>
                <strong>เขียนทับเนื้อหาเดิม</strong><br>
                <span style="color:var(--bs-secondary-color);font-size:12.5px">
                  ปกติระบบจะข้ามหัวข้อที่มีบล็อกเนื้อหาอยู่แล้ว เพื่อไม่ให้ทับงานที่ผู้ดูแลแก้ไขเอง
                  ติ๊กช่องนี้เพื่อลบบล็อกเดิมแล้วเขียนใหม่จากไฟล์ตัวอย่าง
                </span>
              </span>
            </label>
          </div>

          <div style="font-size:12px;color:var(--bs-tertiary-color);margin-top:12px;line-height:1.7">
            <i class="bi bi-info-circle me-1"></i>
            การนำเข้าจะไม่เปลี่ยนสถานะการเผยแพร่และกลุ่มปลายทางที่คุณตั้งไว้
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">เริ่มนำเข้า</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
/** The shared field set for the add/edit level modals. */
function lms_level_fields(string $prefix, bool $withSlug): void
{
    ?>
    <div class="row g-3">
      <?php if ($withSlug): ?>
      <div class="col-12">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">รหัสอ้างอิง (slug)</label>
        <input type="text" name="slug" class="form-control" style="font-size:13px" placeholder="เช่น level-1" autocomplete="off">
        <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px">เว้นว่างได้ — ใช้สำหรับการนำเข้าเนื้อหาตัวอย่างเท่านั้น</div>
      </div>
      <?php endif; ?>
      <div class="col-12">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ชื่อระดับ *</label>
        <input type="text" name="title" id="<?= $prefix ?>Title" required class="form-control" style="font-size:13px" placeholder="เช่น ระดับเริ่มต้น" autocomplete="off">
      </div>
      <div class="col-12">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">คำอธิบายสั้น</label>
        <input type="text" name="subtitle" id="<?= $prefix ?>Subtitle" class="form-control" style="font-size:13px" placeholder="เช่น รู้จัก AI และสมัครใช้งาน">
      </div>
      <div class="col-12">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">รายละเอียด</label>
        <textarea name="description" id="<?= $prefix ?>Description" rows="2" class="form-control" style="font-size:13px"></textarea>
      </div>
      <div class="col-6">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ไอคอน (Bootstrap Icons)</label>
        <input type="text" name="icon" id="<?= $prefix ?>Icon" class="form-control" style="font-size:13px" placeholder="bi-mortarboard" value="bi-mortarboard">
      </div>
      <div class="col-6">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">สีประจำระดับ</label>
        <input type="color" name="accent_color" id="<?= $prefix ?>Color" class="form-control form-control-color" style="width:100%;height:38px" value="#2563EB">
      </div>
      <div class="col-6">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">เกณฑ์ผ่านหลังเรียน (%)</label>
        <input type="number" name="pass_percent" id="<?= $prefix ?>Pass" min="1" max="100" required class="form-control" style="font-size:13px" value="80">
      </div>
      <div class="col-6">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">เกณฑ์ผ่านทบทวน (ข้อ จาก 3)</label>
        <input type="number" name="review_pass_correct" id="<?= $prefix ?>Review" min="1" max="3" required class="form-control" style="font-size:13px" value="2">
      </div>
      <div class="col-6">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">จำนวนข้อ — ก่อนเรียน</label>
        <input type="number" name="pre_question_count" id="<?= $prefix ?>Pre" min="1" max="50" required class="form-control" style="font-size:13px" value="10">
      </div>
      <div class="col-6">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">จำนวนข้อ — หลังเรียน</label>
        <input type="number" name="post_question_count" id="<?= $prefix ?>Post" min="1" max="50" required class="form-control" style="font-size:13px" value="10">
      </div>
      <div class="col-12">
        <div style="font-size:11.5px;color:var(--bs-tertiary-color);line-height:1.7">
          <i class="bi bi-info-circle me-1"></i>
          ระบบจะสุ่มข้อสอบตามจำนวนนี้จากคลังของระดับ พร้อมสลับลำดับข้อและลำดับตัวเลือกใหม่ทุกครั้งที่ทำ
          จึงควรมีคำถามในคลังมากกว่าจำนวนที่สุ่มออก
        </div>
      </div>
    </div>
    <?php
}
?>

<!-- Add level -->
<div class="modal fade" id="addLevelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:560px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add_level">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-journal-plus me-2" style="color:#2563EB"></i>เพิ่มระดับบทเรียน</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px"><?php lms_level_fields('add', true); ?></div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">เพิ่มระดับ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit level -->
<div class="modal fade" id="editLevelModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:560px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save_level">
        <input type="hidden" name="id" id="editLevelId">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-pencil me-2" style="color:#2563EB"></i>แก้ไขระดับ</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px"><?php lms_level_fields('edit', false); ?></div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Mission + target group -->
<div class="modal fade" id="missionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:560px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save_mission">
        <input type="hidden" name="id" id="missionLevelId">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-patch-check me-2" style="color:#059669"></i>ภารกิจพิสูจน์ทักษะ — <span id="missionLevelTitle"></span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">โจทย์ภารกิจที่นักศึกษาต้องทำส่ง</label>
          <textarea name="mission_brief" id="missionBrief" rows="6" class="form-control" style="font-size:13px"
                    placeholder="เช่น ส่งตัวอย่างงานที่ใช้ AI ช่วยทำ 1 ชิ้น พร้อมอธิบายว่าใช้ prompt อะไร และปรับแก้ผลลัพธ์อย่างไร"></textarea>

          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin:16px 0 6px">กลุ่มปลายทางเมื่อภารกิจผ่าน</label>
          <select name="promo_group_id" id="missionGroup" class="form-select" style="font-size:13px">
            <option value="">— ยังไม่กำหนด (ปิดการขอเลื่อนระดับ) —</option>
            <?php foreach ($groups as $g): ?>
              <option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option>
            <?php endforeach; ?>
          </select>

          <div style="margin-top:14px;background:#FFFBEB;border-left:3px solid #D97706;border-radius:8px;padding:11px 13px;font-size:12.5px;line-height:1.75;color:#92400E">
            <i class="bi bi-exclamation-triangle me-1"></i>
            เมื่อผู้ดูแลอนุมัติภารกิจ ระบบจะย้ายผู้เรียนเข้ากลุ่มนี้ทันที ซึ่ง<strong>เปลี่ยนสิทธิ์การจอง AI</strong>
            (โควตา/สัปดาห์, จองล่วงหน้า, จำนวน Pool พร้อมกัน และ Pool ที่เข้าถึงได้) ตามการตั้งค่าของกลุ่ม
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-sm" style="background:#059669;border:none;color:white">บันทึกภารกิจ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Populate the edit / mission modals from the clicked row's data-* attributes.
document.addEventListener('click', function (e) {
  var edit = e.target.closest('[data-bs-target="#editLevelModal"]');
  if (edit) {
    var d = edit.dataset;
    document.getElementById('editLevelId').value      = d.id;
    document.getElementById('editTitle').value        = d.title;
    document.getElementById('editSubtitle').value     = d.subtitle;
    document.getElementById('editDescription').value  = d.description;
    document.getElementById('editIcon').value         = d.icon;
    document.getElementById('editColor').value        = d.color;
    document.getElementById('editPass').value         = d.pass;
    document.getElementById('editPre').value          = d.pre;
    document.getElementById('editPost').value         = d.post;
    document.getElementById('editReview').value       = d.review;
  }
  var mission = e.target.closest('[data-bs-target="#missionModal"]');
  if (mission) {
    var m = mission.dataset;
    document.getElementById('missionLevelId').value    = m.id;
    document.getElementById('missionLevelTitle').textContent = m.title;
    document.getElementById('missionBrief').value      = m.brief;
    document.getElementById('missionGroup').value      = m.group === '0' ? '' : m.group;
  }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
