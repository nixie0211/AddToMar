<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/google-oauth.php';

caps_bootstrap();

if (!google_oauth_is_configured()) {
    header('Location: ' . google_oauth_login_error_url('Google sign-in is not configured yet.'), true, 302);
    exit;
}

$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
google_oauth_remember_redirect_uri();

header('Location: ' . google_oauth_authorization_url($_SESSION['google_oauth_state']), true, 302);
exit;
