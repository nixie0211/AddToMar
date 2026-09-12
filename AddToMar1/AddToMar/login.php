<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) { header('Location: ' . BASE_URL); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please try again.';
    } else {
        $login = clean($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($login === '' || $password === '') {
            $error = 'Please enter your email/username and password.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email']    = $user['email'];
                $_SESSION['role']     = $user['role'];

                if (!empty($_POST['remember'])) {
                    setcookie('addtomar_remember', $user['username'], time() + (86400 * 14), '/');
                }

                header('Location: ' . BASE_URL . ($user['role'] === 'pharmacist' ? 'pharmacist/dashboard.php' : 'customer/dashboard.php'));
                exit;
            } else {
                $error = 'Invalid credentials. Please check your email/username and password.';
            }
        }
    }
}

$page_title = 'Sign In';
$remembered = $_COOKIE['addtomar_remember'] ?? '';
$css_v = file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time();
$js_v = file_exists(__DIR__ . '/assets/js/main.js') ? filemtime(__DIR__ . '/assets/js/main.js') : time();
?>
<!DOCTYPE html>
<html lang="en" class="auth-page">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light only">
<title>Sign In | AddToMar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo $css_v; ?>">
<style>
  html, body.auth-page { background: #ffffff !important; color: #0F172A !important; }
</style>
</head>
<body class="auth-page">
<script>
  document.body.classList.remove('dark-mode');
  try { localStorage.setItem('addtomar_theme', 'light'); } catch (e) {}
</script>
<div class="auth-wrapper">

  <div class="auth-visual-side" style="background-image:url('https://images.unsplash.com/photo-1587854692152-cbe660dbde88?q=80&w=1600&auto=format&fit=crop');">
    <div class="auth-visual-content">
      <div class="auth-brand">
        <span class="auth-brand-icon"><i class="bi bi-capsule"></i></span>
        <span class="auth-brand-name">AddToMar</span>
      </div>
      <span class="auth-badge"><i class="bi bi-shield-check"></i> Pharmacy Inventory Management System</span>
      <h1 class="auth-heading">Managing medicines efficiently,<br>serving customers better.</h1>
      <p class="auth-desc">Browse available OTC medicines, check stock availability, place orders online, and pick up your purchases with ease.</p>
      <div class="auth-pulse-line" aria-hidden="true">
        <svg viewBox="0 0 320 24" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0 12 H110 L125 12 L132 4 L140 20 L148 8 L156 16 L164 12 L320 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>
    <div class="auth-visual-footer">&copy; <?php echo date('Y'); ?> AddToMar</div>
  </div>

  <div class="auth-form-side">
    <div class="auth-mobile-hero d-lg-none">
      <div class="auth-brand auth-brand-sm">
        <span class="auth-brand-icon"><i class="bi bi-capsule"></i></span>
        <span class="auth-brand-name">AddToMar</span>
      </div>
      <span class="auth-badge auth-badge-sm"><i class="bi bi-shield-check"></i> Pharmacy System</span>
      <h1 class="auth-heading auth-heading-sm">Managing medicines efficiently,<br>serving customers better.</h1>
    </div>
    <div class="auth-form-inner fade-in-up">
      <h2 class="fw-bold mb-1">Welcome Back</h2>
      <p class="section-sub mb-4">Sign in to continue to AddToMar.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?php echo clean($error); ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="mb-3">
          <label class="form-label">Email or Username</label>
          <div class="input-icon-group">
            <i class="bi bi-person"></i>
            <input type="text" name="login" class="form-control" placeholder="you@example.com" value="<?php echo clean($remembered); ?>" required autofocus>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label">Password</label>
          <div class="input-icon-group input-icon-group-toggle">
            <i class="bi bi-lock"></i>
            <input type="password" id="pwInput" name="password" class="form-control" placeholder="Enter your password" required>
            <button type="button" class="toggle-password" data-target="#pwInput" aria-label="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" <?php echo $remembered ? 'checked' : ''; ?>>
            <label class="form-check-label small" for="remember">Remember Me</label>
          </div>
          <a href="forgot-password.php" class="small text-decoration-none">Forgot Password?</a>
        </div>

        <button type="submit" class="btn btn-gradient w-100 py-2">Sign In</button>

        <p class="text-center small mt-4 mb-0 text-muted">
          New Customer? <a href="register.php" class="fw-semibold text-decoration-none">Create an Account</a>
        </p>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/main.js?v=<?php echo $js_v; ?>"></script>
</body>
</html>
