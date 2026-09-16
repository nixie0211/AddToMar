<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/pharmacy/config.php';
require_once dirname(__DIR__, 2) . '/pharmacy/includes/database.php';
require_once dirname(__DIR__, 2) . '/includes/resident-notifications.php';
require_once __DIR__ . '/catalog.php';

function residence_prescription_url(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return function_exists('app_url') ? app_url(ltrim($path, '/')) : '/' . ltrim($path, '/');
}

function residence_vat_rate(): float
{
    return 0.15;
}

function residence_item_note(array $item): string
{
    $note = trim((string) ($item['item_note'] ?? $item['note'] ?? $item['additional_note'] ?? ''));
    if ($note === '') {
        return '';
    }

    $note = preg_replace("/[ \t]+\n/", "\n", str_replace(["\r\n", "\r"], "\n", $note)) ?? $note;
    if (function_exists('mb_substr')) {
        return trim(mb_substr($note, 0, 500));
    }

    return trim(substr($note, 0, 500));
}

function residence_down_payment_rate(): float
{
    return 0.6;
}

function residence_apply_vat(float $subtotal): array
{
    $subtotal = round(max(0, $subtotal), 2);
    $vat = round($subtotal * residence_vat_rate(), 2);
    $total = round($subtotal + $vat, 2);

    return [
        'subtotal' => $subtotal,
        'vat' => $vat,
        'total' => $total,
    ];
}

function residence_generate_order_number(PDO $pdo): string
{
    do {
        $number = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare('SELECT id FROM orders WHERE order_number = ? LIMIT 1');
        $stmt->execute([$number]);
        $exists = (bool) $stmt->fetchColumn();
    } while ($exists);

    return $number;
}

function residence_upsert_customer(PDO $pdo, array $profile, string $pharmacyId): int
{
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    $name = trim((string) ($profile['full_name'] ?? 'Customer'));
    $phone = preg_replace('/\D+/', '', (string) ($profile['contact_number'] ?? ''));
    $address = trim((string) ($profile['address'] ?? ''));

    $stmt = $pdo->prepare('SELECT id FROM customers WHERE email = ? AND pharmacy_id = ? LIMIT 1');
    $stmt->execute([$email, $pharmacyId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $update = $pdo->prepare('UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?');
        $update->execute([$name, $phone, $address, $existingId]);

        return (int) $existingId;
    }

    $insert = $pdo->prepare('
        INSERT INTO customers (pharmacy_id, name, phone, email, address, status)
        VALUES (?, ?, ?, ?, ?, "active")
    ');
    $insert->execute([$pharmacyId, $name, $phone, $email, $address]);

    return (int) $pdo->lastInsertId();
}

function residence_store_order_upload(array $file, string $prefix): ?string
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    $mime = (string) mime_content_type((string) $file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    if (!isset($allowed[$mime])) {
        return null;
    }

    if ((int) $file['size'] > 10 * 1024 * 1024) {
        return null;
    }

    $dir = dirname(__DIR__, 2) . '/data/uploads/orders';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $filename = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $filename)) {
        return null;
    }

    return 'data/uploads/orders/' . $filename;
}

/**
 * @return array<string, string> item key or pharmacy id => stored path
 */
function residence_collect_prescription_uploads(): array
{
    $paths = [];

    $files = $_FILES['prescriptions'] ?? null;
    if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
        foreach ($files['name'] as $uploadKey => $name) {
            $uploadKey = trim((string) $uploadKey);
            $file = [
                'name' => (string) $name,
                'type' => (string) ($files['type'][$uploadKey] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$uploadKey] ?? ''),
                'error' => (int) ($files['error'][$uploadKey] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($files['size'][$uploadKey] ?? 0),
            ];
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $path = residence_store_order_upload($file, 'RX');
            if ($path === null && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Could not upload prescription. Use JPG, PNG, WEBP, or PDF up to 10 MB.');
            }
            if ($path !== null && $uploadKey !== '') {
                $paths[$uploadKey] = $path;
            }
        }
    }

    if (isset($_FILES['prescription']) && is_array($_FILES['prescription'])) {
        $path = residence_store_order_upload($_FILES['prescription'], 'RX');
        if ($path === null && (int) ($_FILES['prescription']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Could not upload prescription. Use JPG, PNG, WEBP, or PDF up to 10 MB.');
        }
        if ($path !== null && $paths === []) {
            $paths['_default'] = $path;
        }
    }

    return $paths;
}

function residence_prescription_item_key(string $pharmacyId, int $medicineId): string
{
    return trim($pharmacyId) . '::' . $medicineId;
}

