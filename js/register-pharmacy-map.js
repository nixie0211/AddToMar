(function () {
  'use strict';

  const cfg = window.PHARMACY_REGISTER_MAP_CONFIG || {};
  const ids = Object.assign({
    map: 'pharmacy-register-map',
    lat: 'pharmacy-register-latitude',
    lng: 'pharmacy-register-longitude',
    address: 'pharmacy-register-address',
    search: 'pharmacy-register-map-search',
    searchBtn: 'pharmacy-register-map-search-btn',
    searchResults: 'pharmacy-register-map-search-results',
    locateBtn: 'pharmacy-register-map-locate-btn',
    status: 'pharmacy-register-map-status',
  }, cfg.ids || {});

  const MARKER_SIZE = 52;
  let map = null;
  let userMarker = null;
  let mapClickBound = false;
  let toolbarBound = false;
  let searchTimer = null;
  let reverseRequestId = 0;
  let logoObjectUrl = null;

  function el(id) {
    return document.getElementById(id);
  }

  function setLogoUrl(url) {
    const next = (url || '').trim();
    logoObjectUrl = next !== '' ? next : null;
  }

  function makeIcon(color, size) {
    const totalHeight = size + 10;
    return L.divIcon({
      className: 'register-map-marker',
      html: `<span class="register-map-marker-dot" style="--marker-color:${color};width:${size}px;height:${size}px;"><i></i></span>`,
      iconSize: [size, totalHeight],
      iconAnchor: [size / 2, totalHeight],
    });
  }

  function makeLogoIcon(imageUrl, size) {
    const tipHeight = 12;
    const totalHeight = size + tipHeight;

    return L.divIcon({
      className: 'map-logo-marker',
      html: `
        <div class="map-logo-marker-stack" style="width:${size}px;">
          <div class="map-logo-marker-pin" style="width:${size}px;height:${size}px;">
            <img src="${imageUrl}" alt="Pharmacy logo">
          </div>
          <span class="map-logo-marker-tip" aria-hidden="true"></span>
        </div>
      `,
      iconSize: [size, totalHeight],
      iconAnchor: [size / 2, totalHeight],
    });
  }

  function getMarkerIcon(size) {
    if (logoObjectUrl) {
      return makeLogoIcon(logoObjectUrl, size);
    }

    return makeIcon('#10b981', 22);
  }

  function getPopupHtml() {
    if (logoObjectUrl) {
      return `<div class="map-popup-logo"><img src="${logoObjectUrl}" alt=""><strong>Pharmacy location</strong><br><small>Drag the pin or click the map to adjust.</small></div>`;
    }

    return '<strong>Pharmacy location</strong><br><small>Drag the pin or click the map to adjust.</small>';
  }

  function refreshMarkerAppearance() {
    if (!userMarker) {
      return;
    }

    userMarker.setIcon(getMarkerIcon(MARKER_SIZE));
    userMarker.setPopupContent(getPopupHtml());
  }

  window.updatePharmacyRegisterMapLogo = function updatePharmacyRegisterMapLogo(url) {
    setLogoUrl(url);
    refreshMarkerAppearance();
  };

  function syncLogoFromDom() {
    if (cfg.logoUrl) {
      setLogoUrl(cfg.logoUrl);
      return;
    }

    const selectors = cfg.logoPreviewSelectors || [
      '#settings-logo-preview-img:not([hidden])',
      '#pharmacy-logo-preview-img:not([hidden])',
    ];

    for (const selector of selectors) {
      const img = document.querySelector(selector);
      const src = img?.getAttribute('src') || '';
      if (!img || !src || src === window.location.href) {
        continue;
      }

      setLogoUrl(src);
      return;
    }
  }

  function esc(value) {
    const element = document.createElement('div');
    element.textContent = value || '';
    return element.innerHTML;
  }

  function boxBounds(box) {
    if (!box) return null;
    return L.latLngBounds([box.south, box.west], [box.north, box.east]);
  }

  function getViewBounds() {
    return boxBounds(cfg.bounds);
  }

  function getFitBounds() {
    if (cfg.fitBounds) return boxBounds(cfg.fitBounds);
    const ring = (cfg.serviceOverlay && cfg.serviceOverlay.polygon) || [];
    if (ring.length >= 3) {
      return L.latLngBounds(ring.map((point) => [Number(point.lat), Number(point.lng)]));
    }
    return getViewBounds();
  }

  function addMapTiles(target) {
    const streetUrl = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png';
    if (typeof window.addStreetTileLayer === 'function') {
      window.addStreetTileLayer(target, cfg);
      return;
    }
    L.tileLayer(streetUrl, {
      attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
      subdomains: 'abcd',
      maxZoom: cfg.maxZoom || 19,
      minZoom: cfg.minZoom || 10,
    }).addTo(target);
  }

  function isInServiceArea(lat, lng) {
    if (typeof window.pointInServiceOverlay === 'function') {
      return window.pointInServiceOverlay(lat, lng, cfg);
    }
    const bounds = getFitBounds();
    return bounds ? bounds.contains([lat, lng]) : true;
  }

  function setHiddenCoords(lat, lng) {
    const latInput = el(ids.lat);
    const lngInput = el(ids.lng);
    if (latInput) latInput.value = String(lat);
    if (lngInput) lngInput.value = String(lng);
  }

  function readSavedCoords() {
    const lat = parseFloat(el(ids.lat)?.value || cfg.initialLat || '');
    const lng = parseFloat(el(ids.lng)?.value || cfg.initialLng || '');

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
      return null;
    }

    return { lat, lng };
  }

  function showOutOfAreaPopup() {
    const message = 'Pick a location within Laoag City, San Nicolas, or Batac City.';
    setMapStatus('');
    if (typeof window.showMapAreaPopup === 'function') {
      window.showMapAreaPopup(message);
      return;
    }
    window.alert(message);
  }

  function setLocateLoading(isLoading) {
    const btn = el(ids.locateBtn);
    if (!btn) return;
    btn.disabled = isLoading;
    btn.classList.toggle('is-loading', isLoading);
  }

  function setMapStatus(message, type) {
    const statusEl = el(ids.status);
    if (!statusEl) return;

    if (type === 'warn' && String(message).indexOf('Laoag City, San Nicolas, or Batac City') !== -1) {
      showOutOfAreaPopup();
      return;
    }

    if (!message) {
      statusEl.hidden = true;
      statusEl.textContent = '';
      statusEl.className = 'register-map-status';
      return;
    }

    statusEl.hidden = false;
    statusEl.textContent = message;
    statusEl.className = `register-map-status${type ? ` register-map-status--${type}` : ''}`;
  }

  function hideSearchResults() {
    const list = el(ids.searchResults);
    if (list) {
      list.hidden = true;
      list.innerHTML = '';
    }
  }

  function fillAddress(value) {
    const addressInput = el(ids.address);
    if (!addressInput || !value) return;
    addressInput.value = value.trim();
  }

  async function reverseGeocodeAddress(lat, lng) {
    if (!cfg.reverseGeocodeUrl) return;

    const requestId = ++reverseRequestId;
    setMapStatus('Looking up address…', 'loading');

    try {
      const response = await fetch(
        `${cfg.reverseGeocodeUrl}?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`
      );
      const data = await response.json();

      if (requestId !== reverseRequestId) return;

      if (data.success && data.address) {
        fillAddress(data.address);
        setMapStatus('');
        return;
      }

      setMapStatus(data.message || 'Could not find an address for this pin. You may type it manually.', 'warn');
    } catch {
      if (requestId !== reverseRequestId) return;
      setMapStatus('Could not look up the address. You may type it manually.', 'warn');
    }
  }

  function showSearchResults(results) {
    const list = el(ids.searchResults);
    if (!list) return;

    if (!results.length) {
      list.innerHTML = '<li class="register-map-search-empty">No places found in Laoag, San Nicolas, or Batac.</li>';
      list.hidden = false;
      return;
    }

    list.innerHTML = results.map((result, index) => `
      <li>
        <button type="button" class="register-map-search-item" data-index="${index}">
          ${esc(result.label)}
        </button>
      </li>
    `).join('');

    list.hidden = false;

    list.querySelectorAll('.register-map-search-item').forEach((button) => {
      button.addEventListener('click', () => {
        const result = results[Number(button.dataset.index)];
        if (!result) return;
        applyLocation(result.lat, result.lng, {
          pan: true,
          address: result.label.split(',').slice(0, 5).join(', ').trim(),
          userAction: true,
        });
        hideSearchResults();
        const searchInput = el(ids.search);
        if (searchInput) searchInput.value = result.label.split(',')[0].trim();
      });
    });
  }

  async function searchPlaces(query) {
    if (!cfg.geocodeUrl || query.length < 3) {
      hideSearchResults();
      return;
    }

    const response = await fetch(`${cfg.geocodeUrl}?q=${encodeURIComponent(query)}`);
    const data = await response.json();
    showSearchResults(data.results || []);
  }

  async function geocodeAddress(address) {
    if (!cfg.geocodeUrl || !address) {
      return null;
    }

    const response = await fetch(`${cfg.geocodeUrl}?q=${encodeURIComponent(address)}`);
    const data = await response.json();
    return data.results?.[0] || null;
  }

  function setUserMarker(lat, lng) {
    if (!map) return;

    if (userMarker) {
      userMarker.setLatLng([lat, lng]);
      refreshMarkerAppearance();
      return;
    }

    userMarker = L.marker([lat, lng], {
      icon: getMarkerIcon(MARKER_SIZE),
      draggable: true,
      title: 'Pharmacy location',
      zIndexOffset: 1000,
    })
      .bindPopup(getPopupHtml())
      .addTo(map);

    userMarker.on('dragend', (event) => {
      const { lat: dragLat, lng: dragLng } = event.target.getLatLng();
      applyLocation(dragLat, dragLng, { pan: false, userAction: true });
    });
  }

  function applyLocation(lat, lng, options = {}) {
    if (!isInServiceArea(lat, lng)) {
      if (userMarker && map) {
        map.removeLayer(userMarker);
        userMarker = null;
      }
      setMapStatus('');
      showOutOfAreaPopup();
      return;
    }

    setHiddenCoords(lat, lng);

    if (options.userAction) {
      syncLogoFromDom();
    }

    setUserMarker(lat, lng);

    if (options.pan && map) {
      map.setView([lat, lng], Math.max(map.getZoom(), 15));
    }

    if (options.address) {
      fillAddress(options.address);
      setMapStatus('');
    } else if (!options.skipReverse) {
      reverseGeocodeAddress(lat, lng);
    } else {
      setMapStatus('');
    }

    refreshMarkerAppearance();
  }

  async function restoreSavedLocation() {
    syncLogoFromDom();

    const saved = readSavedCoords();
    if (saved && isInServiceArea(saved.lat, saved.lng)) {
      applyLocation(saved.lat, saved.lng, { pan: true, skipReverse: true });
      return;
    }

    const address = el(ids.address)?.value?.trim() || '';
    if (!address) {
      return;
    }

    const result = await geocodeAddress(address);
    if (!result) {
      return;
    }

    applyLocation(result.lat, result.lng, {
      pan: true,
      skipReverse: true,
      address: result.label.split(',').slice(0, 5).join(', ').trim(),
    });
  }

  function locateUser() {
    if (!navigator.geolocation) return;

    setLocateLoading(true);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setLocateLoading(false);
        applyLocation(position.coords.latitude, position.coords.longitude, { pan: true, userAction: true });
      },
      () => setLocateLoading(false),
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  }

  function bindMapInteractions() {
    if (!map || mapClickBound) return;
    mapClickBound = true;

    map.on('click', (event) => {
      applyLocation(event.latlng.lat, event.latlng.lng, { pan: false, userAction: true });
    });
  }

  function bindToolbar() {
    if (toolbarBound) return;
    toolbarBound = true;

    el(ids.locateBtn)?.addEventListener('click', locateUser);
    el(ids.searchBtn)?.addEventListener('click', () => {
      const query = el(ids.search)?.value.trim() || '';
      if (query) searchPlaces(query);
    });

    el(ids.search)?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const query = el(ids.search).value.trim();
        if (query) searchPlaces(query);
      }
      if (event.key === 'Escape') hideSearchResults();
    });

    el(ids.search)?.addEventListener('input', () => {
      clearTimeout(searchTimer);
      const query = el(ids.search).value.trim();
      if (query.length < 3) {
        hideSearchResults();
        return;
      }
      searchTimer = setTimeout(() => searchPlaces(query), 350);
    });
  }

  function invalidateMapSize() {
    if (!map) return;

    map.invalidateSize({ animate: false });

    requestAnimationFrame(() => {
      map.invalidateSize({ animate: false });
    });

    [120, 320, 600].forEach((delay) => {
      setTimeout(() => {
        if (map) {
          map.invalidateSize({ animate: false });
        }
      }, delay);
    });
  }

  window.resetPharmacyRegisterMap = function resetPharmacyRegisterMap() {
    reverseRequestId += 1;
    hideSearchResults();
    setMapStatus('');
    setLocateLoading(false);
    setLogoUrl('');
    if (userMarker && map) {
      map.removeLayer(userMarker);
      userMarker = null;
    }
    const latInput = el(ids.lat);
    const lngInput = el(ids.lng);
    if (latInput) latInput.value = '';
    if (lngInput) lngInput.value = '';
    const searchInput = el(ids.search);
    if (searchInput) searchInput.value = '';
    if (map) {
      const bounds = getFitBounds();
      if (bounds) {
        map.fitBounds(bounds, { padding: [20, 20] });
      }
    }
  };

  window.initPharmacyRegisterMap = function initPharmacyRegisterMap() {
    const mapEl = el(ids.map);
    if (!window.L || !mapEl) return;

    bindToolbar();
    const viewBounds = getViewBounds();
    const fitBounds = getFitBounds();

    if (!map) {
      map = L.map(ids.map, {
        zoomControl: true,
        minZoom: cfg.minZoom || 10,
        maxZoom: cfg.maxZoom || 19,
        maxBounds: viewBounds || undefined,
        maxBoundsViscosity: 1.0,
      });

      addMapTiles(map);

      if (typeof window.addServiceAreaOverlay === 'function') {
        window.addServiceAreaOverlay(map, cfg);
      }

      bindMapInteractions();

      if (fitBounds) {
        map.fitBounds(fitBounds, { padding: [20, 20] });
      } else {
        map.setView([cfg.defaultLat || 18.1978, cfg.defaultLng || 120.5937], cfg.defaultZoom || 12);
      }

      invalidateMapSize();
      restoreSavedLocation();
      return;
    }

    invalidateMapSize();

    if (!userMarker) {
      restoreSavedLocation();
    } else {
      syncLogoFromDom();
      refreshMarkerAppearance();
    }
  };
})();
