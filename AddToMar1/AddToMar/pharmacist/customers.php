<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Customers';

$customers = $pdo->query("SELECT u.*,
  (SELECT COUNT(*) FROM orders o WHERE o.customer_id = u.id) AS order_count,
  (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.customer_id = u.id AND o.status != 'Cancelled') AS total_spent
  FROM users u WHERE u.role = 'customer' ORDER BY u.created_at DESC")->fetchAll();

$hero_badge = 'Customers';
$hero_title = 'Registered Customers';
$hero_desc = 'View customer accounts and their order activity.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <div class="card p-3 fade-in-up">
    <div class="table-responsive">
      <table class="table align-middle" id="custTable">
        <thead><tr><th>Name</th><th>Username</th><th>Contact</th><th>Email</th><th>Orders</th><th>Total Spent</th><th>Joined</th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td class="fw-semibold"><?php echo clean($c['fullname']); ?></td>
            <td>@<?php echo clean($c['username']); ?></td>
            <td><?php echo clean($c['contact'] ?: '—'); ?></td>
            <td><?php echo clean($c['email']); ?></td>
            <td><?php echo (int)$c['order_count']; ?></td>
            <td><?php echo money($c['total_spent']); ?></td>
            <td class="small text-muted"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php $extra_scripts = '<script>$(function(){ $("#custTable").DataTable({ order: [], pageLength: 10 }); });</script>';
include __DIR__ . '/../includes/footer.php'; ?>
