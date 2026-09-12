    <?php
    $headerUserAddress = trim((string) ($residenceProfile['address'] ?? ''));
    $headerUserHasLocation = ($residenceProfile['latitude'] ?? null) !== null
        && ($residenceProfile['longitude'] ?? null) !== null;
    $headerBuyerName = trim((string) ($residenceProfile['full_name'] ?? 'Customer'));
    $headerBuyerParts = preg_split('/\s+/', $headerBuyerName) ?: [];
    $headerBuyerInitials = '';
    foreach (array_slice($headerBuyerParts, 0, 2) as $headerBuyerPart) {
        $headerBuyerInitials .= strtoupper(substr($headerBuyerPart, 0, 1));
    }
    $headerBuyerInitials = $headerBuyerInitials !== '' ? $headerBuyerInitials : 'CU';
    $headerUnreadNotifications = count(array_filter(
        $residenceNotifications ?? [],
        static fn (array $notification): bool => empty($notification['read_at'])
    ));
    ?>
    <header class="app-topbar">
      <div class="app-location-bar">
        <div class="app-location-bar-inner">
          <?php if ($headerUserHasLocation && $headerUserAddress !== ''): ?>
          <button type="button" class="app-location-chip" onclick="openSavedAddresses()" title="<?= htmlspecialchars($headerUserAddress, ENT_QUOTES, 'UTF-8') ?>">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.6 7-11.5A7 7 0 0 0 5 9.5C5 14.4 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></svg>
            <span class="app-location-text"><?= htmlspecialchars($headerUserAddress, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="app-location-change">Change</span>
          </button>
          <?php elseif ($headerUserHasLocation): ?>
          <button type="button" class="app-location-chip" onclick="openSavedAddresses()" title="Saved pickup coordinates">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.6 7-11.5A7 7 0 0 0 5 9.5C5 14.4 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></svg>
            <span class="app-location-text">Your saved location</span>
            <span class="app-location-change">Change</span>
          </button>
          <?php else: ?>
          <button type="button" class="app-location-chip app-location-chip--empty" onclick="openAddressMapPicker('add')" title="Add your pickup location">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.6 7-11.5A7 7 0 0 0 5 9.5C5 14.4 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></svg>
            <span class="app-location-text">Set your pickup location</span>
            <span class="app-location-change">Add</span>
          </button>
          <?php endif; ?>
        </div>
      </div>
      <div class="app-topbar-inner">
        <a class="app-topbar-brand" href="#" onclick="goHome(); return false;" aria-label="AddToMar home">
          <span class="app-topbar-logo-mark" aria-hidden="true">
            <img src="<?= htmlspecialchars(function_exists('app_url') ? app_url('2.png') : '../2.png', ENT_QUOTES, 'UTF-8') ?>" alt="" width="48" height="48">
          </span>
          <span class="app-topbar-logo">AddToMar</span>
        </a>

        <div class="app-topbar-center">
          <div class="home-search-row">
            <div class="home-search-field" id="home-search-field">
              <div class="home-search">
                <span class="home-search-icon" aria-hidden="true">
                  <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </span>
                <input
                  type="search"
                  id="medicine-search"
                  class="home-search-input"
                  placeholder="Search medicines, e.g. Paracetamol, Amoxicillin..."
                  aria-label="Search medicines"
                  autocomplete="off"
                  role="combobox"
                  aria-autocomplete="list"
                  aria-expanded="false"
                  aria-controls="medicine-search-suggestions"
                >
              </div>
              <ul id="medicine-search-suggestions" class="med-search-suggestions" role="listbox" aria-label="Medicine suggestions" hidden></ul>
            </div>
            <div class="home-locator-group">
              <button type="button" class="home-locator-btn" onclick="go('locator')">
                <svg class="icon" viewBox="0 0 24 24"><path d="M12 21s7-6.6 7-11.5A7 7 0 0 0 5 9.5C5 14.4 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></svg>
                <span>Locator</span>
              </button>
            </div>
          </div>
        </div>

        <nav class="app-topbar-actions" aria-label="Quick navigation">
          <div class="topbar-notification-menu" id="topbar-notification-menu" data-live-region="residence-notify-menu" data-live-keys="notifications,reports,orders">
            <button type="button" class="topbar-nav-item" onclick="toggleNotificationMenu(event)" aria-label="Open notifications" aria-expanded="false">
              <span class="topbar-nav-icon">
                <svg class="icon" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 6 2 6H4c.5 0 2-2 2-6Z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>
                <b class="topbar-count-badge" id="sb-notif-count"<?php if ($headerUnreadNotifications < 1): ?> hidden<?php endif; ?> aria-label="<?= $headerUnreadNotifications ?> unread notifications"><?= $headerUnreadNotifications > 99 ? '99+' : (int) $headerUnreadNotifications ?></b>
              </span>
              <span>Notification</span>
            </button>
            <div class="topbar-notification-dropdown" role="dialog" aria-label="Notifications" hidden data-has-more="<?= count($residenceNotifications ?? []) > 3 ? '1' : '0' ?>">
              <div class="topbar-notification-head"><h3>Notifications</h3></div>
              <div class="topbar-notification-tabs"><button type="button" class="is-active" onclick="filterResidentNotificationTab('all', this, event)">All</button><button type="button" onclick="filterResidentNotificationTab('unread', this, event)">Unread</button></div>
              <div class="topbar-notification-scroll">
                <?php foreach ($residenceNotifications ?? [] as $notification): ?>
                <article class="topbar-notification-item<?= empty($notification['read_at']) ? ' is-unread' : ' is-read' ?>"<?= resident_notification_click_attr($notification) ?>>
                  <span class="topbar-notification-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                  <div class="topbar-notification-copy"><p><?= htmlspecialchars((string) $notification['title'], ENT_QUOTES, 'UTF-8') ?></p><span><?= htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8') ?></span><time><?= htmlspecialchars(date('M j, g:i A', strtotime((string) $notification['created_at'])), ENT_QUOTES, 'UTF-8') ?></time></div>
                  <?php if (empty($notification['read_at'])): ?><i class="topbar-notification-dot" aria-label="Unread"></i><?php endif; ?>
                </article>
                <?php endforeach; ?>
                <p class="topbar-notification-empty" data-notification-empty hidden>No unread notifications</p>
              </div>
              <div class="topbar-notification-footer"<?= count($residenceNotifications ?? []) > 3 ? '' : ' hidden' ?>><button type="button" onclick="expandNotificationHistory(event)">See previous notifications</button></div>
            </div>
          </div>
          <button type="button" class="topbar-nav-item" id="sb-cart-target" data-nav="cart" onclick="go('cart')">
            <span class="topbar-nav-icon">
              <svg class="icon" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.4 12.2a2 2 0 0 0 2 1.6h7.4a2 2 0 0 0 2-1.6L21 7H6"/></svg>
              <em class="sb-cart-count topbar-count-badge" id="sb-cart-count" hidden>0</em>
            </span>
            <span>Cart</span>
          </button>
          <button type="button" class="topbar-nav-item" data-nav="orders" onclick="go('orders')">
            <svg class="icon" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h7M9 16h7M9 8h3"/></svg>
            <span>Orders</span>
          </button>
          <div class="topbar-profile-menu" id="topbar-profile-menu">
            <button type="button" class="topbar-nav-item topbar-profile" onclick="toggleProfileMenu(event)" aria-label="Open profile menu" aria-expanded="false">
              <span class="topbar-profile-avatar"><?= htmlspecialchars($headerBuyerInitials, ENT_QUOTES, 'UTF-8') ?></span>
              <span>Profile</span>
            </button>
            <div class="topbar-profile-dropdown" role="menu" hidden>
              <button type="button" role="menuitem" onclick="go('profile'); closeProfileMenu()">Profile</button>
              <button type="button" role="menuitem" class="is-logout" onclick="logout()">Logout</button>
            </div>
          </div>
        </nav>
      </div>
      <?php include RESIDENCE_ROOT . '/partials/category-nav.php'; ?>
    </header>
