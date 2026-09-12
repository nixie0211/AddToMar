<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');
$u = current_user();
$page_title = 'Medicine Catalog';

$search = clean($_GET['search'] ?? '');
$category = clean($_GET['category'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$where = "WHERE status = 'active'";
$params = [];
if ($search !== '') {
    $where .= " AND (medicine_name LIKE ? OR generic_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($category !== '') {
    $where .= " AND category = ?";
    $params[] = $category;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM medicines $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['c'];
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM medicines $where ORDER BY medicine_name ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$medicines = $stmt->fetchAll();

$categories = $pdo->query("SELECT DISTINCT category FROM medicines ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

$hero_badge = 'Catalog';
$hero_title = 'Medicine Catalog';
$hero_desc = 'Browse OTC medicines and check availability in real time.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <form method="GET" class="card p-3 mb-4 fade-in-up">
    <div class="row g-2">
      <div class="col-md-6">
        <div class="input-icon-group">
          <i class="bi bi-search"></i>
          <input type="text" name="search" class="form-control" placeholder="Search medicine name or generic name..." value="<?php echo clean($search); ?>">
        </div>
      </div>
      <div class="col-md-4">
        <select name="category" class="form-select">
          <option value="">All Categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo clean($c); ?>" <?php echo $category === $c ? 'selected' : ''; ?>><?php echo clean($c); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button class="btn btn-gradient w-100">Filter</button>
      </div>
    </div>
  </form>

  <div class="row g-3">
    <?php if (!$medicines): ?>
      <p class="text-muted text-center py-5">No medicines found matching your search.</p>
    <?php endif; ?>
    <?php foreach ($medicines as $m):
      $stockClass = $m['quantity'] == 0 ? 'stock-out' : ($m['quantity'] <= $m['reorder_level'] ? 'stock-low' : 'stock-in');
      $stockLabel = $m['quantity'] == 0 ? 'Out of Stock' : ($m['quantity'] <= $m['reorder_level'] ? 'Low Stock' : 'In Stock');
    ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="medicine-card fade-in-up">
        <a href="medicine-details.php?id=<?php echo $m['id']; ?>">
          <img src="<?php echo UPLOAD_MED_URL . clean($m['image']); ?>" onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-medicine.png'" alt="<?php echo clean($m['medicine_name']); ?>">
        </a>
        <div class="body">
          <span class="cat-tag"><?php echo clean($m['category']); ?></span>
          <h6 class="mb-1"><a href="medicine-details.php?id=<?php echo $m['id']; ?>" class="text-dark text-decoration-none"><?php echo clean($m['medicine_name']); ?></a></h6>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="price"><?php echo money($m['price']); ?></span>
            <span class="stock-pill <?php echo $stockClass; ?>"><?php echo $stockLabel; ?></span>
          </div>
          <button class="btn btn-gradient w-100 btn-sm add-to-cart-btn" data-id="<?php echo $m['id']; ?>" <?php echo $m['quantity'] == 0 ? 'disabled' : ''; ?>>
            <i class="bi bi-cart-plus"></i> Add to Cart
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="mt-4">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
          <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>"><?php echo $i; ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>

<?php $extra_scripts = '<script>
document.querySelectorAll(".add-to-cart-btn").forEach(btn => {
  btn.addEventListener("click", async function () {
    const id = this.dataset.id;
    const res = await ajaxPost("' . BASE_URL . 'ajax/add-to-cart.php", { medicine_id: id, quantity: 1 });
    if (res.success) {
      showToast("success", res.message);
      updateCartBadge(res.cart_count);
    } else {
      showToast("error", res.message);
    }
  });
});
</script>';
include __DIR__ . '/../includes/footer.php'; ?>
