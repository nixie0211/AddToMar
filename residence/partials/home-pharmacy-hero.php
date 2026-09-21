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
                  $partnerPharmaciesPreview = array_values($sortedResidencePharmacies ?? []);
                  if (count($partnerPharmaciesPreview) > 16) {
                      $partnerPharmaciesPreview = array_slice($partnerPharmaciesPreview, 0, 16);
                  }
                  if ($partnerPharmaciesPreview === []): ?>
                  <p class="hint" style="padding:8px 4px 0;">No approved partner pharmacies yet. They will appear here after admin approval.</p>
                  <?php else:
                    $partnerMarqueeCopies = count($partnerPharmaciesPreview) < 5 ? 4 : 2;
                    $renderPartnerMallSet = static function (array $pharmacies, bool $markNearest, bool $hiddenCopy): void {
                  ?>
                      <div class="home-pharmacy-mall-set home-pharmacy-mall-slide--compact"<?= $hiddenCopy ? ' aria-hidden="true"' : '' ?>>
                        <?php foreach ($pharmacies as $partnerIndex => $pharmacy):
                            $isNearestPartner = $markNearest && $partnerIndex === 0 && !empty($pharmacy['has_user_location']);
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
                            data-pharmacy-label="<?= htmlspecialchars((string) ($pharmacy['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-pharmacy-name="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>"
                            onclick="openPharmacyProfile(this.dataset.pharmacyId)"
                            tabindex="<?= $hiddenCopy ? '-1' : '0' ?>"
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
                                <span class="home-pharmacy-mall-name"><?= htmlspecialchars((string) ($pharmacy['name'] ?? 'Pharmacy'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="home-pharmacy-mall-meta">
                                  <?php if ($showDistance): ?>
                                  <span class="home-pharmacy-mall-distance"><?= htmlspecialchars(number_format((float) $distanceKm, 1), ENT_QUOTES, 'UTF-8') ?> km</span>
                                  <?php endif; ?>
                                  <span class="home-pharmacy-mall-status <?= $isOpen ? 'is-open' : 'is-closed' ?>"><?= $isOpen ? 'Open' : 'Closed' ?></span>
                                </span>
                              </span>
                            </span>
                          </button>
                        <?php endforeach; ?>
                      </div>
                  <?php
                    };
                  ?>
                  <div class="home-pharmacy-mall-viewport is-marquee" id="pharmacy-logo-viewport">
                    <div class="home-pharmacy-mall-track is-marquee" id="pharmacy-logo-track">
                      <?php for ($copy = 0; $copy < $partnerMarqueeCopies; $copy++): ?>
                        <?php $renderPartnerMallSet($partnerPharmaciesPreview, $copy === 0, $copy > 0); ?>
                      <?php endfor; ?>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
