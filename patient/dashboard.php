<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Auto-expire stale pending access requests
$db->prepare("UPDATE access_requests SET status = 'EXPIRED' WHERE status = 'PENDING' AND expires_at IS NOT NULL AND expires_at < NOW()")->execute();

// Handle Consent Action (Approve / Deny / Revoke) directly from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve' || $action === 'deny') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT ar.*, d.full_name AS doctor_name, h.name AS hospital_name
            FROM access_requests ar
            JOIN doctors d ON ar.doctor_id = d.id
            JOIN hospitals h ON ar.hospital_id = h.id
            WHERE ar.id = ? AND ar.patient_id = ? AND ar.status = 'PENDING'
            LIMIT 1
        ");
        $stmt->execute([$requestId, $patientId]);
        $request = $stmt->fetch();

        if ($request) {
            if ($action === 'approve') {
                $db->beginTransaction();
                try {
                    // 1. Mark request approved
                    $db->prepare("UPDATE access_requests SET status = 'APPROVED', approved_at = NOW() WHERE id = ?")
                       ->execute([$requestId]);

                    // 2. Create 30-minute access session
                    $sessionToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $sessionToken);
                    $sessExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $db->prepare("
                        INSERT INTO access_sessions
                            (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                        VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?)
                    ")->execute([$requestId, $request['doctor_id'], $patientId, $request['hospital_id'], $tokenHash, $sessExpiresAt]);

                    // 3. Send notification to doctor with instant link to patient record
                    $db->prepare("
                        INSERT INTO notifications (user_id, type, title, message, reference_type, reference_id, action_url)
                        SELECT u.id, 'ACCESS_GRANTED',
                               'Access Granted by Patient',
                               'Patient approved your access request. 30-minute clinical session is now active.',
                               'access_session', :req_id, :action_url
                        FROM doctors d JOIN users u ON u.id = d.user_id
                        WHERE d.id = :did
                    ")->execute([
                        ':req_id'     => $requestId,
                        ':action_url' => APP_URL . '/doctor/medical-record.php?patient=' . $patientId,
                        ':did'        => $request['doctor_id'],
                    ]);

                    $db->commit();

                    AuditService::log(AuditService::ACCESS_GRANTED, [
                        'user_id'    => $_SESSION['user_id'],
                        'patient_id' => $patientId,
                        'doctor_id'  => $request['doctor_id'],
                        'hospital_id'=> $request['hospital_id'],
                        'metadata'   => ['request_id' => $requestId, 'doctor_name' => $request['doctor_name']],
                    ]);

                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'title' => 'Access Granted! 🎉',
                        'msg' => 'You approved Dr. ' . $request['doctor_name'] . ' (' . $request['hospital_name'] . '). 30-minute secure clinical access is now active.'
                    ];
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['flash'] = ['type' => 'error', 'title' => 'Error', 'msg' => 'Failed to grant access: ' . $e->getMessage()];
                }
            } elseif ($action === 'deny') {
                $db->prepare("UPDATE access_requests SET status = 'DENIED' WHERE id = ?")->execute([$requestId]);

                AuditService::log(AuditService::ACCESS_DENIED, [
                    'user_id'    => $_SESSION['user_id'],
                    'patient_id' => $patientId,
                    'doctor_id'  => $request['doctor_id'],
                    'hospital_id'=> $request['hospital_id'],
                    'metadata'   => ['request_id' => $requestId],
                ]);

                $_SESSION['flash'] = [
                    'type' => 'info',
                    'title' => 'Request Declined',
                    'msg' => 'Access request from Dr. ' . $request['doctor_name'] . ' has been declined.'
                ];
            }
        }
    } elseif ($action === 'revoke') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT s.*, d.full_name AS doctor_name, h.name AS hospital_name
            FROM access_sessions s
            JOIN doctors d ON s.doctor_id = d.id
            JOIN hospitals h ON s.hospital_id = h.id
            WHERE s.id = ? AND s.patient_id = ? AND s.status = 'ACTIVE'
            LIMIT 1
        ");
        $stmt->execute([$sessionId, $patientId]);
        $session = $stmt->fetch();

        if ($session) {
            $db->prepare("UPDATE access_sessions SET status = 'REVOKED', revoked_at = NOW() WHERE id = ?")->execute([$sessionId]);

            AuditService::log('ACCESS_REVOKED_BY_PATIENT', [
                'user_id'    => $_SESSION['user_id'],
                'patient_id' => $patientId,
                'doctor_id'  => $session['doctor_id'],
                'hospital_id'=> $session['hospital_id'],
                'metadata'   => ['session_id' => $sessionId, 'doctor_name' => $session['doctor_name']],
            ]);

            $_SESSION['flash'] = [
                'type' => 'info',
                'title' => 'Access Revoked',
                'msg' => 'Clinical access for Dr. ' . $session['doctor_name'] . ' was terminated immediately.'
            ];
        }
    }

    header('Location: ' . APP_URL . '/patient/dashboard.php');
    exit;
}

