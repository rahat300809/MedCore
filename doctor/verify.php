<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/OTPService.php';
require_once __DIR__ . '/../app/services/AuditService.php';

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
        // Find doctor by registration ID and email
        $stmt = $db->prepare("
            SELECT d.*, u.email, u.phone, u.status AS user_status
            FROM doctors d
            JOIN users u ON u.id = d.user_id
            WHERE d.medical_registration_id = ? AND u.email = ?
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
                $_SESSION['denied_doctor_id']      = $doctor['id'];
                $_SESSION['denied_doctor_name']    = $doctor['full_name'];
                $_SESSION['denied_doctor_user_id'] = $doctor['user_id'];
                $_SESSION['denied_hospital_id']    = $hospitalId;
                $_SESSION['denied_hospital_name']  = $hospital['name'];
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

require_once __DIR__ . '/../app/views/media.php';
$csrf = generateCsrfToken();
$loginTitle = 'Doctor verify';
$loginTitleBn = 'পরিচয় যাচাই';
$loginImage = MC_IMG_DOCTOR;
$demoEmail = 'BMDC-A-12345';
$demoPass = 'dr.rahman@medcore.local';
$demoNote = $hospitalName . ' · OTP 1234';
$backUrl = APP_URL . '/doctor/select-hospital.php';
$registerUrl = '';

ob_start();
?>
<p style="margin:1rem 0 .55rem;font-size:.88rem;color:var(--muted)">Step 2 of 3 · <?= htmlspecialchars($hospitalName) ?></p>
<form method="POST" id="verify-form">
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <label class="mc-field">
    <span>BMDC registration ID</span>
    <input id="medical_registration_id" name="medical_registration_id" type="text" required
      value="<?= htmlspecialchars($_POST['medical_registration_id'] ?? '') ?>"
      placeholder="BMDC-A-12345" autocomplete="username" class="mc-mono">
  </label>
  <label class="mc-field">
    <span>Registered email</span>
    <input id="email" name="email" type="email" required
      value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
      placeholder="dr.rahman@medcore.local" autocomplete="email">
  </label>
  <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg mc-btn-block" style="margin-top:1.1rem">Verify &amp; continue</button>
  <button type="button" class="mc-btn mc-btn-secondary mc-btn-lg mc-btn-block" style="margin-top:.55rem" onclick="fillDoctorDemo()">Fill demo credentials</button>
</form>
<script>
function fillDoctorDemo(){
  const r=document.getElementById('medical_registration_id');
  const e=document.getElementById('email');
  if(r) r.value='BMDC-A-12345';
  if(e) e.value='dr.rahman@medcore.local';
}
</script>
<?php
$loginFormHtml = ob_get_clean();
require __DIR__ . '/../app/views/auth/login_shell.php';
