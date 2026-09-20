<!-- ================= ORDERS VIEW ================= -->
<?php
$allowedStatuses = array_merge(['all'], array_keys(pharmacy_order_status_map()));
$activeStatus = trim((string) ($_GET['status'] ?? 'pending'));
if (!in_array($activeStatus, $allowedStatuses, true)) {
    $activeStatus = 'pending';
}

$selectedOrderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$pharmacyOrdersDrawerOnly = !empty($pharmacyOrdersDrawerOnly);
$pharmacyOrdersListOnly = !empty($pharmacyOrdersListOnly);
$orderStatusCounts = $orderStatusCounts ?? [];

$filteredOrders = [];
$orderItemsCache = [];
$panelOrder = null;

if ($pharmacyOrdersListOnly) {
    $selectedOrderId = 0;
}

if ($pharmacyOrdersDrawerOnly) {
    if ($selectedOrderId > 0) {
        $fetched = pharmacy_get_order_by_id($selectedOrderId);
        if (is_array($fetched)) {
            $panelOrder = $fetched;
            $orderItemsCache[$selectedOrderId] = pharmacy_get_order_items($selectedOrderId);
        }
    }
} else {
    $filteredOrders = $activeStatus === 'all'
        ? pharmacy_get_orders(300)
        : pharmacy_get_orders(300, $activeStatus);
    $orderIds = array_map(static fn(array $order): int => (int) ($order['id'] ?? 0), $filteredOrders);
    $orderItemsCache = pharmacy_get_order_items_map($orderIds);

    if (!$pharmacyOrdersListOnly && $selectedOrderId > 0) {
        foreach ($filteredOrders as $order) {
            if ((int) $order['id'] === $selectedOrderId) {
                $panelOrder = $order;
                break;
            }
        }
        if ($panelOrder === null) {
            $fetched = pharmacy_get_order_by_id($selectedOrderId);
            if (is_array($fetched)) {
                $panelOrder = $fetched;
            }
        }
        if ($panelOrder !== null) {
            $orderItemsCache[$selectedOrderId] = pharmacy_get_order_items($selectedOrderId);
        }
    }
}

$panelOrderId = (int) ($panelOrder['id'] ?? 0);
$statusCounts = $orderStatusCounts ?? [];
$totalOrdersCount = (int) array_sum($statusCounts);
$completedOrdersCount = (int) ($statusCounts['delivered'] ?? 0);
$pendingOrdersCount = (int) ($statusCounts['pending'] ?? 0);
$cancelledOrdersCount = (int) ($statusCounts['cancelled'] ?? 0);

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
$overviewItemCount = count($overviewItems);
$overviewOrderNumber = (string) ($overviewOrder['order_number'] ?? 'ORD-0000');
$overviewTotalAmount = max(0, (float) ($overviewOrder['total_amount'] ?? 0));
$overviewDownPayment = pharmacy_order_down_payment($overviewOrder);
$overviewBalanceDue = pharmacy_order_balance_on_pickup($overviewOrder);
$overviewDownPaymentPercent = pharmacy_order_down_payment_percent($overviewOrder);
$overviewPrices = pharmacy_order_vat_breakdown($overviewOrder, $overviewItems);
$overviewPaymentMethod = 'GCash';
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
$overviewCreatedTs = strtotime((string) ($overviewOrder['created_at'] ?? '')) ?: time();
$overviewDateLabel = date('M j, Y', $overviewCreatedTs);
$overviewTimeLabel = date('g:i A', $overviewCreatedTs);
$overviewConfirmed = $overviewDownPayment > 0;
$overviewReceiptTitle = ($overviewConfirmed || $isCompletedOverview) ? 'Payment Successful' : 'Payment Pending';
$overviewReceiptStatus = ($overviewConfirmed || $isCompletedOverview) ? 'Paid' : 'Pending';
$overviewReceiptAmount = $isCompletedOverview ? $overviewTotalAmount : $overviewDownPayment;
$overviewReceiptNote = $isCompletedOverview
    ? 'All payments have been settled. Your order is fully paid.'
    : 'Customer pays the remaining balance when collecting this order at the pharmacy.';
