    -- =========================================================
    -- AddToMar - Pharmacy Inventory Management System
    -- Database Schema + Seed Data
    -- =========================================================

    CREATE DATABASE IF NOT EXISTS addtomar_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    USE addtomar_db;

    -- ---------------------------------------------------------
    -- Table: users
    -- ---------------------------------------------------------
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(150) NOT NULL,
        contact VARCHAR(20) DEFAULT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        address VARCHAR(255) DEFAULT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('customer','pharmacist') NOT NULL DEFAULT 'customer',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        reset_token VARCHAR(255) DEFAULT NULL,
        reset_expires DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;

    -- ---------------------------------------------------------
    -- Table: medicines
    -- ---------------------------------------------------------
    CREATE TABLE medicines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        medicine_code VARCHAR(30) NOT NULL UNIQUE,
        medicine_name VARCHAR(150) NOT NULL,
        generic_name VARCHAR(150) DEFAULT NULL,
        category VARCHAR(100) NOT NULL,
        description TEXT,
        dosage VARCHAR(100) DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        quantity INT NOT NULL DEFAULT 0,
        reorder_level INT NOT NULL DEFAULT 10,
        expiration_date DATE DEFAULT NULL,
        image VARCHAR(255) DEFAULT 'default-medicine.png',
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_name (medicine_name)
    ) ENGINE=InnoDB;

    -- ---------------------------------------------------------
    -- Table: orders
    -- ---------------------------------------------------------
    CREATE TABLE orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(30) NOT NULL UNIQUE,
        customer_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        down_payment DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        payment_proof VARCHAR(255) DEFAULT NULL,
        paymongo_checkout_id VARCHAR(100) DEFAULT NULL,
        payment_method VARCHAR(20) NOT NULL DEFAULT 'manual',
        payment_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
        payment_remarks VARCHAR(255) DEFAULT NULL,
        pickup_date DATE DEFAULT NULL,
        pickup_time TIME DEFAULT NULL,
        status ENUM('Pending','Approved','Ready For Pickup','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB;

    -- ---------------------------------------------------------
    -- Table: order_items
    -- ---------------------------------------------------------
    CREATE TABLE order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        medicine_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price DECIMAL(10,2) NOT NULL,
        CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        CONSTRAINT fk_items_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;

    -- ---------------------------------------------------------
    -- Table: notifications
    -- ---------------------------------------------------------
    CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        message VARCHAR(255) NOT NULL,
        status ENUM('unread','read') NOT NULL DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;

    -- ---------------------------------------------------------
    -- Table: settings
    -- ---------------------------------------------------------
    CREATE TABLE settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pharmacy_name VARCHAR(150) NOT NULL DEFAULT 'AddToMar Pharmacy',
        address VARCHAR(255) DEFAULT NULL,
        contact_number VARCHAR(20) DEFAULT NULL,
        logo VARCHAR(255) DEFAULT NULL,
        down_payment_percent INT NOT NULL DEFAULT 50
    ) ENGINE=InnoDB;

    -- ---------------------------------------------------------
    -- Table: cart
    -- ---------------------------------------------------------
    CREATE TABLE cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        medicine_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_cart_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_cart_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_cart_item (customer_id, medicine_id)
    ) ENGINE=InnoDB;

    -- =========================================================
    -- SEED DATA
    -- =========================================================

    -- Default Pharmacist Account (username: admin / password: admin123)
    INSERT INTO users (fullname, contact, email, address, username, password, role) VALUES
    ('System Administrator', '09171234567', 'admin@addtomar.com', 'AddToMar Pharmacy, Main St.', 'admin',
    '$2y$10$YkZDVmp9cJVJZkM.vvP7JefOVMNAECbp4ArGvQj73DzjuJ752uaOm', 'pharmacist');
    -- NOTE: hash above corresponds to "admin123" (bcrypt). The app also re-verifies with PHP password_hash on register.

    -- Sample customer
    INSERT INTO users (fullname, contact, email, address, username, password, role) VALUES
    ('Juan Dela Cruz', '09981234567', 'juan@example.com', 'Davila, Ilocos', 'juan',
    '$2y$10$YkZDVmp9cJVJZkM.vvP7JefOVMNAECbp4ArGvQj73DzjuJ752uaOm', 'customer');

    -- Default settings row
    INSERT INTO settings (pharmacy_name, address, contact_number, down_payment_percent) VALUES
    ('AddToMar Pharmacy', '123 Health Street, Davila, Ilocos', '09171234567', 50);

    -- Sample medicines
    INSERT INTO medicines (medicine_code, medicine_name, generic_name, category, description, dosage, price, quantity, reorder_level, expiration_date, image) VALUES
    ('MED-0001', 'Biogesic 500mg', 'Paracetamol', 'Pain Reliever', 'Relieves mild to moderate pain and reduces fever.', '500mg Tablet', 5.50, 150, 20, '2027-06-30', 'default-medicine.png'),
    ('MED-0002', 'Neozep Forte', 'Phenylephrine + Paracetamol', 'Cough and Cold', 'Relieves colds, cough, and flu symptoms.', 'Tablet', 8.00, 100, 20, '2027-03-15', 'default-medicine.png'),
    ('MED-0003', 'Kremil-S', 'Antacid', 'Antacid', 'Relieves hyperacidity, heartburn, and stomach discomfort.', 'Tablet', 9.75, 80, 15, '2026-12-01', 'default-medicine.png'),
    ('MED-0004', 'Cetirizine 10mg', 'Cetirizine HCl', 'Allergy Medicine', 'Relieves allergy symptoms such as sneezing and itching.', '10mg Tablet', 6.25, 60, 15, '2027-01-20', 'default-medicine.png'),
    ('MED-0005', 'Vitamin C 500mg', 'Ascorbic Acid', 'Vitamins', 'Boosts immune system.', '500mg Tablet', 4.00, 200, 30, '2027-09-10', 'default-medicine.png'),
    ('MED-0006', 'Centrum Advance', 'Multivitamins', 'Supplements', 'Complete multivitamin and mineral supplement.', 'Tablet', 15.00, 50, 10, '2027-05-05', 'default-medicine.png'),
    ('MED-0007', 'Betadine Solution', 'Povidone-Iodine', 'First Aid', 'Antiseptic solution for wounds.', '60ml Bottle', 65.00, 40, 10, '2027-08-18', 'default-medicine.png'),
    ('MED-0008', 'Bioflu', 'Phenylephrine + Paracetamol + Chlorphenamine', 'OTC Medicines', 'Relieves flu symptoms.', 'Tablet', 8.50, 90, 20, '2026-11-25', 'default-medicine.png'),
    ('MED-0009', 'Diatabs', 'Loperamide HCl', 'First Aid', 'Controls diarrhea symptoms.', '2mg Capsule', 7.00, 8, 15, '2026-10-05', 'default-medicine.png'),
    ('MED-0010', 'Alaxan FR', 'Ibuprofen + Paracetamol', 'Pain Reliever', 'Fast relief from body pain.', 'Tablet', 6.75, 5, 15, '2026-09-30', 'default-medicine.png');
