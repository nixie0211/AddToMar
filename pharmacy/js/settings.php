<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

let settingsMapReady = false;

window.initSettingsBusinessMap = function initSettingsBusinessMap() {
  if (!document.getElementById('settings-pharmacy-map')) return;

  const run = () => {
    if (typeof window.initPharmacyRegisterMap === 'function') {
      window.initPharmacyRegisterMap();
    }

    const logoPreview = document.getElementById('settings-logo-preview-img');
    const logoSrc = logoPreview?.getAttribute('src') || '';
    if (logoPreview && !logoPreview.hidden && logoSrc && logoSrc !== window.location.href) {
      window.updatePharmacyRegisterMapLogo?.(logoSrc);
    }
  };

  setTimeout(run, 80);
  setTimeout(run, 280);
};

function showSettingsPane(name, el) {
  document.querySelectorAll('.settings-profile-tab').forEach(function (tab) {
    const on = tab === el;
    tab.classList.toggle('active', on);
    tab.setAttribute('aria-selected', on ? 'true' : 'false');
  });
  document.querySelectorAll('.settings-pane').forEach(p => p.classList.remove('active'));
  document.getElementById('pane-' + name).classList.add('active');

  if (name === 'business' && typeof window.initSettingsBusinessMap === 'function') {
    window.initSettingsBusinessMap();
  }
}

function showSettingsAlert(message, type) {
  const alert = document.getElementById('settings-business-alert');
  if (!alert) return;
  alert.hidden = false;
  alert.textContent = message;
  alert.classList.remove('is-error', 'is-success');
  alert.classList.add(type === 'success' ? 'is-success' : 'is-error');
}

function hideSettingsAlert() {
  const alert = document.getElementById('settings-business-alert');
  if (!alert) return;
  alert.hidden = true;
  alert.textContent = '';
  alert.classList.remove('is-error', 'is-success');
}

(function setupSettingsDayHours() {
  const rows = document.querySelectorAll('#settings-business-form .settings-hours-row');
  if (!rows.length) return;

  function syncRows(locked) {
    const isLocked = locked === true || (locked !== false && !document.getElementById('settings-profile-page')?.classList.contains('is-editing'));
    rows.forEach((row) => {
      const checked = !!row.querySelector('input[name="operation_days[]"]')?.checked;
      row.classList.toggle('is-open', checked);
      row.querySelectorAll('input[type="time"]').forEach((input) => {
        input.disabled = isLocked || !checked;
        input.required = !isLocked && checked;
        input.setCustomValidity('');
      });
    });
  }

  rows.forEach((row) => {
    const checkbox = row.querySelector('input[name="operation_days[]"]');
    checkbox?.addEventListener('change', () => syncRows(false));
  });

  window.syncSettingsHoursRows = syncRows;
  syncRows(true);
})();

(function setupSettingsLogo() {
  const logoBox = document.getElementById('settings-logo-preview');
  const logoInput = document.getElementById('settings-pharmacy-logo');
  const logoPreview = document.getElementById('settings-logo-preview-img');
  const logoText = logoBox?.querySelector('.settings-logo-text');
  if (!logoInput || !logoPreview || !logoBox) return;

  function syncMapLogo() {
    const src = logoPreview.getAttribute('src') || '';
    if (logoPreview.hidden || !src || src === window.location.href) return;
    if (typeof window.updatePharmacyRegisterMapLogo === 'function') {
      window.updatePharmacyRegisterMapLogo(src);
    }
  }

  logoInput.addEventListener('change', () => {
    const file = logoInput.files?.[0];
    if (!file || !file.type.startsWith('image/')) return;

    logoPreview.src = URL.createObjectURL(file);
    logoPreview.hidden = false;
    logoBox.classList.add('has-image');
    if (logoText) logoText.hidden = true;
    syncMapLogo();
  });

  syncMapLogo();
})();

(function setupSettingsContact() {
  const contactInput = document.getElementById('settings-contact-number');
  contactInput?.addEventListener('input', () => {
    contactInput.value = contactInput.value.replace(/\D/g, '').slice(0, 11);
  });
})();

