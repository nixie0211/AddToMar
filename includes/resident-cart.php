<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/customers.php';
require_once dirname(__DIR__) . '/residence/includes/catalog.php';

function resident_cart_email(?string $email = null): string
{
    $email = customers_normalize_email($email ?? (string) ($_SESSION['user_email'] ?? ''));
    return $email;
}

function resident_cart_payload(?string $email = null): array
{
    $email = resident_cart_email($email);
    $items = $email === '' ? [] : resident_cart_hydrate($email);
    $first = $items[0] ?? null;

    return [
        'ok' => true,
        'pharmacyId' => (string) ($first['pharmacyId'] ?? ''),
        'pharmacyName' => (string) ($first['pharmacyName'] ?? ''),
        'pharmacyLabel' => (string) ($first['pharmacyLabel'] ?? ''),
        'items' => $items,
    ];
}

function resident_cart_hydrate(string $email): array
{
    $email = customers_normalize_email($email);
    if ($email === '') {
        return [];
    }

    $stmt = caps_db()->prepare(
        'SELECT medicine_id, pharmacy_id, quantity, selected
         FROM cart
         WHERE user_email = ?
         ORDER BY id ASC'
    );
    $stmt->execute([$email]);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        return [];
    }

    $directory = residence_pharmacy_directory();
    $lookup = pharmacy_db()->prepare(
        'SELECT *
         FROM medicines
         WHERE id = ?
           AND pharmacy_id = ?
           AND is_active = 1
         LIMIT 1'
    );
    $delete = caps_db()->prepare(
        'DELETE FROM cart WHERE user_email = ? AND medicine_id = ? AND pharmacy_id = ?'
    );
    $updateQty = caps_db()->prepare(
        'UPDATE cart SET quantity = ? WHERE user_email = ? AND medicine_id = ? AND pharmacy_id = ?'
    );

    $items = [];
    foreach ($rows as $row) {
        $medicineId = (int) ($row['medicine_id'] ?? 0);
        $pharmacyId = trim((string) ($row['pharmacy_id'] ?? ''));
        $quantity = max(1, (int) ($row['quantity'] ?? 1));

        if ($medicineId <= 0 || $pharmacyId === '' || !isset($directory[$pharmacyId])) {
            $delete->execute([$email, $medicineId, $pharmacyId]);
            continue;
        }

        $lookup->execute([$medicineId, $pharmacyId]);
        $medicineRow = $lookup->fetch();
        if (!$medicineRow) {
            $delete->execute([$email, $medicineId, $pharmacyId]);
            continue;
        }

        $hydrated = residence_catalog_hydrate_medicine($medicineRow, $directory[$pharmacyId]);
        if (($hydrated['status'] ?? '') === 'expired' || (int) ($hydrated['stock_quantity'] ?? 0) <= 0) {
            $delete->execute([$email, $medicineId, $pharmacyId]);
            continue;
        }

        $stock = (int) ($hydrated['stock_quantity'] ?? 0);
        if ($quantity > $stock) {
            $quantity = $stock;
            $updateQty->execute([$quantity, $email, $medicineId, $pharmacyId]);
        }

        $items[] = [
            'medicineId' => $medicineId,
            'pharmacyId' => $pharmacyId,
            'pharmacyName' => (string) ($hydrated['pharmacy_name'] ?? ''),
            'pharmacyLabel' => (string) ($hydrated['pharmacy_label'] ?? ''),
            'name' => (string) ($hydrated['name'] ?? ''),
            'meta' => (string) ($hydrated['generic_name'] ?? ''),
            'price' => (float) ($hydrated['price'] ?? 0),
            'quantity' => $quantity,
            'rxRequired' => !empty($hydrated['prescription_required']),
            'selected' => !empty($row['selected']),
            'image' => (string) ($hydrated['image_url'] ?? ''),
        ];
    }

    return $items;
}

function resident_cart_replace(string $email, array $items): array
{
    $email = customers_normalize_email($email);
    if ($email === '') {
        return ['ok' => false, 'error' => 'Sign in to save your cart.'];
    }

    $pdo = caps_db();
    $pdo->beginTransaction();
    try {
        $clear = $pdo->prepare('DELETE FROM cart WHERE user_email = ?');
        $clear->execute([$email]);

        $insert = $pdo->prepare(
            'INSERT INTO cart (user_email, medicine_id, pharmacy_id, quantity, selected)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), selected = VALUES(selected)'
        );

        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $medicineId = (int) ($item['medicine_id'] ?? $item['medicineId'] ?? 0);
            $pharmacyId = trim((string) ($item['pharmacy_id'] ?? $item['pharmacyId'] ?? ''));
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $selected = !empty($item['selected']) ? 1 : 0;
            $key = $medicineId . ':' . $pharmacyId;

            if ($medicineId <= 0 || $pharmacyId === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $insert->execute([$email, $medicineId, $pharmacyId, $quantity, $selected]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Could not save your cart.'];
    }

    return resident_cart_payload($email);
}
