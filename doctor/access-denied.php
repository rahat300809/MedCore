<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

initSession();

$reason   = $_GET['reason'] ?? 'unknown';
$doctorId = (int)($_SESSION['denied_doctor_id'] ?? 0);

// Capture the hospital the doctor was trying to access
$attemptedHospitalId   = (int)($_SESSION['denied_hospital_id'] ?? $_SESSION['selected_hospital_id'] ?? 0);
$attemptedHospitalName = $_SESSION['denied_hospital_name'] ?? $_SESSION['selected_hospital_name'] ?? null;

$db = getDB();
$flashSuccess = '';
$flashError   = '';

// If hospital name is missing, try looking up from DB
if ($attemptedHospitalId && empty($attemptedHospitalName)) {
    $stmtH = $db->prepare("SELECT name FROM hospitals WHERE id = ?");
    $stmtH->execute([$attemptedHospitalId]);
    $hRow = $stmtH->fetch();
    if ($hRow) $attemptedHospitalName = $hRow['name'];
}

// Handle affiliation request submission right here
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_affiliation_request'])) {
    validateCsrf();
    $postHospId   = (int)($_POST['hospital_id'] ?? $attemptedHospitalId);
    $designation  = trim($_POST['designation'] ?? 'Consultant');
    $empType      = trim($_POST['employment_type'] ?? 'FULL_TIME');
    $allowedTypes = ['FULL_TIME', 'PART_TIME', 'VISITING', 'HONORARY'];
    if (!in_array($empType, $allowedTypes)) $empType = 'FULL_TIME';

    if ($doctorId <= 0 || $postHospId <= 0) {
        $flashError = 'Invalid doctor or hospital identification. Please return to login.';
    } else {
        // Verify hospital exists and is verified
        $stmtH = $db->prepare("SELECT id, name FROM hospitals WHERE id = ? AND verification_status = 'VERIFIED'");
        $stmtH->execute([$postHospId]);
        $targetH = $stmtH->fetch();

        if (!$targetH) {
            $flashError = 'Selected hospital was not found or is not currently accredited.';
        } else {
            // Check existing affiliation row
            $stmtEx = $db->prepare("SELECT id, status FROM doctor_hospitals WHERE doctor_id = ? AND hospital_id = ?");
            $stmtEx->execute([$doctorId, $postHospId]);
            $existingRow = $stmtEx->fetch();

            if ($existingRow) {
                if ($existingRow['status'] === 'APPROVED') {
                    $flashError = 'You already have an approved affiliation with ' . htmlspecialchars($targetH['name']) . '.';
                } else {
                    // Update to PENDING (whether it was previously REJECTED, REMOVED, or already PENDING)
                    $db->prepare("
                        UPDATE doctor_hospitals
                        SET status = 'PENDING',
                            designation = ?,
                            employment_type = ?,
                            rejection_reason = NULL,
                            approved_by = NULL,
                            approved_at = NULL,
                            created_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$designation, $empType, $existingRow['id']]);

                    AuditService::log('DOCTOR_AFFILIATION_REQUEST', [
                        'doctor_id'   => $doctorId,
                        'hospital_id' => $postHospId,
                        'metadata'    => ['action' => 'resubmit', 'designation' => $designation, 'hospital_name' => $targetH['name']]
                    ]);
                    $flashSuccess = 'Your affiliation request to <strong>' . htmlspecialchars($targetH['name']) . '</strong> has been submitted. The hospital administration has been notified.';
                }
            } else {
                // Insert new PENDING row
                $db->prepare("
                    INSERT INTO doctor_hospitals (doctor_id, hospital_id, designation, employment_type, status, created_at)
                    VALUES (?, ?, ?, ?, 'PENDING', NOW())
                ")->execute([$doctorId, $postHospId, $designation, $empType]);

                AuditService::log('DOCTOR_AFFILIATION_REQUEST', [
                    'doctor_id'   => $doctorId,
                    'hospital_id' => $postHospId,
                    'metadata'    => ['action' => 'new', 'designation' => $designation, 'hospital_name' => $targetH['name']]
                ]);
                $flashSuccess = 'Your affiliation request to <strong>' . htmlspecialchars($targetH['name']) . '</strong> has been submitted successfully! The hospital administration can now approve your profile.';
            }
        }
    }
}

