<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('residence');
require_once dirname(__DIR__) . '/includes/resident-notifications.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'unread' => 0, 'error' => 'Method not allowed.']);
    exit;
}

$email = (string) ($_SESSION['user_email'] ?? '');
$id = trim((string) ($_POST['notification_id'] ?? ''));
$result = resident_notification_mark_read($email, $id);

echo json_encode([
    'ok' => (bool) ($result['ok'] ?? false),
    'unread' => (int) ($result['unread'] ?? 0),
]);
