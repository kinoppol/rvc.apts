<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

$majors = ['วิทยาการคอมพิวเตอร์', 'เทคโนโลยีสารสนเทศ', 'วิทยาการข้อมูล', 'วิศวกรรมซอฟต์แวร์'];
$perPage = 8;

/** Rebuilds the current list URL so redirects land back on the same filter/page. */
function members_return_url(): string
{
    $q = array_intersect_key($_POST, array_flip(['search', 'status', 'page']));
    return url('admin/members.php') . ($q ? '?' . http_build_query($q) : '');
}

/**
 * Renders an inline POST form for a single member row action.
 * Pass $modal to show a custom Bootstrap confirm modal instead of the native confirm().
 * $modal keys: title, msg, icon (Bootstrap icon class), color (hex), btn (button label), btnCls (Bootstrap btn class).
 */
function member_action_form(int $id, string $action, string $btnCls, string $icon, string $label, ?array $modal = null): string
{
    global $search, $status, $page;
    $confirmAttrs = '';
    if ($modal) {
        $confirmAttrs = ' data-confirm-modal'
            . ' data-confirm-title="' . e($modal['title'] ?? '') . '"'
            . ' data-confirm-msg="' . e($modal['msg'] ?? '') . '"'
            . ' data-confirm-icon="' . e($modal['icon'] ?? 'bi-question-circle') . '"'
            . ' data-confirm-color="' . e($modal['color'] ?? '#2563EB') . '"'
            . ' data-confirm-btn="' . e($modal['btn'] ?? 'ยืนยัน') . '"'
            . ' data-confirm-btn-cls="' . e($modal['btnCls'] ?? 'btn-primary') . '"';
    }
    return '<form method="post" style="margin:0"' . $confirmAttrs . '>'
        . Csrf::field()
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="search" value="' . e($search) . '">'
        . '<input type="hidden" name="status" value="' . e($status) . '">'
        . '<input type="hidden" name="page" value="' . (int) $page . '">'
        . '<button type="submit" class="' . e($btnCls) . '"><i class="bi ' . e($icon) . ' me-1"></i>' . e($label) . '</button>'
        . '</form>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'add') {
        $result = Member::add($_POST);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'เพิ่มสมาชิกเรียบร้อยแล้ว' : ($result['error'] ?? 'เพิ่มสมาชิกไม่สำเร็จ'));
    } elseif ($action === 'approve') {
        Member::approve($id);
        flash_set('ok', 'อนุมัติสมาชิกเรียบร้อยแล้ว');
    } elseif ($action === 'reject') {
        Member::reject($id);
        flash_set('warn', 'ปฏิเสธคำขอสมัครเรียบร้อยแล้ว');
    } elseif ($action === 'suspend') {
        Member::suspend($id);
        flash_set('warn', 'ระงับสิทธิ์สมาชิกเรียบร้อยแล้ว');
    } elseif ($action === 'activate') {
        Member::activate($id);
        flash_set('ok', 'เปิดใช้งานสมาชิกเรียบร้อยแล้ว');
    } elseif ($action === 'delete') {
        Member::delete($id);
        flash_set('warn', 'ลบสมาชิกเรียบร้อยแล้ว');
    } elseif ($action === 'reset_password') {
        $result = Member::resetPassword($id, $_POST['new_password'] ?? '');
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'รีเซตรหัสผ่านเรียบร้อยแล้ว' : ($result['error'] ?? 'รีเซตไม่สำเร็จ'));
    } elseif ($action === 'assign_group') {
        $gid = ($_POST['group_id'] ?? '') !== '' ? (int) $_POST['group_id'] : null;
        $result = Member::assignGroup($id, $gid);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'อัปเดตกลุ่มของสมาชิกเรียบร้อยแล้ว' : ($result['error'] ?? 'อัปเดตกลุ่มไม่สำเร็จ'));
    } elseif ($action === 'waive') {
        $n = Booking::waiveOverdueForUser($id);
        flash_set('ok', 'ปลดการระงับเรียบร้อยแล้ว (ยกเว้นรายงานค้าง ' . $n . ' รายการ)');
    } elseif ($action === 'impersonate') {
        $target = Member::find($id);
        if ($target && $target['role'] === 'student' && $target['status'] === 'approved') {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['impersonating'] = true;
            $_SESSION['user_id'] = $id;
            session_regenerate_id(true);
            header('Location: ' . url('student/dashboard.php'));
            exit;
        }
        flash_set('err', 'ไม่สามารถสวมสิทธิ์ผู้ใช้นี้ได้');
    }
    header('Location: ' . members_return_url());
    exit;
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all', 'approved', 'pending', 'suspended'], true)) {
    $status = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));

