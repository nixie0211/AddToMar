        <div class="med-catalog" id="med-catalog">
        <p class="med-search-status" hidden aria-live="polite"></p>
        <div class="med-grid">
          <?php
          $catalogMedicines = $residenceCatalogMedicines ?? [];
          $brandLogo = function_exists('app_url') ? app_url('2.png') : '../2.png';

          foreach ($catalogMedicines as $medicine):
              $spotlightBadge = null;
              $showAddButton = true;
              $cardClass = '';
              include RESIDENCE_ROOT . '/partials/medicine-card.php';
          endforeach;
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
