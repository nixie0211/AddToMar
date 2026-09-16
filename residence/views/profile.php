      <!-- ========================= PROFILE ========================= -->
      <?php
      $profileName = $residenceProfile['full_name'] !== '' ? $residenceProfile['full_name'] : 'Guest Customer';
      $profileEmail = $residenceProfile['email'] !== '' ? $residenceProfile['email'] : 'Not signed in with a registered account';
      $profileContact = $residenceProfile['contact_number'] !== '' ? residence_format_contact($residenceProfile['contact_number']) : '—';
      $profileInitials = strtoupper(substr(preg_replace('/\s+/', '', $profileName) ?: 'N', 0, 1));
      $savedAddresses = customer_addresses_list($residenceProfile['email']);
      ?>
      <section class="page" data-page="profile" data-live-region="residence-profile" data-live-keys="account" data-live-skip-active="1">
        <div class="profile-page">
        <div class="profile-head">
          <div class="profile-avatar-wrap">
            <div class="profile-avatar"><?= htmlspecialchars($profileInitials, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div>
            <h2 id="profile-display-name" style="font-size:20px;"><?= htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="profile-contact-line">
              <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h13A2.5 2.5 0 0 1 21 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5z"/><path d="m4 6 8 6 8-6"/></svg><?= htmlspecialchars($profileEmail, ENT_QUOTES, 'UTF-8') ?></span>
              <i aria-hidden="true">·</i>
              <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h3l2 5-2 1.5a15 15 0 0 0 5.5 5.5L15 14l5 2v3a2 2 0 0 1-2 2C10.3 21 3 13.7 3 6a2 2 0 0 1 2-2Z"/></svg><?= htmlspecialchars($profileContact, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          </div>
        </div>

        <div class="profile-tabs">
          <div class="p-tab active" onclick="profileTab(this,'p-personal')">Personal Info</div>
          <div class="p-tab" onclick="profileTab(this,'p-address')">Saved Addresses</div>
        </div>

        <div class="p-panel card card-pad" id="p-personal">
          <div class="profile-fields">
            <div class="field"><label>Full name</label><input class="field-input" id="profile-full-name" value="<?= htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="field"><label>Mobile number</label><input class="field-input" id="profile-contact-number" value="<?= htmlspecialchars($profileContact, ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" maxlength="11" oninput="this.value=this.value.replace(/\D/g,'').slice(0,11)"></div>
            <div class="field"><label>Email address</label><input class="field-input" id="profile-email" value="<?= htmlspecialchars($profileEmail, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email"></div>
            <div class="field">
              <label>Current password</label>
              <div class="input-wrap">
                <input class="field-input" id="profile-current-password" type="password" placeholder="Current password" autocomplete="current-password">
                <button type="button" class="toggle-eye" aria-label="Show password" hidden>
                  <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                </button>
              </div>
            </div>
            <div class="field">
              <label>New password</label>
              <div class="input-wrap">
                <input class="field-input" id="profile-new-password" type="password" placeholder="New password" autocomplete="new-password">
                <button type="button" class="toggle-eye" aria-label="Show password" hidden>
                  <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                </button>
              </div>
            </div>
            <div class="field">
              <label>Confirm new password</label>
              <div class="input-wrap">
                <input class="field-input" id="profile-confirm-password" type="password" placeholder="Confirm new password" autocomplete="new-password">
                <button type="button" class="toggle-eye" aria-label="Show password" hidden>
                  <svg class="eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                </button>
              </div>
            </div>
          </div>
          <div class="profile-actions">
            <button class="btn btn-primary" type="button" id="save-profile-btn" onclick="saveProfileChanges()">Save changes</button>
          </div>
        </div>

        <div class="p-panel card card-pad" id="p-address" style="display:none;">
          <div class="saved-addresses-head">
            <div class="saved-addresses-title">
              <span class="saved-addresses-title-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
              <div><h3>Saved Addresses</h3><p>Manage your saved addresses for faster and easier orders.</p></div>
            </div>
            <button type="button" class="btn saved-addresses-add" onclick="openAddressMapPicker('add')">+ <span>Add new address</span></button>
          </div>
          <div id="saved-address-list">
            <?php if ($savedAddresses === []): ?>
            <p class="muted" id="saved-address-empty">No saved addresses yet. Add one from the map.</p>
            <?php else: ?>
            <?php foreach ($savedAddresses as $savedAddress): ?>
            <div class="addr-card<?= !empty($savedAddress['is_current']) ? ' addr-card--current' : '' ?>" data-address-id="<?= (int) $savedAddress['id'] ?>">
              <div class="ai"><svg class="icon" style="width:18px;height:18px;" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg></div>
              <div class="addr-card-copy">
                <div class="addr-card-title">
                  <?= !empty($savedAddress['is_current']) ? 'Current address' : 'Saved address' ?>
                  <?php if (!empty($savedAddress['is_current'])): ?><span class="addr-default">Default</span><?php endif; ?>
                </div>
                <div class="addr-card-address"><?= htmlspecialchars($savedAddress['address'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="addr-card-actions">
                <?php if (empty($savedAddress['is_current'])): ?>
                <button type="button" class="btn btn-ghost btn-sm" onclick="setCurrentSavedAddress(<?= (int) $savedAddress['id'] ?>)">Use as current</button>
                <?php endif; ?>
                <button type="button" class="btn btn-ghost btn-sm" onclick="openAddressMapPicker('edit', <?= (int) $savedAddress['id'] ?>)">Edit</button>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        </div>
      </section>
