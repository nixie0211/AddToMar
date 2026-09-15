<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/pharmacy-accounts.php';
require_once dirname(__DIR__, 2) . '/includes/pharmacy-locations.php';
require_once dirname(__DIR__, 2) . '/pharmacy/config.php';
require_once dirname(__DIR__, 2) . '/pharmacy/includes/database.php';
require_once dirname(__DIR__, 2) . '/pharmacy/includes/repository.php';

function residence_pharmacy_branch_from_address(string $address): string
{
    $haystack = strtolower($address);
    if (str_contains($haystack, 'laoag')) {
        return 'Laoag City';
    }
    if (str_contains($haystack, 'san nicolas')) {
        return 'San Nicolas';
    }
    if (str_contains($haystack, 'batac')) {
        return 'Batac City';
    }

    return $address !== '' ? $address : 'Ilocos Norte';
}

function residence_pharmacy_locator_city_key(array $pharmacy): string
{
    $blob = strtolower(trim(implode(' ', array_filter([
        (string) ($pharmacy['city'] ?? ''),
        (string) ($pharmacy['branch'] ?? ''),
        (string) ($pharmacy['address'] ?? ''),
        (string) ($pharmacy['label'] ?? ''),
        (string) ($pharmacy['name'] ?? ''),
    ]))));

    if (str_contains($blob, 'laoag')) {
        return 'laoag';
    }
    if (str_contains($blob, 'san nicolas')) {
        return 'san-nicolas';
    }
    if (str_contains($blob, 'batac')) {
        return 'batac';
    }

    return '';
}

function residence_pharmacy_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'Rx';
}

