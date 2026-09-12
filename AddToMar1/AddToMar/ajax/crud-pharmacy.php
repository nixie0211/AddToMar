<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid session token.']);
    exit;
}

$action = $_POST['action'] ?? 'save';

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("UPDATE pharmacies SET status = 'inactive' WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Pharmacy deactivated.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = clean($_POST['pharmacy_name'] ?? '');
$address = clean($_POST['address'] ?? '');
$lat = (float)($_POST['latitude'] ?? 0);
$lng = (float)($_POST['longitude'] ?? 0);
$contact = clean($_POST['contact_number'] ?? '');
$hours = clean($_POST['operating_hours'] ?? '');
$openTime = clean($_POST['open_time'] ?? '08:00');
$closeTime = clean($_POST['close_time'] ?? '20:00');
$status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

if (!$name || !$address || !$contact || !$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields including map coordinates.']);
    exit;
}

if ($id > 0) {
    $pdo->prepare('UPDATE pharmacies SET pharmacy_name=?, address=?, latitude=?, longitude=?, contact_number=?, operating_hours=?, open_time=?, close_time=?, status=? WHERE id=?')
        ->execute([$name, $address, $lat, $lng, $contact, $hours, $openTime, $closeTime, $status, $id]);
    echo json_encode(['success' => true, 'message' => 'Pharmacy updated successfully.']);
} else {
    $pdo->prepare('INSERT INTO pharmacies (pharmacy_name, address, latitude, longitude, contact_number, operating_hours, open_time, close_time, status) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$name, $address, $lat, $lng, $contact, $hours, $openTime, $closeTime, $status]);
    echo json_encode(['success' => true, 'message' => 'Pharmacy registered successfully.']);
}
