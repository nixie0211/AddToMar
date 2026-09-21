<?php

declare(strict_types=1);

/**
 * Copy this file to env.production.php on InfinityFree (htdocs).
 * Do not commit env.production.php. Fill values from the InfinityFree
 * client area, Brevo, Google Cloud, and PayMongo.
 */
return [
    'DB_HOST' => 'sql.infinityfree.com',
    'DB_PORT' => '3306',
    'DB_NAME' => 'if0_00000000_caps',
    'DB_USER' => 'if0_00000000',
    'DB_PASS' => '',
    'DB_SSL' => '0',
    'APP_URL' => 'https://yourname.infinityfreeapp.com',
    'PUBLIC_APP_URL' => 'https://yourname.infinityfreeapp.com',
    'ADDTOMAR_SMTP_HOST' => 'smtp-relay.brevo.com',
    'ADDTOMAR_SMTP_PORT' => '587',
    'ADDTOMAR_SMTP_USER' => '',
    'ADDTOMAR_SMTP_PASS' => '',
    'ADDTOMAR_GOOGLE_CLIENT_ID' => '',
    'ADDTOMAR_GOOGLE_CLIENT_SECRET' => '',
    'PAYMONGO_PUBLIC_KEY' => '',
    'PAYMONGO_SECRET_KEY' => '',
];
