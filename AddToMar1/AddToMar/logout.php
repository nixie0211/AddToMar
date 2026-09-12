<?php
require_once __DIR__ . '/includes/auth.php';
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
header('Location: ' . BASE_URL . 'login.php');
exit;
