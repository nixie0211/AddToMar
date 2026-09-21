<?php

declare(strict_types=1);

header('Content-Type: application/javascript; charset=UTF-8');
?>

const MEDICINE_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
const MEDICINE_IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

let medicineUploadApi = null;
window.pharmacyMedicineCatalog = {};

function loadMedicineCatalog(){
  const dataEl = document.getElementById('inventory-medicines-data');
  if(!dataEl) return;

  try {
    const items = JSON.parse(dataEl.textContent || '[]');
    window.pharmacyMedicineCatalog = {};
    items.forEach(function(item){
      if(item && item.id) window.pharmacyMedicineCatalog[item.id] = item;
    });
  } catch (err) {
    window.pharmacyMedicineCatalog = {};
  }
}

function writeMedicineCatalogItem(item){
  if(!item || !item.id) return;
  window.pharmacyMedicineCatalog = window.pharmacyMedicineCatalog || {};
  const existing = window.pharmacyMedicineCatalog[item.id] || {};
  if(!item.image_url && existing.image_url) item.image_url = existing.image_url;
  window.pharmacyMedicineCatalog[item.id] = Object.assign({}, existing, item);

  let dataEl = document.getElementById('inventory-medicines-data');
  if(!dataEl){
    const section = document.getElementById('view-inventory');
    if(!section) return;
    dataEl = document.createElement('script');
    dataEl.type = 'application/json';
    dataEl.id = 'inventory-medicines-data';
    section.appendChild(dataEl);
  }
  dataEl.textContent = JSON.stringify(Object.values(window.pharmacyMedicineCatalog));
}

function finishMedicineSave(data){
  const medicine = data && data.medicine ? data.medicine : null;
  const card = data && data.card ? Object.assign({}, data.card) : null;
  if(medicine){
    writeMedicineCatalogItem(medicine);
    if(card && !card.image_url && medicine.image_url) card.image_url = medicine.image_url;
  }
  const applied = card && typeof applyInventoryMedicineCard === 'function' && applyInventoryMedicineCard(card);
  if(!applied){
    const url = new URL(window.location.href);
    url.searchParams.set('view', 'inventory');
    window.location.href = url.toString();
    return;
  }
  resetMedicineForm();
  if(typeof showView === 'function') showView('inventory', null, { replaceUrl: true });
}

function setMedicineFormMode(mode, medicineName){
  const head = document.querySelector('[data-topbar-head="add-medicine"]');
  const submitLabel = document.getElementById('add-medicine-submit-label');
  if(!head) return;

  const title = head.querySelector('h1');
  const desc = head.querySelector('.desc');

  if(mode === 'edit'){
    if(title) title.textContent = 'Edit Medicine';
    if(desc) desc.textContent = medicineName
      ? 'Update details for ' + medicineName + '.'
      : 'Update the details for this medicine.';
    if(submitLabel) submitLabel.textContent = 'Save changes';
    return;
  }

  if(title) title.textContent = 'Add Medicine';
  if(desc) desc.textContent = 'Fill in the details below to add a medicine to your inventory.';
  if(submitLabel) submitLabel.textContent = 'Save medicine';
}

function resetMedicineForm(){
  const form = document.getElementById('add-medicine-form');
  if(!form) return;

  form.reset();
  const medicineId = document.getElementById('medicine-id');
  if(medicineId) medicineId.value = '';

  medicineUploadApi?.clearPreview?.();
  resetOtherSelectField('med-category', 'med-category-other');
  resetOtherSelectField('med-dosage-form', 'med-dosage-form-other');
  resetOtherSelectField('med-unit', 'med-unit-other');
  setMedicineFormMode('add');
}

function resetOtherSelectField(selectId, otherId){
  const other = document.getElementById(otherId);
  if (other) { other.value = ''; other.hidden = true; other.required = false; }
}

function syncOtherSelectField(selectId, otherId, focusOther){
  const select = document.getElementById(selectId);
  const other = document.getElementById(otherId);
  if (!select || !other) return;
  const show = select.value === 'Other';
  other.hidden = !show;
  other.required = show;
  if (show) {
    if (focusOther !== false) other.focus();
  } else {
    other.value = '';
  }
}

function fillSelectOrOther(selectId, otherId, value){
  const select = document.getElementById(selectId);
  const other = document.getElementById(otherId);
  const next = String(value || '').trim();
  if (!select) return;

  const known = Array.from(select.options).some(function(option){
    return option.value === next && option.value !== '' && option.value !== 'Other';
  });

  if (next !== '' && !known) {
    select.value = 'Other';
    if (other) {
      other.hidden = false;
      other.required = true;
      other.value = next;
    }
    return;
  }

  select.value = next;
  if (other) {
    other.hidden = true;
    other.required = false;
    other.value = '';
  }
}

