<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel') {
        $result = Booking::cancel($user['id'], (int) ($_POST['id'] ?? 0));
        flash_set($result['ok'] ? 'warn' : 'err', $result['ok'] ? 'ยกเลิกการจองเรียบร้อยแล้ว' : ($result['error'] ?? 'ไม่สามารถยกเลิกได้'));
    } elseif ($action === 'checkin') {
        $result = Booking::checkIn($user['id'], (int) ($_POST['id'] ?? 0));
        if ($result['ok']) {
            $msg = !empty($result['earlyAccess']) ? 'เช็คอินสำเร็จ — ใช้งานล่วงหน้าได้เลย!' : 'เช็คอินสำเร็จ รอถึงเวลาจองเพื่อดูรหัสผ่าน';
            flash_set('ok', $msg);
        } else {
            flash_set('err', $result['error'] ?? 'เช็คอินไม่สำเร็จ');
        }
    } elseif ($action === 'checkout') {
        $result = Booking::checkOut($user['id'], (int) ($_POST['id'] ?? 0));
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'เช็คเอาท์เรียบร้อย — ช่วงเวลาคืนสู่ระบบแล้ว' : ($result['error'] ?? 'เช็คเอาท์ไม่สำเร็จ'));
    } elseif ($action === 'report') {
        $extra = [
            'token_start_pct' => $_POST['token_start_pct'] ?? '',
            'token_end_pct'   => $_POST['token_end_pct'] ?? '',
            'token_reset_at'  => $_POST['token_reset_at'] ?? '',
        ];
        $result = Booking::submitReport($user['id'], (int) ($_POST['id'] ?? 0), $_POST['report_text'] ?? '', $_FILES['report_file'] ?? null, $extra);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ส่งรายงานการใช้งานเรียบร้อยแล้ว' : ($result['error'] ?? 'ส่งรายงานไม่สำเร็จ'));
    } elseif ($action === 'issue') {
        $result = Booking::reportIssue($user['id'], (int) ($_POST['id'] ?? 0), $_POST['issue_text'] ?? '', $_FILES['issue_file'] ?? null);
        flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'ส่งรายงานปัญหาเรียบร้อยแล้ว ผู้ดูแลระบบจะได้รับแจ้ง' : ($result['error'] ?? 'ส่งรายงานปัญหาไม่สำเร็จ'));
    }
    header('Location: ' . url('student/my-bookings.php') . (($_GET['filter'] ?? '') !== '' ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}

$filters = ['all' => 'ทั้งหมด', 'upcoming' => 'กำลังจะมาถึง', 'completed' => 'เสร็จสิ้น', 'cancelled' => 'ยกเลิก'];
$filter = $_GET['filter'] ?? 'all';
if (!isset($filters[$filter])) {
    $filter = 'all';
}
$bookings = Booking::listForUser($user['id'], $filter);
$restricted = Booking::isRestricted($user['id']);
$pendingReports = Booking::pendingReportsForUser($user['id']);
$earlyAccess = Booking::earlyAccessForUser($user['id']);
$earlyById = array_column($earlyAccess, null, 'id');

$activeNav = 'my-bookings';
require __DIR__ . '/../includes/header.php';
?>
<h5 style="font-weight:700;margin:0 0 20px">การจองของฉัน</h5>

<?php foreach ($earlyAccess as $ea): ?>
<div class="ea-banner" style="border-radius:10px;padding:14px 16px;margin-bottom:12px">
  <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px">
    <i class="bi bi-lightning-charge-fill" style="color:#059669;font-size:17px;margin-top:1px;flex-shrink:0"></i>
    <div style="flex:1;min-width:0">
      <div class="ea-text-title" style="font-size:14px;font-weight:700">ใช้งาน <?= e($ea['ai_name']) ?> ได้ล่วงหน้า!</div>
      <div style="font-size:12px;color:#059669;margin-top:2px">ช่วง <?= e($ea['prevSlotLabel']) ?> ไม่มีผู้ใช้งาน · ใช้ได้จนถึงสิ้นสุด<?= e($ea['slotLabel']) ?></div>
    </div>
    <span class="badge-ok" style="flex-shrink:0">ช่วงก่อนหน้าว่าง</span>
  </div>
  <?php if ($ea['hasCheckedIn']): ?>
  <div class="ea-cred-box" style="border-radius:8px;padding:10px 14px;display:flex;gap:14px;align-items:center;flex-wrap:wrap;font-size:13px">
    <span style="font-size:11px;color:#059669;display:flex;align-items:center;gap:4px;white-space:nowrap;flex-shrink:0"><i class="bi bi-clock-history"></i>เช็คอินเมื่อ <?= e((new DateTimeImmutable($ea['checked_in_at']))->format('H:i')) ?> น.</span>
    <div style="display:flex;align-items:center;gap:6px">
      <span style="font-size:12px;color:var(--bs-secondary-color)">อีเมล:</span>
      <strong><?= e($ea['ai_email']) ?></strong>
      <button type="button" title="คัดลอกอีเมล" style="background:none;border:1px solid var(--bs-border-color);border-radius:4px;padding:2px 6px;cursor:pointer;color:var(--bs-secondary-color);font-size:12px;line-height:1"
        onclick="copyText(this,<?= e(json_encode($ea['ai_email'], JSON_UNESCAPED_UNICODE)) ?>)"><i class="bi bi-clipboard"></i></button>
    </div>
    <div style="display:flex;align-items:center;gap:6px">
      <span style="font-size:12px;color:var(--bs-secondary-color)">รหัสผ่าน:</span>
      <code id="mbPw<?= (int) $ea['id'] ?>" style="font-size:13px;letter-spacing:0.12em;background:transparent">••••••••</code>
      <button type="button" class="btn btn-sm" style="font-size:11px;padding:2px 8px;border:1px solid var(--bs-border-color);border-radius:5px"
        onclick="(function(b,id,pw){var el=document.getElementById(id);if(el.textContent==='••••••••'){el.textContent=pw;b.textContent='ซ่อน';}else{el.textContent='••••••••';b.textContent='แสดง';}})(this,'mbPw<?= (int) $ea['id'] ?>',<?= e(json_encode($ea['account_password'], JSON_UNESCAPED_UNICODE)) ?>)">แสดง</button>
      <button type="button" title="คัดลอกรหัสผ่าน" style="background:none;border:1px solid var(--bs-border-color);border-radius:4px;padding:2px 6px;cursor:pointer;color:var(--bs-secondary-color);font-size:12px;line-height:1"
        onclick="copyText(this,<?= e(json_encode($ea['account_password'], JSON_UNESCAPED_UNICODE)) ?>)"><i class="bi bi-clipboard"></i></button>
    </div>
  </div>
  <?php else: ?>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <form method="post" action="<?= url('student/my-bookings.php') ?>" style="margin:0" data-checkin-confirm>
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="checkin">
      <input type="hidden" name="id" value="<?= (int) $ea['id'] ?>">
      <button type="submit" style="background:#059669;color:white;border:none;border-radius:8px;padding:7px 16px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px"><i class="bi bi-qr-code-scan"></i>เช็คอินเพื่อใช้งานล่วงหน้า</button>
    </form>
    <span style="font-size:11px;color:#059669">กดเช็คอินเพื่อยืนยันตัวตนและรับข้อมูลบัญชี AI</span>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($restricted): ?>
  <div class="bk-alert-err" style="border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px;display:flex;gap:8px;align-items:flex-start">
    <i class="bi bi-slash-circle-fill" style="flex-shrink:0;margin-top:1px"></i>
    <span><strong>ถูกระงับการจองชั่วคราว</strong> — มีรายงานค้างเกินกำหนด <?= Booking::REPORT_DEADLINE_DAYS ?> วัน กรุณากดปุ่ม "รายงาน" ในรายการด้านล่างให้ครบ ระบบจะปลดล็อกให้จองได้อีกครั้งทันที</span>
  </div>
<?php elseif ($pendingReports): ?>
  <div class="bk-alert-warn" style="border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;display:flex;gap:8px;align-items:center">
    <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0"></i>
    <span>มีการใช้งานที่ยังไม่ได้รายงาน <strong><?= count($pendingReports) ?></strong> รายการ กรุณารายงานภายใน <?= Booking::REPORT_DEADLINE_DAYS ?> วันหลังใช้งาน ไม่เช่นนั้นจะถูกระงับการจอง</span>
  </div>
<?php endif; ?>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
  <div class="card-body" style="padding:20px">
    <div style="display:flex;gap:0;border-bottom:2px solid var(--bs-border-color);margin-bottom:20px;flex-wrap:wrap">
      <?php foreach ($filters as $key => $label): ?>
        <a href="<?= url('student/my-bookings.php') ?>?filter=<?= $key ?>" style="text-decoration:none;padding:8px 16px;font-size:13px;font-weight:600;<?= $filter === $key ? 'color:#2563EB;border-bottom:2px solid #2563EB;margin-bottom:-2px' : 'color:var(--bs-secondary-color)' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($bookings as $bk): ?>
        <?php
          $ea = $earlyById[$bk['id']] ?? null;
          $showCredentials = ($bk['displayStatus'] === 'now')
              || ($ea && $bk['hasCheckedIn']);
          $showEarlyCheckIn = $ea && !$bk['hasCheckedIn'];
          $isActive = in_array($bk['displayStatus'], ['now', 'checked_in', 'check_in_ready']);
        ?>
        <div class="<?= $ea ? 'bk-row-ea' : ($isActive ? 'bk-row-active' : 'bk-row-default') ?>" style="display:flex;align-items:<?= ($ea || $showCredentials || $bk['canCheckIn'] || $showEarlyCheckIn) ? 'flex-start' : 'center' ?>;gap:14px;padding:14px;border-radius:10px">
          <div class="<?= $ea ? 'bk-icon-ea' : ($bk['displayStatus'] === 'now' ? 'bk-icon-now' : ($isActive ? 'bk-icon-active' : 'bk-icon-default')) ?>" style="width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:<?= ($ea || $isActive) ? '2px' : '0' ?>">
            <i class="bi <?= $ea ? 'bi-lightning-charge-fill' : ($bk['displayStatus'] === 'now' ? 'bi-broadcast' : ($bk['canCheckIn'] ? 'bi-qr-code-scan' : 'bi-calendar3')) ?>" style="color:<?= $ea ? '#059669' : ($bk['displayStatus'] === 'now' ? '#059669' : ($isActive ? '#2563EB' : '#2563EB')) ?>;font-size:18px"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:14px"><?= e($bk['slotLabel']) ?></div>
            <div style="font-size:12px;color:var(--bs-secondary-color);margin-top:2px"><?= e($bk['dateLabel']) ?> · <?= e($bk['ai_name']) ?></div>
            <?php if (!empty($bk['purpose'])): ?><div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:2px"><i class="bi bi-bullseye me-1"></i><?= e($bk['purpose']) ?></div><?php endif; ?>

            <?php if ($showCredentials): ?>
            <div class="<?= $ea ? 'bk-cred-ea' : '' ?>" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:8px;padding:8px 10px;<?= $ea ? '' : 'background:var(--bs-secondary-bg);border:1px solid var(--bs-border-color);' ?>border-radius:7px;font-size:12px">
              <?php if ($ea): ?><span class="bk-ea-label" style="font-size:11px"><i class="bi bi-lightning-charge-fill me-1" style="color:#059669"></i>ใช้งานล่วงหน้า</span><?php endif; ?>
              <?php if (!empty($bk['checked_in_at'])): ?><span style="font-size:11px;color:#059669;display:flex;align-items:center;gap:3px;white-space:nowrap;flex-shrink:0"><i class="bi bi-clock-history"></i>เช็คอินเมื่อ <?= e((new DateTimeImmutable($bk['checked_in_at']))->format('H:i')) ?> น.</span><?php endif; ?>
              <span style="display:flex;align-items:center;gap:5px;color:var(--bs-secondary-color)">อีเมล: <strong style="color:var(--bs-body-color)"><?= e($bk['ai_email']) ?></strong>
                <button type="button" title="คัดลอกอีเมล" style="background:none;border:1px solid var(--bs-border-color);border-radius:4px;padding:1px 5px;cursor:pointer;color:var(--bs-secondary-color);font-size:11px;line-height:1"
                  onclick="copyText(this,<?= e(json_encode($bk['ai_email'], JSON_UNESCAPED_UNICODE)) ?>)"><i class="bi bi-clipboard"></i></button>
              </span>
              <span style="display:flex;align-items:center;gap:5px;color:var(--bs-secondary-color)">รหัสผ่าน:
                <code id="rowPw<?= (int) $bk['id'] ?>" style="letter-spacing:0.12em;background:transparent">••••••••</code>
                <button type="button" class="btn btn-sm" style="font-size:10px;padding:1px 7px;border:1px solid var(--bs-border-color);border-radius:4px"
                  onclick="(function(b,id,pw){var el=document.getElementById(id);if(el.textContent==='••••••••'){el.textContent=pw;b.textContent='ซ่อน';}else{el.textContent='••••••••';b.textContent='แสดง';}})(this,'rowPw<?= (int) $bk['id'] ?>',<?= e(json_encode($bk['account_password'], JSON_UNESCAPED_UNICODE)) ?>)">แสดง</button>
                <button type="button" title="คัดลอกรหัสผ่าน" style="background:none;border:1px solid var(--bs-border-color);border-radius:4px;padding:1px 5px;cursor:pointer;color:var(--bs-secondary-color);font-size:11px;line-height:1"
                  onclick="copyText(this,<?= e(json_encode($bk['account_password'], JSON_UNESCAPED_UNICODE)) ?>)"><i class="bi bi-clipboard"></i></button>
              </span>
            </div>
            <?php
              $aiProvider = strtolower($bk['ai_provider'] ?? '');
              $isClaude = str_contains($aiProvider, 'claude') || str_contains(strtolower($bk['ai_name'] ?? ''), 'claude');
              $isChatgpt = str_contains($aiProvider, 'chatgpt') || str_contains($aiProvider, 'openai') || str_contains(strtolower($bk['ai_name'] ?? ''), 'chatgpt');
              $guideSteps = [];
              if ($isClaude) {
                  $guideSteps = [
                      ['icon' => 'bi-chat-left-text',    'text' => 'ระบุ <strong>บทบาท + บริบท + ผลลัพธ์</strong> ที่ต้องการในประโยคแรก เช่น "ช่วยเป็นผู้ตรวจรายงานวิชาการ..."'],
                      ['icon' => 'bi-folder2-open',      'text' => 'ใช้ <strong>Projects</strong> (ไอคอนโฟลเดอร์) เพื่อเก็บ context ข้ามบทสนทนา ไม่ต้องอธิบายซ้ำทุกครั้ง'],
                      ['icon' => 'bi-paperclip',         'text' => 'แนบ<strong>ไฟล์ / รูปภาพ / PDF</strong> ได้โดยตรงในกล่องแชท (ลากวางหรือคลิก +)'],
                      ['icon' => 'bi-arrow-counterclockwise', 'text' => 'หาก Claude ตอบผิดทิศ ให้บอก "ลองใหม่" พร้อมอธิบายส่วนที่ไม่ถูกต้อง ไม่ต้องเริ่มบทสนทนาใหม่'],
                  ];
              } elseif ($isChatgpt) {
                  $guideSteps = [
                      ['icon' => 'bi-cpu',               'text' => 'เลือก <strong>GPT-4o</strong> สำหรับงานทั่วไป / <strong>o1</strong> สำหรับคณิตศาสตร์และการใช้เหตุผลขั้นสูง'],
                      ['icon' => 'bi-image',             'text' => 'สร้างรูปภาพด้วย <strong>DALL·E 3</strong> ได้เลย — พิมพ์ "สร้างรูป..." หรือ "วาด..." ในกล่องแชท'],
                      ['icon' => 'bi-person-gear',       'text' => 'ตั้งค่า <strong>Custom Instructions</strong> (โปรไฟล์ → Customize ChatGPT) เพื่อกำหนด tone และสไตล์ถาวร'],
                      ['icon' => 'bi-tools',             'text' => 'เปิด <strong>Advanced Tools</strong> (โค้ด / ค้นหาเว็บ / Canvas) ได้จากไอคอนเครื่องมือในกล่องข้อความ'],
                  ];
              } else {
                  $guideSteps = [
                      ['icon' => 'bi-chat-left-text',    'text' => 'ระบุบริบทและผลลัพธ์ที่ต้องการให้ชัดเจนตั้งแต่ต้น'],
                      ['icon' => 'bi-arrow-repeat',      'text' => 'ถ้าคำตอบไม่ตรง ให้อธิบายเพิ่มเติมแทนการเริ่มใหม่'],
                      ['icon' => 'bi-paperclip',         'text' => 'ลองแนบไฟล์หรือตัวอย่างเพื่อให้ AI เข้าใจงานได้ดีขึ้น'],
                  ];
              }
            ?>
            <details style="margin-top:8px" <?= !isset($_COOKIE['guide_seen_' . (int) $bk['id']]) ? 'open' : '' ?>>
              <summary style="cursor:pointer;font-size:12px;font-weight:600;color:#2563EB;list-style:none;display:flex;align-items:center;gap:5px;user-select:none;width:fit-content">
                <i class="bi bi-lightbulb-fill" style="color:#D97706"></i>
                คู่มือเริ่มต้นใช้งาน <?= e($bk['ai_name']) ?>
                <i class="bi bi-chevron-down" style="font-size:10px;transition:transform .2s" id="guideChev<?= (int) $bk['id'] ?>"></i>
              </summary>
              <div style="margin-top:8px;padding:10px 12px;background:var(--bs-secondary-bg);border-left:3px solid #D97706;border-radius:0 8px 8px 0;display:flex;flex-direction:column;gap:7px"
                   onconnect="document.getElementById('guideChev<?= (int) $bk['id'] ?>').style.transform='rotate(180deg)'">
                <?php foreach ($guideSteps as $step): ?>
                <div style="display:flex;align-items:flex-start;gap:8px;font-size:12px;line-height:1.5">
                  <i class="bi <?= e($step['icon']) ?>" style="color:#D97706;flex-shrink:0;margin-top:2px"></i>
                  <span><?= $step['text'] ?></span>
                </div>
                <?php endforeach; ?>
                <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:2px;padding-top:6px;border-top:1px solid var(--bs-border-color)">
                  <i class="bi bi-info-circle me-1"></i>อย่าลืมส่ง<strong>รายงานการใช้งาน</strong>ภายใน 7 วันหลังสิ้นสุดการใช้งาน
                </div>
              </div>
            </details>
            <?php elseif ($bk['displayStatus'] === 'checked_in'): ?>
            <div style="margin-top:8px;padding:6px 10px;background:var(--bs-secondary-bg);border-radius:7px;font-size:12px;color:var(--bs-secondary-color);display:inline-flex;align-items:center;gap:6px">
              <i class="bi bi-lock-fill" style="color:#059669"></i>ยืนยันแล้ว — รหัสผ่านจะแสดงเมื่อถึงเวลาจอง
            </div>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end;<?= ($ea || $isActive) ? 'margin-top:2px' : '' ?>">
            <?php if ($ea && $bk['hasCheckedIn']): ?><span class="badge-ok"><i class="bi bi-lightning-charge-fill me-1"></i>ใช้งานล่วงหน้า</span><?php endif; ?>
            <span class="<?= $bk['badgeCls'] ?>"><?= e($bk['statusLabel']) ?></span>

            <?php if ($showEarlyCheckIn): ?>
            <form method="post" action="<?= url('student/my-bookings.php') ?>" style="margin:0" data-checkin-confirm>
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="checkin">
              <input type="hidden" name="id" value="<?= (int) $bk['id'] ?>">
              <button type="submit" class="action-btn-blue"><i class="bi bi-lightning-charge-fill me-1"></i>เช็คอิน (ล่วงหน้า)</button>
            </form>
            <?php elseif ($bk['canCheckIn']): ?>
            <form method="post" action="<?= url('student/my-bookings.php') ?>" style="margin:0" data-checkin-confirm>
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="checkin">
              <input type="hidden" name="id" value="<?= (int) $bk['id'] ?>">
              <button type="submit" class="action-btn-blue"><i class="bi bi-qr-code-scan me-1"></i>เช็คอิน</button>
            </form>
            <?php endif; ?>

            <?php if ($bk['canCheckOut']): ?>
            <form method="post" action="<?= url('student/my-bookings.php') ?>" style="margin:0" data-checkout-confirm>
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="checkout">
              <input type="hidden" name="id" value="<?= (int) $bk['id'] ?>">
              <button type="submit" style="background:none;border:1px solid #D97706;color:#D97706;border-radius:7px;padding:4px 10px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px"><i class="bi bi-box-arrow-right"></i>เช็คเอาท์</button>
            </form>
            <?php endif; ?>

            <?php if ($bk['canReport']): ?>
              <?php if ($bk['needsReport']): ?>
                <span class="<?= $bk['reportOverdue'] ? 'badge-susp' : 'badge-pend' ?>" style="font-size:11px"><?= e($bk['reportStatusText']) ?></span>
              <?php elseif ($bk['reported']): ?>
                <span class="badge-ok" style="font-size:11px"><i class="bi bi-check-circle me-1"></i>รายงานแล้ว</span>
              <?php endif; ?>
              <button type="button" class="action-btn-blue" data-report-booking
                data-id="<?= (int) $bk['id'] ?>"
                data-meta="<?= e($bk['dateLabel'] . ' · ' . $bk['slotLabel']) ?>"
                data-report-text="<?= e($bk['report_text'] ?? '') ?>"
                data-token-start="<?= $bk['token_start_pct'] !== null ? (int) $bk['token_start_pct'] : '' ?>"
                data-token-end="<?= $bk['token_end_pct'] !== null ? (int) $bk['token_end_pct'] : '' ?>"
                data-token-reset="<?= !empty($bk['token_reset_at']) ? date('Y-m-d\TH:i', strtotime($bk['token_reset_at'])) : '' ?>">
                <i class="bi bi-journal-text me-1"></i><?= $bk['reported'] ? 'แก้ไขรายงาน' : 'รายงาน' ?>
              </button>
            <?php endif; ?>
            <?php if ($bk['canReportIssue']): ?>
              <?php if ($bk['hasIssue']): ?>
                <span class="badge-pend" style="font-size:11px"><i class="bi bi-exclamation-triangle-fill me-1"></i>แจ้งปัญหาแล้ว</span>
              <?php endif; ?>
              <button type="button" class="action-btn-warn" data-issue-booking
                data-id="<?= (int) $bk['id'] ?>"
                data-meta="<?= e($bk['dateLabel'] . ' · ' . $bk['slotLabel']) ?>"
                data-issue-text="<?= e($bk['issue_text'] ?? '') ?>"
                data-issue-files="<?= e(json_encode(array_map(fn($f) => ['filename' => $f['filename'], 'original_name' => $f['original_name'] ?? $f['filename']], $bk['issue_files'] ?? []))) ?>">
                <i class="bi bi-bug me-1"></i><?= $bk['hasIssue'] ? 'แก้ไขปัญหา' : 'รายงานปัญหา' ?>
              </button>
            <?php endif; ?>
            <?php if ($bk['canCancel']): ?>
              <form method="post" action="<?= url('student/my-bookings.php') ?>" style="margin:0" onsubmit="return confirm('ยืนยันการยกเลิกการจองนี้?')">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?= (int) $bk['id'] ?>">
                <button type="submit" class="action-btn-err"><i class="bi bi-x-circle me-1"></i>ยกเลิก</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$bookings): ?>
        <div style="text-align:center;padding:40px 16px;color:var(--bs-tertiary-color)">
          <i class="bi bi-calendar-x" style="font-size:32px;display:block;margin-bottom:10px"></i>
          <div style="font-size:14px">ไม่มีรายการจองในหมวดนี้</div>
          <a href="<?= url('student/booking.php') ?>" style="font-size:13px;color:#2563EB;text-decoration:none;font-weight:600;display:inline-block;margin-top:10px">จองคิวตอนนี้ <i class="bi bi-arrow-right"></i></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Usage report modal (populated by app.js) -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post" enctype="multipart/form-data" action="<?= url('student/my-bookings.php') ?>?filter=<?= e($filter) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="report">
        <input type="hidden" name="id">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-journal-text me-2" style="color:#2563EB"></i>รายงานการใช้งาน</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px">
          <p style="font-size:12px;color:var(--bs-secondary-color);margin:0 0 4px" id="reportModalMeta">—</p>
          <p style="font-size:13px;color:var(--bs-secondary-color);margin:0 0 14px">กรอกรายละเอียดการใช้งาน และ/หรือ แนบไฟล์หลักฐาน (รูปภาพหรือ PDF) อย่างน้อยหนึ่งอย่าง</p>
          <div style="margin-bottom:12px">
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">รายละเอียดการใช้งาน</label>
            <textarea name="report_text" rows="4" maxlength="2000" class="form-control" placeholder="อธิบายสิ่งที่ได้ทำ/ผลลัพธ์จากการใช้ AI ในรอบนี้..." style="font-size:13px"></textarea>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">แนบไฟล์ (ไม่บังคับ)</label>
            <input type="file" name="report_file" accept="image/*,application/pdf" class="form-control" style="font-size:13px">
            <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px">รองรับรูปภาพ (JPG/PNG/GIF/WEBP) หรือ PDF ขนาดไม่เกิน 5 MB</div>
          </div>
          <div style="border-top:1px solid var(--bs-border-color);padding-top:14px;margin-top:2px">
            <div style="font-size:11px;font-weight:700;color:var(--bs-secondary-color);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px"><i class="bi bi-speedometer2 me-1"></i>ข้อมูล Token (ไม่บังคับ)</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
              <div>
                <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">% Token ก่อนใช้งาน</label>
                <div style="position:relative">
                  <input type="number" name="token_start_pct" min="0" max="100" class="form-control" placeholder="0–100" style="font-size:13px;padding-right:32px">
                  <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--bs-secondary-color);pointer-events:none">%</span>
                </div>
              </div>
              <div>
                <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">% Token เมื่อสิ้นสุด</label>
                <div style="position:relative">
                  <input type="number" name="token_end_pct" min="0" max="100" class="form-control" placeholder="0–100" style="font-size:13px;padding-right:32px">
                  <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--bs-secondary-color);pointer-events:none">%</span>
                </div>
              </div>
            </div>
            <div>
              <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:4px">เวลารีเซ็ต Token ครั้งต่อไป</label>
              <input type="datetime-local" name="token_reset_at" class="form-control" style="font-size:13px">
            </div>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary btn-sm" style="background:#2563EB;border:none"><i class="bi bi-send me-1"></i>ส่งรายงาน</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Issue / problem report modal -->
