            <div class="home-hero" id="home-partner-hero">
              <div class="home-pharmacy-mall">
                <div class="home-pharmacy-mall-head">
                  <h2 class="home-pharmacy-mall-title">Partner Pharmacies</h2>
                  <button type="button" class="home-pharmacy-mall-see-all" onclick="go('pharmacies')">
                    See All
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                  </button>
                </div>

                <div class="home-pharmacy-mall-wrap">
                  <?php
                  $partnerPharmaciesPreview = array_slice($sortedResidencePharmacies ?? [], 0, 5);
                  if ($partnerPharmaciesPreview === []): ?>
                  <p class="hint" style="padding:8px 4px 0;">No approved partner pharmacies yet. They will appear here after admin approval.</p>
                  <?php else: ?>
                  <div class="home-pharmacy-mall-viewport" id="pharmacy-logo-viewport">
                    <div class="home-pharmacy-mall-track" id="pharmacy-logo-track">
                      <?php
                      $partnerPharmacyIndex = 0;
                      foreach (residence_pharmacy_slides(5, $partnerPharmaciesPreview) as $slideIndex => $slidePharmacies):
                      ?>
                      <div class="home-pharmacy-mall-slide home-pharmacy-mall-slide--compact" data-slide="<?= (int) $slideIndex ?>" aria-label="Pharmacy slide <?= (int) $slideIndex + 1 ?>">
                        <?php foreach ($slidePharmacies as $pharmacy): ?>
                          <?php if ($pharmacy === null): ?>
                          <span class="home-pharmacy-mall-cell home-pharmacy-mall-cell--empty" aria-hidden="true"></span>
                          <?php else:
                            $isNearestPartner = $partnerPharmacyIndex === 0 && !empty($pharmacy['has_user_location']);
                            $partnerPharmacyIndex++;
                            $distanceKm = $pharmacy['distance_km'] ?? null;
                            $showDistance = $distanceKm !== null && (float) $distanceKm < 900;
                            $isOpen = !empty($pharmacy['is_open']);
                            $displayName = trim((string) ($pharmacy['name'] ?? 'Pharmacy') . (!empty($pharmacy['branch']) ? ' — ' . $pharmacy['branch'] : ''));
                          ?>
                          <button
                            type="button"
                            class="home-pharmacy-mall-cell<?= $isNearestPartner ? ' is-nearest' : '' ?>"
                            style="--pharm-color: <?= htmlspecialchars($pharmacy['color'] ?? '#1D5FA8', ENT_QUOTES, 'UTF-8') ?>;"
                            data-pharmacy-id="<?= htmlspecialchars((string) ($pharmacy['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-pharmacy-label="<?= htmlspecialchars($pharmacy['label'], ENT_QUOTES, 'UTF-8') ?>"
                            data-pharmacy-name="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
                            onclick="openPharmacyProfile(this.dataset.pharmacyId)"
                            aria-label="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?><?= $showDistance ? ', ' . number_format((float) $distanceKm, 1) . ' km away' : '' ?>, <?= $isOpen ? 'open now' : 'closed' ?>"
                          >
                            <?php if ($isNearestPartner): ?>
                            <span class="home-pharmacy-mall-nearest-badge">Nearest</span>
                            <?php endif; ?>
                            <span class="home-pharmacy-mall-brand">
                              <span class="home-pharmacy-mall-logo-mark">
                                <img
                                  class="home-pharmacy-mall-logo-img"
                                  src="<?= htmlspecialchars(residence_pharmacy_logo_src($pharmacy), ENT_QUOTES, 'UTF-8') ?>"
                                  alt=""
                                >
                              </span>
                              <span class="home-pharmacy-mall-copy">
                                <span class="home-pharmacy-mall-name"><?= htmlspecialchars($pharmacy['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="home-pharmacy-mall-meta">
                                  <?php if ($showDistance): ?>
                                  <span class="home-pharmacy-mall-distance"><?= htmlspecialchars(number_format((float) $distanceKm, 1), ENT_QUOTES, 'UTF-8') ?> km</span>
                                  <?php endif; ?>
                                  <span class="home-pharmacy-mall-status <?= $isOpen ? 'is-open' : 'is-closed' ?>"><?= $isOpen ? 'Open' : 'Closed' ?></span>
                                </span>
                              </span>
                            </span>
                          </button>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <button
                    type="button"
                    class="home-pharmacy-mall-nav home-pharmacy-mall-prev"
                    id="pharmacy-logo-prev"
                    aria-label="Previous partner pharmacies"
                    onclick="scrollPharmacyCarousel(-1)"
                    hidden
                  >
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                  </button>

                  <button
                    type="button"
                    class="home-pharmacy-mall-nav home-pharmacy-mall-next"
                    id="pharmacy-logo-next"
                    aria-label="Next partner pharmacies"
                    onclick="scrollPharmacyCarousel(1)"
                    hidden
                  >
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                  </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