function residence_prescription_path_for_item(array $prescriptions, string $pharmacyId, int $medicineId, int $rxCount = 0): ?string
{
    $itemKey = residence_prescription_item_key($pharmacyId, $medicineId);
    if (!empty($prescriptions[$itemKey])) {
        return (string) $prescriptions[$itemKey];
    }
    if (!empty($prescriptions[(string) $medicineId])) {
        return (string) $prescriptions[(string) $medicineId];
    }
    if ($rxCount === 1) {
        if (!empty($prescriptions[$pharmacyId])) {
            return (string) $prescriptions[$pharmacyId];
        }
        if (!empty($prescriptions['_default'])) {
            return (string) $prescriptions['_default'];
        }
        $first = reset($prescriptions);

        return is_string($first) && $first !== '' ? $first : null;
    }

    return null;
}

/**
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array{pharmacy_id:string, medicine_id:int}>
 */
function residence_checkout_rx_lines(array $items, string $fallbackPharmacyId = ''): array
{
    $pdo = pharmacy_db();
    $lines = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $medicineId = (int) ($item['medicine_id'] ?? $item['medicineId'] ?? 0);
        $pharmacyId = trim((string) ($item['pharmacy_id'] ?? $item['pharmacyId'] ?? $fallbackPharmacyId));
        if ($medicineId <= 0 || $pharmacyId === '') {
            continue;
        }

        $stmt = $pdo->prepare('
            SELECT prescription_required
            FROM medicines
            WHERE id = ? AND pharmacy_id = ? AND is_active = 1
            LIMIT 1
        ');
        $stmt->execute([$medicineId, $pharmacyId]);
        $medicine = $stmt->fetch();
        if ($medicine && !empty($medicine['prescription_required'])) {
            $lines[] = [
                'pharmacy_id' => $pharmacyId,
                'medicine_id' => $medicineId,
            ];
        }
    }

    return $lines;
}

/**
 * @param array<int, array{pharmacy_id:string, medicine_id:int}> $rxLines
 * @param array<string, string> $prescriptions
 */
function residence_assert_prescriptions_uploaded(array $rxLines, array $prescriptions): void
{
    $counts = [];
    foreach ($rxLines as $line) {
        $pharmacyId = $line['pharmacy_id'];
        $counts[$pharmacyId] = ($counts[$pharmacyId] ?? 0) + 1;
    }

    foreach ($rxLines as $line) {
        $pharmacyId = $line['pharmacy_id'];
        $path = residence_prescription_path_for_item(
            $prescriptions,
            $pharmacyId,
            (int) $line['medicine_id'],
            $counts[$pharmacyId] ?? 0
        );
        if ($path === null) {
            throw new RuntimeException('Upload a prescription for each medicine that requires one.');
        }
    }
}

/**
 * @param array<int, array<string, mixed>> $items
 * @return array<string, array<int, array<string, mixed>>>
 */
function residence_group_checkout_items(array $items, string $fallbackPharmacyId = ''): array
{
    $groups = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $pharmacyId = trim((string) ($item['pharmacy_id'] ?? $item['pharmacyId'] ?? $fallbackPharmacyId));
        if ($pharmacyId === '') {
            continue;
        }
        $groups[$pharmacyId][] = $item;
    }

    return $groups;
}

/**
 * @param array<int, array<string, mixed>> $orders
 * @return array<string, mixed>
 */
function residence_combine_orders_for_receipt(array $orders): array
{
    $orders = array_values(array_filter($orders, static fn ($order) => is_array($order)));
    if ($orders === []) {
        return [];
    }

    $primary = $orders[0];
    $items = [];
    $total = 0.0;
    $down = 0.0;
    $numbers = [];
    $names = [];
    $addresses = [];

    foreach ($orders as $order) {
        $number = trim((string) ($order['order_number'] ?? ''));
        if ($number !== '') {
            $numbers[] = $number;
        }
        $name = trim((string) ($order['pharmacy_name'] ?? ''));
        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }
        $address = trim((string) ($order['pharmacy_address'] ?? ''));
        if ($address !== '' && !in_array($address, $addresses, true)) {
            $addresses[] = $address;
        }
        $total += (float) ($order['total_amount'] ?? 0);
        $down += (float) ($order['down_payment'] ?? 0);
        $pharmacyName = $name !== '' ? $name : 'Pharmacy';
        foreach ($order['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemPharmacy = trim((string) ($item['pharmacy_name'] ?? $item['pharmacyName'] ?? $pharmacyName));
            $item['pharmacy_name'] = $itemPharmacy;
            $item['pharmacyName'] = $itemPharmacy;
            $item['pharmacy_id'] = $item['pharmacy_id'] ?? ($order['pharmacy_id'] ?? '');
            $item['name'] = $item['name'] ?? $item['medicine_name'] ?? 'Medicine';
            $items[] = $item;
        }
    }

    $primary['items'] = $items;
    $primary['orders'] = $orders;
    $primary['related_order_numbers'] = $numbers;
    $primary['order_numbers_label'] = implode(', ', $numbers);
    $primary['pharmacy_name'] = $names !== [] ? implode(', ', $names) : (string) ($primary['pharmacy_name'] ?? 'Pharmacy');
    $primary['pharmacy_address'] = implode(' · ', $addresses);
    $primary['total_amount'] = round($total, 2);
    $primary['down_payment'] = round($down, 2);
    $primary['item_count'] = count($items);

    return $primary;
}

