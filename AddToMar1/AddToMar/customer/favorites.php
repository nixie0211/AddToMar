<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pharmacy.php';
require_role('customer');
$u = current_user();
$page_title = 'Favorite Pharmacies';

$stmt = $pdo->prepare('SELECT p.*, fp.created_at AS favorited_at
                       FROM favorite_pharmacies fp
                       JOIN pharmacies p ON p.id = fp.pharmacy_id
                       WHERE fp.user_id = ? AND p.status = "active"
                       ORDER BY fp.created_at DESC');
$stmt->execute([$u['id']]);
$favorites = $stmt->fetchAll();

$hero_badge = 'Favorites';
$hero_title = 'Favorite Pharmacies';
$hero_desc = 'Quick access to your saved pharmacy locations.';
$hero_actions_html = '<a href="medicine-finder.php" class="btn-hero-outline"><i class="bi bi-search"></i> Medicine Finder</a>';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

<?php if (!$favorites): ?>
  <div class="card p-5 text-center fade-in-up">
    <i class="bi bi-star" style="font-size:2.5rem;color:var(--muted);"></i>
    <p class="text-muted mt-3 mb-3">No favorite pharmacies yet. Star a pharmacy in the Medicine Finder.</p>
    <a href="medicine-finder.php" class="btn btn-gradient mx-auto" style="width:fit-content;">Find Pharmacies</a>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($favorites as $p): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card p-4 fade-in-up h-100">
        <h6 class="fw-bold"><?php echo clean($p['pharmacy_name']); ?></h6>
        <p class="small text-muted mb-2"><i class="bi bi-geo-alt me-1"></i><?php echo clean($p['address']); ?></p>
        <p class="small mb-2"><i class="bi bi-telephone me-1"></i><?php echo clean($p['contact_number']); ?></p>
        <p class="small mb-2"><i class="bi bi-clock me-1"></i><?php echo clean($p['operating_hours']); ?></p>
        <span class="<?php echo pharmacy_is_open($p) ? 'status-open' : 'status-closed'; ?> small">
          <?php echo pharmacy_is_open($p) ? 'Open Now' : 'Closed'; ?>
        </span>
        <div class="mt-3 d-flex gap-2">
          <a href="<?php echo maps_navigate_url((float)$p['latitude'], (float)$p['longitude']); ?>" target="_blank" class="btn btn-gradient btn-sm flex-grow-1">Navigate</a>
          <a href="medicine-finder.php" class="btn btn-outline-soft btn-sm">Finder</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
