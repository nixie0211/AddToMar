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
                <p class="home-spotlight-empty"><?php
                  echo match ($sectionId) {
                      'featured-products' => 'No featured products yet.',
                      'new-products' => 'No new products added today.',
                      'top-sellers' => 'No top sellers yet. Products appear here after they are sold.',
                      default => 'No products available in this section yet.',
                  };
                ?></p>
                <?php else: ?>
                <div class="home-carousel-wrap">
                  <div class="home-spotlight-carousel">
                    <?php foreach ($sectionItems as $medicine): ?>
                      <?php
                      $spotlightBadge = $sectionBadge;
                      $showAddButton = false;
                      $cardClass = 'med-card--spotlight';
                      include RESIDENCE_ROOT . '/partials/medicine-card.php';
                      ?>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
              </section>
