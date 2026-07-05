      </div>
    </main>
  </div>

  <!-- Check-in confirmation modal (shared) -->
  <div class="modal fade" id="checkinConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
      <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.18)">
        <div class="modal-body" style="padding:32px 28px 20px;text-align:center">
          <div style="width:60px;height:60px;background:#DCFCE7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px">
            <i class="bi bi-qr-code-scan" style="font-size:26px;color:#059669"></i>
          </div>
          <h6 style="font-weight:700;font-size:17px;margin:0 0 10px">ยืนยันการเช็คอิน</h6>
          <p style="font-size:13px;color:var(--bs-secondary-color);margin:0;line-height:1.6">ระบบจะบันทึกเวลาเริ่มใช้งาน<br><strong style="color:var(--bs-body-color)">ทันทีที่กดยืนยัน</strong> — ไม่สามารถย้อนกลับได้</p>
        </div>
        <div style="padding:0 28px 28px;display:flex;gap:10px">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="flex:1;font-size:13px;border-radius:10px;padding:9px">ยกเลิก</button>
          <button type="button" id="checkinConfirmBtn" style="flex:1;font-size:13px;font-weight:600;border-radius:10px;padding:9px;background:#059669;color:white;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px"><i class="bi bi-check-lg"></i>ยืนยันเช็คอิน</button>
        </div>
      </div>
    </div>
  </div>

  <?php $__flash = flash_get(); if ($__flash): ?>
    <?php
      $toastClsMap = ['ok' => 'toast-fixed toast-ok', 'warn' => 'toast-fixed toast-warn', 'err' => 'toast-fixed toast-err'];
      $toastIconMap = ['ok' => 'bi bi-check-circle-fill', 'warn' => 'bi bi-exclamation-triangle-fill', 'err' => 'bi bi-x-circle-fill'];
      $type = $__flash['type'] ?? 'ok';
    ?>
    <div class="<?= $toastClsMap[$type] ?? $toastClsMap['ok'] ?>" id="appToast">
      <i class="<?= $toastIconMap[$type] ?? $toastIconMap['ok'] ?>" style="font-size:16px;flex-shrink:0"></i>
      <span><?= e($__flash['msg']) ?></span>
    </div>
  <?php endif; ?>

</div>
<script src="<?= asset('assets/app.js') ?>"></script>
</body>
</html>
