<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireDoctorHospitalContext();

$db = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];

$query  = trim($_GET['q'] ?? '');
$found  = null;
$error  = '';
$accessSession = null;

if ($query) {
    // Search by patient UID, phone number, or NID last 4 digits
    $cleanQuery = trim($query);
    $normalizedUid = (stripos($cleanQuery, 'PT-') === 0) ? strtoupper($cleanQuery) : ('PT-' . str_pad($cleanQuery, 6, '0', STR_PAD_LEFT));
    $phoneWithZero = (strlen($cleanQuery) === 10 && substr($cleanQuery, 0, 1) === '1') ? '0' . $cleanQuery : $cleanQuery;

    $stmt = $db->prepare("
        SELECT p.*, u.email, u.status AS user_status,
               u.last_login_at,
               (SELECT COUNT(*) FROM patient_allergies pa WHERE pa.patient_id = p.id AND pa.severity IN ('SEVERE','LIFE_THREATENING')) AS critical_allergy_count
        FROM patients p
        JOIN users u ON u.id = p.user_id
        WHERE p.patient_uid = ?
           OR p.patient_uid = ?
           OR p.nid_last4 = ?
           OR u.phone = ?
           OR u.phone = ?
        LIMIT 1
    ");
    $stmt->execute([$cleanQuery, $normalizedUid, $cleanQuery, $cleanQuery, $phoneWithZero]);
    $found = $stmt->fetch();

    if (!$found) {
        $error = 'No patient found with "' . htmlspecialchars($query) . '". Try Patient UID (PT-000001), Registered Phone Number, or NID last 4 digits.';
    } else {
        $age = date_diff(new DateTime($found['date_of_birth']), new DateTime())->y;

        // Log search
        AuditService::log('PATIENT_SEARCH', [
            'user_id'    => $_SESSION['user_id'],
            'doctor_id'  => $doctorId,
            'hospital_id'=> $hospitalId,
            'patient_id' => $found['id'],
            'metadata'   => ['query' => $query],
        ]);

        // Auto-expire stale pending access requests
        $db->prepare("UPDATE access_requests SET status = 'EXPIRED' WHERE status = 'PENDING' AND expires_at IS NOT NULL AND expires_at < NOW()")->execute();

        // Check for active access session
        $stmt = $db->prepare("
            SELECT * FROM access_sessions
            WHERE doctor_id = ? AND patient_id = ? AND hospital_id = ?
              AND status = 'ACTIVE' AND expires_at > NOW()
            ORDER BY expires_at DESC
            LIMIT 1
        ");
        $stmt->execute([$doctorId, $found['id'], $hospitalId]);
        $accessSession = $stmt->fetch() ?: null;

        // Also check pending access request
        $stmt = $db->prepare("
            SELECT * FROM access_requests
            WHERE doctor_id = ? AND patient_id = ? AND hospital_id = ?
              AND status = 'PENDING'
              AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY requested_at DESC
            LIMIT 1
        ");
        $stmt->execute([$doctorId, $found['id'], $hospitalId]);
        $pendingRequest = $stmt->fetch() ?: null;
    }
}

// Handle: send access request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    validateCsrf();
    $patientId = (int)$_POST['patient_id'];
    $reason    = trim($_POST['access_reason'] ?? '');

    // Auto-expire stale requests first
    $db->prepare("UPDATE access_requests SET status = 'EXPIRED' WHERE status = 'PENDING' AND expires_at IS NOT NULL AND expires_at < NOW()")->execute();

    // Check no existing pending or active
    $stmt = $db->prepare("
        SELECT id FROM access_requests
        WHERE doctor_id = ? AND patient_id = ? AND hospital_id = ?
          AND status = 'PENDING'
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$doctorId, $patientId, $hospitalId]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'An access request is already pending for this patient.'];
    } else {
        $stmt = $db->prepare("
            INSERT INTO access_requests
                (doctor_id, patient_id, hospital_id, doctor_notes, status, expires_at)
            VALUES (?, ?, ?, ?, 'PENDING', DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ");
        $stmt->execute([$doctorId, $patientId, $hospitalId, $reason]);
        $newReqId = (int)$db->lastInsertId();

        // Create notification for patient
        $doctorName = $_SESSION['name'] ?? 'Doctor';
        $hospitalName = $_SESSION['hospital_name'] ?? 'Hospital';
        $notifStmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, reference_type, reference_id, action_url)
            SELECT u.id, 'ACCESS_REQUEST',
                   CONCAT('Access Request from Dr. ', :dname),
                   CONCAT('Dr. ', :dname2, ' from ', :hname, ' requests access to your medical record.'),
                   'access_request',
                   :ref_id,
                   :action_url
            FROM patients p JOIN users u ON u.id = p.user_id
            WHERE p.id = :pid
        ");
        $notifStmt->execute([
            ':dname'      => $doctorName,
            ':dname2'     => $doctorName,
            ':hname'      => $hospitalName,
            ':ref_id'     => $newReqId,
            ':action_url' => APP_URL . '/patient/dashboard.php#doctor-requests',
            ':pid'        => $patientId,
        ]);

        AuditService::log('ACCESS_REQUESTED', [
            'user_id'    => $_SESSION['user_id'],
            'doctor_id'  => $doctorId,
            'patient_id' => $patientId,
            'hospital_id'=> $hospitalId,
            'metadata'   => ['reason' => $reason],
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Access request sent successfully! Waiting for patient approval.'];
    }
    header('Location: ' . APP_URL . '/doctor/patient-search.php?q=' . urlencode($query));
    exit;
}

// Handle verbal/demo OTP submission (Instant in-chamber approval)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_verbal_otp'])) {
    validateCsrf();
    $patientId = (int)$_POST['patient_id'];
    $otpCode   = trim($_POST['otp_code'] ?? '');
    $reason    = trim($_POST['access_reason'] ?? 'In-Chamber Clinical Consultation');

    // Accept demo code 1234 or verify against database
    $isValid = ($otpCode === '1234');

    if (!$isValid) {
        $stmtOtp = $db->prepare("
            SELECT ov.* FROM otp_verifications ov
            JOIN patients p ON p.user_id = ov.user_id
            WHERE p.id = ? AND ov.status = 'PENDING' AND ov.expires_at > NOW()
            ORDER BY ov.id DESC LIMIT 1
        ");
        $stmtOtp->execute([$patientId]);
        $otpRow = $stmtOtp->fetch();
        if ($otpRow && password_verify($otpCode, $otpRow['otp_hash'])) {
            $isValid = true;
            $db->prepare("UPDATE otp_verifications SET status = 'VERIFIED', verified_at = NOW() WHERE id = ?")->execute([$otpRow['id']]);
        }
    }

    if ($isValid) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO access_requests
                    (doctor_id, patient_id, hospital_id, doctor_notes, status, approved_at, expires_at)
                VALUES (?, ?, ?, ?, 'APPROVED', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");
            $stmt->execute([$doctorId, $patientId, $hospitalId, $reason . ' (Verified via Verbal OTP)']);
            $newReqId = (int)$db->lastInsertId();

            $sessionToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $sessionToken);
            $stmtSess = $db->prepare("
                INSERT INTO access_sessions
                    (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                VALUES (?, ?, ?, ?, ?, 'ACTIVE', DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");
            $stmtSess->execute([$newReqId, $doctorId, $patientId, $hospitalId, $tokenHash]);

            $db->commit();

            AuditService::log(AuditService::ACCESS_GRANTED, [
                'user_id'    => $_SESSION['user_id'],
                'doctor_id'  => $doctorId,
                'patient_id' => $patientId,
                'hospital_id'=> $hospitalId,
                'metadata'   => ['method' => 'verbal_otp', 'reason' => $reason]
            ]);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Patient consent verified via OTP! 30-minute clinical session active.'];
            header('Location: ' . APP_URL . '/doctor/medical-record.php?patient=' . $patientId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Failed to initialize session: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid OTP code. Please enter the demo code 1234 or the patient\'s code.'];
    }
    header('Location: ' . APP_URL . '/doctor/patient-search.php?q=' . urlencode($query));
    exit;
}

// Handle emergency break-glass override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emergency_break_glass'])) {
    validateCsrf();
    $patientId = (int)$_POST['patient_id'];
    $emgReason = trim($_POST['emergency_reason'] ?? '');

    if (empty($emgReason)) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Clinical justification is required for Emergency Override.'];
    } else {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO access_requests
                    (doctor_id, patient_id, hospital_id, doctor_notes, status, approved_at, expires_at)
                VALUES (?, ?, ?, ?, 'APPROVED', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");
            $stmt->execute([$doctorId, $patientId, $hospitalId, '[EMERGENCY_OVERRIDE]: ' . $emgReason]);
            $newReqId = (int)$db->lastInsertId();

            $sessionToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $sessionToken);
            $stmtSess = $db->prepare("
                INSERT INTO access_sessions
                    (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                VALUES (?, ?, ?, ?, ?, 'ACTIVE', DATE_ADD(NOW(), INTERVAL 30 MINUTE))
            ");
            $stmtSess->execute([$newReqId, $doctorId, $patientId, $hospitalId, $tokenHash]);

            $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, reference_type, reference_id, action_url)
                SELECT u.id, 'ACCESS_GRANTED',
                       'EMERGENCY ACCESS OVERRIDE ALERT',
                       CONCAT('Emergency access initiated by Dr. ', :dname, ' at ', :hname, '. Reason: ', :emgreason),
                       'access_session', :req_id, :action_url
                FROM patients p JOIN users u ON u.id = p.user_id
                WHERE p.id = :pid
            ")->execute([
                ':dname'      => $_SESSION['name'] ?? 'Doctor',
                ':hname'      => $_SESSION['hospital_name'] ?? 'Hospital',
                ':emgreason'  => $emgReason,
                ':req_id'     => $newReqId,
                ':action_url' => APP_URL . '/patient/access-history.php',
                ':pid'        => $patientId,
            ]);

            $db->commit();

            AuditService::log(AuditService::ACCESS_GRANTED, [
                'user_id'    => $_SESSION['user_id'],
                'doctor_id'  => $doctorId,
                'patient_id' => $patientId,
                'hospital_id'=> $hospitalId,
                'severity'   => 'CRITICAL',
                'metadata'   => ['protocol' => 'BREAK_GLASS_OVERRIDE', 'reason' => $emgReason]
            ]);

            $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Emergency override logged. 30-minute clinical access granted.'];
            header('Location: ' . APP_URL . '/doctor/medical-record.php?patient=' . $patientId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Emergency override failed: ' . $e->getMessage()];
        }
    }
    header('Location: ' . APP_URL . '/doctor/patient-search.php?q=' . urlencode($query));
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Search — MedCore Doctor Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body>
<div class="portal-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;">M</div>
      <div><div class="brand-name">MedCore</div><div class="brand-tagline" style="color:var(--mc-teal);">Clinical Portal</div></div>
    </div>
    <div style="padding:var(--space-3) var(--space-5);background:var(--mc-blue-50);border-bottom:1px solid var(--mc-border);">
      <div style="font-size:10px;color:var(--mc-text-muted);text-transform:uppercase;letter-spacing:1px;">Active Hospital</div>
      <div style="font-size:13px;font-weight:700;color:var(--mc-blue);margin-top:2px;">
        <i class="bi bi-building-fill-check"></i> <?= htmlspecialchars($_SESSION['hospital_name'] ?? '') ?>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/doctor/dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
      <div class="sidebar-section-title">Patient Care</div>
      <a href="<?= APP_URL ?>/doctor/patient-search.php" class="active" aria-current="page"><i class="bi bi-search-heart-fill"></i> Patient Search</a>
      <a href="<?= APP_URL ?>/doctor/consultations.php"><i class="bi bi-stethoscope"></i> My Consultations</a>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php"><i class="bi bi-file-earmark-medical-fill"></i> My Prescriptions</a>
      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="End your clinical session?"><i class="bi bi-box-arrow-right"></i> End Session</a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar" style="background:var(--mc-teal);"><?= strtoupper(substr($_SESSION['name'] ?? 'D', 3, 1)) ?></div>
        <div class="sidebar-user-info">
          <div class="name"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></div>
          <div class="role">Doctor</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="portal-main">
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar" aria-expanded="false"><i class="bi bi-list"></i></button>
      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Patient Search &amp; Access</h2>
        <div style="font-size:12px;color:var(--mc-text-muted);">Search by Patient ID or last 4 NID digits</div>
      </div>
      <a href="<?= APP_URL ?>/doctor/dashboard.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
    </header>

    <div class="portal-content">

      <!-- Flash -->
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?> mb-4" data-auto-dismiss="4000">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <!-- Search bar -->
      <div class="card mb-6">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:var(--space-4);">Find Patient</h3>
        <form method="GET" style="display:flex;gap:var(--space-3);">
          <div class="search-bar" style="flex:1;">
            <i class="bi bi-search"></i>
            <input type="text" name="q" id="patient-search-input"
              value="<?= htmlspecialchars($query) ?>"
              placeholder="Patient ID (PT-000001) or NID last 4 digits..."
              autocomplete="off" autofocus
              aria-label="Patient search">
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-search-heart-fill"></i> Search
          </button>
        </form>

        <div class="alert" style="background:var(--mc-amber-light);border:1px solid var(--mc-amber);margin-top:var(--space-4);border-radius:var(--radius-sm);">
          <i class="bi bi-shield-fill-exclamation" style="color:#92400E;"></i>
          <span style="font-size:13px;color:#92400E;">
            All patient searches are audit-logged. Records are locked until the patient grants explicit consent.
          </span>
        </div>
      </div>

      <!-- Error -->
      <?php if ($error): ?>
      <div class="alert alert-error mb-4" role="alert">
        <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
      </div>
      <?php endif; ?>

      <!-- FOUND PATIENT -->
      <?php if ($found): ?>
      <?php $age = date_diff(new DateTime($found['date_of_birth']), new DateTime())->y; ?>

      <div class="patient-result-card">
        <!-- Patient header -->
        <div class="patient-result-header">
          <div class="d-flex align-center justify-between gap-4" style="flex-wrap:wrap;">
            <div class="d-flex align-center gap-4">
              <div class="sidebar-avatar" style="width:56px;height:56px;font-size:22px;background:var(--mc-blue);">
                <?= strtoupper(substr($found['full_name'], 0, 1)) ?>
              </div>
              <div>
                <div style="font-size:1.1rem;font-weight:800;"><?= htmlspecialchars($found['full_name']) ?></div>
                <div style="font-size:13px;color:var(--mc-text-secondary);">
                  <?= $found['patient_uid'] ?> · Age <?= $age ?> · <?= ucfirst(strtolower($found['gender'])) ?>
                  · DOB: <?= date('d M Y', strtotime($found['date_of_birth'])) ?>
                </div>
                <div style="font-size:13px;margin-top:4px;">
                  <span class="badge badge-error" style="font-size:11px;">Blood: <?= $found['blood_group'] ?></span>
                  · NID: ●●●● <?= htmlspecialchars($found['nid_last4']) ?>
                </div>
              </div>
            </div>

            <!-- Access status -->
            <div>
              <?php if ($accessSession): ?>
              <?php $secsLeft = max(0, strtotime($accessSession['expires_at']) - time()); ?>
              <div class="access-timer">
                <i class="bi bi-shield-lock-fill"></i>
                <div>
                  <div style="font-size:11px;font-weight:600;">Access Granted</div>
                  <span class="timer-display" id="main-timer"><?= str_pad(floor($secsLeft/60), 2, '0', STR_PAD_LEFT) ?>:<?= str_pad($secsLeft % 60, 2, '0', STR_PAD_LEFT) ?></span>
                  remaining
                </div>
              </div>
              <?php elseif ($pendingRequest): ?>
              <div class="access-timer" style="background:var(--mc-amber-light);border-color:var(--mc-amber);">
                <i class="bi bi-clock-fill" style="color:var(--mc-amber);"></i>
                <div style="font-size:12px;color:#92400E;">
                  <div class="waiting-pulse">Waiting for patient approval...</div>
                  <div style="font-size:11px;">Sent <?= date('h:i A', strtotime($pendingRequest['requested_at'])) ?></div>
                </div>
              </div>
              <?php else: ?>
              <span class="badge" style="background:var(--mc-red-light);color:var(--mc-red);font-size:12px;padding:6px 12px;">
                <i class="bi bi-lock-fill"></i> Record Locked
              </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Critical allergy banner -->
          <?php if ($found['critical_allergy_count'] > 0 && $accessSession): ?>
          <div class="allergy-alert" style="margin-top:var(--space-4);">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>CRITICAL ALLERGY — <?= $found['critical_allergy_count'] ?> severe/life-threatening allergy(ies) documented.</strong>
            Check allergy details before prescribing.
          </div>
          <?php endif; ?>
        </div>

        <!-- Patient body -->
        <div class="patient-result-body">
          <?php if ($accessSession): ?>
          <!-- RECORD UNLOCKED -->
          <div class="d-flex gap-3" style="flex-wrap:wrap;margin-bottom:var(--space-5);">
            <a href="<?= APP_URL ?>/doctor/medical-record.php?patient=<?= $found['id'] ?>" class="btn btn-primary btn-lg" style="flex:1;">
              <i class="bi bi-folder2-open"></i> View Full Medical Record
            </a>
            <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $found['id'] ?>&session=<?= $accessSession['id'] ?>"
               class="btn btn-success btn-lg">
              <i class="bi bi-file-earmark-medical-fill"></i> New Prescription
            </a>
            <a href="<?= APP_URL ?>/doctor/consultations.php"
               class="btn btn-secondary btn-lg">
              <i class="bi bi-clock-history"></i> Past Consultations
            </a>
          </div>

          <?php elseif ($pendingRequest): ?>
          <!-- WAITING WITH INSTANT OTP FALLBACK -->
          <div class="record-locked" style="border-color:var(--mc-amber); max-width:620px; margin:0 auto; padding:28px 24px;">
            <div style="font-size:2.2rem;color:var(--mc-amber);margin-bottom:var(--space-3);" class="waiting-pulse">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <h4 style="font-weight:700;margin-bottom:8px;font-size:1.15rem;">Waiting for In-App Patient Approval</h4>
            <p style="font-size:13px;color:var(--mc-text-secondary);margin-bottom:16px;">
              A consent prompt was sent to <strong><?= htmlspecialchars($found['full_name']) ?></strong>.
              They can approve it on their MedCore mobile/web portal.
            </p>

            <div style="background:#FFFBEB; border:1px dashed #F59E0B; border-radius:12px; padding:16px; margin-top:16px; text-align:left;">
              <div style="font-size:12px; font-weight:700; color:#92400E; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                <i class="bi bi-chat-quote-fill"></i> Or Verify Patient OTP In-Chamber:
              </div>
              <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="verify_verbal_otp" value="1">
                <input type="hidden" name="patient_id" value="<?= $found['id'] ?>">
                <input type="text" name="otp_code" class="form-control" placeholder="Enter OTP (Demo: 1234)" style="flex:1; min-width:180px; font-family:var(--font-mono); font-weight:700; letter-spacing:2px; height:42px;" value="1234" required>
                <button type="submit" class="btn btn-success" style="height:42px; font-weight:700;">
                  <i class="bi bi-unlock-fill"></i> Verify &amp; Unlock
                </button>
              </form>
              <div style="font-size:11px; color:#B45309; margin-top:6px;">
                <i class="bi bi-info-circle"></i> Demo Mode Active: You can use code <strong>1234</strong> for testing without SMS.
              </div>
            </div>

            <p style="font-size:11px;color:var(--mc-text-muted);margin-top:12px;margin-bottom:0;">
              Request expires at <?= date('h:i A', strtotime($pendingRequest['expires_at'])) ?>
            </p>
          </div>

          <?php else: ?>
          <!-- LOCKED — DUAL ACCESS MODES (OTP vs IN-APP vs EMERGENCY) -->
          <div style="max-width:700px; margin:0 auto;">
            <!-- Primary Box: In-Chamber OTP -->
            <div class="card mb-4" style="border:1px solid #E2E8F0; border-radius:16px; padding:24px; box-shadow:0 4px 16px rgba(0,0,0,0.04); text-align:left;">
              <div style="display:flex; align-items:flex-start; gap:16px; margin-bottom:18px;">
                <div style="width:44px; height:44px; border-radius:12px; background:var(--mc-blue-light); color:var(--mc-blue); display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0;">
                  <i class="bi bi-shield-check"></i>
                </div>
                <div>
                  <h4 style="font-size:1.1rem; font-weight:800; color:var(--mc-navy); margin:0 0 4px;">Instant Chamber Consent (OTP)</h4>
                  <div style="font-size:13px; color:var(--mc-text-secondary);">
                    Ask patient for their 4-digit verification code to start an instant 30-minute clinical session.
                  </div>
                </div>
              </div>

              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="verify_verbal_otp" value="1">
                <input type="hidden" name="patient_id" value="<?= $found['id'] ?>">

                <div class="form-group mb-3">
                  <label class="form-label" style="font-size:12px; font-weight:700; text-transform:uppercase; color:var(--mc-text-secondary);">Clinical Consultation Reason</label>
                  <select name="access_reason" class="form-control" style="height:42px;">
                    <option value="General Outpatient Consultation">General Outpatient Consultation</option>
                    <option value="Prescription & Medication Review">Prescription &amp; Medication Review</option>
                    <option value="Follow-up Consultation">Follow-up Consultation</option>
                    <option value="Diagnostic Lab Evaluation">Diagnostic Lab Evaluation</option>
                    <option value="Pre-operative Assessment">Pre-operative Assessment</option>
                  </select>
                </div>

                <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
                  <div style="flex:1; min-width:200px;">
                    <label class="form-label" style="font-size:12px; font-weight:700; text-transform:uppercase; color:var(--mc-text-secondary);">Patient Verification Code</label>
                    <input type="text" name="otp_code" class="form-control" placeholder="Enter 4-digit code (Demo: 1234)" value="1234" style="height:46px; font-family:var(--font-mono); font-size:1.1rem; font-weight:700; letter-spacing:2px;" required>
                  </div>
                  <button type="submit" class="btn btn-primary" style="height:46px; padding:0 24px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
                    <i class="bi bi-unlock-fill"></i> Unlock &amp; Open Record
                  </button>
                </div>

                <div style="display:flex; align-items:center; gap:6px; margin-top:10px; font-size:12px; color:var(--mc-blue); font-weight:600;">
                  <i class="bi bi-info-circle-fill"></i> Demo Mode Active: Use demo code <code>1234</code> to open record instantly.
                </div>
              </form>
            </div>

            <!-- Alternative Options: In-App Request & Emergency Override -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
              <!-- Option B: In-App Request -->
              <div class="card" style="padding:18px; border:1px solid var(--mc-border); border-radius:12px;">
                <div style="font-weight:700; font-size:13px; color:var(--mc-navy); margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                  <i class="bi bi-phone" style="color:var(--mc-teal);"></i> Send In-App Request
                </div>
                <p style="font-size:12px; color:var(--mc-text-muted); margin-bottom:12px; line-height:1.4;">
                  Patient will receive a push notification to review and tap Accept on their mobile phone.
                </p>
                <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="send_request" value="1">
                  <input type="hidden" name="patient_id" value="<?= $found['id'] ?>">
                  <input type="hidden" name="access_reason" value="Outpatient Consultation (In-App Review)">
                  <button type="submit" class="btn btn-outline btn-sm w-100">
                    <i class="bi bi-send"></i> Send to Patient App
                  </button>
                </form>
              </div>

              <!-- Option C: Emergency Override (Break Glass) -->
              <div class="card" style="padding:18px; border:1px solid #FECACA; background:#FEF2F2; border-radius:12px;">
                <div style="font-weight:700; font-size:13px; color:#DC2626; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                  <i class="bi bi-exclamation-octagon-fill"></i> Emergency Override
                </div>
                <p style="font-size:12px; color:#991B1B; margin-bottom:12px; line-height:1.4;">
                  For unconscious or trauma patients in ER/ICU. Strictly logged with central audit trail.
                </p>
                <button type="button" class="btn btn-secondary btn-sm w-100" style="color:#DC2626; border-color:#FCA5A5; background:white; font-weight:700;" onclick="document.getElementById('emg-modal').style.display='flex'">
                  <i class="bi bi-lightning-charge-fill"></i> Break-Glass Access
                </button>
              </div>
            </div>

            <!-- Emergency Modal -->
            <div id="emg-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
              <div style="background:white; border-radius:16px; max-width:480px; width:100%; padding:24px; box-shadow:var(--shadow-lg);">
                <div style="display:flex; align-items:center; gap:10px; color:#DC2626; margin-bottom:12px;">
                  <i class="bi bi-shield-exclamation" style="font-size:1.8rem;"></i>
                  <h3 style="font-size:1.15rem; font-weight:800; margin:0;">Emergency Clinical Override</h3>
                </div>
                <p style="font-size:13px; color:var(--mc-text-secondary); margin-bottom:16px;">
                  You are invoking emergency Break-Glass protocol for <strong><?= htmlspecialchars($found['full_name']) ?></strong>.
                  State the critical clinical reason. This action will be flagged with <strong>HIGH SEVERITY</strong> in the central health audit log.
                </p>
                <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="emergency_break_glass" value="1">
                  <input type="hidden" name="patient_id" value="<?= $found['id'] ?>">
                  <div class="form-group mb-4">
                    <label class="form-label" style="font-size:12px; font-weight:700;">Emergency Clinical Reason</label>
                    <textarea name="emergency_reason" class="form-control" rows="3" placeholder="e.g. Unconscious road trauma patient in ED; required immediate allergy & blood group verification." required></textarea>
                  </div>
                  <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('emg-modal').style.display='none'">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm" style="background:#DC2626; color:white; font-weight:700;">Confirm Emergency Access</button>
                  </div>
                </form>
              </div>
            </div>

          </div>
          <?php endif; ?>

        </div>
      </div>

      <?php endif; ?>

    </div>
  </main>
</div>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
document.getElementById('hamburger-btn')?.addEventListener('click', function() {
  document.getElementById('sidebar').classList.toggle('open');
  this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') !== 'true');
});

<?php if ($accessSession): ?>
startAccessTimer('main-timer', <?= max(0, strtotime($accessSession['expires_at']) - time()) ?>, function() {
  document.querySelector('.patient-result-header').innerHTML += '<div class="alert alert-error" style="margin-top:12px;"><i class="bi bi-lock-fill"></i> Access session expired. Please send a new consent request.</div>';
});
<?php endif; ?>
</script>
</body>
</html>
