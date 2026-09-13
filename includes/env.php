<?php

declare(strict_types=1);

function addtomar_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $raw = $_ENV[$key] ?? $_SERVER[$key] ?? '';
        $value = is_string($raw) ? $raw : '';
    }

    $value = trim($value);
    return $value !== '' ? $value : $default;
}

function addtomar_is_https(): bool
{
    $forwarded = strtolower(addtomar_env('HTTP_X_FORWARDED_PROTO', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwarded === 'https') {
        return true;
    }

    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function addtomar_admin_email(): string
{
    return strtolower(trim(addtomar_env('ADMIN_EMAIL', 'addtomar@gmail.com')));
}

function addtomar_admin_bootstrap_password(): string
{
    $password = addtomar_env('ADMIN_BOOTSTRAP_PASSWORD', 'admin123');

    return $password !== '' ? $password : 'admin123';
}

function addtomar_mysql_can_create_database(): bool
{
    if (addtomar_env('ADDTOMAR_CREATE_DB', '') === '1') {
        return true;
    }

    $host = strtolower(addtomar_env('DB_HOST', 'localhost'));

    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
}

function addtomar_mysql_dsn(string $host, string $name, string $charset): string
{
    $port = addtomar_env('DB_PORT', '3306');
    return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
}

function addtomar_mysql_ssl_enabled(): bool
{
    $flag = strtolower(addtomar_env('DB_SSL', ''));
    if (in_array($flag, ['1', 'true', 'yes'], true)) {
        return true;
    }
    if (in_array($flag, ['0', 'false', 'no'], true)) {
        return false;
    }

    $host = strtolower(addtomar_env('DB_HOST', ''));

    return str_contains($host, 'aivencloud.com')
        || str_contains($host, '.aiven.')
        || str_contains($host, 'tidbcloud.com')
        || str_contains($host, '.tidb.');
}

function addtomar_mysql_options(): array
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 8,
    ];

    if (addtomar_mysql_ssl_enabled()) {
        $ca = addtomar_env('DB_SSL_CA', '/etc/ssl/certs/ca-certificates.crt');
        if (is_file($ca)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        }
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    return $options;
}

function addtomar_mysql_connect(string $host, string $name, string $user, string $pass, string $charset): PDO
{
    $options = addtomar_mysql_options();
    $dsn = addtomar_mysql_dsn($host, $name, $charset);
    $last = null;

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            addtomar_mysql_disable_ansi_quotes($pdo);

            return $pdo;
        } catch (PDOException $error) {
            $last = $error;
            $message = $error->getMessage();
            $previous = $error->getPrevious();
            if ($previous instanceof Throwable) {
                $message .= ' ' . $previous->getMessage();
            }
            $retryable = str_contains($message, 'getaddrinfo')
                || str_contains($message, '2002')
                || str_contains($message, 'timed out')
                || str_contains($message, 'Connection refused');
            if ($retryable && $attempt < 3) {
                usleep(400000 * $attempt);
                continue;
            }
            break;
        }
    }

    if ($last instanceof PDOException && addtomar_mysql_can_create_database()) {
        $fallback = new PDO(
            'mysql:host=' . $host . ';port=' . addtomar_env('DB_PORT', '3306') . ';charset=' . $charset,
            $user,
            $pass,
            $options
        );
        $fallback->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $fallback->exec('USE `' . $name . '`');
        addtomar_mysql_disable_ansi_quotes($fallback);

        return $fallback;
    }

    throw $last ?? new PDOException('Could not connect to the database.');
}

function addtomar_mysql_disable_ansi_quotes(PDO $pdo): void
{
    $mode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    $parts = array_values(array_filter(
        array_map('trim', explode(',', $mode)),
        static fn (string $part): bool => !in_array(strtoupper($part), ['ANSI_QUOTES', 'ANSI'], true) && $part !== ''
    ));
    $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote(implode(',', $parts)));
}
