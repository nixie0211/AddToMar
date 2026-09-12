<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/customers.php';
require_once dirname(__DIR__) . '/includes/paymongo-http.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/pharmacies.php';
require_once __DIR__ . '/includes/receipt.php';
require_portal_auth('residence');

$intentId = trim((string) ($_GET['payment_intent_id'] ?? $_GET['id'] ?? ''));
$pending = $_SESSION['paymongo_pending'] ?? null;
$error = '';
$order = null;
$paymentMeta = [];
$emailed = false;

if ($intentId === '' && is_array($pending)) {
    $intentId = (string) ($pending['intent_id'] ?? '');
}

if ($intentId === '') {
    $error = 'Payment session was not found. Please try checkout again.';
} else {
    $result = paymongo_http('GET', 'payment_intents/' . rawurlencode($intentId));
    if (!$result['ok']) {
        $error = $result['error'] ?? 'Could not confirm PayMongo payment.';
    } else {
        $attrs = $result['data']['data']['attributes'] ?? [];
        $status = (string) ($attrs['status'] ?? '');
        $payments = $attrs['payments'] ?? [];
        $firstPayment = is_array($payments[0] ?? null) ? $payments[0] : [];
        $paymentAttrs = $firstPayment['attributes'] ?? [];
        $paidAtTs = (int) ($paymentAttrs['paid_at'] ?? time());
        $paymentMeta = [
            'intent_id' => $intentId,
            'payment_id' => (string) ($firstPayment['id'] ?? ''),
            'paid_at' => date('M j, Y g:i A', $paidAtTs > 0 ? $paidAtTs : time()),
        ];

        if ($status !== 'succeeded') {
            $error = $status === 'awaiting_next_action'
                ? 'Payment was not authorized. Please try again.'
                : 'Payment was not completed. Please try again.';
        } else {
            $existing = residence_find_order_by_intent($intentId);
            if ($existing) {
                $directory = residence_pharmacy_directory();
                $order = $existing;
                $order['pharmacy_name'] = $directory[$existing['pharmacy_id'] ?? '']['name'] ?? ($existing['pharmacy_name'] ?? 'Pharmacy');
                $order['payer_name'] = $existing['customer_name'] ?? ($pending['name'] ?? '');
                $order['receipt_email'] = $pending['email'] ?? '';
            } elseif (!is_array($pending) || ($pending['intent_id'] ?? '') !== $intentId) {
                $error = 'Payment succeeded, but the checkout session expired. Contact the pharmacy with reference ' . $intentId . '.';
            } else {
                $profile = residence_session_profile();
                if (!empty($pending['name'])) {
                    $profile['full_name'] = $pending['name'];
                }
                if (!empty($pending['email'])) {
                    $profile['email'] = $pending['email'];
                }
                if (!empty($pending['phone'])) {
                    $profile['contact_number'] = $pending['phone'];
                }

                $placed = residence_place_checkout_groups(
                    $profile,
                    residence_group_checkout_items(
                        is_array($pending['items'] ?? null) ? $pending['items'] : [],
                        (string) ($pending['pharmacy_id'] ?? '')
                    ),
                    [
                        'pickup_date' => (string) $pending['pickup_date'],
                        'pickup_time' => (string) $pending['pickup_time'],
                        'payment_method' => 'paymongo_card',
                        'prescription_path' => $pending['prescription_path'] ?? null,
                        'paymongo_intent_id' => $intentId,
                        'receipt_email' => (string) $pending['email'],
                    ],
                    is_array($pending['prescriptions'] ?? null) ? $pending['prescriptions'] : []
                );

                if (!$placed['ok']) {
                    $error = $placed['error'] ?? 'Payment succeeded but the order could not be saved.';
                } else {
                    $order = $placed['order'];
                    $order['payer_name'] = $pending['name'] ?? ($order['payer_name'] ?? '');
                    $order['receipt_email'] = $pending['email'] ?? '';
                    unset($_SESSION['paymongo_pending']);
                }
            }
        }
    }
}

$receiptHtml = '';
$mailError = '';
if ($order && $error === '') {
    $gmailTo = residence_receipt_gmail(is_array($pending) ? (string) ($pending['email'] ?? '') : (string) ($order['receipt_email'] ?? ''));
    $order['receipt_email'] = $gmailTo !== '' ? $gmailTo : (string) ($order['receipt_email'] ?? '');
    $receiptHtml = residence_receipt_html($order, $paymentMeta);
    residence_save_receipt_file((string) ($order['order_number'] ?? 'receipt'), $receiptHtml);
    $mail = residence_email_receipt(
        (string) $order['receipt_email'],
        'AddToMar payment receipt ' . ($order['order_number'] ?? ''),
        $receiptHtml
    );
    $emailed = !empty($mail['ok']);
    $mailError = (string) ($mail['error'] ?? '');
    $_SESSION['paymongo_last_receipt'] = [
        'order' => $order,
        'payment' => $paymentMeta,
        'emailed' => $emailed,
    ];
}

$homeUrl = app_url('residence/') . '?paid=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment receipt — AddToMar</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --green:#1f6f4a; --ink:#163028; --muted:#5d746b; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; background:#eef4f1; color:var(--ink); font-family:Inter,sans-serif; }
    .wrap { max-width:640px; margin:0 auto; padding:28px 16px 48px; }
    .msg { padding:14px 16px; border-radius:12px; background:#fff; border:1px solid #d7e6e0; }
    .msg.error { border-color:#f0c2c2; background:#fff6f6; }
    .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; }
    .btn { appearance:none; border:0; border-radius:10px; padding:12px 16px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; }
    .btn-primary { background:var(--green); color:#fff; }
    .btn-ghost { background:#fff; color:var(--green); border:1px solid #cfe4d8; }
    .note { margin-top:12px; color:var(--muted); font-size:13px; }
    .receipt-frame { background:#fff; border-radius:16px; overflow:hidden; border:1px solid #d7e6e0; }
    iframe { width:100%; min-height:720px; border:0; background:#fff; }
    @media print { .actions, .note { display:none; } body { background:#fff; } .wrap { padding:0; max-width:none; } }
  </style>
</head>
<body>
  <div class="wrap">
    <?php if ($error !== ''): ?>
      <div class="msg error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="actions"><a class="btn btn-primary" href="<?= htmlspecialchars(app_url('residence/'), ENT_QUOTES, 'UTF-8') ?>">Back to checkout</a></div>
    <?php else: ?>
      <div class="actions">
        <button type="button" class="btn btn-primary" onclick="document.querySelector('iframe').contentWindow.print()">Print receipt</button>
        <a class="btn btn-ghost" href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">Continue to tracking</a>
      </div>
      <p class="note"><?php
        if ($emailed) {
            echo 'Receipt sent to <strong>' . htmlspecialchars((string) $order['receipt_email'], ENT_QUOTES, 'UTF-8') . '</strong>.';
        } else {
            echo 'Receipt was generated, but email was not sent' . ($mailError !== '' ? ': ' . htmlspecialchars($mailError, ENT_QUOTES, 'UTF-8') : '.') . ' Print or save this page.';
        }
      ?></p>
      <div class="receipt-frame"><iframe title="Payment receipt" srcdoc="<?= htmlspecialchars($receiptHtml, ENT_QUOTES, 'UTF-8') ?>"></iframe></div>
    <?php endif; ?>
  </div>
  <?php if ($order && $error === ''): ?>
  <script>
    sessionStorage.setItem('residence_last_order', <?= json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
    sessionStorage.setItem('residence_paid_clear', '1');
  </script>
  <?php endif; ?>
</body>
</html>
