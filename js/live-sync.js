(function () {
  const cfg = window.LIVE_SYNC || {};
  if (!cfg.url) return;

  const minDelay = Math.max(2500, Number(cfg.interval) || 4000);
  const maxDelay = 30000;
  let versions = Object.assign({}, cfg.versions || {});
  let delay = minDelay;
  let timer = null;
  let inFlight = false;
  let htmlInFlight = false;
  let pendingKeys = [];
  let queuedRefresh = false;

  function busySelectors() {
    return [
      '.pay-modal:not([hidden])',
      '.address-map-modal:not([hidden])',
      '.residence-report-modal:not([hidden])',
      '.order-rx-viewer:not([hidden])',
      '#pharmacy-report-modal:not([hidden])',
      '.admin-modal:not([hidden])',
      '.rx-popup',
      '#prescription-viewer:not([hidden])',
      '#pickup-proof-modal:not([hidden])',
      '#cancel-order-modal:not([hidden])',
      '.resident-report-floating-panel:not([hidden])'
    ].join(',');
  }

  function isUiBusy() {
    if (document.body.classList.contains('modal-open') && document.querySelector(busySelectors())) {
      return true;
    }
    return Boolean(document.querySelector(busySelectors()));
  }

  function regionLocked(el) {
    if (!el) return true;
    if (el.getAttribute('data-live-skip') === '1') return true;
    if (el.getAttribute('data-live-skip-active') === '1' && (el.classList.contains('active') || el.classList.contains('is-active'))) {
      return true;
    }
    const mode = el.getAttribute('data-live-mode') || 'html';
    if (mode === 'js') return true;
    if (el.querySelector('.notif-dropdown.open, #admin-notify-panel:not([hidden]), .topbar-notification-dropdown:not([hidden])')) {
      return true;
    }
    const active = document.activeElement;
    if (active && el.contains(active)) {
      const tag = active.tagName;
      const type = (active.type || '').toLowerCase();
      if ((tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') && !['hidden', 'checkbox', 'radio', 'button', 'submit'].includes(type)) {
        if (String(active.value || '') !== '') return true;
        if (document.activeElement === active) return true;
      }
      if (active.isContentEditable) return true;
    }
    return false;
  }

  function regionMatches(el, keys) {
    const raw = (el.getAttribute('data-live-keys') || el.getAttribute('data-live-region') || '').trim();
    if (!raw || raw === '*' || raw === 'all') return true;
    const names = raw.split(/[\s,]+/).filter(Boolean);
    return names.some((name) => keys.indexOf(name) !== -1);
  }

  function snapshotPreserve(root) {
    const snaps = [];
    root.querySelectorAll('[data-live-preserve]').forEach((el) => {
      snaps.push({
        key: el.getAttribute('data-live-preserve'),
        value: el.value,
        scroll: el.scrollTop
      });
    });
    return snaps;
  }

  function restorePreserve(root, snaps) {
    snaps.forEach((snap) => {
      const el = root.querySelector('[data-live-preserve="' + snap.key + '"]');
      if (!el) return;
      if (typeof snap.value === 'string') el.value = snap.value;
      if (typeof snap.scroll === 'number') el.scrollTop = snap.scroll;
    });
  }

  function destroyCharts(root) {
    if (typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') return;
    root.querySelectorAll('canvas').forEach((canvas) => {
      const chart = Chart.getChart(canvas);
      if (chart) chart.destroy();
    });
  }

  function copyUiState(fromEl, toEl) {
    ['active', 'is-active', 'open'].forEach(function (name) {
      if (fromEl.classList.contains(name)) toEl.classList.add(name);
      else toEl.classList.remove(name);
    });
    if (fromEl.hidden) toEl.hidden = true;
    if (fromEl.hasAttribute('hidden')) toEl.setAttribute('hidden', '');
    else toEl.removeAttribute('hidden');
  }

  function mergeScriptJson(doc, id, targetKey) {
    const node = doc.getElementById(id);
    if (!node) return;
    try {
      window[targetKey] = JSON.parse(node.textContent || '{}');
    } catch (err) {
      /* keep previous payload */
    }
  }

  function mergeResidenceConfig(doc) {
    const node = doc.getElementById('residence-live-payload');
    if (!node || !window.RESIDENCE_CONFIG) return;
    try {
      const payload = JSON.parse(node.textContent || '{}');
      const previousOrders = JSON.stringify(window.RESIDENCE_CONFIG.orders || []);
      Object.keys(payload).forEach((key) => {
        window.RESIDENCE_CONFIG[key] = payload[key];
      });
      if (payload.pharmacies) window.nearbyPharmacies = payload.pharmacies;
      if (payload.orders && previousOrders !== JSON.stringify(payload.orders || [])) {
        if (typeof window.renderResidenceOrders === 'function') window.renderResidenceOrders();
        const detail = document.getElementById('orders-detail-view');
        const current = detail && detail.dataset ? detail.dataset.orderId : '';
        if (current && typeof window.renderOrderDetail === 'function') window.renderOrderDetail(current);
        if (typeof window.renderTrackingPage === 'function' && document.querySelector('.page[data-page="tracking"].active')) {
          window.renderTrackingPage();
        }
      }
    } catch (err) {
      /* keep previous config */
    }
  }

  function schedule(nextDelay) {
    clearTimeout(timer);
    const hiddenBoost = document.hidden ? Math.max(delay, 12000) : delay;
    timer = window.setTimeout(tick, nextDelay != null ? nextDelay : hiddenBoost);
  }

  function diffVersions(prev, next) {
    const keys = [];
    Object.keys(next || {}).forEach((key) => {
      if (String(prev[key] || '') !== String(next[key] || '')) keys.push(key);
    });
    return keys;
  }

  async function refreshHtml(keys) {
    if (!keys.length || htmlInFlight) {
      if (keys.length) queuedRefresh = true;
      return;
    }
    if (isUiBusy()) {
      pendingKeys = Array.from(new Set(pendingKeys.concat(keys)));
      return;
    }

    const regions = Array.from(document.querySelectorAll('[data-live-region]')).filter((el) => regionMatches(el, keys));
    const unlocked = regions.filter((el) => !regionLocked(el));
    if (!unlocked.length) return;

    htmlInFlight = true;
    try {
      const response = await fetch(window.location.href, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'X-Live-Sync': '1',
          Accept: 'text/html'
        }
      });
      if (!response.ok) throw new Error('html');
      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      mergeResidenceConfig(doc);
      mergeScriptJson(doc, 'pharmacy-chart-data', 'PHARMACY_CHART_DATA');

      let swapped = false;
      unlocked.forEach((current) => {
        const region = current.getAttribute('data-live-region') || '';
        const incoming = doc.querySelector('[data-live-region="' + region.replace(/"/g, '') + '"]');
        if (!incoming) return;
        const preserve = snapshotPreserve(current);
        destroyCharts(current);
        copyUiState(current, incoming);
        current.replaceWith(incoming);
        restorePreserve(incoming, preserve);
        swapped = true;
      });

      if (swapped) {
        document.dispatchEvent(new CustomEvent('livesync:applied', { detail: { keys } }));
      }
    } catch (err) {
      pendingKeys = Array.from(new Set(pendingKeys.concat(keys)));
    } finally {
      htmlInFlight = false;
      if (queuedRefresh) {
        queuedRefresh = false;
        window.setTimeout(() => refreshHtml(keys), 50);
      }
    }
  }

  async function tick() {
    if (inFlight) {
      schedule();
      return;
    }
    inFlight = true;
    try {
      const url = cfg.url + (cfg.url.indexOf('?') >= 0 ? '&' : '?') + 'portal=' + encodeURIComponent(cfg.portal || 'public');
      const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' }
      });
      if (response.status === 401) {
        delay = maxDelay;
        return;
      }
      if (!response.ok) throw new Error('sync');
      const data = await response.json();
      if (!data || data.ok === false) throw new Error('sync');
      delay = minDelay;
      const changed = diffVersions(versions, data.versions || {});
      versions = Object.assign({}, data.versions || versions);
      const allKeys = Array.from(new Set(changed.concat(pendingKeys)));
      pendingKeys = [];
      if (allKeys.length) {
        document.dispatchEvent(new CustomEvent('livesync:change', { detail: { keys: allKeys, versions } }));
        refreshHtml(allKeys);
      }
    } catch (err) {
      delay = Math.min(maxDelay, Math.round(delay * 1.8));
    } finally {
      inFlight = false;
      schedule();
    }
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
      delay = minDelay;
      schedule(400);
    }
  });

  document.addEventListener('click', function () {
    if (pendingKeys.length && !isUiBusy()) {
      const keys = pendingKeys.slice();
      pendingKeys = [];
      refreshHtml(keys);
    }
  });

  window.AddToMarLiveSync = {
    refresh: function () {
      delay = minDelay;
      schedule(200);
    }
  };

  schedule(1200);
})();