// Patient profile
$stmt = $db->prepare("
    SELECT p.*, u.email, u.phone, u.last_login_at, u.status AS account_status
    FROM patients p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

// Age calculation
$age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;

// Stats
$stmtStats = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM prescriptions WHERE patient_id = ? AND status = 'ACTIVE') AS active_prescriptions,
        (SELECT COUNT(*) FROM consultations WHERE patient_id = ?) AS total_consultations,
        (SELECT COUNT(*) FROM lab_reports WHERE patient_id = ?) AS lab_reports,
        (SELECT COUNT(*) FROM medical_histories WHERE patient_id = ? AND status = 'ACTIVE') AS active_conditions,
        (SELECT COUNT(*) FROM patient_medications WHERE patient_id = ? AND status = 'ACTIVE') AS active_medications,
        (SELECT COUNT(*) FROM patient_allergies WHERE patient_id = ?) AS allergies
");
$stmtStats->execute(array_fill(0, 6, $patientId));
$stats = $stmtStats->fetch();

// Pending access requests (for consent)
$stmtPending = $db->prepare("
    SELECT ar.*, d.full_name AS doctor_name, d.specialization, d.medical_registration_id,
           h.name AS hospital_name, h.type AS hospital_type, h.city AS hospital_city
    FROM access_requests ar
    JOIN doctors d ON ar.doctor_id = d.id
    JOIN hospitals h ON ar.hospital_id = h.id
    WHERE ar.patient_id = ? AND ar.status = 'PENDING' AND (ar.expires_at IS NULL OR ar.expires_at > NOW())
    ORDER BY ar.requested_at DESC
");
$stmtPending->execute([$patientId]);
$pendingRequests = $stmtPending->fetchAll();

// Active access sessions (currently granted)
$stmtActive = $db->prepare("
    SELECT s.*, d.full_name AS doctor_name, d.specialization, d.medical_registration_id,
           h.name AS hospital_name
    FROM access_sessions s
    JOIN doctors d ON s.doctor_id = d.id
    JOIN hospitals h ON s.hospital_id = h.id
    WHERE s.patient_id = ? AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ORDER BY s.expires_at ASC
");
$stmtActive->execute([$patientId]);
$activeSessions = $stmtActive->fetchAll();
foreach ($activeSessions as &$s) {
    $s['seconds_remaining'] = max(0, strtotime($s['expires_at']) - time());
}
unset($s);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$csrf = generateCsrfToken();

// Recent prescriptions
$stmtRx = $db->prepare("
    SELECT rx.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name,
           COUNT(pm.id) AS medicines_count
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    WHERE rx.patient_id = ?
    GROUP BY rx.id
    ORDER BY rx.created_at DESC
    LIMIT 5
");
$stmtRx->execute([$patientId]);
$recentRx = $stmtRx->fetchAll();

// Recent consultations
$stmtCons = $db->prepare("
    SELECT c.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN hospitals h ON c.hospital_id = h.id
    WHERE c.patient_id = ?
    ORDER BY c.consultation_date DESC
    LIMIT 4
");
$stmtCons->execute([$patientId]);
$recentConsultations = $stmtCons->fetchAll();

// Allergies (critical display)
$stmtAllergy = $db->prepare("
    SELECT pa.*, a.name, a.type
    FROM patient_allergies pa
    JOIN allergies a ON a.id = pa.allergy_id
    WHERE pa.patient_id = ? AND pa.severity IN ('SEVERE','LIFE_THREATENING')
    LIMIT 5
");
$stmtAllergy->execute([$patientId]);
$criticalAllergies = $stmtAllergy->fetchAll();

// Unread notifications
$stmtNotif = $db->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$stmtNotif->execute([$_SESSION['user_id']]);
$unreadCount = (int)$stmtNotif->fetch()['cnt'];

// Active Lab Tokens for quick presentation at diagnostic labs
$stmtActiveTokens = $db->prepare("
    SELECT plo.*, d.full_name AS doctor_name, h.name AS hospital_name,
           (SELECT COUNT(*) FROM lab_order_tests lot WHERE lot.lab_order_id = plo.id) AS total_tests,
           (SELECT COUNT(*) FROM lab_order_tests lot WHERE lot.lab_order_id = plo.id AND lot.status = 'COMPLETED') AS done_tests
    FROM prescription_lab_orders plo
    JOIN doctors d ON plo.doctor_id = d.id
    JOIN hospitals h ON plo.hospital_id = h.id
    WHERE plo.patient_id = ? AND plo.status IN ('PENDING', 'IN_PROGRESS', 'PARTIAL') AND plo.expires_at > NOW()
    ORDER BY plo.created_at DESC
    LIMIT 3
");
$stmtActiveTokens->execute([$patientId]);
$activeLabTokens = $stmtActiveTokens->fetchAll();

// Upcoming doctor appointments
$stmtUpcomingApt = $db->prepare("
    SELECT a.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN hospitals h ON a.hospital_id = h.id
    WHERE a.patient_id = ? AND a.status IN ('CONFIRMED', 'WAITING', 'IN_CONSULTATION') AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.serial_number ASC
    LIMIT 2
");
$stmtUpcomingApt->execute([$patientId]);
$upcomingAppointments = $stmtUpcomingApt->fetchAll();

// Welcome flag
$isNewUser = isset($_GET['welcome']);

require __DIR__ . '/../app/views/patient/dashboard_view.php';
