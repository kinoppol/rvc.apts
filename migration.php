<?php
/**
 * Web migration runner for the AI Pro Time-Sharing PHP app.
 *
 * Standalone on purpose (like install.php): reads only config.php so it can
 * connect to the already-created database without going through bootstrap.php.
 *
 * Tracks applied migrations in a `migrations` table (created automatically on
 * first visit). All migrate_*.sql files are idempotent (ADD COLUMN IF NOT EXISTS),
 * so re-running a previously applied one is safe.
 */
declare(strict_types=1);
session_start();

require __DIR__ . '/config.php';

if (empty($_SESSION['migration_csrf'])) {
    $_SESSION['migration_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['migration_csrf'];

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function migration_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    return $pdo;
}

/** Discover migrate_*.sql files in database/ sorted alphabetically (stable run order). */
function discover_migrations(): array
{
    $files = glob(__DIR__ . '/database/migrate_*.sql');
    sort($files);
    return $files ?: [];
}

/** Ensure the migrations tracking table exists. */
function ensure_migrations_table(): void
{
    migration_pdo()->exec(
        "CREATE TABLE IF NOT EXISTS migrations (
            filename   VARCHAR(255) NOT NULL PRIMARY KEY,
            applied_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/** Returns array of filename => applied_at strings for already-run migrations. */
function applied_migrations(): array
{
    $rows = migration_pdo()->query('SELECT filename, applied_at FROM migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $rows ?: [];
}

/** Run one migration file and record it. */
function run_migration(string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('อ่านไฟล์ไม่ได้: ' . basename($path));
    }
    migration_pdo()->exec($sql);
    $stmt = migration_pdo()->prepare(
        'INSERT INTO migrations (filename, applied_at) VALUES (?, NOW())
         ON DUPLICATE KEY UPDATE applied_at = NOW()'
    );
    $stmt->execute([basename($path)]);
}

// ── Probe server / DB ──────────────────────────────────────────────────────
$serverOk    = false;
$serverError = null;
$dbExists    = false;

try {
    // Check DB exists before connecting to it
    $probeDsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    $probe    = new PDO($probeDsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt     = $probe->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $stmt->execute([DB_NAME]);
    $dbExists  = (bool) $stmt->fetchColumn();
    $serverOk  = true;
} catch (Throwable $e) {
    $serverError = $e->getMessage();
}

$applied    = [];
$migrations = [];
$tableReady = false;

if ($serverOk && $dbExists) {
    try {
        ensure_migrations_table();
        $tableReady = true;
        $applied    = applied_migrations();
        $migrations = discover_migrations();
    } catch (Throwable $e) {
        $serverError = $e->getMessage();
        $serverOk = false;
    }
}

// ── Handle POST ────────────────────────────────────────────────────────────
$results = [];   // ['file' => string, 'ok' => bool, 'msg' => string]
$postError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $postError = 'โทเคนความปลอดภัยไม่ถูกต้อง กรุณาโหลดหน้านี้ใหม่';
    } elseif (!$serverOk || !$dbExists || !$tableReady) {
        $postError = 'เชื่อมต่อฐานข้อมูลไม่ได้ หรือยังไม่ได้ติดตั้งระบบ (ใช้ install.php ก่อน)';
    } else {
        $target = $_POST['run'] ?? 'pending'; // 'pending' or a specific filename

        foreach ($migrations as $path) {
            $name = basename($path);
            $isPending = !isset($applied[$name]);

            if ($target !== 'pending' && $target !== $name) {
                continue;
            }
            if ($target === 'pending' && !$isPending) {
                continue;
            }

            try {
                run_migration($path);
                $results[] = ['file' => $name, 'ok' => true, 'msg' => 'สำเร็จ'];
            } catch (Throwable $e) {
                $results[] = ['file' => $name, 'ok' => false, 'msg' => $e->getMessage()];
            }
        }

        // Refresh applied list after run
        try { $applied = applied_migrations(); } catch (Throwable) {}
    }
}

$pendingCount  = 0;
foreach ($migrations as $p) {
    if (!isset($applied[basename($p)])) { $pendingCount++; }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Migration — AI Pro Time-Sharing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/app.css?v=<?= @filemtime(__DIR__ . '/assets/app.css') ?>" rel="stylesheet">
<style>
.mig-row { display:flex; align-items:center; gap:10px; padding:10px 0; font-size:13px; border-bottom:1px solid var(--bs-border-color); }
.mig-row:last-child { border-bottom:none; }
.mig-badge { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; border-radius:4px; padding:2px 7px; white-space:nowrap; }
.mig-badge-ok  { background:#D1FAE5; color:#065F46; }
.mig-badge-pending { background:#FEF3C7; color:#92400E; }
.result-item { font-size:12px; padding:6px 10px; border-radius:6px; margin-bottom:6px; display:flex; gap:8px; align-items:flex-start; }
.result-ok  { background:#D1FAE5; color:#065F46; }
.result-err { background:#FEE2E2; color:#991B1B; }
</style>
</head>
<body>
<div class="auth-bg">
  <div class="auth-card auth-card-wide page-anim" style="max-width:680px">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px">
      <div class="logo-icon" style="width:44px;height:44px;border-radius:11px">
        <i class="bi bi-database-gear" style="color:white;font-size:18px"></i>
      </div>
      <div>
        <h5 style="font-weight:700;color:#0F172A;margin:0">จัดการ Migration</h5>
        <p style="color:var(--bs-secondary-color);font-size:13px;margin:0">AI Pro Time-Sharing — วิทยาลัย RVC</p>
      </div>
    </div>

    <?php if ($postError): ?>
      <div class="alert alert-danger" style="font-size:13px"><?= h($postError) ?></div>
    <?php endif; ?>

    <?php if ($results): ?>
      <div style="margin-bottom:16px">
        <?php foreach ($results as $r): ?>
          <div class="result-item <?= $r['ok'] ? 'result-ok' : 'result-err' ?>">
            <i class="bi <?= $r['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>" style="margin-top:1px;flex-shrink:0"></i>
            <div><strong><?= h($r['file']) ?></strong><?php if (!$r['ok']): ?><br><span style="font-size:11px;opacity:.85"><?= h($r['msg']) ?></span><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Connection status -->
    <div style="border:1px solid var(--bs-border-color);border-radius:10px;padding:12px 16px;margin-bottom:16px">
      <div style="font-weight:700;font-size:12px;color:var(--bs-secondary-color);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">สถานะการเชื่อมต่อ</div>
      <?php
      $icon = fn(bool $ok) => $ok
          ? '<i class="bi bi-check-circle-fill" style="color:#059669"></i>'
          : '<i class="bi bi-x-circle-fill" style="color:#DC2626"></i>';
      $row = function (bool $ok, string $label, string $detail = '') use ($icon): void {
          $d = $detail !== '' ? '<span style="color:var(--bs-tertiary-color);font-size:12px;margin-left:6px">' . h($detail) . '</span>' : '';
          echo '<div style="display:flex;align-items:center;gap:8px;padding:7px 0;font-size:13px">'
              . $icon($ok) . '<span>' . h($label) . '</span>' . $d . '</div>';
      };
      $row($serverOk, 'MariaDB ' . DB_HOST . ':' . DB_PORT, $serverOk ? 'ผู้ใช้ ' . DB_USER : (string) $serverError);
      $row($dbExists, 'ฐานข้อมูล "' . DB_NAME . '"', $dbExists ? '' : 'ยังไม่ได้ติดตั้ง — ใช้ install.php ก่อน');
      if ($dbExists) {
          $row($tableReady, 'ตาราง migrations', $tableReady ? 'พร้อม' : 'สร้างไม่ได้');
      }
      ?>
    </div>

    <?php if ($serverOk && $dbExists && $tableReady): ?>

      <!-- Migration list -->
      <div style="border:1px solid var(--bs-border-color);border-radius:10px;padding:4px 16px;margin-bottom:16px">
        <?php if (!$migrations): ?>
          <div style="padding:18px 0;text-align:center;font-size:13px;color:var(--bs-secondary-color)">ไม่พบไฟล์ migrate_*.sql ในโฟลเดอร์ database/</div>
        <?php endif; ?>
        <?php foreach ($migrations as $path):
            $name      = basename($path);
            $label     = preg_replace('/^migrate_|\.sql$/i', '', $name);
            $label     = str_replace('_', ' ', $label);
            $isApplied = isset($applied[$name]);
            $appliedAt = $isApplied ? (new DateTimeImmutable($applied[$name]))->format('d/m/Y H:i') : null;
        ?>
          <div class="mig-row">
            <i class="bi <?= $isApplied ? 'bi-check-circle-fill' : 'bi-circle' ?>" style="color:<?= $isApplied ? '#059669' : '#D97706' ?>;flex-shrink:0;font-size:15px"></i>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($name) ?></div>
              <?php if ($appliedAt): ?>
                <div style="font-size:11px;color:var(--bs-secondary-color);margin-top:1px"><i class="bi bi-clock me-1"></i>ใช้งานแล้ว <?= $appliedAt ?></div>
              <?php else: ?>
                <div style="font-size:11px;color:#D97706;margin-top:1px">รอดำเนินการ</div>
              <?php endif; ?>
            </div>
            <span class="mig-badge <?= $isApplied ? 'mig-badge-ok' : 'mig-badge-pending' ?>"><?= $isApplied ? 'applied' : 'pending' ?></span>
            <?php if (!$isApplied): ?>
              <form method="post" style="margin:0">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="run" value="<?= h($name) ?>">
                <button type="submit" class="btn btn-sm" style="font-size:11px;padding:3px 10px;border:1px solid var(--bs-border-color);white-space:nowrap"><i class="bi bi-play-fill me-1"></i>รัน</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($pendingCount > 0): ?>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="run" value="pending">
          <button type="submit" class="btn btn-primary w-100" style="background:linear-gradient(90deg,#2563EB,#0EA5E9);border:none;font-weight:600;padding:11px;margin-bottom:10px">
            <i class="bi bi-play-circle-fill me-2"></i>รัน Migration ที่ค้างอยู่ทั้งหมด (<?= $pendingCount ?> รายการ)
          </button>
        </form>
      <?php else: ?>
        <div style="background:#D1FAE5;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#065F46;display:flex;gap:8px;align-items:center">
          <i class="bi bi-check-circle-fill"></i>
          <span>Migration ทั้งหมดถูกใช้งานแล้ว — ฐานข้อมูลเป็นเวอร์ชันล่าสุด</span>
        </div>
      <?php endif; ?>

    <?php elseif (!$serverOk): ?>
      <div style="background:#FFF7ED;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#92400E;display:flex;gap:8px;align-items:flex-start">
        <i class="bi bi-info-circle-fill" style="margin-top:1px;flex-shrink:0"></i>
        <span>เชื่อมต่อ MariaDB ไม่ได้ ตรวจว่าเปิดบริการ MariaDB ของ WAMP แล้ว และค่าใน <code>config.php</code> ถูกต้อง (WAMP MariaDB ใช้พอร์ต <strong>3307</strong>)</span>
      </div>
    <?php endif; ?>

    <p style="text-align:center;font-size:12px;color:var(--bs-tertiary-color);margin:4px 0 0">
      <a href="login.php" style="color:#2563EB;font-weight:600;text-decoration:none"><i class="bi bi-arrow-left me-1"></i>กลับหน้าเข้าสู่ระบบ</a>
      &nbsp;·&nbsp;
      <a href="install.php" style="color:var(--bs-secondary-color);text-decoration:none">install.php</a>
    </p>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
