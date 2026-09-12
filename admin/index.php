<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$adminPageTitle = match ($adminPage) {
    'pharmacies' => 'Pharmacies',
    'reports' => 'Reports',
    default => 'Overview',
};

require __DIR__ . '/partials/shell-start.php';
?>

<?php if ($adminFlash !== ''): ?>
<p class="settings-alert success"><?= htmlspecialchars($adminFlash, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($adminFlashError !== ''): ?>
<p class="settings-alert error"><?= htmlspecialchars($adminFlashError, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($adminPage === 'overview'): ?>
<?php
  $pharmacyTotal = (int) $stats['pharmacies_total'];
  $approvedCount = (int) $stats['pharmacies_approved'];
  $pendingCount = (int) $stats['pharmacies_pending'];
  $rejectedCount = (int) $stats['pharmacies_rejected'];
  $blockedCount = (int) $stats['pharmacies_blocked'];
  $approvedPct = $pharmacyTotal > 0 ? ($approvedCount / $pharmacyTotal) * 100 : 0;
  $pendingPct = $pharmacyTotal > 0 ? ($pendingCount / $pharmacyTotal) * 100 : 0;
  $rejectedPct = $pharmacyTotal > 0 ? ($rejectedCount / $pharmacyTotal) * 100 : 0;
  $blockedPct = $pharmacyTotal > 0 ? ($blockedCount / $pharmacyTotal) * 100 : 0;
  $donutApprovedEnd = $approvedPct;
  $donutPendingEnd = $approvedPct + $pendingPct;
  $donutRejectedEnd = $donutPendingEnd + $rejectedPct;
  $openReports = array_values(array_filter(
      $stats['reports'],
      static fn (array $report): bool => in_array(strtolower((string) ($report['status'] ?? '')), ['under_review', 'open'], true)
  ));
  $openReasons = array_values(array_unique(array_filter(array_map(
      static fn (array $report): string => trim((string) ($report['reason'] ?? '')),
      $openReports
  ))));
  $reportAttentionDetail = $openReasons === []
      ? 'Customer reports waiting for a decision'
      : implode(' & ', array_slice($openReasons, 0, 2));
  $activity = admin_overview_activity($stats);
?>
<div class="ov">
  <section class="ov-metrics">
    <article class="admin-card ov-metric">
      <div class="ov-metric-top">
        <div class="ov-metric-icon ov-tone-approved" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5V20h16v-9.5"/><path d="M2 10.5 12 4l10 6.5"/><path d="M10 20v-6h4v6"/></svg>
        </div>
        <div>
          <div class="ov-metric-value"><?= number_format($pharmacyTotal) ?></div>
          <div class="ov-metric-label">Total pharmacies</div>
        </div>
      </div>
      <div class="ov-metric-note ov-text-approved"><?= number_format($approvedCount) ?> currently approved</div>
    </article>

    <article class="admin-card ov-metric">
      <div class="ov-metric-top">
        <div class="ov-metric-icon ov-tone-pending" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12"/><path d="M6 21h12"/><path d="M8 3v1.8c0 2.7 2 4.4 4 5.7 2-1.3 4-3 4-5.7V3"/><path d="M8 21v-1.8c0-2.7 2-4.4 4-5.7 2 1.3 4 3 4 5.7V21"/></svg>
        </div>
        <div>
          <div class="ov-metric-value"><?= number_format($pendingCount) ?></div>
          <div class="ov-metric-label">Pending approvals</div>
        </div>
      </div>
      <div class="ov-metric-note ov-text-pending">Needs your review</div>
    </article>

    <article class="admin-card ov-metric">
      <div class="ov-metric-top">
        <div class="ov-metric-icon ov-tone-review" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4h9l1 3h4v10H14l-1-3H7v7"/></svg>
        </div>
        <div>
          <div class="ov-metric-value"><?= number_format($stats['reports_open']) ?></div>
          <div class="ov-metric-label">Reports under review</div>
        </div>
      </div>
      <div class="ov-metric-note ov-text-review">Needs your action</div>
    </article>
  </section>

  <section class="ov-mid">
    <article class="admin-card ov-panel">
      <h3>Pharmacy status summary</h3>
      <div class="ov-status">
        <div class="ov-donut" style="background: <?= $pharmacyTotal > 0
            ? 'conic-gradient(var(--teal) 0 ' . $donutApprovedEnd . '%, var(--amber) ' . $donutApprovedEnd . '% ' . $donutPendingEnd . '%, #e07a5f ' . $donutPendingEnd . '% ' . $donutRejectedEnd . '%, var(--accent) ' . $donutRejectedEnd . '% 100%)'
            : 'conic-gradient(var(--border) 0 100%)' ?>;">
          <div class="ov-donut-hole">
            <strong><?= number_format($pharmacyTotal) ?></strong>
            <span>Total</span>
          </div>
        </div>
        <ul class="ov-status-legend">
          <li><span class="legend-dot" style="background:var(--teal)"></span><span>Approved</span><b><?= number_format($approvedCount) ?></b><small><?= htmlspecialchars(admin_percent($approvedCount, $pharmacyTotal), ENT_QUOTES, 'UTF-8') ?></small></li>
          <li><span class="legend-dot" style="background:var(--amber)"></span><span>Pending</span><b><?= number_format($pendingCount) ?></b><small><?= htmlspecialchars(admin_percent($pendingCount, $pharmacyTotal), ENT_QUOTES, 'UTF-8') ?></small></li>
          <li><span class="legend-dot" style="background:#e07a5f"></span><span>Rejected</span><b><?= number_format($rejectedCount) ?></b><small><?= htmlspecialchars(admin_percent($rejectedCount, $pharmacyTotal), ENT_QUOTES, 'UTF-8') ?></small></li>
          <li><span class="legend-dot" style="background:var(--accent)"></span><span>Blocked</span><b><?= number_format($blockedCount) ?></b><small><?= htmlspecialchars(admin_percent($blockedCount, $pharmacyTotal), ENT_QUOTES, 'UTF-8') ?></small></li>
        </ul>
      </div>
      <a class="ov-outline-btn" href="<?= htmlspecialchars(admin_url('?page=pharmacies'), ENT_QUOTES, 'UTF-8') ?>">Manage pharmacies <span aria-hidden="true">›</span></a>
    </article>

    <article class="admin-card ov-panel">
      <h3>Needs attention</h3>
      <div class="ov-attention">
        <?php if ($pendingCount === 0 && $stats['reports_open'] === 0): ?>
        <p class="ov-empty">You're all caught up. No pharmacies or reports need review.</p>
        <?php else: ?>
          <?php if ($pendingCount > 0): ?>
          <a class="ov-attention-row" href="<?= htmlspecialchars(admin_url('?page=pharmacies&filter=pending'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="ov-metric-icon ov-tone-pending ov-icon-sm" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5V20h16v-9.5"/><path d="M2 10.5 12 4l10 6.5"/><path d="M10 20v-6h4v6"/></svg>
            </span>
            <span>
              <strong><?= $pendingCount === 1 ? '1 pharmacy awaiting approval' : number_format($pendingCount) . ' pharmacies awaiting approval' ?></strong>
              <small>New registration submitted</small>
            </span>
            <span class="ov-chevron" aria-hidden="true">›</span>
          </a>
          <?php endif; ?>
          <?php if ($stats['reports_open'] > 0): ?>
          <a class="ov-attention-row" href="<?= htmlspecialchars(admin_url('?page=reports'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="ov-metric-icon ov-tone-review ov-icon-sm" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4h9l1 3h4v10H14l-1-3H7v7"/></svg>
            </span>
            <span>
              <strong><?= number_format($stats['reports_open']) ?> report<?= $stats['reports_open'] === 1 ? '' : 's' ?> under review</strong>
              <small><?= htmlspecialchars($reportAttentionDetail, ENT_QUOTES, 'UTF-8') ?></small>
            </span>
            <span class="ov-chevron" aria-hidden="true">›</span>
          </a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <a class="ov-outline-btn" href="<?= htmlspecialchars(admin_url('?page=reports'), ENT_QUOTES, 'UTF-8') ?>">View all reports <span aria-hidden="true">›</span></a>
    </article>
  </section>

  <section class="admin-card ov-panel">
    <h3>Recent activity</h3>
    <?php if ($activity === []): ?>
    <div class="ov-empty-wrap">
      <p class="ov-empty">No recent pharmacy or report activity yet.</p>
    </div>
    <?php else: ?>
    <ul class="ov-activity">
      <?php foreach (array_slice($activity, 0, 5) as $item): ?>
      <li>
        <a class="ov-activity-row" href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>">
          <span class="ov-metric-icon ov-icon-sm ov-tone-<?= htmlspecialchars((string) $item['tone'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
            <?php if (($item['kind'] ?? '') === 'report'): ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4h9l1 3h4v10H14l-1-3H7v7"/></svg>
            <?php else: ?>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5V20h16v-9.5"/><path d="M2 10.5 12 4l10 6.5"/><path d="M10 20v-6h4v6"/></svg>
            <?php endif; ?>
          </span>
          <span class="ov-activity-copy">
            <strong><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></strong>
            <small><?= htmlspecialchars((string) $item['detail'], ENT_QUOTES, 'UTF-8') ?></small>
          </span>
          <span class="status-badge <?= htmlspecialchars((string) $item['status_class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="ov-activity-time"><?= htmlspecialchars(admin_format_relative((string) $item['time']), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<?php elseif ($adminPage === 'pharmacies'): ?>
<?php
  $tableFilter = strtolower(trim((string) ($_GET['filter'] ?? 'all')));
  if (!in_array($tableFilter, ['all', 'pending', 'approved', 'rejected', 'blocked'], true)) {
      $tableFilter = 'all';
  }
  $tableFrom = trim((string) ($_GET['from'] ?? ''));
  $tableTo = trim((string) ($_GET['to'] ?? ''));
  $listedStatuses = static function (string $status) use ($tableFilter): bool {
      $isPending = $status === 'pending';
      $isApproved = $status === 'approved' || $status === 'active';
      $isRejected = $status === 'rejected';
      $isBlocked = $status === 'blocked';
      if ($tableFilter === 'pending') {
          return $isPending;
      }
      if ($tableFilter === 'approved') {
          return $isApproved;
      }
      if ($tableFilter === 'rejected') {
          return $isRejected;
      }
      if ($tableFilter === 'blocked') {
          return $isBlocked;
      }

      return $isPending || $isApproved || $isRejected || $isBlocked;
  };
  $pharmacyRows = array_values(array_filter(
      $stats['all_pharmacies'],
      static function (array $pharmacy) use ($listedStatuses): bool {
          return $listedStatuses(strtolower((string) ($pharmacy['status'] ?? 'pending')));
      }
  ));
  $pharmacyRows = admin_filter_by_date($pharmacyRows, $tableFrom, $tableTo);
  // If a status/date filter no longer contains the selected pharmacy, close its detail panel.
  if ($viewPharmacy) {
      $selectedStillListed = array_filter(
          $pharmacyRows,
          static fn (array $pharmacy): bool => (string) ($pharmacy['id'] ?? '') === $viewPharmacyId
      ) !== [];
      if (!$selectedStillListed) {
          $viewPharmacy = null;
          $viewPharmacyId = '';
      }
  }
  // Keep the list compact while the detail panel is open; otherwise show six cards.
  $pharmacyPageSize = $viewPharmacy ? 3 : 6;
  $pharmacyPaged = admin_paginate($pharmacyRows, (int) ($_GET['p'] ?? 1), $pharmacyPageSize);
  $pharmacyQuery = static function (array $overrides = []) use ($tableFilter, $tableFrom, $tableTo, $viewPharmacyId): string {
      $params = array_merge([
          'page' => 'pharmacies',
          'filter' => $tableFilter,
          'from' => $tableFrom,
          'to' => $tableTo,
          // Keep the selected pharmacy open while moving through pages.
          'pharmacy' => $viewPharmacyId,
      ], $overrides);

      return admin_url('?' . http_build_query(array_filter($params, static fn ($value) => $value !== '' && $value !== null)));
  };
  $pharmTotal = (int) $stats['pharmacies_total'];
  $pharmApproved = (int) $stats['pharmacies_approved'];
  $pharmPending = (int) $stats['pharmacies_pending'];
  $pharmRejected = (int) $stats['pharmacies_rejected'];
  $pharmBlocked = (int) $stats['pharmacies_blocked'];
  $pharmApprovedPct = admin_percent($pharmApproved, $pharmTotal);
  $pharmPendingPct = admin_percent($pharmPending, $pharmTotal);
  $pharmRejectedPct = admin_percent($pharmRejected, $pharmTotal);
  $pharmBlockedPct = admin_percent($pharmBlocked, $pharmTotal);
?>
<section class="pharm-layout">
<section class="pharm-stats" aria-label="Pharmacy statistics">
  <article class="admin-card pharm-stat">
    <span class="pharm-stat-icon pharm-stat-icon--total" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5V20h18v-9.5"/><path d="M2 10.5 12 4l10 6.5"/><path d="M9 20v-5h6v5"/><path d="M10 10.5h4"/></svg>
    </span>
    <div>
      <div class="pharm-stat-line">
        <div class="pharm-stat-value"><?= number_format($pharmTotal) ?></div>
      </div>
      <div class="pharm-stat-label">Total pharmacies</div>
    </div>
  </article>
  <article class="admin-card pharm-stat">
    <span class="pharm-stat-icon pharm-stat-icon--approved" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.3 2.3 4.7-5"/></svg>
    </span>
    <div>
      <div class="pharm-stat-line">
        <div class="pharm-stat-value"><?= number_format($pharmApproved) ?></div>
        <span class="pharm-stat-pill pharm-stat-pill--up"><?= htmlspecialchars($pharmApprovedPct, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="pharm-stat-label">Approved</div>
    </div>
  </article>
  <article class="admin-card pharm-stat">
    <span class="pharm-stat-icon pharm-stat-icon--pending" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
    </span>
    <div>
      <div class="pharm-stat-line">
        <div class="pharm-stat-value"><?= number_format($pharmPending) ?></div>
        <span class="pharm-stat-pill"><?= htmlspecialchars($pharmPendingPct, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="pharm-stat-label">Pending</div>
    </div>
  </article>
  <article class="admin-card pharm-stat">
    <span class="pharm-stat-icon pharm-stat-icon--rejected" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/></svg>
    </span>
    <div>
      <div class="pharm-stat-line">
        <div class="pharm-stat-value"><?= number_format($pharmRejected) ?></div>
        <span class="pharm-stat-pill pharm-stat-pill--down"><?= htmlspecialchars($pharmRejectedPct, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="pharm-stat-label">Rejected</div>
    </div>
  </article>
  <article class="admin-card pharm-stat">
    <span class="pharm-stat-icon pharm-stat-icon--blocked" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m7.5 7.5 9 9"/></svg>
    </span>
    <div>
      <div class="pharm-stat-line">
        <div class="pharm-stat-value"><?= number_format($pharmBlocked) ?></div>
        <span class="pharm-stat-pill pharm-stat-pill--down"><?= htmlspecialchars($pharmBlockedPct, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div class="pharm-stat-label">Blocked</div>
    </div>
  </article>
</section>
<section class="card-list">
  <div class="card-list-title">
    <h3>Pharmacies</h3>
  </div>
  <div class="card-list-toolbar table-toolbar">
    <nav class="table-tabs">
      <a class="<?= $tableFilter === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars($pharmacyQuery(['filter' => 'all', 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>">All</a>
      <a class="<?= $tableFilter === 'pending' ? 'active' : '' ?>" href="<?= htmlspecialchars($pharmacyQuery(['filter' => 'pending', 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>">Pending</a>
      <a class="<?= $tableFilter === 'approved' ? 'active' : '' ?>" href="<?= htmlspecialchars($pharmacyQuery(['filter' => 'approved', 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>">Approved</a>
      <a class="<?= $tableFilter === 'rejected' ? 'active' : '' ?>" href="<?= htmlspecialchars($pharmacyQuery(['filter' => 'rejected', 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>">Rejected</a>
      <a class="<?= $tableFilter === 'blocked' ? 'active' : '' ?>" href="<?= htmlspecialchars($pharmacyQuery(['filter' => 'blocked', 'p' => 1]), ENT_QUOTES, 'UTF-8') ?>">Blocked</a>
    </nav>
    <form class="table-dates" method="get" action="">
      <input type="hidden" name="page" value="pharmacies">
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
<div class="pharm-workspace<?= ($viewPharmacy && !$viewPharmacyModal) ? ' has-detail' : '' ?>">
<section class="pharm-list-column">
  <div class="pharm-cards-wrap">
        <?php if ($pharmacyPaged['items'] === []): ?>
    <p class="pharm-cards-empty">No pharmacies match this filter yet.</p>
        <?php else: ?>
    <div class="pharm-cards">
          <?php foreach ($pharmacyPaged['items'] as $pharmacy): ?>
          <?php
            $badge = admin_status_badge((string) ($pharmacy['status'] ?? 'pending'));
        $cardSchedule = admin_pharmacy_card_schedule($pharmacy);
            $avatarTone = abs(crc32((string) ($pharmacy['pharmacy_name'] ?? 'Rx'))) % 4;
        $cardLogoPath = (string) ($pharmacy['logo_path'] ?? '');
        $cardLogoExists = $cardLogoPath !== '' && is_file(dirname(__DIR__) . '/' . $cardLogoPath);
        $cardContact = residence_format_contact((string) ($pharmacy['contact_number'] ?? ''));
        $cardAddress = trim((string) ($pharmacy['address'] ?? ''));
        $cardViewUrl = $pharmacyQuery(['pharmacy' => (string) ($pharmacy['id'] ?? '')]);
      ?>
      <article class="pharm-card<?= $viewPharmacyId === (string) ($pharmacy['id'] ?? '') ? ' is-active' : '' ?>">
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
            <img src="<?= htmlspecialchars(app_url($cardLogoPath), ENT_QUOTES, 'UTF-8') ?>" alt="">
          </div>
          <?php else: ?>
          <div class="pharm-card-avatar pharm-avatar-<?= $avatarTone ?>">
                  <?= htmlspecialchars(admin_initials((string) ($pharmacy['pharmacy_name'] ?? 'Rx')), ENT_QUOTES, 'UTF-8') ?>
                </div>
          <?php endif; ?>
        </div>
        <div class="pharm-card-body">
          <div class="pharm-card-info">
            <h4><?= htmlspecialchars((string) ($pharmacy['pharmacy_name'] ?? 'Pharmacy'), ENT_QUOTES, 'UTF-8') ?></h4>
            <p class="pharm-card-meta">
              <span><?= htmlspecialchars($cardContact !== '' ? $cardContact : 'No contact number', ENT_QUOTES, 'UTF-8') ?></span>
              <span class="status-pill <?= htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <p class="pharm-card-address"><?= htmlspecialchars($cardAddress !== '' ? $cardAddress : 'No address provided', ENT_QUOTES, 'UTF-8') ?></p>
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
  <?php if ($pharmacyRows !== []): ?>
  <nav class="mini-pager" aria-label="Pharmacies pages">
    <a class="<?= $pharmacyPaged['page'] <= 1 ? 'is-disabled' : '' ?>" href="<?= htmlspecialchars($pharmacyQuery(['p' => max(1, $pharmacyPaged['page'] - 1)]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous page">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6 9 12l6 6"/></svg>
    </a>
    <?php foreach (admin_pagination_pages((int) $pharmacyPaged['page'], (int) $pharmacyPaged['pages']) as $pageNumber): ?>
      <?php if ($pageNumber === '...'): ?>
      <span class="mini-pager-ellipsis" aria-hidden="true">…</span>
      <?php elseif ((int) $pageNumber === (int) $pharmacyPaged['page']): ?>
      <span class="mini-pager-page" aria-current="page"><?= (int) $pageNumber ?></span>
      <?php else: ?>
      <a class="mini-pager-number" href="<?= htmlspecialchars($pharmacyQuery(['p' => (int) $pageNumber]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Page <?= (int) $pageNumber ?>"><?= (int) $pageNumber ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <a class="<?= $pharmacyPaged['page'] >= $pharmacyPaged['pages'] ? 'is-disabled' : '' ?>" href="<?= htmlspecialchars($pharmacyQuery(['p' => min($pharmacyPaged['pages'], $pharmacyPaged['page'] + 1)]), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next page">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
    </a>
  </nav>
  <?php endif; ?>
</section>
<?php if ($viewPharmacy): ?>
<?php
  $pharmacyStatus = strtolower((string) ($viewPharmacy['status'] ?? 'pending'));
  $operationDays = $viewPharmacy['operation_days'] ?? [];
  if (!is_array($operationDays)) {
      $operationDays = [];
  }
  $openTime = pharmacy_accounts_format_time((string) ($viewPharmacy['open_time'] ?? ''));
  $closeTime = pharmacy_accounts_format_time((string) ($viewPharmacy['close_time'] ?? ''));
  $logoPath = (string) ($viewPharmacy['logo_path'] ?? '');
  $logoExists = $logoPath !== '' && is_file(dirname(__DIR__) . '/' . $logoPath);
  $pharmacyDocs = [
      ['label' => 'Business permit', 'path' => (string) ($viewPharmacy['business_permit_path'] ?? '')],
      ['label' => 'Pharmacy license', 'path' => (string) ($viewPharmacy['pharmacy_license_path'] ?? '')],
      ['label' => 'BIR certificate', 'path' => (string) ($viewPharmacy['bir_certificate_path'] ?? '')],
  ];
  $pharmacyId = (string) ($viewPharmacy['id'] ?? '');
  // Closing the detail panel removes only the selected-pharmacy parameter.
  $pharmacyReviewListQuery = $pharmacyQuery(['pharmacy' => '']);
  $contact = residence_format_contact((string) ($viewPharmacy['contact_number'] ?? ''));
  $existingAdminNote = trim((string) ($viewPharmacy['admin_note'] ?? ''));
  $needsDecisionNote = in_array($pharmacyStatus, ['approved', 'active', 'pending'], true);
  $showPharmacyActions = !in_array($pharmacyStatus, ['blocked', 'rejected'], true);
  $rejectReasons = admin_pharmacy_reject_reasons();
  $blockReasons = admin_pharmacy_block_reasons();
?>
<?php if (!$viewPharmacyModal): ?>
<aside class="pharm-detail-panel" aria-label="Pharmacy details">
  <?php
    $pharmacyReviewContext = 'panel';
    require __DIR__ . '/partials/pharmacy-review.php';
  ?>
</aside>
<?php endif; ?>
<?php endif; ?>
</div>
</section>

<?php if ($viewPharmacy && $viewPharmacyModal): ?>
<div class="rx-popup" role="dialog" aria-modal="true" aria-labelledby="rx-popup-title">
  <a class="rx-popup-backdrop" href="<?= htmlspecialchars($pharmacyReviewListQuery, ENT_QUOTES, 'UTF-8') ?>" aria-label="Close review"></a>
          <?php
    $pharmacyReviewContext = 'modal';
    require __DIR__ . '/partials/pharmacy-review.php';
  ?>
</div>
<?php endif; ?>

<?php else: ?>
<?php require __DIR__ . '/partials/reports-page.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/shell-end.php'; ?>
