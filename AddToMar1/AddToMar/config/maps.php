<?php
/**
 * Leaflet + OpenStreetMap settings (no API key required).
 */
define('MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
define('MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors');
define('MAP_DEFAULT_LAT', 18.1978);
define('MAP_DEFAULT_LNG', 120.5937);
define('MAP_DEFAULT_ZOOM', 11);
define('OSRM_ROUTE_URL', 'https://router.project-osrm.org/route/v1/driving');

function maps_navigate_url(float $destLat, float $destLng, ?float $originLat = null, ?float $originLng = null): string {
    if ($originLat !== null && $originLng !== null) {
        return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route='
            . rawurlencode("$originLat,$originLng;$destLat,$destLng");
    }
    return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route='
        . rawurlencode("$destLat,$destLng");
}
