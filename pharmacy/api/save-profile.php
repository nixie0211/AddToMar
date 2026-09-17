<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');
require_once dirname(__DIR__, 2) . '/includes/pharmacy-accounts.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$email = (string) ($_SESSION['user_email'] ?? '');
$result = pharmacy_accounts_update_profile($email, $_POST, $_FILES);

if (!$result['ok']) {
    http_response_code(422);
    echo json_encode($result);
    exit;
}

$account = $result['account'];
$logoUrl = pharmacy_accounts_public_logo_url($account);

echo json_encode([
    'ok' => true,
    'message' => 'Pharmacy profile updated.',
    'account' => [
        'pharmacy_name' => $account['pharmacy_name'] ?? '',
        'contact_number' => $account['contact_number'] ?? '',
        'logo_url' => $logoUrl,
    ],
]);
