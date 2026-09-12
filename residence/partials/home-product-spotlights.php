<?php
$spotlightSections = [
    [
        'id' => 'new-products',
        'title' => 'New Products',
        'items' => $residenceNewProducts ?? [],
        'badge' => 'new',
    ],
    [
        'id' => 'featured-products',
        'title' => 'Featured Products',
        'items' => $residenceFeaturedProducts ?? [],
        'badge' => 'featured',
    ],
];
?>
            <div class="home-spotlights" id="home-spotlights">
              <?php foreach ($spotlightSections as $section): ?>
              <?php include RESIDENCE_ROOT . '/partials/home-spotlight-section.php'; ?>
              <?php endforeach; ?>
            </div>
