-- Medicine Finder & Multi-Pharmacy Module
USE addtomar_db;

-- Extend medicines with leaflet fields
ALTER TABLE medicines ADD COLUMN uses_info TEXT NULL AFTER description;
ALTER TABLE medicines ADD COLUMN dosage_instructions TEXT NULL AFTER uses_info;
ALTER TABLE medicines ADD COLUMN side_effects TEXT NULL AFTER dosage_instructions;
ALTER TABLE medicines ADD COLUMN warnings TEXT NULL AFTER side_effects;
ALTER TABLE medicines ADD COLUMN storage_info TEXT NULL AFTER warnings;
ALTER TABLE medicines ADD COLUMN manufacturer VARCHAR(150) NULL AFTER storage_info;
ALTER TABLE medicines ADD COLUMN leaflet_file VARCHAR(255) NULL AFTER manufacturer;
ALTER TABLE medicines ADD FULLTEXT INDEX ft_medicine_search (medicine_name, generic_name);

-- Pharmacies network
CREATE TABLE IF NOT EXISTS pharmacies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    operating_hours VARCHAR(100) NOT NULL DEFAULT 'Mon-Sat 8:00 AM - 8:00 PM',
    open_time TIME DEFAULT '08:00:00',
    close_time TIME DEFAULT '20:00:00',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_coords (latitude, longitude)
) ENGINE=InnoDB;

-- Per-pharmacy inventory
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id INT NOT NULL,
    medicine_id INT NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_pharmacy_medicine (pharmacy_id, medicine_id),
    INDEX idx_stock (stock_quantity)
) ENGINE=InnoDB;

-- Search analytics
CREATE TABLE IF NOT EXISTS search_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    search_term VARCHAR(150) NOT NULL,
    medicine_id INT DEFAULT NULL,
    results_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_sa_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE SET NULL,
    INDEX idx_term (search_term),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Customer favorite pharmacies
CREATE TABLE IF NOT EXISTS favorite_pharmacies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pharmacy_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fp_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_fav (user_id, pharmacy_id)
) ENGINE=InnoDB;

-- Customer search history
CREATE TABLE IF NOT EXISTS search_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_term VARCHAR(150) NOT NULL,
    medicine_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sh_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE SET NULL,
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB;

-- Link pharmacist to pharmacy (optional)
ALTER TABLE users ADD COLUMN pharmacy_id INT NULL AFTER role;

-- Seed pharmacies (Ilocos Norte area sample coordinates)
INSERT INTO pharmacies (pharmacy_name, address, latitude, longitude, contact_number, operating_hours, open_time, close_time) VALUES
('AddToMar Pharmacy Main', '123 Health Street, Davila, Bacarra, Ilocos Norte', 18.28950000, 120.66780000, '09171234567', 'Mon-Sat 8:00 AM - 8:00 PM', '08:00:00', '20:00:00'),
('HealthPlus Drugstore', 'National Highway, Laoag City, Ilocos Norte', 18.19780000, 120.59370000, '09181234567', 'Daily 7:00 AM - 9:00 PM', '07:00:00', '21:00:00'),
('MedCare Pharmacy', 'Barangay 1, Batac City, Ilocos Norte', 18.05540000, 120.56490000, '09191234567', 'Mon-Sun 8:00 AM - 7:00 PM', '08:00:00', '19:00:00'),
('QuickMeds Express', 'Poblacion, Pagudpud, Ilocos Norte', 18.56160000, 120.78750000, '09201234567', 'Mon-Sat 9:00 AM - 6:00 PM', '09:00:00', '18:00:00'),
('Family Rx Pharmacy', 'San Nicolas, Ilocos Norte', 18.17250000, 120.59580000, '09211234567', 'Mon-Fri 8:00 AM - 5:00 PM', '08:00:00', '17:00:00');

-- Sync inventory from existing medicines to main pharmacy (id=1)
INSERT INTO inventory (pharmacy_id, medicine_id, stock_quantity, price)
SELECT 1, id, quantity, price FROM medicines
ON DUPLICATE KEY UPDATE stock_quantity = VALUES(stock_quantity), price = VALUES(price);

-- Vary stock across pharmacies for demo
UPDATE inventory SET stock_quantity = 0 WHERE pharmacy_id = 2 AND medicine_id IN (SELECT id FROM medicines WHERE generic_name LIKE '%Paracetamol%' LIMIT 1);
UPDATE inventory SET stock_quantity = FLOOR(stock_quantity * 0.6) WHERE pharmacy_id = 3;
UPDATE inventory SET stock_quantity = 0 WHERE pharmacy_id = 4 AND medicine_id IN (SELECT id FROM medicines WHERE medicine_code = 'MED-0009');
UPDATE inventory SET stock_quantity = FLOOR(stock_quantity * 0.8) WHERE pharmacy_id = 5;

-- Leaflet sample data for Paracetamol / Biogesic
UPDATE medicines SET
    uses_info = 'Relieves mild to moderate pain including headache, toothache, muscle pain, and reduces fever.',
    dosage_instructions = 'Adults: 1-2 tablets every 4-6 hours as needed. Maximum 8 tablets in 24 hours. Take with water after meals.',
    side_effects = 'Rare: skin rash, nausea. Stop use and consult a doctor if allergic reaction occurs.',
    warnings = 'Do not exceed recommended dose. Avoid alcohol. Consult doctor if pregnant, breastfeeding, or have liver disease.',
    storage_info = 'Store below 30°C in a dry place. Keep out of reach of children.',
    manufacturer = 'Unilab Inc.'
WHERE generic_name LIKE '%Paracetamol%' OR medicine_name LIKE '%Biogesic%';

UPDATE medicines SET
    uses_info = 'Relieves allergy symptoms including sneezing, runny nose, and itchy eyes.',
    dosage_instructions = 'Adults and children 12+: 1 tablet once daily.',
    side_effects = 'Drowsiness, dry mouth, headache in some patients.',
    warnings = 'May cause drowsiness. Do not drive if affected.',
    storage_info = 'Store at room temperature away from moisture.',
    manufacturer = 'Various'
WHERE generic_name LIKE '%Cetirizine%';
