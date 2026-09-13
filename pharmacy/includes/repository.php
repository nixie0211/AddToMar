<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/pharmacy-context.php';

function pharmacy_settings(): array
{
    $pharmacyId = pharmacy_current_id();
    if ($pharmacyId === '') {
        return [];
    }

    $stmt = pharmacy_db()->prepare('SELECT * FROM pharmacy_settings WHERE pharmacy_id = ? LIMIT 1');
    $stmt->execute([$pharmacyId]);
    $row = $stmt->fetch();

    return $row ?: [];
}

function pharmacy_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials ?: 'MV';
}

function pharmacy_format_money(float $amount): string
{
    if ($amount >= 1000000) {
        return '₱' . number_format($amount / 1000000, 2) . 'M';
    }
    if ($amount >= 1000) {
        return '₱' . number_format($amount, 0);
    }
    return '₱' . number_format($amount, 2);
}

function pharmacy_format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    return date('M j, Y', strtotime($date));
}

function pharmacy_time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 172800) {
        return 'Yesterday';
    }
    return date('M j, Y', $timestamp);
}

function pharmacy_medicine_status(array $medicine): string
{
    $qty = (int) ($medicine['stock_quantity'] ?? 0);
    $min = (int) ($medicine['minimum_stock'] ?? 0);
    $exp = $medicine['expiration_date'] ?? null;

    if ($qty <= 0) {
        return 'out';
    }
    if ($exp) {
        $expiryTimestamp = pharmacy_expiration_timestamp((string) $exp);
        $days = $expiryTimestamp === null ? PHP_INT_MAX : (int) floor(($expiryTimestamp - time()) / 86400);
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'expiring';
        }
    }
    if ($min > 0 && $qty <= $min) {
        return 'low';
    }
    return 'ok';
}

function pharmacy_expiration_timestamp(string $expiration): ?int
{
    $expiration = trim($expiration);
    if ($expiration === '') return null;
    if (preg_match('/^(\d{1,2})\/(?:mm|MM)\/(\d{4})$/', $expiration, $match)) {
        $month = (int) $match[1];
        $year = (int) $match[2];
        return $month >= 1 && $month <= 12 ? mktime(23, 59, 59, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year), $year) : null;
    }
    $timestamp = strtotime($expiration);
    return $timestamp === false ? null : $timestamp;
}

function pharmacy_expiration_display(string $expiration): string
{
    if (preg_match('/^\d{1,2}\/(?:mm|MM)\/\d{4}$/', trim($expiration))) return $expiration;
    $timestamp = pharmacy_expiration_timestamp($expiration);
    return $timestamp === null ? '—' : date('m/d/Y', $timestamp);
}

function pharmacy_status_map(): array
{
    return [
        'ok' => ['c' => 'badge-green', 't' => 'In Stock'],
        'low' => ['c' => 'badge-amber', 't' => 'Low Stock'],
        'out' => ['c' => 'badge-red', 't' => 'Out of Stock'],
        'expiring' => ['c' => 'badge-amber', 't' => 'Expiring Soon'],
        'expired' => ['c' => 'badge-red', 't' => 'Expired'],
    ];
}

function pharmacy_order_status_map(): array
{
    return [
        'pending' => ['c' => 'badge-amber', 't' => 'Pending'],
        'confirmed' => ['c' => 'badge-blue', 't' => 'Confirmed'],
        'preparing' => ['c' => 'badge-blue', 't' => 'Preparing'],
        'ready' => ['c' => 'badge-blue', 't' => 'Ready for Pickup'],
        'delivered' => ['c' => 'badge-green', 't' => 'Completed'],
        'cancelled' => ['c' => 'badge-red', 't' => 'Cancelled'],
    ];
}

function pharmacy_get_medicines(): array
{
    $rows = pharmacy_db()->query('SELECT * FROM medicines WHERE ' . pharmacy_scope_sql() . ' ORDER BY name ASC')->fetchAll();
    foreach ($rows as &$row) {
        $row['status'] = pharmacy_medicine_status($row);
    }
    unset($row);
    return $rows;
}

function pharmacy_get_medicine_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = pharmacy_db()->prepare('SELECT * FROM medicines WHERE id = ? AND ' . pharmacy_scope_sql() . ' LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $row['status'] = pharmacy_medicine_status($row);

    return $row;
}

