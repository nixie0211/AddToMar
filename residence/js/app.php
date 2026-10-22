<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

const pageTitles = {
  dashboard:'Browse', pharmacies:'Partner Pharmacies', 'pharmacy-profile':'Pharmacy Profile', locator:'Pharmacy Locator', browse:'Browse Medicines', cart:'My Cart',
  checkout:'Checkout', tracking:'Order Tracking', notifications:'Notifications', orders:'My Orders', profile:'Profile'
};

function logout(){
  window.location.href = window.LOGOUT_URL || '../logout.php';
}

function toggleProfileMenu(event){
  event?.stopPropagation();
  closeCategoryMenus();
  const menu = document.getElementById('topbar-profile-menu');
  if(!menu) return;
  const dropdown = menu.querySelector('.topbar-profile-dropdown');
  const open = dropdown?.hidden !== false;
  if(dropdown) dropdown.hidden = !open;
  menu.querySelector('.topbar-profile')?.setAttribute('aria-expanded', String(open));
}

function closeProfileMenu(){
  const menu = document.getElementById('topbar-profile-menu');
  if(!menu) return;
  const dropdown = menu.querySelector('.topbar-profile-dropdown');
  if(dropdown) dropdown.hidden = true;
  menu.querySelector('.topbar-profile')?.setAttribute('aria-expanded', 'false');
}

function toggleNotificationMenu(event){
  event?.stopPropagation();
  const menu = document.getElementById('topbar-notification-menu');
  if(!menu) return;
  const dropdown = menu.querySelector('.topbar-notification-dropdown');
  const open = dropdown?.hidden !== false;
  if(dropdown){
    dropdown.hidden = !open;
    if(open) applyResidentNotificationFilter(dropdown, activeResidentNotificationMode(dropdown));
  }
  menu.querySelector('.topbar-nav-item')?.setAttribute('aria-expanded', String(open));
}

function closeNotificationMenu(){
  const menu = document.getElementById('topbar-notification-menu');
  const dropdown = menu?.querySelector('.topbar-notification-dropdown');
  if(dropdown){
    dropdown.hidden = true;
    dropdown.classList.remove('is-expanded');
    const scroll = dropdown.querySelector('.topbar-notification-scroll');
    if(scroll) scroll.scrollTop = 0;
    const footer = dropdown.querySelector('.topbar-notification-footer');
    if(footer && dropdown.getAttribute('data-has-more') === '1') footer.hidden = false;
  }
  menu?.querySelector('.topbar-nav-item')?.setAttribute('aria-expanded', 'false');
  closeNotificationOptions();
}

function notificationOptionsMenu(){
  return document.querySelector('#topbar-notification-menu .topbar-notification-options-menu');
}

function toggleNotificationOptions(event){
  event?.preventDefault?.();
  event?.stopPropagation?.();
  const menu = notificationOptionsMenu();
  const button = event?.currentTarget || document.querySelector('#topbar-notification-menu .topbar-notification-options');
  if(!menu) return;
  const open = menu.hidden !== false;
  menu.hidden = !open;
  button?.setAttribute('aria-expanded', String(open));
}
window.toggleNotificationOptions = toggleNotificationOptions;

function closeNotificationOptions(){
  const menu = notificationOptionsMenu();
  if(menu) menu.hidden = true;
  document.querySelector('#topbar-notification-menu .topbar-notification-options')?.setAttribute('aria-expanded', 'false');
}

function markAllResidentNotificationsRead(event){
  event?.preventDefault?.();
  event?.stopPropagation?.();
  closeNotificationOptions();
  document.querySelectorAll('.topbar-notification-item.is-unread, .topbar-notification-item.unread, .topbar-notification-item[data-unread="1"], .notif-full.unread').forEach(item => {
    const id = String(item.getAttribute('data-notification-id') || '').trim();
    if(id === '' || id.startsWith('sample-')){
      try{ localStorage.setItem('residence_sample_notif_read_' + (id || 'pickup'), '1'); }catch(e){}
    }
    item.classList.remove('is-unread', 'unread');
    item.classList.add('is-read');
    item.setAttribute('data-unread', '0');
    item.querySelector('.topbar-notification-dot')?.remove();
  });
  document.querySelectorAll('.topbar-notification-dropdown, [data-page="notifications"]').forEach(scope => {
    applyResidentNotificationFilter(scope, activeResidentNotificationMode(scope));
  });
  updateResidentNotifBadge(0);
  const url = window.RESIDENCE_CONFIG?.markNotificationReadUrl;
  if(!url) return;
  const form = new FormData();
  form.append('mark_all', '1');
  fetch(url, { method:'POST', body:form, credentials:'same-origin' })
    .then(response => response.json())
    .then(data => {
      if(data && typeof data.unread === 'number') updateResidentNotifBadge(data.unread);
    })
    .catch(() => {});
}
window.markAllResidentNotificationsRead = markAllResidentNotificationsRead;

function openResidentNotificationsPage(event){
  event?.preventDefault?.();
  event?.stopPropagation?.();
  closeNotificationOptions();
  closeNotificationMenu();
  go('notifications');
  const page = document.querySelector('.page[data-page="notifications"]');
  if(!page) return;
  const tabs = page.querySelectorAll('.topbar-notification-tabs button');
  tabs.forEach((tab, index) => tab.classList.toggle('is-active', index === 0));
  applyResidentNotificationFilter(page, 'all');
}
window.openResidentNotificationsPage = openResidentNotificationsPage;

function expandNotificationHistory(event){
  event?.preventDefault?.();
  event?.stopPropagation?.();
  const dropdown = document.querySelector('#topbar-notification-menu .topbar-notification-dropdown');
  const footer = dropdown?.querySelector('.topbar-notification-footer');
  if(!dropdown) return;
  dropdown.classList.add('is-expanded');
  if(footer) footer.hidden = true;
  applyResidentNotificationFilter(dropdown, activeResidentNotificationMode(dropdown));
}
window.expandNotificationHistory = expandNotificationHistory;

function openResidentReport(reportId){
  closeNotificationMenu();
  const report = (window.RESIDENCE_CONFIG?.reports || []).find(item => String(item.id) === String(reportId));
  const root = document.getElementById('resident-report-floating-panel');
  if(!root || !report) return;
  const status = String(report.status || 'under_review').replace(/_/g, ' ');
  const reportReason = String(report.reason || '').replace(/^Other:\s*/i, '');
  const isResolved = status.toLowerCase() === 'resolved';
  const submitted = report.created_at ? new Date(report.created_at).toLocaleString() : '—';
  const proof = report.proof_url ? `<button type="button" class="resident-report-proof-link" data-preview-url="${escapeHtml(report.proof_url)}" data-preview-title="Submitted proof">Open submitted proof <span>›</span></button>` : '<span class="resident-report-none">No proof attached</span>';
  root.innerHTML = `<button type="button" class="resident-report-panel-close" onclick="closeResidentReportDetails()" aria-label="Close report details">×</button><div class="resident-report-panel-heading"><span class="resident-report-panel-icon">💊</span><div><p>Pharmacy report</p><h2>${escapeHtml(report.pharmacy_name || 'Pharmacy')}</h2><span class="resident-report-panel-status ${isResolved ? 'is-resolved' : ''}">${escapeHtml(status)}</span></div></div><div class="resident-report-panel-details"><div><span class="resident-report-detail-label">Reason</span><strong>${escapeHtml(reportReason || '—')}</strong></div><div><span class="resident-report-detail-label">Your details</span><strong>${escapeHtml(report.details || 'No additional details provided.')}</strong></div><div><span class="resident-report-detail-label">Submitted</span><strong>${escapeHtml(submitted)}</strong></div><div><span class="resident-report-detail-label">Proof</span>${proof}</div><div class="resident-report-panel-reply"><span class="resident-report-detail-label">Admin reply</span><strong>${escapeHtml(report.admin_note || 'Your report is still being reviewed.')}</strong></div></div><div class="resident-report-panel-footer ${isResolved ? 'is-resolved' : ''}"><b>${isResolved ? '✓ Resolved' : '◷ Under review'}</b><span>${isResolved ? 'This report has been reviewed and marked as resolved.' : 'We will notify you once this report has been reviewed.'}</span></div>`;
  root.hidden = false;
}

function closeResidentReportDetails(){
  const root = document.getElementById('resident-report-floating-panel');
  if(root) root.hidden = true;
}

function openOrderNotification(orderNumber){
  closeNotificationMenu();
  closeResidentReportDetails();
  const id = String(orderNumber || '').trim();
  if(!id){
    go('orders');
    return;
  }
  openReceiptOrderDetails(id);
}
window.openOrderNotification = openOrderNotification;

function currentResidentNotifBadge(){
  const badge = document.getElementById('sb-notif-count');
  if(!badge || badge.hidden) return 0;
  const raw = String(badge.textContent || '').replace('+', '');
  const n = parseInt(raw, 10);
  return Number.isFinite(n) ? n : 0;
}

function updateResidentNotifBadge(unread){
  const badge = document.getElementById('sb-notif-count');
  if(!badge) return;
  unread = Math.max(0, Number(unread) || 0);
  if(unread < 1){
    badge.hidden = true;
    badge.textContent = '0';
    badge.setAttribute('aria-label', '0 unread notifications');
    return;
  }
  badge.hidden = false;
  badge.textContent = unread > 99 ? '99+' : String(unread);
  badge.setAttribute('aria-label', unread + ' unread notifications');
}

