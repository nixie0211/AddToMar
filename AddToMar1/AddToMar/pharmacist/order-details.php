<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT o.*, u.fullname, u.contact, u.email FROM orders o JOIN users u ON u.id = o.customer_id WHERE o.id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) { header('Location: orders.php'); exit; }

$page_title = 'Order ' . $order['order_number'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['form_action'] ?? '';

    if ($action === 'verify_payment') {
        $decision = $_POST['decision']; // verified | rejected
        $remarks = clean($_POST['remarks'] ?? '');
        $pdo->prepare("UPDATE orders SET payment_status=?, payment_remarks=? WHERE id=?")->execute([$decision, $remarks, $id]);
        add_notification($pdo, $order['customer_id'], 'Payment ' . ucfirst($decision), "Your payment for order {$order['order_number']} was $decision." . ($remarks ? " Remarks: $remarks" : ''));
        $message = 'Payment status updated.';
    }

    if ($action === 'update_status') {
        $newStatus = $_POST['status'];
        $valid = ['Pending','Approved','Ready For Pickup','Completed','Cancelled'];
        if (in_array($newStatus, $valid)) {

            if ($newStatus === 'Approved' && $order['status'] !== 'Approved') {
                // Inventory automation: check & deduct stock
                $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $items->execute([$id]);
                $items = $items->fetchAll();

                $insufficient = [];
                foreach ($items as $it) {
                    $stock = $pdo->prepare("SELECT quantity, medicine_name FROM medicines WHERE id = ?");
                    $stock->execute([$it['medicine_id']]);
                    $row = $stock->fetch();
                    if (!$row || $row['quantity'] < $it['quantity']) $insufficient[] = $row['medicine_name'] ?? 'Unknown';
                }

                if ($insufficient) {
                    $message = 'error:Cannot approve order — insufficient stock for: ' . implode(', ', $insufficient);
                } else {
                    foreach ($items as $it) {
                        $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?")->execute([$it['quantity'], $it['medicine_id']]);
                    }
                    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                    add_notification($pdo, $order['customer_id'], 'Order Approved', "Your order {$order['order_number']} has been approved.");
                    $message = 'Order approved and stock has been deducted.';
                }
            } else {
                $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                $titles = ['Ready For Pickup' => 'Order Ready for Pickup', 'Completed' => 'Order Completed', 'Cancelled' => 'Order Cancelled'];
                if (isset($titles[$newStatus])) {
                    add_notification($pdo, $order['customer_id'], $titles[$newStatus], "Your order {$order['order_number']} is now $newStatus.");
                }
                $message = "Order status updated to $newStatus.";
            }
        }
    }

    // refresh order data
    $stmt->execute([$id]);
    $order = $stmt->fetch();
}

$items = $pdo->prepare("SELECT oi.*, m.medicine_name, m.image FROM order_items oi JOIN medicines m ON m.id = oi.medicine_id WHERE oi.order_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

$hero_badge = 'Order Details';
$hero_title = clean($order['order_number']);
$hero_desc = 'Customer: ' . clean($order['fullname']);
$hero_actions_html = '<a href="orders.php" class="btn-hero-outline"><i class="bi bi-arrow-left"></i> Back to Orders</a>';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <?php if ($message): ?>
    <?php $isErr = str_starts_with($message, 'error:'); ?>
    <div class="alert <?php echo $isErr ? 'alert-danger' : 'alert-success'; ?> fade-in-up"><?php echo clean($isErr ? substr($message,6) : $message); ?></div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3 fade-in-up">
    <div>
      <h4 class="section-title">Order <?php echo clean($order['order_number']); ?></h4>
      <p class="section-sub">Placed by <?php echo clean($order['fullname']); ?> on <?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></p>
    </div>
    <span class="<?php echo status_badge_class($order['status']); ?> fs-6"><?php echo $order['status']; ?></span>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card p-3 mb-3 fade-in-up">
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

      <div class="card p-3 fade-in-up">
        <h6 class="fw-bold mb-3">Payment Verification</h6>
        <?php if (($order['payment_method'] ?? 'manual') === 'paymongo'): ?>
          <div class="alert alert-success small mb-3">
            <i class="bi bi-check-circle me-1"></i>
            Paid via PayMongo (down payment verified automatically).
            <?php if (!empty($order['paymongo_checkout_id'])): ?>
              <br><span class="text-muted">Ref: <?php echo clean($order['paymongo_checkout_id']); ?></span>
            <?php endif; ?>
          </div>
          <p class="small text-muted mb-0">Balance on pickup: <strong><?php echo money(max(0, $order['total_amount'] - $order['down_payment'])); ?></strong></p>
        <?php elseif ($order['payment_proof']): ?>
          <a href="<?php echo UPLOAD_PAY_URL . clean($order['payment_proof']); ?>" target="_blank">
            <img src="<?php echo UPLOAD_PAY_URL . clean($order['payment_proof']); ?>" class="rounded-xl mb-3" style="max-height:260px;" onerror="this.replaceWith(Object.assign(document.createElement('p'),{className:'text-muted small',textContent:'Uploaded file: <?php echo clean($order['payment_proof']); ?> (click to view)'}))">
          </a>

          <form method="POST" class="row g-2">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="form_action" value="verify_payment">
            <div class="col-12">
              <label class="form-label">Remarks (optional)</label>
              <input type="text" name="remarks" class="form-control" placeholder="e.g. Amount mismatch, unclear receipt...">
            </div>
            <div class="col-6">
              <button type="submit" name="decision" value="verified" class="btn btn-gradient w-100">Approve Payment</button>
            </div>
            <div class="col-6">
              <button type="submit" name="decision" value="rejected" class="btn btn-outline-soft text-danger w-100">Reject Payment</button>
            </div>
          </form>
        <?php else: ?>
          <p class="text-muted small">No payment proof uploaded.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-4 mb-3 fade-in-up">
        <h6 class="fw-bold mb-3">Customer Info</h6>
        <p class="small mb-1"><i class="bi bi-person me-2"></i><?php echo clean($order['fullname']); ?></p>
        <p class="small mb-1"><i class="bi bi-telephone me-2"></i><?php echo clean($order['contact'] ?: '—'); ?></p>
        <p class="small mb-0"><i class="bi bi-envelope me-2"></i><?php echo clean($order['email']); ?></p>
      </div>

      <div class="card p-4 mb-3 fade-in-up">
        <h6 class="fw-bold mb-3">Pickup Details</h6>
        <p class="mb-1 small"><i class="bi bi-calendar-event me-2"></i><?php echo date('F d, Y', strtotime($order['pickup_date'])); ?></p>
        <p class="mb-0 small"><i class="bi bi-clock me-2"></i><?php echo date('g:i A', strtotime($order['pickup_time'])); ?></p>
      </div>

      <div class="card p-4 fade-in-up">
        <h6 class="fw-bold mb-3">Update Order Status</h6>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <input type="hidden" name="form_action" value="update_status">
          <select name="status" class="form-select mb-3">
            <?php foreach (['Pending','Approved','Ready For Pickup','Completed','Cancelled'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $order['status']===$s?'selected':''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-gradient-blue w-100">Update Status</button>
        </form>
        <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Approving deducts stock automatically. If stock is insufficient, approval will be blocked.</p>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
