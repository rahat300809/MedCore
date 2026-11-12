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
        WHERE p.patient_uid LIKE ? OR p.full_name LIKE ? OR u.phone LIKE ? OR p.nid_last4 LIKE ?
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
           u.phone AS patient_phone,
           dept.name AS department_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON u.id = p.user_id
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

require_once __DIR__ . '/../app/views/portal/navs.php';
$waitingQueueCount = mc_doctor_shell_setup($db, $doctorId, $hospitalId, $activePatientSession, 'queue');

$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $doctorName;
$portalMeta = $hospitalName . ' · Live queue';
$portalTitle = 'Live patient queue';
$portalSubtitle = 'আজকের রোগী সিরিয়াল · ' . date('d M Y', strtotime($selectedDate));
$portalNav = mc_doctor_nav('queue', $waitingQueueCount);
ob_start();
?>
<form method="GET" style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
  <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()"
         style="border:1px solid var(--line);border-radius:10px;padding:.45rem .7rem;font:inherit;font-size:.86rem;background:#fff">
  <?php if ($selectedDate !== $today): ?>
    <a href="?date=<?= $today ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Today</a>
  <?php endif; ?>
</form>
<?php
$portalTopAction = ob_get_clean();
$extraHead = <<<'CSS'
<style>
  .queue-card {
    background:#fff;border:1px solid var(--line);border-radius:14px;padding:1rem 1.15rem;
    margin-bottom:.75rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;
    transition:border-color .15s ease,box-shadow .15s ease;
  }
  .queue-card:hover { border-color:var(--accent); box-shadow:var(--shadow); }
  .queue-card.emergency-card { border:2px solid #dc2626; background:#fef2f2; }
  .queue-card.in-consult-card { border:2px solid #0284c7; background:#f0f9ff; }
  .serial-badge {
    width:54px;height:54px;border-radius:12px;background:#f1f5f9;color:#0f172a;
    display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:900;flex-shrink:0;
  }
  .serial-badge.emergency { background:#dc2626;color:#fff; }
  .serial-badge.in-consult { background:#0284c7;color:#fff; }
</style>
CSS;
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($message): ?>
  <div class="mc-alert mc-alert-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mc-alert mc-alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Total serials</div><div class="v"><?= (int)$totalInQueue ?></div></div>
  <div class="mc-stat"><div class="l">Waiting</div><div class="v" style="color:var(--warn)"><?= (int)$waitingCount ?></div></div>
  <div class="mc-stat"><div class="l">In consultation</div><div class="v" style="color:#0284c7"><?= (int)$inConsultCount ?></div></div>
  <div class="mc-stat"><div class="l">Emergency / STAT</div><div class="v" style="color:var(--danger)"><?= (int)$emergencyCount ?></div></div>
</div>

<div class="mc-panel" style="border:1.5px dashed #0284c7;background:#f8fbfd">
  <div class="mc-panel-h">
    <h2><i class="bi bi-shield-exclamation" style="color:var(--danger)"></i> Emergency walk-in &amp; manual patient search</h2>
  </div>
  <p style="margin:0 0 .75rem;font-size:.88rem;color:var(--muted)">
    Search by MedCore ID (e.g. <code class="mc-mono">PT-000001</code>), Phone, or NID to add immediately as STAT Emergency.
  </p>
  <form method="GET" class="lab-token-form">
    <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
    <input type="text" name="patient_search" placeholder="Patient UID, Phone, or NID..." value="<?= htmlspecialchars($searchPatientQuery) ?>"
           style="text-transform:none;letter-spacing:normal;font-family:inherit;font-size:.95rem">
    <button type="submit" class="mc-btn mc-btn-primary">Search patient</button>
    <?php if (!empty($searchPatientQuery)): ?>
      <a href="?date=<?= urlencode($selectedDate) ?>" class="mc-btn mc-btn-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!empty($foundPatients)): ?>
    <div class="mc-list" style="margin-top:.85rem">
      <?php foreach ($foundPatients as $fp): ?>
        <div class="mc-list-item" style="background:#fff">
          <div>
            <strong><?= htmlspecialchars($fp['full_name']) ?></strong>
            <span class="lab-status info" style="margin-left:.35rem"><?= htmlspecialchars($fp['patient_uid']) ?></span>
            <div style="font-size:.82rem;color:var(--muted);margin-top:.2rem">
              Blood: <?= htmlspecialchars($fp['blood_group'] ?: 'Unknown') ?> · Phone: <?= htmlspecialchars($fp['phone'] ?: $fp['user_phone']) ?>
            </div>
          </div>
          <form method="POST" style="display:flex;gap:.45rem;flex-wrap:wrap;align-items:center">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_emergency_patient">
            <input type="hidden" name="target_patient_id" value="<?= (int)$fp['id'] ?>">
            <input type="text" name="emergency_notes" placeholder="Emergency symptoms..." required
                   style="border:1px solid var(--line);border-radius:9px;padding:.45rem .75rem;font:inherit;font-size:.85rem;min-width:190px">
            <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm"><i class="bi bi-plus-circle-fill"></i> Add STAT</button>
            <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= (int)$fp['id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Record</a>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php elseif (!empty($searchPatientQuery)): ?>
    <div class="mc-alert mc-alert-danger" style="margin-top:.75rem;margin-bottom:0">No patient found matching "<?= htmlspecialchars($searchPatientQuery) ?>".</div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Patient queue (<?= count($queueList) ?>)</h2>
    <span class="lab-status info">Priority: Emergency first, then serial</span>
  </div>
  <?php if (empty($queueList)): ?>
    <div style="text-align:center;padding:2rem 1rem">
      <h3 style="margin:0 0 .35rem">No patients booked for this date</h3>
      <p style="margin:0;color:var(--muted);font-size:.9rem">Online bookings appear automatically, or add a walk-in patient above.</p>
    </div>
  <?php else: ?>
    <?php foreach ($queueList as $item):
      $isEm = ($item['priority'] === 'EMERGENCY');
      $isInConsult = ($item['status'] === 'IN_CONSULTATION');
      $isDone = ($item['status'] === 'COMPLETED');
      $cardCls = $isEm ? 'emergency-card' : ($isInConsult ? 'in-consult-card' : '');
      $serialCls = $isEm ? 'emergency' : ($isInConsult ? 'in-consult' : '');
      $age = $item['date_of_birth'] ? date_diff(new DateTime($item['date_of_birth']), new DateTime())->y : '—';
    ?>
      <div class="queue-card <?= $cardCls ?>">
        <div style="display:flex;align-items:center;gap:.9rem;flex:1;min-width:240px">
          <div class="serial-badge <?= $serialCls ?>">
            <span style="font-size:9px;letter-spacing:.05em">SERIAL</span>
            <span style="font-size:1.3rem;line-height:1">#<?= str_pad((string)$item['serial_number'], 2, '0', STR_PAD_LEFT) ?></span>
          </div>
          <div>
            <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
              <strong style="font-size:1.05rem"><?= htmlspecialchars($item['patient_name']) ?></strong>
              <span class="mc-mono" style="font-size:.78rem;background:#f1f5f9;padding:.15rem .45rem;border-radius:6px"><?= htmlspecialchars($item['patient_uid']) ?></span>
              <?php if ($isEm): ?>
                <span class="lab-status danger">STAT EMERGENCY</span>
              <?php elseif ($item['priority'] === 'URGENT'): ?>
                <span class="lab-status warn">URGENT</span>
              <?php endif; ?>
            </div>
            <div style="font-size:.84rem;color:var(--muted);margin-top:.2rem">
              Age <?= $age ?> yrs · <?= htmlspecialchars($item['gender'] ?? '—') ?>
              · Blood <strong style="color:var(--danger)"><?= htmlspecialchars($item['blood_group'] ?: 'Unknown') ?></strong>
              · Slot <strong style="color:var(--accent)"><?= htmlspecialchars($item['time_slot']) ?></strong>
            </div>
            <?php if (!empty($item['problem_description'])): ?>
              <div style="font-size:.82rem;color:var(--ink-2);margin-top:.35rem">
                <strong>Complaint:</strong> <?= htmlspecialchars($item['problem_description']) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
          <?php if ($item['status'] === 'COMPLETED'): ?>
            <span class="lab-status ok">Completed</span>
          <?php elseif ($item['status'] === 'IN_CONSULTATION'): ?>
            <span class="lab-status info">In chamber</span>
          <?php elseif ($item['status'] === 'CANCELLED'): ?>
            <span class="lab-status danger">Cancelled</span>
          <?php else: ?>
            <span class="lab-status warn">Waiting</span>
          <?php endif; ?>

          <?php if (!$isDone && $item['status'] !== 'CANCELLED'): ?>
            <form method="POST" style="margin:0">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="start_consultation">
              <input type="hidden" name="appointment_id" value="<?= (int)$item['id'] ?>">
              <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm">
                <i class="bi bi-stethoscope"></i> Call patient
              </button>
            </form>
            <form method="POST" style="margin:0">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="mark_completed">
              <input type="hidden" name="appointment_id" value="<?= (int)$item['id'] ?>">
              <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm" title="Mark Done">
                <i class="bi bi-check2"></i> Done
              </button>
            </form>
          <?php else: ?>
            <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= (int)$item['patient_id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">
              <i class="bi bi-folder2-open"></i> Record
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>

