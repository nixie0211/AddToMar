<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');
$u = current_user();
$page_title = 'Search History';

$stmt = $pdo->prepare('SELECT sh.*, m.medicine_name
                       FROM search_history sh
                       LEFT JOIN medicines m ON m.id = sh.medicine_id
                       WHERE sh.user_id = ?
                       ORDER BY sh.created_at DESC
                       LIMIT 50');
$stmt->execute([$u['id']]);
$history = $stmt->fetchAll();

$hero_badge = 'History';
$hero_title = 'Search History';
$hero_desc = 'Your recent medicine searches.';
$hero_actions_html = '<a href="medicine-finder.php" class="btn-hero-outline"><i class="bi bi-search"></i> New Search</a>';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

<div class="card p-3 fade-in-up">
  <?php if (!$history): ?>
    <p class="text-muted text-center py-4 mb-0">No search history yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Search Term</th><th>Top Match</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td class="fw-semibold"><?php echo clean($h['search_term']); ?></td>
          <td class="small text-muted"><?php echo clean($h['medicine_name'] ?: '—'); ?></td>
          <td class="small text-muted"><?php echo date('M d, Y g:i A', strtotime($h['created_at'])); ?></td>
          <td><a href="medicine-finder.php?q=<?php echo urlencode($h['search_term']); ?>" class="btn btn-sm btn-outline-soft">Search Again</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
