<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

// Order status tabs use real links so the page reloads with the correct filter.

let pickupProofOrderId = 0;
let pickupProofHasExisting = false;
let cancelOrderId = 0;

function isAnyOrderModalOpen() {
  return document.getElementById('prescription-viewer')?.hidden === false
    || document.getElementById('pickup-proof-modal')?.hidden === false
    || document.getElementById('cancel-order-modal')?.hidden === false;
}

function syncModalOpenState() {
  if (isAnyOrderModalOpen()) {
    document.body.classList.add('modal-open');
  } else {
    document.body.classList.remove('modal-open');
  }
}

function bindRxCropZoom(root) {
  if (typeof window.bindRxCropPreview === 'function') window.bindRxCropPreview(root);
}

function resetRxCropZoom(root, flags) {
  if (typeof window.resetRxCropPreview === 'function') {
    window.resetRxCropPreview(root, flags);
    return;
  }
  const stage = root?.querySelector('.rx-crop-stage');
  const media = root?.querySelector('.rx-crop-media');
  const slider = root?.querySelector('.rx-crop-zoom');
  if (stage) {
    stage.classList.toggle('is-pdf', !!flags?.pdf);
    stage.classList.toggle('is-empty', !!flags?.empty);
  }
  if (slider) slider.value = '1';
  if (media) media.style.transform = 'translate(0px, 0px) scale(1)';
}

function openPrescriptionViewer(url) {
  const viewer = document.getElementById('prescription-viewer');
  const image = document.getElementById('prescription-viewer-image');
  const frame = document.getElementById('prescription-viewer-frame');
  const missing = document.getElementById('prescription-viewer-missing');
  if (!viewer) return;
  bindRxCropZoom(viewer);

  const src = String(url || '').trim();
  const showMissing = function (message) {
    if (image) {
      image.hidden = true;
    }
    if (frame) {
      frame.hidden = true;
    }
    if (missing) {
      missing.textContent = message || 'The uploaded prescription could not be loaded.';
      missing.hidden = false;
    }
    resetRxCropZoom(viewer, { empty: true });
  };

  if (!src) {
    showMissing('No prescription file was saved for this order.');
    viewer.hidden = false;
    syncModalOpenState();
    return;
  }

  if (missing) missing.hidden = true;
  const entry = typeof window.preloadRxPreview === 'function' ? window.preloadRxPreview(src) : null;
  const ready = typeof window.rxPreviewReadyUrl === 'function' ? window.rxPreviewReadyUrl(src) : '';
  const looksPdf = typeof window.rxPreviewLooksPdf === 'function'
    ? window.rxPreviewLooksPdf(src)
    : /\.pdf($|\?)/i.test(src);
  const displaySrc = ready || src;
  resetRxCropZoom(viewer, { pdf: looksPdf, empty: false });
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
      resetRxCropZoom(viewer, { pdf: true, empty: false });
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
      if (!result || !result.objectUrl) return;
      const isPdf = /pdf/i.test(result.type || '');
      if (isPdf) {
        if (image) image.hidden = true;
        if (frame) {
          frame.hidden = false;
          frame.src = result.objectUrl;
        }
        resetRxCropZoom(viewer, { pdf: true, empty: false });
      } else if (image && !image.hidden) {
        image.src = result.objectUrl;
      }
    });
  }
  viewer.hidden = false;
  syncModalOpenState();
}

function closePrescriptionViewer() {
  const viewer = document.getElementById('prescription-viewer');
  if (!viewer) return;
  viewer.hidden = true;
  syncModalOpenState();
}

function setPickupProofError(message) {
  const error = document.getElementById('pickup-proof-error');
  if (!error) return;

  if (!message) {
    error.hidden = true;
    error.textContent = '';
    return;
  }

  error.hidden = false;
  error.textContent = message;
}

function updatePickupProofSubmitState() {
  const submitBtn = document.getElementById('pickup-proof-submit-btn');
  const input = document.getElementById('pickup-proof-input');
  if (!submitBtn) return;

  const hasFile = !!(input?.files && input.files[0]);
  submitBtn.disabled = !(hasFile || pickupProofHasExisting);
}

function resetPickupProofModal() {
  const input = document.getElementById('pickup-proof-input');
  const preview = document.getElementById('pickup-proof-preview');
  const previewImage = document.getElementById('pickup-proof-preview-image');
  const previewName = document.getElementById('pickup-proof-preview-name');
  const submitBtn = document.getElementById('pickup-proof-submit-btn');

  pickupProofOrderId = 0;
  pickupProofHasExisting = false;
  if (input) {
    input.value = '';
  }
  if (preview) {
    preview.hidden = true;
  }
  if (previewImage) {
    previewImage.removeAttribute('src');
    previewImage.hidden = false;
  }
  if (previewName) {
    previewName.textContent = '';
  }
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Mark as complete';
  }
  setPickupProofError('');
}

function openPickupProofModal(orderId, orderNumber, customer, hasProof) {
  const modal = document.getElementById('pickup-proof-modal');
  const title = document.getElementById('pickup-proof-modal-sub');
  const input = document.getElementById('pickup-proof-input');
  if (!modal || !orderId) return;

  resetPickupProofModal();
  pickupProofOrderId = orderId;
  pickupProofHasExisting = hasProof === '1' || hasProof === true;

  if (title) {
    title.textContent = (customer || 'Customer') + ' · Order #' + (orderNumber || orderId);
  }

  if (pickupProofHasExisting) {
    updatePickupProofSubmitState();
  }

  if (input) {
    input.value = '';
  }

  modal.hidden = false;
  syncModalOpenState();
}

function closePickupProofModal() {
  const modal = document.getElementById('pickup-proof-modal');
  if (!modal) return;

  modal.hidden = true;
  resetPickupProofModal();
  syncModalOpenState();
}

function setCancelOrderError(message) {
  const error = document.getElementById('cancel-order-error');
  if (!error) return;

  if (!message) {
    error.hidden = true;
    error.textContent = '';
    return;
  }

  error.hidden = false;
  error.textContent = message;
}

const CANCEL_REASON_OTHER = 'other';

