<?php
/**
 * Starts the ONE-RVC SSO flow. Two modes, chosen by whether the visitor is already
 * logged in — there is no separate "?link=1 but not logged in" state to guard, since
 * require_login() itself redirects an anonymous visitor to login.php.
 *
 *   - anonymous  (no ?link) -> "log in with ONE-RVC" from login.php
 *   - logged in  (?link=1)  -> "link my account to ONE-RVC" from the profile page
 *
 * This is a plain GET redirect, not a POST form, matching how every "sign in with X"
 * button works elsewhere — it only stashes a random state in session and sends the
 * browser off-site; nothing account-changing happens until the user has authenticated
 * at ONE-RVC and been redirected back with a token api/callback.php verifies.
 */
require_once __DIR__ . '/bootstrap.php';

$isLink = isset($_GET['link']);

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

header('Location: ' . SsoAuth::authorizationUrl($state));
exit;
