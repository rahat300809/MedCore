<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/includes/session_bar.php';

requireDoctorHospitalContext();

$db         = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];
$doctorName = $_SESSION['name'] ?? 'Doctor';
$hospitalName = $_SESSION['hospital_name'] ?? 'Hospital';

$activePatientSession = getDoctorActivePatientSession($db, $doctorId, $hospitalId);

$today = date('Y-m-d');
$selectedDate = trim($_GET['date'] ?? $today);

// -------------------------------------------------------------
// Handle Status Actions (Call Patient, Complete, Cancel)
// -------------------------------------------------------------
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $aptId  = (int)($_POST['appointment_id'] ?? 0);

    if ($action === 'start_consultation' && $aptId > 0) {
        // Fetch appointment & patient
        $stmtApt = $db->prepare("
            SELECT a.*, p.id AS p_id, p.full_name, p.patient_uid
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            WHERE a.id = ? AND a.doctor_id = ? AND a.hospital_id = ?
            LIMIT 1
        ");
        $stmtApt->execute([$aptId, $doctorId, $hospitalId]);
        $apt = $stmtApt->fetch();

        if ($apt) {
            $patientId = (int)$apt['p_id'];

            // 1. Update appointment status to IN_CONSULTATION
            $db->prepare("UPDATE appointments SET status = 'IN_CONSULTATION', updated_at = NOW() WHERE id = ?")->execute([$aptId]);

            // 2. Check if active session already exists, or create a 1-hour active consultation session
            $stmtSess = $db->prepare("
                SELECT id FROM access_sessions
                WHERE doctor_id = ? AND patient_id = ? AND hospital_id = ? AND status = 'ACTIVE' AND expires_at > NOW()
                LIMIT 1
            ");
            $stmtSess->execute([$doctorId, $patientId, $hospitalId]);
            $existingSess = $stmtSess->fetch();

            if (!$existingSess) {
                // Auto-create active session for confirmed queued appointment
                $tokenHash = hash('sha256', bin2hex(random_bytes(32)));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

                // First find or create an approved access_request
                $db->prepare("
                    INSERT INTO access_requests
                        (patient_id, doctor_id, hospital_id, request_type, status, patient_message, requested_at, approved_at, expires_at)
                    VALUES (?, ?, ?, 'ALL', 'APPROVED', 'Appointment queue consultation check-in', NOW(), NOW(), ?)
                ")->execute([$patientId, $doctorId, $hospitalId, $expiresAt]);
                $reqId = $db->lastInsertId();

                $db->prepare("
                    INSERT INTO access_sessions
                        (access_request_id, patient_id, doctor_id, hospital_id, token_hash, started_at, expires_at, status)
                    VALUES (?, ?, ?, ?, ?, NOW(), ?, 'ACTIVE')
                ")->execute([$reqId, $patientId, $doctorId, $hospitalId, $tokenHash, $expiresAt]);
            }

            AuditService::log('START_QUEUE_CONSULTATION', [
                'user_id'     => $_SESSION['user_id'],
                'doctor_id'   => $doctorId,
                'patient_id'  => $patientId,
                'hospital_id' => $hospitalId,
                'metadata'    => ['appointment_id' => $aptId, 'serial_number' => $apt['serial_number']]
            ]);

            // Redirect doctor straight to the patient's medical record or prescription writer
            header('Location: ' . APP_URL . '/doctor/medical-record.php?patient_id=' . $patientId);
            exit;
        }
    } elseif ($action === 'mark_completed' && $aptId > 0) {
        $db->prepare("UPDATE appointments SET status = 'COMPLETED', updated_at = NOW() WHERE id = ? AND doctor_id = ? AND hospital_id = ?")
           ->execute([$aptId, $doctorId, $hospitalId]);
        $message = "Appointment marked as completed.";
    } elseif ($action === 'cancel_appointment' && $aptId > 0) {
        $db->prepare("UPDATE appointments SET status = 'CANCELLED', updated_at = NOW() WHERE id = ? AND doctor_id = ? AND hospital_id = ?")
           ->execute([$aptId, $doctorId, $hospitalId]);
        $message = "Appointment cancelled.";
    } elseif ($action === 'add_emergency_patient') {
        // Fast-track add an emergency patient directly into today's queue
        $targetPatientId = (int)($_POST['target_patient_id'] ?? 0);
        $symptoms        = trim($_POST['emergency_notes'] ?? 'Emergency Walk-In / Fast Track');

        if ($targetPatientId > 0) {
            $stmtSerial = $db->prepare("
                SELECT COALESCE(MAX(serial_number), 0) + 1
                FROM appointments
                WHERE doctor_id = ? AND hospital_id = ? AND appointment_date = ?
            ");
            $stmtSerial->execute([$doctorId, $hospitalId, $today]);
            $nextSerial = (int)$stmtSerial->fetchColumn();

            $emAptUid = 'APT-' . date('Ymd') . '-EM' . strtoupper(substr(uniqid(), -2));

            $db->prepare("
                INSERT INTO appointments
                    (appointment_uid, serial_number, patient_id, doctor_id, hospital_id,
                     appointment_date, time_slot, priority, problem_description, consultation_fee, payment_status, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Immediate / STAT', 'EMERGENCY', ?, 1000.0, 'UNPAID', 'WAITING')
            ")->execute([
                $emAptUid, $nextSerial, $targetPatientId, $doctorId, $hospitalId, $today, $symptoms
            ]);

            $message = "Emergency patient added to queue with Priority Serial #{$nextSerial}!";
        } else {
            $error = "Please search and select a valid patient.";
        }
    }
}

// -------------------------------------------------------------
// Emergency Manual Patient Search
// -------------------------------------------------------------
$searchPatientQuery = trim($_GET['patient_search'] ?? '');
$foundPatients = [];
if (!empty($searchPatientQuery)) {
    $stmtSearchP = $db->prepare("
        SELECT p.*, u.email, u.phone AS user_phone
        FROM patients p
        JOIN users u ON u.id = p.user_id
        WHERE p.patient_uid LIKE ? OR p.full_name LIKE ? OR p.phone LIKE ? OR p.national_id LIKE ?
        LIMIT 5
    ");
    $termP = "%{$searchPatientQuery}%";
    $stmtSearchP->execute([$termP, $termP, $termP, $termP]);
    $foundPatients = $stmtSearchP->fetchAll();
}

// -------------------------------------------------------------
// Fetch Today's Appointments & Queue
// -------------------------------------------------------------
$stmtQueue = $db->prepare("
    SELECT a.*, p.full_name AS patient_name, p.patient_uid, p.date_of_birth, p.gender, p.blood_group,
           p.phone AS patient_phone,
           dept.name AS department_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    LEFT JOIN departments dept ON a.department_id = dept.id
    WHERE a.doctor_id = ? AND a.hospital_id = ? AND a.appointment_date = ?
    ORDER BY 
        CASE WHEN a.priority = 'EMERGENCY' THEN 1 WHEN a.priority = 'URGENT' THEN 2 ELSE 3 END ASC,
        a.serial_number ASC
");
$stmtQueue->execute([$doctorId, $hospitalId, $selectedDate]);
$queueList = $stmtQueue->fetchAll();

// Queue stats
$totalInQueue = count($queueList);
$waitingCount = 0;
$inConsultCount = 0;
$completedCount = 0;
$emergencyCount = 0;

foreach ($queueList as $item) {
    if ($item['status'] === 'WAITING' || $item['status'] === 'CONFIRMED') $waitingCount++;
    if ($item['status'] === 'IN_CONSULTATION') $inConsultCount++;
    if ($item['status'] === 'COMPLETED') $completedCount++;
    if ($item['priority'] === 'EMERGENCY') $emergencyCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Live Patient Queue — Dr. <?= htmlspecialchars($doctorName) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .queue-card {
      background: #ffffff;
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-lg);
      padding: 18px 20px;
      margin-bottom: 12px;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .queue-card:hover {
      border-color: var(--mc-blue);
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .queue-card.emergency-card {
      border: 2px solid #EF4444;
      background: #FEF2F2;
    }
    .queue-card.in-consult-card {
      border: 2px solid #0EA5E9;
      background: #F0F9FF;
    }
    .serial-badge {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      background: #F1F5F9;
      color: #0F172A;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      flex-shrink: 0;
    }
    .serial-badge.emergency {
      background: #EF4444;
      color: #ffffff;
      animation: pulse-red 1.5s infinite;
    }
    .serial-badge.in-consult {
      background: #0284C7;
      color: #ffffff;
    }
    @keyframes pulse-red {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    .search-patient-bar {
      background: #F8FAFC;
      border: 1.5px dashed #0EA5E9;
      border-radius: var(--radius-lg);
      padding: 18px 22px;
      margin-bottom: 24px;
    }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo-icon">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-tagline">Clinical Portal</div>
      </div>
    </div>

    <div style="padding:var(--space-3) var(--space-5);background:var(--mc-blue-50);border-bottom:1px solid var(--mc-border);">
      <div style="font-size:10px;color:var(--mc-text-muted);text-transform:uppercase;letter-spacing:1px;">Active Hospital</div>
      <div style="font-size:13px;font-weight:700;color:var(--mc-blue);margin-top:2px;">
        <i class="bi bi-building-fill-check"></i> <?= htmlspecialchars($hospitalName) ?>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/doctor/dashboard.php">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
      </a>

      <div class="sidebar-section-title">Patient Care</div>
      <a href="<?= APP_URL ?>/doctor/queue.php" class="active">
        <i class="bi bi-people-fill"></i> Live Patient Queue
        <?php if ($waitingCount > 0): ?>
          <span class="nav-badge" style="background:#0F766E; color:#fff;"><?= $waitingCount ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/doctor/patient-search.php">
        <i class="bi bi-search-heart-fill"></i> Patient Search (ICE / NID)
      </a>
      <a href="<?= APP_URL ?>/doctor/consultations.php">
        <i class="bi bi-stethoscope"></i> My Consultations
      </a>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php">
        <i class="bi bi-file-earmark-medical-fill"></i> My Prescriptions
      </a>

      <div class="sidebar-section-title">Appointments</div>
      <a href="<?= APP_URL ?>/book-doctor.php" target="_blank" style="color:#0F766E; font-weight:600;">
        <i class="bi bi-calendar2-plus-fill"></i> Public Booking Directory &rarr;
      </a>

      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="End your clinical session?">
        <i class="bi bi-box-arrow-right"></i> End Session
      </a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <!-- Active Session Bar -->
    <?php renderDoctorSessionBar($activePatientSession); ?>

    <!-- Header -->
    <header class="portal-header">
      <div>
        <span class="eyebrow" style="color:var(--mc-blue);font-size:11px;font-weight:700;">DAILY CLINICAL SCHEDULE</span>
        <h1 class="portal-title" style="font-size:1.4rem; margin:0;">
          Live Patient Queue (আজকের রোগী সিরিয়াল)
        </h1>
      </div>

      <div class="header-actions">
        <!-- Date Switcher -->
        <form method="GET" class="d-flex align-items-center gap-2">
          <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()" style="border-radius:8px;">
          <?php if ($selectedDate !== $today): ?>
            <a href="?date=<?= $today ?>" class="btn btn-outline btn-sm">Today</a>
          <?php endif; ?>
        </form>
      </div>
    </header>

    <div class="portal-body" style="padding:var(--space-6);">

      <?php if ($message): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- 1. STATS OVERVIEW BAR -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="card p-3 text-center" style="border-left:4px solid #0F766E;">
            <div style="font-size:11px; color:#64748B; font-weight:700; text-transform:uppercase;">Total Serials</div>
            <div style="font-size:1.75rem; font-weight:900; color:#0F172A; line-height:1.2;"><?= $totalInQueue ?></div>
            <div style="font-size:11px; color:#0F766E;">Date: <?= date('d M Y', strtotime($selectedDate)) ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card p-3 text-center" style="border-left:4px solid #F59E0B;">
            <div style="font-size:11px; color:#64748B; font-weight:700; text-transform:uppercase;">Waiting in Lobby</div>
            <div style="font-size:1.75rem; font-weight:900; color:#D97706; line-height:1.2;"><?= $waitingCount ?></div>
            <div style="font-size:11px; color:#64748B;">Ready to be called</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card p-3 text-center" style="border-left:4px solid #0EA5E9;">
            <div style="font-size:11px; color:#64748B; font-weight:700; text-transform:uppercase;">In Consultation</div>
            <div style="font-size:1.75rem; font-weight:900; color:#0284C7; line-height:1.2;"><?= $inConsultCount ?></div>
            <div style="font-size:11px; color:#64748B;">Active in Chamber</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card p-3 text-center" style="border-left:4px solid #EF4444;">
            <div style="font-size:11px; color:#64748B; font-weight:700; text-transform:uppercase;">🚨 Emergency / STAT</div>
            <div style="font-size:1.75rem; font-weight:900; color:#DC2626; line-height:1.2;"><?= $emergencyCount ?></div>
            <div style="font-size:11px; color:#DC2626; font-weight:600;">Top Priority</div>
          </div>
        </div>
      </div>

      <!-- 2. EMERGENCY WALK-IN & MANUAL PATIENT SEARCH BAR -->
      <div class="search-patient-bar">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
          <div>
            <strong style="font-size:13px; color:#0F172A;"><i class="bi bi-shield-exclamation text-danger"></i> Emergency Walk-In &amp; Manual Patient Search (জরুরি রোগী ম্যানুয়াল এন্ট্রি)</strong>
            <div style="font-size:12px; color:#64748B;">Search any patient by MedCore ID (e.g. <code>PT-000001</code>), Phone, or NID to add immediately as STAT Emergency to your queue.</div>
          </div>
        </div>

        <form method="GET" class="d-flex gap-2">
          <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
          <div style="position:relative; flex:1;">
            <i class="bi bi-search" style="position:absolute; left:12px; top:10px; color:#94A3B8;"></i>
            <input type="text" name="patient_search" class="form-control" placeholder="Enter Patient UID (e.g. PT-000001), Phone number, or NID..." value="<?= htmlspecialchars($searchPatientQuery) ?>" style="padding-left:36px; border-radius:10px;">
          </div>
          <button type="submit" class="btn btn-secondary btn-sm" style="padding:0 18px; border-radius:10px;">
            Search Patient
          </button>
          <?php if (!empty($searchPatientQuery)): ?>
            <a href="?date=<?= $selectedDate ?>" class="btn btn-outline btn-sm">Clear</a>
          <?php endif; ?>
        </form>

        <!-- Search Results Dropdown/Box -->
        <?php if (!empty($foundPatients)): ?>
          <div style="margin-top:14px; background:#fff; border:1px solid #CBD5E1; border-radius:12px; padding:12px;">
            <div style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase; margin-bottom:8px;">Matching Patients:</div>
            <?php foreach ($foundPatients as $fp): ?>
              <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #F1F5F9; flex-wrap:wrap; gap:8px;">
                <div>
                  <strong style="color:#0F172A; font-size:13px;"><?= htmlspecialchars($fp['full_name']) ?></strong>
                  <span class="badge badge-secondary"><?= htmlspecialchars($fp['patient_uid']) ?></span>
                  <span style="font-size:12px; color:#64748B;">&bull; Blood: <?= htmlspecialchars($fp['blood_group'] ?: 'Unknown') ?> &bull; Phone: <?= htmlspecialchars($fp['phone'] ?: $fp['user_phone']) ?></span>
                </div>
                <form method="POST" class="d-flex gap-2 align-items-center">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="add_emergency_patient">
                  <input type="hidden" name="target_patient_id" value="<?= $fp['id'] ?>">
                  <input type="text" name="emergency_notes" class="form-control form-control-sm" placeholder="Emergency symptoms..." style="width:200px;" required>
                  <button type="submit" class="btn btn-danger btn-sm" style="white-space:nowrap;">
                    <i class="bi bi-plus-circle-fill"></i> Add to Queue as STAT
                  </button>
                  <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= $fp['id'] ?>" class="btn btn-outline btn-sm" style="white-space:nowrap;">
                    View Record
                  </a>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php elseif (!empty($searchPatientQuery)): ?>
          <div style="margin-top:10px; font-size:12px; color:#DC2626;">No patient found matching "<?= htmlspecialchars($searchPatientQuery) ?>". Please verify ID or phone.</div>
        <?php endif; ?>
      </div>

      <!-- 3. LIVE PATIENT QUEUE LIST -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h3 class="card-title">
            <i class="bi bi-list-ol"></i> Patient Queue for Today (<?= count($queueList) ?> Patients)
          </h3>
          <span class="badge badge-info" style="font-size:11px;">
            <i class="bi bi-clock-history"></i> Priority: Emergency first, then Serial Order
          </span>
        </div>

        <div class="card-body">
          <?php if (empty($queueList)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-calendar2-x" style="font-size:3.5rem; color:#CBD5E1;"></i>
              <h4 style="font-size:1.15rem; font-weight:700; color:#334155; margin-top:12px;">No patients booked for this date</h4>
              <p style="font-size:13px; color:#64748B;">Patients booking online will appear here automatically. You can also add emergency walk-in patients above.</p>
            </div>
          <?php else: ?>
            <div style="display:flex; flex-direction:column;">
              <?php foreach ($queueList as $item): ?>
                <?php
                  $isEm = ($item['priority'] === 'EMERGENCY');
                  $isInConsult = ($item['status'] === 'IN_CONSULTATION');
                  $isDone = ($item['status'] === 'COMPLETED');
                  $cardCls = $isEm ? 'emergency-card' : ($isInConsult ? 'in-consult-card' : '');
                  $serialCls = $isEm ? 'emergency' : ($isInConsult ? 'in-consult' : '');
                  $age = $item['date_of_birth'] ? date_diff(new DateTime($item['date_of_birth']), new DateTime())->y : '—';
                ?>
                <div class="queue-card <?= $cardCls ?>">
                  <!-- Serial Number Badge -->
                  <div class="serial-badge <?= $serialCls ?>">
                    <span style="font-size:9px; text-transform:uppercase; letter-spacing:0.5px;">SERIAL</span>
                    <span style="font-size:1.35rem; line-height:1;"><?= str_pad($item['serial_number'], 2, '0', STR_PAD_LEFT) ?></span>
                  </div>

                  <!-- Patient Info -->
                  <div style="flex:1; min-width:220px;">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                      <h4 style="font-size:1.05rem; font-weight:800; color:#0F172A; margin:0;">
                        <?= htmlspecialchars($item['patient_name']) ?>
                      </h4>
                      <span class="badge" style="background:#E2E8F0; color:#1E293B; font-family:monospace; font-size:11px;">
                        <?= htmlspecialchars($item['patient_uid']) ?>
                      </span>
                      <?php if ($isEm): ?>
                        <span class="badge" style="background:#FEE2E2; color:#991B1B; font-weight:800;">
                          <i class="bi bi-exclamation-triangle-fill"></i> STAT EMERGENCY
                        </span>
                      <?php elseif ($item['priority'] === 'URGENT'): ?>
                        <span class="badge" style="background:#FEF3C7; color:#92400E; font-weight:700;">URGENT</span>
                      <?php endif; ?>
                    </div>

                    <div style="font-size:12px; color:#475569; margin-top:3px;">
                      Age: <strong><?= $age ?> yrs</strong> &bull;
                      Gender: <?= htmlspecialchars($item['gender'] ?? '—') ?> &bull;
                      Blood: <strong style="color:#DC2626;"><?= htmlspecialchars($item['blood_group'] ?: 'Unknown') ?></strong> &bull;
                      Slot: <span style="color:#0F766E;"><?= htmlspecialchars($item['time_slot']) ?></span>
                    </div>

                    <?php if (!empty($item['problem_description'])): ?>
                      <div style="font-size:12px; color:#334155; margin-top:5px; background:rgba(255,255,255,0.7); padding:4px 8px; border-radius:6px; display:inline-block; border:1px solid rgba(0,0,0,0.06);">
                        <strong>Complaint:</strong> <?= htmlspecialchars($item['problem_description']) ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Status Pill -->
                  <div>
                    <?php if ($item['status'] === 'COMPLETED'): ?>
                      <span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Completed</span>
                    <?php elseif ($item['status'] === 'IN_CONSULTATION'): ?>
                      <span class="badge badge-info"><i class="bi bi-gear-fill"></i> In Chamber</span>
                    <?php elseif ($item['status'] === 'CANCELLED'): ?>
                      <span class="badge badge-danger">Cancelled</span>
                    <?php else: ?>
                      <span class="badge badge-warning"><i class="bi bi-clock"></i> Waiting in Queue</span>
                    <?php endif; ?>
                  </div>

                  <!-- Actions -->
                  <div style="display:flex; gap:8px; align-items:center;">
                    <?php if (!$isDone && $item['status'] !== 'CANCELLED'): ?>
                      <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="start_consultation">
                        <input type="hidden" name="appointment_id" value="<?= $item['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#0F766E,#0D9488); border:none; padding:7px 14px; font-weight:700;">
                          <i class="bi bi-stethoscope"></i> Call Patient (রোগী দেখুন)
                        </button>
                      </form>

                      <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="mark_completed">
                        <input type="hidden" name="appointment_id" value="<?= $item['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm text-success" title="Mark Done">
                          <i class="bi bi-check2"></i>
                        </button>
                      </form>
                    <?php else: ?>
                      <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= $item['patient_id'] ?>" class="btn btn-outline btn-sm">
                        <i class="bi bi-folder2-open"></i> Record
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>

