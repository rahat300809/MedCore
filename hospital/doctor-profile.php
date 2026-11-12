<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('HOSPITAL_ADMIN');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Get hospital profile
$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE user_id = ?");
$stmtHosp->execute([$userId]);
$hospital = $stmtHosp->fetch();

if (!$hospital) {
    header('Location: ' . APP_URL . '/hospital/login.php');
    exit;
}
$hospitalId = (int)$hospital['id'];

$targetDoctorId = (int)($_GET['id'] ?? 0);
if ($targetDoctorId <= 0) {
    header('Location: ' . APP_URL . '/hospital/doctors.php');
    exit;
}

// Fetch doctor details and their specific affiliation with this hospital
$stmtDoc = $db->prepare("
    SELECT d.*, u.email, u.phone,
           dh.id AS affiliation_id, dh.designation AS hosp_designation, dh.employment_type AS hosp_emp_type,
           dh.status AS affiliation_status, dh.joined_at, dh.created_at AS affiliation_date,
           dept.name AS department_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN doctor_hospitals dh ON dh.doctor_id = d.id AND dh.hospital_id = ?
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE d.id = ?
");
$stmtDoc->execute([$hospitalId, $targetDoctorId]);
$doctor = $stmtDoc->fetch();

if (!$doctor) {
    header('Location: ' . APP_URL . '/hospital/doctors.php?error=doctor_not_found');
    exit;
}

// Statistics for this doctor at this hospital
$stmtStats = $db->prepare("
    SELECT
        COUNT(DISTINCT rx.patient_id) AS total_patients_treated,
        COUNT(rx.id) AS total_prescriptions,
        (SELECT COUNT(*) FROM consultations c WHERE c.doctor_id = ? AND c.hospital_id = ?) AS total_consultations
    FROM prescriptions rx
    WHERE rx.doctor_id = ? AND rx.hospital_id = ?
");
$stmtStats->execute([$targetDoctorId, $hospitalId, $targetDoctorId, $hospitalId]);
$stats = $stmtStats->fetch();

// Top medicines prescribed by this doctor at this hospital
$stmtMeds = $db->prepare("
    SELECT
        pm.medicine_name,
        pm.strength,
        COUNT(pm.id) AS times_prescribed,
        COUNT(DISTINCT rx.patient_id) AS patients_count
    FROM prescription_medicines pm
    JOIN prescriptions rx ON pm.prescription_id = rx.id
    WHERE rx.doctor_id = ? AND rx.hospital_id = ?
    GROUP BY pm.medicine_name, pm.strength
    ORDER BY times_prescribed DESC
    LIMIT 15
");
$stmtMeds->execute([$targetDoctorId, $hospitalId]);
$topMeds = $stmtMeds->fetchAll();

// Prescriptions issued by this doctor at this hospital
$searchQ = trim($_GET['q'] ?? '');
$rxWhere = ["rx.doctor_id = ?", "rx.hospital_id = ?"];
$rxParams = [$targetDoctorId, $hospitalId];

if (!empty($searchQ)) {
    $rxWhere[] = "(p.full_name LIKE ? OR p.patient_uid LIKE ? OR rx.diagnosis LIKE ? OR pm.medicine_name LIKE ?)";
    $like = "%{$searchQ}%";
    $rxParams = array_merge($rxParams, [$like, $like, $like, $like]);
}

$rxWhereSql = implode(' AND ', $rxWhere);

$stmtRxList = $db->prepare("
    SELECT rx.*,
           p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           GROUP_CONCAT(DISTINCT CONCAT(pm.medicine_name, ' (', IFNULL(pm.dosage, ''), ')') SEPARATOR ', ') AS medicines_list
    FROM prescriptions rx
    JOIN patients p ON rx.patient_id = p.id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    WHERE {$rxWhereSql}
    GROUP BY rx.id
    ORDER BY rx.created_at DESC
    LIMIT 50
");
$stmtRxList->execute($rxParams);
$prescriptions = $stmtRxList->fetchAll();

AuditService::log('HOSPITAL_DOCTOR_AUDIT', [
    'user_id'     => $userId,
    'hospital_id' => $hospitalId,
    'doctor_id'   => $targetDoctorId,
    'metadata'    => ['view' => 'doctor_clinical_profile']
]);

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalAccent = '#0b4f6c';
$portalUser = $hospital['name'] ?? 'Hospital';
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Doctor audit';
$portalTitle = 'Dr. ' . preg_replace('/^Dr\.?\s+/i', '', (string)$doctor['full_name']);
$portalSubtitle = ($doctor['specialization'] ?? 'Physician') . ' · ' . ($doctor['medical_registration_id'] ?? '');
$portalNav = mc_hospital_nav('doctors');
$portalTopAction = '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/hospital/doctors.php"><i class="bi bi-arrow-left"></i> Staff roster</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="mc-panel">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem">
    <div>
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <h2 style="margin:0;font-size:1.3rem">Dr. <?= htmlspecialchars(preg_replace('/^Dr\.?\s+/i', '', (string)$doctor['full_name'])) ?></h2>
        <span class="lab-status <?= $doctor['affiliation_status'] === 'APPROVED' ? 'ok' : 'warn' ?>">
          <?= $doctor['affiliation_status'] === 'APPROVED' ? 'Active staff' : htmlspecialchars($doctor['affiliation_status']) ?>
        </span>
      </div>
      <div style="font-size:.88rem;color:var(--muted);margin-top:.3rem">
        <?= htmlspecialchars($doctor['specialization'] ?? 'Physician') ?>
        · BMDC <span class="mc-mono"><?= htmlspecialchars($doctor['medical_registration_id']) ?></span>
        · UID <span class="mc-mono"><?= htmlspecialchars($doctor['doctor_uid']) ?></span>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:.85rem;font-size:.84rem;color:var(--ink-2);margin-top:.65rem">
        <span>Role: <strong><?= htmlspecialchars($doctor['hosp_designation'] ?? 'Consultant') ?></strong></span>
        <span>Type: <strong><?= htmlspecialchars(str_replace('_', ' ', $doctor['hosp_emp_type'] ?? 'FULL_TIME')) ?></strong></span>
        <?php if ($doctor['department_name']): ?><span>Dept: <strong><?= htmlspecialchars($doctor['department_name']) ?></strong></span><?php endif; ?>
        <?php if ($doctor['joined_at']): ?><span>Joined: <strong><?= date('M j, Y', strtotime($doctor['joined_at'])) ?></strong></span><?php endif; ?>
      </div>
      <?php if ($doctor['qualification']): ?>
        <div style="margin-top:.65rem;font-size:.84rem;color:var(--ink-2)"><?= htmlspecialchars($doctor['qualification']) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Patients treated</div><div class="v"><?= number_format((int)($stats['total_patients_treated'] ?? 0)) ?></div></div>
  <div class="mc-stat"><div class="l">Prescriptions</div><div class="v"><?= number_format((int)($stats['total_prescriptions'] ?? 0)) ?></div></div>
  <div class="mc-stat"><div class="l">Consultations</div><div class="v"><?= number_format((int)($stats['total_consultations'] ?? 0)) ?></div></div>
  <div class="mc-stat"><div class="l">Top meds</div><div class="v"><?= count($topMeds) ?></div></div>
</div>

<?php if (!empty($topMeds)): ?>
  <div class="mc-panel">
    <div class="mc-panel-h"><h2>Frequently prescribed medicines</h2></div>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead><tr><th>Medicine &amp; Strength</th><th>Prescribed count</th><th>Unique patients</th></tr></thead>
        <tbody>
          <?php foreach ($topMeds as $m): ?>
            <tr>
              <td><strong><?= htmlspecialchars($m['medicine_name']) ?></strong><?= $m['strength'] ? ' · ' . htmlspecialchars($m['strength']) : '' ?></td>
              <td><span class="lab-status ok"><?= (int)$m['times_prescribed'] ?>x</span></td>
              <td><?= (int)$m['patients_count'] ?> patients</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Prescriptions issued at this facility</h2></div>
  <?php if (empty($prescriptions)): ?>
    <p style="margin:0;color:var(--muted)">No prescriptions recorded for this doctor at this hospital yet.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead><tr><th>Date &amp; Rx UID</th><th>Patient</th><th>Diagnosis</th><th>Medicines</th><th>Follow-up</th></tr></thead>
        <tbody>
          <?php foreach ($prescriptions as $p): ?>
            <tr>
              <td>
                <div class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($p['prescription_uid']) ?></div>
                <div style="font-size:.78rem;color:var(--muted)"><?= date('M j, Y, h:i A', strtotime($p['created_at'])) ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($p['patient_name']) ?></strong>
                <div class="mc-mono" style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($p['patient_uid']) ?></div>
              </td>
              <td><?= htmlspecialchars($p['diagnosis'] ?: 'General Consultation') ?></td>
              <td style="max-width:300px;font-size:.84rem;color:var(--muted)"><?= htmlspecialchars($p['medicines_list'] ?: 'See prescription record') ?></td>
              <td style="white-space:nowrap"><?= $p['follow_up_date'] ? date('M j, Y', strtotime($p['follow_up_date'])) : 'PRN' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
