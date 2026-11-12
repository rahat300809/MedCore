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
$search   = trim($_GET['search'] ?? '');
$targetRx = trim($_GET['rx'] ?? '');

$query = "
    SELECT rx.*, p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           h.name AS hospital_name,
           (SELECT COUNT(*) FROM prescription_medicines pm WHERE pm.prescription_id = rx.id) AS medicine_count
    FROM prescriptions rx
    JOIN patients p ON rx.patient_id = p.id
    JOIN hospitals h ON rx.hospital_id = h.id
    WHERE rx.doctor_id = ?
";
$params = [$doctorId];

if ($search) {
    $query .= " AND (rx.prescription_uid LIKE ? OR p.full_name LIKE ? OR p.patient_uid LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY rx.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// If viewing a single prescription modal
$viewRx = null;
$viewMeds = [];
if ($targetRx) {
    $stmtView = $db->prepare("
        SELECT rx.*, p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth, p.blood_group,
               h.name AS hospital_name, h.address AS hospital_address, h.phone AS hospital_phone,
               d.full_name AS doctor_name, d.specialization, d.medical_registration_id, d.qualification,
               c.chief_complaint, c.clinical_notes, c.investigation
        FROM prescriptions rx
        JOIN patients p ON rx.patient_id = p.id
        JOIN hospitals h ON rx.hospital_id = h.id
        JOIN doctors d ON rx.doctor_id = d.id
        LEFT JOIN consultations c ON rx.consultation_id = c.id
        WHERE rx.prescription_uid = ? AND rx.doctor_id = ?
        LIMIT 1
    ");
    $stmtView->execute([$targetRx, $doctorId]);
    $viewRx = $stmtView->fetch();

    if ($viewRx) {
        header('Location: ' . APP_URL . '/patient/prescription-report.php?id=' . (int)$viewRx['id']);
        exit;
    }
}

require_once __DIR__ . '/../app/views/portal/navs.php';
$waitingQueueCount = mc_doctor_shell_setup($db, $doctorId, $hospitalId, $activeSession, 'prescriptions');
$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $_SESSION['name'] ?? 'Doctor';
$portalMeta = ($_SESSION['hospital_name'] ?? '') . ' · Prescriptions';
$portalTitle = 'Issued prescriptions';
$portalSubtitle = 'ডিজিটাল প্রেসক্রিপশন লগ (' . count($prescriptions) . ')';
$portalNav = mc_doctor_nav('prescriptions', $waitingQueueCount);
$portalTopAction = '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/doctor/patient-search.php"><i class="bi bi-plus-lg"></i> Issue Rx</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if (isset($_SESSION['flash'])): ?>
  <div class="mc-alert <?= ($_SESSION['flash']['type'] ?? '') === 'success' ? 'mc-alert-ok' : 'mc-alert-danger' ?>">
    <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
  </div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<div class="lab-token-bar">
  <label for="rx-search">Search issued prescriptions</label>
  <form method="GET" class="lab-token-form">
    <input id="rx-search" type="text" name="search" value="<?= htmlspecialchars($search) ?>"
           style="text-transform:none;letter-spacing:normal;font-family:inherit;font-size:.95rem"
           placeholder="Search by Rx ID (RX-...), patient name, or PT UID...">
    <button type="submit" class="mc-btn mc-btn-primary">Search</button>
    <?php if ($search): ?>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php" class="mc-btn mc-btn-secondary">Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Issued prescriptions (<?= count($prescriptions) ?>)</h2></div>
  <?php if (empty($prescriptions)): ?>
    <p style="margin:0;color:var(--muted)">No prescriptions found.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Rx ID</th>
            <th>Date</th>
            <th>Patient</th>
            <th>Hospital</th>
            <th>Medicines</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($prescriptions as $rx): ?>
            <tr>
              <td class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($rx['prescription_uid']) ?></td>
              <td style="white-space:nowrap"><?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?></td>
              <td>
                <strong><?= htmlspecialchars($rx['patient_name']) ?></strong>
                <div class="mc-mono" style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($rx['patient_uid']) ?></div>
              </td>
              <td><?= htmlspecialchars($rx['hospital_name']) ?></td>
              <td><span class="lab-status info"><?= (int)$rx['medicine_count'] ?> items</span></td>
              <td><span class="lab-status <?= $rx['status'] === 'ACTIVE' ? 'ok' : '' ?>"><?= htmlspecialchars($rx['status']) ?></span></td>
              <td style="text-align:right">
                <a href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= (int)$rx['id'] ?>" class="mc-btn mc-btn-primary mc-btn-sm" target="_blank">
                  <i class="bi bi-eye"></i> View / Print
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
