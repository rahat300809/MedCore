<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/includes/session_bar.php';

requireDoctorHospitalContext();

$db        = getDB();
$doctorId  = (int)$_SESSION['doctor_id'];
$activeHospId = (int)$_SESSION['hospital_id'];
$activeSession = getDoctorActivePatientSession($db, $doctorId, $activeHospId);

// Get doctor profile details
$stmtDoc = $db->prepare("SELECT * FROM doctors WHERE id = ?");
$stmtDoc->execute([$doctorId]);
$doctor = $stmtDoc->fetch();

// Hospital filter: default to all hospitals or active hospital
$selectedHospFilter = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$searchQ = trim($_GET['q'] ?? '');

// Fetch all affiliated hospitals for this doctor
$stmtAffils = $db->prepare("
    SELECT h.id, h.name, h.hospital_uid, h.type, h.city, dh.designation, dh.employment_type, dh.status,
           (SELECT COUNT(DISTINCT c.patient_id) FROM consultations c WHERE c.doctor_id = ? AND c.hospital_id = h.id) AS patient_count,
           (SELECT COUNT(*) FROM prescriptions rx WHERE rx.doctor_id = ? AND rx.hospital_id = h.id) AS rx_count,
           (SELECT COUNT(*) FROM consultations c WHERE c.doctor_id = ? AND c.hospital_id = h.id) AS consultation_count
    FROM doctor_hospitals dh
    JOIN hospitals h ON dh.hospital_id = h.id
    WHERE dh.doctor_id = ? AND dh.status = 'APPROVED'
    ORDER BY h.name ASC
");
$stmtAffils->execute([$doctorId, $doctorId, $doctorId, $doctorId]);
$affiliatedHospitals = $stmtAffils->fetchAll();

// Build where clause for analytics
$rxWhere = ["rx.doctor_id = ?"];
$rxParams = [$doctorId];

if ($selectedHospFilter > 0) {
    $rxWhere[] = "rx.hospital_id = ?";
    $rxParams[] = $selectedHospFilter;
}

$rxWhereSql = implode(' AND ', $rxWhere);

// Top Overview Metrics
$totalsParams = array_merge([$doctorId], $rxParams);
$stmtTotals = $db->prepare("
    SELECT
        COUNT(DISTINCT rx.patient_id) AS total_patients,
        COUNT(rx.id) AS total_prescriptions,
        (SELECT COUNT(*) FROM consultations c WHERE c.doctor_id = ? " . ($selectedHospFilter > 0 ? "AND c.hospital_id = {$selectedHospFilter}" : "") . ") AS total_consultations
    FROM prescriptions rx
    WHERE {$rxWhereSql}
");
$stmtTotals->execute($totalsParams);
$totals = $stmtTotals->fetch();

// Medicine Breakdown: which medicines provided and how many times
$stmtMeds = $db->prepare("
    SELECT
        pm.medicine_name,
        pm.strength,
        MAX(pm.dosage) AS dosage,
        MAX(pm.frequency) AS frequency,
        MAX(pm.route) AS route,
        COUNT(pm.id) AS prescribe_count,
        COUNT(DISTINCT rx.patient_id) AS unique_patients
    FROM prescription_medicines pm
    JOIN prescriptions rx ON pm.prescription_id = rx.id
    WHERE {$rxWhereSql}
    GROUP BY pm.medicine_name, pm.strength
    ORDER BY prescribe_count DESC
    LIMIT 50
");
$stmtMeds->execute($rxParams);
$medicineStats = $stmtMeds->fetchAll();

// Total distinct medicines
$totalDistinctMeds = count($medicineStats);

// Prescriptions History Log
$histWhere = ["rx.doctor_id = ?"];
$histParams = [$doctorId];

if ($selectedHospFilter > 0) {
    $histWhere[] = "rx.hospital_id = ?";
    $histParams[] = $selectedHospFilter;
}

if (!empty($searchQ)) {
    $histWhere[] = "(p.full_name LIKE ? OR p.patient_uid LIKE ? OR rx.diagnosis LIKE ? OR pm.medicine_name LIKE ?)";
    $like = "%{$searchQ}%";
    $histParams = array_merge($histParams, [$like, $like, $like, $like]);
}

$histWhereSql = implode(' AND ', $histWhere);

$stmtHistory = $db->prepare("
    SELECT rx.id, rx.prescription_uid, rx.diagnosis, rx.advice, rx.follow_up_date, rx.created_at,
           p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           h.name AS hospital_name,
           GROUP_CONCAT(DISTINCT CONCAT(pm.medicine_name, ' (', IFNULL(pm.dosage, ''), ' ', IFNULL(pm.frequency, ''), ')') SEPARATOR ' • ') AS medicines_summary
    FROM prescriptions rx
    JOIN patients p ON rx.patient_id = p.id
    JOIN hospitals h ON rx.hospital_id = h.id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    WHERE {$histWhereSql}
    GROUP BY rx.id
    ORDER BY rx.created_at DESC
    LIMIT 60
");
$stmtHistory->execute($histParams);
$prescriptionHistory = $stmtHistory->fetchAll();

AuditService::log('REPORTS_VIEW', [
    'user_id'   => $_SESSION['user_id'],
    'doctor_id' => $doctorId,
    'hospital_id' => $activeHospId,
    'metadata'  => ['filter_hospital' => $selectedHospFilter]
]);

require_once __DIR__ . '/../app/views/portal/navs.php';
$waitingQueueCount = mc_doctor_shell_setup($db, $doctorId, $activeHospId, $activeSession, 'reports');
$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $doctor['full_name'] ?? ($_SESSION['name'] ?? 'Doctor');
$portalMeta = ($doctor['medical_registration_id'] ?? '') . ' · Analytics';
$portalTitle = 'Practice analytics & reports';
$portalSubtitle = 'রোগী, প্রেসক্রিপশন ও ওষুধের পরিসংখ্যান';
$portalNav = mc_doctor_nav('reports', $waitingQueueCount);
$portalTopAction = '<button type="button" onclick="window.print()" class="mc-btn mc-btn-secondary mc-btn-sm no-print"><i class="bi bi-printer"></i> Print report</button>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="mc-panel no-print">
  <form method="GET" style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));align-items:end">
    <label class="mc-field" style="margin:0">
      <span>Hospital scope</span>
      <select name="hospital_id" onchange="this.form.submit()">
        <option value="0">All affiliated hospitals</option>
        <?php foreach ($affiliatedHospitals as $ah): ?>
          <option value="<?= (int)$ah['id'] ?>" <?= $selectedHospFilter == $ah['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ah['name']) ?> (<?= htmlspecialchars($ah['city'] ?? 'Dhaka') ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="mc-field" style="margin:0">
      <span>Search medicine / patient / diagnosis</span>
      <input type="text" name="q" placeholder="Medicine, patient, diagnosis..." value="<?= htmlspecialchars($searchQ) ?>">
    </label>
    <div style="display:flex;gap:.45rem">
      <button type="submit" class="mc-btn mc-btn-primary">Apply</button>
      <?php if ($selectedHospFilter || $searchQ): ?>
        <a href="<?= APP_URL ?>/doctor/reports.php" class="mc-btn mc-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Unique patients</div><div class="v"><?= number_format((int)($totals['total_patients'] ?? 0)) ?></div></div>
  <div class="mc-stat"><div class="l">Prescriptions</div><div class="v"><?= number_format((int)($totals['total_prescriptions'] ?? 0)) ?></div></div>
  <div class="mc-stat"><div class="l">Consultations</div><div class="v"><?= number_format((int)($totals['total_consultations'] ?? 0)) ?></div></div>
  <div class="mc-stat"><div class="l">Distinct meds</div><div class="v"><?= number_format($totalDistinctMeds) ?></div></div>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Multi-hospital performance</h2></div>
  <div class="mc-grid-3">
    <?php foreach ($affiliatedHospitals as $hosp): ?>
      <?php $isAct = ($hosp['id'] == $activeHospId); ?>
      <div class="mc-panel" style="margin:0;border-color:<?= $isAct ? 'var(--accent)' : 'var(--line)' ?>;background:<?= $isAct ? 'var(--accent-soft)' : '#fff' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
          <div>
            <strong><?= htmlspecialchars($hosp['name']) ?></strong>
            <div style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($hosp['city'] ?? 'Dhaka') ?> · <?= htmlspecialchars($hosp['type']) ?></div>
          </div>
          <span class="lab-status <?= $isAct ? 'ok' : '' ?>"><?= $isAct ? 'Active' : 'Accredited' ?></span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-top:.85rem;padding:.65rem;border-radius:10px;background:#fff;border:1px solid var(--line)">
          <div><div style="font-size:.72rem;color:var(--muted);font-weight:700">PATIENTS</div><strong style="font-size:1.15rem;color:var(--accent)"><?= (int)$hosp['patient_count'] ?></strong></div>
          <div><div style="font-size:.72rem;color:var(--muted);font-weight:700">RX ISSUED</div><strong style="font-size:1.15rem"><?= (int)$hosp['rx_count'] ?></strong></div>
        </div>
        <div style="margin-top:.65rem;font-size:.8rem;color:var(--muted);display:flex;justify-content:space-between">
          <span><?= htmlspecialchars($hosp['designation'] ?? 'Consultant') ?></span>
          <span><?= ucfirst(strtolower(str_replace('_', ' ', $hosp['employment_type']))) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Prescribed medicines formulary report</h2>
    <span class="lab-status info"><?= count($medicineStats) ?> cataloged</span>
  </div>
  <?php if (empty($medicineStats)): ?>
    <p style="margin:0;color:var(--muted)">No medicines prescribed yet in this practice scope.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Medicine &amp; Strength</th>
            <th>Count</th>
            <th>Patients</th>
            <th>Dosage</th>
            <th>Frequency</th>
            <th>Route</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($medicineStats as $med): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($med['medicine_name']) ?></strong>
                <?php if ($med['strength']): ?><span style="color:var(--muted);font-size:.82rem"> <?= htmlspecialchars($med['strength']) ?></span><?php endif; ?>
              </td>
              <td><span class="lab-status ok"><?= (int)$med['prescribe_count'] ?>x</span></td>
              <td><strong><?= (int)$med['unique_patients'] ?></strong></td>
              <td><?= htmlspecialchars($med['dosage'] ?: 'As needed') ?></td>
              <td><?= htmlspecialchars($med['frequency'] ?: 'Daily') ?></td>
              <td><span class="lab-status"><?= htmlspecialchars($med['route'] ?: 'ORAL') ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Prescription log &amp; clinical audit history</h2></div>
  <?php if (empty($prescriptionHistory)): ?>
    <p style="margin:0;color:var(--muted)">No prescription history matching the current filter.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Date &amp; Rx UID</th>
            <th>Patient</th>
            <th>Hospital</th>
            <th>Diagnosis</th>
            <th>Medicines</th>
            <th>Follow-up</th>
            <th class="no-print"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($prescriptionHistory as $rx): ?>
            <tr>
              <td>
                <div class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($rx['prescription_uid']) ?></div>
                <div style="font-size:.78rem;color:var(--muted)"><?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($rx['patient_name']) ?></strong>
                <div class="mc-mono" style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($rx['patient_uid']) ?></div>
              </td>
              <td><?= htmlspecialchars($rx['hospital_name']) ?></td>
              <td><?= htmlspecialchars($rx['diagnosis'] ?: 'General Consultation') ?></td>
              <td style="max-width:260px;font-size:.84rem;color:var(--muted)"><?= htmlspecialchars($rx['medicines_summary'] ?: 'See Rx details') ?></td>
              <td style="white-space:nowrap"><?= $rx['follow_up_date'] ? date('d M Y', strtotime($rx['follow_up_date'])) : 'PRN' ?></td>
              <td class="no-print">
                <a href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= (int)$rx['id'] ?>" target="_blank" class="mc-btn mc-btn-secondary mc-btn-sm">View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
