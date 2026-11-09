<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireDoctorHospitalContext();
validateCsrf();

$db         = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];
$sessionId  = (int)($_POST['session_id'] ?? 0);
$patientId  = (int)($_POST['patient_id'] ?? 0);
$redirectTo = trim($_POST['redirect_to'] ?? '');

// If session_id passed, verify and revoke that specific session
if ($sessionId > 0) {
    $stmt = $db->prepare("
        SELECT s.*, p.full_name, p.patient_uid
        FROM access_sessions s
        JOIN patients p ON s.patient_id = p.id
        WHERE s.id = ? AND s.doctor_id = ? AND s.hospital_id = ?
    ");
    $stmt->execute([$sessionId, $doctorId, $hospitalId]);
    $sess = $stmt->fetch();
    if ($sess) {
        $db->prepare("UPDATE access_sessions SET status = 'EXPIRED', revoked_at = NOW() WHERE id = ?")
           ->execute([$sessionId]);
        
        AuditService::log('SESSION_ENDED_BY_DOCTOR', [
            'user_id'    => $_SESSION['user_id'],
            'doctor_id'  => $doctorId,
            'patient_id' => $sess['patient_id'],
            'hospital_id'=> $hospitalId,
            'metadata'   => ['session_id' => $sessionId, 'patient_uid' => $sess['patient_uid']]
        ]);

        $_SESSION['flash'] = [
            'type' => 'info',
            'msg'  => 'Consultation session with ' . htmlspecialchars($sess['full_name']) . ' (' . htmlspecialchars($sess['patient_uid']) . ') ended safely.'
        ];
    }
} else {
    // End all active sessions for this doctor & hospital
    $stmt = $db->prepare("
        SELECT s.id, s.patient_id, p.full_name
        FROM access_sessions s
        JOIN patients p ON s.patient_id = p.id
        WHERE s.doctor_id = ? AND s.hospital_id = ? AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ");
    $stmt->execute([$doctorId, $hospitalId]);
    $activeList = $stmt->fetchAll();

    foreach ($activeList as $row) {
        $db->prepare("UPDATE access_sessions SET status = 'EXPIRED', revoked_at = NOW() WHERE id = ?")
           ->execute([$row['id']]);
        AuditService::log('SESSION_ENDED_BY_DOCTOR', [
            'user_id'    => $_SESSION['user_id'],
            'doctor_id'  => $doctorId,
            'patient_id' => $row['patient_id'],
            'hospital_id'=> $hospitalId,
            'metadata'   => ['session_id' => $row['id']]
        ]);
    }

    $_SESSION['flash'] = [
        'type' => 'info',
        'msg'  => 'Active patient consultation session ended. You can now access another patient.'
    ];
}

// Redirect back to patient search so doctor can select next patient
header('Location: ' . APP_URL . '/doctor/patient-search.php');
exit;
