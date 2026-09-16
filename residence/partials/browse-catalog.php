        <div class="med-catalog" id="med-catalog">
        <div class="home-all-products-head" id="home-all-products-head">
          <span class="home-spotlight-line" aria-hidden="true"></span>
          <h2 class="home-spotlight-title">All Products</h2>
          <span class="home-spotlight-line" aria-hidden="true"></span>
        </div>
        <p class="med-search-status" hidden aria-live="polite"></p>
        <div class="med-grid">
          <?php
          $catalogMedicines = $residenceCatalogMedicines ?? [];
          $brandLogo = function_exists('app_url') ? app_url('2.png') : '../2.png';
          $topSellerIds = [];
          foreach ($residenceTopSellers ?? [] as $topSeller) {
              $topId = (int) ($topSeller['id'] ?? 0);
              if ($topId > 0) {
                  $topSellerIds[$topId] = true;
              }
          }

          foreach ($catalogMedicines as $medicine):
              $spotlightBadge = residence_catalog_product_badge($medicine, $topSellerIds);
              $isTopSeller = isset($topSellerIds[(int) ($medicine['id'] ?? 0)]);
              $showAddButton = false;
              $cardClass = '';
              include RESIDENCE_ROOT . '/partials/medicine-card.php';
          endforeach;
          unset($spotlightBadge, $isTopSeller, $showAddButton, $cardClass);
          ?>
          </div>

        <?php if (($catalogMedicines ?? []) === []): ?>
        <div class="med-search-empty">
          <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <h3>No medicines listed yet</h3>
        </div>
        <?php else: ?>
        <div class="med-search-empty" hidden>
          <h3>No medicines found</h3>
        </div>
        <?php endif; ?>
        </div>
