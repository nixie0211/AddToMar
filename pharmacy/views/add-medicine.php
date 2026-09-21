<?php
$addMedicineCategories = ['Antibiotics', 'Analgesics', 'Cardiovascular', 'Respiratory', 'Diabetes', 'Antihistamine', 'Vitamins', 'Gastrointestinal', 'Dermatology', 'Other'];
$addMedicineDosageForms = ['Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Cream', 'Ointment', 'Drops', 'Inhaler', 'Patch', 'Powder', 'Suppository', 'Other'];
$addMedicineUnits = ['Piece', 'Tablet', 'Capsule', 'Bottle', 'Box', 'Strip', 'Vial', 'Tube', 'Sachet', 'Pack', 'Other'];
foreach ($categories as $category) {
    if ($category !== '' && !in_array($category, $addMedicineCategories, true)) {
        $addMedicineCategories[] = $category;
    }
}
?>
      <section class="<?= pharmacy_view_class('add-medicine', $activeView) ?>" id="view-add-medicine" data-live-region="pharmacy-add-medicine" data-live-keys="inventory" data-live-skip="1">
        <form id="add-medicine-form" class="add-med-form" action="api/save-medicine.php" method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="medicine_id" id="medicine-id" value="">
          <p class="add-med-alert" id="add-medicine-alert" hidden role="alert"></p>

          <div class="add-med-layout">
            <aside class="add-med-aside">
              <div class="panel">
                <div class="panel-head">
                  <h3>Medicine image</h3>
                </div>
                <div class="upload-box" id="medicine-upload-box" role="button" tabindex="0" aria-label="Upload medicine image">
                  <input type="file" id="medicine-image-input" name="image" accept="image/png,image/jpeg,image/jpg,image/webp" hidden>
                  <div class="upload-placeholder" id="medicine-upload-placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                    <div class="up-title">Drop image or click to upload</div>
                    <div class="up-sub">PNG, JPG, or WEBP · up to 5 MB</div>
                  </div>
                  <div class="upload-preview" id="medicine-upload-preview" hidden>
                    <div class="upload-preview-frame">
                      <img id="medicine-upload-image" alt="">
                    </div>
                    <button type="button" class="upload-remove" id="medicine-upload-remove">Remove image</button>
                  </div>
                </div>
                <div class="upload-error" id="medicine-upload-error" hidden></div>
              </div>

              <div class="panel">
                <div class="panel-head">
                  <h3>Listing options</h3>
                </div>
                <div class="toggle-row">
                  <div>
                    <div class="t1">Prescription required</div>
                    <div class="t2">Customers must upload a prescription</div>
                  </div>
                  <label class="switch">
                    <input type="checkbox" name="prescription_required" value="1">
                    <span class="slider"></span>
                  </label>
                </div>
              </div>
            </aside>

            <div class="add-med-main">
              <div class="panel">
                <div class="panel-head">
                  <h3>Medicine details</h3>
                </div>
                <div class="form-grid">
                  <div class="field field-span-2">
                    <label for="med-name">Medicine name <span class="req">*</span></label>
                    <input id="med-name" name="name" type="text" placeholder="e.g. Amoxicillin 500mg" required autocomplete="off">
                  </div>
                  <div class="field">
                    <label for="med-generic">Generic name</label>
                    <input id="med-generic" name="generic_name" type="text" placeholder="e.g. Amoxicillin" autocomplete="off">
                  </div>
                  <div class="field">
                    <label for="med-brand">Brand name</label>
                    <input id="med-brand" name="brand" type="text" placeholder="e.g. Amoxil" autocomplete="off">
                  </div>
                  <div class="field">
                    <label for="med-dosage-form">Dosage form</label>
                    <select id="med-dosage-form" name="dosage_form">
                      <option value="">Select dosage form</option>
                      <?php foreach ($addMedicineDosageForms as $form): ?>
                      <option value="<?= htmlspecialchars($form, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($form, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input id="med-dosage-form-other" name="dosage_form_other" type="text" placeholder="Type a specific dosage form" autocomplete="off" hidden>
                  </div>
                  <div class="field">
                    <label for="med-strength">Strength</label>
                    <input id="med-strength" name="strength" type="text" placeholder="e.g. 500mg" autocomplete="off">
                  </div>
                  <div class="field">
                    <label for="med-unit">Unit</label>
                    <select id="med-unit" name="unit">
                      <option value="">Select unit</option>
                      <?php foreach ($addMedicineUnits as $unit): ?>
                      <option value="<?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input id="med-unit-other" name="unit_other" type="text" placeholder="Type a specific unit" autocomplete="off" hidden>
                  </div>
                  <div class="field">
                    <label for="med-category">Category</label>
                    <select id="med-category" name="category">
                      <option value="">Select a category</option>
                      <?php foreach ($addMedicineCategories as $category): ?>
                      <option value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input id="med-category-other" name="category_other" type="text" placeholder="Type a specific category" autocomplete="off" hidden>
                  </div>
                  <div class="field">
                    <label for="med-description">Description</label>
                    <textarea id="med-description" name="description" rows="4" placeholder="Short description of use and indications"></textarea>
                  </div>
                  <div class="field">
                    <label for="med-ingredients">Ingredients</label>
                    <textarea id="med-ingredients" name="ingredients" rows="4" placeholder="Active and inactive ingredients"></textarea>
                  </div>
                </div>
              </div>

              <div class="panel">
                <div class="panel-head">
                  <h3>Stock &amp; pricing</h3>
                </div>
                <div class="form-grid form-grid-3">
                  <div class="field">
                    <label for="med-qty">Stock quantity</label>
                    <input id="med-qty" name="stock_quantity" type="number" min="0" step="1" placeholder="0" value="0">
                  </div>
                  <div class="field">
                    <label for="med-expiration">Expiration date</label>
                    <input id="med-expiration" name="expiration_date" type="text" inputmode="numeric" placeholder="mm/dd/yyyy or 02/mm/2027" pattern="^(?:\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{4}|\d{1,2}\/(?:mm|MM)\/\d{4})$">
                  </div>
                  <div class="field">
                    <label for="med-batch">Batch number</label>
                    <input id="med-batch" name="batch_number" type="text" placeholder="e.g. AB-1182" autocomplete="off">
                  </div>
                  <div class="field">
                    <label for="med-unit-price">Unit price (₱)</label>
                    <input id="med-unit-price" name="unit_price" type="number" min="0" step="0.01" placeholder="0.00" value="0.00">
                  </div>
                  <div class="field">
                    <label for="med-sell">Selling price (₱)</label>
                    <input id="med-sell" name="selling_price" type="number" min="0" step="0.01" placeholder="0.00" value="0.00">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="form-actions add-med-form-actions">
            <button type="button" class="btn" id="add-medicine-cancel">Cancel</button>
            <button type="submit" class="btn btn-accent" id="add-medicine-submit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6L9 17l-5-5"/></svg>
              <span id="add-medicine-submit-label">Save medicine</span>
            </button>
          </div>
        </form>
        <div class="add-med-saving" id="add-medicine-saving" hidden>
          <div class="add-med-saving-backdrop" aria-hidden="true"></div>
          <div class="add-med-saving-card" role="status" aria-live="assertive" aria-busy="true">
            <span class="add-med-saving-spinner" aria-hidden="true"></span>
            <p>Saving</p>
          </div>
        </div>
      </section>
