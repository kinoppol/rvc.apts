<?php
require_once __DIR__ . '/../bootstrap.php';
$user = require_role(['student', 'teacher']);

$termsFile = SlotSettings::getTermsFile();
$termsUrl  = $termsFile ? url('uploads/terms/' . $termsFile) : null;

$activeNav = 'terms';
require __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px">
  <div>
    <h5 style="font-weight:700;margin:0"><i class="bi bi-file-earmark-text me-2" style="color:#2563EB"></i>ข้อตกลงการใช้งาน</h5>
    <p style="color:var(--bs-secondary-color);font-size:14px;margin:4px 0 0">เปิดอ่านทบทวนข้อตกลงการใช้งานระบบได้ตลอดเวลา</p>
  </div>
  <?php if ($termsUrl): ?>
    <a href="<?= e($termsUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary" style="background:#2563EB;border:none;font-size:13px;white-space:nowrap"><i class="bi bi-box-arrow-up-right me-1"></i>เปิดในแท็บใหม่</a>
  <?php endif; ?>
</div>

<?php if ($termsUrl): ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04);overflow:hidden">
    <iframe src="<?= e($termsUrl) ?>#view=FitH" title="ข้อตกลงการใช้งาน" style="width:100%;height:78vh;border:none;display:block"></iframe>
  </div>
  <p style="font-size:12px;color:var(--bs-tertiary-color);margin:10px 0 0">
    <i class="bi bi-info-circle me-1"></i>หากแสดงผลไม่ครบถ้วน สามารถ<a href="<?= e($termsUrl) ?>" target="_blank" rel="noopener" style="color:#2563EB;font-weight:600;text-decoration:none"> เปิดไฟล์ในแท็บใหม่</a>เพื่ออ่านฉบับเต็ม
  </p>
<?php else: ?>
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:40px;text-align:center;color:var(--bs-tertiary-color)">
      <i class="bi bi-file-earmark-x" style="font-size:32px;display:block;margin-bottom:10px"></i>
      ขณะนี้ยังไม่มีเอกสารข้อตกลงการใช้งานในระบบ
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
