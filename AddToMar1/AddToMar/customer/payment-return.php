<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/paymongo.php';
require_role('customer');

$u = current_user();
$page_title = 'Payment';
$error = '';
$pending = $_SESSION['pending_checkout'] ?? null;
$sessionId = clean($_GET['session_id'] ?? '');

if (!$pending || (int)($pending['customer_id'] ?? 0) !== (int)$u['id']) {
    header('Location: checkout.php');
    exit;
}

if (!$sessionId || $sessionId !== ($pending['paymongo_session_id'] ?? '')) {
    $error = 'Invalid payment session. Please try checkout again.';
} else {
    $result = paymongo_get_checkout_session($sessionId);
    if (!$result['ok']) {
        $error = $result['error'];
    } elseif (!paymongo_checkout_is_paid($result['data'])) {
        $error = 'Payment was not completed. Please try again.';
        } else {
            $attrs = $result['data']['data']['attributes'] ?? [];
            $expectedCentavos = paymongo_to_centavos((float)$pending['down_payment']);
            $paidCentavos = (int)($attrs['payment_intent']['attributes']['amount'] ?? 0);
            if (!$paidCentavos) {
                foreach ($attrs['line_items'] ?? [] as $li) {
                    $paidCentavos += (int)($li['amount'] ?? 0) * (int)($li['quantity'] ?? 1);
                }
            }
            if ($paidCentavos > 0 && abs($paidCentavos - $expectedCentavos) > 1) {
            $error = 'Payment amount mismatch. Please contact the pharmacy.';
        } else {
            try {
                $pdo->beginTransaction();

                foreach ($pending['items'] as $it) {
                    $check = $pdo->prepare('SELECT quantity, medicine_name FROM medicines WHERE id = ? FOR UPDATE');
                    $check->execute([(int)$it['medicine_id']]);
                    $med = $check->fetch();
                    if (!$med || (int)$med['quantity'] < (int)$it['quantity']) {
                        throw new RuntimeException('Insufficient stock for: ' . ($med['medicine_name'] ?? 'item'));
                    }
                }

                $order_number = generate_order_number($pdo);
                $ins = $pdo->prepare('INSERT INTO orders (order_number, customer_id, total_amount, down_payment, payment_proof, paymongo_checkout_id, payment_method, payment_status, pickup_date, pickup_time, status)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([
                    $order_number,
                    $u['id'],
                    $pending['total'],
                    $pending['down_payment'],
                    null,
                    $sessionId,
                    'paymongo',
                    'verified',
                    $pending['pickup_date'],
                    $pending['pickup_time'],
                    'Pending',
                ]);
                $order_id = (int)$pdo->lastInsertId();

                $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)');
                foreach ($pending['items'] as $it) {
                    $itemStmt->execute([$order_id, (int)$it['medicine_id'], (int)$it['quantity'], (float)$it['price']]);
                }

                $pdo->prepare('DELETE FROM cart WHERE customer_id = ?')->execute([$u['id']]);
                $pdo->commit();

                unset($_SESSION['pending_checkout']);

                add_notification($pdo, $u['id'], 'Order Submitted', "Your order $order_number has been submitted. Down payment received via PayMongo.");
                notify_all_pharmacists($pdo, 'New Order', "New order $order_number was placed by {$u['fullname']} (PayMongo paid).");

                header('Location: order-details.php?id=' . $order_id . '&placed=1');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Could not create your order after payment. Please contact the pharmacy with your payment reference.';
            }
        }
    }
}

$hero_badge = 'Payment';
$hero_title = 'Payment Result';
$hero_desc = 'Confirming your PayMongo payment.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <?php if ($error): ?>
    <div class="alert alert-danger fade-in-up"><?php echo clean($error); ?></div>
    <a href="checkout.php" class="btn btn-gradient">Back to Checkout</a>
  <?php endif; ?>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
