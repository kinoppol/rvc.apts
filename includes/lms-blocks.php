<?php
/**
 * Renders a topic's content blocks. A view partial in the same spirit as header.php
 * / footer.php — NOT a class, and not required from bootstrap.php; the two pages that
 * render lesson content (student/lms-topic.php and the admin preview pane) require it
 * directly, which is why the admin preview needs no relaxing of the student role guard.
 *
 * Expects in scope:
 *   $blocks — rows from LmsContent::blocks()
 *
 * Every field is escaped with e(); no block type ever emits admin-supplied markup, and
 * every href/src goes through LmsContent::safeUrl() first. That combination is what
 * makes the structured-block editor XSS-safe without a sanitizer library.
 */

$__calloutStyles = [
    'info' => ['#2563EB', '#EFF6FF', 'bi-info-circle'],
    'tip'  => ['#059669', '#ECFDF5', 'bi-lightbulb'],
    'warn' => ['#D97706', '#FFFBEB', 'bi-exclamation-triangle'],
];
?>
<?php if (empty($blocks)): ?>
  <div style="padding:36px;text-align:center;color:var(--bs-tertiary-color);font-size:13px">
    <i class="bi bi-file-earmark-text" style="font-size:26px;display:block;margin-bottom:8px"></i>
    ยังไม่มีเนื้อหาในหัวข้อนี้
  </div>
<?php else: ?>
  <div class="lms-content">
    <?php foreach ($blocks as $__b):
        $__type = (string) $__b['block_type'];
        $__text = (string) ($__b['text_content'] ?? '');
        $__meta = (string) ($__b['meta'] ?? '');
    ?>

      <?php if ($__type === 'heading'): ?>
        <?php $__tag = $__meta === 'h3' ? 'h6' : 'h5'; ?>
        <<?= $__tag ?> class="lms-h" style="font-weight:700;margin:22px 0 10px"><?= e($__text) ?></<?= $__tag ?>>

      <?php elseif ($__type === 'paragraph'): ?>
        <p style="font-size:14px;line-height:1.9;margin:0 0 14px"><?= nl2br(e($__text)) ?></p>

      <?php elseif ($__type === 'list'): ?>
        <?php $__tag = $__meta === 'ol' ? 'ol' : 'ul'; ?>
        <<?= $__tag ?> style="font-size:14px;line-height:1.9;margin:0 0 14px;padding-left:22px">
          <?php foreach (preg_split('/\R/', $__text) as $__item): ?>
            <?php if (trim($__item) !== ''): ?><li><?= e(trim($__item)) ?></li><?php endif; ?>
          <?php endforeach; ?>
        </<?= $__tag ?>>

      <?php elseif ($__type === 'code'): ?>
        <div style="margin:0 0 16px">
          <?php if ($__meta !== ''): ?>
            <div style="font-size:11px;font-weight:700;color:var(--bs-tertiary-color);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px"><?= e($__meta) ?></div>
          <?php endif; ?>
          <pre style="background:var(--bs-secondary-bg);border:1px solid var(--bs-border-color);border-radius:10px;padding:14px 16px;overflow-x:auto;margin:0"><code style="font-size:12.5px;line-height:1.7;font-family:ui-monospace,SFMono-Regular,Consolas,monospace"><?= e($__text) ?></code></pre>
        </div>

      <?php elseif ($__type === 'callout'): ?>
        <?php
          [$__c, $__bg, $__icon] = $__calloutStyles[$__meta] ?? $__calloutStyles['info'];
          $__link = LmsContent::safeUrl($__b['link_url'] ?? null);
        ?>
        <div style="display:flex;gap:10px;background:<?= $__bg ?>;border-left:3px solid <?= $__c ?>;border-radius:8px;padding:12px 14px;margin:0 0 16px">
          <i class="bi <?= $__icon ?>" style="color:<?= $__c ?>;font-size:16px;flex-shrink:0;margin-top:1px"></i>
          <div style="font-size:13.5px;line-height:1.8;color:#1F2937;min-width:0">
            <?= nl2br(e($__text)) ?>
            <?php if ($__link !== null): ?>
              <a href="<?= e($__link) ?>" target="_blank" rel="noopener noreferrer"
                 style="display:inline-block;margin-top:6px;color:<?= $__c ?>;font-weight:600;text-decoration:none">
                <i class="bi bi-box-arrow-up-right me-1"></i>เปิดลิงก์
              </a>
            <?php endif; ?>
          </div>
        </div>

      <?php elseif ($__type === 'youtube'): ?>
        <?php $__vid = LmsContent::youtubeId($__b['link_url'] ?? null); ?>
        <?php if ($__vid !== null): ?>
          <figure style="margin:0 0 18px">
            <div style="position:relative;width:100%;aspect-ratio:16/9;border-radius:12px;overflow:hidden;background:#000">
              <iframe src="https://www.youtube-nocookie.com/embed/<?= e($__vid) ?>"
                      title="<?= e($__text !== '' ? $__text : 'วิดีโอประกอบบทเรียน') ?>"
                      loading="lazy" allowfullscreen
                      allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                      referrerpolicy="strict-origin-when-cross-origin"
                      style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>
            </div>
            <?php if ($__text !== ''): ?>
              <figcaption style="font-size:12px;color:var(--bs-secondary-color);margin-top:6px"><?= e($__text) ?></figcaption>
            <?php endif; ?>
            <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:2px">
              <i class="bi bi-youtube me-1"></i>ที่มา:
              <a href="<?= e((string) LmsContent::safeUrl($__b['link_url'])) ?>" target="_blank" rel="noopener noreferrer"
                 style="color:var(--bs-tertiary-color)">YouTube</a>
            </div>
          </figure>
        <?php endif; ?>

      <?php elseif ($__type === 'image'): ?>
        <?php
          $__ext  = LmsContent::safeUrl($__b['image_url'] ?? null);
          $__file = $__b['image_file'] ?? null;
          $__src  = $__ext !== null ? $__ext : ($__file ? url('uploads/lms/blocks/' . rawurlencode((string) $__file)) : null);
          $__srcUrl = LmsContent::safeUrl($__b['source_url'] ?? null);
        ?>
        <?php if ($__src !== null): ?>
          <figure style="margin:0 0 18px">
            <img src="<?= e($__src) ?>" alt="<?= e($__text !== '' ? $__text : 'ภาพประกอบบทเรียน') ?>"
                 loading="lazy" referrerpolicy="no-referrer"
                 style="max-width:100%;height:auto;border-radius:12px;border:1px solid var(--bs-border-color);display:block">
            <?php if ($__text !== ''): ?>
              <figcaption style="font-size:12px;color:var(--bs-secondary-color);margin-top:7px"><?= e($__text) ?></figcaption>
            <?php endif; ?>
            <?php /* Attribution is mandatory on every image — saveBlock() refuses to store one without it. */ ?>
            <div style="font-size:11px;color:var(--bs-tertiary-color);margin-top:3px">
              <i class="bi bi-camera me-1"></i>
              <?php if ($__srcUrl !== null): ?>
                <a href="<?= e($__srcUrl) ?>" target="_blank" rel="noopener noreferrer nofollow"
                   style="color:var(--bs-tertiary-color)"><?= e((string) $__b['source_label']) ?></a>
              <?php else: ?>
                <?= e((string) $__b['source_label']) ?>
              <?php endif; ?>
            </div>
          </figure>
        <?php endif; ?>

      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
