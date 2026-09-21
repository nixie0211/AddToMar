<!-- ================= ANALYTICS VIEW ================= -->
      <section class="<?= pharmacy_view_class('analytics', $activeView) ?>" id="view-analytics" data-live-region="pharmacy-analytics" data-live-keys="inventory,orders">
        <?php
          $inStockCount = (int) ($analyticsStats['inStock'] ?? 0);
          $lowCount = (int) ($analyticsStats['low'] ?? 0);
          $outCount = (int) ($analyticsStats['out'] ?? 0);
          $expiringCount = (int) ($analyticsStats['expiring'] ?? 0);
          $expiredCount = (int) ($analyticsStats['expired'] ?? 0);
          $stockTotal = max(1, $inStockCount + $lowCount + $outCount + $expiringCount + $expiredCount);
          $attentionCount = $lowCount + $outCount + $expiringCount + $expiredCount;
          $orderTotal = max(1, array_sum(array_map('intval', $orderStatusCounts ?? [])));
          $statusMap = pharmacy_order_status_map();
          $catalogCategories = array_slice($analyticsStats['categories'] ?? [], 0, 8, true);
          $categoryMax = 1;
          foreach ($catalogCategories as $categoryCount) {
              $categoryMax = max($categoryMax, (int) $categoryCount);
          }
          $salesCategories = array_slice($chartData['categories'] ?? [], 0, 8);
          $salesCatMax = 1;
          foreach ($salesCategories as $row) {
              $salesCatMax = max($salesCatMax, (int) ($row['total'] ?? 0));
          }
          $stockMix = [
              'labels' => ['In stock', 'Low stock', 'Out of stock', 'Expiring soon', 'Expired'],
              'values' => [$inStockCount, $lowCount, $outCount, $expiringCount, $expiredCount],
              'colors' => ['#2aab9a', '#F59E0B', '#EF4444', '#FBBF24', '#991B1B'],
          ];
        ?>
        <div class="kpi-grid analytics-kpi-grid">
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:var(--teal-soft);color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></div></div>
            <div class="label">Catalog</div>
            <div class="value"><?= number_format((int) ($analyticsStats['total'] ?? $stats['totalMedicines'] ?? 0)) ?></div>
            <div class="kpi-trend"><?= number_format((int) ($analyticsStats['rx'] ?? 0)) ?> Rx · <?= number_format((int) ($analyticsStats['otc'] ?? 0)) ?> OTC</div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#F0FDFA;color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
            <div class="label">Inventory value</div>
            <div class="value"><?= pharmacy_format_money((float) ($stats['inventoryValue'] ?? 0)) ?></div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#F0FDF4;color:var(--green);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div></div>
            <div class="label">Revenue this month</div>
            <div class="value"><?= pharmacy_format_money((float) ($salesStats['revenue'] ?? $stats['monthlyRevenue'] ?? 0)) ?></div>
            <div class="kpi-trend"><?= number_format((int) ($salesStats['orderCount'] ?? 0)) ?> completed orders</div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#FFFBEB;color:var(--amber);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg></div></div>
            <div class="label">Needs attention</div>
            <div class="value"><?= number_format($attentionCount) ?></div>
            <div class="kpi-trend"><?= number_format($expiredCount) ?> expired · <?= number_format($expiringCount) ?> expiring</div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:#FEF2F2;color:var(--red);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div></div>
            <div class="label">Out of stock</div>
            <div class="value"><?= number_format($outCount) ?></div>
            <div class="kpi-trend"><?= number_format($lowCount) ?> at or below minimum</div>
          </div>
          <div class="kpi-card">
            <div class="top-row"><div class="kpi-icon" style="background:var(--teal-soft);color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg></div></div>
            <div class="label">Avg. completed order</div>
            <div class="value"><?= pharmacy_format_money((float) ($salesStats['avgOrder'] ?? 0)) ?></div>
            <div class="kpi-trend"><?= number_format((int) ($salesStats['weeklyOrders'] ?? 0)) ?> orders in the last 7 days</div>
          </div>
        </div>

        <div class="grid-2">
          <div class="panel">
            <div class="panel-head"><h3>Inventory trend <span data-analytics-trend-label>— 6 months</span></h3><select class="analytics-period-select" aria-label="Inventory trend period"><option value="3">Last 3 months</option><option value="6" selected>Last 6 months</option><option value="12">Last 12 months</option></select></div>
            <p class="analytics-note">Stock in is quantity added that month. Stock out is units sold on completed orders.</p>
            <canvas id="chartInvTrend" height="240"></canvas>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Stock health</h3></div>
            <canvas id="chartStockMix" height="180"></canvas>
            <script type="application/json" id="pharmacy-analytics-stock"><?= json_encode($stockMix, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
            <div class="analytics-health-list">
              <div class="analytics-health-row">
                <span>In stock</span><span><?= number_format($inStockCount) ?></span>
              </div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= round(($inStockCount / $stockTotal) * 100) ?>%; background:linear-gradient(90deg,var(--green),var(--teal));"></div></div>
              <div class="analytics-health-row">
                <span>Low stock</span><span><?= number_format($lowCount) ?></span>
              </div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= round(($lowCount / $stockTotal) * 100) ?>%; background:linear-gradient(90deg,var(--amber),#FBBF24);"></div></div>
              <div class="analytics-health-row">
                <span>Out of stock</span><span><?= number_format($outCount) ?></span>
              </div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= round(($outCount / $stockTotal) * 100) ?>%; background:linear-gradient(90deg,var(--red),#F87171);"></div></div>
              <div class="analytics-health-row">
                <span>Expiring within 7 days</span><span><?= number_format($expiringCount) ?></span>
              </div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= round(($expiringCount / $stockTotal) * 100) ?>%; background:linear-gradient(90deg,#F59E0B,#FBBF24);"></div></div>
              <div class="analytics-health-row">
                <span>Expired</span><span><?= number_format($expiredCount) ?></span>
              </div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= round(($expiredCount / $stockTotal) * 100) ?>%; background:linear-gradient(90deg,#991B1B,#EF4444);"></div></div>
            </div>
          </div>
        </div>

        <div class="grid-2">
          <div class="panel">
            <div class="panel-head"><h3>Catalog by category</h3></div>
            <?php if ($catalogCategories === []): ?>
              <?= pharmacy_render_empty('No medicines in the catalog yet.') ?>
            <?php else: ?>
              <?php foreach ($catalogCategories as $categoryName => $categoryCount): ?>
              <div class="analytics-mix-row">
                <div class="analytics-health-row">
                  <span><?= htmlspecialchars((string) $categoryName, ENT_QUOTES, 'UTF-8') ?></span>
                  <span><?= number_format((int) $categoryCount) ?></span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:<?= round(((int) $categoryCount / $categoryMax) * 100) ?>%; background:linear-gradient(90deg,var(--teal),var(--green));"></div></div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Order mix</h3></div>
            <?php foreach ($orderStatusCounts as $statusKey => $statusCount): ?>
              <?php $badge = $statusMap[$statusKey] ?? ['c' => 'badge-gray', 't' => ucfirst((string) $statusKey)]; ?>
              <div class="analytics-mix-row">
                <div class="analytics-health-row">
                  <span><span class="badge <?= htmlspecialchars((string) $badge['c'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $badge['t'], ENT_QUOTES, 'UTF-8') ?></span></span>
                  <span><?= number_format((int) $statusCount) ?></span>
                </div>
                <div class="progress-track"><div class="progress-fill analytics-order-fill analytics-order-fill--<?= htmlspecialchars((string) $statusKey, ENT_QUOTES, 'UTF-8') ?>" style="width:<?= round(((int) $statusCount / $orderTotal) * 100) ?>%;"></div></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="grid-3">
          <div class="panel">
            <div class="panel-head"><h3>Expired</h3></div>
            <?php if (empty($expiredMedicines)): ?>
              <?= pharmacy_render_empty('No expired medicines.') ?>
            <?php else: ?>
              <?php foreach ($expiredMedicines as $medicine): ?>
              <div class="list-row">
                <div class="list-icon" style="background:#FEE2E2;color:var(--red);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div>
                <div class="list-body">
                  <div class="t1"><?= htmlspecialchars((string) ($medicine['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="t2"><?= (int) ($medicine['stock_quantity'] ?? 0) ?> units · expired <?= htmlspecialchars(pharmacy_format_date($medicine['expiration_date'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Expiring within 7 days</h3></div>
            <?php if (empty($expiringMedicines)): ?>
              <?= pharmacy_render_empty('No medicines expiring this week.') ?>
            <?php else: ?>
              <?php foreach ($expiringMedicines as $medicine): ?>
              <div class="list-row">
                <div class="list-icon" style="background:#FEF3C7;color:var(--amber);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                <div class="list-body">
                  <div class="t1"><?= htmlspecialchars((string) ($medicine['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="t2"><?= (int) ($medicine['stock_quantity'] ?? 0) ?> units · <?= htmlspecialchars(pharmacy_format_date($medicine['expiration_date'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Low / out of stock</h3></div>
            <?php if (empty($lowStockMedicines)): ?>
              <?= pharmacy_render_empty('No low-stock alerts.') ?>
            <?php else: ?>
              <?php foreach ($lowStockMedicines as $medicine): ?>
              <div class="list-row">
                <div class="list-icon" style="background:<?= ($medicine['status'] ?? '') === 'out' ? '#FEE2E2;color:var(--red)' : '#FEF3C7;color:var(--amber)' ?>;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg></div>
                <div class="list-body">
                  <div class="t1"><?= htmlspecialchars((string) ($medicine['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="t2"><?= (int) ($medicine['stock_quantity'] ?? 0) <= 0 ? 'Out of stock' : 'Only ' . (int) $medicine['stock_quantity'] . ' units left' ?></div>
                </div>
                <div class="list-meta mono"><?= (int) ($medicine['stock_quantity'] ?? 0) ?> / <?= (int) ($medicine['minimum_stock'] ?? 0) ?></div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="grid-2">
          <div class="panel">
            <div class="panel-head"><h3>Top selling medicines</h3></div>
            <?php if (empty($topSelling)): ?>
              <?= pharmacy_render_empty('No completed sales yet.') ?>
            <?php else: ?>
              <div class="table-wrap">
                <table>
                  <thead><tr><th>Medicine</th><th>Units</th><th>Revenue</th></tr></thead>
                  <tbody>
                    <?php foreach ($topSelling as $item): ?>
                    <tr>
                      <td><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="mono"><?= number_format((int) ($item['units'] ?? 0)) ?></td>
                      <td class="mono"><?= htmlspecialchars(pharmacy_format_money((float) ($item['revenue'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Units sold by category</h3></div>
            <?php if ($salesCategories === []): ?>
              <?= pharmacy_render_empty('No completed sales by category yet.') ?>
            <?php else: ?>
              <?php foreach ($salesCategories as $row): ?>
              <div class="analytics-mix-row">
                <div class="analytics-health-row">
                  <span><?= htmlspecialchars((string) (($row['label'] ?? '') !== '' && ($row['label'] ?? null) !== null ? $row['label'] : 'Uncategorized'), ENT_QUOTES, 'UTF-8') ?></span>
                  <span><?= number_format((int) ($row['total'] ?? 0)) ?> units</span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:<?= round(((int) ($row['total'] ?? 0) / $salesCatMax) * 100) ?>%; background:linear-gradient(90deg,#0f7a72,var(--teal));"></div></div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
