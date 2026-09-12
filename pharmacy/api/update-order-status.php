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
if ($orderId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit;
}

$updated = pharmacy_advance_order_status($orderId);
if ($updated === null) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This order cannot be moved to the next status.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Order moved to ' . $updated['label'] . '.',
    'status' => $updated['to'],
    'order_id' => $updated['id'],
]);