function markResidentNotificationRead(item){
  if(!item) return;
  const unread = item.classList.contains('is-unread') || item.classList.contains('unread') || item.getAttribute('data-unread') === '1';
  if(!unread) return;

  const id = String(item.getAttribute('data-notification-id') || '').trim();
  const matches = id
    ? document.querySelectorAll('[data-notification-id="' + id.replace(/"/g, '') + '"]')
    : [item];

  matches.forEach(el => {
    el.classList.remove('is-unread', 'unread');
    el.classList.add('is-read');
    el.setAttribute('data-unread', '0');
    el.querySelector('.topbar-notification-dot')?.remove();
  });

  document.querySelectorAll('.topbar-notification-dropdown, [data-page="notifications"]').forEach(scope => {
    applyResidentNotificationFilter(scope, activeResidentNotificationMode(scope));
  });

  const isSample = id === '' || id.startsWith('sample-');
  if(isSample){
    try{ localStorage.setItem('residence_sample_notif_read_' + (id || 'pickup'), '1'); }catch(e){}
    return;
  }

  updateResidentNotifBadge(currentResidentNotifBadge() - 1);

  const url = window.RESIDENCE_CONFIG?.markNotificationReadUrl;
  if(!url) return;
  const form = new FormData();
  form.append('notification_id', id);
  fetch(url, { method:'POST', body:form, credentials:'same-origin' })
    .then(response => response.json())
    .then(data => {
      if(data && typeof data.unread === 'number') updateResidentNotifBadge(data.unread);
    })
    .catch(() => {});
}

function handleResidentNotificationClick(item){
  if(!item) return;
  markResidentNotificationRead(item);
  const reportId = item.getAttribute('data-report-id');
  if(reportId){
    openResidentReport(reportId);
    return;
  }
  if(item.hasAttribute('data-order-number')){
    openOrderNotification(item.getAttribute('data-order-number') || '');
  }
}

function isResidentNotificationUnread(item){
  return item.classList.contains('is-unread') || item.classList.contains('unread') || item.getAttribute('data-unread') === '1';
}

function activeResidentNotificationMode(scope){
  const active = scope?.querySelector('.topbar-notification-tabs button.is-active');
  return active && active.textContent.trim().toLowerCase() === 'unread' ? 'unread' : 'all';
}

function applyResidentNotificationFilter(scope, mode){
  if(!scope) return;
  const expanded = !scope.classList.contains('topbar-notification-dropdown') || scope.classList.contains('is-expanded');
  const items = scope.querySelectorAll('.topbar-notification-item');
  let visible = 0;
  let hiddenLater = 0;
  items.forEach(item => {
    const unread = isResidentNotificationUnread(item);
    const matchesTab = mode !== 'unread' || unread;
    const later = !expanded && item.classList.contains('is-later');
    const show = matchesTab && !later;
    item.hidden = !show;
    item.style.display = show ? '' : 'none';
    if(show) visible += 1;
    if(matchesTab && later) hiddenLater += 1;
  });
  scope.querySelectorAll('[data-notification-group]').forEach(group => {
    if(!group.classList.contains('topbar-notification-group')) return;
    const groupVisible = Array.from(group.querySelectorAll('.topbar-notification-item')).some(item => !item.hidden);
    group.hidden = !groupVisible;
  });
  const empty = scope.querySelector('[data-notification-empty]');
  if(empty){
    const hasItems = items.length > 0;
    const showEmpty = visible < 1 && hiddenLater < 1 && (mode === 'unread' || !hasItems);
    empty.hidden = !showEmpty;
    empty.style.display = showEmpty ? '' : 'none';
    empty.textContent = mode === 'unread' ? 'No unread notifications' : 'No notifications';
  }
  const footer = scope.querySelector('.topbar-notification-footer');
  if(footer && scope.classList.contains('topbar-notification-dropdown')){
    footer.hidden = expanded || hiddenLater < 1;
  }
}

function filterResidentNotificationTab(mode, button, event){
  event?.preventDefault?.();
  event?.stopPropagation?.();
  const scope = button?.closest('.topbar-notification-dropdown, [data-page="notifications"]') || document.getElementById('topbar-notification-menu');
  if(!scope) return;
  scope.querySelectorAll('.topbar-notification-tabs button').forEach(tab => {
    tab.classList.toggle('is-active', tab === button);
  });
  applyResidentNotificationFilter(scope, mode);
}
window.filterResidentNotificationTab = filterResidentNotificationTab;

let cartCount = 0;
let persistCartTimer = 0;
let cart = emptyCart();
let activePharmacyProfileId = '';
let prescriptionFilesByItem = {};
let activePrescriptionItemKey = '';

function emptyCart(){
  return { pharmacyId:'', pharmacyName:'', pharmacyLabel:'', items:[] };
}

function applyCartPayload(payload){
  const previousNotes = {};
  (cart.items || []).forEach(item => {
    const key = checkoutRxItemKey(item);
    const note = String(item.note || '').trim();
    if(key && note) previousNotes[key] = note;
  });
  const items = Array.isArray(payload?.items) ? payload.items.filter(item => Number(item.medicineId) > 0) : [];
  const first = items[0] || {};
  cart = {
    pharmacyId: payload?.pharmacyId || first.pharmacyId || '',
    pharmacyName: payload?.pharmacyName || first.pharmacyName || '',
    pharmacyLabel: payload?.pharmacyLabel || first.pharmacyLabel || '',
    items: items.map(item => ({
      ...item,
      note: String(item.note || previousNotes[checkoutRxItemKey(item)] || ''),
    })),
  };
  syncCartCount();
}

function cartPersistUrl(){
  return window.RESIDENCE_CONFIG?.cartUrl || '../ajax/resident-cart.php';
}

function cartItemsSignature(items){
  return (items || []).map(item => [
    Number(item.medicineId) || 0,
    String(item.pharmacyId || ''),
    Number(item.quantity) || 0,
    Number(item.price) || 0,
    item.selected === true ? 1 : 0,
    String(item.name || '')
  ].join(':')).join('|');
}

function isResidenceCheckoutActive(){
  return Boolean(document.querySelector('.page[data-page="checkout"].active'));
}

async function fetchResidenceCart(){
  ['addtomar_residence_cart','addtomar_residence_cart_v1','addtomar_residence_cart_v2','addtomar_residence_cart_v3','addtomar_residence_cart_v4'].forEach(key => {
    try{ localStorage.removeItem(key); }catch(e){}
  });
  if(isResidenceCheckoutActive()) return;
  const previous = cartItemsSignature(cart.items);
  try{
    const response = await fetch(cartPersistUrl());
    const data = await response.json();
    if(data?.ok){
      applyCartPayload(data);
      const next = cartItemsSignature(cart.items);
      if(previous === next) return;
      renderCartPage();
      if(typeof renderCheckoutPage === 'function') renderCheckoutPage();
    }
  }catch(e){}
}

function saveCart(){
  cart.items = (cart.items || []).filter(item => Number(item.medicineId) > 0 && item.pharmacyId);
  if(cart.items.length === 0){
    cart.pharmacyId = '';
    cart.pharmacyName = '';
    cart.pharmacyLabel = '';
  }
  syncCartCount();
  clearTimeout(persistCartTimer);
  persistCartTimer = setTimeout(flushResidenceCart, 200);
}

function flushResidenceCart(){
  const form = new FormData();
  form.append('items', JSON.stringify((cart.items || []).map(item => ({
    medicine_id: Number(item.medicineId) || 0,
    pharmacy_id: item.pharmacyId || '',
    quantity: Math.max(1, Number(item.quantity) || 1),
    selected: item.selected === true,
  }))));
  fetch(cartPersistUrl(), { method:'POST', body: form }).catch(() => {});
}

function clearCart(){
  cart = emptyCart();
  saveCart();
  renderCartPage();
  renderCheckoutPage();
}

function formatMoney(amount){
  return '₱' + Number(amount || 0).toFixed(2);
}

const CHECKOUT_VAT_RATE = 0.15;
const CHECKOUT_DOWN_RATE = 0.6;

function roundMoney(amount){
  return Math.round((Number(amount) || 0) * 100) / 100;
}

function checkoutPriceTotals(subtotal){
  const items = roundMoney(subtotal);
  const vat = roundMoney(items * CHECKOUT_VAT_RATE);
  const total = roundMoney(items + vat);
  const downPayment = roundMoney(total * CHECKOUT_DOWN_RATE);
  return {
    subtotal: items,
    vat,
    total,
    downPayment,
    remaining: roundMoney(total - downPayment)
  };
}

function cartMedicineFallbackImage(){
  return 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80"><rect width="80" height="80" rx="12" fill="#e8f4ef"/><path d="M28 40h24M40 28v24" stroke="#0a6b54" stroke-width="4" stroke-linecap="round"/></svg>');
}

function medicineImageSrc(item){
  return item?.image || cartMedicineFallbackImage();
}

function escapeHtml(value){
  return String(value ?? '').replace(/[&<>"']/g, ch => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[ch]));
}

function getMarketplacePharmacy(pharmacyId){
  const list = window.RESIDENCE_CONFIG?.marketplacePharmacies || [];
  return list.find(pharmacy => String(pharmacy.id) === String(pharmacyId)) || null;
}

function pharmacyTimeLabel(value){
  const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
  if(!match) return '—';
  let hour = Number(match[1]);
  const minutes = match[2];
  const suffix = hour >= 12 ? 'PM' : 'AM';
  hour = hour % 12 || 12;
  return `${hour}:${minutes} ${suffix}`;
}

function toggleProductDetailInfo(button, contentId){
  const content = document.getElementById(contentId);
  if(!content) return;
  const isExpanded = button.getAttribute('aria-expanded') === 'true';
  button.setAttribute('aria-expanded', String(!isExpanded));
  content.hidden = isExpanded;
}

function pharmacyAreaKey(pharmacy){
  const blob = [
    pharmacy?.city,
    pharmacy?.branch,
    pharmacy?.address,
    pharmacy?.label,
    pharmacy?.name,
  ].join(' ').toLowerCase();
  if(blob.includes('laoag') || blob.includes('castro street') || blob.includes('m.g. castro')) return 'laoag';
  if(blob.includes('san nicolas') || blob.includes('sannicolas')) return 'san-nicolas';
  if(blob.includes('batac')) return 'batac';
  const lat = Number(pharmacy?.latitude);
  if(!Number.isFinite(lat)) return '';
  if(lat >= 18.185) return 'laoag';
  if(lat >= 18.12) return 'san-nicolas';
  if(lat >= 18.02) return 'batac';
  return '';
}

function applyPharmacyCityBanner(pharmacy){
  const banner = document.getElementById('pharmacy-profile-banner');
  if(!banner) return;
  const cfg = window.RESIDENCE_CONFIG || {};
  const urls = {
    laoag: String(banner.dataset.laoagSrc || cfg.laoagCityBannerUrl || '').trim(),
    'san-nicolas': String(banner.dataset.sanNicolasSrc || cfg.sanNicolasBannerUrl || '').trim(),
    batac: String(banner.dataset.batacSrc || cfg.batacCityBannerUrl || '').trim(),
  };
  const url = urls[pharmacyAreaKey(pharmacy)] || '';
  if(url){
    banner.classList.add('is-city-photo');
    banner.style.backgroundImage = `url("${url}")`;
    return;
  }
  banner.classList.remove('is-city-photo');
  banner.style.backgroundImage = '';
}

function openPharmacyProfile(pharmacyId){
  const pharmacy = getMarketplacePharmacy(pharmacyId);
  if(!pharmacy) return;
  activePharmacyProfileId = pharmacyId;
  applyPharmacyCityBanner(pharmacy);

  document.getElementById('pharmacy-profile-name').textContent = pharmacy.name || 'Pharmacy';
  document.getElementById('pharmacy-profile-contact').textContent = formatPharmacyContact(pharmacy.contact) || 'Contact information unavailable';
  document.getElementById('pharmacy-profile-address').textContent = pharmacy.address || 'Address unavailable';
  const distance = Number(pharmacy.distance_km);
  const logo = document.getElementById('pharmacy-profile-logo');
  logo.src = pharmacy.logo_url || '../2.png';
  logo.alt = pharmacy.name || 'Pharmacy logo';

  const isOpen = Boolean(pharmacy.is_open);
  const totalSold = Number(pharmacy.total_sold || 0);
  const distanceLabel = Number.isFinite(distance) && distance < 900 ? `${distance.toFixed(1)} km away` : 'Distance unavailable';
  document.getElementById('pharmacy-profile-status').innerHTML = `<span class="profile-state ${isOpen ? 'is-open' : ''}"><i></i>${isOpen ? 'Open now' : 'Currently closed'}</span><span class="pharmacy-profile-status-distance">${distanceLabel}</span>`;
  document.getElementById('pharmacy-profile-sold').textContent = `${totalSold.toLocaleString()} sold`;
  pharmacyProfilePage = 1;
  pharmacyProfileCategory = '';
  pharmacyProfilePriceSort = '';
  pharmacyProfilePriceMin = null;
  pharmacyProfilePriceMax = null;
  const categorySelect = document.getElementById('pharmacy-profile-category-sort');
  if(categorySelect) categorySelect.value = '';
  const priceSort = document.getElementById('pharmacy-profile-price-sort');
  if(priceSort) priceSort.value = '';
  const priceMin = document.getElementById('pharmacy-profile-price-min');
  const priceMax = document.getElementById('pharmacy-profile-price-max');
  if(priceMin) priceMin.value = '';
  if(priceMax) priceMax.value = '';
  showPharmacyProfileHome();

  go('pharmacy-profile');
}

function viewAllPharmacyProducts(pharmacyId){
  catalogCollection = '';
  const id = String(pharmacyId || document.querySelector('.home-nearest-pharmacy-bar[data-pharmacy-id]')?.dataset.pharmacyId || '').trim();
  if(id){
    openPharmacyProfile(id);
    return;
  }
  go('dashboard');
  document.getElementById('home-browse')?.scrollIntoView({ behavior:'smooth', block:'start' });
}

function viewAllCatalogCollection(mode){
  const next = mode === 'new' || mode === 'top' || mode === 'featured' ? mode : '';
  catalogCollection = next;
  activePharmacyFilter = '';
  activePharmacyName = '';
  activePharmacyAccountId = '';
  activeShopCategory = '';
  activeShopSub = '';
  const input = document.getElementById('medicine-search');
  if(input) input.value = '';
  closeSearchSuggestions();
  updateCategoryNavState();
  updateStoreBrowseView();
  go('dashboard');
  filterMedicines('');
  document.getElementById('home-browse')?.scrollIntoView({ behavior:'smooth', block:'start' });
}

function reportPharmacy(){
  const pharmacy = getMarketplacePharmacy(activePharmacyProfileId);
  const modal = document.getElementById('pharmacy-report-modal');
  const form = document.getElementById('pharmacy-report-form');
  if(!modal || !form || !pharmacy) return;
  form.reset();
  updatePharmacyReportFileName({ files: [] });
  bindPharmacyReportDrop();
  document.getElementById('pharmacy-report-pharmacy-id').value = pharmacy.id || '';
  document.getElementById('pharmacy-report-target').textContent = `Reporting: ${pharmacy.name || 'Pharmacy'}`;
  document.getElementById('pharmacy-report-error').hidden = true;
  togglePharmacyReportOtherReason();
  modal.hidden = false;
}

function closePharmacyReportModal(){
  const modal = document.getElementById('pharmacy-report-modal');
  const input = document.getElementById('pharmacy-report-proof');
  if(input) input.value = '';
  updatePharmacyReportFileName({ files: [] });
  if(modal) modal.hidden = true;
}

function togglePharmacyReportOtherReason(){
  const reason = document.getElementById('pharmacy-report-reason');
  const wrap = document.getElementById('pharmacy-report-other-wrap');
  const input = document.getElementById('pharmacy-report-other');
  const isOther = reason?.value === 'Other';
  if(wrap) wrap.hidden = !isOther;
  if(input) input.required = isOther;
}

function clearPharmacyReportPreview(){
  const drop = document.getElementById('pharmacy-report-drop');
  const img = document.getElementById('pharmacy-report-preview-img');
  const fileCard = document.getElementById('pharmacy-report-preview-file');
  if(drop?.dataset.previewUrl){
    URL.revokeObjectURL(drop.dataset.previewUrl);
    delete drop.dataset.previewUrl;
  }
  if(img){
    img.removeAttribute('src');
    img.hidden = true;
  }
  if(fileCard){
    fileCard.hidden = true;
    fileCard.textContent = '';
  }
}

function updatePharmacyReportFileName(input){
  const label = document.getElementById('pharmacy-report-file-name');
  const drop = document.getElementById('pharmacy-report-drop');
  const img = document.getElementById('pharmacy-report-preview-img');
  const fileCard = document.getElementById('pharmacy-report-preview-file');
  const file = input.files?.[0] || null;
  const name = file?.name || '';
  if(label) label.textContent = name || 'No file chosen';
  drop?.classList.toggle('has-file', !!file);
  clearPharmacyReportPreview();
  if(!file || !drop) return;
  drop.classList.add('has-file');
  const isImage = /^image\/(jpeg|png|webp|gif)$/i.test(file.type) || /\.(jpe?g|png|webp|gif)$/i.test(name);
  if(isImage && img){
    const url = URL.createObjectURL(file);
    drop.dataset.previewUrl = url;
    img.src = url;
    img.hidden = false;
    if(fileCard) fileCard.hidden = true;
    return;
  }
  if(img) img.hidden = true;
  if(fileCard){
    fileCard.hidden = false;
    fileCard.textContent = name;
  }
}

function removePharmacyReportProof(event){
  event.preventDefault();
  event.stopPropagation();
  const input = document.getElementById('pharmacy-report-proof');
  if(input) input.value = '';
  updatePharmacyReportFileName({ files: [] });
}

function bindPharmacyReportDrop(){
  const drop = document.getElementById('pharmacy-report-drop');
  const input = document.getElementById('pharmacy-report-proof');
  if(!drop || !input || drop.dataset.bound === '1') return;
  drop.dataset.bound = '1';
  ['dragenter','dragover'].forEach((eventName) => {
    drop.addEventListener(eventName, (event) => {
      event.preventDefault();
      drop.classList.add('is-dragover');
    });
  });
  ['dragleave','drop'].forEach((eventName) => {
    drop.addEventListener(eventName, (event) => {
      event.preventDefault();
      drop.classList.remove('is-dragover');
    });
  });
  drop.addEventListener('drop', (event) => {
    const file = event.dataTransfer?.files?.[0];
    if(!file) return;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    updatePharmacyReportFileName(input);
  });
}

async function submitPharmacyReport(event){
  event.preventDefault();
  const form = event.currentTarget;
  const error = document.getElementById('pharmacy-report-error');
  const proof = document.getElementById('pharmacy-report-proof');
  const submit = form.querySelector('[type="submit"]');
  togglePharmacyReportOtherReason();
  if(!form.checkValidity()){
    form.reportValidity();
    return;
  }
  if(!proof?.files?.length){
    error.textContent = 'Please upload proof for this report.';
    error.hidden = false;
    return;
  }
  error.hidden = true;
  submit.disabled = true;
  submit.textContent = 'Submitting…';
  try{
    const response = await fetch(window.RESIDENCE_CONFIG?.reportPharmacyUrl || '../ajax/report-pharmacy.php', { method:'POST', body:new FormData(form) });
    const result = await response.json();
    if(!response.ok || !result.ok) throw new Error(result.error || 'Could not submit the report.');
    closePharmacyReportModal();
    toast('Your report has been submitted for review.');
  }catch(err){
    error.textContent = err.message || 'Could not submit the report.';
    error.hidden = false;
  }finally{
    submit.disabled = false;
    submit.textContent = 'Submit report';
  }
}

function pharmacyProfileBadgeForCard(card){
  const id = String(card.dataset.medicineId || '');
  const cfg = window.RESIDENCE_CONFIG || {};
  const newIds = new Set((cfg.newProductIds || []).map(String));
  const topIds = new Set((cfg.topSellerIds || []).map(String));
  const featuredIds = new Set((cfg.featuredProductIds || []).map(String));
  if(card.dataset.isNew === '1' || newIds.has(id)) return { type:'new', label:'New' };
  if(card.dataset.featured === '1' || featuredIds.has(id)) return { type:'featured', label:'Featured' };
  if(card.dataset.topSeller === '1' || topIds.has(id)) return { type:'top', label:'Top Seller' };
  return null;
}

function applySpotlightBadge(card){
  const badge = pharmacyProfileBadgeForCard(card);
  if(!badge) return;
  const existing = card.querySelector('.med-card-flag');
  if(existing){
    if(existing.classList.contains('med-card-flag--expired')
      || existing.classList.contains('med-card-flag--low')
      || existing.classList.contains('med-card-flag--out')
      || existing.classList.contains('med-card-flag--expiring')) return;
    existing.className = `med-card-flag med-card-flag--${badge.type}`;
    existing.textContent = badge.label;
    return;
  }
  const flag = document.createElement('span');
  flag.className = `med-card-flag med-card-flag--${badge.type}`;
  flag.textContent = badge.label;
  card.insertBefore(flag, card.firstChild);
}

function pharmacyProfileCardHtml(card){
  const copy = card.cloneNode(true);
  copy.classList.add('med-card--spotlight');
  copy.classList.remove('is-search-hidden');
  copy.hidden = false;
  copy.removeAttribute('hidden');
  copy.style.removeProperty('display');
  copy.removeAttribute('onclick');
  copy.removeAttribute('onkeydown');
  applySpotlightBadge(copy);
  return copy.outerHTML;
}

function setPharmacyProfileEmpty(isEmpty){
  const page = document.querySelector('.page[data-page="pharmacy-profile"]');
  page?.classList.toggle('is-pharmacy-empty', Boolean(isEmpty));
}

const PHARMACY_PROFILE_PAGE_SIZE = 12;
let pharmacyProfilePage = 1;
let pharmacyProfileCategory = '';
let pharmacyProfilePriceSort = '';
let pharmacyProfilePriceMin = null;
let pharmacyProfilePriceMax = null;

function parsePharmacyProfilePriceInput(value){
  const cleaned = String(value || '').replace(/[^\d.]/g, '');
  if(cleaned === '' || cleaned === '.') return null;
  const amount = Number(cleaned);
  return Number.isFinite(amount) ? amount : null;
}

function pharmacyProfilePriceBounds(){
  let min = pharmacyProfilePriceMin;
  let max = pharmacyProfilePriceMax;
  if(min != null && max != null && min > max){
    return { min: max, max: min };
  }
  return { min, max };
}

function pharmacyProfileMatchesPrice(card){
  const { min, max } = pharmacyProfilePriceBounds();
  if(min == null && max == null) return true;
  const price = getMedicinePrice(card);
  if(min != null && price < min) return false;
  if(max != null && price > max) return false;
  return true;
}

function pharmacyProfileCategoryLabel(card){
  const labeled = (card?.dataset?.categoryLabel || '').trim();
  if(labeled) return labeled;
  return (card?.querySelector('.med-cat')?.textContent || '').replace(/^[·•]\s*/, '').trim();
}

function pharmacyProfileCategorySlug(label){
  const slug = String(label || '').trim().toLowerCase();
  if(!slug) return '';
  if(slug === 'pain relief' || slug === 'pain-relief') return 'pain-relief';
  if(slug === 'antibiotic' || slug === 'antibiotics') return 'antibiotic';
  if(slug === 'cold & flu' || slug === 'cold-flu') return 'cold-flu';
  if(slug === 'vitamins' || slug === 'vitamins & supplements') return 'vitamins';
  return slug.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || '';
}

function pharmacyProfileCardMatchesCategory(card, wanted){
  const needle = String(wanted || '').trim().toLowerCase();
  if(!needle) return true;
  const wantedSlug = pharmacyProfileCategorySlug(needle);
  const label = pharmacyProfileCategoryLabel(card);
  const labelSlug = pharmacyProfileCategorySlug(label);
  const shopSub = String(card?.dataset?.shopSub || '').trim().toLowerCase();
  return label.toLowerCase() === needle
    || (wantedSlug !== '' && (labelSlug === wantedSlug || shopSub === wantedSlug))
    || shopSub === needle;
}

function pharmacyProfileStoreCards(){
  const pharmacyId = String(activePharmacyProfileId || '').trim();
  if(!pharmacyId) return [];
  return Array.from(document.querySelectorAll('.med-catalog .med-grid .med-card')).filter(card =>
    String(card.dataset.pharmacyId || '').trim() === pharmacyId
  );
}

function pharmacyProfileSearchQuery(){
  return (document.getElementById('medicine-search')?.value || '').trim();
}

function pharmacyProfileMatchesSearch(card){
  const q = pharmacyProfileSearchQuery();
  if(!q) return true;
  return medicineMatchesQuery(getMedicineSearchText(card), q);
}

function pharmacyProfileProducts(){
  const wanted = pharmacyProfileCategory.trim();
  return pharmacyProfileStoreCards()
    .filter(card => pharmacyProfileCardMatchesCategory(card, wanted) && pharmacyProfileMatchesPrice(card) && pharmacyProfileMatchesSearch(card))
    .sort((a, b) => {
      if(pharmacyProfilePriceSort === 'asc' || pharmacyProfilePriceSort === 'desc'){
        const byPrice = getMedicinePrice(a) - getMedicinePrice(b);
        if(byPrice !== 0) return pharmacyProfilePriceSort === 'asc' ? byPrice : -byPrice;
        return medicineCardName(a).localeCompare(medicineCardName(b), undefined, { sensitivity: 'base' });
      }
      const byCategory = pharmacyProfileCategoryLabel(a).localeCompare(pharmacyProfileCategoryLabel(b), undefined, { sensitivity: 'base' });
      if(byCategory !== 0) return byCategory;
      return medicineCardName(a).localeCompare(medicineCardName(b), undefined, { sensitivity: 'base' });
    });
}

function refreshPharmacyProfileCategoryOptions(){
  const select = document.getElementById('pharmacy-profile-category-sort');
  if(!select) return;
  const seen = new Map();
  pharmacyProfileStoreCards().forEach(card => {
    const label = pharmacyProfileCategoryLabel(card);
    const shopSub = String(card.dataset.shopSub || '').trim();
    const slug = pharmacyProfileCategorySlug(label) || shopSub.toLowerCase();
    if(!slug) return;
    const display = label || shopCategoryLabels[shopSub] || shopSub.replace(/-/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
    if(!display || seen.has(slug)) return;
    seen.set(slug, display);
  });
  const options = Array.from(seen.entries()).map(([slug, label]) => ({ slug, label }));
  options.sort((a, b) => {
    const aOther = a.label.toLowerCase() === 'other';
    const bOther = b.label.toLowerCase() === 'other';
    if(aOther !== bOther) return aOther ? 1 : -1;
    return a.label.localeCompare(b.label, undefined, { sensitivity: 'base' });
  });
  const current = pharmacyProfileCategory.trim().toLowerCase();
  select.innerHTML = '<option value="">All categories</option>' + options.map(item =>
    `<option value="${escapeHtml(item.slug)}">${escapeHtml(item.label)}</option>`
  ).join('');
  const keep = options.find(item => item.slug === current || item.label.toLowerCase() === current);
  select.value = keep ? keep.slug : '';
  pharmacyProfileCategory = select.value;
}

function applyPharmacyProfileFilters(){
  pharmacyProfilePage = 1;
  renderPharmacyProfileProductPage(pharmacyProfileProducts());
}

function initPharmacyProfileCategorySort(){
  const select = document.getElementById('pharmacy-profile-category-sort');
  if(select && !select._categorySortReady){
    select._categorySortReady = true;
    select.addEventListener('change', () => {
      pharmacyProfileCategory = select.value || '';
      applyPharmacyProfileFilters();
    });
  }
  const priceSort = document.getElementById('pharmacy-profile-price-sort');
  if(priceSort && !priceSort._priceSortReady){
    priceSort._priceSortReady = true;
    priceSort.addEventListener('change', () => {
      pharmacyProfilePriceSort = priceSort.value || '';
      applyPharmacyProfileFilters();
    });
  }
  const priceMin = document.getElementById('pharmacy-profile-price-min');
  const priceMax = document.getElementById('pharmacy-profile-price-max');
  const bindRange = (input, assign) => {
    if(!input || input._priceRangeReady) return;
    input._priceRangeReady = true;
    let timer = 0;
    const apply = () => {
      assign(parsePharmacyProfilePriceInput(input.value));
      applyPharmacyProfileFilters();
    };
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(apply, 200);
    });
    input.addEventListener('change', apply);
    input.addEventListener('keydown', event => {
      if(event.key === 'Enter'){
        event.preventDefault();
        window.clearTimeout(timer);
        apply();
      }
    });
  };
  bindRange(priceMin, value => { pharmacyProfilePriceMin = value; });
  bindRange(priceMax, value => { pharmacyProfilePriceMax = value; });
}

function residencePageNumbers(current, total){
  if(total <= 7){
    return Array.from({length: total}, (_, i) => i + 1);
  }
  const set = new Set([1, 2, 3, total - 2, total - 1, total, current - 1, current, current + 1]);
  const nums = Array.from(set).filter(n => n >= 1 && n <= total).sort((a, b) => a - b);
  const out = [];
  nums.forEach((n, i) => {
    if(i > 0 && n - nums[i - 1] > 1) out.push('…');
    out.push(n);
  });
  return out;
}

function residencePagerControls(page, pages, attr){
  const chevronLeft = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';
  const chevronRight = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
  let html = `<button type="button" class="pager-step" data-${attr}="prev"${page <= 1 ? ' disabled' : ''} aria-label="Previous page"><span class="pager-chevron">${chevronLeft}</span><span>Prev</span></button>`;
  residencePageNumbers(page, pages).forEach(item => {
    if(item === '…'){
      html += '<span class="pager-ellipsis">…</span>';
      return;
    }
    html += `<button type="button" class="pager-num${item === page ? ' is-active' : ''}" data-${attr}="${item}"${item === page ? ' aria-current="page"' : ''}>${item}</button>`;
  });
  html += `<button type="button" class="pager-step" data-${attr}="next"${page >= pages ? ' disabled' : ''} aria-label="Next page"><span>Next</span><span class="pager-chevron">${chevronRight}</span></button>`;
  return html;
}

function pharmacyProfilePagerHtml(page, pages, total){
  if(total < 1) return '';
  const from = (page - 1) * PHARMACY_PROFILE_PAGE_SIZE + 1;
  const to = Math.min(page * PHARMACY_PROFILE_PAGE_SIZE, total);
  const controls = pages > 1
    ? `<div class="page-btns pager-pill">${residencePagerControls(page, pages, 'pharmacy-page')}</div>`
    : '';
  return `<nav class="pharmacy-profile-pager" aria-label="Product pages">${controls}<span class="catalog-page-meta">${from}–${to} of ${total}</span></nav>`;
}

function renderPharmacyProfileProductPage(products, heading = ''){
  const root = document.getElementById('pharmacy-profile-home');
  if(!root) return;
  bindPharmacyProfileProductClicks();
  if(products.length === 0){
    const hasStoreProducts = pharmacyProfileStoreCards().length > 0;
    setPharmacyProfileEmpty(!hasStoreProducts);
    if(!hasStoreProducts){
      root.innerHTML = heading
        ? `<section><div class="pharmacy-profile-section-head"><h3>${heading}</h3></div><p class="pharmacy-profile-empty">No products in this category yet.</p></section>`
        : '<div class="pharmacy-profile-empty-state"><p class="pharmacy-profile-empty">No products listed yet.</p></div>';
      return;
    }
    root.innerHTML = pharmacyProfileSearchQuery()
      ? '<p class="pharmacy-profile-empty">No medicines in this pharmacy match your search.</p>'
      : '<p class="pharmacy-profile-empty">No products match these filters.</p>';
    return;
  }
  setPharmacyProfileEmpty(false);
  const pages = Math.max(1, Math.ceil(products.length / PHARMACY_PROFILE_PAGE_SIZE));
  pharmacyProfilePage = Math.min(Math.max(1, pharmacyProfilePage), pages);
  const start = (pharmacyProfilePage - 1) * PHARMACY_PROFILE_PAGE_SIZE;
  const pageItems = products.slice(start, start + PHARMACY_PROFILE_PAGE_SIZE);
  const head = heading ? `<div class="pharmacy-profile-section-head"><h3>${heading}</h3></div>` : '';
  root.innerHTML = `<section>${head}<div class="med-grid pharmacy-profile-product-grid">${pageItems.map(pharmacyProfileCardHtml).join('')}</div>${pharmacyProfilePagerHtml(pharmacyProfilePage, pages, products.length)}</section>`;
}

function showPharmacyProfileHome(){
  refreshPharmacyProfileCategoryOptions();
  renderPharmacyProfileProductPage(pharmacyProfileProducts());
}

function bindPharmacyProfileProductClicks(){
  const root = document.getElementById('pharmacy-profile-home');
  if(!root || root.dataset.previewBound === '1') return;
  root.dataset.previewBound = '1';
  root.addEventListener('click', event => {
    const pageBtn = event.target.closest('[data-pharmacy-page]');
    if(pageBtn){
      event.preventDefault();
      event.stopPropagation();
      if(pageBtn.disabled) return;
      const raw = pageBtn.getAttribute('data-pharmacy-page');
      if(raw === 'prev') pharmacyProfilePage -= 1;
      else if(raw === 'next') pharmacyProfilePage += 1;
      else pharmacyProfilePage = parseInt(raw, 10) || 1;
      renderPharmacyProfileProductPage(pharmacyProfileProducts());
      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    if(event.target.closest('.med-add, .med-buy, .med-card-actions')) return;
    const card = event.target.closest('.med-card');
    if(!card || !root.contains(card)) return;
    event.preventDefault();
    event.stopPropagation();
    openProductPreview(card);
  });
  root.addEventListener('keydown', event => {
    if(event.key !== 'Enter' && event.key !== ' ') return;
    if(event.target.closest('.med-add, .med-buy, .med-card-actions')) return;
    const card = event.target.closest('.med-card');
    if(!card || !root.contains(card)) return;
    event.preventDefault();
    openProductPreview(card);
  });
}

function browsePharmacyProfileProducts(){
  pharmacyProfilePage = 1;
  showPharmacyProfileHome();
}

function browsePharmacyProfileCategories(){
  pharmacyProfilePage = 1;
  showPharmacyProfileHome();
}

function showPharmacyProfileCategoryProducts(category){
  pharmacyProfileCategory = category || '';
  pharmacyProfilePage = 1;
  const select = document.getElementById('pharmacy-profile-category-sort');
  if(select) select.value = pharmacyProfileCategory;
  renderPharmacyProfileProductPage(pharmacyProfileProducts());
}

function closePharmacyProfile(){
  go('pharmacies');
}

function medicineCardName(card){
  return card.querySelector('.med-card-name, .med-name')?.textContent?.trim() || 'Medicine';
}

function medicineCardGeneric(card){
  return card.querySelector('.med-generic')?.textContent?.trim() || '';
}

function medicineCardPriceText(card){
  return card.querySelector('.med-card-price, .med-price')?.textContent?.trim() || '';
}

function medicineCardImage(card){
  return card.querySelector('img.med-card-photo');
}

function getMedicineCardData(card){
  if(!card) return null;
  return {
    medicineId: parseInt(card.dataset.medicineId || '0', 10),
    pharmacyId: card.dataset.pharmacyId || '',
    pharmacyName: card.dataset.pharmacyName || '',
    pharmacyLabel: card.dataset.pharmacy || '',
    name: medicineCardName(card),
    meta: medicineCardGeneric(card),
    price: getMedicinePrice(card),
    rxRequired: card.dataset.rx === '1' || card.classList.contains('med-card--rx') || !!card.querySelector('.rx-badge'),
    image: medicineCardImage(card)?.getAttribute('src') || '',
  };
}

function cartRequiresPrescription(){
  return cartCheckoutItems().some(item => item.rxRequired);
}

function isCartItemSelected(item){
  return item?.selected === true;
}

function cartCheckoutItems(){
  if(window.buyNowCheckout) return [window.buyNowCheckout.item];
  return cart.items.filter(isCartItemSelected);
}

function checkoutPharmacyId(){ return window.buyNowCheckout?.pharmacyId || cartCheckoutItems()[0]?.pharmacyId || cart.pharmacyId; }
function checkoutPharmacyName(){ return window.buyNowCheckout?.pharmacyName || cartCheckoutItems()[0]?.pharmacyName || cart.pharmacyName; }
function removeCheckoutSummaryItem(){
  if(window.buyNowCheckout) window.buyNowCheckout = null;
  go('cart');
}

function cartSubtotal(){
  return cartCheckoutItems().reduce((sum, item) => sum + (item.price * item.quantity), 0);
}

function toggleCartCheckout(index, checked){
  const item = cart.items[index];
  if(!item) return;
  item.selected = !!checked;
  saveCart();
  renderCartPage();
}

function toggleAllCartCheckout(checked){
  cart.items.forEach(item => { item.selected = !!checked; });
  saveCart();
  renderCartPage();
}

function cartShopName(item){
  return item?.pharmacyName || item?.pharmacyLabel || cart.pharmacyName || 'Pharmacy';
}

function toggleStoreCartCheckout(shopName, checked){
  cart.items.forEach(item => {
    if(cartShopName(item) === shopName) item.selected = !!checked;
  });
  saveCart();
  renderCartPage();
}

function checkoutRxItemKey(item){
  return `${item.pharmacyId || ''}::${item.medicineId || ''}`;
}

function checkoutRxItems(){
  return cartCheckoutItems().filter(item => item.rxRequired && item.pharmacyId && item.medicineId);
}

function prescriptionInputForItem(itemKey){
  const id = String(itemKey);
  return Array.from(document.querySelectorAll('.checkout-prescription-file')).find(input => input.dataset.itemKey === id) || null;
}

function missingPrescriptionItems(){
  return checkoutRxItems().filter(item => {
    const key = checkoutRxItemKey(item);
    return !prescriptionFilesByItem[key] && !prescriptionInputForItem(key)?.files?.length;
  });
}

function appendCheckoutPrescriptions(form){
  checkoutRxItems().forEach(item => {
    const key = checkoutRxItemKey(item);
    const file = prescriptionFilesByItem[key] || prescriptionInputForItem(key)?.files?.[0];
    if(file) form.append(`prescriptions[${key}]`, file);
  });
}

function checkoutItemPayload(){
  captureCheckoutItemNotes();
  return cartCheckoutItems().map(item => ({
    medicine_id: item.medicineId,
    pharmacy_id: item.pharmacyId,
    quantity: item.quantity,
    note: checkoutItemNoteValue(item),
  }));
}

function checkoutItemNoteValue(item){
  const key = checkoutRxItemKey(item);
  const field = document.querySelector(checkoutItemNoteSelector(key));
  if(field) return String(field.value || '').trim().slice(0, 500);
  return String(item.note || '').trim().slice(0, 500);
}

function checkoutItemNoteSelector(key){
  const safe = (window.CSS && typeof CSS.escape === 'function') ? CSS.escape(String(key || '')) : String(key || '').replace(/["\\]/g, '');
  return `.checkout-item-note[data-item-key="${safe}"]`;
}

function rememberCheckoutItemNote(field){
  const key = String(field?.dataset?.itemKey || '');
  const note = String(field?.value || '').slice(0, 500);
  setCheckoutItemNote(key, note);
}

function checkoutNoteKeySelector(key){
  const safe = (window.CSS && typeof CSS.escape === 'function') ? CSS.escape(String(key || '')) : String(key || '').replace(/["\\]/g, '');
  return `.checkout-item-note-btn[data-item-key="${safe}"]`;
}

function checkoutNoteButtonLabel(note){
  return String(note || '').trim() ? 'Edit note' : 'Add note';
}

function checkoutNoteButtonMarkup(note){
  return `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>${checkoutNoteButtonLabel(note)}`;
}

function refreshCheckoutItemNoteUi(key, note){
  const value = String(note || '').trim();
  document.querySelectorAll(checkoutNoteKeySelector(key)).forEach(btn => {
    const row = btn.closest('.checkout-summary-item');
    btn.classList.toggle('has-note', value !== '');
    btn.innerHTML = checkoutNoteButtonMarkup(value);
    if(!row) return;
    row.classList.toggle('has-note', value !== '');
    const copy = row.querySelector('.checkout-summary-item-copy');
    let preview = row.querySelector('.checkout-item-note-preview');
    if(value){
      if(!preview && copy){
        preview = document.createElement('p');
        preview.className = 'checkout-item-note-preview';
        copy.appendChild(preview);
      }
      if(preview) preview.textContent = value;
    }else if(preview){
      preview.remove();
    }
  });
}

function setCheckoutItemNote(key, note){
  const value = String(note || '').trim().slice(0, 500);
  (cart.items || []).forEach(item => {
    if(checkoutRxItemKey(item) === key) item.note = value;
  });
  if(window.buyNowCheckout?.item && checkoutRxItemKey(window.buyNowCheckout.item) === key){
    window.buyNowCheckout.item.note = value;
  }
  saveCart();
  refreshCheckoutItemNoteUi(key, value);
}

let checkoutNoteItemKey = '';

function syncCheckoutNoteCount(){
  const field = document.getElementById('checkout-note-field');
  const count = document.getElementById('checkout-note-count');
  const removeBtn = document.getElementById('checkout-note-remove');
  const length = String(field?.value || '').length;
  if(count) count.textContent = length + ' / 500';
  if(removeBtn) removeBtn.hidden = !checkoutNoteHasSaved();
}

function checkoutNoteHasSaved(){
  const item = cartCheckoutItems().find(entry => checkoutRxItemKey(entry) === checkoutNoteItemKey);
  return String(item?.note || '').trim() !== '';
}

function openCheckoutNoteModal(key){
  checkoutNoteItemKey = String(key || '');
  const modal = document.getElementById('checkout-note-modal');
  const field = document.getElementById('checkout-note-field');
  const nameEl = document.getElementById('checkout-note-item-name');
  const titleEl = document.getElementById('checkout-note-title');
  const item = cartCheckoutItems().find(entry => checkoutRxItemKey(entry) === checkoutNoteItemKey);
  if(!modal || !field) return;
  const saved = String(item?.note || '').slice(0, 500);
  if(titleEl) titleEl.textContent = saved.trim() ? 'Edit note' : 'Add a note';
  if(nameEl) nameEl.textContent = item?.name || 'Medicine';
  field.value = saved;
  modal.hidden = false;
  document.body.classList.add('modal-open');
  syncCheckoutNoteCount();
  setTimeout(() => field.focus(), 40);
}

function closeCheckoutNoteModal(){
  const modal = document.getElementById('checkout-note-modal');
  if(modal) modal.hidden = true;
  document.body.classList.remove('modal-open');
  checkoutNoteItemKey = '';
}

function saveCheckoutNoteModal(){
  const field = document.getElementById('checkout-note-field');
  if(checkoutNoteItemKey) setCheckoutItemNote(checkoutNoteItemKey, field?.value || '');
  closeCheckoutNoteModal();
}

function clearCheckoutNoteModal(){
  if(checkoutNoteItemKey) setCheckoutItemNote(checkoutNoteItemKey, '');
  closeCheckoutNoteModal();
}

window.openCheckoutNoteModal = openCheckoutNoteModal;
window.closeCheckoutNoteModal = closeCheckoutNoteModal;
window.saveCheckoutNoteModal = saveCheckoutNoteModal;
window.clearCheckoutNoteModal = clearCheckoutNoteModal;

function captureCheckoutItemNotes(){
  document.querySelectorAll('.checkout-item-note').forEach(field => rememberCheckoutItemNote(field));
}

function proceedToCheckout(){
  window.buyNowCheckout = null;
  const checkoutItems = cartCheckoutItems();
  const checkoutRoot = document.getElementById('checkout-page-root');
  checkoutRoot?.classList.toggle('has-prescription', cartRequiresPrescription());
  checkoutRoot?.classList.toggle('no-prescription', !cartRequiresPrescription());
  if(checkoutItems.length === 0){
    toast('Check the items you want to check out.');
    return;
  }
  resetCheckoutPaymentSession();
  go('checkout');
}

function syncCartCount(){
  cartCount = cart.items.length;
  const countEl = document.getElementById('sb-cart-count');
  if(!countEl) return;
  countEl.textContent = cartCount > 99 ? '99+' : String(cartCount);
  countEl.hidden = cartCount <= 0;
}

function addMedicineToCart(card, qty = 1){
  const data = getMedicineCardData(card);
  if(!data || Number(data.medicineId) <= 0 || !data.pharmacyId){
    toast('This medicine is not available for ordering yet.');
    return false;
  }

  const pharmacy = getMarketplacePharmacy(data.pharmacyId);
  if(cart.items.length === 0){
    cart.pharmacyId = data.pharmacyId;
    cart.pharmacyName = pharmacy?.name || data.pharmacyName || data.pharmacyLabel || 'Pharmacy';
    cart.pharmacyLabel = data.pharmacyLabel || cart.pharmacyName;
  }

  const existing = cart.items.find(item => item.medicineId === data.medicineId && item.pharmacyId === data.pharmacyId);
  if(existing){
    existing.quantity += qty;
    existing.selected = true;
  }else{
    cart.items.push({ ...data, quantity: qty, selected: true });
  }

  saveCart();
  return true;
}

function renderCartPage(){
  const itemsRoot = document.getElementById('cart-items-root');
  const sideItemsRoot = document.getElementById('cart-side-items-root');
  const summaryRoot = document.getElementById('cart-summary-root');
  const checkoutBtn = document.getElementById('cart-checkout-btn');
  const subtitle = document.getElementById('cart-panel-sub');
  if(!itemsRoot || !summaryRoot) return;

  const fallbackImage = cartMedicineFallbackImage();

  function cartItemCards(){
    return cart.items.map((item, index) => {
      if(!isCartItemSelected(item)) return '';
      const image = item.image || fallbackImage;
      return `
        <article class="cart-side-card">
          <img src="${image}" alt="" onerror="this.onerror=null;this.src='${fallbackImage}'">
          <div class="cart-side-card-info">
            <div class="cart-side-card-top">
              <div class="cart-side-name">${item.name}</div>
              <button type="button" class="cart-side-trash" onclick="removeCartItem(${index})" aria-label="Remove ${item.name}">
                <svg class="icon" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"/></svg>
              </button>
            </div>
            <div class="cart-side-shop">${item.pharmacyName || cart.pharmacyName || item.pharmacyLabel || 'Pharmacy'}</div>
            <div class="cart-side-meta">
              <span class="cart-side-price">${formatMoney(item.price)} × ${item.quantity}</span>
              <span class="cart-side-item-total">${formatMoney(item.price * item.quantity)}</span>
              <span class="cart-side-qty">× ${item.quantity}</span>
            </div>
          </div>
        </article>`;
    }).join('');
  }

  function cartSummaryHtml(subtotal){
    return `
      <div class="cart-side-row cart-side-row--total"><span>Total</span><strong>${formatMoney(subtotal)}</strong></div>`;
  }

  if(cart.items.length === 0){
    if(subtitle) subtitle.textContent = 'Your cart is empty. Continue shopping to add medicines.';
    itemsRoot.innerHTML = '<p class="cart-empty">Add medicines to your cart to continue.</p>';
    if(sideItemsRoot) sideItemsRoot.innerHTML = '<p class="cart-side-empty">Your cart is empty.</p>';
    summaryRoot.innerHTML = cartSummaryHtml(0);
    if(checkoutBtn) checkoutBtn.disabled = true;
    const selectAll = document.getElementById('cart-select-all');
    if(selectAll){
      selectAll.checked = false;
      selectAll.indeterminate = false;
    }
    return;
  }

  if(subtitle){
    subtitle.textContent = 'Select the medicines you want to pick up from each pharmacy.';
  }

  const shopGroups = cart.items.reduce((groups, item, index) => {
    const shopName = cartShopName(item);
    if(!groups[shopName]) groups[shopName] = [];
    groups[shopName].push({ item, index });
    return groups;
  }, {});

  itemsRoot.innerHTML = Object.entries(shopGroups).map(([shopName, entries]) => {
    const selectedCount = entries.filter(({ item }) => isCartItemSelected(item)).length;
    const storeChecked = selectedCount === entries.length ? ' checked' : '';
    const storePartial = selectedCount > 0 && selectedCount < entries.length ? ' data-indeterminate="true"' : '';
    const shopArgument = JSON.stringify(shopName)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    const pharmacyArgument = JSON.stringify(entries[0]?.item?.pharmacyId || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    const storeHeader = `
      <div class="cart-store-head">
        <label class="cart-store-select" title="Select all items from ${shopName}">
          <input type="checkbox" class="cart-store-check"${storeChecked}${storePartial} onchange="toggleStoreCartCheckout(${shopArgument}, this.checked)" aria-label="Select all items from ${shopName}">
        </label>
        <button type="button" class="cart-store-details" onclick="openPharmacyProfile(${pharmacyArgument})" aria-label="View ${shopName}">
          <span class="cart-store-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 10h16v10H4zM3 10l2-6h14l2 6M9 20v-5h6v5"/></svg></span>
          <div class="cart-store-name">${shopName}</div>
        </button>
        <span class="cart-store-count">${entries.length} item${entries.length === 1 ? '' : 's'}</span>
      </div>`;
    const items = entries.map(({ item, index }) => {
      const image = item.image || fallbackImage;
      const checked = isCartItemSelected(item) ? ' checked' : '';
      return `
    <div class="cart-item${isCartItemSelected(item) ? ' is-checkout' : ''}" data-cart-index="${index}">
      <label class="ci-check-wrap">
        <input type="checkbox" class="ci-check" ${checked} onchange="toggleCartCheckout(${index}, this.checked)" aria-label="Check out ${item.name}">
      </label>
      <div class="ci-product">
        <img src="${image}" alt="" onerror="this.onerror=null;this.src='${fallbackImage}'">
        <div class="ci-info">
          <div class="ci-name">${item.name}</div>
          <div class="ci-shop">${item.pharmacyName || cart.pharmacyName || item.pharmacyLabel || 'Pharmacy'}</div>
          <div class="ci-unit">${formatMoney(item.price)} × ${item.quantity} PHP</div>
          <button type="button" class="ci-remove" onclick="removeCartItem(${index})">
            <svg class="icon" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"/></svg>
            Remove
          </button>
        </div>
      </div>
      <div class="qty-ctrl qty-ctrl--round">
        <button type="button" onclick="cartQtyChange(${index}, -1)" aria-label="Decrease quantity">−</button>
        <span class="qn">${item.quantity}</span>
        <button type="button" onclick="cartQtyChange(${index}, 1)" aria-label="Increase quantity">+</button>
      </div>
      <div class="ci-price">${formatMoney(item.price * item.quantity)}</div>
    </div>`;
    }).join('');
    return `<section class="cart-store">${storeHeader}${items}</section>`;
  }).join('');

  document.querySelectorAll('.cart-store-check[data-indeterminate="true"]').forEach(input => {
    input.indeterminate = true;
  });

  const checkoutItems = cartCheckoutItems();
  if(sideItemsRoot){
    sideItemsRoot.innerHTML = checkoutItems.length
      ? cartItemCards()
      : '<p class="cart-side-empty">No items selected</p>';
  }

  const subtotal = cartSubtotal();
  summaryRoot.innerHTML = cartSummaryHtml(subtotal);
  if(checkoutBtn) checkoutBtn.disabled = checkoutItems.length === 0;

  const selectAll = document.getElementById('cart-select-all');
  if(selectAll){
    selectAll.checked = checkoutItems.length === cart.items.length;
    selectAll.indeterminate = checkoutItems.length > 0 && checkoutItems.length < cart.items.length;
  }
}

function cartQtyChange(index, delta){
  const item = cart.items[index];
  if(!item) return;
  item.quantity = Math.max(1, item.quantity + delta);
  saveCart();
  renderCartPage();
}

function removeCartItem(index){
  cart.items.splice(index, 1);
  if(cart.items.length === 0){
    cart.pharmacyId = '';
    cart.pharmacyName = '';
    cart.pharmacyLabel = '';
  }
  saveCart();
  renderCartPage();
}

function removeCheckoutPrescriptionUploads(){
  document.querySelectorAll('.checkout-item-rx, .checkout-item-rx-btn, .checkout-item-rx-clear').forEach(el => el.remove());
}

function renderCheckoutPage(){
  const fallbackImage = cartMedicineFallbackImage();
  const summaryLines = document.querySelector('.checkout-summary-card #checkout-summary-lines');
  const totalEl = document.getElementById('checkout-total');
  const downEl = document.getElementById('checkout-down-payment');
  const balanceNote = document.getElementById('checkout-balance-note');
  const placeBtn = document.getElementById('checkout-place-order-btn');
  const dateInput = document.getElementById('checkout-pickup-date');

  if(!summaryLines) return;
  removeCheckoutPrescriptionUploads();
  document.querySelectorAll('.payment-tab[data-method]').forEach(tab => {
    tab.onclick = () => {
      document.querySelectorAll('.payment-tab[data-method]').forEach(t => t.classList.toggle('active', t === tab));
      const isCard = tab.dataset.method === 'card';
      document.querySelectorAll('.card-only').forEach(field => field.style.display = isCard ? '' : 'none');
    };
  });

  const checkoutItems = cartCheckoutItems();
  const checkoutSignature = cartItemsSignature(checkoutItems);
  if(window.paymongoPaid && checkoutSignature !== checkoutPaidSignature){
    resetCheckoutPaymentSession();
  }
  captureCheckoutItemNotes();
  if(summaryLines.dataset.checkoutSignature === checkoutSignature && checkoutItems.length > 0){
    removeCheckoutPrescriptionUploads();
    return;
  }
  summaryLines.dataset.checkoutSignature = checkoutSignature;
  if(checkoutItems.length === 0){
    summaryLines.innerHTML = '<p class="hint">No items selected for checkout. Go back to the cart and check the medicines you want to order.</p>';
    const emptyTotals = checkoutPriceTotals(0);
    if(totalEl) totalEl.textContent = formatMoney(0);
    document.getElementById('checkout-subtotal') && (document.getElementById('checkout-subtotal').textContent = formatMoney(0));
    document.getElementById('checkout-vat') && (document.getElementById('checkout-vat').textContent = formatMoney(0));
    const emptyGrand = document.getElementById('checkout-grand-total');
    if(emptyGrand) emptyGrand.textContent = formatMoney(0);
    if(downEl) downEl.textContent = formatMoney(0);
    const emptyLater = document.getElementById('checkout-pay-later');
    if(emptyLater) emptyLater.textContent = formatMoney(0);
    renderCheckoutPayButton();
    if(placeBtn) placeBtn.disabled = true;
    return;
  }

  const totals = checkoutPriceTotals(cartSubtotal());
  summaryLines.innerHTML = checkoutItems.map(item => {
    const itemKey = escapeHtml(checkoutRxItemKey(item));
    return `
    <div class="checkout-summary-item${item.note ? ' has-note' : ''}">
      <button type="button" class="checkout-item-note-btn${item.note ? ' has-note' : ''}" data-item-key="${itemKey}">${checkoutNoteButtonMarkup(item.note)}</button>
      <img src="${item.image || fallbackImage}" alt="" onerror="this.onerror=null;this.src='${fallbackImage}'">
      <div class="checkout-summary-item-copy"><strong>${item.name}</strong><span>${item.pharmacyName || checkoutPharmacyName()}</span><b><em>${formatMoney(item.price)} × ${item.quantity}</em><strong>${formatMoney(item.price * item.quantity)}</strong></b>${item.note ? `<p class="checkout-item-note-preview">${escapeHtml(item.note)}</p>` : ''}</div>
    </div>
  `;
  }).join('');
  removeCheckoutPrescriptionUploads();
  restoreCheckoutPrescriptions();
  if(totalEl) totalEl.textContent = formatMoney(totals.total);
  const subtotalEl = document.getElementById('checkout-subtotal');
  const vatEl = document.getElementById('checkout-vat');
  const grandTotalEl = document.getElementById('checkout-grand-total');
  if(subtotalEl) subtotalEl.textContent = formatMoney(totals.subtotal);
  if(vatEl) vatEl.textContent = formatMoney(totals.vat);
  if(grandTotalEl) grandTotalEl.textContent = formatMoney(totals.total);
  if(downEl) downEl.textContent = formatMoney(totals.downPayment);
  const payNowEl = document.getElementById('checkout-pay-now');
  const payLaterEl = document.getElementById('checkout-pay-later');
  if(payNowEl) payNowEl.textContent = formatMoney(totals.downPayment);
  if(payLaterEl) payLaterEl.textContent = formatMoney(totals.remaining);

  renderCheckoutPayButton();
  syncCheckoutConfirmButton();

  if(dateInput && !dateInput.value){
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.value = tomorrow.toISOString().slice(0, 10);
    dateInput.min = new Date().toISOString().slice(0, 10);
  }

  const profile = window.RESIDENCE_CONFIG?.profile || {};
  const emailInput = document.getElementById('payment-payer-email');
  const nameInput = document.getElementById('payment-payer-name');
  const phoneInput = document.getElementById('payment-payer-number');
  const addressInput = document.getElementById('payment-payer-address');
  if(emailInput && !emailInput.value && profile.email) emailInput.value = profile.email;
  if(nameInput && !nameInput.value && profile.full_name) nameInput.value = profile.full_name;
  if(phoneInput && !phoneInput.value && profile.contact_number) phoneInput.value = profile.contact_number;
  if(addressInput && !addressInput.value && profile.address) addressInput.value = profile.address;

  // Pickup availability is coordinated directly with the pharmacy.
}

function initCheckoutPickupSlots(){
  const slots = document.querySelectorAll('#checkout-pickup-slots .slot-btn');
  slots.forEach(btn => {
    btn.onclick = () => {
      slots.forEach(slot => slot.classList.remove('selected'));
      btn.classList.add('selected');
    };
  });
}

let prescriptionCameraStream = null;
async function openPrescriptionCamera(){
  const modal = document.getElementById('prescription-camera-modal');
  const video = document.getElementById('prescription-camera-video');
  if(!modal || !video) return;
  if(!navigator.mediaDevices?.getUserMedia){
    toast('Camera access is not supported by this browser. Use Upload instead.');
    return;
  }
  try{
    prescriptionCameraStream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'environment' }, audio:false });
    video.srcObject = prescriptionCameraStream;
    modal.hidden = false;
  }catch(error){
    toast('Camera permission was not granted. Check your browser camera permission.');
  }
}

function closePrescriptionCamera(){
  prescriptionCameraStream?.getTracks().forEach(track => track.stop());
  prescriptionCameraStream = null;
  const video = document.getElementById('prescription-camera-video');
  if(video) video.srcObject = null;
  const modal = document.getElementById('prescription-camera-modal');
  if(modal) modal.hidden = true;
}

function capturePrescriptionPhoto(){
  const video = document.getElementById('prescription-camera-video');
  const canvas = document.getElementById('prescription-camera-canvas');
  const uploadInput = prescriptionInputForItem(activePrescriptionItemKey) || document.querySelector('.checkout-prescription-file');
  if(!video || !canvas || !uploadInput || !video.videoWidth) return;
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
    if(!blob) return;
    const file = new File([blob], `prescription-${Date.now()}.jpg`, { type:'image/jpeg' });
    applyPrescriptionFile(uploadInput, file);
    closePrescriptionCamera();
  }, 'image/jpeg', .92);
}

function formatPrescriptionSize(bytes){
  const size = Number(bytes) || 0;
  if(size < 1024) return size + ' B';
  if(size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
  return (size / (1024 * 1024)).toFixed(1) + ' MB';
}

function prescriptionUploadRoot(el){
  return el?.closest?.('.checkout-summary-item') || el?.closest?.('.rx-dropzone') || el || null;
}

function syncPrescriptionUploadUI(root, file){
  const item = prescriptionUploadRoot(root);
  if(!item) return;
  const dropzone = item.classList?.contains('rx-dropzone') ? item : item.querySelector?.('.rx-dropzone');
  const text = item.querySelector('.checkout-item-rx-text');
  const clear = item.querySelector('.checkout-item-rx-clear');
  const btn = item.querySelector('.checkout-item-rx-btn');
  const chip = dropzone?.querySelector('.rx-file-chip');
  const nameEl = dropzone?.querySelector('.rx-file-name');
  const sizeEl = dropzone?.querySelector('.rx-file-size');
  const hasFile = !!file;
  if(text) text.textContent = hasFile ? 'Uploaded' : 'Upload prescription';
  if(btn) btn.classList.toggle('is-done', hasFile);
  if(clear) clear.hidden = !hasFile;
  if(chip) chip.hidden = !hasFile;
  dropzone?.classList.toggle('has-file', hasFile);
  if(hasFile){
    if(nameEl) nameEl.textContent = file.name;
    if(sizeEl) sizeEl.textContent = formatPrescriptionSize(file.size);
  }
}

function applyPrescriptionFile(input, file){
  if(!input) return;
  const itemKey = input.dataset.itemKey || checkoutRxItemKey({
    pharmacyId: input.dataset.pharmacyId || '',
    medicineId: input.dataset.medicineId || '',
  });
  if(file && file.size > 5 * 1024 * 1024){
    toast('Prescription must be 5MB or smaller.');
    input.value = '';
    if(itemKey) delete prescriptionFilesByItem[itemKey];
    syncPrescriptionUploadUI(prescriptionUploadRoot(input), null);
    return;
  }
  if(file){
    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    if(itemKey) prescriptionFilesByItem[itemKey] = file;
  }else{
    input.value = '';
    if(itemKey) delete prescriptionFilesByItem[itemKey];
  }
  syncPrescriptionUploadUI(prescriptionUploadRoot(input), file || null);
}

function restoreCheckoutPrescriptions(){
  checkoutRxItems().forEach(item => {
    const key = checkoutRxItemKey(item);
    const input = prescriptionInputForItem(key);
    const stored = prescriptionFilesByItem[key];
    if(input && stored && !input.files?.length) applyPrescriptionFile(input, stored);
    else if(input) syncPrescriptionUploadUI(prescriptionUploadRoot(input), stored || input.files?.[0] || null);
  });
}

function clearPrescriptionUpload(trigger){
  const input = prescriptionUploadRoot(trigger)?.querySelector('.checkout-prescription-file');
  applyPrescriptionFile(input, null);
}

function continueCheckoutFromDetails(){
  const name = document.getElementById('checkout-buyer-name')?.value.trim();
  const contact = document.getElementById('checkout-buyer-contact')?.value.trim();
  if(!name || !contact){
    toast('Enter your name and contact number to continue.');
    return;
  }
  const root = document.getElementById('checkout-page-root');
  root?.classList.remove('is-details-step');
  root?.querySelectorAll('[data-checkout-step]').forEach(step => {
    step.classList.toggle('is-active', step.dataset.checkoutStep === '2');
    step.classList.toggle('is-complete', Number(step.dataset.checkoutStep) < 2);
  });
}

function getSelectedPickupSlot(){
  return document.querySelector('#checkout-pickup-slots .slot-btn.selected')?.dataset.slot || '';
}

function enrichOrderForReceipt(order){
  const checkoutItems = cartCheckoutItems();
  const profile = window.RESIDENCE_CONFIG?.profile || {};
  const lines = Array.isArray(order?.items) && order.items.length ? order.items : checkoutItems;
  const items = lines.map((line, index) => {
    const lineMedicineId = String(line.medicine_id || line.medicineId || '');
    const linePharmacyId = String(line.pharmacy_id || line.pharmacyId || order?.pharmacy_id || '');
    const cartItem = checkoutItems.find(item =>
      String(item.medicineId) === lineMedicineId &&
      (!linePharmacyId || String(item.pharmacyId) === linePharmacyId)
    ) || checkoutItems[index] || {};
    const quantity = Number(line.quantity || cartItem.quantity || 1);
    const unitPrice = Number(line.unit_price ?? line.price ?? cartItem.price ?? 0);
    return {
      ...line,
      name: line.medicine_name || line.name || cartItem.name || 'Medicine',
      image: line.image || cartItem.image || '',
      pharmacyName: line.pharmacyName || line.pharmacy_name || cartItem.pharmacyName || order?.pharmacy_name || '',
      pharmacy_id: line.pharmacy_id || line.pharmacyId || cartItem.pharmacyId || order?.pharmacy_id || '',
      quantity,
      unit_price: unitPrice,
      line_total: unitPrice * quantity,
    };
  });
  const pharmacy = getMarketplacePharmacy(order?.pharmacy_id || checkoutPharmacyId());
  return {
    ...order,
    items,
    customer_name: order?.customer_name || order?.payer_name || profile.full_name || '',
    customer_address: order?.customer_address || profile.address || '',
    pharmacy_address: order?.pharmacy_address || pharmacy?.address || '',
    pickup_date: order?.pickup_date || document.getElementById('checkout-pickup-date')?.value || '',
    pickup_time: order?.pickup_time || getSelectedPickupSlot() || 'Any available time',
    pharmacy_name: order?.pharmacy_name || pharmacy?.name || checkoutPharmacyName(),
  };
}

function combineOrdersForReceipt(orders, fallbackOrder){
  const list = (Array.isArray(orders) && orders.length ? orders : (fallbackOrder ? [fallbackOrder] : []))
    .filter(order => order && typeof order === 'object');
  if(list.length === 0) return fallbackOrder || null;
  if(list.length === 1 && Array.isArray(list[0].items) && list[0].items.length && !list[0].related_order_numbers){
    return enrichOrderForReceipt(list[0]);
  }
  if(Array.isArray(fallbackOrder?.items) && fallbackOrder.items.length > 1 && Array.isArray(fallbackOrder.related_order_numbers)){
    return enrichOrderForReceipt(fallbackOrder);
  }

  const enrichedOrders = list.map(order => enrichOrderForReceipt(order));
  const items = [];
  const numbers = [];
  const pharmacies = [];
  let total = 0;
  let down = 0;
  enrichedOrders.forEach(order => {
    if(order.order_number) numbers.push(String(order.order_number));
    const name = String(order.pharmacy_name || '').trim();
    const address = String(order.pharmacy_address || '').trim();
    if(name && !pharmacies.some(entry => entry.name === name)){
      pharmacies.push({ name, address });
    }
    total += Number(order.total_amount || 0);
    down += Number(order.down_payment || 0);
    (order.items || []).forEach(item => {
      items.push({
        ...item,
        pharmacyName: item.pharmacyName || order.pharmacy_name || '',
        pharmacy_id: item.pharmacy_id || order.pharmacy_id || '',
      });
    });
  });

  if(items.length === 0 && fallbackOrder){
    return enrichOrderForReceipt(fallbackOrder);
  }

  const primary = enrichedOrders[0] || enrichOrderForReceipt(fallbackOrder || {});
  return {
    ...primary,
    items,
    orders: enrichedOrders,
    related_order_numbers: numbers,
    order_numbers_label: numbers.join(', '),
    pharmacy_name: pharmacies.map(entry => entry.name).join(', ') || primary.pharmacy_name,
    pharmacy_address: pharmacies.map(entry => entry.address).filter(Boolean).join(' · ') || primary.pharmacy_address,
    total_amount: total || Number(primary.total_amount || 0),
    down_payment: down || Number(primary.down_payment || 0),
    item_count: items.length,
  };
}

function receiptWithCheckoutItems(order){
  const receipt = combineOrdersForReceipt(order?.orders, order);
  if(!receipt) return order;
  const checkoutItems = cartCheckoutItems();
  if(!checkoutItems.length) return receipt;
  const keys = new Set((receipt.items || []).map(item => `${item.pharmacy_id || ''}::${item.medicine_id || item.medicineId || ''}`));
  checkoutItems.forEach(item => {
    const key = `${item.pharmacyId || ''}::${item.medicineId || ''}`;
    if(keys.has(key)) return;
    keys.add(key);
    const quantity = Number(item.quantity || 1);
    const unitPrice = Number(item.price || item.unit_price || 0);
    receipt.items.push({
      name: item.name || 'Medicine',
      image: item.image || '',
      pharmacyName: item.pharmacyName || item.pharmacyLabel || '',
      pharmacy_id: item.pharmacyId,
      medicine_id: item.medicineId,
      quantity,
      unit_price: unitPrice,
      line_total: unitPrice * quantity,
    });
  });
  receipt.item_count = receipt.items.length;
  const pharmacyNames = [...new Set(receipt.items.map(item => String(item.pharmacyName || '').trim()).filter(Boolean))];
  if(pharmacyNames.length) receipt.pharmacy_name = pharmacyNames.join(', ');
  if(typeof checkoutPriceTotals === 'function'){
    const totals = checkoutPriceTotals(cartSubtotal());
    receipt.total_amount = totals.total;
    receipt.down_payment = totals.downPayment;
  }
  return receipt;
}

function receiptCourierSvg(){
  return `<svg class="order-receipt-hero-art" viewBox="0 0 160 160" aria-hidden="true">
    <circle cx="80" cy="80" r="72" fill="#e7f6f2"/>
    <circle cx="80" cy="80" r="54" fill="#068a78"/>
    <path d="M52 82.5 70 100l38-46" fill="none" stroke="#fff" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>`;
}

function renderTrackingPage(order){
  const root = document.getElementById('tracking-page-root');
  if(!root) return;

  if(!order){
    try{
      order = JSON.parse(sessionStorage.getItem('residence_last_order') || 'null');
    }catch(e){
      order = null;
    }
  }

  if(!order){
    root.innerHTML = '<div class="card card-pad" style="max-width:640px;"><p class="hint">Place an order to see live tracking here.</p></div>';
    return;
  }

  order = combineOrdersForReceipt(order.orders, order);
  const titleEl = document.getElementById('page-title');
  if(titleEl) titleEl.textContent = 'Order Status';

  const fallbackImage = cartMedicineFallbackImage();
  const itemCards = (order.items || []).map(item => `
    <article class="order-receipt-item">
      <img src="${escapeHtml(item.image || fallbackImage)}" alt="">
      <div class="order-receipt-item-copy">
        <span>${escapeHtml(item.pharmacyName || order.pharmacy_name || 'Medicine')}</span>
        <strong>${escapeHtml(item.name)}</strong>
        <em>Qty : ${escapeHtml(item.quantity)}</em>
      </div>
      <b>${formatMoney(item.line_total ?? (item.unit_price * item.quantity))}</b>
    </article>
  `).join('');

  const related = Array.isArray(order.related_order_numbers) ? order.related_order_numbers.filter(Boolean) : [];
  const orderIdLabel = order.order_numbers_label || related.join(', ') || order.order_number || '—';
  const trackOrderNumber = related.length > 1 ? '' : (order.order_number || '');

  root.innerHTML = `
    <div class="order-receipt">
      <div class="order-receipt-hero">
        ${receiptCourierSvg()}
      </div>
      <div class="order-receipt-items">${itemCards || '<p class="hint">No items on this receipt.</p>'}</div>
      <section class="order-receipt-summary">
        <h3>Order Summary</h3>
        <div class="order-receipt-row"><span>${related.length > 1 ? 'Order IDs' : 'Order ID'}</span><b>${escapeHtml(orderIdLabel)}</b></div>
        <div class="order-receipt-row"><span>${related.length > 1 ? 'Pickup pharmacies' : 'Pickup pharmacy'}</span><b>${escapeHtml(order.pharmacy_name || '—')}</b></div>
        <div class="order-receipt-row"><span>Pickup address</span><b>${escapeHtml(order.pharmacy_address || order.customer_address || 'Pharmacy counter')}</b></div>
        <div class="order-receipt-row"><span>Amount paid</span><b>${formatMoney(order.down_payment || 0)}</b></div>
        <div class="order-receipt-row"><span>Balance due at pickup</span><b>${formatMoney((order.total_amount || 0) - (order.down_payment || 0))}</b></div>
      </section>
      <div class="order-receipt-qr"><img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodeURIComponent(location.origin + location.pathname + '?track=' + (order.order_number || ''))}" alt="Order QR code"><span>Scan to track this order</span></div>
      <div class="order-receipt-actions"><button type="button" class="order-receipt-track-btn" onclick="openReceiptOrderDetails('${escapeHtml(trackOrderNumber)}')">Track order</button><button type="button" class="order-receipt-download-btn" onclick="downloadOrderReceipt()">Download receipt</button></div>
      <div class="order-receipt-live" id="order-receipt-live" hidden>
        <div class="pinline">
          <div class="p-step current">
            <div class="p-track"><div class="p-node"><svg class="icon" style="width:16px;height:16px;" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg></div><div class="p-line"></div></div>
            <div class="p-content"><div class="p-title">Order confirmed</div><div class="p-desc">Down payment received. ${escapeHtml(order.pharmacy_name || 'The pharmacy')} is preparing your order.</div></div>
          </div>
          <div class="p-step pending">
            <div class="p-track"><div class="p-node"><svg class="icon" style="width:16px;height:16px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg></div></div>
            <div class="p-content"><div class="p-title">Ready for pickup</div><div class="p-desc">You will be notified when your medicines are ready.</div></div>
          </div>
        </div>
      </div>
    </div>`;
}

function downloadOrderReceipt(){
  const receipt = document.querySelector('.order-receipt');
  if(!receipt) return;
  const html = '<!doctype html><html><head><meta charset="utf-8"><title>AddToMar Receipt</title><style>body{font-family:Arial,sans-serif;max-width:720px;margin:30px auto;color:#152228;padding:20px}.order-receipt{border:1px solid #dce7e5;border-radius:12px;padding:24px}.order-receipt-hero{text-align:center;margin-bottom:20px}.order-receipt-hero svg{width:100px;height:100px}.order-receipt-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #edf1f0}.order-receipt-item img{width:52px;height:52px;object-fit:contain}.order-receipt-summary{margin-top:20px}.order-receipt-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #edf1f0}</style></head><body>'+receipt.outerHTML+'</body></html>';
  const url = URL.createObjectURL(new Blob([html], {type:'text/html'}));
  const link = document.createElement('a'); link.href = url; link.download = 'addtomar-receipt.html'; link.click(); URL.revokeObjectURL(url);
}

let pendingOrderDetailId = '';

function openReceiptOrderDetails(orderNumber){
  let order = null;
  try{
    order = JSON.parse(sessionStorage.getItem('residence_last_order') || 'null');
  }catch(e){
    order = null;
  }
  if(order && (!orderNumber || String(order.order_number) === String(orderNumber) || String(order.id) === String(orderNumber))){
    rememberResidenceOrder(order);
    orderNumber = order.order_number || orderNumber;
  }
  if(!orderNumber){
    go('orders');
    return;
  }
  pendingOrderDetailId = String(orderNumber);
  go('orders');
}

function revealOrderTracking(){
  openReceiptOrderDetails();
}

let checkoutPaying = false;

function checkoutPayControls(){
  return {
    payBtn: document.getElementById('checkout-gcash-pay'),
    gcashBtn: document.querySelector('#checkout-payment-card .pd-gcash'),
    hiddenBtn: document.querySelector('.paymongo-wallet-btn'),
  };
}

function checkoutPayCheckSvg(){
  return '<svg class="pd-pay-check" viewBox="0 0 52 52" aria-hidden="true"><circle class="pd-pay-check-ring" cx="26" cy="26" r="23" fill="none"/><path class="pd-pay-check-mark" fill="none" d="M15.5 26.8l7.1 7.1 14.2-14.6"/></svg>';
}

function checkoutPayButtonAmount(){
  const totals = checkoutPriceTotals(cartSubtotal());
  return formatMoney(totals.downPayment);
}

function renderCheckoutPayButton(){
  const payBtn = document.getElementById('checkout-gcash-pay');
  const wrap = payBtn?.closest('.pd-pay-wrap');
  if(!payBtn) return;
  payBtn.classList.remove('is-verifying', 'is-success');
  if(window.paymongoPaid){
    payBtn.hidden = true;
    if(wrap) wrap.hidden = true;
    return;
  }
  payBtn.hidden = false;
  if(wrap) wrap.hidden = false;
  payBtn.disabled = !!checkoutPaying;
  payBtn.setAttribute('aria-busy', checkoutPaying ? 'true' : 'false');
  payBtn.textContent = 'PAY ' + checkoutPayButtonAmount();
  payBtn.style.pointerEvents = checkoutPaying ? 'none' : 'auto';
}

let checkoutVerifyHideTimer = null;

function setCheckoutVerifyOverlay(mode){
  const overlay = document.getElementById('checkout-verify-overlay');
  if(!overlay) return;
  const busy = overlay.querySelector('.checkout-verify-busy');
  const done = overlay.querySelector('.checkout-verify-done');
  if(checkoutVerifyHideTimer){
    clearTimeout(checkoutVerifyHideTimer);
    checkoutVerifyHideTimer = null;
  }
  if(!mode){
    overlay.hidden = true;
    overlay.classList.remove('is-success');
    document.body.classList.remove('checkout-verifying');
    return;
  }
  overlay.hidden = false;
  document.body.classList.add('checkout-verifying');
  const success = mode === 'success';
  overlay.classList.toggle('is-success', success);
  if(busy) busy.hidden = success;
  if(done) done.hidden = !success;
  if(success){
    checkoutVerifyHideTimer = setTimeout(function(){ setCheckoutVerifyOverlay(''); }, 1600);
  }
}

function setCheckoutPayBusy(paying){
  checkoutPaying = !!paying;
  const { gcashBtn, hiddenBtn } = checkoutPayControls();
  renderCheckoutPayButton();
  if(checkoutPaying && !window.paymongoPaid) setCheckoutVerifyOverlay('verifying');
  else if(!window.paymongoPaid) setCheckoutVerifyOverlay('');
  if(gcashBtn){
    gcashBtn.disabled = checkoutPaying || !!window.paymongoPaid;
    gcashBtn.style.pointerEvents = (checkoutPaying || window.paymongoPaid) ? 'none' : 'auto';
  }
  if(hiddenBtn) hiddenBtn.disabled = checkoutPaying || !!window.paymongoPaid;
}

function checkoutPaymentDetails(){
  return {
    name: document.getElementById('payment-payer-name')?.value.trim() || '',
    phone: document.getElementById('payment-payer-number')?.value.trim() || '',
    address: document.getElementById('payment-payer-address')?.value.trim() || '',
    email: document.getElementById('payment-payer-email')?.value.trim() || '',
  };
}

function markCheckoutPaymentField(id, invalid){
  document.getElementById(id)?.classList.toggle('is-invalid', !!invalid);
}

function validateCheckoutPaymentDetails(){
  const details = checkoutPaymentDetails();
  const emailOk = /@gmail\.com$/i.test(details.email);
  markCheckoutPaymentField('payment-payer-name', !details.name);
  markCheckoutPaymentField('payment-payer-number', !details.phone);
  markCheckoutPaymentField('payment-payer-address', !details.address);
  markCheckoutPaymentField('payment-payer-email', !details.email || !emailOk);
  if(!details.name){
    toast('Enter your full name.');
    document.getElementById('payment-payer-name')?.focus();
    return null;
  }
  if(!details.phone){
    toast('Enter your contact number.');
    document.getElementById('payment-payer-number')?.focus();
    return null;
  }
  if(!details.address){
    toast('Enter your address.');
    document.getElementById('payment-payer-address')?.focus();
    return null;
  }
  if(!details.email){
    toast('Enter your email for the receipt.');
    document.getElementById('payment-payer-email')?.focus();
    return null;
  }
  if(!emailOk){
    toast('Enter your Gmail address so the receipt can be sent to your account.');
    document.getElementById('payment-payer-email')?.focus();
    return null;
  }
  return details;
}

async function startPayMongoPayment(){
  if(window.paymongoPaid || checkoutPaying) return;
  const details = validateCheckoutPaymentDetails();
  if(!details) return;

  const checkoutItems=cartCheckoutItems();
  if(checkoutItems.length===0){ toast('Check the items you want to check out.'); return; }
  const totals = checkoutPriceTotals(cartSubtotal());
  const amount = Math.max(totals.downPayment, 20);
  setCheckoutPayBusy(true);
  closePayMongoPopup();
  const form=new FormData();
  form.append('amount', String(amount));
  form.append('method', 'card');
  form.append('email', details.email);
  form.append('name', details.name);
  form.append('phone', details.phone);
  form.append('address', details.address);
  form.append('pharmacy_id', checkoutPharmacyId());
  form.append('pickup_date', document.getElementById('checkout-pickup-date')?.value || new Date().toISOString().slice(0,10));
  form.append('pickup_time', '');
  form.append('items', JSON.stringify(checkoutItemPayload()));
  appendCheckoutPrescriptions(form);
  try{
    const cfg = window.RESIDENCE_CONFIG || {};
    const checkoutUrl = cfg.paymongoCheckoutUrl || '';
    if(!checkoutUrl){
      toast('Payment is not configured. Refresh the page and try again.');
      setCheckoutPayBusy(false);
      return;
    }
    const r=await fetch(checkoutUrl,{method:'POST',body:form,credentials:'same-origin'});
    const text=await r.text();
    let d={};
    try{
      d=JSON.parse(text);
    }catch(parseErr){
      const plain=String(text||'').replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim().slice(0,180);
      throw new Error(plain || ('Payment could not start (HTTP ' + r.status + ').'));
    }
    if(d.ok && (d.status === 'succeeded' || d.order)){
      closePayMongoPopup();
      paymongoIntentId = d.intent_id || paymongoIntentId;
      markPayMongoAuthorized(d);
      return;
    }
    if(d.ok&&d.checkout_url){
      paymongoPopup = window.open(d.checkout_url, 'addtomar-paymongo', payMongoPopupFeatures());
      if(!paymongoPopup){
        toast('Allow popups for AddToMar so PayMongo can open.');
        setCheckoutPayBusy(false);
        return;
      }
      openPayMongoAuthModal(d.checkout_url, d.intent_id || '');
      return;
    }
    closePayMongoPopup();
    toast(d.error||'Unable to open card authorization.');
    setCheckoutPayBusy(false);
  }catch(err){
    closePayMongoPopup();
    toast(err?.message || 'Unable to open card authorization.');
    setCheckoutPayBusy(false);
  }
}
function closeGcashReview(){ closePayMongoAuthModal(); }
async function continueToPayMongo(){ return startPayMongoPayment(); }

let paymongoPollTimer = null;
let paymongoIntentId = '';
let paymongoCompleting = false;

function setPayModalOpen(id, open){
  const modal = document.getElementById(id);
  if(!modal) return;
  modal.hidden = !open;
}

function stopPayMongoPoll(){
  if(paymongoPollTimer){
    clearInterval(paymongoPollTimer);
    paymongoPollTimer = null;
  }
}

let paymongoAuthUrl = '';
let paymongoPopup = null;

function payMongoPopupFeatures(){
  const width = 480;
  const height = 720;
  const left = Math.max(0, Math.round((window.screenX || 0) + ((window.outerWidth || 980) - width) / 2));
  const top = Math.max(0, Math.round((window.screenY || 0) + ((window.outerHeight || 800) - height) / 2));
  return `popup=yes,width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`;
}

function closePayMongoPopup(){
  try {
    if(paymongoPopup && !paymongoPopup.closed) paymongoPopup.close();
  } catch (e) {}
  paymongoPopup = null;
}

function reopenPayMongoAuthPopup(){
  if(!paymongoAuthUrl) return;
  closePayMongoPopup();
  paymongoPopup = window.open(paymongoAuthUrl, 'addtomar-paymongo', payMongoPopupFeatures());
  if(!paymongoPopup){
    toast('Allow popups for AddToMar, then click Open payment popup.');
  }else{
    paymongoPopup.focus();
  }
}

function closePayMongoAuthModal(){
  stopPayMongoPoll();
  closePayMongoPopup();
  paymongoAuthUrl = '';
  setPayModalOpen('paymongo-auth-modal', false);
}

function payMongoPendingStatus(status){
  return ['awaiting_next_action', 'processing', 'pending', 'awaiting_payment_method'].includes(String(status || ''));
}

function markPayMongoAuthorized(result){
  stopPayMongoPoll();
  closePayMongoPopup();
  paymongoAuthUrl = '';
  setPayModalOpen('paymongo-auth-modal', false);
  applyPaidPayment(result || {});
}

function openPayMongoAuthModal(url, intentId){
  paymongoIntentId = intentId || '';
  paymongoAuthUrl = url || '';
  setCheckoutPaymentStatus('pending');
  setPayModalOpen('paymongo-auth-modal', true);
  if(!paymongoPopup || paymongoPopup.closed){
    reopenPayMongoAuthPopup();
  }else{
    paymongoPopup.focus();
  }
  stopPayMongoPoll();
  if(paymongoIntentId){
    paymongoPollTimer = setInterval(() => {
      try {
        const href = String(paymongoPopup?.location?.href || '');
        if(href.includes('payment-complete.php')){
          completePayMongoPayment(paymongoIntentId, true);
          return;
        }
      } catch (e) {}
      completePayMongoPayment(paymongoIntentId, true);
    }, 1000);
  }
}

async function completePayMongoPayment(intentId, fromPoll){
  if(paymongoCompleting) return;
  const id = intentId || paymongoIntentId;
  if(!id) return;
  const cfg = window.RESIDENCE_CONFIG || {};
  const url = (cfg.paymongoCompleteUrl || '') + '?payment_intent_id=' + encodeURIComponent(id);
  if(!cfg.paymongoCompleteUrl){
    toast('Payment confirmation is not configured. Refresh the page and try again.');
    paymongoCompleting = false;
    return;
  }
  paymongoCompleting = true;
  try{
    const r = await fetch(url, { credentials: 'same-origin' });
    const d = await r.json().catch(() => ({}));
    if(payMongoPendingStatus(d.status) || d.status === 'error') return;
    if(d.ok && (d.status === 'succeeded' || d.order)){
      markPayMongoAuthorized(d);
      return;
    }
    const hardFail = ['failed', 'cancelled', 'expired', 'order_failed', 'missing'];
    if(!hardFail.includes(String(d.status || ''))){
      return;
    }
    stopPayMongoPoll();
    closePayMongoPopup();
    setPayModalOpen('paymongo-auth-modal', false);
    setCheckoutPaymentStatus('failed');
    setCheckoutPayBusy(false);
    toast(d.error || 'Payment was not completed. Click Pay to try again.');
  }catch(err){
    if(!fromPoll) toast('Could not confirm payment.');
  }finally{
    paymongoCompleting = false;
  }
}

let checkoutPaymentStatus = 'pending';
let checkoutPaidSignature = '';

function resetCheckoutPaymentSession(){
  stopPayMongoPoll();
  closePayMongoPopup();
  paymongoIntentId = '';
  paymongoCompleting = false;
  checkoutPaying = false;
  paymongoAuthUrl = '';
  checkoutPaidSignature = '';
  if(window.RESIDENCE_CONFIG) window.RESIDENCE_CONFIG.pendingPaymentIntent = '';
  setPayModalOpen('paymongo-auth-modal', false);
  const walletBtn = document.querySelector('.paymongo-wallet-btn');
  if(walletBtn){
    walletBtn.disabled = false;
    walletBtn.textContent = 'Pay with GCash';
  }
  setCheckoutPaymentStatus('pending');
  setCheckoutPayBusy(false);
}

function syncCheckoutConfirmButton(){
  const placeBtn = document.getElementById('checkout-place-order-btn');
  if(!placeBtn) return;
  const hasItems = cartCheckoutItems().length > 0;
  const paid = checkoutPaymentStatus === 'paid' || checkoutPaymentStatus === 'verified' || checkoutPaymentStatus === 'succeeded' || !!window.paymongoPaid;
  placeBtn.hidden = !paid;
  placeBtn.disabled = !hasItems || !paid;
}

function startGCashFromCard(){
  if(window.paymongoPaid || checkoutPaying) return;
  if(!validateCheckoutPaymentDetails()) return;
  closePayMongoAuthModal();
  setCheckoutPaymentStatus('pending');
  startPayMongoPayment();
}

function setCheckoutPaymentStatus(status){
  checkoutPaymentStatus = status || 'pending';
  window.paymongoPaid = checkoutPaymentStatus === 'paid' || checkoutPaymentStatus === 'verified' || checkoutPaymentStatus === 'succeeded';
  const card = document.getElementById('checkout-payment-card');
  card?.classList.toggle('is-paid', !!window.paymongoPaid);
  renderCheckoutPayButton();
  if(window.paymongoPaid) setCheckoutVerifyOverlay('success');
  else if(!checkoutPaying) setCheckoutVerifyOverlay('');
  syncCheckoutConfirmButton();
}

function applyPaidPayment(d){
  window.paymongoPaid = true;
  checkoutPaying = false;
  checkoutPaidSignature = cartItemsSignature(cartCheckoutItems());
  if(d?.intent_id) paymongoIntentId = d.intent_id;
  setCheckoutPaymentStatus('paid');
  const btn = document.querySelector('.paymongo-wallet-btn');
  if(btn){
    btn.disabled = true;
    btn.textContent = 'Payment authorized';
  }
  toast('Payment confirmed. Click Complete Order to send it to the pharmacy.');
}

function showPayMongoReceipt(d){
  applyPaidPayment(d);
}

function printPayMongoReceipt(){
  document.getElementById('paymongo-receipt-frame')?.contentWindow?.print();
}

function clearPaidCheckoutItems(){
  if(window.buyNowCheckout){
    window.buyNowCheckout = null;
  }else{
    cart.items = cart.items.filter(item => !isCartItemSelected(item));
    if(cart.items.length === 0){
      cart.pharmacyId = '';
      cart.pharmacyName = '';
      cart.pharmacyLabel = '';
    }
    saveCart();
  }
  syncCartCount();
  renderCartPage();
  resetCheckoutPaymentSession();
}

function closePayMongoReceiptModal(){
  setPayModalOpen('paymongo-receipt-modal', false);
  sessionStorage.removeItem('residence_paid_clear');
  clearPaidCheckoutItems();
  go('tracking');
}

window.addEventListener('message', (event) => {
  if(event.data?.type !== 'addtomar-paymongo-complete') return;
  const id = event.data.payment_intent_id || paymongoIntentId;
  if(id) paymongoIntentId = id;
  if(event.data.ok && (event.data.status === 'succeeded' || event.data.order)){
    markPayMongoAuthorized(event.data);
    return;
  }
  completePayMongoPayment(id, true);
});

async function placeOrder(){
  const checkoutItems = cartCheckoutItems();
  if(checkoutItems.length === 0){
    toast('Check the items you want to check out.');
    go('cart');
    return;
  }
  if(!window.paymongoPaid){
    toast('Authorize payment first. Click Pay to complete GCash, then confirm your order.');
    return;
  }
  const details = validateCheckoutPaymentDetails();
  if(!details) return;

  const cfg = window.RESIDENCE_CONFIG || {};
  const pickupDate = document.getElementById('checkout-pickup-date')?.value || new Date().toISOString().slice(0, 10);
  const pickupTime = '';
  const paymentProof = document.getElementById('checkout-payment-proof');
  const placeBtn = document.getElementById('checkout-place-order-btn');

  const form = new FormData();
  form.append('pharmacy_id', checkoutPharmacyId());
  form.append('pickup_date', pickupDate);
  form.append('pickup_time', '');
  form.append('payment_method', document.querySelector('input[name="payment_method"]:checked')?.value || 'gcash');
  form.append('payment_payer_name', details.name);
  form.append('payment_payer_number', details.phone);
  form.append('payment_payer_address', details.address);
  form.append('payment_receipt_email', details.email);
  form.append('items', JSON.stringify(checkoutItemPayload()));
  if(paymongoIntentId) form.append('payment_intent_id', paymongoIntentId);
  if(paymentProof?.files?.length) form.append('payment_proof', paymentProof.files[0]);
  appendCheckoutPrescriptions(form);

  if(placeBtn){
    placeBtn.disabled = true;
    placeBtn.textContent = 'Placing order...';
  }

  try{
    const response = await fetch(cfg.placeOrderUrl, { method:'POST', body: form });
    const result = await response.json();
    if(!result.ok){
      toast(result.error || 'Could not place your order.');
      return;
    }

    const receiptOrder = combineOrdersForReceipt(result.orders, result.order);
    const childOrders = (result.orders || []).map(order => presentResidenceOrder(order, { skipStores:true }));
    sessionStorage.setItem('residence_last_order', JSON.stringify(receiptOrder));
    rememberResidenceOrder({
      ...receiptOrder,
      stores: childOrders,
      is_group: childOrders.length > 1,
      store_count: childOrders.length || 1,
    });
    refreshResidenceOrders();
    if(window.buyNowCheckout){
      window.buyNowCheckout = null;
    }else{
      cart.items = cart.items.filter(item => !isCartItemSelected(item));
      if(cart.items.length === 0){
        cart.pharmacyId = '';
        cart.pharmacyName = '';
        cart.pharmacyLabel = '';
      }
      saveCart();
    }
    renderCartPage();
    renderCheckoutPage();
    renderTrackingPage(receiptOrder);
    go('tracking');
    toast('Order placed — sent to ' + (receiptOrder.pharmacy_name || 'pharmacy'));
  }catch(e){
    toast('Could not place your order. Please try again.');
  }finally{
    if(placeBtn){
      placeBtn.disabled = false;
      placeBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8v4H8z"/><path d="M6 7h12v14H6z"/><path d="M9 12h6M9 16h4"/></svg> Complete Order <span aria-hidden="true">›</span>';
    }
  }
}

document.addEventListener('click', (event) => {
  const noteBtn = event.target instanceof Element ? event.target.closest('.checkout-item-note-btn') : null;
  if(noteBtn){
    openCheckoutNoteModal(noteBtn.dataset.itemKey || '');
    return;
  }
  const preview = event.target instanceof Element ? event.target.closest('.checkout-item-note-preview') : null;
  if(preview){
    const rowBtn = preview.closest('.checkout-summary-item')?.querySelector('.checkout-item-note-btn');
    openCheckoutNoteModal(rowBtn?.dataset.itemKey || '');
    return;
  }
  if(event.target instanceof Element && event.target.id === 'checkout-note-backdrop'){
    closeCheckoutNoteModal();
  }
});
document.addEventListener('keydown', (event) => {
  if(event.key === 'Escape' && !document.getElementById('checkout-note-modal')?.hidden){
    closeCheckoutNoteModal();
  }
});
document.addEventListener('input', (event) => {
  const target = event.target;
  if(target instanceof HTMLTextAreaElement && target.id === 'checkout-note-field'){
    syncCheckoutNoteCount();
    return;
  }
  if(target instanceof HTMLTextAreaElement && target.classList.contains('checkout-item-note')){
    rememberCheckoutItemNote(target);
    return;
  }
  if(!(target instanceof HTMLInputElement)) return;
  if(['payment-payer-name','payment-payer-number','payment-payer-address','payment-payer-email'].includes(target.id)){
    target.classList.remove('is-invalid');
  }
});
document.addEventListener('change', (event) => {
  const target = event.target;
  if(!(target instanceof HTMLInputElement)) return;
  if(target.id === 'checkout-payment-proof'){
    const label = document.getElementById('checkout-payment-label');
    if(label) label.textContent = target.files?.[0]?.name || 'Required before placing your order';
  }
  if(target.classList.contains('checkout-prescription-file')){
    activePrescriptionItemKey = target.dataset.itemKey || '';
    applyPrescriptionFile(target, target.files?.[0] || null);
  }
});
let activePreviewCard = null;
let productDetailQty = 1;
let productDetailPrice = '';
let productPreviewReturnPage = '';
let productPreviewReturnPharmacyId = '';

function currentResidencePage(){
  return document.querySelector('.page.active')?.dataset.page || 'dashboard';
}

function catalogCardForPreview(card){
  if(!card) return null;
  if(card.closest('.med-catalog')) return card;
  const medicineId = String(card.dataset.medicineId || '');
  const pharmacyId = String(card.dataset.pharmacyId || '');
  if(!medicineId) return card;
  const catalogCards = Array.from(document.querySelectorAll('.med-catalog .med-card'));
  return catalogCards.find(item =>
    String(item.dataset.medicineId || '') === medicineId &&
    (!pharmacyId || String(item.dataset.pharmacyId || '') === pharmacyId)
  ) || card;
}

function getCartTarget(){
  return document.getElementById('sb-cart-target') || document.getElementById('bn-cart-target');
}

function bumpCartCount(){
  syncCartCount();
}

function flyToCart(fromBtn){
  const target = getCartTarget();
  if(!target || !fromBtn) return;

  const from = fromBtn.getBoundingClientRect();
  const to = target.getBoundingClientRect();
  const startX = from.left + from.width / 2;
  const startY = from.top + from.height / 2;
  const dx = to.left + to.width / 2 - startX;
  const dy = to.top + to.height / 2 - startY;

  const fly = document.createElement('div');
  fly.className = 'cart-fly';
  fly.textContent = '+1';
  fly.style.left = startX + 'px';
  fly.style.top = startY + 'px';
  document.body.appendChild(fly);

  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      fly.style.transform = `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) scale(0.4)`;
      fly.style.opacity = '0';
    });
  });

  const cleanup = () => fly.remove();
  fly.addEventListener('transitionend', cleanup, { once:true });
  setTimeout(cleanup, 900);

  target.classList.remove('cart-bump');
  void target.offsetWidth;
  target.classList.add('cart-bump');
}

