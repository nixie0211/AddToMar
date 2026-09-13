<?php

declare(strict_types=1);

function admin_notifications_storage_path(): string
{
    return dirname(__DIR__) . '/data/admin-notifications.json';
}

function admin_notifications_load_all(): array
{
    $path = admin_notifications_storage_path();

    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? array_values($data) : [];
}

function admin_notifications_save_all(array $notifications): bool
{
    $path = admin_notifications_storage_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $payload = json_encode(array_values($notifications), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    return file_put_contents($path, $payload . PHP_EOL, LOCK_EX) !== false;
}

function admin_notifications_make_id(string $type, string $entityId): string
{
    return $type . ':' . $entityId;
}

function admin_notifications_pharmacy_detail(array $pharmacy): string
{
    $status = strtolower((string) ($pharmacy['status'] ?? 'pending'));

    return match ($status) {
        'approved', 'active' => 'Pharmacy approved',
        'rejected' => 'Pharmacy rejected',
        'blocked' => 'Pharmacy blocked',
        default => 'Pending approval request',
    };
}

function admin_notifications_report_detail(array $report): string
{
    $reason = trim((string) ($report['reason'] ?? ''));
    if ($reason !== '') {
        return $reason;
    }

    $status = strtolower((string) ($report['status'] ?? 'under_review'));

    return match ($status) {
        'resolved' => 'Report resolved',
        'dismissed' => 'Report dismissed',
        default => 'Report under review',
    };
}

function admin_notifications_pharmacy_href(string $pharmacyId, string $status = 'pending'): string
{
    if (function_exists('admin_pharmacy_review_url')) {
        return admin_pharmacy_review_url($pharmacyId, false);
    }

    return app_url('admin/?' . http_build_query([
        'page' => 'pharmacies',
        'pharmacy' => $pharmacyId,
    ]));
}

function admin_notifications_report_href(string $reportId): string
{
    if (function_exists('admin_report_review_url')) {
        return admin_report_review_url($reportId, false);
    }

    return app_url('admin/?' . http_build_query([
        'page' => 'reports',
        'report' => $reportId,
    ]));
}

function admin_notifications_sync(array $stats): void
{
    $notifications = admin_notifications_load_all();
    $indexed = [];

    foreach ($notifications as $notification) {
        $id = (string) ($notification['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $indexed[$id] = $notification;
    }

    $changed = false;

    foreach ($stats['all_pharmacies'] ?? [] as $pharmacy) {
        $pharmacyId = (string) ($pharmacy['id'] ?? '');
        if ($pharmacyId === '') {
            continue;
        }

        $title = (string) ($pharmacy['pharmacy_name'] ?? 'Pharmacy');
        $href = admin_notifications_pharmacy_href($pharmacyId, (string) ($pharmacy['status'] ?? 'pending'));

        foreach (pharmacy_accounts_status_events($pharmacy) as $event) {
            $status = pharmacy_accounts_normalize_status((string) ($event['status'] ?? 'pending'));
            $time = (string) ($event['at'] ?? '');
            $id = 'pharmacy:' . $pharmacyId . ':' . $status . ':' . $time;
            $detail = match ($status) {
                'approved' => 'Pharmacy approved',
                'rejected' => 'Pharmacy rejected',
                'blocked' => 'Pharmacy blocked',
                default => 'Pending approval request',
            };

            if (!isset($indexed[$id])) {
                $indexed[$id] = [
                    'id' => $id,
                    'type' => 'pharmacy',
                    'entity_id' => $pharmacyId,
                    'title' => $title,
                    'detail' => $detail,
                    'time' => $time,
                    'href' => $href,
                    'read_at' => null,
                ];
                $changed = true;
                continue;
            }

            if ((string) ($indexed[$id]['title'] ?? '') !== $title) {
                $indexed[$id]['title'] = $title;
                $changed = true;
            }
            if ((string) ($indexed[$id]['href'] ?? '') !== $href) {
                $indexed[$id]['href'] = $href;
                $changed = true;
            }
        }
    }

    foreach ($stats['reports'] ?? [] as $report) {
        $reportId = (string) ($report['id'] ?? '');
        if ($reportId === '') {
            continue;
        }

        $id = admin_notifications_make_id('report', $reportId);
        $title = (string) ($report['pharmacy_name'] ?? 'Pharmacy report');
        $time = (string) ($report['created_at'] ?? $report['updated_at'] ?? '');

        if (!isset($indexed[$id])) {
            $indexed[$id] = [
                'id' => $id,
                'type' => 'report',
                'entity_id' => $reportId,
                'title' => $title,
                'detail' => admin_notifications_report_detail($report),
                'time' => $time,
                'href' => admin_notifications_report_href($reportId),
                'read_at' => null,
            ];
            $changed = true;
            continue;
        }

        if ((string) ($indexed[$id]['title'] ?? '') !== $title) {
            $indexed[$id]['title'] = $title;
            $changed = true;
        }

        $href = admin_notifications_report_href($reportId);
        if ((string) ($indexed[$id]['href'] ?? '') !== $href) {
            $indexed[$id]['href'] = $href;
            $changed = true;
        }
    }

    if (!$changed) {
        return;
    }

    $items = array_values($indexed);
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
    });
    admin_notifications_save_all($items);
}

function admin_notifications_mark_read(string $notificationId): bool
{
    $notificationId = trim($notificationId);
    if ($notificationId === '') {
        return false;
    }

    $notifications = admin_notifications_load_all();
    $changed = false;

    foreach ($notifications as $index => $notification) {
        if ((string) ($notification['id'] ?? '') !== $notificationId) {
            continue;
        }

        if (!empty($notification['read_at'])) {
            return true;
        }

        $notifications[$index]['read_at'] = date('c');
        $changed = true;
        break;
    }

    if (!$changed) {
        return false;
    }

    return admin_notifications_save_all($notifications);
}

function admin_notifications_mark_entity_read(string $type, string $entityId): bool
{
    $entityId = trim($entityId);
    if ($entityId === '') {
        return false;
    }

    $notifications = admin_notifications_load_all();
    $changed = false;

    foreach ($notifications as $index => $notification) {
        $sameType = (string) ($notification['type'] ?? '') === $type;
        $sameEntity = (string) ($notification['entity_id'] ?? '') === $entityId;
        $legacyId = (string) ($notification['id'] ?? '') === admin_notifications_make_id($type, $entityId);
        if (!$legacyId && !($sameType && $sameEntity)) {
            continue;
        }

        if (!empty($notification['read_at'])) {
            continue;
        }

        $notifications[$index]['read_at'] = date('c');
        $changed = true;
    }

    if (!$changed) {
        return true;
    }

    return admin_notifications_save_all($notifications);
}

function admin_notifications_list(): array
{
    $notifications = admin_notifications_load_all();

    usort($notifications, static function (array $a, array $b): int {
        return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
    });

    return array_map(static function (array $notification): array {
        $notification['is_read'] = !empty($notification['read_at']);

        return $notification;
    }, $notifications);
}

function admin_notification_unread_count(array $notifications): int
{
    $count = 0;

    foreach ($notifications as $notification) {
        if (empty($notification['is_read']) && empty($notification['read_at'])) {
            $count++;
        }
    }

    return $count;
}

function admin_notification_items(array $stats): array
{
    admin_notifications_sync($stats);

    return admin_notifications_list();
}
