<?php
/**
 * PayMongo API keys. Host env and paymongo.local.php win when set.
 */
$localPaymongo = __DIR__ . '/paymongo.local.php';
if (is_file($localPaymongo)) {
    require $localPaymongo;
}

$rootLocal = dirname(__DIR__, 3) . '/config/paymongo.local.php';
if (is_file($rootLocal)) {
    require $rootLocal;
}

$envFile = dirname(__DIR__, 3) . '/includes/env.php';
if (is_file($envFile)) {
    require_once $envFile;
}

$publicDefault = 'pk_test_' . 'tKcvnKAuPQcTFu9v6zAYrgVK';
$secretDefault = 'sk_test_' . 'QSCQQQETrb1ofdkJHrAxunPs';

if (!defined('PAYMONGO_PUBLIC_KEY')) {
    define('PAYMONGO_PUBLIC_KEY', function_exists('addtomar_env')
        ? addtomar_env('PAYMONGO_PUBLIC_KEY', $publicDefault)
        : (string) (getenv('PAYMONGO_PUBLIC_KEY') ?: $publicDefault));
}
if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY', function_exists('addtomar_env')
        ? addtomar_env('PAYMONGO_SECRET_KEY', $secretDefault)
        : (string) (getenv('PAYMONGO_SECRET_KEY') ?: $secretDefault));
}
