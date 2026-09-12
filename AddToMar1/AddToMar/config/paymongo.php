<?php
/**
 * PayMongo API keys. Set PAYMONGO_PUBLIC_KEY and PAYMONGO_SECRET_KEY
 * in the host environment, or in paymongo.local.php (not committed).
 */
$localPaymongo = __DIR__ . '/paymongo.local.php';
if (is_file($localPaymongo)) {
    require $localPaymongo;
}

if (!defined('PAYMONGO_PUBLIC_KEY')) {
    define('PAYMONGO_PUBLIC_KEY', (string) (getenv('PAYMONGO_PUBLIC_KEY') ?: ''));
}
if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY', (string) (getenv('PAYMONGO_SECRET_KEY') ?: ''));
}
