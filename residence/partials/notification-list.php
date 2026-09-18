<?php

declare(strict_types=1);

/** @var array $residenceNotifications */
/** @var string $notificationListContext dropdown|page */

$notificationListContext = $notificationListContext ?? 'dropdown';
$notifications = is_array($residenceNotifications ?? null) ? $residenceNotifications : [];
$grouped = resident_notifications_grouped($notifications);
$previewLimit = $notificationListContext === 'dropdown' ? 4 : PHP_INT_MAX;
$previewIndex = 0;
$hasMore = count($notifications) > 4;
?>
<?php foreach (['today' => 'Today', 'earlier' => 'Earlier'] as $groupKey => $groupLabel): ?>
<?php $groupItems = $grouped[$groupKey] ?? []; ?>
<?php if ($groupItems === []) continue; ?>
<div class="topbar-notification-group" data-notification-group="<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>">
  <div class="topbar-notification-group-head">
    <h4><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></h4>
  </div>
  <?php foreach ($groupItems as $notification): ?>
  <?php
    $isUnread = empty($notification['read_at']);
    $isLater = $previewIndex >= $previewLimit;
    $previewIndex++;
    $title = trim((string) ($notification['title'] ?? 'Notification'));
    $message = trim((string) ($notification['message'] ?? ''));
    $logoUrl = trim((string) ($notification['logo_url'] ?? ''));
    $initials = resident_notification_initials($notification);
  ?>
  <article
    class="topbar-notification-item<?= $isUnread ? ' is-unread' : ' is-read' ?><?= $isLater ? ' is-later' : '' ?>"
    data-notification-group="<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>"
    <?= resident_notification_click_attr($notification) ?>
  >
    <span class="topbar-notification-avatar<?= $logoUrl !== '' ? ' has-logo' : ' tone-' . resident_notification_tone($notification) ?>" aria-hidden="true">
      <?php if ($logoUrl !== ''): ?>
      <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
      <span hidden><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
      <?php else: ?>
      <span><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </span>
    <div class="topbar-notification-copy">
      <p>
        <b><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></b><?= $message !== '' ? ' ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') : '' ?>
      </p>
      <time datetime="<?= htmlspecialchars((string) ($notification['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(resident_notification_relative_short($notification['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></time>
    </div>
    <?php if ($isUnread): ?><i class="topbar-notification-dot" aria-label="Unread"></i><?php endif; ?>
  </article>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>
<p class="topbar-notification-empty" data-notification-empty<?= $notifications !== [] ? ' hidden' : '' ?>><?= $notifications === [] ? 'No notifications' : 'No unread notifications' ?></p>
<?php
$notificationListHasMore = $hasMore;
?>
