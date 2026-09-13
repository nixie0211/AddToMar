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

$name = trim((string) ($_POST['name'] ?? ''));
if ($name === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a medicine name.']);
    exit;
}

$medicineId = (int) ($_POST['medicine_id'] ?? 0);
$genericName = trim((string) ($_POST['generic_name'] ?? ''));
$brand = trim((string) ($_POST['brand'] ?? ''));
$dosageForm = trim((string) ($_POST['dosage_form'] ?? ''));
$strength = trim((string) ($_POST['strength'] ?? ''));
$unit = trim((string) ($_POST['unit'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));
$categoryOther = trim((string) ($_POST['category_other'] ?? ''));
if (strcasecmp($category, 'Other') === 0) {
    $category = $categoryOther;
}
if ($category === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select or enter a medicine category.']);
    exit;
}
$description = trim((string) ($_POST['description'] ?? ''));
$ingredients = trim((string) ($_POST['ingredients'] ?? ''));
$batchNumber = trim((string) ($_POST['batch_number'] ?? ''));
$stockQuantity = max(0, (int) ($_POST['stock_quantity'] ?? 0));
$unitPrice = max(0, (float) ($_POST['unit_price'] ?? 0));
$sellingPrice = max(0, (float) ($_POST['selling_price'] ?? 0));

$expiration = trim((string) ($_POST['expiration_date'] ?? ''));
if ($expiration === '') {
    $expiration = null;
} elseif (!preg_match('/^(?:\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4}|\d{1,2}\/(?:mm|MM)\/\d{4})$/', $expiration)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter expiration as mm/dd/yyyy or mm/mm/yyyy.']);
    exit;
}

$dosageSummary = trim(implode(' ', array_filter([$strength, $dosageForm], static fn(string $part): bool => $part !== '')));

$imagePath = null;
$imageUpload = null;
$file = $_FILES['image'] ?? null;
if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Could not upload the image.']);
        exit;
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $mime = (string) mime_content_type($tmpName);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Only PNG, JPG, and WEBP images are allowed.']);
        exit;
    }
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Image must be 5 MB or smaller.']);
        exit;
    }

    $bytes = file_get_contents($tmpName);
    if ($bytes === false || $bytes === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Could not read the image.']);
        exit;
    }

    $imageUpload = [
        'filename' => bin2hex(random_bytes(8)) . '.' . $allowed[$mime],
        'mime' => $mime,
        'content' => $bytes,
    ];
}

try {
    $pdo = pharmacy_db();
    $pharmacyId = pharmacy_current_id();
    if ($pharmacyId === '') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Pharmacy session not found.']);
        exit;
    }

    if ($medicineId > 0) {
        $existing = pharmacy_get_medicine_by_id($medicineId);
        if (!$existing || !pharmacy_medicine_owned($existing)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
            exit;
        }

        $finalImagePath = $existing['image_path'] ?? null;
        if ($imageUpload !== null) {
            pharmacy_medicine_store_image($medicineId, $imageUpload['filename'], $imageUpload['mime'], $imageUpload['content']);
            $finalImagePath = 'medicine-image.php?id=' . $medicineId;
        }

        $stmt = $pdo->prepare('
            UPDATE medicines SET
                pharmacy_id = COALESCE(NULLIF(pharmacy_id, ""), ?),
                name = ?, generic_name = ?, brand = ?, dosage_form = ?, strength = ?, unit = ?, category = ?, dosage = ?,
                description = ?, ingredients = ?, batch_number = ?, expiration_date = ?, stock_quantity = ?,
                unit_price = ?, selling_price = ?, prescription_required = ?, is_active = ?, image_path = ?
            WHERE id = ? AND ' . pharmacy_scope_sql() . '
        ');

        $stmt->execute([
            $pharmacyId,
            $name,
            $genericName,
            $brand,
            $dosageForm,
            $strength,
            $unit,
            $category,
            $dosageSummary,
            $description,
            $ingredients,
            $batchNumber,
            $expiration,
            $stockQuantity,
            $unitPrice,
            $sellingPrice,
            isset($_POST['prescription_required']) ? 1 : 0,
            isset($_POST['is_active']) ? 1 : 0,
            $finalImagePath,
            $medicineId,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Medicine updated successfully',
            'medicine_id' => $medicineId,
        ]);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO medicines (
            pharmacy_id, name, generic_name, brand, dosage_form, strength, unit, category, dosage,
            description, ingredients, batch_number, expiration_date, stock_quantity, minimum_stock,
            unit_price, selling_price, prescription_required, is_active, image_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $pharmacyId,
        $name,
        $genericName,
        $brand,
        $dosageForm,
        $strength,
        $unit,
        $category,
        $dosageSummary,
        $description,
        $ingredients,
        $batchNumber,
        $expiration,
        $stockQuantity,
        $unitPrice,
        $sellingPrice,
        isset($_POST['prescription_required']) ? 1 : 0,
        isset($_POST['is_active']) ? 1 : 0,
        $imagePath,
    ]);

    $medicineId = (int) $pdo->lastInsertId();
    if ($imageUpload !== null && $medicineId > 0) {
        pharmacy_medicine_store_image($medicineId, $imageUpload['filename'], $imageUpload['mime'], $imageUpload['content']);
        $pdo->prepare('UPDATE medicines SET image_path = ? WHERE id = ? AND ' . pharmacy_scope_sql())
            ->execute(['medicine-image.php?id=' . $medicineId, $medicineId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Medicine saved successfully',
        'medicine_id' => $medicineId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save this medicine.']);
}
