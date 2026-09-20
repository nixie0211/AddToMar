<?php

declare(strict_types=1);

if (!defined('PHARMACY_SKIP_MIGRATIONS')) {
    define('PHARMACY_SKIP_MIGRATIONS', true);
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/pharmacy/config.php';
require_once __DIR__ . '/pharmacy/includes/database.php';
require_once __DIR__ . '/pharmacy/includes/pharmacy-context.php';
require_once __DIR__ . '/pharmacy/includes/repository.php';

$portal = (string) ($_SESSION['portal'] ?? '');
$orderId = (int) ($_GET['order_id'] ?? 0);

if ($orderId <= 0 || !in_array($portal, ['pharmacy', 'residence'], true)) {
    http_response_code(403);
    echo 'Pickup proof not available.';
    exit;
}

$pdo = pharmacy_db();
$orderStmt = $pdo->prepare('
    SELECT o.id, o.pharmacy_id, o.customer_id, o.pickup_proof_path, c.email AS customer_email
    FROM orders o
    LEFT JOIN customers c ON c.id = o.customer_id
    WHERE o.id = ?
    LIMIT 1
');
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();
if (!is_array($order)) {
    http_response_code(404);
    echo 'Order not found.';
    exit;
}

if ($portal === 'pharmacy') {
    if (trim((string) ($order['pharmacy_id'] ?? '')) !== pharmacy_current_id()) {
        http_response_code(403);
        echo 'Pickup proof not available.';
        exit;
    }
} else {
    $email = strtolower(trim((string) ($_SESSION['user_email'] ?? '')));
    $customerEmail = strtolower(trim((string) ($order['customer_email'] ?? '')));
    if ($customerEmail === '') {
        $customerId = (int) ($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            $emailStmt = $pdo->prepare('SELECT email FROM customers WHERE id = ? LIMIT 1');
            $emailStmt->execute([$customerId]);
            $customerEmail = strtolower(trim((string) $emailStmt->fetchColumn()));
        }
    }
    if ($email === '' || $customerEmail === '' || $email !== $customerEmail) {
        http_response_code(403);
        echo 'Pickup proof not available.';
        exit;
    }
}

session_write_close();

$path = str_replace('\\', '/', trim((string) ($order['pickup_proof_path'] ?? '')));
$file = pharmacy_read_pickup_proof_file($orderId, $path);

if (!is_array($file) || !is_string($file['content'] ?? null) || $file['content'] === '') {
    http_response_code(404);
    echo 'Pickup proof file was not found.';
    exit;
}

$filename = str_replace(['"', "\r", "\n"], '', (string) ($file['filename'] ?? basename($path !== '' ? $path : 'pickup-proof')));
$mime = trim((string) ($file['mime'] ?? ''));
if ($mime === '') {
    $mime = pharmacy_order_upload_mime($filename);
}

$etag = '"' . sha1(($path !== '' ? $path : $filename) . ':' . strlen($file['content'])) . '"';
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), '"') === trim($etag, '"')) {
    http_response_code(304);
    header('ETag: ' . $etag);
    header('Cache-Control: private, max-age=86400');
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . (string) strlen($file['content']));
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
echo $file['content'];
exit;
