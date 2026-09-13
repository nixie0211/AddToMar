(function (global) {
  'use strict';

  function overlayConfig(cfg) {
    return (cfg && cfg.serviceOverlay) || {};
  }

  function polygonRing(cfg) {
    const overlay = overlayConfig(cfg);
    const polygon = overlay.polygon || (cfg && cfg.servicePolygon) || [];
    return polygon.map((point) => [Number(point.lat), Number(point.lng)]).filter((pair) => (
      Number.isFinite(pair[0]) && Number.isFinite(pair[1])
    ));
  }

  function pointInPolygon(lat, lng, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
      const yi = ring[i][0];
      const xi = ring[i][1];
      const yj = ring[j][0];
      const xj = ring[j][1];
      const intersect = ((yi > lat) !== (yj > lat))
        && (lng < ((xj - xi) * (lat - yi)) / ((yj - yi) || 1e-12) + xi);
      if (intersect) inside = !inside;
    }
    return inside;
  }

  function centroid(ring) {
    let lat = 0;
    let lng = 0;
    ring.forEach((point) => {
      lat += point[0];
      lng += point[1];
    });
    return [lat / ring.length, lng / ring.length];
  }

  function endpointIcon(kind, label) {
    return L.divIcon({
      className: 'service-area-endpoint',
      html: `<div class="service-area-endpoint-inner service-area-endpoint--${kind}"><span class="service-area-endpoint-label">${label}</span><span class="service-area-endpoint-dot"></span></div>`,
      iconSize: [0, 0],
      iconAnchor: [0, 0],
    });
  }

  global.pointInServiceOverlay = function pointInServiceOverlay(lat, lng, cfg) {
    const ring = polygonRing(cfg || {});
    if (ring.length >= 3) {
      return pointInPolygon(lat, lng, ring);
    }
    const bounds = cfg && cfg.bounds;
    if (!bounds) return true;
    return lat >= bounds.south && lat <= bounds.north && lng >= bounds.west && lng <= bounds.east;
  };

  global.addServiceAreaOverlay = function addServiceAreaOverlay(map, cfg) {
    if (!map || !global.L || map._serviceAreaOverlay) return;

    const overlay = overlayConfig(cfg);
    const ring = polygonRing(cfg);
    if (ring.length < 3) return;

    const layer = L.layerGroup().addTo(map);
    map._serviceAreaOverlay = layer;

    L.polygon(ring, {
      color: '#f5c400',
      weight: 3,
      opacity: 0.95,
      fillColor: '#f5c400',
      fillOpacity: 0.12,
      interactive: false,
    }).addTo(layer);

    if (overlay.start) {
      L.marker([overlay.start.lat, overlay.start.lng], {
        icon: endpointIcon('start', overlay.start.label || 'Start (Coastline)'),
        interactive: false,
        keyboard: false,
        zIndexOffset: 400,
      }).addTo(layer);
    }

    if (overlay.end) {
      L.marker([overlay.end.lat, overlay.end.lng], {
        icon: endpointIcon('end', overlay.end.label || 'End (Road Point)'),
        interactive: false,
        keyboard: false,
        zIndexOffset: 400,
      }).addTo(layer);
    }

    const title = overlay.title || 'Only available in this area';
    const subtitle = overlay.subtitle || '(From Start to End)';
    L.marker(centroid(ring), {
      icon: L.divIcon({
        className: 'service-area-badge',
        html: `<div class="service-area-badge-card"><strong><span class="service-area-badge-check">✓</span> ${title}</strong><span>${subtitle}</span></div>`,
        iconSize: [0, 0],
        iconAnchor: [0, 0],
      }),
      interactive: false,
      keyboard: false,
      zIndexOffset: 500,
    }).addTo(layer);
  };
})(window);
