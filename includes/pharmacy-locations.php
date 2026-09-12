<?php

declare(strict_types=1);

require_once __DIR__ . '/maps.php';

function pharmacy_locations(): array
{
    return [
        ['id' => 'mercury-laoag', 'name' => 'Mercury Drug', 'branch' => 'Laoag City', 'city' => 'Laoag City', 'address' => 'Gen. Luna St., Laoag City, Ilocos Norte', 'contact' => '09171234501', 'latitude' => 18.1982, 'longitude' => 120.5925],
        ['id' => 'generika-laoag', 'name' => 'Generika', 'branch' => 'Laoag City', 'city' => 'Laoag City', 'address' => 'Balacad, Laoag City, Ilocos Norte', 'contact' => '09171234502', 'latitude' => 18.1965, 'longitude' => 120.5950],
        ['id' => 'southstar-laoag', 'name' => 'South Star Drug', 'branch' => 'Laoag City', 'city' => 'Laoag City', 'address' => 'Rizal St., Laoag City, Ilocos Norte', 'contact' => '09171234503', 'latitude' => 18.1995, 'longitude' => 120.5970],
        ['id' => 'healthplus-laoag', 'name' => 'HealthPlus Pharmacy', 'branch' => 'Laoag City', 'city' => 'Laoag City', 'address' => 'A. Bonifacio Ave., Laoag City, Ilocos Norte', 'contact' => '09171234504', 'latitude' => 18.1948, 'longitude' => 120.5910],
        ['id' => 'watsons-sannicolas', 'name' => 'Watsons', 'branch' => 'San Nicolas', 'city' => 'San Nicolas', 'address' => 'National Highway, San Nicolas, Ilocos Norte', 'contact' => '09171234505', 'latitude' => 18.1710, 'longitude' => 120.5940],
        ['id' => 'tgp-sannicolas', 'name' => 'The Generics Pharmacy', 'branch' => 'San Nicolas', 'city' => 'San Nicolas', 'address' => 'Barangay 3, San Nicolas, Ilocos Norte', 'contact' => '09171234506', 'latitude' => 18.1740, 'longitude' => 120.5965],
        ['id' => 'rose-sannicolas', 'name' => 'Rose Pharmacy', 'branch' => 'San Nicolas', 'city' => 'San Nicolas', 'address' => 'San Nicolas Public Market, Ilocos Norte', 'contact' => '09171234507', 'latitude' => 18.1725, 'longitude' => 120.5952],
        ['id' => 'tgp-batac', 'name' => 'The Generics Pharmacy', 'branch' => 'Batac City', 'city' => 'Batac City', 'address' => 'National Highway, Batac City, Ilocos Norte', 'contact' => '09171234508', 'latitude' => 18.0560, 'longitude' => 120.5655],
        ['id' => 'mercury-batac', 'name' => 'Mercury Drug', 'branch' => 'Batac City', 'city' => 'Batac City', 'address' => 'Plaza Ma. Cristina, Batac City, Ilocos Norte', 'contact' => '09171234509', 'latitude' => 18.0540, 'longitude' => 120.5630],
        ['id' => 'wellcare-batac', 'name' => 'Wellcare Pharmacy', 'branch' => 'Batac City', 'city' => 'Batac City', 'address' => 'Quiling Sur, Batac City, Ilocos Norte', 'contact' => '09171234510', 'latitude' => 18.0575, 'longitude' => 120.5680],
        ['id' => 'careplus-batac', 'name' => 'Care+ Drugstore', 'branch' => 'Batac City', 'city' => 'Batac City', 'address' => 'Rayuray, Batac City, Ilocos Norte', 'contact' => '09171234511', 'latitude' => 18.0530, 'longitude' => 120.5620],
        ['id' => 'farmacia-batac', 'name' => 'Farmacia San Pablo', 'branch' => 'Batac City', 'city' => 'Batac City', 'address' => 'Valdez St., Batac City, Ilocos Norte', 'contact' => '09171234512', 'latitude' => 18.0554, 'longitude' => 120.5649, 'open_time' => '08:00', 'close_time' => '20:00', 'operation_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat']],
    ];
}

function pharmacy_timezone(): DateTimeZone
{
    return function_exists('app_timezone') ? app_timezone() : new DateTimeZone('Asia/Manila');
}

function pharmacy_normalize_time(string $time): string
{
    $time = trim($time);
    if ($time === '') {
        return '08:00:00';
    }

    foreach (['H:i:s', 'H:i', 'g:i A', 'g:i a', 'h:i A', 'h:i a'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat('!' . $format, $time, pharmacy_timezone());
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('H:i:s');
        }
    }

    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ':00';
    }

    return $time;
}

function pharmacy_is_open(array $pharmacy): bool
{
    $now = new DateTimeImmutable('now', pharmacy_timezone());
    $dayMap = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $today = $dayMap[(int) $now->format('w')];

    $days = $pharmacy['operation_days'] ?? null;
    $hours = $pharmacy['operating_hours'] ?? null;
    if (!is_array($days)) {
        $days = [];
    }
    if (!is_array($hours)) {
        $hours = [];
    }
    if ($days === [] && $hours !== []) {
        $days = array_keys($hours);
    }
    if ($days === []) {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    }

    if (!in_array($today, $days, true)) {
        return false;
    }

    $dayHours = $hours[$today] ?? null;
    $openSource = is_array($dayHours) ? (string) ($dayHours['open'] ?? '') : (string) ($pharmacy['open_time'] ?? '08:00');
    $closeSource = is_array($dayHours) ? (string) ($dayHours['close'] ?? '') : (string) ($pharmacy['close_time'] ?? '20:00');
    $open = pharmacy_normalize_time($openSource !== '' ? $openSource : '08:00');
    $close = pharmacy_normalize_time($closeSource !== '' ? $closeSource : '20:00');
    $current = $now->format('H:i:s');

    if ($open <= $close) {
        return $current >= $open && $current <= $close;
    }

    return $current >= $open || $current <= $close;
}

function pharmacy_enrich(array $pharmacy): array
{
    $pharmacy['open_time'] = $pharmacy['open_time'] ?? '08:00';
    $pharmacy['close_time'] = $pharmacy['close_time'] ?? '20:00';
    $pharmacy['operation_days'] = $pharmacy['operation_days'] ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $pharmacy['is_open'] = pharmacy_is_open($pharmacy);

    return $pharmacy;
}

function pharmacies_nearby(?float $userLat, ?float $userLng, int $limit = 5): array
{
    $partnersFile = dirname(__DIR__) . '/residence/includes/pharmacies.php';
    if (is_file($partnersFile)) {
        require_once $partnersFile;
        $pharmacies = residence_pharmacies_for_user($userLat, $userLng);

        return array_slice($pharmacies, 0, $limit);
    }

    return [];
}
