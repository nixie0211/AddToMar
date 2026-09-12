<?php

declare(strict_types=1);

require_once __DIR__ . '/maps.php';
require_once __DIR__ . '/database.php';

function customers_find_by_email(string $email): ?array
{
    $email = customers_normalize_email($email);
    if ($email === '') {
        return null;
    }

    try {
        $stmt = caps_db()->prepare(
            'SELECT email, full_name, contact_number, address, latitude, longitude, password_hash, role, google_id, password_set, created_at, updated_at
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['latitude'] = (float) $row['latitude'];
        $row['longitude'] = (float) $row['longitude'];

        return $row;
    } catch (Throwable) {
        return null;
    }
}

function customers_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function customers_register(array $input): array
{
    $email = customers_normalize_email($input['email'] ?? '');
    $fullName = trim($input['full_name'] ?? '');
    $contact = preg_replace('/\D+/', '', trim($input['contact_number'] ?? ''));
    $address = trim($input['address'] ?? '');
    $latitude = isset($input['latitude']) && $input['latitude'] !== '' ? (float) $input['latitude'] : null;
    $longitude = isset($input['longitude']) && $input['longitude'] !== '' ? (float) $input['longitude'] : null;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.'];
    }

    if ($fullName === '' || $contact === '' || $address === '') {
        return ['ok' => false, 'error' => 'Please complete your full name, contact number, and address.'];
    }

    if (!preg_match('/^\d{11}$/', $contact)) {
        return ['ok' => false, 'error' => 'Contact number must be exactly 11 digits.'];
    }

    if ($latitude === null || $longitude === null) {
        return ['ok' => false, 'error' => 'Please pin your pickup location on the map.'];
    }

    if (!maps_point_in_service_area($latitude, $longitude)) {
        return ['ok' => false, 'error' => 'Your pickup location must be within Laoag City, San Nicolas, or Batac City.'];
    }

    $password = (string) ($input['password'] ?? '');
    $passwordConfirm = (string) ($input['password_confirm'] ?? '');

    if ($password === '' || $passwordConfirm === '') {
        return ['ok' => false, 'error' => 'Please enter and confirm your password.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }

    if ($password !== $passwordConfirm) {
        return ['ok' => false, 'error' => 'Passwords do not match.'];
    }

    if (customers_find_by_email($email)) {
        return ['ok' => false, 'error' => 'An account with this email already exists. Sign in instead.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        return ['ok' => false, 'error' => 'Could not secure your password. Please try again.'];
    }

    try {
        $stmt = caps_db()->prepare(
            'INSERT INTO users (email, full_name, contact_number, address, latitude, longitude, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $email,
            $fullName,
            $contact,
            $address,
            $latitude,
            $longitude,
            $passwordHash,
            'customer',
        ]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'error' => 'An account with this email already exists. Sign in instead.'];
        }

        return ['ok' => false, 'error' => 'Could not save your account. Please make sure MySQL is running and try again.'];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Could not save your account. Please make sure MySQL is running and try again.'];
    }

    $customer = customers_find_by_email($email);
    if (!$customer) {
        return ['ok' => false, 'error' => 'Account was created but could not be loaded. Please sign in.'];
    }

    customer_addresses_seed_from_user($email);

    return ['ok' => true, 'customer' => $customer];
}

function customers_register_from_google(string $email, string $fullName, string $googleId): array
{
    $email = customers_normalize_email($email);
    $fullName = trim($fullName);
    $googleId = trim($googleId);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Google did not return a valid email address.'];
    }

    if ($fullName === '') {
        $fullName = 'Customer';
    }

    $existing = customers_find_by_email($email);
    if ($existing) {
        if ($googleId !== '') {
            try {
                $stmt = caps_db()->prepare('UPDATE users SET google_id = ? WHERE email = ? AND (google_id IS NULL OR google_id = "")');
                $stmt->execute([$googleId, $email]);
            } catch (Throwable) {
            }
        }

        return ['ok' => true, 'customer' => $existing];
    }

    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        return ['ok' => false, 'error' => 'Could not create your Google account. Please try again.'];
    }

    try {
        $stmt = caps_db()->prepare(
            'INSERT INTO users (email, full_name, contact_number, address, latitude, longitude, password_hash, role, google_id, password_set)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([
            $email,
            $fullName,
            '',
            '',
            MAP_DEFAULT_LAT,
            MAP_DEFAULT_LNG,
            $passwordHash,
            'customer',
            $googleId !== '' ? $googleId : null,
        ]);
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            $customer = customers_find_by_email($email);
            if ($customer) {
                return ['ok' => true, 'customer' => $customer];
            }
        }

        return ['ok' => false, 'error' => 'Could not save your Google account. Please try again.'];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Could not save your Google account. Please try again.'];
    }

    $customer = customers_find_by_email($email);
    if (!$customer) {
        return ['ok' => false, 'error' => 'Google account was created but could not be loaded. Please sign in.'];
    }

    customer_addresses_seed_from_user($email);

    return ['ok' => true, 'customer' => $customer];
}