function addToCart(btn){
  if(!btn) return;
  const card = btn.closest('.med-card');
  if(!addMedicineToCart(card, 1)) return;

  btn.classList.add('added');
  btn.textContent = 'Added';

  clearTimeout(btn._resetTimer);
  btn._resetTimer = setTimeout(() => {
    btn.classList.remove('added');
    btn.textContent = 'Add to cart';
  }, 1400);

  flyToCart(btn);
}

function buyNowFromCard(btn){
  if(!btn) return;
  const card = btn.closest('.med-card');
  const data = getMedicineCardData(card);
  if(!data || Number(data.medicineId) <= 0 || !data.pharmacyId){
    toast('This medicine is not available for ordering yet.');
    return;
  }
  window.buyNowCheckout = {
    pharmacyId: data.pharmacyId,
    pharmacyName: (getMarketplacePharmacy(data.pharmacyId)?.name || data.pharmacyName || data.pharmacyLabel || 'Pharmacy'),
    item: { ...data, quantity: 1, selected: true },
  };
  closeProductPreview({ skipPharmacyReturn: true });
  resetCheckoutPaymentSession();
  go('checkout');
  toast('Proceeding to checkout');
}
window.buyNowFromCard = buyNowFromCard;

function updateProductDetailAddLabel(){
  const label = document.getElementById('product-detail-add-label');
  if(!label) return;
  label.textContent = 'Add to cart';
}

