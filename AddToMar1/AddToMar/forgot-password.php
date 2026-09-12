<?php
require_once __DIR__ . '/includes/auth.php';
$message = ''; $link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $email = clean($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $upd->execute([$token, $expires, $user['id']]);
        $link = BASE_URL . 'reset-password.php?token=' . $token;
        $message = 'A password reset link has been generated below (in a real deployment this would be emailed to you).';
    } else {
        $message = 'If that email exists in our system, a reset link has been generated.';
    }
}
?>
<?php $page_title = 'Forgot Password'; include __DIR__ . '/includes/header.php'; ?>
<div class="auth-wrapper d-flex align-items-center justify-content-center" style="min-height:100vh;">
  <div class="card p-4 fade-in-up" style="max-width:440px; width:100%;">
    <h4 class="fw-bold mb-1">Forgot Password</h4>
    <p class="section-sub mb-4">Enter your email to receive a reset link.</p>
    <?php if ($message): ?><div class="alert alert-info small"><?php echo clean($message); ?></div><?php endif; ?>
    <?php if ($link): ?><div class="alert alert-success small text-break"><a href="<?php echo $link; ?>"><?php echo $link; ?></a></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <button class="btn btn-gradient w-100">Send Reset Link</button>
      <p class="text-center small mt-3"><a href="login.php">Back to Sign In</a></p>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
