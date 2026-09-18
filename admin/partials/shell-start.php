<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($adminPageTitle ?? 'Admin Dashboard', ENT_QUOTES, 'UTF-8') ?> — AddToMar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Source+Serif+4:opsz,wght@8..60,600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('admin/css/admin.css'), ENT_QUOTES, 'UTF-8') ?>?v=admin-notify-previous-1">
</head>
<body<?= (!empty($viewPharmacyModal) || !empty($reviewReportModal)) ? ' class="modal-open"' : '' ?> data-admin-mark-read-url="<?= htmlspecialchars(admin_url(), ENT_QUOTES, 'UTF-8') ?>">
<div class="admin-shell">
  <header class="admin-header">
    <a class="admin-brand" href="<?= htmlspecialchars(admin_url(), ENT_QUOTES, 'UTF-8') ?>">
      <img src="<?= htmlspecialchars(app_url('2.png'), ENT_QUOTES, 'UTF-8') ?>" alt="">
      <span>AddToMar</span>
    </a>
    <nav class="admin-side-nav" aria-label="Admin pages">
      <a href="<?= htmlspecialchars(admin_url('?page=overview'), ENT_QUOTES, 'UTF-8') ?>" class="<?= ($adminPage ?? '') === 'overview' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
        Overview
      </a>
      <a href="<?= htmlspecialchars(admin_url('?page=pharmacies'), ENT_QUOTES, 'UTF-8') ?>" class="<?= ($adminPage ?? '') === 'pharmacies' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10.5V20h16v-9.5"/><path d="M2 10.5 12 4l10 6.5"/><path d="M10 20v-6h4v6"/></svg>
        Pharmacies
      </a>
      <a href="<?= htmlspecialchars(admin_url('?page=reports'), ENT_QUOTES, 'UTF-8') ?>" class="<?= ($adminPage ?? '') === 'reports' ? 'active' : '' ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 21V4h9l1 3h4v10H14l-1-3H7v7"/></svg>
        Reports
      </a>
    </nav>
    <div class="admin-sidebar-profile">
      <div class="admin-header-profile-cluster">
      <div class="admin-profile-wrap">
        <div class="admin-profile-bar">
          <div class="admin-notify-wrap" data-live-region="admin-notify" data-live-keys="notifications,admin">
            <button type="button" class="admin-notify-btn" id="admin-notify-btn" aria-haspopup="true" aria-expanded="false" aria-label="<?= $adminNotifyCount > 0 ? number_format($adminNotifyCount) . ' notifications' : 'Notifications' ?>">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M7 9.2a5 5 0 0 1 10 0c0 4.6 2 6.4 2.4 6.8H4.6C5 15.6 7 13.8 7 9.2Z"/>
                <path d="M10.4 18.6a1.7 1.7 0 0 0 3.2 0"/>
              </svg>
              <?php if ($adminNotifyCount > 0): ?>
              <span class="admin-notify-badge"><?= $adminNotifyCount > 9 ? '9+' : number_format($adminNotifyCount) ?></span>
              <?php endif; ?>
            </button>
            <div class="admin-notify-panel" id="admin-notify-panel" hidden data-has-more="<?= count($adminNotifications) > 4 ? '1' : '0' ?>">
              <div class="admin-notify-head">
                <h3>Notifications</h3>
                <?php if ($adminNotifyCount > 0): ?>
                <span id="admin-notify-head-count"><?= $adminNotifyCount === 1 ? '1 new' : number_format($adminNotifyCount) . ' new' ?></span>
                <?php else: ?>
                <span id="admin-notify-head-count" hidden></span>
                <?php endif; ?>
              </div>
              <div class="admin-notify-list" data-live-preserve="admin-notify-scroll">
                <?php if ($adminNotifications === []): ?>
                <p class="admin-notify-empty">No notifications yet.</p>
                <?php else: ?>
                  <?php $adminNotifyIndex = 0; ?>
                  <?php foreach ($adminNotifications as $notification): ?>
                  <?php
                    $isRead = !empty($notification['is_read']);
                    $isLater = $adminNotifyIndex >= 4;
                    $adminNotifyIndex++;
                  ?>
                  <a class="admin-notify-item admin-notify-item--<?= htmlspecialchars((string) $notification['type'], ENT_QUOTES, 'UTF-8') ?><?= $isRead ? ' is-read' : ' is-unread' ?><?= $isLater ? ' is-later' : '' ?>" href="<?= htmlspecialchars((string) $notification['href'], ENT_QUOTES, 'UTF-8') ?>" data-notification-id="<?= htmlspecialchars((string) ($notification['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="admin-notify-item-icon" aria-hidden="true">
                      <?php if (($notification['type'] ?? '') === 'report'): ?>
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4h9l1 3h4v10H14l-1-3H7v7"/></svg>
                      <?php else: ?>
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5V20h16v-9.5"/><path d="M2 10.5 12 4l10 6.5"/><path d="M10 20v-6h4v6"/></svg>
                      <?php endif; ?>
                    </span>
                    <span class="admin-notify-item-copy">
                      <strong><?= htmlspecialchars((string) $notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                      <small><?= htmlspecialchars((string) $notification['detail'], ENT_QUOTES, 'UTF-8') ?></small>
                    </span>
                    <span class="admin-notify-item-meta">
                    <?php if (!$isRead): ?><span class="admin-notify-item-status is-new">New</span><?php endif; ?>
                      <span class="admin-notify-item-time"><?= htmlspecialchars(admin_format_relative((string) $notification['time']), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                  </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="admin-notify-footer" id="admin-notify-footer"<?= count($adminNotifications) > 4 ? '' : ' hidden' ?>>
                <button type="button" id="admin-notify-see-previous">See previous notifications</button>
              </div>
            </div>
          </div>
          <span class="admin-avatar-wrap">
            <span class="admin-avatar" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="12" cy="8.2" r="3.35"/>
                <path d="M5.1 18.9c.85-3.35 3.35-5.15 6.9-5.15s6.05 1.8 6.9 5.15A11.6 11.6 0 0 1 12 20.2c-2.55 0-4.95-.45-6.9-1.3Z"/>
              </svg>
            </span>
          </span>
          <button class="admin-profile" type="button" id="admin-profile-btn" aria-haspopup="true" aria-expanded="false" aria-label="Open profile menu">
            <span class="admin-profile-name">Admin</span>
            <span class="admin-profile-caret-btn" aria-hidden="true">
              <svg class="admin-profile-caret" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </span>
        </button>
        </div>
        <div class="admin-profile-menu" id="admin-profile-menu" hidden>
          <a href="#" id="open-admin-settings">Profile settings</a>
          <a href="<?= htmlspecialchars(app_url('logout.php'), ENT_QUOTES, 'UTF-8') ?>">Logout</a>
        </div>
        </div>
      </div>
    </div>
  </header>
  <div class="admin-workspace">
  <div class="admin-body">
    <main class="admin-main" data-live-region="admin-main" data-live-keys="admin,pharmacies,reports">
