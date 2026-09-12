<?php if (!defined('BASE_URL')) { require_once __DIR__ . '/../config/database.php'; } ?>
<!DOCTYPE html>
<html lang="en" class="<?php echo !empty($hero_layout) ? 'hero-light' : ''; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? clean($page_title) . ' | AddToMar' : 'AddToMar - Pharmacy Inventory Management System'; ?></title>
<link rel="icon" href="<?php echo BASE_URL; ?>assets/images/favicon.png">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Google Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<!-- SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- App CSS -->
<?php
$asset_dir = __DIR__ . '/../assets';
$css_v = file_exists($asset_dir . '/css/style.css') ? filemtime($asset_dir . '/css/style.css') : time();
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo $css_v; ?>">
<?php if (!empty($hero_layout)):
  $hero_v = file_exists($asset_dir . '/css/dashboard-hero.css') ? filemtime($asset_dir . '/css/dashboard-hero.css') : time();
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard-hero.css?v=<?php echo $hero_v; ?>">
<meta name="theme-color" content="#0a5c38">
<meta name="color-scheme" content="light only">
<style>
  html, html.hero-light, body.hero-dashboard, body.hero-dashboard.dark-mode {
    color-scheme: light only !important;
    background: #ffffff !important;
    color: #0F172A !important;
    --bg: #ffffff !important;
    --white: #ffffff !important;
    --dark: #0F172A !important;
    --border: #E2E8F0 !important;
    --muted: #64748B !important;
  }
  body.hero-dashboard .stat-card,
  body.hero-dashboard .card,
  body.hero-dashboard .hero-stats-card,
  body.hero-dashboard .hero-dashboard-main {
    background: #ffffff !important;
    color: #0F172A !important;
  }
</style>
<?php endif; ?>
</head>
<body class="<?php echo $body_class ?? ''; ?><?php echo !empty($hero_layout) ? ' hero-light' : ''; ?>">
<?php if (!empty($hero_layout)): ?>
<script>
  document.documentElement.classList.add('hero-light');
  document.body.classList.remove('dark-mode');
  try { localStorage.setItem('addtomar_theme', 'light'); } catch(e) {}
</script>
<?php endif; ?>
