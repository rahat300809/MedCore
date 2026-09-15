<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/OTPService.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

if (isLoggedIn('PATIENT')) {
    header('Location: ' . APP_URL . '/patient/dashboard.php');
    exit;
}

$errors = [];
$step   = $_SESSION['reg_step'] ?? 1; // Step 1: Form, Step 2: OTP

// Step 2: OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    validateCsrf();
    $otpId = (int)($_SESSION['reg_otp_id'] ?? 0);
    $otp   = trim(implode('', $_POST['otp_digits'] ?? []) ?: ($_POST['otp'] ?? ''));

    $result = OTPService::verify($otpId, $otp);

    if ($result['success']) {
        // Activate the user account
        $db = getDB();
        $db->prepare("UPDATE users SET status='ACTIVE', email_verified=1 WHERE id=?")->execute([$_SESSION['reg_user_id']]);

        // Set session
        session_regenerate_id(true);
        $stmt = $db->prepare("SELECT p.* FROM patients p WHERE p.user_id = ?");
        $stmt->execute([$_SESSION['reg_user_id']]);
        $patient = $stmt->fetch();

        $_SESSION['user_id']     = $_SESSION['reg_user_id'];
        $_SESSION['role']        = 'PATIENT';
        $_SESSION['patient_id']  = $patient['id'];
        $_SESSION['name']        = $patient['full_name'];
        $_SESSION['patient_uid'] = $patient['patient_uid'];
        $_SESSION['email']       = $_SESSION['reg_email'];

        // Clear registration session vars
        unset($_SESSION['reg_step'], $_SESSION['reg_user_id'], $_SESSION['reg_otp_id'], $_SESSION['reg_email']);

        AuditService::log(AuditService::LOGIN, [
            'user_id' => $_SESSION['user_id'],
            'patient_id' => $_SESSION['patient_id'],
            'metadata' => ['method' => 'registration_otp']
        ]);

        header('Location: ' . APP_URL . '/patient/dashboard.php?welcome=1');
        exit;
    } else {
        $errors['otp'] = $result['error'];
    }
}

