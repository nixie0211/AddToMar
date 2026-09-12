<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/pharmacy-context.php';
require_once dirname(__DIR__) . '/includes/repository.php';

$report = (string) ($_GET['report'] ?? '');
$format = (string) ($_GET['format'] ?? 'csv');
if (!in_array($report, ['inventory', 'sales', 'revenue', 'low-stock', 'expired', 'performance', 'customers'], true) || !in_array($format, ['csv', 'excel', 'pdf'], true)) {
    http_response_code(422); exit('Invalid report request.');
}
$title = ucwords(str_replace('-', ' ', $report)) . ' Report';
$rows = [];
switch ($report) {
    case 'inventory':
    case 'low-stock':
    case 'expired':
        foreach (pharmacy_get_medicines() as $m) {
            if ($report === 'low-stock' && !in_array($m['status'], ['low', 'out'], true)) continue;
            if ($report === 'expired' && $m['status'] !== 'expired') continue;
            $rows[] = ['Medicine' => $m['name'], 'Category' => $m['category'], 'Stock' => $m['stock_quantity'], 'Minimum stock' => $m['minimum_stock'], 'Unit price' => $m['unit_price'], 'Stock value' => (float) $m['stock_quantity'] * (float) $m['unit_price'], 'Expiry' => pharmacy_expiration_display((string) $m['expiration_date']), 'Status' => $m['status']];
        }
        break;
    case 'sales':
        foreach (pharmacy_get_recent_transactions(1000) as $o) $rows[] = ['Order' => $o['order_number'], 'Customer' => $o['customer_name'] ?: 'Customer', 'Payment' => $o['payment_method'], 'Amount' => $o['total_amount'], 'Date' => $o['created_at']];
        break;
    case 'revenue':
        $stmt = pharmacy_db()->query('SELECT COALESCE(m.category, "Uncategorized") category, SUM(oi.quantity) units, SUM(oi.quantity * oi.unit_price) revenue FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id LEFT JOIN medicines m ON m.id = oi.medicine_id WHERE o.status = "delivered" AND ' . pharmacy_scope_sql('o') . ' GROUP BY m.category ORDER BY revenue DESC');
        foreach ($stmt->fetchAll() as $r) $rows[] = ['Category' => $r['category'], 'Units sold' => $r['units'], 'Revenue' => $r['revenue'], 'Estimated profit' => round((float) $r['revenue'] * .3, 2)];
        break;
    case 'performance':
        foreach (pharmacy_get_top_selling(1000) as $r) $rows[] = ['Medicine' => $r['name'], 'Units sold' => $r['units'], 'Revenue' => $r['revenue']];
        break;
    case 'customers':
        foreach (pharmacy_get_customers() as $c) $rows[] = ['Customer' => $c['name'], 'Email' => $c['email'], 'Phone' => $c['phone'], 'Orders' => $c['order_count'], 'Total spent' => $c['total_spent'], 'Last order' => $c['last_order_at']];
        break;
}
$filename = strtolower(str_replace(' ', '-', $title)) . '-' . date('Y-m-d');
if ($format === 'pdf') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><title>' . htmlspecialchars($title) . '</title><style>body{font:13px Arial;padding:28px;color:#132f3f}table{border-collapse:collapse;width:100%}th,td{padding:8px;border:1px solid #d3e6e2;text-align:left}th{background:#d6efeb}h1{margin:0 0 5px}p{color:#4a626d}@media print{button{display:none}}</style><button onclick="print()">Print / Save as PDF</button><h1>' . htmlspecialchars($title) . '</h1><p>Generated ' . date('M j, Y g:i A') . '</p><table><thead><tr>';
    foreach (array_keys($rows[0] ?? ['No records' => '']) as $key) echo '<th>' . htmlspecialchars($key) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) { echo '<tr>'; foreach ($row as $value) echo '<td>' . htmlspecialchars((string) $value) . '</td>'; echo '</tr>'; }
    echo '</tbody></table>'; exit;
}
$delimiter = $format === 'excel' ? "\t" : ',';
header('Content-Type: ' . ($format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . ($format === 'excel' ? '.xls' : '.csv') . '"');
$out = fopen('php://output', 'wb');
if ($format === 'excel') fwrite($out, "\xEF\xBB\xBF");
if ($rows !== []) { fputcsv($out, array_keys($rows[0]), $delimiter); foreach ($rows as $row) fputcsv($out, $row, $delimiter); }
else fputcsv($out, ['No records found'], $delimiter);
fclose($out);