function toggleCancelOtherField() {
  const select = document.getElementById('cancel-reason-select');
  const otherField = document.getElementById('cancel-reason-other-field');
  const otherInput = document.getElementById('cancel-reason-other-input');
  const isOther = (select?.value || '') === CANCEL_REASON_OTHER;

  if (otherField) {
    otherField.hidden = !isOther;
  }
  if (!isOther && otherInput) {
    otherInput.value = '';
  }
  if (isOther) {
    otherInput?.focus();
  }
}

function getCancelReason() {
  const select = document.getElementById('cancel-reason-select');
  const otherInput = document.getElementById('cancel-reason-other-input');
  const value = select?.value || '';

  if (!value) {
    return '';
  }
  if (value === CANCEL_REASON_OTHER) {
    return (otherInput?.value || '').trim();
  }

  return value;
}

function resetCancelOrderModal() {
  const select = document.getElementById('cancel-reason-select');
  const otherInput = document.getElementById('cancel-reason-other-input');
  const submitBtn = document.getElementById('cancel-order-submit-btn');

  cancelOrderId = 0;
  if (select) {
    select.value = '';
  }
  if (otherInput) {
    otherInput.value = '';
  }
  toggleCancelOtherField();
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Cancel order';
  }
  setCancelOrderError('');
}

function openCancelOrderModal(orderId, orderNumber, customer) {
  const modal = document.getElementById('cancel-order-modal');
  const sub = document.getElementById('cancel-order-modal-sub');
  if (!modal || !orderId) return;

  resetCancelOrderModal();
  cancelOrderId = orderId;

  if (sub) {
    sub.textContent = (customer || 'Customer') + ' · Order #' + (orderNumber || orderId);
  }

  modal.hidden = false;
  syncModalOpenState();
  document.getElementById('cancel-reason-select')?.focus();
}

function closeCancelOrderModal() {
  const modal = document.getElementById('cancel-order-modal');
  if (!modal) return;

  modal.hidden = true;
  resetCancelOrderModal();
  syncModalOpenState();
}

let drawerRequestId = 0;
let drawerAbort = null;
let listRequestId = 0;
let listAbort = null;
let ordersNavGen = 0;

function ordersViewUrl(href) {
  try {
    const url = new URL(href, window.location.href);
    return url.pathname + url.search;
  } catch (error) {
    return href || '';
  }
}

function parseOrdersHref(href) {
  try {
    const url = new URL(href, window.location.href);
    return {
      nextUrl: url.pathname + url.search,
      status: url.searchParams.get('status') || 'pending',
      orderId: parseInt(url.searchParams.get('order_id') || '0', 10),
    };
  } catch (error) {
    return { nextUrl: href || '', status: 'pending', orderId: 0 };
  }
}

function currentOrdersStatus() {
  return new URLSearchParams(window.location.search).get('status') || 'pending';
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, function (ch) {
    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
  });
}

const ORDER_STATUS_MAP = {
  pending: { t: 'Pending' },
  confirmed: { t: 'Confirmed' },
  preparing: { t: 'Preparing' },
  ready: { t: 'Ready for Pickup' },
  delivered: { t: 'Completed' },
  cancelled: { t: 'Cancelled' },
};

let pharmacyOrdersState = {
  status: 'pending',
  counts: { pending: 0, confirmed: 0, preparing: 0, ready: 0, delivered: 0, cancelled: 0 },
  total: 0,
  orders: [],
  selectedId: 0,
  details: {},
  tabs: {},
};

function ordersHref(status, orderId) {
  let href = 'index.php?view=orders&status=' + encodeURIComponent(status || 'pending');
  if (orderId) href += '&order_id=' + encodeURIComponent(String(orderId));
  return href;
}

function rememberOrdersTab(status, orders) {
  pharmacyOrdersState.tabs[status] = { orders: (orders || []).slice() };
}

function showCachedOrdersTab(status) {
  const cached = pharmacyOrdersState.tabs[status];
  if (!cached) return false;
  pharmacyOrdersState.status = status;
  pharmacyOrdersState.orders = cached.orders.slice();
  pharmacyOrdersState.selectedId = 0;
  renderPharmacyOrders();
  return true;
}

function upsertCachedTabOrder(tabStatus, order) {
  const tab = pharmacyOrdersState.tabs[tabStatus];
  if (!tab || !order) return;
  const orderId = Number(order.id || 0);
  const index = tab.orders.findIndex(function (row) { return Number(row.id) === orderId; });
  const belongs = tabStatus === 'all' || order.status === tabStatus;
  if (!belongs) {
    if (index >= 0) tab.orders.splice(index, 1);
    return;
  }
  if (index >= 0) tab.orders[index] = order;
  else tab.orders.unshift(order);
}

function applyOrdersPayload(payload, status) {
  if (!payload || payload.success === false) return;
  const nextStatus = payload.status || status || pharmacyOrdersState.status;
  pharmacyOrdersState.status = nextStatus;
  pharmacyOrdersState.counts = Object.assign({}, pharmacyOrdersState.counts, payload.counts || {});
  pharmacyOrdersState.total = Number(payload.total || 0);
  pharmacyOrdersState.orders = Array.isArray(payload.orders) ? payload.orders : [];
  rememberOrdersTab(nextStatus, pharmacyOrdersState.orders);
  pharmacyOrdersState.orders.forEach(function (row) {
    if (row && row.detail && row.id) {
      pharmacyOrdersState.details[row.id] = row.detail;
    }
  });
}

