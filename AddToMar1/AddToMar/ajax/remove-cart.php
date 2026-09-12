<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please log in as a customer.']); exit;
}

$customer_id = $_SESSION['user_id'];

if (!empty($_POST['clear'])) {
    $pdo->prepare("DELETE FROM cart WHERE customer_id = ?")->execute([$customer_id]);
    echo json_encode(['success' => true, 'message' => 'Cart cleared.', 'cart_count' => 0]);
    exit;
}

$cart_id = (int)($_POST['cart_id'] ?? 0);
$pdo->prepare("DELETE FROM cart WHERE id = ? AND customer_id = ?")->execute([$cart_id, $customer_id]);

echo json_encode(['success' => true, 'message' => 'Item removed.', 'cart_count' => cart_count($pdo, $customer_id)]);