<div class="modal fade" id="issueModal" tabindex="-1" aria-hidden="true" data-reports-base="<?= e(url('uploads/reports/')) ?>">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:none;border-radius:14px">
      <form method="post" enctype="multipart/form-data" action="<?= url('student/my-bookings.php') ?>?filter=<?= e($filter) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="issue">
        <input type="hidden" name="id" id="issueBookingId">
        <div class="modal-header" style="border-bottom:1px solid var(--bs-border-color)">
          <h6 class="modal-title" style="font-weight:700"><i class="bi bi-bug me-2" style="color:#D97706"></i>รายงานปัญหาการใช้งาน</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body" style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <p style="font-size:12px;color:var(--bs-secondary-color);margin:0" id="issueModalMeta">—</p>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">อธิบายปัญหาที่พบ <span style="color:#EF4444">*</span></label>
            <textarea id="issueText" name="issue_text" rows="4" maxlength="1000" required class="form-control"
              placeholder="เช่น เข้าระบบไม่ได้ / รหัสผ่านไม่ถูกต้อง / เว็บไซต์ล่ม / บัญชีถูกล็อก ..."
              style="font-size:13px"></textarea>
            <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px">สูงสุด 1,000 ตัวอักษร</div>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">แนบรูปภาพหรือ PDF <span style="font-weight:400;color:var(--bs-tertiary-color)">(ไม่บังคับ)</span></label>
            <!-- Existing files list — shown when re-editing -->
            <div id="issueExistingFiles" style="display:none;margin-bottom:8px;padding:8px 12px;background:var(--bs-secondary-bg);border-radius:7px;font-size:12px">
              <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
                <i class="bi bi-paperclip" style="color:#D97706;flex-shrink:0"></i>
                <span style="font-weight:600;color:var(--bs-secondary-color)">ไฟล์ที่แนบไว้</span>
                <span style="font-size:11px;color:var(--bs-tertiary-color);margin-left:auto">อัปโหลดไฟล์ใหม่เพื่อแทนที่ทั้งหมด</span>
              </div>
              <div id="issueExistingFilesList" style="display:flex;flex-direction:column;gap:4px"></div>
            </div>
            <input type="file" name="issue_file[]" id="issueFileInput" accept="image/*,application/pdf" class="form-control" style="font-size:13px" multiple>
            <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:4px">รองรับรูปภาพ (JPG/PNG/GIF/WEBP) หรือ PDF ขนาดไม่เกิน 5 MB ต่อไฟล์ (เลือกได้หลายไฟล์)</div>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--bs-border-color)">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-sm" style="background:#D97706;color:white;border:none"><i class="bi bi-send me-1"></i>ส่งรายงานปัญหา</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
