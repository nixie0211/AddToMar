<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog.php';

function residence_registered_pharmacies(): array
{
    return array_values(residence_pharmacy_directory());
}

function residence_pharmacy_slides(int $perSlide = 10, ?array $pharmacies = null): array
{
    $pharmacies = $pharmacies ?? residence_registered_pharmacies();
    if ($pharmacies === []) {
        return [];
    }

    $slides = array_chunk($pharmacies, $perSlide);

    return $slides;
}

function residence_pharmacies_for_user(?float $userLat, ?float $userLng): array
{
    require_once dirname(__DIR__, 2) . '/includes/maps.php';

    $pharmacies = residence_registered_pharmacies();
    $sortLat = $userLat;
    $sortLng = $userLng;
    $hasUserLocation = $userLat !== null && $userLng !== null;

    if ($sortLat === null || $sortLng === null) {
        $sortLat = MAP_DEFAULT_LAT;
        $sortLng = MAP_DEFAULT_LNG;
    }

    foreach ($pharmacies as &$pharmacy) {
        $lat = $pharmacy['latitude'] ?? null;
        $lng = $pharmacy['longitude'] ?? null;
        if ($lat !== null && $lng !== null) {
            $pharmacy['distance_km'] = distance_km($sortLat, $sortLng, (float) $lat, (float) $lng);
            $pharmacy['has_user_location'] = $hasUserLocation;
            $pharmacy['travel_time'] = format_travel_time((float) $pharmacy['distance_km']);
            $pharmacy['navigate_url'] = maps_navigate_url(
                (float) $lat,
                (float) $lng,
                $hasUserLocation ? $userLat : null,
                $hasUserLocation ? $userLng : null
            );
        } else {
            $pharmacy['distance_km'] = 999;
            $pharmacy['has_user_location'] = false;
            $pharmacy['travel_time'] = '';
            $pharmacy['navigate_url'] = '';
        }
    }
    unset($pharmacy);

    usort($pharmacies, static fn(array $a, array $b): int => ($a['distance_km'] ?? 999) <=> ($b['distance_km'] ?? 999));

    return $pharmacies;
}

function residence_pharmacy_email(array $pharmacy): string
{
    $email = trim((string) ($pharmacy['email'] ?? ''));
    if ($email !== '') {
        return $email;
    }

    $slug = preg_replace('/[^a-z0-9]+/', '.', strtolower((string) ($pharmacy['id'] ?? 'store')));

    return trim((string) $slug, '.') . '@addtomar.ph';
}

function residence_pharmacy_logo_src(array $pharmacy): string
{
    $logoUrl = trim((string) ($pharmacy['logo_url'] ?? ''));
    if ($logoUrl !== '') {
        return $logoUrl;
    }

    $logoPath = trim((string) ($pharmacy['logo_path'] ?? ''));
    if ($logoPath !== '') {
        $absolute = dirname(__DIR__, 2) . '/' . ltrim($logoPath, '/');
        if (is_file($absolute) && function_exists('app_url')) {
            return app_url($logoPath);
        }
    }

    $assetPath = RESIDENCE_ROOT . '/assets/pharmacies/' . ($pharmacy['id'] ?? '') . '.svg';
    if (is_file($assetPath)) {
        return residence_asset('assets/pharmacies/' . $pharmacy['id'] . '.svg');
    }

    $color = preg_replace('/[^#a-fA-F0-9]/', '', $pharmacy['color'] ?? '#1D5FA8') ?: '#1D5FA8';
    $initials = htmlspecialchars($pharmacy['initials'] ?? 'Rx', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img">
  <rect width="64" height="64" rx="16" fill="{$color}"/>
  <text x="32" y="38" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="700" text-anchor="middle">{$initials}</text>
</svg>
SVG;

    return 'data:image/svg+xml,' . rawurlencode($svg);
}