(function setupSettingsEditing() {
  const page = document.getElementById('settings-profile-page');
  const form = document.getElementById('settings-business-form');
  const editBtn = document.getElementById('settings-profile-edit');
  const cancelBtn = document.getElementById('settings-business-cancel');
  const saveBtn = document.getElementById('settings-business-save');
  const securityCancel = document.getElementById('settings-security-cancel');
  const securitySave = document.getElementById('settings-security-save');
  const logoInput = document.getElementById('settings-pharmacy-logo');
  const logoPreview = document.getElementById('settings-logo-preview-img');
  const logoBox = document.getElementById('settings-logo-preview');
  if (!page || !form || !editBtn) return;

  let logoOriginal = {
    src: logoPreview?.getAttribute('src') || '',
    hidden: !!logoPreview?.hidden,
    hasImage: !!logoBox?.classList.contains('has-image'),
  };

  function setEditing(on) {
    page.classList.toggle('is-editing', on);
    form.querySelectorAll('#settings-pharmacy-name, #settings-contact-number, #settings-pharmacy-address').forEach((el) => {
      el.readOnly = !on;
    });
    form.querySelectorAll('input[name="operation_days[]"]').forEach((el) => {
      el.disabled = !on;
    });
    ['settings-pharmacy-map-search', 'settings-pharmacy-map-search-btn', 'settings-pharmacy-map-locate-btn'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.disabled = !on;
    });
    if (logoInput) logoInput.disabled = !on;
    if (typeof window.syncSettingsHoursRows === 'function') window.syncSettingsHoursRows(!on);
    if (cancelBtn) cancelBtn.hidden = !on;
    if (saveBtn) saveBtn.hidden = !on;
    if (securityCancel) securityCancel.hidden = !on;
    if (securitySave) securitySave.hidden = !on;
    ['settings-current-password', 'settings-new-password', 'settings-confirm-password'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.disabled = !on;
    });
  }

  function restoreLogo() {
    if (!logoPreview || !logoBox) return;
    if (logoInput) logoInput.value = '';
    if (logoOriginal.src) {
      logoPreview.src = logoOriginal.src;
      logoPreview.hidden = false;
      logoBox.classList.add('has-image');
      window.updatePharmacyRegisterMapLogo?.(logoOriginal.src);
      return;
    }
    logoPreview.removeAttribute('src');
    logoPreview.hidden = true;
    logoBox.classList.remove('has-image');
  }

  function snapshotAfterSave() {
    form.querySelectorAll('input, textarea').forEach((el) => {
      if (el.type === 'checkbox') el.defaultChecked = el.checked;
      else if (el.type !== 'file') el.defaultValue = el.value;
    });
    logoOriginal = {
      src: logoPreview?.getAttribute('src') || '',
      hidden: !!logoPreview?.hidden,
      hasImage: !!logoBox?.classList.contains('has-image'),
    };
  }

  function cancelEditing() {
    form.reset();
    restoreLogo();
    hideSettingsAlert();
    ['settings-current-password', 'settings-new-password', 'settings-confirm-password'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    setEditing(false);
  }

  editBtn.addEventListener('click', () => {
    hideSettingsAlert();
    setEditing(true);
  });
  cancelBtn?.addEventListener('click', cancelEditing);
  securityCancel?.addEventListener('click', cancelEditing);

  window.setSettingsEditing = setEditing;
  window.snapshotSettingsForm = snapshotAfterSave;
  setEditing(false);
})();

(function setupSettingsBusinessForm() {
  const form = document.getElementById('settings-business-form');
  if (!form) return;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideSettingsAlert();

    const days = form.querySelectorAll('input[name="operation_days[]"]:checked');
    if (days.length === 0) {
      showSettingsAlert('Select at least one day of operation.', 'error');
      return;
    }

    for (const day of days) {
      const row = day.closest('.settings-hours-row');
      const open = row?.querySelector('input[type="time"][name*="[open]"]');
      const close = row?.querySelector('input[type="time"][name*="[close]"]');
      if (open && close && open.value && close.value && open.value >= close.value) {
        showSettingsAlert('Closing time must be later than opening time for each selected day.', 'error');
        close.focus();
        return;
      }
    }

    const lat = document.getElementById('settings-pharmacy-latitude')?.value?.trim();
    const lng = document.getElementById('settings-pharmacy-longitude')?.value?.trim();
    if (!lat || !lng) {
      showSettingsAlert('Please pin your pharmacy location on the map.', 'error');
      return;
    }

    if (!form.reportValidity()) return;

    const saveBtn = document.getElementById('settings-business-save');
    const saveLabel = saveBtn?.querySelector('span');
    const originalLabel = saveLabel?.textContent || 'Save changes';
    if (saveBtn) saveBtn.disabled = true;
    if (saveLabel) saveLabel.textContent = 'Saving...';

    try {
      const response = await fetch('api/save-profile.php', {
        method: 'POST',
        body: new FormData(form),
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.error || 'Could not save your pharmacy profile.');
      }

      showSettingsAlert(data.message || 'Pharmacy profile updated.', 'success');

      if (data.account?.pharmacy_name) {
        const topbarName = document.querySelector('.topbar-profile .name');
        if (topbarName) topbarName.textContent = data.account.pharmacy_name;
        const displayName = document.getElementById('settings-display-name');
        if (displayName) displayName.textContent = data.account.pharmacy_name;
        const initials = document.getElementById('settings-logo-initials');
        if (initials) {
          const parts = String(data.account.pharmacy_name).trim().split(/\s+/);
          initials.textContent = parts.slice(0, 2).map(function (part) { return part.charAt(0).toUpperCase(); }).join('') || 'P';
        }
      }

      if (data.account?.contact_number) {
        const contactLine = document.getElementById('settings-display-contact');
        if (contactLine) {
          const svg = contactLine.querySelector('svg');
          contactLine.textContent = '';
          if (svg) contactLine.appendChild(svg);
          contactLine.appendChild(document.createTextNode(data.account.contact_number));
        }
      }

      if (data.account?.logo_url) {
        const avatar = document.querySelector('.topbar-profile .avatar');
        if (avatar) {
          avatar.classList.add('has-logo');
          let img = avatar.querySelector('.avatar-logo');
          if (!img) {
            avatar.textContent = '';
            img = document.createElement('img');
            img.className = 'avatar-logo';
            img.alt = '';
            avatar.appendChild(img);
          }
          img.src = data.account.logo_url;
        }

        const logoPreview = document.getElementById('settings-logo-preview-img');
        if (logoPreview) {
          logoPreview.src = data.account.logo_url;
          logoPreview.hidden = false;
          document.getElementById('settings-logo-preview')?.classList.add('has-image');
        }

        window.updatePharmacyRegisterMapLogo?.(data.account.logo_url);
      }

      if (typeof window.snapshotSettingsForm === 'function') window.snapshotSettingsForm();
      if (typeof window.setSettingsEditing === 'function') window.setSettingsEditing(false);
    } catch (error) {
      showSettingsAlert(error.message || 'Could not save your pharmacy profile.', 'error');
    } finally {
      if (saveBtn) saveBtn.disabled = false;
      if (saveLabel) saveLabel.textContent = originalLabel;
    }
  });
})();

