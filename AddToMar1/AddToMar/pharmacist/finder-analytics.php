<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Finder Analytics';

$topSearches = $pdo->query('SELECT search_term, COUNT(*) AS search_count, MAX(created_at) AS last_searched
                              FROM search_analytics
                              GROUP BY search_term
                              ORDER BY search_count DESC
                              LIMIT 10')->fetchAll();

$topMedicines = $pdo->query('SELECT m.medicine_name, m.generic_name, COUNT(sa.id) AS request_count
                             FROM search_analytics sa
                             JOIN medicines m ON m.id = sa.medicine_id
                             GROUP BY sa.medicine_id
                             ORDER BY request_count DESC
                             LIMIT 10')->fetchAll();

$stockLevels = $pdo->query('SELECT p.pharmacy_name,
                                   COUNT(i.id) AS sku_count,
                                   COALESCE(SUM(i.stock_quantity),0) AS total_stock,
                                   SUM(CASE WHEN i.stock_quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock
                            FROM pharmacies p
                            LEFT JOIN inventory i ON i.pharmacy_id = p.id
                            WHERE p.status = "active"
                            GROUP BY p.id
                            ORDER BY total_stock DESC')->fetchAll();

$totalSearches = (int)$pdo->query('SELECT COUNT(*) c FROM search_analytics')->fetch()['c'];
$todaySearches = (int)$pdo->query('SELECT COUNT(*) c FROM search_analytics WHERE DATE(created_at) = CURDATE()')->fetch()['c'];
$activePharmacies = (int)$pdo->query('SELECT COUNT(*) c FROM pharmacies WHERE status="active"')->fetch()['c'];

$hero_badge = 'Analytics';
$hero_title = 'Medicine Finder Analytics';
$hero_desc = 'Monitor search trends, most requested medicines, and pharmacy stock levels.';
include __DIR__ . '/../includes/hero-layout-start.php';

$mf_css = file_exists(__DIR__ . '/../assets/css/medicine-finder.css') ? filemtime(__DIR__ . '/../assets/css/medicine-finder.css') : time();
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/medicine-finder.css?v=<?php echo $mf_css; ?>">

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="glass-card analytics-stat fade-in-up"><div class="num"><?php echo $totalSearches; ?></div><div class="small text-muted">Total Searches</div></div></div>
  <div class="col-md-4"><div class="glass-card analytics-stat fade-in-up"><div class="num"><?php echo $todaySearches; ?></div><div class="small text-muted">Searches Today</div></div></div>
  <div class="col-md-4"><div class="glass-card analytics-stat fade-in-up"><div class="num"><?php echo $activePharmacies; ?></div><div class="small text-muted">Active Pharmacies</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-4 fade-in-up">
      <h6 class="fw-bold mb-3">Most Searched Terms</h6>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Term</th><th>Count</th><th>Last</th></tr></thead>
        <tbody>
        <?php foreach ($topSearches as $r): ?>
          <tr><td class="fw-semibold"><?php echo clean($r['search_term']); ?></td><td><?php echo (int)$r['search_count']; ?></td><td class="small text-muted"><?php echo date('M d', strtotime($r['last_searched'])); ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$topSearches): ?><tr><td colspan="3" class="text-muted">No data yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-4 fade-in-up">
      <h6 class="fw-bold mb-3">Most Requested Medicines</h6>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Medicine</th><th>Generic</th><th>Requests</th></tr></thead>
        <tbody>
        <?php foreach ($topMedicines as $r): ?>
          <tr><td class="fw-semibold"><?php echo clean($r['medicine_name']); ?></td><td class="small text-muted"><?php echo clean($r['generic_name']); ?></td><td><?php echo (int)$r['request_count']; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$topMedicines): ?><tr><td colspan="3" class="text-muted">No data yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-12">
    <div class="card p-4 fade-in-up">
      <h6 class="fw-bold mb-3">Pharmacy Stock Levels</h6>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Pharmacy</th><th>SKUs</th><th>Total Stock</th><th>Out of Stock Items</th></tr></thead>
          <tbody>
          <?php foreach ($stockLevels as $r): ?>
            <tr>
              <td class="fw-semibold"><?php echo clean($r['pharmacy_name']); ?></td>
              <td><?php echo (int)$r['sku_count']; ?></td>
              <td><?php echo (int)$r['total_stock']; ?></td>
              <td><span class="<?php echo $r['out_of_stock'] > 0 ? 'text-danger fw-semibold' : 'text-success'; ?>"><?php echo (int)$r['out_of_stock']; ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
