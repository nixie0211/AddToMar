<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');
$u = current_user();
$page_title = 'My Orders';

$stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$u['id']]);
$orders = $stmt->fetchAll();

$hero_badge = 'Orders';
$hero_title = 'My Orders';
$hero_desc = 'Track the status of all your orders.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <div class="card p-3 fade-in-up">
    <div class="table-responsive">
      <table class="table align-middle" id="ordersTable">
        <thead><tr><th>Order #</th><th>Date</th><th>Pickup</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-semibold"><?php echo clean($o['order_number']); ?></td>
            <td><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
            <td><?php echo $o['pickup_date'] ? date('M d, Y', strtotime($o['pickup_date'])) . ' ' . date('g:i A', strtotime($o['pickup_time'])) : '—'; ?></td>
            <td><?php echo money($o['total_amount']); ?></td>
            <td><span class="<?php echo status_badge_class($o['status']); ?>"><?php echo $o['status']; ?></span></td>
            <td><a href="order-details.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-soft">View Details</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php $extra_scripts = '<script>$(function(){ $("#ordersTable").DataTable({ order: [], pageLength: 10 }); });</script>';
include __DIR__ . '/../includes/footer.php'; ?>
