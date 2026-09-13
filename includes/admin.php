<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/customers.php';
require_once __DIR__ . '/pharmacy-accounts.php';
require_once __DIR__ . '/pharmacy-reports.php';
require_once __DIR__ . '/admin-notifications.php';

function admin_dashboard_stats(): array
{
    $stats = [
        'customers' => 0,
        'admins' => 0,
        'pharmacies_total' => 0,
        'pharmacies_pending' => 0,
        'pharmacies_approved' => 0,
        'pharmacies_blocked' => 0,
        'pharmacies_rejected' => 0,
        'reports_open' => 0,
        'reports_total' => 0,
        'reports_resolved' => 0,
        'reports_dismissed' => 0,
        'recent_customers' => [],
        'recent_pharmacies' => [],
        'recent_reports' => [],
        'all_pharmacies' => [],
        'reports' => [],
    ];

    try {
        $pdo = caps_db();

        $stats['customers'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
        $stats['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

        $customerStmt = $pdo->query(
            "SELECT email, full_name, contact_number, address, created_at
             FROM users
             WHERE role = 'customer'
             ORDER BY created_at DESC
             LIMIT 6"
        );
        $stats['recent_customers'] = $customerStmt->fetchAll() ?: [];
    } catch (Throwable) {
        // Keep defaults when database is unavailable.
    }

    $pharmacies = array_values(pharmacy_accounts_load_all());
    $stats['pharmacies_total'] = count($pharmacies);

    foreach ($pharmacies as $pharmacy) {
        $status = strtolower((string) ($pharmacy['status'] ?? 'pending'));
        if ($status === 'approved' || $status === 'active') {
            $stats['pharmacies_approved']++;
        } elseif ($status === 'blocked') {
            $stats['pharmacies_blocked']++;
        } elseif ($status === 'rejected') {
            $stats['pharmacies_rejected']++;
        } else {
            $stats['pharmacies_pending']++;
        }
    }

    usort($pharmacies, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    $stats['all_pharmacies'] = $pharmacies;
    $stats['recent_pharmacies'] = array_slice($pharmacies, 0, 6);

    $reports = pharmacy_reports_load_all();
    usort($reports, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    $stats['reports'] = $reports;
    $stats['reports_total'] = count($reports);
    $stats['reports_open'] = 0;
    $stats['reports_resolved'] = 0;
    $stats['reports_dismissed'] = 0;

    foreach ($reports as $report) {
        $status = strtolower((string) ($report['status'] ?? 'under_review'));
        if ($status === 'under_review' || $status === 'open') {
            $stats['reports_open']++;
        } elseif ($status === 'resolved') {
            $stats['reports_resolved']++;
        } elseif ($status === 'dismissed') {
            $stats['reports_dismissed']++;
        }
    }

    $stats['recent_reports'] = array_slice($reports, 0, 5);

    return $stats;
}

function admin_format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y', $timestamp);
}

function admin_format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y · g:i A', $timestamp);
}

function admin_status_badge(string $status): array
{
    $normalized = strtolower(trim($status));

    return match ($normalized) {
        'approved', 'active' => ['label' => 'Approved', 'class' => 'status-approved'],
        'pending' => ['label' => 'Pending', 'class' => 'status-pending'],
        'rejected' => ['label' => 'Rejected', 'class' => 'status-rejected'],
        'blocked' => ['label' => 'Blocked', 'class' => 'status-blocked'],
        'open', 'under_review' => ['label' => 'Under review', 'class' => 'status-review'],
        'resolved' => ['label' => 'Resolved', 'class' => 'status-approved'],
        'dismissed' => ['label' => 'Dismissed', 'class' => 'status-rejected'],
        default => ['label' => ucfirst($normalized !== '' ? $normalized : 'Unknown'), 'class' => 'status-pending'],
    };
}

function admin_report_status_badge(array $report): array
{
    $status = strtolower((string) ($report['status'] ?? 'under_review'));
    $actionTaken = strtolower((string) ($report['action_taken'] ?? ''));

    if ($status === 'resolved') {
        if ($actionTaken === 'blocked') {
            return ['label' => 'Blocked', 'class' => 'status-blocked'];
        }

        return ['label' => 'Replied', 'class' => 'status-approved'];
    }

    return admin_status_badge($status);
}

function admin_pharmacy_reject_reasons(): array
{
    return [
        'incomplete_documents' => 'Incomplete or invalid documents',
        'expired_permit' => 'Expired business permit',
        'expired_license' => 'Expired pharmacy license',
        'missing_bir' => 'Missing or invalid BIR certificate',
        'unverified_address' => 'Address could not be verified',
        'invalid_contact' => 'Invalid contact information',
        'duplicate_registration' => 'Duplicate registration',
        'other' => 'Other',
    ];
}

function admin_pharmacy_block_reasons(): array
{
    return [
        'customer_complaints' => 'Repeated customer complaints',
        'policy_violation' => 'Violation of platform policies',
        'fraudulent_registration' => 'Fraudulent or misleading registration',
        'invalid_license' => 'Operating without a valid pharmacy license',
        'report_review' => 'Blocked after report review',
        'other' => 'Other',
    ];
}

function admin_pharmacy_decision_reason_label(string $decision, string $reasonKey): ?string
{
    $reasons = $decision === 'blocked'
        ? admin_pharmacy_block_reasons()
        : admin_pharmacy_reject_reasons();

    return $reasons[$reasonKey] ?? null;
}

function admin_pharmacy_build_decision_note(string $decision, string $reasonKey, string $otherNote = ''): string
{
    $label = admin_pharmacy_decision_reason_label($decision, $reasonKey);
    if ($label === null) {
        return '';
    }

    if ($reasonKey === 'other') {
        return $otherNote !== '' ? 'Other: ' . $otherNote : '';
    }

    return $label;
}

function admin_pharmacy_card_schedule(array $pharmacy): array
{
    $status = strtolower((string) ($pharmacy['status'] ?? 'pending'));
    $isApproved = $status === 'approved' || $status === 'active';

    if ($status === 'blocked') {
        $action = 'Blocked';
        $source = (string) ($pharmacy['blocked_at'] ?? $pharmacy['updated_at'] ?? '');
    } elseif ($isApproved) {
        $action = 'Approved';
        $source = (string) ($pharmacy['updated_at'] ?? '');
    } elseif ($status === 'rejected') {
        $action = 'Rejected';
        $source = (string) ($pharmacy['updated_at'] ?? '');
    } else {
        $action = 'Registered';
        $source = (string) ($pharmacy['created_at'] ?? '');
    }

    $timestamp = $source !== '' ? strtotime($source) : false;

    return [
        'time_label' => $action . ' at:',
        'date_label' => $action . ' on:',
        'time' => $timestamp ? date('g:i A', $timestamp) : '—',
        'date' => $timestamp ? date('l, F j', $timestamp) : '—',
    ];
}

function admin_report_card_schedule(array $report): array
{
    $status = strtolower((string) ($report['status'] ?? 'under_review'));
    $actionTaken = strtolower((string) ($report['action_taken'] ?? ''));

    if ($status === 'resolved') {
        $action = $actionTaken === 'blocked' ? 'Blocked' : 'Replied';
        $source = (string) ($report['reviewed_at'] ?? $report['updated_at'] ?? '');
    } elseif ($status === 'dismissed') {
        $action = 'Dismissed';
        $source = (string) ($report['reviewed_at'] ?? $report['updated_at'] ?? '');
    } else {
        $action = 'Filed';
        $source = (string) ($report['created_at'] ?? '');
    }

    $timestamp = $source !== '' ? strtotime($source) : false;

    return [
        'time_label' => $action . ' at:',
        'date_label' => $action . ' on:',
        'time' => $timestamp ? date('g:i A', $timestamp) : '—',
        'date' => $timestamp ? date('l, F j', $timestamp) : '—',
    ];
}

function admin_initials(string $name): string
{
    return residence_user_initials($name);
}

function admin_format_relative(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    $seconds = time() - $timestamp;
    if ($seconds < 0) {
        $seconds = 0;
    }

    if ($seconds < 60) {
        return 'Just now';
    }
    if ($seconds < 3600) {
        $minutes = (int) floor($seconds / 60);

        return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
    }
    if ($seconds < 86400) {
        $hours = (int) floor($seconds / 3600);

        return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
    }
    if ($seconds < 604800) {
        $days = (int) floor($seconds / 86400);

        return $days === 1 ? '1 day ago' : $days . ' days ago';
    }

    return date('M j, Y', $timestamp);
}

function admin_percent(int $part, int $total): string
{
    if ($total <= 0) {
        return '0%';
    }

    return rtrim(rtrim(number_format(($part / $total) * 100, 1), '0'), '.') . '%';
}

function admin_overview_activity(array $stats): array
{
    $items = [];

    foreach ($stats['all_pharmacies'] ?? [] as $pharmacy) {
        $pharmacyId = (string) ($pharmacy['id'] ?? '');
        $title = (string) ($pharmacy['pharmacy_name'] ?? 'Pharmacy');
        $href = $pharmacyId !== ''
            ? admin_url('?page=pharmacies&pharmacy=' . urlencode($pharmacyId))
            : admin_url('?page=pharmacies');

        foreach (pharmacy_accounts_status_events($pharmacy) as $event) {
            $status = pharmacy_accounts_normalize_status((string) ($event['status'] ?? 'pending'));
            $badge = admin_status_badge($status);
            $detail = match ($status) {
                'approved' => 'Account approved',
                'blocked' => 'Account blocked',
                'rejected' => 'Registration rejected',
                default => 'Registration submitted',
            };
            $tone = match ($status) {
                'approved' => 'approved',
                'blocked' => 'blocked',
                'rejected' => 'rejected',
                default => 'pending',
            };

            $items[] = [
                'kind' => 'pharmacy',
                'tone' => $tone,
                'title' => $title,
                'detail' => $detail,
                'status' => $badge['label'],
                'status_class' => $badge['class'],
                'time' => (string) ($event['at'] ?? ''),
                'href' => $href,
            ];
        }
    }

    foreach ($stats['reports'] ?? [] as $report) {
        $status = strtolower((string) ($report['status'] ?? 'under_review'));
        $badge = admin_status_badge($status);
        $id = (string) ($report['id'] ?? '');
        $short = $id !== '' ? strtoupper(substr($id, -3)) : '000';

        $items[] = [
            'kind' => 'report',
            'tone' => 'review',
            'title' => 'Report #' . $short,
            'detail' => (string) ($report['reason'] ?? 'Pharmacy report'),
            'status' => $badge['label'],
            'status_class' => $badge['class'],
            'time' => (string) ($report['updated_at'] ?? $report['created_at'] ?? ''),
            'href' => admin_url('?page=reports&report=' . urlencode($id)),
        ];
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
    });

    return array_slice($items, 0, 8);
}

function admin_proof_is_image(string $path): bool
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
}

