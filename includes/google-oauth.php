<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/google-oauth.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/customers.php';
require_once __DIR__ . '/pharmacy-accounts.php';

function google_oauth_redirect_uri(): string
{
    $stored = trim((string) ($_SESSION['google_oauth_redirect_uri'] ?? ''));
    if ($stored !== '') {
        return $stored;
    }

    return app_absolute_url('google-callback.php');
}

function google_oauth_remember_redirect_uri(): string
{
    $uri = app_absolute_url('google-callback.php');
    $_SESSION['google_oauth_redirect_uri'] = $uri;

    return $uri;
}

function google_oauth_is_configured(): bool
{
    return GOOGLE_OAUTH_CLIENT_ID !== '' && GOOGLE_OAUTH_CLIENT_SECRET !== '';
}

function google_oauth_login_error_url(string $message): string
{
    $_SESSION['google_oauth_error'] = $message;

    return login_url() . '?error=google';
}

function google_oauth_api_error_message(array $data, int $httpCode, string $fallback = 'Google sign-in failed. Please try again.'): string
{
    $code = strtolower(trim((string) ($data['error'] ?? '')));
    $description = trim((string) ($data['error_description'] ?? ''));

    if ($code === 'redirect_uri_mismatch') {
        return 'Google rejected the return URL. In Google Cloud Console, add this Authorized redirect URI: ' . google_oauth_redirect_uri();
    }
    if ($code === 'invalid_client') {
        $detail = $description !== '' ? $description : 'The client secret is invalid.';
        return $detail . ' In Google Cloud Console, under Client secrets, click Add secret. Then paste the new secret into config/google-oauth.local.php as GOOGLE_OAUTH_CLIENT_SECRET.';
    }
    if ($code === 'invalid_grant') {
        return 'The Google sign-in code expired. Click Login with Google again.';
    }
    if ($description !== '') {
        return 'Google sign-in failed: ' . $description;
    }
    if ($code !== '') {
        return 'Google sign-in failed: ' . $code;
    }
    if ($httpCode > 0) {
        return $fallback . ' (HTTP ' . $httpCode . ')';
    }

    return $fallback;
}

function google_oauth_http(string $method, string $url, array $fields = [], array $headers = []): array
{
    $ch = curl_init($url);
    $method = strtoupper($method);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
        if ($headers === []) {
            $headers = ['Content-Type: application/x-www-form-urlencoded'];
        }
    } else {
        $opts[CURLOPT_HTTPGET] = true;
    }

    if ($headers !== []) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        return ['ok' => false, 'error' => 'Could not reach Google (' . $curlError . '). Check your internet connection and PHP curl SSL setup.'];
    }

    $data = json_decode((string) $response, true);
    if ($httpCode >= 400 || !is_array($data)) {
        return [
            'ok' => false,
            'error' => google_oauth_api_error_message(is_array($data) ? $data : [], $httpCode),
        ];
    }

    return ['ok' => true, 'data' => $data];
}

function google_oauth_authorization_url(string $state): string
{
    $query = http_build_query([
        'client_id' => GOOGLE_OAUTH_CLIENT_ID,
        'redirect_uri' => google_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
        'include_granted_scopes' => 'true',
    ]);

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
}

function google_oauth_exchange_code(string $code): array
{
    return google_oauth_http('POST', 'https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => GOOGLE_OAUTH_CLIENT_ID,
        'client_secret' => GOOGLE_OAUTH_CLIENT_SECRET,
        'redirect_uri' => google_oauth_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);
}

function google_oauth_userinfo(string $accessToken): array
{
    return google_oauth_http(
        'GET',
        'https://www.googleapis.com/oauth2/v3/userinfo',
        [],
        ['Authorization: Bearer ' . $accessToken]
    );
}

function google_oauth_pending(): ?array
{
    $pending = $_SESSION['google_oauth_pending'] ?? null;
    if (!is_array($pending) || trim((string) ($pending['email'] ?? '')) === '') {
        return null;
    }

    return $pending;
}

function google_oauth_clear_pending(): void
{
    unset($_SESSION['google_oauth_pending']);
}

function google_oauth_begin_verification(string $email, string $fullName, string $googleId): array
{
    $email = customers_normalize_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Google did not return a valid email address.'];
    }

    // Existing AddToMar logins (password already set) skip the email code
    // and return to the sign-in form.
    if (!google_oauth_needs_password_setup($email)) {
        if ($googleId !== '') {
            customers_register_from_google($email, trim($fullName), $googleId);
        }
        google_oauth_clear_pending();
        $_SESSION['google_existing_login_email'] = $email;

        return [
            'ok' => true,
            'redirect' => login_url() . '?google=existing',
            'mail_sent' => false,
        ];
    }

    $_SESSION['google_oauth_pending'] = [
        'email' => $email,
        'full_name' => trim($fullName),
        'google_id' => trim($googleId),
        'existing_account' => false,
        'attempts' => 0,
    ];

    return google_oauth_issue_code();
}

