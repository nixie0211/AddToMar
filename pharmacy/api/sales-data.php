<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_portal_auth('pharmacy');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/database.php';
require_once dirname(__DIR__) . '/includes/pharmacy-context.php';

header('Content-Type: application/json; charset=UTF-8');
$period = (string) ($_GET['period'] ?? 'daily');
if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';
$definitions = [
    'daily' => ['days' => 7, 'group' => '%b %e', 'label' => 'Daily sales'],
    'weekly' => ['days' => 56, 'group' => 'Week %u', 'label' => 'Weekly sales'],
    'monthly' => ['days' => 365, 'group' => '%b %Y', 'label' => 'Monthly sales'],
];
$definition = $definitions[$period];
$pdo = pharmacy_db();
$scope = pharmacy_scope_sql();
$start = date('Y-m-d 00:00:00', strtotime('-' . ($definition['days'] - 1) . ' days'));
$stats = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) revenue, COUNT(*) orders FROM orders WHERE status = "delivered" AND created_at >= ? AND ' . $scope);
$stats->execute([$start]);
$totals = $stats->fetch() ?: ['revenue' => 0, 'orders' => 0];
$revenue = (float) $totals['revenue'];
$orders = (int) $totals['orders'];
$trend = $pdo->prepare('SELECT DATE_FORMAT(created_at, ?) label, COALESCE(SUM(total_amount),0) revenue FROM orders WHERE status = "delivered" AND created_at >= ? AND ' . $scope . ' GROUP BY DATE_FORMAT(created_at, ?) ORDER BY MIN(created_at)');
$trend->execute([$definition['group'], $start, $definition['group']]);
$rows = $trend->fetchAll();
echo json_encode(['success' => true, 'label' => $definition['label'], 'stats' => ['revenue' => $revenue, 'profit' => $revenue * 0.3, 'avgOrder' => $orders ? $revenue / $orders : 0, 'orders' => $orders], 'trend' => ['labels' => array_column($rows, 'label'), 'revenue' => array_map('floatval', array_column($rows, 'revenue'))]]);
