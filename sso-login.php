<?php
/**
 * Starts the ONE-RVC SSO flow. Two modes, chosen by whether the visitor is already
 * logged in — there is no separate "?link=1 but not logged in" state to guard, since
 * require_login() itself redirects an anonymous visitor to login.php.
 *
 *   - anonymous  (no ?link) -> "log in with ONE-RVC" from login.php
 *   - logged in  (?link=1)  -> "link my account to ONE-RVC" from the profile page
 *
 * This renders a short interstitial with an explicit "ตรวจสอบการเข้าสู่ระบบ ONE-RVC"
 * button rather than issuing an immediate 302, for one specific reason: ONE-RVC's own
 * gateway can bounce straight back without ever showing its login screen (e.g. when
 * the visitor has no live ONE-RVC session) — and when that hop is instant, the whole
 * round trip looks identical to "the button did nothing" from here. A page in between
 * makes our hop and ONE-RVC's hop visually distinguishable, and index.php detects that
 * bounce-back case afterwards and explains it (see the comment there).
 *
 * Nothing account-changing happens until the user has actually authenticated at
 * ONE-RVC and been redirected back with a token api/callback.php verifies — this page
 * only stashes a random state in session.
 */
require_once __DIR__ . '/bootstrap.php';

$isLink = isset($_GET['link']);
$user   = null;

if ($isLink) {
    $user = require_login();
    $_SESSION['sso_link_user_id'] = (int) $user['id'];
} else {
    if (current_user()) {
        header('Location: ' . url('index.php'));
        exit;
    }
    unset($_SESSION['sso_link_user_id']);
}

$state = bin2hex(random_bytes(32));
$_SESSION['sso_state'] = $state;
$authUrl = SsoAuth::authorizationUrl($state);

$cancelUrl = $isLink
    ? url($user['role'] === 'admin' ? 'admin/profile.php' : 'student/profile.php')
    : url('login.php');

$message = $isLink
    ? 'ต้องลงชื่อเข้าใช้งานระบบ ONE-RVC ก่อน จึงจะผูกบัญชีของคุณกับ ONE-RVC ได้'
    : 'ต้องลงชื่อเข้าใช้งานระบบ ONE-RVC ก่อน จึงจะเข้าสู่ระบบด้วยบัญชี ONE-RVC ได้';

if ($isLink) {
    $activeNav = null;
    require __DIR__ . '/includes/header.php';
} else {
    require __DIR__ . '/includes/guest-header.php';
}
?>
<div style="max-width:480px;margin:<?= $isLink ? '20px' : '40px' ?> auto;text-align:center">
  <div class="card" style="border:1px solid var(--bs-border-color);box-shadow:0 1px 4px rgba(0,0,0,.04)">
    <div class="card-body" style="padding:32px 28px">
      <div style="width:56px;height:56px;border-radius:14px;background:#EEF2FF;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
        <i class="bi bi-shield-lock" style="color:#0F172A;font-size:26px"></i>
      </div>
      <h5 style="font-weight:700;margin:0 0 10px">ยืนยันตัวตนผ่าน ONE-RVC</h5>
      <p style="font-size:13.5px;color:var(--bs-secondary-color);line-height:1.8;margin:0 0 6px"><?= e($message) ?></p>
      <p style="font-size:12.5px;color:var(--bs-tertiary-color);line-height:1.8;margin:0 0 22px">
        ระบบจะพาคุณไปยังหน้าลงชื่อเข้าใช้ของ ONE-RVC (รหัสผ่านและการยืนยันตัวตนเพิ่มเติมหากมี)
        เมื่อเสร็จสิ้นระบบจะพากลับมาที่เว็บไซต์นี้โดยอัตโนมัติ
      </p>
      <a href="<?= e($authUrl) ?>" class="btn w-100" style="background:#0F172A;color:#fff;font-weight:600;padding:11px;margin-bottom:10px;display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none">
        <i class="bi bi-box-arrow-up-right"></i>ตรวจสอบการเข้าสู่ระบบ ONE-RVC
      </a>
      <a href="<?= e($cancelUrl) ?>" style="font-size:12.5px;color:var(--bs-secondary-color);text-decoration:none">ยกเลิก</a>
    </div>
  </div>
</div>
<?php
if ($isLink) {
    require __DIR__ . '/includes/footer.php';
} else {
    require __DIR__ . '/includes/guest-footer.php';
}
