<?php
$homeCategories = [];
$customCategories = [];
$standardCategories = ['Analgesics', 'Antibiotics', 'Antihistamine', 'Cardiovascular', 'Diabetes', 'Gastrointestinal', 'Respiratory', 'Dermatology', 'Vitamins'];
foreach ($standardCategories as $label) {
    $homeCategories[] = [
        'label' => $label,
        'category' => residence_catalog_shop_category($label),
        'sub' => residence_catalog_category_slug($label),
    ];
}
foreach (($residenceCatalogMedicines ?? []) as $medicine) {
    $label = trim((string) ($medicine['category'] ?? ''));
    if ($label === '' || strcasecmp($label, 'Other') === 0 || in_array($label, $standardCategories, true)) {
        continue;
    }
    $shopCategory = residence_catalog_shop_category($label);
    $shopSub = residence_catalog_category_slug($label);
    $customCategories[$shopCategory . ':' . $shopSub] = [
        'label' => $label,
        'category' => $shopCategory,
        'sub' => $shopSub,
    ];
}
uasort($customCategories, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));
?>
        <div class="home-category-bar" id="home-category-bar">
          <nav class="home-category-nav" aria-label="Available pharmacy categories">
            <div class="home-category-nav-inner">
              <?php foreach ($homeCategories as $category): ?>
              <button type="button" class="home-category-link" data-category="<?= htmlspecialchars($category['category'], ENT_QUOTES, 'UTF-8') ?>" data-sub="<?= htmlspecialchars($category['sub'], ENT_QUOTES, 'UTF-8') ?>">
                <span><?= htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8') ?></span>
              </button>
              <?php endforeach; ?>
              <?php if ($customCategories !== []): ?>
              <div class="home-category-group">
                <button type="button" class="home-category-trigger" aria-expanded="false">
                  <span>Other</span>
                  <svg class="home-category-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="home-category-menu" hidden>
                  <?php foreach ($customCategories as $category): ?>
                  <button type="button" class="home-category-option" data-category="<?= htmlspecialchars($category['category'], ENT_QUOTES, 'UTF-8') ?>" data-sub="<?= htmlspecialchars($category['sub'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8') ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </nav>
        </div>