function pharmacy_medicine_editor_payload(array $medicine): array
{
    return [
        'id' => (int) ($medicine['id'] ?? 0),
        'name' => (string) ($medicine['name'] ?? ''),
        'generic_name' => (string) ($medicine['generic_name'] ?? ''),
        'brand' => (string) ($medicine['brand'] ?? ''),
        'dosage_form' => (string) ($medicine['dosage_form'] ?? ''),
        'strength' => (string) ($medicine['strength'] ?? ''),
        'unit' => (string) ($medicine['unit'] ?? ''),
        'category' => (string) ($medicine['category'] ?? ''),
        'description' => (string) ($medicine['description'] ?? ''),
        'ingredients' => (string) ($medicine['ingredients'] ?? ''),
        'batch_number' => (string) ($medicine['batch_number'] ?? ''),
        'expiration_date' => (string) ($medicine['expiration_date'] ?? ''),
        'stock_quantity' => (int) ($medicine['stock_quantity'] ?? 0),
        'unit_price' => (float) ($medicine['unit_price'] ?? 0),
        'selling_price' => (float) ($medicine['selling_price'] ?? 0),
        'prescription_required' => !empty($medicine['prescription_required']),
        'is_active' => !empty($medicine['is_active']),
        'image_url' => pharmacy_medicine_image_url($medicine),
    ];
}

function pharmacy_get_categories(): array
{
    return pharmacy_db()->query('SELECT DISTINCT category FROM medicines WHERE TRIM(category) <> "" AND LOWER(TRIM(category)) <> "other" AND ' . pharmacy_scope_sql() . ' ORDER BY category ASC')->fetchAll(PDO::FETCH_COLUMN);
}

