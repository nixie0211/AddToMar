<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/pharmacy-accounts.php';

require_portal_auth('pharmacy');
caps_bootstrap();

$documentId = (int) ($_GET['id'] ?? 0);
$document = pharmacy_accounts_get_document($documentId);
$content = $document['content'] ?? null;
if (is_resource($content)) {
    $content = stream_get_contents($content);
}

$account = pharmacy_accounts_find_by_email((string) ($_SESSION['user_email'] ?? ''));
$pharmacyId = trim((string) ($account['id'] ?? ''));

if (
    !is_array($document)
    || $pharmacyId === ''
    || (string) ($document['pharmacy_id'] ?? '') !== $pharmacyId
    || !is_string($content)
    || $content === ''
) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$filename = basename((string) ($document['filename'] ?? 'document'));
$mime = trim((string) ($document['mime'] ?? ''));
if ($mime === '' || $mime === 'application/octet-stream') {
    $mime = pharmacy_accounts_mime_for_extension(pathinfo($filename, PATHINFO_EXTENSION));
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"');
header('Content-Length: ' . (string) strlen($content));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
echo $content;
exit;
