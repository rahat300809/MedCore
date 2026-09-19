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
    // Search by patient UID or NID last4
    $stmt = $db->prepare("
        SELECT p.*, u.email, u.status AS user_status,
               u.last_login_at,
               (SELECT COUNT(*) FROM patient_allergies pa WHERE pa.patient_id = p.id AND pa.severity IN ('SEVERE','LIFE_THREATENING')) AS critical_allergy_count
        FROM patients p
        JOIN users u ON u.id = p.user_id
        WHERE p.patient_uid = ? OR p.nid_last4 = ?
        LIMIT 1
    ");
    $stmt->execute([$query, ltrim($query, '0')]);
    $found = $stmt->fetch();

    if (!$found) {
        $error = 'No patient found with ID "' . htmlspecialchars($query) . '". Try the full Patient ID (PT-000001) or NID last 4 digits.';
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
            <a href="<?= APP_URL ?>/doctor/new-consultation.php?patient=<?= $found['id'] ?>&session=<?= $accessSession['id'] ?>"
               class="btn btn-secondary btn-lg">
              <i class="bi bi-clipboard2-pulse-fill"></i> New Consultation
            </a>
          </div>

          <?php elseif ($pendingRequest): ?>
          <!-- WAITING -->
          <div class="record-locked" style="border-color:var(--mc-amber);">
            <div style="font-size:2rem;color:var(--mc-amber);margin-bottom:var(--space-4);" class="waiting-pulse">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <h4 style="font-weight:700;margin-bottom:var(--space-3);">Waiting for Patient Approval</h4>
            <p style="font-size:14px;color:var(--mc-text-secondary);">
              A consent request has been sent to <?= htmlspecialchars($found['full_name']) ?>.
              They must approve it on their MedCore portal to grant you access.
            </p>
            <p style="font-size:12px;color:var(--mc-text-muted);margin-top:var(--space-3);">
              Request expires: <?= date('h:i A', strtotime($pendingRequest['expires_at'])) ?>
            </p>
          </div>

          <?php else: ?>
          <!-- LOCKED — Request form -->
          <div class="record-locked">
            <div class="record-locked-icon"><i class="bi bi-lock-fill"></i></div>
            <h4 style="font-weight:700;margin-bottom:var(--space-3);">Medical Record Locked</h4>
            <p style="font-size:14px;color:var(--mc-text-secondary);margin-bottom:var(--space-6);">
              You must request and receive patient consent to access <?= htmlspecialchars($found['full_name']) ?>'s medical record.
            </p>

            <form method="POST" style="max-width:400px;margin:0 auto;text-align:left;">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="send_request" value="1">
              <input type="hidden" name="patient_id" value="<?= $found['id'] ?>">
              <div class="form-group">
                <label class="form-label">Reason for Access</label>
                <select name="access_reason" class="form-control" required>
                  <option value="">Select clinical reason...</option>
                  <option value="General Consultation">General Consultation</option>
                  <option value="Emergency Assessment">Emergency Assessment</option>
                  <option value="Prescription Review">Prescription Review</option>
                  <option value="Follow-up Consultation">Follow-up Consultation</option>
                  <option value="Lab Report Review">Lab Report Review</option>
                  <option value="Referral Assessment">Referral Assessment</option>
                  <option value="Pre-operative Assessment">Pre-operative Assessment</option>
                </select>
              </div>
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-send-fill"></i>
                Send Consent Request to Patient
              </button>
            </form>
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
