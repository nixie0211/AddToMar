            <div class="home-store-hero" id="home-store-hero" hidden>
              <div class="home-store-hero-toolbar">
                <button type="button" class="home-store-hero-back" onclick="clearPharmacyFilter()">
                  <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                  All partner pharmacies
                </button>
              </div>

              <div class="home-store-hero-stage">
                <div class="home-store-hero-banner" id="home-store-hero-banner" aria-hidden="true">
                  <div class="home-store-hero-banner-pattern"></div>
                  <div class="home-store-hero-banner-glow"></div>
                  <div class="home-store-hero-banner-glow home-store-hero-banner-glow--left"></div>
                </div>

                <div class="home-store-hero-overlap">
                  <div class="home-store-hero-logo-circle" id="home-store-hero-logo-wrap">
                    <img class="home-store-hero-logo" id="home-store-hero-logo" alt="" hidden>
                  </div>

                  <div class="home-store-hero-info-card">
                    <div class="home-store-hero-info-grid">
                      <div class="home-store-hero-info-col">
                        <div class="home-store-hero-field">
                          <span class="home-store-hero-field-label">Pharmacy Name</span>
                          <span class="home-store-hero-field-value" id="home-store-hero-name"></span>
                        </div>
                        <div class="home-store-hero-field">
                          <span class="home-store-hero-field-label">Address</span>
                          <span class="home-store-hero-field-value" id="home-store-hero-address">—</span>
                        </div>
                        <div class="home-store-hero-field">
                          <span class="home-store-hero-field-label">Contact Number</span>
                          <span class="home-store-hero-field-value" id="home-store-hero-contact">—</span>
                        </div>
                      </div>
                      <div class="home-store-hero-info-col home-store-hero-info-col--right">
                        <div class="home-store-hero-field">
                          <span class="home-store-hero-field-label">Email Address</span>
                          <span class="home-store-hero-field-value" id="home-store-hero-email">—</span>
                        </div>
                        <div class="home-store-hero-field">
                          <span class="home-store-hero-field-label">Chat</span>
                          <button type="button" class="home-store-hero-link" onclick="openStorePharmacyChat()">
                            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Message this pharmacy
                          </button>
                        </div>
                        <div class="home-store-hero-field">
                          <span class="home-store-hero-field-label">View Map Location</span>
                          <button type="button" class="home-store-hero-link" id="home-store-hero-map-btn" onclick="openStoreMapLocation()">
                            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.6 7-11.5A7 7 0 0 0 5 9.5C5 14.4 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></svg>
                            Open map directions
                          </button>
                        </div>
                      </div>
                    </div>
                    <div class="home-store-hero-status-row" id="home-store-hero-status" hidden></div>
                  </div>
                </div>
              </div>
            </div>
