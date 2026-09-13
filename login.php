<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/maps.php';
require_once __DIR__ . '/includes/customers.php';
require_once __DIR__ . '/includes/pharmacy-accounts.php';
require_once __DIR__ . '/includes/google-oauth.php';
require_once __DIR__ . '/includes/live-sync.php';

caps_bootstrap();

$error = '';
$email = '';
$registerError = '';
$registerSuccess = '';
$openRegisterModal = false;
$pharmacyRegisterError = '';
$pharmacyRegisterSuccess = '';
$openPharmacyRegisterModal = false;
$registerValues = [
    'full_name' => '',
    'email' => '',
    'contact_number' => '',
    'address' => '',
];
$pharmacyRegisterValues = [
    'pharmacy_name' => '',
    'email' => '',
    'contact_number' => '',
    'address' => '',
    'open_time' => '08:00',
    'close_time' => '20:00',
    'operation_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
    'operating_hours' => pharmacy_accounts_default_operating_hours(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'google_verify') {
    header('Content-Type: application/json; charset=utf-8');
    $result = google_oauth_verify_code((string) ($_POST['code'] ?? ''));
    echo json_encode([
        'ok' => (bool) ($result['ok'] ?? false),
        'redirect' => (string) ($result['redirect'] ?? ''),
        'next' => (string) ($result['next'] ?? ''),
        'message' => (string) ($result['error'] ?? ''),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'google_verify_resend') {
    header('Content-Type: application/json; charset=utf-8');
    $pending = google_oauth_pending();
    if ($pending === null) {
        echo json_encode(['ok' => false, 'message' => 'Google sign-in expired. Please try again.']);
        exit;
    }
    $wait = (int) ($pending['resend_after'] ?? 0) - time();
    if ($wait > 0) {
        echo json_encode(['ok' => false, 'wait' => $wait, 'message' => 'Please wait before requesting another code.']);
        exit;
    }
    $result = google_oauth_issue_code();
    echo json_encode([
        'ok' => (bool) ($result['ok'] ?? false),
        'mail_sent' => (bool) ($result['mail_sent'] ?? false),
        'message' => (string) ($result['error'] ?? ''),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'google_verify_cancel') {
    header('Content-Type: application/json; charset=utf-8');
    google_oauth_clear_pending();
    echo json_encode(['ok' => true, 'redirect' => app_url('google-login.php')]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'google_set_password') {
    header('Content-Type: application/json; charset=utf-8');
    $result = google_oauth_set_password((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
    echo json_encode([
        'ok' => (bool) ($result['ok'] ?? false),
        'redirect' => (string) ($result['redirect'] ?? ''),
        'next' => (string) ($result['next'] ?? ''),
        'message' => (string) ($result['error'] ?? ''),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'google_save_location') {
    header('Content-Type: application/json; charset=utf-8');
    $result = google_oauth_save_location(
        (string) ($_POST['address'] ?? ''),
        (string) ($_POST['latitude'] ?? ''),
        (string) ($_POST['longitude'] ?? '')
    );
    echo json_encode([
        'ok' => (bool) ($result['ok'] ?? false),
        'redirect' => (string) ($result['redirect'] ?? ''),
        'message' => (string) ($result['error'] ?? ''),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_customer') {
    $registerValues['full_name'] = trim($_POST['full_name'] ?? '');
    $registerValues['email'] = trim($_POST['email'] ?? '');
    $registerValues['contact_number'] = preg_replace('/\D+/', '', trim($_POST['contact_number'] ?? ''));
    $registerValues['address'] = trim($_POST['address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    if (!filter_var($pharmacyRegisterValues['email'], FILTER_VALIDATE_EMAIL) || !residence_is_gmail($pharmacyRegisterValues['email'])) {
        $pharmacyRegisterError = 'Enter a valid, existing Gmail account. Approval notifications will be sent to this email.';
        $openPharmacyRegisterModal = true;
    } elseif (empty($_POST['accept_terms'])) {
        $registerError = 'Please accept the Terms and Privacy Policy to create an account.';
        $openRegisterModal = true;
    } else {
        $result = customers_register([
            'email' => $registerValues['email'],
            'full_name' => $registerValues['full_name'],
            'contact_number' => $registerValues['contact_number'],
            'address' => $registerValues['address'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
        ]);

        if (!$result['ok']) {
            $registerError = $result['error'];
            $openRegisterModal = true;
        } else {
            $registerSuccess = 'Account created. Sign in with your email and password.';
            $email = $registerValues['email'];
            $registerValues = [
                'full_name' => '',
                'email' => '',
                'contact_number' => '',
                'address' => '',
            ];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_pharmacy') {
    $pharmacyRegisterValues['pharmacy_name'] = trim($_POST['pharmacy_name'] ?? '');
    $pharmacyRegisterValues['email'] = trim($_POST['email'] ?? '');
    $pharmacyRegisterValues['contact_number'] = preg_replace('/\D+/', '', trim($_POST['contact_number'] ?? ''));
    $pharmacyRegisterValues['address'] = trim($_POST['address'] ?? '');
    $pharmacyRegisterValues['open_time'] = trim($_POST['open_time'] ?? '08:00');
    $pharmacyRegisterValues['close_time'] = trim($_POST['close_time'] ?? '20:00');
    $pharmacyRegisterValues['operation_days'] = is_array($_POST['operation_days'] ?? null)
        ? array_values($_POST['operation_days'])
        : [];
    $pharmacyRegisterValues['operating_hours'] = is_array($_POST['day_hours'] ?? null)
        ? $_POST['day_hours']
        : [];

    if (empty($_POST['accept_terms'])) {
        $pharmacyRegisterError = 'Please accept the Terms and Privacy Policy to register your pharmacy.';
        $openPharmacyRegisterModal = true;
    } else {
        $result = pharmacy_accounts_register([
            'pharmacy_name' => $pharmacyRegisterValues['pharmacy_name'],
            'email' => $pharmacyRegisterValues['email'],
            'contact_number' => $pharmacyRegisterValues['contact_number'],
            'address' => $pharmacyRegisterValues['address'],
            'open_time' => $pharmacyRegisterValues['open_time'],
            'close_time' => $pharmacyRegisterValues['close_time'],
            'operation_days' => $pharmacyRegisterValues['operation_days'],
            'day_hours' => $pharmacyRegisterValues['operating_hours'],
            'latitude' => trim($_POST['latitude'] ?? ''),
            'longitude' => trim($_POST['longitude'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
        ], $_FILES);

        if (!$result['ok']) {
            $pharmacyRegisterError = $result['error'];
            $openPharmacyRegisterModal = true;
        } else {
            $pharmacyRegisterSuccess = 'Pharmacy registration submitted. Sign in with your login email and password once approved.';
            $email = $pharmacyRegisterValues['email'];
            $pharmacyRegisterValues = [
                'pharmacy_name' => '',
                'email' => '',
                'contact_number' => '',
                'address' => '',
                'open_time' => '08:00',
                'close_time' => '20:00',
                'operation_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'operating_hours' => pharmacy_accounts_default_operating_hours(),
            ];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'forgot_check') {
    header('Content-Type: application/json; charset=utf-8');
    $forgotEmail = trim($_POST['email'] ?? '');
    $forgotAccount = null;
    if (filter_var($forgotEmail, FILTER_VALIDATE_EMAIL)) {
        $forgotAccount = pharmacy_accounts_find_by_email($forgotEmail)
            ?? pharmacy_accounts_find_db_by_email($forgotEmail)
            ?? customers_find_by_email($forgotEmail);
    }
    if ($forgotAccount !== null) {
        require_once __DIR__ . '/includes/mailer.php';
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $_SESSION['forgot_password_pending'] = [
            'email' => strtolower($forgotEmail),
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires' => time() + 600,
            'attempts' => 0,
        ];
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $sent = smtp_send_html(
            $forgotEmail,
            'Your AddToMar password reset code',
            '<p style="font:16px/1.5 Arial,sans-serif;color:#234556">Your AddToMar password reset code is:</p>'
            . '<p style="font:32px/1.2 Arial,sans-serif;letter-spacing:6px;font-weight:800;color:#0a6b54">' . $safeCode . '</p>'
            . '<p style="font:14px/1.5 Arial,sans-serif;color:#6b8290">This code expires in 10 minutes. If you did not request a password reset, you can ignore this email.</p>',
            "Your AddToMar password reset code is: {$code}\n\nThis code expires in 10 minutes."
        );
        if (!$sent['ok']) {
            unset($_SESSION['forgot_password_pending']);
            echo json_encode(['registered' => false, 'message' => 'We could not send the reset code. ' . ($sent['error'] ?? 'Check your mail settings.')]);
            exit;
        }
    }
    echo json_encode([
        'registered' => $forgotAccount !== null,
        'message' => $forgotAccount !== null
            ? 'Verification code sent.'
            : 'No registered account was found for this email.',
    ]);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'forgot_verify') {
    header('Content-Type: application/json; charset=utf-8');
    $pending = $_SESSION['forgot_password_pending'] ?? null;
    $code = trim((string) ($_POST['code'] ?? ''));
    $valid = is_array($pending)
        && (int) ($pending['expires'] ?? 0) >= time()
        && (int) ($pending['attempts'] ?? 0) < 5
        && preg_match('/^\d{4}$/', $code)
        && password_verify($code, (string) ($pending['code_hash'] ?? ''));
    if (!$valid && is_array($pending)) {
        $_SESSION['forgot_password_pending']['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
    }
    if ($valid) {
        $_SESSION['forgot_password_pending']['verified'] = true;
    }
    echo json_encode(['ok' => $valid, 'message' => $valid ? '' : 'That reset code is incorrect. Please try again.']);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'forgot_set_password') {
    header('Content-Type: application/json; charset=utf-8');
    $pending = $_SESSION['forgot_password_pending'] ?? null;
    if (!is_array($pending) || empty($pending['verified']) || (int) ($pending['expires'] ?? 0) < time()) {
        echo json_encode(['ok' => false, 'message' => 'Password reset expired. Please request a new code.']);
        exit;
    }
    $result = customers_set_login_password((string) ($pending['email'] ?? ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
    if (!empty($result['ok'])) {
        $_SESSION['forgot_password_login_email'] = (string) ($pending['email'] ?? '');
        unset($_SESSION['forgot_password_pending']);
    }
    echo json_encode(['ok' => (bool) ($result['ok'] ?? false), 'message' => (string) ($result['error'] ?? '')]);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password to continue.';
    } else {
        $pharmacy = pharmacy_accounts_find_by_email($email) ?? pharmacy_accounts_find_db_by_email($email);

        if ($pharmacy) {
            if (!pharmacy_accounts_verify_password($pharmacy, $password)) {
                $error = 'Incorrect password. Please try again.';
            } elseif (pharmacy_accounts_is_blocked($pharmacy)) {
                $error = 'This pharmacy account has been blocked. Contact AddToMar support.';
            } elseif (strtolower((string) ($pharmacy['status'] ?? '')) === 'rejected') {
                $error = 'This pharmacy registration was rejected.';
            } elseif (!pharmacy_accounts_is_approved($pharmacy)) {
                $error = 'This pharmacy is still waiting for admin approval.';
            } else {
                pharmacy_accounts_sync_database($pharmacy);
                pharmacy_hydrate_session_from_account($pharmacy);
                pharmacy_accounts_sync_medvault($pharmacy);
                header('Location: ' . app_url('pharmacy/'), true, 302);
                exit;
            }
        } else {
            $customer = customers_find_by_email($email);
            if (!$customer) {
                $error = 'No account found for this email. Create a customer or pharmacy account first.';
            } elseif (($customer['role'] ?? 'customer') === 'pharmacy') {
                $error = 'This pharmacy is still waiting for admin approval.';
            } elseif (!customers_verify_password($customer, $password)) {
                $error = 'Incorrect password. Please try again.';
            } elseif (($customer['role'] ?? 'customer') === 'admin') {
                $_SESSION['portal'] = 'admin';
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $customer['full_name'] ?? 'Admin';
                $_SESSION['user_role'] = 'admin';
                header('Location: ' . app_url('admin/'), true, 302);
                exit;
            } else {
                $_SESSION['portal'] = 'residence';
                $_SESSION['user_email'] = $email;
                residence_hydrate_session_from_customer($customer);
                header('Location: ' . app_url('residence/'), true, 302);
                exit;
            }
        }
    }
}

if ($error === '') {
    $queryError = trim((string) ($_GET['error'] ?? ''));
    if ($queryError === 'google') {
        $error = trim((string) ($_SESSION['google_oauth_error'] ?? 'Google sign-in failed. Please try again.'));
        unset($_SESSION['google_oauth_error']);
    } elseif ($queryError !== '') {
        $error = $queryError;
    }
}

$googleFlow = (string) ($_GET['google'] ?? '');
// A normal visit to the login page must always start at the credentials form.
// Do not restore the Google password step unless the URL explicitly requests it.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $googleFlow === '') {
    google_oauth_clear_pending();
}
$googlePending = google_oauth_pending();
$googleExistingNotice = false;
$googleExistingLoginEmail = trim((string) ($_SESSION['google_existing_login_email'] ?? ''));
if ($googleExistingLoginEmail !== '' && (string) ($_GET['google'] ?? '') === 'existing') {
    $email = $googleExistingLoginEmail;
    unset($_SESSION['google_existing_login_email']);
    $googleExistingNotice = true;
}
$forgotPasswordLoginEmail = trim((string) ($_SESSION['forgot_password_login_email'] ?? ''));
if ($forgotPasswordLoginEmail !== '' && (string) ($_GET['reset'] ?? '') === 'success') {
    $email = $forgotPasswordLoginEmail;
    $registerSuccess = 'Password reset successfully. Sign in with your new password.';
    unset($_SESSION['forgot_password_login_email']);
}
$googleSignupLoginEmail = trim((string) ($_SESSION['google_signup_login_email'] ?? ''));
if ($googleSignupLoginEmail !== '' && (string) ($_GET['google'] ?? '') === 'ready') {
    $email = $googleSignupLoginEmail;
    $registerSuccess = 'Account created. Sign in with your email and password.';
    unset($_SESSION['google_signup_login_email']);
}
$googleVerifyEmail = is_array($googlePending) ? (string) ($googlePending['email'] ?? '') : '';
$googleExistingAccount = is_array($googlePending) && !empty($googlePending['existing_account']);
$openGoogleLocation = is_array($googlePending) && !empty($googlePending['password_ready']);
$openGooglePassword = is_array($googlePending) && !empty($googlePending['code_verified']) && !$openGoogleLocation;
$openGoogleVerify = $googlePending !== null && !$openGooglePassword && !$openGoogleLocation && $googleFlow === 'verify';
$googleVerifyNotice = '';
if ((string) ($_GET['google'] ?? '') === 'verify' && $googlePending === null && $error === '') {
    $error = 'Google sign-in expired. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AddToMar — Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Playfair+Display:wght@600;700&family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/login.css?v=account-location-pin-2">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/map-service-overlay.css'), ENT_QUOTES, 'UTF-8') ?>?v=2">
<style>
  :root{
    --ink:#0d0d0f;
    --paper:#ffffff;
    --line:#e6e4e0;
    --muted:#9a968f;
    --text:#1c1b19;
    --accent:#0f7a72;
    --field:#f4f3f0;
    --mint:#f3fbf9;
    --mint-deep:#e7f4f1;
    --mint-panel:#e4f0ec;
    --navy:#0b2230;
    --body-text:#4a626d;
    --border:#d3e6e2;
    --teal:#0f7a72;
    --teal-light:#2aab9a;
    --teal-dark:#0a6059;
    --teal-soft:#d6efeb;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  html,body{height:100%;}
  @view-transition {
    navigation: auto;
  }
  ::view-transition-old(root),
  ::view-transition-new(root){
    animation:none;
  }
  ::view-transition-group(landing-hero){
    animation-duration:.65s;
    animation-timing-function:cubic-bezier(.22,1,.36,1);
  }
  body{
    font-family:'Inter', sans-serif;
    color:var(--text);
    background:#dcefe9 url('fpb.png') center / cover no-repeat fixed;
    display:flex;
    min-height:100vh;
    overflow-x:hidden;
  }
  body.modal-open{overflow:hidden;}

  .showcase{
    position:relative;
    flex:0.95 1 50%;
    min-width:0;
    z-index:2;
    background:transparent;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    margin:20px 0 20px 20px;
    padding:28px 14% 22px 28px;
    border-radius:28px;
    clip-path:none;
    view-transition-name: landing-hero;
    transition:transform .6s cubic-bezier(.22,1,.36,1), opacity .4s ease, flex .6s cubic-bezier(.22,1,.36,1), margin .6s ease, padding .6s ease, max-width .6s ease, min-width .6s ease;
  }
  .showcase::before{
    content:'';
    position:absolute;
    inset:0;
    z-index:0;
    pointer-events:none;
    background:url('fpb.png') center / cover no-repeat;
  }

  .showcase-hero{
    position:relative;
    z-index:1;
    flex:1;
    min-height:0;
  }

  .showcase-copy{
    position:absolute;
    top:10%;
    left:40px;
    z-index:2;
    max-width:min(380px, 52%);
    pointer-events:none;
  }
  .showcase-copy h2{
    font-family:'Playfair Display', Georgia, serif;
    font-size:clamp(1.7rem, 2.8vw, 2.7rem);
    font-weight:700;
    line-height:1.12;
    letter-spacing:-0.02em;
    color:var(--navy);
    margin-bottom:0.75rem;
  }
  .showcase-copy p{
    font-size:0.92rem;
    line-height:1.65;
    color:var(--body-text);
    margin-bottom:0.85rem;
    max-width:360px;
  }
  .showcase-location{
    display:inline-flex;
    align-items:center;
    gap:0.45rem;
    font-size:0.88rem;
    font-weight:700;
    color:var(--teal);
    letter-spacing:-0.01em;
  }
  .showcase-location svg{
    width:16px;
    height:16px;
    flex-shrink:0;
    fill:currentColor;
  }

  .showcase-img-wrap{
    position:absolute;
    top:-32%;
    left:-2%;
    right:-48%;
    bottom:-28%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:transparent;
    border-radius:28px;
    overflow:hidden;
  }
  .showcase-img-wrap img{
    width:100%;
    height:100%;
    object-fit:contain;
    object-position:center center;
    display:block;
    background:transparent;
    image-rendering:auto;
    -webkit-backface-visibility:hidden;
    backface-visibility:hidden;
    transform:translateZ(0);
    border-radius:28px;
    clip-path:inset(0 round 28px);
  }

  .showcase-features{
    position:relative;
    z-index:4;
    flex-shrink:0;
    width:calc(100% + 18% - 8px);
    max-width:none;
    margin-top:-70px;
    margin-left:-6px;
    margin-right:calc(-18% + 8px);
    background:#fff;
    border:1px solid var(--border);
    border-radius:12px;
    box-shadow:0 8px 32px rgba(15,45,61,.07);
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    overflow:hidden;
  }
  .showcase-feature{
    display:flex;
    align-items:center;
    gap:8px;
    min-width:0;
    padding:12px 12px 12px 14px;
    border-right:1px solid var(--border);
  }
  .showcase-feature:last-child{border-right:none;}
  .showcase-feature svg{
    width:18px;
    height:18px;
    flex-shrink:0;
    stroke:#0f4c4c;
    fill:none;
    stroke-width:1.6;
    stroke-linecap:round;
    stroke-linejoin:round;
  }
  .showcase-feature h3{
    font-size:12px;
    font-weight:700;
    color:#0f4c4c;
    line-height:1.2;
    margin-bottom:2px;
  }
  .showcase-feature p{
    font-size:10.5px;
    line-height:1.35;
    color:#6b7c78;
    margin:0;
  }
  .showcase-feature div{
    min-width:0;
  }

  .login-side{
    flex:1.05 1 50%;
    display:flex;
    flex-direction:column;
    padding:40px 64px;
    position:relative;
    background:transparent;
    z-index:1;
    margin-left:0;
    padding-left:64px;
    animation:loginFadeIn .55s .08s ease both;
    transition:flex .6s cubic-bezier(.22,1,.36,1), padding .55s ease, background .45s ease;
  }
  .showcase{
    display:none;
  }
  .login-side{
    flex:1 1 100%;
    width:100%;
    margin:0;
    border-radius:0;
  }
  .showcase{
    display:block;
    flex:0 0 50%;
    width:50%;
    margin:0;
    padding:0;
    border-radius:0;
    clip-path:none;
    background:transparent;
  }
  .showcase::before,
  .showcase-home{
    display:none;
  }
  .showcase-hero{
    position:absolute;
    inset:0;
    height:100%;
  }
  .showcase-img-wrap{
    inset:0;
    border-radius:0;
    clip-path:polygon(0 0, 100% 0, 84% 100%, 0 100%);
  }
  .showcase-img-wrap img{
    border-radius:0;
    clip-path:none;
    object-fit:cover;
  }
  .showcase-features{
    position:absolute;
    left:28px;
    right:18%;
    bottom:-28px;
    width:auto;
    margin:0;
    z-index:4;
  }
  .login-side{
    flex:1 1 50%;
    width:50%;
  }
  body{
    display:block;
    position:relative;
  }
  .showcase{
    position:absolute;
    inset:36px auto 70px 0;
    width:58%;
    height:auto;
    z-index:1;
    clip-path:none;
    overflow:visible;
  }
  .login-side{
    position:relative;
    z-index:2;
    min-height:100vh;
    width:100%;
    padding-left:calc(50% + 64px);
  }
  @media (max-width:860px){
    body{display:flex;flex-direction:column;}
    .showcase{
      position:relative;
      inset:auto;
      width:100%;
      height:48vh;
      min-height:300px;
      clip-path:none;
    }
    .login-side{
      min-height:52vh;
      width:100%;
      padding:32px 24px;
    }
  }
  .login-brand{
    position:absolute;
    top:24px;
    right:32px;
    z-index:6;
    display:inline-flex;
    align-items:center;
    gap:10px;
    text-decoration:none;
  }
  .login-brand img{
    width:52px;
    height:52px;
    object-fit:contain;
    display:block;
  }
  .login-brand span{
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:1.25rem;
    font-weight:800;
    letter-spacing:-0.03em;
    color:var(--teal-dark);
    white-space:nowrap;
  }
  @keyframes loginFadeIn{
    0%{opacity:0;}
    100%{opacity:1;}
  }
  .showcase-home{
    position:relative;
    z-index:5;
    display:inline-flex;
    align-items:center;
    align-self:flex-start;
    background:var(--navy);
    color:#fff;
    font-size:0.875rem;
    font-weight:600;
    text-decoration:none;
    padding:0.5rem 1rem;
    border-radius:999px;
    margin-bottom:8px;
    pointer-events:auto;
    transition:background 0.2s, transform 0.15s;
  }
  .showcase-home:hover{
    background:var(--accent);
    color:#fff;
    transform:translateY(-1px);
  }

  .login-panels{
    flex:1;
    min-height:0;
    position:relative;
    overflow:hidden;
  }
  .login-panel{
    position:absolute;
    inset:0;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    width:100%;
    transition:opacity .55s ease, transform .6s cubic-bezier(.22,1,.36,1);
    visibility:hidden;
    opacity:0;
    pointer-events:none;
    transform:translateX(28%);
  }
  .login-panel[hidden]{
    display:flex !important;
  }
  .login-panel.is-active{
    visibility:visible;
    opacity:1;
    pointer-events:auto;
    transform:translateX(0);
  }
  .login-panel--login{
    transform:translateX(0);
    max-width:420px;
    margin:0 auto;
  }
  .login-panel--login.leaving,
  .login-panel.leaving{
    transform:translateX(-22%);
    opacity:0;
  }
  .login-panel--register{
    transform:translateX(36%);
  }
  .login-panel--register.is-active{
    transform:translateX(0);
  }
  body.signup-open{
    background:#dcefe9 url('fpb.png') center / cover no-repeat fixed;
  }
  body.signup-open::before{
    content:'';
    position:fixed;
    inset:0;
    z-index:0;
    pointer-events:none;
    background:rgba(220,239,233,.22);
  }
  body.signup-open .showcase{
    flex:0 0 0%;
    max-width:0;
    min-width:0;
    margin-left:0;
    margin-right:0;
    padding-left:0;
    padding-right:0;
    opacity:0;
    transform:translateX(-40%);
    pointer-events:none;
  }
  body.signup-open .login-side{
    flex:1 1 100%;
    width:100%;
    position:relative;
    z-index:1;
    background:transparent;
    margin:0;
    border-radius:0;
    padding:20px 28px;
  }
  body.signup-open .login-side .login-brand{display:none;}
  .login-panel--register{
    overflow:hidden;
    flex-direction:row-reverse;
    flex-wrap:wrap;
    justify-content:stretch;
    align-items:stretch;
    gap:36px;
    padding:12px 24px 12px 8px;
  }
  .signup-hero{
    flex:0 1 38%;
    max-width:440px;
    min-width:0;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    text-align:center;
    padding:56px 28px 56px 28px;
    position:relative;
  }
  .signup-hero h2{
    font-family:'Space Grotesk','Inter',sans-serif;
    font-size:clamp(1.85rem, 2.8vw, 2.7rem);
    font-weight:800;
    line-height:1.12;
    letter-spacing:-0.045em;
    color:#18181b;
    max-width:none;
    margin:0;
  }
  .signup-hero p{
    margin:18px auto 0;
    font-size:15px;
    line-height:1.7;
    color:#8b919a;
    max-width:34ch;
  }
  .signup-hero-foot{
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:10px 26px;
    position:absolute;
    left:28px;
    right:28px;
    bottom:22px;
  }
  .signup-hero-foot a{
    color:var(--teal);
    font-size:14px;
    font-weight:600;
    text-decoration:none;
  }
  .signup-hero-foot a:hover{text-decoration:underline;}
  .signup-panel-foot{
    flex:0 0 100%;
    width:100%;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
    padding:8px 12px 4px;
    font-size:0.85rem;
    color:#8b919a;
  }
  .signup-panel-foot a{
    color:var(--teal);
    font-weight:600;
    text-decoration:none;
  }
  .signup-panel-foot a:hover{text-decoration:underline;}
  .signup-card{
    flex:1 1 70%;
    width:100%;
    max-width:none;
    height:auto;
    max-height:100%;
    min-height:0;
    margin:0;
    align-self:stretch;
    background:#fff;
    border:1px solid rgba(211,230,226,.9);
    border-radius:18px;
    box-shadow:0 22px 60px rgba(15,23,42,.08);
    padding:32px 34px 22px;
    display:flex;
    flex-direction:column;
    overflow:hidden;
  }
  .login-panel--register .signup-layout{
    grid-template-columns:1fr;
  }
  .login-panel--register .signup-card #pharmacy-register-map{
    min-height:0;
    height:100%;
  }
  .signup-progress--compact li,
  .signup-progress--compact li{font-size:10.5px;}
  .signup-card .register-logo-box{
    position:relative;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:4px;
    width:96px;
    height:96px;
    border:1.5px dashed #c9c5bf;
    border-radius:16px;
    background:#f7f6f3;
    overflow:hidden;
    cursor:pointer;
  }
  .signup-card .register-logo-plus{
    width:36px;
    height:36px;
    border-radius:50%;
    background:var(--teal);
    color:#fff;
    font-size:26px;
    font-weight:500;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .signup-card .register-logo-box.has-image .register-logo-plus,
  .signup-card .register-logo-box.has-image .register-logo-preview-placeholder{display:none;}
  .signup-card .register-logo-box:hover{border-color:var(--teal);background:#eef7f5;}
  .signup-card .register-logo-box img{width:100%;height:100%;object-fit:cover;}
  .signup-card .register-logo-preview-placeholder{
    color:#9a968f;font-size:11px;font-weight:600;text-align:center;padding:8px;line-height:1.3;
  }
  .signup-card .register-file-input{
    position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;
  }
  .signup-card .register-day-chip span{
    border:1px solid #d8d5d0;
    background:#fff;
    color:var(--navy);
  }
  .signup-card .register-day-chip input:checked + span{
    background:rgba(15,122,114,.14);
    border-color:var(--teal);
    color:var(--teal);
  }
  .setup-file{
    width:100%;
    border:1px solid #eef0f2;
    background:#f5f8fa;
    border-radius:10px;
    padding:11px 12px;
    font-size:13px;
    font-family:'Inter',sans-serif;
    color:var(--text);
  }
  #panel-pharmacy .pharm-logo-block{
    display:flex;
    flex-direction:column;
    align-items:center;
    text-align:center;
    margin:4px 0 14px;
  }
  #panel-pharmacy .pharm-logo{
    position:relative;
    display:block;
    width:96px;
    height:96px;
    border:1.5px dashed #c9c5bf;
    border-radius:50%;
    background:#f7f6f3;
    overflow:visible;
    cursor:pointer;
  }
  #panel-pharmacy .pharm-logo-plus{
    position:absolute;
    bottom:0;
    right:0;
    z-index:2;
    width:30px;
    height:30px;
    border-radius:50%;
    border:2px solid #fff;
    background:var(--teal);
    color:#fff;
    font-size:20px;
    font-weight:500;
    line-height:1;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 2px 6px rgba(0,0,0,.12);
    transform:translate(10%, -4%);
    pointer-events:none;
  }
  #panel-pharmacy .pharm-logo.has-image .pharm-logo-text{display:none;}
  #panel-pharmacy .pharm-logo:hover{border-color:var(--teal);background:#eef7f5;}
  #panel-pharmacy .pharm-logo.has-image{
    border-color:transparent;
    background:transparent;
  }
  #panel-pharmacy .pharm-logo img{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    object-fit:cover;
    border-radius:50%;
  }
  #panel-pharmacy .pharm-logo-text{display:none;}
  #panel-pharmacy .pharm-logo-input{
    position:absolute;
    inset:0;
    opacity:0;
    cursor:pointer;
  }
  #panel-pharmacy .pharm-doc{
    margin:0 0 16px;
  }
  #panel-pharmacy .pharm-doc-title{
    display:block;
    margin:0 0 8px;
    font-size:13px;
    font-weight:700;
    color:var(--text);
  }
  #panel-pharmacy .pharm-drop{
    position:relative;
    display:flex;
    flex-direction:column;
    align-items:center;
    text-align:center;
    padding:18px 16px 16px;
    border:1.5px dashed #d5d9de;
    border-radius:14px;
    background:#fafbfc;
    transition:border-color .15s ease, background .15s ease;
  }
  #panel-pharmacy .pharm-drop.is-drag{
    border-color:var(--teal);
    background:#eef7f5;
  }
  #panel-pharmacy .pharm-doc-input{
    position:absolute;
    width:1px;
    height:1px;
    opacity:0;
    pointer-events:none;
  }
  #panel-pharmacy .pharm-drop-icon{margin-bottom:6px;}
  #panel-pharmacy .pharm-drop-lead{
    margin:0;
    font-size:13.5px;
    font-weight:600;
    color:#3d4650;
  }
  #panel-pharmacy .pharm-drop-sub{
    margin:4px 0 12px;
    font-size:12px;
    color:#9aa3af;
  }
  #panel-pharmacy .pharm-drop-browse{
    appearance:none;
    border:1px solid #d8dee4;
    background:#fff;
    color:#4b5563;
    border-radius:10px;
    padding:8px 16px;
    font-size:13px;
    font-weight:600;
    font-family:'Inter',sans-serif;
    cursor:pointer;
  }
  #panel-pharmacy .pharm-drop-browse:hover{border-color:var(--teal);color:var(--teal);}
  #panel-pharmacy .pharm-file-list{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-top:10px;
  }
  #panel-pharmacy .pharm-file-card{
    display:flex;
    align-items:flex-start;
    gap:12px;
    margin-top:0;
    padding:12px 12px 12px 12px;
    border:1px solid #e6eaee;
    border-radius:12px;
    background:#fff;
  }
  #panel-pharmacy .pharm-file-card[hidden]{display:none !important;}
  #panel-pharmacy .pharm-file-icon{
    flex:0 0 36px;
    width:36px;
    height:42px;
    border-radius:6px;
    background:#f3f4f6;
    color:#6b7280;
    font-size:8px;
    font-weight:800;
    letter-spacing:.04em;
    display:flex;
    align-items:flex-end;
    justify-content:center;
    padding-bottom:6px;
    position:relative;
    overflow:hidden;
  }
  #panel-pharmacy .pharm-file-icon::before{
    content:"";
    position:absolute;
    top:0;right:0;
    border-width:0 10px 10px 0;
    border-style:solid;
    border-color:#e5e7eb #fff;
  }
  #panel-pharmacy .pharm-file-icon[data-kind="pdf"]{
    background:#fee2e2;
    color:#dc2626;
  }
  #panel-pharmacy .pharm-file-icon[data-kind="pdf"]::before{border-color:#fca5a5 #fff;}
  #panel-pharmacy .pharm-file-icon[data-kind="img"]{
    background:#dbeafe;
    color:#2563eb;
  }
  #panel-pharmacy .pharm-file-icon[data-kind="img"]::before{border-color:#93c5fd #fff;}
  #panel-pharmacy .pharm-file-meta{flex:1;min-width:0;}
  #panel-pharmacy .pharm-file-name{
    margin:0;
    font-size:13px;
    font-weight:700;
    color:#111827;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  #panel-pharmacy .pharm-file-status{
    margin:3px 0 0;
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:6px;
    font-size:12px;
    color:#9aa3af;
  }
  #panel-pharmacy .pharm-file-state{
    display:inline-flex;
    align-items:center;
    gap:5px;
  }
  #panel-pharmacy .pharm-file-state.is-done{color:#16a34a;}
  #panel-pharmacy .pharm-file-spinner{
    width:12px;
    height:12px;
    border:2px solid #d1d5db;
    border-top-color:#3b82f6;
    border-radius:50%;
    animation:pharmSpin .7s linear infinite;
  }
  #panel-pharmacy .pharm-file-check{
    width:12px;
    height:12px;
    border-radius:50%;
    background:#22c55e;
    color:#fff;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:9px;
    font-weight:800;
    line-height:1;
  }
  #panel-pharmacy .pharm-file-bar{
    margin-top:8px;
    height:4px;
    border-radius:999px;
    background:#eceff3;
    overflow:hidden;
  }
  #panel-pharmacy .pharm-file-bar span{
    display:block;
    height:100%;
    width:0;
    border-radius:inherit;
    background:#3b82f6;
    transition:width .12s linear;
  }
  #panel-pharmacy .pharm-file-remove{
    flex-shrink:0;
    width:28px;
    height:28px;
    border:0;
    background:transparent;
    color:#c73e3e;
    border-radius:8px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }
  #panel-pharmacy .pharm-file-remove:hover{background:#fde8e8;color:#a83232;}
  @keyframes pharmSpin{to{transform:rotate(360deg);}}
  #panel-pharmacy .pharm-days{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }
  #panel-pharmacy .pharm-hours{
    display:flex;
    flex-direction:column;
    gap:8px;
  }
  #panel-pharmacy .pharm-hours-row{
    display:flex;
    align-items:center;
    gap:12px;
    padding:8px 10px;
    border:1px solid #e8eee9;
    border-radius:12px;
    background:#f8fbfa;
  }
  #panel-pharmacy .pharm-hours-row.is-open{
    background:#fff;
    border-color:#d7e8e3;
  }
  #panel-pharmacy .pharm-hours-times{
    display:flex;
    align-items:center;
    gap:8px;
    flex:1;
    min-width:0;
  }
  #panel-pharmacy .pharm-hours-times input[type="time"]{
    flex:1;
    min-width:0;
    border:1px solid #eef0f2;
    background:#f5f8fa;
    border-radius:10px;
    padding:8px 10px;
    font:inherit;
    font-size:13px;
    color:var(--text);
  }
  #panel-pharmacy .pharm-hours-times input[type="time"]:disabled{
    opacity:.45;
    cursor:not-allowed;
  }
  #panel-pharmacy .pharm-hours-sep{
    font-size:12px;
    font-weight:700;
    color:#8a97a5;
  }
  #panel-pharmacy .pharm-day{
    position:relative;
    cursor:pointer;
  }
  #panel-pharmacy .pharm-day input{
    position:absolute;
    opacity:0;
    pointer-events:none;
  }
  #panel-pharmacy .pharm-day span{
    display:inline-flex;
    min-width:46px;
    justify-content:center;
    padding:8px 10px;
    border-radius:999px;
    border:1px solid #d8d5d0;
    background:#fff;
    color:#0b2230;
    font-size:12px;
    font-weight:700;
  }
  #panel-pharmacy .pharm-day input:checked + span{
    background:rgba(15,122,114,.14);
    border-color:var(--teal);
    color:var(--teal);
  }
  #panel-pharmacy .register-map,
  #panel-pharmacy #pharmacy-register-map{
    height:100% !important;
    min-height:0;
    border-radius:16px;
    overflow:hidden;
  }
  #panel-pharmacy .signup-progress li{
    font-size:10.5px;
  }
  #panel-pharmacy{
    width:100%;
    height:100%;
  }
  .register-back-btn{
    position:absolute;
    top:10px;
    right:18px;
    z-index:9;
    width:40px;
    height:40px;
    padding:0;
    border:1px solid rgba(15,23,42,.08);
    border-radius:12px;
    background:#fff;
    color:#3f3f46;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 4px 14px rgba(15,23,42,.08);
  }
  .register-back-btn svg{
    width:18px;
    height:18px;
    display:block;
  }
  .register-back-btn:hover{
    background:#f4f6f7;
    color:#111;
    border-color:rgba(15,23,42,.14);
  }
  .signup-card-title{
    font-family:'Space Grotesk',sans-serif;
    font-size:32px;
    font-weight:700;
    color:#111;
    letter-spacing:-0.03em;
    margin:0 0 6px;
  }
  .signup-card-sub{
    font-size:13.5px;
    color:#9aa3ad;
    margin:0 0 18px;
  }
  .signup-progress{
    display:flex;
    list-style:none;
    margin:0 0 22px;
    padding:0;
  }
  .signup-progress li{
    flex:1;
    position:relative;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:8px;
    color:#9aa3ad;
    font-size:11px;
    font-weight:700;
    letter-spacing:.01em;
    text-align:center;
  }
  .signup-progress li:not(:last-child)::after{
    content:'';
    position:absolute;
    top:14px;
    left:calc(50% + 18px);
    right:calc(-50% + 18px);
    height:2px;
    background:#d7e4e0;
  }
  .signup-progress li:not(:last-child)::before{
    content:'';
    position:absolute;
    top:14px;
    left:calc(50% + 18px);
    right:calc(-50% + 18px);
    height:2px;
    background:var(--teal);
    transform:scaleX(0);
    transform-origin:left center;
    transition:transform .45s cubic-bezier(.22,1,.36,1);
    z-index:0;
  }
  .signup-progress li.is-done:not(:last-child)::before{
    transform:scaleX(1);
  }
  .signup-progress-num{
    width:28px;
    height:28px;
    border-radius:50%;
    border:2px solid #d7e4e0;
    background:#fff;
    color:#6b7c78;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    font-weight:800;
    z-index:1;
    transition:background .35s ease, border-color .35s ease, color .35s ease, transform .35s cubic-bezier(.22,1,.36,1), box-shadow .35s ease;
  }
  .signup-progress li{
    transition:color .3s ease;
  }
  .signup-progress li.is-current,
  .signup-progress li.is-done{
    color:var(--teal);
  }
  .signup-progress li.is-current .signup-progress-num,
  .signup-progress li.is-done .signup-progress-num{
    background:var(--teal);
    border-color:var(--teal);
    color:#fff;
  }
  .signup-progress li.is-current .signup-progress-num{
    box-shadow:0 0 0 4px rgba(15,122,114,.16);
    animation:signupProgressPop .45s cubic-bezier(.22,1,.36,1);
  }
  .signup-step:not([hidden]){
    flex:1 1 auto;
    min-height:0;
    overflow:auto;
    animation:signupStepIn .4s cubic-bezier(.22,1,.36,1);
  }
  .signup-card-title,
  .signup-card-sub{
    transition:opacity .25s ease;
  }
  @keyframes signupProgressPop{
    0%{transform:scale(.82);}
    55%{transform:scale(1.12);}
    100%{transform:scale(1);}
  }
  @keyframes signupStepIn{
    from{opacity:0;transform:translateY(10px);}
    to{opacity:1;transform:none;}
  }
  @media (prefers-reduced-motion: reduce){
    .signup-progress li:not(:last-child)::before,
    .signup-progress-num,
    .signup-progress li,
    .signup-card-title,
    .signup-card-sub{
      transition:none;
    }
    .signup-progress li.is-current .signup-progress-num,
    .signup-step:not([hidden]){
      animation:none;
    }
  }
  .signup-step[hidden]{display:none !important;}
  #customer-register-form .signup-step:not([hidden]){
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    width:100%;
    gap:22px;
  }
  #customer-register-form .signup-step .setup-field{
    width:100%;
    max-width:480px;
    margin:0;
  }
  #customer-register-form .signup-step[data-step="3"] .setup-field,
  #pharmacy-register-form .signup-step[data-step="4"] .setup-field{
    max-width:none;
  }
  #pharmacy-register-form .signup-step:not([hidden]){
    display:flex;
    flex-direction:column;
    width:100%;
  }
  #customer-register-form .signup-step[data-step="3"]:not([hidden]),
  #pharmacy-register-form .signup-step[data-step="4"]:not([hidden]){
    justify-content:flex-start;
    align-items:stretch;
    gap:8px;
    overflow:hidden;
    flex:1 1 auto;
    min-height:0;
  }
  #pharmacy-register-form .signup-step[data-step="3"]:not([hidden]){
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    width:100%;
    gap:22px;
  }
  #pharmacy-register-form .signup-step[data-step="3"] .setup-field{
    width:100%;
    max-width:480px;
    margin:0;
  }
  .signup-location-grid{
    display:flex;
    flex-direction:column;
    gap:12px;
    width:100%;
    flex:1 1 auto;
    min-height:0;
  }
  .signup-location-controls{
    display:grid;
    grid-template-columns:minmax(220px, 0.95fr) minmax(340px, 1.55fr);
    gap:6px 12px;
    align-items:end;
    width:100%;
    min-width:0;
  }
  .signup-location-address,
  .signup-location-tools{
    display:flex;
    flex-direction:column;
    gap:6px;
    min-width:0;
    margin:0;
    width:auto;
    max-width:none;
  }
  .signup-location-address label,
  .signup-location-tools .setup-location-label{
    display:block;
    margin:0;
    min-height:16px;
    font-size:12.5px;
    font-weight:600;
    line-height:1.2;
    color:var(--navy);
  }
  .signup-location-address .register-map-search-input,
  .signup-location-controls .register-map-search-input,
  .signup-location-controls .register-map-tool-btn{
    box-sizing:border-box;
    height:42px;
    min-height:42px;
    padding:0 12px;
    border-radius:10px;
    font-size:13px;
    line-height:42px;
  }
  .signup-location-controls .register-map-toolbar{
    flex:1 1 auto;
    flex-wrap:nowrap;
    flex-direction:row;
    margin:0;
    align-items:stretch;
    height:42px;
  }
  .signup-location-controls .register-map-locate-btn{
    white-space:nowrap;
    width:auto;
    flex-shrink:0;
  }
  @media (max-width: 900px){
    .signup-location-controls{
      grid-template-columns:1fr;
    }
  }
  .signup-location-grid > .register-map-hint,
  .signup-location-grid > .register-map-status{
    color:#9a968f;
    margin:0;
  }
  .signup-location-controls,
  .signup-location-grid > .register-map-hint,
  .signup-location-grid > .register-map-status{
    flex-shrink:0;
  }
  .signup-location-map{
    flex:1 1 auto;
    min-width:0;
    min-height:0;
    display:flex;
    flex-direction:column;
    gap:0;
  }
  .signup-location-map-frame{
    position:relative;
    flex:1 1 auto;
    min-height:220px;
    width:100%;
    border-radius:16px;
    overflow:hidden;
  }
  .signup-location-map .register-map-badge{
    position:absolute;
    top:auto;
    bottom:12px;
    left:12px;
    z-index:5;
    padding:10px 14px;
    border-radius:16px;
    background:#fff;
    color:#1b2b34;
    font-size:11px;
    font-weight:700;
    letter-spacing:0.04em;
    text-transform:uppercase;
    box-shadow:0 10px 28px rgba(15,23,42,.18);
    border:1px solid rgba(255,255,255,.9);
  }
  #customer-register-form .signup-location-map #register-map,
  #pharmacy-register-form .signup-location-map #pharmacy-register-map{
    position:absolute;
    inset:0;
    flex:none;
    width:100%;
    height:100% !important;
    min-height:0;
    border-radius:16px;
    overflow:hidden;
  }
  .signup-nav{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:12px;
    margin-top:auto;
    padding-top:16px;
    flex-shrink:0;
    background:#fff;
  }
  .signup-nav-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    margin-left:auto;
  }
  .signup-terms{
    display:none;
    align-items:flex-start;
    gap:10px;
    margin:0;
    flex:1;
    min-width:220px;
    font-size:13.5px;
    color:#3a3a3a;
    line-height:1.4;
  }
  .signup-nav.is-final{
    justify-content:space-between;
  }
  .signup-nav.is-final .signup-terms{
    display:flex;
  }
  .signup-btn-back{
    min-width:110px;
    min-height:38px;
    padding:8px 18px;
    border:1px solid #e6e4e0;
    border-radius:10px;
    background:#fff;
    color:#3a3a3a;
    font-size:13.5px;
    font-weight:700;
    font-family:'Inter',sans-serif;
    cursor:pointer;
  }
  .signup-btn-back:hover{background:#f5f8fa;}
  .signup-btn-next{
    min-width:110px;
    min-height:38px;
    padding:8px 18px;
    border:none;
    border-radius:10px;
    background:var(--teal);
    color:#fff;
    font-size:13.5px;
    font-weight:700;
    font-family:'Inter',sans-serif;
    cursor:pointer;
  }
  .signup-btn-next:hover{background:#0a6059;}
  .signup-terms input{
    width:16px;
    height:16px;
    accent-color:var(--teal);
    flex-shrink:0;
  }
  .signup-terms a{color:var(--teal);font-weight:600;text-decoration:none;}
  .signup-terms a:hover{text-decoration:underline;}
  .legal-modal{
    position:fixed;
    inset:0;
    z-index:80;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px 16px;
  }
  .legal-modal[hidden]{display:none !important;}
  .legal-modal-backdrop{
    position:absolute;
    inset:0;
    background:rgba(11,34,48,.45);
    backdrop-filter:blur(4px);
    -webkit-backdrop-filter:blur(4px);
  }
  .legal-modal-dialog{
    position:relative;
    z-index:1;
    width:100%;
    max-width:560px;
    max-height:min(82vh, 640px);
    display:flex;
    flex-direction:column;
    background:#fff;
    border-radius:24px;
    box-shadow:0 24px 60px rgba(15,23,42,.18);
    padding:28px 28px 20px;
    animation:legalModalIn .32s cubic-bezier(.22,1,.36,1);
  }
  @keyframes legalModalIn{
    from{opacity:0;transform:translateY(12px) scale(.98);}
    to{opacity:1;transform:none;}
  }
  .legal-modal-close{
    position:absolute;
    top:14px;
    right:14px;
    width:36px;
    height:36px;
    border:1px solid rgba(15,23,42,.08);
    border-radius:12px;
    background:#fff;
    color:#3f3f46;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .legal-modal-close:hover{background:#f4f6f7;color:#111;}
  .legal-modal-close svg{width:16px;height:16px;display:block;}
  .legal-modal-dialog h2{
    font-family:'Space Grotesk',sans-serif;
    font-size:24px;
    font-weight:700;
    color:#111;
    letter-spacing:-.03em;
    margin:0 40px 8px 0;
  }
  .legal-modal-lead{
    font-size:13px;
    color:#9aa3ad;
    margin:0 0 16px;
  }
  .legal-modal-body{
    overflow:auto;
    padding-right:6px;
    font-size:14px;
    line-height:1.65;
    color:#3a3a3a;
  }
  .legal-modal-body h3{
    font-size:13px;
    font-weight:800;
    color:var(--teal);
    margin:16px 0 6px;
    letter-spacing:.02em;
  }
  .legal-modal-body h3:first-child{margin-top:0;}
  .legal-modal-body p{margin:0 0 8px;}
  .legal-modal-body ul{
    margin:0 0 8px;
    padding-left:18px;
  }
  .legal-modal-body li{margin-bottom:4px;}
  .legal-modal-ok{
    align-self:flex-end;
    margin-top:16px;
    min-width:110px;
    min-height:38px;
    padding:8px 18px;
    border:none;
    border-radius:10px;
    background:var(--teal);
    color:#fff;
    font-size:13.5px;
    font-weight:700;
    font-family:'Inter',sans-serif;
    cursor:pointer;
  }
  .legal-modal-ok:hover{background:#0a6059;}
  .forgot-modal .legal-modal-dialog{max-width:440px;padding:30px;}
  .forgot-modal h2{font-size:24px;color:var(--navy);margin-bottom:8px;}
  .forgot-modal-lead{font-size:14px;line-height:1.55;color:#6b7c78;margin-bottom:20px;}
  .forgot-modal-form{display:flex;flex-direction:column;gap:14px;}
  .forgot-modal-form label{font-size:13px;font-weight:700;color:var(--navy);}
  .forgot-modal-form input{width:100%;height:48px;padding:0 14px;border:1px solid #d8d5d0;border-radius:10px;font:inherit;color:var(--text);outline:none;}
  .forgot-modal-form input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,122,114,.12);}
  .forgot-modal-submit{height:46px;border:0;border-radius:10px;background:var(--teal);color:#fff;font:700 14px 'Inter',sans-serif;cursor:pointer;}
  .forgot-modal-submit:hover{background:#0a6059;}
  .forgot-modal-status{font-size:13px;line-height:1.45;color:var(--teal);margin-top:2px;}
  .forgot-inline-panel{display:none;width:100%;max-width:420px;flex-direction:column;align-items:center;text-align:center;}
  .login-panel--login.forgot-mode > *:not(.forgot-inline-panel):not(.forgot-code-panel){display:none;}
  .login-panel--login.forgot-mode .forgot-inline-panel{display:flex;}
  .forgot-code-panel{display:none;width:100%;max-width:420px;flex-direction:column;align-items:center;text-align:center;position:relative;z-index:1;}
  .login-panel--login.forgot-code-mode .forgot-inline-panel{display:none;}
  .login-panel--login.forgot-code-mode > *:not(.forgot-code-panel){display:none;}
  .login-panel--login.forgot-code-mode .forgot-code-panel,
  .login-panel--login.google-verify-mode .forgot-code-panel{display:flex;}
  .login-panel--login.google-verify-mode > *:not(.forgot-code-panel){display:none;}
  body.google-verify-open .showcase,
  body.google-verify-open .login-brand,
  body.forgot-open:has(.google-verify-mode) .showcase,
  body.forgot-open:has(.google-verify-mode) .login-brand{display:none;}
  body.google-verify-open .login-side,
  body.forgot-open:has(.google-verify-mode) .login-side{
    width:100%;
    min-height:100vh;
    margin:0;
    padding:40px 24px;
    display:flex;
    flex-direction:column;
    align-items:stretch;
    justify-content:center;
    background:#e8f8f5;
    background-image:
      radial-gradient(circle at -8% 110%, rgba(120, 196, 176, .28) 0 22%, transparent 48%),
      radial-gradient(circle at 108% -8%, rgba(120, 196, 176, .26) 0 22%, transparent 48%);
  }
  body.google-verify-open .login-side::before,
  body.google-verify-open .login-side::after,
  body.forgot-open:has(.google-verify-mode) .login-side::before,
  body.forgot-open:has(.google-verify-mode) .login-side::after{
    content:'+';
    position:absolute;
    color:#7ec9b8;
    font-size:34px;
    font-weight:300;
    line-height:1;
    pointer-events:none;
  }
  body.google-verify-open .login-side::before,
  body.forgot-open:has(.google-verify-mode) .login-side::before{top:56px;right:10%;}
  body.google-verify-open .login-side::after,
  body.forgot-open:has(.google-verify-mode) .login-side::after{bottom:64px;left:11%;}
  body.google-verify-open .login-panel--login,
  body.forgot-open:has(.google-verify-mode) .login-panel--login{max-width:440px;margin:0 auto;background:transparent;}
  .verify-hero{position:relative;width:118px;height:118px;margin:0 0 22px;}
  .verify-hero-circle{width:118px;height:118px;border-radius:50%;background:#d6f1ea;display:grid;place-items:center;}
  .verify-hero-mail{width:58px;height:42px;position:relative;}
  .verify-hero-mail svg{width:58px;height:42px;display:block;}
  .verify-hero-shield{
    position:absolute;right:-8px;bottom:-11px;width:35px;height:35px;border-radius:50%;background:#0b7a66;
    display:grid;place-items:center;box-shadow:0 4px 10px rgba(11,122,102,.28);z-index:2;
  }
  .verify-hero-shield svg{display:block;width:18px;height:18px;}
  .verify-hero-rays{position:absolute;top:10px;right:6px;width:28px;height:22px;}
  .forgot-code-panel h1{font-size:30px;line-height:1.15;color:#0b5f52;margin:0 0 10px;font-family:'Plus Jakarta Sans',sans-serif;}
  .forgot-code-lead{font-size:15px;line-height:1.45;color:#7b8c93;margin:0 0 6px;font-weight:500;}
  .google-existing-notice{font-size:13px;line-height:1.4;color:#0b806f;font-weight:700;margin:0 0 6px;}
  .login-form + .google-existing-notice,
  .login-alert.google-existing-notice{margin:0 0 14px;}
  .forgot-code-email{display:block;font-weight:800;font-size:16px;color:#0b5f52;overflow-wrap:anywhere;margin:0 0 28px;}
  .forgot-code-inputs{display:flex;justify-content:center;gap:14px;width:100%;margin:0 0 18px;}
  .forgot-code-inputs input{
    width:62px;height:62px;text-align:center;border:1.5px solid #dbe4e7;border-radius:14px;background:#fff;
    font:800 24px 'Plus Jakarta Sans','Inter',sans-serif;color:#1c2f36;outline:none;caret-color:#006d5b;
  }
  .forgot-code-inputs input:focus{border-color:#006d5b;border-width:2px;box-shadow:none;}
  .forgot-code-inputs.is-error input,
  .forgot-code-inputs.is-error input:focus{
    border-color:#e11d48;
    border-width:2px;
    background:#fff;
    box-shadow:none;
    caret-color:#e11d48;
  }
  .forgot-code-status{min-height:18px;margin:0 0 10px;font-size:13px;line-height:1.45;color:#e11d48;}
  .forgot-code-status:empty{display:none;}
  .forgot-code-resend{font-size:14px;color:#8a9aa2;margin:0 0 18px;}
  .forgot-code-resend button,.forgot-code-back{border:0;background:none;color:#1aa58a;font:800 14px 'Plus Jakarta Sans','Inter',sans-serif;cursor:pointer;}
  .forgot-code-resend button:disabled{color:#1aa58a;cursor:default;opacity:1;}
  .forgot-code-back{margin-top:4px;}
  .login-panel--login.google-password-mode > *:not(.google-password-panel){display:none;}
  .login-panel--login.google-password-mode .google-password-panel{display:flex;}
  .google-password-panel{display:none;width:100%;max-width:420px;flex-direction:column;align-items:center;text-align:center;}
  .google-password-hero{width:118px;height:118px;border-radius:50%;background:#d6f1ea;display:grid;place-items:center;margin:0 0 24px;position:relative;}
  .google-password-hero svg{width:78px;height:78px;display:block;}
  .google-password-panel h1{font-size:30px;line-height:1.15;color:#0b806f;margin-bottom:12px;}
  .google-password-panel > p{font-size:15px;line-height:1.5;color:#84949b;margin-bottom:26px;}
  .google-password-panel > p .forgot-code-email{color:#263f50;margin:2px 0 0;}
  .google-password-form{display:flex;flex-direction:column;gap:16px;width:100%;text-align:left;}
  .google-password-form label{font-size:14px;font-weight:800;color:#0b806f;margin-bottom:-8px;}
  .google-password-form .input-wrap{position:relative;}
  .google-password-form .input-wrap::before{content:'';position:absolute;left:16px;top:50%;transform:translateY(-50%);width:32px;height:32px;border-radius:50%;background:#dff7f1;z-index:1;}
  .google-password-lock{position:absolute;left:24px;top:50%;transform:translateY(-50%);width:16px;height:18px;z-index:2;}
  .google-password-lock svg{display:block;width:16px;height:18px;}
  .google-password-form input{width:100%;height:56px;padding:0 48px 0 66px;border:1.5px solid #bce9e1;border-radius:14px;background:rgba(255,255,255,.78);font:inherit;color:var(--text);outline:none;box-shadow:0 8px 20px rgba(15,122,114,.06);}
  .google-password-form input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,122,114,.12);}
  .google-password-form .toggle-eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#7c8b99;cursor:pointer;}
  .google-password-submit{height:52px;border:0;border-radius:28px;background:#13aa93;color:#fff;font:800 15px 'Inter',sans-serif;cursor:pointer;box-shadow:0 8px 18px rgba(15,122,114,.16);}
  .google-password-submit:hover{background:#0a6059;}
  .google-password-status{min-height:18px;margin:0;font-size:13px;line-height:1.45;color:#c73e3e;}
  .google-password-status:empty{display:none;}
  .forgot-inline-panel h1{font-size:28px;color:var(--teal);margin-bottom:6px;}
  .forgot-inline-panel > p{font-size:13px;line-height:1.45;color:#8b8984;margin-bottom:18px;}
  .forgot-inline-form{display:flex;flex-direction:column;gap:10px;width:100%;}
  .forgot-inline-form label{text-align:left;font-size:12px;font-weight:700;color:#6b7c78;}
  .forgot-inline-form input{width:100%;height:44px;padding:0 13px;border:1px solid #d8d5d0;border-radius:8px;font:inherit;color:var(--text);outline:none;}
  .forgot-inline-form input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,122,114,.12);}
  .forgot-inline-submit{height:44px;border:0;border-radius:8px;background:var(--teal);color:#fff;font:700 13px 'Inter',sans-serif;cursor:pointer;}
  .forgot-inline-submit:hover{background:#0a6059;}
  .forgot-inline-submit:disabled{opacity:.7;cursor:wait;}
  .forgot-inline-back{border:0;background:none;color:var(--teal);font:700 13px 'Inter',sans-serif;cursor:pointer;}
  .forgot-inline-status{font-size:13px;line-height:1.45;color:var(--teal);margin-top:2px;}
  .forgot-inline-status.error{color:#c73e3e;}
  .forgot-inline-panel .forgot-reset-icon{width:112px;height:112px;border-radius:50%;display:grid;place-items:center;margin-bottom:30px;background:rgba(190,246,238,.55);color:var(--teal);}
  .forgot-reset-icon svg{width:66px;height:66px;}
  body.forgot-open .showcase{display:block;}
  body.forgot-open .login-side{width:100%;min-height:100vh;padding:40px 64px 40px calc(50% + 64px);background:transparent;}
  body.forgot-open .login-panel--login{width:100%;max-width:420px;margin:0 auto;}
  body.forgot-open .forgot-inline-panel{max-width:420px;}
  body.forgot-open .forgot-inline-panel h1{font-size:28px;line-height:1.05;color:var(--teal);margin-bottom:8px;}
  body.forgot-open .forgot-inline-panel > p{max-width:420px;font-size:14px;line-height:1.45;color:#8b8984;margin-bottom:22px;}
  body.forgot-open .forgot-inline-form{max-width:420px;padding:26px 28px 24px;background:rgba(255,255,255,.9);border:1px solid rgba(15,122,114,.12);border-radius:18px;box-shadow:0 10px 25px rgba(15,122,114,.07);gap:14px;}
  body.forgot-open .forgot-inline-form label{font-size:13px;color:#647484;margin-bottom:-6px;}
  body.forgot-open .forgot-email-wrap{position:relative;}
  body.forgot-open .forgot-inline-form input{height:48px;padding:0 14px 0 54px;border:1px solid #dce5eb;border-radius:10px;font-size:15px;color:#647484;}
  body.forgot-open .forgot-email-wrap svg{position:absolute;left:16px;top:50%;width:22px;height:22px;transform:translateY(-50%);fill:none;stroke:#7c8b99;stroke-width:1.8;pointer-events:none;}
  body.forgot-open .forgot-inline-submit{height:46px;border-radius:10px;font-size:14px;}
  body.forgot-open .forgot-inline-back{font-size:13px;margin-top:8px;}
  body.forgot-open.google-verify-open .showcase,
  body.forgot-open.google-verify-open .login-brand{display:none;}
  body.forgot-open.google-verify-open .login-side{
    width:100%;
    min-height:100vh;
    margin:0;
    padding:40px 24px;
    display:flex;
    flex-direction:column;
    align-items:stretch;
    justify-content:center;
    background:#e8f8f5;
    background-image:
      radial-gradient(circle at -8% 110%, rgba(120, 196, 176, .28) 0 22%, transparent 48%),
      radial-gradient(circle at 108% -8%, rgba(120, 196, 176, .26) 0 22%, transparent 48%);
  }
  body.forgot-open.google-verify-open .login-panels{
    overflow:visible;
    width:100%;
    max-width:440px;
    margin:0 auto;
    flex:0 1 auto;
    min-height:0;
    height:auto;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  body.forgot-open.google-verify-open .login-panel--login{
    position:relative;
    inset:auto;
    width:100%;
    max-width:440px;
    height:auto;
    opacity:1;
    visibility:visible;
    transform:none;
  }
  body.forgot-open.google-verify-open .forgot-code-panel{display:flex;}
  .legal-modal.google-location-modal{
    z-index:90;
  }
  .legal-modal.map-area-modal{
    z-index:200000;
  }
  .map-area-modal .legal-modal-dialog{
    max-width:420px;
    max-height:none;
    padding:28px 26px 22px;
    text-align:center;
    background:#fff7f7;
    border:1px solid #f0b4b4;
  }
  .map-area-modal .legal-modal-dialog h2{
    font-size:1.2rem;
    margin:0 0 8px;
    color:#c73e3e;
  }
  .map-area-modal .legal-modal-lead{
    margin:0 0 18px;
    color:#c73e3e;
    font-weight:600;
  }
  .map-area-modal .legal-modal-ok{
    width:100%;
    background:#c73e3e;
  }
  .map-area-modal .legal-modal-ok:hover{
    background:#a83232;
  }
  .google-location-modal .legal-modal-dialog{
    width:min(720px, calc(100vw - 28px));
    max-width:720px;
    max-height:min(92vh, 860px);
    padding:22px 22px 18px;
    text-align:left;
    overflow:auto;
  }
  .google-location-modal .register-map-hint,
  .google-location-modal .register-map-status,
  .google-location-modal .setup-location-label{
    color:#5f6f76;
  }
  .google-location-modal .register-map-badge{
    position:absolute;
    top:auto;
    bottom:12px;
    left:12px;
    z-index:5;
    padding:10px 14px;
    border-radius:16px;
    background:#fff;
    color:#1b2b34;
    font-size:11px;
    font-weight:700;
    letter-spacing:0.04em;
    box-shadow:0 10px 28px rgba(15,23,42,.18);
    border:1px solid rgba(255,255,255,.9);
  }
  .google-location-modal .register-map-search-input{
    border:1px solid #d7e8e3;
    background:#f6fbf9;
    color:#1b2b34;
  }
  .google-location-modal .register-map-search-input::placeholder{color:#adaaa2;}
  .google-location-modal .register-map-tool-btn{
    border:1px solid #d8d5d0;
    background:#f4f3f0;
    color:#1b2b34;
  }
  .google-location-modal .register-map-tool-btn:hover:not(:disabled){background:#e8e6e2;}
  .google-location-modal .register-map-search-results{
    background:#fff;
    border:1px solid #d7e8e3;
    box-shadow:0 12px 28px rgba(15,23,42,.12);
  }
  .google-location-modal .register-map-search-item,
  .google-location-modal .register-map-search-empty{
    color:#1b2b34;
  }
  .google-location-modal .register-map-search-item:hover{background:#f0f7f5;}
  .google-location-modal .signup-location-map-frame{
    position:relative;
  }
  .google-location-modal .legal-modal-dialog h2{
    font-size:1.35rem;
    color:#0b806f;
    margin:0 0 6px;
  }
  .google-location-modal .legal-modal-lead{
    margin:0 0 14px;
    color:#5f6f76;
    font-weight:500;
  }
  .google-location-modal .register-map-toolbar{
    margin-bottom:8px;
  }
  .google-location-modal #google-location-map{
    width:100%;
    height:min(42vh, 360px);
    border-radius:12px;
    border:1px solid #d7e4e1;
    overflow:hidden;
    z-index:1;
  }
  .google-location-modal #google-location-address{
    width:100%;
    min-height:72px;
    margin:10px 0 8px;
    padding:10px 12px;
    border:1px solid #d7e4e1;
    border-radius:10px;
    font:inherit;
    resize:vertical;
  }
  .google-location-modal .google-location-status{
    min-height:1.2em;
    margin:0 0 10px;
    color:#c73e3e;
    font-size:14px;
  }
  .google-location-modal .google-location-submit{
    width:100%;
    min-height:44px;
    border:none;
    border-radius:10px;
    background:var(--teal, #0b806f);
    color:#fff;
    font-size:15px;
    font-weight:700;
    cursor:pointer;
  }
  .google-location-modal .google-location-submit:hover{background:#0a6059;}
  .google-location-modal .google-location-submit:disabled{opacity:.65;cursor:wait;}
  @media (prefers-reduced-motion: reduce){
    .legal-modal-dialog{animation:none;}
  }
  .signup-submit{
    width:auto;
    min-width:140px;
    min-height:38px;
    padding:8px 22px;
    border:none;
    border-radius:10px;
    background:var(--teal);
    color:#fff;
    font-size:13.5px;
    font-weight:700;
    font-family:'Inter',sans-serif;
    cursor:pointer;
    margin-top:10px;
  }
  .signup-submit:hover{background:#0a6059;}
  .signup-nav .signup-submit{margin-top:0;}
  .register-inline-title{
    font-family:'Space Grotesk',sans-serif;
    font-size:24px;
    font-weight:700;
    color:var(--teal);
    letter-spacing:-0.01em;
    margin-bottom:4px;
    align-self:flex-start;
  }
  .register-inline-sub{
    font-size:13px;
    color:#8a8680;
    margin-bottom:18px;
    align-self:flex-start;
  }
  .register-inline-form .field{margin-bottom:12px;}
  .register-inline-form .field label{
    display:block;
    font-size:12.5px;
    font-weight:600;
    color:var(--navy);
    margin-bottom:5px;
  }
  .register-inline-form .field input,
  .register-inline-form .field textarea{
    width:100%;
    background:#f6fbf9;
    border:1px solid #d7e8e3;
    border-radius:12px;
    padding:13px 16px;
    font-size:13.5px;
    font-family:'Inter',sans-serif;
    color:var(--text);
    outline:none;
    transition:border-color .2s ease, box-shadow .2s ease, background .2s ease;
    resize:vertical;
  }
  .register-inline-form .field input:focus,
  .register-inline-form .field textarea:focus{
    border-color:var(--teal);
    background:#fff;
    box-shadow:0 0 0 3px rgba(15,122,114,.12);
  }
  .register-inline-form .field input::placeholder,
  .register-inline-form .field textarea::placeholder{color:#adaaa2;}
  .register-inline-form .field-hint{
    font-size:11px;
    color:#9a968f;
    margin-top:4px;
  }
  .register-inline-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
  }
  .register-inline-form .input-wrap{position:relative;}
  .register-inline-form .input-wrap input{padding-right:42px;}
  .register-inline-form .toggle-eye{
    position:absolute;
    right:10px;
    top:50%;
    transform:translateY(-50%);
    border:none;
    background:transparent;
    color:#9a968f;
    width:30px;height:30px;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;padding:0;
  }
  .register-inline-form .toggle-eye:hover{color:var(--teal);}
  .register-inline-submit{
    width:100%;
    padding:15px;
    border:none;
    border-radius:12px;
    background:var(--teal);
    color:#fff;
    font-size:14.5px;
    font-weight:600;
    font-family:'Inter',sans-serif;
    cursor:pointer;
    margin-top:4px;
    transition:background .2s ease;
  }
  .register-inline-submit:hover{background:#0a6059;}
  .register-inline-alert{
    width:100%;
    margin-bottom:10px;
    padding:10px 12px;
    border-radius:10px;
    font-size:13px;
    line-height:1.4;
    background:#fde8e8;
    color:#a83232;
  }
  .register-map-block-inline{margin:4px 0 14px;}
  .register-map-block-inline .register-map-head label,
  .signup-location-map .setup-location-label,
  .signup-location-tools .setup-location-label{
    font-size:12.5px;font-weight:600;color:var(--navy);
  }
  .register-map-block-inline .register-map-badge{
    background:rgba(15,122,114,.12);color:var(--teal);
  }
  .register-map-block-inline .register-map-search-input,
  .signup-location-map .register-map-search-input,
  .signup-location-controls .register-map-search-input{
    border:1px solid #d7e8e3;background:#f6fbf9;color:var(--text);font-size:13px;
  }
  .register-map-block-inline .register-map-search-input::placeholder,
  .signup-location-map .register-map-search-input::placeholder,
  .signup-location-controls .register-map-search-input::placeholder{color:#adaaa2;}
  .register-map-block-inline .register-map-tool-btn,
  .signup-location-map .register-map-tool-btn,
  .signup-location-controls .register-map-tool-btn{
    border:1px solid #d8d5d0;background:#f4f3f0;color:var(--text);
  }
  .register-map-block-inline .register-map-tool-btn:hover:not(:disabled),
  .signup-location-map .register-map-tool-btn:hover:not(:disabled),
  .signup-location-controls .register-map-tool-btn:hover:not(:disabled){background:#e8e6e2;}
  .register-map-block-inline .register-map-hint,
  .signup-location-map .register-map-hint,
  .signup-location-grid > .register-map-hint{color:#9a968f;}
  .signup-location-controls .register-map-search-results{
    background:#fff;
    border:1px solid #d7e8e3;
    box-shadow:0 12px 28px rgba(15,23,42,.12);
  }
  .signup-location-controls .register-map-search-item,
  .signup-location-controls .register-map-search-empty{
    color:#1b2b34;
  }
  .signup-location-controls .register-map-search-item:hover{background:#f0f7f5;}
  .signup-location-map .register-map{
    border:1px solid #d7e8e3;
  }
  .register-inline-form .register-section-title{
    margin:16px 0 10px;
    font-size:11px;
    font-weight:800;
    letter-spacing:.06em;
    text-transform:uppercase;
    color:var(--teal);
    align-self:flex-start;
    width:100%;
  }
  .register-inline-form .register-section-title:first-of-type{margin-top:0;}
  .register-step{
    width:100%;
    margin:0 0 10px;
    border:1px solid #e4e0da;
    border-radius:14px;
    background:#fff;
    overflow:hidden;
  }
  .register-step-summary{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 16px;
    cursor:pointer;
    list-style:none;
    font-size:12px;
    font-weight:800;
    letter-spacing:.06em;
    text-transform:uppercase;
    color:var(--teal);
    user-select:none;
  }
  .register-step-summary::-webkit-details-marker{display:none;}
  .register-step-summary::marker{content:'';}
  .register-step-chevron{
    width:18px;
    height:18px;
    flex-shrink:0;
    stroke:currentColor;
    fill:none;
    stroke-width:2.2;
    stroke-linecap:round;
    stroke-linejoin:round;
    transition:transform .2s ease;
  }
  .register-step[open] .register-step-chevron{transform:rotate(180deg);}
  .register-step-body{padding:4px 16px 14px;}
  .register-inline-form .register-inline-label{
    display:block;
    margin-bottom:8px;
    font-size:12.5px;
    font-weight:600;
    color:var(--navy);
  }
  .register-inline-form .register-logo-box{
    position:relative;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:4px;
    width:96px;
    height:96px;
    border:1.5px dashed #c9c5bf;
    border-radius:16px;
    background:#f7f6f3;
    overflow:hidden;
    cursor:pointer;
  }
  .register-inline-form .register-logo-plus{
    width:36px;
    height:36px;
    border-radius:50%;
    background:var(--teal);
    color:#fff;
    font-size:26px;
    font-weight:500;
    line-height:34px;
    text-align:center;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .register-inline-form .register-logo-box.has-image .register-logo-plus,
  .register-inline-form .register-logo-box.has-image .register-logo-preview-placeholder{
    display:none;
  }
  .register-inline-form .register-logo-box:hover{
    border-color:var(--teal);
    background:#eef7f5;
  }
  .register-inline-form .register-logo-box img{
    width:100%;
    height:100%;
    object-fit:cover;
  }
  .register-inline-form .register-logo-preview-placeholder{
    color:#9a968f;
    font-size:11px;
    font-weight:600;
    text-align:center;
    padding:8px;
    line-height:1.3;
  }
  .register-inline-form .register-file-input{
    position:absolute;
    width:1px;
    height:1px;
    padding:0;
    margin:-1px;
    overflow:hidden;
    clip:rect(0,0,0,0);
    white-space:nowrap;
    border:0;
  }
  .register-inline-form .register-day-chip span{
    border:1px solid #d8d5d0;
    background:#fff;
    color:var(--navy);
  }
  .register-inline-form .register-day-chip input:checked + span{
    background:rgba(15,122,114,.14);
    border-color:var(--teal);
    color:var(--teal);
  }
  .setup-form{
    width:100%;
    display:flex;
    flex-direction:column;
    gap:8px;
    flex:1;
    min-height:0;
  }
  .signup-layout{
    display:grid;
    grid-template-columns:minmax(280px, 1fr) minmax(300px, 1.2fr);
    gap:20px 32px;
    flex:1;
    min-height:0;
    align-items:stretch;
  }
  .signup-fields{min-width:0;}
  .signup-map-col{
    min-width:0;
    display:flex;
    flex-direction:column;
  }
  .signup-map-col .setup-outline--map,
  .signup-map-col .register-map-block-inline{
    flex:1;
    display:flex;
    flex-direction:column;
    min-height:0;
  }
  .signup-card #register-map,
  .signup-card #pharmacy-register-map{
    min-height:0;
    height:100%;
  }
  .signup-bottom{margin-top:8px;}
  .setup-pair{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px 20px;
  }
  .setup-field{
    min-width:0;
    margin:8px 0 6px;
  }
  .setup-field > label{
    display:block;
    font-size:13px;
    font-weight:700;
    color:#111;
    margin:0 0 7px;
  }
  .setup-field input,
  .setup-field textarea{
    width:100%;
    border:1px solid #eef0f2;
    background:#f5f8fa;
    border-radius:10px;
    padding:13px 16px;
    font-size:14px;
    font-family:'Inter',sans-serif;
    color:var(--text);
    outline:none;
  }
  .setup-field textarea{
    min-height:72px;
    resize:vertical;
    border-radius:12px;
  }
  .setup-field input:focus,
  .setup-field textarea:focus{
    border-color:var(--teal);
    background:#fff;
    box-shadow:0 0 0 3px rgba(15,122,114,.12);
  }
  .setup-field input::placeholder,
  .setup-field textarea::placeholder{
    color:#adaaa2;
  }
  .setup-field .input-wrap{position:relative;}
  .setup-field .input-wrap input{padding-right:44px;}
  .setup-field .toggle-eye{
    position:absolute;right:10px;top:50%;transform:translateY(-50%);
    border:none;background:transparent;color:#9a968f;cursor:pointer;
    width:28px;height:28px;display:flex;align-items:center;justify-content:center;
  }
  .setup-hint{margin:-2px 0 12px;font-size:12px;color:#9aa3ad;line-height:1.45;}
  .setup-mismatch{
    display:flex;
    align-items:center;
    gap:6px;
    margin:8px 0 0;
    font-size:12.5px;
    font-weight:600;
    color:#c73e3e;
    line-height:1.35;
  }
  .setup-mismatch[hidden]{display:none !important;}
  .setup-mismatch svg{flex-shrink:0;}
  .setup-field.is-mismatch input{
    border-color:#e08a8a;
    background:#fff7f7;
  }
  .setup-field.is-mismatch input:focus{
    border-color:#c73e3e;
    box-shadow:0 0 0 3px rgba(199,62,62,.14);
  }
  .setup-location-label{
    display:block;
    font-size:13px;
    font-weight:700;
    color:#111;
    margin:10px 0 8px;
  }
  .setup-outline--map .register-map-block-inline{width:100%;margin:0;}
  .setup-alert{
    width:100%;
    padding:10px 12px;border-radius:10px;background:#fde8e8;color:#a83232;font-size:13px;margin-bottom:14px;
  }
  .setup-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    margin-top:8px;
    padding-top:12px;
  }
  .setup-btn-save{
    border:none;background:var(--teal);color:#fff;font-weight:700;font-size:12px;letter-spacing:.05em;
    padding:12px 24px;border-radius:8px;cursor:pointer;
  }
  .setup-btn-save:hover{background:#0a6059;}
  @media (max-width: 1100px){
    .signup-layout{grid-template-columns:1fr;}
    #customer-register-form .signup-location-map-frame,
    #pharmacy-register-form .signup-location-map-frame{
      min-height:240px;
    }
    .login-panel--register{
      flex-direction:column;
      overflow:auto;
      gap:16px;
      padding:8px;
    }
    .signup-hero{max-width:none;flex:none;padding:16px 8px 12px;}
    .signup-hero h2{max-width:none;font-size:2rem;}
    .signup-hero-foot{position:relative;left:auto;right:auto;bottom:auto;margin-top:16px;}
    .signup-panel-foot{
      flex-direction:column;
      text-align:center;
      padding-top:4px;
    }
    .signup-card{max-width:none;flex:1;}
  }
  @media (max-width: 860px){
    .setup-pair{grid-template-columns:1fr;}
    .signup-card{padding:22px 16px 18px;border-radius:20px;height:auto;}
    #panel-pharmacy .pharm-hours-row{
      flex-direction:column;
      align-items:stretch;
    }
    .login-side:has(#panel-register.is-active),
    body.signup-open .login-side{padding:12px;}
  }

  /* keep old login-form-wrap for the avatar/headline wrapper */
  .login-form-wrap{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    max-width:420px;
    margin:0 auto;
    width:100%;
  }

  .avatar{
    display:none;
  }

  h1.headline{
    font-family:'Space Grotesk', sans-serif;
    font-size:30px;
    font-weight:700;
    color:var(--teal);
    margin-bottom:6px;
    letter-spacing:-0.01em;
  }
  .sub{
    font-size:13.5px;
    color:#8a8680;
    margin-bottom:24px;
    text-align:center;
  }

  .login-form{
    width:100%;
    display:flex;
    flex-direction:column;
    gap:14px;
  }
  .login-form .field{
    width:100%;
  }
  .login-form .input-wrap{
    position:relative;
    width:100%;
  }
  .login-form .field input{
    width:100%;
    background:#fff;
    border:1px solid #d8d5d0;
    border-radius:12px;
    padding:15px 18px;
    font-size:14px;
    font-family:'Inter',sans-serif;
    color:var(--text);
    outline:none;
    transition:border-color .2s ease, box-shadow .2s ease;
  }
  .login-form .input-wrap input{
    padding-left:44px;
    padding-right:18px;
  }
  .login-form .input-wrap.has-toggle input{
    padding-right:46px;
  }
  .login-form .field-icon{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    width:18px;
    height:18px;
    color:#9a968f;
    pointer-events:none;
  }
  .login-form .field-icon svg{
    width:18px;
    height:18px;
    display:block;
    stroke:currentColor;
    fill:none;
    stroke-width:2;
    stroke-linecap:round;
    stroke-linejoin:round;
  }
  .login-form .field input:focus ~ .field-icon,
  .login-form .input-wrap:focus-within .field-icon{
    color:var(--teal);
  }
  .login-form .field input::placeholder{color:#adaaa2;}
  .login-form .field input:focus{
    border-color:var(--teal);
    box-shadow:0 0 0 3px rgba(15,122,114,.12);
  }
  .login-form .toggle-eye{
    position:absolute;
    right:10px;
    top:50%;
    transform:translateY(-50%);
    border:none;
    background:transparent;
    color:#9a968f;
    width:32px;
    height:32px;
    display:none;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    padding:0;
  }
  .login-form .toggle-eye.is-visible{display:flex;}
  .login-form .toggle-eye:hover{color:var(--teal);}
  .login-form .toggle-eye .eye-hide{display:none;}
  .login-form .toggle-eye.is-showing .eye-show{display:none;}
  .login-form .toggle-eye.is-showing .eye-hide{display:block;}

  .forgot-row{
    display:flex;
    justify-content:flex-end;
    margin-top:-4px;
  }
  .forgot-row a{
    font-size:12.5px;
    font-weight:700;
    color:var(--accent);
    text-decoration:none;
  }
  .forgot-row a:hover{text-decoration:underline;}

  .login-alert{
    width:100%;
    margin-bottom:8px;
    padding:10px 12px;
    border-radius:10px;
    font-size:13px;
    line-height:1.4;
  }
  .login-alert.error{background:#fde8e8;color:#a83232;overflow-wrap:anywhere;word-break:break-word;}
  .login-alert.success{background:#e7f4f1;color:#0a6059;}

  .divider{
    display:flex;
    align-items:center;
    gap:14px;
    color:var(--muted);
    font-size:11px;
    margin:6px 0;
    text-transform:uppercase;
    letter-spacing:.12em;
  }
  .divider::before,.divider::after{
    content:"";
    flex:1;
    height:1px;
    background:var(--line);
  }

  .google-btn{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    width:100%;
    padding:14px;
    border-radius:12px;
    border:1px solid var(--line);
    background:#fff;
    font-size:14px;
    font-family:'Inter',sans-serif;
    color:var(--text);
    cursor:pointer;
    transition:border-color .2s ease, box-shadow .2s ease;
  }
  .google-btn:hover{
    border-color:#cfccc5;
    box-shadow:0 2px 10px rgba(0,0,0,.04);
  }
  .google-btn svg{width:16px;height:16px;}
  a.google-btn{text-decoration:none;box-sizing:border-box;}

  .login-btn{
    width:100%;
    padding:16px;
    border:none;
    border-radius:12px;
    background:var(--teal);
    color:#fff;
    font-size:14.5px;
    font-weight:600;
    font-family:'Inter',sans-serif;
    cursor:pointer;
    margin-top:8px;
    transition:background .2s ease, transform .15s ease;
    letter-spacing:.01em;
  }
  .login-btn:hover{background:#0a6059;}
  .login-btn:active{transform:scale(.99);}

  .signup-row{
    text-align:center;
    font-size:13px;
    color:var(--muted);
    margin-top:16px;
    display:flex;
    flex-direction:column;
    gap:8px;
  }
  .signup-row a{
    color:var(--accent);
    text-decoration:none;
    font-weight:600;
  }
  .signup-row a:hover{text-decoration:underline;}
  .signup-sep{margin:0 8px;color:var(--line);}
  .signup-links{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:0;
  }

  @media (max-width: 1320px){
    .showcase-copy{
      top:0;
      left:0;
      max-width:min(260px, 42%);
    }
    .showcase-copy h2{
      font-size:clamp(1.2rem, 1.9vw, 1.75rem);
      line-height:1.15;
      margin-bottom:0.4rem;
    }
    .showcase-copy p{
      font-size:0.78rem;
      line-height:1.45;
      margin-bottom:0.45rem;
      max-width:240px;
    }
    .showcase-location{font-size:0.74rem;}
  }
  @media (max-width: 1100px){
    .showcase-features{grid-template-columns:repeat(2,1fr);}
    .showcase-feature:nth-child(2){border-right:none;}
    .showcase-feature:nth-child(1),
    .showcase-feature:nth-child(2){border-bottom:1px solid var(--border);}
    .showcase-copy{
      top:0;
      left:0;
      max-width:min(230px, 40%);
    }
    .showcase-copy h2{font-size:1.25rem;}
    .showcase-copy p{font-size:0.72rem;line-height:1.4;}
    .showcase-location{font-size:0.7rem;}
  }
  @media (max-width: 860px){
    body{flex-direction:column;}
    .showcase{
      clip-path:none;
      min-height:auto;
      margin:16px 16px 0;
      padding:20px 16px 16px;
      border-radius:24px;
    }
    .showcase-copy{
      top:0;
      left:0;
      max-width:min(240px, 48%);
    }
    .showcase-copy h2{font-size:1.3rem;margin-bottom:0.35rem;}
    .showcase-copy p{font-size:0.75rem;margin-bottom:0.4rem;}
    .showcase-img-wrap{min-height:220px;}
    .showcase-features{width:100%;margin-right:0;}
    .login-side{margin-left:0;padding:32px 24px;}
    .login-brand{top:16px;right:20px;gap:10px;}
    .login-brand img{width:44px;height:44px;}
    .login-form-wrap{padding:20px 0;}
  }
  /* Keep the landing image visible while Google verification is shown. */
  body.google-verify-open .showcase,
  body.forgot-open.google-verify-open .showcase{
    display:block;
    position:absolute;
    inset:36px auto 70px 0;
    width:58%;
    height:auto;
    margin:0;
    padding:0;
    background:transparent;
    overflow:visible;
  }
  body.google-verify-open .login-brand,
  body.forgot-open.google-verify-open .login-brand{display:inline-flex;}
  body.google-verify-open .login-side,
  body.forgot-open.google-verify-open .login-side{
    width:100%;
    min-height:100vh;
    margin:0;
    padding:40px 64px 40px calc(50% + 64px);
    display:flex;
    align-items:stretch;
    justify-content:center;
    background:transparent;
    background-image:none;
  }
  body.google-verify-open .login-panels,
  body.forgot-open.google-verify-open .login-panels{
    width:100%;
    max-width:440px;
    flex:0 1 auto;
    min-height:0;
    margin:0 auto;
  }
  body.google-verify-open .login-panel--login,
  body.forgot-open.google-verify-open .login-panel--login{
    max-width:440px;
    margin:0 auto;
    background:transparent;
  }
  @media (max-width:860px){
    body.google-verify-open .showcase,
    body.forgot-open.google-verify-open .showcase{display:none;}
    body.google-verify-open .login-side,
    body.forgot-open.google-verify-open .login-side{padding:32px 24px;}
  }
</style>
</head>
<body<?= $openGoogleVerify ? ' class="forgot-open google-verify-open"' : ($openGooglePassword ? ' class="forgot-open"' : '') ?>>

  <section class="showcase" aria-label="Find medicine nearby">
    <div class="showcase-hero">
      <div class="showcase-copy">
        <h2>Find medicine nearby.</h2>
        <p>Search nearby pharmacies. Check live stock. Reserve online. Pick up when it's ready.</p>
        <div class="showcase-location">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/>
          </svg>
          <span>Serving Laoag City · San Nicolas · Batac</span>
        </div>
      </div>
      <div class="showcase-img-wrap">
        <img src="fp.png" alt="Pharmacies and medicines available in Laoag City, San Nicolas, and Batac">
      </div>
    </div>
    <div class="showcase-features">
      <div class="showcase-feature">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <div>
          <h3>Search nearby pharmacies</h3>
          <p>Find medicines available at pharmacies in your area.</p>
        </div>
      </div>
      <div class="showcase-feature">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/></svg>
        <div>
          <h3>Check live stock</h3>
          <p>See medicine availability before you go.</p>
        </div>
      </div>
      <div class="showcase-feature">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
        <div>
          <h3>Pay with GCash</h3>
          <p>Pay a down payment to reserve your order.</p>
        </div>
      </div>
      <div class="showcase-feature">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 21v-5a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v5"/><path d="M17.774 10.31a1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.451 0 1.12 1.12 0 0 0-1.548 0 2.5 2.5 0 0 1-3.452 0 1.12 1.12 0 0 0-1.549 0 2.5 2.5 0 0 1-3.77-3.248l2.889-4.184A2 2 0 0 1 7 2h10a2 2 0 0 1 1.653.873l2.895 4.192a2.5 2.5 0 0 1-3.774 3.245"/><path d="M4 10.95V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8.05"/></svg>
        <div>
          <h3>Pick up locally</h3>
          <p>Collect your order and pay the balance at the pharmacy.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="login-side">
    <a class="login-brand" href="<?= htmlspecialchars(app_url(), ENT_QUOTES, 'UTF-8') ?>" aria-label="Go to AddToMar home">
      <img src="2.png" alt="">
      <span>AddToMar</span>
    </a>
    <div class="login-panels">

      <!-- LOGIN PANEL -->
      <div class="login-panel login-panel--login is-active<?= $openGooglePassword ? ' google-password-mode' : ($openGoogleVerify ? ' google-verify-mode' : '') ?>" id="panel-login">
        <h1 class="headline">Welcome back</h1>
        <p class="sub">Login to browse medicines, manage orders, and pick up nearby.</p>

        <?php if ($registerSuccess !== ''): ?>
        <div class="login-alert success"><?= htmlspecialchars($registerSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($pharmacyRegisterSuccess !== ''): ?>
        <div class="login-alert success"><?= htmlspecialchars($pharmacyRegisterSuccess, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($googleExistingNotice): ?>
        <div class="login-alert success google-existing-notice">This Gmail already has an AddToMar account.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
        <div class="login-alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form class="login-form" method="post" action="">
          <div class="field">
            <div class="input-wrap">
              <input id="email" name="email" type="email" placeholder="Email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">
              <span class="field-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
              </span>
            </div>
          </div>
          <div class="field">
            <div class="input-wrap has-toggle">
              <input id="password" name="password" type="password" placeholder="Password" required autocomplete="current-password">
              <span class="field-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
              </span>
              <button type="button" class="toggle-eye" id="toggle-password" aria-label="Show password" hidden>
                <svg class="eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
              </button>
            </div>
          </div>
          <div class="forgot-row">
            <a href="#" id="open-forgot-password">Forgot password?</a>
          </div>
          <button type="submit" class="login-btn">Login</button>
          <div class="divider">OR</div>
          <a class="google-btn" href="<?= htmlspecialchars(app_url('google-login.php'), ENT_QUOTES, 'UTF-8') ?>">
            <svg viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.5 5.5 29.5 3.5 24 3.5 12.7 3.5 3.5 12.7 3.5 24S12.7 44.5 24 44.5 44.5 35.3 44.5 24c0-1.2-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.5 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.5 5.5 29.5 3.5 24 3.5c-7.7 0-14.4 4.4-17.7 10.8z"/><path fill="#4CAF50" d="M24 44.5c5.4 0 10.3-1.9 14.1-5.1l-6.5-5.5C29.5 35.6 26.9 36.5 24 36.5c-5.3 0-9.7-3.1-11.3-7.6l-6.6 5.1C9.5 40 16.2 44.5 24 44.5z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.7l6.5 5.5C41.5 36 44.5 30.6 44.5 24c0-1.2-.1-2.4-.9-3.5z"/></svg>
            Login with Google
          </a>
          <p class="signup-row">
            <span>Don't have an account?</span>
            <span class="signup-links">
              <a href="#" id="open-customer-register">Create account</a>
              <span class="signup-sep">|</span>
              <a href="#" id="open-pharmacy-register">Register pharmacy</a>
            </span>
          </p>
        </form>
        <div class="forgot-inline-panel" id="forgot-inline-panel">
          <div class="forgot-reset-icon" aria-hidden="true">
            <svg viewBox="0 0 80 80" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
              <rect x="13" y="19" width="54" height="42" rx="9" fill="#fff" stroke-width="2"/>
              <path d="m15 24 25 20 25-20" stroke-width="3"/>
              <circle cx="58" cy="58" r="14" fill="var(--teal)" stroke="#fff" stroke-width="2"/>
              <path d="M64 58a6 6 0 1 1-2-4.5" stroke="#fff" stroke-width="2.5"/>
              <path d="M62 51v4h-4" stroke="#fff" stroke-width="2.5"/>
            </svg>
          </div>
          <h1>Reset password</h1>
          <p>Enter your email address and we’ll send you<br>a link to reset your password.</p>
          <form class="forgot-inline-form" id="forgot-inline-form">
            <label for="forgot-inline-email">Email address</label>
            <div class="forgot-email-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
              <input id="forgot-inline-email" name="email" type="email" placeholder="you@example.com" autocomplete="email" required>
            </div>
            <button type="submit" class="forgot-inline-submit">Send reset link</button>
            <p class="forgot-inline-status" id="forgot-inline-status" role="status" hidden></p>
            <button type="button" class="forgot-inline-back" id="forgot-inline-back">Back to login</button>
          </form>
        </div>
        <div class="forgot-code-panel" id="forgot-code-panel">
          <div class="verify-hero" aria-hidden="true">
            <span class="verify-hero-rays">
              <svg viewBox="0 0 28 22" fill="none" stroke="#0b7a66" stroke-width="2.2" stroke-linecap="round">
                <path d="M8 4l4-3M16 8l6-4M20 14h7"/>
              </svg>
            </span>
            <div class="verify-hero-circle">
              <div class="verify-hero-mail">
                <svg viewBox="0 0 58 42" aria-hidden="true">
                  <rect x="1" y="8" width="56" height="32" rx="6" fill="#fff" stroke="#0b7a66" stroke-width="2"/>
                  <path d="M2 10l27 17L56 10" fill="#fff" stroke="#0b7a66" stroke-width="2.2" stroke-linejoin="round"/>
                  <path d="M8 10h42L29 24 8 10z" fill="#0b7a66"/>
                </svg>
                <span class="verify-hero-shield">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6z"/>
                    <path d="m9 12 2.2 2.2L16 10"/>
                  </svg>
                </span>
              </div>
            </div>
          </div>
          <h1>Enter verification code</h1>
          <p class="forgot-code-lead">We sent a 4-digit code to your Gmail.</p>
          <?php if ($googleExistingAccount): ?>
          <p class="google-existing-notice">This Gmail already has an AddToMar account.</p>
          <?php endif; ?>
          <span class="forgot-code-email" id="forgot-code-email"><?= htmlspecialchars($googleVerifyEmail, ENT_QUOTES, 'UTF-8') ?></span>
          <div class="forgot-code-inputs" id="forgot-code-inputs" aria-label="Verification code">
            <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Digit 1">
            <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2">
            <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3">
            <input type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4">
          </div>
          <p class="forgot-code-status" id="forgot-code-status" role="status"></p>
          <p class="forgot-code-resend">Didn't receive a code? <button type="button" id="forgot-code-resend" disabled>Resend in <span id="forgot-code-countdown">20</span> seconds</button></p>
          <button type="button" class="forgot-code-back" id="forgot-code-back">Change email</button>
        </div>
        <div class="google-password-panel" id="google-password-panel">
          <div class="google-password-hero" aria-hidden="true">
            <svg viewBox="0 0 96 96" fill="none">
              <rect x="17" y="39" width="52" height="39" rx="8" fill="#10aa91"/>
              <path d="M29 39V28c0-10 7-17 15-17s15 7 15 17v11" stroke="#0b806f" stroke-width="8" stroke-linecap="round"/>
              <circle cx="43" cy="57" r="6" fill="white"/>
              <path d="M43 62v9" stroke="white" stroke-width="4" stroke-linecap="round"/>
              <path d="m70 48 15 6v12c0 10-6 17-15 21-9-4-15-11-15-21V54z" fill="#087e6d" stroke="#fff" stroke-width="3"/>
              <path d="m63 66 5 5 10-11" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h1>Set your password</h1>
          <p>Create a password so you can also sign in with<br><span class="forgot-code-email"><?= htmlspecialchars($googleVerifyEmail, ENT_QUOTES, 'UTF-8') ?></span></p>
          <form class="google-password-form" id="google-password-form">
            <label for="google-password">Password</label>
            <div class="input-wrap">
              <span class="google-password-lock" aria-hidden="true"><svg viewBox="0 0 24 26" fill="none" stroke="#0b806f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="13" rx="2"/><path d="M8"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="16" r="1.2" fill="#0b806f"/><path d="M12 17.5v3"/></svg></span>
              <input id="google-password" name="password" type="password" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
              <button type="button" class="toggle-eye" id="toggle-google-password" aria-label="Show password">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <label for="google-password-confirm">Repeat password</label>
            <div class="input-wrap">
              <span class="google-password-lock" aria-hidden="true"><svg viewBox="0 0 24 26" fill="none" stroke="#0b806f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="13" rx="2"/><path d="M8"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="16" r="1.2" fill="#0b806f"/><path d="M12 17.5v3"/></svg></span>
              <input id="google-password-confirm" name="password_confirm" type="password" placeholder="Re-enter password" required minlength="8" autocomplete="new-password">
              <button type="button" class="toggle-eye" id="toggle-google-password-confirm" aria-label="Show password">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <p class="google-password-status" id="google-password-status" role="status"></p>
            <button type="submit" class="google-password-submit">Save password and continue</button>
          </form>
        </div>
      </div>

      <?php include __DIR__ . '/partials/pharmacy-register-modal.php'; ?>

      <div class="login-panel login-panel--register" id="panel-register" <?= $openRegisterModal ? '' : 'hidden' ?>>
        <button type="button" class="register-back-btn" id="register-back-btn" aria-label="Back to sign in">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6L6 18"/>
          </svg>
        </button>
        <div class="signup-hero">
          <div>
            <h2>Just a few steps to create an account.</h2>
            <p>Fill in your details, set a password, and you’re ready to search and pick up nearby.</p>
          </div>
          <div class="signup-hero-foot">
            <a href="#terms" class="js-legal-link" data-legal="terms">Terms</a>
            <a href="#privacy" class="js-legal-link" data-legal="privacy">Privacy Policy</a>
            <a href="<?= htmlspecialchars(app_url() . '#contact', ENT_QUOTES, 'UTF-8') ?>">Contact Us</a>
          </div>
        </div>
        <div class="signup-card">
          <h2 class="signup-card-title" id="setup-title">Personal details</h2>
          <p class="signup-card-sub" id="setup-sub">Step 1 of 3 — tell us who you are.</p>

          <ol class="signup-progress" aria-label="Account setup steps">
            <li class="is-current" data-progress="1">
              <span class="signup-progress-num">1</span>
              <span>Personal details</span>
            </li>
            <li data-progress="2">
              <span class="signup-progress-num">2</span>
              <span>Set password</span>
            </li>
            <li data-progress="3">
              <span class="signup-progress-num">3</span>
              <span>Location</span>
            </li>
          </ol>

          <?php if ($openRegisterModal && $registerError !== ''): ?>
          <div class="setup-alert"><?= htmlspecialchars($registerError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <form method="post" action="" class="setup-form" id="customer-register-form">
            <input type="hidden" name="action" value="register_customer">
            <input type="hidden" name="latitude" id="register-latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="longitude" id="register-longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="signup-step" data-step="1">
              <div class="setup-field">
                <label for="register-full-name">Name</label>
                <input id="register-full-name" name="full_name" type="text" placeholder="e.g. Maria Santos" value="<?= htmlspecialchars($registerValues['full_name'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
              </div>
              <div class="setup-field">
                <label for="register-contact">Contact number</label>
                <input id="register-contact" name="contact_number" type="tel" inputmode="numeric" maxlength="11" pattern="\d{11}" placeholder="09171234567" value="<?= htmlspecialchars($registerValues['contact_number'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="tel">
              </div>
              <div class="setup-field">
                <label for="register-email">Email</label>
                <input id="register-email" name="email" type="email" placeholder="you@email.com" value="<?= htmlspecialchars($registerValues['email'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
                <p class="setup-hint">Use an existing Gmail account that you can access. It will be used to reset your password if you forget it.</p>
              </div>
            </div>

            <div class="signup-step" data-step="2" hidden>
              <div class="setup-field">
                <label for="register-password">Password</label>
                <div class="input-wrap">
                  <input id="register-password" name="password" type="password" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
                  <button type="button" class="toggle-eye" id="toggle-register-password" aria-label="Show password">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
                <p class="setup-hint">Use 8 or more characters with a mix of letters, numbers &amp; symbols.</p>
              </div>
              <div class="setup-field">
                <label for="register-password-confirm">Repeat Password</label>
                <div class="input-wrap">
                  <input id="register-password-confirm" name="password_confirm" type="password" placeholder="Re-enter password" required minlength="8" autocomplete="new-password">
                  <button type="button" class="toggle-eye" id="toggle-register-password-confirm" aria-label="Show password">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
                <p class="setup-mismatch" id="register-password-mismatch" hidden role="alert">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                  Passwords do not match.
                </p>
              </div>
            </div>

            <div class="signup-step" data-step="3" hidden>
              <div class="signup-location-grid">
                <div class="signup-location-controls">
                  <div class="setup-field signup-location-address">
                    <label for="register-address">Address</label>
                    <input id="register-address" name="address" type="text" class="register-map-search-input" placeholder="House no., street, barangay, city" value="<?= htmlspecialchars($registerValues['address'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="street-address">
                  </div>
                  <div class="signup-location-tools">
                    <label class="setup-location-label" for="register-map-search">Pin your location</label>
                    <div class="register-map-toolbar">
                      <div class="register-map-search-wrap">
                        <input type="search" id="register-map-search" class="register-map-search-input" placeholder="Search barangay, street, or landmark…" autocomplete="off" aria-label="Search location on map">
                        <button type="button" id="register-map-search-btn" class="register-map-tool-btn">Search</button>
                        <ul id="register-map-search-results" class="register-map-search-results" hidden></ul>
                      </div>
                      <button type="button" id="register-map-locate-btn" class="register-map-tool-btn register-map-locate-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-4.35 7-10a7 7 0 1 0-14 0c0 5.65 7 10 7 10z"/><circle cx="12" cy="11" r="2.5"/></svg>
                        Use my location
                      </button>
                    </div>
                  </div>
                </div>
                <p id="register-map-status" class="register-map-status" hidden aria-live="polite"></p>
                <div class="signup-location-map">
                  <div class="signup-location-map-frame">
                    <span class="register-map-badge">Laoag · San Nicolas · Batac</span>
                    <div id="register-map" class="register-map" aria-label="Map for setting your pickup location"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="signup-nav" id="signup-nav">
              <label class="signup-terms" for="register-accept-terms">
                <input type="checkbox" name="accept_terms" id="register-accept-terms" value="1" required>
                <span>I accept the <a href="#terms" class="js-legal-link" data-legal="terms">Terms</a> and <a href="#privacy" class="js-legal-link" data-legal="privacy">Privacy Policy</a></span>
              </label>
              <div class="signup-nav-actions">
                <button type="button" class="signup-btn-back" id="signup-back-btn" hidden>Back</button>
                <button type="button" class="signup-btn-next" id="signup-next-btn">Next</button>
                <button type="submit" class="signup-submit" id="signup-submit-btn" hidden>Sign Up</button>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div><!-- /.login-panels -->
  </section>

  <div class="legal-modal" id="legal-modal" hidden>
    <div class="legal-modal-backdrop" data-legal-close></div>
    <div class="legal-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title">
      <button type="button" class="legal-modal-close" data-legal-close aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
      <h2 id="legal-modal-title">Terms</h2>
      <p class="legal-modal-lead" id="legal-modal-lead"></p>
      <div class="legal-modal-body" id="legal-modal-terms">
        <h3>Using AddToMar</h3>
        <p>AddToMar helps you search nearby pharmacies in Laoag City, San Nicolas, and Batac, check live stock, reserve medicines, and pick them up when ready.</p>
        <h3>Your account</h3>
        <ul>
          <li>Provide accurate details so pharmacies can confirm your reservation.</li>
          <li>Keep your password private and do not share your account.</li>
          <li>You are responsible for orders placed under your account.</li>
        </ul>
        <h3>Reservations and pickup</h3>
        <p>A reservation is a request to hold stock. It is not a medical prescription. Pharmacies may confirm, adjust, or decline an order based on availability and applicable rules. Pay any required down payment (such as GCash) and collect the balance at pickup.</p>
        <h3>Location</h3>
        <p>The location you pin is used to show the nearest pharmacies where you can buy medicines and pick them up. Use a location you can actually travel to for pickup.</p>
        <h3>Not medical advice</h3>
        <p>AddToMar does not diagnose conditions or replace a pharmacist or doctor. Follow medicine labels and professional advice.</p>
        <h3>Acceptable use</h3>
        <p>Do not misuse the service, submit false information, or interfere with other users or partner pharmacies. We may suspend accounts that violate these terms.</p>
      </div>
      <div class="legal-modal-body" id="legal-modal-privacy" hidden>
        <h3>Information we collect</h3>
        <p>When you create an account we collect your name, mobile number, email, address, and the map location you pin. We also store your password in a protected form, plus order and reservation activity.</p>
        <h3>How we use it</h3>
        <ul>
          <li>Create and secure your account</li>
          <li>Find nearest pharmacies for buying and picking up medicines</li>
          <li>Let partner pharmacies fulfill reservations</li>
          <li>Send updates about your orders</li>
        </ul>
        <h3>Sharing</h3>
        <p>We share only what is needed with the pharmacy handling your order. We do not sell your personal information.</p>
        <h3>Your choices</h3>
        <p>You can update account details in AddToMar. For questions about your data, use Contact Us on the home page.</p>
      </div>
      <button type="button" class="legal-modal-ok" data-legal-close>Close</button>
    </div>
  </div>

  <div class="legal-modal google-location-modal" id="google-location-modal" hidden>
    <div class="legal-modal-backdrop" aria-hidden="true"></div>
    <div class="legal-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="google-location-title">
      <h2 id="google-location-title">Pin your location</h2>
      <p class="legal-modal-lead">Choose a pickup point in Laoag City, San Nicolas, or Batac City.</p>
      <div class="register-map-toolbar">
        <div class="register-map-search-wrap">
          <input type="search" id="google-location-map-search" class="register-map-search-input" placeholder="Search barangay, street, or landmark…" autocomplete="off" aria-label="Search location on map">
          <button type="button" id="google-location-map-search-btn" class="register-map-tool-btn">Search</button>
          <ul id="google-location-map-search-results" class="register-map-search-results" hidden></ul>
        </div>
        <button type="button" id="google-location-map-locate-btn" class="register-map-tool-btn register-map-locate-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-4.35 7-10a7 7 0 1 0-14 0c0 5.65 7 10 7 10z"/><circle cx="12" cy="11" r="2.5"/></svg>
          Use my location
        </button>
      </div>
      <p id="google-location-map-status" class="register-map-status" hidden aria-live="polite"></p>
      <div class="signup-location-map-frame">
        <span class="register-map-badge">Laoag · San Nicolas · Batac</span>
        <div id="google-location-map" class="register-map" aria-label="Map for setting your pickup location"></div>
      </div>
      <label for="google-location-address" class="setup-location-label">Address</label>
      <textarea id="google-location-address" name="address" rows="3" placeholder="House no., street, barangay, city"></textarea>
      <input type="hidden" id="google-location-latitude" value="">
      <input type="hidden" id="google-location-longitude" value="">
      <p class="google-location-status" id="google-location-status" role="status"></p>
      <button type="button" class="google-location-submit" id="google-location-submit">Save location and continue</button>
    </div>
  </div>

  <?php include __DIR__ . '/partials/map-area-modal.php'; ?>

<script>
function toastComingSoon() {
  alert('Social sign-in is coming soon. Please use your email and password.');
}

(function setupForgotPassword() {
  const loginPanel = document.getElementById('panel-login');
  const opener = document.getElementById('open-forgot-password');
  const back = document.getElementById('forgot-inline-back');
  const form = document.getElementById('forgot-inline-form');
  const email = document.getElementById('forgot-inline-email');
  const status = document.getElementById('forgot-inline-status');
  const codePanel = document.getElementById('forgot-code-panel');
  const submitButton = form?.querySelector('.forgot-inline-submit');
  const codeEmail = document.getElementById('forgot-code-email');
  const codeInputs = [...document.querySelectorAll('#forgot-code-inputs input')];
  const codeBack = document.getElementById('forgot-code-back');
  const resend = document.getElementById('forgot-code-resend');
  const countdown = document.getElementById('forgot-code-countdown');
  if (!loginPanel || !opener || !back || !form || !email || !status || !codePanel) return;

  opener.addEventListener('click', (event) => {
    event.preventDefault();
    loginPanel.classList.add('forgot-mode');
    loginPanel.classList.remove('forgot-code-mode');
    document.body.classList.add('forgot-open');
    status.hidden = true;
    email.value = '';
    window.setTimeout(() => email.focus(), 50);
  });

  back.addEventListener('click', () => {
    loginPanel.classList.remove('forgot-mode');
    document.body.classList.remove('forgot-open');
  });

  codeBack?.addEventListener('click', () => {
    if (loginPanel.classList.contains('google-verify-mode')) return;
    loginPanel.classList.remove('forgot-code-mode');
    loginPanel.classList.add('forgot-mode');
    email.focus();
  });

  codeInputs.forEach((input, index) => {
    input.addEventListener('input', () => {
      if (loginPanel.classList.contains('google-verify-mode')) return;
      input.value = input.value.replace(/\D/g, '').slice(-1);
      codePanel.querySelector('.forgot-code-inputs')?.classList.remove('is-error');
      const codeStatus = document.getElementById('forgot-code-status');
      if (codeStatus) codeStatus.textContent = '';
      if (codeInputs.every((box) => box.value)) {
        fetch(window.location.pathname, {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
          body: new URLSearchParams({action: 'forgot_verify', code: codeInputs.map((box) => box.value).join('')}),
        }).then((response) => response.json()).then((result) => {
          if (!result.ok) {
            codePanel.querySelector('.forgot-code-inputs')?.classList.add('is-error');
            document.getElementById('forgot-code-status').textContent = result.message || 'That reset code is incorrect.';
            return;
          }
          loginPanel.classList.remove('forgot-code-mode', 'forgot-mode');
          loginPanel.classList.add('google-password-mode', 'forgot-password-mode');
          document.querySelectorAll('#google-password-panel .forgot-code-email').forEach((node) => {
            node.textContent = codeEmail.textContent;
          });
          document.getElementById('forgot-code-panel')?.style.setProperty('display', 'none');
          document.getElementById('google-password-panel')?.style.setProperty('display', 'flex');
          document.getElementById('google-password')?.focus();
        }).catch(() => codePanel.querySelector('.forgot-code-inputs')?.classList.add('is-error'));
      }
      if (input.value && codeInputs[index + 1]) codeInputs[index + 1].focus();
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Backspace' && !input.value && codeInputs[index - 1]) codeInputs[index - 1].focus();
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!email.checkValidity()) {
      email.reportValidity();
      return;
    }
    status.hidden = true;
    status.classList.remove('error');
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Sending code…';
    }
    try {
      const response = await fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({action: 'forgot_check', email: email.value}),
      });
      const result = await response.json();
      if (!result.registered) {
        status.textContent = result.message || 'No registered account was found for this email.';
        status.classList.add('error');
        status.hidden = false;
        window.setTimeout(() => { status.hidden = true; }, 4000);
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = 'Send reset link';
        }
        return;
      }
    } catch (error) {
      status.textContent = 'We could not verify this email right now. Please try again.';
      status.classList.add('error');
      status.hidden = false;
      window.setTimeout(() => { status.hidden = true; }, 4000);
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = 'Send reset link';
      }
      return;
    }
    codeEmail.textContent = email.value;
    loginPanel.classList.remove('forgot-mode');
    loginPanel.classList.add('forgot-code-mode');
    codeInputs.forEach((input) => { input.value = ''; });
    codeInputs[0]?.focus();
    let seconds = 23;
    if (countdown) countdown.textContent = seconds;
    if (resend) resend.disabled = true;
    const timer = window.setInterval(() => {
      seconds -= 1;
      if (countdown) countdown.textContent = Math.max(seconds, 0);
      if (seconds <= 0) {
        window.clearInterval(timer);
        if (resend) {
          resend.disabled = false;
          resend.textContent = 'Resend code';
        }
      }
    }, 1000);
  });
})();

(function setupGoogleVerify() {
  const loginPanel = document.getElementById('panel-login');
  const codeInputs = [...document.querySelectorAll('#forgot-code-inputs input')];
  const codeBack = document.getElementById('forgot-code-back');
  const resend = document.getElementById('forgot-code-resend');
  const codeWrap = document.getElementById('forgot-code-inputs');
  const status = document.getElementById('forgot-code-status');
  if (!loginPanel || !loginPanel.classList.contains('google-verify-mode')) return;

  let busy = false;
  let timer = null;

  function setCodeError(on) {
    codeWrap?.classList.toggle('is-error', !!on);
  }

  function showStatus(message) {
    if (!status) return;
    status.textContent = message || '';
  }

  function currentCode() {
    return codeInputs.map((input) => input.value.replace(/\D/g, '')).join('');
  }

  function startCountdown(seconds) {
    let remaining = seconds;
    if (resend) {
      resend.disabled = true;
      resend.innerHTML = 'Resend in <span id="forgot-code-countdown">' + remaining + '</span> seconds';
    }
    if (timer) window.clearInterval(timer);
    timer = window.setInterval(() => {
      remaining -= 1;
      const live = document.getElementById('forgot-code-countdown');
      if (live) live.textContent = String(Math.max(remaining, 0));
      if (remaining <= 0) {
        window.clearInterval(timer);
        timer = null;
        if (resend) {
          resend.disabled = false;
          resend.textContent = 'Resend code';
        }
      }
    }, 1000);
  }

  async function verifyCode() {
    const code = currentCode();
    if (code.length !== 4 || busy) return;
    busy = true;
    showStatus('');
    setCodeError(false);
    try {
      const response = await fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({action: 'google_verify', code}),
      });
      const result = await response.json();
      if (result.ok && result.next === 'password') {
        loginPanel.classList.remove('google-verify-mode');
        loginPanel.classList.add('google-password-mode');
        // Ensure the code form is gone before the password form is shown.
        document.getElementById('forgot-code-panel')?.style.setProperty('display', 'none');
        document.getElementById('google-password-panel')?.style.setProperty('display', 'flex');
        document.getElementById('google-password')?.focus();
        return;
      }
      if (result.ok && result.next === 'location') {
        if (typeof window.openGoogleLocationModal === 'function') {
          window.openGoogleLocationModal();
        }
        return;
      }
      if (result.ok && result.redirect) {
        window.location.href = result.redirect;
        return;
      }
      setCodeError(true);
      showStatus(result.message || 'That code is incorrect. Please try again.');
      codeInputs[codeInputs.length - 1]?.focus();
    } catch (error) {
      setCodeError(true);
      showStatus('We could not verify the code right now. Please try again.');
    } finally {
      busy = false;
    }
  }

  codeInputs.forEach((input, index) => {
    input.addEventListener('input', () => {
      setCodeError(false);
      showStatus('');
      input.value = input.value.replace(/\D/g, '').slice(-1);
      if (input.value && codeInputs[index + 1]) codeInputs[index + 1].focus();
      if (currentCode().length === 4) verifyCode();
    });
    input.addEventListener('paste', (event) => {
      const text = ((event.clipboardData || window.clipboardData).getData('text') || '');
      const digits = text.replace(/\D/g, '').slice(0, 4);
      if (!digits) return;
      event.preventDefault();
      codeInputs.forEach((box, i) => { box.value = digits[i] || ''; });
      if (digits.length === 4) verifyCode();
      else codeInputs[Math.min(digits.length, 3)]?.focus();
    });
  });

  resend?.addEventListener('click', async () => {
    if (resend.disabled || busy) return;
    busy = true;
    showStatus('');
    try {
      const response = await fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({action: 'google_verify_resend'}),
      });
      const result = await response.json();
      if (!result.ok) {
        showStatus(result.message || 'Please wait before requesting another code.');
        return;
      }
      showStatus('We sent a new code to your Gmail.');
      setCodeError(false);
      startCountdown(20);
      codeInputs.forEach((input) => { input.value = ''; });
      codeInputs[codeInputs.length - 1]?.focus();
    } catch (error) {
      showStatus('We could not resend the code right now. Please try again.');
    } finally {
      busy = false;
    }
  });

  codeBack?.addEventListener('click', async () => {
    try {
      await fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({action: 'google_verify_cancel'}),
      });
    } catch (error) {}
    window.location.href = <?= json_encode(app_url('google-login.php'), JSON_UNESCAPED_SLASHES) ?>;
  });

  startCountdown(20);
  window.setTimeout(() => codeInputs[0]?.focus(), 50);
})();

(function setupGooglePassword() {
  const form = document.getElementById('google-password-form');
  const status = document.getElementById('google-password-status');
  const password = document.getElementById('google-password');
  const confirm = document.getElementById('google-password-confirm');
  if (!form || !password || !confirm) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (status) status.textContent = '';
    if (password.value.length < 8) {
      if (status) status.textContent = 'Password must be at least 8 characters.';
      password.focus();
      return;
    }
    if (password.value !== confirm.value) {
      if (status) status.textContent = 'Passwords do not match.';
      confirm.focus();
      return;
    }
    try {
      const isForgot = form.closest('.login-panel')?.classList.contains('forgot-password-mode');
      const response = await fetch(<?= json_encode(app_url('login.php'), JSON_UNESCAPED_SLASHES) ?>, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({
          action: isForgot ? 'forgot_set_password' : 'google_set_password',
          password: password.value,
          password_confirm: confirm.value,
        }),
      });
      const result = await response.json();
      if (result.ok && isForgot) {
        window.location.href = <?= json_encode(app_url('login.php?reset=success'), JSON_UNESCAPED_SLASHES) ?>;
        return;
      }
      if (result.ok) {
        if (typeof window.openGoogleLocationModal === 'function') {
          window.openGoogleLocationModal();
        }
        return;
      }
      if (status) status.textContent = result.message || 'Could not save your password. Please try again.';
    } catch (error) {
      if (status) status.textContent = 'Could not save your password right now. Please try again.';
    }
  });
})();

(function setupLegalModal() {
  const modal = document.getElementById('legal-modal');
  const title = document.getElementById('legal-modal-title');
  const lead = document.getElementById('legal-modal-lead');
  const termsBody = document.getElementById('legal-modal-terms');
  const privacyBody = document.getElementById('legal-modal-privacy');
  if (!modal) return;

  const copy = {
    terms: {
      title: 'Terms',
      lead: 'Please read these terms before creating an AddToMar account.',
    },
    privacy: {
      title: 'Privacy Policy',
      lead: 'How AddToMar collects and uses your information.',
    },
  };

  function openLegal(kind) {
    const info = copy[kind] || copy.terms;
    if (title) title.textContent = info.title;
    if (lead) lead.textContent = info.lead;
    if (termsBody) termsBody.hidden = kind !== 'terms';
    if (privacyBody) privacyBody.hidden = kind !== 'privacy';
    modal.hidden = false;
    document.body.classList.add('modal-open');
  }

  function closeLegal() {
    modal.hidden = true;
    document.body.classList.remove('modal-open');
  }

  document.querySelectorAll('.js-legal-link').forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      openLegal(link.dataset.legal);
    });
  });

  modal.querySelectorAll('[data-legal-close]').forEach((el) => {
    el.addEventListener('click', closeLegal);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) closeLegal();
  });
})();

(function setupGoogleLocationModal() {
  const modal = document.getElementById('google-location-modal');
  const submit = document.getElementById('google-location-submit');
  const status = document.getElementById('google-location-status');
  if (!modal || !submit) return;

  function openGoogleLocationModal() {
    document.body.appendChild(modal);
    modal.hidden = false;
    document.body.classList.add('modal-open');
    function tryInit(attempt) {
      if (typeof window.initGoogleLocationMap === 'function') {
        window.initGoogleLocationMap();
        return;
      }
      if (attempt < 40) {
        window.setTimeout(() => tryInit(attempt + 1), 50);
      }
    }
    requestAnimationFrame(() => tryInit(0));
  }

  window.openGoogleLocationModal = openGoogleLocationModal;

  submit.addEventListener('click', async () => {
    const address = (document.getElementById('google-location-address')?.value || '').trim();
    const latitude = (document.getElementById('google-location-latitude')?.value || '').trim();
    const longitude = (document.getElementById('google-location-longitude')?.value || '').trim();
    if (status) status.textContent = '';
    if (!address || !latitude || !longitude) {
      if (status) status.textContent = 'Pin a location on the map first.';
      return;
    }
    submit.disabled = true;
    try {
      const response = await fetch(<?= json_encode(app_url('login.php'), JSON_UNESCAPED_SLASHES) ?>, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: new URLSearchParams({
          action: 'google_save_location',
          address,
          latitude,
          longitude,
        }),
      });
      const result = await response.json();
      if (result.ok && result.redirect) {
        window.location.href = result.redirect;
        return;
      }
      const message = result.message || 'Could not save your location. Please try again.';
      if (typeof window.showMapAreaPopup === 'function' && /Laoag|San Nicolas|Batac/i.test(message)) {
        window.showMapAreaPopup(message);
      } else if (status) {
        status.textContent = message;
      }
    } catch (error) {
      if (status) status.textContent = 'Could not save your location right now. Please try again.';
    } finally {
      submit.disabled = false;
    }
  });

})();

const passwordInput = document.getElementById('password');
const toggleBtn = document.getElementById('toggle-password');

function updatePasswordToggle() {
  if (!passwordInput || !toggleBtn) return;
  const hasText = passwordInput.value.length > 0;
  toggleBtn.classList.toggle('is-visible', hasText);
  toggleBtn.hidden = !hasText;
  if (!hasText && passwordInput.type !== 'password') {
    passwordInput.type = 'password';
    toggleBtn.classList.remove('is-showing');
    toggleBtn.setAttribute('aria-label', 'Show password');
  }
}

toggleBtn?.addEventListener('click', () => {
  if (!passwordInput) return;
  const show = passwordInput.type === 'password';
  passwordInput.type = show ? 'text' : 'password';
  toggleBtn.classList.toggle('is-showing', show);
  toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
});

passwordInput?.addEventListener('input', updatePasswordToggle);
passwordInput?.addEventListener('change', updatePasswordToggle);
updatePasswordToggle();
<?php if ($googleExistingNotice): ?>
passwordInput?.focus();
<?php endif; ?>

function bindPasswordToggle(inputId, buttonId) {
  const input = document.getElementById(inputId);
  const button = document.getElementById(buttonId);
  if (!input || !button) return;

  button.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
}

bindPasswordToggle('register-password', 'toggle-register-password');
bindPasswordToggle('register-password-confirm', 'toggle-register-password-confirm');
bindPasswordToggle('google-password', 'toggle-google-password');
bindPasswordToggle('google-password-confirm', 'toggle-google-password-confirm');

function bindPasswordMatch(passwordId, confirmId, mismatchId) {
  const password = document.getElementById(passwordId);
  const confirm = document.getElementById(confirmId);
  const message = document.getElementById(mismatchId);
  if (!password || !confirm) return;

  function clearMismatch() {
    confirm.closest('.setup-field')?.classList.remove('is-mismatch');
    if (message) message.hidden = true;
  }

  function showMismatchIfNeeded() {
    const mismatch = password.value !== confirm.value;
    confirm.closest('.setup-field')?.classList.toggle('is-mismatch', mismatch);
    if (message) message.hidden = !mismatch;
    return !mismatch;
  }

  password.addEventListener('input', clearMismatch);
  confirm.addEventListener('input', clearMismatch);

  return showMismatchIfNeeded;
}

const syncRegisterPasswordMatch = bindPasswordMatch('register-password', 'register-password-confirm', 'register-password-mismatch');

const panelLogin = document.getElementById('panel-login');
const panelRegister = document.getElementById('panel-register');
const panelPharmacy = document.getElementById('panel-pharmacy');
const openRegisterBtn = document.getElementById('open-customer-register');
const registerBackBtn = document.getElementById('register-back-btn');
const pharmacyBackBtn = document.getElementById('pharmacy-back-btn');
const registerContactInput = document.getElementById('register-contact');
const registerNameInput = document.getElementById('register-full-name');
const shouldOpenRegister = <?= $openRegisterModal ? 'true' : 'false' ?>;

registerContactInput?.addEventListener('input', () => {
  registerContactInput.value = registerContactInput.value.replace(/\D/g, '').slice(0, 11);
});

function allAuthPanels() {
  return [panelLogin, panelRegister, panelPharmacy].filter(Boolean);
}

function showRegisterPanel() {
  document.body.classList.add('signup-open');
  showAuthPanel(panelRegister, () => {
    window.resetRegisterWizard?.();
    registerNameInput?.focus();
  });
}

function showLoginPanel() {
  document.body.classList.remove('signup-open');
  showAuthPanel(panelLogin);
}

function showAuthPanel(panel, onShow) {
  allAuthPanels().forEach((item) => {
    if (item === panel) return;
    if (item.classList.contains('is-active')) {
      item.classList.add('leaving');
      item.classList.remove('is-active');
      setTimeout(() => { item.hidden = true; item.classList.remove('leaving'); }, 600);
    } else {
      item.classList.remove('is-active', 'leaving');
      item.hidden = true;
    }
  });
  panel.hidden = false;
  requestAnimationFrame(() => {
    requestAnimationFrame(() => panel.classList.add('is-active'));
  });
  onShow?.();
}

function showPharmacyPanel() {
  document.body.classList.add('signup-open');
  showAuthPanel(panelPharmacy, () => {
    window.resetPharmacyWizard?.();
    pharmacyNameInput?.focus();
  });
}

function wipeSignupForm(form) {
  if (!form) return;
  form.querySelectorAll('input, textarea, select').forEach((field) => {
    if (field.type === 'hidden' && field.name === 'action') return;
    if (field.type === 'checkbox' || field.type === 'radio') {
      field.checked = false;
      return;
    }
    field.value = '';
  });
  form.querySelectorAll('.is-mismatch').forEach((el) => el.classList.remove('is-mismatch'));
  form.querySelectorAll('.setup-mismatch').forEach((el) => { el.hidden = true; });
}

function resetSignupPasswordField(inputId, buttonId) {
  const input = document.getElementById(inputId);
  const button = document.getElementById(buttonId);
  if (input) input.type = 'password';
  button?.setAttribute('aria-label', 'Show password');
}

function resetCustomerRegisterForm() {
  wipeSignupForm(document.getElementById('customer-register-form'));
  document.getElementById('panel-register')?.querySelectorAll('.setup-alert').forEach((el) => el.remove());
  resetSignupPasswordField('register-password', 'toggle-register-password');
  resetSignupPasswordField('register-password-confirm', 'toggle-register-password-confirm');
  window.resetRegisterMap?.();
  window.resetRegisterWizard?.();
}

function resetPharmacyRegisterForm() {
  wipeSignupForm(document.getElementById('pharmacy-register-form'));
  document.getElementById('panel-pharmacy')?.querySelectorAll('.setup-alert').forEach((el) => el.remove());
  resetSignupPasswordField('pharmacy-register-password', 'toggle-pharmacy-register-password');
  resetSignupPasswordField('pharmacy-register-password-confirm', 'toggle-pharmacy-register-password-confirm');

  const preview = document.getElementById('pharmacy-logo-preview-img');
  const box = document.getElementById('pharmacy-logo-preview');
  const placeholder = document.querySelector('#pharmacy-logo-preview .pharm-logo-text');
  if (preview) {
    preview.removeAttribute('src');
    preview.hidden = true;
  }
  box?.classList.remove('has-image');
  if (placeholder) placeholder.hidden = false;

  window.updatePharmacyRegisterMapLogo?.('');
  window.resetPharmacyDocUploads?.();
  window.resetPharmacyRegisterMap?.();
  document.querySelectorAll('#pharmacy-register-form .pharm-hours-row').forEach((row) => {
    row.querySelector('input[name="operation_days[]"]')?.dispatchEvent(new Event('change'));
  });
  window.resetPharmacyWizard?.();
}

function openRegisterModal() { showRegisterPanel(); }
function closeRegisterModal() {
  resetCustomerRegisterForm();
  showLoginPanel();
}

openRegisterBtn?.addEventListener('click', (e) => { e.preventDefault(); showRegisterPanel(); });
registerBackBtn?.addEventListener('click', closeRegisterModal);
pharmacyBackBtn?.addEventListener('click', () => {
  resetPharmacyRegisterForm();
  showLoginPanel();
});

if (shouldOpenRegister) { showRegisterPanel(); }

(function setupRegisterWizard() {
  const form = document.getElementById('customer-register-form');
  const btnNext = document.getElementById('signup-next-btn');
  const btnBack = document.getElementById('signup-back-btn');
  const btnSubmit = document.getElementById('signup-submit-btn');
  const nav = document.getElementById('signup-nav');
  const terms = document.getElementById('register-accept-terms');
  const title = document.getElementById('setup-title');
  const sub = document.getElementById('setup-sub');
  if (!form || !btnNext || !btnBack || !btnSubmit) return;

  const copy = {
    1: { title: 'Personal details', sub: 'Step 1 of 3 — tell us who you are.' },
    2: { title: 'Set password', sub: 'Step 2 of 3 — keep your account secure.' },
    3: { title: 'Location', sub: 'Step 3 of 3 — we’ll use this to find the nearest pharmacy where you can buy medicines and pick them up.' },
  };
  let current = 1;

  function goTo(step) {
    current = step;
    form.querySelectorAll('.signup-step').forEach((panel) => {
      panel.hidden = Number(panel.dataset.step) !== step;
    });
    form.closest('.login-panel')?.querySelectorAll('.signup-progress li').forEach((item) => {
      const n = Number(item.dataset.progress);
      item.classList.toggle('is-current', n === step);
      item.classList.toggle('is-done', n < step);
    });
    if (title) title.textContent = copy[step].title;
    if (sub) sub.textContent = copy[step].sub;
    btnBack.hidden = step === 1;
    btnNext.hidden = step === 3;
    btnSubmit.hidden = step !== 3;
    nav?.classList.toggle('is-final', step === 3);
    if (terms) terms.required = step === 3;
    if (step === 3 && typeof window.initRegisterMap === 'function') {
      requestAnimationFrame(() => window.initRegisterMap());
    }
    const focusable = form.querySelector(`.signup-step[data-step="${step}"] input, .signup-step[data-step="${step}"] textarea`);
    focusable?.focus();
  }

  function validateStep(step) {
    const panel = form.querySelector(`.signup-step[data-step="${step}"]`);
    if (!panel) return false;
    const fields = panel.querySelectorAll('input, textarea, select');
    for (const field of fields) {
      if (field.type === 'search' || field.type === 'hidden') continue;
      if (!field.checkValidity()) {
        field.reportValidity();
        return false;
      }
    }
    if (step === 2) {
      if (!syncRegisterPasswordMatch?.()) {
        document.getElementById('register-password-confirm')?.focus();
        return false;
      }
    }
    if (step === 3) {
      const termsBox = document.getElementById('register-accept-terms');
      if (termsBox && !termsBox.checked) {
        termsBox.setCustomValidity('Please accept the Terms and Privacy Policy to create an account.');
        termsBox.reportValidity();
        termsBox.setCustomValidity('');
        return false;
      }
    }
    return true;
  }

  btnNext.addEventListener('click', () => {
    if (!validateStep(current)) return;
    goTo(Math.min(3, current + 1));
  });
  btnBack.addEventListener('click', () => goTo(Math.max(1, current - 1)));

  form.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || current === 3 || event.target.tagName === 'TEXTAREA') return;
    event.preventDefault();
    btnNext.click();
  });

  form.addEventListener('submit', (event) => {
    if (current !== 3) {
      event.preventDefault();
      if (validateStep(current)) goTo(current + 1);
      return;
    }

    if (!validateStep(3)) {
      event.preventDefault();
      return;
    }

    const lat = document.getElementById('register-latitude')?.value?.trim();
    const lng = document.getElementById('register-longitude')?.value?.trim();
    const password = document.getElementById('register-password')?.value || '';
    const passwordConfirm = document.getElementById('register-password-confirm')?.value || '';

    if (!lat || !lng) {
      event.preventDefault();
      alert('Please pin your pickup location on the map before creating your account.');
      return;
    }

    if (password.length < 8) {
      event.preventDefault();
      goTo(2);
      alert('Password must be at least 8 characters.');
      return;
    }

    if (password !== passwordConfirm) {
      event.preventDefault();
      goTo(2);
      syncRegisterPasswordMatch?.();
      document.getElementById('register-password-confirm')?.focus();
      return;
    }
  });

  window.resetRegisterWizard = () => goTo(1);
  goTo(1);
})();

(function setupPharmacyWizard() {
  const form = document.getElementById('pharmacy-register-form');
  const btnNext = document.getElementById('pharmacy-signup-next-btn');
  const btnBack = document.getElementById('pharmacy-signup-back-btn');
  const btnSubmit = document.getElementById('pharmacy-signup-submit-btn');
  const nav = document.getElementById('pharmacy-signup-nav');
  const terms = document.getElementById('pharmacy-accept-terms');
  const title = document.getElementById('pharmacy-setup-title');
  const sub = document.getElementById('pharmacy-setup-sub');
  if (!form || !btnNext || !btnBack || !btnSubmit) return;

  const lastStep = 4;
  const copy = {
    1: { title: 'Pharmacy details', sub: 'Step 1 of 4 — tell us about your pharmacy.' },
    2: { title: 'Required documents', sub: 'Step 2 of 4 — upload your permit and licenses.' },
    3: { title: 'Account login', sub: 'Step 3 of 4 — set the email and password for this pharmacy.' },
    4: { title: 'Location', sub: 'Step 4 of 4 — pin your pharmacy so nearby residents can find you.' },
  };
  let current = 1;

  function goTo(step) {
    current = step;
    form.querySelectorAll('.signup-step').forEach((panel) => {
      panel.hidden = Number(panel.dataset.step) !== step;
    });
    form.closest('.login-panel')?.querySelectorAll('.signup-progress li').forEach((item) => {
      const n = Number(item.dataset.progress);
      item.classList.toggle('is-current', n === step);
      item.classList.toggle('is-done', n < step);
    });
    if (title) title.textContent = copy[step].title;
    if (sub) sub.textContent = copy[step].sub;
    btnBack.hidden = step === 1;
    btnNext.hidden = step === lastStep;
    btnSubmit.hidden = step !== lastStep;
    nav?.classList.toggle('is-final', step === lastStep);
    if (terms) terms.required = step === lastStep;
    if (step === lastStep && typeof window.initPharmacyRegisterMap === 'function') {
      requestAnimationFrame(() => window.initPharmacyRegisterMap());
    }
    const focusable = form.querySelector(`.signup-step[data-step="${step}"] input, .signup-step[data-step="${step}"] textarea`);
    focusable?.focus();
  }

  function validateStep(step) {
    const panel = form.querySelector(`.signup-step[data-step="${step}"]`);
    if (!panel) return false;
    const fields = panel.querySelectorAll('input, textarea, select');
    for (const field of fields) {
      if (field.type === 'search' || field.type === 'hidden' || field.disabled) continue;
      if (field.name === 'operation_days[]') continue;
      if (!field.checkValidity()) {
        field.reportValidity();
        return false;
      }
    }
    if (step === 1) {
      const days = panel.querySelectorAll('input[name="operation_days[]"]:checked');
      if (days.length === 0) {
        alert('Select at least one day of operation.');
        return false;
      }
      for (const day of days) {
        const row = day.closest('.pharm-hours-row');
        const open = row?.querySelector('input[type="time"][name*="[open]"]');
        const close = row?.querySelector('input[type="time"][name*="[close]"]');
        if (open && close && open.value && close.value && open.value >= close.value) {
          close.setCustomValidity('Closing time must be later than opening time.');
          close.reportValidity();
          close.setCustomValidity('');
          return false;
        }
      }
    }
    if (step === 3) {
      if (!syncPharmacyPasswordMatch?.()) {
        document.getElementById('pharmacy-register-password-confirm')?.focus();
        return false;
      }
    }
    if (step === lastStep) {
      if (terms && !terms.checked) {
        terms.setCustomValidity('Please accept the Terms and Privacy Policy to register your pharmacy.');
        terms.reportValidity();
        terms.setCustomValidity('');
        return false;
      }
    }
    return true;
  }

  btnNext.addEventListener('click', () => {
    if (!validateStep(current)) return;
    goTo(Math.min(lastStep, current + 1));
  });
  btnBack.addEventListener('click', () => goTo(Math.max(1, current - 1)));

  form.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || current === lastStep || event.target.tagName === 'TEXTAREA') return;
    event.preventDefault();
    btnNext.click();
  });

  form.addEventListener('submit', (event) => {
    if (current !== lastStep) {
      event.preventDefault();
      if (validateStep(current)) goTo(current + 1);
      return;
    }

    if (!validateStep(lastStep)) {
      event.preventDefault();
      return;
    }

    const lat = document.getElementById('pharmacy-register-latitude')?.value?.trim();
    const lng = document.getElementById('pharmacy-register-longitude')?.value?.trim();
    const password = document.getElementById('pharmacy-register-password')?.value || '';
    const passwordConfirm = document.getElementById('pharmacy-register-password-confirm')?.value || '';
    const days = form.querySelectorAll('input[name="operation_days[]"]:checked');

    if (!lat || !lng) {
      event.preventDefault();
      alert('Please pin your pharmacy location on the map before submitting.');
      return;
    }

    if (days.length === 0) {
      event.preventDefault();
      goTo(1);
      alert('Select at least one day of operation.');
      return;
    }

    if (password.length < 8) {
      event.preventDefault();
      goTo(3);
      alert('Password must be at least 8 characters.');
      return;
    }

    if (password !== passwordConfirm) {
      event.preventDefault();
      goTo(3);
      syncPharmacyPasswordMatch?.();
      document.getElementById('pharmacy-register-password-confirm')?.focus();
      return;
    }
  });

  window.resetPharmacyWizard = () => {
    goTo(1);
    window.resetPharmacyDocUploads?.();
  };
  goTo(1);
})();

(function setupPharmacyDayHours() {
  const rows = document.querySelectorAll('#pharmacy-register-form .pharm-hours-row');
  if (!rows.length) return;

  function syncRow(row) {
    const checked = !!row.querySelector('input[name="operation_days[]"]')?.checked;
    row.classList.toggle('is-open', checked);
    row.querySelectorAll('input[type="time"]').forEach((input) => {
      input.disabled = !checked;
      input.required = checked;
      input.setCustomValidity('');
    });
  }

  rows.forEach((row) => {
    const checkbox = row.querySelector('input[name="operation_days[]"]');
    checkbox?.addEventListener('change', () => syncRow(row));
    syncRow(row);
  });
})();

const openPharmacyRegisterBtn = document.getElementById('open-pharmacy-register');
const pharmacyNameInput = document.getElementById('pharmacy-register-name');
const pharmacyContactInput = document.getElementById('pharmacy-register-contact');
const pharmacyLogoBox = document.getElementById('pharmacy-logo-preview');
const pharmacyLogoInput = document.getElementById('pharmacy-register-logo');
const pharmacyLogoPreview = document.getElementById('pharmacy-logo-preview-img');
const pharmacyLogoPlaceholder = document.querySelector('#pharmacy-logo-preview .pharm-logo-text');
const shouldOpenPharmacyRegister = <?= $openPharmacyRegisterModal ? 'true' : 'false' ?>;

pharmacyContactInput?.addEventListener('input', () => {
  pharmacyContactInput.value = pharmacyContactInput.value.replace(/\D/g, '').slice(0, 11);
});

pharmacyLogoInput?.addEventListener('change', () => {
  const file = pharmacyLogoInput.files?.[0];
  if (!file || !pharmacyLogoPreview) return;

  if (!file.type.startsWith('image/')) {
    pharmacyLogoPreview.hidden = true;
    pharmacyLogoBox?.classList.remove('has-image');
    if (pharmacyLogoPlaceholder) pharmacyLogoPlaceholder.hidden = false;
    return;
  }

  pharmacyLogoPreview.src = URL.createObjectURL(file);
  pharmacyLogoPreview.hidden = false;
  pharmacyLogoBox?.classList.add('has-image');
  if (pharmacyLogoPlaceholder) pharmacyLogoPlaceholder.hidden = true;
  if (typeof window.updatePharmacyRegisterMapLogo === 'function') {
    window.updatePharmacyRegisterMapLogo(pharmacyLogoPreview.src);
  }
});

(function setupPharmacyDocUploads() {
  const maxBytes = 5 * 1024 * 1024;
  const maxFiles = 10;
  const allowed = ['pdf', 'jpg', 'jpeg', 'png'];

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function extOf(name) {
    return (name.split('.').pop() || '').toLowerCase();
  }

  function isAllowed(file) {
    return allowed.includes(extOf(file.name)) && file.size > 0 && file.size <= maxBytes;
  }

  function fileKey(file) {
    return file.name + ':' + file.size + ':' + file.lastModified;
  }

  function syncInput(widget) {
    const input = widget.querySelector('.pharm-doc-input');
    if (!input) return;
    const data = new DataTransfer();
    (widget._files || []).forEach((file) => data.items.add(file));
    input.files = data.files;
  }

  const trashIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';

  function updateCard(card, file, progress) {
    const icon = card.querySelector('.pharm-file-icon');
    const nameEl = card.querySelector('.pharm-file-name');
    const sizeEl = card.querySelector('.pharm-file-size');
    const stateEl = card.querySelector('.pharm-file-state');
    const bar = card.querySelector('.pharm-file-bar');
    const barFill = bar?.querySelector('span');
    const removeBtn = card.querySelector('.pharm-file-remove');
    if (!file) return;

    const ext = extOf(file.name);
    const kind = ext === 'pdf' ? 'pdf' : 'img';
    icon.dataset.kind = kind;
    icon.textContent = kind === 'pdf' ? 'PDF' : ext.toUpperCase();
    nameEl.textContent = file.name;
    sizeEl.textContent = formatSize(Math.min(file.size, Math.round(file.size * (progress / 100)))) + ' of ' + formatSize(file.size);

    if (progress < 100) {
      bar.hidden = false;
      if (barFill) barFill.style.width = progress + '%';
      stateEl.classList.remove('is-done');
      stateEl.innerHTML = '<span class="pharm-file-spinner" aria-hidden="true"></span> Uploading...';
      removeBtn.innerHTML = trashIcon;
      removeBtn.setAttribute('aria-label', 'Cancel upload');
    } else {
      bar.hidden = true;
      if (barFill) barFill.style.width = '100%';
      sizeEl.textContent = formatSize(file.size) + ' of ' + formatSize(file.size);
      stateEl.classList.add('is-done');
      stateEl.innerHTML = '<span class="pharm-file-check" aria-hidden="true">✓</span> Completed';
      removeBtn.innerHTML = trashIcon;
      removeBtn.setAttribute('aria-label', 'Remove file');
    }
  }

  function startCardProgress(card, file) {
    let progress = 8;
    updateCard(card, file, progress);
    const timer = setInterval(() => {
      progress = Math.min(100, progress + 12 + Math.round(Math.random() * 18));
      updateCard(card, file, progress);
      if (progress >= 100) clearInterval(timer);
    }, 140);
    card._timer = timer;
  }

  function removeFile(widget, card, file) {
    if (card._timer) clearInterval(card._timer);
    widget._files = (widget._files || []).filter((item) => fileKey(item) !== fileKey(file));
    card.remove();
    syncInput(widget);
  }

  function addFiles(widget, files) {
    const input = widget.querySelector('.pharm-doc-input');
    const list = widget.querySelector('.pharm-file-list');
    const tpl = widget.querySelector('.pharm-file-card-tpl');
    if (!input || !list || !tpl) return;

    widget._files = widget._files || [];
    const existing = new Set(widget._files.map(fileKey));
    let rejected = false;

    Array.from(files).forEach((file) => {
      if (!isAllowed(file)) {
        rejected = true;
        return;
      }
      if (existing.has(fileKey(file))) return;
      if (widget._files.length >= maxFiles) {
        rejected = true;
        return;
      }
      widget._files.push(file);
      existing.add(fileKey(file));
      const card = tpl.content.firstElementChild.cloneNode(true);
      card.dataset.fileKey = fileKey(file);
      card.querySelector('.pharm-file-remove')?.addEventListener('click', () => removeFile(widget, card, file));
      list.appendChild(card);
      startCardProgress(card, file);
    });

    syncInput(widget);
    if (rejected) {
      alert('Use JPG, PNG, or PDF files up to 5 MB each. You can add up to 10 files per document.');
    }
  }

  function clearWidget(widget) {
    widget._files = [];
    widget.querySelectorAll('.pharm-file-card').forEach((card) => {
      if (card._timer) clearInterval(card._timer);
      card.remove();
    });
    syncInput(widget);
  }

  document.querySelectorAll('#panel-pharmacy .pharm-doc').forEach((widget) => {
    const input = widget.querySelector('.pharm-doc-input');
    const drop = widget.querySelector('.pharm-drop');
    const browse = widget.querySelector('.pharm-drop-browse');
    widget._files = [];

    browse?.addEventListener('click', () => input?.click());
    drop?.addEventListener('click', (event) => {
      if (event.target.closest('.pharm-drop-browse')) return;
      input?.click();
    });

    input?.addEventListener('change', () => {
      addFiles(widget, input.files || []);
    });

    ['dragenter', 'dragover'].forEach((type) => {
      drop?.addEventListener(type, (event) => {
        event.preventDefault();
        drop.classList.add('is-drag');
      });
    });
    ['dragleave', 'drop'].forEach((type) => {
      drop?.addEventListener(type, (event) => {
        event.preventDefault();
        if (type === 'dragleave' && drop.contains(event.relatedTarget)) return;
        drop.classList.remove('is-drag');
      });
    });
    drop?.addEventListener('drop', (event) => {
      addFiles(widget, event.dataTransfer?.files || []);
    });
  });

  window.resetPharmacyDocUploads = () => {
    document.querySelectorAll('#panel-pharmacy .pharm-doc').forEach(clearWidget);
  };
})();

bindPasswordToggle('pharmacy-register-password', 'toggle-pharmacy-register-password');
bindPasswordToggle('pharmacy-register-password-confirm', 'toggle-pharmacy-register-password-confirm');
const syncPharmacyPasswordMatch = bindPasswordMatch('pharmacy-register-password', 'pharmacy-register-password-confirm', 'pharmacy-password-mismatch');

function openPharmacyRegisterModal() {
  showPharmacyPanel();
}

function closePharmacyRegisterModal() {
  resetPharmacyRegisterForm();
  showLoginPanel();
}

openPharmacyRegisterBtn?.addEventListener('click', (event) => {
  event.preventDefault();
  openPharmacyRegisterModal();
});

document.getElementById('open-pharmacy-register-foot')?.addEventListener('click', (event) => {
  event.preventDefault();
  openPharmacyRegisterModal();
});

if (shouldOpenPharmacyRegister) {
  openPharmacyRegisterModal();
}

if (window.location.hash === '#register-pharmacy' || new URLSearchParams(window.location.search).get('register') === 'pharmacy') {
  openPharmacyRegisterModal();
}
</script>
<script>
window.PHARMACY_REGISTER_MAP_CONFIG = <?= json_encode([
  'tileUrl' => MAP_TILE_URL,
  'tileAttribution' => MAP_ATTRIBUTION,
  'defaultLat' => MAP_DEFAULT_LAT,
  'defaultLng' => MAP_DEFAULT_LNG,
  'defaultZoom' => MAP_DEFAULT_ZOOM,
  'minZoom' => MAP_MIN_ZOOM,
  'maxZoom' => MAP_MAX_ZOOM,
  'bounds' => maps_bounds(),
  'serviceOverlay' => maps_service_overlay(),
  'serviceCities' => MAP_SERVICE_CITIES,
  'geocodeUrl' => app_url('ajax/geocode-search.php'),
  'reverseGeocodeUrl' => app_url('ajax/reverse-geocode.php'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars(app_url('js/register-pharmacy-map.js'), ENT_QUOTES, 'UTF-8') ?>?v=reset-on-close-1"></script>
<script>
window.REGISTER_MAP_CONFIG = <?= json_encode([
  'tileUrl' => MAP_TILE_URL,
  'tileAttribution' => MAP_ATTRIBUTION,
  'defaultLat' => MAP_DEFAULT_LAT,
  'defaultLng' => MAP_DEFAULT_LNG,
  'defaultZoom' => MAP_DEFAULT_ZOOM,
  'minZoom' => MAP_MIN_ZOOM,
  'maxZoom' => MAP_MAX_ZOOM,
  'bounds' => maps_bounds(),
  'serviceOverlay' => maps_service_overlay(),
  'serviceCities' => MAP_SERVICE_CITIES,
  'geocodeUrl' => app_url('ajax/geocode-search.php'),
  'reverseGeocodeUrl' => app_url('ajax/reverse-geocode.php'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= htmlspecialchars(app_url('js/map-area-popup.js'), ENT_QUOTES, 'UTF-8') ?>?v=1"></script>
<script src="<?= htmlspecialchars(app_url('js/map-service-overlay.js'), ENT_QUOTES, 'UTF-8') ?>?v=4"></script>
<script src="<?= htmlspecialchars(app_url('js/register-map.js'), ENT_QUOTES, 'UTF-8') ?>?v=reset-on-close-1"></script>
<?php if ($openGoogleLocation): ?>
<script>
if (typeof window.openGoogleLocationModal === 'function') {
  window.openGoogleLocationModal();
}
</script>
<?php endif; ?>
<?php live_sync_render_script('public'); ?>
</body>
</html>
