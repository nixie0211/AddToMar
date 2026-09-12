<?php
/**
 * AddToMar - Auth & Helper Functions
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// ---------- CSRF ----------
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// ---------- Session helpers ----------
function is_logged_in() {
    return isset($_SESSION['user_id']);
}
function current_user() {
    if (!is_logged_in()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'fullname' => $_SESSION['fullname'],
        'username' => $_SESSION['username'],
        'email'    => $_SESSION['email'],
        'role'     => $_SESSION['role'],
    ];
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}
function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

// ---------- Sanitization ----------
function clean($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// ---------- Notifications ----------
function add_notification($pdo, $user_id, $title, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $title, $message]);
}
function notify_all_pharmacists($pdo, $title, $message) {
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'pharmacist'");
    foreach ($stmt->fetchAll() as $row) {
        add_notification($pdo, $row['id'], $title, $message);
    }
}
function unread_notification_count($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND status = 'unread'");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetch()['c'];
}

// ---------- Orders ----------
function generate_order_number($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE order_number LIKE ?");
    $stmt->execute(["ADM-$year-%"]);
    $count = (int)$stmt->fetch()['c'] + 1;
    return sprintf("ADM-%s-%04d", $year, $count);
}

// ---------- Cart ----------
function cart_count($pdo, $customer_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) c FROM cart WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    return (int)$stmt->fetch()['c'];
}

// ---------- Formatting ----------
function money($n) {
    return '₱' . number_format((float)$n, 2);
}
function status_badge_class($status) {
    $map = [
        'Pending'          => 'badge-pending',
        'Approved'         => 'badge-approved',
        'Ready For Pickup' => 'badge-ready',
        'Completed'        => 'badge-completed',
        'Cancelled'        => 'badge-cancelled',
    ];
    return $map[$status] ?? 'badge-pending';
}
