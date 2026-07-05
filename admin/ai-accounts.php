<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $r = AiAccount::add($_POST);
        flash_set($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'เพิ่มบัญชี AI เรียบร้อยแล้ว' : ($r['error'] ?? 'เพิ่มไม่สำเร็จ'));
    } elseif ($action === 'update') {
        $r = AiAccount::update((int) ($_POST['id'] ?? 0), $_POST);
        flash_set($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'แก้ไขบัญชี AI เรียบร้อยแล้ว' : ($r['error'] ?? 'แก้ไขไม่สำเร็จ'));
    } elseif ($action === 'delete') {
        $r = AiAccount::delete((int) ($_POST['id'] ?? 0));
        flash_set($r['ok'] ? 'warn' : 'err', $r['ok'] ? 'ลบบัญชี AI เรียบร้อยแล้ว' : ($r['error'] ?? 'ลบไม่สำเร็จ'));
    } elseif ($action === 'change_password') {
        $r = AiAccount::updatePassword((int) ($_POST['id'] ?? 0), $_POST['new_password'] ?? '');
        flash_set($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'เปลี่ยนรหัสผ่านบัญชี AI เรียบร้อยแล้ว' : ($r['error'] ?? 'เปลี่ยนรหัสผ่านไม่สำเร็จ'));
    } elseif ($action === 'type_add') {
        $r = AiProvider::add($_POST['type_name'] ?? '');
        flash_set($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'เพิ่มประเภทเรียบร้อยแล้ว' : ($r['error'] ?? 'เพิ่มประเภทไม่สำเร็จ'));
    } elseif ($action === 'type_rename') {
        $r = AiProvider::rename((int) ($_POST['type_id'] ?? 0), $_POST['type_name'] ?? '');
        flash_set($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'แก้ไขประเภทเรียบร้อยแล้ว' : ($r['error'] ?? 'แก้ไขไม่สำเร็จ'));
    } elseif ($action === 'type_delete') {
        $r = AiProvider::delete((int) ($_POST['type_id'] ?? 0));
        flash_set($r['ok'] ? 'warn' : 'err', $r['ok'] ? 'ลบประเภทเรียบร้อยแล้ว' : ($r['error'] ?? 'ลบไม่สำเร็จ'));
    }
    header('Location: ' . url('admin/ai-accounts.php'));
    exit;
}

$accounts = AiAccount::listWithUsage();
$providers = AiProvider::all();
$typeRows = AiProvider::listWithUsage();

/** Options for the type <select>, marking $selectedId as selected. */
function provider_options(array $providers, int $selectedId = 0): string
{
    $out = '';
    foreach ($providers as $p) {
        $sel = (int) $p['id'] === $selectedId ? ' selected' : '';
        $out .= '<option value="' . (int) $p['id'] . '"' . $sel . '>' . e($p['name']) . '</option>';
    }
    return $out;
}

$reminderOpts = ['none' => 'ปิดการแจ้งเตือน', 'daily' => 'ทุกวัน', 'weekly' => 'ทุกสัปดาห์', 'monthly' => 'ทุกเดือน'];