function customers_set_login_password(string $email, string $password, string $passwordConfirm): array
{
    $email = customers_normalize_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Your account email is missing.'];
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

    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($hash === false) {
        return ['ok' => false, 'error' => 'Could not save your password. Please try again.'];
    }

    try {
        $stmt = caps_db()->prepare('UPDATE users SET password_hash = ?, password_set = 1 WHERE email = ?');
        $stmt->execute([$hash, $email]);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Could not save your password. Please try again.'];
    }

    $customer = customers_find_by_email($email);
    if (!$customer) {
        return ['ok' => false, 'error' => 'Account was saved but could not be loaded. Please sign in.'];
    }

    return ['ok' => true, 'customer' => $customer];
}

function customers_update_credentials(string $currentEmail, array $input): array
{
    $currentEmail = customers_normalize_email($currentEmail);
    $newEmail = customers_normalize_email((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $passwordConfirm = (string) ($input['password_confirm'] ?? '');

    if ($currentEmail === '') {
        return ['ok' => false, 'error' => 'Your account email is missing.'];
    }

    if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.'];
    }

    if ($password === '' && $passwordConfirm === '') {
        if ($newEmail === $currentEmail) {
            return ['ok' => true, 'customer' => customers_find_by_email($currentEmail)];
        }
    } else {
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        if ($password !== $passwordConfirm) {
            return ['ok' => false, 'error' => 'Passwords do not match.'];
        }
    }

    if ($newEmail !== $currentEmail && customers_find_by_email($newEmail)) {
        return ['ok' => false, 'error' => 'That email is already in use.'];
    }

    try {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hash === false) {
                return ['ok' => false, 'error' => 'Could not update your password. Please try again.'];
            }

            $stmt = caps_db()->prepare('UPDATE users SET email = ?, password_hash = ? WHERE email = ?');
            $stmt->execute([$newEmail, $hash, $currentEmail]);
        } else {
            $stmt = caps_db()->prepare('UPDATE users SET email = ? WHERE email = ?');
            $stmt->execute([$newEmail, $currentEmail]);
        }

        if ($newEmail !== $currentEmail) {
            $addrStmt = caps_db()->prepare('UPDATE user_addresses SET user_email = ? WHERE user_email = ?');
            $addrStmt->execute([$newEmail, $currentEmail]);
        }
    } catch (PDOException $e) {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return ['ok' => false, 'error' => 'That email is already in use.'];
        }

        return ['ok' => false, 'error' => 'Could not save your profile. Please try again.'];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Could not save your profile. Please try again.'];
    }

    $updated = customers_find_by_email($newEmail);
    return ['ok' => true, 'customer' => $updated];
}

function customers_update_location(string $email, string $address, float $latitude, float $longitude): array
{
    $email = customers_normalize_email($email);
    $address = trim($address);

    if ($email === '') {
        return ['ok' => false, 'error' => 'You need to be signed in.'];
    }

    if ($address === '') {
        return ['ok' => false, 'error' => 'Pin a location on the map first.'];
    }

    if (!maps_point_in_service_area($latitude, $longitude)) {
        return ['ok' => false, 'error' => 'Choose a location in Laoag City, San Nicolas, or Batac City.'];
    }

    try {
        $stmt = caps_db()->prepare(
            'UPDATE users SET address = ?, latitude = ?, longitude = ? WHERE email = ?'
        );
        $stmt->execute([$address, $latitude, $longitude, $email]);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Could not save your address. Please try again.'];
    }

    $updated = customers_find_by_email($email);
    if (!$updated) {
        return ['ok' => false, 'error' => 'Could not load your updated address.'];
    }

    return ['ok' => true, 'customer' => $updated];
}

