<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');
require_once dirname(__DIR__) . '/config.php';

if (!defined('PHARMACY_SKIP_MIGRATIONS')) {
    define('PHARMACY_SKIP_MIGRATIONS', true);
}

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
    $info = @getimagesize($tmpName);
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
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

    $storedMime = $mime;
    $storedExt = $allowed[$mime];
    $compressed = pharmacy_medicine_compress_image($bytes, $mime);
    if (is_array($compressed) && ($compressed['content'] ?? '') !== '') {
        $bytes = (string) $compressed['content'];
        $storedMime = (string) ($compressed['mime'] ?? $mime);
        $storedExt = $storedMime === 'image/jpeg' ? 'jpg' : $storedExt;
    }

    $imageUpload = [
        'filename' => bin2hex(random_bytes(8)) . '.' . $storedExt,
        'mime' => $storedMime,
        'content' => $bytes,
    ];
}

$fields = [
    'name' => $name,
    'generic_name' => $genericName,
    'brand' => $brand,
    'dosage_form' => $dosageForm,
    'strength' => $strength,
    'unit' => $unit,
    'category' => $category,
    'description' => $description,
    'ingredients' => $ingredients,
    'batch_number' => $batchNumber,
    'expiration_date' => $expiration,
    'stock_quantity' => $stockQuantity,
    'unit_price' => $unitPrice,
    'selling_price' => $sellingPrice,
    'prescription_required' => isset($_POST['prescription_required']) ? 1 : 0,
    'is_active' => isset($_POST['is_active']) ? 1 : 0,
];

try {
    $pdo = pharmacy_db();
    $pharmacyId = pharmacy_current_id();
    if ($pharmacyId === '') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Pharmacy session not found.']);
        exit;
    }

    $imageUrl = null;
    if ($medicineId > 0) {
        if ($imageUpload !== null) {
            pharmacy_medicine_store_image($medicineId, $imageUpload['filename'], $imageUpload['mime'], $imageUpload['content']);
            $imagePath = 'medicine-image.php?id=' . $medicineId;
            $imageUrl = pharmacy_saved_medicine_image_url($medicineId);
        }

        $sql = '
            UPDATE medicines SET
                pharmacy_id = COALESCE(NULLIF(pharmacy_id, ""), ?),
                name = ?, generic_name = ?, brand = ?, dosage_form = ?, strength = ?, unit = ?, category = ?, dosage = ?,
                description = ?, ingredients = ?, batch_number = ?, expiration_date = ?, stock_quantity = ?,
                unit_price = ?, selling_price = ?, prescription_required = ?, is_active = ?
        ';
        $params = [
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
            $fields['prescription_required'],
            $fields['is_active'],
        ];
        if ($imagePath !== null) {
            $sql .= ', image_path = ?';
            $params[] = $imagePath;
        }
        $sql .= ' WHERE id = ? AND ' . pharmacy_scope_sql();
        $params[] = $medicineId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT id FROM medicines WHERE id = ? AND ' . pharmacy_scope_sql() . ' LIMIT 1');
            $exists->execute([$medicineId]);
            if (!$exists->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Medicine not found.']);
                exit;
            }
        }

        $payload = pharmacy_medicine_save_client_payload($medicineId, $fields, $imageUrl, false);
        echo json_encode([
            'success' => true,
            'message' => 'Medicine updated successfully',
            'medicine_id' => $medicineId,
        ] + $payload);
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
        $imageUrl = pharmacy_saved_medicine_image_url($medicineId);
    }

    $payload = pharmacy_medicine_save_client_payload($medicineId, $fields, $imageUrl, true);
    echo json_encode([
        'success' => true,
        'message' => 'Medicine saved successfully',
        'medicine_id' => $medicineId,
    ] + $payload);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save this medicine.']);
}