function renderPharmacyOrders() {
  const view = document.getElementById('view-orders');
  if (!view) return;
  const counts = pharmacyOrdersState.counts || {};
  const total = pharmacyOrdersState.total || Object.keys(counts).reduce(function (sum, key) {
    return sum + Number(counts[key] || 0);
  }, 0);
  const stats = view.querySelectorAll('.orders-stat strong');
  if (stats[0]) stats[0].textContent = String(total);
  if (stats[1]) stats[1].textContent = String(counts.delivered || 0);
  if (stats[2]) stats[2].textContent = String(counts.pending || 0);
  if (stats[3]) stats[3].textContent = String(counts.cancelled || 0);

  view.querySelectorAll('.tabs a.tab-btn').forEach(function (tab) {
    let tabStatus = 'pending';
    try {
      tabStatus = new URL(tab.getAttribute('href'), window.location.href).searchParams.get('status') || 'pending';
    } catch (error) {}
    tab.classList.toggle('active', tabStatus === pharmacyOrdersState.status);
    const countEl = tab.querySelector('.count');
    if (countEl) {
      countEl.textContent = String(tabStatus === 'all' ? total : (counts[tabStatus] || 0));
    }
  });

  const card = view.querySelector('.orders-table-card');
  if (!card) return;
  const orders = pharmacyOrdersState.orders || [];
  if (orders.length === 0) {
    const label = pharmacyOrdersState.status === 'all'
      ? 'No orders yet'
      : ('No ' + ((ORDER_STATUS_MAP[pharmacyOrdersState.status] || {}).t || pharmacyOrdersState.status) + ' orders');
    card.innerHTML = '<div class="orders-empty"><p class="orders-empty-title">' + escapeHtml(label) + '</p><p class="orders-empty-copy">Orders in this status will appear here.</p></div>';
    return;
  }

  const selectedId = pharmacyOrdersState.selectedId;
  const status = pharmacyOrdersState.status;
  const rows = orders.map(function (order) {
    const id = Number(order.id || 0);
    const href = ordersHref(status, id);
    const active = selectedId > 0 && id === selectedId ? ' is-active' : '';
    const avatar = order.avatar || { bg: '#e6f7f1', fg: '#0d9488' };
    return '<tr class="orders-table-row' + active + '" data-order-id="' + id + '">' +
      '<td><a class="orders-table-link" href="' + href + '"><span class="ot-customer"><span class="ot-avatar" style="background:' + escapeHtml(avatar.bg) + ';color:' + escapeHtml(avatar.fg) + ';">' + escapeHtml(order.customer_initials || '') + '</span><span class="ot-customer-name">' + escapeHtml(order.customer_name || 'Customer') + '</span></span></a></td>' +
      '<td><a class="orders-table-link" href="' + href + '"><span class="ot-order">#' + escapeHtml(order.order_number || '') + '</span></a></td>' +
      '<td><a class="orders-table-link" href="' + href + '"><span class="ot-meds">' + escapeHtml(order.medicines_summary || '') + '</span></a></td>' +
      '<td><a class="orders-table-link" href="' + href + '">' + escapeHtml(order.date_label || '') + '</a></td>' +
      '<td><a class="orders-table-link" href="' + href + '">' + escapeHtml(order.time_label || '') + '</a></td>' +
      '<td><a class="orders-table-link" href="' + href + '"><span class="ot-amount">' + escapeHtml(order.amount_label || '') + '</span></a></td>' +
      '<td><a class="orders-table-link" href="' + href + '"><span class="ot-pill ot-pill--' + escapeHtml(order.status || '') + '">' + escapeHtml(order.status_label || order.status || '') + '</span></a></td>' +
    '</tr>';
  }).join('');
  card.innerHTML = '<div class="orders-table-wrap"><table class="orders-table"><thead><tr><th>Customer</th><th>Order</th><th>Medicines</th><th>Order Date</th><th>Order Time</th><th>Amount</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
}

function applyOrderStatusLocally(orderId, nextStatus, extra) {
  extra = extra || {};
  const counts = pharmacyOrdersState.counts;
  const order = (pharmacyOrdersState.orders || []).find(function (row) { return Number(row.id) === Number(orderId); });
  const prev = order ? order.status : '';
  if (counts && prev && prev !== nextStatus) {
    counts[prev] = Math.max(0, Number(counts[prev] || 0) - 1);
    counts[nextStatus] = Number(counts[nextStatus] || 0) + 1;
    pharmacyOrdersState.total = Object.keys(counts).reduce(function (sum, key) {
      return sum + Number(counts[key] || 0);
    }, 0);
  }
  if (order) {
    order.status = nextStatus;
    order.status_label = extra.label || (ORDER_STATUS_MAP[nextStatus] || {}).t || nextStatus;
    if (extra.cancellation_reason) {
      order.cancellation_reason = extra.cancellation_reason;
    }
    if (order.detail) {
      order.detail.status = nextStatus;
      order.detail.status_label = order.status_label;
      order.detail.is_pending = nextStatus === 'pending';
      order.detail.is_ready = nextStatus === 'ready';
      order.detail.is_completed = nextStatus === 'delivered';
      order.detail.is_cancelled = nextStatus === 'cancelled';
      order.detail.can_view_prescription = ['pending', 'confirmed', 'preparing', 'ready'].indexOf(nextStatus) !== -1;
      if (!order.detail.can_view_prescription && Array.isArray(order.detail.items)) {
        order.detail.items.forEach(function (item) { item.prescription_url = ''; });
      }
      order.detail.next_status = nextStatus === 'pending' ? 'confirmed' : (nextStatus === 'confirmed' ? 'preparing' : (nextStatus === 'preparing' ? 'ready' : null));
      order.detail.next_label = (ORDER_STATUS_MAP[order.detail.next_status] || {}).t || '';
      if (extra.cancellation_reason) order.detail.cancellation_reason = extra.cancellation_reason;
      pharmacyOrdersState.details[orderId] = order.detail;
    }
  }
  Object.keys(pharmacyOrdersState.tabs).forEach(function (tabStatus) {
    upsertCachedTabOrder(tabStatus, order);
  });
  const currentTab = pharmacyOrdersState.tabs[pharmacyOrdersState.status];
  if (currentTab) {
    pharmacyOrdersState.orders = currentTab.orders.slice();
  } else if (pharmacyOrdersState.status !== 'all' && pharmacyOrdersState.status !== nextStatus) {
    pharmacyOrdersState.orders = (pharmacyOrdersState.orders || []).filter(function (row) {
      return Number(row.id) !== Number(orderId);
    });
  }
}

function hydratePharmacyOrders() {
  const node = document.getElementById('pharmacy-orders-payload');
  if (!node) return;
  try {
    applyOrdersPayload(JSON.parse(node.textContent || '{}'), currentOrdersStatus());
  } catch (error) {}
}

function orderProgressIndex(status) {
  const key = String(status || '').toLowerCase();
  if (key === 'cancelled') return 0;
  if (key === 'confirmed') return 2;
  if (key === 'preparing') return 3;
  if (key === 'ready') return 4;
  if (['picked_up', 'pickedup', 'delivered', 'completed'].indexOf(key) !== -1) return 5;
  return 1;
}

