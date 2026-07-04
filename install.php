<?php
/**
 * Web installer for the AI Pro Time-Sharing PHP app.
 *
 * Standalone on purpose: it must NOT go through bootstrap.php, because bootstrap
 * connects with the target database selected — which does not exist yet before
 * installation. It reads only config.php for credentials, then imports
 * database/schema.sql + database/seed.sql through its own PDO connection.
 *
 * SECURITY: delete this file once the database is set up — it can drop and
 * recreate the whole database.
 */
declare(strict_types=1);
session_start();

require __DIR__ . '/config.php';

const SCHEMA_FILE = __DIR__ . '/database/schema.sql';
const SEED_FILE   = __DIR__ . '/database/seed.sql';

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['install_csrf'];

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** New PDO connection to the MySQL/MariaDB server; $withDb selects the app database. */
function installer_pdo(bool $withDb): PDO
{
    $dsn = $withDb
        ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET)
        : sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    return new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/** Runs a whole .sql file on a fresh connection (avoids multi-statement result carry-over). */
function run_sql_file(string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('อ่านไฟล์ไม่ได้: ' . basename($path));
    }
    installer_pdo(false)->exec($sql);
}

// ── Detect current state ──
$serverOk = false;
$serverError = null;
$dbExists = false;
$seeded = false;
$userCount = 0;

try {
    $pdo = installer_pdo(false);
    $serverOk = true;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $stmt->execute([DB_NAME]);
    $dbExists = (bool) $stmt->fetchColumn();
    if ($dbExists) {
        try {
            $userCount = (int) installer_pdo(true)->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $seeded = $userCount > 0;
        } catch (Throwable) {
            // database exists but tables not created yet
        }
    }
} catch (Throwable $e) {
    $serverError = $e->getMessage();
}

$phpOk = PHP_VERSION_ID >= 80000;
$pdoMysqlOk = extension_loaded('pdo_mysql');
$filesOk = is_readable(SCHEMA_FILE) && is_readable(SEED_FILE);

// ── Handle install request ──
$done = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $error = 'โทเคนความปลอดภัยไม่ถูกต้อง กรุณาโหลดหน้านี้ใหม่แล้วลองอีกครั้ง';
    } elseif (!$serverOk) {
        $error = 'เชื่อมต่อฐานข้อมูลไม่ได้ ตรวจสอบ config.php และตรวจว่า MariaDB กำลังทำงาน';
    } elseif (!$phpOk || !$pdoMysqlOk || !$filesOk) {
        $error = 'ระบบยังไม่พร้อมติดตั้ง กรุณาแก้รายการที่ยังไม่ผ่านด้านล่างก่อน';
    } else {
        $reinstall = isset($_POST['reinstall']);
        if ($dbExists && $seeded && !$reinstall) {
            $error = 'ฐานข้อมูลมีข้อมูลอยู่แล้ว หากต้องการติดตั้งใหม่ กรุณาติ๊กยืนยันการลบข้อมูลเดิม';
        } else {
            try {
                if ($dbExists && $reinstall) {
                    installer_pdo(false)->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
                }
                run_sql_file(SCHEMA_FILE);
                run_sql_file(SEED_FILE);
                $done = true;
                $userCount = (int) installer_pdo(true)->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $seeded = true;
                $dbExists = true;
            } catch (Throwable $e) {
                $error = 'ติดตั้งไม่สำเร็จ: ' . $e->getMessage();
            }
        }
    }
}

