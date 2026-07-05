<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $action = $_POST['action'] ?? '';

    if ($action === 'terms_upload') {
        $f = $_FILES['terms_pdf'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            flash_set('err', 'อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่');
        } elseif ($f['size'] > 10 * 1024 * 1024) {
            flash_set('err', 'ไฟล์ขนาดเกิน 10 MB');
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            if ($mime !== 'application/pdf') {
                flash_set('err', 'กรุณาอัปโหลดเฉพาะไฟล์ PDF');
            } else {
                $dest = __DIR__ . '/../uploads/terms/';
                // Remove old file if any
                $old = SlotSettings::getTermsFile();
                if ($old && file_exists($dest . $old)) { unlink($dest . $old); }
                $filename = 'terms_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pdf';
                move_uploaded_file($f['tmp_name'], $dest . $filename);
                SlotSettings::updateTermsFile($filename);
                flash_set('ok', 'อัปโหลดไฟล์ข้อตกลงเรียบร้อยแล้ว');
            }
        }
        header('Location: ' . url('admin/slots.php'));
        exit;
    }

    if ($action === 'terms_delete') {
        $old = SlotSettings::getTermsFile();
        if ($old) {
            $path = __DIR__ . '/../uploads/terms/' . $old;
            if (file_exists($path)) { unlink($path); }
        }
        SlotSettings::deleteTermsFile();
        flash_set('ok', 'ลบไฟล์ข้อตกลงเรียบร้อยแล้ว');
        header('Location: ' . url('admin/slots.php'));
        exit;
    }

    // Default: update slot settings
    $result = SlotSettings::update(
        (int) ($_POST['slot_hours'] ?? 0),
        (int) ($_POST['slots_per_day'] ?? 0),
        (int) ($_POST['weekly_quota'] ?? 0),
        (int) ($_POST['max_advance_days'] ?? 0),
        (string) ($_POST['day_start_time'] ?? '')
    );
    flash_set($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'บันทึกการตั้งค่าเรียบร้อยแล้ว' : ($result['error'] ?? 'บันทึกไม่สำเร็จ'));
    header('Location: ' . url('admin/slots.php'));
    exit;
}

$settings   = SlotSettings::get();
$termsFile  = SlotSettings::getTermsFile();

$activeNav = 'slot-management';
require __DIR__ . '/../includes/header.php';
?>
<h5 style="font-weight:700;margin:0 0 20px">จัดการตารางเวลา</h5>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:24px">
  <form method="post">
    <?= Csrf::field() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:600px">
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">ความยาวช่วงเวลา (ชั่วโมง)</label><input name="slot_hours" class="form-control" value="<?= (int) $settings['slot_hours'] ?>" type="number" min="1" max="24" required style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">จำนวน Slots/วัน</label><input name="slots_per_day" class="form-control" value="<?= (int) $settings['slots_per_day'] ?>" type="number" min="1" max="24" required style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">โควต้า/สัปดาห์/คน</label><input name="weekly_quota" class="form-control" value="<?= (int) $settings['weekly_quota'] ?>" type="number" min="1" required style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">จองล่วงหน้าสูงสุด (วัน)</label><input name="max_advance_days" class="form-control" value="<?= (int) $settings['max_advance_days'] ?>" type="number" min="1" required style="font-size:13px"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px">เวลาเริ่มต้นของวัน</label><input name="day_start_time" class="form-control" value="<?= e(substr($settings['day_start_time'], 0, 5)) ?>" type="time" required style="font-size:13px"></div>
    </div>
    <div style="background:#FFF7ED;border-radius:8px;padding:10px 14px;margin-top:16px;font-size:12px;color:#92400E;display:flex;gap:8px;align-items:flex-start;max-width:600px">
      <i class="bi bi-info-circle-fill" style="margin-top:1px;flex-shrink:0"></i>
      <span>รองรับ<strong>ระบบเวลา 30 ชั่วโมง</strong>แบบญี่ปุ่น — ช่วงเวลาที่เลยเที่ยงคืนจะแสดงเป็น 24:00–30:00 (เช่น 25:00 = ตี 1 ของวันถัดไป) เพื่อให้ยังนับเป็นวันเดียวกัน · เวลาเริ่มต้นของวัน + (ความยาวช่วงเวลา × จำนวน Slots/วัน) ต้องไม่เกิน 30:00 น. · การเปลี่ยนแปลงมีผลกับการจองใหม่เท่านั้น</span>
    </div>
    <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;margin-top:16px;font-size:13px"><i class="bi bi-save me-1"></i>บันทึกการตั้งค่า</button>
  </form>
</div>

<h5 style="font-weight:700;margin:28px 0 16px">เอกสารข้อตกลงการใช้งาน</h5>
<div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);padding:24px">
  <?php if ($termsFile): ?>
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;padding:12px 16px;background:var(--bs-secondary-bg);border-radius:8px">
      <i class="bi bi-file-earmark-pdf-fill" style="color:#DC2626;font-size:28px;flex-shrink:0"></i>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:600;color:var(--bs-body-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($termsFile) ?></div>
        <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:2px">ไฟล์ข้อตกลงปัจจุบัน · นักศึกษาต้องยอมรับก่อนสมัครสมาชิก</div>
      </div>
      <a href="<?= url('uploads/terms/' . $termsFile) ?>" target="_blank" class="btn btn-sm" style="font-size:12px;background:var(--bs-body-bg);border:1px solid var(--bs-border-color)"><i class="bi bi-eye me-1"></i>ดูไฟล์</a>
      <form method="post" style="margin:0">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="terms_delete">
        <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:12px" data-confirm-modal data-confirm-title="ลบไฟล์ข้อตกลง" data-confirm-msg="ไฟล์ข้อตกลงจะถูกลบออก นักศึกษาจะไม่ต้องยอมรับข้อตกลงเมื่อสมัครสมาชิก" data-confirm-icon="bi-trash3-fill" data-confirm-color="#DC2626" data-confirm-btn="ลบไฟล์" data-confirm-btn-cls="btn-danger"><i class="bi bi-trash3 me-1"></i>ลบ</button>
      </form>
    </div>
  <?php else: ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;padding:10px 14px;background:#FFF7ED;border-radius:8px;font-size:12px;color:#92400E">
      <i class="bi bi-info-circle-fill"></i>
      <span>ยังไม่มีไฟล์ข้อตกลง — นักศึกษาจะสมัครได้โดยไม่ต้องยอมรับข้อตกลงใดๆ</span>
    </div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="terms_upload">
    <div style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:220px">
        <label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:5px"><?= $termsFile ? 'อัปโหลดไฟล์ใหม่ (แทนที่ไฟล์เดิม)' : 'เลือกไฟล์ PDF ข้อตกลงการใช้งาน' ?></label>
        <input type="file" name="terms_pdf" accept=".pdf,application/pdf" required class="form-control" style="font-size:13px">
        <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:4px">PDF เท่านั้น · ขนาดสูงสุด 10 MB</div>
      </div>
      <button type="submit" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px;white-space:nowrap"><i class="bi bi-upload me-1"></i><?= $termsFile ? 'อัปโหลดแทนที่' : 'อัปโหลดไฟล์' ?></button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
