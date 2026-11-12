<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/views/media.php';

initSession();

if (isLoggedIn('LABORATORY') && isset($_SESSION['lab_id'])) {
    header('Location: ' . APP_URL . '/lab/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    validateCsrf();
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.*, l.id AS lab_id, l.name AS lab_name, l.lab_uid,
                   l.verification_status AS lab_status
            FROM users u
            JOIN laboratories l ON l.user_id = u.id
            WHERE u.email = ? AND u.role = 'LABORATORY'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            AuditService::log(AuditService::FAILED_LOGIN, [
                'metadata' => ['email' => $email, 'role' => 'LABORATORY']
            ]);
            $error = 'Invalid email or password.';
        } elseif ($user['status'] !== 'ACTIVE') {
            $error = 'Your account is ' . strtolower($user['status']) . '. Contact support.';
        } elseif ($user['lab_status'] !== 'VERIFIED') {
            $error = 'Your laboratory is pending verification by the system administrator.';
        } else {
            $stmtHosp = $db->prepare("
                SELECT hl.*, h.name AS hospital_name, h.hospital_uid
                FROM hospital_laboratories hl
                JOIN hospitals h ON hl.hospital_id = h.id
                WHERE hl.laboratory_id = ? AND hl.status = 'APPROVED'
                ORDER BY hl.approved_at DESC
                LIMIT 1
            ");
            $stmtHosp->execute([$user['lab_id']]);
            $affiliation = $stmtHosp->fetch();

            if (!$affiliation) {
                $error = 'Your laboratory has no approved hospital affiliation. Contact your hospital administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['role']          = 'LABORATORY';
                $_SESSION['lab_id']        = $user['lab_id'];
                $_SESSION['lab_uid']       = $user['lab_uid'];
                $_SESSION['lab_name']      = $user['lab_name'];
                $_SESSION['hospital_id']   = $affiliation['hospital_id'];
                $_SESSION['hospital_name'] = $affiliation['hospital_name'];
                $_SESSION['name']          = $user['lab_name'];

                $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

                AuditService::log(AuditService::LOGIN, [
                    'user_id'     => $user['id'],
                    'hospital_id' => $affiliation['hospital_id'],
                    'metadata'    => ['role' => 'LABORATORY', 'lab_uid' => $user['lab_uid']],
                ]);

                header('Location: ' . APP_URL . '/lab/dashboard.php');
                exit;
            }
        }
    }
}

$csrf = generateCsrfToken();
$loginTitle = 'Laboratory Login';
$loginTitleBn = 'ল্যাব লগইন';
$loginImage = MC_IMG_LAB;
$demoEmail = 'lab@apexdiagnostics.com';
$demoPass = 'Demo123!';
$demoNote = 'Apex Diagnostics · LAB-000001';
$registerUrl = APP_URL . '/lab/register.php';
$backUrl = APP_URL . '/role-selection.php';

ob_start();
?>
<form method="POST" id="login-form">
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <input type="hidden" name="login" value="1">

  <label class="mc-field">
    <span>Lab email</span>
    <input id="email" name="email" type="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
  </label>

  <label class="mc-field">
    <span>Password</span>
    <input id="password" name="password" type="password" required autocomplete="current-password">
  </label>

  <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg mc-btn-block" style="margin-top:1.1rem">Log in to lab portal</button>
  <button type="button" onclick="mcFillDemo('lab@apexdiagnostics.com','Demo123!')" class="mc-btn mc-btn-secondary mc-btn-lg mc-btn-block" style="margin-top:.55rem">Fill demo credentials</button>
</form>
<?php
$loginFormHtml = ob_get_clean();
require __DIR__ . '/../app/views/auth/login_shell.php';
