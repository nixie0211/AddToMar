<!-- ================= INVENTORY VIEW ================= -->
      <section class="<?= pharmacy_view_class('inventory', $activeView) ?>" id="view-inventory" data-live-region="pharmacy-inventory" data-live-keys="inventory">
        <div class="panel inventory-panel">
          <div class="filters-bar">
            <div class="search-box inventory-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
              <input id="inventory-search" type="search" placeholder="Search medicines…" autocomplete="off" aria-label="Search medicines" data-live-preserve="inventory-search">
            </div>
            <select class="filter-select" id="inventory-category-filter" aria-label="Filter by category" data-live-preserve="inventory-category">
              <option value="">All categories</option>
              <?php foreach ($categories as $category): ?>
              <option value="<?= htmlspecialchars(strtolower($category), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <select class="filter-select" id="inventory-status-filter" aria-label="Filter by stock status" data-live-preserve="inventory-status">
              <option value="">All statuses</option>
              <option value="ok">In stock</option>
              <option value="low">Low stock</option>
              <option value="out">Out of stock</option>
              <option value="expiring">Expiring soon</option>
            </select>
            <div class="filter-spacer"></div>
            <button class="btn btn-accent" type="button" onclick="openAddMedicineForm()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>Add medicine</button>
          </div>

          <div class="inventory-panel-body">
          <?php if (empty($medicines)): ?>
            <?= pharmacy_render_empty('No medicines in inventory yet. Add your first medicine to get started.') ?>
          <?php else: ?>
          <div class="inventory-grid" id="inventory-grid">
            <div class="inventory-table-head" aria-hidden="true">
              <span>Medicine</span><span>Category</span><span>Status</span><span>Stock</span><span>Unit price</span><span>Selling price</span><span>Expiration date</span><span>Batch no.</span><span>Actions</span>
            </div>
            <?php
              $storeSlug = pharmacy_store_slug($pharmacyName);
              $statusMap = pharmacy_status_map();
            ?>
            <?php foreach ($medicines as $medicine): ?>
            <?php
              $statusKey = (string) ($medicine['status'] ?? 'ok');
              $status = $statusMap[$statusKey] ?? $statusMap['ok'];
              $imageUrl = pharmacy_medicine_image_url($medicine);
              $isNew = pharmacy_medicine_is_new($medicine);
              $requiresRx = pharmacy_medicine_requires_prescription($medicine);
              $categoryKey = strtolower(trim((string) ($medicine['category'] ?? '')));
              $searchBlob = strtolower(implode(' ', [
                  (string) ($medicine['name'] ?? ''),
                  (string) ($medicine['generic_name'] ?? ''),
                  (string) ($medicine['brand'] ?? ''),
                  (string) ($medicine['category'] ?? ''),
              ]));
            ?>
            <article
              class="med-card<?= $requiresRx ? ' med-card--rx' : '' ?><?= $statusKey === 'expired' ? ' med-card--expired' : '' ?>"
              data-medicine-id="<?= (int) ($medicine['id'] ?? 0) ?>"
              data-search="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>"
              data-category="<?= htmlspecialchars($categoryKey, ENT_QUOTES, 'UTF-8') ?>"
              data-status="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"
            >
              <?php if ($statusKey === 'expired'): ?>
              <span class="med-card-flag med-card-flag--expired">Expired</span>
              <?php elseif ($isNew): ?>
              <span class="med-card-new">New</span>
              <?php elseif ($statusKey !== 'ok'): ?>
              <span class="med-card-flag med-card-flag--<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status['t'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>

              <div class="med-card-product">
              <?php if ($requiresRx): ?>
              <div class="med-card-frame med-card-frame--rx">
                <div class="med-card-store med-card-store--rx">
                  <svg class="med-card-cross" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/></svg>
                  <span><?= htmlspecialchars($storeSlug, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="med-card-rx-visual">
                  <?php if ($imageUrl): ?>
                  <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?>" class="med-card-photo med-card-photo--rx">
                  <?php else: ?>
                  <div class="med-card-photo med-card-photo--placeholder" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg>
                  </div>
                  <?php endif; ?>
                  <span class="med-card-rx-symbol" aria-label="Prescription required">Rx</span>
                </div>
              </div>
              <?php else: ?>
              <div class="med-card-frame">
                <div class="med-card-store">
                  <img src="<?= htmlspecialchars(function_exists('app_url') ? app_url('2.png') : '../2.png', ENT_QUOTES, 'UTF-8') ?>" alt="" class="med-card-store-logo" width="18" height="18">
                  <span><?= htmlspecialchars($storeSlug, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="med-card-visual">
                  <?php if ($imageUrl): ?>
                  <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?>" class="med-card-photo">
                  <?php else: ?>
                  <div class="med-card-photo med-card-photo--placeholder" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

              <h3 class="med-card-name"><?= htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="med-card-price"><?= htmlspecialchars(pharmacy_medicine_card_price($medicine), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="med-card-meta">
                <span><?= (int) ($medicine['stock_quantity'] ?? 0) ?> in stock</span>
                <?php if (trim((string) ($medicine['category'] ?? '')) !== ''): ?>
                <span>· <?= htmlspecialchars((string) $medicine['category'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </p>
              </div>
              <span class="med-table-category"><?= htmlspecialchars((string) ($medicine['category'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="med-table-status <?= htmlspecialchars($status['c'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status['t'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="med-table-value"><?= (int) ($medicine['stock_quantity'] ?? 0) ?></span>
              <span class="med-table-value">₱<?= number_format((float) ($medicine['unit_price'] ?? 0), 2) ?></span>
              <span class="med-table-value">₱<?= number_format((float) ($medicine['selling_price'] ?? 0), 2) ?></span>
              <span class="med-table-value"><?= htmlspecialchars(!empty($medicine['expiration_date']) ? pharmacy_expiration_display((string) $medicine['expiration_date']) : '—', ENT_QUOTES, 'UTF-8') ?></span>
              <span class="med-table-value"><?= htmlspecialchars((string) ($medicine['batch_number'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              <button type="button" class="med-card-edit" data-edit-medicine="<?= (int) ($medicine['id'] ?? 0) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                Edit
              </button>
            </article>
            <?php endforeach; ?>
          </div>
          <p class="inventory-empty-filter" id="inventory-empty-filter" hidden>No medicines match your search.</p>
          <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($medicines)): ?>
        <script type="application/json" id="inventory-medicines-data"><?= json_encode(array_map('pharmacy_medicine_editor_payload', $medicines), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <?php endif; ?>
      </section>
