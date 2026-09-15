<?php
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../../app/services/OTPService.php';
require_once __DIR__ . '/../../app/services/AuditService.php';

initSession();

// Must have selected hospital in session
if (!isset($_SESSION['selected_hospital_id'])) {
    header('Location: ' . APP_URL . '/doctor/select-hospital.php');
    exit;
}

if (isLoggedIn('DOCTOR') && isset($_SESSION['hospital_id'])) {
    header('Location: ' . APP_URL . '/doctor/dashboard.php');
    exit;
}

$db = getDB();
$hospitalId   = (int)$_SESSION['selected_hospital_id'];
$hospitalName = $_SESSION['selected_hospital_name'] ?? '';

$error = '';

// Fetch hospital for display
$stmt = $db->prepare("SELECT * FROM hospitals WHERE id = ? AND verification_status = 'VERIFIED'");
$stmt->execute([$hospitalId]);
$hospital = $stmt->fetch();

if (!$hospital) {
    session_unset();
    header('Location: ' . APP_URL . '/doctor/select-hospital.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $regId    = trim($_POST['medical_registration_id'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (empty($regId) || empty($email)) {
        $error = 'Please enter both your Medical Registration ID and email.';
    } else {
        // Find doctor by registration ID or email
        $stmt = $db->prepare("
            SELECT d.*, u.email, u.phone, u.status AS user_status
            FROM doctors d
            JOIN users u ON u.id = d.user_id
            WHERE (d.medical_registration_id = ? OR u.email = ?)
            LIMIT 1
        ");
        $stmt->execute([$regId, $email]);
        $doctor = $stmt->fetch();

        if (!$doctor) {
            $error = 'No doctor found with the provided credentials. Please check your Medical Registration ID and email.';
            AuditService::log(AuditService::FAILED_LOGIN, [
                'metadata' => ['step' => 'verify', 'reg_id' => $regId, 'hospital_id' => $hospitalId, 'reason' => 'doctor_not_found']
            ]);
        } elseif ($doctor['verification_status'] !== 'VERIFIED') {
            AuditService::log(AuditService::FAILED_LOGIN, [
                'metadata' => ['step' => 'verify', 'doctor_id' => $doctor['id'], 'reason' => 'doctor_not_verified', 'status' => $doctor['verification_status']]
            ]);
            header('Location: ' . APP_URL . '/doctor/access-denied.php?reason=doctor_not_verified');
            exit;
        } elseif ($doctor['user_status'] === 'SUSPENDED' || $doctor['user_status'] === 'BLOCKED') {
            header('Location: ' . APP_URL . '/doctor/access-denied.php?reason=account_' . strtolower($doctor['user_status']));
            exit;
        } else {
            // CHECK AFFILIATION (THE CRITICAL STEP)
            $stmt = $db->prepare("
                SELECT dh.* FROM doctor_hospitals dh
                WHERE dh.doctor_id = ?
                  AND dh.hospital_id = ?
                  AND dh.status = 'APPROVED'
                LIMIT 1
            ");
            $stmt->execute([$doctor['id'], $hospitalId]);
            $affiliation = $stmt->fetch();

            if (!$affiliation) {
                AuditService::log(AuditService::FAILED_LOGIN, [
                    'metadata' => [
                        'step'       => 'affiliation_check',
                        'doctor_id'  => $doctor['id'],
                        'hospital_id'=> $hospitalId,
                        'reason'     => 'no_affiliation'
                    ]
                ]);

                // Get their approved hospitals for the "access denied" page
                $_SESSION['denied_doctor_id']   = $doctor['id'];
                $_SESSION['denied_doctor_name'] = $doctor['full_name'];
                header('Location: ' . APP_URL . '/doctor/access-denied.php?reason=no_affiliation');
                exit;
            }

            // ALL CHECKS PASSED — Send OTP
            $otpData = OTPService::generate(
                'DOCTOR_LOGIN',
                $doctor['user_id'],
                null,
                'EMAIL',
                $doctor['email']
            );

            $_SESSION['pending_doctor_id']      = $doctor['id'];
            $_SESSION['pending_doctor_user_id'] = $doctor['user_id'];
            $_SESSION['pending_doctor_name']    = $doctor['full_name'];
            $_SESSION['pending_doctor_email']   = $doctor['email'];
            $_SESSION['pending_otp_id']         = $otpData['id'];

            header('Location: ' . APP_URL . '/doctor/otp.php');
            exit;
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
  <title>Verify Identity — Doctor Login — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body style="background:var(--mc-bg);">

<div class="auth-flow-container" style="flex-direction:column;gap:var(--space-8);">
  <div style="text-align:center;">
    <a href="<?= APP_URL ?>/" class="navbar-logo" style="justify-content:center;">
      <div class="logo-icon" style="width:36px;height:36px;font-size:18px;">M</div>
      <span>MedCore</span>
    </a>
  </div>

  <div class="doctor-verify-card">
    <!-- Step indicator -->
    <div class="step-indicator mb-8">
      <div class="step-dot done" aria-label="Step 1 complete: Hospital selected">
        <i class="bi bi-check-lg" style="font-size:12px;"></i>
      </div>
      <div class="step-line done"></div>
      <div class="step-dot active" aria-label="Step 2: Verify Identity">2</div>
      <div class="step-line"></div>
      <div class="step-dot" aria-label="Step 3: OTP Verification">3</div>
    </div>

    <!-- Selected hospital display -->
    <div class="alert alert-info mb-5">
      <i class="bi bi-building-fill-check"></i>
      <div>
        <strong>Logging in via:</strong> <?= htmlspecialchars($hospitalName) ?>
        <div style="font-size:12px;margin-top:2px;">
          <a href="<?= APP_URL ?>/doctor/select-hospital.php" style="color:inherit;">Change hospital</a>
        </div>
      </div>
    </div>

    <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:var(--space-2);">
      <i class="bi bi-person-badge-fill text-blue"></i>
      Professional Verification
    </h1>
    <p style="font-size:14px;color:var(--mc-text-secondary);margin-bottom:var(--space-6);">
      Enter your Medical Registration ID and registered email. Your affiliation with <?= htmlspecialchars($hospitalName) ?> will be verified automatically.
    </p>

    <?php if ($error): ?>
    <div class="alert alert-error mb-4" role="alert">
      <i class="bi bi-exclamation-circle-fill"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="verify-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

      <div class="form-group">
        <label class="form-label" for="medical_registration_id">
          Medical Registration ID
          <span style="font-size:11px;font-weight:400;color:var(--mc-text-muted);">(BMDC Number)</span>
        </label>
        <input
          type="text"
          id="medical_registration_id"
          name="medical_registration_id"
          class="form-control"
          placeholder="e.g. BMDC-A-12345"
          value="<?= htmlspecialchars($_POST['medical_registration_id'] ?? '') ?>"
          required
          autocomplete="username"
          style="font-family:var(--font-mono);font-size:1rem;letter-spacing:1px;"
        >
        <div class="form-hint">As registered with BMDC National Medical Registry</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Registered Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          placeholder="your.professional@email.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          required
          autocomplete="email"
        >
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg" id="verify-submit-btn">
        <i class="bi bi-shield-fill-check"></i>
        Verify &amp; Continue
      </button>
    </form>

    <div style="margin-top:var(--space-6);padding-top:var(--space-5);border-top:1px solid var(--mc-border);">
      <div class="alert" style="background:var(--mc-bg-alt);border:none;font-size:12px;color:var(--mc-text-muted);">
        <i class="bi bi-info-circle-fill" style="color:var(--mc-blue);"></i>
        <span>All doctor login attempts are audit-logged with IP address, timestamp, and hospital context for compliance.</span>
      </div>
    </div>

    <p style="text-align:center;margin-top:var(--space-4);font-size:13px;color:var(--mc-text-muted);">
      <a href="<?= APP_URL ?>/doctor/select-hospital.php">← Select Different Hospital</a>
    </p>
  </div>
</div>

<?php if (APP_ENV === 'development'): ?>
<div style="position:fixed;bottom:20px;right:20px;background:var(--mc-amber-light);border:1px solid var(--mc-amber);border-radius:var(--radius-md);padding:12px 16px;font-size:12px;max-width:300px;z-index:9999;">
  <strong>🔧 Dev Credentials:</strong><br>
  Reg ID: <code>BMDC-A-12345</code><br>
  Email: <code>dr.rahman@medcore.local</code><br>
  <small>Must be affiliated with selected hospital</small>
</div>
<?php endif; ?>

<script>
document.getElementById('verify-form')?.addEventListener('submit', function() {
  const btn = document.getElementById('verify-submit-btn');
  btn.innerHTML = '<span class="spinner-sm"></span> Verifying...';
  btn.disabled = true;
});
</script>
</body>
</html>
