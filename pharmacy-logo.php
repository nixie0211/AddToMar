<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pharmacy-accounts.php';

caps_bootstrap();

$pharmacyId = trim((string) ($_GET['id'] ?? ''));
if ($pharmacyId === '') {
    http_response_code(404);
    echo 'Logo not found.';
    exit;
}

$documentId = pharmacy_accounts_latest_logo_document_id($pharmacyId);
$document = pharmacy_accounts_get_document($documentId);
$content = $document['content'] ?? null;
if (is_resource($content)) {
    $content = stream_get_contents($content);
}

if (
    !is_array($document)
    || (string) ($document['doc_key'] ?? '') !== 'logo'
    || (string) ($document['pharmacy_id'] ?? '') !== $pharmacyId
    || !is_string($content)
    || $content === ''
) {
    http_response_code(404);
    echo 'Logo not found.';
    exit;
}

$filename = basename((string) ($document['filename'] ?? 'logo.png'));
$mime = trim((string) ($document['mime'] ?? ''));
if ($mime === '' || $mime === 'application/octet-stream') {
    $mime = pharmacy_accounts_mime_for_extension(pathinfo($filename, PATHINFO_EXTENSION));
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"');
header('Content-Length: ' . (string) strlen($content));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
echo $content;
exit;