$data = Member::list($search, $status, $page, $perPage);
$allGroups = UserGroup::all();
$totalPages = max(1, (int) ceil($data['total'] / $perPage));
$page = min($page, $totalPages);
$shownFrom = $data['total'] > 0 ? ($page - 1) * $perPage + 1 : 0;
$shownTo = min($page * $perPage, $data['total']);

$historyId = isset($_GET['history']) ? (int) $_GET['history'] : 0;
$historyMember = $historyId ? Member::find($historyId) : null;
$historyBookings = $historyMember ? Booking::listForUser($historyId) : [];

/** Preserves search/status when building filter + pagination links. */
function members_link(array $overrides = []): string
{
    global $search, $status, $page;
    $params = array_filter(['search' => $search, 'status' => $status, 'page' => $page] + [], fn ($v) => $v !== '' && $v !== null);
    $params = array_merge($params, $overrides);
    return url('admin/members.php') . '?' . http_build_query($params);
}

$activeNav = 'member-management';
require __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <h5 style="font-weight:700;margin:0">จัดการสมาชิก</h5>
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0"><?= (int) $data['totalMembers'] ?> สมาชิก · <?= (int) $data['pendingCount'] ?> รออนุมัติ</p>
  </div>
  <button type="button" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px" data-bs-toggle="modal" data-bs-target="#addMemberModal"><i class="bi bi-person-plus me-1"></i>เพิ่มสมาชิก</button>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:14px">
  <div class="card-body" style="padding:16px">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <div style="flex:1;min-width:200px;position:relative">
        <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--bs-tertiary-color);font-size:14px"></i>
        <input name="search" value="<?= e($search) ?>" type="text" class="form-control" placeholder="ค้นหาชื่อหรือรหัสนักศึกษา..." style="padding-left:36px;font-size:13px">
      </div>
      <button type="submit" class="btn btn-outline-secondary btn-sm" style="font-size:13px">ค้นหา</button>
    </form>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px">
      <?php
        $chips = [
            'all' => ['ทั้งหมด', $data['totalMembers']],
            'approved' => ['อนุมัติแล้ว', $data['approvedCount']],
            'pending' => ['รออนุมัติ', $data['pendingCount']],
            'suspended' => ['ระงับสิทธิ์', $data['suspendedCount']],
        ];
        foreach ($chips as $key => [$label, $count]):
          $active = $status === $key;
      ?>
        <a href="<?= members_link(['status' => $key, 'page' => 1]) ?>" style="text-decoration:none;border-radius:20px;font-size:12px;padding:5px 14px;border:1.5px solid <?= $active ? '#2563EB' : 'var(--bs-border-color)' ?>;font-weight:600;color:<?= $active ? '#2563EB' : 'var(--bs-secondary-color)' ?>;background:<?= $active ? 'rgba(37,99,235,.08)' : 'transparent' ?>"><?= e($label) ?> (<?= (int) $count ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:0;overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="border-bottom:2px solid var(--bs-border-color);background:var(--bs-secondary-bg)">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">สมาชิก</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">รหัส / สาขา</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">สถานะ</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">กลุ่ม</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">ชม.สะสม</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--bs-secondary-color);white-space:nowrap">วันสมัคร</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--bs-secondary-color)">การดำเนินการ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($data['rows'] as $m): ?>
          <tr style="border-bottom:1px solid var(--bs-border-color)">
            <td style="padding:12px 16px">
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#EFF6FF,#DBEAFE);display:flex;align-items:center;justify-content:center;font-weight:700;color:#2563EB;font-size:14px;flex-shrink:0"><?= e($m['initial']) ?></div>
                <div>
                  <div style="font-weight:600"><?= e($m['name']) ?></div>
                  <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:1px"><?= e($m['email']) ?></div>
                </div>
              </div>
            </td>
            <td style="padding:12px 16px">
              <div style="font-family:monospace;font-size:12px;font-weight:600"><?= e($m['student_id'] ?? '—') ?></div>
              <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:2px"><?= e($m['major'] ?? '—') ?></div>
            </td>
            <td style="padding:12px 16px">
              <span class="<?= $m['badgeCls'] ?>"><?= e($m['statusLabel']) ?></span>
              <?php if ($m['restricted']): ?><div><span class="badge-susp" style="margin-top:4px;display:inline-block;font-size:11px" title="มีรายงานการใช้งานค้างเกิน 7 วัน"><i class="bi bi-slash-circle me-1"></i>ระงับการจอง</span></div><?php endif; ?>
            </td>
            <td style="padding:12px 16px">
              <form method="post" style="margin:0">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="assign_group">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <input type="hidden" name="search" value="<?= e($search) ?>">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <input type="hidden" name="page" value="<?= (int) $page ?>">
                <select name="group_id" onchange="this.form.submit()" class="form-select form-select-sm" style="font-size:12px;min-width:130px">
                  <option value="">— ไม่มีกลุ่ม —</option>
                  <?php foreach ($allGroups as $g): ?>
                    <option value="<?= (int) $g['id'] ?>" <?= (int) ($m['group_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td style="padding:12px 16px;font-weight:600"><?= (int) $m['hours'] ?> ชม.</td>
            <td style="padding:12px 16px;color:var(--bs-secondary-color);white-space:nowrap"><?= e($m['joinDate']) ?></td>
            <td style="padding:12px 16px">
              <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
                <?php if ($m['isPending']): ?>
                  <?= member_action_form($m['id'], 'approve', 'action-btn-ok', 'bi-check-lg', 'อนุมัติ') ?>
                  <?= member_action_form($m['id'], 'reject', 'action-btn-err', 'bi-x-lg', 'ปฏิเสธ', ['title' => 'ปฏิเสธคำขอ', 'msg' => 'ปฏิเสธและลบคำขอสมัครนี้ถาวร ไม่สามารถเรียกคืนได้', 'icon' => 'bi-x-circle', 'color' => '#DC2626', 'btn' => 'ปฏิเสธและลบ', 'btnCls' => 'btn-danger']) ?>
                <?php elseif ($m['isApproved']): ?>
                  <?php if ($m['restricted']): ?>
                    <?= member_action_form($m['id'], 'waive', 'action-btn-warn', 'bi-unlock', 'ปลดระงับ', ['title' => 'ปลดการระงับ', 'msg' => 'ปลดการระงับการจองของ ' . $m['name'] . ' (ยกเว้นรายงานค้างทั้งหมด)', 'icon' => 'bi-unlock', 'color' => '#D97706', 'btn' => 'ปลดระงับ', 'btnCls' => 'btn-warning']) ?>
                  <?php endif; ?>
                  <?= member_action_form($m['id'], 'impersonate', 'action-btn-blue', 'bi-person-badge', 'สวมสิทธิ์', ['title' => 'สวมสิทธิ์', 'msg' => 'ดูระบบในมุมมองของ ' . $m['name'] . ' (นักศึกษา) — กด "คืนสิทธิ์ Admin" บนแถบแจ้งเตือนเพื่อออก', 'icon' => 'bi-person-badge', 'color' => '#2563EB', 'btn' => 'สวมสิทธิ์', 'btnCls' => 'btn-primary']) ?>
                  <?= member_action_form($m['id'], 'suspend', 'action-btn-warn', 'bi-slash-circle', 'ระงับ', ['title' => 'ระงับสิทธิ์', 'msg' => 'ระงับสิทธิ์ของ ' . $m['name'] . ' — ผู้ใช้จะไม่สามารถเข้าสู่ระบบและจองได้จนกว่าจะเปิดใช้งาน', 'icon' => 'bi-slash-circle', 'color' => '#D97706', 'btn' => 'ระงับ', 'btnCls' => 'btn-warning']) ?>
                  <button type="button" class="action-btn-blue" data-reset-pw data-id="<?= (int) $m['id'] ?>" data-name="<?= e($m['name']) ?>"><i class="bi bi-key me-1"></i>รีเซตรหัส</button>
                  <a href="<?= members_link(['history' => $m['id']]) ?>" class="action-btn-blue" style="text-decoration:none"><i class="bi bi-clock-history me-1"></i>ประวัติ</a>
                <?php elseif ($m['isSuspended']): ?>
                  <?= member_action_form($m['id'], 'activate', 'action-btn-ok', 'bi-check-circle', 'เปิดใช้') ?>
                  <button type="button" class="action-btn-blue" data-reset-pw data-id="<?= (int) $m['id'] ?>" data-name="<?= e($m['name']) ?>"><i class="bi bi-key me-1"></i>รีเซตรหัส</button>
                  <a href="<?= members_link(['history' => $m['id']]) ?>" class="action-btn-blue" style="text-decoration:none"><i class="bi bi-clock-history me-1"></i>ประวัติ</a>
                  <?= member_action_form($m['id'], 'delete', 'action-btn-err', 'bi-trash', 'ลบ', ['title' => 'ลบสมาชิก', 'msg' => 'ลบ ' . $m['name'] . ' ออกจากระบบอย่างถาวร พร้อมประวัติการจองทั้งหมด ไม่สามารถเรียกคืนได้', 'icon' => 'bi-trash', 'color' => '#DC2626', 'btn' => 'ลบถาวร', 'btnCls' => 'btn-danger']) ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="7" style="padding:32px;text-align:center;color:var(--bs-tertiary-color)">ไม่พบสมาชิกที่ตรงกับเงื่อนไข</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div style="padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--bs-border-color);flex-wrap:wrap;gap:10px">
      <span style="font-size:12px;color:var(--bs-secondary-color)">แสดง <?= (int) $shownFrom ?>–<?= (int) $shownTo ?> จาก <?= (int) $data['total'] ?> รายการ</span>
      <div style="display:flex;gap:4px">
        <a href="<?= members_link(['page' => max(1, $page - 1)]) ?>" class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" style="font-size:12px">ก่อนหน้า</a>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="<?= members_link(['page' => $p]) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>" style="font-size:12px;<?= $p === $page ? 'background:#2563EB;border:none' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="<?= members_link(['page' => min($totalPages, $page + 1)]) ?>" class="btn btn-sm btn-outline-secondary<?= $page >= $totalPages ? ' disabled' : '' ?>" style="font-size:12px">ถัดไป</a>
      </div>
    </div>
  </div>
</div>

<!-- Add member modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="search" value="<?= e($search) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="page" value="<?= (int) $page ?>">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-person-plus me-2" style="color:#2563EB"></i>เพิ่มสมาชิกใหม่</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">ชื่อ-นามสกุล *</label><input name="name" required class="form-control" style="font-size:13px"></div>
          <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">รหัสนักศึกษา *</label><input name="student_id" required class="form-control" style="font-size:13px"></div>
          <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">สาขาวิชา *</label>
            <select name="major" class="form-select" style="font-size:13px">
              <?php foreach ($majors as $mj): ?><option><?= e($mj) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">เบอร์โทร</label><input name="phone" class="form-control" style="font-size:13px"></div>
          <div style="grid-column:span 2"><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">อีเมล *</label><input type="email" name="email" required class="form-control" style="font-size:13px"></div>
          <div style="grid-column:span 2"><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">รหัสผ่านเริ่มต้น *</label><input name="password" required class="form-control" placeholder="อย่างน้อย 8 ตัวอักษร" style="font-size:13px"></div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">เพิ่มสมาชิก (อนุมัติทันที)</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reset password modal (populated by app.js) -->
<div class="modal fade" id="resetPwModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id">
        <input type="hidden" name="search" value="<?= e($search) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="page" value="<?= (int) $page ?>">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-key me-2" style="color:#2563EB"></i>รีเซตรหัสผ่าน</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <p style="font-size:13px;color:var(--bs-secondary-color);margin:0 0 14px">ตั้งรหัสผ่านใหม่ให้ <strong id="resetPwName">สมาชิก</strong> แล้วแจ้งรหัสนี้ให้ผู้ใช้</p>
          <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">รหัสผ่านใหม่ *</label>
          <input name="new_password" required minlength="8" class="form-control" placeholder="อย่างน้อย 8 ตัวอักษร" style="font-size:13px">
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">ตั้งรหัสผ่านใหม่</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Generic confirm modal — JS populates from data-confirm-* on the form -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden">
      <div class="modal-body" style="padding:32px 28px 20px;text-align:center">
        <div id="confirmActionIcon" style="width:60px;height:60px;border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:26px"></div>
        <h6 id="confirmActionTitle" style="font-weight:700;font-size:17px;margin:0 0 10px;color:var(--bs-body-color)"></h6>
        <p id="confirmActionMsg" style="color:var(--bs-secondary-color);font-size:13px;margin:0;line-height:1.6"></p>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--bs-border-color);padding:16px 20px;justify-content:center;gap:10px">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="font-size:13px;min-width:90px;border-radius:8px">ยกเลิก</button>
        <button type="button" id="confirmActionBtn" class="btn" style="font-size:13px;min-width:90px;border-radius:8px;font-weight:600"></button>
      </div>
    </div>
  </div>
</div>

<?php if ($historyMember): ?>
<!-- History modal (auto-opened) -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border:none;border-radius:14px">
      <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
        <h6 class="modal-title" style="font-weight:700"><i class="bi bi-clock-history me-2" style="color:#2563EB"></i>ประวัติการจอง — <?= e($historyMember['name']) ?></h6>
        <a href="<?= members_link() ?>" class="btn-close" aria-label="ปิด"></a>
      </div>
      <div class="modal-body" style="padding:20px">
        <?php if ($historyBookings): ?>
          <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr style="border-bottom:2px solid var(--bs-border-color)">
              <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">วันที่</th>
              <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">ช่วงเวลา</th>
              <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">วัตถุประสงค์ / รายงาน</th>
              <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--bs-secondary-color)">สถานะ</th>
            </tr></thead>
            <tbody>
              <?php foreach ($historyBookings as $b): ?>
                <tr style="border-bottom:1px solid var(--bs-border-color);vertical-align:top">
                  <td style="padding:10px;white-space:nowrap"><?= e($b['dateLabel']) ?></td>
                  <td style="padding:10px;white-space:nowrap"><?= e($b['slotLabel']) ?><div style="font-size:11px;color:var(--bs-tertiary-color)"><?= e($b['ai_name']) ?></div></td>
                  <td style="padding:10px">
                    <div><?= e($b['purpose'] ?: '—') ?></div>
                    <?php if (!empty($b['report_text'])): ?><div style="font-size:11px;color:var(--bs-secondary-color);margin-top:3px"><i class="bi bi-journal-text me-1"></i><?= e($b['report_text']) ?></div><?php endif; ?>
                    <?php if (!empty($b['report_file'])): ?><div style="font-size:11px;margin-top:3px"><a href="<?= url('uploads/reports/' . $b['report_file']) ?>" target="_blank" style="color:#2563EB;text-decoration:none"><i class="bi bi-paperclip me-1"></i>ไฟล์แนบ</a></div><?php endif; ?>
                    <?php if ($b['token_start_pct'] !== null || $b['token_end_pct'] !== null || !empty($b['token_reset_at'])): ?>
                    <div style="margin-top:5px;display:flex;flex-wrap:wrap;gap:6px">
                      <?php if ($b['token_start_pct'] !== null): ?><span style="font-size:10px;background:var(--bs-secondary-bg);border-radius:5px;padding:2px 6px;color:var(--bs-secondary-color)"><i class="bi bi-speedometer2 me-1"></i>ก่อน <?= (int)$b['token_start_pct'] ?>%</span><?php endif; ?>
                      <?php if ($b['token_end_pct'] !== null): ?><span style="font-size:10px;background:var(--bs-secondary-bg);border-radius:5px;padding:2px 6px;color:var(--bs-secondary-color)"><i class="bi bi-speedometer2 me-1"></i>หลัง <?= (int)$b['token_end_pct'] ?>%</span><?php endif; ?>
                      <?php if (!empty($b['token_reset_at'])): ?><span style="font-size:10px;background:var(--bs-secondary-bg);border-radius:5px;padding:2px 6px;color:var(--bs-secondary-color)"><i class="bi bi-arrow-clockwise me-1"></i>รีเซ็ต <?= e((new DateTimeImmutable($b['token_reset_at']))->format('d/m H:i')) ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                  </td>
                  <td style="padding:10px">
                    <span class="<?= $b['badgeCls'] ?>"><?= e($b['statusLabel']) ?></span>
                    <?php if ($b['needsReport']): ?><div><span class="<?= $b['reportOverdue'] ? 'badge-susp' : 'badge-pend' ?>" style="margin-top:4px;display:inline-block;font-size:11px"><?= e($b['reportStatusText']) ?></span></div>
                    <?php elseif ($b['reported']): ?><div style="font-size:11px;color:#059669;margin-top:4px"><i class="bi bi-check-circle me-1"></i>รายงานแล้ว</div><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="text-align:center;color:var(--bs-tertiary-color);padding:20px;margin:0">สมาชิกรายนี้ยังไม่มีประวัติการจอง</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
  window.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('historyModal');
    if (el) new bootstrap.Modal(el).show();
  });
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
