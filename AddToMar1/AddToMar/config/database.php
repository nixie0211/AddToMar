<?php
/**
 * AddToMar - Database Connection
 * Uses PDO with prepared statements for SQL Injection protection.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'addtomar_db');
define('DB_USER', 'root');
define('DB_PASS', '');

$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: __DIR__ . '/..');
define('BASE_URL', '/' . trim(str_replace($docRoot, '', $appRoot), '/') . '/');
define('UPLOAD_MED_PATH', __DIR__ . '/../assets/uploads/medicines/');
define('UPLOAD_PAY_PATH', __DIR__ . '/../assets/uploads/payments/');
define('UPLOAD_MED_URL', BASE_URL . 'assets/uploads/medicines/');
define('UPLOAD_PAY_URL', BASE_URL . 'assets/uploads/payments/');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:Poppins,sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#0F172A;">⚠️ Database Connection Failed</h2>
        <p style="color:#64748B;">Please make sure XAMPP MySQL is running and that you have imported <code>sql/addtomar_db.sql</code>.</p>
        <p style="color:#94A3B8;font-size:13px;">' . htmlspecialchars($e->getMessage()) . '</p></div>');
}