?>
<?php if (!$pharmacyOrdersDrawerOnly): ?>
      <section class="<?= pharmacy_view_class('orders', $activeView) ?>" id="view-orders" data-live-region="pharmacy-orders" data-live-keys="orders" data-live-mode="js">
        <div class="orders-layout">
          <div class="orders-main">
            <div class="orders-stats">
              <div class="orders-stat">
                <strong><?= number_format($totalOrdersCount) ?></strong>
                <span>Total Orders</span>
              </div>
              <div class="orders-stat is-delivered">
                <strong><?= number_format($completedOrdersCount) ?></strong>
                <span>Total Completed</span>
              </div>
              <div class="orders-stat is-pending">
                <strong><?= number_format($pendingOrdersCount) ?></strong>
                <span>Pending Orders</span>
              </div>
              <div class="orders-stat is-cancelled">
                <strong><?= number_format($cancelledOrdersCount) ?></strong>
                <span>Cancelled</span>
              </div>
            </div>
            <div class="orders-tabs-row">
              <div class="tabs">
                <?php foreach (pharmacy_order_tabs() as $tab): ?>
                <?php $tabStatus = (string) $tab['status']; ?>
                <a
                  href="<?= htmlspecialchars(pharmacy_orders_url($tabStatus), ENT_QUOTES, 'UTF-8') ?>"
                  class="tab-btn<?= $activeStatus === $tabStatus ? ' active' : '' ?>"
                ><?= htmlspecialchars((string) $tab['label'], ENT_QUOTES, 'UTF-8') ?> <span class="count"><?= number_format($tabStatus === 'all' ? $totalOrdersCount : (int) ($statusCounts[$tabStatus] ?? 0)) ?></span></a>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="orders-table-card">
            <?php if ($filteredOrders === []): ?>
            <div class="orders-empty">
              <p class="orders-empty-title"><?= $activeStatus === 'all' ? 'No orders yet' : 'No ' . htmlspecialchars(pharmacy_order_status_map()[$activeStatus]['t'] ?? ucfirst($activeStatus), ENT_QUOTES, 'UTF-8') . ' orders' ?></p>
              <p class="orders-empty-copy">Orders in this status will appear here.</p>
            </div>
            <?php else: ?>
            <div class="orders-table-wrap">
              <table class="orders-table">
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Order</th>
                    <th>Medicines</th>
                    <th>Order Date</th>
                    <th>Order Time</th>
                    <th>Amount</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
            <?php foreach ($filteredOrders as $orderIndex => $order): ?>
            <?php
            $orderId = (int) ($order['id'] ?? 0);
            $orderItems = $orderItemsCache[$orderId] ?? [];
            $orderCustomer = trim((string) ($order['customer_name'] ?? 'Customer'));
            $orderNumber = (string) ($order['order_number'] ?? 'ORD-0000');
            $isActiveCard = $panelOrderId === $orderId;
            $orderCancellationReason = trim((string) ($order['cancellation_reason'] ?? ''));
            $orderStatus = (string) ($order['status'] ?? $activeStatus);
            $orderStatusMeta = pharmacy_order_status_map()[$orderStatus] ?? ['c' => 'badge-gray', 't' => ucfirst($orderStatus)];
            $createdAt = strtotime((string) ($order['created_at'] ?? '')) ?: time();
            $avatarTone = pharmacy_order_avatar_tone($orderIndex);
            $medicinesSummary = pharmacy_order_items_summary($orderItems);
            if ($orderStatus === 'cancelled' && $orderCancellationReason !== '') {
                $medicinesSummary .= ' · ' . $orderCancellationReason;
            }
            ?>
                  <tr class="orders-table-row<?= $isActiveCard ? ' is-active' : '' ?>" data-order-id="<?= $orderId ?>">
                    <td>
                      <a class="orders-table-link" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus, $orderId), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="ot-customer">
                          <span class="ot-avatar" style="background:<?= htmlspecialchars($avatarTone['bg'], ENT_QUOTES, 'UTF-8') ?>;color:<?= htmlspecialchars($avatarTone['fg'], ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars(pharmacy_initials($orderCustomer), ENT_QUOTES, 'UTF-8') ?></span>
                          <span class="ot-customer-name"><?= htmlspecialchars($orderCustomer, ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                      </a>
                    </td>
                    <td><a class="orders-table-link" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus, $orderId), ENT_QUOTES, 'UTF-8') ?>"><span class="ot-order">#<?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></span></a></td>
                    <td><a class="orders-table-link" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus, $orderId), ENT_QUOTES, 'UTF-8') ?>"><span class="ot-meds"><?= htmlspecialchars($medicinesSummary, ENT_QUOTES, 'UTF-8') ?></span></a></td>
                    <td><a class="orders-table-link" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus, $orderId), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(date('d/m/Y', $createdAt), ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td><a class="orders-table-link" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus, $orderId), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(date('g:i a', $createdAt), ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td><a class="orders-table-link" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus, $orderId), ENT_QUOTES, 'UTF-8') ?>"><span class="ot-amount"><?= pharmacy_format_money((float) ($order['total_amount'] ?? 0)) ?></span></a></td>
                    <td>
                      <a class="orders-table-link" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus, $orderId), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="ot-pill ot-pill--<?= htmlspecialchars($orderStatus, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orderStatusMeta['t'], ENT_QUOTES, 'UTF-8') ?></span>
                      </a>
                    </td>
                  </tr>
            <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
            </div>
          </div>
