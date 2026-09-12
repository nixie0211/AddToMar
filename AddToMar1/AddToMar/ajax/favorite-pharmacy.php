<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

require_role('customer');

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid session token.']);
    exit;
}

$pharmacyId = (int)($_POST['pharmacy_id'] ?? 0);
$action = $_POST['action'] ?? 'toggle';

if (!$pharmacyId) {
    echo json_encode(['success' => false, 'message' => 'Pharmacy ID required.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($action === 'remove') {
    $pdo->prepare('DELETE FROM favorite_pharmacies WHERE user_id = ? AND pharmacy_id = ?')->execute([$userId, $pharmacyId]);
    echo json_encode(['success' => true, 'favorited' => false, 'message' => 'Removed from favorites.']);
    exit;
}

$exists = $pdo->prepare('SELECT id FROM favorite_pharmacies WHERE user_id = ? AND pharmacy_id = ?');
$exists->execute([$userId, $pharmacyId]);
if ($exists->fetch()) {
    $pdo->prepare('DELETE FROM favorite_pharmacies WHERE user_id = ? AND pharmacy_id = ?')->execute([$userId, $pharmacyId]);
    echo json_encode(['success' => true, 'favorited' => false, 'message' => 'Removed from favorites.']);
} else {
    $pdo->prepare('INSERT INTO favorite_pharmacies (user_id, pharmacy_id) VALUES (?, ?)')->execute([$userId, $pharmacyId]);
    echo json_encode(['success' => true, 'favorited' => true, 'message' => 'Pharmacy saved to favorites.']);
}
