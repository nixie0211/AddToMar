      <!-- ========================= CHECKOUT ========================= -->
      <?php $gcashLogoUrl = htmlspecialchars(function_exists('app_url') ? app_url('gcash.png') : '../gcash.png', ENT_QUOTES, 'UTF-8'); ?>
      <section class="page" data-page="checkout" data-live-region="residence-checkout" data-live-keys="catalog,cart" data-live-skip="1">
        <div class="checkout-page" id="checkout-page-root">
          <div class="checkout-layout">
            <div class="checkout-box card card-pad checkout-card checkout-summary-card">
              <h3 class="checkout-summary-title"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>Order summary</h3>
              <div id="checkout-summary-lines"></div>
              <div class="checkout-price-breakdown">
                <div><span>Subtotal</span><b id="checkout-subtotal">₱0.00</b></div>
                <div><span>VAT (12%)</span><b id="checkout-vat">₱0.00</b></div>
              </div>
              <div class="summary-row total"><span>Total amount</span><span id="checkout-total">₱0.00</span></div>
            </div>

            <div class="checkout-box card card-pad checkout-card checkout-payment-card" id="checkout-payment-card">
              <header class="pd-head">
                <span class="pd-head-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9h16v10H4z"/><path d="M4 9V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2"/><path d="M16 14h2"/><path d="M12 3 5 6v2"/></svg>
                </span>
                <div>
                  <h3>Payment Details</h3>
                  <p>Complete your purchase by providing your payment details.</p>
                </div>
                <span class="pd-secure">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                  Secure
                </span>
              </header>

              <button type="button" class="paymongo-wallet-btn" hidden onclick="startPayMongoPayment()">Pay with GCash</button>

              <div class="pd-fields payment-details-grid payment-card-fields">
                <label class="full-field"><span>Full name</span><input type="text" id="payment-payer-name" placeholder="Your full name" value="<?= htmlspecialchars((string) ($residenceProfile['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="pd-contact"><span>Contact number</span><input type="tel" id="payment-payer-number" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars((string) ($residenceProfile['contact_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="pd-address"><span>Address</span><input type="text" id="payment-payer-address" placeholder="Complete address" value="<?= htmlspecialchars((string) ($residenceProfile['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
                <label class="full-field"><span>Email for receipt</span><input type="email" id="payment-payer-email" placeholder="you@gmail.com" value="<?= htmlspecialchars((string) ($residenceProfile['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
              </div>

              <button type="button" class="pd-pay-btn" id="checkout-gcash-pay" onclick="startGCashFromCard()">Pay</button>

              <div class="pd-total">
                <span>Order Total</span>
                <strong id="checkout-grand-total">₱0.00</strong>
              </div>
              <span id="checkout-total" hidden>₱0.00</span>
              <span id="checkout-pay-now" hidden>₱0.00</span>
              <span id="checkout-down-payment" hidden>₱0.00</span>

              <div class="pd-status" id="checkout-payment-status-box">
                <span class="pd-gcash-logo" aria-hidden="true"><img src="<?= $gcashLogoUrl ?>" alt=""></span>
                <div>
                  <small>Payment status</small>
                  <b id="checkout-payment-status-copy">Waiting for GCash</b>
                </div>
                <span class="pd-status-badge" id="checkout-payment-status">Pending</span>
              </div>

              <div class="pd-remaining">
                <span class="pd-remaining-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18v8H3z"/><path d="M7 10V8a5 5 0 0 1 10 0v2"/><path d="M12 14h.01"/></svg>
                </span>
                <div>
                  <b>Remaining Balance</b>
                  <small id="checkout-balance-note">Remaining 50% will be paid upon pick up.</small>
                </div>
                <strong id="checkout-pay-later">₱0.00</strong>
              </div>

              <button class="btn btn-block checkout-confirm-btn pd-complete" id="checkout-place-order-btn" type="button" onclick="placeOrder()" disabled>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8v4H8z"/><path d="M6 7h12v14H6z"/><path d="M9 12h6M9 16h4"/></svg>
                Complete Order
                <span aria-hidden="true">›</span>
              </button>
            </div>
          </div>

          <footer class="checkout-privacy-note">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.8 8.2 7 10 4.2-1.8 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
            <span><strong>Your privacy is important to us.</strong> Your prescription and personal information are secure and will only be used for this order.</span>
          </footer>

          <div class="pickup-slot-grid" id="checkout-pickup-slots" hidden>
            <button type="button" class="slot-btn" data-slot="9:00–10:00 AM">9:00–10:00 AM</button>
            <button type="button" class="slot-btn selected" data-slot="1:00–2:00 PM">1:00–2:00 PM</button>
            <button type="button" class="slot-btn" data-slot="4:00–5:00 PM">4:00–5:00 PM</button>
          </div>
        </div>
        <div class="prescription-camera-modal" id="prescription-camera-modal" hidden role="dialog" aria-modal="true" aria-label="Take prescription photo">
          <div class="prescription-camera-backdrop" onclick="closePrescriptionCamera()"></div>
          <div class="prescription-camera-panel">
            <div class="prescription-camera-head"><h3>Take prescription photo</h3><button type="button" onclick="closePrescriptionCamera()" aria-label="Close camera">×</button></div>
            <video id="prescription-camera-video" autoplay playsinline></video>
            <canvas id="prescription-camera-canvas" hidden></canvas>
            <div class="prescription-camera-actions"><button type="button" class="btn btn-ghost" onclick="closePrescriptionCamera()">Cancel</button><button type="button" class="btn btn-primary" onclick="capturePrescriptionPhoto()">Capture photo</button></div>
          </div>
        </div>
      </section>
