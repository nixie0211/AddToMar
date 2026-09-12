      <!-- ========================= NOTIFICATIONS ========================= -->
      <section class="page" data-page="notifications" data-live-region="residence-notifications" data-live-keys="notifications,reports,orders">
        <style>
          #resident-report-detail{max-width:780px;margin:0 auto}#resident-report-detail .resident-report-detail-card{margin-top:16px;padding:26px;border:1px solid #d7e9e7;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(20,62,69,.06)}#resident-report-detail h2{margin:4px 0 8px;color:#163542;font-size:24px}#resident-report-detail .resident-report-kicker{margin:0;color:#078d82;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}#resident-report-detail .resident-report-status{display:inline-block;margin:0 0 20px;padding:6px 10px;border-radius:999px;background:#e6f7f3;color:#087d70;font-size:12px;font-weight:800;text-transform:capitalize}#resident-report-detail dl{display:grid;gap:17px;margin:0}#resident-report-detail dl div{display:grid;gap:5px}#resident-report-detail dt{color:#71848d;font-size:11px;font-weight:800;text-transform:uppercase}#resident-report-detail dd{margin:0;color:#294652;font-size:14px;line-height:1.55;white-space:pre-wrap}
        </style>
        <div class="card card-pad">
          <div class="topbar-notification-tabs notif-page-tabs" style="padding:0 0 16px;">
            <button type="button" class="is-active" onclick="filterResidentNotificationTab('all', this, event)">All</button>
            <button type="button" onclick="filterResidentNotificationTab('unread', this, event)">Unread</button>
          </div>
          <div id="resident-report-detail" hidden></div>
          <?php foreach ($residenceNotifications as $notification): ?>
          <div class="notif-full<?= empty($notification['read_at']) ? ' unread' : ' is-read' ?>"<?= resident_notification_click_attr($notification) ?>>
            <div class="ni" style="background:var(--green-soft); color:var(--green-deep);"><svg class="icon" viewBox="0 0 24 24"><path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></div>
            <div class="nt"><div class="t1"><?= htmlspecialchars((string) $notification['title'], ENT_QUOTES, 'UTF-8') ?></div><div class="t2"><?= htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8') ?></div><div class="t3"><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) $notification['created_at'])), ENT_QUOTES, 'UTF-8') ?></div></div>
          </div>
          <?php endforeach; ?>
          <?php
          $sampleOrderNumber = trim((string) (($residenceOrders[0]['order_number'] ?? '') ?: ''));
          $sampleOrderNumberAttr = htmlspecialchars($sampleOrderNumber, ENT_QUOTES, 'UTF-8');
          $sampleOrderLabel = htmlspecialchars($sampleOrderNumber !== '' ? $sampleOrderNumber : 'AM-10231', ENT_QUOTES, 'UTF-8');
          ?>
          <div class="notif-full" role="button" tabindex="0" data-notification-id="sample-payment" data-unread="0" data-order-number="<?= $sampleOrderNumberAttr ?>">
            <div class="ni" style="background:var(--green-soft); color:var(--green-deep);"><svg class="icon" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg></div>
            <div class="nt"><div class="t1">Payment verified</div><div class="t2">Your down payment for order #<?= $sampleOrderLabel ?> has been verified by Wellmed Pharmacy.</div><div class="t3">10 minutes ago</div></div>
          </div>
          <div class="notif-full" role="button" tabindex="0" data-notification-id="sample-ready" data-unread="0" data-order-number="<?= $sampleOrderNumberAttr ?>">
            <div class="ni" style="background:var(--blue-soft); color:var(--blue);"><svg class="icon" viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="4"/></svg></div>
            <div class="nt"><div class="t1">Order ready for pickup</div><div class="t2">Order #<?= htmlspecialchars($sampleOrderNumber !== '' ? $sampleOrderNumber : 'AM-10214', ENT_QUOTES, 'UTF-8') ?> at Care+ Drugstore is ready. Please pick up before 6:00 PM.</div><div class="t3">1 hour ago</div></div>
          </div>
          <div class="notif-full">
            <div class="ni" style="background:var(--green-soft); color:var(--green-deep);"><svg class="icon" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg></div>
            <div class="nt"><div class="t1">Prescription approved</div><div class="t2">Your uploaded prescription for Amoxil 500mg was approved by the pharmacist.</div><div class="t3">Yesterday, 4:32 PM</div></div>
          </div>
          <div class="notif-full" role="button" tabindex="0" data-notification-id="sample-accepted" data-unread="0" data-order-number="<?= $sampleOrderNumberAttr ?>">
            <div class="ni" style="background:var(--blue-soft); color:var(--blue);"><svg class="icon" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg></div>
            <div class="nt"><div class="t1">Order accepted</div><div class="t2">Wellmed Pharmacy accepted your order #<?= $sampleOrderLabel ?> and started processing.</div><div class="t3">Yesterday, 4:15 PM</div></div>
          </div>
          <div class="notif-full" role="button" tabindex="0" data-notification-id="sample-cancelled" data-unread="0" data-order-number="<?= $sampleOrderNumberAttr ?>">
            <div class="ni" style="background:var(--red-soft); color:var(--red);"><svg class="icon" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></div>
            <div class="nt"><div class="t1">Order cancelled</div><div class="t2">Order #<?= htmlspecialchars($sampleOrderNumber !== '' ? $sampleOrderNumber : 'AM-10190', ENT_QUOTES, 'UTF-8') ?> was cancelled — down payment will be refunded to your GCash.</div><div class="t3">3 days ago</div></div>
          </div>
          <div class="notif-full">
            <div class="ni" style="background:var(--amber-soft); color:var(--amber);"><svg class="icon" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></div>
            <div class="nt"><div class="t1">Medicine unavailable</div><div class="t2">Losartan 50mg is currently out of stock at St. Luke Pharmacy. Try another branch.</div><div class="t3">4 days ago</div></div>
          </div>
          <p class="topbar-notification-empty" data-notification-empty hidden>No unread notifications</p>
        </div>
      </section>
