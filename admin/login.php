<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

if (isLoggedIn('SYSTEM_ADMIN')) {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your administrator email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM users
            WHERE email = ? AND role = 'SYSTEM_ADMIN'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'ACTIVE') {
                $error = 'Admin account status is ' . htmlspecialchars($user['status']) . '. Access denied.';
            } else {
                $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = 'SYSTEM_ADMIN';
                $_SESSION['email']   = $user['email'];
                $_SESSION['name']    = 'System Administrator';

                AuditService::log(AuditService::LOGIN, [
                    'user_id'  => $user['id'],
                    'metadata' => ['portal' => 'admin_console', 'method' => 'password']
                ]);

                header('Location: ' . APP_URL . '/admin/dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid administrator credentials.';
            AuditService::log(AuditService::FAILED_LOGIN, [
                'metadata' => ['email' => $email, 'role' => 'SYSTEM_ADMIN', 'portal' => 'admin_console']
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
  <title>System Admin Console — MedCore Central</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    .admin-auth-card {
      background: var(--mc-white);
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-xl);
      box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.15);
      padding: var(--space-8);
      width: 100%;
      max-width: 440px;
    }
    .admin-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      background: #FEF3C7;
      color: #92400E;
      border: 1px solid #FDE68A;
    }
  </style>
</head>
<body style="background: radial-gradient(circle at 50% 20%, #1E293B 0%, #0F172A 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">

<div class="admin-auth-card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6);">
    <a href="<?= APP_URL ?>/" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--mc-text);">
      <div class="logo-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">M</div>
      <span style="font-weight: 800; font-size: 1.1rem;">MedCore</span>
    </a>
    <span class="admin-badge"><i class="bi bi-shield-lock-fill"></i> Central Admin</span>
  </div>

  <div style="margin-bottom: var(--space-6);">
    <h1 style="font-size: 1.35rem; font-weight: 800; color: #0F172A; margin: 0 0 6px 0;">Central Management Console</h1>
    <p style="font-size: 13px; color: var(--mc-text-secondary); margin: 0;">
      National healthcare governance, doctor credential verification, and hospital network oversight.
    </p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    
    <div class="form-group">
      <label class="form-label" for="email">Admin Email Address</label>
      <div class="input-with-icon">
        <i class="bi bi-envelope-at"></i>
        <input type="email" id="email" name="email" class="form-control"
          placeholder="admin@medcore.local"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email" autofocus>
      </div>
    </div>

    <div class="form-group" style="position:relative;">
      <label class="form-label" for="password">Security Password</label>
      <div class="input-with-icon">
        <i class="bi bi-key"></i>
        <input type="password" id="password" name="password" class="form-control"
          placeholder="Enter password" required autocomplete="current-password">
      </div>
      <button type="button" onclick="togglePassword('password', this)"
        style="position:absolute;right:12px;top:38px;background:none;border:none;cursor:pointer;color:var(--mc-text-muted);">
        <i class="bi bi-eye" id="password-eye"></i>
      </button>
    </div>

    <button type="submit" class="btn btn-primary w-100 btn-lg" style="background: linear-gradient(135deg, #1E40AF, #1D4ED8); border: none; margin-top: var(--space-2);">
      <i class="bi bi-shield-check"></i> Authenticate & Enter Console
    </button>
  </form>

  <div style="text-align: center; margin-top: var(--space-6); font-size: 13px; color: var(--mc-text-muted);">
    <a href="<?= APP_URL ?>/role-selection.php" style="color: var(--mc-text-secondary); text-decoration: none;">
      ← Back to Portal Selection
    </a>
  </div>

  <?php if (APP_ENV === 'development'): ?>
  <div class="alert alert-warning" style="margin-top: var(--space-5); font-size: 12px;">
    <i class="bi bi-info-circle-fill"></i>
    <strong>Demo Root:</strong> <code>admin@medcore.local</code> · <code>Demo123!</code>
  </div>
  <?php endif; ?>
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
