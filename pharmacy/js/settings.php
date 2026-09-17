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

  function syncRow(row) {
    const checked = !!row.querySelector('input[name="operation_days[]"]')?.checked;
    row.classList.toggle('is-open', checked);
    row.querySelectorAll('input[type="time"]').forEach((input) => {
      input.disabled = !checked;
      input.required = checked;
      input.setCustomValidity('');
    });
  }

  rows.forEach((row) => {
    const checkbox = row.querySelector('input[name="operation_days[]"]');
    checkbox?.addEventListener('change', () => syncRow(row));
    syncRow(row);
  });
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

(function setupSettingsDocUploads() {
  const allowed = ['pdf', 'jpg', 'jpeg', 'png'];
  const pdfIcon = `<svg width="52" height="60" viewBox="0 0 52 60" fill="none">
    <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#E2574C"/>
    <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".35"/>
    <text x="26" y="42" text-anchor="middle" fill="#fff" font-size="13" font-weight="800" font-family="Manrope, sans-serif">PDF</text>
  </svg>`;

  function extOf(name) {
    return (name.split('.').pop() || '').toLowerCase();
  }

  function isAllowed(file) {
    return allowed.includes(extOf(file.name)) && file.size > 0;
  }

  function syncInput(widget) {
    const input = widget.querySelector('.settings-doc-input');
    if (!input) return;
    const data = new DataTransfer();
    (widget._files || []).forEach((file) => data.items.add(file));
    input.files = data.files;
  }

  function renderList(widget) {
    const grid = widget.querySelector('.settings-doc-grid');
    const uploadBox = widget.querySelector('.settings-doc-upload');
    if (!grid || !uploadBox) return;

    grid.querySelectorAll('.settings-doc-card.is-pending').forEach((card) => card.remove());

    const label = widget.dataset.docLabel || 'Document';

    (widget._files || []).forEach((file) => {
      const isPdf = extOf(file.name) === 'pdf';
      const card = document.createElement('div');
      card.className = 'settings-doc-card is-pending';
      card.innerHTML = `
        <button type="button" class="settings-doc-remove" aria-label="Remove file">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <span class="settings-doc-icon settings-doc-icon--${isPdf ? 'pdf' : 'img'}" aria-hidden="true"></span>
        <span class="settings-doc-meta">
          <strong></strong>
          <small></small>
        </span>
      `;

      const iconWrap = card.querySelector('.settings-doc-icon');
      if (isPdf) {
        iconWrap.innerHTML = pdfIcon;
      } else {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.alt = '';
        iconWrap.appendChild(img);
      }

      card.querySelector('.settings-doc-meta strong').textContent = label;
      card.querySelector('.settings-doc-meta small').textContent = 'Ready to upload';

      card.querySelector('.settings-doc-remove').addEventListener('click', () => {
        widget._files = (widget._files || []).filter((entry) => entry !== file);
        syncInput(widget);
        renderList(widget);
      });

      grid.insertBefore(card, uploadBox);
    });
  }

  function addFiles(widget, files) {
    widget._files = widget._files || [];
    files.forEach((file) => {
      if (!isAllowed(file)) return;
      if (widget._files.some((entry) => entry.name === file.name && entry.size === file.size)) return;
      widget._files.push(file);
    });
    syncInput(widget);
    renderList(widget);
  }

  document.querySelectorAll('#settings-business-form .settings-doc').forEach((widget) => {
    const input = widget.querySelector('.settings-doc-input');
    const uploadBox = widget.querySelector('.settings-doc-upload');
    if (!input || !uploadBox) return;

    widget._files = [];

    input.addEventListener('change', () => {
      addFiles(widget, Array.from(input.files || []));
      input.value = '';
      syncInput(widget);
    });

    uploadBox.addEventListener('dragover', (event) => {
      event.preventDefault();
      uploadBox.classList.add('is-dragover');
    });
    uploadBox.addEventListener('dragleave', () => uploadBox.classList.remove('is-dragover'));
    uploadBox.addEventListener('drop', (event) => {
      event.preventDefault();
      uploadBox.classList.remove('is-dragover');
      addFiles(widget, Array.from(event.dataTransfer?.files || []));
    });
  });
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
      const body = new FormData(form);
      form.querySelectorAll('.settings-doc').forEach((widget) => {
        const input = widget.querySelector('.settings-doc-input');
        if (!input) return;
        body.delete(input.name);
        (widget._files || []).forEach((file) => body.append(input.name, file));
      });

      const response = await fetch('api/save-profile.php', {
        method: 'POST',
        body,
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
