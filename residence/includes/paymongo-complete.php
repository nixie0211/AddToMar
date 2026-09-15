<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/customers.php';
require_once dirname(__DIR__, 2) . '/includes/paymongo-http.php';
require_once dirname(__DIR__, 2) . '/includes/mailer.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/pharmacies.php';
require_once __DIR__ . '/receipt.php';

function residence_finalize_paymongo_payment(?string $intentId = null): array
{
    $pending = $_SESSION['paymongo_pending'] ?? null;
    $intentId = trim((string) ($intentId ?: ($pending['intent_id'] ?? '')));

    if ($intentId === '') {
        return ['ok' => false, 'status' => 'missing', 'error' => 'Payment session was not found. Please try checkout again.'];
    }

    $result = paymongo_http('GET', 'payment_intents/' . rawurlencode($intentId));
    if (!$result['ok']) {
        return ['ok' => false, 'status' => 'error', 'error' => $result['error'] ?? 'Could not confirm PayMongo payment.'];
    }

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

    if ($status === 'awaiting_next_action' || $status === 'processing' || $status === 'pending' || $status === 'awaiting_payment_method') {
        return ['ok' => true, 'status' => $status !== '' ? $status : 'awaiting_next_action', 'intent_id' => $intentId];
    }

    if ($status !== 'succeeded') {
        return [
            'ok' => false,
            'status' => $status !== '' ? $status : 'failed',
            'error' => $status === 'awaiting_next_action'
                ? 'Payment was not authorized. Please try again.'
                : 'Payment was not completed. Please try again.',
        ];
    }

    $existing = residence_find_order_by_intent($intentId);
    $sendMail = false;
    if ($existing) {
        $directory = residence_pharmacy_directory();
        $order = $existing;
        $order['pharmacy_name'] = $directory[$existing['pharmacy_id'] ?? '']['name'] ?? ($existing['pharmacy_name'] ?? 'Pharmacy');
        $order['payer_name'] = $existing['customer_name'] ?? ($pending['name'] ?? '');
        $order['receipt_email'] = is_array($pending) ? (string) ($pending['email'] ?? '') : '';
    } elseif (!is_array($pending) || ($pending['intent_id'] ?? '') !== $intentId) {
        return ['ok' => false, 'status' => 'expired', 'error' => 'Payment succeeded, but the checkout session expired. Contact the pharmacy with reference ' . $intentId . '.'];
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
        if (!empty($pending['address'])) {
            $profile['address'] = $pending['address'];
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
            return ['ok' => false, 'status' => 'order_failed', 'error' => $placed['error'] ?? 'Payment succeeded but the order could not be saved.'];
        }

        $order = $placed['order'];
        $order['payer_name'] = $pending['name'] ?? ($order['payer_name'] ?? '');
        $order['receipt_email'] = $pending['email'] ?? '';
        $placedOrders = $placed['orders'] ?? [$order];
        $order = residence_combine_orders_for_receipt($placedOrders);
        $order['payer_name'] = $pending['name'] ?? ($order['payer_name'] ?? '');
        $order['receipt_email'] = $pending['email'] ?? '';
        unset($_SESSION['paymongo_pending']);
        $sendMail = true;
    }

    $gmailTo = residence_receipt_gmail(is_array($pending) ? (string) ($pending['email'] ?? '') : (string) ($order['receipt_email'] ?? ''));
    $order['receipt_email'] = $gmailTo !== '' ? $gmailTo : (string) ($order['receipt_email'] ?? '');
    $receiptHtml = residence_receipt_html($order, $paymentMeta);
    residence_save_receipt_file((string) ($order['order_number'] ?? 'receipt'), $receiptHtml);

    $emailed = false;
    $mailError = '';
    if ($sendMail) {
        $mail = residence_email_receipt(
            (string) $order['receipt_email'],
            'AddToMar payment receipt ' . ($order['order_number'] ?? ''),
            $receiptHtml
        );
        $emailed = !empty($mail['ok']);
        $mailError = (string) ($mail['error'] ?? '');
    } else {
        $emailed = !empty($_SESSION['paymongo_last_receipt']['emailed']);
        $mailError = (string) ($_SESSION['paymongo_last_receipt']['mail_error'] ?? '');
    }

    $_SESSION['paymongo_last_receipt'] = [
        'order' => $order,
        'payment' => $paymentMeta,
        'emailed' => $emailed,
        'mail_error' => $mailError,
    ];

    return [
        'ok' => true,
        'status' => 'succeeded',
        'intent_id' => $intentId,
        'order' => $order,
        'orders' => $placedOrders ?? [$order],
        'receipt_html' => $receiptHtml,
        'emailed' => $emailed,
        'mail_error' => $mailError,
    ];
}
