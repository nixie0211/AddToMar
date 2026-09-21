<?php

declare(strict_types=1);
?>
      <div class="topbar-leading">
        <div class="topbar-head" data-topbar-head="orders" hidden>
          <h1>Order Management</h1>
          <p class="desc"><?= number_format((int) ($stats['ordersToday'] ?? 0)) ?> orders placed today · <?= number_format((int) ($stats['pendingOrders'] ?? 0)) ?> awaiting confirmation</p>
        </div>

        <div class="topbar-head" data-topbar-head="inventory"<?= $activeView === 'inventory' ? '' : ' hidden' ?>>
          <h1>Inventory</h1>
          <p class="desc">Manage and track all your medicines in one place.</p>
        </div>

        <div class="topbar-head" data-topbar-head="add-medicine" hidden>
          <h1>Add Medicine</h1>
          <p class="desc">Fill in the details below to add a medicine to your inventory.</p>
        </div>

        <div class="topbar-head" data-topbar-head="sales" hidden>
          <h1>Sales Dashboard</h1>
          <p class="desc">Track revenue, profit, and top-performing medicines.</p>
        </div>

        <div class="topbar-head" data-topbar-head="reports" hidden>
          <h1>Generate Reports</h1>
          <p class="desc">Export detailed reports for accounting, audits, and operations.</p>
        </div>

        <div class="topbar-head" data-topbar-head="analytics" hidden>
          <h1>Inventory &amp; Sales Analytics</h1>
          <p class="desc">Stock health, demand, and medicines that need attention.</p>
        </div>

        <div class="topbar-head" data-topbar-head="customers" hidden>
          <span class="eyebrow">Customers</span>
          <h1>Customers</h1>
          <p class="desc"><?= number_format(count($customers)) ?> registered customers<?= (int) ($stats['newCustomers'] ?? 0) > 0 ? ' · ' . number_format((int) $stats['newCustomers']) . ' new this week' : '' ?></p>
        </div>

        <div class="topbar-head" data-topbar-head="suppliers" hidden>
          <span class="eyebrow">Suppliers</span>
          <h1>Suppliers</h1>
          <p class="desc"><?= number_format(count($suppliers)) ?> suppliers</p>
        </div>

        <div class="topbar-head" data-topbar-head="settings" hidden>
          <h1>Pharmacy Profile</h1>
          <p class="desc">Manage your business information and login credentials.</p>
        </div>
      </div>

      <div class="topbar-view-actions">
        <div class="topbar-page-actions" data-topbar-actions="suppliers" hidden>
          <button type="button" class="btn btn-accent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>Add supplier</button>
        </div>
      </div>
