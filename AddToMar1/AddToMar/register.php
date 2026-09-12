<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) { header('Location: ' . BASE_URL); exit; }

$error = ''; $success = '';
$old = ['fullname'=>'', 'contact'=>'', 'email'=>'', 'address'=>'', 'username'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please try again.';
    } else {
        $old['fullname'] = clean($_POST['fullname'] ?? '');
        $old['contact']  = clean($_POST['contact'] ?? '');
        $old['email']    = clean($_POST['email'] ?? '');
        $old['address']  = clean($_POST['address'] ?? '');
        $old['username'] = clean($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if (!$old['fullname'] || !$old['email'] || !$old['username'] || !$password) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $check->execute([$old['email'], $old['username']]);
            if ($check->fetch()) {
                $error = 'Email or username is already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (fullname, contact, email, address, username, password, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
                $stmt->execute([$old['fullname'], $old['contact'], $old['email'], $old['address'], $old['username'], $hash]);
                $success = 'Account created successfully! You may now sign in.';
                $old = ['fullname'=>'', 'contact'=>'', 'email'=>'', 'address'=>'', 'username'=>''];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account | AddToMar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-visual-side" style="background-image:url('https://images.unsplash.com/photo-1576602976047-174e57a47881?q=80&w=1600&auto=format&fit=crop');">
    <div>
      <div class="d-flex align-items-center gap-2 mb-4">
        <span class="brand-mark"><i class="bi bi-capsule"></i></span>
        <span class="fw-bold fs-4">AddToMar</span>
      </div>
      <span class="auth-badge"><i class="bi bi-heart-pulse"></i> Join AddToMar Today</span>
      <h1 class="auth-heading">Create an account and<br>order your medicines online.</h1>
      <p class="auth-desc">Sign up to browse OTC medicines, track stock in real time, and schedule easy pharmacy pickup.</p>
    </div>
    <div class="small">&copy; 2026 AddToMar</div>
  </div>

  <div class="auth-form-side">
    <div class="auth-form-inner fade-in-up" style="max-width:440px;">
      <h2 class="fw-bold mb-1">Create an Account</h2>
      <p class="section-sub mb-4">It only takes a minute.</p>

      <?php if ($error): ?><div class="alert alert-danger py-2 small"><?php echo clean($error); ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success py-2 small"><?php echo clean($success); ?></div><?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Full Name</label>
            <input type="text" name="fullname" class="form-control" value="<?php echo $old['fullname']; ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact" class="form-control" value="<?php echo $old['contact']; ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo $old['email']; ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control" value="<?php echo $old['address']; ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" value="<?php echo $old['username']; ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
        </div>

        <button type="submit" class="btn btn-gradient w-100 py-2 mt-4">Create Account</button>
        <p class="text-center small mt-4 mb-0 text-muted">
          Already have an account? <a href="login.php" class="fw-semibold text-decoration-none">Sign In</a>
        </p>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