/**
 * @param array<string, string> $prescriptions
 * @param array<string, array<int, array<string, mixed>>> $groups
 */
function residence_place_checkout_groups(array $profile, array $groups, array $options = [], array $prescriptions = []): array
{
    if ($groups === []) {
        return ['ok' => false, 'error' => 'Your cart is empty.'];
    }

    $orders = [];
    $intentId = trim((string) ($options['paymongo_intent_id'] ?? ''));
    $groupId = trim((string) ($options['checkout_group_id'] ?? ''));
    if ($groupId === '') {
        $groupId = residence_generate_order_number(pharmacy_db());
    }
    $first = true;

    foreach ($groups as $pharmacyId => $groupItems) {
        $pharmacyId = trim((string) $pharmacyId);
        if ($intentId !== '') {
            $existing = residence_find_order_by_intent_for_pharmacy($intentId, $pharmacyId);
            if ($existing) {
                $directory = residence_pharmacy_directory();
                $existing['pharmacy_name'] = $directory[$pharmacyId]['name'] ?? ($existing['pharmacy_name'] ?? 'Pharmacy');
                $orders[] = $existing;
                $first = false;
                continue;
            }
        }

        $placed = residence_place_order($profile, $pharmacyId, $groupItems, array_merge($options, [
            'item_prescriptions' => $prescriptions,
            'checkout_group_id' => $groupId,
            'order_number' => $first ? $groupId : '',
        ]));
        $first = false;

        if (!($placed['ok'] ?? false)) {
            if ($orders !== []) {
                $placed['order'] = $orders[0];
                $placed['orders'] = $orders;
            }

            return $placed;
        }

        $orders[] = $placed['order'];
    }

    $combined = residence_combine_orders_for_receipt($orders);
    if ($combined !== []) {
        $combined['order_number'] = $groupId;
        $combined['checkout_group_id'] = $groupId;
        $combined['related_order_numbers'] = array_values(array_filter(array_map(
            static fn (array $order): string => trim((string) ($order['order_number'] ?? '')),
            $orders
        )));
    }

    return [
        'ok' => true,
        'order' => $combined !== [] ? $combined : ($orders[0] ?? null),
        'orders' => $orders,
    ];
}

function residence_find_order_by_intent_for_pharmacy(string $intentId, string $pharmacyId): ?array
{
    $intentId = trim($intentId);
    $pharmacyId = trim($pharmacyId);
    if ($intentId === '' || $pharmacyId === '') {
        return null;
    }

    try {
        $pdo = pharmacy_db();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE paymongo_intent_id = ? AND pharmacy_id = ? LIMIT 1');
        $stmt->execute([$intentId, $pharmacyId]);
        $order = $stmt->fetch();
    } catch (Throwable) {
        return null;
    }

    if (!$order) {
        return null;
    }

    $itemsStmt = pharmacy_db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $itemsStmt->execute([(int) $order['id']]);
    $order['items'] = $itemsStmt->fetchAll();

    return $order;
}

/**
 * @param array<int, array{medicine_id:int, quantity:int}> $items
 */
