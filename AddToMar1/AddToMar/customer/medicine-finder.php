<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/maps.php';
require_role('customer');
$u = current_user();
$page_title = 'Medicine Finder';

$preset = clean($_GET['q'] ?? '');
$body_class = 'hero-dashboard';
$hero_badge = 'Medicine Finder';
$hero_title = 'Find Medicines & Pharmacies';
$hero_desc = 'Search by brand or generic name, view leaflets, locate nearby pharmacies, and get directions.';
$hero_actions_html = '<a href="search-history.php" class="btn-hero-outline"><i class="bi bi-clock-history"></i> History</a>
  <a href="favorites.php" class="btn-hero-outline"><i class="bi bi-star"></i> Favorites</a>';
include __DIR__ . '/../includes/hero-layout-start.php';

$mf_css = filemtime(__DIR__ . '/../assets/css/medicine-finder.css');
$mf_js = filemtime(__DIR__ . '/../assets/js/medicine-finder.js');
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/medicine-finder.css?v=<?php echo $mf_css; ?>">

<div class="finder-hero-search mb-4 fade-in-up">
  <label class="form-label fw-bold"><i class="bi bi-search me-1"></i> Search Medicine (brand or generic name)</label>
  <input type="text" id="finderSearch" class="form-control form-control-lg" placeholder="Try Paracetamol, Biogesic, Cetirizine..." value="<?php echo $preset; ?>" autocomplete="off">
  <div class="small text-muted mt-2"><span class="pulse-dot"></span> Real-time inventory · OpenStreetMap powered (no API key needed)</div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="glass-card p-3 mb-3 fade-in-up">
      <h6 class="fw-bold mb-3"><i class="bi bi-capsule me-1 text-primary"></i> Search Results</h6>
      <div id="searchResults">
        <div class="finder-empty small">Start typing to search medicines.</div>
      </div>
    </div>
    <div class="glass-card p-3 fade-in-up">
      <h6 class="fw-bold mb-3"><i class="bi bi-building me-1 text-success"></i> Pharmacies with Stock</h6>
      <div id="pharmacyList">
        <div class="finder-empty small">Select a medicine to see nearby pharmacies.</div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="glass-card p-2 mb-3 fade-in-up">
      <div id="finderMap"></div>
    </div>
    <div id="routePanel" class="mb-3 d-none"></div>
    <div id="altPanel" class="d-none"></div>
  </div>
</div>

<div class="modal fade" id="leafletModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content rounded-xl">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="leafletTitle">Medicine Leaflet</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="leafletBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-gradient" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>

<?php $extra_scripts = '<script>
window.FINDER_CONFIG = {
  searchUrl: "' . BASE_URL . 'ajax/finder-search.php",
  pharmaciesUrl: "' . BASE_URL . 'ajax/finder-pharmacies.php",
  favoriteUrl: "' . BASE_URL . 'ajax/favorite-pharmacy.php",
  csrfToken: "' . generate_csrf_token() . '",
  tileUrl: ' . json_encode(MAP_TILE_URL) . ',
  tileAttribution: ' . json_encode(MAP_ATTRIBUTION) . ',
  osrmUrl: ' . json_encode(OSRM_ROUTE_URL) . ',
  defaultLat: ' . MAP_DEFAULT_LAT . ',
  defaultLng: ' . MAP_DEFAULT_LNG . ',
  defaultZoom: ' . MAP_DEFAULT_ZOOM . ',
  defaultImage: "' . BASE_URL . 'assets/images/default-medicine.png",
  presetSearch: ' . json_encode($preset) . '
};
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
<script src="' . BASE_URL . 'assets/js/medicine-finder.js?v=' . $mf_js . '"></script>';
include __DIR__ . '/../includes/footer.php'; ?>
