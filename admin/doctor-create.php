<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $fullName      = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $password      = $_POST['password'] ?? 'Demo123!';
    $regId         = trim($_POST['medical_registration_id'] ?? '');
    $specialization= trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $designation   = trim($_POST['designation'] ?? 'Consultant');
    $experience    = (int)($_POST['experience_years'] ?? 0);
    $verifyNow     = isset($_POST['verify_now']);

    if (empty($fullName) || empty($email) || empty($regId)) {
        $error = 'Full Name, Email, and BMDC Medical Registration ID are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email or regId exists
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            $error = 'A user with this email address already exists.';
        } else {
            $stmtRegCheck = $db->prepare("SELECT id FROM doctors WHERE medical_registration_id = ?");
            $stmtRegCheck->execute([$regId]);
            if ($stmtRegCheck->fetch()) {
                $error = 'A doctor with this Medical Registration ID already exists.';
            } else {
                try {
                    $db->beginTransaction();

                    // Generate Doctor UID
                    $lastId = (int)$db->query("SELECT MAX(id) FROM doctors")->fetchColumn() + 1;
                    $doctorUid = sprintf('DR-%06d', $lastId);

                    // Insert User
                    $passHash = password_hash($password, PASSWORD_BCRYPT);
                    $userStatus = $verifyNow ? 'ACTIVE' : 'PENDING';
                    $stmtUser = $db->prepare("
                        INSERT INTO users (role, email, phone, password_hash, status, email_verified, phone_verified)
                        VALUES ('DOCTOR', ?, ?, ?, ?, 1, 1)
                    ");
                    $stmtUser->execute([$email, $phone ?: null, $passHash, $userStatus]);
                    $userId = (int)$db->lastInsertId();

                    // Insert Doctor
                    $verStatus  = $verifyNow ? 'VERIFIED' : 'PENDING';
                    $verifiedBy = $verifyNow ? $_SESSION['user_id'] : null;
                    $verifiedAt = $verifyNow ? date('Y-m-d H:i:s') : null;

                    $stmtDoc = $db->prepare("
                        INSERT INTO doctors
                            (user_id, doctor_uid, medical_registration_id, full_name, specialization,
                             qualification, designation, experience_years, verification_status, verified_by, verified_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtDoc->execute([
                        $userId, $doctorUid, $regId, $fullName, $specialization ?: null,
                        $qualification ?: null, $designation ?: null, $experience,
                        $verStatus, $verifiedBy, $verifiedAt
                    ]);
                    $doctorId = (int)$db->lastInsertId();

                    $db->commit();

                    AuditService::log('DOCTOR_CREATED_BY_ADMIN', [
                        'user_id'   => $_SESSION['user_id'],
                        'doctor_id' => $doctorId,
                        'metadata'  => ['doctor_uid' => $doctorUid, 'reg_id' => $regId, 'verified' => $verifyNow]
                    ]);

                    header('Location: ' . APP_URL . '/admin/doctor-detail.php?id=' . $doctorId . '&created=1');
                    exit;

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Register & Approve New Doctor';
$portalSubtitle = 'Doctor registration desk & immediate BMDC verification';
$portalNav = mc_admin_nav('doctors');
$portalTopAction = '<a href="' . APP_URL . '/admin/doctors.php" class="mc-btn mc-btn-secondary mc-btn-sm">← Back to Doctors</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($error): ?>
  <div class="mc-alert mc-alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="mc-panel" style="max-width:840px;">
  <div class="mc-panel-h">
    <h2>Doctor License &amp; Profile Information</h2>
  </div>

  <form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <div class="mc-form-grid">
      <label class="mc-field" style="margin-top:0;">
        <span>Doctor Full Name *</span>
        <input type="text" id="full_name" name="full_name" placeholder="e.g. Dr. Sabrina Ahmed" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>BMDC / Medical Reg Number *</span>
        <input type="text" id="medical_registration_id" name="medical_registration_id" placeholder="e.g. BMDC-A-98765" value="<?= htmlspecialchars($_POST['medical_registration_id'] ?? '') ?>" required>
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Doctor Email Address *</span>
        <input type="email" id="email" name="email" placeholder="doctor@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Phone Number</span>
        <input type="text" id="phone" name="phone" placeholder="e.g. 01712345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </label>
    </div>

    <div class="mc-form-grid-3" style="margin-top:1rem;">
      <label class="mc-field" style="margin-top:0;">
        <span>Medical Specialization</span>
        <input type="text" id="specialization" name="specialization" placeholder="e.g. Cardiology / Internal Medicine" value="<?= htmlspecialchars($_POST['specialization'] ?? '') ?>">
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Primary Designation</span>
        <input type="text" id="designation" name="designation" placeholder="e.g. Senior Consultant" value="<?= htmlspecialchars($_POST['designation'] ?? 'Consultant') ?>">
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Experience (Years)</span>
        <input type="number" id="experience_years" name="experience_years" min="0" max="60" value="<?= htmlspecialchars($_POST['experience_years'] ?? '5') ?>">
      </label>
    </div>

    <div class="mc-form-grid" style="margin-top:1rem;">
      <label class="mc-field" style="margin-top:0;">
        <span>Degrees &amp; Qualifications</span>
        <input type="text" id="qualification" name="qualification" placeholder="e.g. MBBS (DMC), FCPS (Medicine)" value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>">
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Initial Password</span>
        <input type="text" id="password" name="password" value="<?= htmlspecialchars($_POST['password'] ?? 'Demo123!') ?>">
      </label>
    </div>
    <div style="font-size:.76rem;color:var(--muted);margin-top:.35rem;">Default demo password is <code>Demo123!</code></div>

    <div class="mc-alert mc-alert-ok" style="margin-top:1rem;margin-bottom:1.1rem;display:block;">
      <label style="display:flex;align-items:center;gap:.6rem;cursor:pointer;font-weight:700;">
        <input type="checkbox" name="verify_now" value="1" checked style="width:18px;height:18px;accent-color:#027a48;">
        <span>Immediately Approve &amp; Verify Doctor (Active Nationwide)</span>
      </label>
      <div style="font-size:.82rem;margin-left:1.7rem;margin-top:.2rem;">
        When checked, this doctor will immediately be marked <code>VERIFIED</code>, allowing all hospitals in the ecosystem to affiliate them right away.
      </div>
    </div>

    <div style="display:flex;gap:.6rem;justify-content:flex-end;">
      <a href="<?= APP_URL ?>/admin/doctors.php" class="mc-btn mc-btn-secondary">Cancel</a>
      <button type="submit" class="mc-btn mc-btn-primary"><i class="bi bi-check-lg"></i> Register Doctor</button>
    </div>
  </form>
</section>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
