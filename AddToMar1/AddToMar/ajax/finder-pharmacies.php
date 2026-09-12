<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pharmacy.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$medicineId = (int)($_GET['medicine_id'] ?? 0);
$pharmacyId = (int)($_GET['pharmacy_id'] ?? 0);
$userLat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$userLng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

if (!$medicineId) {
    echo json_encode(['success' => false, 'message' => 'Medicine ID required.']);
    exit;
}

$medStmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ? AND status = 'active'");
$medStmt->execute([$medicineId]);
$medicine = $medStmt->fetch();
if (!$medicine) {
    echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
    exit;
}

$pharmacies = get_pharmacies_with_stock($pdo, $medicineId, $userLat, $userLng);

$userId = (int)($_SESSION['user_id'] ?? 0);
$favIds = [];
if ($userId) {
    $favStmt = $pdo->prepare('SELECT pharmacy_id FROM favorite_pharmacies WHERE user_id = ?');
    $favStmt->execute([$userId]);
    $favIds = array_column($favStmt->fetchAll(), 'pharmacy_id');
}

$selected = null;
$alternatives = [];
$outOfStockMessage = null;

foreach ($pharmacies as &$p) {
    $p['is_favorite'] = in_array((int)$p['id'], array_map('intval', $favIds), true);
    $p['is_open'] = pharmacy_is_open($p);
    $p['travel_time'] = $p['distance_km'] !== null ? format_travel_time((float)$p['distance_km']) : null;
    $p['navigate_url'] = maps_navigate_url((float)$p['latitude'], (float)$p['longitude'], $userLat, $userLng);
}
unset($p);

if ($pharmacyId) {
    foreach ($pharmacies as $p) {
        if ((int)$p['id'] === $pharmacyId) {
            $selected = $p;
            break;
        }
    }
    if (!$selected) {
        $chk = $pdo->prepare('SELECT p.*, COALESCE(i.stock_quantity,0) AS stock_quantity, COALESCE(i.price,0) AS pharmacy_price
                              FROM pharmacies p
                              LEFT JOIN inventory i ON i.pharmacy_id = p.id AND i.medicine_id = ?
                              WHERE p.id = ? AND p.status = "active"');
        $chk->execute([$medicineId, $pharmacyId]);
        $selected = $chk->fetch();
        if ($selected) {
            $selected['is_open'] = pharmacy_is_open($selected);
            $selected['distance_km'] = ($userLat !== null && $userLng !== null)
                ? distance_km($userLat, $userLng, (float)$selected['latitude'], (float)$selected['longitude'])
                : null;
            $selected['travel_time'] = $selected['distance_km'] !== null ? format_travel_time((float)$selected['distance_km']) : null;
            $selected['navigate_url'] = maps_navigate_url(
                (float)$selected['latitude'],
                (float)$selected['longitude'],
                $userLat,
                $userLng
            );
            if ((int)($selected['stock_quantity'] ?? 0) === 0) {
                $outOfStockMessage = 'This medicine is currently out of stock in your selected pharmacy.';
                $alternatives = $pharmacies;
            }
        }
    }
}

$nearest = $pharmacies[0] ?? null;

echo json_encode([
    'success'              => true,
    'medicine'             => [
        'id'           => (int)$medicine['id'],
        'medicine_name'=> $medicine['medicine_name'],
        'generic_name' => $medicine['generic_name'],
        'dosage'       => $medicine['dosage'],
        'image_url'    => UPLOAD_MED_URL . ($medicine['image'] ?: 'default-medicine.png'),
    ],
    'pharmacies'           => $pharmacies,
    'selected_pharmacy'    => $selected,
    'nearest_with_stock'   => $nearest,
    'alternatives'         => $alternatives,
    'out_of_stock_message' => $outOfStockMessage,
    'total_pharmacies'     => count($pharmacies),
]);
