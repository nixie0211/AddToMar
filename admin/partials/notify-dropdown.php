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
