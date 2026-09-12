<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/live-sync.php';
require_once dirname(__DIR__) . '/includes/admin.php';
require_once dirname(__DIR__) . '/includes/resident-notifications.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

require_portal_auth('admin');

caps_bootstrap();

$adminName = (string) ($_SESSION['user_name'] ?? 'Admin');
$adminEmail = (string) ($_SESSION['user_email'] ?? '');
$adminInitials = admin_initials($adminName);
$adminPage = 'overview';
$adminSettingsError = '';
$adminSettingsMessage = '';
$openAdminSettings = false;
$adminFlash = '';
$adminFlashError = '';
if (isset($_SESSION['admin_flash']) && is_string($_SESSION['admin_flash'])) {
    $adminFlash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}
if (isset($_SESSION['admin_flash_error']) && is_string($_SESSION['admin_flash_error'])) {
    $adminFlashError = $_SESSION['admin_flash_error'];
    unset($_SESSION['admin_flash_error']);
}

$allowedPages = ['overview', 'pharmacies', 'reports'];
$requestedPage = strtolower(trim((string) ($_GET['page'] ?? 'overview')));
if (in_array($requestedPage, $allowedPages, true)) {
    $adminPage = $requestedPage;
}

$reviewReportId = trim((string) ($_GET['report'] ?? ''));
$reviewReport = $reviewReportId !== '' ? pharmacy_reports_find_by_id($reviewReportId) : null;
$reviewReportModal = trim((string) ($_GET['modal'] ?? '')) === '1';
if ($reviewReportId !== '') {
    $adminPage = 'reports';
    if (!$reviewReport) {
        $adminFlashError = 'Report not found.';
    }
}

$viewPharmacyId = trim((string) ($_GET['pharmacy'] ?? ''));
$viewPharmacy = $viewPharmacyId !== '' ? pharmacy_accounts_find_by_id($viewPharmacyId) : null;
$viewPharmacyModal = trim((string) ($_GET['modal'] ?? '')) === '1';
if ($viewPharmacyId !== '') {
    $adminPage = 'pharmacies';
    if (!$viewPharmacy) {
        $adminFlashError = 'Pharmacy not found.';
    }
}

function admin_url(string $path = ''): string
{
    return app_url('admin/' . ltrim($path, '/'));
}

function admin_pharmacy_review_url(string $pharmacyId, bool $modal = false, array $extra = []): string
{
    $query = array_merge(['page' => 'pharmacies', 'pharmacy' => $pharmacyId], $extra);
    if ($modal) {
        $query['modal'] = '1';
    }

    return admin_url('?' . http_build_query(array_filter($query, static fn ($value) => $value !== '' && $value !== null)));
}

