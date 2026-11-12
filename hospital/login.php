<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/views/media.php';

initSession();

if (isLoggedIn('HOSPITAL_ADMIN')) {
    header('Location: ' . APP_URL . '/hospital/dashboard.php');
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
                    'user_id'     => $user['id'],
                    'hospital_id' => $user['hospital_id'],
                    'metadata'    => ['method' => 'hospital_admin_password'],
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
$loginTitle = 'Hospital Login';
$loginTitleBn = 'হাসপাতাল লগইন';
$loginImage = MC_IMG_HOSPITAL;
$demoEmail = 'admin@abc-hospital.com';
$demoPass = 'Demo123!';
$demoNote = 'ABC General Hospital · HOSP-000001';
$registerUrl = APP_URL . '/hospital/register.php';
$backUrl = APP_URL . '/role-selection.php';

ob_start();
?>
<form method="POST" id="login-form">
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

  <label class="mc-field">
    <span>Admin email</span>
    <input id="email" name="email" type="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
  </label>

  <label class="mc-field">
    <span>Password</span>
    <input id="password" name="password" type="password" required autocomplete="current-password">
  </label>

  <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg mc-btn-block" style="margin-top:1.1rem">Log in to hospital portal</button>
  <button type="button" onclick="mcFillDemo('admin@abc-hospital.com','Demo123!')" class="mc-btn mc-btn-secondary mc-btn-lg mc-btn-block" style="margin-top:.55rem">Fill demo credentials</button>
</form>
<?php
$loginFormHtml = ob_get_clean();
require __DIR__ . '/../app/views/auth/login_shell.php';
