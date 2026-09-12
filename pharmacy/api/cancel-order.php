<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/repository.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));
if ($orderId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit;
}

if ($reason === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide a cancellation reason.']);
    exit;
}

if (mb_strlen($reason) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Cancellation reason must be 500 characters or less.']);
    exit;
}

if (!pharmacy_cancel_order($orderId, $reason)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Order cancelled.',
]);
