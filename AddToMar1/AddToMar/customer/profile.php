<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('customer');
$u = current_user();
$page_title = 'Profile';
$success = ''; $error = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$u['id']]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['update_profile'])) {
        $fullname = clean($_POST['fullname']);
        $contact  = clean($_POST['contact']);
        $address  = clean($_POST['address']);
        $upd = $pdo->prepare("UPDATE users SET fullname=?, contact=?, address=? WHERE id=?");
        $upd->execute([$fullname, $contact, $address, $u['id']]);
        $_SESSION['fullname'] = $fullname;
        $success = 'Profile updated successfully.';
        $profile['fullname'] = $fullname; $profile['contact'] = $contact; $profile['address'] = $address;
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
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $u['id']]);
            $success = 'Password changed successfully.';
        }
    }
}

$hero_badge = 'Profile';
$hero_title = 'My Profile';
$hero_desc = 'Manage your personal information and security.';
include __DIR__ . '/../includes/hero-layout-start.php';
?>

  <?php if ($success): ?><div class="alert alert-success fade-in-up"><?php echo clean($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger fade-in-up"><?php echo clean($error); ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card p-4 fade-in-up">
        <div class="d-flex align-items-center gap-3 mb-4">
          <span class="avatar-circle" style="width:56px;height:56px;font-size:22px;"><?php echo strtoupper(substr($profile['fullname'],0,1)); ?></span>
          <div>
            <h6 class="fw-bold mb-0"><?php echo clean($profile['fullname']); ?></h6>
            <span class="text-muted small">@<?php echo clean($profile['username']); ?></span>
          </div>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="fullname" class="form-control" value="<?php echo clean($profile['fullname']); ?>" required></div>
          <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" value="<?php echo clean($profile['email']); ?>" disabled></div>
          <div class="mb-3"><label class="form-label">Contact Number</label><input type="text" name="contact" class="form-control" value="<?php echo clean($profile['contact']); ?>"></div>
          <div class="mb-3"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="<?php echo clean($profile['address']); ?>"></div>
          <button type="submit" name="update_profile" class="btn btn-gradient">Save Changes</button>
        </form>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card p-4 fade-in-up">
        <h6 class="fw-bold mb-3">Change Password</h6>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
          <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
          <button type="submit" name="change_password" class="btn btn-outline-soft">Update Password</button>
        </form>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/hero-layout-end.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
