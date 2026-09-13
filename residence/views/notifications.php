      <!-- ========================= NOTIFICATIONS ========================= -->
      <section class="page" data-page="notifications" data-live-region="residence-notifications" data-live-keys="notifications,reports,orders">
        <style>
          #resident-report-detail{max-width:780px;margin:0 auto}#resident-report-detail .resident-report-detail-card{margin-top:16px;padding:26px;border:1px solid #d7e9e7;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(20,62,69,.06)}#resident-report-detail h2{margin:4px 0 8px;color:#163542;font-size:24px}#resident-report-detail .resident-report-kicker{margin:0;color:#078d82;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}#resident-report-detail .resident-report-status{display:inline-block;margin:0 0 20px;padding:6px 10px;border-radius:999px;background:#e6f7f3;color:#087d70;font-size:12px;font-weight:800;text-transform:capitalize}#resident-report-detail dl{display:grid;gap:17px;margin:0}#resident-report-detail dl div{display:grid;gap:5px}#resident-report-detail dt{color:#71848d;font-size:11px;font-weight:800;text-transform:uppercase}#resident-report-detail dd{margin:0;color:#294652;font-size:14px;line-height:1.55;white-space:pre-wrap}
        </style>
        <div class="card card-pad notifications-page-card">
          <div class="topbar-notification-head" style="padding:0 0 8px;">
            <h3>Notifications</h3>
          </div>
          <div class="topbar-notification-tabs notif-page-tabs" style="padding:0 0 16px;">
            <button type="button" class="is-active" onclick="filterResidentNotificationTab('all', this, event)">All</button>
            <button type="button" onclick="filterResidentNotificationTab('unread', this, event)">Unread</button>
          </div>
          <div id="resident-report-detail" hidden></div>
          <div class="notifications-page-list">
            <?php
            $notificationListContext = 'page';
            include RESIDENCE_ROOT . '/partials/notification-list.php';
            ?>
          </div>
        </div>
      </section>
