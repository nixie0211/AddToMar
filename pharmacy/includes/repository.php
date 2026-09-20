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

function pharmacy_order_progress_index(string $status): int
{
    return match (strtolower(trim($status))) {
        'cancelled' => 0,
        'confirmed' => 2,
        'preparing' => 3,
        'ready' => 4,
        'picked_up', 'pickedup', 'delivered', 'completed' => 5,
        default => 1,
    };
}

function pharmacy_order_progress_stamp(?string $createdAt): string
{
    $timestamp = strtotime((string) $createdAt);
    if ($timestamp === false) {
        return '';
    }

    return date('M j, Y • g:i A', $timestamp);
}

function pharmacy_order_progress_icon(string $name): string
{
    $paths = [
        'check' => '<path d="m5 13 4 4L19 7"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        'capsule' => '<path d="m8 5 11 11a3.5 3.5 0 0 1-5 5L3 10a3.5 3.5 0 0 1 5-5Z"/><path d="m7 14 7-7"/>',
        'store' => '<path d="M3 10h18l-1.2-5H4.2z"/><path d="M4 10v10h16V10M9 20v-6h6v6"/>',
    ];
    $body = $paths[$name] ?? $paths['check'];

    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function pharmacy_order_progress_html(string $status, string $stamp): string
{
    $current = pharmacy_order_progress_index($status);
    $cancelled = strtolower(trim($status)) === 'cancelled';
    $steps = [
        ['name' => 'Order placed', 'icon' => 'check'],
        ['name' => 'Pending', 'icon' => 'clock'],
        ['name' => 'Confirmed', 'icon' => 'check'],
        ['name' => 'Preparing', 'icon' => 'capsule'],
        ['name' => 'Ready for pick up', 'icon' => 'store'],
        ['name' => 'Completed', 'icon' => 'check'],
    ];
    $html = '<section class="order-progress" aria-label="Order progress">';
    foreach ($steps as $index => $step) {
        if ($cancelled && $index > 0) {
            $state = 'is-muted';
        } elseif ($index < $current) {
            $state = 'is-done';
        } elseif ($index === $current) {
            $state = 'is-current';
        } else {
            $state = 'is-muted';
        }
        $icon = ($state === 'is-done' || ($state === 'is-current' && $index === 5)) ? 'check' : $step['icon'];
        $time = $state !== 'is-muted' ? $stamp : '';
        $html .= '<div class="order-progress-step ' . $state . ($time !== '' ? ' is-dated' : '') . '">';
        $html .= '<span class="order-progress-node">' . pharmacy_order_progress_icon($icon) . '</span>';
        $html .= '<strong>' . htmlspecialchars($step['name'], ENT_QUOTES, 'UTF-8') . '</strong>';
        $html .= '<small>' . ($time !== '' ? htmlspecialchars($time, ENT_QUOTES, 'UTF-8') : '&nbsp;') . '</small>';
        $html .= '</div>';
        if ($index < count($steps) - 1) {
            $html .= '<span class="order-progress-rail' . ($index < $current ? ' is-done' : '') . '"></span>';
        }
    }
    $html .= '</section>';

    return $html;
}

function pharmacy_get_medicines(): array
{
    $rows = pharmacy_db()->query('SELECT * FROM medicines WHERE ' . pharmacy_scope_sql() . ' ORDER BY created_at DESC, id DESC')->fetchAll();
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
        'image_url' => array_key_exists('image_url', $medicine)
            ? ($medicine['image_url'] !== null && $medicine['image_url'] !== '' ? (string) $medicine['image_url'] : null)
            : pharmacy_medicine_image_url($medicine),
    ];
}

function pharmacy_saved_medicine_image_url(int $id): string
{
    $base = function_exists('app_url')
        ? app_url('medicine-image.php?id=' . $id)
        : ('../medicine-image.php?id=' . $id);
    $sep = str_contains($base, '?') ? '&' : '?';
    return $base . $sep . 'v=' . time();
}

