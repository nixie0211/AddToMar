<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Orders';

$statusFilter = clean($_GET['status'] ?? '');
$where = '';
$params = [];
if ($statusFilter) { $where = 'WHERE o.status = ?'; $params[] = $statusFilter; }

$stmt = $pdo->prepare("SELECT o.*, u.fullname, u.contact FROM orders o JOIN users u ON u.id = o.customer_id $where ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$hero_badge = 'Orders';
$hero_title = 'Orders Management';
$hero_desc = 'Verify payments, approve orders, and update pickup status.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <div class="mb-3 fade-in-up">
    <div class="btn-group flex-wrap">
      <a href="orders.php" class="btn btn-sm <?php echo !$statusFilter?'btn-gradient':'btn-outline-soft'; ?>">All</a>
      <?php foreach (['Pending','Approved','Ready For Pickup','Completed','Cancelled'] as $s): ?>
        <a href="?status=<?php echo urlencode($s); ?>" class="btn btn-sm <?php echo $statusFilter===$s?'btn-gradient':'btn-outline-soft'; ?>"><?php echo $s; ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card p-3 fade-in-up">
    <div class="table-responsive">
      <table class="table align-middle" id="ordersTable">
        <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Pickup</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-semibold"><?php echo clean($o['order_number']); ?></td>
            <td><?php echo clean($o['fullname']); ?></td>
            <td><?php echo money($o['total_amount']); ?></td>
            <td><span class="<?php echo $o['payment_status']==='verified'?'badge-completed':($o['payment_status']==='rejected'?'badge-cancelled':'badge-pending'); ?>"><?php echo ucfirst($o['payment_status']); ?></span></td>
            <td class="small"><?php echo $o['pickup_date'] ? date('M d', strtotime($o['pickup_date'])) . ' ' . date('g:iA', strtotime($o['pickup_time'])) : '—'; ?></td>
            <td><span class="<?php echo status_badge_class($o['status']); ?>"><?php echo $o['status']; ?></span></td>
            <td class="small text-muted"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
            <td><a href="order-details.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-soft">Manage</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php $extra_scripts = '<script>$(function(){ $("#ordersTable").DataTable({ order: [], pageLength: 10 }); });</script>';
include __DIR__ . '/../includes/footer.php'; ?>