function orderProgressIcon(name) {
  const paths = {
    check: '<path d="m5 13 4 4L19 7"/>',
    clock: '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    capsule: '<path d="m8 5 11 11a3.5 3.5 0 0 1-5 5L3 10a3.5 3.5 0 0 1 5-5Z"/><path d="m7 14 7-7"/>',
    store: '<path d="M3 10h18l-1.2-5H4.2z"/><path d="M4 10v10h16V10M9 20v-6h6v6"/>'
  };
  return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (paths[name] || paths.check) + '</svg>';
}

function orderProgressHtml(status, stamp) {
  const current = orderProgressIndex(status);
  const cancelled = String(status || '').toLowerCase() === 'cancelled';
  const steps = [
    { name: 'Order placed', icon: 'check' },
    { name: 'Pending', icon: 'clock' },
    { name: 'Confirmed', icon: 'check' },
    { name: 'Preparing', icon: 'capsule' },
    { name: 'Ready for pick up', icon: 'store' },
    { name: 'Completed', icon: 'check' }
  ];
  return '<section class="order-progress" aria-label="Order progress">' + steps.map(function (step, index) {
    const state = cancelled && index > 0
      ? 'is-muted'
      : (index < current ? 'is-done' : index === current ? 'is-current' : 'is-muted');
    const icon = (state === 'is-done' || (state === 'is-current' && index === 5)) ? 'check' : step.icon;
    const time = state !== 'is-muted' ? stamp : '';
    const rail = index < steps.length - 1
      ? '<span class="order-progress-rail' + (index < current ? ' is-done' : '') + '"></span>'
      : '';
    return '<div class="order-progress-step ' + state + (time ? ' is-dated' : '') + '"><span class="order-progress-node">' + orderProgressIcon(icon) + '</span><strong>' + escapeHtml(step.name) + '</strong><small>' + (time ? escapeHtml(time) : '&nbsp;') + '</small></div>' + rail;
  }).join('') + '</section>';
}

function renderOrderDrawerDetail(detail) {
  const drawer = document.getElementById('order-drawer');
  if (!drawer || !detail) return;
  const status = pharmacyOrdersState.status;
  const closeHref = ordersHref(status);
  const canViewRx = detail.can_view_prescription !== false && !detail.is_completed && !detail.is_cancelled;
  const itemsHtml = (detail.items || []).map(function (item) {
    const rxUrl = String(item.prescription_url || detail.prescription_url || '').trim();
    const rx = item.prescription_required
      ? (canViewRx && rxUrl
        ? ' · <button type="button" class="order-prescription-inline view-prescription-trigger" data-prescription-url="' + escapeHtml(rxUrl) + '">Rx required · see prescription ›</button>'
        : ' · Rx required')
      : '';
    const note = item.note ? '<div class="t2 order-item-note">Note: ' + escapeHtml(item.note) + '</div>' : '';
    const thumb = item.image_url
      ? '<div class="med-thumb' + (item.prescription_required ? ' med-thumb--rx' : '') + ' med-thumb--photo"><img src="' + escapeHtml(item.image_url) + '" alt=""></div>'
      : '<div class="med-thumb' + (item.prescription_required ? ' med-thumb--rx' : '') + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></div>';
    return '<div class="list-row order-list-row order-med-row">' + thumb +
      '<div class="list-body"><div class="t1 order-med-title">' + escapeHtml(item.name || 'Medicine') + '</div>' +
      '<div class="t2">Qty ' + Number(item.quantity || 1) + rx + '</div>' + note + '</div>' +
      '<div class="list-meta mono">' + escapeHtml(item.line_total_label || '') + '</div></div>';
  }).join('');

  let actions = '';
  if (detail.is_pending) {
    actions = '<div class="order-panel-actions"><div class="order-panel-actions-row">' +
      '<button type="button" class="btn btn-danger order-cancel-btn" id="cancel-order-btn" data-order-id="' + detail.id + '" data-order-number="' + escapeHtml(detail.order_number) + '" data-customer="' + escapeHtml(detail.customer_name) + '">Cancel order</button>' +
      '<button type="button" class="btn btn-accent order-confirm-btn" id="confirm-order-btn" data-order-id="' + detail.id + '" data-next-status="confirmed" data-next-label="Confirmed">Confirm order</button>' +
      '</div></div>';
  } else if (detail.next_status) {
    actions = '<div class="order-panel-actions"><div class="order-panel-actions-row">' +
      '<button type="button" class="btn btn-accent order-update-status-btn" id="update-order-status-btn" data-order-id="' + detail.id + '" data-next-status="' + escapeHtml(detail.next_status) + '" data-next-label="' + escapeHtml(detail.next_label) + '">Update status</button>' +
      '</div></div>';
  } else if (detail.is_ready) {
    actions = '<div class="order-panel-actions"><div class="order-panel-actions-row">' +
      '<button type="button" class="btn btn-accent order-complete-btn" id="complete-order-btn" data-order-id="' + detail.id + '" data-order-number="' + escapeHtml(detail.order_number) + '" data-customer="' + escapeHtml(detail.customer_name) + '" data-has-proof="' + (detail.has_pickup_proof ? '1' : '0') + '">Mark as complete</button>' +
      '</div></div>';
  }

  let extra = '';
  if (detail.is_completed && detail.has_pickup_proof) {
    extra += '<div class="order-rx-docs-block"><h3 class="order-rx-docs-title">Proof of pickup</h3>' +
      '<button type="button" class="order-rx-doc-card view-prescription-trigger" data-prescription-url="' + escapeHtml(detail.pickup_proof_url || '') + '">' +
      '<span class="order-rx-doc-meta"><strong>Pickup completed</strong><small>' + escapeHtml(detail.pickup_proof_name || '') + '</small></span></button></div>';
  }
  if (detail.is_cancelled) {
    extra += '<div class="order-section-label">Cancellation reason</div><div class="order-cancellation-card"><p class="order-cancellation-text">' + escapeHtml(detail.cancellation_reason || 'No reason recorded.') + '</p></div>';
  }

  const overview = drawer.querySelector('.order-overview');
  if (!overview) return;
  overview.innerHTML =
    '<div class="panel-head"><div><h3 id="order-drawer-title">Order #' + escapeHtml(detail.order_number) + '</h3></div>' +
    '<a class="order-drawer-close-btn" id="order-drawer-close-btn" href="' + closeHref + '" aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></a></div>' +
    orderProgressHtml(detail.status, detail.created_stamp || detail.created_label || '') +
    '<div class="order-overview-body">' +
    '<div class="order-overview-main">' +
    '<div class="order-section-label">Customer</div>' +
    '<div class="list-row order-list-row"><div class="avatar" style="width:36px;height:36px;font-size:12px;">' + escapeHtml(detail.customer_initials) + '</div>' +
    '<div class="list-body"><div class="t1">' + escapeHtml(detail.customer_name) + '</div><div class="t2">' + escapeHtml(detail.customer_line) + '</div></div></div>' +
    '<div class="order-section-label">Medicines</div>' + itemsHtml + extra +
    '</div>' +
    '<div class="order-overview-side"><div class="order-payment-card"><h3 class="payment-summary-title"><span>Payment Summary<small>Payment details and transaction breakdown</small></span></h3>' +
    '<div class="payment-summary-method"><b class="payment-gcash-icon">G</b><span class="payment-summary-details"><strong>GCash</strong><b>' + escapeHtml(detail.paid_label) + '</b></span><small class="payment-summary-meta">Payment confirmed via PayMongo<br>' + escapeHtml(detail.created_label) + '</small><span class="payment-summary-confirmed">✓ Paid</span></div>' +
    '<div class="order-payment-row"><span>Total Amount</span><span class="mono">' + escapeHtml(detail.total_label) + '</span></div>' +
    '<div class="order-payment-row order-payment-row--paid"><span>Paid (' + Number(detail.paid_percent || 0) + '%)<small>' + Number(detail.paid_note_percent || 0) + '% · paid via GCash</small></span><span class="mono">' + escapeHtml(detail.paid_label) + '</span></div>' +
    '<div class="order-payment-row order-payment-row--due"><span>Remaining Balance (' + Number(detail.balance_percent || 0) + '%)</span><span class="mono">' + escapeHtml(detail.balance_label) + '</span></div>' +
    '<p class="order-payment-note">' + escapeHtml(detail.payment_note) + '</p></div></div>' +
    '</div>' + actions;
  drawer.hidden = false;
  drawer.classList.remove('is-loading');
  drawer.setAttribute('data-order-id', String(detail.id || 0));
  if (typeof window.preloadRxPreviewList === 'function') {
    const urls = (detail.items || []).map(function (item) { return item.prescription_url; }).filter(Boolean);
    if (detail.prescription_url) urls.push(detail.prescription_url);
    window.preloadRxPreviewList(urls);
  }
}

