<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/includes/session_bar.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireDoctorHospitalContext();

$db = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];

// Active patient session & clinical briefing
$activePatientSession = getDoctorActivePatientSession($db, $doctorId, $hospitalId);
$activeClinicalSummary = $activePatientSession ? getPatientExecutiveClinicalSummary($db, (int)$activePatientSession['patient_id']) : null;

// Handle quick hospital switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_hospital_id'])) {
    validateCsrf();
    $targetHospId = (int)$_POST['switch_hospital_id'];

    $stmtSwitch = $db->prepare("
        SELECT dh.*, h.name AS hospital_name
        FROM doctor_hospitals dh
        JOIN hospitals h ON dh.hospital_id = h.id
        WHERE dh.doctor_id = ? AND dh.hospital_id = ? AND dh.status = 'APPROVED'
    ");
    $stmtSwitch->execute([$doctorId, $targetHospId]);
    $switched = $stmtSwitch->fetch();

    if ($switched) {
        $_SESSION['hospital_id']   = $switched['hospital_id'];
        $_SESSION['hospital_name'] = $switched['hospital_name'];
        $_SESSION['department_id'] = $switched['department_id'] ?? null;
        $_SESSION['selected_hospital_id']   = $switched['hospital_id'];
        $_SESSION['selected_hospital_name'] = $switched['hospital_name'];

        AuditService::log('DOCTOR_SWITCH_HOSPITAL', [
            'user_id'    => $_SESSION['user_id'],
            'doctor_id'  => $doctorId,
            'hospital_id'=> $targetHospId,
            'metadata'   => ['switched_to' => $switched['hospital_name']]
        ]);

        header('Location: ' . APP_URL . '/doctor/dashboard.php?switched=1');
        exit;
    }
}

