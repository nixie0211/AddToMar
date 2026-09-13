<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/pharmacy-context.php';
require_once dirname(__DIR__) . '/includes/repository.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$medicineId = (int) ($_POST['medicine_id'] ?? 0);
$featured = (int) ($_POST['featured'] ?? 0) === 1 ? 1 : 0;

if ($medicineId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
    exit;
}

try {
    $existing = pharmacy_get_medicine_by_id($medicineId);
    if (!$existing || !pharmacy_medicine_owned($existing)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
        exit;
    }

    $stmt = pharmacy_db()->prepare('UPDATE medicines SET is_featured = ? WHERE id = ? AND ' . pharmacy_scope_sql());
    $stmt->execute([$featured, $medicineId]);

    echo json_encode([
        'success' => true,
        'medicine_id' => $medicineId,
        'featured' => $featured === 1,
    ]);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update featured status.']);
}
