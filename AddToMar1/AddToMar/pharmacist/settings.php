<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('pharmacist');
$page_title = 'Settings';
$u = current_user();
$success = ''; $error = '';

$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
if (!$settings) {
    $pdo->exec("INSERT INTO settings (pharmacy_name) VALUES ('AddToMar Pharmacy')");
    $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$u['id']]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['update_pharmacy'])) {
        $name = clean($_POST['pharmacy_name']);
        $address = clean($_POST['address']);
        $contact = clean($_POST['contact_number']);
        $downPct = max(0, min(100, (int)$_POST['down_payment_percent']));
        $pdo->prepare("UPDATE settings SET pharmacy_name=?, address=?, contact_number=?, down_payment_percent=? WHERE id=?")
            ->execute([$name, $address, $contact, $downPct, $settings['id']]);
        $success = 'Pharmacy information updated.';
        $settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
    } elseif (isset($_POST['update_profile'])) {
        $fullname = clean($_POST['fullname']);
        $contact = clean($_POST['contact']);
        $pdo->prepare("UPDATE users SET fullname=?, contact=? WHERE id=?")->execute([$fullname, $contact, $u['id']]);
        $_SESSION['fullname'] = $fullname;
        $success = 'Account updated.';
        $profile['fullname'] = $fullname; $profile['contact'] = $contact;
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $profile['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT), $u['id']]);
            $success = 'Password changed successfully.';
        }
    }
}

$hero_badge = 'Settings';
$hero_title = 'Settings';
$hero_desc = 'Manage pharmacy information and your account.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <?php if ($success): ?><div class="alert alert-success fade-in-up"><?php echo clean($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger fade-in-up"><?php echo clean($error); ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card p-4 fade-in-up">
        <h6 class="fw-bold mb-3">Pharmacy Information</h6>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <div class="mb-3"><label class="form-label">Pharmacy Name</label><input type="text" name="pharmacy_name" class="form-control" value="<?php echo clean($settings['pharmacy_name']); ?>" required></div>
          <div class="mb-3"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?php echo clean($settings['address']); ?>"></div>
          <div class="mb-3"><label class="form-label">Contact Number</label><input type="text" name="contact_number" class="form-control" value="<?php echo clean($settings['contact_number']); ?>"></div>
          <div class="mb-3"><label class="form-label">Required Down Payment (%)</label><input type="number" name="down_payment_percent" class="form-control" min="0" max="100" value="<?php echo (int)$settings['down_payment_percent']; ?>" required></div>
          <button type="submit" name="update_pharmacy" class="btn btn-gradient">Save Pharmacy Info</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card p-4 fade-in-up">
        <h6 class="fw-bold mb-3">Account Settings</h6>
        <form method="POST" class="mb-4">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="fullname" class="form-control" value="<?php echo clean($profile['fullname']); ?>" required></div>
          <div class="mb-3"><label class="form-label">Contact Number</label><input type="text" name="contact" class="form-control" value="<?php echo clean($profile['contact']); ?>"></div>
          <button type="submit" name="update_profile" class="btn btn-outline-soft">Update Profile</button>
        </form>
        <hr>
        <h6 class="fw-bold mb-3 mt-3">Change Password</h6>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
          <button type="submit" name="change_password" class="btn btn-gradient-blue">Update Password</button>
        </form>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
