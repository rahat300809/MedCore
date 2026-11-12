<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/includes/session_bar.php';

requireDoctorHospitalContext();

$db         = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];
$activeSession = getDoctorActivePatientSession($db, $doctorId, $hospitalId);
$search     = trim($_GET['search'] ?? '');

$query = "
    SELECT c.*, p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           h.name AS hospital_name, dept.name AS department_name,
           rx.prescription_uid
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN hospitals h ON c.hospital_id = h.id
    LEFT JOIN departments dept ON c.department_id = dept.id
    LEFT JOIN prescriptions rx ON rx.consultation_id = c.id
    WHERE c.doctor_id = ?
";
$params = [$doctorId];

if ($search) {
    $query .= " AND (c.consultation_uid LIKE ? OR p.full_name LIKE ? OR p.patient_uid LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY c.consultation_date DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$consultations = $stmt->fetchAll();

require_once __DIR__ . '/../app/views/portal/navs.php';
$waitingQueueCount = mc_doctor_shell_setup($db, $doctorId, $hospitalId, $activeSession, 'consultations');
$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $_SESSION['name'] ?? 'Doctor';
$portalMeta = ($_SESSION['hospital_name'] ?? '') . ' · Consultations';
$portalTitle = 'Consultation history';
$portalSubtitle = 'রোগীর পূর্ববর্তী পরামর্শ রেকর্ড (' . count($consultations) . ')';
$portalNav = mc_doctor_nav('consultations', $waitingQueueCount);
$portalTopAction = '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/doctor/patient-search.php"><i class="bi bi-search"></i> Start consultation</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="lab-token-bar">
  <label for="cons-search">Filter consultations</label>
  <form method="GET" class="lab-token-form">
    <input id="cons-search" type="text" name="search" value="<?= htmlspecialchars($search) ?>"
           style="text-transform:none;letter-spacing:normal;font-family:inherit;font-size:.95rem"
           placeholder="Search by consultation ID, patient name, or PT UID...">
    <button type="submit" class="mc-btn mc-btn-primary">Search</button>
    <?php if ($search): ?>
      <a href="<?= APP_URL ?>/doctor/consultations.php" class="mc-btn mc-btn-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Consultations log (<?= count($consultations) ?>)</h2></div>
  <?php if (empty($consultations)): ?>
    <p style="margin:0;color:var(--muted)">No consultations found.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Consultation ID</th>
            <th>Date</th>
            <th>Patient</th>
            <th>Hospital</th>
            <th>Complaint</th>
            <th>Diagnosis</th>
            <th>Rx</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($consultations as $c): ?>
            <tr>
              <td class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($c['consultation_uid']) ?></td>
              <td style="white-space:nowrap"><?= date('d M Y, h:i A', strtotime($c['consultation_date'])) ?></td>
              <td>
                <strong><?= htmlspecialchars($c['patient_name']) ?></strong>
                <div class="mc-mono" style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($c['patient_uid']) ?></div>
              </td>
              <td><?= htmlspecialchars($c['hospital_name']) ?></td>
              <td><?= htmlspecialchars($c['chief_complaint'] ?? 'General') ?></td>
              <td><span class="lab-status info"><?= htmlspecialchars($c['diagnosis_summary'] ?? 'Under observation') ?></span></td>
              <td>
                <?php if (!empty($c['prescription_uid'])): ?>
                  <a href="<?= APP_URL ?>/doctor/prescriptions.php?rx=<?= urlencode($c['prescription_uid']) ?>" class="mc-btn mc-btn-soft mc-btn-sm">
                    <?= htmlspecialchars($c['prescription_uid']) ?>
                  </a>
                <?php else: ?>
                  <span style="color:var(--muted)">No Rx</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
