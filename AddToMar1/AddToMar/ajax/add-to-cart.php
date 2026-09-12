<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please log in as a customer.']); exit;
}

$medicine_id = (int)($_POST['medicine_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));
$customer_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ? AND status = 'active'");
$stmt->execute([$medicine_id]);
$med = $stmt->fetch();

if (!$med) {
    echo json_encode(['success' => false, 'message' => 'Medicine not found.']); exit;
}
if ($med['quantity'] < $quantity) {
    echo json_encode(['success' => false, 'message' => 'Not enough stock available.']); exit;
}

$existing = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ? AND medicine_id = ?");
$existing->execute([$customer_id, $medicine_id]);
$row = $existing->fetch();

if ($row) {
    $newQty = min($med['quantity'], $row['quantity'] + $quantity);
    $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?")->execute([$newQty, $row['id']]);
} else {
    $pdo->prepare("INSERT INTO cart (customer_id, medicine_id, quantity) VALUES (?, ?, ?)")->execute([$customer_id, $medicine_id, $quantity]);
}

echo json_encode([
    'success' => true,
    'message' => $med['medicine_name'] . ' added to cart.',
    'cart_count' => cart_count($pdo, $customer_id),
]);
