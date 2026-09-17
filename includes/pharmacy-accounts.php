<?php

declare(strict_types=1);

require_once __DIR__ . '/maps.php';

function pharmacy_accounts_storage_path(): string
{
    return dirname(__DIR__) . '/data/pharmacy-accounts.json';
}

function pharmacy_accounts_uploads_root(): string
{
    return dirname(__DIR__) . '/data/uploads/pharmacies';
}

function pharmacy_accounts_load_all(): array
{
    $fromFile = pharmacy_accounts_load_from_file();
    $fromDatabase = pharmacy_accounts_load_from_database();

    return array_replace($fromFile, $fromDatabase);
}

function pharmacy_accounts_load_from_file(): array
{
    $path = pharmacy_accounts_storage_path();

    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function pharmacy_accounts_load_from_database(): array
{
    try {
        $rows = caps_db()->query('SELECT * FROM pharmacies')->fetchAll();
        $accounts = [];
        foreach ($rows as $row) {
            $account = pharmacy_accounts_hydrate_db_row($row);
            $email = pharmacy_accounts_normalize_email((string) ($account['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $accounts[$email] = $account;
        }

        return $accounts;
    } catch (Throwable) {
        return [];
    }
}

function pharmacy_accounts_save_all(array $accounts): bool
{
    $path = pharmacy_accounts_storage_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $payload = json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    return file_put_contents($path, $payload . PHP_EOL, LOCK_EX) !== false;
}

function pharmacy_accounts_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function pharmacy_accounts_normalize_status(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === 'active') {
        return 'approved';
    }

    return $status !== '' ? $status : 'pending';
}

function pharmacy_accounts_status_events(array $account): array
{
    $created = (string) ($account['created_at'] ?? '');
    $updated = (string) ($account['updated_at'] ?? $created);
    $current = pharmacy_accounts_normalize_status((string) ($account['status'] ?? 'pending'));
    $raw = $account['status_history'] ?? null;

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : null;
    }

    $events = [];
    if (is_array($raw)) {
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $status = pharmacy_accounts_normalize_status((string) ($entry['status'] ?? ''));
            $at = trim((string) ($entry['at'] ?? ''));
            if ($at === '') {
                continue;
            }
            $events[] = [
                'status' => $status,
                'at' => $at,
                'note' => trim((string) ($entry['note'] ?? '')),
            ];
        }
    }

    if ($events === []) {
        $events[] = [
            'status' => 'pending',
            'at' => $created !== '' ? $created : $updated,
            'note' => '',
        ];
        if ($current !== 'pending' && $updated !== '') {
            $events[] = [
                'status' => $current,
                'at' => $updated,
                'note' => trim((string) ($account['admin_note'] ?? '')),
            ];
        }
    }

    return $events;
}

function pharmacy_accounts_append_status_event(array $account, string $status, string $at, string $note = ''): array
{
    $status = pharmacy_accounts_normalize_status($status);
    $events = pharmacy_accounts_status_events($account);
    $last = $events[array_key_last($events)] ?? null;
    if (is_array($last) && ($last['status'] ?? '') === $status) {
        $account['status_history'] = $events;

        return $account;
    }

    $events[] = [
        'status' => $status,
        'at' => $at,
        'note' => $note,
    ];
    $account['status_history'] = $events;

    return $account;
}

function pharmacy_accounts_find_by_email(string $email): ?array
{
    $email = pharmacy_accounts_normalize_email($email);
    if ($email === '') {
        return null;
    }

    return pharmacy_accounts_load_all()[$email] ?? null;
}

function pharmacy_accounts_operation_day_options(): array
{
    return [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];
}

function pharmacy_accounts_default_operating_hours(): array
{
    $hours = [];
    foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as $day) {
        $hours[$day] = ['open' => '08:00', 'close' => '20:00'];
    }

    return $hours;
}

function pharmacy_accounts_collect_operating_hours(array $dayHours, array $operationDays): array
{
    $validDays = array_keys(pharmacy_accounts_operation_day_options());
    $hours = [];

    foreach ($operationDays as $day) {
        $day = strtolower(trim((string) $day));
        if (!in_array($day, $validDays, true)) {
            continue;
        }

        $open = trim((string) ($dayHours[$day]['open'] ?? ''));
        $close = trim((string) ($dayHours[$day]['close'] ?? ''));

        if (!preg_match('/^\d{2}:\d{2}$/', $open) || !preg_match('/^\d{2}:\d{2}$/', $close)) {
            return ['ok' => false, 'error' => 'Set opening and closing times for each selected day.'];
        }

        if ($open >= $close) {
            $label = pharmacy_accounts_operation_day_options()[$day];

            return ['ok' => false, 'error' => 'Closing time must be later than opening time on ' . $label . '.'];
        }

        $hours[$day] = ['open' => $open, 'close' => $close];
    }

    if ($hours === []) {
        return ['ok' => false, 'error' => 'Select at least one day of operation.'];
    }

    $opens = array_column($hours, 'open');
    $closes = array_column($hours, 'close');

    return [
        'ok' => true,
        'hours' => $hours,
        'open_time' => min($opens),
        'close_time' => max($closes),
    ];
}

