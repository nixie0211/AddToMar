<?php
$u = current_user();
$is_customer = $u && $u['role'] === 'customer';
$notif_count = $u ? unread_notification_count($pdo, $u['id']) : 0;
$cart_qty = ($is_customer) ? cart_count($pdo, $u['id']) : 0;
$hero_layout = $hero_layout ?? false;
$current = basename($_SERVER['PHP_SELF']);

$customer_links = [
  ['dashboard.php', 'Dashboard'],
  ['medicine-finder.php', 'Medicine Finder'],
  ['catalog.php', 'Medicine Catalog'],
  ['cart.php', 'Shopping Cart'],
  ['orders.php', 'My Orders'],
  ['favorites.php', 'Favorites'],
  ['profile.php', 'Profile'],
];
$pharmacist_links = [
  ['dashboard.php', 'Dashboard'],
  ['inventory.php', 'Inventory'],
  ['pharmacies.php', 'Pharmacies'],
  ['orders.php', 'Orders'],
  ['customers.php', 'Customers'],
  ['finder-analytics.php', 'Finder Analytics'],
  ['../reports/inventory-report.php', 'Reports'],
  ['settings.php', 'Settings'],
];
$nav_links = $is_customer ? $customer_links : $pharmacist_links;
$nav_base = $is_customer ? BASE_URL . 'customer/' : BASE_URL . 'pharmacist/';
?>
<nav class="navbar navbar-expand-lg app-navbar <?php echo $hero_layout ? 'hero-navbar' : ''; ?> <?php echo $hero_layout ? '' : 'sticky-top'; ?>">
  <div class="container-fluid px-3 px-lg-4">
    <button class="btn btn-icon d-lg-none me-2 <?php echo $hero_layout ? 'd-none' : ''; ?>" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <?php if ($hero_layout): ?>
    <div class="dropdown d-lg-none me-2">
      <button class="btn btn-icon" data-bs-toggle="dropdown"><i class="bi bi-list"></i></button>
      <ul class="dropdown-menu">
        <?php foreach ($nav_links as [$href, $label]):
          $file = basename($href);
          $active = ($file === $current) || ($label === 'Reports' && str_contains($current, 'report')) ? 'active' : ''; ?>
          <li><a class="dropdown-item <?php echo $active; ?>" href="<?php echo $nav_base . $href; ?>"><?php echo $label; ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?><?php echo $is_customer ? 'customer/dashboard.php' : 'pharmacist/dashboard.php'; ?>">
      <span class="brand-mark"><i class="bi bi-capsule"></i></span>
      <span class="brand-stack">
        <span class="brand-text">AddToMar</span>
        <?php if ($hero_layout): ?><span class="brand-sub">Pharmacy System</span><?php endif; ?>
      </span>
    </a>

    <?php if ($hero_layout): ?>
    <div class="hero-nav-links d-none d-lg-flex">
      <?php foreach ($nav_links as [$href, $label]):
        $file = basename($href);
        $active = ($file === $current) || ($label === 'Reports' && str_contains($current, 'report')) ? 'active' : ''; ?>
        <a href="<?php echo $nav_base . $href; ?>" class="hero-nav-link <?php echo $active; ?>"><?php echo $label; ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="ms-auto d-flex align-items-center gap-2">
      <button class="btn btn-icon" id="darkModeToggle" title="Toggle dark mode"><i class="bi bi-moon-stars"></i></button>

      <?php if ($is_customer): ?>
      <a href="<?php echo BASE_URL; ?>customer/cart.php" class="btn btn-icon position-relative" title="Cart">
        <i class="bi bi-cart3"></i>
        <?php if ($cart_qty > 0): ?><span class="badge-dot"><?php echo $cart_qty; ?></span><?php endif; ?>
      </a>
      <?php endif; ?>

      <div class="dropdown">
        <button class="btn btn-icon position-relative" data-bs-toggle="dropdown" title="Notifications">
          <i class="bi bi-bell"></i>
          <?php if ($notif_count > 0): ?><span class="badge-dot"><?php echo $notif_count; ?></span><?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end notif-panel p-2">
          <h6 class="px-2 pt-1">Notifications</h6>
          <?php
          $ns = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 6");
          $ns->execute([$u['id'] ?? 0]);
          $rows = $ns->fetchAll();
          if (!$rows): ?>
            <p class="text-muted small px-2 mb-1">No notifications yet.</p>
          <?php else: foreach ($rows as $n): ?>
            <div class="notif-item <?php echo $n['status']==='unread' ? 'unread' : ''; ?>">
              <strong class="d-block small"><?php echo clean($n['title']); ?></strong>
              <span class="d-block small text-muted"><?php echo clean($n['message']); ?></span>
              <span class="d-block text-muted" style="font-size:11px;"><?php echo date('M d, g:i A', strtotime($n['created_at'])); ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="dropdown">
        <button class="btn user-chip dropdown-toggle" data-bs-toggle="dropdown">
          <span class="avatar-circle"><?php echo strtoupper(substr($u['fullname'] ?? 'U', 0, 2)); ?></span>
          <span class="d-none d-md-inline"><?php echo clean($u['fullname'] ?? ''); ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?php echo BASE_URL; ?><?php echo $is_customer ? 'customer/profile.php' : 'pharmacist/settings.php'; ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
