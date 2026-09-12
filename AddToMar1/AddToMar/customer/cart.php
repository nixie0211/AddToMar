<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');
$u = current_user();
$page_title = 'Shopping Cart';

$stmt = $pdo->prepare("SELECT c.id AS cart_id, c.quantity, m.* FROM cart c
                        JOIN medicines m ON m.id = c.medicine_id
                        WHERE c.customer_id = ? ORDER BY c.created_at DESC");
$stmt->execute([$u['id']]);
$items = $stmt->fetchAll();

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
$downPct = (int)($settings['down_payment_percent'] ?? 50);

$total = 0;
foreach ($items as $it) { $total += $it['price'] * $it['quantity']; }
$downPayment = round($total * ($downPct / 100), 2);
$balanceOnPickup = round($total - $downPayment, 2);

$hero_badge = 'Cart';
$hero_title = 'Shopping Cart';
$hero_desc = 'Review your items before checkout.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <?php if (!$items): ?>
    <div class="card p-5 text-center fade-in-up">
      <i class="bi bi-cart-x" style="font-size:2.5rem;color:var(--muted);"></i>
      <p class="text-muted mt-3 mb-3">Your cart is empty.</p>
      <a href="catalog.php" class="btn btn-gradient mx-auto" style="width:fit-content;">Browse Medicines</a>
    </div>
  <?php else: ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card p-3 fade-in-up" id="cartTableWrap">
        <table class="table align-middle">
          <thead><tr><th>Medicine</th><th>Price</th><th style="width:140px;">Quantity</th><th>Subtotal</th><th></th></tr></thead>
          <tbody id="cartBody">
          <?php foreach ($items as $it): ?>
            <tr data-cart-id="<?php echo $it['cart_id']; ?>" data-price="<?php echo $it['price']; ?>">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="<?php echo UPLOAD_MED_URL . clean($it['image']); ?>" onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-medicine.png'" style="width:44px;height:44px;object-fit:cover;border-radius:8px;">
                  <div class="small fw-semibold"><?php echo clean($it['medicine_name']); ?></div>
                </div>
              </td>
              <td><?php echo money($it['price']); ?></td>
              <td>
                <input type="number" class="form-control form-control-sm qty-input" min="1" value="<?php echo $it['quantity']; ?>" style="width:80px;">
              </td>
              <td class="subtotal fw-semibold"><?php echo money($it['price'] * $it['quantity']); ?></td>
              <td><button class="btn btn-sm btn-outline-soft text-danger remove-item"><i class="bi bi-trash"></i></button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <button class="btn btn-sm text-danger" id="clearCartBtn"><i class="bi bi-x-circle me-1"></i>Clear Cart</button>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-4 fade-in-up">
        <h6 class="fw-bold mb-3">Order Summary</h6>
        <div class="d-flex justify-content-between small mb-2"><span class="text-muted">Total Items</span><span id="sumItems"><?php echo array_sum(array_column($items,'quantity')); ?></span></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Amount</span><span class="fw-semibold" id="sumTotal"><?php echo money($total); ?></span></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Pay Now (<?php echo $downPct; ?>%)</span><span class="fw-semibold text-primary-em" id="sumDown"><?php echo money($downPayment); ?></span></div>
        <div class="d-flex justify-content-between mb-3"><span class="text-muted">Balance on Pickup</span><span class="fw-semibold" id="sumBalance"><?php echo money($balanceOnPickup); ?></span></div>
        <hr>
        <a href="checkout.php" class="btn btn-gradient w-100">Proceed to Checkout</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>

<?php $extra_scripts = '<script>
const DOWN_PCT = ' . $downPct . ';
function recalc() {
  let total = 0, items = 0;
  document.querySelectorAll("#cartBody tr").forEach(row => {
    const price = parseFloat(row.dataset.price);
    const qty = parseInt(row.querySelector(".qty-input").value) || 1;
    const subtotal = price * qty;
    row.querySelector(".subtotal").textContent = "₱" + subtotal.toLocaleString(undefined, {minimumFractionDigits:2});
    total += subtotal; items += qty;
  });
  document.getElementById("sumItems").textContent = items;
  document.getElementById("sumTotal").textContent = "₱" + total.toLocaleString(undefined, {minimumFractionDigits:2});
  document.getElementById("sumDown").textContent = "₱" + (total * DOWN_PCT/100).toLocaleString(undefined, {minimumFractionDigits:2});
  document.getElementById("sumBalance").textContent = "₱" + (total * (100 - DOWN_PCT)/100).toLocaleString(undefined, {minimumFractionDigits:2});
}

document.querySelectorAll(".qty-input").forEach(inp => {
  inp.addEventListener("change", async function () {
    const row = this.closest("tr");
    const res = await ajaxPost("' . BASE_URL . 'ajax/update-cart.php", { cart_id: row.dataset.cartId, quantity: this.value });
    if (res.success) { recalc(); updateCartBadge(res.cart_count); } else { showToast("error", res.message); }
  });
});

document.querySelectorAll(".remove-item").forEach(btn => {
  btn.addEventListener("click", async function () {
    const row = this.closest("tr");
    const confirmed = await confirmAction({title:"Remove item?", text:"This item will be removed from your cart."});
    if (!confirmed.isConfirmed) return;
    const res = await ajaxPost("' . BASE_URL . 'ajax/remove-cart.php", { cart_id: row.dataset.cartId });
    if (res.success) { row.remove(); recalc(); updateCartBadge(res.cart_count); showToast("success", res.message); }
  });
});

document.getElementById("clearCartBtn")?.addEventListener("click", async function () {
  const confirmed = await confirmAction({title:"Clear entire cart?"});
  if (!confirmed.isConfirmed) return;
  const res = await ajaxPost("' . BASE_URL . 'ajax/remove-cart.php", { clear: 1 });
  if (res.success) location.reload();
});
</script>';
include __DIR__ . '/../includes/footer.php'; ?>