<?php endif; ?>

<?php if ($pharmacyOrdersDrawerOnly || empty($pharmacyOrdersListOnly)): ?>
          <div class="order-drawer" id="order-drawer" data-order-id="<?= $hasPanelOrder ? $panelOrderId : 0 ?>"<?= $hasPanelOrder ? '' : ' hidden' ?>>
            <button type="button" class="order-drawer-backdrop" id="order-drawer-close" data-orders-close aria-label="Close order details"></button>
            <aside class="panel order-overview" role="dialog" aria-modal="true" aria-labelledby="order-drawer-title">
            <div class="panel-head">
              <a class="order-drawer-close-btn" id="order-drawer-close-btn" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus), ENT_QUOTES, 'UTF-8') ?>" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
              </a>
            </div>

            <?php if (!$hasPanelOrder): ?>
            <p class="order-empty-panel">Select an order from the list to view details.</p>
            <?php else: ?>
            <div class="order-overview-body">
            <div class="ph-order-head">
              <div>
                <h2 id="order-drawer-title">Order #<?= htmlspecialchars($overviewOrderNumber, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= (int) $overviewItemCount ?> item<?= $overviewItemCount === 1 ? '' : 's' ?> • <?= htmlspecialchars($overviewPrices['total_label'], ENT_QUOTES, 'UTF-8') ?></p>
                <small>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
                  <?= htmlspecialchars($overviewDateLabel, ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars($overviewTimeLabel, ENT_QUOTES, 'UTF-8') ?>
                </small>
              </div>
            </div>
            <?php if ($isCancelledOverview): ?>
            <p class="mo-cancel-reason"><strong>Cancellation reason</strong><span><?= htmlspecialchars($overviewCancellationReason !== '' ? $overviewCancellationReason : 'No reason recorded.', ENT_QUOTES, 'UTF-8') ?></span></p>
            <?php endif; ?>
            <div class="ph-order-layout">
            <main class="order-overview-main">
            <?= pharmacy_order_progress_html($overviewStatus, pharmacy_order_progress_stamp((string) ($overviewOrder['created_at'] ?? ''))) ?>
            <article class="ph-customer-card">
              <header class="ph-customer-head">
                <div class="ph-customer-logo"><?= htmlspecialchars(pharmacy_initials($overviewCustomer), ENT_QUOTES, 'UTF-8') ?></div>
                <div>
                  <strong><?= htmlspecialchars($overviewCustomer, ENT_QUOTES, 'UTF-8') ?></strong>
                  <small><?= htmlspecialchars($overviewCustomerLine !== '' ? $overviewCustomerLine : 'No contact details', ENT_QUOTES, 'UTF-8') ?></small>
                </div>
              </header>
              <div class="ph-order-items">
            <?php foreach ($overviewItems as $item): ?>
            <?php
            $itemRequiresRx = !empty($item['prescription_required']);
            $itemImage = trim((string) ($item['image_url'] ?? ''));
            $itemPrescriptionUrl = pharmacy_item_prescription_url($item, $overviewOrder) ?: $overviewPrescriptionUrl;
            $itemNote = trim((string) ($item['item_note'] ?? ''));
            $itemQty = (int) ($item['quantity'] ?? 1);
            ?>
                <div class="ph-order-item">
                  <?php if ($itemImage !== ''): ?>
                  <img src="<?= htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8') ?>" alt="">
                  <?php else: ?>
                  <span class="ph-order-item-thumb" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7L12 3 4 7v10l8 4 8-4V7z"/></svg></span>
                  <?php endif; ?>
                  <div>
                    <b><?= htmlspecialchars((string) ($item['medicine_name'] ?? 'Medicine'), ENT_QUOTES, 'UTF-8') ?></b>
                    <small>Qty: <?= $itemQty ?></small>
                    <?php if ($itemRequiresRx && $itemPrescriptionUrl): ?>
                    <small><button type="button" class="order-prescription-inline view-prescription-trigger" data-prescription-url="<?= htmlspecialchars($itemPrescriptionUrl, ENT_QUOTES, 'UTF-8') ?>">Rx required · see prescription ›</button></small>
                    <?php elseif ($itemRequiresRx): ?>
                    <small>Rx required</small>
                    <?php endif; ?>
                    <?php if ($itemNote !== ''): ?>
                    <small class="mo-item-note">Note: <?= htmlspecialchars($itemNote, ENT_QUOTES, 'UTF-8') ?></small>
                    <?php endif; ?>
                  </div>
                  <strong><?= pharmacy_format_money((float) ($item['unit_price'] ?? 0) * $itemQty) ?></strong>
                </div>
            <?php endforeach; ?>
              </div>
              <div class="ph-order-foot">
                <div class="ph-order-foot-row"><span>VAT (<?= (int) $overviewPrices['vat_percent'] ?>%)</span><b><?= htmlspecialchars($overviewPrices['vat_label'], ENT_QUOTES, 'UTF-8') ?></b></div>
                <div class="ph-order-foot-row"><span>Store Subtotal</span><b><?= htmlspecialchars($overviewPrices['total_label'], ENT_QUOTES, 'UTF-8') ?></b></div>
              </div>
            </article>
            <?php if ($isCompletedOverview && $hasPickupProof): ?>
              <button type="button" class="ph-pickup-proof view-prescription-trigger" data-prescription-url="<?= htmlspecialchars($overviewPickupProofUrl, ENT_QUOTES, 'UTF-8') ?>" data-preview-title="Proof of pickup">
                <span class="ph-pickup-proof-thumb<?= $overviewPickupProofKind === 'pdf' ? ' is-pdf' : '' ?>">
                  <?php if ($overviewPickupProofKind === 'pdf'): ?>PDF<?php else: ?><img src="<?= htmlspecialchars($overviewPickupProofUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Pickup proof"><?php endif; ?>
                </span>
                <span><strong>Pickup completed</strong><small>Photo of completed pickup</small></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
              </button>
            <?php endif; ?>
            </main>
            <aside class="order-overview-side">
            <div class="order-receipt">
              <header class="order-receipt-head">
                <span class="order-receipt-mark" aria-hidden="true">
                  <svg viewBox="0 0 72 56" fill="none">
                    <path d="M28 8h26v36l-3.2-2.2-3.2 2.2-3.2-2.2-3.2 2.2-3.2-2.2-3.2 2.2-3.4-2.2-3.4 2.2V8Z" fill="#fff" stroke="#2563eb" stroke-width="2.2"/>
                    <path d="M18 14h26v36l-3.2-2.2-3.2 2.2-3.2-2.2-3.2 2.2-3.2-2.2-3.2 2.2-3.4-2.2-3.4 2.2V14Z" fill="#fff" stroke="#2563eb" stroke-width="2.2"/>
                    <circle cx="31" cy="32" r="9" fill="#22c55e"/>
                    <path d="m27.2 32.2 2.4 2.4 5.2-5.4" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <h3><?= htmlspecialchars($overviewReceiptTitle, ENT_QUOTES, 'UTF-8') ?></h3>
              </header>
              <div class="order-receipt-perforation" aria-hidden="true"></div>
              <div class="order-receipt-body">
                <h4>Payment Details</h4>
                <div class="order-receipt-row"><span>Order Number</span><i>:</i><b><?= htmlspecialchars($overviewOrderNumber, ENT_QUOTES, 'UTF-8') ?></b></div>
                <div class="order-receipt-row"><span>Order Time</span><i>:</i><b><?= htmlspecialchars($overviewTimeLabel . ', ' . $overviewDateLabel, ENT_QUOTES, 'UTF-8') ?></b></div>
                <div class="order-receipt-row"><span>Payment Method</span><i>:</i><b><?= htmlspecialchars($overviewPaymentMethod !== '' ? $overviewPaymentMethod : 'GCash', ENT_QUOTES, 'UTF-8') ?><small><?= $overviewConfirmed ? 'Payment confirmed via PayMongo' : 'PayMongo GCash checkout' ?></small></b></div>
                <div class="order-receipt-row"><span>Payment Status</span><i>:</i><b><span class="order-receipt-pill<?= ($overviewConfirmed || $isCompletedOverview) ? '' : ' is-pending' ?>"><?= htmlspecialchars($overviewReceiptStatus, ENT_QUOTES, 'UTF-8') ?></span></b></div>
                <div class="order-receipt-row"><span>Amount</span><i>:</i><b><?= pharmacy_format_money($overviewReceiptAmount) ?></b></div>
                <div class="order-receipt-row"><span>VAT (<?= (int) $overviewPrices['vat_percent'] ?>%)</span><i>:</i><b><?= htmlspecialchars($overviewPrices['vat_label'], ENT_QUOTES, 'UTF-8') ?></b></div>
                <div class="order-receipt-row order-receipt-total"><span>Total Amount</span><i>:</i><b><?= htmlspecialchars($overviewPrices['total_label'], ENT_QUOTES, 'UTF-8') ?></b></div>
                <div class="order-receipt-row"><span><?= $isCompletedOverview ? 'Paid (100%)' : 'Paid (' . (int) $overviewDownPaymentPercent . '%)' ?><small><?= (int) $overviewDownPaymentPercent ?>% · paid via GCash</small></span><i>:</i><b class="is-paid"><?= pharmacy_format_money($isCompletedOverview ? $overviewTotalAmount : $overviewDownPayment) ?></b></div>
                <div class="order-receipt-row"><span>Remaining Balance (<?= $isCompletedOverview ? '0' : (string) max(0, 100 - (int) $overviewDownPaymentPercent) ?>%)<small><?= $isCompletedOverview ? 'Paid in full' : 'Pay at pickup' ?></small></span><i>:</i><b><?= pharmacy_format_money($isCompletedOverview ? 0 : $overviewBalanceDue) ?></b></div>
                <p class="order-receipt-note"><?= htmlspecialchars($overviewReceiptNote, ENT_QUOTES, 'UTF-8') ?></p>
              </div>
            </div>
            </aside>
            </div>
            </div>

            <?php if ($isPendingOverview || $canAdvanceStatus || $isReadyOverview): ?>
            <div class="order-panel-actions">
              <?php if ($isPendingOverview): ?>
              <div class="order-panel-actions-row">
                <button type="button" class="btn btn-danger order-cancel-btn" id="cancel-order-btn" data-order-id="<?= $panelOrderId ?>" data-order-number="<?= htmlspecialchars($overviewOrderNumber, ENT_QUOTES, 'UTF-8') ?>" data-customer="<?= htmlspecialchars($overviewCustomer, ENT_QUOTES, 'UTF-8') ?>"<?= $panelOrderId <= 0 ? ' disabled' : '' ?>>Cancel order</button>
                <button type="button" class="btn btn-accent order-confirm-btn" id="confirm-order-btn" data-order-id="<?= $panelOrderId ?>" data-next-status="confirmed" data-next-label="Confirmed"<?= $panelOrderId <= 0 ? ' disabled' : '' ?>>Confirm order</button>
              </div>
              <?php elseif ($canAdvanceStatus): ?>
              <div class="order-panel-actions-row">
                <a class="btn btn-danger" id="order-update-cancel-btn" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
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
                <a class="btn btn-danger" id="order-update-cancel-btn" href="<?= htmlspecialchars(pharmacy_orders_url($activeStatus), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
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

            <?php endif; ?>
            </aside>
          </div>
<?php endif; ?>
<?php if (!$pharmacyOrdersDrawerOnly): ?>
        </div>
<?php if (empty($pharmacyOrdersListOnly)): ?>

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

        <div class="rx-viewer rx-crop-modal" id="prescription-viewer" hidden>
          <button type="button" class="rx-crop-backdrop" id="prescription-viewer-close" aria-label="Close prescription preview"></button>
          <div class="rx-crop-sheet" role="dialog" aria-modal="true" aria-labelledby="prescription-viewer-title">
            <header class="rx-crop-head">
              <h3 id="prescription-viewer-title">Prescription preview</h3>
            </header>
            <div class="rx-crop-stage" id="prescription-viewer-stage">
              <div class="rx-crop-media" id="prescription-viewer-media">
                <img id="prescription-viewer-image" src="" alt="Uploaded prescription">
                <iframe id="prescription-viewer-frame" title="Uploaded prescription" hidden></iframe>
              </div>
              <p class="rx-crop-missing" id="prescription-viewer-missing" hidden>The uploaded prescription could not be loaded.</p>
            </div>
            <div class="rx-crop-zoombar">
              <input type="range" class="rx-crop-zoom" id="prescription-viewer-zoom" min="1" max="2.4" step="0.02" value="1" aria-label="Zoom prescription preview">
            </div>
            <footer class="rx-crop-foot">
              <button type="button" class="rx-crop-cancel" id="prescription-viewer-close-btn">Cancel</button>
            </footer>
          </div>
        </div>
<?php endif; ?>
<?php if (empty($pharmacyOrdersListOnly)): ?>
<script type="application/json" id="pharmacy-orders-payload"><?= json_encode(pharmacy_orders_list_payload_from($filteredOrders, $orderItemsCache, $statusCounts, $activeStatus), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
      </section>
<?php endif; ?>