$activeNav = 'ai-accounts';
require __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <h5 style="font-weight:700;margin:0">บัญชี AI Account Pool</h5>
  <div style="display:flex;gap:8px">
    <button type="button" class="btn btn-outline-secondary" style="font-size:13px" data-bs-toggle="modal" data-bs-target="#manageTypesModal"><i class="bi bi-tags me-1"></i>จัดการประเภท</button>
    <button type="button" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px" data-bs-toggle="modal" data-bs-target="#addAccountModal"><i class="bi bi-plus-lg me-1"></i>เพิ่มบัญชี AI</button>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px">
  <?php foreach ($accounts as $ac): ?>
    <?php $expiresInput = !empty($ac['expires_at']) ? date('Y-m-d\TH:i', strtotime($ac['expires_at'])) : ''; ?>
    <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)<?= $ac['isExpired'] ? ';opacity:.85' : '' ?>">
      <div class="card-body" style="padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <div style="display:flex;align-items:center;gap:10px">
            <div class="stat-icon" style="background:#EFF6FF;width:40px;height:40px"><i class="bi bi-robot" style="color:#2563EB;font-size:17px"></i></div>
            <div>
              <div style="font-weight:700;font-size:14px"><?= e($ac['name']) ?></div>
              <div style="font-size:11px;color:var(--bs-secondary-color)"><?= e($ac['provider']) ?></div>
            </div>
          </div>
          <span class="<?= $ac['statusCls'] ?>"><?= e($ac['statusLabel']) ?></span>
        </div>

        <!-- Credentials -->
        <div style="background:var(--bs-secondary-bg);border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:12px">
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;color:var(--bs-secondary-color)">
            <i class="bi bi-envelope" style="width:14px"></i>
            <span style="color:var(--bs-body-color);word-break:break-all"><?= e($ac['email'] ?: '—') ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:6px;color:var(--bs-secondary-color)">
            <i class="bi bi-key" style="width:14px"></i>
            <?php if (!empty($ac['account_password'])): ?>
              <input type="password" class="pw-field" value="<?= e($ac['account_password']) ?>" readonly
                     style="border:none;background:transparent;padding:0;font-size:12px;color:var(--bs-body-color);flex:1;min-width:0;font-family:monospace">
              <button type="button" class="pw-toggle" title="แสดง/ซ่อนรหัสผ่าน" style="border:none;background:none;cursor:pointer;color:var(--bs-secondary-color);padding:0"><i class="bi bi-eye"></i></button>
            <?php else: ?>
              <span>—</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Usage today -->
        <div style="font-size:12px;color:var(--bs-secondary-color);margin-bottom:6px">ใช้วันนี้: <?= (int) $ac['usedToday'] ?>/<?= (int) $ac['totalSlots'] ?> slots</div>
        <div style="background:var(--bs-border-color);border-radius:4px;height:6px;overflow:hidden;margin-bottom:12px">
          <div style="background:#2563EB;width:<?= e($ac['usagePct']) ?>;height:100%;border-radius:4px"></div>
        </div>

        <!-- Expiry + password reminder + cost -->
        <div style="display:flex;flex-direction:column;gap:7px;font-size:12px;margin-bottom:14px">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <span style="color:var(--bs-secondary-color)"><i class="bi bi-calendar-x me-1"></i>หมดอายุ</span>
            <span style="text-align:right;<?= $ac['expiryWarn'] ? 'color:#DC2626;font-weight:600' : 'color:var(--bs-body-color)' ?>">
              <?= e($ac['expiresLabel']) ?><?php if (!empty($ac['expires_at'])): ?> · <?= e($ac['expiryText']) ?><?php endif; ?>
            </span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <span style="color:var(--bs-secondary-color)"><i class="bi bi-shield-lock me-1"></i>เตือนเปลี่ยนรหัส</span>
            <span style="text-align:right;<?= $ac['pwdWarn'] ? 'color:#D97706;font-weight:600' : 'color:var(--bs-body-color)' ?>">
              <?php if ($ac['pwdReminderOn']): ?><?= e($ac['reminderLabel']) ?> · <?= e($ac['pwdText']) ?><?php else: ?>ไม่แจ้งเตือน<?php endif; ?>
            </span>
          </div>
          <?php if ($ac['monthly_cost'] !== null || $ac['cost_per_slot'] !== null): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <span style="color:var(--bs-secondary-color)"><i class="bi bi-cash-coin me-1"></i>ต้นทุน</span>
            <span style="text-align:right;color:var(--bs-body-color)">
              <?php
                $parts = [];
                if ($ac['monthly_cost'] !== null) $parts[] = '฿' . number_format((float)$ac['monthly_cost'], 2) . '/เดือน';
                if ($ac['cost_per_slot'] !== null) $parts[] = '฿' . number_format((float)$ac['cost_per_slot'], 2) . '/slot';
                echo implode(' · ', $parts);
              ?>
            </span>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($ac['pwdWarn'])): ?>
          <button type="button" style="width:100%;margin-bottom:8px;background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;border-radius:8px;padding:7px;font-size:12px;font-weight:600;cursor:pointer"
                  data-change-pw data-id="<?= (int) $ac['id'] ?>" data-name="<?= e($ac['name']) ?>">
            <i class="bi bi-shield-lock me-1"></i>เปลี่ยนรหัสผ่านตอนนี้ (<?= e($ac['pwdText']) ?>)
          </button>
        <?php endif; ?>
        <div style="display:flex;gap:6px">
          <button type="button" class="action-btn-blue" style="flex:1;text-align:center"
                  data-edit-account
                  data-id="<?= (int) $ac['id'] ?>"
                  data-name="<?= e($ac['name']) ?>"
                  data-provider-id="<?= (int) ($ac['provider_id'] ?? 0) ?>"
                  data-email="<?= e($ac['email'] ?? '') ?>"
                  data-password="<?= e($ac['account_password'] ?? '') ?>"
                  data-status="<?= e($ac['status']) ?>"
                  data-expires="<?= e($expiresInput) ?>"
                  data-reminder="<?= e($ac['password_reminder']) ?>"
                  data-monthly-cost="<?= $ac['monthly_cost'] !== null ? (float)$ac['monthly_cost'] : '' ?>"
                  data-cost-per-slot="<?= $ac['cost_per_slot'] !== null ? (float)$ac['cost_per_slot'] : '' ?>"><i class="bi bi-pencil me-1"></i>แก้ไข</button>
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
    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--bs-tertiary-color)">ยังไม่มีบัญชี AI ในระบบ</div>
  <?php endif; ?>
