<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/google-oauth.php';

caps_bootstrap();

$googleError = trim((string) ($_GET['error'] ?? ''));
if ($googleError !== '') {
    $message = $googleError === 'access_denied'
        ? 'Google sign-in was cancelled.'
        : 'Google sign-in was not completed.';
    header('Location: ' . google_oauth_login_error_url($message), true, 302);
    exit;
}

$state = (string) ($_GET['state'] ?? '');
$expected = (string) ($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);

if ($state === '' || $expected === '' || !hash_equals($expected, $state)) {
    header('Location: ' . google_oauth_login_error_url('Google sign-in could not be verified. Please try again.'), true, 302);
    exit;
}

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    header('Location: ' . google_oauth_login_error_url('Google did not return an authorization code.'), true, 302);
    exit;
}

$token = google_oauth_exchange_code($code);
if (!$token['ok']) {
    header('Location: ' . google_oauth_login_error_url((string) ($token['error'] ?? 'Google sign-in failed.')), true, 302);
    exit;
}

unset($_SESSION['google_oauth_redirect_uri']);

$accessToken = (string) ($token['data']['access_token'] ?? '');
$idToken = (string) ($token['data']['id_token'] ?? '');
if ($accessToken === '' && $idToken === '') {
    header('Location: ' . google_oauth_login_error_url('Google sign-in failed. Please try again.'), true, 302);
    exit;
}

$profile = ['ok' => false, 'data' => []];
if ($accessToken !== '') {
    $profile = google_oauth_userinfo($accessToken);
}

$email = trim((string) ($profile['data']['email'] ?? ''));
$name = trim((string) ($profile['data']['name'] ?? ''));
$googleId = trim((string) ($profile['data']['sub'] ?? ''));

if ($email === '' && $idToken !== '') {
    $parts = explode('.', $idToken);
    if (isset($parts[1])) {
        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (is_array($payload)) {
            $email = trim((string) ($payload['email'] ?? ''));
            $name = $name !== '' ? $name : trim((string) ($payload['name'] ?? ''));
            $googleId = $googleId !== '' ? $googleId : trim((string) ($payload['sub'] ?? ''));
        }
    }
}

if ($email === '') {
    header('Location: ' . google_oauth_login_error_url('Google did not share an email address for this account.'), true, 302);
    exit;
}

$result = google_oauth_begin_verification($email, $name, $googleId);
if (!$result['ok']) {
    header('Location: ' . google_oauth_login_error_url((string) ($result['error'] ?? 'Google sign-in failed.')), true, 302);
    exit;
}

$redirect = trim((string) ($result['redirect'] ?? ''));
header('Location: ' . ($redirect !== '' ? $redirect : login_url() . '?google=verify'), true, 302);
exit;
