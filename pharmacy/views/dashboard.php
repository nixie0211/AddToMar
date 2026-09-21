<!-- ================= DASHBOARD VIEW ================= -->
      <section class="<?= pharmacy_view_class('dashboard', $activeView) ?>" id="view-dashboard" data-live-region="pharmacy-dashboard" data-live-keys="orders,inventory,shell">
        <div class="page-head dashboard-head">
          <div>
            <span class="eyebrow"><?= date('l, F j') ?></span>
            <h1>Good morning, <?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="desc">Here's what's happening at <?= htmlspecialchars($pharmacyName, ENT_QUOTES, 'UTF-8') ?> today.</p>
          </div>
          <div class="head-actions">
            <button type="button" class="btn btn-accent" onclick="openAddMedicineForm()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>Add medicine</button>
          </div>
        </div>

        <div class="kpi-grid">
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:var(--teal-soft);color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></div></div>
            <div class="label">Total Medicines</div>
            <div class="value"><?= number_format($stats['totalMedicines']) ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#F0FDFA;color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
            <div class="label">Inventory Value</div>
            <div class="value"><?= pharmacy_format_money($stats['inventoryValue']) ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:var(--teal-soft);color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg></div></div>
            <div class="label">Orders Today</div>
            <div class="value"><?= number_format($stats['ordersToday']) ?></div>
            <?php if ($stats['pendingOrders'] > 0): ?><div class="kpi-trend trend-up"><?= $stats['pendingOrders'] ?> pending fulfillment</div><?php endif; ?>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#F0FDF4;color:var(--green);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
            <div class="label">Monthly Revenue</div>
            <div class="value"><?= pharmacy_format_money($stats['monthlyRevenue']) ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#FFFBEB;color:var(--amber);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg></div></div>
            <div class="label">Low Stock Medicines</div>
            <div class="value"><?= number_format($stats['lowStock']) ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#FFFBEB;color:var(--amber);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div></div>
            <div class="label">Expiring Soon</div>
            <div class="value"><?= number_format($stats['expiring']) ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#FEF2F2;color:var(--red);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div></div>
            <div class="label">Out of Stock</div>
            <div class="value"><?= number_format($stats['outOfStock']) ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#F0FDFA;color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
            <div class="label">New Customers</div>
            <div class="value"><?= number_format($stats['newCustomers']) ?></div>
          </div>
        </div>

        <div class="grid-2">
          <div class="panel">
            <div class="panel-head">
              <h3>Daily sales — last 14 days</h3>
              <div style="display:flex;gap:14px;">
                <div class="legend-row"><span class="legend-dot" style="background:var(--teal);"></span>Orders</div>
                <div class="legend-row"><span class="legend-dot" style="background:var(--teal);"></span>Revenue (₱k)</div>
              </div>
            </div>
            <canvas id="chartDaily" height="230"></canvas>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Best selling medicines</h3></div>
            <canvas id="chartBestSelling" height="230"></canvas>
          </div>
        </div>

        <div class="grid-3">
          <div class="panel">
            <div class="panel-head"><h3>Recent orders</h3></div>
            <div class="table-wrap">
              <?php if (empty($recentOrders)): ?>
                <?= pharmacy_render_empty('No orders yet.') ?>
              <?php else: ?>
              <table>
                <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentOrders as $order): $badge = pharmacy_order_status_map()[$order['status']] ?? ['c' => 'badge-gray', 't' => ucfirst($order['status'])]; ?>
                  <tr>
                    <td class="mono"><?= htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($order['customer_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $order['item_count'] ?> items</td>
                    <td><?= pharmacy_format_money((float) $order['total_amount']) ?></td>
                    <td><span class="badge <?= $badge['c'] ?>"><?= $badge['t'] ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </div>

          <div class="panel">
            <div class="panel-head"><h3>Low stock alerts</h3></div>
            <?php if (empty($lowStockMedicines)): ?>
              <?= pharmacy_render_empty('No low stock alerts.') ?>
            <?php else: ?>
              <?php foreach ($lowStockMedicines as $medicine): ?>
              <div class="list-row">
                <div class="list-icon" style="background:<?= $medicine['status'] === 'out' ? '#FEE2E2;color:var(--red)' : '#FEF3C7;color:var(--amber)' ?>;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg></div>
                <div class="list-body"><div class="t1"><?= htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8') ?></div><div class="t2"><?= (int) $medicine['stock_quantity'] <= 0 ? 'Out of stock' : 'Only ' . (int) $medicine['stock_quantity'] . ' units left' ?></div></div>
                <div class="list-meta mono"><?= (int) $medicine['stock_quantity'] ?> / <?= (int) $medicine['minimum_stock'] ?></div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="panel">
            <div class="panel-head"><h3>Expiring medicines</h3></div>
            <?php if (empty($expiringMedicines)): ?>
              <?= pharmacy_render_empty('No expiring medicines.') ?>
            <?php else: ?>
              <?php foreach ($expiringMedicines as $medicine): ?>
              <div class="list-row">
                <div class="list-icon" style="background:#FEF3C7;color:var(--amber);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                <div class="list-body"><div class="t1"><?= htmlspecialchars($medicine['name'], ENT_QUOTES, 'UTF-8') ?></div><div class="t2">Batch #<?= htmlspecialchars($medicine['batch_number'], ENT_QUOTES, 'UTF-8') ?></div></div>
                <div class="list-meta"><?= pharmacy_format_date($medicine['expiration_date']) ?></div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
