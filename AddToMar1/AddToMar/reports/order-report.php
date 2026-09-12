<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Order Report';

$dateFrom = clean($_GET['date_from'] ?? '');
$dateTo = clean($_GET['date_to'] ?? '');
$status = clean($_GET['status'] ?? '');

$where = ['1=1']; $params = [];
if ($dateFrom) { $where[] = 'DATE(o.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(o.created_at) <= ?'; $params[] = $dateTo; }
if ($status)   { $where[] = 'o.status = ?'; $params[] = $status; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT o.*, u.fullname FROM orders o JOIN users u ON u.id = o.customer_id WHERE $whereSql ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();
$total = array_sum(array_column($orders, 'total_amount'));

$hero_badge = 'Reports';
$hero_title = 'Order Report';
$hero_desc = 'Filter orders by date range and status.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 fade-in-up">
    <div class="btn-group flex-wrap">
      <a href="inventory-report.php" class="btn btn-sm btn-outline-soft">Inventory Report</a>
      <a href="sales-report.php" class="btn btn-sm btn-outline-soft">Sales Report</a>
      <a href="order-report.php" class="btn btn-sm btn-gradient">Order Report</a>
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-gradient"><i class="bi bi-printer me-1"></i>Print</button>
  </div>

  <form method="GET" class="card p-3 mb-3 fade-in-up">
    <div class="row g-2">
      <div class="col-md-3"><label class="form-label small">From</label><input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>"></div>
      <div class="col-md-3"><label class="form-label small">To</label><input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>"></div>
      <div class="col-md-4">
        <label class="form-label small">Status</label>
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <?php foreach (['Pending','Approved','Ready For Pickup','Completed','Cancelled'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $status===$s?'selected':''; ?>><?php echo $s; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-gradient w-100">Filter</button></div>
    </div>
  </form>

  <div class="card p-3 fade-in-up">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="fw-bold mb-0">Results (<?php echo count($orders); ?> orders)</h6>
      <strong>Total: <?php echo money($total); ?></strong>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-semibold"><?php echo clean($o['order_number']); ?></td>
            <td><?php echo clean($o['fullname']); ?></td>
            <td><?php echo money($o['total_amount']); ?></td>
            <td><span class="<?php echo status_badge_class($o['status']); ?>"><?php echo $o['status']; ?></span></td>
            <td class="small text-muted"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="5" class="text-center text-muted py-4">No orders found for this filter.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