// Fetch doctor's approved hospitals to guide them
$approvedHospitals = [];
$existingStatus    = null;
if ($doctorId) {
    $stmt = $db->prepare("
        SELECT h.name, h.city, h.type, dh.designation, dh.employment_type
        FROM doctor_hospitals dh
        JOIN hospitals h ON dh.hospital_id = h.id
        WHERE dh.doctor_id = ? AND dh.status = 'APPROVED' AND h.verification_status = 'VERIFIED'
        ORDER BY h.name
    ");
    $stmt->execute([$doctorId]);
    $approvedHospitals = $stmt->fetchAll();

    // Check if doctor already has a record with the attempted hospital
    if ($attemptedHospitalId) {
        $stmtCheck = $db->prepare("SELECT status FROM doctor_hospitals WHERE doctor_id = ? AND hospital_id = ?");
        $stmtCheck->execute([$doctorId, $attemptedHospitalId]);
        $existingRow    = $stmtCheck->fetch();
        $existingStatus = $existingRow['status'] ?? null;
    }
}

$reasonMessages = [
    'no_affiliation'      => ['title' => 'No Approved Affiliation', 'msg' => 'You do not have an approved affiliation with the selected hospital. Please choose a hospital you are affiliated with, or request affiliation below.', 'icon' => 'bi-building-x', 'color' => 'var(--mc-amber)'],
    'doctor_not_verified' => ['title' => 'Pending Doctor Verification', 'msg' => 'Your doctor profile is pending verification by the MedCore admin team. You will be notified once your BMDC registration is verified.', 'icon' => 'bi-clock-history', 'color' => 'var(--mc-blue)'],
    'account_suspended'   => ['title' => 'Account Suspended', 'msg' => 'Your account has been suspended. Please contact MedCore support for assistance.', 'icon' => 'bi-slash-circle', 'color' => 'var(--mc-red)'],
    'account_blocked'     => ['title' => 'Account Blocked', 'msg' => 'Your account has been blocked due to security policy violations. Please contact MedCore support.', 'icon' => 'bi-shield-x', 'color' => 'var(--mc-red)'],
];

$info = $reasonMessages[$reason] ?? ['title' => 'Access Denied', 'msg' => 'Access to this portal could not be granted. Please try again or contact support.', 'icon' => 'bi-lock-fill', 'color' => 'var(--mc-red)'];

// Show request button only for no_affiliation + no existing active/pending record
$showRequestBtn  = ($reason === 'no_affiliation' && $attemptedHospitalId && $attemptedHospitalName
                    && !in_array($existingStatus, ['PENDING', 'APPROVED', 'SUSPENDED']) && empty($flashSuccess));
$showPendingNote = ($reason === 'no_affiliation' && ($existingStatus === 'PENDING' || !empty($flashSuccess)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Denied — MedCore Doctor Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
</head>
<body style="background:var(--mc-bg);min-height:100vh;display:flex;flex-direction:column;">

<div class="auth-flow-container" style="flex-direction:column;gap:var(--space-8);padding:40px 16px;">
  <div style="text-align:center;">
    <a href="<?= APP_URL ?>/" class="navbar-logo" style="justify-content:center;">
      <div class="logo-icon" style="width:36px;height:36px;font-size:18px;">M</div>
      <span>MedCore</span>
    </a>
  </div>

  <div class="doctor-verify-card" style="max-width:560px;width:100%;margin:0 auto;">
    <?php if ($flashSuccess): ?>
    <div class="alert alert-success mb-4" style="font-size:13px;line-height:1.5;">
      <i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?>
    </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
    <div class="alert alert-error mb-4" style="font-size:13px;">
      <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?>
    </div>
    <?php endif; ?>

    <!-- Icon -->
    <div style="text-align:center;margin-bottom:var(--space-6);">
      <div style="width:72px;height:72px;border-radius:50%;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-4);font-size:2rem;color:<?= $info['color'] ?>;">
        <i class="<?= $info['icon'] ?>"></i>
      </div>
      <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:var(--space-3);"><?= htmlspecialchars($info['title']) ?></h1>
      <p style="font-size:14px;color:var(--mc-text-secondary);line-height:1.6;"><?= htmlspecialchars($info['msg']) ?></p>
    </div>

    <!-- Approved hospitals list -->
    <?php if (!empty($approvedHospitals)): ?>
    <div style="margin-bottom:var(--space-6);">
      <div class="form-label" style="margin-bottom:var(--space-3);">Your Approved Affiliations</div>
      <?php foreach ($approvedHospitals as $h): ?>
      <div class="hospital-card" style="margin-bottom:var(--space-2);cursor:default;">
        <div class="hospital-logo"><i class="bi bi-hospital-fill" style="color:var(--mc-green);"></i></div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($h['name']) ?></div>
          <div style="font-size:12px;color:var(--mc-text-muted);"><?= htmlspecialchars($h['designation'] ?? '') ?> · <?= ucfirst(strtolower($h['employment_type'])) ?> · <?= htmlspecialchars($h['city'] ?? '') ?></div>
        </div>
        <span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Approved</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================
         REQUEST AFFILIATION WITH THE ATTEMPTED HOSPITAL
         ============================================================ -->
    <?php if ($showRequestBtn): ?>
    <div style="background:linear-gradient(135deg,#EFF6FF,#F0FDF4);border:1.5px solid #BFDBFE;border-radius:14px;padding:18px 20px;margin-bottom:var(--space-5);">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
        <div style="width:38px;height:38px;background:#DBEAFE;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#1E40AF;font-size:18px;flex-shrink:0;">
          <i class="bi bi-building-add"></i>
        </div>
        <div>
          <div style="font-weight:800;font-size:14px;color:#1E3A5F;">Request to Join This Hospital</div>
          <div style="font-size:12px;color:#475569;margin-top:1px;">
            Send an affiliation request to <strong><?= htmlspecialchars($attemptedHospitalName) ?></strong> — they will review and approve it.
          </div>
        </div>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="hospital_id" value="<?= (int)$attemptedHospitalId ?>">
        <input type="hidden" name="submit_affiliation_request" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
          <div>
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748B;display:block;margin-bottom:4px;">Your Designation</label>
            <input type="text" name="designation" class="form-control" value="Consultant"
              placeholder="e.g. Consultant, Resident" style="font-size:13px;" required>
          </div>
          <div>
            <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748B;display:block;margin-bottom:4px;">Employment Type</label>
            <select name="employment_type" class="form-control" style="font-size:13px;">
              <option value="FULL_TIME">Full Time</option>
              <option value="PART_TIME">Part Time</option>
              <option value="VISITING">Visiting</option>
              <option value="HONORARY">Honorary</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100"
          style="background:linear-gradient(135deg,#1E40AF,#1A73E8);border:none;font-weight:700;padding:11px;border-radius:10px;">
          <i class="bi bi-send-fill"></i>
          Send Request to <?= htmlspecialchars($attemptedHospitalName) ?>
        </button>
      </form>
    </div>
    <?php elseif ($showPendingNote): ?>
    <div style="background:#FFFBEB;border:1.5px solid #FDE68A;border-radius:12px;padding:14px 18px;margin-bottom:var(--space-5);display:flex;align-items:center;gap:12px;">
      <i class="bi bi-hourglass-split" style="font-size:22px;color:#92400E;flex-shrink:0;"></i>
      <div>
        <div style="font-weight:700;font-size:13px;color:#78350F;">Affiliation Request Pending</div>
        <div style="font-size:12px;color:#92400E;margin-top:2px;">
          Your request to join <strong><?= htmlspecialchars($attemptedHospitalName) ?></strong> is awaiting hospital admin review.
          You'll be able to practice here once the hospital approves your application.
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-3">
      <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="btn btn-primary flex-1">
        <i class="bi bi-building-fill-check"></i> Select Different Hospital
      </a>
      <a href="<?= APP_URL ?>/" class="btn btn-secondary">
        <i class="bi bi-house-fill"></i>
      </a>
    </div>

    <div style="margin-top:var(--space-6);padding-top:var(--space-5);border-top:1px solid var(--mc-border);font-size:12px;color:var(--mc-text-muted);text-align:center;">
      <i class="bi bi-shield-fill-check" style="color:var(--mc-green);"></i>
      This access attempt has been audit-logged with your IP address and timestamp.
    </div>
  </div>
</div>
</body>
</html>
