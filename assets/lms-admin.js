/**
 * Admin-side LMS modal wiring: shows the fields that belong to the selected content
 * block type, and populates the block / question modals from the clicked row's
 * data-* attributes. Same data-attribute pattern the booking and AI-account modals use.
 *
 * Purely presentational — every rule enforced here is re-checked server-side in
 * LmsContent::saveBlock() and LmsQuestion::save().
 */
(function () {
  "use strict";

  // Which field groups each block type shows, plus its textarea label and hint.
  var BLOCK_FIELDS = {
    heading:   { show: ["text", "meta-heading"], label: "ข้อความหัวข้อ", hint: "" },
    paragraph: { show: ["text"], label: "เนื้อหาย่อหน้า", hint: "ขึ้นบรรทัดใหม่ได้ตามปกติ" },
    list:      { show: ["text", "meta-list"], label: "รายการ", hint: "พิมพ์หนึ่งรายการต่อหนึ่งบรรทัด" },
    code:      { show: ["text", "meta-code"], label: "โค้ด", hint: "แสดงเป็นตัวอักษรความกว้างเท่ากันในกล่องสีเทา" },
    callout:   { show: ["text", "meta-callout", "link"], label: "ข้อความในกล่องเน้น", hint: "" },
    youtube:   { show: ["link", "text"], label: "คำบรรยายใต้วิดีโอ", hint: "ไม่บังคับ", optionalText: true },
    image:     { show: ["image", "text"], label: "คำบรรยายใต้ภาพ", hint: "ไม่บังคับ", optionalText: true }
  };

  function applyBlockType(type) {
    var cfg = BLOCK_FIELDS[type] || BLOCK_FIELDS.paragraph;

    document.querySelectorAll("#blockModal [data-blk]").forEach(function (el) {
      el.style.display = cfg.show.indexOf(el.dataset.blk) === -1 ? "none" : "";
    });

    var label = document.getElementById("blockTextLabel");
    var req   = document.getElementById("blockTextReq");
    var hint  = document.getElementById("blockTextHint");
    var text  = document.getElementById("blockText");
    if (label) { label.textContent = cfg.label; }
    if (req)   { req.style.display = cfg.optionalText ? "none" : ""; }
    if (hint)  { hint.textContent = cfg.hint || ""; }
    if (text)  { text.rows = type === "code" || type === "list" ? 6 : 5; }

    var linkLabel = document.getElementById("blockLinkLabel");
    if (linkLabel) {
      linkLabel.textContent = type === "youtube" ? "ลิงก์วิดีโอ YouTube *" : "ลิงก์ปลายทาง (ไม่บังคับ)";
    }
    var linkInput = document.getElementById("blockLinkUrl");
    if (linkInput) {
      linkInput.placeholder = type === "youtube" ? "https://www.youtube.com/watch?v=…" : "https://…";
    }
  }

  var typeSelect = document.getElementById("blockType");
  if (typeSelect) {
    typeSelect.addEventListener("change", function () { applyBlockType(this.value); });
  }

  function setMeta(type, value) {
    var el = document.querySelector('#blockModal [data-meta-for="' + type + '"]');
    if (el) { el.value = value || (el.tagName === "SELECT" ? el.options[0].value : ""); }
  }

  document.addEventListener("click", function (e) {
    // ── content block modal ──
    var blk = e.target.closest('[data-bs-target="#blockModal"]');
    if (blk) {
      var d = blk.dataset;
      var adding = d.mode !== "edit";
      var type = adding ? "paragraph" : d.type;

      document.getElementById("blockModalTitle").textContent = adding ? "เพิ่มบล็อกเนื้อหา" : "แก้ไขบล็อกเนื้อหา";
      document.getElementById("blockId").value          = adding ? "" : d.id;
      document.getElementById("blockType").value        = type;
      document.getElementById("blockText").value        = adding ? "" : (d.text || "");
      document.getElementById("blockLinkUrl").value     = adding ? "" : (d.linkUrl || "");
      document.getElementById("blockImageUrl").value    = adding ? "" : (d.imageUrl || "");
      document.getElementById("blockSourceUrl").value   = adding ? "" : (d.sourceUrl || "");
      document.getElementById("blockSourceLabel").value = adding ? "" : (d.sourceLabel || "");

      var current = document.getElementById("blockImageCurrent");
      if (current) {
        current.textContent = !adding && d.imageFile
          ? "ไฟล์ปัจจุบัน: " + d.imageFile + " (อัปโหลดใหม่เพื่อแทนที่ หรือใส่ลิงก์เพื่อเปลี่ยนไปใช้รูปภายนอก)"
          : "";
      }

      // Only the control belonging to this type matters; reset the rest to their default.
      ["heading", "list", "callout", "code"].forEach(function (t) { setMeta(t, null); });
      if (!adding) { setMeta(type, d.meta); }

      applyBlockType(type);
    }

    // ── question modal (review bank and pre/post bank share these field ids) ──
    var q = e.target.closest('[data-bs-target="#questionModal"]');
    if (q) {
      var qd = q.dataset;
      var newQ = qd.mode !== "edit";

      var title = document.getElementById("questionModalTitle");
      if (title) { title.textContent = newQ ? "เพิ่มคำถาม" : "แก้ไขคำถาม"; }
      document.getElementById("questionId").value = newQ ? "" : qd.id;
      document.getElementById("qText").value = newQ ? "" : (qd.text || "");
      var expl = document.getElementById("qExplanation");
      if (expl) { expl.value = newQ ? "" : (qd.explanation || ""); }

      var choices = [];
      if (!newQ && qd.choices) {
        try { choices = JSON.parse(qd.choices); } catch (err) { choices = []; }
      }
      for (var i = 0; i < 6; i++) {
        var input = document.getElementById("qChoice" + i);
        var radio = document.getElementById("qCorrect" + i);
        if (!input) { continue; }
        input.value = choices[i] ? choices[i].t : "";
        if (radio) { radio.checked = !!(choices[i] && choices[i].c); }
      }
    }
  });

  // A fresh "add block" modal opens on the default type.
  if (typeSelect) { applyBlockType(typeSelect.value); }
})();
