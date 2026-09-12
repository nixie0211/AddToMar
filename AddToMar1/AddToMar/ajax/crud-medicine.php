<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pharmacy.php';
header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']); exit;
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid session token.']); exit;
}

$action = $_POST['action'] ?? 'save';

// ---------------- DELETE ----------------
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM order_items WHERE medicine_id = ?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetch()['c'] > 0) {
        // Preserve order history integrity: soft-delete instead of hard delete
        $pdo->prepare("UPDATE medicines SET status = 'inactive' WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Medicine has order history, so it was deactivated instead of deleted.']);
    } else {
        $pdo->prepare("DELETE FROM medicines WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Medicine deleted successfully.']);
    }
    exit;
}

// ---------------- ADD / EDIT ----------------
$id = (int)($_POST['id'] ?? 0);
$code = clean($_POST['medicine_code'] ?? '');
$name = clean($_POST['medicine_name'] ?? '');
$generic = clean($_POST['generic_name'] ?? '');
$category = clean($_POST['category'] ?? '');
$dosage = clean($_POST['dosage'] ?? '');
$description = clean($_POST['description'] ?? '');
$usesInfo = clean($_POST['uses_info'] ?? '');
$dosageInstructions = clean($_POST['dosage_instructions'] ?? '');
$sideEffects = clean($_POST['side_effects'] ?? '');
$warnings = clean($_POST['warnings'] ?? '');
$storageInfo = clean($_POST['storage_info'] ?? '');
$manufacturer = clean($_POST['manufacturer'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);
$reorder = (int)($_POST['reorder_level'] ?? 10);
$expiry = clean($_POST['expiration_date'] ?? '') ?: null;
$status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

if (!$code || !$name || !$category || $price < 0 || $quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields correctly.']); exit;
}

// duplicate code check
$dupCheck = $pdo->prepare("SELECT id FROM medicines WHERE medicine_code = ? AND id != ?");
$dupCheck->execute([$code, $id]);
if ($dupCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Medicine code already exists.']); exit;
}

// handle image upload
$imageName = null;
if (!empty($_FILES['image']['name'])) {
    $file = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($file['type'], $allowed) || $file['size'] > 4 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Invalid image. Use JPG/PNG/WEBP under 4MB.']); exit;
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $imageName = 'MED-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], UPLOAD_MED_PATH . $imageName);
}

if ($id > 0) {
    if ($imageName) {
        $stmt = $pdo->prepare("UPDATE medicines SET medicine_code=?, medicine_name=?, generic_name=?, category=?, description=?, uses_info=?, dosage_instructions=?, side_effects=?, warnings=?, storage_info=?, manufacturer=?, dosage=?, price=?, quantity=?, reorder_level=?, expiration_date=?, image=?, status=? WHERE id=?");
        $stmt->execute([$code, $name, $generic, $category, $description, $usesInfo, $dosageInstructions, $sideEffects, $warnings, $storageInfo, $manufacturer, $dosage, $price, $quantity, $reorder, $expiry, $imageName, $status, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE medicines SET medicine_code=?, medicine_name=?, generic_name=?, category=?, description=?, uses_info=?, dosage_instructions=?, side_effects=?, warnings=?, storage_info=?, manufacturer=?, dosage=?, price=?, quantity=?, reorder_level=?, expiration_date=?, status=? WHERE id=?");
        $stmt->execute([$code, $name, $generic, $category, $description, $usesInfo, $dosageInstructions, $sideEffects, $warnings, $storageInfo, $manufacturer, $dosage, $price, $quantity, $reorder, $expiry, $status, $id]);
    }
    sync_main_pharmacy_inventory($pdo, $id, $quantity, $price);
    echo json_encode(['success' => true, 'message' => 'Medicine updated successfully.']);
} else {
    $imageName = $imageName ?: 'default-medicine.png';
    $stmt = $pdo->prepare("INSERT INTO medicines (medicine_code, medicine_name, generic_name, category, description, uses_info, dosage_instructions, side_effects, warnings, storage_info, manufacturer, dosage, price, quantity, reorder_level, expiration_date, image, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $name, $generic, $category, $description, $usesInfo, $dosageInstructions, $sideEffects, $warnings, $storageInfo, $manufacturer, $dosage, $price, $quantity, $reorder, $expiry, $imageName, $status]);
    sync_main_pharmacy_inventory($pdo, (int)$pdo->lastInsertId(), $quantity, $price);
    echo json_encode(['success' => true, 'message' => 'Medicine added successfully.']);
}
