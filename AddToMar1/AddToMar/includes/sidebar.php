<?php
$u = current_user();
$role = $u['role'] ?? 'customer';
$current = basename($_SERVER['PHP_SELF']);

$customer_links = [
  ['dashboard.php', 'bi-grid-1x2', 'Dashboard'],
  ['catalog.php', 'bi-capsule', 'Medicine Catalog'],
  ['cart.php', 'bi-cart3', 'Shopping Cart'],
  ['orders.php', 'bi-bag-check', 'My Orders'],
  ['profile.php', 'bi-person-circle', 'Profile'],
];
$pharmacist_links = [
  ['dashboard.php', 'bi-grid-1x2', 'Dashboard'],
  ['inventory.php', 'bi-boxes', 'Inventory'],
  ['orders.php', 'bi-bag-check', 'Orders'],
  ['customers.php', 'bi-people', 'Customers'],
  ['../reports/inventory-report.php', 'bi-clipboard-data', 'Reports'],
  ['settings.php', 'bi-gear', 'Settings'],
];
$links = $role === 'pharmacist' ? $pharmacist_links : $customer_links;
$base = $role === 'pharmacist' ? BASE_URL . 'pharmacist/' : BASE_URL . 'customer/';
?>
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-inner">
    <div class="sidebar-role-badge">
      <i class="bi <?php echo $role === 'pharmacist' ? 'bi-shield-check' : 'bi-person-heart'; ?>"></i>
      <?php echo $role === 'pharmacist' ? 'Pharmacist Panel' : 'Customer Panel'; ?>
    </div>
    <ul class="sidebar-nav">
      <?php foreach ($links as [$href, $icon, $label]):
        $file = basename($href);
        $active = ($file === $current) ? 'active' : ''; ?>
        <li>
          <a href="<?php echo $base . $href; ?>" class="<?php echo $active; ?>">
            <i class="bi <?php echo $icon; ?>"></i>
            <span><?php echo $label; ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
