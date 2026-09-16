    </div>
  </div>
</div>

<div id="toast"><span class="tick"><svg class="icon" style="width:12px;height:12px; stroke:#fff;" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg></span><span id="toast-msg">Done</span></div>

<aside id="resident-report-floating-panel" class="resident-report-floating-panel" aria-live="polite" hidden></aside>

<style>
  #pharmacy-report-modal[hidden]{display:none!important}
  #pharmacy-report-modal{position:fixed!important;inset:0!important;z-index:3000!important;display:grid!important;place-items:center!important;padding:28px!important;font-family:inherit!important}
  #pharmacy-report-modal .residence-report-backdrop{position:absolute!important;inset:0!important;border:0!important;background:rgba(38,82,89,.42)!important}
  #pharmacy-report-modal .residence-report-dialog{position:relative!important;width:min(680px,calc(100vw - 56px))!important;max-height:calc(100vh - 56px)!important;overflow:auto!important;border-radius:18px!important;background:#fff!important;box-shadow:0 24px 64px rgba(23,64,71,.28)!important}
  #pharmacy-report-modal .residence-report-head{display:flex!important;align-items:flex-start!important;gap:16px!important;padding:24px 28px 20px!important;border-bottom:1px solid #e3f0ef!important}
  #pharmacy-report-modal .residence-report-title-icon{display:grid!important;place-items:center!important;flex:0 0 56px!important;width:56px!important;height:56px!important;border-radius:14px!important;background:#c9f7ee!important}
  #pharmacy-report-modal .residence-report-title-icon svg{width:28px!important;height:28px!important;fill:none!important;stroke:#087d75!important;stroke-width:2!important;stroke-linecap:round!important;stroke-linejoin:round!important}
  #pharmacy-report-modal .residence-report-head>div:nth-child(2){flex:1!important}
  #pharmacy-report-modal .residence-report-head h3{margin:0 0 8px!important;color:#163541!important;font-size:24px!important;font-weight:800!important;line-height:1.2!important}
  #pharmacy-report-modal .residence-report-head p{max-width:none!important;margin:0!important;color:#5d7782!important;font-size:14px!important;font-weight:500!important;line-height:1.5!important}
  #pharmacy-report-modal .residence-report-head button{border:0!important;background:transparent!important;color:#3b7080!important;font-size:32px!important;line-height:1!important;cursor:pointer!important}
  #pharmacy-report-modal .residence-report-form{display:grid!important;gap:20px!important;padding:24px 28px 20px!important}
  #pharmacy-report-modal .residence-report-target{display:none!important}
  #pharmacy-report-modal .residence-report-form label{display:grid!important;gap:8px!important;color:#1a4050!important;font-size:14px!important;font-weight:800!important}
  #pharmacy-report-modal .residence-report-form label>b{display:flex!important;align-items:center!important;gap:10px!important;font-size:14px!important}
  #pharmacy-report-modal .residence-report-form label>b svg{width:18px!important;height:18px!important;fill:none!important;stroke:#087d76!important;stroke-width:2!important;stroke-linecap:round!important;stroke-linejoin:round!important}
  #pharmacy-report-modal .residence-report-form label>b em{color:#e14c49!important;font-style:normal!important}
  #pharmacy-report-modal .residence-report-form label>b span{color:#718b96!important;font-size:13px!important;font-weight:600!important}
  #pharmacy-report-modal select,#pharmacy-report-modal textarea{box-sizing:border-box!important;width:100%!important;border:1px solid #c4e5e4!important;border-radius:12px!important;background:#fff!important;color:#244854!important;font:inherit!important;font-size:15px!important}
  #pharmacy-report-modal select{height:48px!important;padding:0 14px!important;border-color:#68ded4!important}
  #pharmacy-report-modal textarea{min-height:110px!important;padding:14px 16px!important;resize:vertical!important;line-height:1.45!important}
  #pharmacy-report-modal select:focus,#pharmacy-report-modal textarea:focus{outline:2px solid rgba(63,207,194,.18)!important;border-color:#21c8bc!important}
  #pharmacy-report-modal .residence-report-form small{margin-left:28px!important;color:#668390!important;font-size:13px!important;font-weight:500!important}
  #pharmacy-report-modal .residence-report-upload{position:relative!important;display:flex!important;align-items:center!important;justify-content:center!important;gap:14px!important;min-height:96px!important;border:1px dashed #bde5e7!important;border-radius:12px!important;background:#fbffff!important;overflow:hidden!important}
  #pharmacy-report-modal .residence-report-upload>svg{display:block!important;flex:0 0 32px!important;width:32px!important;height:32px!important;max-width:32px!important;max-height:32px!important;fill:none!important;stroke:#078b82!important;stroke-width:2!important;stroke-linecap:round!important;stroke-linejoin:round!important;overflow:visible!important}
  #pharmacy-report-modal .residence-report-file-button{padding:10px 16px!important;border-radius:999px!important;background:#c9f6ef!important;color:#087b72!important;font-size:13px!important;font-weight:800!important}
  #pharmacy-report-modal .residence-report-file-name{color:#5d7782!important;font-size:13px!important;font-weight:600!important}
  #pharmacy-report-modal .residence-report-upload input{position:absolute!important;inset:0!important;width:100%!important;height:100%!important;opacity:0!important;cursor:pointer!important}
  #pharmacy-report-modal .residence-report-error{margin:0!important;color:#bd3030!important;font-size:13px!important;font-weight:700!important}
  #pharmacy-report-modal .residence-report-actions{display:flex!important;justify-content:flex-end!important;gap:12px!important;margin:0 -28px -20px!important;padding:18px 28px!important;border-top:1px solid #e3f0ef!important}
  #pharmacy-report-modal .residence-report-actions .btn{min-height:44px!important;padding:0 22px!important;border-radius:12px!important;font-size:14px!important;font-weight:800!important}
  #pharmacy-report-modal .residence-report-actions .btn-ghost{border:1px solid #d5e9e9!important;background:#fff!important;color:#087c74!important}
  #pharmacy-report-modal .residence-report-actions .btn-primary{background:#078d82!important;color:#fff!important;box-shadow:0 6px 13px rgba(0,122,111,.25)!important}
  #pharmacy-report-modal .residence-report-actions .btn-primary span{display:none!important}
  #pharmacy-report-modal #pharmacy-report-other-wrap[hidden]{display:none!important}
