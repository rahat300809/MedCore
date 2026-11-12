<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/includes/session_bar.php';

requireDoctorHospitalContext();

$db = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];

// Get active patient session if one exists
$activeSession = getDoctorActivePatientSession($db, $doctorId, $hospitalId);

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

    // Strict single-patient lock: cannot request another patient while session is active
    if ($activeSession && (int)$activeSession['patient_id'] !== $patientId) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'msg'  => 'Active consultation with ' . htmlspecialchars($activeSession['full_name']) . ' is ongoing. Please end the active session before requesting another patient.'
        ];
        header('Location: ' . APP_URL . '/doctor/patient-search.php');
        exit;
    }

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
        $reqExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $stmt = $db->prepare("
            INSERT INTO access_requests
                (doctor_id, patient_id, hospital_id, doctor_notes, status, expires_at)
            VALUES (?, ?, ?, ?, 'PENDING', ?)
        ");
        $stmt->execute([$doctorId, $patientId, $hospitalId, $reason, $reqExpiresAt]);
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

    // Strict single-patient lock: cannot unlock another patient while session is active
    if ($activeSession && (int)$activeSession['patient_id'] !== $patientId) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'msg'  => 'Active consultation with ' . htmlspecialchars($activeSession['full_name']) . ' is ongoing. Please end the active session before consulting with another patient.'
        ];
        header('Location: ' . APP_URL . '/doctor/patient-search.php');
        exit;
    }

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
            $sessExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $db->prepare("
                INSERT INTO access_requests
                    (doctor_id, patient_id, hospital_id, doctor_notes, status, approved_at, expires_at)
                VALUES (?, ?, ?, ?, 'APPROVED', NOW(), ?)
            ");
            $stmt->execute([$doctorId, $patientId, $hospitalId, $reason . ' (Verified via Verbal OTP)', $sessExpiresAt]);
            $newReqId = (int)$db->lastInsertId();

            $sessionToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $sessionToken);
            $stmtSess = $db->prepare("
                INSERT INTO access_sessions
                    (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?)
            ");
            $stmtSess->execute([$newReqId, $doctorId, $patientId, $hospitalId, $tokenHash, $sessExpiresAt]);

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

    // Strict single-patient lock
    if ($activeSession && (int)$activeSession['patient_id'] !== $patientId) {
        $_SESSION['flash'] = [
            'type' => 'error',
            'msg'  => 'Active consultation with ' . htmlspecialchars($activeSession['full_name']) . ' is ongoing. Please end the active session before initiating emergency access for another patient.'
        ];
        header('Location: ' . APP_URL . '/doctor/patient-search.php');
        exit;
    }

    if (empty($emgReason)) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Clinical justification is required for Emergency Override.'];
    } else {
        $db->beginTransaction();
        try {
            $emgExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $stmt = $db->prepare("
                INSERT INTO access_requests
                    (doctor_id, patient_id, hospital_id, doctor_notes, status, approved_at, expires_at)
                VALUES (?, ?, ?, ?, 'APPROVED', NOW(), ?)
            ");
            $stmt->execute([$doctorId, $patientId, $hospitalId, '[EMERGENCY_OVERRIDE]: ' . $emgReason, $emgExpiresAt]);
            $newReqId = (int)$db->lastInsertId();

            $sessionToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $sessionToken);
            $stmtSess = $db->prepare("
                INSERT INTO access_sessions
                    (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?)
            ");
            $stmtSess->execute([$newReqId, $doctorId, $patientId, $hospitalId, $tokenHash, $emgExpiresAt]);

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

require_once __DIR__ . '/../app/views/portal/navs.php';
$waitingQueueCount = mc_doctor_shell_setup($db, $doctorId, $hospitalId, $activeSession, 'search');
$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $_SESSION['name'] ?? 'Doctor';
$portalMeta = ($_SESSION['hospital_name'] ?? '') . ' · Patient search';
$portalTitle = 'Patient search & access';
$portalSubtitle = 'রোগীর UID, ফোন বা NID দিয়ে রেকর্ড খুঁজুন';
$portalNav = mc_doctor_nav('search', $waitingQueueCount);
$portalTopAction = '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/doctor/queue.php"><i class="bi bi-people-fill"></i> Live queue</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($activeSession): ?>
  <div class="session-lock-card" role="alert">
    <div class="lock-icon"><i class="bi bi-shield-lock-fill"></i></div>
    <div class="lock-body">
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.35rem">
        <span class="lab-status danger">ACTIVE CONSULTATION IN PROGRESS</span>
        <span class="lab-status warn">30-min clinical window active</span>
      </div>
      <h3 style="margin:.25rem 0 .45rem;font-size:1.1rem">
        Currently consulting with <?= htmlspecialchars($activeSession['full_name']) ?> (<span class="mc-mono"><?= htmlspecialchars($activeSession['patient_uid']) ?></span>)
      </h3>
      <p style="margin:0 0 .85rem;font-size:.88rem;color:var(--ink-2);line-height:1.5">
        Under clinical safety and data privacy protocols, you cannot initiate or verify consultation with another patient without ending the current active session.
      </p>
      <div class="lock-actions">
        <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= (int)$activeSession['patient_id'] ?>" class="mc-btn mc-btn-primary mc-btn-sm">
          <i class="bi bi-file-earmark-medical-fill"></i> Return to <?= htmlspecialchars($activeSession['full_name']) ?>'s Record
        </a>
        <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= (int)$activeSession['patient_id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">
          <i class="bi bi-prescription2"></i> Write Prescription
        </a>
        <form method="POST" action="<?= APP_URL ?>/doctor/end-session.php" style="display:inline;margin:0" onsubmit="return confirm('End current consultation with <?= htmlspecialchars(addslashes($activeSession['full_name'])) ?> to switch patients?');">
          <?= csrfField() ?>
          <input type="hidden" name="session_id" value="<?= (int)$activeSession['id'] ?>">
          <input type="hidden" name="redirect_to" value="/doctor/patient-search.php">
          <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm">
            <i class="bi bi-x-circle-fill"></i> End Session &amp; Switch Patient
          </button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($flash): ?>
  <div class="mc-alert <?= ($flash['type'] ?? '') === 'success' ? 'mc-alert-ok' : 'mc-alert-danger' ?>">
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<div class="lab-token-bar">
  <label for="patient-search-input">Find patient (all searches are audit-logged · consent required before unlocking)</label>
  <form method="GET" class="lab-token-form">
    <input type="text" name="q" id="patient-search-input"
           value="<?= htmlspecialchars($query) ?>"
           style="text-transform:none;letter-spacing:normal;font-family:inherit;font-size:.95rem"
           placeholder="Patient ID (PT-000001), phone, or NID last 4 digits..."
           autocomplete="off" autofocus aria-label="Patient search">
    <button type="submit" class="mc-btn mc-btn-primary"><i class="bi bi-search-heart-fill"></i> Search</button>
  </form>
</div>

<?php if ($error): ?>
  <div class="mc-alert mc-alert-danger" role="alert"><?= $error ?></div>
<?php endif; ?>

<?php if ($found): ?>
  <?php $age = date_diff(new DateTime($found['date_of_birth']), new DateTime())->y; ?>

  <div class="mc-panel">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;padding-bottom:1rem;border-bottom:1px solid var(--line);margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:.9rem">
        <div class="mc-rail-avatar" style="width:52px;height:52px;font-size:1.25rem;border-radius:14px">
          <?= strtoupper(substr($found['full_name'], 0, 1)) ?>
        </div>
        <div>
          <h2 style="font-size:1.2rem;margin:0"><?= htmlspecialchars($found['full_name']) ?></h2>
          <div style="font-size:.86rem;color:var(--muted);margin-top:.2rem">
            <span class="mc-mono"><?= htmlspecialchars($found['patient_uid']) ?></span>
            · Age <?= $age ?> · <?= ucfirst(strtolower($found['gender'])) ?>
            · DOB <?= date('d M Y', strtotime($found['date_of_birth'])) ?>
          </div>
          <div style="display:flex;gap:.45rem;align-items:center;margin-top:.35rem;flex-wrap:wrap">
            <span class="lab-status danger">Blood: <?= htmlspecialchars($found['blood_group'] ?: 'Unknown') ?></span>
            <span class="lab-status">NID: ●●●● <?= htmlspecialchars($found['nid_last4']) ?></span>
          </div>
        </div>
      </div>

      <div>
        <?php if ($accessSession): ?>
          <?php $secsLeft = max(0, strtotime($accessSession['expires_at']) - time()); ?>
          <div class="lab-status ok" style="font-size:.82rem;padding:.4rem .75rem">
            <i class="bi bi-shield-lock-fill"></i> Access granted ·
            <span class="mc-mono" id="main-timer"><?= str_pad((string)floor($secsLeft / 60), 2, '0', STR_PAD_LEFT) ?>:<?= str_pad((string)($secsLeft % 60), 2, '0', STR_PAD_LEFT) ?></span>
          </div>
        <?php elseif ($pendingRequest): ?>
          <div class="lab-status warn" style="font-size:.82rem;padding:.4rem .75rem">
            <i class="bi bi-clock-fill"></i> Waiting approval · Sent <?= date('h:i A', strtotime($pendingRequest['requested_at'])) ?>
          </div>
        <?php else: ?>
          <span class="lab-status danger" style="font-size:.82rem;padding:.4rem .75rem">
            <i class="bi bi-lock-fill"></i> Record locked
          </span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($found['critical_allergy_count'] > 0 && $accessSession): ?>
      <div class="mc-alert mc-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>CRITICAL ALLERGY:</strong> <?= (int)$found['critical_allergy_count'] ?> severe/life-threatening allergy(ies) documented. Check allergy details before prescribing.
      </div>
    <?php endif; ?>

    <?php if ($activeSession && (int)$found['id'] !== (int)$activeSession['patient_id']): ?>
      <div class="session-lock-card" style="margin:0">
        <div class="lock-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="lock-body">
          <span class="lab-status danger">SWITCH BLOCKED</span>
          <h3 style="margin:.4rem 0">Consultation in progress with <?= htmlspecialchars($activeSession['full_name']) ?></h3>
          <p style="margin:0 0 .85rem;font-size:.88rem;color:var(--ink-2)">
            You cannot request consent or verify OTP for <strong><?= htmlspecialchars($found['full_name']) ?></strong> until your active consultation with <strong><?= htmlspecialchars($activeSession['full_name']) ?></strong> is ended.
          </p>
          <div class="lock-actions">
            <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= (int)$activeSession['patient_id'] ?>" class="mc-btn mc-btn-primary mc-btn-sm">Continue with <?= htmlspecialchars($activeSession['full_name']) ?></a>
            <form method="POST" action="<?= APP_URL ?>/doctor/end-session.php" style="display:inline;margin:0">
              <?= csrfField() ?>
              <input type="hidden" name="session_id" value="<?= (int)$activeSession['id'] ?>">
              <input type="hidden" name="redirect_to" value="/doctor/patient-search.php?q=<?= urlencode($query) ?>">
              <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm" onclick="return confirm('End session with <?= htmlspecialchars(addslashes($activeSession['full_name'])) ?> and switch to <?= htmlspecialchars(addslashes($found['full_name'])) ?>?');">
                End session &amp; switch
              </button>
            </form>
          </div>
        </div>
      </div>

    <?php elseif ($accessSession): ?>
      <div style="display:flex;gap:.65rem;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/doctor/medical-record.php?patient=<?= (int)$found['id'] ?>" class="mc-btn mc-btn-primary mc-btn-lg" style="flex:1">
          <i class="bi bi-folder2-open"></i> View full medical record
        </a>
        <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= (int)$found['id'] ?>&session=<?= (int)$accessSession['id'] ?>" class="mc-btn mc-btn-soft mc-btn-lg">
          <i class="bi bi-file-earmark-medical-fill"></i> New prescription
        </a>
        <a href="<?= APP_URL ?>/doctor/consultations.php" class="mc-btn mc-btn-secondary mc-btn-lg">
          <i class="bi bi-clock-history"></i> Past consultations
        </a>
      </div>

    <?php elseif ($pendingRequest): ?>
      <div style="max-width:620px;margin:0 auto">
        <div class="mc-alert mc-alert-warn">
          Waiting for in-app approval from <strong><?= htmlspecialchars($found['full_name']) ?></strong> (expires at <?= date('h:i A', strtotime($pendingRequest['expires_at'])) ?>).
        </div>
        <div class="mc-panel" style="background:#fffbeb;border-color:#fedf89;margin:0">
          <h3 style="font-size:1rem;margin:0 0 .5rem">Or verify patient OTP in-chamber</h3>
          <form method="POST" style="display:flex;gap:.6rem;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="verify_verbal_otp" value="1">
            <input type="hidden" name="patient_id" value="<?= (int)$found['id'] ?>">
            <input type="text" name="otp_code" class="mc-mono" value="1234" required
                   style="flex:1;min-width:160px;border:1px solid var(--line);border-radius:10px;padding:.65rem .9rem;font-size:1rem;background:#fff"
                   placeholder="Enter OTP (Demo: 1234)">
            <button type="submit" class="mc-btn mc-btn-primary"><i class="bi bi-unlock-fill"></i> Verify &amp; unlock</button>
          </form>
          <div style="font-size:.8rem;color:var(--warn);margin-top:.5rem">Demo mode active: code <strong>1234</strong> works without SMS.</div>
        </div>
      </div>

    <?php else: ?>
      <div style="max-width:740px;margin:0 auto">
        <div class="mc-panel" style="background:#fafbfc">
          <div class="mc-panel-h"><h2>Instant chamber consent (OTP)</h2></div>
          <p style="margin:0 0 .85rem;font-size:.88rem;color:var(--muted)">
            Ask the patient for their 4-digit verification code to start an instant 30-minute clinical session.
          </p>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="verify_verbal_otp" value="1">
            <input type="hidden" name="patient_id" value="<?= (int)$found['id'] ?>">

            <label class="mc-field" style="margin-top:0">
              <span>Clinical consultation reason</span>
              <select name="access_reason">
                <option value="General Outpatient Consultation">General Outpatient Consultation</option>
                <option value="Prescription & Medication Review">Prescription &amp; Medication Review</option>
                <option value="Follow-up Consultation">Follow-up Consultation</option>
                <option value="Diagnostic Lab Evaluation">Diagnostic Lab Evaluation</option>
                <option value="Pre-operative Assessment">Pre-operative Assessment</option>
              </select>
            </label>

            <div style="display:flex;gap:.65rem;flex-wrap:wrap;align-items:flex-end;margin-top:.85rem">
              <label class="mc-field" style="margin:0;flex:1;min-width:200px">
                <span>Patient verification code</span>
                <input type="text" name="otp_code" class="mc-mono" value="1234" required placeholder="Enter 4-digit code (Demo: 1234)">
              </label>
              <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg"><i class="bi bi-unlock-fill"></i> Unlock &amp; open record</button>
            </div>
            <div style="font-size:.8rem;color:var(--accent);font-weight:700;margin-top:.6rem">
              Demo mode active: use code <code class="mc-mono">1234</code> to open record instantly.
            </div>
          </form>
        </div>

        <div class="mc-grid-2">
          <div class="mc-panel" style="margin:0">
            <h3 style="font-size:1rem;margin:0 0 .35rem"><i class="bi bi-phone" style="color:var(--accent)"></i> Send in-app request</h3>
            <p style="font-size:.85rem;color:var(--muted);margin:0 0 .85rem">Patient receives a notification to review and tap Accept on their portal.</p>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="send_request" value="1">
              <input type="hidden" name="patient_id" value="<?= (int)$found['id'] ?>">
              <input type="hidden" name="access_reason" value="Outpatient Consultation (In-App Review)">
              <button type="submit" class="mc-btn mc-btn-secondary mc-btn-block"><i class="bi bi-send"></i> Send to patient app</button>
            </form>
          </div>

          <div class="mc-panel" style="margin:0;background:var(--danger-soft);border-color:#fecdca">
            <h3 style="font-size:1rem;margin:0 0 .35rem;color:var(--danger)"><i class="bi bi-exclamation-octagon-fill"></i> Emergency override</h3>
            <p style="font-size:.85rem;color:#991b1b;margin:0 0 .85rem">For unconscious or trauma patients in ER/ICU. Strictly audit-logged.</p>
            <button type="button" class="mc-btn mc-btn-danger mc-btn-block" onclick="document.getElementById('emg-modal').classList.add('open')">
              <i class="bi bi-lightning-charge-fill"></i> Break-glass access
            </button>
          </div>
        </div>
      </div>

      <!-- Emergency Modal -->
      <div id="emg-modal" class="mc-modal-backdrop" role="dialog" aria-modal="true" aria-label="Emergency override">
        <div class="mc-modal">
          <div class="mc-modal-head">
            <h3 style="color:var(--danger)"><i class="bi bi-shield-exclamation"></i> Emergency clinical override</h3>
            <button type="button" class="mc-modal-close" onclick="document.getElementById('emg-modal').classList.remove('open')" aria-label="Close"><i class="bi bi-x-lg"></i></button>
          </div>
          <p style="font-size:.9rem;color:var(--ink-2);margin:0 0 1rem">
            You are invoking emergency Break-Glass protocol for <strong><?= htmlspecialchars($found['full_name']) ?></strong>.
            This action is flagged with <strong>HIGH SEVERITY</strong> in the central audit log.
          </p>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="emergency_break_glass" value="1">
            <input type="hidden" name="patient_id" value="<?= (int)$found['id'] ?>">
            <label class="mc-field" style="margin-top:0">
              <span>Emergency clinical reason</span>
              <textarea name="emergency_reason" rows="3" placeholder="e.g. Unconscious road trauma patient in ED; immediate allergy & blood group check required." required></textarea>
            </label>
            <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem">
              <button type="button" class="mc-btn mc-btn-secondary" onclick="document.getElementById('emg-modal').classList.remove('open')">Cancel</button>
              <button type="submit" class="mc-btn mc-btn-danger">Confirm emergency access</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  <?php if ($accessSession): ?>
  if (typeof startAccessTimer === 'function') {
    startAccessTimer('main-timer', <?= max(0, strtotime($accessSession['expires_at']) - time()) ?>, function() {
      location.reload();
    });
  }
  <?php endif; ?>
});
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