</div>

<?php
/** Shared account form body (add & edit). $prefix keeps element ids unique between the two modals. */
function account_form_fields(array $providers, array $reminderOpts, string $prefix): void
{
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div style="grid-column:span 2"><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ชื่อบัญชี *</label><input name="name" required class="form-control" placeholder="เช่น Claude Pro #3" style="font-size:13px"></div>
      <div style="grid-column:span 2"><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ประเภท *</label>
        <select name="provider_id" required class="form-select" style="font-size:13px">
          <?php if (!$providers): ?><option value="">— ยังไม่มีประเภท กรุณาเพิ่มก่อน —</option><?php endif; ?>
          <?= provider_options($providers) ?>
        </select>
      </div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">อีเมลบัญชี</label><input type="email" name="email" class="form-control" placeholder="account@example.com" style="font-size:13px"></div>
      <div>
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">รหัสผ่านบัญชี</label>
        <div style="display:flex;gap:6px">
          <input name="account_password" id="<?= e($prefix) ?>_account_password" class="form-control" placeholder="<?= $prefix === 'edit' ? 'เว้นว่างไว้หากไม่เปลี่ยน' : 'รหัสผ่านสำหรับใช้ร่วมกัน' ?>" style="font-size:13px;font-family:monospace">
          <button type="button" class="btn btn-outline-secondary" data-pw-generate="#<?= e($prefix) ?>_account_password" title="สุ่มรหัสผ่าน"><i class="bi bi-shuffle"></i></button>
          <button type="button" class="btn btn-outline-secondary" data-pw-copy="#<?= e($prefix) ?>_account_password" title="คัดลอก"><i class="bi bi-clipboard"></i></button>
        </div>
      </div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">วันเวลาหมดอายุ</label><input type="datetime-local" name="expires_at" class="form-control" style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">สถานะ</label>
        <select name="status" class="form-select" style="font-size:13px"><option value="active">ใช้งานได้</option><option value="maintenance">บำรุงรักษา</option></select>
      </div>
      <div style="grid-column:span 2"><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">แจ้งเตือนให้เปลี่ยนรหัสผ่าน</label>
        <select name="password_reminder" class="form-select" style="font-size:13px">
          <?php foreach ($reminderOpts as $val => $lbl): ?><option value="<?= e($val) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div style="grid-column:span 2;border-top:1px solid var(--bs-border-color);padding-top:12px;margin-top:2px">
        <div style="font-size:11px;font-weight:700;color:var(--bs-secondary-color);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px"><i class="bi bi-cash-coin me-1"></i>ต้นทุน (ไม่บังคับ)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ค่าใช้จ่าย/เดือน (บาท)</label><input type="number" name="monthly_cost" min="0" step="0.01" class="form-control" placeholder="เช่น 800" style="font-size:13px"></div>
          <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ค่าต่อ 1 ช่วงเวลา (บาท)</label><input type="number" name="cost_per_slot" min="0" step="0.01" class="form-control" placeholder="เช่น 50" style="font-size:13px"></div>
        </div>
      </div>
    </div>
    <?php
}
?>

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
        <div class="modal-body" style="padding:20px"><?php account_form_fields($providers, $reminderOpts, 'add'); ?></div>
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
        <div class="modal-body" style="padding:20px"><?php account_form_fields($providers, $reminderOpts, 'edit'); ?></div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">บันทึกการแก้ไข</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Manage AI types modal -->
