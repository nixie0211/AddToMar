<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/pharmacy-locations.php';

header('Content-Type: application/json');

$userLat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : null;
$userLng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : null;
$limit = isset($_GET['limit']) ? max(1, min(20, (int) $_GET['limit'])) : 5;

echo json_encode([
    'success' => true,
    'service_area' => MAP_SERVICE_CITIES,
    'pharmacies' => pharmacies_nearby($userLat, $userLng, $limit),
], JSON_UNESCAPED_UNICODE);
