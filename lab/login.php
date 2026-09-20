<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

// Already logged in as lab
if (isLoggedIn('LABORATORY') && isset($_SESSION['lab_id'])) {
    header('Location: ' . APP_URL . '/lab/dashboard.php');
    exit;
}

$error = '';
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
            // Check for approved hospital affiliation
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
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['role']         = 'LABORATORY';
                $_SESSION['lab_id']       = $user['lab_id'];
                $_SESSION['lab_uid']      = $user['lab_uid'];
                $_SESSION['lab_name']     = $user['lab_name'];
                $_SESSION['hospital_id']  = $affiliation['hospital_id'];
                $_SESSION['hospital_name']= $affiliation['hospital_name'];
                $_SESSION['name']         = $user['lab_name'];

                $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

                AuditService::log(AuditService::LOGIN, [
                    'user_id'    => $user['id'],
                    'hospital_id'=> $affiliation['hospital_id'],
                    'metadata'   => ['role' => 'LABORATORY', 'lab_uid' => $user['lab_uid']],
                ]);

                header('Location: ' . APP_URL . '/lab/dashboard.php');
                exit;
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
<title>Laboratory Login — MedCore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/portal.css">
<style>
:root{--accent:#0ea5e9;--accent-dark:#0284c7;--glass:rgba(255,255,255,0.07);--border:rgba(255,255,255,0.12)}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%);font-family:'Inter',sans-serif}
.login-wrap{width:100%;max-width:420px;padding:1rem}
.login-card{background:rgba(15,23,42,0.8);border:1px solid var(--border);border-radius:20px;padding:2.5rem;backdrop-filter:blur(20px);box-shadow:0 25px 60px rgba(0,0,0,0.5)}
.lab-icon{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,var(--accent),#6366f1);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:1.8rem;box-shadow:0 8px 24px rgba(14,165,233,0.4)}
h1{text-align:center;font-size:1.5rem;font-weight:700;color:#f1f5f9;margin-bottom:.25rem}
.subtitle{text-align:center;color:#94a3b8;font-size:.875rem;margin-bottom:2rem}
.form-group{margin-bottom:1.25rem}
label{display:block;color:#cbd5e1;font-size:.8125rem;font-weight:500;margin-bottom:.5rem}
input{width:100%;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:10px;padding:.75rem 1rem;color:#f1f5f9;font-size:.9375rem;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(14,165,233,0.15)}
.btn-login{width:100%;background:linear-gradient(135deg,var(--accent),#6366f1);border:none;border-radius:10px;padding:.875rem;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;transition:opacity .2s,transform .15s;margin-top:.5rem}
.btn-login:hover{opacity:.9;transform:translateY(-1px)}
.alert-error{background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);border-radius:10px;padding:.875rem 1rem;color:#fca5a5;font-size:.875rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem}
.links{text-align:center;margin-top:1.5rem;font-size:.8125rem;color:#64748b}
.links a{color:var(--accent);text-decoration:none}
.links a:hover{text-decoration:underline}
.badge{display:inline-flex;align-items:center;gap:.35rem;background:rgba(14,165,233,0.1);border:1px solid rgba(14,165,233,0.2);border-radius:20px;padding:.25rem .75rem;font-size:.75rem;color:var(--accent);margin:0 auto .5rem;width:fit-content}
.badge-wrap{text-align:center;margin-bottom:1.25rem}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="lab-icon">🔬</div>
    <h1>Laboratory Portal</h1>
    <p class="subtitle">Sign in to manage test orders and upload results</p>

    <?php if ($error): ?>
    <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'affiliation_revoked'): ?>
    <div class="alert-error">⚠️ Your lab affiliation has been revoked. Please contact your hospital administrator.</div>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label for="email">Lab Email Address</label>
        <input type="email" id="email" name="email" placeholder="lab@hospital.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="username">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" name="login" class="btn-login">Sign In to Lab Portal</button>
    </form>

    <div class="links">
      <a href="<?= APP_URL ?>/lab/register.php">Register your laboratory</a>
      &nbsp;·&nbsp;
      <a href="<?= APP_URL ?>/role-selection.php">← Back</a>
    </div>
  </div>
</div>
</body>
</html>
