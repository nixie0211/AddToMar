      <section class="page" data-page="pharmacy-profile" data-live-region="residence-pharmacy-profile" data-live-keys="pharmacies,catalog" data-live-skip-active="1">
        <style>
          /* View-local fallback: keeps the pharmacy hero correct even if shared CSS is cached. */
          [data-page="pharmacy-profile"] .pharmacy-profile-dialog{position:relative}
          [data-page="pharmacy-profile"] .pharmacy-profile-top-status{position:absolute;top:108px;left:144px;z-index:10;display:flex;align-items:center;gap:9px;font-size:12px;font-weight:800}
          [data-page="pharmacy-profile"] .pharmacy-profile-top-status .profile-state{display:inline-flex;align-items:center;gap:7px;color:#bf3a45}
          [data-page="pharmacy-profile"] .pharmacy-profile-top-status .profile-state.is-open{color:#087c5d}
          [data-page="pharmacy-profile"] .pharmacy-profile-top-status .profile-state i{display:block;width:7px;height:7px;border-radius:50%;background:currentColor}
          [data-page="pharmacy-profile"] .pharmacy-profile-top-status .pharmacy-profile-status-distance{padding-left:10px;border-left:1px solid rgba(75,96,117,.28);color:#4b6075}
          [data-page="pharmacy-profile"] .pharmacy-profile-report--hero{position:absolute;top:22px;right:24px;z-index:11;display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:0;border-radius:10px;background:rgba(255,255,255,.9);color:#b33a48;font:inherit;font-size:14px;font-weight:800;line-height:1.2;cursor:pointer}
          [data-page="pharmacy-profile"] .pharmacy-profile-report--hero svg{display:block;width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
          [data-page="pharmacy-profile"] .pharmacy-profile-report--hero:hover{background:#fff5f5}
          @media(max-width:600px){[data-page="pharmacy-profile"] .pharmacy-profile-top-status{top:89px;left:114px;font-size:10px}[data-page="pharmacy-profile"] .pharmacy-profile-report--hero{top:16px;right:16px;padding:9px 14px;font-size:13px}[data-page="pharmacy-profile"] .pharmacy-profile-report--hero svg{width:16px;height:16px}}
        </style>
        <div class="pharmacy-profile-page">
          <section class="pharmacy-profile-dialog" aria-labelledby="pharmacy-profile-name">
            <div class="pharmacy-profile-banner"></div>
            <div class="pharmacy-profile-top-status" id="pharmacy-profile-status"></div>
            <button type="button" class="pharmacy-profile-report pharmacy-profile-report--hero" onclick="reportPharmacy()">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 21V4m0 1h10l-1 4 1 4H5"/></svg>
              <span>Report</span>
            </button>
            <div class="pharmacy-profile-logo"><img id="pharmacy-profile-logo" alt=""></div>
            <div class="pharmacy-profile-content">
              <div class="pharmacy-profile-title-row">
                <h2 id="pharmacy-profile-name">Pharmacy</h2>
              </div>
              <div class="pharmacy-profile-meta"><p id="pharmacy-profile-contact">—</p><span id="pharmacy-profile-address"></span><span id="pharmacy-profile-sold"></span></div>
              <div class="pharmacy-profile-home" id="pharmacy-profile-home"></div>
            </div>
          </section>
          <?php include RESIDENCE_ROOT . '/partials/footer.php'; ?>
        </div>
      </section>
