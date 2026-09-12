<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('residence');
require_once dirname(__DIR__) . '/residence/includes/paymongo-complete.php';

header('Content-Type: application/json; charset=UTF-8');

$intentId = trim((string) ($_GET['payment_intent_id'] ?? $_POST['payment_intent_id'] ?? ''));
$result = residence_finalize_paymongo_payment($intentId !== '' ? $intentId : null);
unset($result['receipt_html']);
http_response_code(200);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
