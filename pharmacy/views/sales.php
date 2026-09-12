<!-- ================= SALES VIEW ================= -->
      <section class="<?= pharmacy_view_class('sales', $activeView) ?>" id="view-sales" data-live-region="pharmacy-sales" data-live-keys="orders">
        <div class="tabs sales-period-tabs">
          <button type="button" class="tab-btn active" data-sales-period="daily">Daily</button>
          <button type="button" class="tab-btn" data-sales-period="weekly">Weekly</button>
          <button type="button" class="tab-btn" data-sales-period="monthly">Monthly</button>
        </div>

        <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr); margin-top:0;">
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#F0FDF4;color:var(--green);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
            <div class="label">Revenue</div><div class="value" data-sales-stat="revenue"><?= htmlspecialchars(pharmacy_format_money((float) ($salesStats['revenue'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:var(--teal-soft);color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18M7 15l4-5 3 3 5-7"/></svg></div></div>
            <div class="label">Profit</div><div class="value" data-sales-stat="profit"><?= htmlspecialchars(pharmacy_format_money((float) ($salesStats['profit'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#F0FDFA;color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg></div></div>
            <div class="label">Avg. order value</div><div class="value" data-sales-stat="avgOrder"><?= htmlspecialchars(pharmacy_format_money((float) ($salesStats['avgOrder'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#FFFBEB;color:var(--amber);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></div></div>
            <div class="label" data-sales-orders-label>Weekly sales</div><div class="value" data-sales-stat="orders"><?= number_format((int) ($salesStats['weeklyOrders'] ?? 0)) ?> orders</div>
          </div>
        </div>

        <div class="grid-2">
          <div class="panel">
            <div class="panel-head"><h3>Revenue trend</h3>
              <div class="legend-row"><span class="legend-dot" style="background:var(--teal);"></span>Revenue</div>
            </div>
            <canvas id="chartSalesLine" height="230"></canvas>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Sales by category</h3></div>
            <canvas id="chartSalesCategory" height="230"></canvas>
          </div>
        </div>

        <div class="grid-2">
          <div class="panel">
            <div class="panel-head"><h3>Recent transactions</h3></div>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Transaction</th><th>Customer</th><th>Payment</th><th>Amount</th><th>Date</th></tr></thead>
                <tbody>
                  <?php if (empty($recentTransactions)): ?>
                  <tr><td colspan="5"><?= pharmacy_render_empty('No transactions yet.') ?></td></tr>
                  <?php else: ?>
                  <?php foreach ($recentTransactions as $txn): ?>
                  <?php
                    $method = strtolower((string) ($txn['payment_method'] ?? ''));
                    $payBadge = str_contains($method, 'gcash') ? 'badge-blue' : (str_contains($method, 'card') ? 'badge-green' : 'badge-gray');
                  ?>
                  <tr>
                    <td class="mono"><?= htmlspecialchars((string) ($txn['order_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($txn['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= $payBadge ?>"><?= htmlspecialchars((string) ($txn['payment_method'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="mono"><?= htmlspecialchars(pharmacy_format_money((float) ($txn['total_amount'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(pharmacy_time_ago($txn['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Top selling medicines</h3></div>
            <?php if (empty($topSelling)): ?>
            <?= pharmacy_render_empty('No sales yet.') ?>
            <?php else: ?>
            <?php foreach ($topSelling as $item): ?>
            <div class="list-row">
              <div class="med-thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></div>
              <div class="list-body">
                <div class="t1"><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="t2"><?= number_format((int) ($item['units'] ?? 0)) ?> units sold</div>
              </div>
              <div class="list-meta mono"><?= htmlspecialchars(pharmacy_format_money((float) ($item['revenue'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
