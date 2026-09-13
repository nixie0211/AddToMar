<?php

declare(strict_types=1);

function addtomar_runtime_env(): array
{
    static $loaded = null;
    if (is_array($loaded)) {
        return $loaded;
    }

    $file = '/tmp/addtomar-runtime-env.php';
    if (is_file($file)) {
        $data = require $file;
        $loaded = is_array($data) ? $data : [];
        return $loaded;
    }

    $loaded = [];
    return $loaded;
}

function addtomar_env(string $key, string $default = ''): string
{
    $runtime = addtomar_runtime_env();
    if (isset($runtime[$key]) && is_string($runtime[$key]) && trim($runtime[$key]) !== '') {
        return trim($runtime[$key]);
    }

    $value = getenv($key);
    if ($value === false || $value === '') {
        $apache = function_exists('apache_getenv') ? apache_getenv($key, true) : false;
        $raw = ($apache !== false && $apache !== '')
            ? $apache
            : ($_ENV[$key] ?? $_SERVER[$key] ?? '');
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

function addtomar_header_host(string $key): string
{
    $fromServer = $_SERVER[$key] ?? '';
    $fromEnv = getenv($key);
    $raw = is_string($fromServer) && $fromServer !== ''
        ? $fromServer
        : (is_string($fromEnv) ? $fromEnv : '');
    $raw = trim(explode(',', $raw)[0]);
    $raw = strtolower((string) preg_replace('/:\d+$/', '', $raw));

    return $raw;
}

function addtomar_public_origin(): string
{
    $forced = rtrim(addtomar_env('PUBLIC_APP_URL'), '/');
    if ($forced !== '') {
        return $forced;
    }

    $forwarded = addtomar_header_host('HTTP_X_FORWARDED_HOST');
    if (str_ends_with($forwarded, '.vercel.app')) {
        return 'https://' . $forwarded;
    }

    $configured = rtrim(addtomar_env('APP_URL'), '/');
    if ($configured !== '') {
        return $configured;
    }

    $host = addtomar_header_host('HTTP_HOST');
    if ($host === '') {
        return '';
    }

    return (addtomar_is_https() ? 'https://' : 'http://') . $host;
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

function addtomar_mysql_is_tidb(): bool
{
    $host = strtolower(addtomar_env('DB_HOST', ''));

    return str_contains($host, 'tidbcloud.com') || str_contains($host, '.tidb.');
}

function addtomar_mysql_can_create_database(): bool
{
    if (addtomar_env('ADDTOMAR_CREATE_DB', '') === '1') {
        return true;
    }

    if (addtomar_mysql_is_tidb()) {
        return true;
    }

    $host = strtolower(addtomar_env('DB_HOST', 'localhost'));

    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1';
}

function addtomar_mysql_default_port(): string
{
    $port = addtomar_env('DB_PORT', '');
    if ($port !== '') {
        return $port;
    }

    return addtomar_mysql_is_tidb() ? '4000' : '3306';
}

function addtomar_mysql_dsn(string $host, string $name, string $charset, bool $includeDatabase = true): string
{
    $port = addtomar_mysql_default_port();
    if ($includeDatabase && $name !== '') {
        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
    }

    return sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);
}

function addtomar_mysql_ssl_ca_path(): string
{
    $configured = addtomar_env('DB_SSL_CA', '');
    $candidates = array_values(array_filter([
        $configured,
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/ssl/cert.pem',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ]));

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
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
        $ca = addtomar_mysql_ssl_ca_path();
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca !== '' ? $ca : '/etc/ssl/certs/ca-certificates.crt';
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    return $options;
}

function addtomar_mysql_open(string $host, string $user, string $pass, string $charset, array $options, string $name = ''): PDO
{
    $pdo = new PDO(addtomar_mysql_dsn($host, $name, $charset, $name !== ''), $user, $pass, $options);
    addtomar_mysql_disable_ansi_quotes($pdo);

    return $pdo;
}

function addtomar_mysql_connect(string $host, string $name, string $user, string $pass, string $charset): PDO
{
    $options = addtomar_mysql_options();
    $last = null;
    $names = [$name];
    if (addtomar_mysql_is_tidb()) {
        array_push($names, 'test', '');
        $names = array_values(array_unique($names));
    }

    foreach ($names as $tryName) {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $pdo = addtomar_mysql_open($host, $user, $pass, $charset, $options, $tryName);
                $safeName = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?: 'CAPS';
                if ($safeName !== '' && $tryName !== $safeName) {
                    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $safeName . '`');
                    $pdo->exec('USE `' . $safeName . '`');
                }

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
    }

    throw $last ?? new PDOException('Could not connect to the database.');
}

function addtomar_mysql_disable_ansi_quotes(PDO $pdo): void
{
    try {
        $mode = (string) $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $parts = array_values(array_filter(
            array_map('trim', explode(',', $mode)),
            static fn (string $part): bool => !in_array(strtoupper($part), ['ANSI_QUOTES', 'ANSI'], true) && $part !== ''
        ));
        $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote(implode(',', $parts)));
    } catch (Throwable) {
        // TiDB and some managed MySQL hosts ignore sql_mode tweaks.
    }
}
