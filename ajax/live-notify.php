<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/live-sync.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requested = strtolower(trim((string) ($_GET['portal'] ?? '')));
$sessionPortal = live_sync_portal();
$portal = in_array($requested, ['residence', 'pharmacy', 'admin'], true) ? $requested : $sessionPortal;

if (!in_array($portal, ['residence', 'pharmacy', 'admin'], true) || $sessionPortal !== $portal) {
    http_response_code(401);
    echo '<!DOCTYPE html><html><body></body></html>';
    exit;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

echo '<!DOCTYPE html><html><body>';

if ($portal === 'residence') {
    require_once dirname(__DIR__) . '/residence/config.php';
    require_once dirname(__DIR__) . '/includes/resident-notifications.php';
    $email = (string) ($_SESSION['user_email'] ?? '');
    $residenceNotifications = resident_notifications_for_user($email);
    $headerUnreadNotifications = count(array_filter(
        $residenceNotifications,
        static fn (array $notification): bool => empty($notification['read_at'])
    ));
    include dirname(__DIR__) . '/residence/partials/notify-menu.php';
} else {
    require_once dirname(__DIR__) . '/includes/admin.php';
    require_once dirname(__DIR__) . '/includes/admin-notifications.php';
    $adminNotifications = admin_notifications_list();
    $adminNotifyCount = admin_notification_unread_count($adminNotifications);
    include dirname(__DIR__) . '/admin/partials/notify-dropdown.php';
}

echo '</body></html>';
