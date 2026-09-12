<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');
$u = current_user();
$page_title = 'Dashboard';
$hero_layout = true;
$body_class = 'hero-dashboard';

$totalOrders = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE customer_id = ?");
$totalOrders->execute([$u['id']]);
$totalOrders = (int)$totalOrders->fetch()['c'];

$pendingOrders = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE customer_id = ? AND status = 'Pending'");
$pendingOrders->execute([$u['id']]);
$pendingOrders = (int)$pendingOrders->fetch()['c'];

$completedOrders = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE customer_id = ? AND status = 'Completed'");
$completedOrders->execute([$u['id']]);
$completedOrders = (int)$completedOrders->fetch()['c'];

$totalSpent = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) s FROM orders WHERE customer_id = ? AND status != 'Cancelled'");
$totalSpent->execute([$u['id']]);
$totalSpent = (float)$totalSpent->fetch()['s'];

$recent = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5");
$recent->execute([$u['id']]);
$recent = $recent->fetchAll();

$featured = $pdo->query("SELECT * FROM medicines WHERE status='active' AND quantity > 0 ORDER BY created_at DESC LIMIT 4")->fetchAll();

$firstName = clean(explode(' ', $u['fullname'])[0]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="hero-dashboard-wrap">
  <section class="dashboard-hero">
    <div class="dashboard-hero-inner fade-in-up">
      <div class="hero-badge"><i class="bi bi-heart-pulse"></i> Welcome back, <?php echo $firstName; ?></div>
      <h1 class="hero-title">
        Your Health, Managed Through Modern
        <span class="accent">Pharmacy Services</span>
      </h1>
      <p class="hero-desc">
        Browse medicines, track your orders, manage your cart, and pick up prescriptions — all in one convenient place.
      </p>
      <div class="hero-actions">
        <a href="medicine-finder.php" class="btn-hero-primary">Find Medicines <i class="bi bi-geo-alt"></i></a>
        <a href="catalog.php" class="btn-hero-outline">Browse Catalog <i class="bi bi-arrow-right"></i></a>
        <a href="orders.php" class="btn-hero-outline"><i class="bi bi-bag-check"></i> My Orders</a>
      </div>

      <div class="hero-stats-card fade-in-up">
        <div class="hero-stat-item">
          <div class="hero-stat-value"><?php echo $totalOrders; ?></div>
          <div class="hero-stat-label">Total Orders</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-value"><?php echo $pendingOrders; ?></div>
          <div class="hero-stat-label">Pending Orders</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-value"><?php echo $completedOrders; ?></div>
          <div class="hero-stat-label">Completed Orders</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-value"><?php echo money($totalSpent); ?></div>
          <div class="hero-stat-label">Total Spent</div>
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

    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card p-3 fade-in-up">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">Recent Orders</h6>
            <a href="orders.php" class="small">View all</a>
          </div>
          <?php if (!$recent): ?>
            <p class="text-muted small py-4 text-center">You haven't placed any orders yet.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead><tr><th>Order #</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($recent as $o): ?>
                <tr>
                  <td class="fw-semibold"><?php echo clean($o['order_number']); ?></td>
                  <td class="small text-muted"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                  <td><?php echo money($o['total_amount']); ?></td>
                  <td><span class="<?php echo status_badge_class($o['status']); ?>"><?php echo $o['status']; ?></span></td>
                  <td><a href="order-details.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-soft">View</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card p-3 fade-in-up">
          <h6 class="fw-bold mb-3">Newly Added Medicines</h6>
          <?php foreach ($featured as $m): ?>
            <div class="d-flex align-items-center gap-3 mb-3">
              <img src="<?php echo UPLOAD_MED_URL . clean($m['image']); ?>" onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-medicine.png'" style="width:48px;height:48px;object-fit:cover;border-radius:10px;">
              <div class="flex-grow-1">
                <div class="fw-semibold small"><?php echo clean($m['medicine_name']); ?></div>
                <div class="text-muted small"><?php echo clean($m['category']); ?></div>
              </div>
              <div class="fw-bold small"><?php echo money($m['price']); ?></div>
            </div>
          <?php endforeach; ?>
          <a href="catalog.php" class="btn btn-outline-soft w-100 mt-1">See All Medicines</a>
        </div>
      </div>
    </div>

  </main>
</div>
<?php include __DIR__ . '/../includes/hero-mobile-nav.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
