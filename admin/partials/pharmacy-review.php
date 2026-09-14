<?php

declare(strict_types=1);

/** @var array $viewPharmacy */
/** @var string $pharmacyReviewContext 'panel'|'modal' */
/** @var string $pharmacyReviewListQuery */

$appRoot = dirname(__DIR__, 2);
$pharmacyReviewTitleId = $pharmacyReviewContext === 'modal' ? 'rx-popup-title' : 'rx-panel-title';
$pharmacyReviewSheetClass = 'rx-sheet' . ($pharmacyReviewContext === 'panel' ? ' rx-sheet--panel' : '');
$pharmacyReviewCloseLabel = $pharmacyReviewContext === 'panel' ? 'Close' : 'Back to list';
?>
<section class="<?= htmlspecialchars($pharmacyReviewSheetClass, ENT_QUOTES, 'UTF-8') ?>">
  <div class="rx-hero">
    <a class="rx-view-btn" href="<?= htmlspecialchars($pharmacyReviewListQuery, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pharmacyReviewCloseLabel, ENT_QUOTES, 'UTF-8') ?></a>
    <div class="rx-avatar">
      <?php if ($logoExists): ?>
      <img src="<?= htmlspecialchars($logoUrl !== '' ? $logoUrl : app_url($logoPath), ENT_QUOTES, 'UTF-8') ?>" alt="">
      <?php else: ?>
      <?= htmlspecialchars(admin_initials((string) ($viewPharmacy['pharmacy_name'] ?? 'Rx')), ENT_QUOTES, 'UTF-8') ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="rx-sheet-body">
    <h1 class="rx-name" id="<?= htmlspecialchars($pharmacyReviewTitleId, ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars((string) ($viewPharmacy['pharmacy_name'] ?? 'Pharmacy'), ENT_QUOTES, 'UTF-8') ?>
      <?php if ($pharmacyStatus === 'approved' || $pharmacyStatus === 'active'): ?>
      <span class="rx-verify" aria-label="Approved" title="Approved">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
      </span>
      <?php endif; ?>
    </h1>
    <p class="rx-sub"><?= htmlspecialchars($contact !== '' ? $contact : 'No contact number', ENT_QUOTES, 'UTF-8') ?></p>

    <section class="rx-schedule" aria-label="Operating schedule">
      <h3 class="rx-schedule-heading">Operating schedule</h3>
      <?php /* Daily hours below are the source of truth; no aggregate time cards needed. */ ?>
      <?php /*
        <div class="rx-time-card">
          <span class="rx-time-card-icon rx-time-card-icon--open" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
          </span>
          <div>
            <span class="rx-time-card-label">Earliest opening</span>
            <strong><?= htmlspecialchars($openTime !== '' ? $openTime : '—', ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
        </div>
        <div class="rx-time-card">
          <span class="rx-time-card-icon rx-time-card-icon--close" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          </span>
          <div>
            <span class="rx-time-card-label">Latest closing</span>
            <strong><?= htmlspecialchars($closeTime !== '' ? $closeTime : '—', ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
        </div>
      */ ?>
      <div class="rx-schedule-days">
        <span class="rx-time-card-label">Days of operation</span>
        <?php if ($operationDays === []): ?>
        <p class="rx-schedule-empty">No days selected</p>
        <?php else: ?>
        <div class="rx-day-hours" role="list" aria-label="Hours by day">
          <?php foreach (pharmacy_accounts_operation_day_options() as $dayKey => $dayLabel): ?>
          <?php
            $isOpenDay = in_array($dayKey, $operationDays, true);
            $dayHours = is_array($viewPharmacy['operating_hours'][$dayKey] ?? null) ? $viewPharmacy['operating_hours'][$dayKey] : null;
            $dayOpen = $isOpenDay ? pharmacy_accounts_format_time((string) ($dayHours['open'] ?? $viewPharmacy['open_time'] ?? '')) : '';
            $dayClose = $isOpenDay ? pharmacy_accounts_format_time((string) ($dayHours['close'] ?? $viewPharmacy['close_time'] ?? '')) : '';
          ?>
          <div class="rx-day-hours-row<?= $isOpenDay ? ' is-open' : '' ?>" role="listitem">
            <span class="rx-day-pill<?= $isOpenDay ? ' is-active' : '' ?>"><?= htmlspecialchars(substr($dayLabel, 0, 3), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="rx-day-hours-time"><?= $isOpenDay ? htmlspecialchars($dayOpen . ' – ' . $dayClose, ENT_QUOTES, 'UTF-8') : 'Closed' ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <div class="rx-row">
      <div class="rx-row-copy">
        <h3>Login email</h3>
        <p>Account email used to sign in.</p>
      </div>
      <div class="rx-row-field">
        <div class="rx-input"><?= htmlspecialchars(trim((string) ($viewPharmacy['email'] ?? '')) !== '' ? (string) $viewPharmacy['email'] : 'No login email', ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>

    <div class="rx-row">
      <div class="rx-row-copy">
        <h3>Pharmacy address</h3>
        <p>Location submitted with this registration.</p>
      </div>
      <div class="rx-row-field">
        <div class="rx-input"><?= htmlspecialchars((string) ($viewPharmacy['address'] ?? 'No address provided'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>

    <div class="rx-docs-block">
      <h3>Uploaded documents</h3>
      <p>Business permit, pharmacy license, and BIR certificate.</p>
      <div class="rx-docs">
        <?php foreach ($pharmacyDocs as $doc): ?>
        <?php
          $docPath = (string) ($doc['path'] ?? '');
          $docId = (int) ($doc['doc_id'] ?? 0);
          $docAbs = $docPath !== '' ? $appRoot . '/' . $docPath : '';
          $docExists = $docId > 0 || ($docAbs !== '' && is_file($docAbs));
          $docUrl = $docId > 0
            ? admin_url('document.php?id=' . $docId)
            : ($docExists ? app_url($docPath) : '');
          $docExt = strtolower(pathinfo((string) ($doc['filename'] ?? $docPath), PATHINFO_EXTENSION));
          $docKind = $docExt === 'pdf' ? 'pdf' : ($docExists && $docExt !== '' ? 'img' : 'empty');
          $tag = $docExists
            ? '<a class="rx-doc-card" href="' . htmlspecialchars($docUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
            : '<div class="rx-doc-card is-empty">';
          $tagEnd = $docExists ? '</a>' : '</div>';
        ?>
        <?= $tag ?>
          <span class="rx-doc-icon rx-doc-icon--<?= htmlspecialchars($docKind, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
            <?php if ($docKind === 'pdf'): ?>
            <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
              <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#E2574C"/>
              <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".35"/>
              <text x="26" y="42" text-anchor="middle" fill="#fff" font-size="13" font-weight="800" font-family="Manrope, sans-serif">PDF</text>
            </svg>
            <?php elseif ($docKind === 'img'): ?>
            <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
              <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#0f7a72"/>
              <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".35"/>
              <text x="26" y="42" text-anchor="middle" fill="#fff" font-size="12" font-weight="800" font-family="Manrope, sans-serif"><?= htmlspecialchars(strtoupper($docExt), ENT_QUOTES, 'UTF-8') ?></text>
            </svg>
            <?php else: ?>
            <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
              <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#d0d5dd"/>
              <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".45"/>
            </svg>
            <?php endif; ?>
          </span>
          <span class="rx-doc-meta">
            <strong><?= htmlspecialchars($doc['label'], ENT_QUOTES, 'UTF-8') ?></strong>
          </span>
        <?= $tagEnd ?>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (!$showPharmacyActions): ?>
      <?php if ($existingAdminNote !== ''): ?>
      <div class="rx-row rx-row-actions">
        <div class="rx-admin-note-display">
          <span class="rx-admin-note-label">Admin note</span>
          <p><?= htmlspecialchars($existingAdminNote, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
      <?php endif; ?>
    <?php else: ?>
    <form method="post" action="" class="rx-row rx-row-actions" id="rx-pharmacy-status-form">
      <input type="hidden" name="action" value="update_pharmacy_status">
      <input type="hidden" name="pharmacy_id" value="<?= htmlspecialchars($pharmacyId, ENT_QUOTES, 'UTF-8') ?>">
      <?php if ($viewPharmacyModal): ?>
      <input type="hidden" name="review_modal" value="1">
      <?php endif; ?>
      <?php if ($needsDecisionNote): ?>
      <input type="hidden" name="decision" id="rx-decision-input" value="" disabled>
      <?php elseif ($existingAdminNote !== ''): ?>
      <div class="rx-admin-note-display">
        <span class="rx-admin-note-label">Admin note</span>
        <p><?= htmlspecialchars($existingAdminNote, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <?php endif; ?>
      <div class="rx-actions" id="rx-primary-actions">
        <?php if ($pharmacyStatus === 'approved' || $pharmacyStatus === 'active'): ?>
        <button type="button" class="rx-btn rx-btn-danger" data-rx-decision="blocked">Block</button>
        <?php else: ?>
        <?php if ($pharmacyStatus !== 'rejected'): ?>
        <button type="button" class="rx-btn rx-btn-danger" data-rx-decision="rejected">Reject</button>
        <?php endif; ?>
        <button type="submit" name="decision" value="approved" class="rx-btn rx-btn-primary">Approve</button>
        <?php if ($pharmacyStatus !== 'pending'): ?>
        <button type="submit" name="decision" value="pending" class="rx-btn">Pending</button>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if ($needsDecisionNote): ?>
      <div class="admin-modal report-action-modal" id="rx-decision-modal" hidden role="dialog" aria-modal="true" aria-labelledby="rx-decision-modal-title">
        <button type="button" class="admin-modal-backdrop" id="rx-decision-backdrop" aria-label="Close decision dialog"></button>
        <div class="admin-modal-card report-action-modal-card">
          <h2 id="rx-decision-modal-title">Block account</h2>
          <p class="admin-modal-sub" id="rx-decision-modal-sub">Choose a reason before blocking this pharmacy account.</p>
          <div class="rx-admin-note-field">
            <label for="rx-decision-reason" id="rx-decision-reason-label">Reason for blocking</label>
            <select id="rx-decision-reason" name="decision_reason" disabled>
              <option value="">Select a reason</option>
            </select>
            <div class="rx-admin-note-other" id="rx-admin-note-other" hidden>
              <label for="rx-admin-note-other-input">Specify reason</label>
              <textarea id="rx-admin-note-other-input" name="admin_note_other" rows="2" placeholder="Describe the reason" disabled></textarea>
            </div>
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="rx-btn" id="rx-decision-cancel">Cancel</button>
            <button type="button" class="rx-btn rx-btn-danger" id="rx-decision-confirm">Confirm block</button>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </form>
    <?php if ($needsDecisionNote): ?>
    <script>
    (function () {
      var form = document.getElementById('rx-pharmacy-status-form');
      var decisionModal = document.getElementById('rx-decision-modal');
      var decisionBackdrop = document.getElementById('rx-decision-backdrop');
      var decisionTitle = document.getElementById('rx-decision-modal-title');
      var decisionSub = document.getElementById('rx-decision-modal-sub');
      var reason = document.getElementById('rx-decision-reason');
      var reasonLabel = document.getElementById('rx-decision-reason-label');
      var otherWrap = document.getElementById('rx-admin-note-other');
      var otherInput = document.getElementById('rx-admin-note-other-input');
      var primaryActions = document.getElementById('rx-primary-actions');
      var confirmButton = document.getElementById('rx-decision-confirm');
      var cancelButton = document.getElementById('rx-decision-cancel');
      var decisionInput = document.getElementById('rx-decision-input');
      var rejectReasons = <?= json_encode($rejectReasons, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      var blockReasons = <?= json_encode($blockReasons, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      var pendingDecision = '';

      if (!form || !decisionModal || !reason || !primaryActions || !decisionInput) return;

      function setBodyModalOpen(open) {
        if (open) {
          document.body.classList.add('modal-open');
        } else if (!document.querySelector('.admin-modal:not([hidden])')) {
          document.body.classList.remove('modal-open');
        }
      }

      function setFieldEnabled(enabled) {
        reason.disabled = !enabled;
        if (otherInput) {
          otherInput.disabled = !enabled;
        }
        decisionInput.disabled = !enabled;
      }

      function populateReasonOptions(decision) {
        var reasons = decision === 'blocked' ? blockReasons : rejectReasons;
        reason.innerHTML = '<option value="">Select a reason</option>';

        Object.keys(reasons).forEach(function (key) {
          var option = document.createElement('option');
          option.value = key;
          option.textContent = reasons[key];
          reason.appendChild(option);
        });
      }

      function syncOtherField() {
        if (!otherWrap || !otherInput) return;
        var showOther = reason.value === 'other';
        otherWrap.hidden = !showOther;
        if (!showOther) {
          otherInput.value = '';
          otherInput.setCustomValidity('');
        }
      }

      function closeDecisionModal() {
        pendingDecision = '';
        decisionInput.value = '';
        reason.value = '';
        decisionModal.hidden = true;
        setFieldEnabled(false);
        syncOtherField();
        setBodyModalOpen(false);
      }

      function openDecisionModal(decision) {
        pendingDecision = decision;
        decisionInput.value = decision;
        populateReasonOptions(decision);
        setFieldEnabled(true);
        syncOtherField();

        if (decisionTitle) {
          decisionTitle.textContent = decision === 'blocked' ? 'Block account' : 'Reject registration';
        }
        if (decisionSub) {
          decisionSub.textContent = decision === 'blocked'
            ? 'Choose a reason before blocking this pharmacy account.'
            : 'Choose a reason before rejecting this pharmacy registration.';
        }
        if (reasonLabel) {
          reasonLabel.textContent = decision === 'blocked' ? 'Reason for blocking' : 'Reason for rejection';
        }
        if (confirmButton) {
          confirmButton.textContent = decision === 'blocked' ? 'Confirm block' : 'Confirm reject';
        }

        decisionModal.hidden = false;
        setBodyModalOpen(true);
        reason.focus();
      }

      primaryActions.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-rx-decision]');
        if (!trigger) return;
        event.preventDefault();
        openDecisionModal(trigger.getAttribute('data-rx-decision') || '');
      });

      cancelButton?.addEventListener('click', closeDecisionModal);
      decisionBackdrop?.addEventListener('click', closeDecisionModal);

      reason.addEventListener('change', function () {
        reason.setCustomValidity('');
        syncOtherField();
      });

      otherInput?.addEventListener('input', function () {
        otherInput.setCustomValidity('');
      });

      confirmButton?.addEventListener('click', function (event) {
        event.preventDefault();
        if (!pendingDecision) return;

        if (!reason.value) {
          reason.setCustomValidity('Choose a specific reason before continuing.');
          reason.reportValidity();
          return;
        }

        if (reason.value === 'other' && otherInput && otherInput.value.trim() === '') {
          otherInput.setCustomValidity('Describe the reason for this decision.');
          otherInput.reportValidity();
          return;
        }

        reason.setCustomValidity('');
        if (otherInput) otherInput.setCustomValidity('');

        decisionInput.value = pendingDecision;
        decisionInput.disabled = false;
        form.submit();
      });

      document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || decisionModal.hidden) return;
        event.stopPropagation();
        closeDecisionModal();
      }, true);
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