function google_oauth_issue_code(): array
{
    $pending = google_oauth_pending();
    if ($pending === null) {
        return ['ok' => false, 'error' => 'Google sign-in expired. Please try again.'];
    }

    require_once __DIR__ . '/mailer.php';

    $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $_SESSION['google_oauth_pending']['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
    $_SESSION['google_oauth_pending']['code_expires'] = time() + 600;
    $_SESSION['google_oauth_pending']['resend_after'] = time() + 23;
    $_SESSION['google_oauth_pending']['attempts'] = 0;
    unset($_SESSION['google_oauth_pending']['display_code'], $_SESSION['google_oauth_mail_error']);

    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $text = "Your AddToMar verification code is: {$code}\n\nThis code expires in 10 minutes.";
    $html = '<p style="font:16px/1.5 Arial,sans-serif;color:#234556">Your AddToMar verification code is:</p>'
        . '<p style="font:32px/1.2 Arial,sans-serif;letter-spacing:6px;font-weight:800;color:#0a6b54">' . $safeCode . '</p>'
        . '<p style="font:14px/1.5 Arial,sans-serif;color:#6b8290">This code expires in 10 minutes. If you did not sign in with Google, you can ignore this email.</p>';

    $sent = smtp_send_html($pending['email'], 'Your AddToMar verification code', $html, $text);
    if (!$sent['ok']) {
        $_SESSION['google_oauth_mail_error'] = (string) ($sent['error'] ?? 'We could not send the verification email.');

        return [
            'ok' => false,
            'mail_sent' => false,
            'error' => 'We could not send the verification code to ' . $pending['email'] . '. ' . (string) ($sent['error'] ?? 'Check your mail settings.'),
        ];
    }

    return ['ok' => true, 'mail_sent' => true, 'error' => ''];
}

function google_oauth_verify_code(string $code): array
{
    $pending = google_oauth_pending();
    if ($pending === null) {
        return ['ok' => false, 'error' => 'Google sign-in expired. Please try again.'];
    }

    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (strlen($code) !== 4) {
        return ['ok' => false, 'error' => 'Enter the 4-digit code sent to your email.'];
    }

    if ((int) ($pending['code_expires'] ?? 0) < time()) {
        return ['ok' => false, 'error' => 'That code has expired. Resend a new code and try again.'];
    }

    $attempts = (int) ($pending['attempts'] ?? 0) + 1;
    $_SESSION['google_oauth_pending']['attempts'] = $attempts;
    if ($attempts > 8) {
        google_oauth_clear_pending();

        return ['ok' => false, 'error' => 'Too many incorrect codes. Please sign in with Google again.'];
    }

    $hash = (string) ($pending['code_hash'] ?? '');
    if ($hash === '' || !password_verify($code, $hash)) {
        return ['ok' => false, 'error' => 'That code is incorrect. Please try again.'];
    }

    $_SESSION['google_oauth_pending']['code_verified'] = true;

    return google_oauth_after_verified();
}

function google_oauth_needs_password_setup(string $email): bool
{
    $email = customers_normalize_email($email);
    $pharmacy = pharmacy_accounts_find_by_email($email) ?? pharmacy_accounts_find_db_by_email($email);
    if ($pharmacy) {
        return false;
    }

    $customer = customers_find_by_email($email);
    if (!$customer) {
        return true;
    }

    if (($customer['role'] ?? 'customer') === 'admin' || ($customer['role'] ?? '') === 'pharmacy') {
        return false;
    }

    return (int) ($customer['password_set'] ?? 1) !== 1;
}

function google_oauth_needs_location(string $email): bool
{
    $email = customers_normalize_email($email);
    $pharmacy = pharmacy_accounts_find_by_email($email) ?? pharmacy_accounts_find_db_by_email($email);
    if ($pharmacy) {
        return false;
    }

    $customer = customers_find_by_email($email);
    if (!$customer) {
        return true;
    }

    if (($customer['role'] ?? 'customer') === 'admin' || ($customer['role'] ?? '') === 'pharmacy') {
        return false;
    }

    return trim((string) ($customer['address'] ?? '')) === '';
}

function google_oauth_after_verified(): array
{
    $pending = google_oauth_pending();
    if ($pending === null || empty($pending['code_verified'])) {
        return ['ok' => false, 'error' => 'Google sign-in expired. Please try again.'];
    }

    $email = (string) ($pending['email'] ?? '');
    $fullName = (string) ($pending['full_name'] ?? '');
    $googleId = (string) ($pending['google_id'] ?? '');

    caps_ensure_admin_account(caps_db());
    $customer = customers_find_by_email($email);
    if (customers_is_admin($customer) || customers_normalize_email($email) === addtomar_admin_email()) {
        if ($googleId !== '') {
            customers_register_from_google($email, $fullName !== '' ? $fullName : 'AddToMar Admin', $googleId);
            $customer = customers_find_by_email($email) ?? $customer;
        }
        google_oauth_clear_pending();
        $_SESSION['portal'] = 'admin';
        $_SESSION['user_email'] = customers_normalize_email($email);
        $_SESSION['user_name'] = $customer['full_name'] ?? 'Admin';
        $_SESSION['user_role'] = 'admin';

        return ['ok' => true, 'redirect' => app_url('admin/')];
    }

    if (google_oauth_needs_password_setup($email)) {
        return ['ok' => true, 'next' => 'password'];
    }

    if (google_oauth_needs_location($email)) {
        $_SESSION['google_oauth_pending']['password_ready'] = true;
        return ['ok' => true, 'next' => 'location'];
    }

    // Existing AddToMar accounts return to the login form so the user can
    // continue with the normal password login flow.
    $_SESSION['google_existing_login_email'] = $email;
    google_oauth_clear_pending();
    return ['ok' => true, 'redirect' => login_url() . '?google=existing'];

}

function google_oauth_set_password(string $password, string $passwordConfirm): array
{
    $pending = google_oauth_pending();
    if ($pending === null || empty($pending['code_verified'])) {
        return ['ok' => false, 'error' => 'Google sign-in expired. Please try again.'];
    }

    $email = (string) ($pending['email'] ?? '');
    $fullName = (string) ($pending['full_name'] ?? '');
    $googleId = (string) ($pending['google_id'] ?? '');

    $customer = customers_find_by_email($email);
    if (!$customer) {
        $created = customers_register_from_google($email, $fullName, $googleId);
        if (!$created['ok']) {
            return $created;
        }
    } elseif ($googleId !== '') {
        customers_register_from_google($email, $fullName, $googleId);
    }

    $saved = customers_set_login_password($email, $password, $passwordConfirm);
    if (!$saved['ok']) {
        return $saved;
    }

    $_SESSION['google_oauth_pending']['password_ready'] = true;

    return ['ok' => true, 'next' => 'location'];
}

function google_oauth_save_location(string $address, string $latitude, string $longitude): array
{
    $pending = google_oauth_pending();
    if ($pending === null || empty($pending['code_verified']) || empty($pending['password_ready'])) {
        return ['ok' => false, 'error' => 'Google sign-in expired. Please try again.'];
    }

    $email = (string) ($pending['email'] ?? '');

    if ($email === '') {
        return ['ok' => false, 'error' => 'Your account email is missing.'];
    }

    $updated = customers_update_location(
        $email,
        $address,
        (float) $latitude,
        (float) $longitude
    );
    if (!$updated['ok']) {
        return $updated;
    }

    google_oauth_clear_pending();
    $_SESSION['google_signup_login_email'] = $email;

    return ['ok' => true, 'redirect' => login_url() . '?google=ready'];
}

function google_oauth_sign_in(string $email, string $fullName, string $googleId): array
{
    $email = customers_normalize_email($email);
    $pharmacy = pharmacy_accounts_find_by_email($email) ?? pharmacy_accounts_find_db_by_email($email);

    if ($pharmacy) {
        if (pharmacy_accounts_is_blocked($pharmacy)) {
            return ['ok' => false, 'error' => 'This pharmacy account has been blocked. Contact AddToMar support.'];
        }
        if (strtolower((string) ($pharmacy['status'] ?? '')) === 'rejected') {
            return ['ok' => false, 'error' => 'This pharmacy registration was rejected.'];
        }
        if (!pharmacy_accounts_is_approved($pharmacy)) {
            return ['ok' => false, 'error' => 'This pharmacy is still waiting for admin approval.'];
        }

        pharmacy_accounts_sync_database($pharmacy);
        pharmacy_hydrate_session_from_account($pharmacy);
        pharmacy_accounts_sync_medvault($pharmacy);

        return ['ok' => true, 'redirect' => app_url('pharmacy/')];
    }

    $customer = customers_find_by_email($email);
    if (!$customer) {
        $created = customers_register_from_google($email, $fullName, $googleId);
        if (!$created['ok']) {
            return $created;
        }
        $customer = $created['customer'];
    } elseif ($googleId !== '') {
        customers_register_from_google($email, $fullName, $googleId);
    }

    if (($customer['role'] ?? 'customer') === 'pharmacy') {
        return ['ok' => false, 'error' => 'This pharmacy is still waiting for admin approval.'];
    }

    if (customers_is_admin($customer) || customers_normalize_email($email) === addtomar_admin_email()) {
        caps_ensure_admin_account(caps_db());
        $customer = customers_find_by_email($email) ?? $customer;
        $_SESSION['portal'] = 'admin';
        $_SESSION['user_email'] = customers_normalize_email($email);
        $_SESSION['user_name'] = $customer['full_name'] ?? 'Admin';
        $_SESSION['user_role'] = 'admin';

        return ['ok' => true, 'redirect' => app_url('admin/')];
    }

    $_SESSION['portal'] = 'residence';
    $_SESSION['user_email'] = $email;
    residence_hydrate_session_from_customer($customer);

    return ['ok' => true, 'redirect' => app_url('residence/')];
}
