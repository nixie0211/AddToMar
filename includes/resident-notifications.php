<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function resident_notification_add(string $email, string $title, string $message, string $type = 'info', string $reportId = '', string $orderNumber = ''): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }

    $reportId = trim($reportId);
    $orderNumber = trim($orderNumber);

    try {
        if ($reportId !== '') {
            $id = 'report-' . $reportId;
        } elseif ($orderNumber !== '') {
            $id = 'order-' . preg_replace('/[^A-Za-z0-9_-]/', '', $orderNumber) . '-' . bin2hex(random_bytes(4));
        } else {
            $id = 'notice-' . bin2hex(random_bytes(8));
        }

        $statement = caps_db()->prepare('INSERT INTO resident_notifications (id, user_email, title, message, type, report_id, order_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), message = VALUES(message), type = VALUES(type), report_id = VALUES(report_id), order_number = VALUES(order_number), created_at = VALUES(created_at), read_at = \'\'');
        return $statement->execute([$id, $email, $title, $message, $type, $reportId, $orderNumber, date('c')]);
    } catch (Throwable) {
        return false;
    }
}

function resident_notification_order_number(array $notification): string
{
    $direct = trim((string) ($notification['order_number'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }

    $haystack = (string) ($notification['title'] ?? '') . ' ' . (string) ($notification['message'] ?? '');
    if (preg_match('/#\s*([A-Z]{2,8}-[A-Za-z0-9-]+)/i', $haystack, $match)) {
        return $match[1];
    }

    return '';
}

function resident_notification_click_attr(array $notification): string
{
    $id = trim((string) ($notification['id'] ?? ''));
    $reportId = trim((string) ($notification['report_id'] ?? ''));
    $orderNumber = resident_notification_order_number($notification);
    $type = strtolower(trim((string) ($notification['type'] ?? '')));
    $unread = empty($notification['read_at']);
    $parts = [];

    if ($id !== '') {
        $parts[] = 'data-notification-id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
    }
    $parts[] = 'data-unread="' . ($unread ? '1' : '0') . '"';

    if ($reportId !== '' && $type !== 'order') {
        $parts[] = 'data-report-id="' . htmlspecialchars($reportId, ENT_QUOTES, 'UTF-8') . '"';
    } elseif ($orderNumber !== '' || $type === 'order') {
        $parts[] = 'data-order-number="' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '"';
    }

    $parts[] = 'role="button"';
    $parts[] = 'tabindex="0"';

    return $parts === [] ? '' : ' ' . implode(' ', $parts);
}

function resident_notification_unread_count(string $email): int
{
    try {
        $statement = caps_db()->prepare("SELECT COUNT(*) FROM resident_notifications WHERE user_email = ? AND (read_at IS NULL OR read_at = '')");
        $statement->execute([strtolower(trim($email))]);
        return (int) $statement->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function resident_notification_mark_read(string $email, string $id): array
{
    $email = strtolower(trim($email));
    $id = trim($id);
    if ($email === '' || $id === '') {
        return ['ok' => false, 'unread' => resident_notification_unread_count($email)];
    }

    try {
        $statement = caps_db()->prepare("UPDATE resident_notifications SET read_at = ? WHERE id = ? AND user_email = ? AND (read_at IS NULL OR read_at = '')");
        $statement->execute([date('c'), $id, $email]);
        return ['ok' => true, 'unread' => resident_notification_unread_count($email)];
    } catch (Throwable) {
        return ['ok' => false, 'unread' => resident_notification_unread_count($email)];
    }
}

function resident_notifications_for_user(string $email): array
{
    try {
        $statement = caps_db()->prepare('SELECT * FROM resident_notifications WHERE user_email = ? ORDER BY created_at DESC');
        $statement->execute([strtolower(trim($email))]);
        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function resident_reports_for_user(string $email): array
{
    try {
        $statement = caps_db()->prepare('SELECT id, pharmacy_name, reason, details, proof_path, status, admin_note, action_taken, reviewed_at, created_at FROM pharmacy_reports WHERE reporter_email = ? ORDER BY created_at DESC');
        $statement->execute([strtolower(trim($email))]);
        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}
