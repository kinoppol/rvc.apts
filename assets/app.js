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

  // ── Booking confirmation modal (student/booking.php) — click the whole slot cell, then pick a Pool ──
  var bookModal = document.getElementById("bookSlotModal");
  if (bookModal) {
    document.querySelectorAll(".slot-cell").forEach(function (cell) {
      cell.addEventListener("click", function () {
        document.getElementById("bookModalDate").value = cell.dataset.date;
        document.getElementById("bookModalSlotIndex").value = cell.dataset.slotIndex;
        document.getElementById("bookModalDayLabel").textContent = cell.dataset.dayLabel;
        document.getElementById("bookModalSlotTime").textContent = cell.dataset.slotLabel + " (" + cell.dataset.slotTime + ")";

        var select = document.getElementById("bookModalPoolSelect");
        if (select) {
          select.innerHTML = "";
          var pools = [];
          try { pools = JSON.parse(cell.dataset.pools || "[]"); } catch (e) { pools = []; }
          pools.forEach(function (p) {
            var opt = document.createElement("option");
            opt.value = p.id;
            opt.textContent = p.name;
            select.appendChild(opt);
          });
        }
        new bootstrap.Modal(bookModal).show();
      });
    });
  }

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
      new bootstrap.Modal(modalEl).show();
    });
  });

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

  // ── Report submission modal (student/my-bookings.php) ──
  document.querySelectorAll("[data-report-booking]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var modalEl = document.getElementById("reportModal");
      if (!modalEl) return;
      modalEl.querySelector("[name=id]").value = btn.dataset.id;
      var meta = document.getElementById("reportModalMeta");
      if (meta) meta.textContent = btn.dataset.meta || "";
      new bootstrap.Modal(modalEl).show();
    });
  });

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