function residence_place_order(array $profile, string $pharmacyId, array $items, array $options = []): array
{
    $pharmacyId = trim($pharmacyId);
    if ($pharmacyId === '') {
        return ['ok' => false, 'error' => 'Select a pharmacy before placing your order.'];
    }

    $directory = residence_pharmacy_directory();
    if (!isset($directory[$pharmacyId])) {
        return ['ok' => false, 'error' => 'This pharmacy is not available for orders.'];
    }

    if ($items === []) {
        return ['ok' => false, 'error' => 'Your cart is empty.'];
    }

    $pickupDate = trim((string) ($options['pickup_date'] ?? ''));
    $pickupTime = trim((string) ($options['pickup_time'] ?? ''));
    if ($pickupDate === '') {
        return ['ok' => false, 'error' => 'Select a pickup date.'];
    }

    $pdo = pharmacy_db();
    $pdo->beginTransaction();

    try {
        $lineItems = [];
        $total = 0.0;
        $requiresPrescription = false;

        foreach ($items as $item) {
            $medicineId = (int) ($item['medicine_id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($medicineId <= 0) {
                throw new RuntimeException('Invalid medicine in cart.');
            }

            $stmt = $pdo->prepare('
                SELECT *
                FROM medicines
                WHERE id = ?
                  AND pharmacy_id = ?
                  AND is_active = 1
                LIMIT 1
            ');
            $stmt->execute([$medicineId, $pharmacyId]);
            $medicine = $stmt->fetch();

            if (!$medicine) {
                throw new RuntimeException('One or more medicines are no longer available from this pharmacy.');
            }

            $stock = (int) ($medicine['stock_quantity'] ?? 0);
            if ($stock < $quantity) {
                throw new RuntimeException((string) ($medicine['name'] ?? 'A medicine') . ' only has ' . $stock . ' left in stock.');
            }

            $unitPrice = (float) ($medicine['selling_price'] ?? 0);
            if ($unitPrice <= 0) {
                $unitPrice = (float) ($medicine['unit_price'] ?? 0);
            }

            if (!empty($medicine['prescription_required'])) {
                $requiresPrescription = true;
            }

            $lineTotal = $unitPrice * $quantity;
            $total += $lineTotal;

            $lineItems[] = [
                'medicine_id' => $medicineId,
                'medicine_name' => (string) ($medicine['name'] ?? 'Medicine'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'prescription_required' => !empty($medicine['prescription_required']),
                'prescription_path' => null,
                'item_note' => residence_item_note($item),
                'pharmacy_id' => $pharmacyId,
                'pharmacy_name' => (string) ($directory[$pharmacyId]['name'] ?? 'Pharmacy'),
            ];
        }

        $itemPrescriptions = is_array($options['item_prescriptions'] ?? null) ? $options['item_prescriptions'] : [];
        $rxCount = 0;
        foreach ($lineItems as $line) {
            if (!empty($line['prescription_required'])) {
                $rxCount++;
            }
        }
        foreach ($lineItems as &$line) {
            if (empty($line['prescription_required'])) {
                continue;
            }
            $path = residence_prescription_path_for_item(
                $itemPrescriptions,
                $pharmacyId,
                (int) $line['medicine_id'],
                $rxCount
            );
            if ($path === null && $rxCount === 1 && !empty($options['prescription_path'])) {
                $path = (string) $options['prescription_path'];
            }
            if ($path === null) {
                throw new RuntimeException('Upload a prescription for each medicine that requires one.');
            }
            $line['prescription_path'] = $path;
        }
        unset($line);

        $orderPrescriptionPath = $options['prescription_path'] ?? null;
        foreach ($lineItems as $line) {
            if (!empty($line['prescription_path'])) {
                $orderPrescriptionPath = $orderPrescriptionPath ?: $line['prescription_path'];
                break;
            }
        }

        if ($requiresPrescription && empty($orderPrescriptionPath)) {
            throw new RuntimeException('Upload a prescription for each medicine that requires one.');
        }

        $priced = residence_apply_vat($total);
        $total = $priced['total'];
        $downPayment = round($total * residence_down_payment_rate(), 2);
        $customerId = residence_upsert_customer($pdo, $profile, $pharmacyId);
        $forcedNumber = trim((string) ($options['order_number'] ?? ''));
        $orderNumber = $forcedNumber !== '' ? $forcedNumber : residence_generate_order_number($pdo);
        $checkoutGroupId = trim((string) ($options['checkout_group_id'] ?? $orderNumber));
        $notes = 'Pickup date: ' . $pickupDate;
        $intentId = trim((string) ($options['paymongo_intent_id'] ?? ''));
        if ($intentId !== '') {
            $notes .= ' | PayMongo ' . $intentId;
        }
        $receiptEmail = trim((string) ($options['receipt_email'] ?? ''));
        if ($receiptEmail !== '') {
            $notes .= ' | Receipt: ' . $receiptEmail;
        }

        if (function_exists('pharmacy_ensure_column')) {
            try {
                pharmacy_ensure_column($pdo, 'orders', 'checkout_group_id', 'VARCHAR(50) NULL');
            } catch (Throwable) {
            }
        }

        try {
            $orderStmt = $pdo->prepare('
                INSERT INTO orders (
                    pharmacy_id, order_number, customer_id, status, payment_method,
                    total_amount, down_payment, notes, paymongo_intent_id, checkout_group_id, prescription_path, pickup_proof_path
                ) VALUES (?, ?, ?, "pending", ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $orderStmt->execute([
                $pharmacyId,
                $orderNumber,
                $customerId,
                trim((string) ($options['payment_method'] ?? 'gcash')),
                $total,
                $downPayment,
                $notes,
                $intentId !== '' ? $intentId : null,
                $checkoutGroupId !== '' ? $checkoutGroupId : $orderNumber,
                $orderPrescriptionPath,
                $options['payment_proof_path'] ?? null,
            ]);
        } catch (Throwable $insertError) {
            if (!str_contains($insertError->getMessage(), 'checkout_group_id')) {
                throw $insertError;
            }
            $orderStmt = $pdo->prepare('
                INSERT INTO orders (
                    pharmacy_id, order_number, customer_id, status, payment_method,
                    total_amount, down_payment, notes, paymongo_intent_id, prescription_path, pickup_proof_path
                ) VALUES (?, ?, ?, "pending", ?, ?, ?, ?, ?, ?, ?)
            ');
            $orderStmt->execute([
                $pharmacyId,
                $orderNumber,
                $customerId,
                trim((string) ($options['payment_method'] ?? 'gcash')),
                $total,
                $downPayment,
                $notes,
                $intentId !== '' ? $intentId : null,
                $orderPrescriptionPath,
                $options['payment_proof_path'] ?? null,
            ]);
        }

        $orderId = (int) $pdo->lastInsertId();

        if (function_exists('pharmacy_ensure_column')) {
            try {
                pharmacy_ensure_column($pdo, 'order_items', 'item_note', 'TEXT NULL');
            } catch (Throwable) {
            }
        }

        $itemStmt = $pdo->prepare('
            INSERT INTO order_items (order_id, medicine_id, medicine_name, quantity, unit_price, prescription_required, prescription_path, item_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $itemStmtWithNote = true;

        foreach ($lineItems as $line) {
            $itemValues = [
                $orderId,
                $line['medicine_id'],
                $line['medicine_name'],
                $line['quantity'],
                $line['unit_price'],
                $line['prescription_required'] ? 1 : 0,
                $line['prescription_path'] ?: null,
            ];
            if ($itemStmtWithNote) {
                try {
                    $itemStmt->execute([...$itemValues, ($line['item_note'] ?? '') !== '' ? $line['item_note'] : null]);
                } catch (Throwable $itemInsertError) {
                    if (!str_contains($itemInsertError->getMessage(), 'item_note')) {
                        throw $itemInsertError;
                    }
                    $itemStmtWithNote = false;
                    $itemStmt = $pdo->prepare('
                        INSERT INTO order_items (order_id, medicine_id, medicine_name, quantity, unit_price, prescription_required, prescription_path)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ');
                    $itemStmt->execute($itemValues);
                }
            } else {
                $itemStmt->execute($itemValues);
            }

            $stockStmt = $pdo->prepare('
                UPDATE medicines
                SET stock_quantity = stock_quantity - ?
                WHERE id = ? AND pharmacy_id = ? AND stock_quantity >= ?
            ');
            $stockStmt->execute([$line['quantity'], $line['medicine_id'], $pharmacyId, $line['quantity']]);

            if ($stockStmt->rowCount() === 0) {
                throw new RuntimeException('Could not reserve stock for ' . $line['medicine_name'] . '.');
            }
        }

        $pdo->commit();

        $notifyEmail = strtolower(trim((string) ($profile['email'] ?? $receiptEmail)));
        $pharmacyName = (string) ($directory[$pharmacyId]['name'] ?? 'the pharmacy');
        if ($notifyEmail !== '') {
            resident_notification_add(
                $notifyEmail,
                'Order placed',
                'Your order #' . $orderNumber . ' at ' . $pharmacyName . ' was placed. We will notify you as it is prepared.',
                'order',
                '',
                $orderNumber
            );
        }

        return [
            'ok' => true,
            'order' => [
                'id' => $orderId,
                'order_number' => $orderNumber,
                'pharmacy_id' => $pharmacyId,
                'pharmacy_name' => $directory[$pharmacyId]['name'],
                'total_amount' => $total,
                'down_payment' => $downPayment,
                'status' => 'pending',
                'pickup_date' => $pickupDate,
                'pickup_time' => $pickupTime,
                'items' => $lineItems,
                'paymongo_intent_id' => $intentId,
                'checkout_group_id' => $checkoutGroupId !== '' ? $checkoutGroupId : $orderNumber,
                'receipt_email' => $receiptEmail,
                'payment_method' => trim((string) ($options['payment_method'] ?? 'gcash')),
                'created_at' => date('Y-m-d H:i:s'),
                'pharmacy_address' => (string) ($directory[$pharmacyId]['address'] ?? ''),
                'customer_address' => (string) ($profile['address'] ?? ''),
            ],
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();

        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function residence_get_customer_orders(string $email): array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return [];
    }

    $pdo = pharmacy_db();
    $stmt = $pdo->prepare('
        SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
               c.email AS customer_email, ps.pharmacy_name AS settings_pharmacy_name
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        LEFT JOIN pharmacy_settings ps ON ps.pharmacy_id = o.pharmacy_id
        WHERE LOWER(TRIM(c.email)) = ?
        ORDER BY o.created_at DESC
        LIMIT 50
    ');
    $stmt->execute([$email]);
    $orders = $stmt->fetchAll();

    $medicineIds = [];
    foreach ($orders as &$order) {
        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $itemsStmt->execute([(int) ($order['id'] ?? 0)]);
        $order['items'] = $itemsStmt->fetchAll();
        foreach ($order['items'] as $item) {
            $medicineId = (int) ($item['medicine_id'] ?? 0);
            if ($medicineId > 0) {
                $medicineIds[$medicineId] = $medicineId;
            }
        }
    }
    unset($order);

    $images = [];
    if ($medicineIds !== []) {
        $placeholders = implode(',', array_fill(0, count($medicineIds), '?'));
        $imageStmt = $pdo->prepare("SELECT id, image_path FROM medicines WHERE id IN ({$placeholders})");
        $imageStmt->execute(array_values($medicineIds));
        foreach ($imageStmt->fetchAll() as $row) {
            $images[(int) $row['id']] = (string) (pharmacy_medicine_image_url($row) ?? '');
        }
    }

    foreach ($orders as &$order) {
        foreach ($order['items'] as &$item) {
            $item['image'] = $images[(int) ($item['medicine_id'] ?? 0)] ?? '';
        }
        unset($item);
    }
    unset($order);

    return $orders;
}

function residence_order_status_meta(string $status): array
{
    return match ($status) {
        'pending', 'processing' => ['tab' => 'processing', 'class' => 'processing', 'label' => 'Processing'],
        'confirmed' => ['tab' => 'confirmed', 'class' => 'confirmed', 'label' => 'Confirmed'],
        'preparing' => ['tab' => 'preparing', 'class' => 'preparing', 'label' => 'Preparing'],
        'ready' => ['tab' => 'ready', 'class' => 'ready', 'label' => 'Ready for pick up'],
        'picked_up', 'pickedup' => ['tab' => 'picked_up', 'class' => 'picked-up', 'label' => 'Picked up'],
        'delivered', 'completed' => ['tab' => 'completed', 'class' => 'completed', 'label' => 'Completed'],
        'cancelled' => ['tab' => 'cancelled', 'class' => 'cancelled', 'label' => 'Cancelled'],
        default => ['tab' => 'processing', 'class' => 'processing', 'label' => ucfirst($status !== '' ? $status : 'Processing')],
    };
}

function residence_present_order(array $order, array $directory = []): array
{
    $pharmacyId = trim((string) ($order['pharmacy_id'] ?? ''));
    $pharmacy = $directory[$pharmacyId] ?? [];
    $created = strtotime((string) ($order['created_at'] ?? '')) ?: time();
    $status = (string) ($order['status'] ?? 'pending');
    $meta = residence_order_status_meta($status);
    $items = [];

    foreach ($order['items'] ?? [] as $item) {
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
        $items[] = [
            'medicine_id' => (int) ($item['medicine_id'] ?? 0),
            'name' => (string) ($item['medicine_name'] ?? $item['name'] ?? 'Medicine'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
            'prescription_required' => !empty($item['prescription_required']),
            'prescription_path' => trim((string) ($item['prescription_path'] ?? '')),
            'prescription_url' => residence_prescription_url((string) ($item['prescription_path'] ?? '')),
            'item_note' => residence_item_note($item),
            'image' => (string) ($item['image'] ?? $item['image_url'] ?? ''),
            'pharmacy_id' => $pharmacyId,
            'pharmacy_name' => (string) ($order['pharmacy_name'] ?? $order['settings_pharmacy_name'] ?? $pharmacy['name'] ?? 'Pharmacy'),
        ];
    }

    return [
        'id' => (int) ($order['id'] ?? 0),
        'order_number' => (string) ($order['order_number'] ?? ''),
        'pharmacy_id' => $pharmacyId,
        'pharmacy_name' => (string) ($order['pharmacy_name'] ?? $order['settings_pharmacy_name'] ?? $pharmacy['name'] ?? 'Pharmacy'),
        'pharmacy_address' => (string) ($pharmacy['address'] ?? $order['pharmacy_address'] ?? ''),
        'pharmacy_logo' => (string) ($pharmacy['logo_url'] ?? ''),
        'customer_address' => (string) ($order['customer_address'] ?? ''),
        'status' => $status,
        'status_tab' => $meta['tab'],
        'status_class' => $meta['class'],
        'status_label' => $meta['label'],
        'total_amount' => (float) ($order['total_amount'] ?? 0),
        'down_payment' => ((float) ($order['down_payment'] ?? 0) > 0)
            ? round((float) $order['down_payment'], 2)
            : round((float) ($order['total_amount'] ?? 0) * residence_down_payment_rate(), 2),
        'payment_method' => (string) ($order['payment_method'] ?? 'gcash'),
        'created_at' => (string) ($order['created_at'] ?? ''),
        'updated_at' => (string) ($order['updated_at'] ?? ''),
        'date_label' => date('M j, Y', $created),
        'time_label' => date('g:i A', $created),
        'item_count' => count($items),
        'items' => $items,
        'prescription_path' => trim((string) ($order['prescription_path'] ?? '')),
        'prescription_url' => residence_prescription_url((string) ($order['prescription_path'] ?? '')),
        'pickup_proof_path' => trim((string) ($order['pickup_proof_path'] ?? '')),
        'pickup_proof_url' => residence_prescription_url((string) ($order['pickup_proof_path'] ?? '')),
        'balance_paid' => in_array($status, ['picked_up', 'pickedup', 'delivered', 'completed'], true),
        'checkout_group_id' => trim((string) ($order['checkout_group_id'] ?? '')),
        'paymongo_intent_id' => trim((string) ($order['paymongo_intent_id'] ?? '')),
        'cancellation_reason' => trim((string) ($order['cancellation_reason'] ?? '')),
        'can_cancel' => residence_order_status_is_cancellable($status),
        'stores' => [],
        'is_group' => false,
        'store_count' => 1,
        'partially_fulfilled' => false,
        'completed_store_count' => in_array($status, ['picked_up', 'pickedup', 'delivered', 'completed'], true) ? 1 : 0,
    ];
}

function residence_order_status_is_cancellable(string $status): bool
{
    return in_array(strtolower(trim($status)), ['pending', 'processing'], true);
}

function residence_order_progress_rank(string $status): int
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

function residence_status_from_progress_rank(int $rank): string
{
    return match ($rank) {
        0 => 'cancelled',
        2 => 'confirmed',
        3 => 'preparing',
        4 => 'ready',
        5 => 'completed',
        default => 'pending',
    };
}

function residence_order_group_key(array $order): string
{
    $group = trim((string) ($order['checkout_group_id'] ?? ''));
    if ($group !== '') {
        return 'g:' . $group;
    }
    $intent = trim((string) ($order['paymongo_intent_id'] ?? ''));
    if ($intent !== '') {
        return 'i:' . $intent;
    }

    return 'o:' . (string) ($order['id'] ?? $order['order_number'] ?? uniqid('order', true));
}

function residence_parent_fulfillment(array $statuses): array
{
    $active = array_values(array_filter(
        $statuses,
        static fn (string $status): bool => strtolower($status) !== 'cancelled'
    ));
    if ($active === []) {
        $meta = residence_order_status_meta('cancelled');

        return $meta + [
            'status' => 'cancelled',
            'partial' => false,
            'completed' => 0,
            'store_count' => count($statuses),
        ];
    }

    $ranks = array_map('residence_order_progress_rank', $active);
    $min = (int) min($ranks);
    $status = residence_status_from_progress_rank($min);
    $meta = residence_order_status_meta($status);
    $completed = count(array_filter(
        $active,
        static fn (string $status): bool => residence_order_progress_rank($status) >= 5
    ));
    $partial = $completed > 0 && $completed < count($active);
    if ($partial) {
        $meta['label'] = 'Partially fulfilled';
        $meta['class'] = 'partial';
    }

    return $meta + [
        'status' => $status,
        'partial' => $partial,
        'completed' => $completed,
        'store_count' => count($active),
    ];
}

function residence_present_order_group(array $orders, array $directory = []): array
{
    usort($orders, static function (array $left, array $right): int {
        return strcmp((string) ($left['created_at'] ?? ''), (string) ($right['created_at'] ?? ''));
    });
    $stores = array_map(
        static fn (array $order): array => residence_present_order($order, $directory),
        $orders
    );
    $primary = $stores[0];
    $primary['stores'] = $stores;
    $primary['store_count'] = count($stores);
    $primary['is_group'] = count($stores) > 1;
    if (count($stores) === 1) {
        return $primary;
    }

    $groupId = trim((string) ($orders[0]['checkout_group_id'] ?? ''));
    $items = [];
    $total = 0.0;
    $down = 0.0;
    $statuses = [];
    foreach ($stores as $store) {
        $total += (float) ($store['total_amount'] ?? 0);
        $down += (float) ($store['down_payment'] ?? 0);
        $statuses[] = (string) ($store['status'] ?? 'pending');
        foreach ($store['items'] ?? [] as $item) {
            $item['pharmacy_id'] = $store['pharmacy_id'];
            $item['pharmacy_name'] = $store['pharmacy_name'];
            $items[] = $item;
        }
    }
    $overall = residence_parent_fulfillment($statuses);
    $names = array_values(array_unique(array_filter(array_map(
        static fn (array $store): string => trim((string) ($store['pharmacy_name'] ?? '')),
        $stores
    ))));

    $reasons = array_values(array_unique(array_filter(array_map(
        static fn (array $store): string => trim((string) ($store['cancellation_reason'] ?? '')),
        $stores
    ))));
    $canCancel = $stores !== [] && array_reduce(
        $stores,
        static fn (bool $ok, array $store): bool => $ok && !empty($store['can_cancel']),
        true
    );

    return array_merge($primary, [
        'order_number' => $groupId !== '' ? $groupId : (string) ($primary['order_number'] ?? ''),
        'related_order_numbers' => array_values(array_filter(array_map(
            static fn (array $store): string => (string) ($store['order_number'] ?? ''),
            $stores
        ))),
        'pharmacy_name' => implode(', ', $names),
        'stores' => $stores,
        'is_group' => true,
        'store_count' => count($stores),
        'items' => $items,
        'item_count' => count($items),
        'total_amount' => round($total, 2),
        'down_payment' => round($down, 2),
        'status' => $overall['status'],
        'status_tab' => $overall['tab'],
        'status_class' => $overall['class'],
        'status_label' => $overall['label'],
        'partially_fulfilled' => !empty($overall['partial']),
        'completed_store_count' => (int) ($overall['completed'] ?? 0),
        'balance_paid' => ($overall['status'] ?? '') === 'completed',
        'cancellation_reason' => implode(' · ', $reasons),
        'can_cancel' => $canCancel,
    ]);
}

function residence_customer_orders_payload(string $email): array
{
    $directory = residence_pharmacy_directory();
    $buckets = [];
    foreach (residence_get_customer_orders($email) as $order) {
        $buckets[residence_order_group_key($order)][] = $order;
    }

    $orders = [];
    foreach ($buckets as $group) {
        $orders[] = residence_present_order_group($group, $directory);
    }

    usort($orders, static function (array $left, array $right): int {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    return $orders;
}

function residence_find_customer_order_group(string $email, int $orderId, string $orderNumber = ''): array
{
    $orderNumber = trim($orderNumber);
    foreach (residence_customer_orders_payload($email) as $group) {
        $ids = [(int) ($group['id'] ?? 0)];
        $numbers = [
            (string) ($group['order_number'] ?? ''),
            (string) ($group['checkout_group_id'] ?? ''),
        ];
        foreach ($group['stores'] ?? [] as $store) {
            $ids[] = (int) ($store['id'] ?? 0);
            $numbers[] = (string) ($store['order_number'] ?? '');
            $numbers[] = (string) ($store['checkout_group_id'] ?? '');
        }
        if ($orderId > 0 && in_array($orderId, $ids, true)) {
            return $group;
        }
        if ($orderNumber !== '' && in_array($orderNumber, $numbers, true)) {
            return $group;
        }
    }

    return [];
}

function residence_restore_order_stock(PDO $pdo, int $orderId): void
{
    $stmt = $pdo->prepare('SELECT medicine_id, quantity FROM order_items WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $restore = $pdo->prepare('UPDATE medicines SET stock_quantity = stock_quantity + ? WHERE id = ?');
    foreach ($stmt->fetchAll() as $item) {
        $medicineId = (int) ($item['medicine_id'] ?? 0);
        $quantity = max(0, (int) ($item['quantity'] ?? 0));
        if ($medicineId > 0 && $quantity > 0) {
            $restore->execute([$quantity, $medicineId]);
        }
    }
}

function residence_cancel_customer_order(string $email, int $orderId, string $orderNumber, string $reason): array
{
    $reason = trim($reason);
    if ($reason === '') {
        return ['ok' => false, 'error' => 'Please provide a cancellation reason.'];
    }
    if (mb_strlen($reason) > 500) {
        return ['ok' => false, 'error' => 'Cancellation reason must be 500 characters or less.'];
    }

    $group = residence_find_customer_order_group($email, $orderId, $orderNumber);
    if ($group === []) {
        return ['ok' => false, 'error' => 'This order was not found.'];
    }
    if (empty($group['can_cancel'])) {
        return ['ok' => false, 'error' => 'Only processing orders can be cancelled.'];
    }

    $stores = $group['stores'] ?? [];
    if ($stores === []) {
        $stores = [$group];
    }

    $pdo = pharmacy_db();
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('
            UPDATE orders
            SET status = "cancelled", cancellation_reason = ?
            WHERE id = ? AND status IN ("pending", "processing")
        ');
        $cancelled = [];
        foreach ($stores as $store) {
            $storeId = (int) ($store['id'] ?? 0);
            if ($storeId <= 0 || empty($store['can_cancel'])) {
                throw new RuntimeException('Only processing orders can be cancelled.');
            }
            $update->execute([$reason, $storeId]);
            if ($update->rowCount() < 1) {
                throw new RuntimeException('Only processing orders can be cancelled.');
            }
            residence_restore_order_stock($pdo, $storeId);
            $cancelled[] = $store;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $notifyEmail = strtolower(trim($email));
    foreach ($cancelled as $store) {
        $orderNumberLabel = trim((string) ($store['order_number'] ?? ''));
        $pharmacyName = trim((string) ($store['pharmacy_name'] ?? 'the pharmacy'));
        if ($notifyEmail === '' || $orderNumberLabel === '') {
            continue;
        }
        resident_notification_add(
            $notifyEmail,
            'Order cancelled',
            'You cancelled order #' . $orderNumberLabel . ' at ' . ($pharmacyName !== '' ? $pharmacyName : 'the pharmacy') . ' — ' . $reason,
            'order',
            '',
            $orderNumberLabel
        );
    }

    return [
        'ok' => true,
        'message' => 'Order cancelled.',
        'orders' => residence_customer_orders_payload($email),
    ];
}

function residence_find_order_by_intent(string $intentId): ?array
{
    $intentId = trim($intentId);
    if ($intentId === '') {
        return null;
    }

    $pdo = pharmacy_db();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE paymongo_intent_id = ? LIMIT 1');
        $stmt->execute([$intentId]);
        $order = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }
    if (!$order) {
        return null;
    }

    $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $itemsStmt->execute([(int) $order['id']]);
    $order['items'] = $itemsStmt->fetchAll();

    return $order;
}