function pharmacy_medicine_save_client_payload(int $id, array $fields, ?string $imageUrl, bool $createdNow): array
{
    $row = $fields;
    $row['id'] = $id;
    $row['image_url'] = $imageUrl;
    $row['minimum_stock'] = (int) ($row['minimum_stock'] ?? 0);
    $row['status'] = pharmacy_medicine_status($row);
    $statusKey = (string) $row['status'];
    $status = pharmacy_status_map()[$statusKey] ?? pharmacy_status_map()['ok'];
    $expiration = trim((string) ($row['expiration_date'] ?? ''));

    return [
        'medicine' => pharmacy_medicine_editor_payload($row),
        'card' => [
            'id' => $id,
            'name' => (string) ($row['name'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'status' => $statusKey,
            'status_class' => (string) ($status['c'] ?? 'badge-green'),
            'status_label' => (string) ($status['t'] ?? 'In Stock'),
            'stock' => (int) ($row['stock_quantity'] ?? 0),
            'unit_price' => (float) ($row['unit_price'] ?? 0),
            'selling_price' => (float) ($row['selling_price'] ?? 0),
            'price_label' => pharmacy_medicine_card_price($row),
            'expiration_display' => $expiration === '' ? '—' : pharmacy_expiration_display($expiration),
            'batch_number' => trim((string) ($row['batch_number'] ?? '')) !== '' ? (string) $row['batch_number'] : '—',
            'image_url' => $imageUrl,
            'is_new' => pharmacy_medicine_is_new($row),
            'requires_rx' => !empty($row['prescription_required']),
            'search' => strtolower(trim(implode(' ', array_filter([
                (string) ($row['name'] ?? ''),
                (string) ($row['generic_name'] ?? ''),
                (string) ($row['brand'] ?? ''),
                (string) ($row['category'] ?? ''),
            ])))),
        ],
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

function pharmacy_get_order_by_id(int $orderId, bool $scoped = true): ?array
{
    if ($orderId <= 0) {
        return null;
    }

    $sql = '
        SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
               c.email AS customer_email,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.id = ?
    ';
    if ($scoped) {
        $sql .= ' AND ' . pharmacy_scope_sql('o');
    }
    $sql .= ' LIMIT 1';

    $stmt = pharmacy_db()->prepare($sql);
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function pharmacy_update_order_status(int $orderId, string $status, ?string $fromStatus = null): bool
{
    if ($orderId <= 0) {
        return false;
    }

    $allowed = array_keys(pharmacy_order_status_map());
    if (!in_array($status, $allowed, true)) {
        return false;
    }

    if ($fromStatus !== null && $fromStatus !== '') {
        $stmt = pharmacy_db()->prepare(
            'UPDATE orders SET status = ? WHERE id = ? AND status = ? AND ' . pharmacy_scope_sql()
        );
        $stmt->execute([$status, $orderId, $fromStatus]);
    } else {
        $stmt = pharmacy_db()->prepare('UPDATE orders SET status = ? WHERE id = ? AND ' . pharmacy_scope_sql());
        $stmt->execute([$status, $orderId]);
    }

    $fresh = pharmacy_get_order_by_id($orderId);

    return $fresh !== null && (string) ($fresh['status'] ?? '') === $status;
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
    $map = pharmacy_order_status_map();

    if ($next === null) {
        return null;
    }

    $didUpdate = pharmacy_update_order_status($orderId, $next, $current);
    $fresh = pharmacy_get_order_by_id($orderId);
    $freshStatus = (string) ($fresh['status'] ?? '');

    if ($freshStatus !== $next) {
        return null;
    }

    if ($didUpdate && $current !== $next) {
        pharmacy_notify_resident_order($order, $next);
    }

    return [
        'id' => $orderId,
        'from' => $current === $next ? (string) ($order['status'] ?? $current) : $current,
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

function pharmacy_get_orders(int $limit = 50, ?string $status = null): array
{
    $sql = '
        SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE ' . pharmacy_scope_sql('o') . '
    ';
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= ' AND o.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY o.updated_at DESC, o.created_at DESC, o.id DESC LIMIT ' . max(1, $limit);

    $stmt = pharmacy_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function pharmacy_hydrate_order_item(array $item): array
{
    $item['image_url'] = pharmacy_medicine_image_url([
        'id' => (int) ($item['catalog_medicine_id'] ?? $item['medicine_id'] ?? 0),
        'medicine_id' => (int) ($item['catalog_medicine_id'] ?? $item['medicine_id'] ?? 0),
        'image_path' => (string) ($item['image_path'] ?? ''),
    ]) ?? '';

    return $item;
}

function pharmacy_get_order_items_map(array $orderIds): array
{
    $ids = [];
    foreach ($orderIds as $orderId) {
        $orderId = (int) $orderId;
        if ($orderId > 0) {
            $ids[$orderId] = $orderId;
        }
    }
    $ids = array_values($ids);
    $map = [];
    foreach ($ids as $orderId) {
        $map[$orderId] = [];
    }
    if ($ids === []) {
        return $map;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = pharmacy_db()->prepare('
        SELECT oi.*, m.image_path, m.id AS catalog_medicine_id
        FROM order_items oi
        LEFT JOIN medicines m ON m.id = oi.medicine_id
        WHERE oi.order_id IN (' . $placeholders . ')
        ORDER BY oi.id ASC
    ');
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $item) {
        $orderId = (int) ($item['order_id'] ?? 0);
        if ($orderId <= 0) {
            continue;
        }
        $map[$orderId][] = pharmacy_hydrate_order_item($item);
    }

    return $map;
}

function pharmacy_get_order_items(int $orderId): array
{
    return pharmacy_get_order_items_map([$orderId])[$orderId] ?? [];
}

function pharmacy_order_table_items_map(array $orderIds): array
{
    $ids = [];
    foreach ($orderIds as $orderId) {
        $orderId = (int) $orderId;
        if ($orderId > 0) {
            $ids[$orderId] = $orderId;
        }
    }
    $ids = array_values($ids);
    $map = [];
    foreach ($ids as $orderId) {
        $map[$orderId] = [];
    }
    if ($ids === []) {
        return $map;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = pharmacy_db()->prepare('
        SELECT order_id, medicine_name, quantity, prescription_required
        FROM order_items
        WHERE order_id IN (' . $placeholders . ')
        ORDER BY id ASC
    ');
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $item) {
        $orderId = (int) ($item['order_id'] ?? 0);
        if ($orderId <= 0) {
            continue;
        }
        $map[$orderId][] = $item;
    }

    return $map;
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
        ['status' => 'all', 'label' => 'All'],
        ['status' => 'pending', 'label' => 'Pending'],
        ['status' => 'confirmed', 'label' => 'Confirmed'],
        ['status' => 'preparing', 'label' => 'Preparing'],
        ['status' => 'ready', 'label' => 'Ready for Pickup'],
        ['status' => 'delivered', 'label' => 'Completed'],
        ['status' => 'cancelled', 'label' => 'Cancelled'],
    ];
}

function pharmacy_order_avatar_tone(int $index): array
{
    $tones = [
        ['bg' => '#e8f1ff', 'fg' => '#2563eb'],
        ['bg' => '#e6f7f1', 'fg' => '#0d9488'],
        ['bg' => '#fde8ef', 'fg' => '#e11d48'],
        ['bg' => '#fff1e6', 'fg' => '#d97706'],
        ['bg' => '#f3e8ff', 'fg' => '#7c3aed'],
        ['bg' => '#ecfdf3', 'fg' => '#16a34a'],
    ];

    return $tones[$index % count($tones)];
}

function pharmacy_orders_url(string $status = 'pending', int $orderId = 0): string
{
    $params = ['view' => 'orders', 'status' => $status];
    if ($orderId > 0) {
        $params['order_id'] = $orderId;
    }

    return 'index.php?' . http_build_query($params);
}

function pharmacy_orders_allowed_status(string $status): string
{
    $allowed = array_merge(['all'], array_keys(pharmacy_order_status_map()));
    $status = trim($status);

    return in_array($status, $allowed, true) ? $status : 'pending';
}

function pharmacy_order_list_row(array $order, array $items, int $index): array
{
    $status = (string) ($order['status'] ?? 'pending');
    $meta = pharmacy_order_status_map()[$status] ?? ['c' => 'badge-gray', 't' => ucfirst($status)];
    $createdAt = strtotime((string) ($order['created_at'] ?? '')) ?: time();
    $customer = trim((string) ($order['customer_name'] ?? 'Customer'));
    $reason = trim((string) ($order['cancellation_reason'] ?? ''));
    $summary = pharmacy_order_items_summary($items);
    if ($status === 'cancelled' && $reason !== '') {
        $summary .= ' · ' . $reason;
    }

    return [
        'id' => (int) ($order['id'] ?? 0),
        'order_number' => (string) ($order['order_number'] ?? 'ORD-0000'),
        'status' => $status,
        'status_label' => $meta['t'],
        'customer_name' => $customer,
        'customer_initials' => pharmacy_initials($customer),
        'avatar' => pharmacy_order_avatar_tone($index),
        'medicines_summary' => $summary,
        'date_label' => date('d/m/Y', $createdAt),
        'time_label' => date('g:i a', $createdAt),
        'amount_label' => pharmacy_format_money((float) ($order['total_amount'] ?? 0)),
        'cancellation_reason' => $reason,
        'detail' => pharmacy_order_detail_from_row($order, $items),
    ];
}

function pharmacy_orders_list_payload_from(array $orders, array $itemsCache, array $counts, string $status): array
{
    $status = pharmacy_orders_allowed_status($status);
    $rows = [];
    foreach (array_values($orders) as $index => $order) {
        $orderId = (int) ($order['id'] ?? 0);
        $rows[] = pharmacy_order_list_row($order, $itemsCache[$orderId] ?? [], $index);
    }

    return [
        'success' => true,
        'status' => $status,
        'counts' => $counts,
        'total' => (int) array_sum($counts),
        'orders' => $rows,
    ];
}

function pharmacy_orders_list_payload(string $status = 'pending'): array
{
    $status = pharmacy_orders_allowed_status($status);
    $counts = pharmacy_get_order_status_counts();
    $orders = $status === 'all'
        ? pharmacy_get_orders(300)
        : pharmacy_get_orders(300, $status);
    $itemsCache = pharmacy_get_order_items_map(array_map(static fn(array $order): int => (int) ($order['id'] ?? 0), $orders));

    return pharmacy_orders_list_payload_from($orders, $itemsCache, $counts, $status);
}

function pharmacy_order_detail_from_row(array $order, array $items): array
{
    $status = (string) ($order['status'] ?? 'pending');
    $meta = pharmacy_order_status_map()[$status] ?? ['c' => 'badge-gray', 't' => ucfirst($status)];
    $customer = trim((string) ($order['customer_name'] ?? 'Customer'));
    $contact = trim((string) ($order['customer_phone'] ?? ''));
    $address = trim((string) ($order['customer_address'] ?? ''));
    $next = pharmacy_next_order_status($status);
    $total = max(0, (float) ($order['total_amount'] ?? 0));
    $down = pharmacy_order_down_payment($order);
    $isCompleted = $status === 'delivered';
    $pickupUrl = pharmacy_pickup_proof_url($order);
    $percent = pharmacy_order_down_payment_percent($order);
    $prices = pharmacy_order_vat_breakdown($order, $items);
    $itemRows = [];
    foreach ($items as $item) {
        $qty = (int) ($item['quantity'] ?? 1);
        $itemRows[] = [
            'id' => (int) ($item['id'] ?? 0),
            'name' => (string) ($item['medicine_name'] ?? 'Medicine'),
            'quantity' => $qty,
            'image_url' => trim((string) ($item['image_url'] ?? '')),
            'prescription_required' => !empty($item['prescription_required']),
            'prescription_url' => pharmacy_item_prescription_url($item, $order) ?: '',
            'note' => trim((string) ($item['item_note'] ?? '')),
            'line_total_label' => pharmacy_format_money((float) ($item['unit_price'] ?? 0) * $qty),
        ];
    }

    return [
        'id' => (int) ($order['id'] ?? 0),
        'order_number' => (string) ($order['order_number'] ?? 'ORD-0000'),
        'status' => $status,
        'status_label' => $meta['t'],
        'status_class' => $meta['c'],
        'customer_name' => $customer,
        'customer_initials' => pharmacy_initials($customer),
        'customer_line' => implode(' · ', array_filter([$contact, $address])) ?: 'No contact details',
        'pharmacy_name' => (string) ($order['pharmacy_name'] ?? 'Pharmacy'),
        'items' => $itemRows,
        'created_label' => pharmacy_format_date((string) ($order['created_at'] ?? '')),
        'created_stamp' => pharmacy_order_progress_stamp((string) ($order['created_at'] ?? '')),
        'subtotal_label' => $prices['subtotal_label'],
        'vat_label' => $prices['vat_label'],
        'vat_percent' => $prices['vat_percent'],
        'total_label' => $prices['total_label'],
        'paid_label' => pharmacy_format_money($isCompleted ? $total : $down),
        'paid_percent' => $isCompleted ? 100 : $percent,
        'paid_note_percent' => $percent,
        'balance_label' => pharmacy_format_money($isCompleted ? 0 : pharmacy_order_balance_on_pickup($order)),
        'balance_percent' => $isCompleted ? 0 : max(0, 100 - $percent),
        'payment_note' => $isCompleted
            ? '✓ All payments have been settled. Your order is fully paid.'
            : 'Customer pays the remaining balance when collecting this order at the pharmacy.',
        'is_pending' => $status === 'pending',
        'is_ready' => $status === 'ready',
        'is_completed' => $isCompleted,
        'is_cancelled' => $status === 'cancelled',
        'can_view_prescription' => pharmacy_order_allows_prescription_view($status),
        'prescription_url' => pharmacy_prescription_url($order) ?: '',
        'next_status' => $next,
        'next_label' => $next !== null ? (string) (pharmacy_order_status_map()[$next]['t'] ?? ucfirst($next)) : '',
        'cancellation_reason' => trim((string) ($order['cancellation_reason'] ?? '')),
        'has_pickup_proof' => $pickupUrl !== null,
        'pickup_proof_url' => $pickupUrl,
        'pickup_proof_name' => basename((string) ($order['pickup_proof_path'] ?? 'pickup-proof.jpg')),
        'pickup_proof_kind' => strtolower(pathinfo((string) ($order['pickup_proof_path'] ?? ''), PATHINFO_EXTENSION)) === 'pdf'
            ? 'pdf'
            : ($pickupUrl ? 'img' : 'empty'),
    ];
}

function pharmacy_order_detail_payload(int $orderId): ?array
{
    $order = pharmacy_get_order_by_id($orderId);
    if ($order === null) {
        return null;
    }

    return pharmacy_order_detail_from_row($order, pharmacy_get_order_items($orderId));
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

function pharmacy_notification_id(string $type, string $subject): string
{
    $type = strtolower(trim($type));
    $subject = trim($subject);

    return $type . ':' . $subject;
}

function pharmacy_notification_read_ids(): array
{
    $pharmacyId = pharmacy_current_id();
    if ($pharmacyId === '') {
        return [];
    }

    try {
        $stmt = pharmacy_db()->prepare('SELECT notice_id FROM pharmacy_notification_reads WHERE pharmacy_id = ?');
        $stmt->execute([$pharmacyId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) {
        return [];
    }

    return array_values(array_filter(array_map('strval', is_array($ids) ? $ids : [])));
}

function pharmacy_notification_is_read(string $noticeId, ?array $readIds = null): bool
{
    $noticeId = trim($noticeId);
    if ($noticeId === '') {
        return false;
    }

    $readIds ??= pharmacy_notification_read_ids();

    return in_array($noticeId, $readIds, true);
}

function pharmacy_notification_unread_count(?array $notifications = null): int
{
    $notifications ??= pharmacy_get_notifications();

    return count(array_filter($notifications, static fn(array $item): bool => !empty($item['unread'])));
}

function pharmacy_notification_mark_read(string $noticeId): array
{
    $pharmacyId = pharmacy_current_id();
    $noticeId = trim($noticeId);
    if ($pharmacyId === '' || $noticeId === '' || strlen($noticeId) > 160) {
        return ['ok' => false, 'unread' => pharmacy_notification_unread_count()];
    }

    if (!preg_match('/^[a-z0-9\-]+:[A-Za-z0-9_\-]+$/', $noticeId)) {
        return ['ok' => false, 'unread' => pharmacy_notification_unread_count()];
    }

    $stmt = pharmacy_db()->prepare('
        INSERT IGNORE INTO pharmacy_notification_reads (pharmacy_id, notice_id, read_at)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$pharmacyId, $noticeId, date('Y-m-d H:i:s')]);

    return ['ok' => true, 'unread' => pharmacy_notification_unread_count()];
}

function pharmacy_get_notifications(): array
{
    $notifications = [];
    $readIds = pharmacy_notification_read_ids();

    $orders = pharmacy_db()->query('
        SELECT o.*, c.name AS customer_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE o.status = "pending" AND ' . pharmacy_scope_sql('o') . '
        ORDER BY o.created_at DESC
        LIMIT 5
    ')->fetchAll();

    foreach ($orders as $order) {
        $customer = trim((string) ($order['customer_name'] ?: 'Customer'));
        $noticeId = pharmacy_notification_id('new-order', (string) (int) ($order['id'] ?? 0));
        $notifications[] = [
            'id' => $noticeId,
            'type' => 'new-order',
            'unread' => !pharmacy_notification_is_read($noticeId, $readIds),
            'headline' => $customer,
            'title' => $customer . ' placed a new order worth ' . pharmacy_format_money((float) $order['total_amount']),
            'subtitle' => (string) ($order['order_number'] ?? ''),
            'time' => pharmacy_time_ago($order['created_at'] ?? null),
            'created_at' => (string) ($order['created_at'] ?? ''),
            'initials' => pharmacy_initials($customer),
            'view' => 'orders',
        ];
    }

    foreach (pharmacy_get_low_stock_medicines(5) as $medicine) {
        $name = trim((string) ($medicine['name'] ?? 'Medicine'));
        $noticeId = pharmacy_notification_id('low-stock', (string) (int) ($medicine['id'] ?? 0));
        $notifications[] = [
            'id' => $noticeId,
            'type' => 'low-stock',
            'unread' => !pharmacy_notification_is_read($noticeId, $readIds),
            'headline' => $name,
            'title' => $name . ' is low on stock — only ' . (int) $medicine['stock_quantity'] . ' units left',
            'subtitle' => 'Minimum threshold: ' . (int) $medicine['minimum_stock'] . ' units',
            'time' => pharmacy_time_ago($medicine['updated_at'] ?? $medicine['created_at'] ?? null),
            'created_at' => (string) ($medicine['updated_at'] ?? $medicine['created_at'] ?? ''),
            'initials' => strtoupper(substr($name, 0, 2)),
            'view' => 'inventory',
        ];
    }

    foreach (pharmacy_get_expiring_medicines(5) as $medicine) {
        $name = trim((string) ($medicine['name'] ?? 'Medicine'));
        $expiryTimestamp = pharmacy_expiration_timestamp((string) $medicine['expiration_date']);
        $days = $expiryTimestamp === null ? 0 : (int) floor(($expiryTimestamp - time()) / 86400);
        $noticeId = pharmacy_notification_id('expiring', (string) (int) ($medicine['id'] ?? 0));
        $notifications[] = [
            'id' => $noticeId,
            'type' => 'expiring',
            'unread' => !pharmacy_notification_is_read($noticeId, $readIds),
            'headline' => $name,
            'title' => $name . ' batch #' . $medicine['batch_number'] . ' expires in ' . max($days, 0) . ' days',
            'subtitle' => (int) $medicine['stock_quantity'] . ' units in this batch',
            'time' => pharmacy_time_ago($medicine['updated_at'] ?? $medicine['created_at'] ?? null),
            'created_at' => (string) ($medicine['updated_at'] ?? $medicine['created_at'] ?? ''),
            'initials' => strtoupper(substr($name, 0, 2)),
            'view' => 'inventory',
        ];
    }

    usort($notifications, static function (array $a, array $b): int {
        return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
    });

    return $notifications;
}

function pharmacy_notification_is_today(?string $value): bool
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

function pharmacy_notifications_grouped(array $notifications): array
{
    $today = [];
    $earlier = [];
    foreach ($notifications as $notification) {
        if (pharmacy_notification_is_today($notification['created_at'] ?? null)) {
            $today[] = $notification;
        } else {
            $earlier[] = $notification;
        }
    }

    return ['today' => $today, 'earlier' => $earlier];
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
    $path = str_replace('\\', '/', trim($path));
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $relative = ltrim($path, '/');

    return function_exists('app_url') ? app_url($relative) : '/' . $relative;
}

function pharmacy_order_upload_mime(string $filename): string
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    return match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        default => 'application/octet-stream',
    };
}

function pharmacy_store_order_upload_blob(string $relativePath, string $bytes, string $mime = ''): void
{
    $relativePath = str_replace('\\', '/', trim($relativePath));
    if ($relativePath === '' || $bytes === '') {
        return;
    }

    $filename = basename($relativePath);
    if ($mime === '') {
        $mime = pharmacy_order_upload_mime($filename);
    }

    try {
        $stmt = pharmacy_db()->prepare(
            'INSERT INTO order_uploads (relative_path, filename, mime, content)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE filename = VALUES(filename), mime = VALUES(mime), content = VALUES(content)'
        );
        $stmt->execute([$relativePath, $filename, $mime, $bytes]);
    } catch (Throwable) {
        pharmacy_ensure_order_uploads_table(pharmacy_db());
        $stmt = pharmacy_db()->prepare(
            'INSERT INTO order_uploads (relative_path, filename, mime, content)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE filename = VALUES(filename), mime = VALUES(mime), content = VALUES(content)'
        );
        $stmt->execute([$relativePath, $filename, $mime, $bytes]);
    }
}

function pharmacy_order_upload_blob(string $relativePath): ?array
{
    $relativePath = str_replace('\\', '/', trim($relativePath));
    if ($relativePath === '') {
        return null;
    }

    try {
        $stmt = pharmacy_db()->prepare(
            'SELECT filename, mime, content FROM order_uploads WHERE relative_path = ? LIMIT 1'
        );
        $stmt->execute([$relativePath]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        return null;
    }

    if (!is_array($row)) {
        return null;
    }
    if (is_resource($row['content'] ?? null)) {
        $row['content'] = stream_get_contents($row['content']);
    }
    if (!is_string($row['content'] ?? null) || $row['content'] === '') {
        return null;
    }

    return $row;
}

function pharmacy_read_order_upload_file(string $relativePath): ?array
{
    $blob = pharmacy_order_upload_blob($relativePath);
    if ($blob !== null) {
        return $blob;
    }

    $absolute = dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    if (!is_file($absolute)) {
        return null;
    }
    $bytes = file_get_contents($absolute);
    if (!is_string($bytes) || $bytes === '') {
        return null;
    }

    $filename = basename($relativePath);
    $mime = pharmacy_order_upload_mime($filename);
    try {
        pharmacy_store_order_upload_blob($relativePath, $bytes, $mime);
    } catch (Throwable) {
    }

    return [
        'filename' => $filename,
        'mime' => $mime,
        'content' => $bytes,
    ];
}

function pharmacy_order_prescription_file(array $order, array $item = []): ?array
{
    $path = trim((string) ($item['prescription_path'] ?? ''));
    if ($path === '') {
        $path = trim((string) ($order['prescription_path'] ?? ''));
    }
    if ($path === '') {
        return null;
    }

    return pharmacy_read_order_upload_file($path);
}

function pharmacy_order_prescription_view_url(array $order, array $item = []): ?string
{
    if ($order !== [] && !pharmacy_order_allows_prescription_view($order)) {
        return null;
    }

    $orderId = (int) ($order['id'] ?? 0);
    $itemPath = trim((string) ($item['prescription_path'] ?? ''));
    $orderPath = trim((string) ($order['prescription_path'] ?? ''));
    if ($itemPath === '' && $orderPath === '') {
        return null;
    }

    if ($orderId <= 0) {
        return pharmacy_upload_url($itemPath !== '' ? $itemPath : $orderPath);
    }

    $query = 'order_id=' . $orderId;
    $itemId = (int) ($item['id'] ?? 0);
    if ($itemId > 0) {
        $query .= '&item_id=' . $itemId;
    }

    return function_exists('app_url') ? app_url('order-prescription.php?' . $query) : ('/order-prescription.php?' . $query);
}

function pharmacy_order_allows_prescription_view($orderOrStatus): bool
{
    $status = is_array($orderOrStatus)
        ? (string) ($orderOrStatus['status'] ?? '')
        : (string) $orderOrStatus;

    return in_array($status, ['pending', 'confirmed', 'preparing', 'ready'], true);
}

function pharmacy_prescription_url(array $order): ?string
{
    return pharmacy_order_prescription_view_url($order, []);
}

function pharmacy_item_prescription_url(array $item, array $order = []): ?string
{
    return pharmacy_order_prescription_view_url($order, $item);
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

function pharmacy_vat_rate(): float
{
    return 0.15;
}

function pharmacy_order_vat_breakdown(array $order, array $items = []): array
{
    $rate = pharmacy_vat_rate();
    $percent = (int) round($rate * 100);
    $total = round(max(0, (float) ($order['total_amount'] ?? 0)), 2);
    $subtotal = 0.0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $subtotal += (float) ($item['unit_price'] ?? $item['price'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
    }
    $subtotal = round($subtotal, 2);
    $vat = round((float) ($order['vat'] ?? 0), 2);
    if ($vat <= 0) {
        if ($subtotal > 0 && $total >= $subtotal) {
            $vat = round($total - $subtotal, 2);
        } elseif ($subtotal > 0) {
            $vat = round($subtotal * $rate, 2);
            $total = round($subtotal + $vat, 2);
        } elseif ($total > 0) {
            $vat = round($total - ($total / (1 + $rate)), 2);
            $subtotal = round($total - $vat, 2);
        }
    } elseif ($subtotal <= 0) {
        $subtotal = round(max(0, $total - $vat), 2);
    }

    return [
        'subtotal' => max(0, $subtotal),
        'vat' => max(0, $vat),
        'total' => $total,
        'vat_percent' => $percent,
        'subtotal_label' => pharmacy_format_money(max(0, $subtotal)),
        'vat_label' => pharmacy_format_money(max(0, $vat)),
        'total_label' => pharmacy_format_money($total),
    ];
}

function pharmacy_order_down_payment(array $order, float $defaultPercent = 60): float
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

function pharmacy_order_balance_on_pickup(array $order, float $defaultPercent = 60): float
{
    $total = max(0, (float) ($order['total_amount'] ?? 0));

    return max(0, round($total - pharmacy_order_down_payment($order, $defaultPercent), 2));
}

function pharmacy_order_down_payment_percent(array $order, float $defaultPercent = 60): int
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

function pharmacy_medicine_compress_image(string $content, string $mime): ?array
{
    if ($content === '' || !function_exists('imagecreatefromstring')) {
        return null;
    }

    $size = strlen($content);
    $info = @getimagesizefromstring($content);
    $width = is_array($info) ? (int) ($info[0] ?? 0) : 0;
    $height = is_array($info) ? (int) ($info[1] ?? 0) : 0;
    if (
        $mime === 'image/jpeg'
        && $size <= 350000
        && $width > 0
        && $height > 0
        && $width <= 1200
        && $height <= 1200
    ) {
        return null;
    }

    $image = @imagecreatefromstring($content);
    if ($image === false) {
        return null;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $maxEdge = 1200;
    $nextWidth = $width;
    $nextHeight = $height;
    if ($width > $maxEdge || $height > $maxEdge) {
        $scale = min($maxEdge / max(1, $width), $maxEdge / max(1, $height));
        $nextWidth = max(1, (int) round($width * $scale));
        $nextHeight = max(1, (int) round($height * $scale));
    }

    $canvas = imagecreatetruecolor($nextWidth, $nextHeight);
    if ($canvas === false) {
        imagedestroy($image);
        return null;
    }
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $nextWidth, $nextHeight, $white);
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nextWidth, $nextHeight, $width, $height);
    imagedestroy($image);

    ob_start();
    $ok = imagejpeg($canvas, null, 82);
    $out = ob_get_clean();
    imagedestroy($canvas);
    if (!$ok || !is_string($out) || $out === '') {
        return null;
    }

    return ['mime' => 'image/jpeg', 'content' => $out];
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
    $id = (int) ($medicine['medicine_id'] ?? 0);
    if ($id < 1) {
        $id = (int) ($medicine['id'] ?? 0);
    }
    $path = trim((string) ($medicine['image_path'] ?? ''));
    if (preg_match('/medicine-image\.php\?(?:.*&)?id=(\d+)/i', $path, $matches)) {
        $id = (int) $matches[1];
    }
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

function pharmacy_medicine_is_new(array $medicine): bool
{
    $raw = trim((string) ($medicine['listed_at'] ?? $medicine['created_at'] ?? ''));
    if ($raw === '') {
        return false;
    }

    $timezone = function_exists('app_timezone') ? app_timezone() : new DateTimeZone('Asia/Manila');
    try {
        $listed = new DateTimeImmutable($raw, $timezone);
    } catch (Exception) {
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return false;
        }
        $listed = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
    }

    $now = new DateTimeImmutable('now', $timezone);
    $expires = $listed->add(new DateInterval('P7D'));

    return $now < $expires;
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