function residence_pharmacy_directory(): array
{
    $directory = [];
    $soldByPharmacy = [];

    try {
        $soldRows = pharmacy_db()->query('
            SELECT o.pharmacy_id, COALESCE(SUM(oi.quantity), 0) AS total_sold
            FROM orders o
            INNER JOIN order_items oi ON oi.order_id = o.id
            WHERE o.status = "delivered"
              AND o.pharmacy_id IS NOT NULL
              AND o.pharmacy_id <> ""
            GROUP BY o.pharmacy_id
        ')->fetchAll();
        foreach ($soldRows as $soldRow) {
            $soldByPharmacy[(string) $soldRow['pharmacy_id']] = (int) $soldRow['total_sold'];
        }
    } catch (Throwable) {
        // A pharmacy with no order history simply displays zero sold items.
    }

    foreach (pharmacy_accounts_list_approved() as $account) {
        $id = trim((string) ($account['id'] ?? ''));
        if ($id === '') {
            continue;
        }

        $name = trim((string) ($account['pharmacy_name'] ?? 'Pharmacy'));
        $address = trim((string) ($account['address'] ?? ''));
        $branch = residence_pharmacy_branch_from_address($address);
        $label = $branch !== '' && !str_contains(strtolower($name), strtolower($branch))
            ? $name . ' — ' . $branch
            : $name;

        $logoUrl = pharmacy_accounts_public_logo_url($account);
        $logoPath = trim((string) ($account['logo_path'] ?? ''));

        $lat = $account['latitude'] ?? null;
        $lng = $account['longitude'] ?? null;

        $pharmacy = pharmacy_enrich([
            'id' => $id,
            'name' => $name,
            'branch' => $branch,
            'label' => $label,
            'initials' => residence_pharmacy_initials($name),
            'logo_label' => $name,
            'color' => '#1D5FA8',
            'address' => $address,
            'contact' => trim((string) ($account['contact_number'] ?? '')),
            'email' => trim((string) ($account['email'] ?? '')),
            'latitude' => $lat !== null && $lat !== '' ? (float) $lat : null,
            'longitude' => $lng !== null && $lng !== '' ? (float) $lng : null,
            'open_time' => (string) ($account['open_time'] ?? '08:00'),
            'close_time' => (string) ($account['close_time'] ?? '20:00'),
            'operation_days' => (is_array($account['operation_days'] ?? null) && $account['operation_days'] !== [])
                ? $account['operation_days']
                : ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'operating_hours' => is_array($account['operating_hours'] ?? null) ? $account['operating_hours'] : [],
            'total_sold' => $soldByPharmacy[$id] ?? 0,
            'logo_url' => $logoUrl,
            'logo_path' => $logoPath,
        ]);

        $directory[$id] = $pharmacy;
    }

    return $directory;
}

function residence_catalog_hydrate_medicine(array $row, array $pharmacy): array
{
    $status = pharmacy_medicine_status($row);
    $price = (float) ($row['selling_price'] ?? 0);
    if ($price <= 0) {
        $price = (float) ($row['unit_price'] ?? 0);
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'pharmacy_id' => (string) ($row['pharmacy_id'] ?? ''),
        'pharmacy_label' => $pharmacy['label'],
        'pharmacy_name' => $pharmacy['name'],
        'store_slug' => residence_store_slug((string) ($pharmacy['name'] ?? 'Pharmacy')),
        'pharmacy_logo_url' => (string) ($pharmacy['logo_url'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'generic_name' => (string) ($row['generic_name'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
        'dosage' => (string) ($row['dosage'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'price' => $price,
        'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
        'prescription_required' => !empty($row['prescription_required']),
        'status' => $status,
        'image_url' => pharmacy_medicine_image_url($row),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'is_new' => pharmacy_medicine_is_new($row),
        'is_featured' => !empty($row['is_featured']),
        'units_sold' => (int) ($row['units_sold'] ?? 0),
    ];
}

function residence_catalog_medicines(): array
{
    $directory = residence_pharmacy_directory();
    if ($directory === []) {
        return [];
    }

    $pdo = pharmacy_db();
    $stmt = $pdo->query('
        SELECT *
        FROM medicines
        WHERE is_active = 1
          AND pharmacy_id IS NOT NULL
          AND pharmacy_id <> ""
          AND stock_quantity > 0
        ORDER BY created_at DESC, name ASC
    ');
    $rows = $stmt->fetchAll();
    $catalog = [];

    foreach ($rows as $row) {
        $pharmacyId = trim((string) ($row['pharmacy_id'] ?? ''));
        if ($pharmacyId === '' || !isset($directory[$pharmacyId])) {
            continue;
        }

        $medicine = residence_catalog_hydrate_medicine($row, $directory[$pharmacyId]);
        if (($medicine['status'] ?? '') === 'expired') {
            continue;
        }
        $catalog[] = $medicine;
    }

    usort($catalog, static function (array $a, array $b): int {
        $byDate = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        if ($byDate !== 0) {
            return $byDate;
        }

        return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return $catalog;
}

function residence_catalog_new_products(array $catalog, int $limit = 10): array
{
    $items = array_values(array_filter($catalog, static fn(array $medicine): bool => !empty($medicine['is_new'])));
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    return array_slice($items, 0, $limit);
}

function residence_catalog_featured_products(array $catalog, int $limit = 10): array
{
    $items = array_values(array_filter(
        $catalog,
        static fn(array $medicine): bool => !empty($medicine['is_featured'])
    ));

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return array_slice($items, 0, $limit);
}

function residence_catalog_product_badge(array $medicine, array $topSellerIds = []): ?string
{
    if (!empty($medicine['is_new'])) {
        return 'new';
    }
    if (!empty($medicine['is_featured'])) {
        return 'featured';
    }
    $id = (int) ($medicine['id'] ?? 0);
    if ($id > 0 && isset($topSellerIds[$id])) {
        return 'top';
    }

    return null;
}

function residence_catalog_top_sellers(int $limit = 10): array
{
    $directory = residence_pharmacy_directory();
    if ($directory === []) {
        return [];
    }

    $pdo = pharmacy_db();
    $stmt = $pdo->query('
        SELECT m.*, COALESCE(SUM(oi.quantity), 0) AS units_sold
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN medicines m ON m.id = oi.medicine_id
        WHERE m.is_active = 1
          AND m.pharmacy_id IS NOT NULL
          AND m.pharmacy_id <> ""
          AND m.stock_quantity > 0
          AND o.status = "delivered"
        GROUP BY m.id
        HAVING COALESCE(SUM(oi.quantity), 0) > 0
        ORDER BY units_sold DESC, m.name ASC
        LIMIT ' . max(1, $limit)
    );
    $rows = $stmt->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        if ((int) ($row['units_sold'] ?? 0) <= 0) {
            continue;
        }

        $pharmacyId = trim((string) ($row['pharmacy_id'] ?? ''));
        if ($pharmacyId === '' || !isset($directory[$pharmacyId])) {
            continue;
        }

        $medicine = residence_catalog_hydrate_medicine($row, $directory[$pharmacyId]);
        if (($medicine['status'] ?? '') === 'expired') {
            continue;
        }
        $items[] = $medicine;
    }

    return $items;
}

function residence_store_slug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '', $slug) ?? '';

    return $slug !== '' ? $slug : 'pharmacy';
}

function residence_catalog_category_slug(string $category): string
{
    $slug = strtolower(trim($category));

    return match ($slug) {
        'pain relief', 'pain-relief' => 'pain-relief',
        'antibiotic', 'antibiotics' => 'antibiotic',
        'maintenance' => 'maintenance',
        'cold & flu', 'cold-flu' => 'cold-flu',
        'vitamins', 'vitamins & supplements' => 'vitamins',
        default => preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'medicines',
    };
}

function residence_catalog_shop_category(string $category): string
{
    $slug = residence_catalog_category_slug($category);

    return in_array($slug, ['vitamins', 'wellness', 'skincare'], true) ? 'health-products' : 'medicines';
}
