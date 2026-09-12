<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pharmacy.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$q = trim($_GET['q'] ?? $_POST['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'medicines' => [], 'message' => 'Type at least 2 characters.']);
    exit;
}

$like = '%' . $q . '%';
$stmt = $pdo->prepare("SELECT m.id, m.medicine_name, m.generic_name, m.dosage, m.description,
                              m.uses_info, m.dosage_instructions, m.side_effects, m.warnings,
                              m.storage_info, m.manufacturer, m.leaflet_file, m.image,
                              MIN(i.price) AS min_price,
                              SUM(i.stock_quantity) AS total_stock
                       FROM medicines m
                       LEFT JOIN inventory i ON i.medicine_id = m.id AND i.stock_quantity > 0
                       LEFT JOIN pharmacies p ON p.id = i.pharmacy_id AND p.status = 'active'
                       WHERE m.status = 'active'
                         AND (m.medicine_name LIKE ? OR m.generic_name LIKE ? OR m.medicine_code LIKE ?)
                       GROUP BY m.id
                       ORDER BY m.medicine_name ASC
                       LIMIT 20");
$stmt->execute([$like, $like, $like]);
$medicines = $stmt->fetchAll();

$userId = (int)($_SESSION['user_id'] ?? 0);
$firstId = $medicines[0]['id'] ?? null;
log_medicine_search($pdo, $userId ?: null, $q, $firstId ? (int)$firstId : null, count($medicines));

foreach ($medicines as &$m) {
    $m['image_url'] = UPLOAD_MED_URL . ($m['image'] ?: 'default-medicine.png');
    $m['min_price'] = $m['min_price'] !== null ? (float)$m['min_price'] : (float)($m['price'] ?? 0);
    $m['total_stock'] = (int)($m['total_stock'] ?? 0);
    $m['in_stock'] = $m['total_stock'] > 0;
}
unset($m);

echo json_encode(['success' => true, 'query' => $q, 'medicines' => $medicines]);
