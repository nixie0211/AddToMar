<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/pharmacy-accounts.php';

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
        return resident_notifications_attach_pharmacy_logos($statement->fetchAll());
    } catch (Throwable) {
        return [];
    }
}

function resident_notifications_attach_pharmacy_logos(array $notifications): array
{
    if ($notifications === []) {
        return [];
    }

    $orderNumbers = [];
    $reportIds = [];
    foreach ($notifications as $notification) {
        $orderNumber = resident_notification_order_number($notification);
        if ($orderNumber !== '') {
            $orderNumbers[$orderNumber] = true;
        }
        $reportId = trim((string) ($notification['report_id'] ?? ''));
        if ($reportId !== '') {
            $reportIds[$reportId] = true;
        }
    }

    $pharmacyByOrder = [];
    if ($orderNumbers !== []) {
        try {
            $keys = array_keys($orderNumbers);
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $pdo = function_exists('pharmacy_db') ? pharmacy_db() : caps_db();
            $statement = $pdo->prepare('SELECT order_number, pharmacy_id FROM orders WHERE order_number IN (' . $placeholders . ')');
            $statement->execute($keys);
            foreach ($statement->fetchAll() as $row) {
                $pharmacyByOrder[(string) ($row['order_number'] ?? '')] = trim((string) ($row['pharmacy_id'] ?? ''));
            }
        } catch (Throwable) {
        }
    }

    $pharmacyByReport = [];
    if ($reportIds !== []) {
        try {
            $keys = array_keys($reportIds);
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $statement = caps_db()->prepare('SELECT id, pharmacy_id FROM pharmacy_reports WHERE id IN (' . $placeholders . ')');
            $statement->execute($keys);
            foreach ($statement->fetchAll() as $row) {
                $pharmacyByReport[(string) ($row['id'] ?? '')] = trim((string) ($row['pharmacy_id'] ?? ''));
            }
        } catch (Throwable) {
        }
    }

    $accountsById = [];
    $accountsByName = [];
    foreach (pharmacy_accounts_list_approved() as $account) {
        $id = trim((string) ($account['id'] ?? ''));
        $name = strtolower(trim((string) ($account['pharmacy_name'] ?? '')));
        if ($id !== '') {
            $accountsById[$id] = $account;
        }
        if ($id !== '' && $name !== '') {
            $accountsByName[$name] = $account;
        }
    }

    foreach ($notifications as &$notification) {
        $pharmacyId = $pharmacyByOrder[resident_notification_order_number($notification)] ?? '';
        if ($pharmacyId === '') {
            $pharmacyId = $pharmacyByReport[trim((string) ($notification['report_id'] ?? ''))] ?? '';
        }

        $account = $pharmacyId !== '' ? ($accountsById[$pharmacyId] ?? null) : null;
        if (!is_array($account)) {
            $haystack = strtolower(trim((string) ($notification['message'] ?? '') . ' ' . (string) ($notification['title'] ?? '')));
            foreach ($accountsByName as $name => $namedAccount) {
                if ($name !== '' && str_contains($haystack, $name)) {
                    $account = $namedAccount;
                    $pharmacyId = trim((string) ($namedAccount['id'] ?? $pharmacyId));
                    break;
                }
            }
        }

        $logoUrl = is_array($account) ? pharmacy_accounts_public_logo_url($account) : '';
        if ($logoUrl === '' && $pharmacyId !== '') {
            $logoUrl = pharmacy_accounts_public_logo_url(['id' => $pharmacyId]);
        }

        $notification['pharmacy_id'] = $pharmacyId;
        $notification['logo_url'] = $logoUrl;
    }
    unset($notification);

    return $notifications;
}

function resident_notification_is_today(?string $value): bool
{
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return false;
    }

    $timezone = function_exists('app_timezone') ? app_timezone() : new DateTimeZone('Asia/Manila');
    $created = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
    $now = new DateTimeImmutable('now', $timezone);

    return $created->format('Y-m-d') === $now->format('Y-m-d');
}

function resident_notification_relative_short(?string $value): string
{
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '';
    }

    $seconds = time() - $timestamp;
    if ($seconds < 0) {
        $seconds = 0;
    }
    if ($seconds < 60) {
        return 'Just now';
    }
    if ($seconds < 3600) {
        return (string) (int) floor($seconds / 60) . 'm';
    }
    if ($seconds < 86400) {
        return (string) (int) floor($seconds / 3600) . 'h';
    }
    if ($seconds < 604800) {
        return (string) (int) floor($seconds / 86400) . 'd';
    }

    $timezone = function_exists('app_timezone') ? app_timezone() : new DateTimeZone('Asia/Manila');
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format('M j');
}

function resident_notification_initials(array $notification): string
{
    $message = trim((string) ($notification['message'] ?? ''));
    $title = trim((string) ($notification['title'] ?? ''));
    $source = $message !== '' ? $message : $title;
    if (preg_match('/\bat\s+([A-Z][\w&.\'-]*(?:\s+[A-Z][\w&.\'-]*){0,3})/', $message, $match)) {
        $source = $match[1];
    } elseif (preg_match('/^([A-Z][\w&.\'-]*(?:\s+[A-Z][\w&.\'-]*){0,2})\s/', $message, $match)) {
        $source = $match[1];
    }

    $parts = preg_split('/\s+/', trim($source)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'Rx';
}

function resident_notification_tone(array $notification): int
{
    return abs(crc32((string) ($notification['id'] ?? $notification['title'] ?? 'n'))) % 4;
}

function resident_notifications_grouped(array $notifications): array
{
    $today = [];
    $earlier = [];
    foreach ($notifications as $notification) {
        if (resident_notification_is_today($notification['created_at'] ?? null)) {
            $today[] = $notification;
        } else {
            $earlier[] = $notification;
        }
    }

    return ['today' => $today, 'earlier' => $earlier];
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
