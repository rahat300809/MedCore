<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

if (isLoggedIn('HOSPITAL_ADMIN')) {
    header('Location: ' . APP_URL . '/hospital/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.*, h.id AS hospital_id, h.name AS hospital_name,
                   h.hospital_uid, h.verification_status
            FROM users u
            JOIN hospitals h ON h.user_id = u.id
            WHERE u.email = ? AND u.role = 'HOSPITAL_ADMIN'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['verification_status'] !== 'VERIFIED') {
                $error = 'Your hospital is pending verification. Please contact MedCore admin.';
            } elseif (in_array($user['status'], ['SUSPENDED', 'BLOCKED'])) {
                $error = 'Account has been ' . strtolower($user['status']) . '. Contact support.';
            } else {
                $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
                session_regenerate_id(true);
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['role']          = 'HOSPITAL_ADMIN';
                $_SESSION['hospital_id']   = $user['hospital_id'];
                $_SESSION['hospital_name'] = $user['hospital_name'];
                $_SESSION['hospital_uid']  = $user['hospital_uid'];
                $_SESSION['name']          = $user['hospital_name'] . ' Admin';

                AuditService::log(AuditService::LOGIN, [
                    'user_id'    => $user['id'],
                    'hospital_id'=> $user['hospital_id'],
                    'metadata'   => ['method' => 'hospital_admin_password'],
                ]);

                header('Location: ' . APP_URL . '/hospital/dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
            AuditService::log(AuditService::FAILED_LOGIN, ['metadata' => ['email' => $email, 'role' => 'HOSPITAL_ADMIN']]);
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
  <title>Hospital Admin Login — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
</head>
<body style="background:var(--mc-bg);">
<div class="auth-flow-container">
  <div class="auth-card">
    <div class="auth-logo">
      <a href="<?= APP_URL ?>/" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--mc-text);">
        <div class="logo-icon">M</div>
        <span style="font-weight:800;">MedCore</span>
      </a>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:var(--space-5);">
      <div class="pillar-icon pillar-icon-teal" style="width:48px;height:48px;flex-shrink:0;">
        <i class="bi bi-building-fill-cross"></i>
      </div>
      <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">Hospital Portal</h1>
        <div style="font-size:13px;color:var(--mc-text-muted);">Administrator Login</div>
      </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error mb-4" data-auto-dismiss="5000">
      <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <div class="form-group">
        <label class="form-label" for="email">Admin Email</label>
        <input type="email" id="email" name="email" class="form-control"
          placeholder="admin@yourhospital.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>
      <div class="form-group" style="position:relative;">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
          placeholder="Your password" required autocomplete="current-password">
        <button type="button" onclick="togglePassword('password', this)"
          style="position:absolute;right:12px;top:38px;background:none;border:none;cursor:pointer;color:var(--mc-text-muted);">
          <i class="bi bi-eye" id="password-eye"></i>
        </button>
      </div>
      <button type="submit" class="btn btn-primary w-100 btn-lg" style="background:var(--mc-teal);border-color:var(--mc-teal);">
        <i class="bi bi-building-fill-check"></i> Log In to Hospital Portal
      </button>
    </form>

    <div style="text-align:center;margin-top:var(--space-5);font-size:13px;color:var(--mc-text-muted);">
      <a href="<?= APP_URL ?>/role-selection.php">← Back to Portal Selection</a>
    </div>

    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--mc-border); text-align:center; font-size:13px;">
      <span style="color:var(--mc-text-muted);">New hospital?</span>
      <a href="<?= APP_URL ?>/hospital/register.php" style="color: #0D9488; font-weight: 700; text-decoration: none; margin-left: 5px;">
        <i class="bi bi-building-add"></i> Apply to Join MedCore Network
      </a>
    </div>

    <?php if (APP_ENV === 'development'): ?>
    <div class="alert alert-warning" style="margin-top:var(--space-5);font-size:12px;">
      <i class="bi bi-bug-fill"></i>
      <strong>Dev:</strong> Email: <code>admin@abc-hospital.com</code> · Pass: <code>Demo123!</code>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
function togglePassword(id, btn) {
  const input = document.getElementById(id);
  const eye = document.getElementById(id + '-eye');
  input.type = input.type === 'password' ? 'text' : 'password';
  eye.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
