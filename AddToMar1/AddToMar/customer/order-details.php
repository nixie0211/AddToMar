<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');
$u = current_user();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
$stmt->execute([$id, $u['id']]);
$order = $stmt->fetch();
if (!$order) { header('Location: orders.php'); exit; }

$page_title = 'Order ' . $order['order_number'];

$items = $pdo->prepare("SELECT oi.*, m.medicine_name, m.image FROM order_items oi
                         JOIN medicines m ON m.id = oi.medicine_id WHERE oi.order_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

$steps = ['Pending', 'Approved', 'Ready For Pickup', 'Completed'];
$currentIdx = array_search($order['status'], $steps);

$hero_badge = 'Order Details';
$hero_title = clean($order['order_number']);
$hero_desc = 'Track your order status and pickup details.';
$hero_actions_html = '<a href="orders.php" class="btn-hero-outline"><i class="bi bi-arrow-left"></i> Back to Orders</a>';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <?php if (isset($_GET['placed'])): ?>
    <div class="alert alert-success fade-in-up"><i class="bi bi-check-circle me-1"></i> Your order has been placed successfully and is now awaiting approval!</div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3 fade-in-up">
    <div>
      <h4 class="section-title">Order <?php echo clean($order['order_number']); ?></h4>
      <p class="section-sub">Placed on <?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></p>
    </div>
    <span class="<?php echo status_badge_class($order['status']); ?> fs-6"><?php echo $order['status']; ?></span>
  </div>

  <?php if ($order['status'] !== 'Cancelled'): ?>
  <div class="card p-4 mb-3 fade-in-up">
    <div class="d-flex justify-content-between position-relative">
      <?php foreach ($steps as $i => $step): ?>
        <div class="text-center flex-fill">
          <div class="mx-auto mb-1 d-flex align-items-center justify-content-center rounded-circle"
               style="width:34px;height:34px; background:<?php echo $i <= $currentIdx ? 'var(--gradient-primary)' : '#E2E8F0'; ?>; color:#fff;">
            <i class="bi <?php echo $i < $currentIdx ? 'bi-check-lg' : 'bi-circle-fill'; ?>" style="font-size:<?php echo $i < $currentIdx ? '16' : '8'; ?>px;"></i>
          </div>
          <div class="small <?php echo $i <= $currentIdx ? 'fw-semibold' : 'text-muted'; ?>"><?php echo $step; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
    <div class="alert alert-danger fade-in-up">This order has been cancelled.</div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card p-3 fade-in-up">
        <h6 class="fw-bold mb-3">Items</h6>
        <table class="table align-middle">
          <thead><tr><th>Medicine</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td class="d-flex align-items-center gap-2">
                <img src="<?php echo UPLOAD_MED_URL . clean($it['image']); ?>" onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-medicine.png'" style="width:36px;height:36px;object-fit:cover;border-radius:8px;">
                <?php echo clean($it['medicine_name']); ?>
              </td>
              <td><?php echo $it['quantity']; ?></td>
              <td><?php echo money($it['price']); ?></td>
              <td><?php echo money($it['price'] * $it['quantity']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-4 mb-3 fade-in-up">
        <h6 class="fw-bold mb-3">Order Summary</h6>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Amount</span><strong><?php echo money($order['total_amount']); ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Paid Online (Down Payment)</span><strong><?php echo money($order['down_payment']); ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Balance on Pickup</span><strong><?php echo money(max(0, $order['total_amount'] - $order['down_payment'])); ?></strong></div>
        <?php if (($order['payment_method'] ?? 'manual') === 'paymongo'): ?>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Payment Method</span><strong>PayMongo</strong></div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Payment Status</span>
          <span class="<?php echo $order['payment_status']==='verified'?'badge-completed':($order['payment_status']==='rejected'?'badge-cancelled':'badge-pending'); ?>"><?php echo ucfirst($order['payment_status']); ?></span>
        </div>
        <?php if ($order['payment_remarks']): ?>
          <div class="alert alert-warning small mt-2 mb-0"><?php echo clean($order['payment_remarks']); ?></div>
        <?php endif; ?>
      </div>

      <div class="card p-4 fade-in-up">
        <h6 class="fw-bold mb-3">Pickup Details</h6>
        <p class="mb-1 small"><i class="bi bi-calendar-event me-2"></i><?php echo date('F d, Y', strtotime($order['pickup_date'])); ?></p>
        <p class="mb-0 small"><i class="bi bi-clock me-2"></i><?php echo date('g:i A', strtotime($order['pickup_time'])); ?></p>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
