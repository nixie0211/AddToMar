<?php

declare(strict_types=1);

function pharmacy_seed_partner_pharmacies_once(PDO $pdo): void
{
    try {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS app_meta (
                meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
                meta_value VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $meta = $pdo->prepare('SELECT meta_value FROM app_meta WHERE meta_key = ? LIMIT 1');
        $meta->execute(['seed_partner_pharmacies_v1']);
        if ($meta->fetchColumn()) {
            return;
        }
    } catch (Throwable) {
        return;
    }

    $pharmaciesExist = $pdo->query("SHOW TABLES LIKE 'pharmacies'")->fetch();
    $medicinesExist = $pdo->query("SHOW TABLES LIKE 'medicines'")->fetch();
    if (!$pharmaciesExist || !$medicinesExist) {
        return;
    }

    pharmacy_ensure_demo_pharmacy($pdo, [
        'id' => 'f4d04eb0aac0bd74',
        'email' => 'tgp@gmail.com',
        'pharmacy_name' => 'TGP Pharmacy',
        'contact_number' => '09020250501',
        'address' => 'Madamba Road, Nangalisan East, San Nicolas',
        'latitude' => 18.1735849,
        'longitude' => 120.5928847,
        'password' => 'TGP2026!',
        'name_like' => '%tgp%',
    ]);
    pharmacy_ensure_demo_pharmacy($pdo, [
        'id' => 'f0d7169a9b7c5777',
        'email' => 'stjoseph@addtomar.local',
        'pharmacy_name' => 'St. Joseph Drugstore',
        'contact_number' => '0777701234',
        'address' => 'National Highway, San Nicolas, Ilocos Norte',
        'latitude' => 18.1762100,
        'longitude' => 120.5954100,
        'password' => 'StJoseph2026!',
        'name_like' => '%joseph%',
    ]);

    pharmacy_seed_tgp_inventory($pdo);
    pharmacy_seed_st_joseph_inventory($pdo);

    $mark = $pdo->prepare('INSERT INTO app_meta (meta_key, meta_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)');
    $mark->execute(['seed_partner_pharmacies_v1', date('c')]);
}

function pharmacy_ensure_demo_pharmacy(PDO $pdo, array $row): void
{
    $email = strtolower(trim((string) ($row['email'] ?? '')));
    $name = trim((string) ($row['pharmacy_name'] ?? ''));
    if ($email === '' || $name === '') {
        return;
    }

    $find = $pdo->prepare('SELECT id FROM pharmacies WHERE LOWER(email) = ? LIMIT 1');
    $find->execute([$email]);
    if ($find->fetchColumn()) {
        $pdo->prepare('UPDATE pharmacies SET status = ? WHERE LOWER(email) = ? AND status <> ?')
            ->execute(['approved', $email, 'approved']);
        return;
    }

    $like = $pdo->prepare('SELECT id FROM pharmacies WHERE LOWER(pharmacy_name) LIKE ? LIMIT 1');
    $like->execute([(string) ($row['name_like'] ?? ('%' . strtolower($name) . '%'))]);
    if ($like->fetchColumn()) {
        return;
    }

    $id = trim((string) ($row['id'] ?? ''));
    if ($id === '') {
        $id = bin2hex(random_bytes(8));
    }
    $idCheck = $pdo->prepare('SELECT id FROM pharmacies WHERE id = ? LIMIT 1');
    $idCheck->execute([$id]);
    if ($idCheck->fetchColumn()) {
        $id = bin2hex(random_bytes(8));
    }

    $history = json_encode([['status' => 'approved', 'at' => date('c')]], JSON_UNESCAPED_SLASHES);
    $insert = $pdo->prepare('
        INSERT INTO pharmacies (
            id, email, pharmacy_name, contact_number, address, latitude, longitude, password_hash,
            open_time, close_time, status, status_history
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $insert->execute([
        $id,
        $email,
        $name,
        (string) ($row['contact_number'] ?? ''),
        (string) ($row['address'] ?? ''),
        $row['latitude'] ?? null,
        $row['longitude'] ?? null,
        password_hash((string) ($row['password'] ?? bin2hex(random_bytes(8))), PASSWORD_DEFAULT),
        '08:00',
        '20:00',
        'approved',
        $history,
    ]);
}

function pharmacy_seed_st_joseph_inventory(PDO $pdo): void
{
    $find = $pdo->query("
        SELECT id FROM pharmacies
        WHERE LOWER(pharmacy_name) LIKE '%joseph%'
           OR LOWER(email) = 'stjoseph@addtomar.local'
        ORDER BY created_at ASC
    ");
    $pharmacyIds = $find ? $find->fetchAll(PDO::FETCH_COLUMN) : [];
    if ($pharmacyIds === []) {
        return;
    }

    $rows = [
        ['Paracetamol 500mg', 'Paracetamol', 'Biogesic', 'Tablet', '500mg', 'Tablet', 'Analgesics & Antipyretics', 'Pain and fever relief.', 'PCM-SJ01', '2028-11-30', 320, 1.20, 2.00, 0, 1],
        ['Ibuprofen 400mg', 'Ibuprofen', 'Advil', 'Tablet', '400mg', 'Tablet', 'Analgesics & Antipyretics', 'Pain, inflammation, and fever.', 'IBU-SJ02', '2028-08-31', 210, 3.25, 5.50, 0, 1],
        ['Mefenamic Acid 500mg', 'Mefenamic Acid', 'Ponstan', 'Capsule', '500mg', 'Capsule', 'Analgesics & Antipyretics', 'Short-term mild to moderate pain.', 'MEF-SJ03', '2028-01-31', 140, 4.00, 6.50, 0, 0],
        ['Amoxicillin 500mg', 'Amoxicillin', 'Amoxil', 'Capsule', '500mg', 'Capsule', 'Antibiotics', 'For certain bacterial infections.', 'AMX-SJ04', '2027-08-31', 110, 8.50, 12.00, 1, 1],
        ['Co-Amoxiclav 625mg', 'Amoxicillin + Clavulanic Acid', 'Augmentin', 'Tablet', '625mg', 'Tablet', 'Antibiotics', 'Combination antibiotic.', 'CAV-SJ05', '2027-12-31', 75, 18.00, 25.00, 1, 0],
        ['Cefalexin 500mg', 'Cefalexin', 'Cefalin', 'Capsule', '500mg', 'Capsule', 'Antibiotics', 'Cephalosporin antibiotic.', 'CEF-SJ06', '2028-03-31', 90, 12.00, 16.50, 1, 0],
        ['Cetirizine 10mg', 'Cetirizine', 'Virlix', 'Tablet', '10mg', 'Tablet', 'Antihistamines', 'Allergy symptom relief.', 'CTZ-SJ07', '2028-06-30', 180, 3.50, 5.00, 0, 1],
        ['Loratadine 10mg', 'Loratadine', 'Claritin', 'Tablet', '10mg', 'Tablet', 'Antihistamines', 'Non-drowsy allergy relief.', 'LOR-SJ08', '2028-09-30', 150, 6.00, 9.00, 0, 0],
        ['Omeprazole 20mg', 'Omeprazole', 'Losec', 'Capsule', '20mg', 'Capsule', 'Gastrointestinal', 'Reduces stomach acid.', 'OMP-SJ09', '2028-04-30', 160, 5.50, 8.00, 0, 1],
        ['Loperamide 2mg', 'Loperamide', 'Imodium', 'Capsule', '2mg', 'Capsule', 'Gastrointestinal', 'Short-term diarrhea control.', 'LOP-SJ10', '2029-03-31', 95, 6.00, 9.00, 0, 0],
        ['Dicycloverine 10mg', 'Dicycloverine', 'Relestal', 'Tablet', '10mg', 'Tablet', 'Gastrointestinal', 'For abdominal cramps.', 'DIC-SJ11', '2028-02-29', 100, 3.75, 6.00, 1, 0],
        ['Hyoscine 10mg', 'Hyoscine N-butylbromide', 'Buscopan', 'Tablet', '10mg', 'Tablet', 'Gastrointestinal', 'Antispasmodic for stomach pain.', 'HYO-SJ12', '2028-07-31', 120, 8.00, 12.00, 0, 0],
        ['Salbutamol Syrup 2mg/5mL', 'Salbutamol', 'Ventolin', 'Syrup', '2mg/5mL', 'Bottle', 'Respiratory', 'Bronchodilator syrup.', 'SAL-SJ13', '2027-09-30', 40, 75.00, 95.00, 1, 0],
        ['Lagundi 300mg', 'Vitex negundo', 'Ascof', 'Tablet', '300mg', 'Tablet', 'Respiratory', 'Herbal cough and cold support.', 'LAG-SJ14', '2028-10-31', 200, 5.50, 8.00, 0, 1],
        ['Carbocisteine 500mg', 'Carbocisteine', 'Solmux', 'Capsule', '500mg', 'Capsule', 'Respiratory', 'Helps loosen phlegm.', 'CAR-SJ15', '2028-07-31', 170, 7.00, 10.50, 0, 0],
        ['Amlodipine 5mg', 'Amlodipine', 'Norvasc', 'Tablet', '5mg', 'Tablet', 'Cardiovascular', 'Blood pressure management.', 'AML-SJ16', '2028-12-31', 190, 2.50, 4.00, 1, 1],
        ['Losartan 50mg', 'Losartan', 'Lifezar', 'Tablet', '50mg', 'Tablet', 'Cardiovascular', 'Angiotensin receptor blocker.', 'LOS-SJ17', '2028-09-30', 155, 4.50, 7.00, 1, 0],
        ['Simvastatin 20mg', 'Simvastatin', 'Vidastat', 'Tablet', '20mg', 'Tablet', 'Cardiovascular', 'Cholesterol management.', 'SIM-SJ18', '2028-04-30', 130, 5.00, 8.00, 1, 0],
        ['Metformin 500mg', 'Metformin', 'Glucophage', 'Tablet', '500mg', 'Tablet', 'Antidiabetic', 'Blood glucose management.', 'MET-SJ19', '2028-07-31', 145, 4.00, 6.00, 1, 1],
        ['Gliclazide 80mg', 'Gliclazide', 'Diamicron', 'Tablet', '80mg', 'Tablet', 'Antidiabetic', 'Oral antidiabetic tablet.', 'GLI-SJ20', '2028-11-30', 80, 7.50, 11.00, 1, 0],
        ['Vitamin C 500mg', 'Ascorbic Acid', 'Ceelin', 'Tablet', '500mg', 'Tablet', 'Vitamins & Supplements', 'Vitamin C supplement.', 'VTC-SJ21', '2029-10-31', 260, 3.00, 5.00, 0, 1],
        ['Multivitamins Tablet', 'Multivitamins', 'Enervon', 'Tablet', 'Standard', 'Tablet', 'Vitamins & Supplements', 'Daily multivitamin support.', 'MVT-SJ22', '2029-06-30', 280, 4.50, 7.50, 0, 0],
        ['Vitamin B-Complex', 'Vitamin B-Complex', 'Neurobion', 'Tablet', 'Standard', 'Tablet', 'Vitamins & Supplements', 'B-complex supplement.', 'VTB-SJ23', '2029-01-31', 170, 8.00, 12.00, 0, 0],
        ['Hydrocortisone Cream 1%', 'Hydrocortisone', 'Cortaid', 'Cream', '1%', 'Tube', 'Dermatology', 'Minor skin irritation cream.', 'HYD-SJ24', '2028-05-31', 60, 45.00, 60.00, 0, 0],
        ['Mupirocin Ointment 2%', 'Mupirocin', 'Bactroban', 'Ointment', '2%', 'Tube', 'Dermatology', 'Topical antibacterial ointment.', 'MUP-SJ25', '2028-02-28', 45, 85.00, 110.00, 1, 0],
        ['Oral Rehydration Salts', 'Oral Rehydration Salts', 'Hydrite', 'Powder', 'Standard', 'Sachet', 'Oral Rehydration & Electrolytes', 'Oral rehydration sachet.', 'ORS-SJ26', '2029-02-28', 100, 7.00, 10.00, 0, 0],
        ['Zinc Sulfate 20mg', 'Zinc Sulfate', 'E-Zinc', 'Tablet', '20mg', 'Tablet', 'Vitamins & Supplements', 'Zinc supplement.', 'ZNC-SJ27', '2029-04-30', 130, 4.00, 6.50, 0, 0],
        ['Aspirin 80mg', 'Acetylsalicylic Acid', 'Aspilet', 'Tablet', '80mg', 'Tablet', 'Cardiovascular', 'Low-dose aspirin tablet.', 'ASP-SJ28', '2028-06-30', 200, 2.00, 3.50, 0, 0],
        ['Domperidone 10mg', 'Domperidone', 'Motilium', 'Tablet', '10mg', 'Tablet', 'Gastrointestinal', 'For nausea and bloating.', 'DOM-SJ29', '2028-08-31', 85, 6.50, 9.50, 0, 0],
        ['Clotrimazole Cream 1%', 'Clotrimazole', 'Canesten', 'Cream', '1%', 'Tube', 'Dermatology', 'Antifungal cream.', 'CLT-SJ30', '2028-12-31', 55, 55.00, 72.00, 0, 0],
    ];

    $exists = $pdo->prepare('
        SELECT id FROM medicines
        WHERE pharmacy_id = ? AND (batch_number = ? OR medicine_code = ?)
        LIMIT 1
    ');
    $insert = $pdo->prepare('
        INSERT INTO medicines (
            pharmacy_id, medicine_code, name, generic_name, brand, dosage_form, strength, unit, category, dosage,
            manufacturer, description, ingredients, batch_number, expiration_date, stock_quantity, minimum_stock,
            unit_price, selling_price, prescription_required, is_active, is_featured, listed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 10, ?, ?, ?, 1, ?, NOW())
    ');

    foreach ($pharmacyIds as $pharmacyId) {
        $pharmacyId = trim((string) $pharmacyId);
        if ($pharmacyId === '') {
            continue;
        }
        foreach ($rows as $row) {
            [
                $name, $generic, $brand, $form, $strength, $unit, $category, $description,
                $code, $expiry, $stock, $unitPrice, $selling, $rx, $featured
            ] = $row;
            $exists->execute([$pharmacyId, $code, $code]);
            if ($exists->fetch()) {
                continue;
            }
            $insert->execute([
                $pharmacyId,
                $code,
                $name,
                $generic,
                $brand,
                $form,
                $strength,
                $unit,
                $category,
                trim($strength . ' ' . $form),
                $brand,
                $description,
                'Active: ' . $generic,
                $code,
                $expiry,
                $stock,
                $unitPrice,
                $selling,
                $rx,
                $featured,
            ]);
        }
    }
}
