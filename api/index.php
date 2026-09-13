<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri = is_string($uri) && $uri !== '' ? $uri : '/';
$uri = '/' . ltrim($uri, '/');
$uri = preg_replace('#/+#', '/', $uri) ?? $uri;

$aliases = [
    '/' => '/index.php',
    '/login' => '/login.php',
    '/register' => '/register.php',
    '/logout' => '/logout.php',
    '/pharmacy' => '/pharmacy/index.php',
    '/pharmacy/' => '/pharmacy/index.php',
    '/residence' => '/residence/index.php',
    '/residence/' => '/residence/index.php',
    '/admin' => '/admin/index.php',
    '/admin/' => '/admin/index.php',
];
$uri = $aliases[$uri] ?? $uri;

if (str_ends_with($uri, '/') && $uri !== '/') {
    $uri .= 'index.php';
}

$relative = ltrim($uri, '/');
$candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
$realRoot = realpath($root);
$realPath = realpath($candidate);

if ($realRoot === false) {
    http_response_code(500);
    echo 'App root is missing.';
    exit;
}

$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['DOCUMENT_ROOT'] = $realRoot;
if (empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
}
if (empty($_SERVER['HTTP_X_FORWARDED_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_X_FORWARDED_HOST'] = (string) $_SERVER['HTTP_HOST'];
}

$rootPrefix = $realRoot . DIRECTORY_SEPARATOR;
if ($realPath === false || ($realPath !== $realRoot && !str_starts_with($realPath, $rootPrefix))) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$publicPath = str_replace('\\', '/', substr($realPath, strlen($realRoot)));
$publicPath = '/' . ltrim($publicPath, '/');

$blocked = [
    '#^/api/index\.php$#',
    '#^/config/#',
    '#^/includes/#',
    '#^/docker/#',
    '#^/AddToMar1/#',
    '#^/admin/config\.php$#',
    '#^/admin/partials/#',
    '#^/pharmacy/config\.php$#',
    '#^/pharmacy/bootstrap\.php$#',
    '#^/pharmacy/includes/#',
    '#^/pharmacy/partials/#',
    '#^/pharmacy/views/#',
    '#^/residence/config\.php$#',
    '#^/residence/includes/#',
    '#^/residence/partials/#',
    '#^/residence/views/#',
    '#/\.local\.php$#',
];
foreach ($blocked as $pattern) {
    if (preg_match($pattern, $publicPath) === 1) {
        http_response_code(404);
        echo 'Not found.';
        exit;
    }
}

$extension = strtolower((string) pathinfo($realPath, PATHINFO_EXTENSION));
$staticTypes = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'mjs' => 'application/javascript; charset=utf-8',
    'map' => 'application/json; charset=utf-8',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'txt' => 'text/plain; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
];

if (isset($staticTypes[$extension])) {
    header('Content-Type: ' . $staticTypes[$extension]);
    header('Cache-Control: public, max-age=300');
    readfile($realPath);
    exit;
}

$allowedPhp = $publicPath === '/index.php'
    || preg_match('#^/(login|logout|register|health|google-login|google-callback|pharmacy-logo|pharmacy-dashboard)\.php$#', $publicPath) === 1
    || preg_match('#^/ajax/[^/]+\.php$#', $publicPath) === 1
    || preg_match('#^/admin/(index|document)\.php$#', $publicPath) === 1
    || preg_match('#^/pharmacy/(index|api/[^/]+|js/[^/]+)\.php$#', $publicPath) === 1
    || preg_match('#^/residence/(index|payment-complete|payment-return|js/[^/]+)\.php$#', $publicPath) === 1;

if ($extension !== 'php' || !$allowedPhp) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$_SERVER['SCRIPT_FILENAME'] = $realPath;
$_SERVER['SCRIPT_NAME'] = $publicPath;
$_SERVER['PHP_SELF'] = $publicPath;
chdir(dirname($realPath));
require $realPath;
