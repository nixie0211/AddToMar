<?php

declare(strict_types=1);

if (!defined('PHARMACY_SKIP_MIGRATIONS')) {
    define('PHARMACY_SKIP_MIGRATIONS', true);
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/pharmacy/config.php';
require_once __DIR__ . '/pharmacy/includes/database.php';
require_once __DIR__ . '/pharmacy/includes/pharmacy-context.php';

$portal = (string) ($_SESSION['portal'] ?? '');
$orderId = (int) ($_GET['order_id'] ?? 0);
$itemId = (int) ($_GET['item_id'] ?? 0);

if ($orderId <= 0 || !in_array($portal, ['pharmacy', 'residence'], true)) {
    http_response_code(403);
    echo 'Prescription not available.';
    exit;
}

$pdo = pharmacy_db();
$orderStmt = $pdo->prepare('
    SELECT o.id, o.pharmacy_id, o.status, o.prescription_path, c.email AS customer_email
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
        echo 'Prescription not available.';
        exit;
    }
    $status = (string) ($order['status'] ?? '');
    if (!in_array($status, ['pending', 'confirmed', 'preparing', 'ready'], true)) {
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

session_write_close();

$path = '';
if ($itemId > 0) {
    $itemStmt = $pdo->prepare('SELECT prescription_path FROM order_items WHERE id = ? AND order_id = ? LIMIT 1');
    $itemStmt->execute([$itemId, $orderId]);
    $path = trim((string) ($itemStmt->fetchColumn() ?: ''));
}
if ($path === '') {
    $path = str_replace('\\', '/', trim((string) ($order['prescription_path'] ?? '')));
}
if ($path === '') {
    http_response_code(404);
    echo 'Prescription file was not found.';
    exit;
}

$file = null;
try {
    $blobStmt = $pdo->prepare('SELECT filename, mime, content FROM order_uploads WHERE relative_path = ? LIMIT 1');
    $blobStmt->execute([$path]);
    $file = $blobStmt->fetch();
    if (is_array($file) && is_resource($file['content'] ?? null)) {
        $file['content'] = stream_get_contents($file['content']);
    }
} catch (Throwable) {
    $file = null;
}

if (!is_array($file) || !is_string($file['content'] ?? null) || $file['content'] === '') {
    $absolute = __DIR__ . '/' . ltrim($path, '/');
    if (is_file($absolute)) {
        $bytes = file_get_contents($absolute);
        if (is_string($bytes) && $bytes !== '') {
            $filename = basename($path);
            $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                default => 'application/octet-stream',
            };
            $file = ['filename' => $filename, 'mime' => $mime, 'content' => $bytes];
        }
    }
}

if (!is_array($file) || !is_string($file['content'] ?? null) || $file['content'] === '') {
    http_response_code(404);
    echo 'Prescription file was not found.';
    exit;
}

$filename = str_replace(['"', "\r", "\n"], '', (string) ($file['filename'] ?? basename($path)));
$mime = trim((string) ($file['mime'] ?? ''));
if ($mime === '') {
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        default => 'application/octet-stream',
    };
}

$etag = '"' . sha1($path . ':' . strlen($file['content'])) . '"';
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
