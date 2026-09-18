    </main>
  </div>
  </div>
</div>

<div class="admin-modal" id="admin-settings-modal" <?= !empty($openAdminSettings) ? '' : 'hidden' ?> role="dialog" aria-modal="true" aria-labelledby="admin-settings-title">
  <div class="admin-modal-backdrop" data-close-settings></div>
  <div class="admin-modal-card">
    <button type="button" class="admin-modal-close" data-close-settings aria-label="Close">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
    <h2 id="admin-settings-title">Profile settings</h2>
    <p class="admin-modal-sub">Update your admin email and password.</p>

    <?php if (!empty($adminSettingsMessage)): ?>
    <p class="settings-alert success"><?= htmlspecialchars($adminSettingsMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($adminSettingsError)): ?>
    <p class="settings-alert error"><?= htmlspecialchars($adminSettingsError, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="" class="settings-form">
      <input type="hidden" name="action" value="update_admin_credentials">
      <div class="settings-field">
        <label for="admin-settings-email">Email</label>
        <input id="admin-settings-email" name="email" type="email" value="<?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">
      </div>
      <div class="settings-field">
        <label for="admin-settings-password">Password</label>
        <div class="settings-input-wrap">
          <input id="admin-settings-password" name="password" type="password" minlength="8" placeholder="At least 8 characters" autocomplete="new-password">
          <button type="button" class="settings-toggle-eye" id="toggle-admin-password" aria-label="Show password" hidden>
            <svg class="eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
          </button>
        </div>
      </div>
      <div class="settings-field">
        <label for="admin-settings-password-confirm">Confirm password</label>
        <div class="settings-input-wrap">
          <input id="admin-settings-password-confirm" name="password_confirm" type="password" minlength="8" placeholder="Re-enter password" autocomplete="new-password">
          <button type="button" class="settings-toggle-eye" id="toggle-admin-password-confirm" aria-label="Show password" hidden>
            <svg class="eye-show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="eye-hide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
          </button>
        </div>
      </div>
      <div class="admin-modal-actions">
        <button type="button" class="btn-pill btn-secondary" data-close-settings>Cancel</button>
        <button type="submit" class="btn-pill btn-primary">Save changes</button>
      </div>
    </form>
  </div>
</div>
<script>
(function () {
  var wrap = document.querySelector('.admin-profile-wrap');
  var button = document.getElementById('admin-profile-btn');
  var menu = document.getElementById('admin-profile-menu');
  var notifyWrap = document.querySelector('.admin-notify-wrap');
  var notifyButton = document.getElementById('admin-notify-btn');
  var notifyPanel = document.getElementById('admin-notify-panel');
  var modal = document.getElementById('admin-settings-modal');
  var openSettings = document.getElementById('open-admin-settings');

  function placeMenu() {
    if (!button || !menu) return;
    var rect = button.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.zIndex = '40';
    menu.style.top = (rect.bottom + 8) + 'px';
    menu.style.bottom = 'auto';
    menu.style.left = 'auto';
    menu.style.right = Math.max(12, window.innerWidth - rect.right) + 'px';
    menu.style.minWidth = '190px';
    menu.style.width = 'auto';
  }

  function placeNotifyPanel() {
    if (!notifyButton || !notifyPanel) return;
    var rect = notifyButton.getBoundingClientRect();
    notifyPanel.style.top = (rect.bottom + 8) + 'px';
    notifyPanel.style.right = Math.max(12, window.innerWidth - rect.right) + 'px';
    notifyPanel.style.left = 'auto';
  }

  function closeMenu() {
    if (!wrap || !button || !menu) return;
    wrap.classList.remove('open');
    menu.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  }

  function collapseNotifyHistory() {
    if (!notifyPanel) return;
    notifyPanel.classList.remove('is-expanded');
    var list = notifyPanel.querySelector('.admin-notify-list');
    if (list) list.scrollTop = 0;
    var footer = document.getElementById('admin-notify-footer');
    if (footer && notifyPanel.getAttribute('data-has-more') === '1') footer.hidden = false;
  }

  function expandNotifyHistory() {
    if (!notifyPanel) return;
    notifyPanel.classList.add('is-expanded');
    var footer = document.getElementById('admin-notify-footer');
    if (footer) footer.hidden = true;
  }

  function closeNotify() {
    if (!notifyWrap || !notifyButton || !notifyPanel) return;
    notifyWrap.classList.remove('open');
    notifyPanel.hidden = true;
    notifyButton.setAttribute('aria-expanded', 'false');
    collapseNotifyHistory();
  }

  function toggleMenu() {
    if (!wrap || !button || !menu) return;
    closeNotify();
    var isOpen = wrap.classList.toggle('open');
    menu.hidden = !isOpen;
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) placeMenu();
  }

  function toggleNotify() {
    if (!notifyWrap || !notifyButton || !notifyPanel) return;
    closeMenu();
    var isOpen = notifyWrap.classList.toggle('open');
    notifyPanel.hidden = !isOpen;
    notifyButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) placeNotifyPanel();
  }

  function openModal() {
    if (!modal) return;
    closeMenu();
    closeNotify();
    modal.hidden = false;
    document.body.classList.add('modal-open');
    document.getElementById('admin-settings-email')?.focus();
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    if (!document.querySelector('.rx-popup')) {
      document.body.classList.remove('modal-open');
    }
  }

  document.addEventListener('click', function (event) {
    wrap = document.querySelector('.admin-profile-wrap');
    button = document.getElementById('admin-profile-btn');
    menu = document.getElementById('admin-profile-menu');
    notifyWrap = document.querySelector('.admin-notify-wrap');
    notifyButton = document.getElementById('admin-notify-btn');
    notifyPanel = document.getElementById('admin-notify-panel');
    notifyHeadCount = document.getElementById('admin-notify-head-count');

    if (event.target.closest('#admin-notify-see-previous')) {
      event.preventDefault();
      event.stopPropagation();
      expandNotifyHistory();
      return;
    }
    if (event.target.closest('#admin-notify-btn')) {
      event.preventDefault();
      event.stopPropagation();
      toggleNotify();
      return;
    }
    if (event.target.closest('#admin-profile-btn')) {
      event.stopPropagation();
      toggleMenu();
      return;
    }
    if (event.target.closest('#open-admin-settings')) {
      event.preventDefault();
      event.stopPropagation();
      openModal();
      return;
    }
    if (event.target.closest('[data-close-settings]')) {
      closeModal();
      return;
    }
    var notifyItem = event.target.closest('.admin-notify-item[data-notification-id]');
    if (notifyItem) {
      markNotificationRead(notifyItem);
      var href = notifyItem.getAttribute('href');
      if (!href) return;
      try {
        var url = new URL(href, window.location.origin);
        if (!url.searchParams.has('modal')) return;
        event.preventDefault();
        url.searchParams.delete('modal');
        window.location.href = url.pathname + url.search + url.hash;
      } catch (error) {
        /* allow default navigation */
      }
      return;
    }
    if (wrap && !wrap.contains(event.target)) closeMenu();
    if (notifyWrap && !notifyWrap.contains(event.target)) closeNotify();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (modal && !modal.hidden) {
      closeModal();
      return;
    }
    var rxPopup = document.querySelector('.rx-popup');
    if (rxPopup) {
      var closeLink = rxPopup.querySelector('.rx-popup-backdrop');
      if (closeLink && closeLink.getAttribute('href')) {
        window.location.href = closeLink.href;
      }
      return;
    }
    if (notifyWrap && notifyWrap.classList.contains('open')) {
      closeNotify();
      return;
    }
    closeMenu();
  });

  window.addEventListener('resize', function () {
    if (wrap && wrap.classList.contains('open')) placeMenu();
    if (notifyWrap && notifyWrap.classList.contains('open')) placeNotifyPanel();
  });

  function bindPasswordToggle(inputId, buttonId) {
    var input = document.getElementById(inputId);
    var toggle = document.getElementById(buttonId);
    if (!input || !toggle) return;

    function sync() {
      var hasText = input.value.length > 0;
      toggle.hidden = !hasText;
      if (!hasText && input.type !== 'password') {
        input.type = 'password';
        toggle.classList.remove('is-showing');
        toggle.setAttribute('aria-label', 'Show password');
      }
    }

    toggle.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.classList.toggle('is-showing', show);
      toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    input.addEventListener('input', sync);
    input.addEventListener('change', sync);
    sync();
  }

  bindPasswordToggle('admin-settings-password', 'toggle-admin-password');
  bindPasswordToggle('admin-settings-password-confirm', 'toggle-admin-password-confirm');

  var markReadUrl = document.body.getAttribute('data-admin-mark-read-url') || window.location.pathname;
  var notifyHeadCount = document.getElementById('admin-notify-head-count');

  function formatNotifyCount(count) {
    return count === 1 ? '1 new' : String(count) + ' new';
  }

  function updateNotifyBadge(unread) {
    if (!notifyButton) return;

    var badge = notifyButton.querySelector('.admin-notify-badge');
    if (unread > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'admin-notify-badge';
        notifyButton.appendChild(badge);
      }
      badge.textContent = unread > 9 ? '9+' : String(unread);
      notifyButton.setAttribute('aria-label', unread + ' notifications');
    } else if (badge) {
      badge.remove();
      notifyButton.setAttribute('aria-label', 'Notifications');
    }

    if (notifyHeadCount) {
      if (unread > 0) {
        notifyHeadCount.textContent = formatNotifyCount(unread);
        notifyHeadCount.hidden = false;
      } else {
        notifyHeadCount.textContent = '';
        notifyHeadCount.hidden = true;
      }
    }
  }

  function markNotificationRead(item) {
    if (!item || item.classList.contains('is-read')) return;

    var notificationId = item.getAttribute('data-notification-id');
    if (!notificationId) return;

    item.classList.remove('is-unread');
    item.classList.add('is-read');

    var status = item.querySelector('.admin-notify-item-status');
    if (status) {
      status.classList.remove('is-new');
      status.classList.add('is-read');
      status.textContent = 'Read';
    }

    var currentUnread = notifyPanel ? notifyPanel.querySelectorAll('.admin-notify-item.is-unread').length : 0;
    updateNotifyBadge(currentUnread);

    fetch(markReadUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: 'action=mark_admin_notification_read&notification_id=' + encodeURIComponent(notificationId)
    }).then(function (response) {
      return response.json();
    }).then(function (data) {
      if (data && typeof data.unread === 'number') {
        updateNotifyBadge(data.unread);
      }
    }).catch(function () {
      /* keep optimistic UI */
    });
  }

  function refreshAdminShellRefs() {
    wrap = document.querySelector('.admin-profile-wrap');
    button = document.getElementById('admin-profile-btn');
    menu = document.getElementById('admin-profile-menu');
    notifyWrap = document.querySelector('.admin-notify-wrap');
    notifyButton = document.getElementById('admin-notify-btn');
    notifyPanel = document.getElementById('admin-notify-panel');
    notifyHeadCount = document.getElementById('admin-notify-head-count');
  }

  document.addEventListener('livesync:applied', function () {
    refreshAdminShellRefs();
    if (notifyWrap && notifyWrap.classList.contains('open') && notifyPanel) {
      notifyPanel.hidden = false;
      placeNotifyPanel();
    }
  });
})();
</script>
<?php if (function_exists('live_sync_render_script')) live_sync_render_script('admin'); ?>
</body>
</html>
