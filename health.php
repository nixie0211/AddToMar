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
$error = trim((string) ($GLOBALS['addtomar_db_error'] ?? ''));
$host = addtomar_env('DB_HOST', '(empty)');
$port = addtomar_env('DB_PORT', '(empty)');
$name = addtomar_env('DB_NAME', '(empty)');

echo "database unavailable\n";
echo 'host=' . $host . "\n";
echo 'port=' . $port . "\n";
echo 'name=' . $name . "\n";
echo 'error=' . ($error !== '' ? $error : '(none)');
