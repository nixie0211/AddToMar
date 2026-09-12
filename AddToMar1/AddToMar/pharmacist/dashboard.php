<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Dashboard';
$hero_layout = true;
$body_class = 'hero-dashboard';

$totalMedicines = (int)$pdo->query("SELECT COUNT(*) c FROM medicines WHERE status='active'")->fetch()['c'];
$totalOrders = (int)$pdo->query("SELECT COUNT(*) c FROM orders")->fetch()['c'];
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE status='Pending'")->fetch()['c'];
$completedOrders = (int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE status='Completed'")->fetch()['c'];
$totalSales = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) s FROM orders WHERE status != 'Cancelled'")->fetch()['s'];
$lowStock = (int)$pdo->query("SELECT COUNT(*) c FROM medicines WHERE quantity > 0 AND quantity <= reorder_level AND status='active'")->fetch()['c'];
$outStock = (int)$pdo->query("SELECT COUNT(*) c FROM medicines WHERE quantity = 0 AND status='active'")->fetch()['c'];
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE role='customer'")->fetch()['c'];

$monthlySales = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, SUM(total_amount) total
                              FROM orders WHERE status != 'Cancelled'
                              GROUP BY ym ORDER BY ym DESC LIMIT 6")->fetchAll();
$monthlySales = array_reverse($monthlySales);

$statusBreakdown = $pdo->query("SELECT status, COUNT(*) c FROM orders GROUP BY status")->fetchAll();
$recentOrders = $pdo->query("SELECT o.*, u.fullname FROM orders o JOIN users u ON u.id = o.customer_id ORDER BY o.created_at DESC LIMIT 6")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="hero-dashboard-wrap">
  <section class="dashboard-hero">
    <div class="dashboard-hero-inner fade-in-up">
      <div class="hero-badge"><i class="bi bi-shield-check"></i> Pharmacist Control Panel</div>
      <h1 class="hero-title">
        Empowering Healthcare Through Modern
        <span class="accent">Pharmacy Management</span>
      </h1>
      <p class="hero-desc">
        Manage inventory, process orders, monitor sales performance, and serve customers efficiently — all from one modern dashboard.
      </p>
      <div class="hero-actions">
        <a href="orders.php" class="btn-hero-primary">Manage Orders <i class="bi bi-arrow-right"></i></a>
        <a href="inventory.php" class="btn-hero-outline"><i class="bi bi-boxes"></i> View Inventory</a>
      </div>

      <div class="hero-stats-card fade-in-up">
        <div class="hero-stat-item">
          <div class="hero-stat-value"><?php echo $totalMedicines; ?></div>
          <div class="hero-stat-label">Total Medicines</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-value"><?php echo $totalOrders; ?></div>
          <div class="hero-stat-label">Total Orders</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-value"><?php echo $pendingOrders; ?></div>
          <div class="hero-stat-label">Pending Orders</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-value"><?php echo money($totalSales); ?></div>
          <div class="hero-stat-label">Total Sales</div>
        </div>
      </div>
    </div>

    <div class="hero-wave">
      <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path fill="#ffffff" d="M0,40 C360,90 720,0 1080,40 C1260,60 1380,50 1440,40 L1440,80 L0,80 Z"/>
      </svg>
    </div>
  </section>

  <main class="hero-dashboard-main">

    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-card fade-in-up"><div class="stat-icon" style="background:#0F172A;"><i class="bi bi-check2-circle"></i></div>
          <div class="stat-value"><?php echo $completedOrders; ?></div><div class="stat-label">Completed Orders</div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card fade-in-up"><div class="stat-icon" style="background:#F59E0B;"><i class="bi bi-exclamation-triangle"></i></div>
          <div class="stat-value"><?php echo $lowStock; ?></div><div class="stat-label">Low Stock</div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card fade-in-up"><div class="stat-icon" style="background:#EF4444;"><i class="bi bi-x-octagon"></i></div>
          <div class="stat-value"><?php echo $outStock; ?></div><div class="stat-label">Out of Stock</div></div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card fade-in-up"><div class="stat-icon" style="background:var(--gradient-blue);"><i class="bi bi-people"></i></div>
          <div class="stat-value"><?php echo $totalCustomers; ?></div><div class="stat-label">Registered Customers</div></div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-7">
        <div class="card p-3 fade-in-up">
          <h6 class="fw-bold mb-3">Monthly Sales</h6>
          <canvas id="salesChart" height="140"></canvas>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card p-3 fade-in-up">
          <h6 class="fw-bold mb-3">Orders Analytics</h6>
          <canvas id="statusChart" height="140"></canvas>
        </div>
      </div>
    </div>

    <div class="card p-3 fade-in-up">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0">Recent Activities</h6>
        <a href="orders.php" class="small">View all orders</a>
      </div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td class="fw-semibold"><?php echo clean($o['order_number']); ?></td>
              <td><?php echo clean($o['fullname']); ?></td>
              <td><?php echo money($o['total_amount']); ?></td>
              <td><span class="<?php echo status_badge_class($o['status']); ?>"><?php echo $o['status']; ?></span></td>
              <td class="small text-muted"><?php echo date('M d, g:i A', strtotime($o['created_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
<?php include __DIR__ . '/../includes/hero-mobile-nav.php'; ?>

<?php
$labels = json_encode(array_map(fn($r) => date('M Y', strtotime($r['ym'] . '-01')), $monthlySales));
$data = json_encode(array_map(fn($r) => (float)$r['total'], $monthlySales));
$statusLabels = json_encode(array_column($statusBreakdown, 'status'));
$statusData = json_encode(array_map('intval', array_column($statusBreakdown, 'c')));

$extra_scripts = "<script>
new Chart(document.getElementById('salesChart'), {
  type: 'line',
  data: { labels: $labels, datasets: [{ label: 'Sales (₱)', data: $data, borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,.12)', fill: true, tension: .4 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: { labels: $statusLabels, datasets: [{ data: $statusData, backgroundColor: ['#F59E0B','#2563EB','#6366F1','#10B981','#EF4444'] }] },
  options: { plugins: { legend: { position: 'bottom' } } }
});
</script>";
include __DIR__ . '/../includes/footer.php'; ?>
