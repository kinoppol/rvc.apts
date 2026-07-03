<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    $name = $_POST['name'] ?? '';
    $provider = $_POST['provider'] ?? '';
    $status = $_POST['status'] ?? 'active';

    if ($action === 'add') {
        $result = AiAccount::add($name, $provider, $status);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'เพิ่มบัญชี AI เรียบร้อยแล้ว' : ($result['error'] ?? 'เพิ่มไม่สำเร็จ'));
    } elseif ($action === 'update') {
        $result = AiAccount::update((int) ($_POST['id'] ?? 0), $name, $provider, $status);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'แก้ไขบัญชี AI เรียบร้อยแล้ว' : ($result['error'] ?? 'แก้ไขไม่สำเร็จ'));
    } elseif ($action === 'delete') {
        $result = AiAccount::delete((int) ($_POST['id'] ?? 0));
        flash_set($result['ok'] ? 'warn' : 'err', $result['ok'] ? 'ลบบัญชี AI เรียบร้อยแล้ว' : ($result['error'] ?? 'ลบไม่สำเร็จ'));
    }
    header('Location: ' . url('admin/ai-accounts.php'));
    exit;
}

$accounts = AiAccount::listWithUsage();
$providers = ['Claude Pro', 'ChatGPT Plus', 'Gemini Advanced'];

$activeNav = 'ai-accounts';
require __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <h5 style="font-weight:700;margin:0">บัญชี AI Account Pool</h5>
  <button type="button" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px" data-bs-toggle="modal" data-bs-target="#addAccountModal"><i class="bi bi-plus-lg me-1"></i>เพิ่มบัญชี AI</button>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
  <?php foreach ($accounts as $ac): ?>
    <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
      <div class="card-body" style="padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <div style="display:flex;align-items:center;gap:10px">
            <div class="stat-icon" style="background:#EFF6FF;width:40px;height:40px"><i class="bi bi-robot" style="color:#2563EB;font-size:17px"></i></div>
            <div>
              <div style="font-weight:700;font-size:14px"><?= e($ac['name']) ?></div>
              <div style="font-size:11px;color:#64748B"><?= e($ac['provider']) ?></div>
            </div>
          </div>
          <span class="<?= $ac['statusCls'] ?>"><?= e($ac['statusLabel']) ?></span>
        </div>
        <div style="font-size:12px;color:#64748B;margin-bottom:6px">ใช้วันนี้: <?= (int) $ac['usedToday'] ?>/<?= (int) $ac['totalSlots'] ?> slots</div>
        <div style="background:var(--bs-border-color);border-radius:4px;height:6px;overflow:hidden">
          <div style="background:#2563EB;width:<?= e($ac['usagePct']) ?>;height:100%;border-radius:4px"></div>
        </div>
        <div style="display:flex;gap:6px;margin-top:12px">
          <button type="button" class="action-btn-blue" style="flex:1;text-align:center"
                  data-edit-account data-id="<?= (int) $ac['id'] ?>" data-name="<?= e($ac['name']) ?>" data-provider="<?= e($ac['provider']) ?>" data-status="<?= e($ac['status']) ?>"><i class="bi bi-pencil me-1"></i>แก้ไข</button>
          <form method="post" style="margin:0" onsubmit="return confirm('ลบบัญชี AI นี้?')">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $ac['id'] ?>">
            <button type="submit" class="action-btn-err"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$accounts): ?>
    <div style="grid-column:1/-1;text-align:center;padding:40px;color:#94A3B8">ยังไม่มีบัญชี AI ในระบบ</div>
  <?php endif; ?>
</div>

<!-- Add account modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-plus-lg me-2" style="color:#2563EB"></i>เพิ่มบัญชี AI</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:12px">
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">ชื่อบัญชี *</label><input name="name" required class="form-control" placeholder="เช่น Claude Pro #3" style="font-size:13px"></div>
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">ประเภท *</label>
            <input name="provider" required list="providerList" class="form-control" placeholder="Claude Pro" style="font-size:13px">
            <datalist id="providerList"><?php foreach ($providers as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist>
          </div>
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">สถานะ</label>
            <select name="status" class="form-select" style="font-size:13px"><option value="active">ใช้งานได้</option><option value="maintenance">บำรุงรักษา</option></select>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">เพิ่มบัญชี</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit account modal (populated by app.js) -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-pencil me-2" style="color:#2563EB"></i>แก้ไขบัญชี AI</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:12px">
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">ชื่อบัญชี *</label><input name="name" required class="form-control" style="font-size:13px"></div>
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">ประเภท *</label>
            <input name="provider" required list="providerList" class="form-control" style="font-size:13px">
          </div>
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">สถานะ</label>
            <select name="status" class="form-select" style="font-size:13px"><option value="active">ใช้งานได้</option><option value="maintenance">บำรุงรักษา</option></select>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">บันทึกการแก้ไข</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
