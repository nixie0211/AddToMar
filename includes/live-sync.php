<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';

function live_sync_hash(string ...$parts): string
{
    return substr(hash('sha256', implode('|', $parts)), 0, 20);
}

function live_sync_row(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    } catch (Throwable) {
        return [];
    }
}

function live_sync_stamp_from_row(array $row): string
{
    return live_sync_hash(
        (string) ($row['c'] ?? '0'),
        (string) ($row['u'] ?? ''),
        (string) ($row['s'] ?? ''),
        (string) ($row['q'] ?? '')
    );
}

function live_sync_pharmacy_pdo(): ?PDO
{
    static $ready = null;
    static $pdo = null;

    if ($ready === false) {
        return null;
    }
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        require_once dirname(__DIR__) . '/pharmacy/config.php';
        require_once dirname(__DIR__) . '/pharmacy/includes/database.php';
        $pdo = pharmacy_db();
        $ready = true;

        return $pdo;
    } catch (Throwable) {
        $ready = false;

        return null;
    }
}

function live_sync_public_versions(): array
{
    $pharmacies = live_sync_row(
        caps_db(),
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(updated_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', status, '|', pharmacy_name))), 0) AS s
         FROM pharmacies
         WHERE status IN ('approved', 'active')"
    );

    return [
        'pharmacies' => live_sync_stamp_from_row($pharmacies),
    ];
}

function live_sync_residence_versions(string $email): array
{
    $email = strtolower(trim($email));
    $versions = live_sync_public_versions();

    $cart = live_sync_row(
        caps_db(),
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(updated_at), '') AS u,
                COALESCE(SUM(quantity), 0) AS q
         FROM cart
         WHERE user_email = ?",
        [$email]
    );
    $notifications = live_sync_row(
        caps_db(),
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(created_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', IFNULL(read_at, '')))), 0) AS s
         FROM resident_notifications
         WHERE user_email = ?",
        [$email]
    );
    $reports = live_sync_row(
        caps_db(),
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(updated_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', status))), 0) AS s
         FROM pharmacy_reports
         WHERE LOWER(TRIM(reporter_email)) = ?",
        [$email]
    );

    $versions['cart'] = live_sync_stamp_from_row($cart);
    $versions['notifications'] = live_sync_stamp_from_row($notifications);
    $versions['reports'] = live_sync_stamp_from_row($reports);
    $versions['catalog'] = $versions['pharmacies'];
    $versions['orders'] = live_sync_hash('0');

    $pharmacyPdo = live_sync_pharmacy_pdo();
    if ($pharmacyPdo instanceof PDO) {
        $catalog = live_sync_row(
            $pharmacyPdo,
            "SELECT COUNT(*) AS c,
                    COALESCE(MAX(updated_at), '') AS u,
                    COALESCE(SUM(stock_quantity), 0) AS q,
                    COALESCE(SUM(CRC32(CONCAT(id, '|', IFNULL(is_active, 0), '|', IFNULL(is_featured, 0), '|', IFNULL(selling_price, 0)))), 0) AS s
             FROM medicines"
        );
        $orders = $email === ''
            ? []
            : live_sync_row(
                $pharmacyPdo,
                "SELECT COUNT(*) AS c,
                        COALESCE(MAX(o.updated_at), '') AS u,
                        COALESCE(SUM(CRC32(CONCAT(o.id, '|', o.status, '|', IFNULL(o.total_amount, 0)))), 0) AS s
                 FROM orders o
                 LEFT JOIN customers c ON c.id = o.customer_id
                 WHERE LOWER(TRIM(c.email)) = ?",
                [$email]
            );
        $versions['catalog'] = live_sync_hash($versions['pharmacies'], live_sync_stamp_from_row($catalog));
        $versions['orders'] = live_sync_stamp_from_row($orders);
    }

    return $versions;
}

function live_sync_pharmacy_versions(string $pharmacyId): array
{
    $pharmacyId = trim($pharmacyId);
    $account = live_sync_row(
        caps_db(),
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(updated_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', status, '|', IFNULL(admin_note, '')))), 0) AS s
         FROM pharmacies
         WHERE id = ?",
        [$pharmacyId]
    );
    $versions = [
        'account' => live_sync_stamp_from_row($account),
        'orders' => live_sync_hash('0'),
        'inventory' => live_sync_hash('0'),
        'customers' => live_sync_hash('0'),
        'suppliers' => live_sync_hash('0'),
    ];

    $pharmacyPdo = live_sync_pharmacy_pdo();
    if (!($pharmacyPdo instanceof PDO) || $pharmacyId === '') {
        $versions['shell'] = live_sync_hash($versions['account'], $versions['orders'], $versions['inventory']);

        return $versions;
    }

    $orders = live_sync_row(
        $pharmacyPdo,
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(updated_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', status, '|', IFNULL(total_amount, 0), '|', IFNULL(pickup_proof_path, '')))), 0) AS s
         FROM orders
         WHERE pharmacy_id = ?",
        [$pharmacyId]
    );
    $inventory = live_sync_row(
        $pharmacyPdo,
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(updated_at), '') AS u,
                COALESCE(SUM(stock_quantity), 0) AS q,
                COALESCE(SUM(CRC32(CONCAT(id, '|', IFNULL(is_active, 0), '|', IFNULL(is_featured, 0), '|', IFNULL(selling_price, 0)))), 0) AS s
         FROM medicines
         WHERE pharmacy_id = ?",
        [$pharmacyId]
    );
    $customers = live_sync_row(
        $pharmacyPdo,
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(created_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', status))), 0) AS s
         FROM customers
         WHERE pharmacy_id = ?",
        [$pharmacyId]
    );
    $suppliers = live_sync_row(
        $pharmacyPdo,
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(created_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', status, '|', medicines_count))), 0) AS s
         FROM suppliers
         WHERE pharmacy_id = ?",
        [$pharmacyId]
    );

    $versions['orders'] = live_sync_stamp_from_row($orders);
    $versions['inventory'] = live_sync_stamp_from_row($inventory);
    $versions['customers'] = live_sync_stamp_from_row($customers);
    $versions['suppliers'] = live_sync_stamp_from_row($suppliers);
    $versions['shell'] = live_sync_hash(
        $versions['account'],
        $versions['orders'],
        $versions['inventory'],
        $versions['customers']
    );

    return $versions;
}

function live_sync_admin_versions(): array
{
    $pharmacies = live_sync_row(
        caps_db(),
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(updated_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', status, '|', IFNULL(admin_note, '')))), 0) AS s
         FROM pharmacies"
    );
    $reports = live_sync_row(
        caps_db(),
        "SELECT COUNT(*) AS c,
                COALESCE(MAX(updated_at), '') AS u,
                COALESCE(SUM(CRC32(CONCAT(id, '|', status, '|', IFNULL(action_taken, ''), '|', IFNULL(admin_note, '')))), 0) AS s
         FROM pharmacy_reports"
    );
    $pharmacyStamp = live_sync_stamp_from_row($pharmacies);
    $reportStamp = live_sync_stamp_from_row($reports);

    $unread = '0';
    try {
        require_once __DIR__ . '/admin-notifications.php';
        $unread = (string) admin_notification_unread_count(admin_notifications_list());
    } catch (Throwable) {
        $unread = '0';
    }

    return [
        'pharmacies' => $pharmacyStamp,
        'reports' => $reportStamp,
        'notifications' => live_sync_hash($pharmacyStamp, $reportStamp, $unread),
        'admin' => live_sync_hash($pharmacyStamp, $reportStamp, $unread),
    ];
}

function live_sync_portal(): string
{
    $portal = (string) ($_SESSION['portal'] ?? '');
    if (in_array($portal, ['residence', 'pharmacy', 'admin'], true)) {
        return $portal;
    }

    return 'public';
}

function live_sync_versions(?string $portal = null): array
{
    $portal = $portal ?? live_sync_portal();

    try {
        caps_bootstrap();

        return match ($portal) {
            'residence' => live_sync_residence_versions((string) ($_SESSION['user_email'] ?? '')),
            'pharmacy' => live_sync_pharmacy_versions((string) ($_SESSION['pharmacy_id'] ?? '')),
            'admin' => live_sync_admin_versions(),
            default => live_sync_public_versions(),
        };
    } catch (Throwable) {
        return ['clock' => live_sync_hash((string) time())];
    }
}

function live_sync_client_config(?string $portal = null): array
{
    $portal = $portal ?? live_sync_portal();

    return [
        'url' => app_url('ajax/live-sync.php'),
        'interval' => 4000,
        'portal' => $portal,
        'versions' => live_sync_versions($portal),
    ];
}

function live_sync_render_script(?string $portal = null): void
{
    $config = live_sync_client_config($portal);
    echo '<script>window.LIVE_SYNC=' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
    echo '<script src="' . htmlspecialchars(app_url('js/live-sync.js'), ENT_QUOTES, 'UTF-8') . '?v=live-sync-4" defer></script>' . "\n";
}
