<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');
if (!defined('PHARMACY_SKIP_MIGRATIONS')) {
    define('PHARMACY_SKIP_MIGRATIONS', true);
}
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/repository.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$orderId = (int) ($_GET['order_id'] ?? 0);
$detail = pharmacy_order_detail_payload($orderId);
if ($detail === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

echo json_encode(['success' => true, 'order' => $detail], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