function customer_addresses_format_row(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'label' => (string) ($row['label'] ?? 'Address'),
        'address' => (string) ($row['address'] ?? ''),
        'latitude' => isset($row['latitude']) && $row['latitude'] !== '' ? (float) $row['latitude'] : null,
        'longitude' => isset($row['longitude']) && $row['longitude'] !== '' ? (float) $row['longitude'] : null,
        'is_current' => !empty($row['is_current']),
    ];
}

function customer_addresses_count(string $email): int
{
    $stmt = caps_db()->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_email = ?');
    $stmt->execute([$email]);

    return (int) $stmt->fetchColumn();
}

function customer_addresses_seed_from_user(string $email): void
{
    $email = customers_normalize_email($email);
    if ($email === '') {
        return;
    }

    try {
        if (customer_addresses_count($email) > 0) {
            return;
        }

        $customer = customers_find_by_email($email);
        if (!$customer) {
            return;
        }

        $address = trim((string) ($customer['address'] ?? ''));
        $lat = $customer['latitude'] ?? null;
        $lng = $customer['longitude'] ?? null;
        if ($address === '' || $lat === null || $lng === null) {
            return;
        }

        $stmt = caps_db()->prepare(
            'INSERT INTO user_addresses (user_email, label, address, latitude, longitude, is_current)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$email, 'Current address', $address, $lat, $lng]);
    } catch (Throwable) {
        // Listing can stay empty if the table is not ready yet.
    }
}

function customer_addresses_list(string $email): array
{
    $email = customers_normalize_email($email);
    if ($email === '') {
        return [];
    }

    customer_addresses_seed_from_user($email);

    try {
        $stmt = caps_db()->prepare(
            'SELECT id, label, address, latitude, longitude, is_current
             FROM user_addresses
             WHERE user_email = ?
             ORDER BY is_current DESC, id ASC'
        );
        $stmt->execute([$email]);
        $rows = $stmt->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }

    return array_map('customer_addresses_format_row', $rows);
}

function customer_addresses_find(string $email, int $id): ?array
{
    $stmt = caps_db()->prepare(
        'SELECT id, label, address, latitude, longitude, is_current
         FROM user_addresses
         WHERE user_email = ? AND id = ?
         LIMIT 1'
    );
    $stmt->execute([$email, $id]);
    $row = $stmt->fetch();

    return $row ? customer_addresses_format_row($row) : null;
}

function customer_addresses_mark_current(string $email, int $id): void
{
    $clear = caps_db()->prepare('UPDATE user_addresses SET is_current = 0 WHERE user_email = ?');
    $clear->execute([$email]);
    $set = caps_db()->prepare('UPDATE user_addresses SET is_current = 1, label = ? WHERE user_email = ? AND id = ?');
    $set->execute(['Current address', $email, $id]);
}

function customers_save_address(string $email, array $input): array
{
    $email = customers_normalize_email($email);
    $address = trim((string) ($input['address'] ?? ''));
    $latRaw = $input['latitude'] ?? '';
    $lngRaw = $input['longitude'] ?? '';
    $id = (int) ($input['id'] ?? 0);
    $makeCurrent = !empty($input['make_current']);

    if ($email === '') {
        return ['ok' => false, 'error' => 'You need to be signed in.'];
    }

    if ($address === '' || $latRaw === '' || $lngRaw === '') {
        return ['ok' => false, 'error' => 'Pin a location on the map first.'];
    }

    $latitude = (float) $latRaw;
    $longitude = (float) $lngRaw;

    if (!maps_point_in_service_area($latitude, $longitude)) {
        return ['ok' => false, 'error' => 'Choose a location in Laoag City, San Nicolas, or Batac City.'];
    }

    try {
        $existingCount = customer_addresses_count($email);
        if ($existingCount === 0) {
            $makeCurrent = true;
        }

        if ($id > 0) {
            $existing = customer_addresses_find($email, $id);
            if (!$existing) {
                return ['ok' => false, 'error' => 'That saved address was not found.'];
            }

            $stmt = caps_db()->prepare(
                'UPDATE user_addresses SET address = ?, latitude = ?, longitude = ? WHERE user_email = ? AND id = ?'
            );
            $stmt->execute([$address, $latitude, $longitude, $email, $id]);
            if ($existing['is_current']) {
                $makeCurrent = true;
            }
        } else {
            $label = $makeCurrent ? 'Current address' : ('Address ' . ($existingCount + 1));
            $stmt = caps_db()->prepare(
                'INSERT INTO user_addresses (user_email, label, address, latitude, longitude, is_current)
                 VALUES (?, ?, ?, ?, ?, 0)'
            );
            $stmt->execute([$email, $label, $address, $latitude, $longitude]);
            $id = (int) caps_db()->lastInsertId();
        }

        if ($makeCurrent) {
            customer_addresses_mark_current($email, $id);
            $updated = customers_update_location($email, $address, $latitude, $longitude);
            if (!($updated['ok'] ?? false)) {
                return $updated;
            }
            $customer = $updated['customer'];
        } else {
            $customer = customers_find_by_email($email);
        }
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Could not save your address. Please try again.'];
    }

    if (!$customer) {
        return ['ok' => false, 'error' => 'Could not load your updated address.'];
    }

    return [
        'ok' => true,
        'customer' => $customer,
        'addresses' => customer_addresses_list($email),
        'make_current' => $makeCurrent,
    ];
}

function customers_set_current_address(string $email, int $id): array
{
    $email = customers_normalize_email($email);
    if ($email === '' || $id < 1) {
        return ['ok' => false, 'error' => 'Choose an address to use.'];
    }

    $existing = customer_addresses_find($email, $id);
    if (!$existing) {
        return ['ok' => false, 'error' => 'That saved address was not found.'];
    }

    try {
        customer_addresses_mark_current($email, $id);
        $updated = customers_update_location(
            $email,
            $existing['address'],
            (float) $existing['latitude'],
            (float) $existing['longitude']
        );
        if (!($updated['ok'] ?? false)) {
            return $updated;
        }
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Could not update your current address.'];
    }

    return [
        'ok' => true,
        'customer' => $updated['customer'],
        'addresses' => customer_addresses_list($email),
        'make_current' => true,
    ];
}

function customers_verify_password(array $customer, string $password): bool
{
    $hash = $customer['password_hash'] ?? '';

    if ($hash === '') {
        return $password !== '';
    }

    return password_verify($password, $hash);
}

function residence_hydrate_session_from_customer(array $customer): void
{
    $_SESSION['user_email'] = $customer['email'] ?? '';
    $_SESSION['user_name'] = $customer['full_name'] ?? 'Customer';
    $_SESSION['user_contact'] = $customer['contact_number'] ?? '';
    $_SESSION['user_address'] = $customer['address'] ?? '';
    $_SESSION['user_latitude'] = $customer['latitude'] ?? null;
    $_SESSION['user_longitude'] = $customer['longitude'] ?? null;
}

function residence_session_profile(): array
{
    $lat = $_SESSION['user_latitude'] ?? null;
    $lng = $_SESSION['user_longitude'] ?? null;

    return [
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'full_name' => (string) ($_SESSION['user_name'] ?? ''),
        'contact_number' => (string) ($_SESSION['user_contact'] ?? ''),
        'address' => (string) ($_SESSION['user_address'] ?? ''),
        'latitude' => $lat !== null && $lat !== '' ? (float) $lat : null,
        'longitude' => $lng !== null && $lng !== '' ? (float) $lng : null,
    ];
}

function residence_user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'Rx';
}

function residence_format_contact(string $contact): string
{
    $digits = preg_replace('/\D+/', '', $contact);

    if (strlen($digits) === 11) {
        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
    }

    return $contact;
}
