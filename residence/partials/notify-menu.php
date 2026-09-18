          <div class="topbar-notification-menu" id="topbar-notification-menu" data-live-region="residence-notify-menu" data-live-keys="notifications,reports,orders">
            <button type="button" class="topbar-nav-item" onclick="toggleNotificationMenu(event)" aria-label="Open notifications" aria-expanded="false">
              <span class="topbar-nav-icon">
                <svg class="icon" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 4 1.5 6 2 6H4c.5 0 2-2 2-6Z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>
                <b class="topbar-count-badge" id="sb-notif-count"<?php if ($headerUnreadNotifications < 1): ?> hidden<?php endif; ?> aria-label="<?= $headerUnreadNotifications ?> unread notifications"><?= $headerUnreadNotifications > 99 ? '99+' : (int) $headerUnreadNotifications ?></b>
              </span>
              <span>Notification</span>
            </button>
            <div class="topbar-notification-dropdown" role="dialog" aria-label="Notifications" hidden data-has-more="<?= count($residenceNotifications ?? []) > 4 ? '1' : '0' ?>">
              <div class="topbar-notification-head">
                <h3>Notifications</h3>
                <button type="button" class="topbar-notification-options" onclick="event.stopPropagation(); closeNotificationMenu(); go('notifications');" aria-label="See all notifications">···</button>
              </div>
              <div class="topbar-notification-tabs">
                <button type="button" class="is-active" onclick="filterResidentNotificationTab('all', this, event)">All</button>
                <button type="button" onclick="filterResidentNotificationTab('unread', this, event)">Unread</button>
              </div>
              <div class="topbar-notification-scroll" data-live-preserve="residence-notify-scroll">
                <?php
                $notificationListContext = 'dropdown';
                include RESIDENCE_ROOT . '/partials/notification-list.php';
                ?>
              </div>
              <div class="topbar-notification-footer"<?= count($residenceNotifications ?? []) > 4 ? '' : ' hidden' ?>><button type="button" onclick="expandNotificationHistory(event)">See previous notifications</button></div>
            </div>
          </div>
