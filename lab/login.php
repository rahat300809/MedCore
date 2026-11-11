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
:root{--accent:#0284c7;--accent-dark:#0369a1;--border:#e2e8f0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8fafc;font-family:'Plus Jakarta Sans','Inter',-apple-system,sans-serif;margin:0;padding:20px}
.login-wrap{width:100%;max-width:440px}
.login-card{background:#ffffff;border:1.5px solid #e2e8f0;border-radius:24px;padding:2.5rem 2rem;box-shadow:0 12px 35px rgba(15,23,42,0.06)}
.lab-icon{width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#0a2540,#0284c7);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:1.8rem;color:#ffffff;box-shadow:0 6px 20px rgba(2,132,199,0.25)}
h1{text-align:center;font-size:1.55rem;font-weight:900;color:#0a2540;margin:0 0 .35rem}
.subtitle{text-align:center;color:#64748b;font-size:.875rem;margin:0 0 1.75rem}
.form-group{margin-bottom:1.25rem}
label{display:block;color:#334155;font-size:.8125rem;font-weight:700;margin-bottom:.4rem}
input{width:100%;background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:.75rem 1rem;color:#0f172a;font-size:.9375rem;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(2,132,199,0.15)}
.btn-login{width:100%;background:linear-gradient(135deg,#0a2540,#0284c7);border:none;border-radius:10px;padding:.9rem;color:#fff;font-size:1rem;font-weight:800;cursor:pointer;transition:all .15s;margin-top:.5rem;box-shadow:0 4px 14px rgba(10,37,64,0.2)}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(10,37,64,0.3)}
.alert-error{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:.875rem 1rem;color:#991b1b;font-size:.875rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem;font-weight:600}
.links{text-align:center;margin-top:1.5rem;font-size:.82rem;color:#64748b}
.links a{color:var(--accent);text-decoration:none;font-weight:700}
.links a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="lab-icon">🔬</div>
    <h1>Laboratory Portal</h1>
    <p class="subtitle">ল্যাব টেস্ট অর্ডার পরিচালনা ও রেজাল্ট আপলোড পোর্টাল</p>

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

      <!-- Demo Credentials Card -->
      <div style="background:#FFFBEB;border:1.5px solid #FDE68A;border-radius:14px;padding:14px 16px;margin-top:20px;text-align:left;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-size:12px;font-weight:800;color:#B45309;text-transform:uppercase;letter-spacing:0.5px;display:flex;align-items:center;gap:5px;">
            <i class="bi bi-key-fill"></i> পরীক্ষামূলক ডায়াগনস্টিক ল্যাব ডেমো আইডি
          </span>
          <span style="font-size:10px;font-weight:800;background:#FEF3C7;color:#92400E;padding:2px 6px;border-radius:4px;">DEMO</span>
        </div>
        <div style="font-size:12px;color:#334155;line-height:1.6;margin-bottom:10px;">
          <div>ল্যাব ইমেইল: <strong style="color:#0A2540;font-family:monospace;">lab@apexdiagnostics.com</strong></div>
          <div>পাসওয়ার্ড: <strong style="color:#0A2540;font-family:monospace;">Demo123!</strong></div>
          <div style="font-size:11px;color:#64748B;">ল্যাব: Apex Diagnostics Ltd. (ABC General Hospital Affiliated)</div>
        </div>
        <button type="button" onclick="fillLabDemo()" style="width:100%;background:#D97706;color:#fff;border:none;border-radius:8px;padding:8px 12px;font-size:12.5px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 2px 6px rgba(217,119,6,0.25);">
          <i class="bi bi-lightning-charge-fill"></i> ⚡ ১-ক্লিকে ল্যাব তথ্য পূরণ করুন (Auto-Fill)
        </button>
      </div>
    </form>

    <div class="links">
      <a href="<?= APP_URL ?>/lab/register.php">Register your laboratory</a>
      &nbsp;·&nbsp;
      <a href="<?= APP_URL ?>/role-selection.php">← Back to Portals</a>
    </div>
  </div>
</div>
<script>
function fillLabDemo() {
  const emailInput = document.getElementById('email');
  const passInput = document.getElementById('password');
  if (emailInput && passInput) {
    emailInput.value = 'lab@apexdiagnostics.com';
    passInput.value = 'Demo123!';
  }
}
</script>
</body>
</html>
