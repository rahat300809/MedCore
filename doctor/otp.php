<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/OTPService.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

if (!isset($_SESSION['pending_doctor_id'], $_SESSION['pending_otp_id'])) {
    header('Location: ' . APP_URL . '/doctor/select-hospital.php');
    exit;
}

$error = '';
$doctorName  = $_SESSION['pending_doctor_name'] ?? '';
$doctorEmail = $_SESSION['pending_doctor_email'] ?? '';
$otpId       = (int)$_SESSION['pending_otp_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $otp = trim(implode('', $_POST['otp_digits'] ?? []) ?: ($_POST['otp'] ?? ''));
    $result = OTPService::verify($otpId, $otp);

    if ($result['success']) {
        $db = getDB();

        // Final security check — re-verify affiliation still valid
        $stmt = $db->prepare("
            SELECT dh.* FROM doctor_hospitals dh
            WHERE dh.doctor_id = ? AND dh.hospital_id = ?
              AND dh.status = 'APPROVED'
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['pending_doctor_id'], $_SESSION['selected_hospital_id']]);
        if (!$stmt->fetch()) {
            session_unset();
            header('Location: ' . APP_URL . '/doctor/access-denied.php?reason=no_affiliation');
            exit;
        }

        // Set doctor session
        $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$_SESSION['pending_doctor_user_id']]);

        session_regenerate_id(true);
        $_SESSION['user_id']     = $_SESSION['pending_doctor_user_id'];
        $_SESSION['role']        = 'DOCTOR';
        $_SESSION['doctor_id']   = $_SESSION['pending_doctor_id'];
        $_SESSION['hospital_id'] = $_SESSION['selected_hospital_id'];
        $_SESSION['hospital_name'] = $_SESSION['selected_hospital_name'];
        $_SESSION['name']        = $doctorName;

        // Clean up
        unset($_SESSION['pending_doctor_id'], $_SESSION['pending_doctor_user_id'],
              $_SESSION['pending_doctor_name'], $_SESSION['pending_doctor_email'],
              $_SESSION['pending_otp_id'], $_SESSION['selected_hospital_id'], $_SESSION['selected_hospital_name']);

        AuditService::log(AuditService::LOGIN, [
            'user_id'    => $_SESSION['user_id'],
            'doctor_id'  => $_SESSION['doctor_id'],
            'hospital_id'=> $_SESSION['hospital_id'],
            'metadata'   => ['method' => 'doctor_otp', 'hospital' => $_SESSION['hospital_name']],
        ]);

        header('Location: ' . APP_URL . '/doctor/dashboard.php');
        exit;
    } else {
        $error = $result['error'];
        AuditService::log(AuditService::FAILED_OTP, [
            'doctor_id'   => $_SESSION['pending_doctor_id'],
            'hospital_id' => $_SESSION['selected_hospital_id'],
            'metadata'    => ['reason' => $error],
        ]);
    }
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OTP Verification — Doctor Login — MedCore</title>
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
    <div class="step-indicator mb-8">
      <div class="step-dot done"><i class="bi bi-check-lg" style="font-size:12px;"></i></div>
      <div class="step-line done"></div>
      <div class="step-dot done"><i class="bi bi-check-lg" style="font-size:12px;"></i></div>
      <div class="step-line done"></div>
      <div class="step-dot active" aria-label="Step 3: OTP">3</div>
    </div>

    <div style="text-align:center;margin-bottom:var(--space-6);">
      <div class="pillar-icon pillar-icon-blue" style="margin:0 auto var(--space-4);">
        <i class="bi bi-shield-lock-fill"></i>
      </div>
      <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:var(--space-2);">OTP Verification</h1>
      <p style="font-size:14px;color:var(--mc-text-secondary);">
        A 6-digit code was sent to<br><strong><?= htmlspecialchars($doctorEmail) ?></strong>
      </p>
      <p style="font-size:12px;color:var(--mc-text-muted);margin-top:var(--space-2);">
        Logging into: <strong><?= htmlspecialchars($_SESSION['selected_hospital_name'] ?? '') ?></strong>
      </p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error mb-4" role="alert">
      <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="otp-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="otp" id="otp-combined">

      <div class="otp-inputs mb-6" style="margin-bottom:var(--space-6);">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <input type="text" maxlength="1" class="otp-input" name="otp_digits[]"
          inputmode="numeric" pattern="[0-9]" aria-label="OTP digit <?= $i ?>">
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg" id="otp-submit-btn">
        <i class="bi bi-shield-fill-check"></i> Verify &amp; Enter Portal
      </button>
    </form>

    <?php
    if (APP_ENV === 'development') {
        $db = getDB();
        $stmt = $db->prepare("SELECT otp_display, expires_at, attempt_count, max_attempts FROM otp_verifications WHERE id = ?");
        $stmt->execute([$otpId]);
        $otpRec = $stmt->fetch();
    ?>
    <div class="alert alert-warning" style="margin-top:var(--space-4);font-size:13px;">
      <i class="bi bi-bug-fill"></i>
      <strong>Dev OTP:</strong> <code style="font-size:1.2rem;font-weight:800;letter-spacing:4px;"><?= $otpRec['otp_display'] ?? 'N/A' ?></code>
      &nbsp;| Expires: <?= $otpRec['expires_at'] ?>
      &nbsp;| Attempts: <?= $otpRec['attempt_count'] ?>/<?= $otpRec['max_attempts'] ?>
    </div>
    <?php } ?>

    <p style="text-align:center;margin-top:var(--space-5);font-size:13px;color:var(--mc-text-muted);">
      <a href="<?= APP_URL ?>/doctor/verify.php">← Re-enter credentials</a>
    </p>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
document.getElementById('otp-form')?.addEventListener('submit', function() {
  const digits = [...document.querySelectorAll('.otp-input')].map(i => i.value).join('');
  document.getElementById('otp-combined').value = digits;
  const btn = document.getElementById('otp-submit-btn');
  btn.innerHTML = '<span class="spinner-sm"></span> Verifying...';
  btn.disabled = true;
});
</script>
</body>
</html>