<div class="modal fade" id="manageTypesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
        <h6 class="modal-title" style="font-weight:700"><i class="bi bi-tags me-2" style="color:#2563EB"></i>จัดการประเภท AI</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body" style="padding:20px">
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px">
          <?php foreach ($typeRows as $t): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid var(--bs-border-color);border-radius:8px">
              <form method="post" style="display:flex;gap:6px;flex:1;margin:0">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="type_rename">
                <input type="hidden" name="type_id" value="<?= (int) $t['id'] ?>">
                <input name="type_name" value="<?= e($t['name']) ?>" class="form-control form-control-sm" style="font-size:13px">
                <button type="submit" class="btn btn-sm btn-outline-primary" style="font-size:12px;white-space:nowrap" title="บันทึกชื่อ"><i class="bi bi-check-lg"></i></button>
              </form>
              <span style="font-size:11px;color:var(--bs-tertiary-color);white-space:nowrap"><?= (int) $t['usage'] ?> บัญชี</span>
              <form method="post" style="margin:0" onsubmit="return confirm('ลบประเภทนี้?')">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="type_delete">
                <input type="hidden" name="type_id" value="<?= (int) $t['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:12px" <?= $t['usage'] > 0 ? 'disabled title="มีบัญชีใช้อยู่ ลบไม่ได้"' : '' ?>><i class="bi bi-trash"></i></button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if (!$typeRows): ?>
            <div style="text-align:center;color:var(--bs-tertiary-color);font-size:13px;padding:12px">ยังไม่มีประเภท</div>
          <?php endif; ?>
        </div>
        <form method="post" style="display:flex;gap:8px;border-top:1px solid var(--bs-border-color);padding-top:16px">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="type_add">
          <input name="type_name" required class="form-control" placeholder="เพิ่มประเภทใหม่ เช่น Google Gemini Advanced" style="font-size:13px">
          <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px;white-space:nowrap"><i class="bi bi-plus-lg me-1"></i>เพิ่ม</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Change AI-account password modal: JS auto-generates a random password (populated by app.js) -->
<div class="modal fade" id="changePwModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="id" id="changePwId">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-shield-lock me-2" style="color:#2563EB"></i>เปลี่ยนรหัสผ่าน — <span id="changePwAccountName"></span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <p style="font-size:13px;color:var(--bs-secondary-color);margin:0 0 14px">ระบบสร้างรหัสผ่านใหม่แบบสุ่มให้ — คัดลอกไปแจ้งผู้ใช้งานบัญชีนี้ แล้วกด "บันทึกรหัสผ่านนี้" เพื่อยืนยัน</p>
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">รหัสผ่านใหม่</label>
          <div style="display:flex;gap:6px">
            <input type="text" name="new_password" id="changePwValue" readonly class="form-control" style="font-family:monospace;font-size:15px;font-weight:700;letter-spacing:.5px">
            <button type="button" id="changePwCopyBtn" class="btn btn-outline-secondary" title="คัดลอก"><i class="bi bi-clipboard"></i></button>
            <button type="button" id="changePwRegenBtn" class="btn btn-outline-secondary" title="สุ่มใหม่"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
          <div id="changePwCopiedHint" style="font-size:11px;color:#059669;margin-top:6px;display:none"><i class="bi bi-check-circle me-1"></i>คัดลอกไปยังคลิปบอร์ดแล้ว</div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none"><i class="bi bi-save me-1"></i>บันทึกรหัสผ่านนี้</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
