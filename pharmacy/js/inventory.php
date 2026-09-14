<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

function escapeInventoryHtml(value){
  return String(value ?? '').replace(/[&<>"']/g, function(char){
    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]);
  });
}

function formatInventoryPeso(value){
  const amount = Number(value || 0);
  return '₱' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function inventoryCardContext(){
  const section = document.getElementById('view-inventory');
  const existing = document.querySelector('#inventory-grid .med-card');
  const store = existing?.querySelector('.med-card-store span, .med-card-store--rx span');
  const logo = existing?.querySelector('.med-card-store-logo');
  return {
    storeSlug: store ? store.textContent.trim() : 'store',
    logoUrl: (section && section.dataset.storeLogo) || (logo ? logo.getAttribute('src') : '') || '../2.png',
  };
}

function ensureInventoryGrid(){
  let grid = document.getElementById('inventory-grid');
  if (grid) return grid;

  const body = document.querySelector('#view-inventory .inventory-panel-body');
  if (!body) return null;

  body.innerHTML =
    '<div class="inventory-grid" id="inventory-grid">' +
      '<div class="inventory-table-head" aria-hidden="true">' +
        '<span>Medicine</span><span>Category</span><span>Status</span><span>Stock</span>' +
        '<span>Unit price</span><span>Selling price</span><span>Expiration date</span><span>Batch no.</span><span>Actions</span>' +
      '</div>' +
    '</div>' +
    '<p class="inventory-empty-filter" id="inventory-empty-filter" hidden>No medicines match your search.</p>';

  grid = document.getElementById('inventory-grid');
  if (grid) delete grid.dataset.invBound;
  initInventoryFilters();
  return grid;
}

function ensureInventoryCategoryOption(category){
  const select = document.getElementById('inventory-category-filter');
  const value = String(category || '').trim();
  if (!select || value === '') return;
  const key = value.toLowerCase();
  const exists = Array.from(select.options).some(function(option){
    return option.value === key;
  });
  if (exists) return;
  const option = document.createElement('option');
  option.value = key;
  option.textContent = value;
  select.appendChild(option);
}

function buildInventoryCardHtml(card, featured){
  const ctx = inventoryCardContext();
  const id = Number(card.id || 0);
  const name = escapeInventoryHtml(card.name || 'Medicine');
  const category = escapeInventoryHtml(card.category || '—');
  const statusKey = escapeInventoryHtml(card.status || 'ok');
  const statusClass = escapeInventoryHtml(card.status_class || 'badge-green');
  const statusLabel = escapeInventoryHtml(card.status_label || 'In Stock');
  const search = escapeInventoryHtml(card.search || '');
  const categoryKey = escapeInventoryHtml(String(card.category || '').trim().toLowerCase());
  const stock = Number(card.stock || 0);
  const requiresRx = !!card.requires_rx;
  const isNew = !!card.is_new;
  const imageUrl = card.image_url ? escapeInventoryHtml(card.image_url) : '';
  const price = escapeInventoryHtml(card.price_label || formatInventoryPeso(card.selling_price || card.unit_price));
  const unitPrice = escapeInventoryHtml(formatInventoryPeso(card.unit_price));
  const sellingPrice = escapeInventoryHtml(formatInventoryPeso(card.selling_price));
  const expiration = escapeInventoryHtml(card.expiration_display || '—');
  const batch = escapeInventoryHtml(card.batch_number || '—');
  const store = escapeInventoryHtml(ctx.storeSlug);
  const logo = escapeInventoryHtml(ctx.logoUrl);
  const featuredOn = featured ? ' is-featured' : '';
  const featuredClass = featured ? ' is-on' : '';
  const featuredPressed = featured ? 'true' : 'false';
  const featuredLabel = featured ? 'Remove from featured products' : 'Feature this product on the resident dashboard';
  const featuredTitle = featured ? 'Featured on resident dashboard' : 'Feature on resident dashboard';
  const rxClass = requiresRx ? ' med-card--rx' : '';
  const expiredClass = statusKey === 'expired' ? ' med-card--expired' : '';

  let flag = '';
  if (statusKey === 'expired') {
    flag = '<span class="med-card-flag med-card-flag--expired">Expired</span>';
  } else if (isNew) {
    flag = '<span class="med-card-new">New</span>';
  } else if (statusKey !== 'ok') {
    flag = '<span class="med-card-flag med-card-flag--' + statusKey + '">' + statusLabel + '</span>';
  }

  const photo = imageUrl
    ? '<img src="' + imageUrl + '" alt="' + name + '" class="med-card-photo' + (requiresRx ? ' med-card-photo--rx' : '') + '">'
    : '<div class="med-card-photo med-card-photo--placeholder" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></div>';

  const productVisual = requiresRx
    ? '<div class="med-card-frame med-card-frame--rx"><div class="med-card-store med-card-store--rx"><img src="' + logo + '" alt="" class="med-card-store-logo" width="18" height="18"><span>' + store + '</span></div><div class="med-card-rx-visual">' + photo + '<span class="med-card-rx-symbol" aria-label="Prescription required">Rx</span></div></div>'
    : '<div class="med-card-frame"><div class="med-card-store"><img src="' + logo + '" alt="" class="med-card-store-logo" width="18" height="18"><span>' + store + '</span></div><div class="med-card-visual">' + photo + '</div></div>';

  return (
    '<article class="med-card' + rxClass + expiredClass + featuredOn + '" data-medicine-id="' + id + '" data-search="' + search + '" data-category="' + categoryKey + '" data-status="' + statusKey + '" data-featured="' + (featured ? '1' : '0') + '">' +
      '<button type="button" class="med-card-heart' + featuredClass + '" data-feature-medicine="' + id + '" aria-pressed="' + featuredPressed + '" aria-label="' + featuredLabel + '" title="' + featuredTitle + '">' +
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>' +
      '</button>' +
      flag +
      '<div class="med-card-product">' + productVisual +
        '<h3 class="med-card-name">' + name + '</h3>' +
        '<p class="med-card-price">' + price + '</p>' +
        '<p class="med-card-meta"><span>' + stock + ' in stock</span>' + (card.category ? '<span>· ' + category + '</span>' : '') + '</p>' +
      '</div>' +
      '<span class="med-table-category">' + category + '</span>' +
      '<span class="med-table-status ' + statusClass + '">' + statusLabel + '</span>' +
      '<span class="med-table-value">' + stock + '</span>' +
      '<span class="med-table-value">' + unitPrice + '</span>' +
      '<span class="med-table-value">' + sellingPrice + '</span>' +
      '<span class="med-table-value">' + expiration + '</span>' +
      '<span class="med-table-value">' + batch + '</span>' +
      '<button type="button" class="med-card-edit" data-edit-medicine="' + id + '">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>' +
        'Edit' +
      '</button>' +
    '</article>'
  );
}

function applyInventoryMedicineCard(card){
  if (!card || !card.id) return false;
  const grid = ensureInventoryGrid();
  if (!grid) return false;

  const existing = grid.querySelector('.med-card[data-medicine-id="' + card.id + '"]');
  const featured = existing ? existing.dataset.featured === '1' : false;
  if (existing && !card.is_new && existing.querySelector('.med-card-new')) {
    card.is_new = true;
  }

  const html = buildInventoryCardHtml(card, featured);
  const wrap = document.createElement('div');
  wrap.innerHTML = html.trim();
  const nextCard = wrap.firstElementChild;
  if (!nextCard) return false;

  if (existing) {
    existing.replaceWith(nextCard);
  } else {
    const head = grid.querySelector('.inventory-table-head');
    if (head && head.nextSibling) {
      grid.insertBefore(nextCard, head.nextSibling);
    } else {
      grid.appendChild(nextCard);
    }
  }

  ensureInventoryCategoryOption(card.category);
  initInventoryFilters();
  return true;
}

function initInventoryFilters(){
  const grid = document.getElementById('inventory-grid');
  const searchInput = document.getElementById('inventory-search');
  const categoryFilter = document.getElementById('inventory-category-filter');
  const statusFilter = document.getElementById('inventory-status-filter');
  const emptyFilter = document.getElementById('inventory-empty-filter');

  if(!grid) return;

  function applyFilters(){
    const cards = Array.from(grid.querySelectorAll('.med-card'));
    const query = (searchInput?.value || '').trim().toLowerCase();
    const category = (categoryFilter?.value || '').trim().toLowerCase();
    const status = (statusFilter?.value || '').trim().toLowerCase();
    let visible = 0;

    cards.forEach(function(card){
      const matchesSearch = !query || (card.dataset.search || '').includes(query);
      const matchesCategory = !category || (card.dataset.category || '') === category;
      const matchesStatus = !status || (card.dataset.status || '') === status;
      const show = matchesSearch && matchesCategory && matchesStatus;
      card.hidden = !show;
      if(show) visible++;
    });

    if(emptyFilter){
      emptyFilter.hidden = visible > 0;
    }
    grid.hidden = visible === 0;
  }

  if(grid.dataset.invBound === '1') {
    applyFilters();
    return;
  }
  grid.dataset.invBound = '1';
  grid.addEventListener('inventory-filter-refresh', applyFilters);

  function toggleFeaturedMedicine(button){
    const medicineId = parseInt(button.getAttribute('data-feature-medicine') || '0', 10);
    if(medicineId < 1 || button.dataset.busy === '1') return;

    const nextFeatured = button.classList.contains('is-on') ? 0 : 1;
    button.dataset.busy = '1';
    const body = new FormData();
    body.append('medicine_id', String(medicineId));
    body.append('featured', String(nextFeatured));

    fetch('api/toggle-featured.php', { method:'POST', body, credentials:'same-origin' })
      .then(function(response){ return response.json(); })
      .then(function(data){
        if(!data || !data.success) throw new Error(data?.message || 'Could not update featured status.');
        const on = !!data.featured;
        button.classList.toggle('is-on', on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        button.setAttribute('aria-label', on ? 'Remove from featured products' : 'Feature this product on the resident dashboard');
        button.title = on ? 'Featured on resident dashboard' : 'Feature on resident dashboard';
        const card = button.closest('.med-card');
        if(card){
          card.classList.toggle('is-featured', on);
          card.dataset.featured = on ? '1' : '0';
        }
      })
      .catch(function(){
        window.alert('Could not update featured status. Try again.');
      })
      .finally(function(){
        delete button.dataset.busy;
      });
  }

  searchInput?.addEventListener('input', applyFilters);
  categoryFilter?.addEventListener('change', applyFilters);
  statusFilter?.addEventListener('change', applyFilters);

  grid.addEventListener('click', function(e){
    const heartBtn = e.target.closest('[data-feature-medicine]');
    if(heartBtn){
      e.preventDefault();
      e.stopPropagation();
      toggleFeaturedMedicine(heartBtn);
      return;
    }
    const editBtn = e.target.closest('[data-edit-medicine]');
    if(!editBtn) return;
    e.preventDefault();
    const medicineId = parseInt(editBtn.getAttribute('data-edit-medicine') || '0', 10);
    if(medicineId > 0 && typeof openEditMedicineForm === 'function'){
      openEditMedicineForm(medicineId);
    }
  });

  applyFilters();
}

document.addEventListener('DOMContentLoaded', initInventoryFilters);
document.addEventListener('livesync:applied', function () {
  const grid = document.getElementById('inventory-grid');
  const searchInput = document.getElementById('inventory-search');
  if (grid) delete grid.dataset.invBound;
  if (searchInput) delete searchInput.dataset.liveBound;
  initInventoryFilters();
  if (typeof loadMedicineCatalog === 'function') loadMedicineCatalog();
});