function pharmacy_accounts_verify_password(array $account, string $password): bool
{
    $hash = $account['password_hash'] ?? '';

    if ($hash === '') {
        return $password !== '';
    }

    return password_verify($password, $hash);
}

function pharmacy_accounts_files_from_upload(array $files, string $key): array
{
    if (!isset($files[$key]) || !is_array($files[$key])) {
        return [];
    }

    $entry = $files[$key];
    if (!isset($entry['name'])) {
        return [];
    }

    if (!is_array($entry['name'])) {
        return [$entry];
    }

    $list = [];
    foreach ($entry['name'] as $index => $name) {
        $list[] = [
            'name' => (string) $name,
            'type' => (string) ($entry['type'][$index] ?? ''),
            'tmp_name' => (string) ($entry['tmp_name'][$index] ?? ''),
            'error' => $entry['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => (int) ($entry['size'][$index] ?? 0),
        ];
    }

    return $list;
}

function pharmacy_accounts_document_paths(array $account, string $key): array
{
    $paths = $account[$key . '_paths'] ?? [];
    if (!is_array($paths)) {
        $paths = [];
    }

    $paths = array_values(array_filter(array_map('strval', $paths)));
    if ($paths !== []) {
        return $paths;
    }

    $single = (string) ($account[$key . '_path'] ?? '');
    return $single !== '' ? [$single] : [];
}

function pharmacy_accounts_store_upload(array $file, string $accountId, string $basename, array $allowedExtensions): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    if (($file['size'] ?? 0) < 1) {
        return null;
    }

    $original = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    $dir = pharmacy_accounts_uploads_root() . '/' . $accountId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $filename = $basename . '.' . $extension;
    $absolute = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $absolute)) {
        return null;
    }

    $relative = 'data/uploads/pharmacies/' . $accountId . '/' . $filename;
    pharmacy_accounts_persist_document_file($accountId, $basename, $relative, $absolute);

    return $relative;
}

function pharmacy_accounts_mime_for_extension(string $extension): string
{
    return match (strtolower($extension)) {
        'pdf' => 'application/pdf',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        default => 'application/octet-stream',
    };
}

function pharmacy_accounts_doc_key_from_filename(string $basename): string
{
    $basename = strtolower($basename);
    if (str_starts_with($basename, 'business_permit')) {
        return 'business_permit';
    }
    if (str_starts_with($basename, 'pharmacy_license')) {
        return 'pharmacy_license';
    }
    if (str_starts_with($basename, 'bir_certificate')) {
        return 'bir_certificate';
    }
    if (str_starts_with($basename, 'logo')) {
        return 'logo';
    }

    return $basename;
}

function pharmacy_accounts_persist_document_file(string $pharmacyId, string $basename, string $relativePath, string $absolutePath): void
{
    if ($pharmacyId === '' || !is_file($absolutePath)) {
        return;
    }

    $contents = file_get_contents($absolutePath);
    if ($contents === false || $contents === '') {
        return;
    }

    $filename = basename($relativePath);
    $mime = pharmacy_accounts_mime_for_extension(pathinfo($filename, PATHINFO_EXTENSION));
    $docKey = pharmacy_accounts_doc_key_from_filename($basename !== '' ? $basename : pathinfo($filename, PATHINFO_FILENAME));

    try {
        $pdo = caps_db();
        $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM pharmacy_documents WHERE pharmacy_id = ? AND doc_key = ?');
        $sortStmt->execute([$pharmacyId, $docKey]);
        $sort = (int) $sortStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO pharmacy_documents (pharmacy_id, doc_key, filename, mime, content, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$pharmacyId, $docKey, $filename, $mime, $contents, $sort]);
    } catch (Throwable) {
        // Keep the disk copy even if the database copy cannot be stored.
    }
}

