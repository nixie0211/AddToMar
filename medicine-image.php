<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/pharmacy/config.php';
require_once __DIR__ . '/pharmacy/includes/database.php';
require_once __DIR__ . '/pharmacy/includes/repository.php';

caps_bootstrap();

$medicineId = (int) ($_GET['id'] ?? 0);
if ($medicineId > 0) {
    try {
        $stmt = pharmacy_db()->prepare('SELECT image_path FROM medicines WHERE id = ? LIMIT 1');
        $stmt->execute([$medicineId]);
        $storedPath = trim((string) $stmt->fetchColumn());
        if (preg_match('#^https?://#i', $storedPath)) {
            header('Location: ' . $storedPath, true, 302);
            exit;
        }
    } catch (Throwable) {
    }
}

$image = pharmacy_medicine_get_image($medicineId);
$content = $image['content'] ?? null;
if (is_resource($content)) {
    $content = stream_get_contents($content);
}

if ($medicineId <= 0 || !is_array($image) || !is_string($content) || $content === '') {
    http_response_code(404);
    echo 'Image not found.';
    exit;
}

$filename = basename((string) ($image['filename'] ?? 'medicine.jpg'));
$mime = trim((string) ($image['mime'] ?? ''));
if ($mime === '' || $mime === 'application/octet-stream') {
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'image/jpeg',
    };
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"');
header('Content-Length: ' . (string) strlen($content));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
echo $content;
exit;
