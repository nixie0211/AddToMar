      <!-- ========================= ORDER HISTORY ========================= -->
      <section class="page" data-page="orders" data-live-region="residence-orders" data-live-keys="orders" data-live-mode="js">
        <div id="orders-list-view">
          <div class="orders-reference-head">
            <div>
              <h2>
                <span>
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h7M9 16h7M9 8h3"/></svg>
                </span>
                My Orders
              </h2>
              <p>Track your purchases and check the status of your orders.</p>
            </div>
          </div>
          <div class="orders-reference-tools">
            <div class="orders-reference-tabs" id="orders-status-tabs">
              <button type="button" class="is-active" data-order-tab="all">All</button>
              <button type="button" data-order-tab="processing">Processing</button>
              <button type="button" data-order-tab="confirmed">Confirmed</button>
              <button type="button" data-order-tab="preparing">Preparing</button>
              <button type="button" data-order-tab="ready">Ready for pick up</button>
              <button type="button" data-order-tab="completed">Completed</button>
              <button type="button" data-order-tab="cancelled">Cancelled</button>
            </div>
            <div class="orders-reference-actions">
              <label>
                <span aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </span>
                <input type="search" id="orders-search" placeholder="Search by order number...">
              </label>
            </div>
          </div>
          <div class="orders-reference-list" id="orders-reference-list"></div>
        </div>
        <div id="orders-detail-view" style="display:none;"></div>
      </section>
