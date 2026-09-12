<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ? AND status = 'active'");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { header('Location: catalog.php'); exit; }

$page_title = $m['medicine_name'];
$related = $pdo->prepare("SELECT * FROM medicines WHERE category = ? AND id != ? AND status='active' LIMIT 4");
$related->execute([$m['category'], $m['id']]);
$related = $related->fetchAll();

$stockClass = $m['quantity'] == 0 ? 'stock-out' : ($m['quantity'] <= $m['reorder_level'] ? 'stock-low' : 'stock-in');
$stockLabel = $m['quantity'] == 0 ? 'Out of Stock' : ($m['quantity'] <= $m['reorder_level'] ? 'Low Stock' : 'In Stock (' . $m['quantity'] . ')');

$hero_badge = clean($m['category']);
$hero_title = clean($m['medicine_name']);
$hero_desc = clean($m['generic_name'] ?: 'View medicine details and add to cart.');
$hero_actions_html = '<a href="catalog.php" class="btn-hero-outline"><i class="bi bi-arrow-left"></i> Back to Catalog</a>';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <div class="card p-4 fade-in-up">
    <div class="row g-4">
      <div class="col-md-5">
        <img src="<?php echo UPLOAD_MED_URL . clean($m['image']); ?>" onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-medicine.png'" class="w-100 rounded-xl" style="object-fit:cover; max-height:340px;">
      </div>
      <div class="col-md-7">
        <span class="cat-tag"><?php echo clean($m['category']); ?></span>
        <h3 class="fw-bold mt-2"><?php echo clean($m['medicine_name']); ?></h3>
        <p class="text-muted mb-1">Generic Name: <strong><?php echo clean($m['generic_name'] ?: '—'); ?></strong></p>
        <p class="text-muted mb-3">Dosage: <strong><?php echo clean($m['dosage'] ?: '—'); ?></strong></p>
        <h3 class="text-primary-em fw-bold"><?php echo money($m['price']); ?></h3>
        <span class="stock-pill <?php echo $stockClass; ?> mb-3 d-inline-block"><?php echo $stockLabel; ?></span>
        <p><?php echo nl2br(clean($m['description'])); ?></p>

        <?php if (($m['uses_info'] ?? '') || ($m['side_effects'] ?? '') || ($m['dosage_instructions'] ?? '')): ?>
        <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#leafletModal">
          <i class="bi bi-file-medical me-1"></i> View Leaflet
        </button>
        <?php endif; ?>

        <div class="d-flex align-items-center gap-2 mt-4">
          <input type="number" id="qtyInput" class="form-control" style="width:90px;" value="1" min="1" max="<?php echo max(1,$m['quantity']); ?>" <?php echo $m['quantity']==0?'disabled':''; ?>>
          <button id="addCartBtn" class="btn btn-gradient px-4" <?php echo $m['quantity']==0?'disabled':''; ?>><i class="bi bi-cart-plus me-1"></i> Add to Cart</button>
        </div>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
  <h6 class="fw-bold mt-4 mb-3">Related Medicines</h6>
  <div class="row g-3">
    <?php foreach ($related as $r): ?>
    <div class="col-6 col-md-3">
      <div class="medicine-card">
        <a href="medicine-details.php?id=<?php echo $r['id']; ?>">
          <img src="<?php echo UPLOAD_MED_URL . clean($r['image']); ?>" onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-medicine.png'">
        </a>
        <div class="body">
          <h6 class="mb-1"><a href="medicine-details.php?id=<?php echo $r['id']; ?>" class="text-dark text-decoration-none"><?php echo clean($r['medicine_name']); ?></a></h6>
          <span class="price"><?php echo money($r['price']); ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (($m['uses_info'] ?? '') || ($m['description'] ?? '')): ?>
  <div class="modal fade" id="leafletModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content rounded-xl">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><?php echo clean($m['medicine_name']); ?> — Leaflet</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php
          $sections = [
            'Description' => $m['description'] ?? '',
            'Uses' => $m['uses_info'] ?? '',
            'Dosage Instructions' => $m['dosage_instructions'] ?? '',
            'Side Effects' => $m['side_effects'] ?? '',
            'Warnings' => $m['warnings'] ?? '',
            'Storage' => $m['storage_info'] ?? '',
            'Manufacturer' => $m['manufacturer'] ?? '',
          ];
          foreach ($sections as $title => $text):
            if (!$text) continue;
          ?>
            <div class="mb-3"><h6 class="fw-bold text-primary"><?php echo $title; ?></h6><p class="small mb-0"><?php echo nl2br(clean($text)); ?></p></div>
          <?php endforeach; ?>
          <a href="medicine-finder.php?q=<?php echo urlencode($m['generic_name'] ?: $m['medicine_name']); ?>" class="btn btn-gradient btn-sm mt-2"><i class="bi bi-geo-alt me-1"></i> Find in Nearby Pharmacies</a>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>

<?php $extra_scripts = '<script>
document.getElementById("addCartBtn")?.addEventListener("click", async function () {
  const qty = document.getElementById("qtyInput").value || 1;
  const res = await ajaxPost("' . BASE_URL . 'ajax/add-to-cart.php", { medicine_id: ' . (int)$m['id'] . ', quantity: qty });
  if (res.success) { showToast("success", res.message); updateCartBadge(res.cart_count); }
  else { showToast("error", res.message); }
});
</script>';
include __DIR__ . '/../includes/footer.php'; ?>
