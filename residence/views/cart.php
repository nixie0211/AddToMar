      <!-- ========================= CART ========================= -->
      <section class="page" data-page="cart" data-live-region="residence-cart" data-live-keys="cart,catalog" data-live-skip-active="1">
        <div class="cart-layout" id="cart-page-root">
          <div class="cart-panel">
            <div class="cart-panel-head">
              <div class="cart-panel-title-row">
                <button type="button" class="cart-continue" onclick="go('dashboard')">
                  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6 9 12l6 6"/></svg>
                  Continue Shopping
                </button>
                <h2>Shopping Cart</h2>
              </div>
              <p class="cart-panel-sub" id="cart-panel-sub">Medicines selected for you, ready for pharmacy pickup.</p>
            </div>
            <div class="cart-table-head">
              <label class="ci-check-wrap" title="Select all for checkout">
                <input type="checkbox" id="cart-select-all" onchange="toggleAllCartCheckout(this.checked)" aria-label="Select all items for checkout">
              </label>
              <span>Product</span>
              <span>Quantity</span>
              <span>Subtotal</span>
            </div>
            <div id="cart-items-root"></div>
          </div>

          <aside class="cart-side">
            <div class="cart-side-items" id="cart-side-items-root"></div>
            <div class="cart-side-footer">
              <div id="cart-summary-root"></div>
              <button type="button" class="cart-side-checkout" id="cart-checkout-btn" onclick="proceedToCheckout()">
                Checkout
              </button>
            </div>
          </aside>
        </div>
      </section>
