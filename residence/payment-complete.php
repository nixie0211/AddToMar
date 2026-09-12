<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('residence');
require_once __DIR__ . '/includes/paymongo-complete.php';

$intentId = trim((string) ($_GET['payment_intent_id'] ?? $_GET['id'] ?? ''));
if ($intentId === '') {
    $intentId = trim((string) ($_SESSION['paymongo_pending']['intent_id'] ?? ''));
}
$home = app_url('residence/');
$final = $intentId !== ''
    ? residence_finalize_paymongo_payment($intentId)
    : ['ok' => false, 'status' => 'missing', 'error' => 'Payment session was not found.'];

$payload = [
    'type' => 'addtomar-paymongo-complete',
    'payment_intent_id' => $intentId,
    'ok' => !empty($final['ok']),
    'status' => (string) ($final['status'] ?? ''),
    'error' => (string) ($final['error'] ?? ''),
    'order' => $final['order'] ?? null,
    'orders' => $final['orders'] ?? null,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment complete</title>
</head>
<body>
<script>
(function () {
  var msg = <?= json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  try {
    if (window.opener && !window.opener.closed) {
      window.opener.postMessage(msg, '*');
      setTimeout(function () { window.close(); }, 200);
      return;
    }
  } catch (e) {}
  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage(msg, '*');
      return;
    }
  } catch (e) {}
  var url = <?= json_encode($home, JSON_UNESCAPED_SLASHES) ?>;
  location.replace(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'payment_intent_id=' + encodeURIComponent(msg.payment_intent_id || '') + '&modal=1');
})();
</script>
<p>Payment finished. You can close this window.</p>
</body>
</html>
