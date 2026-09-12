<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('residence');
require_once dirname(__DIR__) . '/includes/customers.php';
require_once dirname(__DIR__) . '/residence/includes/orders.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$pharmacyId = trim((string) ($_POST['pharmacy_id'] ?? ''));
$itemsRaw = (string) ($_POST['items'] ?? '');
$items = json_decode($itemsRaw, true);

if (!is_array($items) || $items === []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Your cart is empty.']);
    exit;
}

try {
    $prescriptions = residence_collect_prescription_uploads();
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

$paymentProofPath = null;
if (isset($_FILES['payment_proof']) && is_array($_FILES['payment_proof'])) {
    $paymentProofPath = residence_store_order_upload($_FILES['payment_proof'], 'PAY');
}

$groups = residence_group_checkout_items($items, $pharmacyId);
$result = residence_place_checkout_groups(
    residence_session_profile(),
    $groups,
    [
        'pickup_date' => (string) ($_POST['pickup_date'] ?? ''),
        'pickup_time' => '',
        'payment_method' => (string) ($_POST['payment_method'] ?? 'gcash'),
        'payment_proof_path' => $paymentProofPath,
    ],
    $prescriptions
);

if (!$result['ok']) {
    http_response_code(422);
    echo json_encode($result);
    exit;
}

if (!empty($result['orders']) && is_array($result['orders'])) {
    $result['order'] = residence_combine_orders_for_receipt($result['orders']);
}

echo json_encode($result);
