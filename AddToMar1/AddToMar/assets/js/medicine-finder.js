(function () {
  'use strict';

  const cfg = window.FINDER_CONFIG || {};
  let map = null;
  let routingControl = null;
  let markersLayer = null;
  let userMarker = null;
  let userPos = null;
  let selectedMedicine = null;
  let pharmacyData = [];
  let searchTimer = null;

  const $ = (sel) => document.querySelector(sel);
  const searchInput = $('#finderSearch');
  const resultsEl = $('#searchResults');
  const pharmacyListEl = $('#pharmacyList');
  const routePanel = $('#routePanel');
  const altPanel = $('#altPanel');
  const leafletModal = document.getElementById('leafletModal');

  function makeIcon(color, size) {
    return L.divIcon({
      className: 'finder-map-marker',
      html: `<span style="background:${color};width:${size}px;height:${size}px;border:3px solid #fff;border-radius:50%;display:block;box-shadow:0 2px 8px rgba(0,0,0,.25);"></span>`,
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2]
    });
  }

  function initMap() {
    if (!window.L || !$('#finderMap')) return;

    const lat = cfg.defaultLat || 18.1978;
    const lng = cfg.defaultLng || 120.5937;
    const zoom = cfg.defaultZoom || 11;

    map = L.map('finderMap', { zoomControl: true }).setView([lat, lng], zoom);

    L.tileLayer(cfg.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: cfg.tileAttribution || '&copy; OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          userPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
          setUserMarker(userPos.lat, userPos.lng);
          map.setView([userPos.lat, userPos.lng], 13);
        },
        () => showToast('info', 'Location access denied. Using default map center.')
      );
    }
  }

  function setUserMarker(lat, lng) {
    if (userMarker) markersLayer.removeLayer(userMarker);
    userMarker = L.marker([lat, lng], { icon: makeIcon('#0ea5e9', 18), title: 'Your Location' })
      .bindPopup('<strong>Your Location</strong>')
      .addTo(markersLayer);
  }

  function clearRoute() {
    if (routingControl) {
      map.removeControl(routingControl);
      routingControl = null;
    }
  }

  function clearPharmacyMarkers() {
    markersLayer.clearLayers();
    if (userMarker) userMarker.addTo(markersLayer);
  }

  function addPharmacyMarkers(pharmacies) {
    clearPharmacyMarkers();
    const bounds = [];

    if (userPos) bounds.push([userPos.lat, userPos.lng]);

    pharmacies.forEach((p, idx) => {
      const lat = parseFloat(p.latitude);
      const lng = parseFloat(p.longitude);
      const isNearest = idx === 0 && pharmacies.length > 1;
      const marker = L.marker([lat, lng], {
        icon: makeIcon(isNearest ? '#10b981' : '#0369a1', isNearest ? 22 : 18),
        title: p.pharmacy_name
      });
      marker.bindPopup(`
        <div style="min-width:180px;">
          <strong>${esc(p.pharmacy_name)}</strong><br>
          <small>${esc(p.address)}</small><br>
          <small>Stock: ${p.stock_quantity} · ${p.is_open ? '<span style="color:#059669">Open</span>' : '<span style="color:#ef4444">Closed</span>'}</small><br>
          <small>${esc(p.contact_number)}</small>
        </div>`);
      marker.on('click', () => selectPharmacy(p.id));
      marker.addTo(markersLayer);
      bounds.push([lat, lng]);
    });

    if (bounds.length) {
      map.fitBounds(bounds, { padding: [40, 40] });
    }
  }

  async function drawRoute(destLat, destLng) {
    clearRoute();
    if (!userPos || !window.L.Routing) return null;

    return new Promise((resolve) => {
      routingControl = L.Routing.control({
        waypoints: [
          L.latLng(userPos.lat, userPos.lng),
          L.latLng(destLat, destLng)
        ],
        routeWhileDragging: false,
        show: false,
        addWaypoints: false,
        draggableWaypoints: false,
        fitSelectedRoutes: true,
        lineOptions: { styles: [{ color: '#059669', weight: 5, opacity: 0.85 }] },
        createMarker: () => null,
        router: L.Routing.osrmv1({
          serviceUrl: cfg.osrmUrl || 'https://router.project-osrm.org/route/v1'
        })
      }).addTo(map);

      routingControl.on('routesfound', (e) => {
        const route = e.routes[0];
        if (route) {
          resolve({
            distanceKm: (route.summary.totalDistance / 1000).toFixed(1),
            durationText: formatDuration(route.summary.totalTime)
          });
        } else {
          resolve(null);
        }
      });

      routingControl.on('routingerror', () => resolve(null));
    });
  }

  function formatDuration(seconds) {
    const mins = Math.round(seconds / 60);
    if (mins < 60) return mins + ' min';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    return h + ' hr' + (m ? ' ' + m + ' min' : '');
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function money(n) {
    return '₱' + parseFloat(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
  }

  async function doSearch(q) {
    if (q.length < 2) {
      resultsEl.innerHTML = '<div class="finder-empty small">Type at least 2 characters to search.</div>';
      return;
    }
    resultsEl.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    const res = await fetch(cfg.searchUrl + '?q=' + encodeURIComponent(q));
    const data = await res.json();
    if (!data.success || !data.medicines.length) {
      resultsEl.innerHTML = '<div class="finder-empty">No medicines found for "' + esc(q) + '".</div>';
      return;
    }
    resultsEl.innerHTML = data.medicines.map((m) => `
      <div class="glass-card medicine-result-card fade-in-finder" data-id="${m.id}">
        <div class="d-flex gap-3 align-items-center">
          <img src="${esc(m.image_url)}" onerror="this.src='${cfg.defaultImage}'" alt="">
          <div class="flex-grow-1">
            <div class="fw-bold">${esc(m.medicine_name)}</div>
            <div class="small text-muted">${esc(m.generic_name || '—')} · ${esc(m.dosage || '—')}</div>
            <div class="d-flex justify-content-between mt-1">
              <span class="fw-semibold text-primary">${money(m.min_price)}</span>
              <span class="small ${m.in_stock ? 'text-success' : 'text-danger'}">${m.in_stock ? m.total_stock + ' in network' : 'Out of stock'}</span>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary view-leaflet-btn" data-med='${JSON.stringify(m).replace(/'/g, '&#39;')}'>Leaflet</button>
        </div>
      </div>
    `).join('');

    resultsEl.querySelectorAll('.medicine-result-card').forEach((el) => {
      el.addEventListener('click', (e) => {
        if (e.target.closest('.view-leaflet-btn')) return;
        selectMedicine(parseInt(el.dataset.id, 10));
      });
    });
    resultsEl.querySelectorAll('.view-leaflet-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        showMedicineLeaflet(JSON.parse(btn.dataset.med));
      });
    });
  }

  async function selectMedicine(id) {
    selectedMedicine = id;
    resultsEl.querySelectorAll('.medicine-result-card').forEach((el) => {
      el.classList.toggle('active', parseInt(el.dataset.id, 10) === id);
    });
    await loadPharmacies(id);
  }

  async function loadPharmacies(medicineId, pharmacyId) {
    let url = cfg.pharmaciesUrl + '?medicine_id=' + medicineId;
    if (userPos) url += '&lat=' + userPos.lat + '&lng=' + userPos.lng;
    if (pharmacyId) url += '&pharmacy_id=' + pharmacyId;
    const res = await fetch(url);
    const data = await res.json();
    if (!data.success) return;

    pharmacyData = data.pharmacies || [];
    renderPharmacyList(data);
    if (map) addPharmacyMarkers(pharmacyData);

    if (data.out_of_stock_message && data.nearest_with_stock) {
      altPanel.classList.remove('d-none');
      altPanel.innerHTML = `
        <div class="alt-pharmacy-alert fade-in-finder">
          <strong><i class="bi bi-exclamation-triangle me-1"></i>${esc(data.out_of_stock_message)}</strong>
          <div class="mt-2 p-2 bg-white rounded">
            <span class="pulse-dot"></span><strong>Nearest with stock:</strong> ${esc(data.nearest_with_stock.pharmacy_name)}<br>
            <small>Available: ${data.nearest_with_stock.stock_quantity} units · ${data.nearest_with_stock.distance_km || '—'} km · ${esc(data.nearest_with_stock.contact_number)}</small>
            <div class="mt-2 d-flex gap-2 flex-wrap">
              <button class="btn btn-sm btn-gradient alt-route-btn" data-id="${data.nearest_with_stock.id}">Show Route</button>
              <a href="${data.nearest_with_stock.navigate_url}" target="_blank" class="btn btn-sm btn-outline-success">Navigate Now</a>
            </div>
          </div>
        </div>`;
      altPanel.querySelector('.alt-route-btn')?.addEventListener('click', () => selectPharmacy(data.nearest_with_stock.id));
    } else {
      altPanel.classList.add('d-none');
    }
  }

  function renderPharmacyList() {
    if (!pharmacyData.length) {
      pharmacyListEl.innerHTML = '<div class="finder-empty small">No pharmacies have this medicine in stock.</div>';
      routePanel.classList.add('d-none');
      clearRoute();
      return;
    }
    pharmacyListEl.innerHTML = pharmacyData.map((p, i) => `
      <div class="pharmacy-list-item fade-in-finder ${i === 0 ? 'nearest-highlight' : ''}" data-id="${p.id}">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            ${i === 0 ? '<span class="badge bg-success mb-1">Nearest</span> ' : ''}
            <div class="fw-bold">${esc(p.pharmacy_name)}</div>
            <div class="small text-muted">${esc(p.address)}</div>
            <div class="small mt-1">
              Stock: <strong>${p.stock_quantity}</strong> · ${money(p.pharmacy_price)} ·
              <span class="${p.is_open ? 'status-open' : 'status-closed'}">${p.is_open ? 'Open' : 'Closed'}</span>
            </div>
            ${p.distance_km != null ? `<div class="small text-primary">${p.distance_km} km · ~${esc(p.travel_time || '')}</div>` : ''}
          </div>
          <button class="btn btn-sm btn-link fav-btn ${p.is_favorite ? 'text-warning' : 'text-muted'}" data-id="${p.id}" title="Favorite">
            <i class="bi bi-star${p.is_favorite ? '-fill' : ''}"></i>
          </button>
        </div>
      </div>
    `).join('');

    pharmacyListEl.querySelectorAll('.pharmacy-list-item').forEach((el) => {
      el.addEventListener('click', (e) => {
        if (e.target.closest('.fav-btn')) return;
        selectPharmacy(parseInt(el.dataset.id, 10));
      });
    });
    pharmacyListEl.querySelectorAll('.fav-btn').forEach((btn) => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const res = await ajaxPost(cfg.favoriteUrl, { pharmacy_id: btn.dataset.id, csrf_token: cfg.csrfToken });
        if (res.success) {
          showToast('success', res.message);
          if (selectedMedicine) loadPharmacies(selectedMedicine);
        }
      });
    });
  }

  async function selectPharmacy(id) {
    const pharmacy = pharmacyData.find((p) => parseInt(p.id, 10) === id);
    if (!pharmacy) return;

    pharmacyListEl.querySelectorAll('.pharmacy-list-item').forEach((el) => {
      el.classList.toggle('selected', parseInt(el.dataset.id, 10) === id);
    });

    let distanceKm = pharmacy.distance_km ?? '—';
    let travelTime = pharmacy.travel_time || '—';

    routePanel.classList.remove('d-none');
    routePanel.innerHTML = `
      <div class="route-panel fade-in-finder">
        <h6 class="fw-bold mb-3"><i class="bi bi-signpost-split me-1"></i> Route to ${esc(pharmacy.pharmacy_name)}</h6>
        <div class="row g-2 mb-3">
          <div class="col-4 route-stat"><span class="val" id="routeDist">${distanceKm}</span><span class="lbl">km</span></div>
          <div class="col-4 route-stat"><span class="val" id="routeTime">${esc(travelTime)}</span><span class="lbl">est. time</span></div>
          <div class="col-4 route-stat"><span class="val">${pharmacy.stock_quantity}</span><span class="lbl">in stock</span></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="${pharmacy.navigate_url}" target="_blank" class="btn btn-light btn-sm flex-grow-1"><i class="bi bi-navigation me-1"></i> Navigate Now</a>
          <a href="tel:${esc(pharmacy.contact_number)}" class="btn btn-outline-light btn-sm"><i class="bi bi-telephone"></i></a>
        </div>
      </div>`;

    if (map) {
      setTimeout(() => map.invalidateSize(), 200);
      const routeInfo = await drawRoute(parseFloat(pharmacy.latitude), parseFloat(pharmacy.longitude));
      if (routeInfo) {
        const distEl = $('#routeDist');
        const timeEl = $('#routeTime');
        if (distEl) distEl.textContent = routeInfo.distanceKm;
        if (timeEl) timeEl.textContent = routeInfo.durationText;
      }
    }
  }

  function showMedicineLeaflet(m) {
    $('#leafletTitle').textContent = m.medicine_name;
    $('#leafletBody').innerHTML = `
      <div class="row g-3">
        <div class="col-md-4 text-center">
          <img src="${esc(m.image_url)}" class="rounded-xl w-100" style="max-height:200px;object-fit:cover;" onerror="this.src='${cfg.defaultImage}'">
        </div>
        <div class="col-md-8">
          <p class="text-muted mb-2">Generic: <strong>${esc(m.generic_name || '—')}</strong> · Dosage: <strong>${esc(m.dosage || '—')}</strong></p>
          ${section('Description', m.description)}
          ${section('Uses', m.uses_info)}
          ${section('Dosage Instructions', m.dosage_instructions)}
          ${section('Side Effects', m.side_effects)}
          ${section('Warnings', m.warnings)}
          ${section('Storage', m.storage_info)}
          ${section('Manufacturer', m.manufacturer)}
        </div>
      </div>`;
    bootstrap.Modal.getOrCreateInstance(leafletModal).show();
  }

  function section(title, text) {
    if (!text) return '';
    return `<div class="leaflet-section"><h6>${esc(title)}</h6><p class="small mb-0">${esc(text)}</p></div>`;
  }

  searchInput?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => doSearch(searchInput.value.trim()), 350);
  });

  document.addEventListener('DOMContentLoaded', () => {
    initMap();
    const preset = cfg.presetSearch || '';
    if (preset) {
      searchInput.value = preset;
      doSearch(preset);
    }
  });
})();
