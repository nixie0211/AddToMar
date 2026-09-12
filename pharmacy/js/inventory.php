<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

function initInventoryFilters(){
  const grid = document.getElementById('inventory-grid');
  const searchInput = document.getElementById('inventory-search');
  const categoryFilter = document.getElementById('inventory-category-filter');
  const statusFilter = document.getElementById('inventory-status-filter');
  const emptyFilter = document.getElementById('inventory-empty-filter');

  if(!grid) return;
  if(grid.dataset.invBound === '1') {
    grid.dispatchEvent(new Event('inventory-filter-refresh'));
    return;
  }
  grid.dataset.invBound = '1';

  const cards = Array.from(grid.querySelectorAll('.med-card'));

  function applyFilters(){
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

  searchInput?.addEventListener('input', applyFilters);
  categoryFilter?.addEventListener('change', applyFilters);
  statusFilter?.addEventListener('change', applyFilters);

  grid.addEventListener('click', function(e){
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
