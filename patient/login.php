<?php
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/services/OTPService.php';
require_once __DIR__ . '/../../app/services/AuditService.php';

initSession();

// Already logged in
if (isLoggedIn('PATIENT')) {
    header('Location: ' . APP_URL . '/patient/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.*, p.id AS patient_id, p.full_name, p.patient_uid
            FROM users u
            JOIN patients p ON p.user_id = u.id
            WHERE u.email = ? AND u.role = 'PATIENT'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] === 'SUSPENDED' || $user['status'] === 'BLOCKED') {
                $error = 'Your account has been ' . strtolower($user['status']) . '. Please contact support.';
                AuditService::log(AuditService::FAILED_LOGIN, [
                    'metadata' => ['reason' => 'account_' . $user['status'], 'email' => $email]
                ]);
            } else {
                // Update last login
                $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

                // Set session
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['role']       = 'PATIENT';
                $_SESSION['patient_id'] = $user['patient_id'];
                $_SESSION['name']       = $user['full_name'];
                $_SESSION['patient_uid'] = $user['patient_uid'];
                $_SESSION['email']      = $user['email'];

                AuditService::log(AuditService::LOGIN, [
                    'user_id'    => $user['id'],
                    'patient_id' => $user['patient_id'],
                    'metadata'   => ['method' => 'password']
                ]);

                header('Location: ' . APP_URL . '/patient/dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
            AuditService::log(AuditService::FAILED_LOGIN, [
                'metadata' => ['email' => $email, 'reason' => 'invalid_credentials']
            ]);
        }
    }
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Login — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
</head>
<body style="background:var(--mc-bg);">

<div class="auth-flow-container">
  <div class="auth-card">
    <!-- Logo -->
    <div class="auth-logo">
      <a href="<?= APP_URL ?>/" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--mc-text);">
        <div class="logo-icon">M</div>
        <span style="font-weight:800;font-size:1.1rem;">MedCore</span>
      </a>
    </div>

    <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:var(--space-2);">Patient Login</h1>
    <p style="font-size:14px;color:var(--mc-text-secondary);margin-bottom:var(--space-6);">
      Access your personal health record securely.
    </p>

    <?php if ($error): ?>
      <div class="alert alert-error mb-4" role="alert" data-auto-dismiss="5000">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success mb-4" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="login-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          placeholder="you@example.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          required
          autocomplete="email"
          aria-required="true"
        >
      </div>

      <div class="form-group" style="position:relative;">
        <label class="form-label" for="password">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          placeholder="Enter your password"
          required
          autocomplete="current-password"
          aria-required="true"
        >
        <button type="button"
          onclick="togglePassword('password', this)"
          style="position:absolute;right:12px;top:38px;background:none;border:none;cursor:pointer;color:var(--mc-text-muted);"
          aria-label="Toggle password visibility">
          <i class="bi bi-eye" id="password-eye"></i>
        </button>
      </div>

      <div class="d-flex justify-between align-center" style="margin-bottom:var(--space-6);">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
          <input type="checkbox" name="remember" style="width:14px;height:14px;">
          Remember me
        </label>
        <a href="#" style="font-size:13px;">Forgot password?</a>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg" id="login-submit-btn">
        <i class="bi bi-shield-lock-fill"></i>
        Log In Securely
      </button>
    </form>

    <div style="text-align:center;margin-top:var(--space-6);">
      <p style="font-size:13px;color:var(--mc-text-muted);">
        Don't have an account?
        <a href="<?= APP_URL ?>/patient/register.php" style="font-weight:600;">Register Free</a>
      </p>
      <p style="font-size:12px;color:var(--mc-text-muted);margin-top:var(--space-3);">
        <a href="<?= APP_URL ?>/role-selection.php">← Change Portal</a>
      </p>
    </div>

    <!-- Security note -->
    <div style="margin-top:var(--space-6);padding-top:var(--space-5);border-top:1px solid var(--mc-border);">
      <div style="display:flex;align-items:center;justify-content:center;gap:var(--space-5);">
        <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--mc-text-muted);">
          <i class="bi bi-shield-fill-check" style="color:var(--mc-green);"></i>AES-256 Encrypted
        </div>
        <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--mc-text-muted);">
          <i class="bi bi-lock-fill" style="color:var(--mc-blue);"></i>HIPAA Ready
        </div>
        <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--mc-text-muted);">
          <i class="bi bi-eye-slash-fill" style="color:var(--mc-amber);"></i>Zero-Trust Access
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (APP_ENV === 'development'): ?>
<!-- Dev helper -->
<div style="position:fixed;bottom:20px;right:20px;background:var(--mc-amber-light);border:1px solid var(--mc-amber);border-radius:var(--radius-md);padding:12px 16px;font-size:12px;max-width:280px;z-index:9999;">
  <strong>🔧 Dev Credentials:</strong><br>
  Email: <code>rahat@example.com</code><br>
  Pass: <code>Demo123!</code> (update hash in seed.sql)<br>
  <small style="color:var(--mc-text-muted);">Actual hash: password_hash('Demo123!')</small>
</div>
<?php endif; ?>

<script>
function togglePassword(id, btn) {
  const input = document.getElementById(id);
  const eye = document.getElementById(id + '-eye');
  if (input.type === 'password') {
    input.type = 'text';
    eye.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    eye.className = 'bi bi-eye';
  }
}

// Loading state on submit
document.getElementById('login-form')?.addEventListener('submit', function() {
  const btn = document.getElementById('login-submit-btn');
  btn.innerHTML = '<span class="spinner-sm"></span> Verifying...';
  btn.disabled = true;
});
</script>
</body>
</html>
