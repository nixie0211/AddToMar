<!-- ================= SUPPLIERS VIEW ================= -->
      <section class="<?= pharmacy_view_class('suppliers', $activeView) ?>" id="view-suppliers" data-live-region="pharmacy-suppliers" data-live-keys="suppliers">
        <div class="panel">
          <div class="table-wrap">
            <table>
              <thead><tr><th>Supplier</th><th>Contact person</th><th>Medicines supplied</th><th>Last delivery</th><th>On-time rate</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (empty($suppliers)): ?>
                <tr><td colspan="6"><?= pharmacy_render_empty('No suppliers yet.') ?></td></tr>
                <?php else: ?>
                <?php foreach ($suppliers as $supplier): ?>
                <?php
                  $status = strtolower((string) ($supplier['status'] ?? 'active'));
                  $badge = match ($status) {
                      'delayed' => ['badge-amber', 'Delayed'],
                      'inactive' => ['badge-gray', 'Inactive'],
                      default => ['badge-green', 'Active'],
                  };
                ?>
                <tr>
                  <td>
                    <div class="cell-med">
                      <div class="list-icon" style="background:var(--teal-soft);color:var(--teal);width:32px;height:32px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v11H3zM16 10h3l3 3v5h-6z"/></svg>
                      </div>
                      <div class="name"><?= htmlspecialchars((string) ($supplier['name'] ?? 'Supplier'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars((string) ($supplier['contact_person'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= number_format((int) ($supplier['medicines_count'] ?? 0)) ?> SKUs</td>
                  <td><?= !empty($supplier['last_delivery']) ? htmlspecialchars(date('M j, Y', strtotime((string) $supplier['last_delivery'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                  <td><?= number_format((float) ($supplier['on_time_rate'] ?? 0), 0) ?>%</td>
                  <td><span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
