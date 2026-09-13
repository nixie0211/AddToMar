<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/pharmacies.php';
require_once __DIR__ . '/includes/catalog.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/live-sync.php';
require_once dirname(__DIR__) . '/includes/customers.php';
require_once dirname(__DIR__) . '/includes/pharmacy-locations.php';
require_once dirname(__DIR__) . '/includes/maps.php';
require_once dirname(__DIR__) . '/includes/resident-notifications.php';
require_once __DIR__ . '/includes/orders.php';
require_portal_auth('residence');

$residenceProfile = residence_session_profile();
if (strtolower((string) ($_SERVER['HTTP_X_LIVE_SYNC'] ?? '')) === '1' && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$residenceOrders = residence_customer_orders_payload((string) ($residenceProfile['email'] ?? $_SESSION['user_email'] ?? ''));
$residenceNotifications = resident_notifications_for_user((string) ($residenceProfile['email'] ?? $_SESSION['user_email'] ?? ''));
$residenceReports = resident_reports_for_user((string) ($residenceProfile['email'] ?? $_SESSION['user_email'] ?? ''));
$userLat = $residenceProfile['latitude'];
$userLng = $residenceProfile['longitude'];
$sortedResidencePharmacies = residence_pharmacies_for_user($userLat, $userLng);
$residenceCatalogMedicines = residence_catalog_medicines();
$residenceNewProducts = residence_catalog_new_products($residenceCatalogMedicines);
$residenceFeaturedProducts = residence_catalog_featured_products($residenceCatalogMedicines);
$residenceTopSellers = residence_catalog_top_sellers();
$residenceMarketplacePharmacies = $sortedResidencePharmacies;
$nearbyPharmacies = $sortedResidencePharmacies;
$residencePharmacyCatalog = array_map(static function (array $pharmacy): array {
    return [
        'id' => $pharmacy['id'],
        'label' => $pharmacy['label'],
        'name' => $pharmacy['name'],
        'branch' => $pharmacy['branch'],
        'address' => $pharmacy['address'] ?? '',
        'contact' => $pharmacy['contact'] ?? '',
        'email' => residence_pharmacy_email($pharmacy),
        'navigate_url' => $pharmacy['navigate_url'] ?? '',
        'color' => $pharmacy['color'] ?? '#1D5FA8',
        'distance_km' => $pharmacy['distance_km'] ?? null,
        'travel_time' => $pharmacy['travel_time'] ?? '',
        'is_open' => !empty($pharmacy['is_open']),
        'logo_url' => residence_pharmacy_logo_src($pharmacy),
        'latitude' => $pharmacy['latitude'] ?? null,
        'longitude' => $pharmacy['longitude'] ?? null,
    ];
}, $sortedResidencePharmacies);

$views = [
    'dashboard',
    'pharmacies',
    'pharmacy-profile',
    'locator',
    'cart',
    'checkout',
    'tracking',
    'notifications',
    'orders',
    'profile',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include RESIDENCE_ROOT . '/partials/head.php'; ?>
</head>
<body>

<?php include RESIDENCE_ROOT . '/partials/shell-start.php'; ?>

<?php foreach ($views as $view): ?>
<?php include RESIDENCE_ROOT . '/views/' . $view . '.php'; ?>
<?php endforeach; ?>

<?php include RESIDENCE_ROOT . '/partials/shell-end.php'; ?>

<script>window.LOGOUT_URL = '<?= htmlspecialchars(app_url('logout.php'), ENT_QUOTES, 'UTF-8') ?>';</script>
<script>
window.USER_LOCATION = <?= json_encode([
  'lat' => $userLat,
  'lng' => $userLng,
  'address' => $residenceProfile['address'],
  'hasLocation' => $userLat !== null && $userLng !== null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.RESIDENCE_CONFIG = <?= json_encode([
  'nearbyUrl' => app_url('ajax/nearby-pharmacies.php'),
  'profile' => $residenceProfile,
  'tileUrl' => MAP_TILE_URL,
  'tileAttribution' => MAP_ATTRIBUTION,
  'defaultLat' => MAP_DEFAULT_LAT,
  'defaultLng' => MAP_DEFAULT_LNG,
  'defaultZoom' => MAP_DEFAULT_ZOOM,
  'minZoom' => MAP_MIN_ZOOM,
  'maxZoom' => MAP_MAX_ZOOM,
  'bounds' => maps_bounds(),
  'serviceOverlay' => maps_service_overlay(),
  'pharmacies' => $residencePharmacyCatalog,
  'marketplacePharmacies' => $residenceMarketplacePharmacies,
  'placeOrderUrl' => app_url('ajax/place-order.php'),
  'cartUrl' => app_url('ajax/resident-cart.php'),
  'paymongoCompleteUrl' => app_url('ajax/paymongo-complete.php'),
  'pendingPaymentIntent' => (string) ($_GET['payment_intent_id'] ?? ''),
  'saveAddressUrl' => app_url('ajax/save-address.php'),
  'saveProfileUrl' => app_url('ajax/save-profile.php'),
  'ordersUrl' => app_url('ajax/residence-orders.php'),
  'reportPharmacyUrl' => app_url('ajax/report-pharmacy.php'),
  'markNotificationReadUrl' => app_url('ajax/resident-notification-read.php'),
  'geocodeUrl' => app_url('ajax/geocode-search.php'),
  'reverseGeocodeUrl' => app_url('ajax/reverse-geocode.php'),
  'savedAddresses' => customer_addresses_list($residenceProfile['email']),
  'reports' => array_map(static function (array $report): array {
      $report['proof_url'] = !empty($report['proof_path']) ? app_url((string) $report['proof_path']) : '';
      return $report;
  }, $residenceReports),
  'orders' => $residenceOrders,
  'newProductIds' => array_values(array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $residenceNewProducts)),
  'featuredProductIds' => array_values(array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $residenceFeaturedProducts)),
  'topSellerIds' => array_values(array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $residenceTopSellers)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.nearbyPharmacies = window.RESIDENCE_CONFIG.pharmacies || [];
</script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('css/map-service-overlay.css'), ENT_QUOTES, 'UTF-8') ?>?v=1">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= htmlspecialchars(app_url('js/map-service-overlay.js'), ENT_QUOTES, 'UTF-8') ?>?v=1"></script>
<script type="application/json" id="residence-live-payload"><?= json_encode([
  'profile' => $residenceProfile,
  'pharmacies' => $residencePharmacyCatalog,
  'marketplacePharmacies' => $residenceMarketplacePharmacies,
  'orders' => $residenceOrders,
  'reports' => array_map(static function (array $report): array {
      $report['proof_url'] = !empty($report['proof_path']) ? app_url((string) $report['proof_path']) : '';
      return $report;
  }, $residenceReports),
  'savedAddresses' => customer_addresses_list($residenceProfile['email']),
  'newProductIds' => array_values(array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $residenceNewProducts)),
  'featuredProductIds' => array_values(array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $residenceFeaturedProducts)),
  'topSellerIds' => array_values(array_map(static fn(array $item): int => (int) ($item['id'] ?? 0), $residenceTopSellers)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= residence_asset('js/app.php') ?>?v=service-overlay-1"></script>
<?php live_sync_render_script('residence'); ?>
<script>
window.openProductPreview = window.openProductPreview || function(card) {
  if (typeof window.go === 'function') window.go('browse');
};
</script>
</body>
</html>
