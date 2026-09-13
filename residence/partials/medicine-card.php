<?php
$medicine = $medicine ?? [];
$brandLogo = $brandLogo ?? (function_exists('app_url') ? app_url('2.png') : '../2.png');
$spotlightBadge = $spotlightBadge ?? null;
$showAddButton = $showAddButton ?? true;
$cardClass = trim((string) ($cardClass ?? ''));

$shopCategory = residence_catalog_shop_category((string) ($medicine['category'] ?? ''));
$shopSub = residence_catalog_category_slug((string) ($medicine['category'] ?? ''));
$price = (float) ($medicine['price'] ?? 0);
$stock = (int) ($medicine['stock_quantity'] ?? 0);
$status = (string) ($medicine['status'] ?? 'ok');
$rxRequired = !empty($medicine['prescription_required']);
$imageUrl = trim((string) ($medicine['image_url'] ?? ''));
$storeSlug = (string) ($medicine['store_slug'] ?? residence_store_slug((string) ($medicine['pharmacy_name'] ?? 'Pharmacy')));
$storeLogo = trim((string) ($medicine['pharmacy_logo_url'] ?? '')) ?: $brandLogo;
$description = trim((string) ($medicine['description'] ?? ''));
if ($description === '') {
    $description = 'Available for pickup at ' . ($medicine['pharmacy_name'] ?? 'this pharmacy') . '.';
}

$statusLabel = match ($status) {
    'low' => 'Low stock',
    'out' => 'Out of stock',
    'expiring' => 'Expiring soon',
    'expired' => 'Expired',
    default => '',
};

$badgeLabel = match ($spotlightBadge) {
    'new' => 'New',
    'top' => 'Top Seller',
    'featured' => 'Featured',
    default => '',
};
?>
          <article
            class="med-card<?= $rxRequired ? ' med-card--rx' : '' ?><?= $status === 'expired' ? ' med-card--expired' : '' ?><?= $shopCategory === 'medicines' ? ' med-card--medicine' : '' ?><?= $cardClass !== '' ? ' ' . htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8') : '' ?>"
            role="button"
            tabindex="0"
            data-medicine-id="<?= (int) ($medicine['id'] ?? 0) ?>"
            data-pharmacy-id="<?= htmlspecialchars((string) ($medicine['pharmacy_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            data-pharmacy-name="<?= htmlspecialchars((string) ($medicine['pharmacy_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            data-shop-category="<?= htmlspecialchars($shopCategory, ENT_QUOTES, 'UTF-8') ?>"
            data-shop-sub="<?= htmlspecialchars($shopSub, ENT_QUOTES, 'UTF-8') ?>"
            data-pharmacy="<?= htmlspecialchars((string) ($medicine['pharmacy_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            data-price="<?= htmlspecialchars(number_format($price, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
            data-created-at="<?= htmlspecialchars((string) ($medicine['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            data-is-new="<?= !empty($medicine['is_new']) ? '1' : '0' ?>"
            data-featured="<?= !empty($medicine['is_featured']) ? '1' : '0' ?>"
            data-rx="<?= $rxRequired ? '1' : '0' ?>"
            data-distance="999"
            data-desc="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>"
            onclick="window.openProductPreview?.(this)"
            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.openProductPreview?.(this);}" 
          >
            <?php if ($status === 'expired'): ?>
            <span class="med-card-flag med-card-flag--expired">Expired</span>
            <?php elseif ($badgeLabel !== ''): ?>
            <span class="med-card-flag med-card-flag--<?= htmlspecialchars((string) $spotlightBadge, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <?php elseif ($statusLabel !== ''): ?>
            <span class="med-card-flag med-card-flag--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>

            <?php if ($rxRequired): ?>
            <div class="med-card-frame med-card-frame--rx">
              <div class="med-card-store med-card-store--rx">
                <img src="<?= htmlspecialchars($storeLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" class="med-card-store-logo" width="18" height="18">
                <span><?= htmlspecialchars($storeSlug, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="med-card-rx-visual">
                <?php if ($imageUrl !== ''): ?>
                <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?>" class="med-card-photo med-card-photo--rx">
                <?php else: ?>
                <div class="med-card-photo med-card-photo--placeholder" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg>
                </div>
                <?php endif; ?>
                <span class="med-card-rx-symbol rx-badge" aria-label="Prescription required">Rx</span>
              </div>
            </div>
            <?php else: ?>
            <div class="med-card-frame">
              <div class="med-card-store">
                <img src="<?= htmlspecialchars($storeLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" class="med-card-store-logo" width="18" height="18">
                <span><?= htmlspecialchars($storeSlug, ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="med-card-visual">
                <?php if ($imageUrl !== ''): ?>
                <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?>" class="med-card-photo">
                <?php else: ?>
                <div class="med-card-photo med-card-photo--placeholder" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <h3 class="med-card-name med-name"><?= htmlspecialchars((string) ($medicine['name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="med-generic" hidden><?= htmlspecialchars((string) ($medicine['generic_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="med-card-price med-price">₱<?= htmlspecialchars(number_format($price, 2), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="med-card-meta">
              <span>In stock</span>
              <?php if (trim((string) ($medicine['category'] ?? '')) !== ''): ?>
              <span class="med-cat">· <?= htmlspecialchars((string) $medicine['category'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </p>
            <span class="med-meta-line" hidden><?= htmlspecialchars((string) ($medicine['dosage'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="med-meta-line" hidden><?= htmlspecialchars((string) ($medicine['pharmacy_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($showAddButton): ?>
              <button type="button" class="med-add" onclick="event.stopPropagation(); addToCart(this)">Add to cart</button>
            <?php endif; ?>
          </article>