function updateProductDetailBuyLabel(){
  const label = document.getElementById('product-detail-buy-label');
  if(!label) return;
  label.textContent = 'Buy now';
}

function productDetailQtyChange(delta){
  productDetailQty = Math.max(1, productDetailQty + delta);
  const qtyEl = document.getElementById('product-detail-qty');
  if(qtyEl) qtyEl.textContent = String(productDetailQty);
}

function renderProductDetailOptions(card){
  const bottleSection = document.getElementById('product-detail-bottle-section');
  const qtySection = document.getElementById('product-detail-qty-section');
  const optionsEl = document.getElementById('product-detail-size-options');
  const orderType = card.dataset.orderType || 'quantity';
  const sizes = (card.dataset.sizes || '').split(',').map(s => s.trim()).filter(Boolean);
  const isBottle = orderType === 'bottle' && sizes.length > 0;

  if(bottleSection) bottleSection.hidden = !isBottle;
  if(qtySection) qtySection.hidden = isBottle;

  if(!isBottle || !optionsEl){
    productDetailQty = 1;
    const qtyEl = document.getElementById('product-detail-qty');
    if(qtyEl) qtyEl.textContent = '1';
    return;
  }

  optionsEl.innerHTML = sizes.map((size, index) => `
    <label class="product-detail-option">
      <input type="radio" name="product-detail-size" value="${size.replace(/"/g, '&quot;')}"${index === 0 ? ' checked' : ''}>
      <span>${size}</span>
    </label>
  `).join('');
}

function renderRelatedProducts(card){
  const section = document.getElementById('product-detail-related');
  const grid = document.getElementById('product-detail-related-grid');
  const subEl = document.getElementById('product-detail-related-sub');
  const viewAll = document.getElementById('product-detail-related-view-all');
  const pharmacy = card.dataset.pharmacy || '';
  const currentSub = card.dataset.shopSub || '';
  const currentCategory = card.dataset.shopCategory || '';

  if(!section || !grid) return;

  const medicineId = String(card.dataset.medicineId || '');
  const pharmacyId = String(card.dataset.pharmacyId || '');
  const seen = new Set();
  const fromThisPharmacy = [];
  const fromOtherPharmacies = [];

  Array.from(document.querySelectorAll('.med-catalog .med-grid .med-card')).forEach(item => {
    const id = String(item.dataset.medicineId || '');
    if(id && seen.has(id)) return;
    const sameItem = item === card || (medicineId && id === medicineId);
    if(sameItem) return;
    if(id) seen.add(id);
    const samePharmacy = pharmacyId
      ? String(item.dataset.pharmacyId || '') === pharmacyId
      : item.dataset.pharmacy === pharmacy;
    if(samePharmacy) fromThisPharmacy.push(item);
    else fromOtherPharmacies.push(item);
  });

  const scoreCard = item =>
    (item.dataset.shopSub === currentSub ? 0 : 2) +
    (item.dataset.shopCategory === currentCategory ? 0 : 1);
  fromThisPharmacy.sort((a, b) => scoreCard(a) - scoreCard(b));
  fromOtherPharmacies.sort((a, b) => scoreCard(a) - scoreCard(b));

  const suggestions = [...fromThisPharmacy, ...fromOtherPharmacies].slice(0, 10);
  grid.innerHTML = '';

  if(suggestions.length === 0){
    section.hidden = true;
    if(subEl) subEl.textContent = '';
    if(viewAll){
      viewAll.hidden = true;
      viewAll.dataset.pharmacyId = '';
    }
    return;
  }

  section.hidden = false;
  const relatedPharmacyId = pharmacyId || suggestions[0]?.dataset?.pharmacyId || '';
  const mixedStores = suggestions.some(item => String(item.dataset.pharmacyId || '') !== pharmacyId);
  if(subEl){
    subEl.textContent = mixedStores && fromThisPharmacy.length < 10
      ? 'partner pharmacies'
      : pharmacy;
    subEl.dataset.pharmacyId = relatedPharmacyId;
  }
  if(viewAll){
    viewAll.hidden = !relatedPharmacyId;
    viewAll.dataset.pharmacyId = relatedPharmacyId;
  }

  suggestions.forEach(sourceCard => {
    grid.appendChild(buildRelatedProductCard(sourceCard));
  });
}

function buildRelatedProductCard(sourceCard){
  const item = sourceCard.cloneNode(true);
  item.classList.add('med-card--spotlight', 'med-card--related');
  item.classList.remove('is-search-hidden');
  item.hidden = false;
  item.removeAttribute('hidden');
  item.style.removeProperty('display');
  applySpotlightBadge(item);

  const open = () => openProductPreview(sourceCard);
  item.addEventListener('click', open);
  item.addEventListener('keydown', event => {
    if(event.key === 'Enter' || event.key === ' '){
      event.preventDefault();
      open();
    }
  });

  return item;
}

function populateProductDetail(card){
  const img = medicineCardImage(card);
  const name = medicineCardName(card);
  const generic = medicineCardGeneric(card);
  const metaLines = Array.from(card.querySelectorAll('.med-meta-line')).map(el => el.textContent.trim()).filter(Boolean);
  const category = card.querySelector('.med-cat')?.textContent.trim().replace(/^[^\w]+\s*/, '') || '';
  productDetailPrice = medicineCardPriceText(card);
  const desc = card.dataset.desc || `Available at a partner pharmacy near you. Use as directed for ${category.toLowerCase() || 'this medicine'}.`;

  const previewImg = document.getElementById('product-detail-img');
  if(previewImg){
    previewImg.src = img?.src || '';
    previewImg.alt = img?.alt || name;
  }

  const nameEl = document.getElementById('product-detail-name');
  if(nameEl) nameEl.textContent = name;

  const priceEl = document.getElementById('product-detail-price');
  if(priceEl) priceEl.textContent = productDetailPrice;

  const genericEl = document.getElementById('product-detail-generic');
  if(genericEl){
    genericEl.textContent = generic || 'Not specified';
    genericEl.hidden = false;
    const ingredientsWrap = genericEl.closest('#product-detail-ingredients-wrap');
    ingredientsWrap.hidden = !generic;
    ingredientsWrap.querySelector('.product-detail-detail-toggle')?.setAttribute('aria-expanded', 'true');
  }

  const metaEl = document.getElementById('product-detail-meta');
  if(metaEl){
    metaLines.splice(1);
    card.dataset.distance = '';
    const distance = card.dataset.distance ? `${card.dataset.distance} km away` : '';
    metaEl.textContent = [category, ...metaLines, distance].filter(Boolean).join(' · ');
  }

  const descEl = document.getElementById('product-detail-desc');
  if(descEl){
    descEl.textContent = desc;
    descEl.hidden = false;
    descEl.closest('.product-detail-detail-block')?.querySelector('.product-detail-detail-toggle')?.setAttribute('aria-expanded', 'true');
  }

  const badgesEl = document.getElementById('product-detail-badges');
  if(badgesEl){
    badgesEl.innerHTML = '';
    if(card.dataset.rx === '1' || card.classList.contains('med-card--rx')){
      const rx = document.createElement('span');
      rx.className = 'badge badge-amber';
      rx.textContent = 'Rx required';
      badgesEl.appendChild(rx);
    }
  }

  renderProductDetailOptions(card);

  const addBtn = document.getElementById('product-detail-add');
  if(addBtn){
    addBtn.classList.remove('added');
    addBtn.disabled = false;
  }
  const buyBtn = document.getElementById('product-detail-buy');
  if(buyBtn) buyBtn.disabled = false;
  updateProductDetailAddLabel();
  updateProductDetailBuyLabel();
  renderRelatedProducts(card);
}

let productPreviewOpening = false;

function setProductPreviewMode(isOpen){
  const catalog = document.getElementById('med-catalog');
  const detail = document.getElementById('product-detail');
  const relatedGrid = document.getElementById('product-detail-related-grid');
  const main = document.querySelector('.home-main');
  const dashboard = document.querySelector('.page[data-page="dashboard"]');

  main?.classList.toggle('is-product-open', isOpen);
  document.body.classList.toggle('is-product-open', isOpen);
  detail?.classList.toggle('is-visible', isOpen);
  if(isOpen) dashboard?.setAttribute('data-live-skip', '1');
  else dashboard?.removeAttribute('data-live-skip');

  if(catalog){
    catalog.hidden = false;
    catalog.removeAttribute('hidden');
    if('inert' in catalog) catalog.inert = false;
  }

  if(detail){
    detail.hidden = !isOpen;
    if(isOpen) detail.removeAttribute('hidden');
    else detail.setAttribute('hidden', '');
    if('inert' in detail) detail.inert = false;
  }

  if(!isOpen && relatedGrid) relatedGrid.innerHTML = '';

  if(typeof closeSearchSuggestions === 'function') closeSearchSuggestions();
  if(typeof closeCategoryMenus === 'function') closeCategoryMenus();
  if(typeof closeProfileMenu === 'function') closeProfileMenu();
}

function resetStuckProductPreview(){
  const detail = document.getElementById('product-detail');
  if(detail && !detail.hidden) return;
  setProductPreviewMode(false);
  activePreviewCard = null;
}

