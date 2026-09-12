<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/maps.php';

header('Content-Type: application/json');

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '' || mb_strlen($query) < 3) {
    echo json_encode(['success' => false, 'results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$bounds = maps_bounds();
$viewbox = sprintf(
    '%s,%s,%s,%s',
    $bounds['west'],
    $bounds['north'],
    $bounds['east'],
    $bounds['south']
);

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q' => $query . ', Ilocos Norte, Philippines',
    'format' => 'json',
    'limit' => 8,
    'viewbox' => $viewbox,
    'bounded' => 1,
    'countrycodes' => 'ph',
    'addressdetails' => 1,
]);

$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: AddToMar/1.0 (customer registration map)\r\nAccept: application/json\r\n",
        'timeout' => 10,
    ],
]);

$raw = @file_get_contents($url, false, $context);
if ($raw === false) {
    echo json_encode(['success' => false, 'results' => [], 'message' => 'Search unavailable. Try again.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = json_decode($raw, true);
if (!is_array($items)) {
    echo json_encode(['success' => false, 'results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$results = [];
foreach ($items as $item) {
    if (!isset($item['lat'], $item['lon'])) {
        continue;
    }

    $lat = (float) $item['lat'];
    $lng = (float) $item['lon'];

    if (!maps_point_in_service_area($lat, $lng)) {
        continue;
    }

    $results[] = [
        'label' => (string) ($item['display_name'] ?? $query),
        'lat' => $lat,
        'lng' => $lng,
    ];
}

echo json_encode([
    'success' => true,
    'results' => array_slice($results, 0, 6),
], JSON_UNESCAPED_UNICODE);
