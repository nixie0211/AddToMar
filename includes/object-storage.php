<?php

declare(strict_types=1);

function addtomar_object_storage_enabled(): bool
{
    return addtomar_env('R2_ACCOUNT_ID') !== ''
        && addtomar_env('R2_ACCESS_KEY_ID') !== ''
        && addtomar_env('R2_SECRET_ACCESS_KEY') !== ''
        && addtomar_env('R2_BUCKET') !== ''
        && addtomar_env('R2_PUBLIC_BASE_URL') !== '';
}

function addtomar_file_public_url(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (function_exists('app_url')) {
        return app_url(ltrim($path, '/'));
    }

    return '/' . ltrim($path, '/');
}

function addtomar_object_storage_public_url(string $key): string
{
    return rtrim(addtomar_env('R2_PUBLIC_BASE_URL'), '/') . '/' . str_replace('%2F', '/', rawurlencode($key));
}

function addtomar_object_storage_put(string $key, string $body, string $contentType = 'application/octet-stream'): string
{
    if (!addtomar_object_storage_enabled() || $key === '' || $body === '') {
        return '';
    }

    $accountId = addtomar_env('R2_ACCOUNT_ID');
    $accessKey = addtomar_env('R2_ACCESS_KEY_ID');
    $secretKey = addtomar_env('R2_SECRET_ACCESS_KEY');
    $bucket = addtomar_env('R2_BUCKET');
    $region = addtomar_env('R2_REGION', 'auto');
    $host = $accountId . '.r2.cloudflarestorage.com';
    $uri = '/' . rawurlencode($bucket) . '/' . str_replace('%2F', '/', rawurlencode($key));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $amzDate = $now->format('Ymd\\THis\\Z');
    $dateStamp = $now->format('Ymd');
    $payloadHash = hash('sha256', $body);
    $canonicalHeaders = "content-type:" . strtolower($contentType) . "\nhost:" . $host . "\nx-amz-content-sha256:" . $payloadHash . "\nx-amz-date:" . $amzDate . "\n";
    $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
    $canonicalRequest = "PUT\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
    $scope = $dateStamp . '/' . $region . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);
    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    $authorization = 'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $scope
        . ', SignedHeaders=' . $signedHeaders
        . ', Signature=' . $signature;

    $handle = curl_init('https://' . $host . $uri);
    if ($handle === false) {
        return '';
    }
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
            'Content-Type: ' . $contentType,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $amzDate,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    return ($status >= 200 && $status < 300) ? addtomar_object_storage_public_url($key) : '';
}
