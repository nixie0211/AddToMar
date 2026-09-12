<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Inventory';

$medicines = $pdo->query("SELECT * FROM medicines ORDER BY created_at DESC")->fetchAll();
$categories = ['Pain Reliever','Vitamins','Supplements','Antacid','Allergy Medicine','Cough and Cold','First Aid','OTC Medicines'];

$hero_badge = 'Inventory';
$hero_title = 'Inventory Management';
$hero_desc = 'Add, edit, and monitor your medicine stock.';
$hero_actions_html = '<button class="btn-hero-primary" data-bs-toggle="modal" data-bs-target="#medicineModal" onclick="openAddModal()"><i class="bi bi-plus-lg"></i> Add Medicine</button>';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <div class="card p-3 fade-in-up">
    <div class="table-responsive">
      <table class="table align-middle" id="inventoryTable">
        <thead>
          <tr><th>Image</th><th>Code</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Expiry</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($medicines as $m):
          $stockClass = $m['quantity'] == 0 ? 'stock-out' : ($m['quantity'] <= $m['reorder_level'] ? 'stock-low' : 'stock-in');
          $stockLabel = $m['quantity'] == 0 ? 'Out of Stock' : ($m['quantity'] <= $m['reorder_level'] ? 'Low Stock' : 'In Stock');
          $isExpiring = $m['expiration_date'] && strtotime($m['expiration_date']) < strtotime('+60 days');
        ?>
          <tr>
            <td><img src="<?php echo UPLOAD_MED_URL . clean($m['image']); ?>" onerror="this.src='<?php echo BASE_URL; ?>assets/images/default-medicine.png'" style="width:42px;height:42px;object-fit:cover;border-radius:8px;"></td>
            <td class="small"><?php echo clean($m['medicine_code']); ?></td>
            <td class="fw-semibold"><?php echo clean($m['medicine_name']); ?></td>
            <td><?php echo clean($m['category']); ?></td>
            <td><?php echo money($m['price']); ?></td>
            <td><span class="stock-pill <?php echo $stockClass; ?>"><?php echo $m['quantity']; ?> · <?php echo $stockLabel; ?></span></td>
            <td class="small <?php echo $isExpiring ? 'text-danger fw-semibold' : 'text-muted'; ?>"><?php echo $m['expiration_date'] ? date('M d, Y', strtotime($m['expiration_date'])) : '—'; ?></td>
            <td><span class="badge <?php echo $m['status']==='active' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo ucfirst($m['status']); ?></span></td>
            <td>
              <button class="btn btn-sm btn-outline-soft edit-btn" data-med='<?php echo json_encode($m); ?>' data-bs-toggle="modal" data-bs-target="#medicineModal"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-sm btn-outline-soft text-danger delete-btn" data-id="<?php echo $m['id']; ?>"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>

<!-- Add/Edit Medicine Modal -->
<div class="modal fade" id="medicineModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-xl">
      <form id="medicineForm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="modalTitle">Add Medicine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <input type="hidden" name="id" id="med_id">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Medicine Code</label><input type="text" name="medicine_code" id="med_code" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Medicine Name</label><input type="text" name="medicine_name" id="med_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Generic Name</label><input type="text" name="generic_name" id="med_generic" class="form-control"></div>
            <div class="col-md-6">
              <label class="form-label">Category</label>
              <select name="category" id="med_category" class="form-select" required>
                <?php foreach ($categories as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Dosage</label><input type="text" name="dosage" id="med_dosage" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Price (₱)</label><input type="number" step="0.01" name="price" id="med_price" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Quantity</label><input type="number" name="quantity" id="med_qty" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Reorder Level</label><input type="number" name="reorder_level" id="med_reorder" class="form-control" value="10" required></div>
            <div class="col-md-4"><label class="form-label">Expiration Date</label><input type="date" name="expiration_date" id="med_expiry" class="form-control"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="med_desc" class="form-control" rows="2"></textarea></div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" id="med_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Image</label>
              <input type="file" name="image" id="med_image" class="form-control image-upload-input" accept="image/*" data-preview="#imgPreview">
            </div>
            <div class="col-12"><img id="imgPreview" style="max-height:100px;display:none;border-radius:10px;" onload="this.style.display='block';"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-soft" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-gradient">Save Medicine</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php $extra_scripts = '<script>
$(function(){ $("#inventoryTable").DataTable({ order: [], pageLength: 10 }); });

function openAddModal() {
  document.getElementById("medicineForm").reset();
  document.getElementById("med_id").value = "";
  document.getElementById("modalTitle").textContent = "Add Medicine";
  document.getElementById("imgPreview").style.display = "none";
}

document.querySelectorAll(".edit-btn").forEach(btn => {
  btn.addEventListener("click", function () {
    const m = JSON.parse(this.dataset.med);
    document.getElementById("modalTitle").textContent = "Edit Medicine";
    document.getElementById("med_id").value = m.id;
    document.getElementById("med_code").value = m.medicine_code;
    document.getElementById("med_name").value = m.medicine_name;
    document.getElementById("med_generic").value = m.generic_name || "";
    document.getElementById("med_category").value = m.category;
    document.getElementById("med_dosage").value = m.dosage || "";
    document.getElementById("med_price").value = m.price;
    document.getElementById("med_qty").value = m.quantity;
    document.getElementById("med_reorder").value = m.reorder_level;
    document.getElementById("med_expiry").value = m.expiration_date || "";
    document.getElementById("med_desc").value = m.description || "";
    document.getElementById("med_status").value = m.status;
    const preview = document.getElementById("imgPreview");
    preview.src = "' . UPLOAD_MED_URL . '" + m.image;
    preview.style.display = "block";
  });
});

document.getElementById("medicineForm").addEventListener("submit", async function (e) {
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch("' . BASE_URL . 'ajax/crud-medicine.php", { method: "POST", body: fd });
  const data = await res.json();
  if (data.success) {
    showToast("success", data.message);
    setTimeout(() => location.reload(), 900);
  } else {
    showToast("error", data.message);
  }
});

document.querySelectorAll(".delete-btn").forEach(btn => {
  btn.addEventListener("click", async function () {
    const confirmed = await confirmAction({ title: "Delete this medicine?", text: "This action cannot be undone.", icon: "warning" });
    if (!confirmed.isConfirmed) return;
    const res = await ajaxPost("' . BASE_URL . 'ajax/crud-medicine.php", { action: "delete", id: this.dataset.id, csrf_token: "' . generate_csrf_token() . '" });
    if (res.success) { showToast("success", res.message); setTimeout(() => location.reload(), 800); }
    else { showToast("error", res.message); }
  });
});
</script>';
include __DIR__ . '/../includes/footer.php'; ?>
