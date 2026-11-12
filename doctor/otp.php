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

$db = getDB();
$stmt = $db->prepare("SELECT otp_display, expires_at, attempt_count, max_attempts FROM otp_verifications WHERE id = ?");
$stmt->execute([$otpId]);
$otpRec = $stmt->fetch();
$demoCode = $otpRec['otp_display'] ?? '1234';
$digitCount = max(4, min(6, strlen($demoCode)));

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
        $affilRow = $stmt->fetch();
        if (!$affilRow) {
            session_unset();
            header('Location: ' . APP_URL . '/doctor/access-denied.php?reason=no_affiliation');
            exit;
        }

        // Set doctor session
        $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$_SESSION['pending_doctor_user_id']]);

        session_regenerate_id(true);
        $_SESSION['user_id']       = $_SESSION['pending_doctor_user_id'];
        $_SESSION['role']          = 'DOCTOR';
        $_SESSION['doctor_id']     = $_SESSION['pending_doctor_id'];
        $_SESSION['hospital_id']   = $_SESSION['selected_hospital_id'];
        $_SESSION['hospital_name'] = $_SESSION['selected_hospital_name'];
        $_SESSION['department_id'] = $affilRow['department_id'] ?? null;
        $_SESSION['name']          = $doctorName;

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

require_once __DIR__ . '/../app/views/media.php';
$csrf = generateCsrfToken();
$loginTitle = 'Doctor OTP';
$loginTitleBn = 'OTP যাচাই';
$loginImage = MC_IMG_DOCTOR;
$demoEmail = $doctorEmail;
$demoPass = 'OTP ' . $demoCode;
$demoNote = ($_SESSION['selected_hospital_name'] ?? '') . ' · ' . $doctorName;
$backUrl = APP_URL . '/doctor/verify.php';
$registerUrl = '';

ob_start();
?>
<p style="margin:1rem 0 .55rem;font-size:.88rem;color:var(--muted)">Step 3 of 3 · Code sent to <?= htmlspecialchars($doctorEmail) ?></p>
<form method="POST" id="otp-form">
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <input type="hidden" name="otp" id="otp-combined">
  <label class="mc-field">
    <span>Enter OTP</span>
    <div style="display:flex;gap:.45rem;margin-top:.35rem">
      <?php for ($i = 1; $i <= $digitCount; $i++): ?>
        <input type="text" maxlength="1" class="otp-input" name="otp_digits[]"
          inputmode="numeric" pattern="[0-9]" aria-label="OTP digit <?= $i ?>"
          style="width:2.6rem;text-align:center;font:inherit;font-weight:800;padding:.7rem .2rem;border:1px solid var(--line);border-radius:10px;background:#f9fafb">
      <?php endfor; ?>
    </div>
  </label>
  <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg mc-btn-block" style="margin-top:1.1rem">Verify &amp; enter portal</button>
  <button type="button" class="mc-btn mc-btn-secondary mc-btn-lg mc-btn-block" style="margin-top:.55rem" onclick="autofillDemoOtp('<?= htmlspecialchars($demoCode, ENT_QUOTES) ?>')">Fill demo OTP (<?= htmlspecialchars($demoCode) ?>)</button>
</form>
<script>
function autofillDemoOtp(code){
  const inputs=[...document.querySelectorAll('.otp-input')];
  [...code].forEach((ch,i)=>{ if(inputs[i]) inputs[i].value=ch; });
  const h=document.getElementById('otp-combined'); if(h) h.value=code;
}
document.getElementById('otp-form')?.addEventListener('submit',function(){
  document.getElementById('otp-combined').value=[...document.querySelectorAll('.otp-input')].map(i=>i.value).join('');
});
document.querySelectorAll('.otp-input').forEach((input,idx,arr)=>{
  input.addEventListener('input',()=>{ if(input.value && arr[idx+1]) arr[idx+1].focus(); });
});
</script>
<?php
$loginFormHtml = ob_get_clean();
// login_shell already prints $error when set
require __DIR__ . '/../app/views/auth/login_shell.php';
