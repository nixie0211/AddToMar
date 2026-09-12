<?php
require_once __DIR__ . '/../config/maps.php';

/** Haversine distance in kilometers */
function distance_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $earth = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
}

function pharmacy_is_open(array $pharmacy): bool {
    if (($pharmacy['status'] ?? '') !== 'active') {
        return false;
    }
    $open = $pharmacy['open_time'] ?? '08:00:00';
    $close = $pharmacy['close_time'] ?? '20:00:00';
    $now = date('H:i:s');
    if ($open <= $close) {
        return $now >= $open && $now <= $close;
    }
    return $now >= $open || $now <= $close;
}

function sync_main_pharmacy_inventory(PDO $pdo, int $medicineId, int $quantity, float $price): void {
    $stmt = $pdo->prepare('SELECT id FROM pharmacies ORDER BY id ASC LIMIT 1');
    $stmt->execute();
    $main = $stmt->fetch();
    if (!$main) {
        return;
    }
    $upsert = $pdo->prepare('INSERT INTO inventory (pharmacy_id, medicine_id, stock_quantity, price)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE stock_quantity = VALUES(stock_quantity), price = VALUES(price)');
    $upsert->execute([(int)$main['id'], $medicineId, $quantity, $price]);
}

function log_medicine_search(PDO $pdo, ?int $userId, string $term, ?int $medicineId, int $resultsCount): void {
    $term = trim($term);
    if ($term === '') {
        return;
    }
    $pdo->prepare('INSERT INTO search_analytics (user_id, search_term, medicine_id, results_count) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $term, $medicineId, $resultsCount]);
    if ($userId) {
        $pdo->prepare('INSERT INTO search_history (user_id, search_term, medicine_id) VALUES (?, ?, ?)')
            ->execute([$userId, $term, $medicineId]);
    }
}

function get_pharmacies_with_stock(PDO $pdo, int $medicineId, ?float $userLat = null, ?float $userLng = null): array {
    $sql = 'SELECT p.*, i.stock_quantity, i.price AS pharmacy_price
            FROM pharmacies p
            INNER JOIN inventory i ON i.pharmacy_id = p.id
            WHERE p.status = "active" AND i.medicine_id = ? AND i.stock_quantity > 0
            ORDER BY p.pharmacy_name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$medicineId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['is_open'] = pharmacy_is_open($row);
        $row['distance_km'] = null;
        if ($userLat !== null && $userLng !== null) {
            $row['distance_km'] = distance_km($userLat, $userLng, (float)$row['latitude'], (float)$row['longitude']);
        }
    }
    unset($row);

    if ($userLat !== null && $userLng !== null) {
        usort($rows, static fn($a, $b) => ($a['distance_km'] ?? 999) <=> ($b['distance_km'] ?? 999));
    }

    return $rows;
}

function format_travel_time(float $distanceKm): string {
    $minutes = (int) max(1, round(($distanceKm / 30) * 60));
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . ' hr' . ($m ? " {$m} min" : '');
}
