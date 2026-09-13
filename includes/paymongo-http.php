<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once dirname(__DIR__) . '/AddToMar1/AddToMar/config/paymongo.php';

function paymongo_http(string $method, string $endpoint, ?array $body = null): array
{
    $ch = curl_init('https://api.paymongo.com/v1/' . ltrim($endpoint, '/'));
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => PAYMONGO_SECRET_KEY . ':',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $data = json_decode((string) $response, true);
    if ($curlError) {
        return ['ok' => false, 'error' => $curlError];
    }
    if ($httpCode >= 400) {
        $details = [];
        foreach (is_array($data['errors'] ?? null) ? $data['errors'] : [] as $err) {
            if (!is_array($err)) {
                continue;
            }
            $details[] = (string) ($err['detail'] ?? $err['code'] ?? '');
        }
        $details = array_values(array_filter($details));
        return ['ok' => false, 'error' => $details !== [] ? implode(' ', $details) : 'PayMongo request failed.', 'data' => $data];
    }

    return ['ok' => true, 'data' => $data];
}

function paymongo_app_base_url(): string
{
    $configured = addtomar_public_origin();
    if ($configured !== '') {
        return $configured;
    }

    $scheme = addtomar_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#/ajax/[^/]+$#', $script)) {
        $base = preg_replace('#/ajax/[^/]+$#', '', $script);
    } elseif (preg_match('#/(residence|pharmacy|admin)(/|$)#', $script)) {
        $base = preg_replace('#/(residence|pharmacy|admin)(/.*)?$#', '', $script);
    } else {
        $base = rtrim(dirname($script), '/');
    }

    return $scheme . '://' . $host . ($base ?: '');
}
