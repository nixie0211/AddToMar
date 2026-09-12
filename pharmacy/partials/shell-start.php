<div id="app-shell">
  <aside class="sidebar" data-live-region="pharmacy-nav" data-live-keys="shell,orders,inventory">
    <a class="brand-mark" href="<?= htmlspecialchars(function_exists('app_url') ? app_url('pharmacy/') : './', ENT_QUOTES, 'UTF-8') ?>" aria-label="AddToMar home">
      <span class="brand-logo" aria-hidden="true">
        <img src="<?= htmlspecialchars(function_exists('app_url') ? app_url('2.png') : '../2.png', ENT_QUOTES, 'UTF-8') ?>" alt="" width="40" height="40">
      </span>
      <span class="brand-copy"><span class="brand-name">AddToMar</span><span class="brand-subtitle">Pharmacy Inventory</span></span>
    </a>

    <div class="nav-group-label">Overview</div>
    <div class="nav-item<?= $activeView === 'dashboard' ? ' active' : '' ?>" data-view="dashboard" onclick="showView('dashboard', this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
      Dashboard
    </div>
    <div class="nav-item<?= $activeView === 'inventory' ? ' active' : '' ?>" data-view="inventory" onclick="showView('inventory', this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
      Inventory
      <?php if ((int) ($stats['lowStock'] ?? 0) > 0): ?>
      <span class="badge-count"><?= (int) $stats['lowStock'] ?></span>
      <?php endif; ?>
    </div>
    <div class="nav-item<?= $activeView === 'orders' ? ' active' : '' ?>" data-view="orders" onclick="showView('orders', this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg>
      Orders
      <?php if ((int) ($stats['pendingOrders'] ?? 0) > 0): ?>
      <span class="badge-count"><?= (int) $stats['pendingOrders'] ?></span>
      <?php endif; ?>
    </div>

    <div class="nav-group-label">Business</div>
    <div class="nav-item" data-view="sales" onclick="showView('sales', this)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18M7 15l4-5 3 3 5-7"/></svg>
      Sales
    </div>

    <div class="nav-group-label">Insights</div>
    <div class="nav-item" data-view="reports" onclick="showView('reports', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6M9 9h1"/></svg>Reports</div>
    <div class="nav-item" data-view="analytics" onclick="showView('analytics', this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>Analytics</div>

    <div class="sidebar-foot">
      <div class="sidebar-summary">
        <strong>Today's Summary</strong>
        <div><span>Total Medicines</span><b><?= number_format((int) ($stats['totalMedicines'] ?? 0)) ?></b></div>
        <div><span>Low Stock</span><b><?= number_format((int) ($stats['lowStock'] ?? 0)) ?></b></div>
        <div><span>Expiring Soon</span><b><?= number_format((int) ($stats['expiring'] ?? 0)) ?></b></div>
      </div>
      <a class="sidebar-logout" href="<?= htmlspecialchars(function_exists('app_url') ? app_url('logout.php') : '../logout.php', ENT_QUOTES, 'UTF-8') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
        Logout
      </a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