function pharmacy_get_dashboard_stats(): array
{
    $pdo = pharmacy_db();

    $scope = pharmacy_scope_sql();
    $totalMedicines = (int) $pdo->query('SELECT COUNT(*) FROM medicines WHERE ' . $scope)->fetchColumn();
    $inventoryValue = (float) $pdo->query('SELECT COALESCE(SUM(stock_quantity * unit_price), 0) FROM medicines WHERE ' . $scope)->fetchColumn();
    $ordersToday = (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() AND ' . $scope)->fetchColumn();
    $pendingOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE status = "pending" AND ' . $scope)->fetchColumn();
    $monthlyRevenue = (float) $pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = "delivered" AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND ' . $scope)->fetchColumn();
    $newCustomers = (int) $pdo->query('SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND ' . $scope)->fetchColumn();

    $medicines = pharmacy_get_medicines();
    $lowStock = 0;
    $expiring = 0;
    $outOfStock = 0;
    foreach ($medicines as $medicine) {
        $status = $medicine['status'];
        if ($status === 'low') {
            $lowStock++;
        } elseif ($status === 'expiring') {
            $expiring++;
        } elseif ($status === 'out') {
            $outOfStock++;
        }
    }

    return compact('totalMedicines', 'inventoryValue', 'ordersToday', 'pendingOrders', 'monthlyRevenue', 'newCustomers', 'lowStock', 'expiring', 'outOfStock');
}

function pharmacy_get_low_stock_medicines(int $limit = 10): array
{
    $medicines = array_values(array_filter(pharmacy_get_medicines(), static fn(array $m): bool => in_array($m['status'], ['low', 'out'], true)));
    return array_slice($medicines, 0, $limit);
}

function pharmacy_get_expiring_medicines(int $limit = 10): array
{
    $medicines = array_values(array_filter(pharmacy_get_medicines(), static fn(array $m): bool => $m['status'] === 'expiring'));
    usort($medicines, static fn(array $a, array $b): int => strcmp((string) $a['expiration_date'], (string) $b['expiration_date']));
    return array_slice($medicines, 0, $limit);
}

function pharmacy_get_order_by_id(int $orderId): ?array
{
    if ($orderId <= 0) {
        return null;
    }

    $stmt = pharmacy_db()->prepare('
        SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
               c.email AS customer_email,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.id = ? AND ' . pharmacy_scope_sql('o') . '
        LIMIT 1
    ');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function pharmacy_update_order_status(int $orderId, string $status): bool
{
    if ($orderId <= 0) {
        return false;
    }

    $allowed = array_keys(pharmacy_order_status_map());
    if (!in_array($status, $allowed, true)) {
        return false;
    }

    $stmt = pharmacy_db()->prepare('UPDATE orders SET status = ? WHERE id = ? AND ' . pharmacy_scope_sql());
    $stmt->execute([$status, $orderId]);

    return $stmt->rowCount() > 0;
}

function pharmacy_cancel_order(int $orderId, string $reason = ''): bool
{
    $order = pharmacy_get_order_by_id($orderId);
    if (!$order || ($order['status'] ?? '') !== 'pending') {
        return false;
    }

    $reason = trim($reason);
    if ($reason === '') {
        return false;
    }

    $stmt = pharmacy_db()->prepare('UPDATE orders SET status = "cancelled", cancellation_reason = ? WHERE id = ? AND status = "pending" AND ' . pharmacy_scope_sql());
    $stmt->execute([$reason, $orderId]);

    $ok = $stmt->rowCount() > 0;
    if ($ok) {
        pharmacy_notify_resident_order($order, 'cancelled', $reason);
    }

    return $ok;
}

function pharmacy_next_order_status(string $status): ?string
{
    $flow = [
        'pending' => 'confirmed',
        'confirmed' => 'preparing',
        'preparing' => 'ready',
    ];

    return $flow[$status] ?? null;
}

function pharmacy_advance_order_status(int $orderId): ?array
{
    $order = pharmacy_get_order_by_id($orderId);
    if (!$order) {
        return null;
    }

    $current = (string) ($order['status'] ?? '');
    $next = pharmacy_next_order_status($current);
    if ($next === null) {
        return null;
    }

    if (!pharmacy_update_order_status($orderId, $next)) {
        return null;
    }

    pharmacy_notify_resident_order($order, $next);

    $map = pharmacy_order_status_map();

    return [
        'id' => $orderId,
        'from' => $current,
        'to' => $next,
        'label' => $map[$next]['t'] ?? ucfirst($next),
    ];
}

function pharmacy_pickup_proof_url(array $order): ?string
{
    return pharmacy_upload_url((string) ($order['pickup_proof_path'] ?? ''));
}

function pharmacy_order_has_pickup_proof(array $order): bool
{
    return pharmacy_pickup_proof_url($order) !== null;
}

function pharmacy_save_pickup_proof(int $orderId, string $relativePath): bool
{
    if ($orderId <= 0 || trim($relativePath) === '') {
        return false;
    }

    $stmt = pharmacy_db()->prepare('UPDATE orders SET pickup_proof_path = ? WHERE id = ? AND status = "ready" AND ' . pharmacy_scope_sql());
    $stmt->execute([$relativePath, $orderId]);

    return $stmt->rowCount() > 0;
}

function pharmacy_complete_order(int $orderId): bool
{
    $order = pharmacy_get_order_by_id($orderId);
    if (!$order || ($order['status'] ?? '') !== 'ready') {
        return false;
    }

    if (!pharmacy_order_has_pickup_proof($order)) {
        return false;
    }

    $ok = pharmacy_update_order_status($orderId, 'delivered');
    if ($ok) {
        pharmacy_notify_resident_order($order, 'delivered');
    }

    return $ok;
}

function pharmacy_notify_resident_order(array $order, string $status, string $extra = ''): void
{
    require_once dirname(__DIR__, 2) . '/includes/resident-notifications.php';

    $email = strtolower(trim((string) ($order['customer_email'] ?? '')));
    if ($email === '') {
        return;
    }

    $orderNumber = trim((string) ($order['order_number'] ?? ''));
    if ($orderNumber === '') {
        return;
    }

    $pharmacyName = trim((string) (pharmacy_settings()['pharmacy_name'] ?? ''));
    if ($pharmacyName === '') {
        $pharmacyName = 'the pharmacy';
    }

    $copy = match ($status) {
        'confirmed' => ['Order confirmed', $pharmacyName . ' confirmed your order #' . $orderNumber . '.'],
        'preparing' => ['Order is being prepared', $pharmacyName . ' is preparing order #' . $orderNumber . '.'],
        'ready' => ['Your order is ready for pickup', 'Order #' . $orderNumber . ' at ' . $pharmacyName . ' is ready for pickup.'],
        'delivered' => ['Order completed', 'Order #' . $orderNumber . ' was picked up. Thank you for ordering with AddToMar.'],
        'cancelled' => ['Order cancelled', 'Order #' . $orderNumber . ' was cancelled' . ($extra !== '' ? ' — ' . $extra : '.')],
        default => ['Order update', 'Order #' . $orderNumber . ' is now ' . $status . '.'],
    };

    resident_notification_add($email, $copy[0], $copy[1], 'order', '', $orderNumber);
}

function pharmacy_get_orders(int $limit = 50): array
{
    $stmt = pharmacy_db()->prepare('
        SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE ' . pharmacy_scope_sql('o') . '
        ORDER BY o.created_at DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function pharmacy_get_order_items(int $orderId): array
{
    $stmt = pharmacy_db()->prepare('
        SELECT oi.*, m.image_path
        FROM order_items oi
        LEFT JOIN medicines m ON m.id = oi.medicine_id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ');
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['image_url'] = pharmacy_medicine_image_url($item) ?? '';
    }
    unset($item);

    return $items;
}

function pharmacy_get_order_status_counts(): array
{
    $rows = pharmacy_db()->query('SELECT status, COUNT(*) AS total FROM orders WHERE ' . pharmacy_scope_sql() . ' GROUP BY status')->fetchAll();
    $counts = [
        'pending' => 0,
        'confirmed' => 0,
        'preparing' => 0,
        'ready' => 0,
        'delivered' => 0,
        'cancelled' => 0,
    ];
    foreach ($rows as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }
    return $counts;
}

function pharmacy_order_items_summary(array $items): string
{
    if ($items === []) {
        return 'No medicines listed';
    }

    $first = $items[0];
    $summary = (string) ($first['medicine_name'] ?? 'Medicine') . ' × ' . (int) ($first['quantity'] ?? 1);
    if (count($items) > 1) {
        $summary .= ', +' . (count($items) - 1) . ' more';
    }

    foreach ($items as $item) {
        if (!empty($item['prescription_required'])) {
            $summary .= ' · Rx attached';
            break;
        }
    }

    return $summary;
}

function pharmacy_order_tabs(): array
{
    return [
        ['status' => 'pending', 'label' => 'Pending'],
        ['status' => 'confirmed', 'label' => 'Confirmed'],
        ['status' => 'preparing', 'label' => 'Preparing'],
        ['status' => 'ready', 'label' => 'Ready for Pickup'],
        ['status' => 'delivered', 'label' => 'Completed'],
        ['status' => 'cancelled', 'label' => 'Cancelled'],
    ];
}

function pharmacy_orders_url(string $status = 'pending', int $orderId = 0): string
{
    $params = ['view' => 'orders', 'status' => $status];
    if ($orderId > 0) {
        $params['order_id'] = $orderId;
    }

    return 'index.php?' . http_build_query($params);
}

function pharmacy_active_view(array $allowedViews): string
{
    $view = trim((string) ($_GET['view'] ?? 'dashboard'));

    return in_array($view, $allowedViews, true) ? $view : 'dashboard';
}

function pharmacy_view_class(string $view, string $activeView): string
{
    return 'view' . ($activeView === $view ? ' active' : '');
}

function pharmacy_get_customers(): array
{
    $scope = pharmacy_scope_sql('c');
    $orderScope = pharmacy_scope_sql('o');
    $sql = '
        SELECT c.*,
               COUNT(o.id) AS order_count,
               COALESCE(SUM(CASE WHEN o.status = "delivered" THEN o.total_amount ELSE 0 END), 0) AS total_spent,
               MAX(o.created_at) AS last_order_at
        FROM customers c
        LEFT JOIN orders o ON o.customer_id = c.id AND ' . $orderScope . '
        WHERE ' . $scope . '
        GROUP BY c.id
        ORDER BY c.name ASC
    ';
    return pharmacy_db()->query($sql)->fetchAll();
}

function pharmacy_get_suppliers(): array
{
    return pharmacy_db()->query('SELECT * FROM suppliers WHERE ' . pharmacy_scope_sql() . ' ORDER BY name ASC')->fetchAll();
}

function pharmacy_get_notifications(): array
{
    $notifications = [];

    $orders = pharmacy_db()->query('
        SELECT o.*, c.name AS customer_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.status = "pending" AND ' . pharmacy_scope_sql('o') . '
        ORDER BY o.created_at DESC
        LIMIT 5
    ')->fetchAll();

    foreach ($orders as $order) {
        $notifications[] = [
            'type' => 'new-order',
            'unread' => true,
            'title' => ($order['customer_name'] ?: 'Customer') . ' placed a new order worth ' . pharmacy_format_money((float) $order['total_amount']),
            'subtitle' => $order['order_number'],
            'time' => pharmacy_time_ago($order['created_at']),
            'initials' => pharmacy_initials((string) ($order['customer_name'] ?: 'CU')),
        ];
    }

    foreach (pharmacy_get_low_stock_medicines(5) as $medicine) {
        $notifications[] = [
            'type' => 'low-stock',
            'unread' => true,
            'title' => $medicine['name'] . ' is low on stock — only ' . (int) $medicine['stock_quantity'] . ' units left',
            'subtitle' => 'Minimum threshold: ' . (int) $medicine['minimum_stock'] . ' units',
            'time' => pharmacy_time_ago($medicine['updated_at'] ?? $medicine['created_at']),
            'initials' => strtoupper(substr($medicine['name'], 0, 2)),
        ];
    }

    foreach (pharmacy_get_expiring_medicines(5) as $medicine) {
        $expiryTimestamp = pharmacy_expiration_timestamp((string) $medicine['expiration_date']);
        $days = $expiryTimestamp === null ? 0 : (int) floor(($expiryTimestamp - time()) / 86400);
        $notifications[] = [
            'type' => 'expiring',
            'unread' => false,
            'title' => $medicine['name'] . ' batch #' . $medicine['batch_number'] . ' expires in ' . max($days, 0) . ' days',
            'subtitle' => (int) $medicine['stock_quantity'] . ' units in this batch',
            'time' => pharmacy_time_ago($medicine['updated_at'] ?? $medicine['created_at']),
            'initials' => strtoupper(substr($medicine['name'], 0, 2)),
        ];
    }

    return $notifications;
}

function pharmacy_get_chart_data(): array
{
    $pdo = pharmacy_db();
    $orderScope = pharmacy_scope_sql();
    $orderScopeAlias = pharmacy_scope_sql('o');
    $medicineScope = pharmacy_scope_sql();

    $dailyLabels = [];
    $dailyOrders = [];
    $dailyRevenue = [];
    for ($i = 13; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $dailyLabels[] = date('M j', strtotime($date));

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND ' . $orderScope);
        $stmt->execute([$date]);
        $dailyOrders[] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = "delivered" AND DATE(created_at) = ? AND ' . $orderScope);
        $stmt->execute([$date]);
        $dailyRevenue[] = round(((float) $stmt->fetchColumn()) / 1000, 1);
    }

    $bestSelling = $pdo->query('
        SELECT oi.medicine_name AS label, SUM(oi.quantity) AS total
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE o.status = "delivered" AND ' . $orderScopeAlias . '
        GROUP BY oi.medicine_name
        ORDER BY total DESC
        LIMIT 5
    ')->fetchAll();

    $salesMonths = [];
    $salesRevenue = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));
        $salesMonths[] = date('M', strtotime($month . '-01'));
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = "delivered" AND DATE_FORMAT(created_at, "%Y-%m") = ? AND ' . $orderScope);
        $stmt->execute([$month]);
        $salesRevenue[] = round((float) $stmt->fetchColumn(), 0);
    }

    $categoryRows = $pdo->query('
        SELECT m.category AS label, COALESCE(SUM(oi.quantity), 0) AS total
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        LEFT JOIN medicines m ON m.id = oi.medicine_id
        WHERE o.status = "delivered" AND ' . $orderScopeAlias . '
        GROUP BY m.category
        ORDER BY total DESC
    ')->fetchAll();

    $stockIn = [];
    $stockOut = [];
    $trendMonths = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} months"));
        $trendMonths[] = date('M', strtotime($month . '-01'));
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(stock_quantity), 0) FROM medicines WHERE DATE_FORMAT(created_at, "%Y-%m") = ? AND ' . $medicineScope);
        $stmt->execute([$month]);
        $stockIn[] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.status = "delivered" AND DATE_FORMAT(o.created_at, "%Y-%m") = ? AND ' . $orderScopeAlias);
        $stmt->execute([$month]);
        $stockOut[] = (int) $stmt->fetchColumn();
    }

    return [
        'daily' => [
            'labels' => $dailyLabels,
            'orders' => $dailyOrders,
            'revenue' => $dailyRevenue,
        ],
        'bestSelling' => $bestSelling,
        'sales' => [
            'labels' => $salesMonths,
            'revenue' => $salesRevenue,
        ],
        'categories' => $categoryRows,
        'inventoryTrend' => [
            'labels' => $trendMonths,
            'stockIn' => $stockIn,
            'stockOut' => $stockOut,
        ],
    ];
}

function pharmacy_get_sales_stats(): array
{
    $pdo = pharmacy_db();
    $orderScope = pharmacy_scope_sql();
    $revenue = (float) $pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = "delivered" AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND ' . $orderScope)->fetchColumn();
    $profit = $revenue * 0.3;
    $orderCount = (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE status = "delivered" AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND ' . $orderScope)->fetchColumn();
    $avgOrder = $orderCount > 0 ? $revenue / $orderCount : 0;
    $weeklyOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND ' . $orderScope)->fetchColumn();

    return compact('revenue', 'profit', 'avgOrder', 'weeklyOrders');
}

function pharmacy_get_recent_transactions(int $limit = 10): array
{
    $stmt = pharmacy_db()->prepare('
        SELECT o.*, c.name AS customer_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.status = "delivered" AND ' . pharmacy_scope_sql('o') . '
        ORDER BY o.created_at DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function pharmacy_get_top_selling(int $limit = 5): array
{
    $stmt = pharmacy_db()->prepare('
        SELECT oi.medicine_name AS name, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price) AS revenue
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE o.status = "delivered" AND ' . pharmacy_scope_sql('o') . '
        GROUP BY oi.medicine_name
        ORDER BY units DESC
        LIMIT ?
    ');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function pharmacy_get_analytics_stats(): array
{
    $medicines = pharmacy_get_medicines();
    $inStock = 0;
    $low = 0;
    $out = 0;
    $expiring = 0;
    foreach ($medicines as $medicine) {
        match ($medicine['status']) {
            'low' => $low++,
            'out' => $out++,
            'expiring' => $expiring++,
            default => $inStock++,
        };
    }
    return compact('inStock', 'low', 'out', 'expiring');
}

function pharmacy_report_buttons(string $report): string
{
    $report = htmlspecialchars($report, ENT_QUOTES, 'UTF-8');
    return '<button type="button" class="chip-btn" data-report="' . $report . '" data-report-format="pdf">PDF</button>'
        . '<button type="button" class="chip-btn" data-report="' . $report . '" data-report-format="excel">Excel</button>'
        . '<button type="button" class="chip-btn" data-report="' . $report . '" data-report-format="csv">CSV</button>';
}

function pharmacy_render_empty(string $message): string
{
    return '<div class="empty-state">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
}

function pharmacy_store_slug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '', $slug) ?? '';

    return $slug !== '' ? $slug : 'pharmacy';
}

function pharmacy_upload_url(string $path): ?string
{
    $path = trim($path);
    if ($path === '') {
        return null;
    }

    $absolute = dirname(__DIR__, 2) . '/' . ltrim($path, '/');
    if (!is_file($absolute)) {
        return null;
    }

    return function_exists('app_url') ? app_url($path) : $path;
}

function pharmacy_prescription_url(array $order): ?string
{
    return pharmacy_upload_url((string) ($order['prescription_path'] ?? ''));
}

function pharmacy_item_prescription_url(array $item, array $order = []): ?string
{
    $itemUrl = pharmacy_upload_url((string) ($item['prescription_path'] ?? ''));
    if ($itemUrl) {
        return $itemUrl;
    }

    return $order !== [] ? pharmacy_prescription_url($order) : null;
}

function pharmacy_order_requires_prescription(array $order, array $items = []): bool
{
    if (trim((string) ($order['prescription_path'] ?? '')) !== '') {
        return true;
    }

    foreach ($items as $item) {
        if (!empty($item['prescription_required'])) {
            return true;
        }
    }

    return false;
}

function pharmacy_order_down_payment(array $order, float $defaultPercent = 50): float
{
    $total = max(0, (float) ($order['total_amount'] ?? 0));
    $down = (float) ($order['down_payment'] ?? 0);

    if ($down > 0 && $down < $total) {
        return round($down, 2);
    }

    if ($total <= 0) {
        return 0;
    }

    return round($total * ($defaultPercent / 100), 2);
}

function pharmacy_order_balance_on_pickup(array $order, float $defaultPercent = 50): float
{
    $total = max(0, (float) ($order['total_amount'] ?? 0));

    return max(0, round($total - pharmacy_order_down_payment($order, $defaultPercent), 2));
}

function pharmacy_order_down_payment_percent(array $order, float $defaultPercent = 50): int
{
    $total = max(0, (float) ($order['total_amount'] ?? 0));
    if ($total <= 0) {
        return 0;
    }

    $down = pharmacy_order_down_payment($order, $defaultPercent);

    return (int) round(($down / $total) * 100);
}

function pharmacy_medicine_db_image_ids(bool $refresh = false): array
{
    static $ids = null;
    if ($refresh) {
        $ids = null;
    }
    if (is_array($ids)) {
        return $ids;
    }

    try {
        $rows = pharmacy_db()->query('SELECT medicine_id FROM medicine_images')->fetchAll(PDO::FETCH_COLUMN);
        $ids = [];
        foreach ($rows ?: [] as $rowId) {
            $ids[(int) $rowId] = true;
        }
    } catch (Throwable) {
        $ids = [];
    }

    return $ids;
}

function pharmacy_medicine_store_image(int $medicineId, string $filename, string $mime, string $content): void
{
    if ($medicineId <= 0 || $content === '') {
        return;
    }

    $stmt = pharmacy_db()->prepare(
        'INSERT INTO medicine_images (medicine_id, filename, mime, content)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE filename = VALUES(filename), mime = VALUES(mime), content = VALUES(content)'
    );
    $stmt->execute([$medicineId, $filename, $mime, $content]);
    pharmacy_medicine_db_image_ids(true);
}

function pharmacy_medicine_get_image(int $medicineId): ?array
{
    if ($medicineId <= 0) {
        return null;
    }

    try {
        $stmt = pharmacy_db()->prepare(
            'SELECT medicine_id, filename, mime, content FROM medicine_images WHERE medicine_id = ? LIMIT 1'
        );
        $stmt->execute([$medicineId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        if (is_resource($row['content'] ?? null)) {
            $row['content'] = stream_get_contents($row['content']);
        }

        return $row;
    } catch (Throwable) {
        return null;
    }
}

function pharmacy_medicine_image_url(array $medicine): ?string
{
    $id = (int) ($medicine['id'] ?? $medicine['medicine_id'] ?? 0);
    $path = trim((string) ($medicine['image_path'] ?? ''));
    $hasDb = $id > 0 && (
        str_contains($path, 'medicine-image.php')
        || isset(pharmacy_medicine_db_image_ids()[$id])
    );
    if ($hasDb) {
        return function_exists('app_url') ? app_url('medicine-image.php?id=' . $id) : ('medicine-image.php?id=' . $id);
    }

    if ($path === '') {
        return null;
    }

    $absolute = dirname(__DIR__, 2) . '/' . ltrim($path, '/');
    if ($id > 0 && is_file($absolute)) {
        $bytes = file_get_contents($absolute);
        if (is_string($bytes) && $bytes !== '') {
            $filename = basename($path);
            $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'application/octet-stream',
            };
            try {
                pharmacy_medicine_store_image($id, $filename, $mime, $bytes);
                pharmacy_db()->prepare('UPDATE medicines SET image_path = ? WHERE id = ?')
                    ->execute(['medicine-image.php?id=' . $id, $id]);
            } catch (Throwable) {
                // Fall back to the disk URL below if the database copy cannot be stored.
            }
            if (isset(pharmacy_medicine_db_image_ids()[$id])) {
                return function_exists('app_url') ? app_url('medicine-image.php?id=' . $id) : ('medicine-image.php?id=' . $id);
            }
        }
    }

    if (!is_file($absolute)) {
        return null;
    }

    return function_exists('app_url') ? app_url($path) : $path;
}

function pharmacy_medicine_is_new(array $medicine, int $days = 14): bool
{
    $created = strtotime((string) ($medicine['created_at'] ?? ''));
    if ($created === false) {
        return false;
    }

    return (time() - $created) <= ($days * 86400);
}

function pharmacy_medicine_requires_prescription(array $medicine): bool
{
    return !empty($medicine['prescription_required']);
}

function pharmacy_medicine_card_price(array $medicine): string
{
    $selling = (float) ($medicine['selling_price'] ?? 0);
    if ($selling > 0) {
        return '₱' . number_format($selling, 2);
    }

    $unit = (float) ($medicine['unit_price'] ?? 0);
    if ($unit > 0) {
        return '₱' . number_format($unit, 2);
    }

    return '₱0.00';
}
