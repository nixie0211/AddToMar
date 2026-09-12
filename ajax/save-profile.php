<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/customers.php';

caps_bootstrap();
header('Content-Type: application/json; charset=utf-8');

$email = trim((string) ($_SESSION['user_email'] ?? ''));
$name = trim((string) ($_POST['full_name'] ?? ''));
$contact = preg_replace('/\D+/', '', (string) ($_POST['contact_number'] ?? '')) ?? '';
$newEmail = trim((string) ($_POST['email'] ?? ''));
$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['password_confirm'] ?? '');

if ($email === '' || $name === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Enter a valid name and email address.']);
    exit;
}
if ($contact !== '' && strlen($contact) !== 11) {
    echo json_encode(['ok' => false, 'error' => 'Mobile number must be exactly 11 digits.']);
    exit;
}

$customer = customers_find_by_email($email);
if (!$customer) {
    echo json_encode(['ok' => false, 'error' => 'Your account could not be found.']);
    exit;
}
if ($currentPassword === '' || !customers_verify_password($customer, $currentPassword)) {
    echo json_encode(['ok' => false, 'error' => 'Enter your current password to save profile changes.']);
    exit;
}
if ($newPassword !== '') {
    $result = customers_update_credentials($email, ['email' => $newEmail, 'password' => $newPassword, 'password_confirm' => $confirmPassword]);
} else {
    $result = customers_update_credentials($email, ['email' => $newEmail]);
}
if (empty($result['ok'])) {
    echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'Could not save your profile.')]);
    exit;
}

try {
    $stmt = caps_db()->prepare('UPDATE users SET full_name = ?, contact_number = ? WHERE email = ?');
    $stmt->execute([$name, $contact, $newEmail]);
    $_SESSION['user_email'] = $newEmail;
    $_SESSION['user_name'] = $name;
    residence_hydrate_session_from_customer(customers_find_by_email($newEmail) ?? $customer);
    echo json_encode(['ok' => true, 'message' => 'Profile updated successfully.']);
} catch (Throwable) {
    echo json_encode(['ok' => false, 'error' => 'Could not save your profile.']);
}