</style>
<div class="residence-report-modal" id="pharmacy-report-modal" hidden>
  <button type="button" class="residence-report-backdrop" onclick="closePharmacyReportModal()" aria-label="Close report form"></button>
  <section class="residence-report-dialog" role="dialog" aria-modal="true" aria-labelledby="pharmacy-report-title">
    <div class="residence-report-head"><div class="residence-report-title-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5M10 13h5M10 17h5"/></svg></div><div><h3 id="pharmacy-report-title">Report Pharmacy</h3><p>Help us keep AddToMar safe. Share any issues, concerns, or feedback about this pharmacy.</p></div><button type="button" onclick="closePharmacyReportModal()" aria-label="Close">&times;</button></div>
    <form id="pharmacy-report-form" class="residence-report-form" novalidate onsubmit="submitPharmacyReport(event)">
      <input type="hidden" name="pharmacy_id" id="pharmacy-report-pharmacy-id">
      <p class="residence-report-target" id="pharmacy-report-target"></p>
      <label><b><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"/><path d="m9.5 12 1.7 1.7 3.6-3.6"/></svg>Reason for report <em>*</em></b><select name="reason" id="pharmacy-report-reason" required onchange="togglePharmacyReportOtherReason()"><option value="">Select a reason</option><option value="Incorrect pharmacy information">Incorrect pharmacy information</option><option value="Unsafe or counterfeit medicine">Unsafe or counterfeit medicine</option><option value="Unprofessional service">Unprofessional service</option><option value="Other">Other</option></select></label>
      <label id="pharmacy-report-other-wrap" hidden><b><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 18l-2 3 4-1h11a3 3 0 0 0 3-3v-9a3 3 0 0 0-3-3H6a3 3 0 0 0-3 3v7a3 3 0 0 0 2 3z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/></svg>Specify the reason</b><textarea name="other_reason" id="pharmacy-report-other" rows="3" maxlength="500" placeholder="Describe the issue in detail..."></textarea></label>
      <label><b><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>Details <span>(optional)</span></b><textarea name="details" rows="3" maxlength="500" placeholder="Include anything that may help us review this report..."></textarea></label>
      <label><b><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v14H5z"/><path d="m7 15 3-3 2 2 2-2 3 3M9 9h.01"/></svg>Proof of report</b><small>Upload a photo or file (JPG, PNG, WEBP, or PDF - up to 5 MB).</small><span class="residence-report-upload"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg><span class="residence-report-file-button">Choose File</span><span class="residence-report-file-name" id="pharmacy-report-file-name">No file chosen</span><input type="file" name="proof" id="pharmacy-report-proof" accept="image/jpeg,image/png,image/webp,application/pdf" required onchange="updatePharmacyReportFileName(this)"></span></label>
      <p class="residence-report-error" id="pharmacy-report-error" role="alert" hidden></p>
      <div class="residence-report-actions"><button type="button" class="btn btn-ghost" onclick="closePharmacyReportModal()">Cancel</button><button type="submit" class="btn btn-primary">Submit report</button></div>
    </form>
  </section>