function admin_table_sort_icon(): string
{
    return '<span class="th-sort" aria-hidden="true"><svg width="9" height="12" viewBox="0 0 9 12" fill="none"><path d="M4.5 1 8 5.2H1L4.5 1Z"/><path d="M4.5 11 1 6.8h7L4.5 11Z"/></svg></span>';
}

function admin_filter_by_date(array $items, string $from, string $to, string $field = 'created_at'): array
{
    $fromTs = $from !== '' ? strtotime($from . ' 00:00:00') : false;
    $toTs = $to !== '' ? strtotime($to . ' 23:59:59') : false;

    if ($fromTs === false && $toTs === false) {
        return array_values($items);
    }

    return array_values(array_filter($items, static function (array $item) use ($fromTs, $toTs, $field): bool {
        $timestamp = strtotime((string) ($item[$field] ?? ''));
        if ($timestamp === false) {
            return false;
        }
        if ($fromTs !== false && $timestamp < $fromTs) {
            return false;
        }
        if ($toTs !== false && $timestamp > $toTs) {
            return false;
        }

        return true;
    }));
}

function admin_paginate(array $items, int $page, int $perPage = 8): array
{
    $total = count($items);
    $pages = max(1, (int) ceil(($total > 0 ? $total : 1) / $perPage));
    if ($total === 0) {
        $pages = 1;
    }
    $page = max(1, min($page, $pages));

    return [
        'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
        'offset' => ($page - 1) * $perPage,
    ];
}

function admin_pagination_pages(int $page, int $pages): array
{
    if ($pages <= 7) {
        return range(1, $pages);
    }

    $items = [1];
    $start = max(2, $page - 1);
    $end = min($pages - 1, $page + 1);

    if ($start > 2) {
        $items[] = '...';
    }

    for ($index = $start; $index <= $end; $index++) {
        $items[] = $index;
    }

    if ($end < $pages - 1) {
        $items[] = '...';
    }

    $items[] = $pages;

    return $items;
}