function applyOtherFormValue(formData, fieldName){
  const selected = String(formData.get(fieldName) || '').trim();
  const custom = String(formData.get(fieldName + '_other') || '').trim();
  if (selected.toLowerCase() === 'other') {
    formData.set(fieldName, custom);
  }
}

function formatExpirationDateInput(event){
  const input = event.target;
  const compact = input.value.replace(/[^0-9mM]/g, '');
  // Let Backspace remove the first separator naturally.
  if ((event.inputType || '').startsWith('delete') && compact.length <= 2) {
    input.value = compact;
    return;
  }
  let formatted = compact.slice(0, 2);
  if (compact.length > 2) formatted += '/' + compact.slice(2, 4);
  if (compact.length > 4) formatted += '/' + compact.slice(4, 8);
  input.value = formatted;
}

function populateMedicineForm(medicine){
  const form = document.getElementById('add-medicine-form');
  if(!form || !medicine) return;

  const setValue = function(name, value){
    const field = form.querySelector('[name="' + name + '"]');
    if(!field) return;
    if(field.tagName === 'SELECT'){
      const nextValue = value ?? '';
      if(nextValue && !Array.from(field.options).some(function(option){ return option.value === nextValue; })){
        const option = document.createElement('option');
        option.value = nextValue;
        option.textContent = nextValue;
        field.appendChild(option);
      }
      field.value = nextValue;
      return;
    }
    field.value = value ?? '';
  };

  const medicineId = document.getElementById('medicine-id');
  if(medicineId) medicineId.value = String(medicine.id || '');

  setValue('name', medicine.name);
  setValue('generic_name', medicine.generic_name);
  setValue('brand', medicine.brand);
  fillSelectOrOther('med-dosage-form', 'med-dosage-form-other', medicine.dosage_form);
  setValue('strength', medicine.strength);
  fillSelectOrOther('med-unit', 'med-unit-other', medicine.unit);
  fillSelectOrOther('med-category', 'med-category-other', medicine.category);
  setValue('description', medicine.description);
  setValue('ingredients', medicine.ingredients);
  setValue('batch_number', medicine.batch_number);
  const expiry = String(medicine.expiration_date || '');
  const isoExpiry = expiry.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  setValue('expiration_date', isoExpiry ? isoExpiry[2] + '/' + isoExpiry[3] + '/' + isoExpiry[1] : expiry);
  setValue('stock_quantity', medicine.stock_quantity);
  setValue('unit_price', Number(medicine.unit_price || 0).toFixed(2));
  setValue('selling_price', Number(medicine.selling_price || 0).toFixed(2));

  const prescription = form.querySelector('[name="prescription_required"]');
  if(prescription) prescription.checked = !!medicine.prescription_required;

  if(medicine.image_url){
    medicineUploadApi?.showImageUrl?.(medicine.image_url);
  } else {
    medicineUploadApi?.clearPreview?.();
  }

  setMedicineFormMode('edit', medicine.name || '');
}

function openAddMedicineForm(){
  resetMedicineForm();
  if(typeof showView === 'function') showView('add-medicine');
}

function openEditMedicineForm(medicineId){
  const medicine = window.pharmacyMedicineCatalog[medicineId];
  if(!medicine){
    return;
  }

  resetMedicineForm();
  populateMedicineForm(medicine);
  if(typeof showView === 'function') showView('add-medicine');
}

