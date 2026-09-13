<?php

declare(strict_types=1);

$tableFilter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
if (!in_array($tableFilter, ['all', 'under_review', 'resolved'], true)) {
    $tableFilter = 'all';
}
$tableFrom = trim((string) ($_GET['from'] ?? ''));
$tableTo = trim((string) ($_GET['to'] ?? ''));
$reportRows = $stats['reports'];
$reportRows = array_values(array_filter(
    $reportRows,
    static function (array $report) use ($tableFilter): bool {
        $status = strtolower((string) ($report['status'] ?? 'under_review'));
        if ($status === 'dismissed') {
            return false;
        }
        if ($tableFilter === 'under_review') {
            return $status === 'under_review' || $status === 'open';
        }
        if ($tableFilter === 'resolved') {
            return $status === 'resolved';
        }

        return true;
    }
));
$reportRows = admin_filter_by_date($reportRows, $tableFrom, $tableTo);
$reportPaged = admin_paginate($reportRows, (int) ($_GET['p'] ?? 1));
$reportQuery = static function (array $overrides = []) use ($tableFilter, $tableFrom, $tableTo): string {
    $params = array_merge([
        'page' => 'reports',
        'filter' => $tableFilter,
        'from' => $tableFrom,
        'to' => $tableTo,
    ], $overrides);

    return admin_url('?' . http_build_query(array_filter($params, static fn ($value) => $value !== '' && $value !== null)));
};
$reportTotal = (int) $stats['reports_open'] + (int) $stats['reports_resolved'];
$reportOpen = (int) $stats['reports_open'];
$reportResolved = (int) $stats['reports_resolved'];
$reportOpenPct = admin_percent($reportOpen, $reportTotal);
$reportResolvedPct = admin_percent($reportResolved, $reportTotal);
?>
<section class="pharm-layout">
<section class="pharm-stats pharm-stats--3" aria-label="Report statistics">
  <article class="admin-card pharm-stat">
    <span class="pharm-stat-icon pharm-stat-icon--total" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4h9l1 3h4v10H14l-1-3H7v7"/></svg>
    </span>
    <div>
      <div class="pharm-stat-line">
        <div class="pharm-stat-value"><?= number_format($reportTotal) ?></div>
      </div>
      <div class="pharm-stat-label">Total reports</div>
    </div>
  </article>
  <article class="admin-card pharm-stat">
    <span class="pharm-stat-icon pharm-stat-icon--pending" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
    </span>
    <div>
      <div class="pharm-stat-line">
        <div class="pharm-stat-value"><?= number_format($reportOpen) ?></div>
        <span class="pharm-stat-pill"><?= htmlspecialchars($reportOpenPct, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="pharm-stat-label">Under review</div>
    </div>
  </article>
  <article class="admin-card pharm-stat">
    <span class="pharm-stat-icon pharm-stat-icon--approved" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.3 2.3 4.7-5"/></svg>
    </span>
    <div>
      <div class="pharm-stat-line">
        <div class="pharm-stat-value"><?= number_format($reportResolved) ?></div>
        <span class="pharm-stat-pill pharm-stat-pill--up"><?= htmlspecialchars($reportResolvedPct, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="pharm-stat-label">Resolved</div>
    </div>
  </article>
</section>
<section class="card-list">
  <div class="card-list-title">
    <h3>Reports</h3>
  </div>
  <div class="card-list-toolbar table-toolbar">
    <nav class="table-tabs">
      <a class="<?= $tableFilter === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars($reportQuery(['filter' => 'all', 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>">All</a>
      <a class="<?= $tableFilter === 'under_review' ? 'active' : '' ?>" href="<?= htmlspecialchars($reportQuery(['filter' => 'under_review', 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>">Under review</a>
      <a class="<?= $tableFilter === 'resolved' ? 'active' : '' ?>" href="<?= htmlspecialchars($reportQuery(['filter' => 'resolved', 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>">Resolved</a>
    </nav>
    <form class="table-dates" method="get" action="">
      <input type="hidden" name="page" value="reports">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($tableFilter, ENT_QUOTES, 'UTF-8') ?>">
      <label class="table-date">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>
        <input type="date" name="from" value="<?= htmlspecialchars($tableFrom, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
      </label>
      <span>To</span>
      <label class="table-date">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>
        <input type="date" name="to" value="<?= htmlspecialchars($tableTo, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
      </label>
    </form>
  </div>
  <div class="pharm-workspace<?= ($reviewReport && !$reviewReportModal) ? ' has-detail' : '' ?>">
  <section class="pharm-list-column">
  <div class="pharm-cards-wrap">
    <?php if ($reportPaged['items'] === []): ?>
    <p class="pharm-cards-empty">No pharmacy reports yet.</p>
    <?php else: ?>
    <div class="pharm-cards">
      <?php foreach ($reportPaged['items'] as $report): ?>
      <?php
        $badge = admin_report_status_badge($report);
        $cardSchedule = admin_report_card_schedule($report);
        $avatarTone = abs(crc32((string) ($report['pharmacy_name'] ?? 'Rx'))) % 4;
        $linkedPharmacy = pharmacy_accounts_find_by_id((string) ($report['pharmacy_id'] ?? '')) ?? [];
        $cardLogoUrl = pharmacy_accounts_public_logo_url($linkedPharmacy);
        $cardLogoExists = $cardLogoUrl !== '';
        $cardViewUrl = $reportQuery(['report' => (string) ($report['id'] ?? '')]);
        $reporterName = trim((string) ($report['reporter_name'] ?? ''));
        $reason = trim((string) ($report['reason'] ?? ''));
      ?>
      <article class="pharm-card<?= $reviewReportId === (string) ($report['id'] ?? '') ? ' is-active' : '' ?>">
        <div class="pharm-card-top">
          <div class="pharm-card-schedule">
            <p class="pharm-card-schedule-line">
              <span class="pharm-card-schedule-label"><?= htmlspecialchars($cardSchedule['time_label'], ENT_QUOTES, 'UTF-8') ?></span>
              <strong><?= htmlspecialchars($cardSchedule['time'], ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
            <p class="pharm-card-schedule-line">
              <span class="pharm-card-schedule-label"><?= htmlspecialchars($cardSchedule['date_label'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="pharm-card-schedule-value"><?= htmlspecialchars($cardSchedule['date'], ENT_QUOTES, 'UTF-8') ?></span>
            </p>
          </div>
          <?php if ($cardLogoExists): ?>
          <div class="pharm-card-avatar">
            <img src="<?= htmlspecialchars($cardLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
          </div>
          <?php else: ?>
          <div class="pharm-card-avatar pharm-avatar-<?= $avatarTone ?>">
            <?= htmlspecialchars(admin_initials((string) ($report['pharmacy_name'] ?? 'Rx')), ENT_QUOTES, 'UTF-8') ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="pharm-card-body">
          <div class="pharm-card-info">
            <h4><?= htmlspecialchars((string) ($report['pharmacy_name'] ?? 'Pharmacy'), ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="pharm-card-meta">
              <span><?= htmlspecialchars($reporterName !== '' ? $reporterName : 'Unknown reporter', ENT_QUOTES, 'UTF-8') ?></span>
              <span class="status-pill <?= htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <p class="pharm-card-address"><?= htmlspecialchars($reason !== '' ? $reason : 'No problem described', ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
        <div class="pharm-card-foot">
          <a href="<?= htmlspecialchars($cardViewUrl, ENT_QUOTES, 'UTF-8') ?>">View details <span aria-hidden="true">›</span></a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <nav class="mini-pager" aria-label="Reports pages">
    <a class="<?= $reportPaged['page'] <= 1 ? 'is-disabled' : '' ?>" href="<?= htmlspecialchars($reportQuery(['p' => max(1, $reportPaged['page'] - 1)]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous page">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6 9 12l6 6"/></svg>
    </a>
    <?php foreach (admin_pagination_pages((int) $reportPaged['page'], (int) $reportPaged['pages']) as $pageNumber): ?>
      <?php if ($pageNumber === '...'): ?>
      <span class="mini-pager-ellipsis" aria-hidden="true">…</span>
      <?php elseif ((int) $pageNumber === (int) $reportPaged['page']): ?>
      <span class="mini-pager-page" aria-current="page"><?= (int) $pageNumber ?></span>
      <?php else: ?>
      <a class="mini-pager-number" href="<?= htmlspecialchars($reportQuery(['p' => (int) $pageNumber]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Page <?= (int) $pageNumber ?>"><?= (int) $pageNumber ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <a class="<?= $reportPaged['page'] >= $reportPaged['pages'] ? 'is-disabled' : '' ?>" href="<?= htmlspecialchars($reportQuery(['p' => min($reportPaged['pages'], $reportPaged['page'] + 1)]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next page">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </a>
  </nav>
  </section>
  <?php if ($reviewReport): ?>
  <?php
    $reviewStatus = (string) ($reviewReport['status'] ?? 'under_review');
    $reviewBadge = admin_report_status_badge($reviewReport);
    $canReview = in_array($reviewStatus, ['under_review', 'open'], true);
    $proofPath = (string) ($reviewReport['proof_path'] ?? '');
    $proofExists = $proofPath !== '' && is_file(dirname(__DIR__, 2) . '/' . $proofPath);
    $proofUrl = $proofExists ? app_url($proofPath) : '';
    $proofIsImage = $proofExists && admin_proof_is_image($proofPath);
    $reportReviewListQuery = $reportQuery();
  ?>
  <?php if (!$reviewReportModal): ?>
  <aside class="pharm-detail-panel" aria-label="Report details">
    <?php
      $reportReviewContext = 'panel';
      require __DIR__ . '/report-review.php';
    ?>
  </aside>
  <?php endif; ?>
  <?php endif; ?>
  </div>
</section>
</section>

<?php if ($reviewReport && $reviewReportModal): ?>
<?php
  $reviewStatus = (string) ($reviewReport['status'] ?? 'under_review');
  $reviewBadge = admin_report_status_badge($reviewReport);
  $canReview = in_array($reviewStatus, ['under_review', 'open'], true);
  $proofPath = (string) ($reviewReport['proof_path'] ?? '');
  $proofExists = $proofPath !== '' && is_file(dirname(__DIR__, 2) . '/' . $proofPath);
  $proofUrl = $proofExists ? app_url($proofPath) : '';
  $proofIsImage = $proofExists && admin_proof_is_image($proofPath);
  $reportReviewListQuery = $reportQuery();
?>
<div class="rx-popup" role="dialog" aria-modal="true" aria-labelledby="rx-report-popup-title">
  <a class="rx-popup-backdrop" href="<?= htmlspecialchars($reportReviewListQuery, ENT_QUOTES, 'UTF-8') ?>" aria-label="Close review"></a>
  <?php
    $reportReviewContext = 'modal';
    require __DIR__ . '/report-review.php';
  ?>
</div>
<?php endif; ?>
