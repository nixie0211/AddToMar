<?php

declare(strict_types=1);

$pharmacyDayOptions = pharmacy_accounts_operation_day_options();
$selectedDays = $pharmacyRegisterValues['operation_days'] ?? [];
?>
<div class="login-panel login-panel--register" id="panel-pharmacy" <?= $openPharmacyRegisterModal ? '' : 'hidden' ?>>
  <button type="button" class="register-back-btn" id="pharmacy-back-btn" aria-label="Back to sign in">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
      <path d="M6 6l12 12M18 6L6 18"/>
    </svg>
  </button>
  <div class="signup-hero">
    <div>
      <h2>Just a few steps to register your pharmacy.</h2>
      <p>Add your details, documents, and location so nearby residents can find you.</p>
    </div>
    <div class="signup-hero-foot">
      <a href="#terms" class="js-legal-link" data-legal="terms">Terms</a>
      <a href="#privacy" class="js-legal-link" data-legal="privacy">Privacy Policy</a>
      <a href="<?= htmlspecialchars(app_url() . '#contact', ENT_QUOTES, 'UTF-8') ?>">Contact Us</a>
    </div>
  </div>
  <div class="signup-card">
    <h2 class="signup-card-title" id="pharmacy-setup-title">Pharmacy details</h2>
    <p class="signup-card-sub" id="pharmacy-setup-sub">Step 1 of 4 — tell us about your pharmacy.</p>

    <ol class="signup-progress" aria-label="Pharmacy registration steps">
      <li class="is-current" data-progress="1">
        <span class="signup-progress-num">1</span>
        <span>Pharmacy details</span>
      </li>
      <li data-progress="2">
        <span class="signup-progress-num">2</span>
        <span>Documents</span>
      </li>
      <li data-progress="3">
        <span class="signup-progress-num">3</span>
        <span>Account</span>
      </li>
      <li data-progress="4">
        <span class="signup-progress-num">4</span>
        <span>Location</span>
      </li>
    </ol>

    <?php if ($openPharmacyRegisterModal && $pharmacyRegisterError !== ''): ?>
    <div class="setup-alert"><?= htmlspecialchars($pharmacyRegisterError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="" class="setup-form" id="pharmacy-register-form" enctype="multipart/form-data">
      <input type="hidden" name="action" value="register_pharmacy">
      <input type="hidden" name="latitude" id="pharmacy-register-latitude" value="<?= htmlspecialchars($_POST['latitude'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="longitude" id="pharmacy-register-longitude" value="<?= htmlspecialchars($_POST['longitude'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <div class="signup-step" data-step="1">
        <div class="pharm-logo-block">
          <label class="pharm-logo" for="pharmacy-register-logo" id="pharmacy-logo-preview" aria-label="Add pharmacy logo">
            <span class="pharm-logo-plus" aria-hidden="true">+</span>
            <span class="pharm-logo-text">Add logo</span>
            <img id="pharmacy-logo-preview-img" alt="" hidden>
            <input id="pharmacy-register-logo" class="pharm-logo-input" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/*" required>
          </label>
        </div>

        <div class="setup-pair">
          <div class="setup-field">
            <label for="pharmacy-register-name">Pharmacy name</label>
            <input id="pharmacy-register-name" name="pharmacy_name" type="text" placeholder="e.g. Wellcare Pharmacy" value="<?= htmlspecialchars($pharmacyRegisterValues['pharmacy_name'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off">
          </div>
          <div class="setup-field">
            <label for="pharmacy-register-contact">Contact number</label>
            <input id="pharmacy-register-contact" name="contact_number" type="tel" inputmode="numeric" maxlength="11" pattern="\d{11}" placeholder="09171234567" value="<?= htmlspecialchars($pharmacyRegisterValues['contact_number'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
        </div>

        <div class="setup-field">
          <span class="setup-location-label">Days and hours of operation</span>
          <p class="setup-hint">Select the days you are open, then set hours for each day.</p>
          <div class="pharm-hours">
            <?php foreach ($pharmacyDayOptions as $dayKey => $dayLabel): ?>
            <?php
              $isOpenDay = in_array($dayKey, $selectedDays, true);
              $dayOpen = (string) (($pharmacyRegisterValues['operating_hours'][$dayKey]['open'] ?? '') ?: '08:00');
              $dayClose = (string) (($pharmacyRegisterValues['operating_hours'][$dayKey]['close'] ?? '') ?: '20:00');
            ?>
            <label class="pharm-hours-row<?= $isOpenDay ? ' is-open' : '' ?>">
              <span class="pharm-day">
                <input type="checkbox" name="operation_days[]" value="<?= htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8') ?>" <?= $isOpenDay ? 'checked' : '' ?>>
                <span><?= htmlspecialchars(substr($dayLabel, 0, 3), ENT_QUOTES, 'UTF-8') ?></span>
              </span>
              <span class="pharm-hours-times">
                <input type="time" name="day_hours[<?= htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8') ?>][open]" value="<?= htmlspecialchars($dayOpen, ENT_QUOTES, 'UTF-8') ?>" <?= $isOpenDay ? 'required' : 'disabled' ?> aria-label="<?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?> opening time">
                <span class="pharm-hours-sep">to</span>
                <input type="time" name="day_hours[<?= htmlspecialchars($dayKey, ENT_QUOTES, 'UTF-8') ?>][close]" value="<?= htmlspecialchars($dayClose, ENT_QUOTES, 'UTF-8') ?>" <?= $isOpenDay ? 'required' : 'disabled' ?> aria-label="<?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?> closing time">
              </span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="signup-step" data-step="2" hidden>
        <?php
        $pharmacyDocFields = [
          ['id' => 'pharmacy-register-business-permit', 'name' => 'business_permit', 'label' => 'Business permit'],
          ['id' => 'pharmacy-register-license', 'name' => 'pharmacy_license', 'label' => 'Pharmacy license'],
          ['id' => 'pharmacy-register-bir', 'name' => 'bir_certificate', 'label' => 'BIR certificate'],
        ];
        foreach ($pharmacyDocFields as $doc):
        ?>
        <div class="pharm-doc" data-doc="<?= htmlspecialchars($doc['name'], ENT_QUOTES, 'UTF-8') ?>">
          <span class="pharm-doc-title"><?= htmlspecialchars($doc['label'], ENT_QUOTES, 'UTF-8') ?></span>
          <div class="pharm-drop">
            <input id="<?= htmlspecialchars($doc['id'], ENT_QUOTES, 'UTF-8') ?>" class="pharm-doc-input" name="<?= htmlspecialchars($doc['name'], ENT_QUOTES, 'UTF-8') ?>[]" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" multiple required>
            <svg class="pharm-drop-icon" width="36" height="36" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M7.5 18.5h9.2A3.3 3.3 0 0 0 20 15.3c0-1.6-1.1-2.9-2.6-3.2A5.1 5.1 0 0 0 7.4 10 3.8 3.8 0 0 0 4 13.7c0 2.1 1.6 3.8 3.5 3.8Z" stroke="#9aa3af" stroke-width="1.6" stroke-linejoin="round"/>
              <path d="M12 16.2V9.8M9.4 12.2 12 9.6l2.6 2.6" stroke="#9aa3af" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <p class="pharm-drop-lead">Choose files or drag &amp; drop them here.</p>
            <p class="pharm-drop-sub">JPEG, PNG, and PDF. Add one or more, up to 5 MB each.</p>
            <button type="button" class="pharm-drop-browse">Browse Files</button>
          </div>
          <div class="pharm-file-list"></div>
          <template class="pharm-file-card-tpl">
            <div class="pharm-file-card">
              <div class="pharm-file-icon" data-kind="pdf" aria-hidden="true">PDF</div>
              <div class="pharm-file-meta">
                <p class="pharm-file-name"></p>
                <p class="pharm-file-status">
                  <span class="pharm-file-size"></span>
                  <span class="pharm-file-sep">·</span>
                  <span class="pharm-file-state"></span>
                </p>
                <div class="pharm-file-bar" hidden><span></span></div>
              </div>
              <button type="button" class="pharm-file-remove" aria-label="Remove file">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
              </button>
            </div>
          </template>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="signup-step" data-step="3" hidden>
        <div class="setup-field">
          <label for="pharmacy-register-email">Login email</label>
          <input id="pharmacy-register-email" name="email" type="email" placeholder="pharmacy@email.com" value="<?= htmlspecialchars($pharmacyRegisterValues['email'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
          <p class="setup-hint">Use an existing Gmail account that you can access. We’ll send your approval notification to this email.</p>
        </div>
        <div class="setup-field">
          <label for="pharmacy-register-password">Password</label>
          <div class="input-wrap">
            <input id="pharmacy-register-password" name="password" type="password" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
            <button type="button" class="toggle-eye" id="toggle-pharmacy-register-password" aria-label="Show password">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div class="setup-field">
          <label for="pharmacy-register-password-confirm">Confirm password</label>
          <div class="input-wrap">
            <input id="pharmacy-register-password-confirm" name="password_confirm" type="password" placeholder="Re-enter password" required minlength="8" autocomplete="new-password">
            <button type="button" class="toggle-eye" id="toggle-pharmacy-register-password-confirm" aria-label="Show password">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <p class="setup-mismatch" id="pharmacy-password-mismatch" hidden role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
            Passwords do not match.
          </p>
        </div>
      </div>

      <div class="signup-step" data-step="4" hidden>
        <div class="signup-location-grid">
          <div class="signup-location-controls">
            <div class="setup-field signup-location-address">
              <label for="pharmacy-register-address">Pharmacy address</label>
              <textarea id="pharmacy-register-address" name="address" rows="2" placeholder="Street, barangay, city" required><?= htmlspecialchars($pharmacyRegisterValues['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="signup-location-tools">
              <span class="setup-location-label">Pin your pharmacy location</span>
              <div class="register-map-toolbar">
                <div class="register-map-search-wrap">
                  <input type="search" id="pharmacy-register-map-search" class="register-map-search-input" placeholder="Search barangay, street, or landmark…" autocomplete="off" aria-label="Search pharmacy location">
                  <button type="button" id="pharmacy-register-map-search-btn" class="register-map-tool-btn">Search</button>
                  <ul id="pharmacy-register-map-search-results" class="register-map-search-results" hidden></ul>
                </div>
                <button type="button" id="pharmacy-register-map-locate-btn" class="register-map-tool-btn register-map-locate-btn">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 21s7-4.35 7-10a7 7 0 1 0-14 0c0 5.65 7 10 7 10z"/><circle cx="12" cy="11" r="2.5"/>
                  </svg>
                  Use my location
                </button>
              </div>
            </div>
          </div>
          <p class="register-map-hint">Click the map or drag the pin to set your pharmacy location.</p>
          <p id="pharmacy-register-map-status" class="register-map-status" hidden aria-live="polite"></p>
          <div class="signup-location-map">
            <div class="signup-location-map-frame">
              <span class="register-map-badge">Laoag · San Nicolas · Batac</span>
              <div id="pharmacy-register-map" class="register-map" aria-label="Map for pinning pharmacy location"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="signup-nav" id="pharmacy-signup-nav">
        <label class="signup-terms" for="pharmacy-accept-terms">
          <input type="checkbox" name="accept_terms" id="pharmacy-accept-terms" value="1" required>
          <span>I accept the <a href="#terms" class="js-legal-link" data-legal="terms">Terms</a> and <a href="#privacy" class="js-legal-link" data-legal="privacy">Privacy Policy</a></span>
        </label>
        <div class="signup-nav-actions">
          <button type="button" class="signup-btn-back" id="pharmacy-signup-back-btn" hidden>Back</button>
          <button type="button" class="signup-btn-next" id="pharmacy-signup-next-btn">Next</button>
          <button type="submit" class="signup-submit" id="pharmacy-signup-submit-btn" hidden>Submit Registration</button>
        </div>
      </div>
    </form>
  </div>
</div>
