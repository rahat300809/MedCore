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
$patientId  = (int)($_GET['patient_id'] ?? $_GET['patient'] ?? 0);

if (!$patientId) {
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

// 1. Verify Active Access Session (CRITICAL SECURITY CHECK)
$stmtSession = $db->prepare("
    SELECT s.*
    FROM access_sessions s
    WHERE s.doctor_id = ? AND s.patient_id = ? AND s.hospital_id = ?
      AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ORDER BY s.expires_at DESC
    LIMIT 1
");
$stmtSession->execute([$doctorId, $patientId, $hospitalId]);
$accessSession = $stmtSession->fetch();
if ($accessSession) {
    $accessSession['seconds_remaining'] = max(0, strtotime($accessSession['expires_at']) - time());
}

if (!$accessSession) {
    AuditService::log(AuditService::UNAUTHORIZED_ACCESS, [
        'user_id'    => $_SESSION['user_id'],
        'doctor_id'  => $doctorId,
        'patient_id' => $patientId,
        'hospital_id'=> $hospitalId,
        'severity'   => 'WARNING',
        'metadata'   => ['reason' => 'No active consent session when accessing medical record'],
    ]);
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Active consent required. Please search and request patient access first.'];
    header('Location: ' . APP_URL . '/doctor/patient-search.php?q=PT-' . str_pad($patientId, 6, '0', STR_PAD_LEFT));
    exit;
}

// Handle revoke session from doctor side
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_session'])) {
    validateCsrf();
    $db->prepare("UPDATE access_sessions SET status = 'EXPIRED', revoked_at = NOW() WHERE id = ?")
       ->execute([$accessSession['id']]);
    AuditService::log('SESSION_ENDED_BY_DOCTOR', [
        'user_id'    => $_SESSION['user_id'],
        'doctor_id'  => $doctorId,
        'patient_id' => $patientId,
        'hospital_id'=> $hospitalId,
    ]);
    $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Medical record access session ended safely.'];
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

// 2. Fetch Patient Details
$stmtPatient = $db->prepare("
    SELECT p.*, u.email, u.phone, u.last_login_at
    FROM patients p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
");
$stmtPatient->execute([$patientId]);
$patient = $stmtPatient->fetch();

if (!$patient) {
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

$age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;
$clinicalSummary = getPatientExecutiveClinicalSummary($db, $patientId);

// Log authorized access
AuditService::log(AuditService::VIEW_MEDICAL_HISTORY, [
    'user_id'    => $_SESSION['user_id'],
    'doctor_id'  => $doctorId,
    'patient_id' => $patientId,
    'hospital_id'=> $hospitalId,
    'metadata'   => ['session_id' => $accessSession['id']],
]);

// 3. Fetch Medical History (Conditions)
$stmtHistory = $db->prepare("
    SELECT mh.*, d.full_name AS doctor_name
    FROM medical_histories mh
    LEFT JOIN doctors d ON mh.created_by = d.id
    WHERE mh.patient_id = ?
    ORDER BY mh.diagnosed_date DESC, mh.id DESC
");
$stmtHistory->execute([$patientId]);
$conditions = $stmtHistory->fetchAll();

// 4. Fetch Allergies
$stmtAllergies = $db->prepare("
    SELECT pa.*, a.name AS allergy_name, a.type AS allergy_type, a.description AS allergy_desc
    FROM patient_allergies pa
    JOIN allergies a ON a.id = pa.allergy_id
    WHERE pa.patient_id = ?
    ORDER BY FIELD(pa.severity, 'LIFE_THREATENING', 'SEVERE', 'MODERATE', 'MILD')
");
$stmtAllergies->execute([$patientId]);
$allergies = $stmtAllergies->fetchAll();

// 5. Fetch Active & Past Medications
$stmtMeds = $db->prepare("
    SELECT pm.*, m.generic_name, m.brand_name, m.strength, m.dosage_form,
           d.full_name AS doctor_name
    FROM patient_medications pm
    JOIN medications m ON pm.medication_id = m.id
    LEFT JOIN doctors d ON pm.prescribed_by = d.id
    WHERE pm.patient_id = ?
    ORDER BY FIELD(pm.status, 'ACTIVE', 'COMPLETED', 'DISCONTINUED'), pm.started_at DESC
");
$stmtMeds->execute([$patientId]);
$medications = $stmtMeds->fetchAll();

// 6. Fetch Prescriptions
$stmtRx = $db->prepare("
    SELECT rx.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name,
           (SELECT COUNT(*) FROM prescription_medicines pm WHERE pm.prescription_id = rx.id) AS medicine_count
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    WHERE rx.patient_id = ?
    ORDER BY rx.created_at DESC
");
$stmtRx->execute([$patientId]);
$prescriptions = $stmtRx->fetchAll();

// 7. Fetch Lab Reports
$stmtLabs = $db->prepare("
    SELECT * FROM lab_reports
    WHERE patient_id = ?
    ORDER BY test_date DESC
");
$stmtLabs->execute([$patientId]);
$labReports = $stmtLabs->fetchAll();

// 7.1 Fetch Prescription Lab Orders & Tokens
$stmtPatientLabOrders = $db->prepare("
    SELECT plo.*, l.name AS lab_name, h.name AS hospital_name, d.full_name AS doctor_name,
           (SELECT COUNT(*) FROM lab_order_tests lot WHERE lot.lab_order_id = plo.id) AS total_tests,
           (SELECT COUNT(*) FROM lab_order_tests lot2 WHERE lot2.lab_order_id = plo.id AND lot2.status = 'COMPLETED') AS done_tests
    FROM prescription_lab_orders plo
    LEFT JOIN laboratories l ON plo.laboratory_id = l.id
    LEFT JOIN hospitals h ON plo.hospital_id = h.id
    LEFT JOIN doctors d ON plo.doctor_id = d.id
    WHERE plo.patient_id = ?
    ORDER BY plo.created_at DESC
");
$stmtPatientLabOrders->execute([$patientId]);
$patientLabOrders = $stmtPatientLabOrders->fetchAll();

// 8. Fetch Consultations History
$stmtCons = $db->prepare("
    SELECT c.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name,
           dept.name AS department_name
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN hospitals h ON c.hospital_id = h.id
    LEFT JOIN departments dept ON c.department_id = dept.id
    WHERE c.patient_id = ?
    ORDER BY c.consultation_date DESC
");
$stmtCons->execute([$patientId]);
$consultations = $stmtCons->fetchAll();

$hasCriticalAllergy = false;
foreach ($allergies as $alg) {
    if (in_array($alg['severity'], ['SEVERE', 'LIFE_THREATENING'])) {
        $hasCriticalAllergy = true;
        break;
    }
}
?>
<?php
require_once __DIR__ . '/../app/views/portal/navs.php';
$waitingQueueCount = mc_doctor_shell_setup($db, $doctorId, $hospitalId, $accessSession, 'medical-record');
$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $_SESSION['name'] ?? 'Doctor';
$portalMeta = ($_SESSION['hospital_name'] ?? '') . ' · Medical record';
$portalTitle = $patient['full_name'] ?? 'Medical record';
$portalSubtitle = ($patient['patient_uid'] ?? '') . ' · Authorized clinical record';
$portalNav = mc_doctor_nav('search', $waitingQueueCount);
ob_start();
?>
<div style="display:flex;gap:.45rem;flex-wrap:wrap;align-items:center">
  <span class="lab-status warn"><i class="bi bi-hourglass-split"></i> <strong id="timer-text" class="mc-mono">--:--</strong></span>
  <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="mc-btn mc-btn-primary mc-btn-sm">
    <i class="bi bi-plus-circle-fill"></i> New Rx
  </a>
  <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Are you sure you want to end this medical record session?');">
    <?= csrfField() ?>
    <input type="hidden" name="end_session" value="1">
    <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm"><i class="bi bi-x-circle"></i> End session</button>
  </form>
</div>
<?php
$portalTopAction = ob_get_clean();
$extraHead = <<<'CSS'
<style>
  .tab-pane { display:none; }
  .tab-pane.active { display:block; }
  .record-stats { display:grid; gap:.75rem; grid-template-columns:repeat(2,1fr); margin-bottom:1rem; }
  @media (min-width:900px) { .record-stats { grid-template-columns:repeat(5,1fr); } }
</style>
CSS;
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($hasCriticalAllergy): ?>
  <div class="mc-alert mc-alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>CRITICAL CLINICAL ALERT:</strong> Severe/life-threatening allergies documented. Review the Allergies tab before prescribing.
  </div>
<?php endif; ?>

<div class="mc-panel" style="background:linear-gradient(135deg,#0b2538 0%,#0f766e 100%);color:#fff;border:0">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
    <div>
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <h2 style="font-size:1.4rem;color:#fff;margin:0"><?= htmlspecialchars($patient['full_name']) ?></h2>
        <span class="mc-mono" style="background:rgba(255,255,255,.18);padding:.2rem .55rem;border-radius:7px;font-size:.82rem"><?= htmlspecialchars($patient['patient_uid']) ?></span>
        <span style="background:#059669;color:#fff;padding:.2rem .55rem;border-radius:999px;font-size:.74rem;font-weight:800"><i class="bi bi-shield-check"></i> Consent verified</span>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:.5rem 1.2rem;font-size:.88rem;opacity:.92;margin-top:.55rem">
        <span><?= $age ?> yrs (<?= date('d M Y', strtotime($patient['date_of_birth'])) ?>) · <?= ucfirst(strtolower($patient['gender'])) ?></span>
        <span>Blood: <strong><?= htmlspecialchars($patient['blood_group'] ?? 'Unknown') ?></strong></span>
        <span>NID: ●●●● <?= htmlspecialchars($patient['nid_last4']) ?></span>
        <span>Emergency: <?= htmlspecialchars($patient['emergency_contact_name'] ?? 'N/A') ?> (<?= htmlspecialchars($patient['emergency_contact_phone'] ?? 'N/A') ?>)</span>
      </div>
    </div>
    <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="mc-btn mc-btn-secondary">
      <i class="bi bi-prescription2"></i> Start consultation &amp; Rx
    </a>
  </div>
</div>

<div class="record-stats">
  <div class="mc-stat"><div class="l">Conditions</div><div class="v"><?= count($conditions) ?></div></div>
  <div class="mc-stat"><div class="l">Medications</div><div class="v"><?= count($medications) ?></div></div>
  <div class="mc-stat"><div class="l">Allergies</div><div class="v" style="color:<?= $hasCriticalAllergy ? 'var(--danger)' : 'var(--accent)' ?>"><?= count($allergies) ?></div></div>
  <div class="mc-stat"><div class="l">Prescriptions</div><div class="v"><?= count($prescriptions) ?></div></div>
  <div class="mc-stat"><div class="l">Lab reports</div><div class="v"><?= count($labReports) ?></div></div>
</div>

<div class="mc-tabs" role="tablist">
  <button type="button" class="mc-tab active" onclick="switchTab('tab-summary', this)"><i class="bi bi-stars"></i> Executive summary</button>
  <button type="button" class="mc-tab" onclick="switchTab('tab-conditions', this)"><i class="bi bi-heart-pulse-fill"></i> History <span class="mc-tab-count"><?= count($conditions) ?></span></button>
  <button type="button" class="mc-tab" onclick="switchTab('tab-allergies', this)"><i class="bi bi-exclamation-octagon-fill"></i> Allergies <span class="mc-tab-count"><?= count($allergies) ?></span></button>
  <button type="button" class="mc-tab" onclick="switchTab('tab-medications', this)"><i class="bi bi-capsule-pill"></i> Medications <span class="mc-tab-count"><?= count($medications) ?></span></button>
  <button type="button" class="mc-tab" onclick="switchTab('tab-prescriptions', this)"><i class="bi bi-prescription2"></i> Prescriptions <span class="mc-tab-count"><?= count($prescriptions) ?></span></button>
  <button type="button" class="mc-tab" onclick="switchTab('tab-labs', this)"><i class="bi bi-file-earmark-medical"></i> Lab reports <span class="mc-tab-count"><?= count($labReports) ?></span></button>
  <button type="button" class="mc-tab" onclick="switchTab('tab-consultations', this)"><i class="bi bi-journal-medical"></i> Consultations <span class="mc-tab-count"><?= count($consultations) ?></span></button>
</div>

<div id="tab-summary" class="tab-pane active">
  <?php renderExecutiveClinicalSummary($clinicalSummary, false); ?>
</div>

<div id="tab-conditions" class="tab-pane">
  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Chronic illnesses &amp; diagnosed conditions</h2><span class="lab-status info"><?= count($conditions) ?> records</span></div>
    <?php if (empty($conditions)): ?>
      <p style="margin:0;color:var(--muted)">No prior chronic conditions or diagnoses recorded.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="lab-table">
          <thead><tr><th>Condition</th><th>ICD</th><th>Status</th><th>Date</th><th>By</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach ($conditions as $cond): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($cond['condition_name']) ?></strong>
                  <?php if ($cond['description']): ?><div style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($cond['description']) ?></div><?php endif; ?>
                </td>
                <td class="mc-mono"><?= htmlspecialchars($cond['icd_code'] ?? 'N/A') ?></td>
                <td><span class="lab-status <?= in_array($cond['status'], ['ACTIVE','CHRONIC']) ? 'warn' : 'ok' ?>"><?= htmlspecialchars($cond['status']) ?></span></td>
                <td><?= $cond['diagnosed_date'] ? date('d M Y', strtotime($cond['diagnosed_date'])) : 'Undated' ?></td>
                <td><?= htmlspecialchars($cond['doctor_name'] ? 'Dr. ' . $cond['doctor_name'] : 'System') ?></td>
                <td><?= htmlspecialchars($cond['notes'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="tab-allergies" class="tab-pane">
  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Allergies &amp; adverse drug reactions</h2><span class="lab-status danger"><?= count($allergies) ?> documented</span></div>
    <?php if (empty($allergies)): ?>
      <p style="margin:0;color:var(--muted)">No known drug, food, or environmental allergies documented.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="lab-table">
          <thead><tr><th>Allergen</th><th>Category</th><th>Severity</th><th>Reaction</th><th>Verification</th></tr></thead>
          <tbody>
            <?php foreach ($allergies as $alg):
              $sev = $alg['severity'] ?? '';
              $sCls = in_array($sev, ['LIFE_THREATENING', 'SEVERE']) ? 'danger' : ($sev === 'MODERATE' ? 'warn' : 'info');
            ?>
              <tr style="<?= $sCls === 'danger' ? 'background:#fef2f2' : '' ?>">
                <td><strong style="<?= $sCls === 'danger' ? 'color:var(--danger)' : '' ?>"><?= htmlspecialchars($alg['allergy_name']) ?></strong></td>
                <td><span class="lab-status"><?= htmlspecialchars($alg['allergy_type']) ?></span></td>
                <td><span class="lab-status <?= $sCls ?>"><?= htmlspecialchars(str_replace('_', ' ', $sev)) ?></span></td>
                <td><?= htmlspecialchars($alg['reaction'] ?? 'General sensitivity') ?></td>
                <td><span class="lab-status <?= $alg['verified'] ? 'ok' : '' ?>"><?= $alg['verified'] ? 'Verified' : 'Patient reported' ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="tab-medications" class="tab-pane">
  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Current &amp; past medications</h2><span class="lab-status info"><?= count($medications) ?> total</span></div>
    <?php if (empty($medications)): ?>
      <p style="margin:0;color:var(--muted)">No medications currently recorded.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="lab-table">
          <thead><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Route</th><th>Status</th><th>Started</th><th>Prescriber</th></tr></thead>
          <tbody>
            <?php foreach ($medications as $med): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($med['brand_name'] ?: $med['generic_name']) ?></strong>
                  <?php if ($med['brand_name'] && $med['generic_name']): ?>
                    <div style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($med['generic_name']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($med['dosage'] ?? $med['strength'] ?? 'Standard') ?> · <?= htmlspecialchars($med['dosage_form']) ?></td>
                <td><span class="lab-status"><?= htmlspecialchars($med['frequency'] ?? 'As directed') ?></span></td>
                <td><?= htmlspecialchars($med['route']) ?></td>
                <td><span class="lab-status <?= $med['status'] === 'ACTIVE' ? 'ok' : ($med['status'] === 'COMPLETED' ? '' : 'danger') ?>"><?= htmlspecialchars($med['status']) ?></span></td>
                <td><?= $med['started_at'] ? date('d M Y', strtotime($med['started_at'])) : '—' ?></td>
                <td><?= $med['prescribed_by'] == $doctorId ? '<strong>Dr. ' . htmlspecialchars($med['doctor_name']) . '</strong> (You)' : 'Attending Physician' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="tab-prescriptions" class="tab-pane">
  <div class="mc-panel">
    <div class="mc-panel-h">
      <h2>Prescriptions history</h2>
      <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="mc-btn mc-btn-primary mc-btn-sm"><i class="bi bi-plus-lg"></i> Issue Rx</a>
    </div>
    <?php if (empty($prescriptions)): ?>
      <p style="margin:0;color:var(--muted)">No past prescriptions found for this patient.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="lab-table">
          <thead><tr><th>Rx ID</th><th>Date</th><th>Doctor</th><th>Hospital</th><th>Diagnosis</th><th>Medicines</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($prescriptions as $rx): ?>
              <tr>
                <td class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($rx['prescription_uid']) ?></td>
                <td><?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?></td>
                <td><?= $rx['doctor_id'] == $doctorId ? '<strong>Dr. ' . htmlspecialchars($rx['doctor_name']) . '</strong>' : 'Licensed Physician' ?></td>
                <td><?= htmlspecialchars($rx['hospital_name']) ?></td>
                <td><?= htmlspecialchars($rx['diagnosis'] ?? 'Clinical Evaluation') ?></td>
                <td><span class="lab-status info"><?= (int)$rx['medicine_count'] ?> items</span></td>
                <td><span class="lab-status ok"><?= htmlspecialchars($rx['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="tab-labs" class="tab-pane">
  <?php if (!empty($patientLabOrders)): ?>
    <div class="mc-panel">
      <div class="mc-panel-h"><h2>Prescribed lab tokens (<?= count($patientLabOrders) ?>)</h2></div>
      <div style="overflow-x:auto">
        <table class="lab-table">
          <thead><tr><th>Token</th><th>Date</th><th>Doctor / Hospital</th><th>Tests</th><th>Lab</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($patientLabOrders as $plo):
              $oCls = $plo['status'] === 'COMPLETED' ? 'ok' : ($plo['status'] === 'IN_PROGRESS' ? 'info' : 'warn');
            ?>
              <tr>
                <td class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($plo['lab_token']) ?></td>
                <td><?= date('d M Y', strtotime($plo['created_at'])) ?></td>
                <td><strong>Dr. <?= htmlspecialchars($plo['doctor_name']) ?></strong><div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($plo['hospital_name']) ?></div></td>
                <td><?= (int)$plo['done_tests'] ?> / <?= (int)$plo['total_tests'] ?> done</td>
                <td><?= htmlspecialchars($plo['lab_name'] ?: 'Any accredited lab') ?></td>
                <td><span class="lab-status <?= $oCls ?>"><?= htmlspecialchars(str_replace('_', ' ', $plo['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Diagnostic lab reports (<?= count($labReports) ?>)</h2></div>
    <?php if (empty($labReports)): ?>
      <p style="margin:0;color:var(--muted)">No diagnostic lab reports uploaded for this patient.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="lab-table">
          <thead><tr><th>Test / Report</th><th>Date</th><th>Laboratory</th><th>Result summary</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($labReports as $lab): ?>
              <tr>
                <td><strong><?= htmlspecialchars($lab['report_name']) ?></strong></td>
                <td><?= $lab['test_date'] ? date('d M Y', strtotime($lab['test_date'])) : '—' ?></td>
                <td><?= htmlspecialchars($lab['laboratory_name'] ?? 'Diagnostic Center') ?></td>
                <td><?= htmlspecialchars($lab['result_summary'] ?? 'Attached document') ?></td>
                <td><span class="lab-status <?= $lab['verified'] ? 'ok' : 'warn' ?>"><?= $lab['verified'] ? 'Verified' : 'Pending' ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="tab-consultations" class="tab-pane">
  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Past consultations (<?= count($consultations) ?>)</h2></div>
    <?php if (empty($consultations)): ?>
      <p style="margin:0;color:var(--muted)">No prior consultations recorded.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="lab-table">
          <thead><tr><th>ID</th><th>Date</th><th>Doctor</th><th>Hospital / Dept</th><th>Complaint</th><th>Advice</th></tr></thead>
          <tbody>
            <?php foreach ($consultations as $cons): ?>
              <tr>
                <td class="mc-mono"><?= htmlspecialchars($cons['consultation_uid']) ?></td>
                <td><?= date('d M Y', strtotime($cons['consultation_date'])) ?></td>
                <td>Dr. <?= htmlspecialchars($cons['doctor_name']) ?></td>
                <td><?= htmlspecialchars($cons['hospital_name']) ?> · <?= htmlspecialchars($cons['department_name'] ?? 'OPD') ?></td>
                <td><?= htmlspecialchars($cons['chief_complaint'] ?? 'General checkup') ?></td>
                <td><?= htmlspecialchars($cons['advice'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
let secondsLeft = <?= (int)($accessSession['seconds_remaining'] ?? 1800) ?>;
const timerEl = document.getElementById('timer-text');

function updateTimer() {
  if (!timerEl) return;
  if (secondsLeft <= 0) {
    timerEl.textContent = 'Expired';
    alert('Your 30-minute authorized access session has expired. Please re-request consent if needed.');
    window.location.href = '<?= APP_URL ?>/doctor/patient-search.php';
    return;
  }
  const mins = Math.floor(secondsLeft / 60);
  const secs = secondsLeft % 60;
  timerEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  secondsLeft--;
}
updateTimer();
setInterval(updateTimer, 1000);

function switchTab(tabId, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.mc-tab').forEach(b => b.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
  btn.classList.add('active');
}
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
