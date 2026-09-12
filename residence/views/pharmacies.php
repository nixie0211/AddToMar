      <!-- ========================= PARTNER PHARMACIES ========================= -->
      <section class="page" data-page="pharmacies" data-live-region="residence-pharmacies" data-live-keys="pharmacies,catalog">
        <div class="partner-pharmacies-page">
          <div class="partner-pharmacies-head">
            <button type="button" class="partner-pharmacies-back" onclick="go('dashboard')">
              <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
              Back to Browse
            </button>
            <div>
              <h1 class="partner-pharmacies-title">Partner Pharmacies</h1>
              <p class="partner-pharmacies-sub">
                <?php if ($sortedResidencePharmacies === []): ?>
                No approved partner pharmacies yet. Pharmacies appear here after admin approval.
                <?php else: ?>
                Explore partner pharmacies near you.
                <?php endif; ?>
              </p>
            </div>
          </div>

          <div class="partner-pharmacies-grid" id="partner-pharmacies-grid">
            <?php if ($sortedResidencePharmacies === []): ?>
            <div class="card card-pad" style="grid-column:1 / -1;">
              <p class="hint">When a pharmacy is approved, it will show here with its live inventory.</p>
            </div>
            <?php endif; ?>
            <?php foreach ($sortedResidencePharmacies as $index => $pharmacy): ?>
            <?php
              $isNearest = $index === 0 && !empty($pharmacy['has_user_location']);
              $showDistance = isset($pharmacy['distance_km']) && (float) $pharmacy['distance_km'] < 900;
              $isOpen = !empty($pharmacy['is_open']);
              $displayName = trim((string) ($pharmacy['name'] ?? 'Pharmacy') . (!empty($pharmacy['branch']) ? ' — ' . $pharmacy['branch'] : ''));
            ?>
            <article
              class="card partner-pharmacy-card<?= $isNearest ? ' is-nearest' : '' ?>"
              role="button"
              tabindex="0"
              data-pharmacy-id="<?= htmlspecialchars($pharmacy['id'], ENT_QUOTES, 'UTF-8') ?>"
              data-pharmacy-label="<?= htmlspecialchars($pharmacy['label'], ENT_QUOTES, 'UTF-8') ?>"
              data-pharmacy-name="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
              onclick="openPharmacyProfile(this.dataset.pharmacyId)"
              onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openPharmacyProfile(this.dataset.pharmacyId);}"
              aria-label="View medicines at <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
            >
              <?php if ($isNearest): ?>
              <span class="partner-pharmacy-nearest">Nearest</span>
              <?php endif; ?>

              <div class="partner-pharmacy-card-top">
                <div class="partner-pharmacy-logo">
                  <img
                    src="<?= htmlspecialchars(residence_pharmacy_logo_src($pharmacy), ENT_QUOTES, 'UTF-8') ?>"
                    alt=""
                  >
                </div>
                <div class="partner-pharmacy-main">
                  <h2 class="partner-pharmacy-name"><?= htmlspecialchars($pharmacy['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                  <p class="partner-pharmacy-branch"><?= htmlspecialchars($pharmacy['branch'], ENT_QUOTES, 'UTF-8') ?></p>
                  <div class="partner-pharmacy-meta">
                    <?php if ($showDistance): ?>
                    <span class="partner-pharmacy-distance">
                      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-6.6 7-11.5A7 7 0 0 0 5 9.5C5 14.4 12 21 12 21Z"/></svg>
                      <?= htmlspecialchars(number_format((float) $pharmacy['distance_km'], 1), ENT_QUOTES, 'UTF-8') ?> km
                    </span>
                    <?php if (!empty($pharmacy['travel_time'])): ?>
                    <span class="partner-pharmacy-time">
                      <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                      ~<?= htmlspecialchars($pharmacy['travel_time'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php endif; ?>
                    <?php endif; ?>
                    <span class="partner-pharmacy-status <?= $isOpen ? 'is-open' : 'is-closed' ?>">
                      <?= $isOpen ? 'Open' : 'Closed' ?>
                    </span>
                  </div>
                  <?php if (!empty($pharmacy['address'])): ?>
                  <p class="partner-pharmacy-address"><?= htmlspecialchars($pharmacy['address'], ENT_QUOTES, 'UTF-8') ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
