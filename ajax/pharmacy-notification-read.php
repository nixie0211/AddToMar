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
    echo json_encode(['ok' => false, 'unread' => 0, 'error' => 'Method not allowed.']);
    exit;
}

$id = trim((string) ($_POST['notification_id'] ?? ''));
$result = pharmacy_notification_mark_read($id);

echo json_encode([
    'ok' => (bool) ($result['ok'] ?? false),
    'unread' => (int) ($result['unread'] ?? 0),
]);
