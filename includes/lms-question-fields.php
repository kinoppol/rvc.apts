<?php
/**
 * The multiple-choice question form body, shared by the review-question modal on
 * admin/lms-topics.php and the pre/post bank modal on admin/lms-questions.php.
 *
 * Six fixed choice rows: leaving a row blank is how an admin removes that choice, and
 * exactly one radio must be selected — LmsQuestion::save() enforces both server-side.
 * The ids here are what assets/lms-admin.js populates when an edit button is clicked.
 */
?>
<label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin-bottom:6px">โจทย์คำถาม *</label>
<textarea name="question_text" id="qText" rows="3" required class="form-control" style="font-size:13px"></textarea>

<label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin:16px 0 6px">
  ตัวเลือก — เลือกวงกลมหน้าข้อที่ถูกต้อง (2-6 ข้อ)
</label>
<div id="qChoices">
  <?php for ($i = 0; $i < 6; $i++): ?>
    <div style="display:flex;align-items:center;gap:9px;margin-bottom:7px">
      <input type="radio" name="correct_index" value="<?= $i ?>" id="qCorrect<?= $i ?>" class="form-check-input"
             style="flex-shrink:0;margin:0;width:17px;height:17px" title="ข้อนี้คือคำตอบที่ถูก">
      <input type="text" name="choice_text[]" id="qChoice<?= $i ?>" class="form-control" style="font-size:13px"
             placeholder="<?= $i < 2 ? 'ตัวเลือกที่ ' . ($i + 1) . ' (จำเป็น)' : 'ตัวเลือกที่ ' . ($i + 1) . ' (เว้นว่างได้)' ?>"
             autocomplete="off">
    </div>
  <?php endfor; ?>
</div>
<div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:2px">
  เว้นช่องว่างไว้หากต้องการตัวเลือกน้อยกว่า 6 ข้อ · ระบบจะสลับลำดับตัวเลือกใหม่ทุกครั้งที่นักศึกษาทำแบบทดสอบ
</div>

<label style="font-size:12px;font-weight:600;color:var(--bs-secondary-color);display:block;margin:16px 0 6px">
  คำอธิบายเฉลย <span style="font-weight:400;color:var(--bs-tertiary-color)">(แสดงหลังส่งคำตอบแล้วเท่านั้น)</span>
</label>
<textarea name="explanation" id="qExplanation" rows="2" class="form-control" style="font-size:13px"
          placeholder="อธิบายว่าทำไมข้อนี้จึงถูก เพื่อให้ผู้เรียนเข้าใจมากขึ้น"></textarea>
