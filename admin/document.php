<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin.php';

require_portal_auth('admin');
caps_bootstrap();

$documentId = (int) ($_GET['id'] ?? 0);
$document = pharmacy_accounts_get_document($documentId);
$content = $document['content'] ?? null;
if (is_resource($content)) {
    $content = stream_get_contents($content);
}

if (!is_array($document) || !is_string($content) || $content === '') {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$filename = basename((string) ($document['filename'] ?? 'document'));
$mime = trim((string) ($document['mime'] ?? ''));
if ($mime === '') {
    $mime = pharmacy_accounts_mime_for_extension(pathinfo($filename, PATHINFO_EXTENSION));
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"');
header('Content-Length: ' . (string) strlen($content));
header('X-Content-Type-Options: nosniff');
echo $content;
exit;
