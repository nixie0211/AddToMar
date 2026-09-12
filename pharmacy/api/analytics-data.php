<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/pharmacy-context.php';
header('Content-Type: application/json; charset=UTF-8');
$months = (int) ($_GET['months'] ?? 6);
if (!in_array($months, [3, 6, 12], true)) $months = 6;
$pdo = pharmacy_db(); $labels = []; $stockIn = []; $stockOut = [];
for ($i = $months - 1; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months")); $labels[] = date('M', strtotime($month . '-01'));
    $in = $pdo->prepare('SELECT COALESCE(SUM(stock_quantity),0) FROM medicines WHERE DATE_FORMAT(created_at, "%Y-%m") = ? AND ' . pharmacy_scope_sql()); $in->execute([$month]); $stockIn[] = (int) $in->fetchColumn();
    $out = $pdo->prepare('SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.status = "delivered" AND DATE_FORMAT(o.created_at, "%Y-%m") = ? AND ' . pharmacy_scope_sql('o')); $out->execute([$month]); $stockOut[] = (int) $out->fetchColumn();
}
echo json_encode(['success' => true, 'trend' => compact('labels', 'stockIn', 'stockOut')]);
