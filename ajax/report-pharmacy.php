<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_portal_auth('residence');
require_once dirname(__DIR__) . '/includes/customers.php';
require_once dirname(__DIR__) . '/includes/pharmacy-accounts.php';
require_once dirname(__DIR__) . '/includes/pharmacy-reports.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$pharmacyId = trim((string) ($_POST['pharmacy_id'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));
$otherReason = trim((string) ($_POST['other_reason'] ?? ''));
$details = trim((string) ($_POST['details'] ?? ''));
$allowedReasons = [
    'Incorrect pharmacy information',
    'Unsafe or counterfeit medicine',
    'Unprofessional service',
    'Other',
];

if ($pharmacyId === '' || !in_array($reason, $allowedReasons, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Choose a reason for the report.']);
    exit;
}

if ($reason === 'Other') {
    if ($otherReason === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Specify the reason for your report.']);
        exit;
    }
    // Store the resident's specified reason, not the generic selector label.
    $reason = $otherReason;
}

$pharmacy = null;
foreach (pharmacy_accounts_list_approved() as $account) {
    if ((string) ($account['id'] ?? '') === $pharmacyId) {
        $pharmacy = $account;
        break;
    }
}

if ($pharmacy === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'This pharmacy is no longer available.']);
    exit;
}

$proof = $_FILES['proof'] ?? null;
if (!is_array($proof) || (int) ($proof['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Upload proof for this report.']);
    exit;
}

if ((int) ($proof['size'] ?? 0) < 1 || (int) ($proof['size'] ?? 0) > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Proof must be no larger than 5 MB.']);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $proof['tmp_name']);
$extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

if (!isset($extensions[$mime])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Proof must be a JPG, PNG, WEBP, or PDF file.']);
    exit;
}

$uploadDir = dirname(__DIR__) . '/uploads/reports';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not prepare proof storage.']);
    exit;
}

$fileName = 'report-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
$proofBytes = (string) file_get_contents((string) $proof['tmp_name']);
$proofPath = 'uploads/reports/' . $fileName;
if (function_exists('addtomar_object_storage_put') && addtomar_object_storage_enabled() && $proofBytes !== '') {
    $stored = addtomar_object_storage_put('reports/' . $fileName, $proofBytes, (string) $mime);
    if ($stored !== '') {
        $proofPath = $stored;
    }
}
if ($proofPath === 'uploads/reports/' . $fileName) {
    if (!move_uploaded_file((string) $proof['tmp_name'], $uploadDir . '/' . $fileName)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save the proof file.']);
        exit;
    }
}

$email = (string) ($_SESSION['user_email'] ?? '');
$customer = customers_find_by_email($email);
$reports = pharmacy_reports_load_all();
$report = [
    'id' => 'RPT-' . strtoupper(bin2hex(random_bytes(4))),
    'pharmacy_id' => $pharmacyId,
    'pharmacy_name' => trim((string) ($pharmacy['pharmacy_name'] ?? 'Pharmacy')),
    'pharmacy_email' => trim((string) ($pharmacy['email'] ?? '')),
    'reporter_name' => trim((string) ($customer['full_name'] ?? ($_SESSION['user_name'] ?? 'Resident'))),
    'reporter_email' => $email,
    'reason' => $reason,
    'details' => $details,
    'proof_path' => $proofPath,
    'status' => 'under_review',
    'created_at' => date('c'),
    'updated_at' => date('c'),
];
$reports[] = $report;

if (!pharmacy_reports_save_all($reports)) {
    @unlink($uploadDir . '/' . $fileName);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save the report.']);
    exit;
}

echo json_encode(['ok' => true, 'report_id' => $report['id']], JSON_UNESCAPED_UNICODE);
