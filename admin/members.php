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

/** Renders an inline POST form for a single member row action. */
function member_action_form(int $id, string $action, string $btnCls, string $icon, string $label, ?string $confirm = null): string
{
    global $search, $status, $page;
    $onsubmit = $confirm ? ' onsubmit="return confirm(' . htmlspecialchars(json_encode($confirm, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')"' : '';
    return '<form method="post" style="margin:0"' . $onsubmit . '>'
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
    <p style="color:#64748B;font-size:14px;margin:4px 0 0"><?= (int) $data['totalMembers'] ?> สมาชิก · <?= (int) $data['pendingCount'] ?> รออนุมัติ</p>
  </div>
  <button type="button" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px" data-bs-toggle="modal" data-bs-target="#addMemberModal"><i class="bi bi-person-plus me-1"></i>เพิ่มสมาชิก</button>
</div>

<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);margin-bottom:14px">
  <div class="card-body" style="padding:16px">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:0">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <div style="flex:1;min-width:200px;position:relative">
        <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:14px"></i>
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
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#64748B;white-space:nowrap">สมาชิก</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#64748B;white-space:nowrap">รหัส / สาขา</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#64748B">สถานะ</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#64748B;white-space:nowrap">ชม.สะสม</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#64748B;white-space:nowrap">วันสมัคร</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:#64748B">การดำเนินการ</th>
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
                  <div style="font-size:11px;color:#94A3B8;margin-top:1px"><?= e($m['email']) ?></div>
                </div>
              </div>
            </td>
            <td style="padding:12px 16px">
              <div style="font-family:monospace;font-size:12px;font-weight:600"><?= e($m['student_id'] ?? '—') ?></div>
              <div style="font-size:11px;color:#64748B;margin-top:2px"><?= e($m['major'] ?? '—') ?></div>
            </td>
            <td style="padding:12px 16px"><span class="<?= $m['badgeCls'] ?>"><?= e($m['statusLabel']) ?></span></td>
            <td style="padding:12px 16px;font-weight:600"><?= (int) $m['hours'] ?> ชม.</td>
            <td style="padding:12px 16px;color:#64748B;white-space:nowrap"><?= e($m['joinDate']) ?></td>
            <td style="padding:12px 16px">
              <div style="display:flex;gap:5px;justify-content:center;flex-wrap:wrap">
                <?php if ($m['isPending']): ?>
                  <?= member_action_form($m['id'], 'approve', 'action-btn-ok', 'bi-check-lg', 'อนุมัติ') ?>
                  <?= member_action_form($m['id'], 'reject', 'action-btn-err', 'bi-x-lg', 'ปฏิเสธ', 'ปฏิเสธและลบคำขอนี้?') ?>
                <?php elseif ($m['isApproved']): ?>
                  <?= member_action_form($m['id'], 'suspend', 'action-btn-warn', 'bi-slash-circle', 'ระงับ', 'ระงับสิทธิ์สมาชิกนี้?') ?>
                  <a href="<?= members_link(['history' => $m['id']]) ?>" class="action-btn-blue" style="text-decoration:none"><i class="bi bi-clock-history me-1"></i>ประวัติ</a>
                <?php elseif ($m['isSuspended']): ?>
                  <?= member_action_form($m['id'], 'activate', 'action-btn-ok', 'bi-check-circle', 'เปิดใช้') ?>
                  <a href="<?= members_link(['history' => $m['id']]) ?>" class="action-btn-blue" style="text-decoration:none"><i class="bi bi-clock-history me-1"></i>ประวัติ</a>
                  <?= member_action_form($m['id'], 'delete', 'action-btn-err', 'bi-trash', 'ลบ', 'ลบสมาชิกนี้อย่างถาวร?') ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="6" style="padding:32px;text-align:center;color:#94A3B8">ไม่พบสมาชิกที่ตรงกับเงื่อนไข</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div style="padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--bs-border-color);flex-wrap:wrap;gap:10px">
      <span style="font-size:12px;color:#64748B">แสดง <?= (int) $shownFrom ?>–<?= (int) $shownTo ?> จาก <?= (int) $data['total'] ?> รายการ</span>
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
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">ชื่อ-นามสกุล *</label><input name="name" required class="form-control" style="font-size:13px"></div>
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">รหัสนักศึกษา *</label><input name="student_id" required class="form-control" style="font-size:13px"></div>
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">สาขาวิชา *</label>
            <select name="major" class="form-select" style="font-size:13px">
              <?php foreach ($majors as $mj): ?><option><?= e($mj) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">เบอร์โทร</label><input name="phone" class="form-control" style="font-size:13px"></div>
          <div style="grid-column:span 2"><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">อีเมล *</label><input type="email" name="email" required class="form-control" style="font-size:13px"></div>
          <div style="grid-column:span 2"><label style="font-size:12px;font-weight:600;color:#64748B;display:block;margin-bottom:4px">รหัสผ่านเริ่มต้น *</label><input name="password" required class="form-control" placeholder="อย่างน้อย 8 ตัวอักษร" style="font-size:13px"></div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none">เพิ่มสมาชิก (อนุมัติทันที)</button>
        </div>
      </form>
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
              <th style="padding:8px 10px;text-align:left;font-weight:600;color:#64748B">วันที่</th>
              <th style="padding:8px 10px;text-align:left;font-weight:600;color:#64748B">ช่วงเวลา</th>
              <th style="padding:8px 10px;text-align:left;font-weight:600;color:#64748B">AI Account</th>
              <th style="padding:8px 10px;text-align:left;font-weight:600;color:#64748B">สถานะ</th>
            </tr></thead>
            <tbody>
              <?php foreach ($historyBookings as $b): ?>
                <tr style="border-bottom:1px solid var(--bs-border-color)">
                  <td style="padding:10px"><?= e($b['dateLabel']) ?></td>
                  <td style="padding:10px"><?= e($b['slotLabel']) ?></td>
                  <td style="padding:10px;color:#64748B"><?= e($b['ai_name']) ?></td>
                  <td style="padding:10px"><span class="<?= $b['badgeCls'] ?>"><?= e($b['statusLabel']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="text-align:center;color:#94A3B8;padding:20px;margin:0">สมาชิกรายนี้ยังไม่มีประวัติการจอง</p>
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