function pharmacy_accounts_list_documents(string $pharmacyId, ?string $docKey = null): array
{
    if ($pharmacyId === '') {
        return [];
    }

    try {
        if ($docKey) {
            $stmt = caps_db()->prepare(
                'SELECT id, pharmacy_id, doc_key, filename, mime, sort_order
                 FROM pharmacy_documents
                 WHERE pharmacy_id = ? AND doc_key = ?
                 ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([$pharmacyId, $docKey]);
        } else {
            $stmt = caps_db()->prepare(
                'SELECT id, pharmacy_id, doc_key, filename, mime, sort_order
                 FROM pharmacy_documents
                 WHERE pharmacy_id = ?
                 ORDER BY doc_key ASC, sort_order ASC, id ASC'
            );
            $stmt->execute([$pharmacyId]);
        }

        return $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
}

function pharmacy_accounts_latest_logo_document_id(string $pharmacyId): int
{
    if ($pharmacyId === '') {
        return 0;
    }

    try {
        $stmt = caps_db()->prepare(
            'SELECT id FROM pharmacy_documents WHERE pharmacy_id = ? AND doc_key = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$pharmacyId, 'logo']);

        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function pharmacy_accounts_public_logo_url(array $account): string
{
    if (!function_exists('app_url')) {
        require_once __DIR__ . '/auth.php';
    }

    $id = trim((string) ($account['id'] ?? ''));
    if ($id !== '' && pharmacy_accounts_latest_logo_document_id($id) > 0) {
        return app_url('pharmacy-logo.php?id=' . rawurlencode($id));
    }

    $logoPath = trim((string) ($account['logo_path'] ?? ''));
    if ($logoPath === '') {
        return '';
    }

    $absolute = dirname(__DIR__) . '/' . ltrim($logoPath, '/');
    if (is_file($absolute)) {
        return app_url($logoPath);
    }

    return '';
}

function pharmacy_accounts_get_document(int $documentId): ?array
{
    if ($documentId <= 0) {
        return null;
    }

    try {
        $stmt = caps_db()->prepare(
            'SELECT id, pharmacy_id, doc_key, filename, mime, content
             FROM pharmacy_documents
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$documentId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function pharmacy_accounts_backfill_documents(array $account): void
{
    $pharmacyId = trim((string) ($account['id'] ?? ''));
    if ($pharmacyId === '' || pharmacy_accounts_list_documents($pharmacyId) !== []) {
        return;
    }

    $root = dirname(__DIR__);
    $keys = ['logo' => 'logo', 'business_permit' => 'business_permit', 'pharmacy_license' => 'pharmacy_license', 'bir_certificate' => 'bir_certificate'];
    foreach ($keys as $key => $basename) {
        $paths = $key === 'logo'
            ? array_values(array_filter([(string) ($account['logo_path'] ?? '')]))
            : pharmacy_accounts_document_paths($account, $key);
        foreach ($paths as $index => $relative) {
            $absolute = $root . '/' . ltrim($relative, '/');
            if (!is_file($absolute)) {
                continue;
            }
            $name = $index === 0 ? $basename : $basename . '-' . ($index + 1);
            pharmacy_accounts_persist_document_file($pharmacyId, $name, $relative, $absolute);
        }
    }
}

function pharmacy_accounts_register(array $input, array $files): array
{
    $email = pharmacy_accounts_normalize_email($input['email'] ?? '');
    $contact = preg_replace('/\D+/', '', trim($input['contact_number'] ?? ''));
    $pharmacyName = trim($input['pharmacy_name'] ?? '');
    $address = trim($input['address'] ?? '');
    $openTime = trim($input['open_time'] ?? '');
    $closeTime = trim($input['close_time'] ?? '');
    $latitude = isset($input['latitude']) && $input['latitude'] !== '' ? (float) $input['latitude'] : null;
    $longitude = isset($input['longitude']) && $input['longitude'] !== '' ? (float) $input['longitude'] : null;
    $password = (string) ($input['password'] ?? '');
    $passwordConfirm = (string) ($input['password_confirm'] ?? '');
    $operationDays = $input['operation_days'] ?? [];
    $dayHoursInput = $input['day_hours'] ?? [];

    if (!is_array($operationDays)) {
        $operationDays = [];
    }
    if (!is_array($dayHoursInput)) {
        $dayHoursInput = [];
    }

    $operationDays = array_values(array_unique(array_filter(array_map(
        static fn($day) => strtolower(trim((string) $day)),
        $operationDays
    ))));

    $validDays = array_keys(pharmacy_accounts_operation_day_options());
    $operationDays = array_values(array_intersect($operationDays, $validDays));
    $schedule = pharmacy_accounts_collect_operating_hours($dayHoursInput, $operationDays);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid login email for your pharmacy account.'];
    }

    if ($pharmacyName === '') {
        return ['ok' => false, 'error' => 'Enter your pharmacy name.'];
    }

    if ($contact === '' || !preg_match('/^\d{11}$/', $contact)) {
        return ['ok' => false, 'error' => 'Contact number must be exactly 11 digits.'];
    }

    if ($password === '' || $passwordConfirm === '') {
        return ['ok' => false, 'error' => 'Please enter and confirm your password.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    if ($password !== $passwordConfirm) {
        return ['ok' => false, 'error' => 'Passwords do not match.'];
    }

    if (!$schedule['ok']) {
        return ['ok' => false, 'error' => $schedule['error']];
    }

    $operationDays = array_keys($schedule['hours']);
    $openTime = $schedule['open_time'];
    $closeTime = $schedule['close_time'];

    if ($address === '') {
        return ['ok' => false, 'error' => 'Enter your pharmacy address.'];
    }

    if ($latitude === null || $longitude === null) {
        return ['ok' => false, 'error' => 'Pin your pharmacy location on the map.'];
    }

    if (!maps_point_in_service_area($latitude, $longitude)) {
        return ['ok' => false, 'error' => 'Pharmacy location must be within Laoag City, San Nicolas, or Batac City.'];
    }

    $accounts = pharmacy_accounts_load_all();
    if (isset($accounts[$email])) {
        return ['ok' => false, 'error' => 'A pharmacy account with this email already exists. Sign in instead.'];
    }

    require_once __DIR__ . '/customers.php';
    if (customers_find_by_email($email)) {
        return ['ok' => false, 'error' => 'This email is already used by a customer account.'];
    }

    $requiredFiles = [
        'logo' => ['label' => 'Pharmacy logo', 'ext' => ['jpg', 'jpeg', 'png', 'webp', 'svg']],
        'business_permit' => ['label' => 'Business permit', 'ext' => ['jpg', 'jpeg', 'png', 'pdf']],
        'pharmacy_license' => ['label' => 'Pharmacy license', 'ext' => ['jpg', 'jpeg', 'png', 'pdf']],
        'bir_certificate' => ['label' => 'BIR certificate', 'ext' => ['jpg', 'jpeg', 'png', 'pdf']],
    ];

    foreach ($requiredFiles as $key => $meta) {
        if ($key === 'logo') {
            if (($files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return ['ok' => false, 'error' => 'Upload your ' . $meta['label'] . '.'];
            }
            continue;
        }

        $hasFile = false;
        foreach (pharmacy_accounts_files_from_upload($files, $key) as $file) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $hasFile = true;
                break;
            }
        }
        if (!$hasFile) {
            return ['ok' => false, 'error' => 'Upload your ' . $meta['label'] . '.'];
        }
    }

    $accountId = bin2hex(random_bytes(8));
    $storedFiles = [];

    foreach ($requiredFiles as $key => $meta) {
        if ($key === 'logo') {
            $path = pharmacy_accounts_store_upload($files[$key], $accountId, $key, $meta['ext']);
            if ($path === null) {
                return ['ok' => false, 'error' => 'Could not upload ' . $meta['label'] . '. Use JPG, PNG, WEBP, or SVG.'];
            }
            $storedFiles[$key . '_path'] = $path;
            continue;
        }

        $uploaded = array_values(array_filter(
            pharmacy_accounts_files_from_upload($files, $key),
            static fn(array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ));

        if ($uploaded === []) {
            return ['ok' => false, 'error' => 'Upload your ' . $meta['label'] . '.'];
        }

        if (count($uploaded) > 10) {
            return ['ok' => false, 'error' => $meta['label'] . ' can have at most 10 files.'];
        }

        $paths = [];
        foreach ($uploaded as $index => $file) {
            $basename = $index === 0 ? $key : $key . '-' . ($index + 1);
            $path = pharmacy_accounts_store_upload($file, $accountId, $basename, $meta['ext']);
            if ($path === null) {
                return ['ok' => false, 'error' => 'Could not upload ' . $meta['label'] . '. Use JPG, PNG, or PDF.'];
            }
            $paths[] = $path;
        }

        $storedFiles[$key . '_path'] = $paths[0];
        $storedFiles[$key . '_paths'] = $paths;
    }

    $now = date('c');
    $accounts[$email] = array_merge([
        'id' => $accountId,
        'email' => $email,
        'contact_number' => $contact,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'pharmacy_name' => $pharmacyName,
        'open_time' => $openTime,
        'close_time' => $closeTime,
        'operation_days' => $operationDays,
        'operating_hours' => $schedule['hours'],
        'address' => $address,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'status' => 'pending',
        'created_at' => $now,
        'updated_at' => $now,
        'status_history' => [
            ['status' => 'pending', 'at' => $now, 'note' => ''],
        ],
    ], $storedFiles);

    if (!pharmacy_accounts_save_all($accounts)) {
        return ['ok' => false, 'error' => 'Could not save your pharmacy account. Please try again.'];
    }

    // Mirror the registration in users immediately, even while it is pending review.
    $sync = pharmacy_accounts_sync_database($accounts[$email]);
    if (!$sync['ok']) {
        return ['ok' => false, 'error' => $sync['error'] ?? 'Could not save your pharmacy account to the database.'];
    }

    return ['ok' => true, 'account' => $accounts[$email]];
}

function pharmacy_accounts_find_by_id(string $id): ?array
{
    if ($id === '') {
        return null;
    }

    foreach (pharmacy_accounts_load_all() as $account) {
        if (($account['id'] ?? '') === $id) {
            return $account;
        }
    }

    return null;
}

function pharmacy_accounts_set_status(string $email, string $status, string $note = ''): array
{
    $email = pharmacy_accounts_normalize_email($email);
    $accounts = pharmacy_accounts_load_all();

    if ($email === '' || !isset($accounts[$email])) {
        return ['ok' => false, 'error' => 'Pharmacy account not found.'];
    }

    $now = date('c');
    $accounts[$email]['status'] = $status;
    $accounts[$email]['updated_at'] = $now;
    if ($note !== '') {
        $accounts[$email]['admin_note'] = $note;
    }
    if ($status === 'blocked') {
        $accounts[$email]['blocked_at'] = $now;
    }
    $accounts[$email] = pharmacy_accounts_append_status_event($accounts[$email], $status, $now, $note);

    if (!pharmacy_accounts_save_all($accounts)) {
        return ['ok' => false, 'error' => 'Could not update pharmacy status.'];
    }

    $account = $accounts[$email];
    $sync = pharmacy_accounts_sync_database($account);
    if (!$sync['ok']) {
        return ['ok' => false, 'error' => $sync['error'] ?? 'Could not save the pharmacy account to the database.'];
    }

    if (pharmacy_accounts_is_approved($account)) {
        pharmacy_accounts_sync_medvault($account);
    }

    return ['ok' => true, 'account' => $account];
}

function pharmacy_accounts_is_blocked(array $account): bool
{
    return strtolower((string) ($account['status'] ?? '')) === 'blocked';
}

function pharmacy_accounts_is_approved(array $account): bool
{
    $status = strtolower((string) ($account['status'] ?? ''));

    return $status === 'approved' || $status === 'active';
}

function pharmacy_accounts_encode_json(mixed $value): string
{
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '[]';
}

function pharmacy_accounts_sync_database(array $account): array
{
    require_once __DIR__ . '/database.php';

    $email = pharmacy_accounts_normalize_email((string) ($account['email'] ?? ''));
    $id = trim((string) ($account['id'] ?? ''));
    $status = strtolower((string) ($account['status'] ?? 'pending'));
    $passwordHash = (string) ($account['password_hash'] ?? '');

    if ($email === '' || $id === '') {
        return ['ok' => false, 'error' => 'Pharmacy account is missing an email or ID.'];
    }

    if ($passwordHash === '') {
        return ['ok' => false, 'error' => 'Pharmacy account is missing a password and cannot be saved.'];
    }

    $pharmacyName = trim((string) ($account['pharmacy_name'] ?? 'Pharmacy'));
    $contact = preg_replace('/\D+/', '', (string) ($account['contact_number'] ?? ''));
    $address = trim((string) ($account['address'] ?? ''));
    $latitude = isset($account['latitude']) && $account['latitude'] !== '' && $account['latitude'] !== null
        ? (float) $account['latitude']
        : null;
    $longitude = isset($account['longitude']) && $account['longitude'] !== '' && $account['longitude'] !== null
        ? (float) $account['longitude']
        : null;
    $openTime = (string) ($account['open_time'] ?? '08:00');
    $closeTime = (string) ($account['close_time'] ?? '20:00');
    $logoPath = (string) ($account['logo_path'] ?? '');
    $permitPath = (string) ($account['business_permit_path'] ?? '');
    $licensePath = (string) ($account['pharmacy_license_path'] ?? '');
    $birPath = (string) ($account['bir_certificate_path'] ?? '');
    $adminNote = (string) ($account['admin_note'] ?? '');
    $statusHistory = pharmacy_accounts_encode_json(pharmacy_accounts_status_events($account));
    $documentPaths = pharmacy_accounts_encode_json([
        'business_permit' => pharmacy_accounts_document_paths($account, 'business_permit'),
        'pharmacy_license' => pharmacy_accounts_document_paths($account, 'pharmacy_license'),
        'bir_certificate' => pharmacy_accounts_document_paths($account, 'bir_certificate'),
        'logo' => array_values(array_filter([(string) ($account['logo_path'] ?? '')])),
    ]);
    $operationDays = is_array($account['operation_days'] ?? null) ? $account['operation_days'] : [];
    $operatingHours = is_array($account['operating_hours'] ?? null) ? $account['operating_hours'] : [];

    try {
        $pdo = caps_db();

        $stmt = $pdo->prepare('
            INSERT INTO pharmacies (
                id, email, pharmacy_name, contact_number, address, latitude, longitude, password_hash,
                open_time, close_time, operation_days, operating_hours, logo_path, business_permit_path,
                pharmacy_license_path, bir_certificate_path, document_paths, status, admin_note, status_history
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                pharmacy_name = VALUES(pharmacy_name),
                contact_number = VALUES(contact_number),
                address = VALUES(address),
                latitude = VALUES(latitude),
                longitude = VALUES(longitude),
                password_hash = VALUES(password_hash),
                open_time = VALUES(open_time),
                close_time = VALUES(close_time),
                operation_days = VALUES(operation_days),
                operating_hours = VALUES(operating_hours),
                logo_path = VALUES(logo_path),
                business_permit_path = VALUES(business_permit_path),
                pharmacy_license_path = VALUES(pharmacy_license_path),
                bir_certificate_path = VALUES(bir_certificate_path),
                document_paths = VALUES(document_paths),
                status = VALUES(status),
                admin_note = VALUES(admin_note),
                status_history = VALUES(status_history)
        ');
        $stmt->execute([
            $id,
            $email,
            $pharmacyName !== '' ? $pharmacyName : 'Pharmacy',
            $contact,
            $address,
            $latitude,
            $longitude,
            $passwordHash,
            $openTime,
            $closeTime,
            pharmacy_accounts_encode_json($operationDays),
            pharmacy_accounts_encode_json($operatingHours),
            $logoPath,
            $permitPath,
            $licensePath,
            $birPath,
            $documentPaths,
            $status,
            $adminNote,
            $statusHistory,
        ]);

        $userStmt = $pdo->prepare('SELECT email, role FROM users WHERE email = ? LIMIT 1');
        $userStmt->execute([$email]);
        $existingUser = $userStmt->fetch();

        if ($existingUser && ($existingUser['role'] ?? '') !== 'pharmacy') {
            return ['ok' => false, 'error' => 'This email already belongs to another account type in the database.'];
        }

        // Keep every registered pharmacy in users, including pending/rejected accounts.
        // Login still checks the pharmacy status before allowing access.
        if ($existingUser) {
            $updateUser = $pdo->prepare('
                UPDATE users
                SET full_name = ?, contact_number = ?, address = ?, latitude = ?, longitude = ?, password_hash = ?, role = ?
                WHERE email = ?
            ');
            $updateUser->execute([
                $pharmacyName !== '' ? $pharmacyName : 'Pharmacy',
                $contact,
                $address,
                $latitude ?? 0,
                $longitude ?? 0,
                $passwordHash,
                'pharmacy',
                $email,
            ]);
        } else {
            $insertUser = $pdo->prepare('
                INSERT INTO users (email, full_name, contact_number, address, latitude, longitude, password_hash, role)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insertUser->execute([
                $email,
                $pharmacyName !== '' ? $pharmacyName : 'Pharmacy',
                $contact,
                $address,
                $latitude ?? 0,
                $longitude ?? 0,
                $passwordHash,
                'pharmacy',
            ]);
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not save the pharmacy account to the database. Make sure MySQL is running.'];
    }

    return ['ok' => true];
}

function pharmacy_accounts_find_db_by_email(string $email): ?array
{
    require_once __DIR__ . '/database.php';

    $email = pharmacy_accounts_normalize_email($email);
    if ($email === '') {
        return null;
    }

    try {
        $stmt = caps_db()->prepare('SELECT * FROM pharmacies WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return pharmacy_accounts_hydrate_db_row($row);
    } catch (Throwable) {
        return null;
    }
}

function pharmacy_accounts_hydrate_db_row(array $row): array
{
    $days = json_decode((string) ($row['operation_days'] ?? ''), true);
    $hours = json_decode((string) ($row['operating_hours'] ?? ''), true);

    $row['operation_days'] = is_array($days) ? $days : [];
    $row['operating_hours'] = is_array($hours) ? $hours : [];
    $history = json_decode((string) ($row['status_history'] ?? ''), true);
    $row['status_history'] = is_array($history) ? $history : [];
    $storedPaths = json_decode((string) ($row['document_paths'] ?? ''), true);
    if (is_array($storedPaths)) {
        foreach (['business_permit', 'pharmacy_license', 'bir_certificate'] as $key) {
            $paths = $storedPaths[$key] ?? [];
            if (!is_array($paths)) {
                $paths = [];
            }
            $paths = array_values(array_filter(array_map('strval', $paths)));
            if ($paths !== []) {
                $row[$key . '_paths'] = $paths;
                $row[$key . '_path'] = $paths[0];
            }
        }
        $logoPaths = $storedPaths['logo'] ?? [];
        if (is_array($logoPaths) && isset($logoPaths[0]) && trim((string) $logoPaths[0]) !== '') {
            $row['logo_path'] = (string) $logoPaths[0];
        }
    }
    $row['latitude'] = isset($row['latitude']) && $row['latitude'] !== null && $row['latitude'] !== ''
        ? (float) $row['latitude']
        : null;
    $row['longitude'] = isset($row['longitude']) && $row['longitude'] !== null && $row['longitude'] !== ''
        ? (float) $row['longitude']
        : null;

    return $row;
}

function pharmacy_accounts_list_approved(): array
{
    require_once __DIR__ . '/database.php';

    try {
        $rows = caps_db()->query("
            SELECT *
            FROM pharmacies
            WHERE status IN ('approved', 'active')
            ORDER BY pharmacy_name ASC
        ")->fetchAll();

        $partners = [];
        foreach ($rows as $row) {
            $account = pharmacy_accounts_hydrate_db_row($row);
            if (pharmacy_accounts_is_approved($account) && trim((string) ($account['id'] ?? '')) !== '') {
                $partners[] = $account;
            }
        }

        return $partners;
    } catch (Throwable) {
        $partners = [];
        foreach (pharmacy_accounts_load_all() as $account) {
            if (!pharmacy_accounts_is_approved($account)) {
                continue;
            }
            $partners[] = $account;
        }

        return array_values($partners);
    }
}

function pharmacy_hydrate_session_from_account(array $account): void
{
    $_SESSION['portal'] = 'pharmacy';
    $_SESSION['user_email'] = $account['email'] ?? '';
    $_SESSION['user_name'] = $account['pharmacy_name'] ?? 'Pharmacy';
    $_SESSION['pharmacy_id'] = $account['id'] ?? '';
    $_SESSION['pharmacy_address'] = $account['address'] ?? '';
    $_SESSION['pharmacy_latitude'] = $account['latitude'] ?? null;
    $_SESSION['pharmacy_longitude'] = $account['longitude'] ?? null;
}

function pharmacy_accounts_sync_medvault(array $account): void
{
    $contextFile = dirname(__DIR__) . '/pharmacy/includes/pharmacy-context.php';
    if (!is_file($contextFile)) {
        return;
    }

    require_once dirname(__DIR__) . '/pharmacy/config.php';
    require_once dirname(__DIR__) . '/pharmacy/includes/database.php';
    require_once $contextFile;
    pharmacy_sync_settings_from_account($account);
}

function pharmacy_accounts_format_days(array $days): string
{
    $labels = pharmacy_accounts_operation_day_options();
    $parts = [];

    foreach ($days as $day) {
        if (isset($labels[$day])) {
            $parts[] = $labels[$day];
        }
    }

    return implode(', ', $parts);
}

function pharmacy_accounts_format_time(string $time): string
{
    $dt = DateTime::createFromFormat('H:i', $time);

    return $dt ? $dt->format('g:i A') : $time;
}

function pharmacy_accounts_update_profile(string $email, array $input, array $files): array
{
    $email = pharmacy_accounts_normalize_email($email);
    $accounts = pharmacy_accounts_load_all();

    if ($email === '' || !isset($accounts[$email])) {
        return ['ok' => false, 'error' => 'Pharmacy account not found.'];
    }

    $account = $accounts[$email];
    $accountId = (string) ($account['id'] ?? '');
    if ($accountId === '') {
        return ['ok' => false, 'error' => 'Pharmacy account not found.'];
    }

    $pharmacyName = trim($input['pharmacy_name'] ?? '');
    $contact = preg_replace('/\D+/', '', trim($input['contact_number'] ?? ''));
    $address = trim($input['address'] ?? '');
    $latitude = isset($input['latitude']) && $input['latitude'] !== '' ? (float) $input['latitude'] : null;
    $longitude = isset($input['longitude']) && $input['longitude'] !== '' ? (float) $input['longitude'] : null;
    $operationDays = $input['operation_days'] ?? [];
    $dayHoursInput = $input['day_hours'] ?? [];

    if (!is_array($operationDays)) {
        $operationDays = [];
    }
    if (!is_array($dayHoursInput)) {
        $dayHoursInput = [];
    }

    $operationDays = array_values(array_unique(array_filter(array_map(
        static fn($day) => strtolower(trim((string) $day)),
        $operationDays
    ))));

    $validDays = array_keys(pharmacy_accounts_operation_day_options());
    $operationDays = array_values(array_intersect($operationDays, $validDays));
    $schedule = pharmacy_accounts_collect_operating_hours($dayHoursInput, $operationDays);

    if ($pharmacyName === '') {
        return ['ok' => false, 'error' => 'Enter your pharmacy name.'];
    }

    if ($contact === '' || !preg_match('/^\d{11}$/', $contact)) {
        return ['ok' => false, 'error' => 'Contact number must be exactly 11 digits.'];
    }

    if (!$schedule['ok']) {
        return ['ok' => false, 'error' => $schedule['error']];
    }

    if ($address === '') {
        return ['ok' => false, 'error' => 'Enter your pharmacy address.'];
    }

    if ($latitude === null || $longitude === null) {
        return ['ok' => false, 'error' => 'Pin your pharmacy location on the map.'];
    }

    require_once __DIR__ . '/maps.php';

    if (!maps_point_in_service_area($latitude, $longitude)) {
        return ['ok' => false, 'error' => 'Pharmacy location must be within Laoag City, San Nicolas, or Batac City.'];
    }

    if (isset($files['logo']) && ($files['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $logoPath = pharmacy_accounts_store_upload($files['logo'], $accountId, 'logo', ['jpg', 'jpeg', 'png', 'webp', 'svg']);
        if ($logoPath === null) {
            return ['ok' => false, 'error' => 'Could not upload pharmacy logo. Use JPG, PNG, WEBP, or SVG.'];
        }
        $account['logo_path'] = $logoPath;
    }

    $optionalDocs = [
        'business_permit' => ['label' => 'Business permit', 'ext' => ['jpg', 'jpeg', 'png', 'pdf']],
        'pharmacy_license' => ['label' => 'Pharmacy license', 'ext' => ['jpg', 'jpeg', 'png', 'pdf']],
        'bir_certificate' => ['label' => 'BIR certificate', 'ext' => ['jpg', 'jpeg', 'png', 'pdf']],
    ];

    foreach ($optionalDocs as $key => $meta) {
        $uploaded = pharmacy_accounts_files_from_upload($files, $key);
        if ($uploaded === []) {
            continue;
        }

        $paths = pharmacy_accounts_document_paths($account, $key);
        foreach ($uploaded as $index => $file) {
            $basename = $paths === [] && $index === 0 ? $key : $key . '-' . (count($paths) + $index + 1);
            $path = pharmacy_accounts_store_upload($file, $accountId, $basename, $meta['ext']);
            if ($path === null) {
                return ['ok' => false, 'error' => 'Could not upload ' . $meta['label'] . '. Use JPG, PNG, or PDF.'];
            }
            $paths[] = $path;
        }

        $account[$key . '_path'] = $paths[0];
        $account[$key . '_paths'] = $paths;
    }

    $account['pharmacy_name'] = $pharmacyName;
    $account['contact_number'] = $contact;
    $account['open_time'] = $schedule['open_time'];
    $account['close_time'] = $schedule['close_time'];
    $account['operation_days'] = array_keys($schedule['hours']);
    $account['operating_hours'] = $schedule['hours'];
    $account['address'] = $address;
    $account['latitude'] = $latitude;
    $account['longitude'] = $longitude;
    $account['updated_at'] = date('c');

    $accounts[$email] = $account;

    if (!pharmacy_accounts_save_all($accounts)) {
        return ['ok' => false, 'error' => 'Could not save your pharmacy profile. Please try again.'];
    }

    if (pharmacy_accounts_is_approved($account)) {
        pharmacy_accounts_sync_database($account);
    }

    pharmacy_accounts_sync_medvault($account);

    pharmacy_hydrate_session_from_account($account);

    return ['ok' => true, 'account' => $account];
}
