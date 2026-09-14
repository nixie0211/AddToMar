<?php

declare(strict_types=1);

define('MAP_TILE_URL', 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png');
define('MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>');
define('MAP_DEFAULT_LAT', 18.14);
define('MAP_DEFAULT_LNG', 120.62);
define('MAP_DEFAULT_ZOOM', 11);
define('MAP_MIN_ZOOM', 10);
define('MAP_MAX_ZOOM', 19);

/** Wider view box so nearby towns stay visible around the yellow service outline */
define('MAP_BOUNDS_SOUTH', 17.98);
define('MAP_BOUNDS_WEST', 120.46);
define('MAP_BOUNDS_NORTH', 18.32);
define('MAP_BOUNDS_EAST', 120.82);

define('MAP_SERVICE_CITIES', 'Laoag City, San Nicolas, and Batac City');

function maps_service_polygon(): array
{
    return [
        ['lat' => 18.222, 'lng' => 120.558],
        ['lat' => 18.222, 'lng' => 120.618],
        ['lat' => 18.188, 'lng' => 120.628],
        ['lat' => 18.155, 'lng' => 120.618],
        ['lat' => 18.100, 'lng' => 120.598],
        ['lat' => 18.038, 'lng' => 120.582],
        ['lat' => 18.028, 'lng' => 120.548],
        ['lat' => 18.048, 'lng' => 120.532],
        ['lat' => 18.112, 'lng' => 120.542],
        ['lat' => 18.168, 'lng' => 120.552],
        ['lat' => 18.205, 'lng' => 120.548],
    ];
}

function maps_service_overlay(): array
{
    return [
        'polygon' => maps_service_polygon(),
    ];
}

function maps_fit_bounds(): array
{
    $polygon = maps_service_polygon();
    $lats = array_column($polygon, 'lat');
    $lngs = array_column($polygon, 'lng');
    $pad = 0.02;

    return [
        'south' => min($lats) - $pad,
        'west' => min($lngs) - $pad,
        'north' => max($lats) + $pad,
        'east' => max($lngs) + $pad,
    ];
}

function maps_bounds(): array
{
    return [
        'south' => MAP_BOUNDS_SOUTH,
        'west' => MAP_BOUNDS_WEST,
        'north' => MAP_BOUNDS_NORTH,
        'east' => MAP_BOUNDS_EAST,
    ];
}

function maps_point_in_polygon(float $lat, float $lng, array $polygon): bool
{
    $inside = false;
    $count = count($polygon);
    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        $yi = (float) $polygon[$i]['lat'];
        $xi = (float) $polygon[$i]['lng'];
        $yj = (float) $polygon[$j]['lat'];
        $xj = (float) $polygon[$j]['lng'];
        $denom = $yj - $yi;
        if ($denom == 0.0) {
            $denom = 0.0000001;
        }
        $intersect = (($yi > $lat) !== ($yj > $lat))
            && ($lng < ($xj - $xi) * ($lat - $yi) / $denom + $xi);
        if ($intersect) {
            $inside = !$inside;
        }
    }

    return $inside;
}

function maps_point_in_service_area(float $lat, float $lng): bool
{
    return maps_point_in_polygon($lat, $lng, maps_service_polygon());
}

function maps_navigate_url(float $destLat, float $destLng, ?float $originLat = null, ?float $originLng = null): string
{
    if ($originLat !== null && $originLng !== null) {
        return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route='
            . rawurlencode("$originLat,$originLng;$destLat,$destLng");
    }

    return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route='
        . rawurlencode("$destLat,$destLng");
}

function distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
}

function format_travel_time(float $distanceKm): string
{
    $minutes = (int) max(1, round(($distanceKm / 30) * 60));
    if ($minutes < 60) {
        return $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;

    return $hours . ' hr' . ($remaining ? ' ' . $remaining . ' min' : '');
}
