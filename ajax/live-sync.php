<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/live-sync.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requested = strtolower(trim((string) ($_GET['portal'] ?? '')));
$sessionPortal = live_sync_portal();
$portal = in_array($requested, ['public', 'residence', 'pharmacy', 'admin'], true)
    ? $requested
    : $sessionPortal;

if (in_array($portal, ['residence', 'pharmacy', 'admin'], true) && $sessionPortal !== $portal) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'auth' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

echo json_encode([
    'ok' => true,
    'portal' => $portal,
    'versions' => live_sync_versions($portal),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