function openProductPreview(card){
  if(productPreviewOpening) return;
  const source = catalogCardForPreview(card);
  if(!source) return;
  productPreviewOpening = true;
  window.setTimeout(() => { productPreviewOpening = false; }, 0);

  activePreviewCard = source;
  const fromPage = currentResidencePage();
  if(fromPage === 'pharmacy-profile'){
    productPreviewReturnPage = 'pharmacy-profile';
    productPreviewReturnPharmacyId = activePharmacyProfileId || source.dataset.pharmacyId || '';
  }else if(fromPage !== 'dashboard'){
    productPreviewReturnPage = '';
    productPreviewReturnPharmacyId = '';
  }
  if(fromPage !== 'dashboard'){
    go('dashboard', { keepProductPreview: true });
  }

  const detail = document.getElementById('product-detail');
  if(!detail){
    productPreviewOpening = false;
    return;
  }

  populateProductDetail(source);
  setProductPreviewMode(true);

  const main = document.querySelector('.home-main');
  document.querySelector('.content')?.scrollTo({ top: 0, behavior: 'smooth' });
  main?.scrollTo({ top: 0, behavior: 'smooth' });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function closeProductPreview(options = {}){
  const returnPage = productPreviewReturnPage;
  const returnPharmacyId = productPreviewReturnPharmacyId;
  const skipPharmacyReturn = Boolean(options.skipPharmacyReturn);

  setProductPreviewMode(false);
  updateStoreBrowseView();

  activePreviewCard = null;
  productDetailQty = 1;
  productPreviewReturnPage = '';
  productPreviewReturnPharmacyId = '';

  if(!skipPharmacyReturn && returnPage === 'pharmacy-profile' && returnPharmacyId){
    openPharmacyProfile(returnPharmacyId);
  }
}

window.openProductPreview = openProductPreview;
window.closeProductPreview = closeProductPreview;
window.go = go;

document.addEventListener('pointerdown', function(event){
  const detail = document.getElementById('product-detail');
  const previewOpen = Boolean(detail && !detail.hidden && detail.classList.contains('is-visible'));
  if(previewOpen) return;
  const catalog = document.getElementById('med-catalog');
  if(catalog && catalog.inert) catalog.inert = false;
  if(detail && detail.inert) detail.inert = false;
  document.body.classList.remove('is-product-open');
  document.querySelector('.home-main')?.classList.remove('is-product-open');
  detail?.classList.remove('is-visible');
  document.querySelector('.page[data-page="dashboard"]')?.removeAttribute('data-live-skip');
}, true);

function addPreviewToCart(){
  if(!activePreviewCard) return;

  const previewBtn = document.getElementById('product-detail-add');
  const qty = productDetailQty;
  if(!addMedicineToCart(activePreviewCard, qty)) return;

  if(previewBtn) flyToCart(previewBtn);
  if(!previewBtn) return;

  previewBtn.classList.add('added');
  const label = document.getElementById('product-detail-add-label');
  if(label) label.textContent = qty > 1 ? `added ${qty} to cart` : 'added to cart';

  clearTimeout(previewBtn._resetTimer);
  previewBtn._resetTimer = setTimeout(() => {
    previewBtn.classList.remove('added');
    updateProductDetailAddLabel();
  }, 1400);
}

function buyNowFromPreview(){
  if(!activePreviewCard) return;

  const qty = productDetailQty;
  const data = getMedicineCardData(activePreviewCard);
  if(!data) return;

  // Buy now uses a temporary checkout item and never changes the saved cart.
  window.buyNowCheckout = { pharmacyId:data.pharmacyId, pharmacyName:(getMarketplacePharmacy(data.pharmacyId)?.name || data.pharmacyName || data.pharmacyLabel || 'Pharmacy'), item:{ ...data, quantity:qty, selected:true } };
  closeProductPreview({ skipPharmacyReturn: true });
  resetCheckoutPaymentSession();
  go('checkout');
  toast(qty > 1 ? `${qty} items added — proceed to checkout` : 'Proceeding to checkout');
}

function initPharmacyMarquee(){
  const track = document.getElementById('pharmacy-logo-track');
  const sets = track ? track.querySelectorAll('.home-pharmacy-mall-set') : [];
  if(!track || !sets.length) return;
  if(window.matchMedia('(max-width: 760px)').matches){
    track.style.setProperty('--marquee-duration', '0s');
    return;
  }
  const first = sets[0];
  const pixelsPerSecond = 42;
  const duration = Math.max(18, Math.round((first.scrollWidth || 480) / pixelsPerSecond));
  track.style.setProperty('--marquee-duration', duration + 's');
}

function scrollPharmacyCarousel(direction){
  const track = document.getElementById('pharmacy-logo-track');
  if(!track || track.classList.contains('is-marquee')) return;

  const slides = track.querySelectorAll('.home-pharmacy-mall-slide');
  if(!slides.length) return;

  if(typeof window.pharmacyCarouselIndex !== 'number') window.pharmacyCarouselIndex = 0;

  window.pharmacyCarouselIndex = Math.max(
    0,
    Math.min(slides.length - 1, window.pharmacyCarouselIndex + direction)
  );

  track.style.transform = `translateX(-${window.pharmacyCarouselIndex * 100}%)`;
  updatePharmacyCarouselNav();
}

function updatePharmacyCarouselNav(){
  const track = document.getElementById('pharmacy-logo-track');
  const prevBtn = document.getElementById('pharmacy-logo-prev');
  const nextBtn = document.getElementById('pharmacy-logo-next');
  if(!track) return;

  const slides = track.querySelectorAll('.home-pharmacy-mall-slide');
  const index = typeof window.pharmacyCarouselIndex === 'number' ? window.pharmacyCarouselIndex : 0;
  const lastIndex = Math.max(slides.length - 1, 0);

  if(prevBtn){
    prevBtn.hidden = slides.length <= 1 || index <= 0;
    prevBtn.disabled = index <= 0;
  }

  if(nextBtn){
    nextBtn.hidden = slides.length <= 1;
    nextBtn.disabled = index >= lastIndex;
  }
}

function updateProductCarouselNav(wrap){
  const track = wrap.querySelector('.home-spotlight-carousel');
  const prevBtn = wrap.querySelector('.home-carousel-prev');
  const nextBtn = wrap.querySelector('.home-carousel-next');
  if(!track) return;

  const maxScroll = Math.max(track.scrollWidth - track.clientWidth, 0);
  const atStart = track.scrollLeft <= 4;
  const atEnd = track.scrollLeft >= maxScroll - 4;

  if(prevBtn){
    prevBtn.disabled = atStart;
    prevBtn.hidden = maxScroll <= 0;
  }

  if(nextBtn){
    nextBtn.disabled = atEnd;
    nextBtn.hidden = maxScroll <= 0;
  }
}

function scrollProductCarousel(wrap, direction){
  const track = wrap.querySelector('.home-spotlight-carousel');
  if(!track) return;

  const card = track.querySelector('.med-card--spotlight');
  const gap = 16;
  const amount = card ? (card.offsetWidth + gap) * 2 : track.clientWidth * 0.8;
  track.scrollBy({ left: direction * amount, behavior: 'smooth' });
}

function initProductCarousels(){
  document.querySelectorAll('[data-carousel-wrap]').forEach(wrap => {
    if(wrap._carouselReady) return;
    wrap._carouselReady = true;

    const track = wrap.querySelector('.home-spotlight-carousel');
    const prevBtn = wrap.querySelector('.home-carousel-prev');
    const nextBtn = wrap.querySelector('.home-carousel-next');
    if(!track) return;

    const refresh = () => updateProductCarouselNav(wrap);
    prevBtn?.addEventListener('click', () => scrollProductCarousel(wrap, -1));
    nextBtn?.addEventListener('click', () => scrollProductCarousel(wrap, 1));
    track.addEventListener('scroll', refresh, { passive: true });
    window.addEventListener('resize', refresh);
    refresh();
  });
}

function go(pageId, options = {}){
  if(!options.keepProductPreview){
    closeProductPreview({ skipPharmacyReturn: true });
  }
  if(pageId === 'browse') pageId = 'dashboard';
  document.body.classList.toggle('is-pharmacy-profile-page', pageId === 'pharmacy-profile');
  if(pageId !== 'pharmacy-profile'){
    setPharmacyProfileEmpty(false);
  }

  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  const target = document.querySelector(`.page[data-page="${pageId}"]`);
  if(target) target.classList.add('active');
  const titleEl = document.getElementById('page-title');
  if(titleEl) titleEl.textContent = pageTitles[pageId] || 'AddToMar';

  document.querySelectorAll('.topbar-nav-item[data-nav]').forEach(i=>i.classList.remove('active'));
  const topNav = document.querySelector(`.topbar-nav-item[data-nav="${pageId}"]`);
  if(topNav) topNav.classList.add('active');

  document.querySelector('.content').scrollTop = 0;
  window.scrollTo({top:0, behavior:'smooth'});
  updateMedicineSearchPlaceholder();

  if(pageId === 'locator'){
    setTimeout(initLocatorMap, 120);
  }

  if(pageId === 'dashboard'){
    updateStoreBrowseView();
    if(!options.keepProductPreview){
      filterMedicines(document.getElementById('medicine-search')?.value || '', true);
    }
  }

  if(pageId === 'cart'){
    renderCartPage();
  }

  if(pageId === 'checkout'){
    renderCheckoutPage();
  }

  if(pageId === 'tracking'){
    renderTrackingPage();
  }

  if(pageId === 'orders'){
    bindResidenceOrdersTools();
    renderResidenceOrders();
    if(pendingOrderDetailId){
      const id = pendingOrderDetailId;
      pendingOrderDetailId = '';
      viewOrder(id);
    }else{
      closeOrderDetail();
    }
  }
}

function goHome(){
  catalogCollection = '';
  activeShopCategory = '';
  activeShopSub = '';
  activePharmacyFilter = '';
  activePharmacyName = '';
  activePharmacyAccountId = '';
  const input = document.getElementById('medicine-search');
  if(input) input.value = '';
  closeSearchSuggestions();
  updateCategoryNavState();
  updateStoreBrowseView();
  go('dashboard');
  filterMedicines('');
  document.querySelector('.home-main')?.scrollTo({ top:0, behavior:'smooth' });
}

function toast(msg){
  const t = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msg;
  t.classList.add('show');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(()=>t.classList.remove('show'), 2600);
}

function selectPharmacy(el, id){
  document.querySelectorAll('.pharm-card').forEach(c=>c.classList.remove('selected'));
  el.classList.add('selected');
  document.querySelectorAll('.pharmacy-map-marker span').forEach(marker => {
    marker.classList.toggle('is-selected', String(marker.dataset.pharmacyId) === String(id));
  });
}

function qtyChange(btn, delta){
  const wrap = btn.closest('.qty-ctrl');
  const qEl = wrap.querySelector('.qn');
  let q = parseInt(qEl.textContent) + delta;
  if(q < 1) q = 1;
  qEl.textContent = q;
}

function rememberResidenceOrder(order){
  if(!order) return;
  const presented = presentResidenceOrder(order);
  if(!presented.order_number) return;
  const list = window.RESIDENCE_CONFIG.orders = Array.isArray(window.RESIDENCE_CONFIG?.orders) ? window.RESIDENCE_CONFIG.orders : [];
  const related = new Set([
    String(presented.order_number),
    ...((presented.related_order_numbers || []).map(String)),
    ...((presented.stores || []).map(store => String(store.order_number || ''))),
  ].filter(Boolean));
  for(let index = list.length - 1; index >= 0; index -= 1){
    const item = list[index];
    const ids = [item.order_number, ...(item.related_order_numbers || []), ...((item.stores || []).map(store => store.order_number))].map(String);
    if(ids.some(id => related.has(id))) list.splice(index, 1);
  }
  list.unshift(presented);
  renderResidenceOrders();
}

function residenceOrderVatBreakdown(items, totalAmount, storedVat, storedSubtotal){
  const itemSubtotal = roundMoney((items || []).reduce((sum, item) => sum + Number(item.line_total || 0), 0));
  let subtotal = roundMoney(storedSubtotal);
  let vat = roundMoney(storedVat);
  const total = roundMoney(totalAmount);
  if(subtotal <= 0) subtotal = itemSubtotal;
  if(vat <= 0){
    vat = (subtotal > 0 && total >= subtotal)
      ? roundMoney(total - subtotal)
      : roundMoney(subtotal * CHECKOUT_VAT_RATE);
  }
  return { subtotal, vat, total };
}

function presentResidenceOrder(order, options = {}){
  const status = String(order.status || 'pending');
  const meta = residenceOrderStatusMeta(status);
  const created = order.created_at ? new Date(String(order.created_at).replace(' ', 'T')) : new Date();
  const items = Array.isArray(order.items) ? order.items.map(item => {
    const quantity = Math.max(1, Number(item.quantity) || 1);
    const unitPrice = Number(item.unit_price ?? item.price ?? 0);
    return {
      medicine_id: Number(item.medicine_id || item.medicineId) || 0,
      name: item.medicine_name || item.name || 'Medicine',
      quantity,
      unit_price: unitPrice,
      line_total: unitPrice * quantity,
      prescription_required: !!item.prescription_required,
      prescription_url: String(item.prescription_url || order.prescription_url || ''),
      item_note: String(item.item_note || item.note || '').trim(),
      image: item.image || item.image_url || '',
      pharmacy_id: String(item.pharmacy_id || order.pharmacy_id || ''),
      pharmacy_name: item.pharmacy_name || item.pharmacyName || order.pharmacy_name || '',
    };
  }) : [];
  const pharmacy = getMarketplacePharmacy(order.pharmacy_id) || {};
  const stores = !options.skipStores && Array.isArray(order.stores) && order.stores.length
    ? order.stores.map(store => presentResidenceOrder(store, { skipStores:true }))
    : [];
  const prices = residenceOrderVatBreakdown(
    items,
    Number(order.total_amount ?? order.total ?? 0),
    order.vat,
    order.subtotal
  );
  const presented = {
    id: Number(order.id) || 0,
    order_number: String(order.order_number || ''),
    pharmacy_id: String(order.pharmacy_id || pharmacy.id || ''),
    pharmacy_name: order.pharmacy_name || pharmacy.name || 'Pharmacy',
    pharmacy_address: order.pharmacy_address || pharmacy.address || '',
    pharmacy_logo: order.pharmacy_logo || pharmacy.logo_url || '',
    customer_address: order.customer_address || window.RESIDENCE_CONFIG?.profile?.address || '',
    status,
    status_tab: order.status_tab || meta.tab,
    status_class: order.status_class || meta.className,
    status_label: order.status_label || meta.label,
    total_amount: prices.total,
    subtotal: prices.subtotal,
    vat: prices.vat,
    down_payment: roundMoney(Number(order.down_payment) > 0 ? order.down_payment : Number(order.total_amount ?? order.total ?? 0) * CHECKOUT_DOWN_RATE),
    payment_method: order.payment_method || 'gcash',
    created_at: order.created_at || '',
    date_label: order.date_label || (Number.isNaN(created.getTime()) ? '' : created.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric', timeZone:'Asia/Manila' })),
    time_label: order.time_label || (Number.isNaN(created.getTime()) ? '' : created.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', timeZone:'Asia/Manila' })),
    progress_stamps: Array.isArray(order.progress_stamps) ? order.progress_stamps : [],
    item_count: items.length || Number(order.item_count) || 0,
    items,
    prescription_path: String(order.prescription_path || ''),
    prescription_url: String(order.prescription_url || ''),
    pickup_proof_url: String(order.pickup_proof_url || ''),
    pickup_proof_kind: String(order.pickup_proof_kind || (/\.pdf($|\?)/i.test(String(order.pickup_proof_path || order.pickup_proof_url || '')) ? 'pdf' : 'img')),
    balance_paid: !!order.balance_paid,
    checkout_group_id: String(order.checkout_group_id || ''),
    cancellation_reason: String(order.cancellation_reason || ''),
    can_cancel: order.can_cancel === true || order.can_cancel === 1 || order.can_cancel === '1',
    related_order_numbers: Array.isArray(order.related_order_numbers) ? order.related_order_numbers.map(String) : [],
    stores,
    is_group: stores.length > 1 || !!order.is_group,
    store_count: stores.length || Number(order.store_count) || 1,
    partially_fulfilled: !!order.partially_fulfilled,
    completed_store_count: Number(order.completed_store_count) || 0,
  };
  if(!options.skipStores && presented.stores.length === 0){
    presented.stores = [{ ...presented, stores:[], is_group:false, store_count:1 }];
  }
  if(!presented.related_order_numbers.length){
    presented.related_order_numbers = presented.stores.map(store => String(store.order_number || '')).filter(Boolean);
  }
  presented.store_count = presented.stores.length || 1;
  presented.is_group = presented.store_count > 1;
  if(presented.is_group){
    const storeVat = presented.stores.reduce((sum, store) => sum + Number(store.vat || 0), 0);
    const storeSubtotal = presented.stores.reduce((sum, store) => sum + Number(store.subtotal || 0), 0);
    if(storeVat > 0) presented.vat = roundMoney(storeVat);
    if(storeSubtotal > 0) presented.subtotal = roundMoney(storeSubtotal);
  }
  if(presented.can_cancel !== true){
    const stores = presented.stores.length ? presented.stores : [presented];
    presented.can_cancel = stores.every(store => ['pending','processing'].includes(String(store.status || '').toLowerCase()));
  }
  return presented;
}

function residenceOrderStatusMeta(status){
  const key = String(status || '').toLowerCase();
  if(key === 'ready') return { tab:'ready', className:'ready', label:'Ready for pick up' };
  if(key === 'picked_up' || key === 'pickedup') return { tab:'picked_up', className:'picked-up', label:'Picked up' };
  if(key === 'delivered' || key === 'completed') return { tab:'completed', className:'completed', label:'Completed' };
  if(key === 'cancelled') return { tab:'cancelled', className:'cancelled', label:'Cancelled' };
  if(key === 'confirmed') return { tab:'confirmed', className:'confirmed', label:'Confirmed' };
  if(key === 'preparing') return { tab:'preparing', className:'preparing', label:'Preparing' };
  if(key === 'partial' || key === 'partially_fulfilled') return { tab:'processing', className:'partial', label:'Partially fulfilled' };
  return { tab:'processing', className:'processing', label:'Processing' };
}

function residenceOrders(){
  return Array.isArray(window.RESIDENCE_CONFIG?.orders) ? window.RESIDENCE_CONFIG.orders : [];
}

function activeOrdersTab(){
  return document.querySelector('#orders-status-tabs button.is-active')?.dataset.orderTab || 'all';
}

function ordersListIcon(name){
  const icons = {
    capsule:'<path d="m8 5 11 11a3.5 3.5 0 0 1-5 5L3 10a3.5 3.5 0 0 1 5-5Z"/><path d="m7 14 7-7"/>',
    pin:'<path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/>',
    box:'<path d="M3 8h18l-1.5 12H4.5z"/><path d="M3 8 12 3l9 5M12 3v17"/>',
    clock:'<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
    search:'<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    chevron:'<path d="m9 6 6 6-6 6"/>'
  };
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name] || ''}</svg>`;
}

function updateOrdersTabCounts(){
  const all = residenceOrders();
  document.querySelectorAll('#orders-status-tabs button[data-order-tab]').forEach(button => {
    const tab = button.dataset.orderTab;
    const count = tab === 'all' ? all.length : all.filter(order => order.status_tab === tab).length;
    let badge = button.querySelector('.orders-tab-count');
    if(!badge){
      badge = document.createElement('em');
      badge.className = 'orders-tab-count';
      button.appendChild(badge);
    }
    badge.textContent = String(count);
    badge.hidden = count < 1;
  });
}

function normalizeOrderSearch(value){
  return String(value || '')
    .toLowerCase()
    .replace(/^order\s*/i, '')
    .replace(/#/g, '')
    .replace(/\s+/g, '')
    .trim();
}

function orderMatchesSearch(order, query){
  const raw = String(query || '').trim().toLowerCase();
  if(!raw) return true;
  const q = normalizeOrderSearch(raw);
  const compactQ = q.replace(/[^a-z0-9]/g, '');
  const number = String(order.order_number || order.orderNumber || '');
  const compactNumber = normalizeOrderSearch(number).replace(/[^a-z0-9]/g, '');
  const id = String(order.id || '');
  return number.toLowerCase().includes(raw)
    || normalizeOrderSearch(number).includes(q)
    || (compactQ !== '' && compactNumber.includes(compactQ))
    || id === raw
    || id === q
    || String(order.pharmacy_name || '').toLowerCase().includes(raw)
    || (order.related_order_numbers || []).some(value => String(value).toLowerCase().includes(raw) || normalizeOrderSearch(value).includes(q))
    || (order.stores || []).some(store => String(store.order_number || '').toLowerCase().includes(raw) || String(store.pharmacy_name || '').toLowerCase().includes(raw));
}

const ORDERS_PAGE_SIZE = 10;
let ordersPage = 1;

function renderOrdersPagination(total){
  const pager = document.getElementById('orders-pagination');
  if(!pager) return;
  if(total < 1){
    pager.hidden = true;
    pager.innerHTML = '';
    return;
  }
  const pages = Math.max(1, Math.ceil(total / ORDERS_PAGE_SIZE));
  ordersPage = Math.min(Math.max(1, ordersPage), pages);
  const from = (ordersPage - 1) * ORDERS_PAGE_SIZE + 1;
  const to = Math.min(ordersPage * ORDERS_PAGE_SIZE, total);
  pager.hidden = false;
  pager.innerHTML = (total > ORDERS_PAGE_SIZE
    ? `<div class="page-btns pager-pill">${residencePagerControls(ordersPage, pages, 'orders-page')}</div>`
    : '')
    + `<span class="catalog-page-meta">${from}–${to} of ${total}</span>`;
}

function renderResidenceOrders(keepPage = true){
  const root = document.getElementById('orders-reference-list');
  if(!root) return;
  if(!keepPage) ordersPage = 1;
  updateOrdersTabCounts();
  const query = document.getElementById('orders-search')?.value || '';
  const hasQuery = normalizeOrderSearch(query) !== '';
  const tab = activeOrdersTab();
  const orders = residenceOrders().filter(order => {
    const matchesTab = hasQuery || tab === 'all' || order.status_tab === tab;
    return matchesTab && orderMatchesSearch(order, query);
  });
  if(orders.length === 0){
    root.classList.add('is-empty');
    root.innerHTML = hasQuery
      ? '<p class="orders-empty">No orders match that number.</p>'
      : (tab === 'cancelled'
        ? '<p class="orders-empty">No cancelled orders.</p>'
        : '<p class="orders-empty">No orders yet. Medicines you check out will appear here and at the pharmacy.</p>');
    renderOrdersPagination(0);
    return;
  }
  const pages = Math.max(1, Math.ceil(orders.length / ORDERS_PAGE_SIZE));
  ordersPage = Math.min(Math.max(1, ordersPage), pages);
  const start = (ordersPage - 1) * ORDERS_PAGE_SIZE;
  const pageOrders = orders.slice(start, start + ORDERS_PAGE_SIZE);
  root.classList.remove('is-empty');
  root.innerHTML = pageOrders.map(order => {
    const thumbs = (order.items || []).slice(0, 3).map(item => {
      const src = escapeHtml(item.image || item.image_url || '');
      const alt = escapeHtml(item.name || 'Medicine');
      const fallback = cartMedicineFallbackImage();
      return src
        ? `<img class="order-reference-thumb" src="${src}" alt="${alt}" onerror="this.onerror=null;this.src='${fallback}'">`
        : `<img class="order-reference-thumb" src="${fallback}" alt="${alt}">`;
    }).join('');
    const extra = Math.max(0, (order.items || []).length - 3);
    const extraHtml = extra > 0 ? `<em>+${extra}</em>` : '';
    const number = escapeHtml(order.order_number);
    const count = order.item_count || (order.items || []).length;
    const storeCount = Number(order.store_count || order.stores?.length || 1);
    const summary = storeCount > 1
      ? `${storeCount} stores • ${count} item${count === 1 ? '' : 's'}`
      : `${count} item${count === 1 ? '' : 's'} • ${escapeHtml(order.pharmacy_name)}`;
    const pickup = storeCount > 1
      ? 'Multiple pharmacies in this checkout'
      : escapeHtml(order.pharmacy_address || order.pharmacy_name || 'Pharmacy pickup');
    return `
      <article class="order-reference-card" data-order-number="${number}" data-order-tab="${escapeHtml(order.status_tab)}">
        <div class="order-reference-product">
          <div>
            <b>Order #${number}</b>
            <small>${summary}</small>
            <div class="order-reference-thumbs">${thumbs}${extraHtml}</div>
          </div>
        </div>
        <div class="order-reference-address">
          <b>${ordersListIcon('pin')} Pickup branch</b>
          <span>${storeCount > 1 ? pickup : pickup.replace(/,\s*/g, ',<br>')}</span>
          <b class="is-pickup">${ordersListIcon('box')} Pick up only</b>
          <span>Show this order at the pharmacy counter.</span>
        </div>
        <div class="order-reference-status">
          <strong class="${escapeHtml(order.status_class)}">${ordersListIcon('clock')} ${escapeHtml(order.status_label)}</strong>
          <small>Order Date</small>
          <b>${ordersListIcon('calendar')} ${escapeHtml(order.date_label)} • ${escapeHtml(order.time_label)}</b>
        </div>
        <div class="order-reference-total">
          <small>Total Amount</small>
          <b class="order-reference-total-amount">${formatMoney(order.total_amount)}</b>
          <button type="button" data-view-order="${number}">View details ${ordersListIcon('chevron')}</button>
        </div>
      </article>`;
  }).join('');
  renderOrdersPagination(orders.length);
}

function bindResidenceOrdersTools(){
  const tabs = document.getElementById('orders-status-tabs');
  if(tabs && tabs.dataset.bound !== '1'){
    tabs.dataset.bound = '1';
    tabs.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-order-tab]');
      if(!button) return;
      tabs.querySelectorAll('button').forEach(tab => tab.classList.toggle('is-active', tab === button));
      renderResidenceOrders(false);
    });
  }
  const search = document.getElementById('orders-search');
  if(search && search.dataset.bound !== '1'){
    search.dataset.bound = '1';
    const runSearch = () => renderResidenceOrders(false);
    search.addEventListener('input', runSearch);
    search.addEventListener('search', runSearch);
    search.addEventListener('keyup', runSearch);
    search.addEventListener('paste', () => setTimeout(runSearch, 0));
    search.addEventListener('keydown', (event) => {
      if(event.key === 'Enter') event.preventDefault();
    });
  }
  const pager = document.getElementById('orders-pagination');
  if(pager && pager.dataset.bound !== '1'){
    pager.dataset.bound = '1';
    pager.addEventListener('click', (event) => {
      const button = event.target.closest('[data-orders-page]');
      if(!button || button.disabled) return;
      const raw = button.getAttribute('data-orders-page');
      if(raw === 'prev') ordersPage -= 1;
      else if(raw === 'next') ordersPage += 1;
      else ordersPage = parseInt(raw, 10) || 1;
      renderResidenceOrders(true);
      document.getElementById('orders-list-view')?.scrollIntoView({ behavior:'smooth', block:'start' });
    });
  }
  const list = document.getElementById('orders-reference-list');
  if(list && list.dataset.bound !== '1'){
    list.dataset.bound = '1';
    list.addEventListener('click', (event) => {
      const button = event.target.closest('[data-view-order]');
      if(button){
        event.preventDefault();
        viewOrder(button.getAttribute('data-view-order'));
        return;
      }
      const card = event.target.closest('.order-reference-card[data-order-number]');
      if(!card) return;
      event.preventDefault();
      viewOrder(card.getAttribute('data-order-number'));
    });
  }
  ['orders-detail-backdrop', 'orders-detail-edge'].forEach(function(id){
    const el = document.getElementById(id);
    if(!el || el.dataset.bound === '1') return;
    el.dataset.bound = '1';
    el.addEventListener('click', function(event){
      event.preventDefault();
      closeOrderDetail();
    });
  });
}

function bindResidenceRxCropZoom(root){
  if(typeof window.bindRxCropPreview === 'function') window.bindRxCropPreview(root);
}

function resetResidenceRxCropZoom(root, flags){
  if(typeof window.resetRxCropPreview === 'function'){
    window.resetRxCropPreview(root, flags);
    return;
  }
  const stage = root?.querySelector('.rx-crop-stage');
  const media = root?.querySelector('.rx-crop-media');
  const slider = root?.querySelector('.rx-crop-zoom');
  if(stage){
    stage.classList.toggle('is-pdf', !!flags?.pdf);
    stage.classList.toggle('is-empty', !!flags?.empty);
  }
  if(slider) slider.value = '1';
  if(media) media.style.transform = 'translate(0px, 0px) scale(1)';
}

function openOrderDocumentPreview(url, title){
  const src = String(url || '').trim();
  const heading = String(title || 'Prescription preview').trim() || 'Prescription preview';
  const modal = document.getElementById('order-rx-viewer');
  const titleEl = document.getElementById('order-rx-viewer-title');
  const image = document.getElementById('order-rx-viewer-image');
  const frame = document.getElementById('order-rx-viewer-frame');
  const missing = document.getElementById('order-rx-viewer-missing');
  if(!modal) return;
  if(titleEl) titleEl.textContent = heading;
  if(image) image.alt = heading;
  if(frame) frame.title = heading;
  bindResidenceRxCropZoom(modal);
  if(!src){
    if(image) image.hidden = true;
    if(frame) frame.hidden = true;
    if(missing){
      missing.hidden = false;
      const headingLower = heading.toLowerCase();
      missing.textContent = headingLower.indexOf('pickup') !== -1
        ? 'No pickup proof file is available.'
        : (headingLower.indexOf('proof') !== -1 ? 'No proof file is available.' : 'No prescription file is available.');
    }
    resetResidenceRxCropZoom(modal, { empty:true });
    modal.hidden = false;
    return;
  }
  const entry = typeof window.preloadRxPreview === 'function' ? window.preloadRxPreview(src) : null;
  const ready = typeof window.rxPreviewReadyUrl === 'function' ? window.rxPreviewReadyUrl(src) : '';
  const isPdf = typeof window.rxPreviewLooksPdf === 'function' ? window.rxPreviewLooksPdf(src) : /\.pdf($|\?)/i.test(src);
  const displaySrc = ready || src;
  resetResidenceRxCropZoom(modal, { pdf:isPdf, empty:false });
  if(image){
    image.hidden = isPdf;
    if(!isPdf) image.src = displaySrc;
  }
  if(frame){
    frame.hidden = !isPdf;
    if(isPdf) frame.src = displaySrc;
  }
  if(missing) missing.hidden = true;
  if(entry && entry.promise && !ready){
    entry.promise.then(function(result){
      if(!result || !result.objectUrl) return;
      const pdf = /pdf/i.test(result.type || '');
      if(pdf){
        if(image) image.hidden = true;
        if(frame){
          frame.hidden = false;
          frame.src = result.objectUrl;
        }
        resetResidenceRxCropZoom(modal, { pdf:true, empty:false });
      }else if(image && !image.hidden){
        image.src = result.objectUrl;
      }
    });
  }
  modal.hidden = false;
}

function openOrderPrescription(url){
  openOrderDocumentPreview(url, 'Prescription preview');
}

function closeOrderPrescription(){
  const modal = document.getElementById('order-rx-viewer');
  if(modal) modal.hidden = true;
}
window.openOrderPrescription = openOrderPrescription;
window.openOrderDocumentPreview = openOrderDocumentPreview;
window.closeOrderPrescription = closeOrderPrescription;

function syncResidenceOrderDrawer(open){
  const detail = document.getElementById('orders-detail-view');
  const backdrop = document.getElementById('orders-detail-backdrop');
  const page = document.querySelector('[data-page="orders"]');
  if(detail) detail.hidden = !open;
  if(backdrop) backdrop.hidden = !open;
  page?.classList.toggle('is-order-detail', !!open);
  document.body.classList.toggle('res-order-drawer-open', !!open);
}

function markActiveResidenceOrder(id){
  const needle = String(id || '');
  document.querySelectorAll('#orders-reference-list .order-reference-card').forEach(card => {
    card.classList.toggle('is-active', needle !== '' && card.getAttribute('data-order-number') === needle);
  });
}

function viewOrder(id){
  syncResidenceOrderDrawer(true);
  renderOrderDetail(id);
  markActiveResidenceOrder(id);
}

let residenceOrdersRefreshTimer = null;
async function refreshResidenceOrders(){
  const cfg = window.RESIDENCE_CONFIG || {};
  if(!cfg.ordersUrl) return;
  if(document.getElementById('residence-cancel-order-modal')?.hidden === false) return;
  try{
    const response = await fetch(cfg.ordersUrl, {credentials:'same-origin', cache:'no-store'});
    const data = await response.json();
    if(!data.ok || !Array.isArray(data.orders)) return;
    const previous = JSON.stringify(cfg.orders || []);
    const next = JSON.stringify(data.orders);
    if(previous === next) return;
    cfg.orders = data.orders;
    renderResidenceOrders();
    const detail = document.getElementById('orders-detail-view');
    const current = detail?.hidden === false ? (detail.dataset?.orderId || '') : '';
    if(current){
      renderOrderDetail(current);
      markActiveResidenceOrder(current);
    }
  }catch(e){}
}
function startResidenceOrdersRefresh(){
  if(residenceOrdersRefreshTimer) return;
  refreshResidenceOrders();
  residenceOrdersRefreshTimer = setInterval(function(){
    if(!document.hidden) refreshResidenceOrders();
  }, 2500);
  document.addEventListener('visibilitychange', function(){
    if(!document.hidden) refreshResidenceOrders();
  });
}
document.addEventListener('livesync:change', function(event){
  const keys = event.detail?.keys || [];
  if(keys.includes('orders') || keys.includes('notifications')) refreshResidenceOrders();
  if(keys.includes('cart') && typeof fetchResidenceCart === 'function' && !document.querySelector('.page[data-page="checkout"].active')){
    fetchResidenceCart();
  }
  if((keys.includes('orders') || keys.includes('notifications') || keys.includes('catalog')) && typeof renderTrackingPage === 'function' && document.querySelector('.page[data-page="tracking"].active')){
    renderTrackingPage();
  }
});
document.addEventListener('livesync:applied', function(event){
  const keys = event.detail?.keys || [];
  if(keys.includes('orders') || keys.includes('notifications')) refreshResidenceOrders();
  document.querySelectorAll('[data-carousel-wrap]').forEach(wrap => { wrap._carouselReady = false; });
  if(typeof initProductCarousels === 'function') initProductCarousels();
  if(typeof initPharmacyMarquee === 'function') initPharmacyMarquee();
  document.querySelectorAll('.med-catalog .med-card').forEach(applySpotlightBadge);
  if(typeof resetStuckProductPreview === 'function') resetStuckProductPreview();
  if(typeof updateStoreBrowseView === 'function' && document.querySelector('.page[data-page="dashboard"].active')){
    updateStoreBrowseView();
  }
  document.querySelectorAll('.topbar-notification-dropdown, [data-page="notifications"]').forEach(scope => {
    if(typeof applyResidentNotificationFilter === 'function' && typeof activeResidentNotificationMode === 'function'){
      applyResidentNotificationFilter(scope, activeResidentNotificationMode(scope));
    }
  });
});
startResidenceOrdersRefresh();
window.renderResidenceOrders = renderResidenceOrders;
window.refreshResidenceOrders = refreshResidenceOrders;
window.renderOrderDetail = renderOrderDetail;
window.viewOrder = viewOrder;
window.closeOrderDetail = closeOrderDetail;

function odIcon(name){
  const icons = {
    back:'<path d="M15 18l-6-6 6-6"/>',
    pin:'<path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/>',
    calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
    check:'<path d="m5 13 4 4L19 7"/>',
    truck:'<path d="M3 7h11v10H3zM14 11h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/>',
    package:'<path d="M12 3 3 8v8l9 5 9-5V8z"/><path d="M12 13V3M3 8l9 5 9-5"/>',
    shield:'<path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6z"/>',
    capsule:'<path d="m8 5 11 11a3.5 3.5 0 0 1-5 5L3 10a3.5 3.5 0 0 1 5-5Z"/><path d="m7 14 7-7"/>',
    bag:'<path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
    store:'<path d="M3 10h18l-1.2-5H4.2z"/><path d="M4 10v10h16V10M9 20v-6h6v6"/>',
    receipt:'<path d="M7 3h10v18l-2.4-1.6L12 21l-2.6-1.6L7 21z"/><path d="M10 8h4M10 12h4"/>',
    image:'<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m5 17 4-4 3 3 2-2 5 5"/>',
    clock:'<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    headset:'<path d="M4 13a8 8 0 0 1 16 0"/><path d="M4 13v4a2 2 0 0 0 2 2h1v-8H6a2 2 0 0 0-2 2zM20 13v4a2 2 0 0 1-2 2h-1v-8h1a2 2 0 0 1 2 2z"/>',
    chevron:'<path d="m9 6 6 6-6 6"/>'
  };
  const body = icons[name];
  if(!body) return '';
  return `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:16px;height:16px;max-width:16px;max-height:16px;display:block;flex:none">${body}</svg>`;
}

function findResidenceOrder(id){
  const needle = String(id || '');
  return residenceOrders().find(item => {
    const order = presentResidenceOrder(item);
    if(String(order.order_number) === needle || String(order.id) === needle) return true;
    return (order.stores || []).some(store => String(store.order_number) === needle || String(store.id) === needle);
  }) || null;
}

function orderProgressIndex(status){
  const key = String(status || '').toLowerCase();
  if(key === 'cancelled') return 0;
  if(key === 'confirmed') return 2;
  if(key === 'preparing') return 3;
  if(key === 'ready') return 4;
  if(['picked_up','pickedup','delivered','completed'].includes(key)) return 5;
  return 1;
}

function orderTimelineHtml(status, stamp, stamps, options){
  const current = orderProgressIndex(status);
  const times = Array.isArray(stamps) ? stamps : [];
  const proofUrl = String(options?.pickupProofUrl || '').trim();
  const steps = [
    {name:'Order placed', icon:'check'},
    {name:'Processing', icon:'clock'},
    {name:'Confirmed', icon:'check'},
    {name:'Preparing', icon:'capsule'},
    {name:'Ready for pick up', icon:'store'},
    {name:'Completed', icon:'check'}
  ].map((step, index) => {
    const state = String(status).toLowerCase() === 'cancelled' && index > 0
      ? 'is-muted'
      : (index < current ? 'is-done' : index === current ? 'is-current' : 'is-muted');
    const icon = (state === 'is-done' || (state === 'is-current' && index === 5)) ? 'check' : step.icon;
    const time = state !== 'is-muted' ? String(times[index] || (index === 0 ? stamp : '') || '') : '';
    return { ...step, state, icon, time };
  });
  return steps.map((step, index) => {
    const rail = index < steps.length - 1
      ? `<span class="od-rail${index < current ? ' is-done' : ''}"></span>`
      : '';
    const proofBtn = (index === 5 && step.state !== 'is-muted' && proofUrl)
      ? `<button type="button" class="od-view-proof-btn" data-preview-url="${escHtml(proofUrl)}" data-preview-title="Proof of pickup">View pickup proof</button>`
      : '';
    return `<div class="od-step ${step.state}${step.time ? ' is-dated' : ''}${proofBtn ? ' has-proof-action' : ''}"><span class="od-node">${odIcon(step.icon)}</span><strong>${step.name}</strong><small>${step.time ? escapeHtml(step.time) : '&nbsp;'}</small>${proofBtn}</div>${rail}`;
  }).join('');
}

function bindGroupedOrderDetail(root){
  root.querySelectorAll('[data-mo-track]').forEach(button => {
    button.addEventListener('click', () => {
      const card = button.closest('.mo-store-card');
      if(!card) return;
      const open = card.classList.toggle('is-open');
      button.textContent = open ? 'Hide tracking details' : 'View tracking details';
    });
  });
}

function setResidenceOrderHeadActions(html){
  const headActions = document.getElementById('orders-detail-head-actions');
  if(headActions) headActions.innerHTML = html || '';
}

function renderOrderDetail(id){
  const drawer = document.getElementById('orders-detail-view');
  const root = document.getElementById('orders-detail-body') || drawer;
  const title = document.getElementById('orders-detail-title');
  if(!root) return;
  if(drawer) drawer.dataset.orderId = String(id);

  const order = findResidenceOrder(id);
  if(!order){
    if(title) title.textContent = 'Order details';
    setResidenceOrderHeadActions('');
    root.innerHTML = `<p class="orders-empty">This order was not found.</p>`;
    return;
  }

  const data = presentResidenceOrder(order);
  const paidOnline = roundMoney(Number(data.down_payment) > 0 ? data.down_payment : data.total_amount * CHECKOUT_DOWN_RATE);
  const remaining = roundMoney(data.total_amount - paidOnline);
  const fullyPaid = data.balance_paid || ['picked_up','pickedup','delivered','completed'].includes(String(data.status).toLowerCase());
  const peso = (n) => formatMoney(n);
  const stamp = `${data.date_label} • ${data.time_label}`;
  const fallbackImage = cartMedicineFallbackImage();
  const stores = Array.isArray(data.stores) && data.stores.length ? data.stores : [data];
  const storeCount = stores.length;
  const completedStores = stores.filter(store => orderProgressIndex(store.status) >= 5).length;
  const confirmed = data.down_payment > 0;
  const pickupProofButton = (url, kind) => {
    const src = String(url || '').trim();
    if(!src) return '';
    const looksPdf = String(kind || '') === 'pdf' || /\.pdf($|\?)/i.test(src);
    const label = looksPdf ? 'PDF proof of pickup' : 'Photo of completed pickup';
    const thumb = looksPdf
      ? `<span class="od-pickup-proof-thumb is-pdf" aria-hidden="true">PDF</span>`
      : `<span class="od-pickup-proof-thumb"><img src="${escHtml(src)}" alt="Pickup proof"></span>`;
    return `<button type="button" class="od-pickup-proof od-pickup-proof--head" data-preview-url="${escHtml(src)}" data-preview-title="Proof of pickup">${thumb}<span><strong>Pickup completed</strong><small>${escapeHtml(label)}</small></span>${odIcon('chevron')}</button>`;
  };
  const pickupProofEntries = [];
  stores.forEach(store => {
    if(store.pickup_proof_url) pickupProofEntries.push({ url: store.pickup_proof_url, kind: store.pickup_proof_kind });
  });
  if(data.pickup_proof_url) pickupProofEntries.unshift({ url: data.pickup_proof_url, kind: data.pickup_proof_kind });
  const uniqueProofs = Array.from(new Map(pickupProofEntries.filter(item => item.url).map(item => [item.url, item])).values());
  const pickupProofHtml = uniqueProofs.map(item => pickupProofButton(item.url, item.kind)).join('');
  const pickupProofUrls = uniqueProofs.map(item => item.url);
  const timelineHtml = orderTimelineHtml(data.status, stamp, data.progress_stamps, {
    pickupProofUrl: pickupProofUrls[0] || ''
  });

  const storeCards = stores.map(store => {
    const pharmacy = getMarketplacePharmacy(store.pharmacy_id) || {};
    const logo = store.pharmacy_logo || pharmacy.logo_url || '';
    const logoHtml = logo
      ? `<img src="${escHtml(logo)}" alt="" onerror="this.style.display='none'">`
      : `<span>${escHtml(String(store.pharmacy_name || 'P').slice(0, 1))}</span>`;
    const itemsHtml = (store.items || []).map(item => {
      const rxUrl = item.prescription_url || store.prescription_url || data.prescription_url || '';
      const rxHtml = item.prescription_required
        ? (rxUrl
          ? `<small><button type="button" class="od-rx-link" data-rx-url="${escHtml(rxUrl)}">Rx required · see prescription ›</button></small>`
          : `<small>Rx required</small>`)
        : '';
      return `
      <div class="mo-store-item">
        <img src="${escapeHtml(item.image || fallbackImage)}" alt="" onerror="this.onerror=null;this.src='${fallbackImage}'">
        <div>
          <b>${escapeHtml(item.name)}</b>
          <small>Qty: ${item.quantity}</small>
          ${rxHtml}
          ${item.item_note ? `<small class="mo-item-note">Note: ${escapeHtml(item.item_note)}</small>` : ''}
        </div>
        <strong>${peso(item.line_total)}</strong>
      </div>
    `;
    }).join('');
    const itemSubtotal = (store.items || []).reduce((sum, item) => sum + Number(item.line_total || 0), 0);
    const storePrices = residenceOrderVatBreakdown(store.items || [], store.total_amount || itemSubtotal, store.vat, store.subtotal);
    const vatPercent = Math.round(CHECKOUT_VAT_RATE * 100);
    const storeTrackHtml = storeCount > 1
      ? `<div class="mo-store-track">
          <div class="od-timeline mo-store-timeline">${orderTimelineHtml(store.status, `${store.date_label || data.date_label} • ${store.time_label || data.time_label}`, store.progress_stamps || data.progress_stamps)}</div>
        </div>
        <button type="button" class="mo-store-track-btn" data-mo-track>View tracking details</button>`
      : '';
    return `
      <article class="mo-store-card">
        <header class="mo-store-head">
          <div class="mo-store-logo">${logoHtml}</div>
          <div>
            <strong>${escHtml(store.pharmacy_name)}</strong>
            <small>Pickup: ${escHtml(store.pharmacy_address || pharmacy.address || 'Pharmacy counter')}</small>
          </div>
          ${storeCount > 1 ? `<span class="mo-store-status ${escHtml(store.status_class || '')}">${escHtml(store.status_label || 'Processing')}</span>` : ''}
        </header>
        <div class="mo-store-items">${itemsHtml}</div>
        <div class="mo-store-foot">
          <div class="mo-store-foot-row"><span>VAT (${vatPercent}%)</span><b>${peso(storePrices.vat)}</b></div>
          <div class="mo-store-foot-row"><span>Store Subtotal</span><b>${peso(store.total_amount || storePrices.total || storePrices.subtotal)}</b></div>
        </div>
        ${storeTrackHtml}
      </article>`;
  }).join('');

  const fulfillmentNote = storeCount > 1
    ? `${completedStores} of ${storeCount} stores completed`
    : '';
  const canCancel = !!data.can_cancel && ['pending', 'processing'].includes(String(data.status || '').toLowerCase()) && !data.partially_fulfilled;
  const cancelHtml = canCancel
    ? `<button type="button" class="btn od-cancel-btn" onclick="openResidenceCancelOrder(${Number(data.id) || 0}, '${escHtml(data.order_number)}')">Cancel order</button>`
    : '';
  const cancelReasonHtml = String(data.status || '').toLowerCase() === 'cancelled' && data.cancellation_reason
    ? `<p class="mo-cancel-reason"><strong>Cancellation reason</strong><span>${escHtml(data.cancellation_reason)}</span></p>`
    : '';
  const receiptStatus = confirmed || fullyPaid ? 'Paid' : 'Pending';
  const paidPercent = fullyPaid ? 100 : Math.round((paidOnline / Math.max(1, Number(data.total_amount) || 1)) * 100);
  const duePercent = Math.max(0, 100 - paidPercent);
  const collectAmount = fullyPaid ? 0 : remaining;
  const paidNowAmount = fullyPaid ? data.total_amount : paidOnline;
  const heroTitle = fullyPaid ? 'Fully paid' : (confirmed ? 'Down payment received' : 'Payment pending');
  const orderPrices = residenceOrderVatBreakdown(data.items || [], data.total_amount, data.vat, data.subtotal);
  const payNote = fullyPaid
    ? 'All payments have been settled. Your order is fully paid.'
    : `Order total includes ${peso(orderPrices.vat)} VAT. Pay the remaining balance when you pick up your medicine.`;

  if(title) title.textContent = 'Order #' + String(data.order_number || '');
  setResidenceOrderHeadActions(cancelHtml);
  root.innerHTML = `
    <div class="res-order-layout">
      <aside class="res-order-track">
        <section class="od-timeline">${timelineHtml}</section>
      </aside>
      <div class="res-order-facts">
        ${cancelReasonHtml}
        ${storeCount > 1 ? `<p class="mo-parent-note">${fulfillmentNote ? escHtml(fulfillmentNote) + '. ' : ''}You purchased items from multiple pharmacies in one checkout. Each store will process your items separately.</p>` : ''}
        ${pickupProofHtml ? `<div class="mo-parent-proofs">${pickupProofHtml}</div>` : ''}
        <div class="mo-store-list">${storeCards}</div>
        <section class="od-receipt pay-card">
          <div class="pay-card-hero">
            <p class="pay-card-status${confirmed || fullyPaid ? '' : ' is-pending'}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.4 2.4 4.6-5"/></svg>
              ${heroTitle}
            </p>
            <p class="pay-card-kicker">${fullyPaid ? 'Remaining at pickup' : 'Pay at pickup'}</p>
            <p class="pay-card-amount">${peso(collectAmount)}</p>
            <p class="pay-card-hint">${fullyPaid ? 'This order is fully paid. No balance is due at pickup.' : 'You pay this when you collect the order.'}</p>
            <div class="pay-card-bar" aria-hidden="true"><i style="width:${paidPercent}%"></i></div>
            <div class="pay-card-legend">
              <span><i class="is-paid"></i> Paid ${peso(paidNowAmount)} (${paidPercent}%)</span>
              <span><i class="is-due"></i> At pickup ${peso(collectAmount)} (${duePercent}%)</span>
            </div>
          </div>
          <div class="pay-card-body">
            <h4>Payment details</h4>
            <div class="pay-card-row"><span>Order number</span><b>${escHtml(data.order_number)}</b></div>
            <div class="pay-card-row"><span>Order time</span><b>${escHtml(data.time_label)}, ${escHtml(data.date_label)}</b></div>
            <div class="pay-card-row"><span>Payment method</span><b>GCash<small>${confirmed ? 'Confirmed through PayMongo' : 'PayMongo GCash checkout'}</small></b></div>
            <div class="pay-card-row"><span>Payment status</span><b><span class="od-receipt-pill${confirmed || fullyPaid ? '' : ' is-pending'}">${receiptStatus}</span></b></div>
            <div class="pay-card-row"><span>Order total</span><b>${peso(data.total_amount)}</b></div>
            <div class="pay-card-row"><span>Paid now (${paidPercent}%)</span><b class="is-paid">${peso(paidNowAmount)}</b></div>
            <div class="pay-card-row"><span>Balance (${duePercent}%)</span><b>${peso(collectAmount)}</b></div>
            <p class="pay-card-note">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
              <span>${payNote}</span>
            </p>
          </div>
        </section>
      </div>
    </div>`;
  bindGroupedOrderDetail(root);
  if(typeof window.preloadRxPreviewList === 'function'){
    const rxUrls = [];
    stores.forEach(store => {
      if(store.prescription_url) rxUrls.push(store.prescription_url);
      (store.items || []).forEach(item => {
        if(item.prescription_url) rxUrls.push(item.prescription_url);
      });
    });
    if(data.prescription_url) rxUrls.push(data.prescription_url);
    pickupProofUrls.forEach(url => rxUrls.push(url));
    window.preloadRxPreviewList(rxUrls);
  }
}

function renderReferenceOrderDetail(id){
  return renderOrderDetail(id);
}

let residenceCancelOrderId = 0;
let residenceCancelOrderNumber = '';

function setResidenceCancelOrderError(message){
  const error = document.getElementById('residence-cancel-order-error');
  if(!error) return;
  if(!message){
    error.hidden = true;
    error.textContent = '';
    return;
  }
  error.hidden = false;
  error.textContent = message;
}

function toggleResidenceCancelOtherReason(){
  const select = document.getElementById('residence-cancel-reason-select');
  const otherField = document.getElementById('residence-cancel-reason-other-field');
  const otherInput = document.getElementById('residence-cancel-reason-other-input');
  const isOther = (select?.value || '') === 'Other';
  if(otherField) otherField.hidden = !isOther;
  if(otherInput){
    otherInput.required = isOther;
    if(!isOther) otherInput.value = '';
  }
}

function resetResidenceCancelOrderForm(){
  const select = document.getElementById('residence-cancel-reason-select');
  const otherInput = document.getElementById('residence-cancel-reason-other-input');
  const submitBtn = document.getElementById('residence-cancel-order-submit');
  if(select) select.value = '';
  if(otherInput) otherInput.value = '';
  if(submitBtn){
    submitBtn.disabled = false;
    submitBtn.textContent = 'Cancel order';
  }
  setResidenceCancelOrderError('');
  toggleResidenceCancelOtherReason();
}

function openResidenceCancelOrder(orderId, orderNumber){
  residenceCancelOrderId = Number(orderId) || 0;
  residenceCancelOrderNumber = String(orderNumber || '');
  const modal = document.getElementById('residence-cancel-order-modal');
  const sub = document.getElementById('residence-cancel-order-sub');
  if(!modal) return;
  resetResidenceCancelOrderForm();
  if(sub) sub.textContent = residenceCancelOrderNumber ? `Order #${residenceCancelOrderNumber}` : '';
  modal.hidden = false;
  document.getElementById('residence-cancel-reason-select')?.focus();
}
window.openResidenceCancelOrder = openResidenceCancelOrder;

function closeResidenceCancelOrderModal(){
  const modal = document.getElementById('residence-cancel-order-modal');
  if(modal) modal.hidden = true;
  residenceCancelOrderId = 0;
  residenceCancelOrderNumber = '';
  resetResidenceCancelOrderForm();
}
window.closeResidenceCancelOrderModal = closeResidenceCancelOrderModal;

async function submitResidenceCancelOrder(){
  const select = document.getElementById('residence-cancel-reason-select');
  const otherInput = document.getElementById('residence-cancel-reason-other-input');
  const submitBtn = document.getElementById('residence-cancel-order-submit');
  const reason = String(select?.value || '').trim();
  const otherReason = String(otherInput?.value || '').trim();
  if(!reason){
    setResidenceCancelOrderError('Please select a cancellation reason.');
    return;
  }
  if(reason === 'Other' && otherReason === ''){
    setResidenceCancelOrderError('Please type the reason for cancellation.');
    otherInput?.focus();
    return;
  }
  if(submitBtn){
    submitBtn.disabled = true;
    submitBtn.textContent = 'Cancelling...';
  }
  setResidenceCancelOrderError('');
  try{
    const body = new FormData();
    body.set('order_id', String(residenceCancelOrderId || 0));
    body.set('order_number', residenceCancelOrderNumber);
    body.set('reason', reason);
    body.set('other_reason', otherReason);
    const response = await fetch(window.RESIDENCE_CONFIG?.cancelOrderUrl || '../ajax/cancel-residence-order.php', {
      method: 'POST',
      body,
      credentials: 'same-origin'
    });
    const result = await response.json().catch(() => ({ ok:false, error:'Could not cancel this order.' }));
    if(!result?.ok){
      setResidenceCancelOrderError(result.error || 'Could not cancel this order.');
      if(submitBtn){
        submitBtn.disabled = false;
        submitBtn.textContent = 'Cancel order';
      }
      return;
    }
    if(Array.isArray(result.orders)){
      window.RESIDENCE_CONFIG = window.RESIDENCE_CONFIG || {};
      window.RESIDENCE_CONFIG.orders = result.orders;
    }
    const detailId = document.getElementById('orders-detail-view')?.dataset?.orderId || residenceCancelOrderNumber;
    closeResidenceCancelOrderModal();
    renderResidenceOrders();
    if(detailId) renderOrderDetail(detailId);
    toast(result.message || 'Order cancelled.');
  }catch(e){
    setResidenceCancelOrderError('Could not cancel this order. Please try again.');
    if(submitBtn){
      submitBtn.disabled = false;
      submitBtn.textContent = 'Cancel order';
    }
  }
}

function initResidenceCancelOrderModal(){
  const select = document.getElementById('residence-cancel-reason-select');
  select?.addEventListener('change', toggleResidenceCancelOtherReason);
  document.getElementById('residence-cancel-order-submit')?.addEventListener('click', submitResidenceCancelOrder);
  document.addEventListener('keydown', (event) => {
    if(event.key === 'Escape' && document.getElementById('residence-cancel-order-modal')?.hidden === false){
      closeResidenceCancelOrderModal();
    }
  });
}

function orderBranchDirectionsUrl(pharmacy){
  if(pharmacy?.navigate_url) return String(pharmacy.navigate_url);
  const lat = Number(pharmacy?.latitude);
  const lng = Number(pharmacy?.longitude);
  if(!Number.isFinite(lat) || !Number.isFinite(lng)) return '';
  const origin = window.USER_LOCATION;
  if(origin?.hasLocation && origin.lat != null && origin.lng != null){
    return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=' + encodeURIComponent(`${origin.lat},${origin.lng};${lat},${lng}`);
  }
  return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=' + encodeURIComponent(`${lat},${lng}`);
}

function closeOrderDetail(){
  const detail = document.getElementById('orders-detail-view');
  if(detail) delete detail.dataset.orderId;
  setResidenceOrderHeadActions('');
  syncResidenceOrderDrawer(false);
  markActiveResidenceOrder('');
}

function profileTab(el, panelId){
  document.querySelectorAll('.p-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('.p-panel').forEach(p=>p.style.display='none');
  document.getElementById(panelId).style.display='block';
}

function openSavedAddresses(){
  go('profile');
  const tab = document.querySelector('.profile-tabs .p-tab[onclick*="p-address"]');
  if(tab) profileTab(tab, 'p-address');
}

function escHtml(value){
  const el = document.createElement('div');
  el.textContent = value || '';
  return el.innerHTML;
}

let addressMapInstance = null;
let addressMapMarker = null;
let addressMapPick = null;
let addressMapReverseId = 0;
let addressMapSearchTimer = null;
let addressMapReady = false;
let addressMapRequired = false;

function addressMapBounds(){
  const bounds = window.RESIDENCE_CONFIG?.bounds;
  if(!bounds) return null;
  return L.latLngBounds([bounds.south, bounds.west], [bounds.north, bounds.east]);
}

function addressMapInService(lat, lng){
  const cfg = window.RESIDENCE_CONFIG || {};
  if(typeof window.pointInServiceOverlay === 'function'){
    return window.pointInServiceOverlay(lat, lng, cfg);
  }
  const bounds = addressMapBounds();
  return bounds ? bounds.contains([lat, lng]) : true;
}

function validAddressMapCoordinate(value){
  const coordinate = Number(value);
  return Number.isFinite(coordinate) ? coordinate : null;
}

function setAddressMapStatus(message){
  const status = document.getElementById('address-map-status');
  if(!status) return;
  status.hidden = !message;
  status.textContent = message || '';
}

function hideAddressSearchResults(){
  const list = document.getElementById('address-map-search-results');
  if(!list) return;
  list.hidden = true;
  list.innerHTML = '';
}

function setAddressMapPin(lat, lng, options = {}){
  if(!addressMapInstance) return;

  lat = validAddressMapCoordinate(lat);
  lng = validAddressMapCoordinate(lng);
  if(lat === null || lng === null){
    setAddressMapStatus('Choose a valid point on the map.');
    return;
  }

  if(!addressMapInService(lat, lng)){
    if(typeof window.showMapAreaPopup === 'function'){
      window.showMapAreaPopup('Pick a location within Laoag City, San Nicolas, or Batac City.');
    }else{
      setAddressMapStatus('Choose a location in Laoag City, San Nicolas, or Batac City.');
    }
    return;
  }

  addressMapPick = { lat, lng, address: options.address || addressMapPick?.address || '' };
  const addressInput = document.getElementById('address-map-address');
  if(addressInput && options.address){
    addressInput.value = options.address;
  }

  if(addressMapMarker){
    addressMapMarker.setLatLng([lat, lng]);
  }else{
    addressMapMarker = L.marker([lat, lng], {
      icon: makeAddressMapPinIcon(),
      draggable: true,
      title: 'Your address',
    }).addTo(addressMapInstance);
    addressMapMarker.on('dragend', (event) => {
      const pos = event.target.getLatLng();
      setAddressMapPin(pos.lat, pos.lng);
    });
  }

  if(options.pan){
    addressMapInstance.setView([lat, lng], Math.max(addressMapInstance.getZoom(), 15));
  }

  if(options.address){
    // A previous lookup must not replace an address selected from search or edit mode.
    addressMapReverseId++;
    setAddressMapStatus('');
    return;
  }

  reverseAddressMapPin(lat, lng);
}

function reverseAddressMapPin(lat, lng){
  const cfg = window.RESIDENCE_CONFIG || {};
  if(!cfg.reverseGeocodeUrl) return;
  const requestId = ++addressMapReverseId;
  setAddressMapStatus('Looking up address…');

  fetch(`${cfg.reverseGeocodeUrl}?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`)
    .then(response => response.json())
    .then(data => {
      if(requestId !== addressMapReverseId) return;
      const addressInput = document.getElementById('address-map-address');
      if(data.success && data.address){
        if(addressInput) addressInput.value = data.address;
        if(addressMapPick) addressMapPick.address = data.address;
        setAddressMapStatus('');
        return;
      }
      setAddressMapStatus(data.message || 'Could not find an address for this pin. Try another point.');
    })
    .catch(() => {
      if(requestId !== addressMapReverseId) return;
      setAddressMapStatus('Could not look up the address. Try again.');
    });
}

function showAddressSearchResults(results){
  const list = document.getElementById('address-map-search-results');
  if(!list) return;

  if(!results.length){
    list.innerHTML = '<li class="address-map-search-empty">No places found in Laoag, San Nicolas, or Batac.</li>';
    list.hidden = false;
    return;
  }

  list.innerHTML = results.map((result, index) => `
    <li>
      <button type="button" class="address-map-search-item" data-index="${index}">${escHtml(result.label)}</button>
    </li>
  `).join('');
  list.hidden = false;

  list.querySelectorAll('.address-map-search-item').forEach(button => {
    button.addEventListener('click', () => {
      const result = results[Number(button.dataset.index)];
      if(!result) return;
      const resultName = String(result.label || '').split(',')[0].trim().toLowerCase();
      const matchedPharmacy = (window.RESIDENCE_CONFIG?.marketplacePharmacies || []).find(pharmacy =>
        String(pharmacy.name || '').trim().toLowerCase() === resultName
      );
      if(matchedPharmacy){
        hideAddressSearchResults();
        openPharmacyProfile(matchedPharmacy.id);
        return;
      }
      const label = String(result.label || '').split(',').slice(0, 5).join(', ').trim();
      setAddressMapPin(result.lat, result.lng, { pan: true, address: label });
      hideAddressSearchResults();
      const searchInput = document.getElementById('address-map-search');
      if(searchInput) searchInput.value = String(result.label || '').split(',')[0].trim();
    });
  });
}

function searchAddressPlaces(query){
  const cfg = window.RESIDENCE_CONFIG || {};
  if(!cfg.geocodeUrl || query.length < 3){
    hideAddressSearchResults();
    return;
  }

  fetch(`${cfg.geocodeUrl}?q=${encodeURIComponent(query)}`)
    .then(response => response.json())
    .then(data => showAddressSearchResults(data.results || []))
    .catch(() => showAddressSearchResults([]));
}

function ensureAddressMap(){
  const mapEl = document.getElementById('address-map');
  const cfg = window.RESIDENCE_CONFIG || {};
  if(!mapEl || !window.L){
    setAddressMapStatus('Map could not be loaded. Check your internet connection and try again.');
    return false;
  }

  if(!addressMapInstance){
    addressMapInstance = L.map(mapEl, {
      zoomControl: true,
      minZoom: cfg.minZoom || 11,
      maxZoom: cfg.maxZoom || 19,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      subdomains: 'abc',
      maxZoom: cfg.maxZoom || 19,
      minZoom: cfg.minZoom || 10,
    }).addTo(addressMapInstance);

    if(typeof window.addServiceAreaOverlay === 'function'){
      window.addServiceAreaOverlay(addressMapInstance, cfg);
    }

    const bounds = addressMapBounds();
    if(bounds){
      addressMapInstance.setMaxBounds(bounds.pad(0.08));
      addressMapInstance.fitBounds(bounds, { padding: [20, 20] });
    }else{
      addressMapInstance.setView([cfg.defaultLat || 18.1978, cfg.defaultLng || 120.5937], cfg.defaultZoom || 12);
    }

    addressMapInstance.on('click', (event) => {
      setAddressMapPin(event.latlng.lat, event.latlng.lng);
    });
  }

  setTimeout(() => addressMapInstance.invalidateSize(), 80);
  return true;
}

let addressMapEditId = 0;

function savedAddressesList(){
  return Array.isArray(window.RESIDENCE_CONFIG?.savedAddresses) ? window.RESIDENCE_CONFIG.savedAddresses : [];
}

function findSavedAddress(id){
  return savedAddressesList().find(item => Number(item.id) === Number(id)) || null;
}

function renderSavedAddresses(addresses){
  const list = document.getElementById('saved-address-list');
  if(!list) return;
  window.RESIDENCE_CONFIG = window.RESIDENCE_CONFIG || {};
  window.RESIDENCE_CONFIG.savedAddresses = addresses || [];

  if(!addresses || !addresses.length){
    list.innerHTML = '<div class="saved-address-empty" id="saved-address-empty"><p>No saved addresses yet</p><span>Add one from the map to reuse it on your next order.</span></div>';
    return;
  }

  list.innerHTML = addresses.map(item => {
    const current = !!item.is_current;
    const id = Number(item.id);
    return `
      <div class="addr-card${current ? ' addr-card--current' : ''}" data-address-id="${id}">
        <div class="ai"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg></div>
        <div class="addr-card-copy">
          <div class="addr-card-title">
            ${current ? 'Current address' : 'Saved address'}
            ${current ? '<span class="addr-default">Default</span>' : ''}
          </div>
          <div class="addr-card-address">${escHtml(item.address || '')}</div>
        </div>
        <div class="addr-card-actions">
          ${current ? '' : `<button type="button" class="btn btn-ghost btn-sm" onclick="setCurrentSavedAddress(${id})">Use as current</button>`}
          <button type="button" class="btn btn-ghost btn-sm" onclick="openAddressMapPicker('edit', ${id})">Edit</button>
          <button type="button" class="addr-card-remove" onclick="removeSavedAddress(${id})" aria-label="Remove address">×</button>
        </div>
      </div>
    `;
  }).join('');
}

function applySavedAddressToUi(payload){
  if(Array.isArray(payload.addresses)){
    renderSavedAddresses(payload.addresses);
  }

  if(!payload.make_current){
    return;
  }

  const address = payload.address || '';
  const lat = payload.latitude;
  const lng = payload.longitude;

  window.USER_LOCATION = {
    lat,
    lng,
    address,
    hasLocation: !!(address && lat != null && lng != null),
    needsLocation: !address,
  };

  if(window.RESIDENCE_CONFIG?.profile){
    window.RESIDENCE_CONFIG.profile.address = address;
    window.RESIDENCE_CONFIG.profile.latitude = lat;
    window.RESIDENCE_CONFIG.profile.longitude = lng;
  }

  const chip = document.querySelector('.app-location-chip');
  const chipText = document.querySelector('.app-location-text');
  const chipChange = document.querySelector('.app-location-change');
  if(chip && chipText){
    chip.classList.remove('app-location-chip--empty');
    chip.title = address;
    chipText.textContent = address || 'Your saved location';
    if(chipChange) chipChange.textContent = 'Change';
    chip.setAttribute('onclick', 'openSavedAddresses()');
  }

  initUserLocationExperience();
}

function closeAddressMapPicker(){
  if(addressMapRequired) return;
  const modal = document.getElementById('address-map-modal');
  if(modal){
    modal.hidden = true;
    modal.classList.remove('is-required');
  }
  hideAddressSearchResults();
  addressMapEditId = 0;
  document.body.style.overflow = '';
}

function openAddressMapPicker(mode, addressId){
  const modal = document.getElementById('address-map-modal');
  const title = document.querySelector('#address-map-title .address-map-title-text');
  const addressInput = document.getElementById('address-map-address');
  const searchInput = document.getElementById('address-map-search');
  const currentWrap = document.getElementById('address-map-current-wrap');
  const currentHint = document.getElementById('address-map-current-hint');
  const currentCheck = document.getElementById('address-map-make-current');
  if(!modal) return;

  const existing = savedAddressesList();
  const editing = mode === 'edit' ? findSavedAddress(addressId) : null;
  addressMapEditId = editing ? Number(editing.id) : 0;

  if(title) title.textContent = addressMapRequired ? 'Add your location' : (editing ? 'Edit Address' : 'Add New Address');
  if(searchInput) searchInput.value = '';
  hideAddressSearchResults();
  setAddressMapStatus('');
  modal.hidden = false;
  modal.classList.toggle('is-required', addressMapRequired);
  document.body.style.overflow = 'hidden';
  const lead = modal.querySelector('.address-map-dialog-head .muted');
  if(lead){
    lead.textContent = addressMapRequired
      ? 'Pin a pickup point in Laoag City, San Nicolas, or Batac City to continue.'
      : 'Search or pin a location on the map to add your delivery address.';
  }
  if(!ensureAddressMap()) return;

  const location = window.USER_LOCATION || {};
  const cfg = window.RESIDENCE_CONFIG || {};
  const hasSaved = existing.length > 0;
  const editingCurrent = !!(editing && editing.is_current);

  if(currentWrap && currentHint && currentCheck){
    if(editingCurrent || !hasSaved){
      currentWrap.hidden = true;
      currentHint.hidden = true;
      currentCheck.checked = true;
    }else{
      currentWrap.hidden = false;
      currentHint.hidden = false;
      currentCheck.checked = false;
      currentHint.textContent = editing
        ? 'Check this to switch your current address to this location. Your other saved addresses stay.'
        : 'Leave unchecked to keep your current address and save this as another location.';
    }
  }

  const editLat = validAddressMapCoordinate(editing?.latitude);
  const editLng = validAddressMapCoordinate(editing?.longitude);
  const userLat = validAddressMapCoordinate(location.lat);
  const userLng = validAddressMapCoordinate(location.lng);
  const startLat = editLat ?? userLat ?? Number(cfg.defaultLat || 18.1978);
  const startLng = editLng ?? userLng ?? Number(cfg.defaultLng || 120.5937);

  if(editing){
    if(addressInput) addressInput.value = editing.address || '';
    setAddressMapPin(startLat, startLng, { pan: true, address: editing.address || '' });
  }else{
    if(addressInput) addressInput.value = '';
    addressMapPick = null;
    if(addressMapMarker && addressMapInstance){
      addressMapInstance.removeLayer(addressMapMarker);
      addressMapMarker = null;
    }
    addressMapInstance.setView([startLat, startLng], location.hasLocation ? 15 : (cfg.defaultZoom || 13));
  }

  setTimeout(() => addressMapInstance && addressMapInstance.invalidateSize(), 120);
}

function initAddressMapPicker(){
  if(addressMapReady) return;
  addressMapReady = true;

  document.querySelectorAll('[data-close-address-map]').forEach(el => {
    el.addEventListener('click', closeAddressMapPicker);
  });

  document.getElementById('address-map-locate-btn')?.addEventListener('click', () => {
    if(!navigator.geolocation){
      setAddressMapStatus('Location is not available in this browser.');
      return;
    }
    setAddressMapStatus('Finding your location…');
    navigator.geolocation.getCurrentPosition(
      (position) => setAddressMapPin(position.coords.latitude, position.coords.longitude, { pan: true }),
      () => setAddressMapStatus('Could not read your current location. Pin it on the map instead.'),
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  });

  const searchInput = document.getElementById('address-map-search');
  searchInput?.addEventListener('input', () => {
    clearTimeout(addressMapSearchTimer);
    const query = searchInput.value.trim();
    if(query.length < 3){
      hideAddressSearchResults();
      return;
    }
    addressMapSearchTimer = setTimeout(() => searchAddressPlaces(query), 280);
  });
  searchInput?.addEventListener('keydown', (event) => {
    if(event.key === 'Enter'){
      event.preventDefault();
      searchAddressPlaces(searchInput.value.trim());
    }
    if(event.key === 'Escape') hideAddressSearchResults();
  });

  document.getElementById('address-map-save-btn')?.addEventListener('click', () => {
    const cfg = window.RESIDENCE_CONFIG || {};
    const addressInput = document.getElementById('address-map-address');
    const address = (addressInput?.value || addressMapPick?.address || '').trim();
    if(!addressMapPick || !address){
      setAddressMapStatus('Pin a location on the map first.');
      return;
    }
    if(!cfg.saveAddressUrl){
      setAddressMapStatus('Could not save this address right now.');
      return;
    }

    const saveBtn = document.getElementById('address-map-save-btn');
    if(saveBtn) saveBtn.disabled = true;
    const makeCurrent = document.getElementById('address-map-make-current')?.checked ? '1' : '0';
    const body = new URLSearchParams({
      action: 'save',
      id: String(addressMapEditId || 0),
      address,
      latitude: String(addressMapPick.lat),
      longitude: String(addressMapPick.lng),
      make_current: makeCurrent,
    });

    fetch(cfg.saveAddressUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    })
      .then(async response => {
        const data = await response.json();
        if(!response.ok) throw new Error(data.error || 'Could not save your address.');
        return data;
      })
      .then(data => {
        if(!data.ok){
          setAddressMapStatus(data.error || 'Could not save your address.');
          return;
        }
        applySavedAddressToUi(data);
        addressMapRequired = false;
        closeAddressMapPicker();
        toast(data.make_current ? 'Now using this as your current address' : 'Address added');
      })
      .catch(error => setAddressMapStatus(error.message || 'Could not save your address. Please try again.'))
      .finally(() => {
        if(saveBtn) saveBtn.disabled = false;
      });
  });
}

function removeSavedAddress(id){
  const cfg = window.RESIDENCE_CONFIG || {};
  if(!cfg.saveAddressUrl || !id) return;

  const body = new URLSearchParams({
    action: 'delete',
    id: String(id),
  });

  fetch(cfg.saveAddressUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  })
    .then(response => response.json())
    .then(data => {
      if(!data.ok){
        toast(data.error || 'Could not remove that address.');
        return;
      }
      applySavedAddressToUi(data);
      toast('Address removed');
    })
    .catch(() => toast('Could not remove that address.'));
}
window.removeSavedAddress = removeSavedAddress;

function setCurrentSavedAddress(id){
  const cfg = window.RESIDENCE_CONFIG || {};
  if(!cfg.saveAddressUrl || !id) return;

  const body = new URLSearchParams({
    action: 'set_current',
    id: String(id),
  });

  fetch(cfg.saveAddressUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  })
    .then(response => response.json())
    .then(data => {
      if(!data.ok){
        toast(data.error || 'Could not update your current address.');
        return;
      }
      applySavedAddressToUi(data);
      toast('Now using this as your current address');
    })
    .catch(() => toast('Could not update your current address.'));
}

