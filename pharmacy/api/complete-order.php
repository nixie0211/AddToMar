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

$order = pharmacy_get_order_by_id($orderId);
if (!$order || ($order['status'] ?? '') !== 'ready') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Only ready-for-pickup orders can be marked complete.']);
    exit;
}

if (!pharmacy_order_has_pickup_proof($order)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Upload proof of pickup before marking this order complete.']);
    exit;
}

if (!pharmacy_complete_order($orderId)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Could not mark this order complete.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Order marked as complete.',
    'status' => 'delivered',
    'order_id' => $orderId,
]);
