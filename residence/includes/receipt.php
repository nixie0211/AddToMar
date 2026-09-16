<?php

declare(strict_types=1);

function residence_format_money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function residence_receipt_html(array $order, array $payment = []): string
{
    $orderNumber = htmlspecialchars((string) ($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $pharmacy = htmlspecialchars((string) ($order['pharmacy_name'] ?? 'Pharmacy'), ENT_QUOTES, 'UTF-8');
    $customer = htmlspecialchars((string) ($order['customer_name'] ?? $order['payer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars((string) ($order['receipt_email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $intentId = htmlspecialchars((string) ($order['paymongo_intent_id'] ?? $payment['intent_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $paymentId = htmlspecialchars((string) ($payment['payment_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $paidAt = htmlspecialchars((string) ($payment['paid_at'] ?? date('M j, Y g:i A')), ENT_QUOTES, 'UTF-8');
    $total = residence_format_money((float) ($order['total_amount'] ?? 0));
    $down = residence_format_money((float) ($order['down_payment'] ?? 0));
    $balance = residence_format_money((float) ($order['total_amount'] ?? 0) - (float) ($order['down_payment'] ?? 0));
    $pickup = htmlspecialchars(trim((string) ($order['pickup_date'] ?? '') . ' ' . (string) ($order['pickup_time'] ?? '')), ENT_QUOTES, 'UTF-8');

    $itemSubtotal = 0.0;
    $lines = '';
    foreach ($order['items'] ?? [] as $item) {
        $name = htmlspecialchars((string) ($item['medicine_name'] ?? $item['name'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
        $qty = (int) ($item['quantity'] ?? 1);
        $lineAmount = (float) ($item['unit_price'] ?? $item['price'] ?? 0) * $qty;
        $itemSubtotal += $lineAmount;
        $lineTotal = residence_format_money($lineAmount);
        $lines .= '<tr><td>' . $name . ' × ' . $qty . '</td><td style="text-align:right">' . $lineTotal . '</td></tr>';
    }
    $vatAmount = (float) ($order['vat'] ?? 0);
    if ($vatAmount <= 0) {
        $priced = function_exists('residence_apply_vat') ? residence_apply_vat($itemSubtotal) : ['subtotal' => $itemSubtotal, 'vat' => round($itemSubtotal * 0.15, 2), 'total' => round($itemSubtotal * 1.15, 2)];
        $itemSubtotal = $priced['subtotal'];
        $vatAmount = $priced['vat'];
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Receipt ' . $orderNumber . '</title></head><body style="margin:0;background:#f4f7f6;font-family:Arial,sans-serif;color:#163028">
      <div style="max-width:560px;margin:24px auto;background:#fff;border:1px solid #d7e6e0;border-radius:16px;overflow:hidden">
        <div style="padding:22px 24px;background:#1f6f4a;color:#fff">
          <div style="font-size:12px;letter-spacing:.12em;font-weight:700">ADDTOMAR RECEIPT</div>
          <h1 style="margin:8px 0 0;font-size:22px">Payment confirmed</h1>
        </div>
        <div style="padding:24px">
          <p style="margin:0 0 16px;color:#4d6a60">Official receipt for your 60% down payment. Remaining balance is due at pickup.</p>
          <table style="width:100%;border-collapse:collapse;font-size:14px">
            <tr><td style="padding:6px 0;color:#4d6a60">Receipt / Order</td><td style="text-align:right;font-weight:700">' . $orderNumber . '</td></tr>
            <tr><td style="padding:6px 0;color:#4d6a60">Customer</td><td style="text-align:right">' . $customer . '</td></tr>
            <tr><td style="padding:6px 0;color:#4d6a60">Email</td><td style="text-align:right">' . $email . '</td></tr>
            <tr><td style="padding:6px 0;color:#4d6a60">Pharmacy</td><td style="text-align:right">' . $pharmacy . '</td></tr>
            <tr><td style="padding:6px 0;color:#4d6a60">Paid at</td><td style="text-align:right">' . $paidAt . '</td></tr>
            <tr><td style="padding:6px 0;color:#4d6a60">Pickup</td><td style="text-align:right">' . $pickup . '</td></tr>
          </table>
          <hr style="border:none;border-top:1px solid #edf1f0;margin:18px 0">
          <table style="width:100%;border-collapse:collapse;font-size:14px">' . $lines . '</table>
          <hr style="border:none;border-top:1px solid #edf1f0;margin:18px 0">
          <table style="width:100%;border-collapse:collapse;font-size:14px">
            <tr><td>Subtotal</td><td style="text-align:right">' . residence_format_money($itemSubtotal) . '</td></tr>
            <tr><td>VAT (15%)</td><td style="text-align:right">' . residence_format_money($vatAmount) . '</td></tr>
            <tr><td>Order total</td><td style="text-align:right">' . $total . '</td></tr>
            <tr><td style="padding-top:8px;font-weight:800;color:#1f6f4a">Amount paid now (60%)</td><td style="text-align:right;padding-top:8px;font-weight:800;color:#1f6f4a">' . $down . '</td></tr>
            <tr><td>Balance due on pickup</td><td style="text-align:right">' . $balance . '</td></tr>
          </table>
          <p style="margin:18px 0 0;font-size:12px;color:#6b8178">PayMongo payment ID: ' . $paymentId . '<br>Payment Intent: ' . $intentId . '</p>
          <p style="margin:10px 0 0;padding:10px;border-radius:6px;background:#fff7e8;color:#8a5a12;font-size:12px"><strong>Balance due at pickup:</strong> ' . $balance . '</p>
        </div>
      </div>
    </body></html>';
}

function residence_save_receipt_file(string $orderNumber, string $html): ?string
{
    $dir = dirname(__DIR__, 2) . '/data/uploads/receipts';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }
    $filename = preg_replace('/[^A-Za-z0-9_-]/', '', $orderNumber) . '.html';
    $path = $dir . '/' . $filename;
    if (file_put_contents($path, $html) === false) {
        return null;
    }

    return 'data/uploads/receipts/' . $filename;
}

function residence_order_vat_amount(array $order): float
{
    $vat = (float) ($order['vat'] ?? 0);
    if ($vat > 0) {
        return round($vat, 2);
    }

    $itemSubtotal = 0.0;
    foreach ($order['items'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        $itemSubtotal += (float) ($item['unit_price'] ?? $item['price'] ?? 0) * $qty;
    }

    if ($itemSubtotal > 0) {
        $priced = function_exists('residence_apply_vat')
            ? residence_apply_vat($itemSubtotal)
            : ['vat' => round($itemSubtotal * 0.15, 2)];

        return round((float) $priced['vat'], 2);
    }

    $total = (float) ($order['total_amount'] ?? 0);
    if ($total <= 0) {
        return 0.0;
    }

    $rate = function_exists('residence_vat_rate') ? residence_vat_rate() : 0.15;

    return round($total - ($total / (1 + $rate)), 2);
}

function residence_notify_admin_vat(array $order, array $payment = []): array
{
    require_once dirname(__DIR__, 2) . '/includes/mailer.php';
    require_once dirname(__DIR__, 2) . '/includes/env.php';

    $adminEmail = function_exists('addtomar_admin_email') ? addtomar_admin_email() : '';
    if ($adminEmail === '' || !residence_is_gmail($adminEmail)) {
        return ['ok' => false, 'error' => 'Admin Gmail is not configured.'];
    }

    $vatAmount = residence_order_vat_amount($order);
    if ($vatAmount <= 0) {
        return ['ok' => false, 'error' => 'No VAT amount to report.'];
    }

    $orderNumber = htmlspecialchars((string) ($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $pharmacy = htmlspecialchars((string) ($order['pharmacy_name'] ?? 'Pharmacy'), ENT_QUOTES, 'UTF-8');
    $customer = htmlspecialchars((string) ($order['customer_name'] ?? $order['payer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $paidAt = htmlspecialchars((string) ($payment['paid_at'] ?? date('M j, Y g:i A')), ENT_QUOTES, 'UTF-8');
    $vatLabel = residence_format_money($vatAmount);
    $rate = function_exists('residence_vat_rate') ? (int) round(residence_vat_rate() * 100) : 15;
    $total = residence_format_money((float) ($order['total_amount'] ?? 0));
    $down = residence_format_money((float) ($order['down_payment'] ?? 0));

    $html = '<!DOCTYPE html><html><body style="margin:0;background:#f4f7f6;font-family:Arial,sans-serif;color:#163028">'
        . '<div style="max-width:560px;margin:24px auto;background:#fff;border:1px solid #d7e6e0;border-radius:16px;overflow:hidden">'
        . '<div style="padding:22px 24px;background:#1f6f4a;color:#fff">'
        . '<div style="font-size:12px;letter-spacing:.12em;font-weight:700">ADDTOMAR VAT</div>'
        . '<h1 style="margin:8px 0 0;font-size:22px">VAT received</h1>'
        . '</div>'
        . '<div style="padding:24px">'
        . '<p style="margin:0 0 16px;color:#4d6a60">A successful order was placed. AddToMar received the VAT for this payment.</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
        . '<tr><td style="padding:6px 0;color:#4d6a60">Order</td><td style="text-align:right;font-weight:700">' . $orderNumber . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#4d6a60">Customer</td><td style="text-align:right">' . $customer . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#4d6a60">Pharmacy</td><td style="text-align:right">' . $pharmacy . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#4d6a60">Paid at</td><td style="text-align:right">' . $paidAt . '</td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:800;color:#1f6f4a">VAT (' . $rate . '%)</td><td style="text-align:right;padding:8px 0;font-weight:800;color:#1f6f4a">' . $vatLabel . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#4d6a60">Order total</td><td style="text-align:right">' . $total . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#4d6a60">Amount paid now</td><td style="text-align:right">' . $down . '</td></tr>'
        . '</table>'
        . '</div></div></body></html>';

    return smtp_send_gmail($adminEmail, 'AddToMar VAT received ' . ($order['order_number'] ?? ''), $html);
}

function residence_email_receipt(string $email, string $subject, string $html): array
{
    require_once dirname(__DIR__, 2) . '/includes/mailer.php';

    return smtp_send_gmail($email, $subject, $html);
}
