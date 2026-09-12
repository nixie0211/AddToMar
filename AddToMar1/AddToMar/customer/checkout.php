<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paymongo.php';
require_role('customer');
$u = current_user();
$page_title = 'Checkout';

$stmt = $pdo->prepare("SELECT c.id AS cart_id, c.quantity, m.* FROM cart c
                        JOIN medicines m ON m.id = c.medicine_id
                        WHERE c.customer_id = ?");
$stmt->execute([$u['id']]);
$items = $stmt->fetchAll();

if (!$items) { header('Location: cart.php'); exit; }

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$downPct = (int)($settings['down_payment_percent'] ?? 50);
$total = 0;
foreach ($items as $it) { $total += $it['price'] * $it['quantity']; }
$downPayment = round($total * ($downPct / 100), 2);
$balanceOnPickup = round($total - $downPayment, 2);

$error = '';
$paymongoReady = paymongo_is_configured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please try again.';
    } else {
        $pickup_date = clean($_POST['pickup_date'] ?? '');
        $pickup_time = clean($_POST['pickup_time'] ?? '');

        if (!$pickup_date || !$pickup_time) {
            $error = 'Please select a pickup date and time.';
        } else {
            $insufficient = [];
            foreach ($items as $it) {
                $check = $pdo->prepare("SELECT quantity FROM medicines WHERE id = ?");
                $check->execute([$it['id']]);
                $current = (int)$check->fetch()['quantity'];
                if ($current < $it['quantity']) $insufficient[] = $it['medicine_name'];
            }

            if ($insufficient) {
                $error = 'Insufficient stock for: ' . implode(', ', $insufficient);
            } elseif ($paymongoReady) {
                $cartItems = array_map(static function ($it) {
                    return [
                        'medicine_id' => (int)$it['id'],
                        'quantity'    => (int)$it['quantity'],
                        'price'       => (float)$it['price'],
                    ];
                }, $items);

                $successUrl = app_url('customer/payment-return.php?session_id={CHECKOUT_SESSION_ID}');
                $cancelUrl = app_url('customer/checkout.php?cancelled=1');
                $description = sprintf('AddToMar down payment (%d%%) for %s', $downPct, $u['fullname']);

                $session = paymongo_create_checkout_session($downPayment, $description, $successUrl, $cancelUrl);
                if (!$session['ok']) {
                    $error = 'Could not start online payment: ' . $session['error'];
                } else {
                    $sessionId = $session['data']['data']['id'] ?? '';
                    $checkoutUrl = $session['data']['data']['attributes']['checkout_url'] ?? '';

                    if (!$sessionId || !$checkoutUrl) {
                        $error = 'PayMongo did not return a checkout link. Please try again.';
                    } else {
                        $_SESSION['pending_checkout'] = [
                            'customer_id'        => $u['id'],
                            'pickup_date'        => $pickup_date,
                            'pickup_time'        => $pickup_time,
                            'total'              => $total,
                            'down_payment'       => $downPayment,
                            'paymongo_session_id'=> $sessionId,
                            'items'              => $cartItems,
                        ];
                        header('Location: ' . $checkoutUrl);
                        exit;
                    }
                }
            } elseif (empty($_FILES['payment_proof']['name'])) {
                $error = 'Please upload your payment proof.';
            } else {
                $file = $_FILES['payment_proof'];
                $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                if (!in_array($file['type'], $allowed) || $file['size'] > 5 * 1024 * 1024) {
                    $error = 'Invalid file. Please upload a JPG, PNG, or PDF under 5MB.';
                } else {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'PAY-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($file['tmp_name'], UPLOAD_PAY_PATH . $filename);

                    try {
                        $pdo->beginTransaction();
                        $order_number = generate_order_number($pdo);
                        $ins = $pdo->prepare("INSERT INTO orders (order_number, customer_id, total_amount, down_payment, payment_proof, payment_method, pickup_date, pickup_time, status)
                                               VALUES (?, ?, ?, ?, ?, 'manual', ?, ?, 'Pending')");
                        $ins->execute([$order_number, $u['id'], $total, $downPayment, $filename, $pickup_date, $pickup_time]);
                        $order_id = $pdo->lastInsertId();

                        $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
                        foreach ($items as $it) {
                            $itemStmt->execute([$order_id, $it['id'], $it['quantity'], $it['price']]);
                        }

                        $pdo->prepare("DELETE FROM cart WHERE customer_id = ?")->execute([$u['id']]);
                        $pdo->commit();

                        add_notification($pdo, $u['id'], 'Order Submitted', "Your order $order_number has been submitted and is awaiting approval.");
                        notify_all_pharmacists($pdo, 'New Order', "New order $order_number was placed by {$u['fullname']}.");

                        header('Location: order-details.php?id=' . $order_id . '&placed=1');
                        exit;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Something went wrong while placing your order. Please try again.';
                    }
                }
            }
        }
    }
}

