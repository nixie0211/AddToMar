<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('pharmacy');
require_once dirname(__DIR__) . '/pharmacy/config.php';
require_once dirname(__DIR__) . '/pharmacy/includes/database.php';
require_once dirname(__DIR__) . '/pharmacy/includes/pharmacy-context.php';
require_once dirname(__DIR__) . '/pharmacy/includes/repository.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'unread' => pharmacy_new_order_badge_count()]);
    exit;
}

echo json_encode([
    'ok' => true,
    'unread' => pharmacy_mark_pending_orders_seen(),
]);
