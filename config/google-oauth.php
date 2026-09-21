<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';

$localGoogleOAuth = __DIR__ . '/google-oauth.local.php';
if (is_file($localGoogleOAuth)) {
    require $localGoogleOAuth;
}

if (!defined('GOOGLE_OAUTH_CLIENT_ID')) {
    define('GOOGLE_OAUTH_CLIENT_ID', addtomar_env('ADDTOMAR_GOOGLE_CLIENT_ID'));
}
if (!defined('GOOGLE_OAUTH_CLIENT_SECRET')) {
    define('GOOGLE_OAUTH_CLIENT_SECRET', addtomar_env('ADDTOMAR_GOOGLE_CLIENT_SECRET'));
}
