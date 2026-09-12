<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/database.php';

if (caps_bootstrap()) {
    http_response_code(200);
    echo 'ok';
    exit;
}

http_response_code(503);
echo 'database unavailable';
