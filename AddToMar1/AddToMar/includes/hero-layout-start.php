<?php
/**
 * Opens the website-style hero layout (no sidebar).
 * Set $hero_badge, $hero_title, $hero_desc before including.
 * Optional: $hero_actions_html for buttons in the hero.
 */
$hero_layout = true;
$body_class = 'hero-dashboard';
$hero_badge = $hero_badge ?? strtoupper($page_title ?? 'Page');
$hero_title = $hero_title ?? ($page_title ?? 'Page');
$hero_desc = $hero_desc ?? '';
$hero_actions_html = $hero_actions_html ?? '';

include __DIR__ . '/header.php';
include __DIR__ . '/navbar.php';
?>
<div class="hero-dashboard-wrap">
  <section class="dashboard-hero dashboard-hero-compact">
    <div class="dashboard-hero-inner fade-in-up">
      <div class="hero-badge"><i class="bi bi-capsule"></i> <?php echo clean($hero_badge); ?></div>
      <h1 class="hero-title hero-title-compact"><?php echo clean($hero_title); ?></h1>
      <?php if ($hero_desc): ?><p class="hero-desc"><?php echo clean($hero_desc); ?></p><?php endif; ?>
      <?php if ($hero_actions_html): ?><div class="hero-actions"><?php echo $hero_actions_html; ?></div><?php endif; ?>
    </div>
    <div class="hero-wave">
      <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <path fill="#ffffff" d="M0,40 C360,90 720,0 1080,40 C1260,60 1380,50 1440,40 L1440,80 L0,80 Z"/>
      </svg>
    </div>
  </section>
  <main class="hero-dashboard-main hero-page-main">
