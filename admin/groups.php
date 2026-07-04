<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = ($_POST['id'] ?? '') !== '' ? (int) $_POST['id'] : null;
        $r = UserGroup::save($_POST, $id);
        flash_set($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'บันทึกกลุ่มเรียบร้อยแล้ว' : ($r['error'] ?? 'บันทึกไม่สำเร็จ'));
    } elseif ($action === 'delete') {
        UserGroup::delete((int) ($_POST['id'] ?? 0));
        flash_set('warn', 'ลบกลุ่มเรียบร้อยแล้ว (สมาชิกในกลุ่มกลับไปใช้ค่าเริ่มต้น)');
    }
    header('Location: ' . url('admin/groups.php'));
    exit;
}

$groups = UserGroup::listWithUsage();
$defaults = SlotSettings::get();
$accounts = AiAccount::allBasic();

$activeNav = 'groups';
require __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <h5 style="font-weight:700;margin:0">จัดการกลุ่มผู้ใช้</h5>
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">ตั้งค่าจำกัดการใช้งานระดับกลุ่ม — สมาชิกจะได้สิทธิ์ตามกลุ่มที่สังกัด</p>
  </div>
  <button type="button" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px" data-add-group data-bs-toggle="modal" data-bs-target="#groupModal"><i class="bi bi-plus-lg me-1"></i>เพิ่มกลุ่ม</button>
</div>

<div style="background:#FFF7ED;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#92400E;display:flex;gap:8px;align-items:flex-start">
  <i class="bi bi-info-circle-fill" style="margin-top:1px;flex-shrink:0"></i>
  <span>ค่าเริ่มต้นของระบบ: โควต้า <strong><?= (int) $defaults['weekly_quota'] ?></strong> รอบ/สัปดาห์ · จองล่วงหน้า <strong><?= (int) $defaults['max_advance_days'] ?></strong> วัน — กลุ่มที่เว้นว่างช่องใดจะใช้ค่านี้</span>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:0;overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="border-bottom:2px solid var(--bs-border-color);background:var(--bs-secondary-bg)">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">กลุ่ม</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">โควต้า/สัปดาห์</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">จองล่วงหน้า</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">Pool ที่จองได้ / พร้อมกัน</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">สมาชิก</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--bs-secondary-color)">การดำเนินการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($groups as $g): $gPools = UserGroup::accountIds((int) $g['id']); ?>
          <tr style="border-bottom:1px solid var(--bs-border-color)">
            <td style="padding:12px 16px">
              <div style="font-weight:600"><?= e($g['name']) ?></div>
              <?php if (!empty($g['description'])): ?><div style="font-size:11px;color:var(--bs-secondary-color);margin-top:1px"><?= e($g['description']) ?></div><?php endif; ?>
            </td>
            <td style="padding:12px 16px"><?= $g['weekly_quota'] !== null ? (int) $g['weekly_quota'] . ' รอบ' : '<span style="color:var(--bs-tertiary-color)">ค่าเริ่มต้น</span>' ?></td>
            <td style="padding:12px 16px"><?= $g['max_advance_days'] !== null ? (int) $g['max_advance_days'] . ' วัน' : '<span style="color:var(--bs-tertiary-color)">ค่าเริ่มต้น</span>' ?></td>
            <td style="padding:12px 16px">
              <?php if ($gPools): ?><?= count($gPools) ?> Pool<?php else: ?><span style="color:#DC2626">ยังไม่ได้กำหนด</span><?php endif; ?>
              <span style="color:var(--bs-tertiary-color)"> · จองพร้อมกัน <?= (int) $g['max_concurrent'] ?></span>
            </td>
            <td style="padding:12px 16px"><?= (int) $g['member_count'] ?> คน</td>
            <td style="padding:12px 16px">
              <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
                <button type="button" class="action-btn-blue" data-edit-group
                        data-id="<?= (int) $g['id'] ?>" data-name="<?= e($g['name']) ?>" data-description="<?= e($g['description'] ?? '') ?>"
                        data-weekly_quota="<?= $g['weekly_quota'] !== null ? (int) $g['weekly_quota'] : '' ?>"
                        data-max_advance_days="<?= $g['max_advance_days'] !== null ? (int) $g['max_advance_days'] : '' ?>"
                        data-max_concurrent="<?= (int) $g['max_concurrent'] ?>"
                        data-pools="<?= e(implode(',', $gPools)) ?>"
                        data-bs-toggle="modal" data-bs-target="#groupModal"><i class="bi bi-pencil me-1"></i>แก้ไข</button>
                <form method="post" style="margin:0" onsubmit="return confirm('ลบกลุ่มนี้? สมาชิกจะกลับไปใช้ค่าเริ่มต้น')">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                  <button type="submit" class="action-btn-err"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$groups): ?>
          <tr><td colspan="6" style="padding:32px;text-align:center;color:var(--bs-tertiary-color)">ยังไม่มีกลุ่ม — กด"เพิ่มกลุ่ม" เพื่อสร้าง</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit group modal (populated by app.js) -->
<div class="modal fade" id="groupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-people me-2" style="color:#2563EB"></i><span id="groupModalTitle">เพิ่มกลุ่ม</span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:12px">
          <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ชื่อกลุ่ม *</label><input name="name" required class="form-control" style="font-size:13px"></div>
          <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">คำอธิบาย</label><input name="description" class="form-control" style="font-size:13px"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">โควต้า/สัปดาห์</label><input type="number" name="weekly_quota" min="1" class="form-control" placeholder="ค่าเริ่มต้น" style="font-size:13px"></div>
            <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">จองล่วงหน้า (วัน)</label><input type="number" name="max_advance_days" min="1" class="form-control" placeholder="ค่าเริ่มต้น" style="font-size:13px"></div>
            <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">จองพร้อมกัน (Pool)</label><input type="number" name="max_concurrent" min="1" value="1" class="form-control" style="font-size:13px"></div>
          </div>
          <div style="font-size:11px;color:var(--bs-tertiary-color)">เว้นว่างช่องโควต้า/จองล่วงหน้าเพื่อใช้ค่าเริ่มต้นของระบบ · "จองพร้อมกัน" = จำนวน Pool ที่จองได้ในช่วงเวลาเดียวกัน</div>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">Pool ที่กลุ่มนี้จองได้ <span style="color:#DC2626">*</span></label>
            <div style="display:flex;flex-direction:column;gap:6px;max-height:180px;overflow-y:auto;border:1px solid var(--bs-border-color);border-radius:8px;padding:10px">
              <?php foreach ($accounts as $ac): ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                  <input type="checkbox" name="pool_ids[]" value="<?= (int) $ac['id'] ?>" class="group-pool-cb">
                  <span style="font-weight:600"><?= e($ac['name']) ?></span>
                  <span style="font-size:11px;color:var(--bs-tertiary-color)"><?= e($ac['provider']) ?></span>
                </label>
              <?php endforeach; ?>
              <?php if (!$accounts): ?><div style="font-size:12px;color:var(--bs-tertiary-color)">ยังไม่มีบัญชี AI ในระบบ</div><?php endif; ?>
            </div>
            <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px">ถ้าไม่เลือก Pool ใดเลย สมาชิกกลุ่มนี้จะยังจองไม่ได้</div>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
