<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('residence');
require_once dirname(__DIR__) . '/includes/customers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$email = (string) ($_SESSION['user_email'] ?? '');
$action = trim((string) ($_POST['action'] ?? 'save'));

if ($action === 'set_current') {
    $result = customers_set_current_address($email, (int) ($_POST['id'] ?? 0));
} else {
    $result = customers_save_address($email, [
        'id' => $_POST['id'] ?? 0,
        'address' => $_POST['address'] ?? '',
        'latitude' => $_POST['latitude'] ?? '',
        'longitude' => $_POST['longitude'] ?? '',
        'make_current' => ($_POST['make_current'] ?? '0') === '1',
    ]);
}

if (!($result['ok'] ?? false)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Could not save your address.']);
    exit;
}

residence_hydrate_session_from_customer($result['customer']);
$profile = residence_session_profile();

echo json_encode([
    'ok' => true,
    'make_current' => !empty($result['make_current']),
    'address' => $profile['address'],
    'latitude' => $profile['latitude'],
    'longitude' => $profile['longitude'],
    'addresses' => $result['addresses'] ?? [],
], JSON_UNESCAPED_UNICODE);
