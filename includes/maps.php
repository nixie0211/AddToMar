<?php

declare(strict_types=1);

define('MAP_TILE_URL', 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}');
define('MAP_ATTRIBUTION', 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community');
define('MAP_DEFAULT_LAT', 18.1978);
define('MAP_DEFAULT_LNG', 120.5937);
define('MAP_DEFAULT_ZOOM', 12);
define('MAP_MIN_ZOOM', 11);
define('MAP_MAX_ZOOM', 19);

/** Southwest and northeast corners: Laoag, San Nicolas, and Batac service area */
define('MAP_BOUNDS_SOUTH', 18.02);
define('MAP_BOUNDS_WEST', 120.52);
define('MAP_BOUNDS_NORTH', 18.22);
define('MAP_BOUNDS_EAST', 120.62);

define('MAP_SERVICE_CITIES', 'Laoag City, San Nicolas, and Batac City');

function maps_bounds(): array
{
    return [
        'south' => MAP_BOUNDS_SOUTH,
        'west' => MAP_BOUNDS_WEST,
        'north' => MAP_BOUNDS_NORTH,
        'east' => MAP_BOUNDS_EAST,
    ];
}

function maps_point_in_service_area(float $lat, float $lng): bool
{
    return $lat >= MAP_BOUNDS_SOUTH
        && $lat <= MAP_BOUNDS_NORTH
        && $lng >= MAP_BOUNDS_WEST
        && $lng <= MAP_BOUNDS_EAST;
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
