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

    $pdo = addtomar_mysql_connect(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET);

    caps_run_migrations($pdo);

    return $pdo;
}

function caps_bootstrap(): bool
{
    $GLOBALS['addtomar_db_error'] = '';
    try {
        if (function_exists('date_default_timezone_set')) {
            date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Manila');
        }
        caps_db();
        return true;
    } catch (Throwable $e) {
        $GLOBALS['addtomar_db_error'] = $e->getMessage();
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
            contact_number VARCHAR(20) NOT NULL DEFAULT \'\',
            address TEXT NOT NULL,
            latitude DECIMAL(10, 7) NULL,
            longitude DECIMAL(10, 7) NULL,
            password_hash VARCHAR(255) NOT NULL,
            open_time VARCHAR(8) NOT NULL DEFAULT \'08:00\',
            close_time VARCHAR(8) NOT NULL DEFAULT \'20:00\',
            operation_days TEXT,
            operating_hours TEXT,
            logo_path VARCHAR(255) NOT NULL DEFAULT \'\',
            business_permit_path VARCHAR(255) NOT NULL DEFAULT \'\',
            pharmacy_license_path VARCHAR(255) NOT NULL DEFAULT \'\',
            bir_certificate_path VARCHAR(255) NOT NULL DEFAULT \'\',
            status VARCHAR(32) NOT NULL DEFAULT \'pending\',
            admin_note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pharmacies_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $statusHistoryColumn = $pdo->query("SHOW COLUMNS FROM pharmacies LIKE 'status_history'")->fetch();
    if (!$statusHistoryColumn) {
        $pdo->exec('ALTER TABLE pharmacies ADD COLUMN status_history TEXT NULL AFTER admin_note');
    }

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

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS php_sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data MEDIUMTEXT NOT NULL,
            updated_at INT UNSIGNED NOT NULL,
            INDEX idx_php_sessions_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS pharmacy_documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pharmacy_id VARCHAR(32) NOT NULL,
            doc_key VARCHAR(64) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            mime VARCHAR(127) NOT NULL DEFAULT \'application/octet-stream\',
            content LONGBLOB NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pharmacy_documents_pharmacy (pharmacy_id, doc_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $documentPathsColumn = $pdo->query("SHOW COLUMNS FROM pharmacies LIKE 'document_paths'")->fetch();
    if (!$documentPathsColumn) {
        $pdo->exec('ALTER TABLE pharmacies ADD COLUMN document_paths TEXT NULL AFTER bir_certificate_path');
    }

    caps_clear_sample_reports($pdo);
    caps_ensure_admin_account($pdo);
    caps_seed_tgp_inventory();
}

function caps_seed_tgp_inventory(): void
{
    $pharmacyDatabase = dirname(__DIR__) . '/pharmacy/includes/database.php';
    $pharmacyConfig = dirname(__DIR__) . '/pharmacy/config.php';
    if (!is_file($pharmacyDatabase) || !is_file($pharmacyConfig)) {
        return;
    }

    require_once $pharmacyConfig;
    require_once $pharmacyDatabase;

    try {
        pharmacy_db();
    } catch (Throwable) {
        // Pharmacy migrations also clear seeded TGP inventory once.
    }
}

function caps_clear_sample_reports(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS app_meta (
            meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
            meta_value VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $stmt = $pdo->prepare('SELECT meta_value FROM app_meta WHERE meta_key = ? LIMIT 1');
    $stmt->execute(['clear_sample_reports_v1']);
    if ($stmt->fetchColumn()) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO app_meta (meta_key, meta_value) VALUES (?, ?)');
    $insert->execute(['clear_sample_reports_v1', date('c')]);
}

function caps_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function caps_wipe_json_file(string $path, string $contents): void
{
    if (!is_dir(dirname($path))) {
        return;
    }

    @file_put_contents($path, $contents);
}

function caps_wipe_directory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            caps_wipe_directory($full);
            @rmdir($full);
            continue;
        }

        @unlink($full);
    }
}

function caps_wipe_non_admin_accounts(PDO $pdo): void
{
    // Disabled. Deploy-time account wipes destroyed live data.
}

function caps_reset_admin_operational_data(PDO $pdo): void
{
    // Disabled. This used to DELETE pharmacies, medicines, sessions, and
    // non-admin users on boot when app_meta was missing — which happens on
    // every new Render deploy if the app connected to an empty schema.
    try {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS app_meta (
                meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
                meta_value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $mark = $pdo->prepare('INSERT IGNORE INTO app_meta (meta_key, meta_value) VALUES (?, ?)');
        $mark->execute(['reset_admin_operational_data_v1', date('c')]);
    } catch (Throwable) {
        // Never block the app or delete rows if meta cannot be written.
    }
}

function caps_ensure_admin_account(PDO $pdo): void
{
    $email = addtomar_admin_email();
    $password = addtomar_admin_bootstrap_password();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $insert = $pdo->prepare(
            'INSERT INTO users (email, full_name, contact_number, address, latitude, longitude, password_hash, role, password_set)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
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

        return;
    }

    $pdo->prepare("UPDATE users SET role = 'admin', password_set = 1 WHERE email = ?")->execute([$email]);

    $hash = (string) ($existing['password_hash'] ?? '');
    $needsPassword = $hash === '' || !password_verify($password, $hash);

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS app_meta (
            meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
            meta_value VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    $meta = $pdo->prepare('SELECT meta_value FROM app_meta WHERE meta_key = ? LIMIT 1');
    $meta->execute(['admin_login_restore_v1']);
    $restored = (string) $meta->fetchColumn();

    if ($needsPassword || $restored === '') {
        $pdo->prepare('UPDATE users SET password_hash = ?, password_set = 1, role = \'admin\' WHERE email = ?')
            ->execute([$passwordHash, $email]);
        if ($restored === '') {
            $pdo->prepare('INSERT IGNORE INTO app_meta (meta_key, meta_value) VALUES (?, ?)')
                ->execute(['admin_login_restore_v1', date('c')]);
        }
    }
}
