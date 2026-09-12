<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('residence');
require_once dirname(__DIR__) . '/includes/resident-cart.php';

header('Content-Type: application/json; charset=UTF-8');

$email = (string) ($_SESSION['user_email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(resident_cart_payload($email), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$itemsRaw = (string) ($_POST['items'] ?? '');
$items = json_decode($itemsRaw, true);
if (!is_array($items)) {
    $items = [];
}

$result = resident_cart_replace($email, $items);
if (!($result['ok'] ?? false)) {
    http_response_code(422);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
