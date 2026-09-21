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

function openPrescriptionViewer(url, title) {
  const viewer = document.getElementById('prescription-viewer');
  const image = document.getElementById('prescription-viewer-image');
  const frame = document.getElementById('prescription-viewer-frame');
  const missing = document.getElementById('prescription-viewer-missing');
  const heading = document.getElementById('prescription-viewer-title');
  const zoom = document.getElementById('prescription-viewer-zoom');
  const closeBackdrop = document.getElementById('prescription-viewer-close');
  if (!viewer) return;
  bindRxCropZoom(viewer);

  const isPickupProof = /pickup/i.test(String(title || '')) || /order-pickup-proof/i.test(String(url || ''));
  const headingText = String(title || '').trim() || (isPickupProof ? 'Proof of pickup' : 'Prescription preview');
  if (heading) heading.textContent = headingText;
  if (zoom) zoom.setAttribute('aria-label', 'Zoom ' + headingText.toLowerCase());
  if (closeBackdrop) closeBackdrop.setAttribute('aria-label', 'Close ' + headingText.toLowerCase());
  if (image) image.alt = isPickupProof ? 'Proof of pickup' : 'Uploaded prescription';
  if (frame) frame.title = isPickupProof ? 'Proof of pickup' : 'Uploaded prescription';

  const src = String(url || '').trim();
  const showMissing = function (message) {
    if (image) {
      image.hidden = true;
    }
    if (frame) {
      frame.hidden = true;
    }
    if (missing) {
      missing.textContent = message || (isPickupProof
        ? 'The pickup proof could not be loaded.'
        : 'The uploaded prescription could not be loaded.');
      missing.hidden = false;
    }
    resetRxCropZoom(viewer, { empty: true });
  };

  if (!src) {
    showMissing(isPickupProof
      ? 'No pickup proof file is available.'
      : 'No prescription file was saved for this order.');
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
  allOrders: [],
  allLoaded: false,
  selectedId: 0,
  details: {},
  tabs: {},
};
let pharmacyOrdersRefreshTimer = null;
let pharmacyOrdersRefreshWait = null;

function ordersHref(status, orderId) {
  let href = 'index.php?view=orders&status=' + encodeURIComponent(status || 'pending');
  if (orderId) href += '&order_id=' + encodeURIComponent(String(orderId));
  return href;
}

function rememberOrdersTab(status, orders) {
  pharmacyOrdersState.tabs[status] = { orders: (orders || []).slice() };
}

function ordersForStatus(status) {
  const all = pharmacyOrdersState.allOrders || [];
  if (!status || status === 'all') return all.slice();
  return all.filter(function (row) { return String(row.status || '') === String(status); });
}

function rememberDetailsFromOrders(orders) {
  (orders || []).forEach(function (row) {
    if (row && row.detail && row.id) {
      pharmacyOrdersState.details[row.id] = row.detail;
    }
  });
}

function showOrdersForStatus(status) {
  pharmacyOrdersState.status = status;
  if (pharmacyOrdersState.allLoaded) {
    pharmacyOrdersState.orders = ordersForStatus(status);
    rememberOrdersTab(status, pharmacyOrdersState.orders);
  } else {
    const cached = pharmacyOrdersState.tabs[status];
    if (!cached) return false;
    pharmacyOrdersState.orders = cached.orders.slice();
  }
  pharmacyOrdersState.selectedId = 0;
  renderPharmacyOrders();
  return true;
}

function showCachedOrdersTab(status) {
  return showOrdersForStatus(status);
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
  pharmacyOrdersState.counts = Object.assign({}, pharmacyOrdersState.counts, payload.counts || {});
  pharmacyOrdersState.total = Number(payload.total || 0);
  const incoming = Array.isArray(payload.orders) ? payload.orders : [];
  rememberDetailsFromOrders(incoming);
  rememberOrdersTab(nextStatus, incoming);
  if (nextStatus === 'all') {
    pharmacyOrdersState.allOrders = incoming.slice();
    pharmacyOrdersState.allLoaded = true;
    pharmacyOrdersState.status = pharmacyOrdersState.status || 'pending';
    pharmacyOrdersState.orders = ordersForStatus(pharmacyOrdersState.status);
    rememberOrdersTab(pharmacyOrdersState.status, pharmacyOrdersState.orders);
    return;
  }
  incoming.forEach(function (row) {
    const orderId = Number(row.id || 0);
    const index = pharmacyOrdersState.allOrders.findIndex(function (item) { return Number(item.id) === orderId; });
    if (index >= 0) pharmacyOrdersState.allOrders[index] = row;
    else pharmacyOrdersState.allOrders.push(row);
  });
  pharmacyOrdersState.status = nextStatus;
  pharmacyOrdersState.orders = incoming.slice();
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
  const order = (pharmacyOrdersState.allOrders || []).find(function (row) { return Number(row.id) === Number(orderId); })
    || (pharmacyOrdersState.orders || []).find(function (row) { return Number(row.id) === Number(orderId); });
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
  if (order) {
    const allIndex = pharmacyOrdersState.allOrders.findIndex(function (row) { return Number(row.id) === Number(orderId); });
    if (allIndex >= 0) pharmacyOrdersState.allOrders[allIndex] = order;
    else pharmacyOrdersState.allOrders.unshift(order);
  }
  Object.keys(pharmacyOrdersState.tabs).forEach(function (tabStatus) {
    upsertCachedTabOrder(tabStatus, order);
  });
  if (pharmacyOrdersState.allLoaded) {
    pharmacyOrdersState.orders = ordersForStatus(pharmacyOrdersState.status);
    rememberOrdersTab(pharmacyOrdersState.status, pharmacyOrdersState.orders);
  } else {
    const currentTab = pharmacyOrdersState.tabs[pharmacyOrdersState.status];
    if (currentTab) {
      pharmacyOrdersState.orders = currentTab.orders.slice();
    } else if (pharmacyOrdersState.status !== 'all' && pharmacyOrdersState.status !== nextStatus) {
      pharmacyOrdersState.orders = (pharmacyOrdersState.orders || []).filter(function (row) {
        return Number(row.id) !== Number(orderId);
      });
    }
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

function pharmacyPaymentReceiptHtml(detail) {
  const confirmed = !!detail.payment_confirmed || Number(detail.paid_percent || 0) > 0;
  const fullyPaid = !!detail.is_completed;
  const title = confirmed || fullyPaid ? 'Payment Successful' : 'Payment Pending';
  const status = confirmed || fullyPaid ? 'Paid' : 'Pending';
  const method = 'GCash';
  const timeLabel = escapeHtml(detail.time_label || '');
  const dateLabel = escapeHtml(detail.date_label || detail.created_label || '');
  const stamp = [timeLabel, dateLabel].filter(Boolean).join(', ');
  const note = escapeHtml(String(detail.payment_note || '').replace(/^✓\s*/, ''));
  return (
    '<div class="order-receipt">' +
      '<header class="order-receipt-head">' +
        '<span class="order-receipt-mark" aria-hidden="true">' +
          '<svg viewBox="0 0 72 56" fill="none">' +
            '<path d="M28 8h26v36l-3.2-2.2-3.2 2.2-3.2-2.2-3.2 2.2-3.2-2.2-3.2 2.2-3.4-2.2-3.4 2.2V8Z" fill="#fff" stroke="#2563eb" stroke-width="2.2"/>' +
            '<path d="M18 14h26v36l-3.2-2.2-3.2 2.2-3.2-2.2-3.2 2.2-3.2-2.2-3.2 2.2-3.4-2.2-3.4 2.2V14Z" fill="#fff" stroke="#2563eb" stroke-width="2.2"/>' +
            '<circle cx="31" cy="32" r="9" fill="#22c55e"/>' +
            '<path d="m27.2 32.2 2.4 2.4 5.2-5.4" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>' +
          '</svg>' +
        '</span>' +
        '<h3>' + title + '</h3>' +
      '</header>' +
      '<div class="order-receipt-perforation" aria-hidden="true"></div>' +
      '<div class="order-receipt-body">' +
        '<h4>Payment Details</h4>' +
        '<div class="order-receipt-row"><span>Order Number</span><i>:</i><b>' + escapeHtml(detail.order_number || '') + '</b></div>' +
        '<div class="order-receipt-row"><span>Order Time</span><i>:</i><b>' + stamp + '</b></div>' +
        '<div class="order-receipt-row"><span>Payment Method</span><i>:</i><b>' + method + '<small>' + (confirmed ? 'Payment confirmed via PayMongo' : 'PayMongo GCash checkout') + '</small></b></div>' +
        '<div class="order-receipt-row"><span>Payment Status</span><i>:</i><b><span class="order-receipt-pill' + (confirmed || fullyPaid ? '' : ' is-pending') + '">' + status + '</span></b></div>' +
        '<div class="order-receipt-row"><span>Amount</span><i>:</i><b>' + escapeHtml(detail.paid_label || '') + '</b></div>' +
        '<div class="order-receipt-row"><span>VAT (' + Number(detail.vat_percent || 15) + '%)</span><i>:</i><b>' + escapeHtml(detail.vat_label || '') + '</b></div>' +
        '<div class="order-receipt-row order-receipt-total"><span>Total Amount</span><i>:</i><b>' + escapeHtml(detail.total_label || '') + '</b></div>' +
        '<div class="order-receipt-row"><span>Paid (' + Number(detail.paid_percent || 0) + '%)<small>' + Number(detail.paid_note_percent || 0) + '% · paid via GCash</small></span><i>:</i><b class="is-paid">' + escapeHtml(detail.paid_label || '') + '</b></div>' +
        '<div class="order-receipt-row"><span>Remaining Balance (' + Number(detail.balance_percent || 0) + '%)<small>' + (fullyPaid ? 'Paid in full' : 'Pay at pickup') + '</small></span><i>:</i><b>' + escapeHtml(detail.balance_label || '') + '</b></div>' +
        '<p class="order-receipt-note">' + note + '</p>' +
      '</div>' +
    '</div>'
  );
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

function setOrdersDetailMode(on) {
  const view = document.getElementById('view-orders');
  if (view) view.classList.toggle('has-order-detail', !!on);
}

function orderBackHtml(closeHref) {
  return '<div class="panel-head"><a class="order-drawer-close-btn" id="order-drawer-close-btn" href="' + closeHref + '">' +
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg> Back to Orders</a></div>';
}

function renderOrderDrawerDetail(detail) {
  const drawer = document.getElementById('order-drawer');
  if (!drawer || !detail) return;
  const status = pharmacyOrdersState.status;
  const closeHref = ordersHref(status);
  const canViewRx = detail.can_view_prescription !== false && !detail.is_completed && !detail.is_cancelled;
  const itemCount = (detail.items || []).length;
  const itemsHtml = (detail.items || []).map(function (item) {
    const rxUrl = String(item.prescription_url || detail.prescription_url || '').trim();
    const rx = item.prescription_required
      ? (canViewRx && rxUrl
        ? '<small><button type="button" class="order-prescription-inline view-prescription-trigger" data-prescription-url="' + escapeHtml(rxUrl) + '">Rx required · see prescription ›</button></small>'
        : '<small>Rx required</small>')
      : '';
    const note = item.note ? '<small class="mo-item-note">Note: ' + escapeHtml(item.note) + '</small>' : '';
    const thumb = item.image_url
      ? '<img src="' + escapeHtml(item.image_url) + '" alt="">'
      : '<span class="ph-order-item-thumb" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></span>';
    return '<div class="ph-order-item">' + thumb +
      '<div><b>' + escapeHtml(item.name || 'Medicine') + '</b>' +
      '<small>Qty: ' + Number(item.quantity || 1) + '</small>' + rx + note + '</div>' +
      '<strong>' + escapeHtml(item.line_total_label || '') + '</strong></div>';
  }).join('');

  let actions = '';
  if (detail.is_pending) {
    actions = '<div class="ph-order-head-actions">' +
      '<button type="button" class="btn btn-danger order-cancel-btn" id="cancel-order-btn" data-order-id="' + detail.id + '" data-order-number="' + escapeHtml(detail.order_number) + '" data-customer="' + escapeHtml(detail.customer_name) + '">Cancel order</button>' +
      '<button type="button" class="btn btn-accent order-confirm-btn" id="confirm-order-btn" data-order-id="' + detail.id + '" data-next-status="confirmed" data-next-label="Confirmed">Confirm order</button>' +
      '</div>';
  } else if (detail.next_status) {
    actions = '<div class="ph-order-head-actions">' +
      '<button type="button" class="btn btn-accent order-update-status-btn" id="update-order-status-btn" data-order-id="' + detail.id + '" data-next-status="' + escapeHtml(detail.next_status) + '" data-next-label="' + escapeHtml(detail.next_label) + '">Update status</button>' +
      '</div>';
  } else if (detail.is_ready) {
    actions = '<div class="ph-order-head-actions">' +
      '<button type="button" class="btn btn-accent order-complete-btn" id="complete-order-btn" data-order-id="' + detail.id + '" data-order-number="' + escapeHtml(detail.order_number) + '" data-customer="' + escapeHtml(detail.customer_name) + '" data-has-proof="' + (detail.has_pickup_proof ? '1' : '0') + '">Mark as complete</button>' +
      '</div>';
  }

  let extra = '';
  if (detail.is_completed && detail.has_pickup_proof) {
    const proofUrl = escapeHtml(detail.pickup_proof_url || '');
    const isPdf = String(detail.pickup_proof_kind || '') === 'pdf';
    extra += '<button type="button" class="ph-pickup-proof view-prescription-trigger" data-prescription-url="' + proofUrl + '" data-preview-title="Proof of pickup">' +
      '<span class="ph-pickup-proof-thumb' + (isPdf ? ' is-pdf' : '') + '">' + (isPdf ? 'PDF' : '<img src="' + proofUrl + '" alt="Pickup proof">') + '</span>' +
      '<span><strong>Pickup completed</strong><small>Photo of completed pickup</small></span>' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>';
  }
  const cancelReason = detail.is_cancelled
    ? '<p class="mo-cancel-reason"><strong>Cancellation reason</strong><span>' + escapeHtml(detail.cancellation_reason || 'No reason recorded.') + '</span></p>'
    : '';

  const overview = drawer.querySelector('.order-overview');
  if (!overview) return;
  overview.innerHTML =
    orderBackHtml(closeHref) +
    '<div class="order-overview-body">' +
    '<div class="ph-order-head"><div>' +
    '<h2 id="order-drawer-title">Order #' + escapeHtml(detail.order_number) + '</h2>' +
    '<p>' + itemCount + ' item' + (itemCount === 1 ? '' : 's') + ' • ' + escapeHtml(detail.total_label || '') + '</p>' +
    '<small><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg> ' +
    escapeHtml(detail.date_label || detail.created_label || '') + (detail.time_label ? ' • ' + escapeHtml(detail.time_label) : '') + '</small>' +
    '</div>' + actions + '</div>' +
    cancelReason +
    '<div class="ph-order-layout">' +
    '<main class="order-overview-main">' +
    orderProgressHtml(detail.status, detail.created_stamp || detail.created_label || '') +
    '<article class="ph-customer-card"><header class="ph-customer-head">' +
    '<div class="ph-customer-logo">' + escapeHtml(detail.customer_initials) + '</div>' +
    '<div><strong>' + escapeHtml(detail.customer_name) + '</strong><small>' + escapeHtml(detail.customer_line) + '</small></div>' +
    '</header><div class="ph-order-items">' + itemsHtml + '</div>' +
    '<div class="ph-order-foot">' +
    '<div class="ph-order-foot-row"><span>VAT (' + Number(detail.vat_percent || 15) + '%)</span><b>' + escapeHtml(detail.vat_label || '') + '</b></div>' +
    '<div class="ph-order-foot-row"><span>Store Subtotal</span><b>' + escapeHtml(detail.total_label || '') + '</b></div>' +
    '</div></article>' + extra +
    '</main>' +
    '<aside class="order-overview-side">' + pharmacyPaymentReceiptHtml(detail) + '</aside>' +
    '</div></div>';
  drawer.hidden = false;
  drawer.classList.remove('is-loading');
  drawer.setAttribute('data-order-id', String(detail.id || 0));
  setOrdersDetailMode(true);
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
  setOrdersDetailMode(true);
  return true;
}

function showOrderDrawerPlaceholder(orderId, fromEl) {
  const drawer = document.getElementById('order-drawer');
  if (!drawer) return;
  const row = fromEl && fromEl.closest ? fromEl.closest('.orders-table-row') : null;
  const customer = ((row && row.querySelector('.ot-customer-name')) ? row.querySelector('.ot-customer-name').textContent : '').trim();
  const closeHref = 'index.php?view=orders&status=' + encodeURIComponent(currentOrdersStatus());
  drawer.hidden = false;
  drawer.classList.add('is-loading');
  drawer.setAttribute('data-order-id', String(orderId));
  setOrdersDetailMode(true);
  const overview = drawer.querySelector('.order-overview');
  if (!overview) return;
  overview.innerHTML =
    orderBackHtml(closeHref) +
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
  setOrdersDetailMode(false);
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
  if (pharmacyOrdersState.allLoaded) {
    showOrdersForStatus(parsed.status);
    return;
  }
  if (showCachedOrdersTab(parsed.status)) {
    refreshPharmacyOrders();
    return;
  }
  pharmacyOrdersState.status = parsed.status;
  showOrdersTableLoading();
  refreshPharmacyOrders().then(function () {
    if (pharmacyOrdersState.allLoaded) {
      showOrdersForStatus(parsed.status);
      return;
    }
    loadOrdersView(parsed.nextUrl, { skipHistory: true, navGen: ++ordersNavGen });
  });
}

function syncOpenOrderDetail() {
  const selectedId = pharmacyOrdersState.selectedId;
  const drawer = document.getElementById('order-drawer');
  if (!drawer || drawer.hidden || selectedId < 1) return;
  const stillThere = (pharmacyOrdersState.allOrders || pharmacyOrdersState.orders || []).some(function (row) {
    return Number(row.id) === Number(selectedId);
  });
  if (stillThere && pharmacyOrdersState.details[selectedId]) {
    renderOrderDrawerDetail(pharmacyOrdersState.details[selectedId]);
    return;
  }
  if (!stillThere) {
    closeOrderDrawer(ordersHref(pharmacyOrdersState.status), { replace: true });
  }
}

async function refreshPharmacyOrders() {
  if (pharmacyOrdersRefreshWait) return pharmacyOrdersRefreshWait;
  if (isAnyOrderModalOpen()) return;
  pharmacyOrdersRefreshWait = (async function () {
    const current = pharmacyOrdersState.status;
    const selectedId = pharmacyOrdersState.selectedId;
    try {
      const response = await fetch('api/orders-list.php?status=all', {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) throw new Error('load');
      const payload = await response.json();
      if (!payload || payload.success === false || !Array.isArray(payload.orders)) return;
      const previous = JSON.stringify({
        orders: pharmacyOrdersState.allOrders,
        counts: pharmacyOrdersState.counts,
        total: pharmacyOrdersState.total,
      });
      const next = JSON.stringify({
        orders: payload.orders,
        counts: payload.counts || {},
        total: Number(payload.total || 0),
      });
      if (previous === next && pharmacyOrdersState.allLoaded) return;
      applyOrdersPayload(payload, 'all');
      pharmacyOrdersState.status = current;
      pharmacyOrdersState.orders = ordersForStatus(current);
      pharmacyOrdersState.selectedId = selectedId;
      renderPharmacyOrders();
      syncOpenOrderDetail();
    } catch (error) {
    }
  })().finally(function () {
    pharmacyOrdersRefreshWait = null;
  });
  return pharmacyOrdersRefreshWait;
}

function refreshCachedOrderTabs() {
  return refreshPharmacyOrders();
}

function startPharmacyOrdersRefresh() {
  if (pharmacyOrdersRefreshTimer) return;
  refreshPharmacyOrders();
  pharmacyOrdersRefreshTimer = setInterval(function () {
    if (!document.hidden) refreshPharmacyOrders();
  }, 2500);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) refreshPharmacyOrders();
  });
}

window.refreshPharmacyOrders = refreshPharmacyOrders;

function stayOnOrdersTable() {
  const status = currentOrdersStatus();
  const href = ordersHref(status);
  closeOrderDrawer(href, { replace: true });
  if (pharmacyOrdersState.allLoaded) {
    pharmacyOrdersState.orders = ordersForStatus(status);
  }
  renderPharmacyOrders();
  refreshPharmacyOrders();
}

function navigateOrdersLink(link) {
  const href = link.getAttribute('href');
  if (!href) return;
  if (link.classList.contains('tab-btn')) {
    switchOrdersTab(href);
    return;
  }
  if (link.classList.contains('order-drawer-close-btn') || link.id === 'order-drawer-close' || link.id === 'order-update-cancel-btn') {
    const close = document.getElementById('order-drawer-close-btn');
    closeOrderDrawer((close && close.getAttribute('href')) || href);
    return;
  }
  openOrderFromLink(href, {}, link);
}

async function submitOrderStatusAdvance(button) {
  if (!button || button.disabled || button.dataset.updating === '1') return;

  const orderId = parseInt(button.dataset.orderId || '0', 10);
  const nextStatus = button.dataset.nextStatus || '';
  if (!orderId || !nextStatus) return;

  const originalLabel = button.textContent;
  button.dataset.updating = '1';
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
      button.dataset.updating = '0';
      button.textContent = originalLabel;
      return;
    }

    applyOrderStatusLocally(orderId, result.status || nextStatus);
    stayOnOrdersTable();
  } catch (error) {
    window.alert('Could not update order status. Please try again.');
    button.disabled = false;
    button.dataset.updating = '0';
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

    const closeEl = event.target.closest('#order-drawer-close, #order-drawer-close-btn, #order-update-cancel-btn');
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
      openPrescriptionViewer(
        rx.getAttribute('data-prescription-url') || '',
        rx.getAttribute('data-preview-title') || ''
      );
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
  startPharmacyOrdersRefresh();
  const selectedId = parseInt(new URLSearchParams(window.location.search).get('order_id') || '0', 10);
  if (selectedId > 0 && pharmacyOrdersState.details[selectedId]) {
    pharmacyOrdersState.selectedId = selectedId;
    renderOrderDrawerDetail(pharmacyOrdersState.details[selectedId]);
  }
});
document.addEventListener('livesync:applied', function () {
  initPharmacyOrderUi();
  refreshPharmacyOrders();
});
document.addEventListener('livesync:change', function (event) {
  const keys = (event.detail && event.detail.keys) || [];
  if (keys.indexOf('orders') === -1) return;
  refreshPharmacyOrders();
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