// Step 1: Registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    validateCsrf();

    $fullName    = trim($_POST['full_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $dob         = $_POST['date_of_birth'] ?? '';
    $gender      = $_POST['gender'] ?? '';
    $bloodGroup  = $_POST['blood_group'] ?? 'UNKNOWN';
    $nid         = trim($_POST['nid'] ?? '');

    // Validation
    if (strlen($fullName) < 3) $errors['full_name'] = 'Full name must be at least 3 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email address.';
    if (strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors['confirm'] = 'Passwords do not match.';
    if (empty($dob)) $errors['dob'] = 'Date of birth is required.';
    if (!in_array($gender, ['MALE','FEMALE','OTHER'])) $errors['gender'] = 'Please select gender.';
    if (strlen($nid) < 10) $errors['nid'] = 'NID must be at least 10 digits.';

    if (empty($errors)) {
        $db = getDB();

        // Check email uniqueness
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered.';
        }

        // Check NID uniqueness
        $nidHash = hash('sha256', $nid);
        $stmt = $db->prepare("SELECT id FROM patients WHERE nid_lookup_hash = ?");
        $stmt->execute([$nidHash]);
        if ($stmt->fetch()) {
            $errors['nid'] = 'This NID is already registered.';
        }
    }

    if (empty($errors)) {
        $db = getDB();
        $db->beginTransaction();

        try {
            // Generate patient UID
            $stmt = $db->query("SELECT MAX(id) + 1 AS next FROM patients");
            $next = max(1, (int)($stmt->fetch()['next'] ?? 1));
            $patientUid = 'PT-' . str_pad($next, 6, '0', STR_PAD_LEFT);

            // Create user
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT INTO users (role, email, phone, password_hash, status)
                VALUES ('PATIENT', ?, ?, ?, 'PENDING')
            ");
            $stmt->execute([$email, $phone, $hash]);
            $userId = (int)$db->lastInsertId();

            // Create patient
            $nidHash    = hash('sha256', $nid);
            $nidLast4   = substr(preg_replace('/\D/', '', $nid), -4);
            $nidEncrypted = base64_encode($nid); // TODO: Use AES-256 in production

            $stmt = $db->prepare("
                INSERT INTO patients
                    (user_id, patient_uid, nid_encrypted, nid_lookup_hash, nid_last4,
                     full_name, date_of_birth, gender, blood_group)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $patientUid, $nidEncrypted, $nidHash, $nidLast4,
                            $fullName, $dob, $gender, $bloodGroup]);

            $db->commit();

            // Generate OTP
            $otpData = OTPService::generate('PATIENT_REGISTRATION', $userId, null, 'EMAIL', $email);

            $_SESSION['reg_step']    = 2;
            $_SESSION['reg_user_id'] = $userId;
            $_SESSION['reg_otp_id']  = $otpData['id'];
            $_SESSION['reg_email']   = $email;

            $step = 2;

        } catch (Exception $e) {
            $db->rollBack();
            $errors['general'] = 'Registration failed. Please try again.';
            error_log('Registration error: ' . $e->getMessage());
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
  <title><?= $step === 2 ? 'Verify Email' : 'Create Patient Account' ?> — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    body { background: var(--mc-bg); }
    .register-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--space-8) var(--space-4);
    }
    .register-card {
      background: var(--mc-white);
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-xl);
      padding: var(--space-10);
      width: 100%;
      max-width: 540px;
      box-shadow: var(--shadow-lg);
    }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }
    @media (max-width: 480px) { .form-row { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="register-container">
  <div class="register-card">
    <!-- Header -->
    <div class="auth-logo">
      <a href="<?= APP_URL ?>/" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--mc-text);">
        <div class="logo-icon">M</div>
        <span style="font-weight:800;">MedCore</span>
      </a>
    </div>

    <?php if ($step === 2): ?>
    <!-- ===== OTP VERIFICATION ===== -->
    <div style="text-align:center;margin-bottom:var(--space-6);">
      <div class="pillar-icon pillar-icon-green" style="margin:0 auto var(--space-4);">
        <i class="bi bi-envelope-fill"></i>
      </div>
      <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:var(--space-2);">Verify Your Email</h1>
      <p style="font-size:14px;color:var(--mc-text-secondary);">
        We've sent a 6-digit verification code to<br>
        <strong><?= htmlspecialchars($_SESSION['reg_email']) ?></strong>
      </p>
    </div>

    <?php if (isset($errors['otp'])): ?>
      <div class="alert alert-error mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['otp']) ?></div>
    <?php endif; ?>

    <form method="POST" id="otp-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="verify_otp" value="1">
      <input type="hidden" name="otp" id="otp-combined">

      <div class="otp-inputs mb-6" style="margin-bottom:var(--space-6);">
        <input type="text" maxlength="1" class="otp-input" name="otp_digits[]" inputmode="numeric" pattern="[0-9]" aria-label="OTP digit 1">
        <input type="text" maxlength="1" class="otp-input" name="otp_digits[]" inputmode="numeric" pattern="[0-9]" aria-label="OTP digit 2">
        <input type="text" maxlength="1" class="otp-input" name="otp_digits[]" inputmode="numeric" pattern="[0-9]" aria-label="OTP digit 3">
        <input type="text" maxlength="1" class="otp-input" name="otp_digits[]" inputmode="numeric" pattern="[0-9]" aria-label="OTP digit 4">
        <input type="text" maxlength="1" class="otp-input" name="otp_digits[]" inputmode="numeric" pattern="[0-9]" aria-label="OTP digit 5">
        <input type="text" maxlength="1" class="otp-input" name="otp_digits[]" inputmode="numeric" pattern="[0-9]" aria-label="OTP digit 6">
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg" id="verify-btn">
        <i class="bi bi-check-circle-fill"></i> Verify & Create Account
      </button>
    </form>

    <?php
    // DEV MODE: Show OTP
    if (APP_ENV === 'development' && isset($_SESSION['reg_otp_id'])):
        $db = getDB();
        $stmt = $db->prepare("SELECT otp_display, expires_at FROM otp_verifications WHERE id = ?");
        $stmt->execute([$_SESSION['reg_otp_id']]);
        $otpRecord = $stmt->fetch();
    ?>
    <div class="alert alert-warning" style="margin-top:var(--space-4);font-size:13px;">
      <i class="bi bi-bug-fill"></i>
      <strong>Dev Mode OTP:</strong> <code style="font-size:1.1rem;font-weight:800;"><?= $otpRecord['otp_display'] ?? 'N/A' ?></code>
      &nbsp;| Expires: <?= $otpRecord['expires_at'] ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- ===== REGISTRATION FORM ===== -->
    <h1 style="font-size:1.5rem;font-weight:800;margin-bottom:var(--space-2);">Create Your Health Account</h1>
    <p style="font-size:14px;color:var(--mc-text-secondary);margin-bottom:var(--space-6);">Free for patients. Lifelong medical record ownership.</p>

    <?php if (isset($errors['general'])): ?>
      <div class="alert alert-error mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" id="register-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="register" value="1">

      <div class="form-group">
        <label class="form-label" for="full_name">Full Legal Name</label>
        <input type="text" id="full_name" name="full_name" class="form-control"
          placeholder="As per NID" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
        <?php if (isset($errors['full_name'])): ?><div class="form-error"><?= $errors['full_name'] ?></div><?php endif; ?>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="date_of_birth">Date of Birth</label>
          <input type="date" id="date_of_birth" name="date_of_birth" class="form-control"
            value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>" required
            max="<?= date('Y-m-d', strtotime('-1 year')) ?>">
          <?php if (isset($errors['dob'])): ?><div class="form-error"><?= $errors['dob'] ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label" for="gender">Gender</label>
          <select id="gender" name="gender" class="form-control" required>
            <option value="">Select gender</option>
            <option value="MALE" <?= ($_POST['gender'] ?? '') === 'MALE' ? 'selected' : '' ?>>Male</option>
            <option value="FEMALE" <?= ($_POST['gender'] ?? '') === 'FEMALE' ? 'selected' : '' ?>>Female</option>
            <option value="OTHER" <?= ($_POST['gender'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Other</option>
          </select>
          <?php if (isset($errors['gender'])): ?><div class="form-error"><?= $errors['gender'] ?></div><?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="blood_group">Blood Group</label>
          <select id="blood_group" name="blood_group" class="form-control">
            <option value="UNKNOWN">Unknown</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
              <option value="<?= $bg ?>" <?= ($_POST['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="nid">National ID (NID)</label>
          <input type="text" id="nid" name="nid" class="form-control"
            placeholder="13 or 17 digits"
            value="<?= htmlspecialchars($_POST['nid'] ?? '') ?>"
            pattern="[0-9]{10,17}" required maxlength="17"
            aria-describedby="nid-hint">
          <div class="form-hint" id="nid-hint">Used for identity only. Never grants record access.</div>
          <?php if (isset($errors['nid'])): ?><div class="form-error"><?= $errors['nid'] ?></div><?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control"
          placeholder="you@example.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
        <?php if (isset($errors['email'])): ?><div class="form-error"><?= $errors['email'] ?></div><?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="phone">Phone Number (Optional)</label>
        <input type="tel" id="phone" name="phone" class="form-control"
          placeholder="017XXXXXXXX"
          value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control"
            placeholder="Min. 8 characters" required autocomplete="new-password"
            minlength="8">
          <?php if (isset($errors['password'])): ?><div class="form-error"><?= $errors['password'] ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control"
            placeholder="Repeat password" required autocomplete="new-password">
          <?php if (isset($errors['confirm'])): ?><div class="form-error"><?= $errors['confirm'] ?></div><?php endif; ?>
        </div>
      </div>

      <div style="margin-bottom:var(--space-6);">
        <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;cursor:pointer;">
          <input type="checkbox" required style="margin-top:2px;"> 
          <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>. I understand my medical data is protected under HIPAA and local laws.</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg">
        <i class="bi bi-person-plus-fill"></i>
        Create Account
      </button>
    </form>

    <div style="text-align:center;margin-top:var(--space-5);font-size:13px;color:var(--mc-text-muted);">
      Already have an account? <a href="<?= APP_URL ?>/patient/login.php" style="font-weight:600;">Log In</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
// OTP form combine digits before submit
document.getElementById('otp-form')?.addEventListener('submit', function() {
  const digits = [...document.querySelectorAll('.otp-input')].map(i => i.value).join('');
  document.getElementById('otp-combined').value = digits;
});
</script>
</body>
</html>
