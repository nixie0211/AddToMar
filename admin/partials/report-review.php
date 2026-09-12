<?php

declare(strict_types=1);

/** @var array $reviewReport */
/** @var string $reportReviewContext 'panel'|'modal' */
/** @var string $reportReviewListQuery */

$reportReviewTitleId = $reportReviewContext === 'modal' ? 'rx-report-popup-title' : 'rx-report-panel-title';
$reportReviewSheetClass = 'rx-sheet' . ($reportReviewContext === 'panel' ? ' rx-sheet--panel' : '');
$reportReviewCloseLabel = $reportReviewContext === 'panel' ? 'Close' : 'Back to list';
$blockReasons = admin_pharmacy_block_reasons();
?>
<section class="<?= htmlspecialchars($reportReviewSheetClass, ENT_QUOTES, 'UTF-8') ?>">
  <div class="rx-hero">
    <a class="rx-view-btn" href="<?= htmlspecialchars($reportReviewListQuery, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($reportReviewCloseLabel, ENT_QUOTES, 'UTF-8') ?></a>
    <div class="rx-avatar">
      <?= htmlspecialchars(admin_initials((string) ($reviewReport['pharmacy_name'] ?? 'Rx')), ENT_QUOTES, 'UTF-8') ?>
    </div>
  </div>
  <div class="rx-sheet-body report-review-body">
    <h1 class="rx-name" id="<?= htmlspecialchars($reportReviewTitleId, ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars((string) ($reviewReport['pharmacy_name'] ?? 'Pharmacy'), ENT_QUOTES, 'UTF-8') ?>
      <span class="status-badge <?= htmlspecialchars($reviewBadge['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($reviewBadge['label'], ENT_QUOTES, 'UTF-8') ?></span>
    </h1>
    <p class="rx-sub"><?= htmlspecialchars((string) ($reviewReport['pharmacy_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="admin-modal-sub">Check the problem and proof before you resolve or block the pharmacy.</p>

    <div class="report-review-meta">
      <div>
        <span class="report-label">Reporter</span>
        <strong><?= htmlspecialchars((string) ($reviewReport['reporter_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
        <small><?= htmlspecialchars((string) ($reviewReport['reporter_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
      </div>
    </div>

    <div class="settings-field">
      <span class="report-label">Problem</span>
      <strong class="report-reason"><?= htmlspecialchars((string) ($reviewReport['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
      <p class="report-details"><?= htmlspecialchars((string) ($reviewReport['details'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="settings-field">
      <span class="report-label">Proof</span>
      <div class="report-proof-box">
        <?php if ($proofExists && $proofIsImage): ?>
        <a href="<?= htmlspecialchars($proofUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
          <img src="<?= htmlspecialchars($proofUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Report proof">
        </a>
        <?php elseif ($proofExists): ?>
        <a class="btn-pill btn-secondary" href="<?= htmlspecialchars($proofUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open proof file</a>
        <?php else: ?>
        <p class="report-details">No proof file was attached to this report.</p>
        <?php endif; ?>
      </div>
    </div>

    <form method="post" action="" class="settings-form" id="report-review-form">
      <input type="hidden" name="action" value="review_pharmacy_report">
      <input type="hidden" name="report_id" value="<?= htmlspecialchars((string) ($reviewReport['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <?php if ($reviewReportModal): ?>
      <input type="hidden" name="review_modal" value="1">
      <?php endif; ?>
      <?php if ($canReview): ?>
      <input type="hidden" name="decision" id="report-decision-input" value="" disabled>
      <div class="admin-modal-actions report-actions" id="report-primary-actions">
        <button type="button" class="btn-pill btn-primary" id="report-reply-trigger">Reply</button>
        <button type="button" class="btn-pill btn-danger" id="report-block-trigger">Block account</button>
      </div>

      <div class="admin-modal report-action-modal" id="report-reply-modal" hidden role="dialog" aria-modal="true" aria-labelledby="report-reply-modal-title">
        <button type="button" class="admin-modal-backdrop" id="report-reply-backdrop" aria-label="Close reply dialog"></button>
        <div class="admin-modal-card report-action-modal-card">
          <h2 id="report-reply-modal-title">Reply</h2>
          <p class="admin-modal-sub">Write your admin note before sending this reply.</p>
          <div class="settings-field">
            <label for="admin_note">Admin note</label>
            <textarea id="admin_note" name="admin_note" rows="4" placeholder="Write your reply to the reporter" required disabled></textarea>
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="btn-pill btn-secondary" id="report-reply-cancel">Cancel</button>
            <button type="button" class="btn-pill btn-primary" id="report-reply-confirm">Send reply</button>
          </div>
        </div>
      </div>

      <div class="admin-modal report-action-modal" id="report-block-modal" hidden role="dialog" aria-modal="true" aria-labelledby="report-block-modal-title">
        <button type="button" class="admin-modal-backdrop" id="report-block-backdrop" aria-label="Close block dialog"></button>
        <div class="admin-modal-card report-action-modal-card">
          <h2 id="report-block-modal-title">Block account</h2>
          <p class="admin-modal-sub">Choose a reason before blocking this pharmacy account.</p>
          <div class="rx-admin-note-field">
            <label for="report-block-reason">Reason for blocking</label>
            <select id="report-block-reason" name="decision_reason" disabled>
              <option value="">Select a reason</option>
              <?php foreach ($blockReasons as $reasonKey => $reasonLabel): ?>
              <option value="<?= htmlspecialchars($reasonKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($reasonLabel, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <div class="rx-admin-note-other" id="report-block-reason-other" hidden>
              <label for="report-block-reason-other-input">Specify reason</label>
              <textarea id="report-block-reason-other-input" name="admin_note_other" rows="2" placeholder="Describe the reason" disabled></textarea>
            </div>
          </div>
          <div class="admin-modal-actions">
            <button type="button" class="btn-pill btn-secondary" id="report-block-cancel">Cancel</button>
            <button type="button" class="btn-pill btn-danger" id="report-block-confirm">Confirm block</button>
          </div>
        </div>
      </div>

      <script>
      (function () {
        var form = document.getElementById('report-review-form');
        var replyModal = document.getElementById('report-reply-modal');
        var blockModal = document.getElementById('report-block-modal');
        var adminNote = document.getElementById('admin_note');
        var reason = document.getElementById('report-block-reason');
        var otherWrap = document.getElementById('report-block-reason-other');
        var otherInput = document.getElementById('report-block-reason-other-input');
        var replyTrigger = document.getElementById('report-reply-trigger');
        var blockTrigger = document.getElementById('report-block-trigger');
        var replyCancel = document.getElementById('report-reply-cancel');
        var replyConfirm = document.getElementById('report-reply-confirm');
        var replyBackdrop = document.getElementById('report-reply-backdrop');
        var blockCancel = document.getElementById('report-block-cancel');
        var blockConfirm = document.getElementById('report-block-confirm');
        var blockBackdrop = document.getElementById('report-block-backdrop');
        var decisionInput = document.getElementById('report-decision-input');

        if (!form || !replyModal || !blockModal || !decisionInput || !replyTrigger || !blockTrigger) return;

        function shouldKeepModalOpen() {
          return !!document.querySelector('.rx-popup');
        }

        function setBodyModalOpen(open) {
          if (open || shouldKeepModalOpen()) {
            document.body.classList.add('modal-open');
          } else {
            document.body.classList.remove('modal-open');
          }
        }

        function closeReplyModal() {
          decisionInput.value = '';
          decisionInput.disabled = true;
          if (adminNote) {
            adminNote.disabled = true;
          }
          replyModal.hidden = true;
          if (blockModal.hidden) {
            setBodyModalOpen(false);
          }
        }

        function openReplyModal() {
          if (!blockModal.hidden) {
            closeBlockModal();
          }
          decisionInput.value = 'resolve';
          decisionInput.disabled = false;
          if (adminNote) {
            adminNote.disabled = false;
          }
          replyModal.hidden = false;
          setBodyModalOpen(true);
          adminNote?.focus();
        }

        function setBlockFieldsEnabled(enabled) {
          reason.disabled = !enabled;
          if (otherInput) {
            otherInput.disabled = !enabled;
          }
          if (enabled) {
            decisionInput.disabled = false;
          } else if (replyModal.hidden) {
            decisionInput.disabled = true;
          }
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

        function closeBlockModal() {
          decisionInput.value = '';
          reason.value = '';
          blockModal.hidden = true;
          setBlockFieldsEnabled(false);
          syncOtherField();
          if (replyModal.hidden) {
            setBodyModalOpen(false);
          }
        }

        function openBlockModal() {
          if (!replyModal.hidden) {
            closeReplyModal();
          }
          decisionInput.value = 'block';
          blockModal.hidden = false;
          setBlockFieldsEnabled(true);
          syncOtherField();
          setBodyModalOpen(true);
          reason.focus();
        }

        replyTrigger.addEventListener('click', function (event) {
          event.preventDefault();
          openReplyModal();
        });

        blockTrigger.addEventListener('click', function (event) {
          event.preventDefault();
          openBlockModal();
        });

        replyCancel?.addEventListener('click', closeReplyModal);
        replyBackdrop?.addEventListener('click', closeReplyModal);
        blockCancel?.addEventListener('click', closeBlockModal);
        blockBackdrop?.addEventListener('click', closeBlockModal);

        reason.addEventListener('change', function () {
          reason.setCustomValidity('');
          syncOtherField();
        });

        otherInput?.addEventListener('input', function () {
          otherInput.setCustomValidity('');
        });

        adminNote?.addEventListener('input', function () {
          adminNote.setCustomValidity('');
        });

        replyConfirm?.addEventListener('click', function (event) {
          event.preventDefault();
          decisionInput.value = 'resolve';
          decisionInput.disabled = false;
          if (adminNote) {
            adminNote.disabled = false;
            if (adminNote.value.trim() === '') {
              adminNote.setCustomValidity('Admin note is required before sending a reply.');
              adminNote.reportValidity();
              return;
            }
            adminNote.setCustomValidity('');
          }
          form.submit();
        });

        blockConfirm?.addEventListener('click', function (event) {
          event.preventDefault();

          if (!reason.value) {
            reason.setCustomValidity('Choose a specific reason before continuing.');
            reason.reportValidity();
            return;
          }

          if (reason.value === 'other' && otherInput && otherInput.value.trim() === '') {
            otherInput.setCustomValidity('Describe the reason for blocking this account.');
            otherInput.reportValidity();
            return;
          }

          reason.setCustomValidity('');
          if (otherInput) otherInput.setCustomValidity('');

          if (!window.confirm('Block this pharmacy account? They will not be able to sign in.')) {
            return;
          }

          decisionInput.value = 'block';
          decisionInput.disabled = false;
          form.submit();
        });

        document.addEventListener('keydown', function (event) {
          if (event.key !== 'Escape') return;
          if (!replyModal.hidden) {
            event.stopPropagation();
            closeReplyModal();
            return;
          }
          if (!blockModal.hidden) {
            event.stopPropagation();
            closeBlockModal();
          }
        }, true);
      })();
      </script>
      <?php else: ?>
      <?php if (trim((string) ($reviewReport['admin_note'] ?? '')) !== ''): ?>
      <div class="rx-admin-note-display">
        <span class="rx-admin-note-label">Admin note</span>
        <p><?= htmlspecialchars((string) ($reviewReport['admin_note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <?php endif; ?>
      <p class="report-details">This report has already been reviewed<?= !empty($reviewReport['reviewed_at']) ? ' on ' . htmlspecialchars(admin_format_datetime((string) $reviewReport['reviewed_at']), ENT_QUOTES, 'UTF-8') : '' ?>.</p>
      <?php endif; ?>
    </form>
  </div>
</section>
