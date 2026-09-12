<?php
require_once __DIR__ . '/includes/auth.php';
$token = clean($_GET['token'] ?? $_POST['token'] ?? '');
$error = ''; $success = '';

$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = 'This reset link is invalid or has expired.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $upd->execute([$hash, $user['id']]);
        $success = 'Password updated successfully. You may now sign in.';
    }
}
?>
<?php $page_title = 'Reset Password'; include __DIR__ . '/includes/header.php'; ?>
<div class="auth-wrapper d-flex align-items-center justify-content-center" style="min-height:100vh;">
  <div class="card p-4 fade-in-up" style="max-width:440px; width:100%;">
    <h4 class="fw-bold mb-1">Reset Password</h4>
    <p class="section-sub mb-4">Choose a new password for your account.</p>
    <?php if ($error): ?><div class="alert alert-danger small"><?php echo clean($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success small"><?php echo clean($success); ?></div><?php endif; ?>
    <?php if ($user && !$success): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <input type="hidden" name="token" value="<?php echo clean($token); ?>">
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>
      <button class="btn btn-gradient w-100">Update Password</button>
    </form>
    <?php else: ?>
      <p class="text-center small mt-3"><a href="login.php">Back to Sign In</a></p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
