<?php

declare(strict_types=1);

$profileEmail = trim((string) ($pharmacyAccount['email'] ?? ($_SESSION['user_email'] ?? '')));
$profileContact = (string) ($pharmacyAccount['contact_number'] ?? '');
$profileAddress = (string) ($pharmacyAccount['address'] ?? '');
$profileLatitude = $pharmacyAccount['latitude'] ?? '';
$profileLongitude = $pharmacyAccount['longitude'] ?? '';
$profileInitials = pharmacy_initials($pharmacyName);
$profileDocFields = [
    ['name' => 'business_permit', 'label' => 'Business permit'],
    ['name' => 'pharmacy_license', 'label' => 'Pharmacy license'],
    ['name' => 'bir_certificate', 'label' => 'BIR certificate'],
];
?>
<section class="<?= pharmacy_view_class('settings', $activeView) ?>" id="view-settings" data-live-region="pharmacy-settings" data-live-keys="account" data-live-skip="1">
        <div class="settings-profile-page">
          <div class="settings-profile-head">
            <label class="settings-logo<?= $pharmacyLogoUrl !== '' ? ' has-image' : '' ?>" for="settings-pharmacy-logo" id="settings-logo-preview" aria-label="Change pharmacy logo">
              <span class="settings-logo-plus" aria-hidden="true">+</span>
              <span class="settings-logo-initials" id="settings-logo-initials"><?= htmlspecialchars($profileInitials, ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ($pharmacyLogoUrl !== ''): ?>
              <img id="settings-logo-preview-img" src="<?= htmlspecialchars($pharmacyLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
              <?php else: ?>
              <img id="settings-logo-preview-img" alt="" hidden>
              <?php endif; ?>
              <input id="settings-pharmacy-logo" class="settings-logo-input" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/*" form="settings-business-form">
            </label>
            <div>
              <h2 id="settings-display-name"><?= htmlspecialchars($pharmacyName, ENT_QUOTES, 'UTF-8') ?></h2>
              <div class="settings-contact-line">
                <span id="settings-display-email"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h13A2.5 2.5 0 0 1 21 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5z"/><path d="m4 6 8 6 8-6"/></svg><?= htmlspecialchars($profileEmail !== '' ? $profileEmail : 'No email on file', ENT_QUOTES, 'UTF-8') ?></span>
                <i aria-hidden="true">·</i>
                <span id="settings-display-contact"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h3l2 5-2 1.5a15 15 0 0 0 5.5 5.5L15 14l5 2v3a2 2 0 0 1-2 2C10.3 21 3 13.7 3 6a2 2 0 0 1 2-2Z"/></svg><?= htmlspecialchars($profileContact !== '' ? $profileContact : '—', ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
          </div>

          <div class="settings-profile-tabs" role="tablist" aria-label="Profile settings">
            <button type="button" class="settings-profile-tab active" role="tab" aria-selected="true" onclick="showSettingsPane('business', this)">Business information</button>
            <button type="button" class="settings-profile-tab" role="tab" aria-selected="false" onclick="showSettingsPane('security', this)">Login credentials</button>
          </div>

          <div class="settings-pane active" id="pane-business">
              <form class="settings-panel settings-business-form" id="settings-business-form" enctype="multipart/form-data" novalidate>
                <div class="settings-business-body">
                <div class="settings-business-split">
                <div class="settings-business-main">
                <div class="settings-section">
                  <div class="form-grid settings-details-grid">
                    <div class="field">
                      <label for="settings-pharmacy-name">Pharmacy name</label>
                      <input id="settings-pharmacy-name" name="pharmacy_name" type="text" placeholder="e.g. Your pharmacy name" value="<?= htmlspecialchars($pharmacyName, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="field">
                      <label for="settings-contact-number">Contact number</label>
                      <input id="settings-contact-number" name="contact_number" type="tel" inputmode="numeric" maxlength="11" pattern="\d{11}" placeholder="09171234567" value="<?= htmlspecialchars($profileContact, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="tel">
                      <p class="field-note">11-digit mobile number (e.g. 09171234567)</p>
                    </div>
                  </div>
                </div>

                <div class="settings-section">
                  <div class="settings-section-title">Days and hours of operation</div>
                  <p class="settings-hint">Select the days you are open, then set hours for each day.</p>
                  <div class="settings-hours-list">
                    <?php foreach ($pharmacyProfileDays as $dayKey => $dayLabel): ?>
                    <?php
                      $isOpenDay = in_array($dayKey, $pharmacyProfileSelectedDays, true);
                      $dayOpen = (string) (($pharmacyProfileHours[$dayKey]['open'] ?? '') ?: '08:00');
                      $dayClose = (string) (($pharmacyProfileHours[$dayKey]['close'] ?? '') ?: '20:00');
                    ?>
                    <label class="settings-hours-row<?= $isOpenDay ? ' is-open' : '' ?>">
                      <span class="settings-day">
                        <input type="checkbox" name="operation_days[]" value="<?= htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8') ?>" <?= $isOpenDay ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars(substr($dayLabel, 0, 3), ENT_QUOTES, 'UTF-8') ?></span>
                      </span>
                      <span class="settings-hours-times">
                        <input type="time" name="day_hours[<?= htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8') ?>][open]" value="<?= htmlspecialchars($dayOpen, ENT_QUOTES, 'UTF-8') ?>" <?= $isOpenDay ? 'required' : 'disabled' ?> aria-label="<?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?> opening time">
                        <span class="settings-hours-sep">to</span>
                        <input type="time" name="day_hours[<?= htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8') ?>][close]" value="<?= htmlspecialchars($dayClose, ENT_QUOTES, 'UTF-8') ?>" <?= $isOpenDay ? 'required' : 'disabled' ?> aria-label="<?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?> closing time">
                      </span>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="settings-section">
                  <div class="settings-section-title">Documents</div>
                  <p class="settings-hint">Business permit, pharmacy license, and BIR certificate submitted at registration. These cannot be changed here.</p>
                  <?php
                    $settingsDocuments = pharmacy_accounts_settings_documents($pharmacyAccount);
                  ?>
                  <div class="settings-doc">
                    <div class="settings-doc-grid">
                      <?php
                        $allDocCards = [];
                        foreach ($profileDocFields as $doc) {
                          foreach ($settingsDocuments[(string) $doc['name']] ?? [] as $docCard) {
                            $allDocCards[] = [
                              'url' => (string) ($docCard['url'] ?? ''),
                              'kind' => (string) ($docCard['kind'] ?? 'img'),
                              'label' => (string) ($docCard['label'] ?? $doc['label']),
                              'filename' => (string) ($docCard['filename'] ?? ''),
                            ];
                          }
                        }
                      ?>
                      <?php foreach ($allDocCards as $docCard): ?>
                      <button type="button" class="settings-doc-card" data-permit-url="<?= htmlspecialchars($docCard['url'], ENT_QUOTES, 'UTF-8') ?>" data-permit-label="<?= htmlspecialchars($docCard['label'], ENT_QUOTES, 'UTF-8') ?>" data-permit-kind="<?= htmlspecialchars($docCard['kind'], ENT_QUOTES, 'UTF-8') ?>">
                        <span class="settings-doc-icon settings-doc-icon--<?= htmlspecialchars($docCard['kind'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                          <?php if ($docCard['kind'] === 'pdf'): ?>
                          <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
                            <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#E2574C"/>
                            <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".35"/>
                            <text x="26" y="42" text-anchor="middle" fill="#fff" font-size="13" font-weight="800" font-family="Manrope, sans-serif">PDF</text>
                          </svg>
                          <?php else: ?>
                          <img src="<?= htmlspecialchars($docCard['url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                          <?php endif; ?>
                        </span>
                        <span class="settings-doc-meta">
                          <strong><?= htmlspecialchars($docCard['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                          <small><?= htmlspecialchars($docCard['filename'], ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                      </button>
                      <?php endforeach; ?>
                      <?php if ($allDocCards === []): ?>
                      <p class="settings-doc-empty">No document on file</p>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                </div>

                <div class="settings-business-side">
                <div class="settings-section">
                  <div class="settings-section-title">Pharmacy location</div>
                  <p class="settings-hint">Update your address and pin your pharmacy on the map.</p>
                  <input type="hidden" name="latitude" id="settings-pharmacy-latitude" value="<?= htmlspecialchars((string) $profileLatitude, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="longitude" id="settings-pharmacy-longitude" value="<?= htmlspecialchars((string) $profileLongitude, ENT_QUOTES, 'UTF-8') ?>">
                  <div class="settings-location-card">
                    <div class="field">
                      <label for="settings-pharmacy-address">Pharmacy address</label>
                      <textarea id="settings-pharmacy-address" name="address" rows="3" placeholder="Street, barangay, city" required><?= htmlspecialchars($profileAddress, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="settings-location-map">
                      <div class="settings-location-map-head">
                        <label class="settings-location-label" for="settings-pharmacy-map-search">Pin your pharmacy location</label>
                        <span class="settings-map-badge-inline">Laoag · San Nicolas · Batac</span>
                      </div>

                      <div class="settings-map-toolbar">
                        <div class="settings-map-search-wrap">
                          <input type="search" id="settings-pharmacy-map-search" class="settings-map-search-input" placeholder="Search barangay, street, or landmark…" autocomplete="off" aria-label="Search pharmacy location">
                          <button type="button" id="settings-pharmacy-map-search-btn" class="settings-map-tool-btn settings-map-search-btn">Search</button>
                          <ul id="settings-pharmacy-map-search-results" class="settings-map-search-results" hidden></ul>
                        </div>
                        <button type="button" id="settings-pharmacy-map-locate-btn" class="settings-map-tool-btn settings-map-locate-btn">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 21s7-4.35 7-10a7 7 0 1 0-14 0c0 5.65 7 10 7 10z"/><circle cx="12" cy="11" r="2.5"/>
                          </svg>
                          Use my location
                        </button>
                      </div>

                      <p class="settings-map-hint">Click the map or drag the pin to set your pharmacy location.</p>
                      <p id="settings-pharmacy-map-status" class="settings-map-status" hidden aria-live="polite"></p>

                      <div class="settings-map-frame">
                        <div id="settings-pharmacy-map" class="settings-map" aria-label="Map for pinning pharmacy location"></div>
                      </div>
                    </div>
                  </div>
                </div>
                </div>
                </div>
                </div>

                <div class="settings-alert" id="settings-business-alert" hidden role="alert"></div>

                <div class="settings-panel-foot">
                  <button type="submit" class="btn btn-accent" id="settings-business-save"><span>Save changes</span></button>
                </div>
              </form>
            </div>

            <div class="settings-pane" id="pane-security">
              <div class="settings-panel">
                <div class="settings-section settings-section--flush">
                  <div class="form-grid">
                    <div class="field"><label for="settings-login-email">Email address</label><input id="settings-login-email" type="email" value="<?= htmlspecialchars($profileEmail, ENT_QUOTES, 'UTF-8') ?>" readonly autocomplete="email"></div>
                    <div class="field"><label for="settings-current-password">Current password</label><input id="settings-current-password" type="password" placeholder="Current password" autocomplete="current-password"></div>
                    <div class="field"><label for="settings-new-password">New password</label><input id="settings-new-password" type="password" placeholder="New password" autocomplete="new-password"></div>
                    <div class="field"><label for="settings-confirm-password">Confirm new password</label><input id="settings-confirm-password" type="password" placeholder="Confirm new password" autocomplete="new-password"></div>
                  </div>
                </div>
                <div class="settings-alert" id="settings-security-alert" hidden role="alert"></div>
                <div class="settings-panel-foot">
                  <button type="button" class="btn btn-accent" id="settings-security-save">Save changes</button>
                </div>
              </div>
            </div>
        </div>

        <div class="rx-viewer rx-crop-modal" id="permit-viewer" hidden>
          <button type="button" class="rx-crop-backdrop" id="permit-viewer-close" aria-label="Close document preview"></button>
          <div class="rx-crop-sheet" role="dialog" aria-modal="true" aria-labelledby="permit-viewer-title">
            <header class="rx-crop-head">
              <h3 id="permit-viewer-title">Document preview</h3>
            </header>
            <div class="rx-crop-stage" id="permit-viewer-stage">
              <div class="rx-crop-media" id="permit-viewer-media">
                <img id="permit-viewer-image" src="" alt="Uploaded document">
                <iframe id="permit-viewer-frame" title="Uploaded document" hidden></iframe>
              </div>
              <p class="rx-crop-missing" id="permit-viewer-missing" hidden>The uploaded document could not be loaded.</p>
            </div>
            <div class="rx-crop-zoombar">
              <input type="range" class="rx-crop-zoom" id="permit-viewer-zoom" min="1" max="2.4" step="0.02" value="1" aria-label="Zoom document preview">
            </div>
            <footer class="rx-crop-foot">
              <button type="button" class="rx-crop-cancel" id="permit-viewer-close-btn">Cancel</button>
            </footer>
          </div>
        </div>
      </section>
