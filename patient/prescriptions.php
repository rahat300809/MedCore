<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];
$search    = trim($_GET['search'] ?? '');
$targetRx  = trim($_GET['rx'] ?? '');

$query = "
    SELECT rx.*, d.full_name AS doctor_name, d.specialization, d.qualification,
           h.name AS hospital_name,
           (SELECT COUNT(*) FROM prescription_medicines pm WHERE pm.prescription_id = rx.id) AS medicine_count
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    WHERE rx.patient_id = ?
";
$params = [$patientId];

if ($search) {
    $query .= " AND (rx.prescription_uid LIKE ? OR d.full_name LIKE ? OR h.name LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY rx.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// If viewing a single prescription modal / detail
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
        WHERE rx.prescription_uid = ? AND rx.patient_id = ?
        LIMIT 1
    ");
    $stmtView->execute([$targetRx, $patientId]);
    $viewRx = $stmtView->fetch();

    if ($viewRx) {
        $stmtMeds = $db->prepare("
            SELECT pm.*, m.generic_name, m.brand_name, m.dosage_form
            FROM prescription_medicines pm
            LEFT JOIN medications m ON pm.medication_id = m.id
            WHERE pm.prescription_id = ?
            ORDER BY pm.id ASC
        ");
        $stmtMeds->execute([$viewRx['id']]);
        $viewMeds = $stmtMeds->fetchAll();

        $stmtAllergies = $db->prepare("
            SELECT pa.severity, pa.reaction, a.name AS allergy_name
            FROM patient_allergies pa
            JOIN allergies a ON pa.allergy_id = a.id
            WHERE pa.patient_id = ?
            ORDER BY FIELD(pa.severity, 'LIFE_THREATENING','SEVERE','MODERATE','MILD')
        ");
        $stmtAllergies->execute([$patientId]);
        $viewAllergies = $stmtAllergies->fetchAll();

        // Fetch Lab Order and Tests
        $stmtLabOrd = $db->prepare("
            SELECT plo.*, l.name AS assigned_lab_name, l.lab_uid AS assigned_lab_uid
            FROM prescription_lab_orders plo
            LEFT JOIN laboratories l ON plo.laboratory_id = l.id
            WHERE plo.prescription_id = ?
            LIMIT 1
        ");
        $stmtLabOrd->execute([$viewRx['id']]);
        $viewLabOrder = $stmtLabOrd->fetch();

        $viewLabTests = [];
        if ($viewLabOrder) {
            $stmtLT = $db->prepare("
                SELECT lot.*, ltr.result_status, ltr.result_summary, ltr.result_value, ltr.reference_range,
                       ltr.technician_name, ltr.uploaded_at AS result_uploaded_at,
                       rl.name AS result_lab_name
                FROM lab_order_tests lot
                LEFT JOIN lab_test_results ltr ON ltr.lab_order_test_id = lot.id
                LEFT JOIN laboratories rl ON ltr.laboratory_id = rl.id
                WHERE lot.lab_order_id = ?
                ORDER BY lot.id ASC
            ");
            $stmtLT->execute([$viewLabOrder['id']]);
            $viewLabTests = $stmtLT->fetchAll();
        }
    }
}
$viewAllergies = $viewAllergies ?? [];
require __DIR__ . '/../app/views/patient/prescriptions_view.php';
