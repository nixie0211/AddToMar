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

$status = pharmacy_orders_allowed_status(trim((string) ($_GET['status'] ?? 'pending')));

echo json_encode(pharmacy_orders_list_payload($status), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
