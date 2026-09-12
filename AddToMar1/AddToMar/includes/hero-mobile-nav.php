<?php if (empty($hero_layout)) return;
$current = basename($_SERVER['PHP_SELF']);
$is_customer = ($u ?? current_user())['role'] === 'customer';

$mobile_links = $is_customer ? [
  ['dashboard.php', 'bi-grid-1x2', 'Home'],
  ['catalog.php', 'bi-capsule', 'Catalog'],
  ['cart.php', 'bi-cart3', 'Cart'],
  ['orders.php', 'bi-bag-check', 'Orders'],
  ['profile.php', 'bi-person', 'Profile'],
] : [
  ['dashboard.php', 'bi-grid-1x2', 'Home'],
  ['inventory.php', 'bi-boxes', 'Stock'],
  ['orders.php', 'bi-bag-check', 'Orders'],
  ['customers.php', 'bi-people', 'Users'],
  ['settings.php', 'bi-gear', 'Settings'],
];
$mobile_base = $is_customer ? BASE_URL . 'customer/' : BASE_URL . 'pharmacist/';
?>
<nav class="hero-mobile-nav d-lg-none" aria-label="Mobile navigation">
  <?php foreach ($mobile_links as [$href, $icon, $label]):
    $file = basename($href);
    $active = ($file === $current) ? 'active' : ''; ?>
    <a href="<?php echo $mobile_base . $href; ?>" class="<?php echo $active; ?>">
      <i class="bi <?php echo $icon; ?>"></i>
      <span><?php echo $label; ?></span>
    </a>
  <?php endforeach; ?>
</nav>
