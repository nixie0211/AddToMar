(function () {
  'use strict';

  const REGISTER_IDS = {
    map: 'register-map',
    lat: 'register-latitude',
    lng: 'register-longitude',
    address: 'register-address',
    locateBtn: 'register-map-locate-btn',
    search: 'register-map-search',
    searchBtn: 'register-map-search-btn',
    results: 'register-map-search-results',
    status: 'register-map-status',
  };

  const GOOGLE_IDS = {
    map: 'google-location-map',
    lat: 'google-location-latitude',
    lng: 'google-location-longitude',
    address: 'google-location-address',
    locateBtn: 'google-location-map-locate-btn',
    search: 'google-location-map-search',
    searchBtn: 'google-location-map-search-btn',
    results: 'google-location-map-search-results',
    status: 'google-location-map-status',
  };

  function createLocationMap(ids) {
    const cfg = window.REGISTER_MAP_CONFIG || {};
    let map = null;
    let userMarker = null;
    let userPos = null;
    let serviceBounds = null;
    let mapClickBound = false;
    let toolbarBound = false;
    let searchTimer = null;
    let reverseRequestId = 0;

    function el(id) {
      return document.getElementById(id);
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

    function esc(value) {
      const element = document.createElement('div');
      element.textContent = value || '';
      return element.innerHTML;
    }

    function getServiceBounds() {
      if (serviceBounds) return serviceBounds;
      if (!cfg.bounds) return null;

      serviceBounds = L.latLngBounds(
        [cfg.bounds.south, cfg.bounds.west],
        [cfg.bounds.north, cfg.bounds.east]
      );

      return serviceBounds;
    }

    function isInServiceArea(lat, lng) {
      if (typeof window.pointInServiceOverlay === 'function') {
        return window.pointInServiceOverlay(lat, lng, cfg);
      }
      const bounds = getServiceBounds();
      return bounds ? bounds.contains([lat, lng]) : true;
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

    function setHiddenCoords(lat, lng) {
      const latInput = el(ids.lat);
      const lngInput = el(ids.lng);
      if (latInput) latInput.value = String(lat);
      if (lngInput) lngInput.value = String(lng);
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
      const list = el(ids.results);
      if (list) {
        list.hidden = true;
        list.innerHTML = '';
      }
    }

    function formatSearchAddress(label) {
      return label.split(',').slice(0, 5).join(', ').trim();
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
      const list = el(ids.results);
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
          applyUserLocation(result.lat, result.lng, {
            pan: true,
            address: formatSearchAddress(result.label),
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

    function setUserMarker(lat, lng) {
      if (!map) return;

      if (userMarker) {
        userMarker.setLatLng([lat, lng]);
        return;
      }

      userMarker = L.marker([lat, lng], {
        icon: makeIcon('#0ea5e9', 22),
        draggable: true,
        title: 'Your location',
        zIndexOffset: 1000,
      })
        .bindPopup('<strong>Your location</strong><br><small>Drag the pin or click the map to adjust.</small>')
        .addTo(map);

      userMarker.on('dragend', (event) => {
        const { lat: dragLat, lng: dragLng } = event.target.getLatLng();
        applyUserLocation(dragLat, dragLng, { pan: false });
      });
    }

    function applyUserLocation(lat, lng, options = {}) {
      const inServiceArea = isInServiceArea(lat, lng);

      if (!inServiceArea) {
        if (userMarker && map) {
          map.removeLayer(userMarker);
          userMarker = null;
        }
        setMapStatus('');
        showOutOfAreaPopup();
        return;
      }

      userPos = { lat, lng, inServiceArea };
      setHiddenCoords(lat, lng);

      setUserMarker(lat, lng);

      if (options.pan && map) {
        map.setView([lat, lng], Math.max(map.getZoom(), 14));
      }

      if (options.address) {
        fillAddress(options.address);
        setMapStatus('');
      } else {
        reverseGeocodeAddress(lat, lng);
      }
    }

    function locateUser(options = {}) {
      const fallbackLat = cfg.defaultLat || 18.1978;
      const fallbackLng = cfg.defaultLng || 120.5937;

      if (!navigator.geolocation) {
        if (!options.silent) {
          applyUserLocation(fallbackLat, fallbackLng, { pan: true });
        }
        return;
      }

      setLocateLoading(true);

      navigator.geolocation.getCurrentPosition(
        (position) => {
          setLocateLoading(false);
          applyUserLocation(position.coords.latitude, position.coords.longitude, { pan: true });
        },
        () => {
          setLocateLoading(false);
          if (!options.silent) {
            applyUserLocation(fallbackLat, fallbackLng, { pan: true });
          }
        },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
      );
    }

    function bindMapInteractions() {
      if (!map || mapClickBound) return;
      mapClickBound = true;

      map.on('click', (event) => {
        applyUserLocation(event.latlng.lat, event.latlng.lng, { pan: false });
      });
    }

    function bindToolbar() {
      if (toolbarBound) return;
      toolbarBound = true;

      const locateBtn = el(ids.locateBtn);
      const searchInput = el(ids.search);
      const searchBtn = el(ids.searchBtn);

      locateBtn?.addEventListener('click', () => locateUser({ silent: false }));

      searchBtn?.addEventListener('click', () => {
        const query = searchInput?.value.trim() || '';
        if (query) searchPlaces(query);
      });

      searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          const query = searchInput.value.trim();
          if (query) searchPlaces(query);
        }
        if (event.key === 'Escape') hideSearchResults();
      });

      searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = searchInput.value.trim();
        if (query.length < 3) {
          hideSearchResults();
          return;
        }
        searchTimer = setTimeout(() => searchPlaces(query), 350);
      });

      document.addEventListener('click', (event) => {
        const wrap = searchInput?.closest('.register-map-search-wrap');
        if (!wrap || !wrap.contains(event.target)) {
          hideSearchResults();
        }
      });
    }

    function init() {
      const mapEl = el(ids.map);
      if (!window.L || !mapEl) return;

      bindToolbar();

      const bounds = getServiceBounds();

      if (!map) {
        map = L.map(ids.map, {
          zoomControl: true,
          minZoom: cfg.minZoom || 11,
          maxZoom: cfg.maxZoom || 18,
          maxBounds: bounds || undefined,
          maxBoundsViscosity: 1.0,
        });

        if (bounds) {
          map.fitBounds(bounds, { padding: [20, 20] });
        } else {
          map.setView([cfg.defaultLat || 18.1978, cfg.defaultLng || 120.5937], cfg.defaultZoom || 12);
        }

        L.tileLayer(cfg.tileUrl || 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
          attribution: cfg.tileAttribution || '&copy; OpenStreetMap contributors',
          maxZoom: cfg.maxZoom || 18,
          minZoom: cfg.minZoom || 11,
        }).addTo(map);

        if (typeof window.addServiceAreaOverlay === 'function') {
          window.addServiceAreaOverlay(map, cfg);
        }

        bindMapInteractions();

        const savedLat = parseFloat(el(ids.lat)?.value || '');
        const savedLng = parseFloat(el(ids.lng)?.value || '');
        if (Number.isFinite(savedLat) && Number.isFinite(savedLng) && isInServiceArea(savedLat, savedLng)) {
          applyUserLocation(savedLat, savedLng, { pan: true });
        }
      }

      setTimeout(() => map.invalidateSize(), 220);
    }

    return { init };
  }

  const registerPicker = createLocationMap(REGISTER_IDS);
  const googlePicker = createLocationMap(GOOGLE_IDS);

  window.initRegisterMap = function initRegisterMap() {
    registerPicker.init();
  };

  window.initGoogleLocationMap = function initGoogleLocationMap() {
    googlePicker.init();
  };
})();