function admin_report_review_url(string $reportId, bool $modal = false, array $extra = []): string
{
    $query = array_merge(['page' => 'reports', 'report' => $reportId], $extra);
    if ($modal) {
        $query['modal'] = '1';
    }

    return admin_url('?' . http_build_query(array_filter($query, static fn ($value) => $value !== '' && $value !== null)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_admin_notification_read') {
    $notificationId = trim((string) ($_POST['notification_id'] ?? ''));
    admin_notifications_mark_read($notificationId);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'unread' => admin_notification_unread_count(admin_notifications_list()),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_admin_credentials') {
    $result = customers_update_credentials($adminEmail, [
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
    ]);

    if (!$result['ok']) {
        $adminSettingsError = $result['error'];
        $openAdminSettings = true;
        $adminEmail = trim((string) ($_POST['email'] ?? $adminEmail));
    } else {
        $updated = $result['customer'] ?? [];
        $_SESSION['user_email'] = (string) ($updated['email'] ?? $adminEmail);
        $_SESSION['user_name'] = (string) ($updated['full_name'] ?? $adminName);
        $adminEmail = (string) $_SESSION['user_email'];
        $adminName = (string) $_SESSION['user_name'];
        $adminInitials = admin_initials($adminName);
        $adminSettingsMessage = 'Profile settings saved.';
        $openAdminSettings = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_pharmacy_report') {
    $adminPage = 'reports';
    $reportId = trim((string) ($_POST['report_id'] ?? ''));
    $decision = trim((string) ($_POST['decision'] ?? ''));
    $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
    $decisionReason = trim((string) ($_POST['decision_reason'] ?? ''));
    $adminNoteOther = trim((string) ($_POST['admin_note_other'] ?? ''));
    $report = pharmacy_reports_find_by_id($reportId);

    if (!$report) {
        $adminFlashError = 'Report not found.';
    } elseif (!in_array($decision, ['resolve', 'dismiss', 'block'], true)) {
        $adminFlashError = 'Choose a valid review action.';
        $reviewReport = $report;
        $reviewReportModal = trim((string) ($_POST['review_modal'] ?? '')) === '1';
    } else {
        if ($decision === 'block') {
            $blockNote = admin_pharmacy_build_decision_note('blocked', $decisionReason, $adminNoteOther);
            if ($blockNote === '') {
                $adminFlashError = 'Choose a specific reason before blocking this pharmacy account.';
                $reviewReport = $report;
                $reviewReportModal = trim((string) ($_POST['review_modal'] ?? '')) === '1';
            } else {
                $block = pharmacy_accounts_set_status(
                    (string) ($report['pharmacy_email'] ?? ''),
                    'blocked',
                    $blockNote
                );

                if (!$block['ok']) {
                    $adminFlashError = $block['error'];
                    $reviewReport = $report;
                    $reviewReportModal = trim((string) ($_POST['review_modal'] ?? '')) === '1';
                } else {
                    $update = pharmacy_reports_update($reportId, [
                        'status' => 'resolved',
                        'reviewed_at' => date('c'),
                        'admin_note' => $adminNote !== '' ? $adminNote : $blockNote,
                        'action_taken' => 'blocked',
                    ]);
                    $adminFlash = $update['ok']
                        ? 'Pharmacy account blocked and report moved to resolved.'
                        : ($update['error'] ?? 'Could not update report.');
                }
            }
        } elseif ($decision === 'resolve') {
            if ($adminNote === '') {
                $adminFlashError = 'Admin note is required before sending a reply.';
                $reviewReport = $report;
                $reviewReportModal = trim((string) ($_POST['review_modal'] ?? '')) === '1';
            } else {
                $update = pharmacy_reports_update($reportId, [
                    'status' => 'resolved',
                    'reviewed_at' => date('c'),
                    'admin_note' => $adminNote,
                    'action_taken' => 'resolved',
                ]);
                if ($update['ok']) {
                    resident_notification_add(
                        (string) ($report['reporter_email'] ?? ''),
                        'Update on your pharmacy report',
                        'Admin replied to your report about ' . (string) ($report['pharmacy_name'] ?? 'the pharmacy') . ': ' . $adminNote,
                        'report',
                        $reportId
                    );
                }
                $adminFlash = $update['ok'] ? 'Reply sent and report moved to resolved.' : ($update['error'] ?? 'Could not update report.');
            }
        } else {
            $update = pharmacy_reports_update($reportId, [
                'status' => 'dismissed',
                'reviewed_at' => date('c'),
                'admin_note' => $adminNote,
                'action_taken' => 'dismissed',
            ]);
            $adminFlash = $update['ok'] ? 'Report dismissed.' : ($update['error'] ?? 'Could not update report.');
        }

        if ($adminFlashError === '' && $adminFlash !== '') {
            $_SESSION['admin_flash'] = $adminFlash;
            header('Location: ' . admin_url('?page=reports&filter=resolved'), true, 302);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_pharmacy_status') {
    $adminPage = 'pharmacies';
    $pharmacyId = trim((string) ($_POST['pharmacy_id'] ?? ''));
    $decision = trim((string) ($_POST['decision'] ?? ''));
    $decisionReason = trim((string) ($_POST['decision_reason'] ?? ''));
    $adminNoteOther = trim((string) ($_POST['admin_note_other'] ?? ''));
    $account = pharmacy_accounts_find_by_id($pharmacyId);

    if (!$account) {
        $adminFlashError = 'Pharmacy not found.';
    } elseif (!in_array($decision, ['approved', 'rejected', 'pending', 'blocked'], true)) {
        $adminFlashError = 'Choose a valid pharmacy action.';
        $viewPharmacy = $account;
    } elseif (in_array($decision, ['rejected', 'blocked'], true)) {
        $adminNote = admin_pharmacy_build_decision_note($decision, $decisionReason, $adminNoteOther);
        if ($adminNote === '') {
            $adminFlashError = 'Choose a specific reason before rejecting or blocking this pharmacy.';
            $viewPharmacy = $account;
        } else {
            $update = pharmacy_accounts_set_status((string) ($account['email'] ?? ''), $decision, $adminNote);
            if (!$update['ok']) {
                $adminFlashError = $update['error'] ?? 'Could not update pharmacy status.';
                $viewPharmacy = $account;
            } else {
                $redirectModal = trim((string) ($_POST['review_modal'] ?? '')) === '1';
                header('Location: ' . admin_pharmacy_review_url($pharmacyId, $redirectModal), true, 302);
                exit;
            }
        }
    } else {
        $note = match ($decision) {
            'approved' => 'Pharmacy account approved.',
            default => 'Pharmacy returned to pending review.',
        };

        $update = pharmacy_accounts_set_status((string) ($account['email'] ?? ''), $decision, $note);
        if (!$update['ok']) {
            $adminFlashError = $update['error'] ?? 'Could not update pharmacy status.';
            $viewPharmacy = $account;
        } else {
            if ($decision === 'approved' && !pharmacy_accounts_is_approved($account)) {
                $approvalEmail = strtolower(trim((string) ($account['email'] ?? '')));
                $pharmacyName = trim((string) ($account['pharmacy_name'] ?? $account['name'] ?? 'Pharmacy'));
                if ($approvalEmail !== '' && residence_is_gmail($approvalEmail)) {
                    $safeName = htmlspecialchars($pharmacyName, ENT_QUOTES, 'UTF-8');
                    $safeLogin = htmlspecialchars(app_url('pharmacy/'), ENT_QUOTES, 'UTF-8');
                    smtp_send_gmail(
                        $approvalEmail,
                        'Your AddToMar pharmacy registration was approved',
                        '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#163948;line-height:1.6">'
                        . '<h2>Your pharmacy registration has been approved</h2>'
                        . '<p>Hello ' . $safeName . ',</p>'
                        . '<p>Your AddToMar pharmacy registration has been approved. You can now sign in to manage your pharmacy account.</p>'
                        . '<p><a href="' . $safeLogin . '" style="display:inline-block;padding:10px 16px;background:#0f8f82;color:#fff;text-decoration:none;border-radius:6px">Sign in to Pharmacy Portal</a></p>'
                        . '<p>Thank you,<br>AddToMar Team</p></body></html>'
                    );
                }
            }
            $redirectModal = trim((string) ($_POST['review_modal'] ?? '')) === '1';
            header('Location: ' . admin_pharmacy_review_url($pharmacyId, $redirectModal), true, 302);
            exit;
        }
    }
}

$stats = admin_dashboard_stats();
admin_notifications_sync($stats);

if ($viewPharmacy) {
    admin_notifications_mark_entity_read('pharmacy', (string) ($viewPharmacy['id'] ?? ''));
}
if ($reviewReport) {
    admin_notifications_mark_entity_read('report', (string) ($reviewReport['id'] ?? ''));
}

$adminNotifications = admin_notification_items($stats);
$adminNotifyCount = admin_notification_unread_count($adminNotifications);