function initProfilePasswordToggles(){
  document.querySelectorAll('.profile-fields .input-wrap').forEach(wrap => {
    const input = wrap.querySelector('input');
    const toggle = wrap.querySelector('.toggle-eye');
    if(!input || !toggle || toggle._ready) return;
    toggle._ready = true;

    const sync = () => {
      const hasText = input.value.length > 0;
      toggle.hidden = !hasText;
      if(!hasText && input.type !== 'password'){
        input.type = 'password';
        toggle.classList.remove('is-showing');
        toggle.setAttribute('aria-label', 'Show password');
      }
    };

    toggle.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.classList.toggle('is-showing', show);
      toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    input.addEventListener('input', sync);
    input.addEventListener('change', sync);
    sync();
  });
}

function saveProfileChanges(){
  const cfg = window.RESIDENCE_CONFIG || {};
  const button = document.getElementById('save-profile-btn');
  const fields = document.querySelectorAll('#p-personal .profile-fields > .field');
  const value = (index) => fields[index]?.querySelector('input')?.value.trim() || '';
  const body = new URLSearchParams({
    full_name: value(0), contact_number: value(1).replace(/\D/g, ''), email: value(2),
    current_password: document.getElementById('profile-current-password')?.value || '',
    new_password: document.getElementById('profile-new-password')?.value || '',
    password_confirm: document.getElementById('profile-confirm-password')?.value || ''
  });
  if(button) button.disabled = true;
  fetch(cfg.saveProfileUrl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(response => response.json())
    .then(data => {
      if(data.ok){
        const name = value(0), contact = value(1), email = value(2);
        const displayName = document.getElementById('profile-display-name');
        const displayEmail = document.getElementById('profile-display-email');
        const displayContact = document.getElementById('profile-display-contact');
        if(displayName) displayName.textContent = name;
        if(displayEmail) displayEmail.textContent = email;
        if(displayContact) displayContact.textContent = contact || '—';
        const contactItems = document.querySelectorAll('.profile-contact-line > span');
        if(!displayEmail && contactItems[0]) contactItems[0].lastChild.textContent = email;
        if(!displayContact && contactItems[1]) contactItems[1].lastChild.textContent = contact || '—';
        ['profile-current-password','profile-new-password','profile-confirm-password'].forEach(id => {
          const input = document.getElementById(id); if(input) input.value = '';
        });
      }
      toast(data.ok ? data.message : (data.error || 'Could not save your profile.'));
    })
    .catch(() => toast('Could not save your profile.'))
    .finally(() => { if(button) button.disabled = false; });
}

function focusBrowseSearch(){
  go('dashboard');
  const input = document.getElementById('medicine-search') || document.querySelector('.home-search-input');
  const section = document.getElementById('home-browse');
  if(section){
    section.scrollIntoView({ behavior:'smooth', block:'start' });
  }
  if(input){
    setTimeout(()=>input.focus(), 350);
  }
}

function getMedicineSearchText(card){
  const sub = String(card.dataset.shopSub || '').trim();
  const parts = [
    medicineCardName(card),
    catalogCategoryLabel(card),
    sub.replace(/-/g, ' '),
    shopCategoryLabels[sub] || '',
  ];
  return parts.filter(Boolean).join(' ').toLowerCase();
}

function normalizeSearchText(value){
  return (value || '')
    .toLowerCase()
    .replace(/[^\w\s+-]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function getSearchTerms(query){
  return normalizeSearchText(query).split(' ').filter(Boolean);
}

function termMatchesText(haystack, words, term){
  if(words.some(word => word === term || word.startsWith(term))) return true;

  let idx = haystack.indexOf(term);
  while(idx !== -1){
    const prev = idx === 0 ? ' ' : haystack[idx - 1];
    const next = haystack[idx + term.length] || ' ';
    if(prev === ' ' || next === ' ' || prev === '-' || next === '-') return true;
    idx = haystack.indexOf(term, idx + 1);
  }

  return false;
}

function medicineMatchesQuery(searchText, query){
  const terms = getSearchTerms(query);
  if(!terms.length) return true;

  const haystack = normalizeSearchText(searchText);
  const words = haystack.split(' ').filter(Boolean);
  return terms.every(term => termMatchesText(haystack, words, term));
}

let medicineIndex = [];
let catalogPage = 1;
const CATALOG_PAGE_SIZE = 20;
let catalogCollection = '';
let activeShopCategory = '';
let activeShopSub = '';
let activePharmacyFilter = '';
let activePharmacyName = '';
let activePharmacyAccountId = '';
const medicineOriginalOrder = new Map();

const shopCategoryLabels = {
  medicines:'Medicines',
  'health-products':'Health Products',
  'personal-care':'Personal Care',
  'baby-care':'Baby Care',
  'healthcare-devices':'Healthcare Devices',
  analgesics:'Analgesics',
  antibiotic:'Antibiotics',
  antihistamine:'Antihistamine',
  cardiovascular:'Cardiovascular',
  diabetes:'Diabetes',
  gastrointestinal:'Gastrointestinal',
  respiratory:'Respiratory',
  dermatology:'Dermatology',
  'pain-relief':'Pain Relief',
  maintenance:'Maintenance',
  'cold-flu':'Cold & Flu',
  digestive:'Digestive',
  allergy:'Allergy',
  vitamins:'Vitamins',
  'vitamins-supplements':'Vitamins & Supplements',
  wellness:'Wellness',
  skincare:'Skincare',
  'oral-care':'Oral Care',
  hygiene:'Hygiene'
};

function getMedicineGrid(){
  return document.querySelector('.med-catalog .med-grid');
}

function getMedicinePrice(card){
  const raw = card.dataset.price;
  if(raw) return parseFloat(raw) || 0;
  const text = card.querySelector('.med-price')?.textContent || '';
  return parseFloat(text.replace(/[^\d.]/g, '')) || 0;
}

function getMedicineDistance(card){
  return parseFloat(card.dataset.distance || '999') || 999;
}

function medicineMatchesPharmacyFilter(card){
  if(activePharmacyAccountId){
    return (card.dataset.pharmacyId || '') === activePharmacyAccountId;
  }
  if(!activePharmacyFilter) return true;
  return normalizePharmacyLabel(card.dataset.pharmacy || '') === normalizePharmacyLabel(activePharmacyFilter);
}

function medicineMatchesCollection(card){
  if(catalogCollection === 'new') return card.dataset.isNew === '1';
  if(catalogCollection === 'top') return Number(card.dataset.unitsSold || 0) > 0;
  if(catalogCollection === 'featured') return card.dataset.featured === '1';
  return true;
}

function catalogPageSize(){
  if(catalogCollection || activePharmacyAccountId || activePharmacyFilter) return 12;
  return CATALOG_PAGE_SIZE;
}

function hasCatalogFilters(){
  const input = document.getElementById('medicine-search');
  const q = input?.value.trim() || '';
  return Boolean(q || activeShopCategory || activePharmacyFilter || catalogCollection);
}

function catalogCardMatches(card, query){
  const q = String(query || '').trim();
  return (!q || medicineMatchesQuery(getMedicineSearchText(card), q))
    && medicineMatchesCategory(card)
    && medicineMatchesPharmacyFilter(card)
    && medicineMatchesCollection(card);
}

function spreadCatalogCardsByPharmacy(cards){
  const newestFirst = [...cards].sort((a, b) => String(b.dataset.createdAt || '').localeCompare(String(a.dataset.createdAt || '')));
  const queues = new Map();
  newestFirst.forEach(card => {
    const pharmacyId = card.dataset.pharmacyId || '_none';
    const queue = queues.get(pharmacyId) || [];
    queue.push(card);
    queues.set(pharmacyId, queue);
  });
  const spread = [];
  while(queues.size){
    for(const pharmacyId of [...queues.keys()]){
      const queue = queues.get(pharmacyId);
      if(!queue?.length){
        queues.delete(pharmacyId);
        continue;
      }
      spread.push(queue.shift());
      if(!queue.length) queues.delete(pharmacyId);
    }
  }
  return spread;
}

function getPharmacyById(pharmacyId){
  const pharmacies = window.RESIDENCE_CONFIG?.pharmacies || [];
  return pharmacies.find(pharmacy => pharmacy.id === pharmacyId) || null;
}

function getPharmacyByLabel(label){
  const pharmacies = window.RESIDENCE_CONFIG?.pharmacies || [];
  if(activePharmacyAccountId){
    const byId = getPharmacyById(activePharmacyAccountId);
    if(byId) return byId;
  }
  const normalized = normalizePharmacyLabel(label || '');
  return pharmacies.find(pharmacy => normalizePharmacyLabel(pharmacy.label || '') === normalized) || null;
}

function formatPharmacyContact(contact){
  const digits = String(contact || '').replace(/\D+/g, '');
  if(digits.length === 11){
    return `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`;
  }
  return contact || '—';
}

function openStorePharmacyChat(){
  toast('Pharmacy chat coming soon');
}

function openStoreMapLocation(){
  const pharmacy = getPharmacyById(activePharmacyAccountId) || getPharmacyByLabel(activePharmacyFilter);
  if(pharmacy?.navigate_url){
    window.open(pharmacy.navigate_url, '_blank', 'noopener,noreferrer');
    return;
  }
  go('locator');
}

function populateStoreHero(pharmacy){
  const logoWrap = document.getElementById('home-store-hero-logo-wrap');
  const logoEl = document.getElementById('home-store-hero-logo');
  const bannerEl = document.getElementById('home-store-hero-banner');
  const heroRoot = document.getElementById('home-store-hero');
  const nameEl = document.getElementById('home-store-hero-name');
  const addressEl = document.getElementById('home-store-hero-address');
  const contactEl = document.getElementById('home-store-hero-contact');
  const emailEl = document.getElementById('home-store-hero-email');
  const statusEl = document.getElementById('home-store-hero-status');

  const fallbackName = activePharmacyName || activePharmacyFilter || 'Partner Pharmacy';
  const branch = pharmacy?.branch || fallbackName.split(' — ')[1] || '';
  const displayName = pharmacy
    ? (branch ? `${pharmacy.name} — ${branch}` : pharmacy.name)
    : fallbackName;

  const accent = pharmacy?.color || '#1d5fa8';
  if(heroRoot){
    heroRoot.style.setProperty('--store-accent', accent);
  }
  if(bannerEl){
    bannerEl.style.setProperty('--store-accent', accent);
  }

  if(logoWrap){
    logoWrap.style.background = accent;
  }

  if(logoEl){
    if(pharmacy?.logo_url){
      logoEl.src = pharmacy.logo_url;
      logoEl.hidden = false;
    }else{
      logoEl.removeAttribute('src');
      logoEl.hidden = true;
    }
    logoEl.alt = displayName;
  }

  if(nameEl) nameEl.textContent = displayName;
  if(addressEl) addressEl.textContent = pharmacy?.address || '—';
  if(contactEl) contactEl.textContent = formatPharmacyContact(pharmacy?.contact);
  if(emailEl) emailEl.textContent = pharmacy?.email || '—';

  if(statusEl){
    const parts = [];
    const distance = parseFloat(pharmacy?.distance_km);
    if(Number.isFinite(distance) && distance < 900){
      parts.push(`<span class="home-store-hero-status-pill">${distance.toFixed(1)} km away</span>`);
      if(pharmacy?.travel_time){
        parts.push(`<span class="home-store-hero-status-pill">~${pharmacy.travel_time}</span>`);
      }
    }
    const isOpen = Boolean(pharmacy?.is_open);
    parts.push(`<span class="home-store-hero-status-pill ${isOpen ? 'is-open' : 'is-closed'}">${isOpen ? 'Open now' : 'Closed'}</span>`);
    statusEl.innerHTML = parts.join('');
    statusEl.hidden = parts.length === 0;
  }
}

function updateStoreBrowseView(){
  const partnerHero = document.getElementById('home-partner-hero');
  const storeHero = document.getElementById('home-store-hero');
  const homeMain = document.querySelector('.home-main');
  const categoryBar = document.getElementById('home-category-bar');
  const categoryHost = document.getElementById('home-category-host');
  const storeMode = Boolean(activePharmacyFilter || activePharmacyAccountId);

  if(storeMode){
    populateStoreHero(getPharmacyById(activePharmacyAccountId) || getPharmacyByLabel(activePharmacyFilter));
    if(partnerHero) partnerHero.hidden = true;
    if(storeHero) storeHero.hidden = false;
    if(homeMain) homeMain.classList.add('is-store-browse');
    document.body.classList.add('is-store-browse');
  }else{
    if(partnerHero) partnerHero.hidden = false;
    if(storeHero) storeHero.hidden = true;
    if(homeMain) homeMain.classList.remove('is-store-browse');
    document.body.classList.remove('is-store-browse');
  }

  if(categoryBar && categoryHost && categoryBar.parentElement !== categoryHost){
    categoryHost.appendChild(categoryBar);
  }
}

function clearPharmacyFilter(){
  activePharmacyFilter = '';
  activePharmacyName = '';
  activePharmacyAccountId = '';
  updateStoreBrowseView();
  filterMedicines(document.getElementById('medicine-search')?.value || '');
  document.getElementById('home-partner-hero')?.scrollIntoView({ behavior:'smooth', block:'start' });
}

function browsePharmacyMedicines(pharmacyLabel, displayName, pharmacyAccountId){
  catalogCollection = '';
  activePharmacyFilter = pharmacyLabel || '';
  activePharmacyName = displayName || pharmacyLabel || '';
  activePharmacyAccountId = pharmacyAccountId || '';
  activeShopCategory = '';
  activeShopSub = '';
  updateCategoryNavState();
  closeProductPreview();
  updateStoreBrowseView();
  go('dashboard');

  const input = document.getElementById('medicine-search');
  if(input) input.value = '';
  closeSearchSuggestions();
  filterMedicines('');

  setTimeout(() => {
    document.getElementById('home-store-hero')?.scrollIntoView({ behavior:'smooth', block:'start' });
  }, 80);
}

function catalogCategoryLabel(card){
  const labeled = (card?.dataset?.categoryLabel || '').trim();
  if(labeled) return labeled;
  const fromMeta = (card?.querySelector('.med-cat')?.textContent || '').replace(/^[·•]\s*/, '').trim();
  if(fromMeta) return fromMeta;
  const sub = card?.dataset?.shopSub || '';
  if(sub && shopCategoryLabels[sub]) return shopCategoryLabels[sub];
  return 'Other';
}

function catalogCategoryKey(card){
  return (card?.dataset?.shopCategory || '') + ':' + (card?.dataset?.shopSub || '');
}

function refreshCatalogCategoryOptions(){
  const select = document.getElementById('catalog-category-sort');
  if(!select) return;
  const cards = document.querySelectorAll('.med-catalog .med-grid .med-card');
  const seen = new Map();
  cards.forEach(card => {
    if(!medicineMatchesPharmacyFilter(card)) return;
    const label = catalogCategoryLabel(card);
    if(label === '') return;
    const key = catalogCategoryKey(card);
    if(!seen.has(key)) seen.set(key, { key, label, category: card.dataset.shopCategory || '', sub: card.dataset.shopSub || '' });
  });
  const options = Array.from(seen.values()).sort((a, b) => a.label.localeCompare(b.label, undefined, { sensitivity: 'base' }));
  const current = (activeShopCategory || activeShopSub)
    ? (activeShopCategory + ':' + activeShopSub)
    : '';
  select.innerHTML = '<option value="">All categories</option>' + options.map(item =>
    `<option value="${escapeHtml(item.key)}">${escapeHtml(item.label)}</option>`
  ).join('');
  if(current && Array.from(select.options).some(option => option.value === current)){
    select.value = current;
  }else{
    select.value = '';
  }
}

function syncCatalogCategorySelect(){
  const select = document.getElementById('catalog-category-sort');
  if(!select) return;
  const current = (activeShopCategory || activeShopSub) ? (activeShopCategory + ':' + activeShopSub) : '';
  if(Array.from(select.options).some(option => option.value === current)){
    select.value = current;
  }else{
    select.value = '';
  }
}

function applyMedicineSort(){
  const grid = getMedicineGrid();
  if(!grid) return;
  const cards = Array.from(grid.querySelectorAll('.med-card'));
  let ordered;
  if(catalogCollection === 'new'){
    ordered = [...cards].sort((a, b) => {
      const byDate = String(b.dataset.createdAt || '').localeCompare(String(a.dataset.createdAt || ''));
      if(byDate !== 0) return byDate;
      return medicineCardName(a).localeCompare(medicineCardName(b), undefined, { sensitivity: 'base' });
    });
  }else if(catalogCollection === 'top'){
    ordered = [...cards].sort((a, b) => {
      const bySold = Number(b.dataset.unitsSold || 0) - Number(a.dataset.unitsSold || 0);
      if(bySold !== 0) return bySold;
      return medicineCardName(a).localeCompare(medicineCardName(b), undefined, { sensitivity: 'base' });
    });
  }else{
    ordered = spreadCatalogCardsByPharmacy(cards);
  }
  ordered.forEach(card => grid.appendChild(card));
}

function syncCatalogCollectionHeading(){
  const title = document.querySelector('#home-all-products-head .home-spotlight-title');
  if(title){
    title.textContent = catalogCollection === 'new'
      ? 'New Products'
      : catalogCollection === 'top'
        ? 'Current Top Sellers'
        : catalogCollection === 'featured'
          ? 'Featured Products'
          : 'All Products';
  }
  document.querySelector('.home-main')?.classList.toggle('is-catalog-collection', Boolean(catalogCollection));
}

function initCatalogCategorySort(){
  const select = document.getElementById('catalog-category-sort');
  if(!select || select._categorySortReady) return;
  select._categorySortReady = true;
  refreshCatalogCategoryOptions();
  select.addEventListener('change', () => {
    const value = select.value || '';
    const split = value.indexOf(':');
    if(value === ''){
      activeShopCategory = '';
      activeShopSub = '';
    }else if(split === -1){
      activeShopCategory = value;
      activeShopSub = '';
    }else{
      activeShopCategory = value.slice(0, split);
      activeShopSub = value.slice(split + 1);
    }
    updateCategoryNavState();
    closeCategoryMenus();
    filterMedicines(document.getElementById('medicine-search')?.value || '');
  });
}

function buildMedicineIndex(){
  const browse = document.getElementById('home-browse');
  if(!browse) return [];

  return Array.from(browse.querySelectorAll('.med-grid .med-card')).map(card => ({
    card,
    name: medicineCardName(card),
    generic: medicineCardGeneric(card),
    img: medicineCardImage(card)?.src || '',
    imgAlt: medicineCardImage(card)?.alt || '',
    searchText: getMedicineSearchText(card),
  }));
}

function closeSearchSuggestions(){
  document.getElementById('home-search-field')?.classList.remove('is-open');
}

function medicineMatchesCategory(card){
  if(!activeShopCategory && !activeShopSub) return true;

  const category = card.dataset.shopCategory || '';
  const sub = card.dataset.shopSub || '';
  if(activeShopSub){
    return sub === activeShopSub;
  }
  return Boolean(activeShopCategory && category === activeShopCategory);
}

function getActiveCategoryLabel(){
  if(!activeShopCategory) return '';

  const select = document.getElementById('catalog-category-sort');
  if(select?.selectedOptions?.[0]?.value){
    const selectedLabel = select.selectedOptions[0].textContent.trim();
    if(selectedLabel) return selectedLabel;
  }

  const activeLink = document.querySelector('.home-category-link.is-active span');
  if(activeLink?.textContent.trim()) return activeLink.textContent.trim();

  const activeOption = document.querySelector('.home-category-option.is-active');
  if(activeOption?.textContent.trim()) return activeOption.textContent.trim();

  if(activeShopSub && shopCategoryLabels[activeShopSub]){
    return shopCategoryLabels[activeShopSub];
  }

  return shopCategoryLabels[activeShopCategory] || activeShopCategory;
}

function updateCategoryNavState(){
  document.querySelectorAll('.home-category-link').forEach(link => {
    link.classList.toggle('is-active', link.dataset.category === activeShopCategory && (link.dataset.sub || '') === activeShopSub);
  });

  document.querySelectorAll('.home-category-option').forEach(option => {
    const category = option.dataset.category || '';
    const sub = option.dataset.sub || '';
    option.classList.toggle('is-active', category === activeShopCategory && sub === activeShopSub);
  });

  document.querySelectorAll('.home-category-group').forEach(group => {
    const trigger = group.querySelector('.home-category-trigger');
    const isActive = Array.from(group.querySelectorAll('.home-category-option')).some(option =>
      (option.dataset.category || '') === activeShopCategory && (option.dataset.sub || '') === activeShopSub
    );
    trigger?.classList.toggle('is-active', isActive);
  });
  syncCatalogCategorySelect();
}

function closeCategoryMenus(exceptGroup){
  document.querySelectorAll('.home-category-group').forEach(group => {
    if(group === exceptGroup) return;
    group.classList.remove('is-open');
    const trigger = group.querySelector('.home-category-trigger');
    const menu = group.querySelector('.home-category-menu');
    if(trigger) trigger.setAttribute('aria-expanded', 'false');
    if(menu) menu.hidden = true;
  });
}

function openCategoryMenu(group){
  const trigger = group.querySelector('.home-category-trigger');
  const menu = group.querySelector('.home-category-menu');
  if(!trigger || !menu) return;

  closeProfileMenu();
  closeCategoryMenus(group);
  group.classList.add('is-open');
  trigger.setAttribute('aria-expanded', 'true');
  menu.hidden = false;
}

function toggleCategoryMenu(group){
  if(group.classList.contains('is-open')){
    closeCategoryMenus();
    return;
  }
  openCategoryMenu(group);
}

function setCategoryFilter(category, sub = ''){
  const isSame = category === activeShopCategory && sub === activeShopSub;
  activeShopCategory = isSame ? '' : category;
  activeShopSub = isSame ? '' : sub;

  updateCategoryNavState();
  closeCategoryMenus();

  const input = document.getElementById('medicine-search');
  filterMedicines(input?.value || '');
  document.getElementById('home-browse')?.scrollIntoView({ behavior:'smooth', block:'start' });
}

function initCategoryNav(){
  const nav = document.querySelector('.home-category-nav');
  if(!nav || nav._categoryReady) return;
  nav._categoryReady = true;

  nav.querySelectorAll('.home-category-trigger').forEach(trigger => {
    trigger.addEventListener('click', e => {
      e.stopPropagation();
      const group = trigger.closest('.home-category-group');
      if(!group) return;
      toggleCategoryMenu(group);
    });
  });

  nav.querySelectorAll('.home-category-option').forEach(option => {
    option.addEventListener('click', e => {
      e.stopPropagation();
      setCategoryFilter(option.dataset.category || '', option.dataset.sub || '');
    });
  });

  nav.querySelectorAll('.home-category-link').forEach(link => {
    link.addEventListener('click', () => {
      if(currentResidencePage() !== 'dashboard'){
        go('dashboard');
      }
      closeCategoryMenus();
      setCategoryFilter(link.dataset.category || '', link.dataset.sub || '');
    });
  });

  document.addEventListener('click', e => {
    if(!e.target.closest('.home-category-nav')) closeCategoryMenus();
  });

  document.addEventListener('keydown', e => {
    if(e.key === 'Escape') closeCategoryMenus();
  });
}

function catalogPagerHtml(page, pages){
  if(pages <= 1) return '';
  return `<div class="page-btns pager-pill">${residencePagerControls(page, pages, 'catalog-page')}</div>`;
}

function applyCatalogPage(matches){
  const pager = document.getElementById('catalog-pagination');
  const pageSize = catalogPageSize();
  const total = matches.length;
  const pages = Math.max(1, Math.ceil(total / pageSize));
  catalogPage = Math.min(Math.max(1, catalogPage), pages);
  const start = (catalogPage - 1) * pageSize;
  const end = start + pageSize;

  matches.forEach((card, index) => {
    const onPage = index >= start && index < end;
    card.hidden = !onPage;
    card.classList.toggle('is-search-hidden', !onPage);
    if(onPage){
      card.style.removeProperty('display');
    }else{
      card.style.display = 'none';
    }
  });

  if(!pager) return;
  const from = start + 1;
  const to = Math.min(end, total);
  if(total < 1){
    pager.hidden = true;
    pager.innerHTML = '';
    return;
  }
  pager.hidden = false;
  pager.innerHTML = (total > pageSize ? catalogPagerHtml(catalogPage, pages) : '')
    + '<span class="catalog-page-meta">' + from + '–' + to + ' of ' + total + '</span>';
}

function initCatalogPagination(){
  const pager = document.getElementById('catalog-pagination');
  if(!pager || pager._catalogPagerReady) return;
  pager._catalogPagerReady = true;
  pager.addEventListener('click', event => {
    const button = event.target.closest('[data-catalog-page]');
    if(!button || button.disabled) return;
    const raw = button.getAttribute('data-catalog-page');
    const pages = Math.max(1, Math.ceil(
      Array.from(document.querySelectorAll('.med-catalog .med-grid .med-card')).filter(card => {
        const q = document.getElementById('medicine-search')?.value.trim() || '';
        return catalogCardMatches(card, q);
      }).length / catalogPageSize()
    ));
    if(raw === 'prev') catalogPage -= 1;
    else if(raw === 'next') catalogPage += 1;
    else catalogPage = parseInt(raw, 10) || 1;
    catalogPage = Math.min(Math.max(1, catalogPage), pages);
    filterMedicines(document.getElementById('medicine-search')?.value || '', true);
    document.getElementById('home-browse')?.scrollIntoView({ behavior:'smooth', block:'start' });
  });
}

function filterMedicines(query, keepPage = false){
  const browse = document.getElementById('home-browse');
  if(!browse) return;
  if(!keepPage) catalogPage = 1;
  refreshCatalogCategoryOptions();

  const q = query.trim();
  const cards = browse.querySelectorAll('.med-catalog .med-grid .med-card');
  const emptyEl = browse.querySelector('.med-search-empty');
  const statusEl = browse.querySelector('.med-search-status');
  const grid = browse.querySelector('.med-catalog .med-grid');
  const matches = [];

  cards.forEach(card => {
    const match = catalogCardMatches(card, q);
    if(match){
      matches.push(card);
    }else{
      card.hidden = true;
      card.classList.add('is-search-hidden');
      card.style.display = 'none';
    }
  });

  const visible = matches.length;
  applyMedicineSort();
  const orderedMatches = Array.from(browse.querySelectorAll('.med-catalog .med-grid .med-card')).filter(card => matches.includes(card));
  applyCatalogPage(orderedMatches);

  if(grid) grid.classList.toggle('is-filtering', hasCatalogFilters());

  const homeMain = document.querySelector('.home-main');
  if(homeMain){
    const catalogFiltered = hasCatalogFilters() && !activePharmacyFilter && !activePharmacyAccountId;
    homeMain.classList.toggle('is-catalog-filtered', catalogFiltered);
    homeMain.classList.toggle('is-catalog-empty', catalogFiltered && visible === 0);
  }
  syncCatalogCollectionHeading();

  if(emptyEl){
    emptyEl.hidden = visible > 0 || !hasCatalogFilters();
  }

  if(statusEl){
    const categoryLabel = getActiveCategoryLabel();
    if(q && visible > 0){
      statusEl.textContent = visible === 1
        ? `1 result for “${q}”${categoryLabel ? ` in ${categoryLabel}` : ''}`
        : `${visible} results for “${q}”${categoryLabel ? ` in ${categoryLabel}` : ''}`;
      statusEl.hidden = false;
    }else if(!q && catalogCollection === 'featured' && visible > 0){
      statusEl.textContent = visible === 1 ? '1 featured product from partner pharmacies' : `${visible} featured products from partner pharmacies`;
      statusEl.hidden = false;
    }else if(!q && catalogCollection === 'featured' && visible === 0){
      statusEl.textContent = 'No featured products yet';
      statusEl.hidden = false;
    }else if(!q && catalogCollection === 'new' && visible > 0){
      statusEl.textContent = visible === 1 ? '1 new product' : `${visible} new products`;
      statusEl.hidden = false;
    }else if(!q && catalogCollection === 'new' && visible === 0){
      statusEl.textContent = 'No new products yet';
      statusEl.hidden = false;
    }else if(!q && catalogCollection === 'top' && visible > 0){
      statusEl.textContent = visible === 1 ? '1 top seller' : `${visible} top sellers`;
      statusEl.hidden = false;
    }else if(!q && catalogCollection === 'top' && visible === 0){
      statusEl.textContent = 'No top sellers yet';
      statusEl.hidden = false;
    }else if(!q && activePharmacyFilter && visible > 0){
      statusEl.textContent = visible === 1
        ? `1 medicine at ${activePharmacyName || activePharmacyFilter}`
        : `${visible} medicines at ${activePharmacyName || activePharmacyFilter}`;
      statusEl.hidden = false;
    }else if(!q && activePharmacyFilter && visible === 0){
      statusEl.textContent = `No medicines listed for ${activePharmacyName || activePharmacyFilter} yet`;
      statusEl.hidden = false;
    }else if(!q && categoryLabel && visible > 0){
      statusEl.textContent = visible === 1
        ? `1 product in ${categoryLabel}`
        : `${visible} products in ${categoryLabel}`;
      statusEl.hidden = false;
    }else if(!q && categoryLabel && visible === 0){
      statusEl.textContent = `No products in ${categoryLabel} yet`;
      statusEl.hidden = false;
    }else{
      statusEl.textContent = '';
      statusEl.hidden = true;
    }
  }

}

function updateMedicineSearchPlaceholder(){
  const input = document.getElementById('medicine-search');
  if(!input) return;
  if(currentResidencePage() === 'pharmacy-profile' && activePharmacyProfileId){
    const name = (document.getElementById('pharmacy-profile-name')?.textContent || '').trim() || 'this pharmacy';
    input.placeholder = `Search medicines in ${name}...`;
    input.setAttribute('aria-label', `Search medicines in ${name}`);
    return;
  }
  input.placeholder = 'Search medicines, e.g. Paracetamol, Amoxicillin...';
  input.setAttribute('aria-label', 'Search medicines by name or category');
}

function applyResidenceSearch(query, keepPage = false){
  updateMedicineSearchPlaceholder();
  if(currentResidencePage() === 'pharmacy-profile' && activePharmacyProfileId){
    if(!keepPage) pharmacyProfilePage = 1;
    renderPharmacyProfileProductPage(pharmacyProfileProducts());
    if(String(query || '').trim()){
      document.getElementById('pharmacy-profile-home')?.scrollIntoView({ behavior:'smooth', block:'start' });
    }
    return;
  }
  filterMedicines(query, keepPage);
  if(String(query || '').trim() && currentResidencePage() === 'dashboard'){
    document.getElementById('home-browse')?.scrollIntoView({ behavior:'smooth', block:'nearest' });
  }
}

function initMedicineSearch(){
  const input = document.getElementById('medicine-search');
  if(!input || input._searchReady) return;
  input._searchReady = true;

  medicineIndex = buildMedicineIndex();
  updateMedicineSearchPlaceholder();

  input.addEventListener('input', () => {
    applyResidenceSearch(input.value);
  });

  input.addEventListener('keydown', e => {
    if(e.key === 'Enter'){
      e.preventDefault();
      applyResidenceSearch(input.value);
      if(currentResidencePage() === 'pharmacy-profile'){
        document.getElementById('pharmacy-profile-home')?.scrollIntoView({ behavior:'smooth', block:'start' });
      }else if(currentResidencePage() === 'dashboard'){
        document.getElementById('home-browse')?.scrollIntoView({ behavior:'smooth', block:'start' });
      }
      return;
    }

    if(e.key === 'Escape'){
      if(input.value){
        e.stopPropagation();
        input.value = '';
        applyResidenceSearch('');
      }
    }
  });
}

document.addEventListener('keydown', e => {
  if(e.key === 'Escape'){
    const rxViewer = document.getElementById('order-rx-viewer');
    if(rxViewer && !rxViewer.hidden){
      closeOrderPrescription();
      return;
    }
    if(document.getElementById('residence-cancel-order-modal')?.hidden === false){
      closeResidenceCancelOrderModal();
      return;
    }
    if(document.getElementById('orders-detail-view')?.hidden === false){
      closeOrderDetail();
      return;
    }
    const detail = document.getElementById('product-detail');
    if(detail && !detail.hidden) closeProductPreview();
  }
});

document.addEventListener('click', e => {
  const rxLink = e.target.closest('.od-rx-link');
  if(rxLink){
    e.preventDefault();
    openOrderPrescription(rxLink.getAttribute('data-rx-url') || '');
    return;
  }
  const pickupPreview = e.target.closest('.od-pickup-proof, .od-view-proof-btn');
  if(pickupPreview){
    e.preventDefault();
    openOrderDocumentPreview(
      pickupPreview.getAttribute('data-preview-url') || '',
      pickupPreview.getAttribute('data-preview-title') || 'Proof of pickup'
    );
    return;
  }
  const reportProof = e.target.closest('.resident-report-proof-link[data-preview-url]');
  if(reportProof){
    e.preventDefault();
    openOrderDocumentPreview(
      reportProof.getAttribute('data-preview-url') || '',
      reportProof.getAttribute('data-preview-title') || 'Submitted proof'
    );
    return;
  }
  const notificationItem = e.target.closest('.topbar-notification-item[role="button"], .notif-full[role="button"]');
  if(notificationItem){
    handleResidentNotificationClick(notificationItem);
    return;
  }
  if(!e.target.closest('#topbar-profile-menu')) closeProfileMenu();
  if(!e.target.closest('.topbar-notification-options-wrap')) closeNotificationOptions();
  if(!e.target.closest('#topbar-notification-menu')) closeNotificationMenu();
});

document.addEventListener('keydown', e => {
  if(e.key !== 'Enter' && e.key !== ' ') return;
  const item = e.target.closest('.topbar-notification-item[role="button"], .notif-full[role="button"]');
  if(!item) return;
  e.preventDefault();
  item.click();
});

function pharmacyDisplayLabel(pharmacy){
  if(!pharmacy) return '';
  if(pharmacy.name === 'HealthPlus Pharmacy' || pharmacy.name === 'HealthPlus'){
    return `HealthPlus Pharmacy — ${pharmacy.branch}`;
  }
  if(pharmacy.name === 'The Generics Pharmacy'){
    return `The Generics Pharmacy — ${pharmacy.branch}`;
  }
  return `${pharmacy.name} — ${pharmacy.branch}`;
}

function normalizePharmacyLabel(label){
  return label
    .replace('The Generics —', 'The Generics Pharmacy —')
    .replace('HealthPlus —', 'HealthPlus Pharmacy —');
}

function buildPharmacyDistanceMap(pharmacies){
  const map = new Map();
  (pharmacies || []).forEach(pharmacy => {
    map.set(pharmacyDisplayLabel(pharmacy), parseFloat(pharmacy.distance_km));
    map.set(`${pharmacy.name} — ${pharmacy.branch}`, parseFloat(pharmacy.distance_km));
  });
  return map;
}

function applyCatalogDistances(pharmacies){
  const byId = new Map();
  const byLabel = buildPharmacyDistanceMap(pharmacies);
  (pharmacies || []).forEach(pharmacy => {
    if(pharmacy.id) byId.set(pharmacy.id, parseFloat(pharmacy.distance_km));
  });
  document.querySelectorAll('.med-card').forEach(card => {
    const distance = byId.get(card.dataset.pharmacyId || '') ?? byLabel.get(normalizePharmacyLabel(card.dataset.pharmacy || ''));
    if(Number.isFinite(distance)){
      card.dataset.distance = String(distance);
    }
  });
}

function initUserLocationExperience(){
  const location = window.USER_LOCATION || {};
  const cfg = window.RESIDENCE_CONFIG || {};
  if(!location.hasLocation || !cfg.nearbyUrl) return Promise.resolve();

  return fetch(`${cfg.nearbyUrl}?lat=${encodeURIComponent(location.lat)}&lng=${encodeURIComponent(location.lng)}&limit=12`)
    .then(response => response.json())
    .then(data => {
      if(!data.success) return;
      window.nearbyPharmacies = data.pharmacies || [];
      applyCatalogDistances(window.nearbyPharmacies);
    })
    .catch(() => {});
}

let locatorMapInstance = null;
let locatorMarkersLayer = null;
let locatorCityLayer = null;
let locatorVisiblePharmacyIds = null;
let locatorFilterMode = 'all';

function locatorCityRing(cityKey){
  const overlay = (window.RESIDENCE_CONFIG && window.RESIDENCE_CONFIG.serviceOverlay) || {};
  const polygons = overlay.cityPolygons || {};
  const ring = polygons[cityKey] || [];
  return ring
    .map((point) => [Number(point.lat), Number(point.lng)])
    .filter((pair) => Number.isFinite(pair[0]) && Number.isFinite(pair[1]));
}

function setLocatorServiceOverlayVisible(visible){
  const layer = locatorMapInstance && locatorMapInstance._serviceAreaOverlay;
  if(!layer || !locatorMapInstance) return;
  if(visible){
    if(!locatorMapInstance.hasLayer(layer)) locatorMapInstance.addLayer(layer);
    return;
  }
  if(locatorMapInstance.hasLayer(layer)) locatorMapInstance.removeLayer(layer);
}

function updateLocatorCityOutline(){
  if(!locatorMapInstance || !window.L) return;
  if(locatorCityLayer){
    locatorMapInstance.removeLayer(locatorCityLayer);
    locatorCityLayer = null;
  }
  const ring = locatorCityRing(locatorFilterMode);
  const showCity = ring.length >= 3;
  setLocatorServiceOverlayVisible(!showCity);
  if(!showCity) return;
  locatorCityLayer = L.polygon(ring, {
    color: '#c73e3e',
    weight: 3,
    opacity: 0.95,
    fillColor: '#c73e3e',
    fillOpacity: 0.12,
    interactive: false,
    className: 'city-area-polygon',
  }).addTo(locatorMapInstance);
}

function filterLocatorPharmacies(){
  const input = document.getElementById('locator-search-input');
  const query = String(input?.value || '').trim().toLowerCase();
  const cards = Array.from(document.querySelectorAll('.pharm-card[data-pharmacy-id]'));
  const visibleIds = new Set();

  cards.forEach(card => {
    const matchesSearch = !query || card.textContent.toLowerCase().includes(query);
    const city = String(card.dataset.city || '');
    const matchesMode = locatorFilterMode === 'all' || city === locatorFilterMode;
    const matches = matchesSearch && matchesMode;
    card.hidden = !matches;
    if(matches) visibleIds.add(String(card.dataset.pharmacyId));
  });

  locatorVisiblePharmacyIds = visibleIds;
  initLocatorMap();
}

function initLocatorSearch(){
  const input = document.getElementById('locator-search-input');
  if(input && !input.dataset.ready){
    input.dataset.ready = 'true';
    input.addEventListener('input', filterLocatorPharmacies);
  }

  document.querySelectorAll('[data-locator-filter]').forEach(button => {
    if(button.dataset.ready) return;
    button.dataset.ready = 'true';
    button.addEventListener('click', () => {
      locatorFilterMode = button.dataset.locatorFilter || 'all';
      document.querySelectorAll('[data-locator-filter]').forEach(item => item.classList.toggle('active', item === button));
      filterLocatorPharmacies();
    });
  });
}

function makeLocatorIcon(color, size){
  return L.divIcon({
    className: 'register-map-marker',
    html: `<span style="background:${color};width:${size}px;height:${size}px;border:3px solid #fff;border-radius:50%;display:block;box-shadow:0 2px 8px rgba(0,0,0,.25);"></span>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  });
}

function makeAddressMapPinIcon(){
  return L.divIcon({
    className: 'address-map-pin-marker',
    html: '<span class="address-map-pin"><i></i></span>',
    iconSize: [30, 40],
    iconAnchor: [15, 38],
  });
}

function makePharmacyLocatorIcon(pharmacy, size = 38, selected = false){
  const registeredPharmacy = getMarketplacePharmacy(pharmacy?.id);
  const locatorCard = Array.from(document.querySelectorAll('.pharm-card')).find(card => String(card.dataset.pharmacyId) === String(pharmacy?.id));
  const locatorCardLogo = locatorCard?.dataset.logoUrl || locatorCard?.querySelector('.pharm-card-top img')?.currentSrc || locatorCard?.querySelector('.pharm-card-top img')?.src || '';
  // Prefer the catalog URL: it is resolved for the resident route and matches the pharmacy card logo.
  const logo = String(locatorCardLogo || registeredPharmacy?.logo_url || pharmacy?.logo_url || '../2.png')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  return L.divIcon({
    className: 'pharmacy-map-marker',
    html: `<span class="${selected ? 'is-selected' : ''}" data-pharmacy-id="${String(pharmacy?.id || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;')}" style="width:54px;height:42px;"><img src="${logo}" alt="" onerror="this.src='../2.png'"></span>`,
    iconSize: [54, 52],
    iconAnchor: [27, 52],
  });
}

function initLocatorMap(){
  const mapEl = document.getElementById('locator-map');
  const cfg = window.RESIDENCE_CONFIG || {};
  const location = window.USER_LOCATION || {};
  if(!mapEl || !window.L) return;

  if(!locatorMapInstance){
    locatorMapInstance = L.map(mapEl, {
      zoomControl: true,
      minZoom: cfg.minZoom || 11,
      maxZoom: cfg.maxZoom || 19,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      subdomains: 'abc',
      maxZoom: cfg.maxZoom || 19,
      minZoom: cfg.minZoom || 10,
    }).addTo(locatorMapInstance);

    if(typeof window.addServiceAreaOverlay === 'function'){
      window.addServiceAreaOverlay(locatorMapInstance, cfg);
    }

    locatorMarkersLayer = L.layerGroup().addTo(locatorMapInstance);
  } else {
    setTimeout(() => locatorMapInstance.invalidateSize(), 80);
  }

  locatorMarkersLayer.clearLayers();
  updateLocatorCityOutline();
  const bounds = [];
  if(locatorCityLayer){
    const cityBounds = locatorCityLayer.getBounds();
    bounds.push(cityBounds.getSouthWest());
    bounds.push(cityBounds.getNorthEast());
  }

  if(location.hasLocation){
    L.marker([location.lat, location.lng], {
      icon: makeAddressMapPinIcon(),
      title: 'Your location',
    }).bindPopup('<strong>Your location</strong>').addTo(locatorMarkersLayer);
    bounds.push([location.lat, location.lng]);
  }

  (window.nearbyPharmacies || []).filter(pharmacy => !locatorVisiblePharmacyIds || locatorVisiblePharmacyIds.has(String(pharmacy.id))).forEach((pharmacy) => {
    const lat = parseFloat(pharmacy.latitude);
    const lng = parseFloat(pharmacy.longitude);
    if(!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    L.marker([lat, lng], {
      icon: makePharmacyLocatorIcon(pharmacy, 36, false),
      title: pharmacy.name,
    })
      .bindPopup(`<strong>${pharmacy.name}</strong><br>${pharmacy.branch}<br>${pharmacy.distance_km} km away`)
      .addTo(locatorMarkersLayer);

    bounds.push([lat, lng]);
  });

  if(bounds.length > 1){
    locatorMapInstance.fitBounds(bounds, { padding: [36, 36] });
  }else if(bounds.length === 1){
    locatorMapInstance.setView(bounds[0], 14);
  }else if(cfg.bounds){
    locatorMapInstance.fitBounds([
      [cfg.bounds.south, cfg.bounds.west],
      [cfg.bounds.north, cfg.bounds.east],
    ], { padding: [24, 24] });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  removeCheckoutPrescriptionUploads();
  bindResidenceOrdersTools();
  renderResidenceOrders();
  const trackOrder = new URLSearchParams(location.search).get('track');
  if(trackOrder){
    try { const saved = JSON.parse(sessionStorage.getItem('residence_last_order') || 'null'); if(saved && saved.order_number === trackOrder){ renderTrackingPage(saved); go('tracking'); } } catch(e){}
  }
  const paidReturn = sessionStorage.getItem('residence_paid_clear') === '1' || new URLSearchParams(location.search).get('paid') === '1';
  if(paidReturn){
    sessionStorage.removeItem('residence_paid_clear');
    clearPaidCheckoutItems();
    try{
      const url = new URL(location.href);
      if(url.searchParams.has('paid') || url.searchParams.has('payment_intent_id')){
        url.searchParams.delete('paid');
        url.searchParams.delete('payment_intent_id');
        history.replaceState({}, '', url.pathname + url.search + url.hash);
      }
    }catch(e){}
  }
  const pendingIntent = window.RESIDENCE_CONFIG?.pendingPaymentIntent || new URLSearchParams(location.search).get('payment_intent_id');
  if(pendingIntent){
    completePayMongoPayment(pendingIntent, false);
  }
  fetchResidenceCart();
  applyCatalogDistances(window.RESIDENCE_CONFIG?.pharmacies || []);
  document.querySelectorAll('.med-catalog .med-card').forEach(applySpotlightBadge);
  initMedicineSearch();
  initCatalogPagination();
  initCatalogCategorySort();
  initPharmacyProfileCategorySort();
  filterMedicines(document.getElementById('medicine-search')?.value || '');
  initCategoryNav();
  initProductCarousels();
  initPharmacyMarquee();
  updatePharmacyCarouselNav();
  initUserLocationExperience();
  initProfilePasswordToggles();
  initAddressMapPicker();
  if(window.USER_LOCATION?.needsLocation){
    addressMapRequired = true;
    openAddressMapPicker('add');
  }
  initLocatorSearch();
  initResidenceCancelOrderModal();

  if(pendingIntent){
    go('checkout');
  }else if(paidReturn){
    renderCartPage();
    go('tracking');
  }else if(document.querySelector('.page[data-page="dashboard"].active')){
    go('dashboard');
  }
});
