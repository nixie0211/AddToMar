<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');

header('Content-Type: application/json; charset=UTF-8');

http_response_code(403);
echo json_encode(['ok' => false, 'error' => 'Pharmacy documents cannot be changed from profile settings.']);
