<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pharmacy.php';
require_once __DIR__ . '/../config/maps.php';
require_role('pharmacist');
$page_title = 'Manage Pharmacies';

$pharmacies = $pdo->query('SELECT p.*, COUNT(i.id) AS medicine_count, COALESCE(SUM(i.stock_quantity),0) AS total_stock
                             FROM pharmacies p
                             LEFT JOIN inventory i ON i.pharmacy_id = p.id
                             GROUP BY p.id
                             ORDER BY p.pharmacy_name ASC')->fetchAll();

$hero_badge = 'Pharmacies';
$hero_title = 'Manage Pharmacies';
$hero_desc = 'Register pharmacy locations with coordinates for the Medicine Finder map.';
$hero_actions_html = '<button class="btn-hero-primary" data-bs-toggle="modal" data-bs-target="#pharmacyModal" onclick="openPharmacyModal()"><i class="bi bi-plus-lg"></i> Register Pharmacy</button>';
include __DIR__ . '/../includes/hero-layout-start.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>#pharmacyPickMap{height:220px;border-radius:12px;border:1px solid #e2e8f0;z-index:1;}</style>

<div class="row g-3 mb-4">
  <?php foreach ($pharmacies as $p): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card p-4 fade-in-up h-100">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h6 class="fw-bold mb-0"><?php echo clean($p['pharmacy_name']); ?></h6>
        <span class="badge <?php echo $p['status']==='active'?'text-bg-success':'text-bg-secondary'; ?>"><?php echo ucfirst($p['status']); ?></span>
      </div>
      <p class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i><?php echo clean($p['address']); ?></p>
      <p class="small mb-1"><i class="bi bi-telephone me-1"></i><?php echo clean($p['contact_number']); ?></p>
      <p class="small mb-2"><i class="bi bi-clock me-1"></i><?php echo clean($p['operating_hours']); ?></p>
      <p class="small mb-2"><span class="<?php echo pharmacy_is_open($p)?'status-open':'status-closed'; ?>"><?php echo pharmacy_is_open($p)?'Open':'Closed'; ?></span> · <?php echo (int)$p['medicine_count']; ?> medicines · <?php echo (int)$p['total_stock']; ?> total stock</p>
      <p class="small text-muted mb-3">Lat: <?php echo $p['latitude']; ?>, Lng: <?php echo $p['longitude']; ?></p>
      <button class="btn btn-sm btn-outline-soft edit-pharmacy-btn" data-ph='<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>' data-bs-toggle="modal" data-bs-target="#pharmacyModal"><i class="bi bi-pencil"></i> Edit</button>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="modal fade" id="pharmacyModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <form id="pharmacyForm">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="phModalTitle">Register Pharmacy</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <input type="hidden" name="id" id="ph_id">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Pharmacy Name *</label><input type="text" name="pharmacy_name" id="ph_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Contact Number *</label><input type="text" name="contact_number" id="ph_contact" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Complete Address *</label><input type="text" name="address" id="ph_address" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Latitude *</label><input type="number" step="any" name="latitude" id="ph_lat" class="form-control" required placeholder="18.1978"></div>
            <div class="col-md-4"><label class="form-label">Longitude *</label><input type="number" step="any" name="longitude" id="ph_lng" class="form-control" required placeholder="120.5937"></div>
            <div class="col-md-4"><label class="form-label">Status</label><select name="status" id="ph_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="col-md-6"><label class="form-label">Operating Hours (display)</label><input type="text" name="operating_hours" id="ph_hours" class="form-control" value="Mon-Sat 8:00 AM - 8:00 PM"></div>
            <div class="col-md-3"><label class="form-label">Opens</label><input type="time" name="open_time" id="ph_open" class="form-control" value="08:00"></div>
            <div class="col-md-3"><label class="form-label">Closes</label><input type="time" name="close_time" id="ph_close" class="form-control" value="20:00"></div>
            <div class="col-12">
              <label class="form-label">Pick Location on Map *</label>
              <div id="pharmacyPickMap"></div>
              <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Click the map to set coordinates, or enter lat/lng manually. You can also use <a href="https://www.openstreetmap.org" target="_blank" rel="noopener">OpenStreetMap</a> → right-click a spot → "Show address" to copy coordinates.</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-soft" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-gradient">Save Pharmacy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>

<?php $extra_scripts = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
const PH_MAP = {
  tileUrl: ' . json_encode(MAP_TILE_URL) . ',
  attribution: ' . json_encode(MAP_ATTRIBUTION) . ',
  defaultLat: ' . MAP_DEFAULT_LAT . ',
  defaultLng: ' . MAP_DEFAULT_LNG . ',
  defaultZoom: ' . MAP_DEFAULT_ZOOM . '
};
let pickMap = null, pickMarker = null;

function setPickMarker(lat, lng, pan) {
  document.getElementById("ph_lat").value = lat.toFixed(8);
  document.getElementById("ph_lng").value = lng.toFixed(8);
  if (!pickMap) return;
  if (pickMarker) pickMap.removeLayer(pickMarker);
  pickMarker = L.marker([lat, lng], { draggable: true }).addTo(pickMap);
  pickMarker.on("dragend", (e) => {
    const p = e.target.getLatLng();
    document.getElementById("ph_lat").value = p.lat.toFixed(8);
    document.getElementById("ph_lng").value = p.lng.toFixed(8);
  });
  if (pan) pickMap.setView([lat, lng], Math.max(pickMap.getZoom(), 14));
}

function initPickMap() {
  if (pickMap) { pickMap.invalidateSize(); return; }
  const lat = parseFloat(document.getElementById("ph_lat").value) || PH_MAP.defaultLat;
  const lng = parseFloat(document.getElementById("ph_lng").value) || PH_MAP.defaultLng;
  pickMap = L.map("pharmacyPickMap").setView([lat, lng], PH_MAP.defaultZoom);
  L.tileLayer(PH_MAP.tileUrl, { attribution: PH_MAP.attribution, maxZoom: 19 }).addTo(pickMap);
  pickMap.on("click", (e) => setPickMarker(e.latlng.lat, e.latlng.lng, false));
  if (document.getElementById("ph_lat").value && document.getElementById("ph_lng").value) {
    setPickMarker(lat, lng, false);
  }
  setTimeout(() => pickMap.invalidateSize(), 200);
}

function openPharmacyModal() {
  document.getElementById("phModalTitle").textContent = "Register Pharmacy";
  document.getElementById("pharmacyForm").reset();
  document.getElementById("ph_id").value = "";
  document.getElementById("ph_lat").value = PH_MAP.defaultLat;
  document.getElementById("ph_lng").value = PH_MAP.defaultLng;
  if (pickMarker && pickMap) { pickMap.removeLayer(pickMarker); pickMarker = null; }
}

document.getElementById("pharmacyModal").addEventListener("shown.bs.modal", initPickMap);

document.querySelectorAll(".edit-pharmacy-btn").forEach(btn => {
  btn.addEventListener("click", () => {
    const p = JSON.parse(btn.dataset.ph);
    document.getElementById("phModalTitle").textContent = "Edit Pharmacy";
    document.getElementById("ph_id").value = p.id;
    document.getElementById("ph_name").value = p.pharmacy_name;
    document.getElementById("ph_address").value = p.address;
    document.getElementById("ph_lat").value = p.latitude;
    document.getElementById("ph_lng").value = p.longitude;
    document.getElementById("ph_contact").value = p.contact_number;
    document.getElementById("ph_hours").value = p.operating_hours;
    document.getElementById("ph_open").value = (p.open_time || "08:00:00").substring(0,5);
    document.getElementById("ph_close").value = (p.close_time || "20:00:00").substring(0,5);
    document.getElementById("ph_status").value = p.status;
    setTimeout(() => {
      if (pickMap) setPickMarker(parseFloat(p.latitude), parseFloat(p.longitude), true);
    }, 300);
  });
});

["ph_lat", "ph_lng"].forEach(id => {
  document.getElementById(id).addEventListener("change", () => {
    const lat = parseFloat(document.getElementById("ph_lat").value);
    const lng = parseFloat(document.getElementById("ph_lng").value);
    if (!isNaN(lat) && !isNaN(lng) && pickMap) setPickMarker(lat, lng, true);
  });
});

document.getElementById("pharmacyForm").addEventListener("submit", async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  fd.append("action", "save");
  const res = await fetch("' . BASE_URL . 'ajax/crud-pharmacy.php", { method: "POST", body: fd }).then(r => r.json());
  if (res.success) { showToast("success", res.message); setTimeout(() => location.reload(), 800); }
  else { showToast("error", res.message); }
});
</script>';
include __DIR__ . '/../includes/footer.php'; ?>
