<!-- ================= ANALYTICS VIEW ================= -->
      <section class="<?= pharmacy_view_class('analytics', $activeView) ?>" id="view-analytics" data-live-region="pharmacy-analytics" data-live-keys="inventory,orders">
        <div class="grid-2">
          <div class="panel">
            <div class="panel-head"><h3>Inventory trend <span data-analytics-trend-label>— 6 months</span></h3><select class="analytics-period-select" aria-label="Inventory trend period"><option value="3">Last 3 months</option><option value="6" selected>Last 6 months</option><option value="12">Last 12 months</option></select></div>
            <canvas id="chartInvTrend" height="240"></canvas>
          </div>
          <div class="panel">
            <div class="panel-head"><h3>Stock health</h3></div>
            <?php
              $stockTotal = max(1, (int) ($analyticsStats['inStock'] ?? 0) + (int) ($analyticsStats['low'] ?? 0) + (int) ($analyticsStats['out'] ?? 0) + (int) ($analyticsStats['expiring'] ?? 0));
              $inPct = round(((int) ($analyticsStats['inStock'] ?? 0) / $stockTotal) * 100);
              $lowPct = round(((int) ($analyticsStats['low'] ?? 0) / $stockTotal) * 100);
              $outPct = round(((int) ($analyticsStats['out'] ?? 0) / $stockTotal) * 100);
              $expPct = round(((int) ($analyticsStats['expiring'] ?? 0) / $stockTotal) * 100);
            ?>
            <div style="margin-top:6px;">
              <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; margin-bottom:2px;"><span>In stock</span><span><?= number_format((int) ($analyticsStats['inStock'] ?? 0)) ?> medicines</span></div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= $inPct ?>%; background:linear-gradient(90deg,var(--green),var(--teal));"></div></div>
            </div>
            <div style="margin-top:16px;">
              <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; margin-bottom:2px;"><span>Low stock</span><span><?= number_format((int) ($analyticsStats['low'] ?? 0)) ?> medicines</span></div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= $lowPct ?>%; background:linear-gradient(90deg,var(--amber),#FBBF24);"></div></div>
            </div>
            <div style="margin-top:16px;">
              <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; margin-bottom:2px;"><span>Out of stock</span><span><?= number_format((int) ($analyticsStats['out'] ?? 0)) ?> medicines</span></div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= $outPct ?>%; background:linear-gradient(90deg,var(--red),#F87171);"></div></div>
            </div>
            <div style="margin-top:16px;">
              <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; margin-bottom:2px;"><span>Expiring within 7 days</span><span><?= number_format((int) ($analyticsStats['expiring'] ?? 0)) ?> medicines</span></div>
              <div class="progress-track"><div class="progress-fill" style="width:<?= $expPct ?>%; background:linear-gradient(90deg,var(--amber),#FBBF24);"></div></div>
            </div>
          </div>
        </div>
      </section>
