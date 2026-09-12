<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = addtomar_is_https();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Manila');
}
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set(APP_TIMEZONE);
}

function app_timezone(): DateTimeZone
{
    return new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Manila');
}

function app_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', app_timezone());
}

function app_url(string $path = ''): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (preg_match('#/ajax/[^/]+$#', $script)) {
        $base = (string) preg_replace('#/ajax/[^/]+$#', '', $script);
    } elseif (preg_match('#/(pharmacy|residence|admin)(/|$)#', $script)) {
        $base = (string) preg_replace('#/(pharmacy|residence|admin)(/.*)?$#', '', $script);
    } else {
        $base = str_replace('\\', '/', dirname($script));
    }

    $base = rtrim($base, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base;
    }

    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

function login_url(): string
{
    return app_url('login.php');
}

function app_absolute_url(string $path = ''): string
{
    $configured = rtrim(addtomar_env('APP_URL'), '/');
    if ($configured !== '') {
        if ($path === '') {
            return $configured . (app_url('') === '/' ? '' : app_url(''));
        }
        return $configured . '/' . ltrim(app_url($path), '/');
    }

    $https = addtomar_is_https();
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (str_contains($host, ':')) {
        [$hostname, $port] = explode(':', $host, 2);
        if (($scheme === 'http' && $port === '80') || ($scheme === 'https' && $port === '443')) {
            $host = $hostname;
        }
    }

    return $scheme . '://' . $host . app_url($path);
}

function require_portal_auth(string $portal): void
{
    if (($_SESSION['portal'] ?? '') !== $portal) {
        header('Location: ' . login_url(), true, 302);
        exit;
    }
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
