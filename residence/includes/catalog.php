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

        $logoUrl = '';
        $logoPath = trim((string) ($account['logo_path'] ?? ''));
        $logoAbs = $logoPath !== '' ? dirname(__DIR__, 2) . '/' . $logoPath : '';
        if ($logoPath !== '' && is_file($logoAbs) && function_exists('app_url')) {
            $logoUrl = app_url($logoPath);
        }

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
        ORDER BY name ASC
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

    return $catalog;
}

function residence_catalog_new_products(array $catalog, int $limit = 12): array
{
    $items = array_values(array_filter($catalog, static fn(array $medicine): bool => !empty($medicine['is_new'])));
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    if (count($items) < $limit) {
        $seen = array_flip(array_column($items, 'id'));
        $fallback = $catalog;
        usort($fallback, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        foreach ($fallback as $medicine) {
            if (isset($seen[$medicine['id']])) {
                continue;
            }
            $items[] = $medicine;
            if (count($items) >= $limit) {
                break;
            }
        }
    }

    return array_slice($items, 0, $limit);
}

function residence_catalog_featured_products(array $catalog, int $limit = 12): array
{
    $items = array_values(array_filter(
        $catalog,
        static function (array $medicine): bool {
            return (int) ($medicine['stock_quantity'] ?? 0) >= 5;
        }
    ));

    usort($items, static function (array $a, array $b): int {
        $imageA = trim((string) ($a['image_url'] ?? '')) !== '' ? 1 : 0;
        $imageB = trim((string) ($b['image_url'] ?? '')) !== '' ? 1 : 0;
        if ($imageA !== $imageB) {
            return $imageB <=> $imageA;
        }

        $stockCompare = (int) ($b['stock_quantity'] ?? 0) <=> (int) ($a['stock_quantity'] ?? 0);
        if ($stockCompare !== 0) {
            return $stockCompare;
        }

        return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    if (count($items) < $limit) {
        $seen = array_flip(array_column($items, 'id'));
        foreach ($catalog as $medicine) {
            if (isset($seen[$medicine['id']])) {
                continue;
            }
            $items[] = $medicine;
            $seen[$medicine['id']] = true;
            if (count($items) >= $limit) {
                break;
            }
        }
    }

    return array_slice($items, 0, $limit);
}

function residence_catalog_top_sellers(int $limit = 12): array
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
          AND o.status NOT IN ("cancelled", "rejected")
        GROUP BY m.id
        ORDER BY units_sold DESC, m.name ASC
        LIMIT ' . max(1, $limit)
    );
    $rows = $stmt->fetchAll();
    $items = [];

    foreach ($rows as $row) {
        $pharmacyId = trim((string) ($row['pharmacy_id'] ?? ''));
        if ($pharmacyId === '' || !isset($directory[$pharmacyId])) {
            continue;
        }

        $items[] = residence_catalog_hydrate_medicine($row, $directory[$pharmacyId]);
    }

    if ($items !== []) {
        return $items;
    }

    $catalog = residence_catalog_medicines();
    usort($catalog, static function (array $a, array $b): int {
        $stockCompare = (int) ($b['stock_quantity'] ?? 0) <=> (int) ($a['stock_quantity'] ?? 0);
        if ($stockCompare !== 0) {
            return $stockCompare;
        }

        return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    return array_slice($catalog, 0, $limit);
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
