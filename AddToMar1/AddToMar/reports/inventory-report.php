<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Inventory Report';

$all = $pdo->query("SELECT * FROM medicines WHERE status='active' ORDER BY medicine_name")->fetchAll();
$lowStock = array_filter($all, fn($m) => $m['quantity'] > 0 && $m['quantity'] <= $m['reorder_level']);
$outStock = array_filter($all, fn($m) => $m['quantity'] == 0);
$expiring = array_filter($all, fn($m) => $m['expiration_date'] && strtotime($m['expiration_date']) < strtotime('+60 days'));

$hero_badge = 'Reports';
$hero_title = 'Inventory Report';
$hero_desc = 'Current stock levels, low stock, out-of-stock, and expiring medicines.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 fade-in-up">
    <div class="btn-group flex-wrap">
      <a href="inventory-report.php" class="btn btn-sm btn-gradient">Inventory Report</a>
      <a href="sales-report.php" class="btn btn-sm btn-outline-soft">Sales Report</a>
      <a href="order-report.php" class="btn btn-sm btn-outline-soft">Order Report</a>
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-gradient"><i class="bi bi-printer me-1"></i>Print</button>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-value"><?php echo count($all); ?></div><div class="stat-label">Current Stock Items</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-value"><?php echo count($lowStock); ?></div><div class="stat-label">Low Stock</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-value"><?php echo count($outStock); ?></div><div class="stat-label">Out of Stock</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-value"><?php echo count($expiring); ?></div><div class="stat-label">Expiring Soon (60d)</div></div></div>
  </div>

  <div class="card p-3 fade-in-up">
    <h6 class="fw-bold mb-3">Full Inventory</h6>
    <div class="table-responsive">
      <table class="table align-middle" id="reportTable">
        <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Stock</th><th>Reorder Lvl</th><th>Expiry</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($all as $m):
          $isLow = $m['quantity'] > 0 && $m['quantity'] <= $m['reorder_level'];
          $isOut = $m['quantity'] == 0;
          $isExp = $m['expiration_date'] && strtotime($m['expiration_date']) < strtotime('+60 days');
        ?>
          <tr>
            <td><?php echo clean($m['medicine_code']); ?></td>
            <td class="fw-semibold"><?php echo clean($m['medicine_name']); ?></td>
            <td><?php echo clean($m['category']); ?></td>
            <td><?php echo $m['quantity']; ?></td>
            <td><?php echo $m['reorder_level']; ?></td>
            <td class="<?php echo $isExp ? 'text-danger fw-semibold' : ''; ?>"><?php echo $m['expiration_date'] ? date('M d, Y', strtotime($m['expiration_date'])) : '—'; ?></td>
            <td>
              <?php if ($isOut): ?><span class="stock-pill stock-out">Out of Stock</span>
              <?php elseif ($isLow): ?><span class="stock-pill stock-low">Low Stock</span>
              <?php else: ?><span class="stock-pill stock-in">In Stock</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php $extra_scripts = '<script>$(function(){ $("#reportTable").DataTable({ order: [], pageLength: 15 }); });</script>';
include __DIR__ . '/../includes/footer.php'; ?>