</div>

<div class="pay-modal" id="paymongo-auth-modal" hidden>
  <div class="pay-modal-backdrop" onclick="closePayMongoAuthModal()"></div>
  <section class="pay-modal-dialog pay-modal-dialog--auth" role="dialog" aria-modal="true" aria-labelledby="paymongo-auth-title">
    <div class="pay-modal-head">
      <div>
        <p class="pay-modal-kicker">PayMongo test payment</p>
        <h3 id="paymongo-auth-title">Authorize test payment</h3>
      </div>
      <button type="button" class="pay-modal-close" onclick="closePayMongoAuthModal()" aria-label="Close">×</button>
    </div>
    <div class="pay-modal-wait">
      <p>Complete the PayMongo test payment in the popup window.</p>
      <p class="pay-modal-wait-hint">If the popup did not open, allow popups for this site, then try again.</p>
      <button type="button" class="btn btn-accent" id="paymongo-reopen-btn" onclick="reopenPayMongoAuthPopup()">Open payment popup</button>
    </div>
  </section>
</div>
<div class="pay-modal" id="paymongo-receipt-modal" hidden>
  <div class="pay-modal-backdrop" onclick="closePayMongoReceiptModal()"></div>
  <section class="pay-modal-dialog pay-modal-dialog--receipt" role="dialog" aria-modal="true" aria-labelledby="paymongo-receipt-title">
    <div class="pay-modal-head">
      <div>
        <p class="pay-modal-kicker">Payment confirmed</p>
        <h3 id="paymongo-receipt-title">Receipt</h3>
      </div>
      <button type="button" class="pay-modal-close" onclick="closePayMongoReceiptModal()" aria-label="Close">×</button>
    </div>
    <p class="pay-modal-note" id="paymongo-receipt-note"></p>
    <div class="pay-modal-actions">
      <button type="button" class="btn btn-primary" onclick="printPayMongoReceipt()">Print receipt</button>
      <button type="button" class="btn btn-ghost" onclick="closePayMongoReceiptModal()">Done</button>
    </div>
    <iframe id="paymongo-receipt-frame" title="Payment receipt"></iframe>
  </section>
</div>

