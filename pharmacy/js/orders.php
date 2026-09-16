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

function openPrescriptionViewer(url) {
  const viewer = document.getElementById('prescription-viewer');
  const image = document.getElementById('prescription-viewer-image');
  if (!viewer || !image || !url) return;

  image.src = url;
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

function ordersViewUrl(href) {
  try {
    const url = new URL(href, window.location.href);
    return url.pathname + url.search;
  } catch (error) {
    return href || '';
  }
}

async function loadOrdersView(href, options) {
  options = options || {};
  const nextUrl = ordersViewUrl(href);
  if (!nextUrl) return;
  if (!options.force && !options.skipHistory && (window.location.pathname + window.location.search) === nextUrl) {
    return;
  }
  if (loadOrdersView.inFlight) return;
  loadOrdersView.inFlight = true;

  try {
    const response = await fetch(nextUrl, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'X-Live-Sync': '1',
        Accept: 'text/html',
      },
    });
    if (!response.ok) throw new Error('load');

    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const incoming = doc.getElementById('view-orders');
    const current = document.getElementById('view-orders');
    if (!incoming || !current) {
      window.location.href = nextUrl;
      return;
    }

    incoming.classList.toggle('active', current.classList.contains('active'));
    current.replaceWith(incoming);

    if (!options.skipHistory) {
      const currentUrl = window.location.pathname + window.location.search;
      if (currentUrl !== nextUrl) {
        const state = { view: 'orders' };
        if (options.replace) history.replaceState(state, '', nextUrl);
        else history.pushState(state, '', nextUrl);
      }
    }

    const view = document.getElementById('view-orders');
    if (view) delete view.dataset.orderUiBound;
    initPharmacyOrderUi();
  } catch (error) {
    window.location.href = nextUrl;
  } finally {
    loadOrdersView.inFlight = false;
  }
}

function navigateOrdersLink(link) {
  const href = link.getAttribute('href');
  if (!href) return;
  loadOrdersView(href);
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

    const status = result.status || nextStatus;
    loadOrdersView('index.php?view=orders&status=' + encodeURIComponent(status) + '&order_id=' + orderId, { replace: true, force: true });
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

  loadOrdersView('index.php?view=orders&status=delivered&order_id=' + orderId, { replace: true, force: true });
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

function initPharmacyOrderUi() {
  const view = document.getElementById('view-orders');
  if (!view || view.dataset.orderUiBound === '1') return;
  view.dataset.orderUiBound = '1';
  document.querySelectorAll('#view-orders .tabs a.tab-btn, #view-orders a.orders-table-link, #view-orders a.order-drawer-close-btn').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      navigateOrdersLink(link);
    });
  });
  document.getElementById('order-drawer-close')?.addEventListener('click', function () {
    const close = document.getElementById('order-drawer-close-btn');
    if (close) navigateOrdersLink(close);
  });

  document.querySelectorAll('.view-prescription-trigger').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      openPrescriptionViewer(trigger.getAttribute('data-prescription-url') || '');
    });
  });

  document.getElementById('prescription-viewer-close')?.addEventListener('click', closePrescriptionViewer);
  document.getElementById('prescription-viewer-close-btn')?.addEventListener('click', closePrescriptionViewer);
  document.getElementById('pickup-proof-modal-close')?.addEventListener('click', closePickupProofModal);
  document.getElementById('pickup-proof-modal-close-btn')?.addEventListener('click', closePickupProofModal);
  document.getElementById('pickup-proof-cancel-btn')?.addEventListener('click', closePickupProofModal);
  document.getElementById('cancel-order-modal-close')?.addEventListener('click', closeCancelOrderModal);
  document.getElementById('cancel-order-modal-close-btn')?.addEventListener('click', closeCancelOrderModal);
  document.getElementById('cancel-order-dismiss-btn')?.addEventListener('click', closeCancelOrderModal);

  const cancelReasonSelect = document.getElementById('cancel-reason-select');
  if (cancelReasonSelect) {
    cancelReasonSelect.addEventListener('change', function () {
      setCancelOrderError('');
      toggleCancelOtherField();
    });
  }

  const cancelReasonOtherInput = document.getElementById('cancel-reason-other-input');
  if (cancelReasonOtherInput) {
    cancelReasonOtherInput.addEventListener('input', function () {
      setCancelOrderError('');
    });
  }

  const cancelOrderSubmitBtn = document.getElementById('cancel-order-submit-btn');
  if (cancelOrderSubmitBtn) {
    cancelOrderSubmitBtn.addEventListener('click', async function () {
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

        loadOrdersView('index.php?view=orders&status=cancelled&order_id=' + orderId, { replace: true, force: true });
      } catch (error) {
        setCancelOrderError('Could not cancel order. Please try again.');
        cancelOrderSubmitBtn.disabled = false;
        cancelOrderSubmitBtn.textContent = 'Cancel order';
      }
    });
  }

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

  const pickupProofInput = document.getElementById('pickup-proof-input');
  if (pickupProofInput) {
    pickupProofInput.addEventListener('change', function () {
      const file = pickupProofInput.files && pickupProofInput.files[0];
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
    });
  }

  const pickupProofSubmitBtn = document.getElementById('pickup-proof-submit-btn');
  if (pickupProofSubmitBtn) {
    pickupProofSubmitBtn.addEventListener('click', async function () {
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
      } catch (error) {
        setPickupProofError(error.message || 'Could not complete this order.');
        pickupProofSubmitBtn.disabled = false;
        pickupProofSubmitBtn.textContent = 'Mark as complete';
        updatePickupProofSubmitState();
      }
    });
  }

  const updateStatusBtn = document.getElementById('update-order-status-btn');
  if (updateStatusBtn) {
    updateStatusBtn.addEventListener('click', function () {
      submitOrderStatusAdvance(updateStatusBtn);
    });
  }

  const confirmOrderBtn = document.getElementById('confirm-order-btn');
  if (confirmOrderBtn) {
    confirmOrderBtn.addEventListener('click', function () {
      submitOrderStatusAdvance(confirmOrderBtn);
    });
  }

  const completeBtn = document.getElementById('complete-order-btn');
  if (completeBtn) {
    completeBtn.addEventListener('click', function () {
      const orderId = parseInt(completeBtn.dataset.orderId || '0', 10);
      if (!orderId) return;

      openPickupProofModal(
        orderId,
        completeBtn.dataset.orderNumber || '',
        completeBtn.dataset.customer || '',
        completeBtn.dataset.hasProof || '0'
      );
    });
  }

  const cancelBtn = document.getElementById('cancel-order-btn');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      const orderId = parseInt(cancelBtn.dataset.orderId || '0', 10);
      if (!orderId) return;

      openCancelOrderModal(
        orderId,
        cancelBtn.dataset.orderNumber || '',
        cancelBtn.dataset.customer || ''
      );
    });
  }
}

document.addEventListener('DOMContentLoaded', initPharmacyOrderUi);
document.addEventListener('livesync:applied', function () {
  const view = document.getElementById('view-orders');
  if (view) delete view.dataset.orderUiBound;
  initPharmacyOrderUi();
});
window.addEventListener('popstate', function () {
  const params = new URLSearchParams(window.location.search);
  if ((params.get('view') || '') !== 'orders') return;
  loadOrdersView(window.location.pathname + window.location.search, { skipHistory: true });
});