<?php include PHARMACY_ROOT . '/partials/topbar-heads.php'; ?>
      <div class="topbar-actions">
        <div class="notif-wrap" data-live-region="pharmacy-notify" data-live-keys="shell,orders,inventory">
          <button class="icon-btn" onclick="toggleNotifDropdown(event)" title="Notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9zM13.73 21a2 2 0 0 1-3.46 0"/></svg><?php if (!empty($notifications)): ?><span class="dot-badge"></span><?php endif; ?></button>

          <div class="notif-dropdown" id="notif-dropdown">
            <div class="notif-head">
              <h3>Notifications</h3>
            </div>
            <div class="notif-tabs">
              <button class="notif-tab active" onclick="filterNotifTab('all', this)">All</button>
              <button class="notif-tab" onclick="filterNotifTab('unread', this)">Unread</button>
            </div>

            <div class="notif-scroll">
              <?php if (empty($notifications)): ?>
              <p class="empty-state" style="margin:12px 8px; border:none; background:transparent;">No notifications yet.</p>
              <?php else: ?>
              <?php
                $notifGroups = [
                  'new-order' => 'New orders',
                  'low-stock' => 'Low stock',
                  'expiring' => 'Expiring soon',
                ];
                $notifTones = [
                  'new-order' => 'linear-gradient(135deg,var(--teal),var(--teal-light))',
                  'low-stock' => 'linear-gradient(135deg,var(--amber),#D97706)',
                  'expiring' => 'linear-gradient(135deg,var(--red),#B91C1C)',
                ];
                $notifIconBg = [
                  'new-order' => 'var(--teal)',
                  'low-stock' => 'var(--amber)',
                  'expiring' => 'var(--red)',
                ];
              ?>
              <div id="notif-panel-recent">
              <?php foreach ($notifGroups as $type => $label): ?>
              <?php
                $groupItems = array_values(array_filter($notifications, static fn(array $item): bool => ($item['type'] ?? '') === $type));
                if ($groupItems === []) {
                    continue;
                }
              ?>
              <div class="notif-section-head"><span class="title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span><span class="see-all" onclick="showNotifTypeInPanel(event, '<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>')">See all</span></div>
              <?php foreach ($groupItems as $item): ?>
              <?php $itemType = (string) ($item['type'] ?? $type); ?>
              <div class="notif-item" data-type="<?= htmlspecialchars($itemType, ENT_QUOTES, 'UTF-8') ?>" data-unread="<?= !empty($item['unread']) ? '1' : '0' ?>">
                <div class="notif-avatar-wrap">
                  <div class="notif-avatar" style="background:<?= htmlspecialchars($notifTones[$itemType] ?? 'var(--teal)', ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars((string) ($item['initials'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="notif-badge-icon" style="background:<?= htmlspecialchars($notifIconBg[$itemType] ?? 'var(--teal)', ENT_QUOTES, 'UTF-8') ?>;">
                    <?php if ($itemType === 'new-order'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg>
                    <?php elseif ($itemType === 'low-stock'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="notif-body">
                  <div class="notif-text"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></div>
                  <?php if (!empty($item['subtitle'])): ?>
                  <div class="notif-sub"><?= htmlspecialchars((string) $item['subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                  <div class="notif-time"><?= htmlspecialchars((string) ($item['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if (!empty($item['unread'])): ?>
                <div class="notif-dot"></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              <?php endforeach; ?>
              </div>

              <div id="notif-panel-all" hidden>
              <div class="notif-section-head"><span class="title">All notifications</span></div>
              <?php foreach ($notifications as $item): ?>
              <?php $itemType = (string) ($item['type'] ?? ''); ?>
              <div class="notif-item" data-type="<?= htmlspecialchars($itemType, ENT_QUOTES, 'UTF-8') ?>" data-unread="<?= !empty($item['unread']) ? '1' : '0' ?>">
                <div class="notif-avatar-wrap">
                  <div class="notif-avatar" style="background:<?= htmlspecialchars($notifTones[$itemType] ?? 'var(--teal)', ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars((string) ($item['initials'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="notif-badge-icon" style="background:<?= htmlspecialchars($notifIconBg[$itemType] ?? 'var(--teal)', ENT_QUOTES, 'UTF-8') ?>;">
                    <?php if ($itemType === 'new-order'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg>
                    <?php elseif ($itemType === 'low-stock'): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg>
                    <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="notif-body">
                  <div class="notif-text"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></div>
                  <?php if (!empty($item['subtitle'])): ?>
                  <div class="notif-sub"><?= htmlspecialchars((string) $item['subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                  <div class="notif-time"><?= htmlspecialchars((string) ($item['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php if (!empty($item['unread'])): ?>
                <div class="notif-dot"></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>

            <?php if (!empty($notifications)): ?>
            <div class="notif-foot"><a href="javascript:void(0)" id="notif-see-previous" onclick="toggleNotifHistory(event)">See previous notifications</a></div>
            <?php endif; ?>
          </div>
        </div>
        <button class="icon-btn" onclick="showView('settings')" title="Settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.6.5 1.13 1 1.51H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></button>
        <div class="topbar-profile" title="Pharmacy profile">
          <div class="avatar avatar--profile<?= $pharmacyLogoUrl !== '' ? ' has-logo' : '' ?>">
            <?php if ($pharmacyLogoUrl !== ''): ?>
            <img src="<?= htmlspecialchars($pharmacyLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="avatar-logo">
            <?php else: ?>
            <?= htmlspecialchars($pharmacyInitials, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
          </div>
          <div>
            <div class="name"><?= htmlspecialchars($pharmacyName, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
      </div>
    </header>

    <div class="content">
