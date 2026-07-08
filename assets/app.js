(function () {
  "use strict";

  var root = document.getElementById("appRoot");

  // ── Sidebar collapse ──
  var sidebar = document.getElementById("sidebar");
  var toggleBtn = document.getElementById("sidebarToggle");
  if (sidebar && toggleBtn) {
    if (localStorage.getItem("sidebarCollapsed") === "1") sidebar.classList.add("collapsed");
    toggleBtn.addEventListener("click", function () {
      sidebar.classList.toggle("collapsed");
      localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed") ? "1" : "0");
    });
  }

  // ── Theme ──
  var THEME_SEL_BG = "#2563EB", THEME_SEL_TXT = "white";
  var THEME_IDLE_BG = "transparent", THEME_IDLE_TXT = "var(--bs-secondary-color)";

  function resolveTheme(pref) {
    if (pref === "system") {
      return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
    }
    return pref;
  }

  function applyTheme(pref) {
    if (root) root.setAttribute("data-bs-theme", resolveTheme(pref));
    document.querySelectorAll(".theme-btn").forEach(function (btn) {
      var active = btn.dataset.theme === pref;
      btn.style.background = active ? THEME_SEL_BG : THEME_IDLE_BG;
      btn.style.color = active ? THEME_SEL_TXT : THEME_IDLE_TXT;
    });
    window.dispatchEvent(new CustomEvent("app:theme-changed", { detail: { theme: resolveTheme(pref) } }));
  }

  var storedTheme = localStorage.getItem("theme") || "system";
  applyTheme(storedTheme);

  document.querySelectorAll(".theme-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      localStorage.setItem("theme", btn.dataset.theme);
      applyTheme(btn.dataset.theme);
    });
  });

  window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", function () {
    if ((localStorage.getItem("theme") || "system") === "system") applyTheme("system");
  });

  // ── Toast auto-dismiss ──
  var toast = document.getElementById("appToast");
  if (toast) {
    setTimeout(function () {
      toast.style.transition = "opacity .25s ease";
      toast.style.opacity = "0";
      setTimeout(function () { toast.remove(); }, 250);
    }, 3200);
  }

  // ── Booking confirmation modal (student/booking.php) — click the whole slot cell, then check off Pools ──
  var bookModal = document.getElementById("bookSlotModal");
  if (bookModal) {
    var applySelectLimit = function (container, maxSelect) {
      var boxes = container.querySelectorAll("input[type=checkbox]");
      var checkedCount = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length;
      boxes.forEach(function (b) {
        b.disabled = !b.checked && checkedCount >= maxSelect;
      });
    };

    document.querySelectorAll(".slot-cell").forEach(function (cell) {
      cell.addEventListener("click", function () {
        document.getElementById("bookModalDate").value = cell.dataset.date;
        document.getElementById("bookModalSlotIndex").value = cell.dataset.slotIndex;
        document.getElementById("bookModalDayLabel").textContent = cell.dataset.dayLabel;
        document.getElementById("bookModalSlotTime").textContent = cell.dataset.slotLabel + " (" + cell.dataset.slotTime + ")";

        var maxSelect = parseInt(cell.dataset.maxSelect, 10) || 1;
        var hint = document.getElementById("bookModalMaxHint");
        if (hint) hint.textContent = "เลือกได้สูงสุด " + maxSelect + " Pool";

        var container = document.getElementById("bookModalPoolChecks");
        if (container) {
          container.innerHTML = "";
          var pools = [];
          try { pools = JSON.parse(cell.dataset.pools || "[]"); } catch (e) { pools = []; }
          pools.forEach(function (p) {
            var label = document.createElement("label");
            label.style.cssText = "display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer";
            var box = document.createElement("input");
            box.type = "checkbox";
            box.name = "ai_account_id[]";
            box.value = p.id;
            box.addEventListener("change", function () { applySelectLimit(container, maxSelect); });
            label.appendChild(box);
            label.appendChild(document.createTextNode(p.name));
            container.appendChild(label);
          });
          applySelectLimit(container, maxSelect);
        }
        new bootstrap.Modal(bookModal).show();
      });
    });
  }

  // ── Change AI-account password modal (admin/ai-accounts.php) — auto-generate, copy, save ──
  function generateSecurePassword(length) {
    length = length || 12;
    // Avoid visually ambiguous characters (0/O, 1/l/I); one guaranteed char from each set keeps it
    // secure without needing extra length, then the rest is filled from the combined pool and shuffled.
    var sets = ["ABCDEFGHJKLMNPQRSTUVWXYZ", "abcdefghijkmnpqrstuvwxyz", "23456789", "!@#$%^&*-_="];
    var pick = function (str) {
      var arr = new Uint32Array(1);
      crypto.getRandomValues(arr);
      return str[arr[0] % str.length];
    };
    var all = sets.join("");
    var chars = sets.map(function (s) { return pick(s); });
    while (chars.length < length) chars.push(pick(all));
    for (var i = chars.length - 1; i > 0; i--) {
      var arr = new Uint32Array(1);
      crypto.getRandomValues(arr);
      var j = arr[0] % (i + 1);
      var tmp = chars[i]; chars[i] = chars[j]; chars[j] = tmp;
    }
    return chars.join("");
  }

  var changePwModal = document.getElementById("changePwModal");
  if (changePwModal) {
    var pwValue = document.getElementById("changePwValue");
    var pwHint = document.getElementById("changePwCopiedHint");
    var regenerate = function () {
      pwValue.value = generateSecurePassword(12);
      if (pwHint) pwHint.style.display = "none";
    };

    document.querySelectorAll("[data-change-pw]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        document.getElementById("changePwId").value = btn.dataset.id;
        document.getElementById("changePwAccountName").textContent = btn.dataset.name;
        regenerate();
        new bootstrap.Modal(changePwModal).show();
      });
    });

    var regenBtn = document.getElementById("changePwRegenBtn");
    if (regenBtn) regenBtn.addEventListener("click", regenerate);

    var copyBtn = document.getElementById("changePwCopyBtn");
    if (copyBtn) {
      copyBtn.addEventListener("click", function () {
        pwValue.select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(pwValue.value);
        } else {
          document.execCommand("copy");
        }
        if (pwHint) pwHint.style.display = "block";
      });
    }
  }

  // ── Generate/copy buttons on the "รหัสผ่านบัญชี" field (add & edit AI-account modals) ──
  // Delegated so it works regardless of when the add/edit modal markup is in the DOM.
  document.addEventListener("click", function (e) {
    var genBtn = e.target.closest("[data-pw-generate]");
    if (genBtn) {
      var genInput = document.querySelector(genBtn.dataset.pwGenerate);
      if (genInput) genInput.value = generateSecurePassword(12);
      return;
    }
    var copyBtn = e.target.closest("[data-pw-copy]");
    if (copyBtn) {
      var copyInput = document.querySelector(copyBtn.dataset.pwCopy);
      if (copyInput && copyInput.value) {
        copyInput.select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(copyInput.value);
        } else {
          document.execCommand("copy");
        }
      }
    }
  });

  // ── Avatar emoji picker (admin/ai-accounts.php) ──
  function applyAvatarSelection(container, selectedEmoji) {
    container.querySelectorAll(".avatar-emoji-btn").forEach(function (b) {
      var match = b.dataset.emoji === selectedEmoji && selectedEmoji !== "";
      b.style.borderColor = match ? "#2563EB" : "transparent";
      b.style.background  = match ? "#EFF6FF" : "";
    });
  }

  document.querySelectorAll(".avatar-emoji-picker").forEach(function (picker) {
    picker.querySelectorAll(".avatar-emoji-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var inputId = picker.dataset.input;
        var inputEl = document.getElementById(inputId);
        if (!inputEl) return;
        var emoji = btn.dataset.emoji; // "" for the clear button
        inputEl.value = emoji;
        applyAvatarSelection(picker.closest(".modal-body") || picker.parentElement, emoji);
      });
    });
  });

  // ── AI account edit modal (admin/ai-accounts.php) ──
  document.querySelectorAll("[data-edit-account]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var modalEl = document.getElementById("editAccountModal");
      var set = function (name, val) {
        var f = modalEl.querySelector("[name=" + name + "]");
        if (f) f.value = val == null ? "" : val;
      };
      set("id", btn.dataset.id);
      set("name", btn.dataset.name);
      set("provider_id", btn.dataset.providerId);
      set("email", btn.dataset.email);
      set("account_password", btn.dataset.password);
      set("status", btn.dataset.status);
      set("expires_at", btn.dataset.expires);
      set("password_reminder", btn.dataset.reminder);
      set("monthly_cost", btn.dataset.monthlyCost);
      set("cost_per_slot", btn.dataset.costPerSlot);
      set("capacity", btn.dataset.capacity || "1");
      // Avatar picker
      var avatarVal = btn.dataset.avatar || "";
      set("avatar_emoji", avatarVal);
      applyAvatarSelection(modalEl, avatarVal);
      new bootstrap.Modal(modalEl).show();
    });
  });

  // ── Bulk reset all AI-account passwords (admin/ai-accounts.php) ──
  var bulkResetBtn = document.getElementById("bulkResetPwBtn");
  if (bulkResetBtn) {
    var bulkAccounts = JSON.parse(bulkResetBtn.dataset.accounts || "[]");

    function escHtml(str) {
      return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function buildBulkResetTable() {
      var tableEl = document.getElementById("bulkResetPwTable");
      if (!tableEl) return;
      var html = '<table style="width:100%;border-collapse:collapse;font-size:13px">';
      html += '<thead><tr>'
        + '<th style="padding:6px 8px;border-bottom:2px solid var(--bs-border-color);text-align:left;font-size:11px;color:var(--bs-secondary-color)">บัญชี</th>'
        + '<th style="padding:6px 8px;border-bottom:2px solid var(--bs-border-color);text-align:left;font-size:11px;color:var(--bs-secondary-color)">รหัสผ่านใหม่</th>'
        + '<th style="width:38px;border-bottom:2px solid var(--bs-border-color)"></th>'
        + '</tr></thead><tbody>';
      bulkAccounts.forEach(function (ac) {
        var pw = generateSecurePassword(12);
        html += '<tr data-bulk-id="' + ac.id + '">'
          + '<td style="padding:7px 8px;border-bottom:1px solid var(--bs-border-color);font-weight:600">' + escHtml(ac.name)
          + '<input type="hidden" name="passwords[' + ac.id + ']" value="' + escHtml(pw) + '"></td>'
          + '<td style="padding:7px 8px;border-bottom:1px solid var(--bs-border-color)"><code class="bulk-pw-val" style="font-size:13px;letter-spacing:.3px;word-break:break-all">' + escHtml(pw) + '</code></td>'
          + '<td style="padding:7px 4px;border-bottom:1px solid var(--bs-border-color)"><button type="button" class="btn btn-sm btn-outline-secondary bulk-regen-one" title="สุ่มใหม่"><i class="bi bi-arrow-clockwise"></i></button></td>'
          + '</tr>';
      });
      html += '</tbody></table>';
      tableEl.innerHTML = html;
      tableEl.querySelectorAll(".bulk-regen-one").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var row = btn.closest("tr");
          var newPw = generateSecurePassword(12);
          row.querySelector("input[type=hidden]").value = newPw;
          row.querySelector(".bulk-pw-val").textContent = newPw;
        });
      });
    }

    bulkResetBtn.addEventListener("click", function () {
      buildBulkResetTable();
      new bootstrap.Modal(document.getElementById("bulkResetPwModal")).show();
    });

    var regenAllBtn = document.getElementById("bulkResetRegenAll");
    if (regenAllBtn) {
      regenAllBtn.addEventListener("click", buildBulkResetTable);
    }
  }

  // ── Shared-password reveal toggles (admin/ai-accounts.php) ──
  document.querySelectorAll(".pw-toggle").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var field = btn.parentElement.querySelector(".pw-field");
      if (!field) return;
      var show = field.type === "password";
      field.type = show ? "text" : "password";
      var icon = btn.querySelector("i");
      if (icon) icon.className = show ? "bi bi-eye-slash" : "bi bi-eye";
    });
  });

  // ── User-group add/edit modal (admin/groups.php) ──
  var groupModal = document.getElementById("groupModal");
  if (groupModal) {
    var fillGroup = function (data, isEdit) {
      groupModal.querySelector("[name=id]").value = data.id || "";
      groupModal.querySelector("[name=name]").value = data.name || "";
      groupModal.querySelector("[name=description]").value = data.description || "";
      groupModal.querySelector("[name=weekly_quota]").value = data.weekly_quota || "";
      groupModal.querySelector("[name=max_advance_days]").value = data.max_advance_days || "";
      groupModal.querySelector("[name=max_concurrent]").value = data.max_concurrent || "1";
      var pools = (data.pools || "").split(",").filter(Boolean);
      groupModal.querySelectorAll(".group-pool-cb").forEach(function (cb) {
        cb.checked = pools.indexOf(cb.value) !== -1;
      });
      var title = document.getElementById("groupModalTitle");
      if (title) title.textContent = isEdit ? "แก้ไขกลุ่ม" : "เพิ่มกลุ่ม";
    };
    document.querySelectorAll("[data-edit-group]").forEach(function (btn) {
      btn.addEventListener("click", function () { fillGroup(btn.dataset, true); });
    });
    document.querySelectorAll("[data-add-group]").forEach(function (btn) {
      btn.addEventListener("click", function () { fillGroup({}, false); });
    });
  }

  // ── Reset-password modal (admin/members.php) ──
  document.querySelectorAll("[data-reset-pw]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var modalEl = document.getElementById("resetPwModal");
      if (!modalEl) return;
      modalEl.querySelector("[name=id]").value = btn.dataset.id;
      modalEl.querySelector("[name=new_password]").value = "";
      var nameEl = document.getElementById("resetPwName");
      if (nameEl) nameEl.textContent = btn.dataset.name || "สมาชิก";
      new bootstrap.Modal(modalEl).show();
    });
  });

  // ── Edit-credentials modal (admin/members.php) ──
  document.querySelectorAll("[data-edit-cred]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var modalEl = document.getElementById("editCredModal");
      if (!modalEl) return;
      document.getElementById("editCredId").value = btn.dataset.id;
      document.getElementById("editCredName").textContent = btn.dataset.name || "สมาชิก";
      document.getElementById("editCredEmail").value = btn.dataset.email || "";
      document.getElementById("editCredPw").value = "";
      new bootstrap.Modal(modalEl).show();
    });
  });

  // ── Report submission modal (student/my-bookings.php) ──
  document.querySelectorAll("[data-report-booking]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var modalEl = document.getElementById("reportModal");
      if (!modalEl) return;
      modalEl.querySelector("[name=id]").value = btn.dataset.id;
      var meta = document.getElementById("reportModalMeta");
      if (meta) meta.textContent = btn.dataset.meta || "";
      var textarea = modalEl.querySelector("[name=report_text]");
      if (textarea) textarea.value = btn.dataset.reportText || "";
      var fileInput = modalEl.querySelector("[name=report_file]");
      if (fileInput) fileInput.value = ""; // reset file picker; existing file is kept server-side
      var tsStart = modalEl.querySelector("[name=token_start_pct]");
      if (tsStart) tsStart.value = btn.dataset.tokenStart || "";
      var tsEnd = modalEl.querySelector("[name=token_end_pct]");
      if (tsEnd) tsEnd.value = btn.dataset.tokenEnd || "";
      var tsReset = modalEl.querySelector("[name=token_reset_at]");
      if (tsReset) tsReset.value = btn.dataset.tokenReset || "";
      new bootstrap.Modal(modalEl).show();
    });
  });

  // ── Issue / problem report modal (student/my-bookings.php) ──
  document.querySelectorAll("[data-issue-booking]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var modalEl = document.getElementById("issueModal");
      if (!modalEl) return;
      document.getElementById("issueBookingId").value = btn.dataset.id;
      var meta = document.getElementById("issueModalMeta");
      if (meta) meta.textContent = btn.dataset.meta || "";
      var textarea = document.getElementById("issueText");
      if (textarea) textarea.value = btn.dataset.issueText || "";
      // Reset file input so previous selection doesn't carry over
      var fileInput = document.getElementById("issueFileInput");
      if (fileInput) fileInput.value = "";
      // Show existing-files list when re-editing a submitted issue
      var existingWrap = document.getElementById("issueExistingFiles");
      var existingList = document.getElementById("issueExistingFilesList");
      if (existingWrap && existingList) {
        var filesJson = btn.dataset.issueFiles || "[]";
        var files = [];
        try { files = JSON.parse(filesJson); } catch (e) {}
        if (files.length) {
          existingList.innerHTML = "";
          var base = modalEl.dataset.reportsBase || "";
          files.forEach(function (f) {
            var a = document.createElement("a");
            a.href = base + f.filename;
            a.target = "_blank";
            a.textContent = f.original_name || f.filename;
            a.style.cssText = "color:#2563EB;text-decoration:none;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block";
            existingList.appendChild(a);
          });
          existingWrap.style.display = "block";
        } else {
          existingWrap.style.display = "none";
        }
      }
      new bootstrap.Modal(modalEl).show();
    });
  });

  // ── Check-in confirmation modal (intercepts forms with data-checkin-confirm) ──
  var checkinModal = document.getElementById("checkinConfirmModal");
  var checkinConfirmBtn = document.getElementById("checkinConfirmBtn");
  var pendingCheckinForm = null;
  if (checkinModal && checkinConfirmBtn) {
    document.addEventListener("submit", function (e) {
      var form = e.target;
      if (form.hasAttribute("data-checkin-confirm") && !form._checkinOk) {
        e.preventDefault();
        pendingCheckinForm = form;
        new bootstrap.Modal(checkinModal).show();
      }
    });
    checkinConfirmBtn.addEventListener("click", function () {
      bootstrap.Modal.getInstance(checkinModal).hide();
      if (pendingCheckinForm) {
        pendingCheckinForm._checkinOk = true;
        pendingCheckinForm.requestSubmit();
        pendingCheckinForm = null;
      }
    });
  }

  // ── Check-out confirmation modal (intercepts forms with data-checkout-confirm) ──
  var checkoutModal = document.getElementById("checkoutConfirmModal");
  var checkoutConfirmBtn = document.getElementById("checkoutConfirmBtn");
  var pendingCheckoutForm = null;
  if (checkoutModal && checkoutConfirmBtn) {
    document.addEventListener("submit", function (e) {
      var form = e.target;
      if (form.hasAttribute("data-checkout-confirm") && !form._checkoutOk) {
        e.preventDefault();
        pendingCheckoutForm = form;
        new bootstrap.Modal(checkoutModal).show();
      }
    });
    checkoutConfirmBtn.addEventListener("click", function () {
      bootstrap.Modal.getInstance(checkoutModal).hide();
      if (pendingCheckoutForm) {
        pendingCheckoutForm._checkoutOk = true;
        pendingCheckoutForm.requestSubmit();
        pendingCheckoutForm = null;
      }
    });
  }

  // ── Generic confirm modal (replaces native confirm() on admin/members.php) ──
  var confirmActionModal = document.getElementById("confirmActionModal");
  var confirmActionBtn = document.getElementById("confirmActionBtn");
  var pendingConfirmForm = null;
  if (confirmActionModal && confirmActionBtn) {
    document.addEventListener("submit", function (e) {
      var form = e.target;
      if (form.hasAttribute("data-confirm-modal") && !form._confirmOk) {
        e.preventDefault();
        pendingConfirmForm = form;

        var icon = form.dataset.confirmIcon || "bi-question-circle";
        var color = form.dataset.confirmColor || "#2563EB";
        var title = form.dataset.confirmTitle || "ยืนยัน";
        var msg = form.dataset.confirmMsg || "";
        var btn = form.dataset.confirmBtn || "ยืนยัน";
        var btnCls = form.dataset.confirmBtnCls || "btn-primary";

        var iconEl = document.getElementById("confirmActionIcon");
        if (iconEl) {
          iconEl.style.background = color + "1a";
          iconEl.innerHTML = '<i class="bi ' + icon + '" style="color:' + color + ';font-size:26px"></i>';
        }
        var titleEl = document.getElementById("confirmActionTitle");
        if (titleEl) titleEl.textContent = title;
        var msgEl = document.getElementById("confirmActionMsg");
        if (msgEl) msgEl.textContent = msg;

        confirmActionBtn.className = "btn " + btnCls;
        confirmActionBtn.textContent = btn;
        confirmActionBtn.style.cssText = "font-size:13px;min-width:90px;border-radius:8px;font-weight:600";

        new bootstrap.Modal(confirmActionModal).show();
      }
    });
    confirmActionBtn.addEventListener("click", function () {
      bootstrap.Modal.getInstance(confirmActionModal).hide();
      if (pendingConfirmForm) {
        pendingConfirmForm._confirmOk = true;
        pendingConfirmForm.requestSubmit();
        pendingConfirmForm = null;
      }
    });
  }

  // ── Clipboard copy helper (used by credential copy buttons) ──
  window.copyText = function (btn, text) {
    var orig = btn.innerHTML;
    var done = function () {
      btn.innerHTML = '<i class="bi bi-clipboard-check" style="color:#059669"></i>';
      setTimeout(function () { btn.innerHTML = orig; }, 1500);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        try { done(); } catch (e) {}
      });
    } else {
      done();
    }
  };

  // ── Chart.js init hook for admin/dashboard.php ──
  window.initUsageChart = function (canvasId, labels, datasets) {
    var canvas = document.getElementById(canvasId);
    if (!canvas || !window.Chart) return;
    var render = function () {
      var isDark = root.getAttribute("data-bs-theme") === "dark";
      var gc = isDark ? "rgba(255,255,255,.08)" : "rgba(0,0,0,.06)";
      var tc = isDark ? "#94A3B8" : "#64748B";
      if (canvas._chart) canvas._chart.destroy();
      canvas._chart = new Chart(canvas, {
        type: "bar",
        data: { labels: labels, datasets: datasets },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { position: "top", labels: { color: tc, font: { size: 12 } } } },
          scales: {
            x: { grid: { color: gc }, ticks: { color: tc } },
            y: { grid: { color: gc }, ticks: { color: tc, callback: function (v) { return v + "%"; } }, max: 100, min: 0 },
          },
        },
      });
    };
    render();
    window.addEventListener("app:theme-changed", render);
  };
})();
