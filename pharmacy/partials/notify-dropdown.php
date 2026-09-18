        <div class="notif-wrap" data-live-region="pharmacy-notify" data-live-keys="shell,orders,inventory" data-mark-read-url="<?= htmlspecialchars(app_url('ajax/pharmacy-notification-read.php'), ENT_QUOTES, 'UTF-8') ?>">
          <?php
            $notifUnreadCount = count(array_filter($notifications ?? [], static fn($item): bool => !empty($item['unread'])));
            $notifBadgeLabel = $notifUnreadCount > 9 ? '9+' : (string) $notifUnreadCount;
          ?>
          <button class="icon-btn" onclick="toggleNotifDropdown(event)" title="Notifications" aria-label="<?= $notifUnreadCount > 0 ? $notifUnreadCount . ' unread notifications' : 'Notifications' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9zM13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="notif-count-badge" id="notif-count-badge"<?= $notifUnreadCount < 1 ? ' hidden' : '' ?>><?= htmlspecialchars($notifBadgeLabel, ENT_QUOTES, 'UTF-8') ?></span>
          </button>

          <div class="notif-dropdown" id="notif-dropdown" data-has-more="<?= count($notifications) > 4 ? '1' : '0' ?>">
            <div class="notif-head">
              <h3>Notifications</h3>
              <button type="button" class="notif-kebab" onclick="event.stopPropagation(); closePharmacyNotif(); showView('notifications');" aria-label="See all notifications">···</button>
            </div>
            <div class="notif-tabs">
              <button type="button" class="notif-tab active" onclick="filterNotifTab('all', this, event)">All</button>
              <button type="button" class="notif-tab" onclick="filterNotifTab('unread', this, event)">Unread</button>
            </div>

            <div class="notif-scroll" data-live-preserve="pharmacy-notif-scroll">
              <?php if (empty($notifications)): ?>
              <p class="notif-empty">No notifications yet.</p>
              <?php else: ?>
              <?php
                $notifTones = ['#1877f2', '#42b72a', '#f7b928', '#f02849'];
                $previewIndex = 0;
                $groupedNotifs = pharmacy_notifications_grouped($notifications);
              ?>
              <?php foreach (['today' => 'Today', 'earlier' => 'Earlier'] as $groupKey => $groupLabel): ?>
              <?php $groupItems = $groupedNotifs[$groupKey] ?? []; ?>
              <?php if ($groupItems === []) continue; ?>
              <div class="notif-section" data-notif-group="<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>">
                <div class="notif-section-head">
                  <span class="title"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php foreach ($groupItems as $item): ?>
                <?php
                  $itemType = (string) ($item['type'] ?? '');
                  $isUnread = !empty($item['unread']);
                  $isLater = $previewIndex >= 4;
                  $previewIndex++;
                  $tone = $notifTones[abs(crc32((string) ($item['headline'] ?? $item['title'] ?? 'n'))) % 4];
                  $headline = trim((string) ($item['headline'] ?? ''));
                  $title = trim((string) ($item['title'] ?? ''));
                  $rest = $headline !== '' && str_starts_with($title, $headline)
                    ? trim(substr($title, strlen($headline)))
                    : $title;
                  $view = (string) ($item['view'] ?? 'notifications');
                ?>
                <button type="button" class="notif-item<?= $isUnread ? ' is-unread' : ' is-read' ?><?= $isLater ? ' is-later' : '' ?>" data-id="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-type="<?= htmlspecialchars($itemType, ENT_QUOTES, 'UTF-8') ?>" data-unread="<?= $isUnread ? '1' : '0' ?>" onclick="openPharmacyNotification(event, '<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>')">
                  <span class="notif-avatar-wrap">
                    <span class="notif-avatar" style="background:<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars((string) ($item['initials'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="notif-badge-icon is-<?= htmlspecialchars($itemType, ENT_QUOTES, 'UTF-8') ?>">
                      <?php if ($itemType === 'new-order'): ?>
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg>
                      <?php elseif ($itemType === 'low-stock'): ?>
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg>
                      <?php else: ?>
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                      <?php endif; ?>
                    </span>
                  </span>
                  <span class="notif-body">
                    <span class="notif-text"><?php if ($headline !== ''): ?><b><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></b><?= $rest !== '' ? ' ' . htmlspecialchars($rest, ENT_QUOTES, 'UTF-8') : '' ?><?php else: ?><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></span>
                    <time class="notif-time"><?= htmlspecialchars((string) ($item['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></time>
                  </span>
                  <?php if ($isUnread): ?><i class="notif-dot" aria-label="Unread"></i><?php endif; ?>
                </button>
                <?php endforeach; ?>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div class="notif-foot" id="notif-foot"<?= count($notifications) > 4 ? '' : ' hidden' ?>>
              <button type="button" id="notif-see-previous" onclick="expandNotifHistory(event)">See previous notifications</button>
            </div>
          </div>
        </div>
