      <!-- ========================= DASHBOARD ========================= -->
      <section class="page active" data-page="dashboard" data-live-region="residence-dashboard" data-live-keys="catalog,pharmacies">
        <div class="home-dashboard-layout">
          <div class="home-main">
            <?php include RESIDENCE_ROOT . '/partials/store-hero.php'; ?>
            <?php include RESIDENCE_ROOT . '/partials/home-pharmacy-hero.php'; ?>
            <div class="home-spotlights home-spotlights--top-sellers">
            <?php include RESIDENCE_ROOT . '/partials/home-top-sellers.php'; ?>
            </div>
            <?php include RESIDENCE_ROOT . '/partials/home-product-spotlights.php'; ?>

            <div class="home-browse" id="home-browse">
              <?php include RESIDENCE_ROOT . '/partials/browse-catalog.php'; ?>
              <?php include RESIDENCE_ROOT . '/partials/product-detail.php'; ?>
            </div>
            <?php include RESIDENCE_ROOT . '/partials/footer.php'; ?>
          </div>
        </div>
      </section>
