<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    header('Location: ' . url('index.php'));
    exit;
}

$majors    = Major::listActive();
$subjects  = Subject::listActive();
$error     = null;
$values    = ['role' => 'student', 'name' => '', 'student_id' => '', 'major_id' => '', 'subject_id' => '', 'subject_new_name' => '', 'phone' => '', 'school_name' => '', 'province' => '', 'email' => ''];
$termsFile = SlotSettings::getTermsFile();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $values = array_merge($values, array_intersect_key($_POST, $values));
    $values['role']       = in_array($_POST['role'] ?? '', ['student', 'teacher'], true) ? $_POST['role'] : 'student';
    $values['major_id']   = (int) ($_POST['major_id'] ?? 0);
    $values['subject_id'] = (int) ($_POST['subject_id'] ?? 0);
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
        <!-- major autocomplete -->
        <div id="majorAutoWrap" style="position:relative">
          <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--bs-tertiary-color);font-size:13px;pointer-events:none"><i class="bi bi-search"></i></span>
          <input type="text" id="majorText" autocomplete="off" class="form-control" placeholder="พิมพ์เพื่อค้นหาสาขาวิชา..." style="font-size:13px;padding-left:33px">
          <input type="hidden" name="major_id" id="majorId" data-selected="<?= (int) $values['major_id'] ?>">
          <div id="majorDrop" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.12);z-index:1050;max-height:220px;overflow-y:auto"></div>
        </div>
        <!-- subject autocomplete -->
        <div id="subjectAutoWrap" style="position:relative;display:none">
          <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--bs-tertiary-color);font-size:13px;pointer-events:none"><i class="bi bi-search"></i></span>
          <input type="text" id="subjectText" autocomplete="off" class="form-control" placeholder="พิมพ์เพื่อค้นหาวิชาสอน..." style="font-size:13px;padding-left:33px">
          <input type="hidden" name="subject_id" id="subjectId" data-selected="<?= (int) $values['subject_id'] ?>">
          <input type="hidden" name="subject_new_name" id="subjectNewName" value="<?= e($values['subject_new_name']) ?>">
          <div id="subjectDrop" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.12);z-index:1050;max-height:220px;overflow-y:auto"></div>
        </div>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">เบอร์โทรศัพท์</label>
        <input type="tel" name="phone" value="<?= e($values['phone']) ?>" class="form-control" placeholder="08X-XXX-XXXX" style="font-size:13px">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">ชื่อสถานศึกษา</label>
        <input type="text" name="school_name" value="<?= e($values['school_name']) ?>" class="form-control" placeholder="โรงเรียน / วิทยาลัย RVC" style="font-size:13px">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px">จังหวัด</label>
        <input type="text" name="province" list="provinceList" value="<?= e($values['province']) ?>" autocomplete="off" class="form-control" placeholder="เลือกหรือพิมพ์จังหวัด" style="font-size:13px">
        <datalist id="provinceList">
          <?php foreach (['กรุงเทพมหานคร','กระบี่','กาญจนบุรี','กาฬสินธุ์','กำแพงเพชร','ขอนแก่น','จันทบุรี','ฉะเชิงเทรา','ชลบุรี','ชัยนาท','ชัยภูมิ','ชุมพร','เชียงราย','เชียงใหม่','ตรัง','ตราด','ตาก','นครนายก','นครปฐม','นครพนม','นครราชสีมา','นครศรีธรรมราช','นครสวรรค์','นนทบุรี','นราธิวาส','น่าน','บึงกาฬ','บุรีรัมย์','ปทุมธานี','ประจวบคีรีขันธ์','ปราจีนบุรี','ปัตตานี','พระนครศรีอยุธยา','พะเยา','พังงา','พัทลุง','พิจิตร','พิษณุโลก','เพชรบุรี','เพชรบูรณ์','แพร่','ภูเก็ต','มหาสารคาม','มุกดาหาร','แม่ฮ่องสอน','ยโสธร','ยะลา','ร้อยเอ็ด','ระนอง','ระยอง','ราชบุรี','ลพบุรี','ลำปาง','ลำพูน','เลย','ศรีสะเกษ','สกลนคร','สงขลา','สตูล','สมุทรปราการ','สมุทรสงคราม','สมุทรสาคร','สระแก้ว','สระบุรี','สิงห์บุรี','สุโขทัย','สุพรรณบุรี','สุราษฎร์ธานี','สุรินทร์','หนองคาย','หนองบัวลำภู','อ่างทอง','อำนาจเจริญ','อุดรธานี','อุตรดิตถ์','อุทัยธานี','อุบลราชธานี'] as $pv): ?>
            <option value="<?= e($pv) ?>">
          <?php endforeach; ?>
        </datalist>
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
  var majorsData   = <?= json_encode(array_values(array_map(fn($m) => ['id' => (int)$m['id'], 'name' => $m['name']], $majors))) ?>;
  var subjectsData = <?= json_encode(array_values(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name']], $subjects))) ?>;
  var subjectNewNameInitial = <?= json_encode($values['subject_new_name']) ?>;

  /* ── Autocomplete widget ─────────────────────────────────── */
  // opts: { allowNew: bool, newNameId: string } — when allowNew, shows "add" row for unmatched input
  function initAutocomplete(textId, dropId, hiddenId, data, opts) {
    opts = opts || {};
    var textEl    = document.getElementById(textId);
    var dropEl    = document.getElementById(dropId);
    var hiddenEl  = document.getElementById(hiddenId);
    var newNameEl = opts.newNameId ? document.getElementById(opts.newNameId) : null;
    var selectedId = parseInt(hiddenEl.dataset.selected || '0', 10);

    if (selectedId) {
      var pre = data.find(function(d) { return d.id === selectedId; });
      if (pre) { textEl.value = pre.name; hiddenEl.value = pre.id; }
    }

    function esc(s) {
      return s.replace(/[&<>"]/g, function(c) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
      });
    }

    function renderDrop(items, rawQ) {
      dropEl.innerHTML = '';
      items.forEach(function(item) {
        var div = document.createElement('div');
        div.textContent = item.name;
        div.style.cssText = 'padding:9px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--bs-border-color)';
        div.addEventListener('mousedown', function(e) {
          e.preventDefault();
          textEl.value   = item.name;
          hiddenEl.value = item.id;
          if (newNameEl) newNameEl.value = '';
          dropEl.style.display = 'none';
          textEl.setCustomValidity('');
        });
        div.addEventListener('mouseenter', function() { div.style.background = '#EFF6FF'; div.style.color = '#2563EB'; });
        div.addEventListener('mouseleave', function() { div.style.background = ''; div.style.color = ''; });
        dropEl.appendChild(div);
      });

      // "Add new" row — only when allowNew, query is non-empty, no exact match
      if (opts.allowNew && newNameEl && rawQ) {
        var exactMatch = data.some(function(d) { return d.name.toLowerCase() === rawQ.toLowerCase(); });
        if (!exactMatch) {
          if (items.length) {
            var sep = document.createElement('div');
            sep.style.cssText = 'height:1px;background:var(--bs-border-color)';
            dropEl.appendChild(sep);
          }
          var addDiv = document.createElement('div');
          addDiv.innerHTML = '<i class="bi bi-plus-circle me-2" style="color:#059669;font-size:13px"></i>'
            + '<span style="font-size:13px">เพิ่ม <strong>"' + esc(rawQ) + '"</strong> เป็นวิชาสอนใหม่</span>';
          addDiv.style.cssText = 'padding:10px 14px;cursor:pointer;display:flex;align-items:center;color:#065f46;background:rgba(5,150,105,.05)';
          addDiv.addEventListener('mousedown', function(e) {
            e.preventDefault();
            hiddenEl.value  = '';
            newNameEl.value = rawQ;
            dropEl.style.display = 'none';
            textEl.setCustomValidity('');
          });
          addDiv.addEventListener('mouseenter', function() { addDiv.style.background = '#ECFDF5'; });
          addDiv.addEventListener('mouseleave', function() { addDiv.style.background = 'rgba(5,150,105,.05)'; });
          dropEl.appendChild(addDiv);
        }
      }

      dropEl.style.display = dropEl.children.length ? 'block' : 'none';
    }

    function filter() {
      var q  = textEl.value.trim();
      var ql = q.toLowerCase();
      renderDrop(q ? data.filter(function(d) { return d.name.toLowerCase().indexOf(ql) !== -1; }) : data, q);
    }

    textEl.addEventListener('focus', filter);
    textEl.addEventListener('input', function() {
      hiddenEl.value = '';
      if (newNameEl) newNameEl.value = '';
      filter();
    });
    textEl.addEventListener('blur', function() {
      setTimeout(function() {
        dropEl.style.display = 'none';
        var hasValue = hiddenEl.value || (newNameEl && newNameEl.value);
        if (!hasValue) { textEl.value = ''; }
      }, 180);
    });
    textEl.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') { dropEl.style.display = 'none'; }
    });

    return {
      clear: function() {
        textEl.value = ''; hiddenEl.value = '';
        if (newNameEl) newNameEl.value = '';
        dropEl.style.display = 'none';
      },
      setRequired: function(r) {
        textEl.required = r;
        if (!r) textEl.setCustomValidity('');
      }
    };
  }

  /* ── Init both widgets ───────────────────────────────────── */
  var majorAC   = initAutocomplete('majorText',   'majorDrop',   'majorId',   majorsData);
  var subjectAC = initAutocomplete('subjectText', 'subjectDrop', 'subjectId', subjectsData,
                                   { allowNew: true, newNameId: 'subjectNewName' });

  // Pre-fill after POST error when teacher had typed a new subject name
  if (subjectNewNameInitial && !parseInt(document.getElementById('subjectId').dataset.selected || '0', 10)) {
    document.getElementById('subjectText').value = subjectNewNameInitial;
  }

  /* ── Role switching ──────────────────────────────────────── */
  var initial = <?= json_encode($values['role']) ?>;

  function setRole(role) {
    document.getElementById('roleInput').value = role;
    var isTeacher = role === 'teacher';

    var btnS = document.getElementById('btnStudent');
    var btnT = document.getElementById('btnTeacher');
    btnS.style.background  = isTeacher ? 'var(--bs-secondary-bg)' : '#2563EB';
    btnS.style.color       = isTeacher ? 'var(--bs-secondary-color)' : 'white';
    btnS.style.borderColor = isTeacher ? 'var(--bs-border-color)' : '#2563EB';
    btnT.style.background  = isTeacher ? '#059669' : 'var(--bs-secondary-bg)';
    btnT.style.color       = isTeacher ? 'white' : 'var(--bs-secondary-color)';
    btnT.style.borderColor = isTeacher ? '#059669' : 'var(--bs-border-color)';

    var idLabel = document.getElementById('idLabel');
    var idInput = document.getElementById('idInput');
    idLabel.innerHTML   = (isTeacher ? 'เลขตำแหน่ง' : 'รหัสนักศึกษา') + (isTeacher ? '' : ' <span style="color:#DC2626">*</span>');
    idInput.placeholder = isTeacher ? 'ไม่บังคับ' : '6501CS001';
    idInput.required    = !isTeacher;

    document.getElementById('majorLabel').innerHTML = (isTeacher ? 'วิชาสอน' : 'สาขาวิชา') + ' <span style="color:#DC2626">*</span>';
    document.getElementById('majorAutoWrap').style.display   = isTeacher ? 'none' : '';
    document.getElementById('subjectAutoWrap').style.display = isTeacher ? '' : 'none';
    majorAC.setRequired(!isTeacher);
    subjectAC.setRequired(isTeacher);
  }

  window.setRole = setRole;
  setRole(initial);

  /* ── Submit guard ────────────────────────────────────────── */
  document.getElementById('regForm').addEventListener('submit', function(e) {
    var isTeacher = document.getElementById('roleInput').value === 'teacher';
    if (isTeacher) {
      var hasId  = !!document.getElementById('subjectId').value;
      var hasNew = !!document.getElementById('subjectNewName').value;
      if (!hasId && !hasNew) {
        document.getElementById('subjectText').setCustomValidity('กรุณาเลือกหรือพิมพ์ชื่อวิชาสอน');
        document.getElementById('subjectText').reportValidity();
        e.preventDefault();
      }
    } else {
      if (!document.getElementById('majorId').value) {
        document.getElementById('majorText').setCustomValidity('กรุณาเลือกสาขาวิชาจากรายการ');
        document.getElementById('majorText').reportValidity();
        e.preventDefault();
      }
    }
  });
})();
</script>
<?php require __DIR__ . '/includes/guest-footer.php'; ?>
