<!-- ================= ORDERS VIEW ================= -->
<?php
$allowedStatuses = array_keys(pharmacy_order_status_map());
$activeStatus = trim((string) ($_GET['status'] ?? 'pending'));
if (!in_array($activeStatus, $allowedStatuses, true)) {
    $activeStatus = 'pending';
}

$selectedOrderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$filteredOrders = pharmacy_get_orders(300, $activeStatus);

$orderItemsCache = [];
foreach ($filteredOrders as $order) {
    $orderItemsCache[(int) $order['id']] = pharmacy_get_order_items((int) $order['id']);
}

$panelOrder = null;
if ($selectedOrderId > 0) {
    foreach ($filteredOrders as $order) {
        if ((int) $order['id'] === $selectedOrderId) {
            $panelOrder = $order;
            break;
        }
    }
}
if ($panelOrder === null) {
    $panelOrder = $filteredOrders[0] ?? null;
}

$panelOrderId = (int) ($panelOrder['id'] ?? 0);
$statusCounts = $orderStatusCounts ?? [];

$overviewItems = $panelOrder ? ($orderItemsCache[$panelOrderId] ?? pharmacy_get_order_items($panelOrderId)) : [];
$overviewOrder = $panelOrder ?? [
    'order_number' => '—',
    'status' => $activeStatus,
    'customer_name' => 'No order selected',
    'customer_phone' => '',
    'customer_address' => '',
    'total_amount' => 0,
    'down_payment' => 0,
    'payment_method' => 'GCash',
];

$overviewBadge = pharmacy_order_status_map()[$overviewOrder['status'] ?? 'pending'] ?? ['c' => 'badge-gray', 't' => ucfirst((string) ($overviewOrder['status'] ?? 'pending'))];
$overviewCustomer = trim((string) ($overviewOrder['customer_name'] ?? 'Customer'));
$overviewContact = trim((string) ($overviewOrder['customer_phone'] ?? ''));
$overviewAddress = trim((string) ($overviewOrder['customer_address'] ?? ''));
$overviewCustomerLine = implode(' · ', array_filter([$overviewContact, $overviewAddress]));
$overviewRequiresRx = pharmacy_order_requires_prescription($overviewOrder, $overviewItems);
$overviewPrescriptionUrl = pharmacy_prescription_url($overviewOrder);
$overviewPrescriptionPath = trim((string) ($overviewOrder['prescription_path'] ?? ''));
$overviewPrescriptionName = $overviewPrescriptionPath !== '' ? basename($overviewPrescriptionPath) : 'prescription.jpg';
$overviewPrescriptionExt = strtolower(pathinfo($overviewPrescriptionPath, PATHINFO_EXTENSION));
$overviewPrescriptionKind = $overviewPrescriptionExt === 'pdf' ? 'pdf' : ($overviewPrescriptionUrl ? 'img' : 'empty');
$overviewPrescriptionDate = !empty($overviewOrder['created_at'])
    ? pharmacy_format_date((string) $overviewOrder['created_at'])
    : date('M j, Y');
$overviewOrderNumber = (string) ($overviewOrder['order_number'] ?? 'ORD-0000');
$overviewTotalAmount = max(0, (float) ($overviewOrder['total_amount'] ?? 0));
$overviewDownPayment = pharmacy_order_down_payment($overviewOrder);
$overviewBalanceDue = pharmacy_order_balance_on_pickup($overviewOrder);
$overviewDownPaymentPercent = pharmacy_order_down_payment_percent($overviewOrder);
$overviewPaymentMethod = trim((string) ($overviewOrder['payment_method'] ?? 'GCash'));
$isPendingOverview = ($overviewOrder['status'] ?? '') === 'pending';
$hasPanelOrder = $panelOrder !== null;
$overviewStatus = (string) ($overviewOrder['status'] ?? '');
$nextOrderStatus = pharmacy_next_order_status($overviewStatus);
$canAdvanceStatus = $hasPanelOrder && $nextOrderStatus !== null;
$nextStatusLabel = $nextOrderStatus !== null
    ? (pharmacy_order_status_map()[$nextOrderStatus]['t'] ?? ucfirst($nextOrderStatus))
    : '';
