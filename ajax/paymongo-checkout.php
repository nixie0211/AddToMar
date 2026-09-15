<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('residence');
require_once dirname(__DIR__) . '/includes/paymongo-http.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/residence/includes/orders.php';
require_once dirname(__DIR__) . '/residence/includes/paymongo-complete.php';

header('Content-Type: application/json; charset=UTF-8');
ob_start();

function paymongo_checkout_respond(array $payload): void
{
    unset($payload['receipt_html']);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json !== false ? $json : json_encode(['ok' => false, 'error' => 'Could not encode payment response.']);
    exit;
}

$amount = max(0, (float) ($_POST['amount'] ?? 0));
$paymongoMin = 20.00;
$amount = round(max($amount, $paymongoMin), 2);
$email = residence_receipt_gmail((string) ($_POST['email'] ?? ''));
$name = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$pharmacyId = trim((string) ($_POST['pharmacy_id'] ?? ''));
$pickupDate = trim((string) ($_POST['pickup_date'] ?? date('Y-m-d')));
$pickupTime = trim((string) ($_POST['pickup_time'] ?? 'Any available time'));
$items = json_decode((string) ($_POST['items'] ?? '[]'), true);

if ($email === '' || !residence_is_gmail($email)) {
    paymongo_checkout_respond(['ok' => false, 'error' => 'Enter the Gmail address for your account so we can send the receipt.']);
}

if ($name === '') {
    paymongo_checkout_respond(['ok' => false, 'error' => 'Enter your full name.']);
}

if ($phone === '') {
    paymongo_checkout_respond(['ok' => false, 'error' => 'Enter your contact number.']);
}

if ($address === '') {
    paymongo_checkout_respond(['ok' => false, 'error' => 'Enter your address.']);
}

if (!is_array($items) || $items === []) {
    paymongo_checkout_respond(['ok' => false, 'error' => 'Select items before paying.']);
}

if ($pharmacyId === '') {
    paymongo_checkout_respond(['ok' => false, 'error' => 'Select a pharmacy before paying.']);
}

try {
    $prescriptions = residence_collect_prescription_uploads();
    $rxLines = residence_checkout_rx_lines($items, $pharmacyId);
    residence_assert_prescriptions_uploaded($rxLines, $prescriptions);
    $prescriptionPath = null;
    if ($rxLines !== []) {
        $first = $rxLines[0];
        $prescriptionPath = residence_prescription_path_for_item(
            $prescriptions,
            (string) $first['pharmacy_id'],
            (int) $first['medicine_id'],
            count($rxLines)
        );
    }
    if ($prescriptionPath === null) {
        $prescriptionPath = $prescriptions[$pharmacyId] ?? ($prescriptions['_default'] ?? (reset($prescriptions) ?: null));
    }

    $centavos = (int) round($amount * 100);
    $intent = paymongo_http('POST', 'payment_intents', [
        'data' => [
            'attributes' => [
                'amount' => $centavos,
                'currency' => 'PHP',
                'description' => 'AddToMar down payment (60%)',
                'statement_descriptor' => 'AddToMar',
                'payment_method_allowed' => ['card'],
                'payment_method_options' => [
                    'card' => ['request_three_d_secure' => 'automatic'],
                ],
            ],
        ],
    ]);
    if (!$intent['ok']) {
        paymongo_checkout_respond(['ok' => false, 'error' => $intent['error'] ?? 'Unable to start card payment.']);
    }

    $intentId = (string) ($intent['data']['data']['id'] ?? '');
    if ($intentId === '') {
        paymongo_checkout_respond(['ok' => false, 'error' => 'PayMongo did not return a payment intent.']);
    }

    $returnUrl = paymongo_app_base_url() . '/residence/payment-complete.php?payment_intent_id=' . rawurlencode($intentId);
    $method = paymongo_http('POST', 'payment_methods', [
        'data' => [
            'attributes' => [
                'type' => 'card',
                'details' => [
                    'card_number' => '4343434343434345',
                    'exp_month' => 12,
                    'exp_year' => (int) date('Y') + 2,
                    'cvc' => '123',
                ],
                'billing' => array_filter([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                ]),
            ],
        ],
    ]);
    if (!$method['ok']) {
        paymongo_checkout_respond(['ok' => false, 'error' => $method['error'] ?? 'Unable to prepare test card.']);
    }

    $attach = paymongo_http('POST', 'payment_intents/' . rawurlencode($intentId) . '/attach', [
        'data' => [
            'attributes' => [
                'payment_method' => $method['data']['data']['id'] ?? '',
                'return_url' => $returnUrl,
            ],
        ],
    ]);
    if (!$attach['ok']) {
        paymongo_checkout_respond(['ok' => false, 'error' => $attach['error'] ?? 'Unable to authorize the test card.']);
    }

    $_SESSION['paymongo_pending'] = [
        'intent_id' => $intentId,
        'amount' => $amount,
        'centavos' => $centavos,
        'email' => $email,
        'name' => $name,
        'phone' => $phone,
        'address' => $address,
        'pharmacy_id' => $pharmacyId,
        'pickup_date' => $pickupDate !== '' ? $pickupDate : date('Y-m-d'),
        'pickup_time' => $pickupTime !== '' ? $pickupTime : 'Any available time',
        'items' => $items,
        'prescription_path' => $prescriptionPath,
        'prescriptions' => $prescriptions,
    ];

    $attrs = $attach['data']['data']['attributes'] ?? [];
    $attachStatus = (string) ($attrs['status'] ?? '');
    $nextAction = is_array($attrs['next_action'] ?? null) ? $attrs['next_action'] : [];
    $authUrl = $nextAction['redirect']['url']
        ?? $nextAction['redirect']['return_url']
        ?? $nextAction['url']
        ?? null;

    if ($attachStatus === 'succeeded') {
        $final = residence_finalize_paymongo_payment($intentId);
        $final['intent_id'] = $intentId;
        paymongo_checkout_respond($final);
    }

    paymongo_checkout_respond([
        'ok' => true,
        'checkout_url' => $authUrl ?: $returnUrl,
        'intent_id' => $intentId,
        'status' => $attachStatus,
    ]);
} catch (Throwable $e) {
    paymongo_checkout_respond(['ok' => false, 'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to start card payment.']);
}