function check_row(string $label, bool $ok, string $detail = ''): string
{
    $icon = $ok
        ? '<i class="bi bi-check-circle-fill" style="color:#059669"></i>'
        : '<i class="bi bi-x-circle-fill" style="color:#DC2626"></i>';
    $d = $detail !== '' ? '<span style="color:var(--bs-tertiary-color);font-size:12px;margin-left:6px">' . h($detail) . '</span>' : '';
    return '<div style="display:flex;align-items:center;gap:8px;padding:7px 0;font-size:13px">' . $icon
        . '<span>' . h($label) . '</span>' . $d . '</div>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ติดตั้งระบบ — AI Pro Time-Sharing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/app.css" rel="stylesheet">
</head>
<body>
<div class="auth-bg">
  <div class="auth-card auth-card-wide page-anim">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px">
      <div class="logo-icon" style="width:44px;height:44px;border-radius:11px">
        <i class="bi bi-hdd-stack-fill" style="color:white;font-size:18px"></i>
      </div>
      <div>
        <h5 style="font-weight:700;color:#0F172A;margin:0">ติดตั้งระบบ</h5>
        <p style="color:var(--bs-secondary-color);font-size:13px;margin:0">AI Pro Time-Sharing — วิทยาลัย RVC</p>
      </div>
    </div>

    <?php if ($done): ?>
      <div class="alert alert-success" style="font-size:13px;display:flex;gap:8px;align-items:flex-start">
        <i class="bi bi-check-circle-fill" style="margin-top:1px"></i>
        <span>ติดตั้งฐานข้อมูลสำเร็จ! นำเข้าโครงสร้างและข้อมูลตัวอย่างเรียบร้อยแล้ว (ผู้ใช้ <?= (int) $userCount ?> บัญชี)</span>
      </div>
      <div class="info-box" style="margin-bottom:16px">
        <div style="font-weight:700;font-size:13px;margin-bottom:8px">บัญชีสำหรับเข้าสู่ระบบ (รหัสผ่านทุกบัญชี: <code>Passw0rd!</code>)</div>
        <div style="font-size:13px;line-height:1.9">
          <div><i class="bi bi-shield-lock me-1" style="color:#2563EB"></i><strong>ผู้ดูแลระบบ:</strong> admin@rvc.ac.th</div>
          <div><i class="bi bi-person me-1" style="color:#2563EB"></i><strong>นักศึกษา (ตัวอย่าง):</strong> somchai@rvc.ac.th</div>
        </div>
      </div>
      <div style="background:#FEF2F2;border-radius:8px;padding:10px 14px;margin-bottom:18px;font-size:12px;color:#991B1B;display:flex;gap:8px;align-items:flex-start">
        <i class="bi bi-exclamation-triangle-fill" style="margin-top:1px;flex-shrink:0"></i>
        <span>เพื่อความปลอดภัย โปรด <strong>ลบไฟล์ <code>install.php</code></strong> ออกหลังติดตั้งเสร็จ (ไฟล์นี้สามารถลบและสร้างฐานข้อมูลใหม่ได้)</span>
      </div>
      <a href="login.php" class="btn btn-primary w-100" style="background:linear-gradient(90deg,#2563EB,#0EA5E9);border:none;font-weight:600;padding:11px">
        <i class="bi bi-box-arrow-in-right me-2"></i>ไปหน้าเข้าสู่ระบบ
      </a>

    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger" style="font-size:13px"><?= h($error) ?></div>
      <?php endif; ?>

      <div style="border:1px solid var(--bs-border-color);border-radius:10px;padding:12px 16px;margin-bottom:16px">
        <div style="font-weight:700;font-size:12px;color:var(--bs-secondary-color);text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px">ตรวจสอบความพร้อม</div>
        <?= check_row('PHP ' . PHP_VERSION, $phpOk, $phpOk ? '' : 'ต้องการ PHP 8.0 ขึ้นไป') ?>
        <?= check_row('ส่วนขยาย pdo_mysql', $pdoMysqlOk, $pdoMysqlOk ? '' : 'ยังไม่ได้เปิดใช้งาน') ?>
        <?= check_row('ไฟล์ schema.sql / seed.sql', $filesOk, $filesOk ? '' : 'ไม่พบในโฟลเดอร์ database/') ?>
        <?= check_row(
              'เชื่อมต่อฐานข้อมูล ' . DB_HOST . ':' . DB_PORT,
              $serverOk,
              $serverOk ? ('ผู้ใช้ ' . DB_USER) : (string) $serverError
        ) ?>
        <?php if ($serverOk): ?>
          <?= check_row(
                'ฐานข้อมูล "' . DB_NAME . '"',
                true,
                $dbExists ? ($seeded ? 'มีอยู่แล้ว · มีข้อมูล ' . $userCount . ' ผู้ใช้' : 'มีอยู่แล้ว · ยังไม่มีตาราง') : 'ยังไม่มี — จะถูกสร้างใหม่'
          ) ?>
        <?php endif; ?>
      </div>

      <?php if (!$serverOk): ?>
        <div style="background:#FFF7ED;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#92400E;display:flex;gap:8px;align-items:flex-start">
          <i class="bi bi-info-circle-fill" style="margin-top:1px;flex-shrink:0"></i>
          <span>เชื่อมต่อ MariaDB ไม่ได้ ตรวจว่าเปิดบริการ MariaDB ของ WAMP แล้ว และค่าใน <code>config.php</code> ถูกต้อง (WAMP MariaDB ใช้พอร์ต <strong>3307</strong>)</span>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <?php if ($dbExists && $seeded): ?>
          <label style="display:flex;gap:8px;align-items:flex-start;background:#FEF2F2;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#991B1B;cursor:pointer">
            <input type="checkbox" name="reinstall" value="1" style="margin-top:2px">
            <span>ฐานข้อมูลมีข้อมูลอยู่แล้ว — ติ๊กเพื่อ <strong>ลบฐานข้อมูลเดิมทั้งหมดแล้วติดตั้งใหม่</strong> (ข้อมูลที่มีอยู่จะหายทั้งหมด)</span>
          </label>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary w-100" style="background:linear-gradient(90deg,#2563EB,#0EA5E9);border:none;font-weight:600;padding:11px" <?= $serverOk ? '' : 'disabled' ?>>
          <i class="bi bi-download me-2"></i><?= $dbExists && $seeded ? 'ติดตั้งใหม่' : 'ติดตั้งฐานข้อมูล' ?>
        </button>
      </form>
      <p style="text-align:center;font-size:12px;color:var(--bs-tertiary-color);margin:14px 0 0">
        ติดตั้งแล้ว? <a href="login.php" style="color:#2563EB;font-weight:600;text-decoration:none">ไปหน้าเข้าสู่ระบบ</a>
      </p>
    <?php endif; ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
