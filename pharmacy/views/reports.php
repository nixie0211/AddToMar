<!-- ================= REPORTS VIEW ================= -->
      <section class="<?= pharmacy_view_class('reports', $activeView) ?>" id="view-reports" data-live-region="pharmacy-reports" data-live-keys="orders,inventory">
        <div class="report-grid">
          <div class="report-card">
            <div class="r-icon" style="background:var(--teal-soft);color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></div>
            <h4>Inventory Report</h4><p>Full stock listing with quantities, values, and batch details.</p>
            <div class="report-dl"><?= pharmacy_report_buttons('inventory') ?></div>
          </div>
          <div class="report-card">
            <div class="r-icon" style="background:#F0FDF4;color:var(--green);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18M7 15l4-5 3 3 5-7"/></svg></div>
            <h4>Sales Report</h4><p>Order volume and sales performance across any date range.</p>
            <div class="report-dl"><?= pharmacy_report_buttons('sales') ?></div>
          </div>
          <div class="report-card">
            <div class="r-icon" style="background:#F0FDFA;color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <h4>Revenue Report</h4><p>Revenue and profit breakdown by category and period.</p>
            <div class="report-dl"><?= pharmacy_report_buttons('revenue') ?></div>
          </div>
          <div class="report-card">
            <div class="r-icon" style="background:#FFFBEB;color:var(--amber);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg></div>
            <h4>Low Stock Report</h4><p>Medicines below their minimum stock threshold.</p>
            <div class="report-dl"><?= pharmacy_report_buttons('low-stock') ?></div>
          </div>
          <div class="report-card">
            <div class="r-icon" style="background:#FEF2F2;color:var(--red);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
            <h4>Expired Medicines</h4><p>Medicines past expiration awaiting disposal or return.</p>
            <div class="report-dl"><?= pharmacy_report_buttons('expired') ?></div>
          </div>
          <div class="report-card">
            <div class="r-icon" style="background:var(--teal-soft);color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg></div>
            <h4>Medicine Performance</h4><p>Best and worst performing medicines by sales velocity.</p>
            <div class="report-dl"><?= pharmacy_report_buttons('performance') ?></div>
          </div>
          <div class="report-card">
            <div class="r-icon" style="background:#F0FDFA;color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg></div>
            <h4>Customer Purchases</h4><p>Purchase history and spend per registered customer.</p>
            <div class="report-dl"><?= pharmacy_report_buttons('customers') ?></div>
          </div>
        </div>
      </section>
