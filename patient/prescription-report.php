<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

// Allowed roles: PATIENT (owner), DOCTOR, HOSPITAL_ADMIN, SYSTEM_ADMIN, LABORATORY
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . APP_URL . '/role-selection.php');
    exit;
}

$db = getDB();
$rxId = (int)($_GET['id'] ?? 0);

if ($rxId <= 0) {
    header('Location: ' . APP_URL . '/patient/prescriptions.php');
    exit;
}

// Fetch Prescription details
$stmtRx = $db->prepare("
    SELECT rx.*,
           d.full_name AS doctor_name, d.specialization AS doctor_spec, d.qualification AS doctor_qual,
           d.medical_registration_id, d.doctor_uid,
           h.name AS hospital_name, h.registration_number AS hosp_reg, h.address AS hosp_addr,
           h.city AS hosp_city, h.phone AS hosp_phone, h.email AS hosp_email, h.type AS hosp_type,
           p.full_name AS patient_name, p.patient_uid, p.date_of_birth, p.gender, p.blood_group,
           p.emergency_contact_phone, p.id AS patient_db_id,
           c.chief_complaint, c.symptoms, c.investigation, c.clinical_notes, c.consultation_uid
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    JOIN patients p ON rx.patient_id = p.id
    LEFT JOIN consultations c ON rx.consultation_id = c.id
    WHERE rx.id = ?
");
$stmtRx->execute([$rxId]);
$rx = $stmtRx->fetch();

if (!$rx) {
    echo "Prescription not found.";
    exit;
}

// Authorization check
$userRole  = $_SESSION['role'];
$patientId = $rx['patient_db_id'];

if ($userRole === 'PATIENT') {
    if ((int)$_SESSION['patient_id'] !== $patientId) {
        http_response_code(403);
        exit("Unauthorized access to this prescription.");
    }
} elseif ($userRole === 'HOSPITAL_ADMIN') {
    $stmtH = $db->prepare("SELECT id FROM hospitals WHERE user_id = ?");
    $stmtH->execute([$_SESSION['user_id']]);
    $myHospId = (int)$stmtH->fetchColumn();
    if ($myHospId !== (int)$rx['hospital_id']) {
        http_response_code(403);
        exit("Unauthorized access.");
    }
} elseif ($userRole === 'DOCTOR') {
    $currentDocId = (int)($_SESSION['doctor_id'] ?? 0);
    if ($currentDocId !== (int)$rx['doctor_id']) {
        $stmtSess = $db->prepare("
            SELECT id FROM access_sessions
            WHERE doctor_id = ? AND patient_id = ? AND status = 'ACTIVE' AND expires_at > NOW()
            LIMIT 1
        ");
        $stmtSess->execute([$currentDocId, $patientId]);
        if (!$stmtSess->fetch()) {
            http_response_code(403);
            exit("Active consent session required to view this patient's prescription.");
        }
    }
} elseif ($userRole === 'LABORATORY') {
    $currentLabId = (int)($_SESSION['lab_id'] ?? 0);
    $stmtLabOrd = $db->prepare("
        SELECT id FROM prescription_lab_orders
        WHERE prescription_id = ? AND (laboratory_id = ? OR hospital_id = ?)
        LIMIT 1
    ");
    $stmtLabOrd->execute([$rxId, $currentLabId, (int)($_SESSION['hospital_id'] ?? 0)]);
    if (!$stmtLabOrd->fetch()) {
        http_response_code(403);
        exit("Unauthorized access to this prescription.");
    }
}

// Fetch prescribed medicines
$stmtMeds = $db->prepare("
    SELECT pm.*, m.generic_name, m.brand_name, m.dosage_form
    FROM prescription_medicines pm
    LEFT JOIN medications m ON pm.medication_id = m.id
    WHERE pm.prescription_id = ?
    ORDER BY pm.id ASC
");
$stmtMeds->execute([$rxId]);
$medicines = $stmtMeds->fetchAll();

// Fetch patient allergy alerts
$stmtAllergies = $db->prepare("
    SELECT pa.severity, pa.reaction, a.name AS allergy_name
    FROM patient_allergies pa
    JOIN allergies a ON pa.allergy_id = a.id
    WHERE pa.patient_id = ?
    ORDER BY FIELD(pa.severity, 'LIFE_THREATENING','SEVERE','MODERATE','MILD')
");
$stmtAllergies->execute([$patientId]);
$allergies = $stmtAllergies->fetchAll();

// Fetch previous prescriptions for clean comprehensive history
$stmtPrev = $db->prepare("
    SELECT rx2.id, rx2.prescription_uid, rx2.created_at, rx2.diagnosis,
           d2.full_name AS doc_name, h2.name AS hosp_name,
           (SELECT COUNT(*) FROM prescription_medicines pm2 WHERE pm2.prescription_id = rx2.id) AS med_cnt
    FROM prescriptions rx2
    JOIN doctors d2 ON rx2.doctor_id = d2.id
    JOIN hospitals h2 ON rx2.hospital_id = h2.id
    WHERE rx2.patient_id = ? AND rx2.id != ?
    ORDER BY rx2.created_at DESC
    LIMIT 5
");
$stmtPrev->execute([$patientId, $rxId]);
$prevPrescriptions = $stmtPrev->fetchAll();

// Fetch Lab Order and Token if any
$stmtLabOrder = $db->prepare("
    SELECT plo.*, l.name AS lab_name, l.lab_uid
    FROM prescription_lab_orders plo
    LEFT JOIN laboratories l ON plo.laboratory_id = l.id
    WHERE plo.prescription_id = ?
    LIMIT 1
");
$stmtLabOrder->execute([$rxId]);
$prescriptionLabOrder = $stmtLabOrder->fetch();

$labTestsOrdered = [];
if ($prescriptionLabOrder) {
    $stmtLT = $db->prepare("
        SELECT lot.*, ltr.result_status, ltr.result_summary, ltr.result_value, ltr.reference_range,
               l2.name AS result_lab_name, l2.lab_uid AS result_lab_uid
        FROM lab_order_tests lot
        LEFT JOIN lab_test_results ltr ON ltr.lab_order_test_id = lot.id
        LEFT JOIN laboratories l2 ON ltr.laboratory_id = l2.id
        WHERE lot.lab_order_id = ?
        ORDER BY lot.id ASC
    ");
    $stmtLT->execute([$prescriptionLabOrder['id']]);
    $labTestsOrdered = $stmtLT->fetchAll();
}

AuditService::log('VIEW_PRESCRIPTION_REPORT', [
    'user_id'    => $_SESSION['user_id'],
    'patient_id' => $patientId,
    'metadata'   => ['prescription_uid' => $rx['prescription_uid']]
]);

$labOrder = $prescriptionLabOrder;
$labTests = $labTestsOrdered;
$showToolbar = true;
$backUrl = 'javascript:history.back()';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prescription — <?= htmlspecialchars($rx['prescription_uid']) ?> — MedCore</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,700&family=Source+Sans+3:wght@400;600;700;800&family=Great+Vibes&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/competition.css?v=4">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/rx-pad.css?v=1">
  <style>
    body { background: #e8eef3; margin: 0; padding: 24px 14px 40px; font-family: 'Source Sans 3', system-ui, sans-serif; }
  </style>
</head>
<body>
<?php require __DIR__ . '/../app/views/partials/rx_pad.php'; ?>

<?php if (!empty($prevPrescriptions)): ?>
  <div class="no-print" style="max-width:860px;margin:18px auto 0">
    <div style="font-weight:800;font-size:.85rem;color:#475569;margin-bottom:8px">Previous prescriptions</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach ($prevPrescriptions as $prx): ?>
        <a href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= (int)$prx['id'] ?>"
           style="background:#fff;border:1px solid #d5dde6;border-radius:10px;padding:10px 14px;text-decoration:none;color:#0f1c2e;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
          <span>
            <strong style="font-family:JetBrains Mono,monospace;color:#0b6e6a"><?= htmlspecialchars($prx['prescription_uid']) ?></strong>
            · <?= date('d M Y', strtotime($prx['created_at'])) ?>
            · Dr. <?= htmlspecialchars($prx['doc_name']) ?>
          </span>
          <span style="color:#64748b;font-size:.85rem"><?= (int)$prx['med_cnt'] ?> meds</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/rx-pad.js?v=1"></script>
</body>
</html>
