<!-- ================= CUSTOMERS VIEW ================= -->
      <section class="<?= pharmacy_view_class('customers', $activeView) ?>" id="view-customers" data-live-region="pharmacy-customers" data-live-keys="customers,orders">
        <div class="panel">
          <div class="table-wrap">
            <table>
              <thead><tr><th>Customer</th><th>Contact</th><th>Orders</th><th>Total spent</th><th>Last order</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (empty($customers)): ?>
                <tr><td colspan="6"><?= pharmacy_render_empty('No customers yet.') ?></td></tr>
                <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                <?php
                  $status = strtolower((string) ($customer['status'] ?? 'active'));
                  $badge = match ($status) {
                      'vip' => ['badge-blue', 'VIP'],
                      'inactive' => ['badge-gray', 'Inactive'],
                      default => ['badge-green', 'Active'],
                  };
                ?>
                <tr>
                  <td>
                    <div class="cell-med">
                      <div class="avatar" style="width:32px;height:32px;font-size:11px;"><?= htmlspecialchars(pharmacy_initials((string) ($customer['name'] ?? 'CU')), ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="name"><?= htmlspecialchars((string) ($customer['name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars((string) ($customer['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= number_format((int) ($customer['order_count'] ?? 0)) ?></td>
                  <td class="mono"><?= htmlspecialchars(pharmacy_format_money((float) ($customer['total_spent'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= !empty($customer['last_order_at']) ? htmlspecialchars(pharmacy_time_ago((string) $customer['last_order_at']), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                  <td><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
