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

function addtomar_mysql_options(): array
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (addtomar_env('DB_SSL', '0') === '1') {
        $ca = addtomar_env('DB_SSL_CA', '/etc/ssl/certs/ca-certificates.crt');
        if (is_file($ca)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        }
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    return $options;
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
