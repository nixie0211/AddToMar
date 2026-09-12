<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function pharmacy_current_id(): string
{
    return trim((string) ($_SESSION['pharmacy_id'] ?? ''));
}

function pharmacy_scope_sql(string $alias = ''): string
{
    $id = pharmacy_current_id();
    if ($id === '') {
        return '0=1';
    }

    $column = ($alias !== '' ? $alias . '.' : '') . 'pharmacy_id';
    $quoted = pharmacy_db()->quote($id);

    // Inventory and related records belong only to the signed-in pharmacy.
    return "{$column} = {$quoted}";
}

function pharmacy_sync_settings_from_account(array $account): void
{
    $pharmacyId = trim((string) ($account['id'] ?? ''));
    if ($pharmacyId === '') {
        return;
    }

    $openTime = (string) ($account['open_time'] ?? '08:00');
    $closeTime = (string) ($account['close_time'] ?? '20:00');
    if (strlen($openTime) === 5) {
        $openTime .= ':00';
    }
    if (strlen($closeTime) === 5) {
        $closeTime .= ':00';
    }

    $pdo = pharmacy_db();
    $stmt = $pdo->prepare('
        INSERT INTO pharmacy_settings (
            pharmacy_id, pharmacy_name, owner_name, address, email, phone,
            opening_time, closing_time, staff_name, staff_role
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            pharmacy_name = VALUES(pharmacy_name),
            owner_name = VALUES(owner_name),
            address = VALUES(address),
            email = VALUES(email),
            phone = VALUES(phone),
            opening_time = VALUES(opening_time),
            closing_time = VALUES(closing_time),
            updated_at = CURRENT_TIMESTAMP
    ');

    $stmt->execute([
        $pharmacyId,
        trim((string) ($account['pharmacy_name'] ?? '')),
        trim((string) ($account['pharmacy_name'] ?? '')),
        trim((string) ($account['address'] ?? '')),
        trim((string) ($account['email'] ?? '')),
        preg_replace('/\D+/', '', (string) ($account['contact_number'] ?? '')),
        $openTime,
        $closeTime,
        'Pharmacist',
        'Admin',
    ]);
}

function pharmacy_medicine_owned(array $medicine): bool
{
    $pharmacyId = pharmacy_current_id();
    if ($pharmacyId === '') {
        return false;
    }

    $ownerId = trim((string) ($medicine['pharmacy_id'] ?? ''));
    return $ownerId === $pharmacyId;
}

function pharmacy_order_owned(?array $order): bool
{
    if (!$order) {
        return false;
    }

    $pharmacyId = pharmacy_current_id();
    if ($pharmacyId === '') {
        return false;
    }

    $ownerId = trim((string) ($order['pharmacy_id'] ?? ''));
    return $ownerId === $pharmacyId;
}
