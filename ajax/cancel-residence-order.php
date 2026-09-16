<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/customers.php';
require_once dirname(__DIR__) . '/residence/includes/orders.php';

header('Content-Type: application/json; charset=utf-8');
require_portal_auth('residence');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$profile = residence_session_profile();
$email = strtolower(trim((string) ($profile['email'] ?? $_SESSION['user_email'] ?? '')));
$orderId = (int) ($_POST['order_id'] ?? 0);
$orderNumber = trim((string) ($_POST['order_number'] ?? ''));
$reasonChoice = trim((string) ($_POST['reason'] ?? ''));
$otherReason = trim((string) ($_POST['other_reason'] ?? ''));
$allowedReasons = [
    'Changed my mind',
    'Ordered by mistake',
    'Need to update my order',
    'Found another pharmacy',
    'Other',
];

if ($email === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please sign in to cancel an order.']);
    exit;
}

if ($orderId <= 0 && $orderNumber === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid order.']);
    exit;
}

if (!in_array($reasonChoice, $allowedReasons, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please select a cancellation reason.']);
    exit;
}

if ($reasonChoice === 'Other') {
    if ($otherReason === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Please type the reason for cancellation.']);
        exit;
    }
    $reason = $otherReason;
} elseif ($otherReason !== '') {
    $reason = $reasonChoice . ' — ' . $otherReason;
} else {
    $reason = $reasonChoice;
}

$result = residence_cancel_customer_order($email, $orderId, $orderNumber, $reason);
if (empty($result['ok'])) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'Could not cancel this order.')]);
    exit;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