function syncOrdersHistory(nextUrl, options) {
  options = options || {};
  if (options.skipHistory || !nextUrl) return;
  const currentUrl = window.location.pathname + window.location.search;
  if (currentUrl === nextUrl) return;
  const state = { view: 'orders' };
  if (options.replace) history.replaceState(state, '', nextUrl);
  else history.pushState(state, '', nextUrl);
}

function ordersFragmentUrl(nextUrl) {
  const parsed = parseOrdersHref(nextUrl);
  const frag = new URL('api/orders-view.php', window.location.href);
  frag.searchParams.set('status', parsed.status);
  return frag.pathname + frag.search;
}

function markActiveOrderRow(orderId) {
  document.querySelectorAll('#view-orders .orders-table-row').forEach(function (row) {
    const id = parseInt(row.getAttribute('data-order-id') || '0', 10);
    row.classList.toggle('is-active', orderId > 0 && id === orderId);
  });
}

function applyOrderDrawerHtml(html, orderId) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const incoming = doc.getElementById('order-drawer');
  const current = document.getElementById('order-drawer');
  if (!incoming || !current) return false;
  const loadedId = parseInt(incoming.getAttribute('data-order-id') || '0', 10);
  if (orderId > 0 && loadedId > 0 && loadedId !== orderId) return false;
  current.replaceWith(incoming);
  incoming.hidden = false;
  incoming.classList.remove('is-loading');
  incoming.setAttribute('data-order-id', String(orderId || loadedId || 0));
  return true;
}

