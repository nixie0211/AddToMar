<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/maps.php';

header('Content-Type: application/json');

$lat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float) $_GET['lat'] : null;
$lng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float) $_GET['lng'] : null;

if ($lat === null || $lng === null) {
    echo json_encode(['success' => false, 'address' => ''], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!maps_point_in_service_area($lat, $lng)) {
    echo json_encode(['success' => false, 'address' => '', 'message' => 'Location is outside the service area.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
    'lat' => $lat,
    'lon' => $lng,
    'format' => 'json',
    'addressdetails' => 1,
    'zoom' => 18,
]);

$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: AddToMar/1.0 (customer registration map)\r\nAccept: application/json\r\n",
        'timeout' => 10,
    ],
]);

$raw = @file_get_contents($url, false, $context);
if ($raw === false) {
    echo json_encode(['success' => false, 'address' => '', 'message' => 'Could not look up address.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$item = json_decode($raw, true);
if (!is_array($item)) {
    echo json_encode(['success' => false, 'address' => ''], JSON_UNESCAPED_UNICODE);
    exit;
}

$address = geocode_format_address($item);

echo json_encode([
    'success' => $address !== '',
    'address' => $address,
], JSON_UNESCAPED_UNICODE);

function geocode_format_address(array $item): string
{
    $address = $item['address'] ?? [];
    if (!is_array($address)) {
        return trim((string) ($item['display_name'] ?? ''));
    }

    $parts = [];

    foreach (['house_number', 'road', 'neighbourhood', 'suburb', 'village', 'hamlet', 'town', 'city', 'municipality'] as $key) {
        if (empty($address[$key])) {
            continue;
        }

        $value = trim((string) $address[$key]);
        if ($value !== '' && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    if ($parts !== []) {
        return implode(', ', array_slice($parts, 0, 5));
    }

    $display = trim((string) ($item['display_name'] ?? ''));

    return implode(', ', array_slice(explode(',', $display), 0, 4));
}
