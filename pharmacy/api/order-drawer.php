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

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');

$activeView = 'orders';
$orderStatusCounts = [];
$pharmacyOrdersDrawerOnly = true;

include dirname(__DIR__) . '/views/orders.php';
