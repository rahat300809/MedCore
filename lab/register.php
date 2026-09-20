<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    validateCsrf();

    $labName    = trim($_POST['lab_name'] ?? '');
    $regNum     = trim($_POST['registration_number'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $services   = trim($_POST['services'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if (empty($labName) || empty($regNum) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();

        // Check email uniqueness
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Check reg number uniqueness
            $stmtReg = $db->prepare("SELECT id FROM laboratories WHERE registration_number = ?");
            $stmtReg->execute([$regNum]);
            if ($stmtReg->fetch()) {
                $error = 'A laboratory with this registration number already exists.';
            } else {
                $db->beginTransaction();
                try {
                    // Generate unique lab UID
                    $stmtMax = $db->query("SELECT MAX(CAST(SUBSTRING(lab_uid,5) AS UNSIGNED)) AS mx FROM laboratories");
                    $maxRow   = $stmtMax->fetch();
                    $labUid   = 'LAB-' . str_pad(($maxRow['mx'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);

                    // Create user
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->prepare("
                        INSERT INTO users (role, email, phone, password_hash, status, email_verified)
                        VALUES ('LABORATORY', ?, ?, ?, 'ACTIVE', 0)
                    ")->execute([$email, $phone, $hash]);
                    $userId = $db->lastInsertId();

                    // Create lab record
                    $db->prepare("
                        INSERT INTO laboratories
                            (user_id, lab_uid, name, registration_number, contact_phone,
                             contact_email, address, city, services, verification_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
                    ")->execute([$userId, $labUid, $labName, $regNum, $phone, $email, $address, $city, $services]);
                    $labId = $db->lastInsertId();

                    AuditService::log(AuditService::LAB_REGISTERED, [
                        'user_id'  => $userId,
                        'metadata' => ['lab_uid' => $labUid, 'lab_name' => $labName],
                    ]);

                    $db->commit();
                    $success = "Laboratory registered successfully! Your Lab UID is <strong>{$labUid}</strong>. Please wait for admin verification before you can log in.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
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
<title>Register Laboratory — MedCore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/portal.css">
<style>
:root{--accent:#0ea5e9;--border:rgba(255,255,255,0.12)}
body{min-height:100vh;background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);font-family:'Inter',sans-serif;color:#f1f5f9;padding:2rem 1rem}
.page-wrap{max-width:680px;margin:0 auto}
.card{background:rgba(15,23,42,0.85);border:1px solid var(--border);border-radius:20px;padding:2.5rem;backdrop-filter:blur(20px);box-shadow:0 25px 60px rgba(0,0,0,0.5)}
.header{text-align:center;margin-bottom:2rem}
.lab-icon{width:60px;height:60px;border-radius:15px;background:linear-gradient(135deg,var(--accent),#6366f1);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.6rem;box-shadow:0 8px 24px rgba(14,165,233,0.35)}
h1{font-size:1.6rem;font-weight:700;margin-bottom:.25rem}
.subtitle{color:#94a3b8;font-size:.875rem}
.section-title{font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--accent);margin:1.5rem 0 1rem;padding-bottom:.5rem;border-bottom:1px solid var(--border)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{margin-bottom:1rem}
label{display:block;color:#cbd5e1;font-size:.8125rem;font-weight:500;margin-bottom:.4rem}
input,textarea{width:100%;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:10px;padding:.7rem 1rem;color:#f1f5f9;font-size:.9rem;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;font-family:inherit}
textarea{resize:vertical;min-height:80px}
input:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(14,165,233,0.15)}
.btn{width:100%;background:linear-gradient(135deg,var(--accent),#6366f1);border:none;border-radius:10px;padding:.9rem;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;margin-top:.75rem;transition:opacity .2s,transform .15s}
.btn:hover{opacity:.9;transform:translateY(-1px)}
.alert{border-radius:10px;padding:.875rem 1rem;font-size:.875rem;margin-bottom:1.25rem;display:flex;align-items:flex-start;gap:.5rem}
.alert-error{background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);color:#fca5a5}
.alert-success{background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.25);color:#86efac}
.back-link{text-align:center;margin-top:1.25rem;font-size:.8125rem;color:#64748b}
.back-link a{color:var(--accent);text-decoration:none}
@media(max-width:560px){.grid-2{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="page-wrap">
  <div class="card">
    <div class="header">
      <div class="lab-icon">🔬</div>
      <h1>Register Your Laboratory</h1>
      <p class="subtitle">Create a laboratory account to process test orders from MedCore hospitals</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= $success ?></div>
    <div class="back-link"><a href="<?= APP_URL ?>/lab/login.php">← Go to Login</a></div>
    <?php else: ?>

    <form method="POST">
      <?= csrfField() ?>

      <div class="section-title">Laboratory Information</div>
      <div class="grid-2">
        <div class="form-group">
          <label>Laboratory Name *</label>
          <input type="text" name="lab_name" placeholder="City Diagnostics Centre" value="<?= htmlspecialchars($_POST['lab_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Registration / License Number *</label>
          <input type="text" name="registration_number" placeholder="LAB-2026-DHAKA-0001" value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>City</label>
          <input type="text" name="city" placeholder="Dhaka" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Contact Phone</label>
          <input type="tel" name="phone" placeholder="+880 1XXX-XXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Address</label>
        <textarea name="address" placeholder="Full laboratory address"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Services Offered <span style="color:#64748b">(comma separated)</span></label>
        <input type="text" name="services" placeholder="CBC, Blood Sugar, X-Ray, ECG, Urine R/E" value="<?= htmlspecialchars($_POST['services'] ?? '') ?>">
      </div>

      <div class="section-title">Account Credentials</div>
      <div class="grid-2">
        <div class="form-group">
          <label>Email Address *</label>
          <input type="email" name="email" placeholder="lab@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" placeholder="Min. 8 characters" required>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Confirm Password *</label>
          <input type="password" name="confirm_password" placeholder="Re-enter password" required>
        </div>
      </div>

      <button type="submit" name="register" class="btn">Register Laboratory</button>
    </form>

    <div class="back-link">
      Already registered? <a href="<?= APP_URL ?>/lab/login.php">Login here</a>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
