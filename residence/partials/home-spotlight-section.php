<?php
$section = $section ?? [];
$sectionId = htmlspecialchars((string) ($section['id'] ?? ''), ENT_QUOTES, 'UTF-8');
$sectionTitle = htmlspecialchars((string) ($section['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$sectionItems = $section['items'] ?? [];
$sectionBadge = $section['badge'] ?? null;
?>
              <section class="home-spotlight-section home-spotlight-section--<?= $sectionId ?>" aria-labelledby="<?= $sectionId ?>-title">
                <div class="home-spotlight-head">
                  <span class="home-spotlight-line" aria-hidden="true"></span>
                  <h2 class="home-spotlight-title" id="<?= $sectionId ?>-title"><?= $sectionTitle ?></h2>
                  <span class="home-spotlight-line" aria-hidden="true"></span>
                </div>

                <?php if ($sectionItems === []): ?>
                <p class="home-spotlight-empty"><?= $sectionId === 'featured-products' ? 'No featured products yet.' : ($sectionId === 'new-products' ? 'No new products added today.' : 'No products available in this section yet.') ?></p>
                <?php else: ?>
                <div class="home-carousel-wrap" data-carousel-wrap="<?= $sectionId ?>">
                  <button
                    type="button"
                    class="home-carousel-arrow home-carousel-prev"
                    aria-label="Previous <?= $sectionTitle ?>"
                  >
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                  </button>

                  <div class="home-spotlight-carousel" data-carousel="<?= $sectionId ?>">
                    <?php foreach ($sectionItems as $medicine): ?>
                      <?php
                      $spotlightBadge = $sectionBadge;
                      $showAddButton = false;
                      $cardClass = 'med-card--spotlight';
                      include RESIDENCE_ROOT . '/partials/medicine-card.php';
                      ?>
                    <?php endforeach; ?>
                  </div>

                  <button
                    type="button"
                    class="home-carousel-arrow home-carousel-next"
                    aria-label="Next <?= $sectionTitle ?>"
                  >
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                  </button>
                </div>
                <?php endif; ?>
              </section>
