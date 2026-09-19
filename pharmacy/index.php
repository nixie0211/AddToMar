<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/live-sync.php';
require_portal_auth('pharmacy');
require_once __DIR__ . '/bootstrap.php';

$views = [
    'dashboard',
    'inventory',
    'orders',
    'add-medicine',
    'sales',
    'reports',
    'analytics',
    'notifications',
    'customers',
    'suppliers',
    'settings',
];
$activeView = pharmacy_active_view($views);

require_once dirname(__DIR__) . '/includes/maps.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include PHARMACY_ROOT . '/partials/head.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/map-service-overlay.css'), ENT_QUOTES, 'UTF-8') ?>?v=street-map-2">
<link rel="stylesheet" href="css/base.css?v=pharmacy-notif-count-1">
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/rx-crop-preview.css'), ENT_QUOTES, 'UTF-8') ?>?v=rx-crop-contain-1">
<link rel="stylesheet" href="css/orders.css?v=order-update-btn-right-1">
<link rel="stylesheet" href="css/reports.css">
<link rel="stylesheet" href="css/settings.css?v=settings-logo-plus-1">
<link rel="stylesheet" href="css/add-medicine.css?v=saving-overlay-1">
<link rel="stylesheet" href="css/inventory.css?v=store-name-ellipsis-2">
</head>
<body>

<?php include PHARMACY_ROOT . '/partials/shell-start.php'; ?>

<?php foreach ($views as $view): ?>
<?php include PHARMACY_ROOT . '/views/' . $view . '.php'; ?>
<?php endforeach; ?>

<?php include PHARMACY_ROOT . '/partials/shell-end.php'; ?>
<?php include dirname(__DIR__) . '/partials/map-area-modal.php'; ?>

<script src="js/app.php?v=pharmacy-settings-reset-1"></script>
<script type="application/json" id="pharmacy-chart-data"><?= json_encode($chartData ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
window.PHARMACY_CHART_DATA = <?= json_encode($chartData ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="js/dashboard.php"></script>
<script src="js/add-medicine.php?v=other-dosage-unit-1"></script>
<script src="js/inventory.php?v=new-badge-7d"></script>
<script src="<?= htmlspecialchars(app_url('js/rx-crop-preview.js'), ENT_QUOTES, 'UTF-8') ?>?v=rx-crop-preload-1"></script>
<script src="js/orders.php?v=order-rx-bold-1"></script>
<script src="js/sales.php"></script>
<script src="js/analytics.php"></script>
<script src="js/reports.php"></script>
<script>
window.PHARMACY_REGISTER_MAP_CONFIG = <?= json_encode([
  'tileUrl' => MAP_TILE_URL,
  'tileAttribution' => MAP_ATTRIBUTION,
  'defaultLat' => MAP_DEFAULT_LAT,
  'defaultLng' => MAP_DEFAULT_LNG,
  'defaultZoom' => MAP_DEFAULT_ZOOM,
  'minZoom' => MAP_MIN_ZOOM,
  'maxZoom' => MAP_MAX_ZOOM,
  'bounds' => maps_bounds(),
  'fitBounds' => maps_fit_bounds(),
  'serviceOverlay' => maps_service_overlay(),
  'serviceCities' => MAP_SERVICE_CITIES,
  'geocodeUrl' => app_url('ajax/geocode-search.php'),
  'reverseGeocodeUrl' => app_url('ajax/reverse-geocode.php'),
  'logoUrl' => $pharmacyLogoUrl !== '' ? $pharmacyLogoUrl : null,
  'initialLat' => isset($pharmacyAccount['latitude']) && $pharmacyAccount['latitude'] !== '' && $pharmacyAccount['latitude'] !== null
      ? (float) $pharmacyAccount['latitude']
      : null,
  'initialLng' => isset($pharmacyAccount['longitude']) && $pharmacyAccount['longitude'] !== '' && $pharmacyAccount['longitude'] !== null
      ? (float) $pharmacyAccount['longitude']
      : null,
  'ids' => [
    'map' => 'settings-pharmacy-map',
    'lat' => 'settings-pharmacy-latitude',
    'lng' => 'settings-pharmacy-longitude',
    'address' => 'settings-pharmacy-address',
    'search' => 'settings-pharmacy-map-search',
    'searchBtn' => 'settings-pharmacy-map-search-btn',
    'searchResults' => 'settings-pharmacy-map-search-results',
    'locateBtn' => 'settings-pharmacy-map-locate-btn',
    'status' => 'settings-pharmacy-map-status',
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= htmlspecialchars(app_url('js/map-area-popup.js'), ENT_QUOTES, 'UTF-8') ?>?v=1"></script>
<script src="<?= htmlspecialchars(app_url('js/map-service-overlay.js'), ENT_QUOTES, 'UTF-8') ?>?v=street-map-2"></script>
<script src="<?= htmlspecialchars(app_url('js/register-pharmacy-map.js'), ENT_QUOTES, 'UTF-8') ?>?v=settings-map-view-1"></script>
<script src="js/settings.php?v=settings-logo-plus-1"></script>
<?php live_sync_render_script('pharmacy'); ?>
</body>
</html>
