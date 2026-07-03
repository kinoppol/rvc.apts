      </div>
    </main>
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
<script src="<?= url('assets/app.js') ?>"></script>
</body>
</html>
