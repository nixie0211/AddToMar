<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/customers.php';
require_once dirname(__DIR__) . '/residence/includes/orders.php';

header('Content-Type: application/json; charset=utf-8');
require_portal_auth('residence');
$profile = residence_session_profile();
$email = (string) ($profile['email'] ?? $_SESSION['user_email'] ?? '');
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

echo json_encode(['ok' => true, 'orders' => residence_customer_orders_payload($email)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
