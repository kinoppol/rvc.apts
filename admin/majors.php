<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    $tab    = $_POST['tab'] ?? 'majors';

    if ($action === 'add_major') {
        $result = Major::add($_POST['name'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'เพิ่มสาขาวิชาเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'edit_major') {
        $result = Major::update((int) ($_POST['id'] ?? 0), $_POST['name'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'แก้ไขสาขาวิชาเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'toggle_major') {
        Major::toggleActive((int) ($_POST['id'] ?? 0));
        flash_set('ok', 'อัปเดตสถานะเรียบร้อยแล้ว');
    } elseif ($action === 'delete_major') {
        $result = Major::delete((int) ($_POST['id'] ?? 0));
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ลบสาขาวิชาเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'add_subject') {
        $tab = 'subjects';
        $result = Subject::add($_POST['name'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'เพิ่มวิชาสอนเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'edit_subject') {
        $tab = 'subjects';
        $result = Subject::update((int) ($_POST['id'] ?? 0), $_POST['name'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'แก้ไขวิชาสอนเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    } elseif ($action === 'toggle_subject') {
        $tab = 'subjects';
        Subject::toggleActive((int) ($_POST['id'] ?? 0));
        flash_set('ok', 'อัปเดตสถานะเรียบร้อยแล้ว');
    } elseif ($action === 'delete_subject') {
        $tab = 'subjects';
        $result = Subject::delete((int) ($_POST['id'] ?? 0));
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ลบวิชาสอนเรียบร้อยแล้ว' : ($result['error'] ?? 'เกิดข้อผิดพลาด'));
    }
    header('Location: ' . url('admin/majors.php') . '?tab=' . urlencode($tab));
    exit;
}

$tab     = in_array($_GET['tab'] ?? '', ['majors', 'subjects']) ? ($_GET['tab']) : 'majors';
$majors  = Major::listAll();
$subjects = Subject::listAll();

$activeNav = 'majors';
require __DIR__ . '/../includes/header.php';

/** Helper: POST form for a single-button row action. */
function ms_form(string $action, int $id, string $btnCls, string $icon, string $label, string $tab, ?array $modal = null): string
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
    $csrf = Csrf::field();
    return "<form method='post' style='display:inline;margin:0'>{$csrf}"
        . "<input type='hidden' name='action' value='" . e($action) . "'>"
        . "<input type='hidden' name='id' value='{$id}'>"
        . "<input type='hidden' name='tab' value='" . e($tab) . "'>"
        . "<button type='submit' class='{$btnCls}'{$attrs}><i class='bi {$icon}'></i>" . ($label ? " {$label}" : '') . "</button></form>";
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <h5 style="font-weight:700;margin:0">จัดการสาขาวิชา / วิชาสอน</h5>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--bs-border-color);margin-bottom:20px">
  <a href="<?= url('admin/majors.php') ?>?tab=majors"
     style="text-decoration:none;padding:9px 20px;font-size:13px;font-weight:600;<?= $tab==='majors' ? 'color:#2563EB;border-bottom:2px solid #2563EB;margin-bottom:-2px' : 'color:var(--bs-secondary-color)' ?>">
    <i class="bi bi-mortarboard me-2"></i>สาขาวิชา (นักศึกษา)
  </a>
  <a href="<?= url('admin/majors.php') ?>?tab=subjects"
     style="text-decoration:none;padding:9px 20px;font-size:13px;font-weight:600;<?= $tab==='subjects' ? 'color:#059669;border-bottom:2px solid #059669;margin-bottom:-2px' : 'color:var(--bs-secondary-color)' ?>">
    <i class="bi bi-person-workspace me-2"></i>วิชาสอน (ครูผู้สอน)
  </a>
</div>

<?php if ($tab === 'majors'): ?>
<!-- ── Majors ── -->
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:0">
    <div style="padding:16px 20px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between">
      <span style="font-weight:700;font-size:14px">สาขาวิชา (<?= count($majors) ?> รายการ)</span>
      <button class="btn btn-primary btn-sm" style="background:#2563EB;border:none;font-size:13px" data-bs-toggle="modal" data-bs-target="#addMajorModal">
        <i class="bi bi-plus-lg me-1"></i>เพิ่มสาขาวิชา
      </button>
    </div>
    <?php if (empty($majors)): ?>
      <div style="padding:32px;text-align:center;color:var(--bs-secondary-color)">ยังไม่มีสาขาวิชา</div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:var(--bs-secondary-bg);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--bs-tertiary-color)">
          <th style="padding:10px 20px;text-align:left">ชื่อสาขาวิชา</th>
          <th style="padding:10px 16px;text-align:center">สมาชิก</th>
          <th style="padding:10px 16px;text-align:center">สถานะ</th>
          <th style="padding:10px 16px;text-align:right">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($majors as $mj): ?>
        <tr style="border-top:1px solid var(--bs-border-color)">
          <td style="padding:13px 20px">
            <div style="font-weight:600;font-size:14px"><?= e($mj['name']) ?></div>
          </td>
          <td style="padding:13px 16px;text-align:center">
            <span style="font-size:13px;color:var(--bs-secondary-color)"><?= (int) $mj['user_count'] ?> คน</span>
          </td>
          <td style="padding:13px 16px;text-align:center">
            <?php if ($mj['is_active']): ?>
              <span class="badge-ok">เปิดใช้งาน</span>
            <?php else: ?>
              <span class="badge-susp">ปิดใช้งาน</span>
            <?php endif; ?>
          </td>
          <td style="padding:13px 16px;text-align:right">
            <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">
              <button class="action-btn-blue" style="font-size:12px"
                data-bs-toggle="modal" data-bs-target="#editMajorModal"
                data-id="<?= (int) $mj['id'] ?>" data-name="<?= e($mj['name']) ?>">
                <i class="bi bi-pencil"></i> แก้ไข
              </button>
              <?= ms_form(
                    $mj['is_active'] ? 'toggle_major' : 'toggle_major',
                    (int) $mj['id'],
                    $mj['is_active'] ? 'action-btn-warn' : 'action-btn-ok',
                    $mj['is_active'] ? 'bi-pause-circle' : 'bi-play-circle',
                    $mj['is_active'] ? 'ปิด' : 'เปิด',
                    'majors'
              ) ?>
              <?= ms_form('delete_major', (int) $mj['id'], 'action-btn-err', 'bi-trash3', 'ลบ', 'majors', [
                    'title' => 'ลบสาขาวิชา',
                    'msg'   => 'ต้องการลบสาขาวิชา "' . $mj['name'] . '" ใช่หรือไม่?',
                    'icon'  => 'bi-trash3', 'color' => '#DC2626', 'btn' => 'ลบ',
              ]) ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Add Major Modal -->
<div class="modal fade" id="addMajorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add_major">
        <input type="hidden" name="tab" value="majors">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-mortarboard me-2" style="color:#2563EB"></i>เพิ่มสาขาวิชา</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ชื่อสาขาวิชา *</label>
          <input type="text" name="name" required class="form-control" placeholder="เช่น วิทยาการคอมพิวเตอร์" style="font-size:13px" autocomplete="off">
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">เพิ่มสาขาวิชา</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Major Modal -->
<div class="modal fade" id="editMajorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="edit_major">
        <input type="hidden" name="tab" value="majors">
        <input type="hidden" name="id" id="editMajorId">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-pencil me-2" style="color:#2563EB"></i>แก้ไขสาขาวิชา</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ชื่อสาขาวิชา *</label>
          <input type="text" name="name" id="editMajorName" required class="form-control" style="font-size:13px">
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── Subjects ── -->
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:0">
    <div style="padding:16px 20px;border-bottom:1px solid var(--bs-border-color);display:flex;align-items:center;justify-content:space-between">
      <span style="font-weight:700;font-size:14px">วิชาสอน (<?= count($subjects) ?> รายการ)</span>
      <button class="btn btn-sm" style="background:#059669;border:none;color:white;font-size:13px" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
        <i class="bi bi-plus-lg me-1"></i>เพิ่มวิชาสอน
      </button>
    </div>
    <?php if (empty($subjects)): ?>
      <div style="padding:32px;text-align:center;color:var(--bs-secondary-color)">ยังไม่มีวิชาสอน</div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:var(--bs-secondary-bg);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--bs-tertiary-color)">
          <th style="padding:10px 20px;text-align:left">ชื่อวิชาสอน</th>
          <th style="padding:10px 16px;text-align:center">ครูผู้สอน</th>
          <th style="padding:10px 16px;text-align:center">สถานะ</th>
          <th style="padding:10px 16px;text-align:right">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $sj): ?>
        <tr style="border-top:1px solid var(--bs-border-color)">
          <td style="padding:13px 20px">
            <div style="font-weight:600;font-size:14px"><?= e($sj['name']) ?></div>
          </td>
          <td style="padding:13px 16px;text-align:center">
            <span style="font-size:13px;color:var(--bs-secondary-color)"><?= (int) $sj['user_count'] ?> คน</span>
          </td>
          <td style="padding:13px 16px;text-align:center">
            <?php if ($sj['is_active']): ?>
              <span class="badge-ok">เปิดใช้งาน</span>
            <?php else: ?>
              <span class="badge-susp">ปิดใช้งาน</span>
            <?php endif; ?>
          </td>
          <td style="padding:13px 16px;text-align:right">
            <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">
              <button class="action-btn-blue" style="font-size:12px"
                data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                data-id="<?= (int) $sj['id'] ?>" data-name="<?= e($sj['name']) ?>">
                <i class="bi bi-pencil"></i> แก้ไข
              </button>
              <?= ms_form(
                    'toggle_subject', (int) $sj['id'],
                    $sj['is_active'] ? 'action-btn-warn' : 'action-btn-ok',
                    $sj['is_active'] ? 'bi-pause-circle' : 'bi-play-circle',
                    $sj['is_active'] ? 'ปิด' : 'เปิด',
                    'subjects'
              ) ?>
              <?= ms_form('delete_subject', (int) $sj['id'], 'action-btn-err', 'bi-trash3', 'ลบ', 'subjects', [
                    'title' => 'ลบวิชาสอน',
                    'msg'   => 'ต้องการลบวิชาสอน "' . $sj['name'] . '" ใช่หรือไม่?',
                    'icon'  => 'bi-trash3', 'color' => '#DC2626', 'btn' => 'ลบ',
              ]) ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add_subject">
        <input type="hidden" name="tab" value="subjects">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-person-workspace me-2" style="color:#059669"></i>เพิ่มวิชาสอน</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ชื่อวิชาสอน *</label>
          <input type="text" name="name" required class="form-control" placeholder="เช่น คณิตศาสตร์" style="font-size:13px" autocomplete="off">
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-sm" style="background:#059669;border:none;color:white">เพิ่มวิชาสอน</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="edit_subject">
        <input type="hidden" name="tab" value="subjects">
        <input type="hidden" name="id" id="editSubjectId">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-pencil me-2" style="color:#059669"></i>แก้ไขวิชาสอน</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">ชื่อวิชาสอน *</label>
          <input type="text" name="name" id="editSubjectName" required class="form-control" style="font-size:13px">
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-sm" style="background:#059669;border:none;color:white">บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Populate edit modals from data-* attributes
document.addEventListener('click', function(e) {
  var btn = e.target.closest('[data-bs-target="#editMajorModal"]');
  if (btn) {
    document.getElementById('editMajorId').value   = btn.dataset.id;
    document.getElementById('editMajorName').value = btn.dataset.name;
  }
  var btn2 = e.target.closest('[data-bs-target="#editSubjectModal"]');
  if (btn2) {
    document.getElementById('editSubjectId').value   = btn2.dataset.id;
    document.getElementById('editSubjectName').value = btn2.dataset.name;
  }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