function showOrderDrawerPlaceholder(orderId, fromEl) {
  const drawer = document.getElementById('order-drawer');
  if (!drawer) return;
  const row = fromEl && fromEl.closest ? fromEl.closest('.orders-table-row') : null;
  const orderNumber = ((row && row.querySelector('.ot-order')) ? row.querySelector('.ot-order').textContent : '').trim() || ('#' + orderId);
  const customer = ((row && row.querySelector('.ot-customer-name')) ? row.querySelector('.ot-customer-name').textContent : '').trim();
  const closeHref = 'index.php?view=orders&status=' + encodeURIComponent(currentOrdersStatus());
  drawer.hidden = false;
  drawer.classList.add('is-loading');
  drawer.setAttribute('data-order-id', String(orderId));
  const overview = drawer.querySelector('.order-overview');
  if (!overview) return;
  overview.innerHTML =
    '<div class="panel-head">' +
      '<div><h3 id="order-drawer-title">Order ' + escapeHtml(orderNumber.replace(/^#/, '#')) + '</h3></div>' +
      '<a class="order-drawer-close-btn" id="order-drawer-close-btn" href="' + closeHref + '" aria-label="Close">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
      '</a>' +
    '</div>' +
    '<p class="order-empty-panel">' + (customer ? ('Loading details for ' + escapeHtml(customer) + '…') : 'Loading order details…') + '</p>';
}

async function loadOrderDrawer(orderId, status, options) {
  options = options || {};
  const requestId = ++drawerRequestId;
  if (drawerAbort) drawerAbort.abort();
  drawerAbort = new AbortController();

  try {
    if (pharmacyOrdersState.details[orderId]) {
      if (requestId !== drawerRequestId) return;
      renderOrderDrawerDetail(pharmacyOrdersState.details[orderId]);
      return;
    }
    const response = await fetch(
      'api/order-detail.php?order_id=' + encodeURIComponent(String(orderId)),
      {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
        signal: drawerAbort.signal,
      }
    );
    if (requestId !== drawerRequestId) return;
    if (!response.ok) throw new Error('load');
    const result = await response.json();
    if (requestId !== drawerRequestId) return;
    if (!result || !result.success || !result.order) throw new Error('parse');
    pharmacyOrdersState.details[orderId] = result.order;
    renderOrderDrawerDetail(result.order);
  } catch (error) {
    if (error && error.name === 'AbortError') return;
    if (options.fallbackUrl) window.location.href = options.fallbackUrl;
  }
}

function openOrderFromLink(href, options, fromEl) {
  options = options || {};
  const parsed = parseOrdersHref(href);
  if (parsed.orderId <= 0) {
    closeOrderDrawer(parsed.nextUrl, options);
    return;
  }
  ordersNavGen += 1;
  if (listAbort) listAbort.abort();
  pharmacyOrdersState.selectedId = parsed.orderId;
  syncOrdersHistory(parsed.nextUrl, options);
  markActiveOrderRow(parsed.orderId);
  const cached = pharmacyOrdersState.details[parsed.orderId];
  if (cached) {
    renderOrderDrawerDetail(cached);
    return;
  }
  showOrderDrawerPlaceholder(parsed.orderId, fromEl);
  loadOrderDrawer(parsed.orderId, parsed.status, { fallbackUrl: parsed.nextUrl });
}

function closeOrderDrawer(href, options) {
  options = options || {};
  const nextUrl = ordersViewUrl(href);
  drawerRequestId += 1;
  if (drawerAbort) drawerAbort.abort();
  pharmacyOrdersState.selectedId = 0;
  const drawer = document.getElementById('order-drawer');
  if (drawer) {
    drawer.hidden = true;
    drawer.classList.remove('is-loading');
    drawer.setAttribute('data-order-id', '0');
  }
  markActiveOrderRow(0);
  syncOrdersHistory(nextUrl, Object.assign({ replace: true }, options));
}

function setActiveOrdersTab(status) {
  document.querySelectorAll('#view-orders .tabs a.tab-btn').forEach(function (tab) {
    try {
      const tabStatus = new URL(tab.getAttribute('href'), window.location.href).searchParams.get('status') || 'pending';
      tab.classList.toggle('active', tabStatus === status);
    } catch (error) {
      tab.classList.remove('active');
    }
  });
}

function showOrdersTableLoading() {
  const card = document.querySelector('#view-orders .orders-table-card');
  if (!card) return;
  card.innerHTML = '<div class="orders-empty"><p class="orders-empty-title">Loading orders…</p></div>';
}

async function loadOrdersView(href, options) {
  options = options || {};
  const nextUrl = ordersViewUrl(href);
  if (!nextUrl) return;
  const parsed = parseOrdersHref(nextUrl);
  const requestId = ++listRequestId;
  const navGen = options.navGen || ordersNavGen;
  if (listAbort) listAbort.abort();
  listAbort = new AbortController();

  try {
    const response = await fetch('api/orders-list.php?status=' + encodeURIComponent(parsed.status), {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
      signal: listAbort.signal,
    });
    if (requestId !== listRequestId || navGen !== ordersNavGen) return;
    if (!response.ok) throw new Error('load');
    const payload = await response.json();
    if (requestId !== listRequestId || navGen !== ordersNavGen) return;
    applyOrdersPayload(payload, parsed.status);
    if (options.keepSelection) {
      pharmacyOrdersState.selectedId = parsed.orderId || pharmacyOrdersState.selectedId;
    } else if (!options.keepDrawer) {
      pharmacyOrdersState.selectedId = 0;
    }
    renderPharmacyOrders();
    syncOrdersHistory(nextUrl, options);
  } catch (error) {
    if (error && error.name === 'AbortError') return;
    if (requestId !== listRequestId || navGen !== ordersNavGen) return;
    window.location.href = nextUrl;
  }
}

function switchOrdersTab(href, options) {
  options = options || {};
  const parsed = parseOrdersHref(href);
  drawerRequestId += 1;
  if (drawerAbort) drawerAbort.abort();
  closeOrderDrawer(parsed.nextUrl, { skipHistory: true });
  syncOrdersHistory(parsed.nextUrl, options);
  pharmacyOrdersState.selectedId = 0;
  setActiveOrdersTab(parsed.status);
  if (showCachedOrdersTab(parsed.status)) {
    return;
  }
  pharmacyOrdersState.status = parsed.status;
  showOrdersTableLoading();
  loadOrdersView(parsed.nextUrl, { skipHistory: true, navGen: ++ordersNavGen });
}

async function refreshCachedOrderTabs() {
  const statuses = Object.keys(pharmacyOrdersState.tabs);
  if (statuses.indexOf(pharmacyOrdersState.status) === -1) {
    statuses.push(pharmacyOrdersState.status);
  }
  const current = pharmacyOrdersState.status;
  const selectedId = pharmacyOrdersState.selectedId;
  try {
    const payloads = await Promise.all(statuses.map(function (status) {
      return fetch('api/orders-list.php?status=' + encodeURIComponent(status), {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      }).then(function (response) {
        if (!response.ok) throw new Error('load');
        return response.json();
      });
    }));
    payloads.forEach(function (payload) {
      if (!payload || payload.success === false) return;
      const status = payload.status;
      pharmacyOrdersState.counts = Object.assign({}, pharmacyOrdersState.counts, payload.counts || {});
      pharmacyOrdersState.total = Number(payload.total || pharmacyOrdersState.total);
      rememberOrdersTab(status, payload.orders || []);
      (payload.orders || []).forEach(function (row) {
        if (row && row.detail && row.id) {
          pharmacyOrdersState.details[row.id] = row.detail;
        }
      });
    });
    if (pharmacyOrdersState.tabs[current]) {
      pharmacyOrdersState.status = current;
      pharmacyOrdersState.orders = pharmacyOrdersState.tabs[current].orders.slice();
    }
    pharmacyOrdersState.selectedId = selectedId;
    renderPharmacyOrders();
    const drawer = document.getElementById('order-drawer');
    if (drawer && drawer.hidden === false && selectedId > 0) {
      const stillThere = (pharmacyOrdersState.orders || []).some(function (row) {
        return Number(row.id) === Number(selectedId);
      });
      if (stillThere && pharmacyOrdersState.details[selectedId]) {
        renderOrderDrawerDetail(pharmacyOrdersState.details[selectedId]);
      } else if (!stillThere) {
        closeOrderDrawer(ordersHref(current), { replace: true });
      }
    }
  } catch (error) {}
}

function stayOnOrdersTable() {
  const status = currentOrdersStatus();
  const href = ordersHref(status);
  closeOrderDrawer(href, { replace: true });
  renderPharmacyOrders();
}

function navigateOrdersLink(link) {
  const href = link.getAttribute('href');
  if (!href) return;
  if (link.classList.contains('tab-btn')) {
    switchOrdersTab(href);
    return;
  }
  if (link.classList.contains('order-drawer-close-btn') || link.id === 'order-drawer-close') {
    const close = document.getElementById('order-drawer-close-btn');
    closeOrderDrawer((close && close.getAttribute('href')) || href);
    return;
  }
  openOrderFromLink(href, {}, link);
}

async function submitOrderStatusAdvance(button) {
  const orderId = parseInt(button.dataset.orderId || '0', 10);
  const nextStatus = button.dataset.nextStatus || '';
  if (!orderId || !nextStatus) return;

  const originalLabel = button.textContent;
  button.disabled = true;
  button.textContent = 'Updating...';

  try {
    const body = new FormData();
    body.append('order_id', String(orderId));

    const response = await fetch('api/update-order-status.php', {
      method: 'POST',
      body,
      credentials: 'same-origin',
    });

    const result = await response.json().catch(function () {
      return { success: false, message: 'Could not update order status.' };
    });

    if (!response.ok || !result.success) {
      window.alert(result.message || 'Could not update order status.');
      button.disabled = false;
      button.textContent = originalLabel;
      return;
    }

    applyOrderStatusLocally(orderId, result.status || nextStatus);
    stayOnOrdersTable();
  } catch (error) {
    window.alert('Could not update order status. Please try again.');
    button.disabled = false;
    button.textContent = originalLabel;
  }
}

async function completeOrder(orderId) {
  const body = new FormData();
  body.append('order_id', String(orderId));

  const response = await fetch('api/complete-order.php', {
    method: 'POST',
    body,
    credentials: 'same-origin',
  });

  const result = await response.json().catch(function () {
    return { success: false, message: 'Could not complete this order.' };
  });

  if (!response.ok || !result.success) {
    throw new Error(result.message || 'Could not complete this order.');
  }

  applyOrderStatusLocally(orderId, 'delivered');
  stayOnOrdersTable();
}

async function uploadPickupProof(orderId, file) {
  const body = new FormData();
  body.append('order_id', String(orderId));
  body.append('proof', file);

  const response = await fetch('api/upload-pickup-proof.php', {
    method: 'POST',
    body,
    credentials: 'same-origin',
  });

  const result = await response.json().catch(function () {
    return { success: false, message: 'Could not upload proof.' };
  });

  if (!response.ok || !result.success) {
    throw new Error(result.message || 'Could not upload proof.');
  }
}

async function handleCancelOrderSubmit(cancelOrderSubmitBtn) {
  const select = document.getElementById('cancel-reason-select');
  const otherInput = document.getElementById('cancel-reason-other-input');
  const reason = getCancelReason();
  const orderId = cancelOrderId;

  if (!orderId) return;
  if (!(select?.value || '')) {
    setCancelOrderError('Please select a cancellation reason.');
    select?.focus();
    return;
  }
  if ((select?.value || '') === CANCEL_REASON_OTHER && !reason) {
    setCancelOrderError('Please specify the cancellation reason.');
    otherInput?.focus();
    return;
  }

  cancelOrderSubmitBtn.disabled = true;
  cancelOrderSubmitBtn.textContent = 'Cancelling...';
  setCancelOrderError('');

  try {
    const body = new FormData();
    body.append('order_id', String(orderId));
    body.append('reason', reason);

    const response = await fetch('api/cancel-order.php', {
      method: 'POST',
      body,
      credentials: 'same-origin',
    });

    const result = await response.json().catch(function () {
      return { success: false, message: 'Could not cancel order.' };
    });

    if (!response.ok || !result.success) {
      setCancelOrderError(result.message || 'Could not cancel order.');
      cancelOrderSubmitBtn.disabled = false;
      cancelOrderSubmitBtn.textContent = 'Cancel order';
      return;
    }

    closeCancelOrderModal();
    applyOrderStatusLocally(orderId, 'cancelled', { cancellation_reason: reason, label: 'Cancelled' });
    stayOnOrdersTable();
  } catch (error) {
    setCancelOrderError('Could not cancel order. Please try again.');
    cancelOrderSubmitBtn.disabled = false;
    cancelOrderSubmitBtn.textContent = 'Cancel order';
  }
}

async function handlePickupProofSubmit(pickupProofSubmitBtn) {
  const input = document.getElementById('pickup-proof-input');
  const file = input?.files && input.files[0];
  const orderId = pickupProofOrderId;

  if (!orderId) return;
  if (!file && !pickupProofHasExisting) {
    setPickupProofError('Choose a file to continue.');
    return;
  }

  pickupProofSubmitBtn.disabled = true;
  pickupProofSubmitBtn.textContent = 'Processing...';
  setPickupProofError('');

  try {
    if (file) {
      await uploadPickupProof(orderId, file);
    }
    await completeOrder(orderId);
    closePickupProofModal();
  } catch (error) {
    setPickupProofError(error.message || 'Could not complete this order.');
    pickupProofSubmitBtn.disabled = false;
    pickupProofSubmitBtn.textContent = 'Mark as complete';
    updatePickupProofSubmitState();
  }
}

function handlePickupProofChange() {
  const pickupProofInput = document.getElementById('pickup-proof-input');
  const file = pickupProofInput && pickupProofInput.files && pickupProofInput.files[0];
  const preview = document.getElementById('pickup-proof-preview');
  const previewImage = document.getElementById('pickup-proof-preview-image');
  const previewName = document.getElementById('pickup-proof-preview-name');

  setPickupProofError('');

  if (!file) {
    if (preview) preview.hidden = true;
    updatePickupProofSubmitState();
    return;
  }

  if (previewName) {
    previewName.textContent = file.name;
  }
  if (preview) {
    preview.hidden = false;
  }
  if (previewImage) {
    if (file.type === 'application/pdf') {
      previewImage.hidden = true;
      previewImage.removeAttribute('src');
    } else {
      previewImage.hidden = false;
      previewImage.src = URL.createObjectURL(file);
    }
  }

  pickupProofHasExisting = false;
  updatePickupProofSubmitState();
}

function initPharmacyOrderUi() {
  const view = document.getElementById('view-orders');
  if (!view || view.dataset.orderUiBound === '1') return;
  view.dataset.orderUiBound = '1';

  view.addEventListener('click', function (event) {
    const tab = event.target.closest('.tabs a.tab-btn');
    if (tab && view.contains(tab)) {
      event.preventDefault();
      navigateOrdersLink(tab);
      return;
    }

    const tableLink = event.target.closest('a.orders-table-link');
    if (tableLink && view.contains(tableLink)) {
      event.preventDefault();
      navigateOrdersLink(tableLink);
      return;
    }

    const closeEl = event.target.closest('#order-drawer-close, #order-drawer-close-btn');
    if (closeEl) {
      event.preventDefault();
      navigateOrdersLink(closeEl);
      return;
    }

    const rx = event.target.closest('.view-prescription-trigger');
    if (rx) {
      if (rx.classList.contains('order-prescription-inline')) {
        const detail = pharmacyOrdersState.details[pharmacyOrdersState.selectedId];
        if (detail && (detail.is_completed || detail.is_cancelled || detail.can_view_prescription === false)) {
          return;
        }
      }
      openPrescriptionViewer(rx.getAttribute('data-prescription-url') || '');
      return;
    }

    if (event.target.closest('#prescription-viewer-close, #prescription-viewer-close-btn')) {
      closePrescriptionViewer();
      return;
    }
    if (event.target.closest('#pickup-proof-modal-close, #pickup-proof-modal-close-btn, #pickup-proof-cancel-btn')) {
      closePickupProofModal();
      return;
    }
    if (event.target.closest('#cancel-order-modal-close, #cancel-order-modal-close-btn, #cancel-order-dismiss-btn')) {
      closeCancelOrderModal();
      return;
    }

    const updateStatusBtn = event.target.closest('#update-order-status-btn, #confirm-order-btn');
    if (updateStatusBtn) {
      submitOrderStatusAdvance(updateStatusBtn);
      return;
    }

    const completeBtn = event.target.closest('#complete-order-btn');
    if (completeBtn) {
      const orderId = parseInt(completeBtn.dataset.orderId || '0', 10);
      if (!orderId) return;
      openPickupProofModal(
        orderId,
        completeBtn.dataset.orderNumber || '',
        completeBtn.dataset.customer || '',
        completeBtn.dataset.hasProof || '0'
      );
      return;
    }

    const cancelBtn = event.target.closest('#cancel-order-btn');
    if (cancelBtn) {
      const orderId = parseInt(cancelBtn.dataset.orderId || '0', 10);
      if (!orderId) return;
      openCancelOrderModal(
        orderId,
        cancelBtn.dataset.orderNumber || '',
        cancelBtn.dataset.customer || ''
      );
      return;
    }

    const cancelOrderSubmitBtn = event.target.closest('#cancel-order-submit-btn');
    if (cancelOrderSubmitBtn) {
      handleCancelOrderSubmit(cancelOrderSubmitBtn);
      return;
    }

    const pickupProofSubmitBtn = event.target.closest('#pickup-proof-submit-btn');
    if (pickupProofSubmitBtn) {
      handlePickupProofSubmit(pickupProofSubmitBtn);
    }
  });

  view.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'cancel-reason-select') {
      setCancelOrderError('');
      toggleCancelOtherField();
    }
    if (event.target && event.target.id === 'pickup-proof-input') {
      handlePickupProofChange();
    }
  });

  view.addEventListener('input', function (event) {
    if (event.target && event.target.id === 'cancel-reason-other-input') {
      setCancelOrderError('');
    }
  });

  if (!window.__pharmacyOrderEscapeBound) {
    window.__pharmacyOrderEscapeBound = true;
    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      if (document.getElementById('cancel-order-modal')?.hidden === false) {
        closeCancelOrderModal();
        return;
      }
      if (document.getElementById('pickup-proof-modal')?.hidden === false) {
        closePickupProofModal();
        return;
      }
      if (document.getElementById('prescription-viewer')?.hidden === false) {
        closePrescriptionViewer();
        return;
      }
      const drawerClose = document.getElementById('order-drawer-close-btn');
      if (document.getElementById('order-drawer')?.hidden === false && drawerClose) {
        navigateOrdersLink(drawerClose);
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', function () {
  hydratePharmacyOrders();
  initPharmacyOrderUi();
  const selectedId = parseInt(new URLSearchParams(window.location.search).get('order_id') || '0', 10);
  if (selectedId > 0 && pharmacyOrdersState.details[selectedId]) {
    pharmacyOrdersState.selectedId = selectedId;
    renderOrderDrawerDetail(pharmacyOrdersState.details[selectedId]);
  }
});
document.addEventListener('livesync:applied', function () {
  const view = document.getElementById('view-orders');
  if (view) delete view.dataset.orderUiBound;
  initPharmacyOrderUi();
});
document.addEventListener('livesync:change', function (event) {
  const keys = (event.detail && event.detail.keys) || [];
  if (keys.indexOf('orders') === -1) return;
  const view = document.getElementById('view-orders');
  if (!view || !view.classList.contains('active')) return;
  refreshCachedOrderTabs();
});
window.addEventListener('popstate', function () {
  const params = new URLSearchParams(window.location.search);
  if ((params.get('view') || '') !== 'orders') return;
  const href = window.location.pathname + window.location.search;
  const orderId = parseInt(params.get('order_id') || '0', 10);
  const status = params.get('status') || 'pending';
  const activeTab = document.querySelector('#view-orders .tab-btn.active');
  let tabStatus = status;
  if (activeTab) {
    try {
      tabStatus = new URL(activeTab.getAttribute('href'), window.location.href).searchParams.get('status') || status;
    } catch (error) {
      tabStatus = status;
    }
  }
  if (tabStatus !== status) {
    switchOrdersTab(href, { skipHistory: true });
    return;
  }
  if (orderId > 0) {
    openOrderFromLink(href, { skipHistory: true });
    return;
  }
  closeOrderDrawer(href, { skipHistory: true });
});