if (document.getElementById('pane-business')?.classList.contains('active')) {
  window.initSettingsBusinessMap?.();
}

(function setupPermitPreview() {
  const viewer = document.getElementById('permit-viewer');
  if (!viewer) return;

  const title = document.getElementById('permit-viewer-title');
  const image = document.getElementById('permit-viewer-image');
  const frame = document.getElementById('permit-viewer-frame');
  const missing = document.getElementById('permit-viewer-missing');

  function resetCrop(flags) {
    if (typeof window.resetRxCropPreview === 'function') {
      window.resetRxCropPreview(viewer, flags);
    }
  }

  function closePermitViewer() {
    viewer.hidden = true;
    document.body.classList.remove('modal-open');
    if (image) image.removeAttribute('src');
    if (frame) frame.removeAttribute('src');
  }

  function openPermitViewer(url, label, kind) {
    const src = String(url || '').trim();
    if (title) title.textContent = label || 'Document preview';
    if (typeof window.bindRxCropPreview === 'function') window.bindRxCropPreview(viewer);

    const showMissing = function (message) {
      if (image) image.hidden = true;
      if (frame) frame.hidden = true;
      if (missing) {
        missing.textContent = message || 'The uploaded document could not be loaded.';
        missing.hidden = false;
      }
      resetCrop({ empty: true });
    };

    if (!src) {
      showMissing('No document file is available.');
      viewer.hidden = false;
      document.body.classList.add('modal-open');
      return;
    }

    if (missing) missing.hidden = true;
    const entry = typeof window.preloadRxPreview === 'function' ? window.preloadRxPreview(src) : null;
    const ready = typeof window.rxPreviewReadyUrl === 'function' ? window.rxPreviewReadyUrl(src) : '';
    const looksPdf = kind === 'pdf' || (typeof window.rxPreviewLooksPdf === 'function'
      ? window.rxPreviewLooksPdf(src)
      : /\.pdf($|\?)/i.test(src));
    const displaySrc = ready || src;
    resetCrop({ pdf: looksPdf, empty: false });
    if (image) {
      image.onload = function () { if (missing) missing.hidden = true; };
      image.onerror = function () {
        if (ready || !frame) {
          showMissing();
          return;
        }
        image.hidden = true;
        frame.hidden = false;
        frame.src = displaySrc;
        resetCrop({ pdf: true, empty: false });
      };
      image.hidden = looksPdf;
      if (!looksPdf) image.src = displaySrc;
    }
    if (frame) {
      frame.hidden = !looksPdf;
      if (looksPdf) frame.src = displaySrc;
    }
    if (entry && entry.promise && !ready) {
      entry.promise.then(function (result) {
        if (!result || !result.objectUrl || viewer.hidden) return;
        const isPdf = /pdf/i.test(result.type || '');
        if (isPdf) {
          if (image) image.hidden = true;
          if (frame) {
            frame.hidden = false;
            frame.src = result.objectUrl;
          }
          resetCrop({ pdf: true, empty: false });
        } else if (image && !image.hidden) {
          image.src = result.objectUrl;
        }
      });
    }
    viewer.hidden = false;
    document.body.classList.add('modal-open');
  }

  document.querySelectorAll('.settings-doc-card[data-permit-url]').forEach((card) => {
    const url = card.getAttribute('data-permit-url') || '';
    if (url && typeof window.preloadRxPreview === 'function') window.preloadRxPreview(url);
    card.addEventListener('click', () => {
      openPermitViewer(url, card.getAttribute('data-permit-label') || '', card.getAttribute('data-permit-kind') || '');
    });
  });

  viewer.addEventListener('click', (event) => {
    if (event.target.closest('#permit-viewer-close, #permit-viewer-close-btn')) {
      closePermitViewer();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && viewer.hidden === false) {
      closePermitViewer();
    }
  });
})();
