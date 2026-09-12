<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if ($_SESSION['role'] === 'pharmacist') {
        header('Location: ' . BASE_URL . 'pharmacist/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'customer/dashboard.php');
    }
} else {
    header('Location: ' . BASE_URL . 'login.php');
}
exit;
