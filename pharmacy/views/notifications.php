<!-- ================= NOTIFICATIONS VIEW ================= -->
      <section class="<?= pharmacy_view_class('notifications', $activeView) ?>" id="view-notifications" data-live-region="pharmacy-notifications" data-live-keys="orders,inventory,shell">
        <div class="panel">
          <?php if (empty($notifications)): ?>
          <?= pharmacy_render_empty('No notifications yet.') ?>
          <?php else: ?>
          <?php foreach ($notifications as $item): ?>
          <?php
            $type = (string) ($item['type'] ?? '');
            $iconStyle = match ($type) {
                'low-stock', 'expiring' => 'background:#FEF3C7;color:var(--amber);',
                default => 'background:var(--teal-soft);color:var(--teal);',
            };
          ?>
          <div class="list-row" data-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
            <div class="list-icon" style="<?= $iconStyle ?>">
              <?php if ($type === 'low-stock'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg>
              <?php elseif ($type === 'expiring'): ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              <?php else: ?>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg>
              <?php endif; ?>
            </div>
            <div class="list-body">
              <div class="t1"><?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="t2"><?= htmlspecialchars((string) ($item['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="list-meta"><?= htmlspecialchars((string) ($item['time'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
