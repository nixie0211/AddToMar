<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';

if (!defined('MAIL_SMTP_HOST')) {
    define('MAIL_SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('MAIL_SMTP_PORT')) {
    define('MAIL_SMTP_PORT', 587);
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'AddToMar Receipts');
}

$localMail = __DIR__ . '/mail.local.php';
if (is_file($localMail)) {
    require $localMail;
}

if (!defined('MAIL_SMTP_USER')) {
    define('MAIL_SMTP_USER', addtomar_env('ADDTOMAR_SMTP_USER'));
}
if (!defined('MAIL_SMTP_PASS')) {
    define('MAIL_SMTP_PASS', addtomar_env('ADDTOMAR_SMTP_PASS'));
}
