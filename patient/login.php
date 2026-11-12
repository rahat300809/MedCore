<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/OTPService.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/views/media.php';

initSession();

$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
if (!empty($redirect) && (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//'))) {
    $redirect = '';
}

if (isLoggedIn('PATIENT')) {
    header('Location: ' . APP_URL . (!empty($redirect) ? $redirect : '/patient/dashboard.php'));
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
                $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);
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

                header('Location: ' . APP_URL . (!empty($redirect) ? $redirect : '/patient/dashboard.php'));
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
$loginTitle = 'Patient Login';
$loginTitleBn = 'নাগরিক লগইন';
$loginImage = MC_IMG_PATIENT;
$demoEmail = 'rahat@example.com';
$demoPass = 'Demo123!';
$demoNote = 'OTP: 1234 · Rahat Mahamud (PT-000001)';
$registerUrl = APP_URL . '/patient/register.php';
$backUrl = APP_URL . '/role-selection.php';

ob_start();
?>
<form method="POST" id="login-form">
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <?php if (!empty($redirect)): ?>
    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
  <?php endif; ?>

  <label class="mc-field">
    <span>Email</span>
    <input id="email" name="email" type="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
  </label>

  <label class="mc-field">
    <span>Password</span>
    <input id="password" name="password" type="password" required autocomplete="current-password">
  </label>

  <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg mc-btn-block" style="margin-top:1.1rem">Log in securely</button>
  <button type="button" onclick="mcFillDemo('rahat@example.com','Demo123!')" class="mc-btn mc-btn-secondary mc-btn-lg mc-btn-block" style="margin-top:.55rem">Fill demo credentials</button>
</form>
<?php
$loginFormHtml = ob_get_clean();
require __DIR__ . '/../app/views/auth/login_shell.php';
