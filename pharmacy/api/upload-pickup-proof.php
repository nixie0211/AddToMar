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
$order = pharmacy_get_order_by_id($orderId);
if (!$order || ($order['status'] ?? '') !== 'ready') {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Proof can only be uploaded for orders that are ready for pickup.']);
    exit;
}

$file = $_FILES['proof'] ?? null;
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a photo or PDF as proof of pickup.']);
    exit;
}

if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Could not upload the file.']);
    exit;
}

$tmpName = (string) ($file['tmp_name'] ?? '');
$mime = is_file($tmpName) ? (string) mime_content_type($tmpName) : '';
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];
if (!isset($allowed[$mime])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP, or PDF files are allowed.']);
    exit;
}
if ((int) $file['size'] > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'File must be 5 MB or smaller.']);
    exit;
}

$filename = 'order-' . $orderId . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
$dir = dirname(__DIR__, 2) . '/uploads/pickup-proof';
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not store the file.']);
    exit;
}

$absolute = $dir . '/' . $filename;
if (!move_uploaded_file($tmpName, $absolute)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not store the file.']);
    exit;
}

$relativePath = 'uploads/pickup-proof/' . $filename;
if (!pharmacy_save_pickup_proof($orderId, $relativePath)) {
    @unlink($absolute);
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Could not save the pickup proof.']);
    exit;
}

$bytes = is_file($absolute) ? (string) file_get_contents($absolute) : '';
if ($bytes !== '') {
    pharmacy_store_order_upload_blob($relativePath, $bytes, $mime);
}

echo json_encode([
    'success' => true,
    'message' => 'Proof of pickup uploaded.',
    'path' => $relativePath,
]);
