<?php

declare(strict_types=1);

if (!defined('PHARMACY_DB_HOST')) {
    require_once dirname(__DIR__) . '/config.php';
}

function pharmacy_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = addtomar_mysql_options();

    try {
        $pdo = new PDO(
            addtomar_mysql_dsn(PHARMACY_DB_HOST, PHARMACY_DB_NAME, PHARMACY_DB_CHARSET),
            PHARMACY_DB_USER,
            PHARMACY_DB_PASS,
            $options
        );
    } catch (PDOException) {
        $dsn = 'mysql:host=' . PHARMACY_DB_HOST . ';port=' . addtomar_env('DB_PORT', '3306') . ';charset=' . PHARMACY_DB_CHARSET;
        $pdo = new PDO($dsn, PHARMACY_DB_USER, PHARMACY_DB_PASS, $options);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . PHARMACY_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . PHARMACY_DB_NAME . '`');
    }

    addtomar_mysql_disable_ansi_quotes($pdo);
    pharmacy_run_migrations($pdo);

    return $pdo;
}

function pharmacy_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $tableStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $tableStmt->execute([PHARMACY_DB_NAME, $table]);
    if ((int) $tableStmt->fetchColumn() === 0) {
        return;
    }
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ');
    $stmt->execute([PHARMACY_DB_NAME, $table, $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
}

function pharmacy_run_migrations(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS pharmacy_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pharmacy_name VARCHAR(255) NOT NULL DEFAULT '',
            license_number VARCHAR(100) NOT NULL DEFAULT '',
            owner_name VARCHAR(255) NOT NULL DEFAULT '',
            address TEXT,
            email VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            opening_time TIME DEFAULT '08:00:00',
            closing_time TIME DEFAULT '21:00:00',
            staff_name VARCHAR(255) NOT NULL DEFAULT '',
            staff_role VARCHAR(100) NOT NULL DEFAULT 'Pharmacist',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS medicines (
            id INT PRIMARY KEY AUTO_INCREMENT,
            medicine_code VARCHAR(50) NULL,
            name VARCHAR(255) NOT NULL,
            generic_name VARCHAR(255) NOT NULL DEFAULT '',
            brand VARCHAR(255) NOT NULL DEFAULT '',
            dosage_form VARCHAR(100) NOT NULL DEFAULT '',
            strength VARCHAR(100) NOT NULL DEFAULT '',
            unit VARCHAR(50) NOT NULL DEFAULT '',
            category VARCHAR(100) NOT NULL DEFAULT '',
            dosage VARCHAR(255) NOT NULL DEFAULT '',
            manufacturer VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT,
            ingredients TEXT,
            batch_number VARCHAR(100) NOT NULL DEFAULT '',
            expiration_date DATE NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            minimum_stock INT NOT NULL DEFAULT 0,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            prescription_required TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            image_path VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_medicine_code (medicine_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

    pharmacy_ensure_column($pdo, 'medicines', 'medicine_code', 'VARCHAR(50) NULL AFTER id');
    pharmacy_ensure_column($pdo, 'medicines', 'dosage_form', "VARCHAR(100) NOT NULL DEFAULT '' AFTER brand");
    pharmacy_ensure_column($pdo, 'medicines', 'strength', "VARCHAR(100) NOT NULL DEFAULT '' AFTER dosage_form");
    pharmacy_ensure_column($pdo, 'medicines', 'unit', "VARCHAR(50) NOT NULL DEFAULT '' AFTER strength");
    // Packages may identify only an expiry month and year, e.g. 02/mm/2027.
    $expirationTypeStmt = $pdo->prepare('SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = "medicines" AND COLUMN_NAME = "expiration_date"');
    $expirationTypeStmt->execute([PHARMACY_DB_NAME]);
    if (strtolower((string) $expirationTypeStmt->fetchColumn()) === 'date') {
        $pdo->exec('ALTER TABLE medicines MODIFY expiration_date VARCHAR(20) NULL');
    }
    pharmacy_ensure_column($pdo, 'orders', 'prescription_path', 'VARCHAR(255) NULL AFTER notes');
    pharmacy_ensure_column($pdo, 'orders', 'pickup_proof_path', 'VARCHAR(255) NULL AFTER prescription_path');
    pharmacy_ensure_column($pdo, 'orders', 'cancellation_reason', 'TEXT NULL AFTER pickup_proof_path');
    pharmacy_ensure_column($pdo, 'orders', 'down_payment', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_amount');
    pharmacy_ensure_column($pdo, 'orders', 'paymongo_intent_id', 'VARCHAR(80) NULL AFTER notes');
    pharmacy_ensure_column($pdo, 'order_items', 'prescription_path', 'VARCHAR(255) NULL AFTER prescription_required');

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS customers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            status ENUM('active','inactive','vip') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS suppliers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255) NOT NULL DEFAULT '',
            medicines_count INT NOT NULL DEFAULT 0,
            last_delivery DATE NULL,
            on_time_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
            status ENUM('active','delayed','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS orders (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_number VARCHAR(50) NOT NULL UNIQUE,
            customer_id INT NULL,
            status ENUM('pending','confirmed','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(50) NOT NULL DEFAULT '',
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS order_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            medicine_id INT NULL,
            medicine_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            prescription_required TINYINT(1) NOT NULL DEFAULT 0,
            prescription_path VARCHAR(255) NULL,
            CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_items_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    // Apply compatibility columns after all base tables exist.
    pharmacy_ensure_column($pdo, 'orders', 'prescription_path', 'VARCHAR(255) NULL AFTER notes');
    pharmacy_ensure_column($pdo, 'orders', 'pickup_proof_path', 'VARCHAR(255) NULL AFTER prescription_path');
    pharmacy_ensure_column($pdo, 'orders', 'cancellation_reason', 'TEXT NULL AFTER pickup_proof_path');
    pharmacy_ensure_column($pdo, 'orders', 'down_payment', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER total_amount');
    pharmacy_ensure_column($pdo, 'orders', 'paymongo_intent_id', 'VARCHAR(80) NULL AFTER notes');
    pharmacy_ensure_column($pdo, 'order_items', 'prescription_path', 'VARCHAR(255) NULL AFTER prescription_required');

    pharmacy_ensure_column($pdo, 'medicines', 'pharmacy_id', 'VARCHAR(32) NULL AFTER id');
    pharmacy_ensure_column($pdo, 'medicines', 'is_featured', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
    pharmacy_ensure_column($pdo, 'orders', 'pharmacy_id', 'VARCHAR(32) NULL AFTER id');
    pharmacy_ensure_column($pdo, 'customers', 'pharmacy_id', 'VARCHAR(32) NULL AFTER id');
    pharmacy_ensure_column($pdo, 'suppliers', 'pharmacy_id', 'VARCHAR(32) NULL AFTER id');
    pharmacy_ensure_column($pdo, 'pharmacy_settings', 'pharmacy_id', 'VARCHAR(32) NULL AFTER id');

    $indexStmt = $pdo->prepare('
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
    ');

        $indexStmt->execute([PHARMACY_DB_NAME, 'medicines', 'uniq_medicine_code']);
    if ((int) $indexStmt->fetchColumn() > 0) {
        $pdo->exec('ALTER TABLE medicines DROP INDEX uniq_medicine_code');
    }

        $indexStmt->execute([PHARMACY_DB_NAME, 'medicines', 'uniq_pharmacy_medicine_code']);
    if ((int) $indexStmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE medicines ADD UNIQUE KEY uniq_pharmacy_medicine_code (pharmacy_id, medicine_code)');
    }

        $indexStmt->execute([PHARMACY_DB_NAME, 'pharmacy_settings', 'uniq_pharmacy_settings']);
    if ((int) $indexStmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE pharmacy_settings ADD UNIQUE KEY uniq_pharmacy_settings (pharmacy_id)');
    }

        $indexStmt->execute([PHARMACY_DB_NAME, 'medicines', 'idx_medicines_pharmacy']);
    if ((int) $indexStmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE medicines ADD KEY idx_medicines_pharmacy (pharmacy_id)');
    }

        $indexStmt->execute([PHARMACY_DB_NAME, 'orders', 'idx_orders_pharmacy']);
    if ((int) $indexStmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE orders ADD KEY idx_orders_pharmacy (pharmacy_id)');
    }

    $settingsCount = (int) $pdo->query('SELECT COUNT(*) FROM pharmacy_settings WHERE pharmacy_id IS NOT NULL AND pharmacy_id <> ""')->fetchColumn();
    if ($settingsCount === 0) {
        $legacyCount = (int) $pdo->query('SELECT COUNT(*) FROM pharmacy_settings')->fetchColumn();
        if ($legacyCount === 0) {
            // Rows are created per pharmacy when they sign in or save profile.
        }
    }

    pharmacy_seed_tgp_inventory($pdo);
}

function pharmacy_seed_tgp_inventory(PDO $pdo): void
{
    $pharmaciesExist = $pdo->query("SHOW TABLES LIKE 'pharmacies'")->fetch();
    if (!$pharmaciesExist) {
        return;
    }

    $find = $pdo->query("
        SELECT id FROM pharmacies
        WHERE LOWER(pharmacy_name) LIKE '%the generics pharmacy%'
           OR LOWER(pharmacy_name) LIKE '%tgp%'
        ORDER BY created_at ASC
    ");
    $pharmacyIds = $find ? $find->fetchAll(PDO::FETCH_COLUMN) : [];
    if ($pharmacyIds === []) {
        return;
    }

    $rows = [
        [
            'name' => 'Cetirizine 10mg',
            'generic_name' => 'Cetirizine',
            'brand' => 'Allerkast',
            'dosage_form' => 'Tablet',
            'strength' => '10mg',
            'unit' => 'Tablet',
            'category' => 'Antihistamine',
            'description' => 'Antihistamine used for allergy symptoms.',
            'ingredients' => "Active: Cetirizine hydrochloride\nInactive: Lactose monohydrate, microcrystalline cellulose, magnesium stearate",
            'batch_number' => 'CTZ-2603',
            'expiration_date' => '2028-06-30',
            'stock_quantity' => 180,
            'unit_price' => 3.50,
            'selling_price' => 5.00,
            'prescription_required' => 0,
        ],
        [
            'name' => 'Omeprazole 20mg',
            'generic_name' => 'Omeprazole',
            'brand' => 'Losec',
            'dosage_form' => 'Capsule',
            'strength' => '20mg',
            'unit' => 'Capsule',
            'category' => 'Gastrointestinal',
            'description' => 'Used to reduce stomach acid production.',
            'ingredients' => "Active: Omeprazole\nInactive: Lactose, sucrose, crospovidone, magnesium stearate, gelatin",
            'batch_number' => 'OMP-2604',
            'expiration_date' => '2028-03-31',
            'stock_quantity' => 90,
            'unit_price' => 6.00,
            'selling_price' => 8.50,
            'prescription_required' => 0,
        ],
        [
            'name' => 'Salbutamol Syrup 2mg/5mL',
            'generic_name' => 'Salbutamol',
            'brand' => 'Ventolin',
            'dosage_form' => 'Syrup',
            'strength' => '2mg/5mL',
            'unit' => 'Bottle',
            'category' => 'Respiratory',
            'description' => 'Bronchodilator medicine used for certain breathing conditions.',
            'ingredients' => "Active: Salbutamol sulfate\nInactive: Sucrose, sodium benzoate, citric acid, flavoring, purified water",
            'batch_number' => 'SAL-2605',
            'expiration_date' => '2027-09-30',
            'stock_quantity' => 45,
            'unit_price' => 75.00,
            'selling_price' => 95.00,
            'prescription_required' => 1,
        ],
        [
            'name' => 'Amlodipine 5mg',
            'generic_name' => 'Amlodipine',
            'brand' => 'Norvasc',
            'dosage_form' => 'Tablet',
            'strength' => '5mg',
            'unit' => 'Tablet',
            'category' => 'Cardiovascular',
            'description' => 'Calcium-channel blocker used for blood pressure management.',
            'ingredients' => "Active: Amlodipine besylate\nInactive: Microcrystalline cellulose, dibasic calcium phosphate, sodium starch glycolate, magnesium stearate",
            'batch_number' => 'AML-2606',
            'expiration_date' => '2028-12-31',
            'stock_quantity' => 200,
            'unit_price' => 2.50,
            'selling_price' => 4.00,
            'prescription_required' => 1,
        ],
        [
            'name' => 'Metformin 500mg',
            'generic_name' => 'Metformin',
            'brand' => 'Glucophage',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'unit' => 'Tablet',
            'category' => 'Diabetes',
            'description' => 'Medicine used as part of blood glucose management.',
            'ingredients' => "Active: Metformin hydrochloride\nInactive: Povidone, magnesium stearate, hypromellose, polyethylene glycol",
            'batch_number' => 'MET-2607',
            'expiration_date' => '2028-07-31',
            'stock_quantity' => 150,
            'unit_price' => 4.00,
            'selling_price' => 6.00,
            'prescription_required' => 1,
        ],
        [
            'name' => 'Vitamin C 500mg',
            'generic_name' => 'Ascorbic Acid',
            'brand' => 'Ceelin',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'unit' => 'Tablet',
            'category' => 'Vitamins & Supplements',
            'description' => 'Vitamin supplement containing ascorbic acid.',
            'ingredients' => "Active: Ascorbic acid\nInactive: Corn starch, povidone, stearic acid, magnesium stearate",
            'batch_number' => 'VTC-2608',
            'expiration_date' => '2029-10-31',
            'stock_quantity' => 275,
            'unit_price' => 3.00,
            'selling_price' => 5.00,
            'prescription_required' => 0,
        ],
        [
            'name' => 'Hydrocortisone Cream 1%',
            'generic_name' => 'Hydrocortisone',
            'brand' => 'Cortaid',
            'dosage_form' => 'Cream',
            'strength' => '1%',
            'unit' => 'Tube',
            'category' => 'Dermatology',
            'description' => 'Topical cream for temporary relief of minor skin irritation.',
            'ingredients' => "Active: Hydrocortisone\nInactive: Petrolatum, mineral oil, cetyl alcohol, emulsifying wax, purified water",
            'batch_number' => 'HYD-2609',
            'expiration_date' => '2028-05-31',
            'stock_quantity' => 65,
            'unit_price' => 45.00,
            'selling_price' => 60.00,
            'prescription_required' => 0,
        ],
        [
            'name' => 'Oral Rehydration Salts',
            'generic_name' => 'Oral Rehydration Salts',
            'brand' => 'Hydrite',
            'dosage_form' => 'Powder',
            'strength' => 'Standard',
            'unit' => 'Sachet',
            'category' => 'Oral Rehydration & Electrolytes',
            'description' => 'Powder intended to be prepared as an oral rehydration solution.',
            'ingredients' => "Active: Sodium chloride, potassium chloride, glucose\nInactive: Flavoring agent, permitted color",
            'batch_number' => 'ORS-2610',
            'expiration_date' => '2029-02-28',
            'stock_quantity' => 100,
            'unit_price' => 7.00,
            'selling_price' => 10.00,
            'prescription_required' => 0,
        ],
    ];

    $exists = $pdo->prepare('
        SELECT id FROM medicines
        WHERE pharmacy_id = ? AND (batch_number = ? OR medicine_code = ?)
        LIMIT 1
    ');
    $insert = $pdo->prepare('
        INSERT INTO medicines (
            pharmacy_id, medicine_code, name, generic_name, brand, dosage_form, strength, unit, category, dosage,
            description, ingredients, batch_number, expiration_date, stock_quantity, minimum_stock,
            unit_price, selling_price, prescription_required, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 1)
    ');

    foreach ($pharmacyIds as $pharmacyId) {
        $pharmacyId = trim((string) $pharmacyId);
        if ($pharmacyId === '') {
            continue;
        }

        foreach ($rows as $row) {
            $exists->execute([$pharmacyId, $row['batch_number'], $row['batch_number']]);
            if ($exists->fetch()) {
                continue;
            }

            $dosage = trim($row['strength'] . ' ' . $row['dosage_form']);
            $insert->execute([
                $pharmacyId,
                $row['batch_number'],
                $row['name'],
                $row['generic_name'],
                $row['brand'],
                $row['dosage_form'],
                $row['strength'],
                $row['unit'],
                $row['category'],
                $dosage,
                $row['description'],
                $row['ingredients'],
                $row['batch_number'],
                $row['expiration_date'],
                $row['stock_quantity'],
                $row['unit_price'],
                $row['selling_price'],
                $row['prescription_required'],
            ]);
        }
    }
}
