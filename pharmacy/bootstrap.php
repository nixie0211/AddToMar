<?php

declare(strict_types=1);

require_once PHARMACY_ROOT . '/includes/database.php';
require_once PHARMACY_ROOT . '/includes/pharmacy-context.php';
require_once PHARMACY_ROOT . '/includes/repository.php';
require_once dirname(PHARMACY_ROOT) . '/includes/pharmacy-accounts.php';

try {
    pharmacy_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;">';
    echo '<h1>Database connection failed</h1>';
    echo '<p>Make sure MySQL is running in XAMPP, then refresh this page.</p>';
    echo '<pre style="background:#f8fafc;padding:16px;border-radius:8px;">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</body></html>';
    exit;
}

$pharmacyAccount = pharmacy_accounts_find_by_email((string) ($_SESSION['user_email'] ?? '')) ?? [];
if ($pharmacyAccount !== []) {
    pharmacy_sync_settings_from_account($pharmacyAccount);
}
$pharmacyProfileDays = pharmacy_accounts_operation_day_options();
$pharmacyProfileSelectedDays = is_array($pharmacyAccount['operation_days'] ?? null) ? $pharmacyAccount['operation_days'] : [];
$pharmacyProfileHours = is_array($pharmacyAccount['operating_hours'] ?? null) && ($pharmacyAccount['operating_hours'] ?? []) !== []
    ? $pharmacyAccount['operating_hours']
    : pharmacy_accounts_default_operating_hours();

$pharmacyLogoPath = (string) ($pharmacyAccount['logo_path'] ?? '');
$pharmacyLogoUrl = pharmacy_accounts_public_logo_url($pharmacyAccount);

$settings = pharmacy_settings();
$stats = pharmacy_get_dashboard_stats();
$medicines = pharmacy_get_medicines();
$categories = pharmacy_get_categories();
$orders = pharmacy_get_orders();
$orderStatusCounts = pharmacy_get_order_status_counts();
$customers = pharmacy_get_customers();
$suppliers = pharmacy_get_suppliers();
$chartData = pharmacy_get_chart_data();
$salesStats = pharmacy_get_sales_stats();
$recentTransactions = pharmacy_get_recent_transactions();
$topSelling = pharmacy_get_top_selling();
$analyticsStats = pharmacy_get_analytics_stats();
$lowStockMedicines = pharmacy_get_low_stock_medicines();
$expiringMedicines = pharmacy_get_expiring_medicines();
$recentOrders = array_slice($orders, 0, 5);
$selectedOrder = $orders[0] ?? null;
$selectedOrderItems = $selectedOrder ? pharmacy_get_order_items((int) $selectedOrder['id']) : [];

$pharmacyName = trim((string) ($pharmacyAccount['pharmacy_name'] ?? '')) ?: (($settings['pharmacy_name'] ?? '') ?: 'Your Pharmacy');
$pharmacyEmail = trim((string) ($pharmacyAccount['email'] ?? ($_SESSION['user_email'] ?? '')));
$staffName = $settings['staff_name'] ?: 'Pharmacist';
$staffRole = $settings['staff_role'] ?: 'Admin';
$staffInitials = pharmacy_initials($staffName);
$pharmacyInitials = pharmacy_initials($pharmacyName);