function initMedicineUpload(){
  const form = document.getElementById('add-medicine-form');
  const box = document.getElementById('medicine-upload-box');
  const input = document.getElementById('medicine-image-input');
  const placeholder = document.getElementById('medicine-upload-placeholder');
  const previewWrap = document.getElementById('medicine-upload-preview');
  const previewImg = document.getElementById('medicine-upload-image');
  const removeBtn = document.getElementById('medicine-upload-remove');
  const errorEl = document.getElementById('medicine-upload-error');
  const alertEl = document.getElementById('add-medicine-alert');
  const submitBtn = document.getElementById('add-medicine-submit');
  const cancelBtn = document.getElementById('add-medicine-cancel');

  if(!box || !input || !form) return;

  function showUploadError(message){
    errorEl.textContent = message;
    errorEl.hidden = !message;
  }

  function showAlert(message, type){
    if(!alertEl) return;
    alertEl.hidden = !message;
    alertEl.textContent = message || '';
    alertEl.classList.toggle('is-error', type === 'error');
    alertEl.classList.toggle('is-success', type === 'success');
  }

  function clearPreview(){
    input.value = '';
    previewImg.removeAttribute('src');
    previewWrap.hidden = true;
    placeholder.hidden = false;
    box.classList.remove('has-image');
    showUploadError('');
  }

  function showImageUrl(url){
    if(!url){
      clearPreview();
      return;
    }
    previewImg.src = url;
    placeholder.hidden = true;
    previewWrap.hidden = false;
    box.classList.add('has-image');
    showUploadError('');
  }

  function showPreview(file){
    const reader = new FileReader();
    reader.onload = function(e){
      showImageUrl(e.target.result);
    };
    reader.readAsDataURL(file);
  }

  medicineUploadApi = {
    clearPreview: clearPreview,
    showImageUrl: showImageUrl,
  };

  function validateFile(file){
    if(!file) return 'No file selected.';
    if(!MEDICINE_IMAGE_TYPES.includes(file.type)){
      return 'Only PNG, JPG, and WEBP images are allowed.';
    }
    if(file.size > MEDICINE_IMAGE_MAX_BYTES){
      return 'Image must be 5 MB or smaller.';
    }
    return '';
  }

  function handleFile(file){
    const error = validateFile(file);
    if(error){
      showUploadError(error);
      input.value = '';
      return;
    }
    showUploadError('');
    showPreview(file);
  }

  box.addEventListener('click', function(e){
    if(e.target.closest('#medicine-upload-remove')) return;
    input.click();
  });

  box.addEventListener('keydown', function(e){
    if(e.key === 'Enter' || e.key === ' '){
      e.preventDefault();
      input.click();
    }
  });

  input.addEventListener('change', function(){
    handleFile(input.files && input.files[0]);
  });

  removeBtn.addEventListener('click', function(e){
    e.stopPropagation();
    clearPreview();
  });

  ['dragenter', 'dragover'].forEach(function(eventName){
    box.addEventListener(eventName, function(e){
      e.preventDefault();
      e.stopPropagation();
      box.classList.add('dragover');
    });
  });

  ['dragleave', 'drop'].forEach(function(eventName){
    box.addEventListener(eventName, function(e){
      e.preventDefault();
      e.stopPropagation();
      box.classList.remove('dragover');
    });
  });

  box.addEventListener('drop', function(e){
    const file = e.dataTransfer.files && e.dataTransfer.files[0];
    if(!file) return;
    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    handleFile(file);
  });

  cancelBtn?.addEventListener('click', function(){
    resetMedicineForm();
    showAlert('', '');
    if(typeof showView === 'function') showView('inventory');
  });

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const name = (form.querySelector('[name="name"]')?.value || '').trim();
    if(!name){
      showAlert('Enter a medicine name.', 'error');
      form.querySelector('[name="name"]')?.focus();
      return;
    }

    submitBtn.disabled = true;
    const savingEl = document.getElementById('add-medicine-saving');
    function setSaving(on){
      if (savingEl) savingEl.hidden = !on;
      document.body.classList.toggle('add-med-saving-open', on);
    }
    setSaving(true);
    showAlert('', '');

    try {
      const formData = new FormData(form);
      applyOtherFormValue(formData, 'category');
      applyOtherFormValue(formData, 'dosage_form');
      applyOtherFormValue(formData, 'unit');

      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
      });
      const data = await response.json();
      if(!response.ok || !data.success){
        setSaving(false);
        showAlert(data.message || 'Could not save this medicine.', 'error');
        submitBtn.disabled = false;
        return;
      }

      finishMedicineSave(data);
      setSaving(false);
      submitBtn.disabled = false;
    } catch (err) {
      setSaving(false);
      showAlert('Could not save this medicine. Try again.', 'error');
      submitBtn.disabled = false;
    }
  });
}

document.addEventListener('keydown', function(e){
  const savingEl = document.getElementById('add-medicine-saving');
  if (!savingEl || savingEl.hidden) return;
  e.preventDefault();
  e.stopPropagation();
}, true);

document.addEventListener('DOMContentLoaded', function(){
  loadMedicineCatalog();
  initMedicineUpload();
  document.getElementById('med-category')?.addEventListener('change', function(){ syncOtherSelectField('med-category', 'med-category-other'); });
  document.getElementById('med-dosage-form')?.addEventListener('change', function(){ syncOtherSelectField('med-dosage-form', 'med-dosage-form-other'); });
  document.getElementById('med-unit')?.addEventListener('change', function(){ syncOtherSelectField('med-unit', 'med-unit-other'); });
  document.getElementById('med-expiration')?.addEventListener('input', formatExpirationDateInput);
});
