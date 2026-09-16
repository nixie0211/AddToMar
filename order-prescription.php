<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/customers.php';
require_once __DIR__ . '/pharmacy/config.php';
require_once __DIR__ . '/pharmacy/includes/database.php';
require_once __DIR__ . '/pharmacy/includes/repository.php';

$portal = (string) ($_SESSION['portal'] ?? '');
$orderId = (int) ($_GET['order_id'] ?? 0);
$itemId = (int) ($_GET['item_id'] ?? 0);

if ($orderId <= 0 || !in_array($portal, ['pharmacy', 'residence'], true)) {
    http_response_code(403);
    echo 'Prescription not available.';
    exit;
}

$order = pharmacy_get_order_by_id($orderId, false);
if ($order === null) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

if ($portal === 'pharmacy') {
    if (trim((string) ($order['pharmacy_id'] ?? '')) !== pharmacy_current_id()) {
        http_response_code(403);
        echo 'Prescription not available.';
        exit;
    }
    if (!pharmacy_order_allows_prescription_view($order)) {
        http_response_code(403);
        echo 'Prescription is no longer available for this order status.';
        exit;
    }
} else {
    $email = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));
    $customerEmail = strtolower(trim((string) ($order['customer_email'] ?? '')));
    if ($email === '' || $customerEmail === '' || $email !== $customerEmail) {
        http_response_code(403);
        echo 'Prescription not available.';
        exit;
    }
}

$item = [];
if ($itemId > 0) {
    foreach (pharmacy_get_order_items($orderId) as $row) {
        if ((int) ($row['id'] ?? 0) === $itemId) {
            $item = $row;
            break;
        }
    }
}

$file = pharmacy_order_prescription_file($order, $item);
if ($file === null) {
    http_response_code(404);
    echo 'Prescription file was not found.';
    exit;
}

$filename = str_replace(['"', "\r", "\n"], '', (string) ($file['filename'] ?? 'prescription.jpg'));
$mime = trim((string) ($file['mime'] ?? ''));
if ($mime === '') {
    $mime = pharmacy_order_upload_mime($filename);
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . (string) strlen((string) $file['content']));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $file['content'];
exit;
