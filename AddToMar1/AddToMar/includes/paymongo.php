<?php
require_once __DIR__ . '/../config/paymongo.php';

function paymongo_is_configured(): bool {
    return defined('PAYMONGO_SECRET_KEY')
        && PAYMONGO_SECRET_KEY !== ''
        && defined('PAYMONGO_PUBLIC_KEY')
        && PAYMONGO_PUBLIC_KEY !== '';
}

function app_url(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL . ltrim($path, '/');
}

function paymongo_to_centavos(float $pesos): int {
    return max(100, (int) round($pesos * 100));
}

function paymongo_request(string $method, string $endpoint, ?array $body = null): array {
    $url = 'https://api.paymongo.com/v1/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => PAYMONGO_SECRET_KEY . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 30,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['ok' => false, 'error' => $curlError];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $data['errors'][0]['detail'] ?? $data['errors'][0]['title'] ?? 'PayMongo request failed.';
        return ['ok' => false, 'error' => $msg, 'data' => $data];
    }

    return ['ok' => true, 'data' => $data];
}

function paymongo_create_checkout_session(float $amountPesos, string $description, string $successUrl, string $cancelUrl): array {
    $centavos = paymongo_to_centavos($amountPesos);

    return paymongo_request('POST', 'checkout_sessions', [
        'data' => [
            'attributes' => [
                'billing'              => ['enabled' => false],
                'description'          => $description,
                'line_items'           => [[
                    'currency'    => 'PHP',
                    'amount'      => $centavos,
                    'description' => $description,
                    'name'        => 'Down Payment (50%)',
                    'quantity'    => 1,
                ]],
                'payment_method_types' => ['gcash', 'card', 'grab_pay', 'paymaya'],
                'success_url'          => $successUrl,
                'cancel_url'           => $cancelUrl,
                'send_email_receipt'   => false,
            ],
        ],
    ]);
}

function paymongo_get_checkout_session(string $sessionId): array {
    return paymongo_request('GET', 'checkout_sessions/' . rawurlencode($sessionId));
}

function paymongo_checkout_is_paid(array $sessionData): bool {
    $attrs = $sessionData['data']['attributes'] ?? [];

    if (($attrs['payment_status'] ?? '') === 'paid') {
        return true;
    }

    $payments = $attrs['payments'] ?? [];
    foreach ($payments as $payment) {
        $status = $payment['attributes']['status'] ?? '';
        if ($status === 'paid') {
            return true;
        }
    }

    $intent = $attrs['payment_intent'] ?? null;
    if (is_array($intent)) {
        $intentStatus = $intent['attributes']['status'] ?? '';
        if ($intentStatus === 'succeeded') {
            return true;
        }
    }

    return false;
}