// Doctor profile
$stmt = $db->prepare("
    SELECT d.*, u.email, u.phone, u.last_login_at,
           h.name AS hospital_name, h.type AS hospital_type, h.city AS hospital_city,
           dh.designation AS affiliation_designation, dh.department_id, dh.employment_type,
           dept.name AS department_name
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    JOIN doctor_hospitals dh ON dh.doctor_id = d.id AND dh.hospital_id = ?
    JOIN hospitals h ON h.id = ?
    LEFT JOIN departments dept ON dept.id = dh.department_id
    WHERE d.id = ?
");
$stmt->execute([$hospitalId, $hospitalId, $doctorId]);
$doctor = $stmt->fetch();

// Fetch all active hospital affiliations for this doctor
$stmtAllAffils = $db->prepare("
    SELECT dh.*, h.name AS hospital_name, h.hospital_uid, h.type AS hospital_type, h.city AS hospital_city,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN hospitals h ON dh.hospital_id = h.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.doctor_id = ? AND dh.status = 'APPROVED'
    ORDER BY h.name ASC
");
$stmtAllAffils->execute([$doctorId]);
$affiliatedHospitals = $stmtAllAffils->fetchAll();

// Stats
$stmt = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM consultations WHERE doctor_id = :d1 AND hospital_id = :h1 AND DATE(consultation_date) = CURDATE()) AS today_consultations,
        (SELECT COUNT(*) FROM consultations WHERE doctor_id = :d2 AND hospital_id = :h2) AS total_consultations,
        (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = :d3 AND hospital_id = :h3 AND status = 'ACTIVE') AS active_prescriptions,
        (SELECT COUNT(*) FROM access_sessions WHERE doctor_id = :d4 AND status = 'ACTIVE' AND expires_at > NOW()) AS active_sessions,
        (SELECT COUNT(*) FROM access_requests WHERE doctor_id = :d5 AND status = 'PENDING') AS pending_requests
");
$stmt->execute([
    ':d1' => $doctorId, ':h1' => $hospitalId,
    ':d2' => $doctorId, ':h2' => $hospitalId,
    ':d3' => $doctorId, ':h3' => $hospitalId,
    ':d4' => $doctorId,
    ':d5' => $doctorId,
]);
$stats = $stmt->fetch();

// Recent consultations
$stmt = $db->prepare("
    SELECT c.*, p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           (SELECT COUNT(*) FROM patient_allergies pa WHERE pa.patient_id = p.id AND pa.severity IN ('SEVERE','LIFE_THREATENING')) AS critical_allergy_count
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    WHERE c.doctor_id = ? AND c.hospital_id = ?
    ORDER BY c.consultation_date DESC
    LIMIT 6
");
$stmt->execute([$doctorId, $hospitalId]);
$recentConsultations = $stmt->fetchAll();

// Active access sessions
$stmt = $db->prepare("
    SELECT acs.*, p.full_name AS patient_name, p.patient_uid
    FROM access_sessions acs
    JOIN patients p ON acs.patient_id = p.id
    WHERE acs.doctor_id = ? AND acs.status = 'ACTIVE' AND acs.expires_at > NOW()
    ORDER BY acs.expires_at ASC
    LIMIT 3
");
$stmt->execute([$doctorId]);
$activeSessions = $stmt->fetchAll();
foreach ($activeSessions as &$acs) {
    $acs['seconds_remaining'] = max(0, strtotime($acs['expires_at']) - time());
}
unset($acs);

// Recent prescriptions issued
$stmt = $db->prepare("
    SELECT rx.*, p.full_name AS patient_name, p.patient_uid,
           COUNT(pm.id) AS med_count
    FROM prescriptions rx
    JOIN patients p ON rx.patient_id = p.id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    WHERE rx.doctor_id = ? AND rx.hospital_id = ?
    GROUP BY rx.id
    ORDER BY rx.created_at DESC
    LIMIT 5
");
$stmt->execute([$doctorId, $hospitalId]);
$recentPrescriptions = $stmt->fetchAll();

// Today's Patient Queue for this doctor and hospital
$todayDateStr = date('Y-m-d');
$stmtTodayQueue = $db->prepare("
    SELECT a.*, p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND a.hospital_id = ? AND a.appointment_date = ?
    ORDER BY
        CASE WHEN a.priority = 'EMERGENCY' THEN 1 WHEN a.priority = 'URGENT' THEN 2 ELSE 3 END ASC,
        a.serial_number ASC
    LIMIT 6
");
$stmtTodayQueue->execute([$doctorId, $hospitalId, $todayDateStr]);
$todayQueue = $stmtTodayQueue->fetchAll();

$stmtCountQueue = $db->prepare("
    SELECT COUNT(*) FROM appointments
    WHERE doctor_id = ? AND hospital_id = ? AND appointment_date = ? AND status IN ('CONFIRMED','WAITING','IN_CONSULTATION')
");
$stmtCountQueue->execute([$doctorId, $hospitalId, $todayDateStr]);
$waitingQueueCount = (int)$stmtCountQueue->fetchColumn();

// Log dashboard access
AuditService::log('DASHBOARD_VIEW', [
    'user_id'    => $_SESSION['user_id'],
    'doctor_id'  => $doctorId,
    'hospital_id'=> $hospitalId,
]);

$csrf = generateCsrfToken();

ob_start();
renderActivePatientSessionBar($activePatientSession, 'dashboard');
$portalTopBanner = ob_get_clean();

ob_start();
?>
<div class="mc-rail-context">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:.4rem">
    <span class="mc-rail-context-label">Active hospital</span>
    <?php if (count($affiliatedHospitals) > 1): ?>
      <button type="button" onclick="openHospitalModal()" style="background:none;border:0;padding:0;font-size:.75rem;font-weight:800;color:var(--accent);cursor:pointer">
        Switch (<?= count($affiliatedHospitals) ?>)
      </button>
    <?php endif; ?>
  </div>
  <div class="mc-rail-context-val"><i class="bi bi-building-fill-check"></i> <?= htmlspecialchars($doctor['hospital_name'] ?? '') ?></div>
  <div style="font-size:.75rem;color:var(--muted);margin-top:.15rem">
    <?= htmlspecialchars($doctor['affiliation_designation'] ?? 'Consultant') ?> · <?= htmlspecialchars($doctor['employment_type'] ?? 'FULL_TIME') ?>
  </div>
</div>
<?php
$portalRailContext = ob_get_clean();

$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $doctor['full_name'] ?? ($_SESSION['name'] ?? 'Doctor');
$portalMeta = ($doctor['department_name'] ?? $doctor['specialization'] ?? 'Physician') . ' · BMDC Verified';
$portalTitle = 'Chamber overview';
$portalSubtitle = ($doctor['hospital_name'] ?? '') . ' · ' . date('D, d M Y');
$portalNav = mc_doctor_nav('dashboard', $waitingQueueCount);
$portalTopAction = (count($affiliatedHospitals) > 1
    ? '<button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="openHospitalModal()"><i class="bi bi-arrow-left-right"></i> Switch hospital</button>'
    : '')
    . '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/doctor/patient-search.php"><i class="bi bi-search-heart-fill"></i> Find patient</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if (isset($_GET['switched'])): ?>
  <div class="mc-alert mc-alert-ok">Switched practice location to <strong><?= htmlspecialchars($doctor['hospital_name']) ?></strong>.</div>
<?php endif; ?>

<?php if ($activePatientSession && !empty($activeClinicalSummary)): ?>
  <div style="margin-bottom:1rem">
    <?php renderExecutiveClinicalSummary($activeClinicalSummary, true); ?>
  </div>
<?php else: ?>
  <div class="mc-panel" style="background:linear-gradient(135deg,#0b2538 0%,#0f766e 100%);color:#fff;border:0">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
      <div>
        <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#a7f3d0">
          <i class="bi bi-person-bounding-box"></i> Chamber ready
        </div>
        <h2 style="font-size:1.28rem;margin:.3rem 0;color:#fff">Ready for next patient consultation</h2>
        <p style="margin:0;opacity:.88;font-size:.9rem;max-width:36rem">
          Search by Patient UID (e.g. PT-000001), phone number, or NID to unlock records &amp; clinical summary.
        </p>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?= APP_URL ?>/doctor/queue.php" class="mc-btn mc-btn-secondary mc-btn-sm">
          <i class="bi bi-people-fill"></i> Live queue (<?= $waitingQueueCount ?>)
        </a>
        <a href="<?= APP_URL ?>/doctor/patient-search.php" class="mc-btn mc-btn-soft mc-btn-sm">
          <i class="bi bi-search-heart-fill"></i> Find patient
        </a>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Today consults</div><div class="v"><?= (int)($stats['today_consultations'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Hospital total</div><div class="v"><?= (int)($stats['total_consultations'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Active Rx</div><div class="v"><?= (int)($stats['active_prescriptions'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Hospitals</div><div class="v"><?= count($affiliatedHospitals) ?></div></div>
</div>

<?php if (!empty($activeSessions)): ?>
  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Active record sessions</h2></div>
    <div class="mc-list">
      <?php foreach ($activeSessions as $s): ?>
        <?php $mins = max(0, (int)ceil($s['seconds_remaining'] / 60)); ?>
        <div class="mc-list-item" style="background:#fff">
          <div>
            <strong><?= htmlspecialchars($s['patient_name']) ?></strong>
            <div style="font-size:.84rem;color:var(--muted)">
              <span class="mc-mono"><?= htmlspecialchars($s['patient_uid']) ?></span>
              · Time left: <strong id="timer-<?= (int)$s['id'] ?>"><?= str_pad((string)$mins, 2, '0', STR_PAD_LEFT) ?>:00</strong>
            </div>
          </div>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <a href="<?= APP_URL ?>/doctor/medical-record.php?patient=<?= (int)$s['patient_id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Open record</a>
            <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient=<?= (int)$s['patient_id'] ?>" class="mc-btn mc-btn-primary mc-btn-sm">Issue Rx</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="lab-token-bar">
  <label for="quick-patient-q">Quick patient lookup (all searches are audit-logged)</label>
  <form method="GET" action="<?= APP_URL ?>/doctor/patient-search.php" class="lab-token-form">
    <input id="quick-patient-q" type="text" name="q" required autocomplete="off"
           style="text-transform:none;letter-spacing:normal;font-family:inherit;font-size:.95rem"
           placeholder="Enter Phone (017...), Patient UID (PT-000001), or NID...">
    <button type="submit" class="mc-btn mc-btn-primary"><i class="bi bi-search"></i> Find patient</button>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Today's consultation queue (আজকের রোগী সিরিয়াল)</h2>
    <div style="display:flex;gap:.5rem;align-items:center">
      <span class="lab-status info"><?= $waitingQueueCount ?> waiting</span>
      <a href="<?= APP_URL ?>/doctor/queue.php" class="mc-btn mc-btn-secondary mc-btn-sm">Open full queue</a>
    </div>
  </div>
  <?php if (empty($todayQueue)): ?>
    <p style="margin:0;color:var(--muted)">No patients queued for today yet. <a href="<?= APP_URL ?>/doctor/queue.php" style="color:var(--accent);font-weight:700">Add walk-in / emergency</a></p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Serial</th>
            <th>Patient</th>
            <th>ID</th>
            <th>Priority</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($todayQueue as $q):
            $pCls = $q['priority'] === 'EMERGENCY' ? 'danger' : ($q['priority'] === 'URGENT' ? 'warn' : '');
            $sCls = $q['status'] === 'COMPLETED' ? 'ok' : ($q['status'] === 'IN_CONSULTATION' ? 'info' : 'warn');
          ?>
            <tr>
              <td class="mc-mono" style="color:<?= $q['priority'] === 'EMERGENCY' ? '#b42318' : 'var(--accent)' ?>">#<?= str_pad((string)$q['serial_number'], 2, '0', STR_PAD_LEFT) ?></td>
              <td><strong><?= htmlspecialchars($q['patient_name']) ?></strong></td>
              <td class="mc-mono"><?= htmlspecialchars($q['patient_uid']) ?></td>
              <td><span class="lab-status <?= $pCls ?>"><?= htmlspecialchars($q['priority']) ?></span></td>
              <td><span class="lab-status <?= $sCls ?>"><?= htmlspecialchars(str_replace('_', ' ', $q['status'])) ?></span></td>
              <td style="text-align:right">
                <a href="<?= APP_URL ?>/doctor/queue.php" class="mc-btn mc-btn-primary mc-btn-sm">Call patient</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>My hospital practice network</h2>
    <a href="<?= APP_URL ?>/doctor/request-affiliation.php" style="font-weight:800;color:var(--accent);font-size:.88rem">Request affiliation</a>
  </div>
  <div class="mc-grid-3">
    <?php foreach ($affiliatedHospitals as $aff): ?>
      <?php $isCurrent = ($aff['hospital_id'] == $hospitalId); ?>
      <div class="mc-list-item" style="background:<?= $isCurrent ? 'var(--accent-soft)' : '#fff' ?>;border-color:<?= $isCurrent ? 'var(--accent)' : 'var(--line)' ?>">
        <div>
          <strong><?= htmlspecialchars($aff['hospital_name']) ?></strong>
          <div style="font-size:.82rem;color:var(--muted)">
            <?= htmlspecialchars($aff['department_name'] ?? 'General') ?> · <?= htmlspecialchars($aff['employment_type']) ?>
          </div>
        </div>
        <?php if (!$isCurrent): ?>
          <form method="POST" style="margin:0">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="switch_hospital_id" value="<?= (int)$aff['hospital_id'] ?>">
            <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm">Switch</button>
          </form>
        <?php else: ?>
          <span class="lab-status ok">In session</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="mc-grid-2">
  <div class="mc-panel">
    <div class="mc-panel-h">
      <h2>Recent consultations</h2>
      <a href="<?= APP_URL ?>/doctor/consultations.php" style="font-weight:800;color:var(--accent);font-size:.88rem">View all</a>
    </div>
    <?php if (empty($recentConsultations)): ?>
      <p style="margin:0;color:var(--muted)">No consultations at <?= htmlspecialchars($doctor['hospital_name']) ?> yet.</p>
    <?php else: ?>
      <div class="mc-list">
        <?php foreach ($recentConsultations as $cons): ?>
          <?php $age = date_diff(new DateTime($cons['date_of_birth']), new DateTime())->y; ?>
          <div class="mc-list-item" style="background:#fff">
            <div>
              <strong><?= htmlspecialchars($cons['patient_name']) ?></strong>
              <div style="font-size:.82rem;color:var(--muted)">
                <?= htmlspecialchars($cons['patient_uid']) ?> · Age <?= $age ?> · <?= ucfirst(strtolower($cons['gender'])) ?>
                <?php if ($cons['critical_allergy_count'] > 0): ?>
                  · <span style="color:var(--danger);font-weight:800">Allergy</span>
                <?php endif; ?>
              </div>
              <div style="font-size:.78rem;color:var(--muted)"><?= date('d M, h:i A', strtotime($cons['consultation_date'])) ?></div>
            </div>
            <a href="<?= APP_URL ?>/doctor/patient-search.php?q=<?= urlencode($cons['patient_uid']) ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Open</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="mc-panel">
    <div class="mc-panel-h">
      <h2>Recent prescriptions</h2>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php" style="font-weight:800;color:var(--accent);font-size:.88rem">View all</a>
    </div>
    <?php if (empty($recentPrescriptions)): ?>
      <p style="margin:0;color:var(--muted)">No prescriptions issued yet.</p>
    <?php else: ?>
      <div class="mc-list">
        <?php foreach ($recentPrescriptions as $rx): ?>
          <a class="mc-list-item" href="<?= APP_URL ?>/doctor/prescriptions.php?rx=<?= urlencode($rx['prescription_uid']) ?>" style="background:#fff">
            <div>
              <div class="mc-mono" style="color:var(--accent);font-size:.8rem"><?= htmlspecialchars($rx['prescription_uid']) ?></div>
              <strong><?= htmlspecialchars($rx['patient_name']) ?></strong>
              <div style="font-size:.82rem;color:var(--muted)"><?= (int)$rx['med_count'] ?> med(s) · <?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?></div>
            </div>
            <span class="lab-status <?= $rx['status'] === 'ACTIVE' ? 'ok' : '' ?>"><?= htmlspecialchars($rx['status']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- HOSPITAL SWITCH MODAL -->
<div id="hospital-modal" class="mc-modal-backdrop" role="dialog" aria-modal="true" aria-label="Switch hospital">
  <div class="mc-modal">
    <div class="mc-modal-head">
      <h3><i class="bi bi-hospital" style="color:var(--accent)"></i> Switch Clinical Hospital</h3>
      <button type="button" class="mc-modal-close" onclick="closeHospitalModal()" aria-label="Close">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <p style="font-size:.9rem;color:var(--muted);margin:0 0 1rem">Select the hospital where you are conducting consultations right now:</p>
    <div class="mc-list">
      <?php foreach ($affiliatedHospitals as $aff): ?>
        <?php $isCurrent = ($aff['hospital_id'] == $hospitalId); ?>
        <form method="POST" style="margin:0">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="switch_hospital_id" value="<?= (int)$aff['hospital_id'] ?>">
          <button type="submit" class="mc-list-item" style="width:100%;text-align:left;cursor:pointer;background:<?= $isCurrent ? 'var(--accent-soft)' : '#fff' ?>;border-color:<?= $isCurrent ? 'var(--accent)' : 'var(--line)' ?>">
            <div>
              <strong><?= htmlspecialchars($aff['hospital_name']) ?></strong>
              <div style="font-size:.82rem;color:var(--muted)">
                <?= htmlspecialchars($aff['department_name'] ?? 'General') ?>
                · <?= htmlspecialchars($aff['employment_type']) ?>
                · <?= htmlspecialchars($aff['hospital_city'] ?? '') ?>
              </div>
            </div>
            <?php if ($isCurrent): ?>
              <span class="lab-status ok">Active</span>
            <?php else: ?>
              <span class="mc-btn mc-btn-secondary mc-btn-sm">Switch →</span>
            <?php endif; ?>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function openHospitalModal(){ document.getElementById('hospital-modal')?.classList.add('open'); }
function closeHospitalModal(){ document.getElementById('hospital-modal')?.classList.remove('open'); }
document.addEventListener('DOMContentLoaded', function() {
  <?php foreach ($activeSessions as $s): ?>
  if (typeof startAccessTimer === 'function') {
    startAccessTimer('timer-<?= (int)$s['id'] ?>', <?= max(0, (int)$s['seconds_remaining']) ?>, function() { location.reload(); });
  }
  <?php endforeach; ?>
});
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
