      <!-- ========================= PHARMACY LOCATOR ========================= -->
      <section class="page" data-page="locator" data-live-region="residence-locator" data-live-keys="pharmacies" data-live-skip-active="1">
        <div class="locator-wrap">
          <div class="locator-list">
            <div class="input-wrap locator-search">
              <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
              <input class="field-input" id="locator-search-input" placeholder="Search pharmacy name or address">
            </div>
            <div class="filter-chip-row">
              <button type="button" class="chip active" data-locator-filter="all">All</button>
              <button type="button" class="chip" data-locator-filter="laoag">Laoag City</button>
              <button type="button" class="chip" data-locator-filter="san-nicolas">San Nicolas</button>
              <button type="button" class="chip" data-locator-filter="batac">Batac City</button>
            </div>

            <div id="locator-pharmacy-list">
              <?php if (empty($nearbyPharmacies)): ?>
              <div class="card pharm-card">
                <div class="pharm-card-top">
                  <div style="flex:1; min-width:0;">
                    <div class="pn">No partner pharmacies yet</div>
                    <div class="pd">Approved pharmacies will appear here with their registered map location.</div>
                  </div>
                </div>
              </div>
              <?php else: ?>
              <?php foreach ($nearbyPharmacies as $index => $pharmacy): ?>
              <?php
                $displayName = trim((string) ($pharmacy['name'] ?? 'Pharmacy') . (!empty($pharmacy['branch']) ? ' — ' . $pharmacy['branch'] : ''));
                $distance = $pharmacy['distance_km'] ?? null;
                $showDistance = $distance !== null && (float) $distance < 900;
                $locatorCity = residence_pharmacy_locator_city_key($pharmacy);
              ?>
              <div
                class="card pharm-card"
                data-pharmacy-id="<?= htmlspecialchars($pharmacy['id'], ENT_QUOTES, 'UTF-8') ?>"
                data-pharmacy-label="<?= htmlspecialchars((string) ($pharmacy['label'] ?? $displayName), ENT_QUOTES, 'UTF-8') ?>"
                data-pharmacy-name="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
                data-city="<?= htmlspecialchars($locatorCity, ENT_QUOTES, 'UTF-8') ?>"
                data-logo-url="<?= htmlspecialchars(residence_pharmacy_logo_src($pharmacy), ENT_QUOTES, 'UTF-8') ?>"
                data-distance="<?= htmlspecialchars((string) ($pharmacy['distance_km'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                data-is-open="<?= !empty($pharmacy['is_open']) ? '1' : '0' ?>"
                onclick="selectPharmacy(this, '<?= htmlspecialchars($pharmacy['id'], ENT_QUOTES, 'UTF-8') ?>')"
              >
                <div class="pharm-card-top">
                  <img src="<?= htmlspecialchars(residence_pharmacy_logo_src($pharmacy), ENT_QUOTES, 'UTF-8') ?>" alt="">
                  <div style="flex:1; min-width:0;">
                    <div class="pn"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="pd">
                      <?php if ($showDistance): ?>
                      <span><svg class="icon" style="width:13px;height:13px;" viewBox="0 0 24 24"><path d="M12 21s7-6.6 7-11.5A7 7 0 0 0 5 9.5C5 14.4 12 21 12 21Z"/></svg> <?= htmlspecialchars(number_format((float) $distance, 1), ENT_QUOTES, 'UTF-8') ?> km</span>
                      <?php endif; ?>
                      <?php if (!empty($pharmacy['travel_time']) && $showDistance): ?>
                      <span><svg class="icon" style="width:13px;height:13px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> ~<?= htmlspecialchars((string) $pharmacy['travel_time'], ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="pd">
                      <span><?= htmlspecialchars((string) ($pharmacy['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="<?= !empty($pharmacy['is_open']) ? 'status-open' : 'status-closed' ?>">● <?= !empty($pharmacy['is_open']) ? 'Open' : 'Closed' ?></span>
                    </div>
                  </div>
                </div>
                <div class="pharm-card-actions">
                  <button class="btn btn-primary btn-sm" style="flex:1;" onclick="event.stopPropagation(); openPharmacyProfile(this.closest('.pharm-card').dataset.pharmacyId)">Browse Medicines</button>
                  <?php if (!empty($pharmacy['navigate_url'])): ?>
                  <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($pharmacy['navigate_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();">Directions</a>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="map-panel">
            <div id="locator-map" class="locator-map" aria-label="Map showing your location and nearby pharmacies"></div>
            <div class="map-legend" id="locator-map-legend" hidden>
              <?php if ($nearbyPharmacies === []): ?>
              No approved partner pharmacies to show on the map yet.
              <?php elseif ($userLat !== null && $userLng !== null): ?>
              Showing <?= count($nearbyPharmacies) ?> approved <?= count($nearbyPharmacies) === 1 ? 'pharmacy' : 'pharmacies' ?> near your pickup location<?= $residenceProfile['address'] !== '' ? ' · ' . htmlspecialchars($residenceProfile['address'], ENT_QUOTES, 'UTF-8') : '' ?>
              <?php else: ?>
              Showing approved partner pharmacies. Sign in with a registered location to sort by distance.
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>
