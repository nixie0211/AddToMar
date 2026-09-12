<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Sales Report';

$filter = $_GET['filter'] ?? 'monthly';
$map = ['daily' => '%Y-%m-%d', 'weekly' => '%x-W%v', 'monthly' => '%Y-%m', 'yearly' => '%Y'];
$fmt = $map[$filter] ?? $map['monthly'];

$sales = $pdo->prepare("SELECT DATE_FORMAT(created_at, ?) period, COUNT(*) order_count, SUM(total_amount) total
                         FROM orders WHERE status != 'Cancelled'
                         GROUP BY period ORDER BY period DESC LIMIT 20");
$sales->execute([$fmt]);
$sales = $sales->fetchAll();

$grandTotal = array_sum(array_column($sales, 'total'));
$grandOrders = array_sum(array_column($sales, 'order_count'));

$hero_badge = 'Reports';
$hero_title = 'Sales Report';
$hero_desc = 'Sales performance broken down by period.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 fade-in-up">
    <div class="btn-group flex-wrap">
      <a href="inventory-report.php" class="btn btn-sm btn-outline-soft">Inventory Report</a>
      <a href="sales-report.php" class="btn btn-sm btn-gradient">Sales Report</a>
      <a href="order-report.php" class="btn btn-sm btn-outline-soft">Order Report</a>
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-gradient"><i class="bi bi-printer me-1"></i>Print</button>
  </div>

  <div class="mb-3 fade-in-up">
    <div class="btn-group">
      <?php foreach (['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly','yearly'=>'Yearly'] as $k=>$label): ?>
        <a href="?filter=<?php echo $k; ?>" class="btn btn-sm <?php echo $filter===$k?'btn-gradient':'btn-outline-soft'; ?>"><?php echo $label; ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-4"><div class="stat-card"><div class="stat-value"><?php echo money($grandTotal); ?></div><div class="stat-label">Total Sales (shown)</div></div></div>
    <div class="col-6 col-lg-4"><div class="stat-card"><div class="stat-value"><?php echo $grandOrders; ?></div><div class="stat-label">Total Orders (shown)</div></div></div>
    <div class="col-6 col-lg-4"><div class="stat-card"><div class="stat-value"><?php echo money($grandOrders > 0 ? $grandTotal/$grandOrders : 0); ?></div><div class="stat-label">Avg Order Value</div></div></div>
  </div>

  <div class="card p-3 fade-in-up">
    <h6 class="fw-bold mb-3">Sales by Period (<?php echo ucfirst($filter); ?>)</h6>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead><tr><th>Period</th><th>Orders</th><th>Total Sales</th></tr></thead>
        <tbody>
        <?php foreach ($sales as $s): ?>
          <tr><td><?php echo clean($s['period']); ?></td><td><?php echo $s['order_count']; ?></td><td><?php echo money($s['total']); ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$sales): ?><tr><td colspan="3" class="text-center text-muted py-4">No sales data yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