<style>
#address-map-modal[hidden]{display:none!important}
#address-map-modal{position:fixed!important;inset:0!important;z-index:5000!important;display:flex!important;align-items:center!important;justify-content:center!important;padding:20px!important}
#address-map-modal .address-map-modal-backdrop{position:absolute!important;inset:0!important;background:rgba(15,28,32,.48)!important}
#address-map-modal .address-map-dialog{position:relative!important;z-index:1!important;width:min(760px,calc(100vw - 40px))!important;max-height:calc(100vh - 36px)!important;overflow:auto!important;padding:28px!important;border-radius:16px!important;background:#fff!important;box-shadow:0 18px 50px rgba(15,28,32,.25)!important}
#address-map-modal .address-map-dialog-head{align-items:flex-start!important;margin:0 0 18px!important;padding:0 42px 0 0!important}#address-map-modal .address-map-dialog-head h3{display:flex!important;align-items:center!important;gap:12px!important;margin:0 0 4px!important;color:#173642!important;font-size:19px!important;font-weight:800!important}.address-map-title-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#e3faf2;color:#07896f}.address-map-title-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}#address-map-modal .address-map-dialog-head p{margin:0 0 0 50px!important;color:#72818c!important;font-size:12px!important;line-height:1.4!important}#address-map-modal .address-map-close{position:absolute!important;top:20px!important;right:20px!important;display:grid!important;place-items:center!important;width:30px!important;height:30px!important;padding:0!important;border:0!important;background:transparent!important;color:#315765!important;font-size:25px!important;line-height:1!important;box-shadow:none!important}
#address-map-modal .address-map-toolbar{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:12px!important;align-items:center!important;margin:0 0 16px!important}#address-map-modal .address-map-search-wrap{position:relative!important;min-width:0!important}#address-map-modal .address-map-search-icon{position:absolute!important;z-index:3!important;top:50%!important;left:16px!important;width:18px!important;height:18px!important;transform:translateY(-50%)!important;fill:none!important;stroke:#55717d!important;stroke-width:2!important;stroke-linecap:round!important;stroke-linejoin:round!important;pointer-events:none!important}#address-map-modal .address-map-search-wrap .field-input{height:44px!important;padding:0 15px 0 46px!important;border:1px solid #e0eaeb!important;border-radius:11px!important;font-size:13px!important}#address-map-modal #address-map-locate-btn{justify-self:end!important;white-space:nowrap!important;min-height:40px!important;padding:0 18px!important;border:1px solid #a9e5d8!important;border-radius:20px!important;background:#fff!important;color:#087e6c!important;font-size:12.5px!important;font-weight:800!important;box-shadow:none!important}
#address-map-modal .address-map-canvas{display:block!important;width:100%!important;height:285px!important;min-height:285px!important;margin:0 0 12px!important;border:1px solid #d7e6e4!important;border-radius:11px!important;overflow:hidden!important;background:#edf5f3!important}
.address-map-pin{position:relative;display:block;width:28px;height:28px;border:3px solid #fff;border-radius:50% 50% 50% 0;background:#e53935;transform:rotate(-45deg);box-shadow:0 3px 8px rgba(100,20,20,.34)}.address-map-pin i{position:absolute;top:7px;left:7px;width:8px;height:8px;border-radius:50%;background:#b71c1c}
#address-map-modal .address-map-status{margin:0 0 8px!important;font-size:12px!important}#address-map-modal .address-map-preview{margin:0 0 12px!important}#address-map-modal .address-map-preview span{margin:0 0 6px!important;font-size:12px!important;color:#31515d!important}#address-map-modal .address-map-preview .field-input{height:40px!important;padding:0 32px 0 13px!important;border:1px solid #dce9e8!important;border-radius:9px!important;font-size:12px!important}
#address-map-modal .address-map-current-opt{display:flex!important;align-items:center!important;gap:7px!important;margin:0 0 2px!important;color:#22414c!important;font-size:12px!important;font-weight:800!important;line-height:18px!important}#address-map-modal .address-map-current-opt input{width:14px!important;height:14px!important;margin:0!important;flex:0 0 14px!important;accent-color:#07896f!important}#address-map-modal .address-map-current-hint{margin:0 0 15px 23px!important;font-size:11px!important;line-height:1.35!important}#address-map-modal .address-map-actions{margin:0!important;padding-top:5px!important;gap:10px!important}#address-map-modal .address-map-actions .btn{min-height:38px!important;padding:0 19px!important;border-radius:19px!important;font-size:12px!important;font-weight:800!important}#address-map-modal .address-map-actions .btn-ghost{border:1px solid #d8e6e6!important;background:#fff!important;color:#137d70!important}#address-map-modal .address-map-actions .btn-primary{border:0!important;background:#087d68!important;box-shadow:0 5px 12px rgba(4,110,89,.2)!important}
@media(max-width:600px){#address-map-modal{padding:12px!important}#address-map-modal .address-map-dialog{width:calc(100vw - 24px)!important;padding:16px!important}#address-map-modal .address-map-canvas{height:280px!important;min-height:280px!important}}
</style>
<div class="address-map-modal" id="address-map-modal" hidden>
  <div class="address-map-modal-backdrop" data-close-address-map></div>
  <div class="address-map-dialog" role="dialog" aria-modal="true" aria-labelledby="address-map-title">
    <div class="address-map-dialog-head">
      <div>
        <h3 id="address-map-title"><span class="address-map-title-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span><span class="address-map-title-text">Add New Address</span></h3>
        <p class="muted">Search or pin a location on the map to add your delivery address.</p>
      </div>
      <button type="button" class="address-map-close" data-close-address-map aria-label="Close">&times;</button>
    </div>
    <div class="address-map-toolbar">
      <div class="address-map-search-wrap">
        <svg class="address-map-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></svg>
        <input type="search" id="address-map-search" class="field-input" placeholder="Search a street, barangay, or landmark" autocomplete="off">
        <ul id="address-map-search-results" class="address-map-search-results" hidden></ul>
      </div>
      <button type="button" class="btn btn-ghost" id="address-map-locate-btn">Use my location</button>
    </div>
    <div id="address-map" class="address-map-canvas"></div>
    <p class="address-map-status muted" id="address-map-status" hidden></p>
    <label class="field address-map-preview">
      <span>Selected address</span>
      <input class="field-input" id="address-map-address" placeholder="Pin a point on the map" readonly>
    </label>
    <label class="address-map-current-opt" id="address-map-current-wrap">
      <input type="checkbox" id="address-map-make-current">
      <span>Use as current address</span>
    </label>
    <p class="muted address-map-current-hint" id="address-map-current-hint">Leave unchecked to keep your current address and save this as another location.</p>
    <div class="profile-actions address-map-actions">
      <button type="button" class="btn btn-ghost" data-close-address-map>Cancel</button>
      <button type="button" class="btn btn-primary" id="address-map-save-btn">Save address</button>
    </div>
  </div>
</div>

<div class="order-rx-viewer" id="order-rx-viewer" hidden>
  <button type="button" class="order-rx-viewer-backdrop" onclick="closeOrderPrescription()" aria-label="Close prescription viewer"></button>
  <section class="order-rx-viewer-sheet" role="dialog" aria-modal="true" aria-labelledby="order-rx-viewer-title">
    <header class="order-rx-viewer-head">
      <div>
        <span class="order-rx-viewer-kicker">Uploaded prescription</span>
        <h3 id="order-rx-viewer-title">Prescription</h3>
      </div>
      <button type="button" class="order-rx-viewer-close" onclick="closeOrderPrescription()" aria-label="Close">×</button>
    </header>
    <div class="order-rx-viewer-body">
      <img id="order-rx-viewer-image" alt="Uploaded prescription" hidden>
      <iframe id="order-rx-viewer-frame" title="Uploaded prescription" hidden></iframe>
      <p class="order-rx-viewer-missing" id="order-rx-viewer-missing" hidden>No prescription file is available.</p>
    </div>
  </section>
</div>

<div class="residence-cancel-order-modal" id="residence-cancel-order-modal" hidden>
  <button type="button" class="residence-cancel-order-backdrop" onclick="closeResidenceCancelOrderModal()" aria-label="Close cancel order"></button>
  <section class="residence-cancel-order-sheet" role="dialog" aria-modal="true" aria-labelledby="residence-cancel-order-title">
    <div class="residence-cancel-order-head">
      <div>
        <span>Cancel order</span>
        <h3 id="residence-cancel-order-title">Reason for cancellation</h3>
        <p id="residence-cancel-order-sub"></p>
      </div>
      <button type="button" class="residence-cancel-order-close" onclick="closeResidenceCancelOrderModal()" aria-label="Close">&times;</button>
    </div>
    <div class="residence-cancel-order-body">
      <p>Choose a reason, or pick Other and type the specific reason. This cannot be undone.</p>
      <label for="residence-cancel-reason-select">
        <span>Cancellation reason</span>
        <select id="residence-cancel-reason-select">
          <option value="">Select a reason</option>
          <option value="Changed my mind">Changed my mind</option>
          <option value="Ordered by mistake">Ordered by mistake</option>
          <option value="Need to update my order">Need to update my order</option>
          <option value="Found another pharmacy">Found another pharmacy</option>
          <option value="Other">Other</option>
        </select>
      </label>
      <label id="residence-cancel-reason-other-field" hidden for="residence-cancel-reason-other-input">
        <span>Specify reason</span>
        <textarea id="residence-cancel-reason-other-input" rows="4" maxlength="500" placeholder="Type the specific reason for cancelling this order..."></textarea>
      </label>
      <p class="residence-cancel-order-error" id="residence-cancel-order-error" hidden role="alert"></p>
    </div>
    <div class="residence-cancel-order-foot">
      <button type="button" class="btn btn-ghost" onclick="closeResidenceCancelOrderModal()">Keep order</button>
      <button type="button" class="btn" id="residence-cancel-order-submit">Cancel order</button>
    </div>
  </section>
</div>
