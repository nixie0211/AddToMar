<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please log in as a customer.']); exit;
}

$cart_id = (int)($_POST['cart_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));
$customer_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT c.*, m.quantity AS stock FROM cart c JOIN medicines m ON m.id = c.medicine_id WHERE c.id = ? AND c.customer_id = ?");
$stmt->execute([$cart_id, $customer_id]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Cart item not found.']); exit;
}
if ($quantity > $row['stock']) {
    echo json_encode(['success' => false, 'message' => 'Only ' . $row['stock'] . ' in stock.']); exit;
}

$pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?")->execute([$quantity, $cart_id]);

echo json_encode(['success' => true, 'cart_count' => cart_count($pdo, $customer_id)]);