$isReadyOverview = $hasPanelOrder && $overviewStatus === 'ready';
$isCompletedOverview = $hasPanelOrder && $overviewStatus === 'delivered';
$isCancelledOverview = $hasPanelOrder && $overviewStatus === 'cancelled';
$overviewCancellationReason = trim((string) ($overviewOrder['cancellation_reason'] ?? ''));
$overviewPickupProofPath = trim((string) ($overviewOrder['pickup_proof_path'] ?? ''));
$overviewPickupProofUrl = pharmacy_pickup_proof_url($overviewOrder);
$hasPickupProof = $overviewPickupProofUrl !== null;
$overviewPickupProofName = $overviewPickupProofPath !== '' ? basename($overviewPickupProofPath) : 'pickup-proof.jpg';
$overviewPickupProofExt = strtolower(pathinfo($overviewPickupProofPath, PATHINFO_EXTENSION));
$overviewPickupProofKind = $overviewPickupProofExt === 'pdf' ? 'pdf' : ($overviewPickupProofUrl ? 'img' : 'empty');
?>
      <section class="<?= pharmacy_view_class('orders', $activeView) ?>" id="view-orders" data-live-region="pharmacy-orders" data-live-keys="orders">
        <div class="grid-2 orders-layout">
          <div class="orders-main">
            <div class="orders-tabs-row">
              <div class="tabs">
                <?php foreach (pharmacy_order_tabs() as $tab): ?>
                <?php $tabStatus = (string) $tab['status']; ?>
                <a
                  href="<?= htmlspecialchars(pharmacy_orders_url($tabStatus), ENT_QUOTES, 'UTF-8') ?>"
                  class="tab-btn<?= $activeStatus === $tabStatus ? ' active' : '' ?>"
                ><?= htmlspecialchars((string) $tab['label'], ENT_QUOTES, 'UTF-8') ?> <span class="count"><?= number_format((int) ($statusCounts[$tabStatus] ?? 0)) ?></span></a>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="orders-list">
            <?php if ($filteredOrders === []): ?>
            <div class="orders-empty">
              <p class="orders-empty-title">No <?= htmlspecialchars(pharmacy_order_status_map()[$activeStatus]['t'] ?? ucfirst($activeStatus), ENT_QUOTES, 'UTF-8') ?> orders</p>
              <p class="orders-empty-copy">Orders in this status will appear here.</p>
            </div>
            <?php else: ?>
            <?php foreach ($filteredOrders as $order): ?>
            <?php
            $orderId = (int) ($order['id'] ?? 0);
            $orderItems = $orderItemsCache[$orderId] ?? [];
            $orderCustomer = trim((string) ($order['customer_name'] ?? 'Customer'));
            $orderNumber = (string) ($order['order_number'] ?? 'ORD-0000');
            $isActiveCard = $panelOrderId === $orderId;
            $orderCancellationReason = trim((string) ($order['cancellation_reason'] ?? ''));
            ?>
            <a
              href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus, $orderId), ENT_QUOTES, 'UTF-8') ?>"
              class="order-card<?= $isActiveCard ? ' is-active' : '' ?>"
            >
              <div class="list-icon" style="background:var(--teal-soft);color:var(--teal);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2l1.5 3h9L18 2M3 6h18l-1.5 13a2 2 0 0 1-2 1.8H6.5a2 2 0 0 1-2-1.8L3 6z"/></svg></div>
              <div>
                <div class="order-id">#<?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="cust"><?= htmlspecialchars($orderCustomer, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="meds"><?= htmlspecialchars(pharmacy_order_items_summary($orderItems), ENT_QUOTES, 'UTF-8') ?><?= $activeStatus === 'cancelled' && $orderCancellationReason !== '' ? ' · ' . htmlspecialchars($orderCancellationReason, ENT_QUOTES, 'UTF-8') : '' ?></div>
              </div>
              <div class="right-block">
                <div class="amount"><?= pharmacy_format_money((float) ($order['total_amount'] ?? 0)) ?></div>
                <div class="time"><?= htmlspecialchars(pharmacy_time_ago($order['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
            </div>
          </div>

          <div class="panel order-overview">
            <div class="panel-head">
              <h3>Order #<?= htmlspecialchars($overviewOrderNumber, ENT_QUOTES, 'UTF-8') ?></h3>
              <span class="badge <?= htmlspecialchars($overviewBadge['c'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($overviewBadge['t'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php if (!$hasPanelOrder): ?>
            <p class="order-empty-panel">Select an order from the list to view details.</p>
            <?php else: ?>

            <div class="order-section-label">Customer</div>
            <div class="list-row order-list-row">
              <div class="avatar" style="width:36px;height:36px;font-size:12px;"><?= htmlspecialchars(pharmacy_initials($overviewCustomer), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="list-body">
                <div class="t1"><?= htmlspecialchars($overviewCustomer, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="t2"><?= htmlspecialchars($overviewCustomerLine !== '' ? $overviewCustomerLine : 'No contact details', ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>

            <div class="order-section-label">Medicines</div>
            <?php foreach ($overviewItems as $item): ?>
            <?php
            $itemRequiresRx = !empty($item['prescription_required']);
            $itemImage = trim((string) ($item['image_url'] ?? ''));
            $itemPrescriptionUrl = pharmacy_item_prescription_url($item, $overviewOrder);
            ?>
            <div class="list-row order-list-row order-med-row">
              <div class="med-thumb<?= $itemRequiresRx ? ' med-thumb--rx' : '' ?><?= $itemImage !== '' ? ' med-thumb--photo' : '' ?>">
                <?php if ($itemImage !== ''): ?>
                <img src="<?= htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['medicine_name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg>
                <?php endif; ?>
              </div>
              <div class="list-body">
                <div class="t1 order-med-title">
                  <?= htmlspecialchars((string) ($item['medicine_name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="t2 order-med-pharmacy"><?= htmlspecialchars((string) ($overviewOrder['pharmacy_name'] ?? 'Pharmacy'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="t2">Qty <?= (int) ($item['quantity'] ?? 1) ?><?php if ($itemRequiresRx && $itemPrescriptionUrl): ?> · <button type="button" class="order-prescription-inline view-prescription-trigger" data-prescription-url="<?= htmlspecialchars($itemPrescriptionUrl, ENT_QUOTES, 'UTF-8') ?>">Rx required&nbsp; see prescription ›</button><?php elseif ($itemRequiresRx): ?> · Rx required<?php endif; ?></div>
              </div>
              <div class="list-meta mono"><?= pharmacy_format_money((float) ($item['unit_price'] ?? 0) * (int) ($item['quantity'] ?? 1)) ?></div>
            </div>
            <?php endforeach; ?>

            <?php if (false && $overviewRequiresRx): ?>
            <div class="order-rx-docs-block">
              <h3 class="order-rx-docs-title">Uploaded prescription</h3>
              <?php if ($overviewPrescriptionUrl): ?>
              <button type="button" class="order-rx-doc-card view-prescription-trigger" data-prescription-url="<?= htmlspecialchars($overviewPrescriptionUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="View uploaded prescription">
                <span class="order-rx-doc-icon order-rx-doc-icon--<?= htmlspecialchars($overviewPrescriptionKind, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                  <?php if ($overviewPrescriptionKind === 'pdf'): ?>
                  <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
                    <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#E2574C"/>
                    <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".35"/>
                    <text x="26" y="42" text-anchor="middle" fill="#fff" font-size="13" font-weight="800" font-family="Plus Jakarta Sans, sans-serif">PDF</text>
                  </svg>
                  <?php else: ?>
                  <img src="<?= htmlspecialchars($overviewPrescriptionUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                  <?php endif; ?>
                </span>
                <span class="order-rx-doc-meta">
                  <strong>Prescription</strong>
                  <small><?= htmlspecialchars($overviewPrescriptionDate, ENT_QUOTES, 'UTF-8') ?></small>
                </span>
                <span class="order-rx-doc-chevron" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                </span>
              </button>
              <?php else: ?>
              <div class="order-rx-doc-card is-empty">
                <span class="order-rx-doc-icon order-rx-doc-icon--empty" aria-hidden="true">
                  <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
                    <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#d0d5dd"/>
                    <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".45"/>
                  </svg>
                </span>
                <span class="order-rx-doc-meta">
                  <strong>Prescription required</strong>
                  <small>Waiting for customer upload</small>
                </span>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="order-payment-card">
              <h3 class="payment-summary-title"><span class="payment-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 3.5h12v17l-2.2-1.4-2.2 1.4-2.2-1.4-2.2 1.4L6 20.5v-17Z"/><path d="M9 8h6M9 11.5h6M9 15h3"/></svg></span><span>Payment Summary<small>Payment details and transaction breakdown</small></span></h3>
              <div class="payment-summary-method"><b class="payment-gcash-icon">G</b><span class="payment-summary-details"><strong>GCash</strong><b><?= pharmacy_format_money($overviewDownPayment) ?></b></span><small class="payment-summary-meta">Payment confirmed via PayMongo<br><?= htmlspecialchars($overviewPrescriptionDate, ENT_QUOTES, 'UTF-8') ?></small><span class="payment-summary-confirmed">✓ Paid</span></div>
              <div class="order-payment-row">
                <span>Total Amount</span>
                <span class="mono"><?= pharmacy_format_money($overviewTotalAmount) ?></span>
              </div>
              <div class="order-payment-row order-payment-row--paid">
                <span>
                  Paid (<?= $isCompletedOverview ? '100' : $overviewDownPaymentPercent ?>%)
                  <small><?= $overviewDownPaymentPercent ?>% · paid via GCash</small>
                </span>
                <span class="mono"><?= pharmacy_format_money($isCompletedOverview ? $overviewTotalAmount : $overviewDownPayment) ?></span>
              </div>
              <div class="order-payment-row order-payment-row--due">
                <span>Remaining Balance (<?= $isCompletedOverview ? 0 : max(0, 100 - (int) $overviewDownPaymentPercent) ?>%)</span>
                <span class="mono"><?= pharmacy_format_money($isCompletedOverview ? 0 : $overviewBalanceDue) ?></span>
              </div>
              <p class="order-payment-note"><?= $isCompletedOverview ? '✓ All payments have been settled. Your order is fully paid.' : 'Customer pays the remaining balance when collecting this order at the pharmacy.' ?></p>
            </div>

            <?php if ($isCompletedOverview && $hasPickupProof): ?>
            <div class="order-rx-docs-block">
              <h3 class="order-rx-docs-title">Proof of pickup</h3>
              <button type="button" class="order-rx-doc-card view-prescription-trigger" data-prescription-url="<?= htmlspecialchars($overviewPickupProofUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="View proof of pickup">
                <span class="order-rx-doc-icon order-rx-doc-icon--<?= htmlspecialchars($overviewPickupProofKind, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                  <?php if ($overviewPickupProofKind === 'pdf'): ?>
                  <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
                    <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#E2574C"/>
                    <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".35"/>
                    <text x="26" y="42" text-anchor="middle" fill="#fff" font-size="13" font-weight="800" font-family="Plus Jakarta Sans, sans-serif">PDF</text>
                  </svg>
                  <?php else: ?>
                  <img src="<?= htmlspecialchars($overviewPickupProofUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                  <?php endif; ?>
                </span>
                <span class="order-rx-doc-meta">
                  <strong>Pickup completed</strong>
                  <small><?= htmlspecialchars($overviewPickupProofName, ENT_QUOTES, 'UTF-8') ?></small>
                </span>
                <span class="order-rx-doc-chevron" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                </span>
              </button>
            </div>
            <?php endif; ?>

            <?php if ($isCancelledOverview): ?>
            <div class="order-section-label">Cancellation reason</div>
            <div class="order-cancellation-card">
              <p class="order-cancellation-text"><?= htmlspecialchars($overviewCancellationReason !== '' ? $overviewCancellationReason : 'No reason recorded.', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php endif; ?>

            <div class="order-panel-actions">
              <?php if ($isPendingOverview): ?>
              <div class="order-panel-actions-row">
                <button type="button" class="btn btn-danger order-cancel-btn" id="cancel-order-btn" data-order-id="<?= $panelOrderId ?>" data-order-number="<?= htmlspecialchars($overviewOrderNumber, ENT_QUOTES, 'UTF-8') ?>" data-customer="<?= htmlspecialchars($overviewCustomer, ENT_QUOTES, 'UTF-8') ?>"<?= $panelOrderId <= 0 ? ' disabled' : '' ?>>Cancel order</button>
                <button type="button" class="btn btn-accent order-confirm-btn" id="confirm-order-btn" data-order-id="<?= $panelOrderId ?>" data-next-status="confirmed" data-next-label="Confirmed"<?= $panelOrderId <= 0 ? ' disabled' : '' ?>>Confirm order</button>
              </div>
              <?php elseif ($canAdvanceStatus): ?>
              <div class="order-panel-actions-row">
                <button
                  type="button"
                  class="btn btn-accent order-update-status-btn"
                  id="update-order-status-btn"
                  data-order-id="<?= $panelOrderId ?>"
                  data-next-status="<?= htmlspecialchars($nextOrderStatus, ENT_QUOTES, 'UTF-8') ?>"
                  data-next-label="<?= htmlspecialchars($nextStatusLabel, ENT_QUOTES, 'UTF-8') ?>"
                >Update status</button>
              </div>
              <?php elseif ($isReadyOverview): ?>
              <div class="order-panel-actions-row">
                <button
                  type="button"
                  class="btn btn-accent order-complete-btn"
                  id="complete-order-btn"
                  data-order-id="<?= $panelOrderId ?>"
                  data-order-number="<?= htmlspecialchars($overviewOrderNumber, ENT_QUOTES, 'UTF-8') ?>"
                  data-customer="<?= htmlspecialchars($overviewCustomer, ENT_QUOTES, 'UTF-8') ?>"
                  data-has-proof="<?= $hasPickupProof ? '1' : '0' ?>"
                  data-proof-url="<?= htmlspecialchars($overviewPickupProofUrl ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >Mark as complete</button>
              </div>
              <?php endif; ?>
            </div>

            <?php endif; ?>
          </div>
        </div>

        <div class="cancel-order-modal" id="cancel-order-modal" hidden>
          <button type="button" class="cancel-order-modal-backdrop" id="cancel-order-modal-close" aria-label="Close cancel order modal"></button>
          <div class="cancel-order-modal-sheet" role="dialog" aria-modal="true" aria-labelledby="cancel-order-modal-title">
            <div class="cancel-order-modal-head">
              <div class="cancel-order-modal-head-copy">
                <span class="cancel-order-modal-eyebrow">Cancel order</span>
                <h3 id="cancel-order-modal-title">Reason for cancellation</h3>
                <p class="cancel-order-modal-sub" id="cancel-order-modal-sub"></p>
              </div>
              <button type="button" class="cancel-order-modal-close" id="cancel-order-modal-close-btn" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="cancel-order-modal-body">
              <p class="cancel-order-modal-note">Please tell us why this order is being cancelled. This cannot be undone.</p>
              <label class="cancel-reason-field" for="cancel-reason-select">
                <span>Cancellation reason</span>
                <select id="cancel-reason-select">
                  <option value="">Select a reason</option>
                  <option value="Out of stock">Out of stock</option>
                  <option value="Invalid prescription">Invalid prescription</option>
                  <option value="Customer requested cancellation">Customer requested cancellation</option>
                  <option value="Payment issue">Payment issue</option>
                  <option value="other">Others</option>
                </select>
              </label>
              <div class="cancel-reason-other-field" id="cancel-reason-other-field" hidden>
                <label class="cancel-reason-field" for="cancel-reason-other-input">
                  <span>Specify reason</span>
                  <textarea id="cancel-reason-other-input" rows="4" maxlength="500" placeholder="Enter the reason for cancelling this order..."></textarea>
                </label>
              </div>
              <p class="cancel-order-error" id="cancel-order-error" hidden role="alert"></p>
            </div>
            <div class="cancel-order-modal-foot">
              <button type="button" class="btn" id="cancel-order-dismiss-btn">Keep order</button>
              <button type="button" class="btn btn-danger" id="cancel-order-submit-btn">Cancel order</button>
            </div>
          </div>
        </div>

        <div class="pickup-proof-modal" id="pickup-proof-modal" hidden>
          <button type="button" class="pickup-proof-modal-backdrop" id="pickup-proof-modal-close" aria-label="Close proof of pickup modal"></button>
          <div class="pickup-proof-modal-sheet" role="dialog" aria-modal="true" aria-labelledby="pickup-proof-modal-title">
            <div class="pickup-proof-modal-head">
              <div class="pickup-proof-modal-head-copy">
                <span class="pickup-proof-modal-eyebrow">Complete order</span>
                <h3 id="pickup-proof-modal-title">Proof of pickup</h3>
                <p class="pickup-proof-modal-sub" id="pickup-proof-modal-sub"></p>
              </div>
              <button type="button" class="pickup-proof-modal-close" id="pickup-proof-modal-close-btn" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="pickup-proof-modal-body">
              <p class="pickup-proof-modal-note">Upload a photo or PDF showing the customer picked up this order. This is required to mark the order complete.</p>
              <div class="pickup-proof-preview" id="pickup-proof-preview" hidden>
                <img id="pickup-proof-preview-image" src="" alt="Selected proof of pickup preview">
                <p class="pickup-proof-preview-name" id="pickup-proof-preview-name"></p>
              </div>
              <label class="pickup-proof-upload" id="pickup-proof-upload-label">
                <span class="pickup-proof-upload-icon" aria-hidden="true">
                  <svg width="52" height="60" viewBox="0 0 52 60" fill="none">
                    <path d="M8 0h24l16 16v36a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z" fill="#d0d5dd"/>
                    <path d="M32 0v12a4 4 0 0 0 4 4h16L32 0Z" fill="#fff" fill-opacity=".45"/>
                  </svg>
                </span>
                <span class="pickup-proof-upload-copy">
                  <strong>Choose file</strong>
                  <small>JPG, PNG, WEBP, or PDF · max 5 MB</small>
                </span>
                <input type="file" id="pickup-proof-input" accept="image/jpeg,image/png,image/webp,application/pdf" hidden>
              </label>
              <p class="pickup-proof-error" id="pickup-proof-error" hidden role="alert"></p>
            </div>
            <div class="pickup-proof-modal-foot">
              <button type="button" class="btn" id="pickup-proof-cancel-btn">Cancel</button>
              <button type="button" class="btn btn-accent" id="pickup-proof-submit-btn" disabled>Mark as complete</button>
            </div>
          </div>
        </div>

        <div class="rx-viewer" id="prescription-viewer" hidden>
          <button type="button" class="rx-viewer-backdrop" id="prescription-viewer-close" aria-label="Close prescription viewer"></button>
          <div class="rx-viewer-sheet" role="dialog" aria-modal="true" aria-labelledby="prescription-viewer-title">
            <div class="rx-viewer-head">
              <div class="rx-viewer-head-copy">
                <span class="rx-viewer-eyebrow">Prescription review</span>
                <h3 id="prescription-viewer-title"><?= htmlspecialchars($overviewCustomer, ENT_QUOTES, 'UTF-8') ?> · Order #<?= htmlspecialchars($overviewOrderNumber, ENT_QUOTES, 'UTF-8') ?></h3>
              </div>
              <button type="button" class="rx-viewer-close" id="prescription-viewer-close-btn" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </button>
            </div>
            <div class="rx-viewer-body">
              <div class="rx-viewer-canvas">
                <img id="prescription-viewer-image" src="" alt="Uploaded prescription">
              </div>
            </div>
          </div>
        </div>
      </section>
