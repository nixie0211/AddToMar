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

$badgeSvgs = [
    'order' => '<svg viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="4"/><path d="M7 12h10"/></svg>',
    'report' => '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'default' => '<svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 6 2 6H4c.5 0 2-2 2-6Z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>',
];
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
    $type = strtolower(trim((string) ($notification['type'] ?? 'info')));
    $badgeKey = in_array($type, ['order', 'report'], true) ? $type : 'default';
    $title = trim((string) ($notification['title'] ?? 'Notification'));
    $message = trim((string) ($notification['message'] ?? ''));
  ?>
  <article
    class="topbar-notification-item<?= $isUnread ? ' is-unread' : ' is-read' ?><?= $isLater ? ' is-later' : '' ?>"
    data-notification-group="<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>"
    <?= resident_notification_click_attr($notification) ?>
  >
    <span class="topbar-notification-avatar tone-<?= resident_notification_tone($notification) ?>" aria-hidden="true">
      <span><?= htmlspecialchars(resident_notification_initials($notification), ENT_QUOTES, 'UTF-8') ?></span>
      <i class="topbar-notification-badge is-<?= htmlspecialchars($badgeKey, ENT_QUOTES, 'UTF-8') ?>"><?= $badgeSvgs[$badgeKey] ?></i>
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