$hero_badge = 'Checkout';
$hero_title = 'Checkout';
$hero_desc = $paymongoReady
    ? 'Pay 50% online now via PayMongo (GCash / Card). Pay the remaining balance when you pick up your order.'
    : 'Confirm your pickup schedule and upload proof of down payment.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <?php if (isset($_GET['cancelled'])): ?>
    <div class="alert alert-warning fade-in-up">Payment was cancelled. You can try again when ready.</div>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger fade-in-up"><?php echo clean($error); ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <form method="POST" enctype="multipart/form-data" class="card p-4 fade-in-up">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <h6 class="fw-bold mb-3">Pickup Schedule</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label">Pickup Date</label>
            <input type="date" name="pickup_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Pickup Time</label>
            <input type="time" name="pickup_time" class="form-control" required>
          </div>
        </div>

        <?php if ($paymongoReady): ?>
          <h6 class="fw-bold mb-2">Online Down Payment (PayMongo)</h6>
          <div class="alert alert-info small mb-3">
            You will pay <strong><?php echo money($downPayment); ?></strong> now (<?php echo $downPct; ?>% of your order).
            The remaining <strong><?php echo money($balanceOnPickup); ?></strong> is due at pickup.
          </div>
          <p class="section-sub mb-3">Supported: GCash, Maya, GrabPay, and debit/credit cards (test mode).</p>
          <button type="submit" class="btn btn-gradient w-100 py-2">
            <i class="bi bi-credit-card me-1"></i> Pay <?php echo money($downPayment); ?> Now
          </button>
        <?php else: ?>
          <h6 class="fw-bold mb-2">Upload Payment Proof</h6>
          <p class="section-sub mb-2">Please pay the required down payment of <strong><?php echo money($downPayment); ?></strong> via GCash / Bank Transfer and upload your receipt.</p>
          <div class="mb-2">
            <input type="file" name="payment_proof" class="form-control image-upload-input" accept="image/*,.pdf" data-preview="#proofPreview" required>
          </div>
          <img id="proofPreview" class="rounded-xl mb-3" style="max-height:180px; display:none;" onload="this.style.display='block';">
          <button type="submit" class="btn btn-gradient w-100 py-2">Place Order</button>
        <?php endif; ?>
      </form>
    </div>

    <div class="col-lg-5">
      <div class="card p-4 fade-in-up">
        <h6 class="fw-bold mb-3">Order Summary</h6>
        <?php foreach ($items as $it): ?>
          <div class="d-flex justify-content-between small mb-2">
            <span><?php echo clean($it['medicine_name']); ?> &times; <?php echo $it['quantity']; ?></span>
            <span><?php echo money($it['price'] * $it['quantity']); ?></span>
          </div>
        <?php endforeach; ?>
        <hr>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Amount</span><strong><?php echo money($total); ?></strong></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Pay Now (<?php echo $downPct; ?>%)</span><strong class="text-primary-em"><?php echo money($downPayment); ?></strong></div>
        <div class="d-flex justify-content-between"><span class="text-muted">Balance on Pickup</span><strong><?php echo money($balanceOnPickup); ?></strong></div>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
