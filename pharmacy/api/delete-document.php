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

$documentId = (int) ($_POST['document_id'] ?? 0);
$result = pharmacy_accounts_delete_owned_document((string) ($_SESSION['user_email'] ?? ''), $documentId);

if (!$result['ok']) {
    http_response_code(422);
    echo json_encode($result);
    exit;
}

echo json_encode(['ok' => true]);
