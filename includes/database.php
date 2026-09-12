<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

if (!defined('DB_HOST')) {
    define('DB_HOST', addtomar_env('DB_HOST', 'localhost'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', addtomar_env('DB_NAME', 'CAPS'));
}
if (!defined('DB_USER')) {
    define('DB_USER', addtomar_env('DB_USER', 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', addtomar_env('DB_PASS', ''));
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', addtomar_env('DB_CHARSET', 'utf8mb4'));
}

function caps_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = addtomar_mysql_options();

    try {
        $pdo = new PDO(addtomar_mysql_dsn(DB_HOST, DB_NAME, DB_CHARSET), DB_USER, DB_PASS, $options);
    } catch (PDOException) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . addtomar_env('DB_PORT', '3306') . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . DB_NAME . '`');
    }

    caps_run_migrations($pdo);

    return $pdo;
}

function caps_bootstrap(): bool
{
    try {
        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Manila');
        }
        caps_db();
        return true;
    } catch (Throwable $e) {
        error_log('AddToMar database error: ' . $e->getMessage());
        return false;
    }
}

function caps_run_migrations(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(20) NOT NULL,
            address TEXT NOT NULL,
            latitude DECIMAL(10, 7) NOT NULL,
            longitude DECIMAL(10, 7) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(32) NOT NULL DEFAULT \'customer\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $roleColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if (!$roleColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'customer' AFTER password_hash");
    }

    $googleIdColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'google_id'")->fetch();
    if (!$googleIdColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(64) NULL AFTER role");
    }
    $googleIdIndex = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_google_id'")->fetch();
    if (!$googleIdIndex) {
        $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_google_id (google_id)');
    }

    $passwordSetColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_set'")->fetch();
    if (!$passwordSetColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN password_set TINYINT(1) NOT NULL DEFAULT 1 AFTER google_id");
    }

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS pharmacies (
            id VARCHAR(32) NOT NULL,
            email VARCHAR(255) NOT NULL,
            pharmacy_name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(20) NOT NULL DEFAULT "",
            address TEXT NOT NULL,
            latitude DECIMAL(10, 7) NULL,
            longitude DECIMAL(10, 7) NULL,
            password_hash VARCHAR(255) NOT NULL,
            open_time VARCHAR(8) NOT NULL DEFAULT "08:00",
            close_time VARCHAR(8) NOT NULL DEFAULT "20:00",
            operation_days TEXT,
            operating_hours TEXT,
            logo_path VARCHAR(255) NOT NULL DEFAULT "",
            business_permit_path VARCHAR(255) NOT NULL DEFAULT "",
            pharmacy_license_path VARCHAR(255) NOT NULL DEFAULT "",
            bir_certificate_path VARCHAR(255) NOT NULL DEFAULT "",
            status VARCHAR(32) NOT NULL DEFAULT "pending",
            admin_note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pharmacies_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS user_addresses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_email VARCHAR(255) NOT NULL,
            label VARCHAR(64) NOT NULL DEFAULT \'Address\',
            address TEXT NOT NULL,
            latitude DECIMAL(10, 7) NOT NULL,
            longitude DECIMAL(10, 7) NOT NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_addresses_email (user_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS pharmacy_reports (
            id VARCHAR(32) NOT NULL PRIMARY KEY,
            pharmacy_id VARCHAR(64) NOT NULL,
            pharmacy_name VARCHAR(255) NOT NULL,
            pharmacy_email VARCHAR(255) NOT NULL DEFAULT \'\',
            reporter_name VARCHAR(255) NOT NULL DEFAULT \'\',
            reporter_email VARCHAR(255) NOT NULL DEFAULT \'\',
            reason VARCHAR(255) NOT NULL,
            details TEXT,
            proof_path VARCHAR(255) NOT NULL DEFAULT \'\',
            status VARCHAR(32) NOT NULL DEFAULT \'under_review\',
            admin_note TEXT,
            action_taken VARCHAR(64) NOT NULL DEFAULT \'\',
            reviewed_at VARCHAR(40) NOT NULL DEFAULT \'\',
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            INDEX idx_pharmacy_reports_status (status),
            INDEX idx_pharmacy_reports_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS resident_notifications (
            id VARCHAR(64) NOT NULL PRIMARY KEY,
            user_email VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(32) NOT NULL DEFAULT \'info\',
            report_id VARCHAR(32) NOT NULL DEFAULT \'\',
            order_number VARCHAR(64) NOT NULL DEFAULT \'\',
            created_at VARCHAR(40) NOT NULL,
            read_at VARCHAR(40) NOT NULL DEFAULT \'\',
            INDEX idx_resident_notifications_user (user_email, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $orderNumberColumn = $pdo->query("SHOW COLUMNS FROM resident_notifications LIKE 'order_number'")->fetch();
    if (!$orderNumberColumn) {
        $pdo->exec("ALTER TABLE resident_notifications ADD COLUMN order_number VARCHAR(64) NOT NULL DEFAULT '' AFTER report_id");
    }

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS cart (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_email VARCHAR(255) NOT NULL,
            medicine_id INT NOT NULL,
            pharmacy_id VARCHAR(64) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            selected TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cart_user_medicine_pharmacy (user_email, medicine_id, pharmacy_id),
            INDEX idx_cart_user (user_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    caps_ensure_admin_account($pdo);
}

function caps_ensure_admin_account(PDO $pdo): void
{
    $email = 'addtomar@gmail.com';
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare(
            'UPDATE users
             SET full_name = ?, password_hash = ?, role = ?, contact_number = ?, address = ?
             WHERE email = ?'
        );
        $update->execute([
            'AddToMar Admin',
            $passwordHash,
            'admin',
            '09171234567',
            'AddToMar, Laoag City, Ilocos Norte',
            $email,
        ]);
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (email, full_name, contact_number, address, latitude, longitude, password_hash, role)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $email,
        'AddToMar Admin',
        '09171234567',
        'AddToMar, Laoag City, Ilocos Norte',
        18.1978000,
        120.5936000,
        $passwordHash,
        'admin',
    ]);
}
