<?php

declare(strict_types=1);

if (!defined('PHARMACY_ROOT')) {
    define('PHARMACY_ROOT', __DIR__);
}

require_once dirname(__DIR__) . '/includes/env.php';

if (!defined('PHARMACY_DB_HOST')) {
    define('PHARMACY_DB_HOST', addtomar_env('DB_HOST', 'localhost'));
}
if (!defined('PHARMACY_DB_NAME')) {
    // Keep pharmacy inventory in the main application database.
    define('PHARMACY_DB_NAME', addtomar_env('DB_NAME', 'CAPS'));
}
if (!defined('PHARMACY_DB_USER')) {
    define('PHARMACY_DB_USER', addtomar_env('DB_USER', 'root'));
}
if (!defined('PHARMACY_DB_PASS')) {
    define('PHARMACY_DB_PASS', addtomar_env('DB_PASS', ''));
}
if (!defined('PHARMACY_DB_CHARSET')) {
    define('PHARMACY_DB_CHARSET', addtomar_env('DB_CHARSET', 'utf8mb4'));
}

function pharmacy_asset(string $path): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') . '/' . ltrim($path, '/');
}
