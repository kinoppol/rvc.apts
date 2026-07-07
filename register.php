<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    header('Location: ' . url('index.php'));
    exit;
}

$majors    = ['วิทยาการคอมพิวเตอร์', 'เทคโนโลยีสารสนเทศ', 'วิทยาการข้อมูล', 'วิศวกรรมซอฟต์แวร์'];
$error     = null;
$values    = ['role' => 'student', 'name' => '', 'student_id' => '', 'major' => $majors[0], 'phone' => '', 'email' => ''];
$termsFile = SlotSettings::getTermsFile();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $values = array_merge($values, array_intersect_key($_POST, $values));
    $values['role'] = in_array($_POST['role'] ?? '', ['student', 'teacher'], true) ? $_POST['role'] : 'student';
    if ($termsFile && empty($_POST['terms_agreed'])) {
        $error = 'กรุณายอมรับข้อตกลงการใช้งานก่อนสมัครสมาชิก';
    } else {
        $result = Auth::register($_POST);
        if ($result['ok']) {
            flash_set('ok', 'สมัครสำเร็จ! รอการอนุมัติจากผู้ดูแลระบบ');
            header('Location: ' . url('login.php'));
            exit;
        }
        $error = $result['error'];
    }
}

require __DIR__ . '/includes/guest-header.php';
?>
<div class="auth-card auth-card-wide page-anim">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
    <div class="logo-icon" style="width:44px;height:44px;border-radius:11px">
      <i class="bi bi-person-plus-fill" style="color:white;font-size:18px"></i>
    </div>
    <div>
      <h5 style="font-weight:700;color:#0F172A;margin:0">สมัครสมาชิก</h5>
      <p style="color:var(--bs-secondary-color);font-size:13px;margin:0">AI Pro Time-Sharing System</p>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger" style="font-size:13px"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" id="regForm">
    <?= Csrf::field() ?>
    <input type="hidden" name="role" id="roleInput" value="<?= e($values['role']) ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:18px">
      <button type="button" id="btnStudent" onclick="setRole('student')"
        class="btn" style="font-size:13px;font-weight:600;padding:9px;border-radius:8px;border:2px solid transparent;display:flex;align-items:center;justify-content:center;gap:7px">
        <i class="bi bi-mortarboard-fill"></i>นักศึกษา
      </button>
      <button type="button" id="btnTeacher" onclick="setRole('teacher')"
        class="btn" style="font-size:13px;font-weight:600;padding:9px;border-radius:8px;border:2px solid transparent;display:flex;align-items:center;justify-content:center;gap:7px">
        <i class="bi bi-person-workspace"></i>ครูผู้สอน
      </button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">ชื่อ-นามสกุล <span style="color:#DC2626">*</span></label>
        <input type="text" name="name" required value="<?= e($values['name']) ?>" class="form-control" placeholder="สมชาย ใจดี" style="font-size:13px">
      </div>
      <div id="idField">
        <label id="idLabel" style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">รหัสนักศึกษา <span style="color:#DC2626">*</span></label>
        <input type="text" name="student_id" id="idInput" value="<?= e($values['student_id']) ?>" class="form-control" placeholder="6501CS001" style="font-size:13px">
      </div>
      <div>
        <label id="majorLabel" style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">สาขาวิชา <span style="color:#DC2626">*</span></label>
        <select name="major" id="majorSelect" class="form-select" style="font-size:13px">
          <?php foreach ($majors as $m): ?>
            <option <?= $values['major'] === $m ? 'selected' : '' ?>><?= e($m) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="major_text" id="majorText" value="" class="form-control" placeholder="แผนก/ภาควิชา" style="font-size:13px;display:none">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">เบอร์โทรศัพท์</label>
        <input type="tel" name="phone" value="<?= e($values['phone']) ?>" class="form-control" placeholder="08X-XXX-XXXX" style="font-size:13px">
      </div>
      <div style="grid-column:span 2">
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">อีเมล <span style="color:#DC2626">*</span></label>
        <input type="email" name="email" required value="<?= e($values['email']) ?>" class="form-control" placeholder="student@rvc.ac.th" style="font-size:13px">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">รหัสผ่าน <span style="color:#DC2626">*</span></label>
        <input type="password" name="password" required class="form-control" placeholder="••••••••" style="font-size:13px">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">ยืนยันรหัสผ่าน <span style="color:#DC2626">*</span></label>
        <input type="password" name="password_confirm" required class="form-control" placeholder="••••••••" style="font-size:13px">
      </div>
    </div>
    <div style="background:#FFF7ED;border-radius:8px;padding:10px 14px;margin-bottom:18px;font-size:12px;color:#92400E;display:flex;gap:8px;align-items:flex-start">
      <i class="bi bi-info-circle-fill" style="margin-top:1px;flex-shrink:0"></i>
      <span>หลังสมัครสมาชิก บัญชีของคุณจะอยู่ในสถานะ <strong>รอการอนุมัติ</strong> จนกว่าผู้ดูแลระบบจะตรวจสอบและอนุมัติ</span>
    </div>
    <?php if ($termsFile): ?>
    <div style="background:var(--bs-secondary-bg);border:1px solid var(--bs-border-color);border-radius:8px;padding:12px 14px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
      <input class="form-check-input" type="checkbox" name="terms_agreed" id="terms_agreed" value="1" required style="margin-top:2px;flex-shrink:0;width:16px;height:16px">
      <label for="terms_agreed" style="font-size:13px;color:var(--bs-body-color);margin:0;cursor:pointer">
        ฉันได้อ่านและยอมรับ
        <a href="<?= url('uploads/terms/' . $termsFile) ?>" target="_blank" style="color:#2563EB;font-weight:600;text-decoration:none">
          <i class="bi bi-file-earmark-pdf me-1"></i>ข้อตกลงการใช้งาน
        </a>
        ของระบบ AI Pro Time-Sharing
      </label>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary w-100" style="background:linear-gradient(90deg,#2563EB,#0EA5E9);border:none;font-weight:600;padding:11px;margin-bottom:12px">
      <i class="bi bi-person-check-fill me-2"></i>สมัครสมาชิก
    </button>
  </form>
  <p style="text-align:center;font-size:13px;color:var(--bs-secondary-color);margin:0">
    มีบัญชีแล้ว? <a href="<?= url('login.php') ?>" style="color:#2563EB;font-weight:600;text-decoration:none">เข้าสู่ระบบ</a>
  </p>
</div>
<script>
(function(){
  var STUDENT_MAJORS = <?= json_encode($majors, JSON_UNESCAPED_UNICODE) ?>;
  var initial = <?= json_encode($values['role']) ?>;

  function setRole(role) {
    document.getElementById('roleInput').value = role;

    var isTeacher = role === 'teacher';
    var btnS = document.getElementById('btnStudent');
    var btnT = document.getElementById('btnTeacher');
    var idLabel = document.getElementById('idLabel');
    var idInput = document.getElementById('idInput');
    var majorLabel = document.getElementById('majorLabel');
    var majorSelect = document.getElementById('majorSelect');
    var majorText = document.getElementById('majorText');

    // Toggle button styles
    btnS.style.background = isTeacher ? 'var(--bs-secondary-bg)' : '#2563EB';
    btnS.style.color = isTeacher ? 'var(--bs-secondary-color)' : 'white';
    btnS.style.borderColor = isTeacher ? 'var(--bs-border-color)' : '#2563EB';
    btnT.style.background = isTeacher ? '#059669' : 'var(--bs-secondary-bg)';
    btnT.style.color = isTeacher ? 'white' : 'var(--bs-secondary-color)';
    btnT.style.borderColor = isTeacher ? '#059669' : 'var(--bs-border-color)';

    // ID field
    idLabel.innerHTML = (isTeacher ? 'รหัสพนักงาน' : 'รหัสนักศึกษา') + (isTeacher ? '' : ' <span style="color:#DC2626">*</span>');
    idInput.placeholder = isTeacher ? 'T001' : '6501CS001';
    idInput.required = !isTeacher;

    // Major vs Department
    majorLabel.innerHTML = (isTeacher ? 'แผนก/ภาควิชา' : 'สาขาวิชา') + ' <span style="color:#DC2626">*</span>';
    if (isTeacher) {
      majorSelect.style.display = 'none';
      majorSelect.disabled = true;
      majorText.style.display = '';
      majorText.required = true;
      majorText.name = 'major';
      majorSelect.name = 'major_unused';
    } else {
      majorSelect.style.display = '';
      majorSelect.disabled = false;
      majorSelect.name = 'major';
      majorText.style.display = 'none';
      majorText.required = false;
      majorText.name = 'major_text';
    }
  }

  window.setRole = setRole;
  setRole(initial);
})();
</script>
<?php require __DIR__ . '/includes/guest-footer.php'; ?>
