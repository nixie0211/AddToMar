        <article class="product-detail" id="product-detail" hidden aria-labelledby="product-detail-name">
          <button type="button" class="product-detail-back" onclick="closeProductPreview()">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Back to browse
          </button>

          <div class="product-detail-layout">
            <div class="product-detail-media">
              <div class="product-detail-visual">
                <div class="product-detail-image-stage">
                  <div class="product-detail-badges" id="product-detail-badges"></div>
                  <img id="product-detail-img" src="" alt="">
                </div>
              </div>
            </div>

            <div class="product-detail-info">
              <button type="button" class="product-detail-pharmacy" id="product-detail-pharmacy" hidden aria-label="View pharmacy profile" onclick="openPharmacyProfile(this.dataset.pharmacyId)">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                <span id="product-detail-pharmacy-name"></span>
              </button>
              <div class="product-detail-title">
                <h1 id="product-detail-name"></h1>
                <p class="product-detail-price" id="product-detail-price"></p>
              </div>
              <p class="product-detail-meta" id="product-detail-meta"></p>

              <div class="product-detail-section" id="product-detail-bottle-section" hidden>
                <p class="product-detail-label">bottle size</p>
                <div class="product-detail-options" id="product-detail-size-options" role="radiogroup" aria-label="Bottle size"></div>
              </div>

              <div class="product-detail-section" id="product-detail-qty-section">
                <div class="product-detail-qty">
                  <span class="product-detail-qty-label">Quantity:</span>
                  <div class="qty-ctrl product-detail-qty-ctrl">
                    <button type="button" onclick="productDetailQtyChange(-1)" aria-label="Decrease quantity">-</button>
                    <span class="qn" id="product-detail-qty">1</span>
                    <button type="button" onclick="productDetailQtyChange(1)" aria-label="Increase quantity">+</button>
                  </div>
                </div>
              </div>

              <div class="product-detail-image-details">
                <div class="product-detail-detail-block">
                  <button type="button" class="product-detail-detail-toggle" aria-expanded="true" aria-controls="product-detail-desc" onclick="toggleProductDetailInfo(this, 'product-detail-desc')">
                    <span>Description</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                  </button>
                  <p id="product-detail-desc"></p>
                </div>
                <div class="product-detail-detail-block" id="product-detail-ingredients-wrap">
                  <button type="button" class="product-detail-detail-toggle" aria-expanded="true" aria-controls="product-detail-generic" onclick="toggleProductDetailInfo(this, 'product-detail-generic')">
                    <span>Ingredients</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                  </button>
                  <p id="product-detail-generic"></p>
                </div>
              </div>

              <div class="product-detail-actions">
                <button type="button" class="product-detail-add" id="product-detail-add" onclick="addPreviewToCart()">
                  <span id="product-detail-add-label">Add to cart</span>
                </button>
                <button type="button" class="product-detail-buy" id="product-detail-buy" onclick="buyNowFromPreview()">
                  <span id="product-detail-buy-label">Buy now</span>
                </button>
              </div>

            </div>
          </div>

          <section class="product-detail-related" id="product-detail-related" hidden aria-labelledby="product-detail-related-title">
            <div class="product-detail-related-head">
              <div>
                <h2 id="product-detail-related-title">More from this pharmacy</h2>
                <p class="product-detail-related-copy">Other items available at <button type="button" class="product-detail-related-pharmacy" id="product-detail-related-sub" onclick="openPharmacyProfile(this.dataset.pharmacyId)" aria-label="View pharmacy profile"></button></p>
              </div>
              <button type="button" class="product-detail-related-view-all" id="product-detail-related-view-all" hidden onclick="openPharmacyProfile(this.dataset.pharmacyId)">View all</button>
            </div>
            <div class="product-detail-related-grid" id="product-detail-related-grid"></div>
          </section>
        </article>
