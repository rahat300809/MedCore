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
                    $db->prepare("
                        INSERT INTO access_sessions
                            (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                        VALUES (?, ?, ?, ?, ?, 'ACTIVE', DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                    ")->execute([$requestId, $request['doctor_id'], $patientId, $request['hospital_id'], $tokenHash]);

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
           h.name AS hospital_name,
           TIMESTAMPDIFF(SECOND, NOW(), s.expires_at) AS seconds_remaining
    FROM access_sessions s
    JOIN doctors d ON s.doctor_id = d.id
    JOIN hospitals h ON s.hospital_id = h.id
    WHERE s.patient_id = ? AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ORDER BY s.expires_at ASC
");
$stmtActive->execute([$patientId]);
$activeSessions = $stmtActive->fetchAll();

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

// Welcome flag
$isNewUser = isset($_GET['welcome']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Dashboard — MedCore Patient Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body>

<div class="portal-layout">
  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="sidebar" role="navigation" aria-label="Patient portal navigation">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-tagline">Patient Portal</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/patient/dashboard.php" class="active" aria-current="page">
        <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i> Dashboard
      </a>

      <div class="sidebar-section-title">My Health</div>
      <a href="<?= APP_URL ?>/patient/medical-history.php">
        <i class="bi bi-clipboard2-heart-fill" aria-hidden="true"></i> Medical History
      </a>
      <a href="<?= APP_URL ?>/patient/timeline.php">
        <i class="bi bi-calendar2-heart-fill" aria-hidden="true"></i> Health Timeline
      </a>
      <a href="<?= APP_URL ?>/patient/medications.php">
        <i class="bi bi-capsule-pill" aria-hidden="true"></i> Medications
      </a>
      <a href="<?= APP_URL ?>/patient/allergies.php">
        <i class="bi bi-shield-exclamation" aria-hidden="true"></i> Allergies
      </a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php">
        <i class="bi bi-file-earmark-bar-graph-fill" aria-hidden="true"></i> Lab Reports
      </a>

      <div class="sidebar-section-title">Consultations</div>
      <a href="<?= APP_URL ?>/patient/prescriptions.php">
        <i class="bi bi-file-earmark-medical-fill" aria-hidden="true"></i> Prescriptions
      </a>

      <div class="sidebar-section-title">Privacy & Access</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php">
        <i class="bi bi-person-check-fill" aria-hidden="true"></i> Consent Requests
        <?php if (count($pendingRequests) > 0): ?>
          <span class="nav-badge"><?= count($pendingRequests) ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/patient/access-history.php">
        <i class="bi bi-clock-history" aria-hidden="true"></i> Access History
      </a>
      <a href="<?= APP_URL ?>/patient/notifications.php">
        <i class="bi bi-bell-fill" aria-hidden="true"></i> Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="nav-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>

      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/patient/profile.php">
        <i class="bi bi-person-circle" aria-hidden="true"></i> My Profile
      </a>
      <a href="<?= APP_URL ?>/patient/settings.php">
        <i class="bi bi-gear-fill" aria-hidden="true"></i> Settings
      </a>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="Log out of your session?">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log Out
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar" aria-hidden="true">
          <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
          <div class="name"><?= htmlspecialchars($patient['full_name']) ?></div>
          <div class="role"><?= htmlspecialchars($patient['patient_uid']) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <main class="portal-main">
    <!-- Top Bar -->
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>

      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">
          Welcome back, <?= htmlspecialchars(explode(' ', $patient['full_name'])[0]) ?> 👋
        </h2>
        <div style="font-size:12px;color:var(--mc-text-muted);">
          <?= $patient['patient_uid'] ?> · <?= date('D, d M Y') ?>
        </div>
      </div>

      <!-- Consent badge alert -->
      <?php if (count($pendingRequests) > 0): ?>
      <a href="#doctor-requests" class="btn btn-sm" style="background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;font-weight:700;box-shadow:0 2px 8px rgba(245,158,11,0.25);">
        <i class="bi bi-broadcast" style="color:#DC2626;"></i>
        <?= count($pendingRequests) ?> Doctor Request<?= count($pendingRequests) > 1 ? 's' : '' ?> (Action Required)
      </a>
      <?php endif; ?>

      <a href="<?= APP_URL ?>/patient/notifications.php" class="btn btn-secondary btn-sm" style="position:relative;">
        <i class="bi bi-bell"></i>
        <?php if ($unreadCount > 0): ?>
          <span style="position:absolute;top:-4px;right:-4px;background:var(--mc-red);color:white;width:18px;height:18px;border-radius:50%;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700;"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
    </header>

    <!-- Content -->
    <div class="portal-content">

      <!-- Flash Notification -->
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?> mb-6" data-auto-dismiss="6000" role="status">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : ($flash['type'] === 'info' ? 'info-circle-fill' : 'exclamation-circle-fill') ?>" style="font-size:1.3rem;flex-shrink:0;"></i>
        <div>
          <?php if (!empty($flash['title'])): ?><strong><?= htmlspecialchars($flash['title']) ?></strong><br><?php endif; ?>
          <?= htmlspecialchars($flash['msg']) ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ACTIVE ACCESS SESSIONS (CURRENTLY UNLOCKED) -->
      <?php if (!empty($activeSessions)): ?>
      <div class="card mb-6" style="border-left:4px solid var(--mc-green); background:linear-gradient(to right, #F0FDF4, #FFFFFF); box-shadow:0 4px 15px rgba(16,185,129,0.12);">
        <div class="d-flex align-center justify-between gap-3 mb-4" style="flex-wrap:wrap;">
          <div class="d-flex align-center gap-2">
            <i class="bi bi-shield-lock-fill" style="color:var(--mc-green); font-size:1.3rem;"></i>
            <div>
              <h3 style="font-size:1rem; font-weight:800; color:#065F46; margin:0;">Active Clinical Record Access</h3>
              <div style="font-size:12px; color:var(--mc-text-muted);">This physician currently has active temporary permission to view your records and prescribe</div>
            </div>
          </div>
          <span class="badge badge-success"><i class="bi bi-broadcast"></i> ACTIVE SESSION</span>
        </div>

        <?php foreach ($activeSessions as $s): ?>
        <div style="background:white; border:1px solid #BBF7D0; border-radius:var(--radius-md); padding:var(--space-4); margin-bottom:var(--space-3);" class="d-flex align-center justify-between gap-4 flex-wrap">
          <div class="d-flex align-center gap-3">
            <div style="width:44px; height:44px; border-radius:50%; background:#DCFCE7; color:#15803D; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0;">
              <i class="bi bi-person-badge-fill"></i>
            </div>
            <div>
              <div style="font-weight:700; font-size:15px; color:var(--mc-navy);">
                Dr. <?= htmlspecialchars($s['doctor_name']) ?>
                <span style="font-size:12px; font-weight:500; color:var(--mc-text-muted);">(BMDC Reg: <?= htmlspecialchars($s['medical_registration_id']) ?>)</span>
              </div>
              <div style="font-size:12px; color:var(--mc-text-muted);">
                <?= htmlspecialchars($s['specialization'] ?? 'Physician') ?> · <?= htmlspecialchars($s['hospital_name']) ?>
              </div>
            </div>
          </div>

          <div class="d-flex align-center gap-3">
            <div class="access-timer" style="background:#FEF3C7; border:1px solid #F59E0B; padding:6px 14px; border-radius:var(--radius-md); display:flex; align-items:center; gap:8px;">
              <i class="bi bi-stopwatch-fill" style="color:#D97706; font-size:1.1rem;"></i>
              <div>
                <div style="font-size:10px; font-weight:700; text-transform:uppercase; color:#92400E;">Time Remaining</div>
                <span class="timer-display" id="dash-timer-<?= $s['id'] ?>" style="font-size:1.15rem; color:#92400E; font-weight:800; font-family:var(--font-mono);">
                  <?= sprintf('%02d:%02d', floor($s['seconds_remaining'] / 60), $s['seconds_remaining'] % 60) ?>
                </span>
              </div>
            </div>

            <form method="POST" action="<?= APP_URL ?>/patient/dashboard.php" style="margin:0;" onsubmit="return confirm('Immediately revoke clinical access for Dr. <?= htmlspecialchars(addslashes($s['doctor_name'])) ?>?');">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
              <input type="hidden" name="action" value="revoke">
              <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--mc-red); border-color:#FCA5A5; background:#FEF2F2; font-weight:600;">
                <i class="bi bi-slash-circle"></i> Revoke Access
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- DOCTOR ACCESS REQUEST HERO BANNER (PENDING APPROVAL) -->
      <?php if (!empty($pendingRequests)): ?>
      <div id="doctor-requests" class="mb-6">
        <div style="background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border: 2px solid #F59E0B; border-radius: var(--radius-lg); padding: var(--space-5); box-shadow: 0 8px 25px rgba(245, 158, 11, 0.18);">
          
          <div class="d-flex align-center justify-between gap-3 mb-4" style="flex-wrap:wrap; border-bottom: 1px solid rgba(245, 158, 11, 0.3); padding-bottom: 12px;">
            <div class="d-flex align-center gap-2">
              <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#DC2626; box-shadow:0 0 0 4px rgba(220,38,38,0.25);"></span>
              <h3 style="font-size:1.05rem; font-weight:800; color:#92400E; margin:0; text-transform:uppercase; letter-spacing:0.5px;">
                Doctor Access Request Pending Your Approval
              </h3>
              <span class="badge" style="background:#F59E0B; color:white; font-weight:700;"><?= count($pendingRequests) ?> <?= count($pendingRequests) > 1 ? 'Doctors Waiting' : 'Doctor Waiting' ?></span>
            </div>
            <a href="<?= APP_URL ?>/patient/consent-requests.php" class="btn btn-secondary btn-sm" style="background:white; border-color:#F59E0B; color:#92400E; font-weight:600;">
              <i class="bi bi-shield-check"></i> Consent Center
            </a>
          </div>

          <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            <?php foreach ($pendingRequests as $req): ?>
            <div style="background:white; border:1px solid #FDE68A; border-radius:var(--radius-md); padding:var(--space-4); box-shadow:0 2px 8px rgba(0,0,0,0.04);">
              <div class="d-flex justify-between align-start gap-4" style="flex-wrap:wrap;">
                
                <!-- Doctor & Hospital Profile -->
                <div class="d-flex align-start gap-3" style="flex:1; min-width:280px;">
                  <div style="width:48px; height:48px; border-radius:50%; background:linear-gradient(135deg, #0EA5E9, #0284C7); color:white; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; box-shadow:0 4px 10px rgba(14,165,233,0.3);">
                    <i class="bi bi-stethoscope"></i>
                  </div>
                  <div>
                    <div class="d-flex align-center gap-2 flex-wrap">
                      <span style="font-weight:800; font-size:16px; color:var(--mc-navy);">Dr. <?= htmlspecialchars($req['doctor_name']) ?></span>
                      <span class="badge badge-primary" style="font-size:11px;">BMDC: <?= htmlspecialchars($req['medical_registration_id']) ?></span>
                    </div>
                    <div style="font-size:13px; font-weight:600; color:var(--mc-text-secondary); margin-top:2px;">
                      <?= htmlspecialchars($req['specialization'] ?? 'Physician') ?>
                    </div>
                    <div style="font-size:13px; color:var(--mc-text-muted); margin-top:4px;">
                      <i class="bi bi-hospital" style="color:var(--mc-teal);"></i>
                      <strong><?= htmlspecialchars($req['hospital_name']) ?></strong>
                      <?php if (!empty($req['hospital_city'])): ?>
                        <span>(<?= htmlspecialchars($req['hospital_city']) ?>)</span>
                      <?php endif; ?>
                    </div>

                    <!-- Clinical Reason Note -->
                    <div style="margin-top:10px; padding:10px 14px; background:#F8FAFC; border-left:3px solid var(--mc-teal); border-radius:var(--radius-sm); font-size:13px;">
                      <div style="font-weight:700; color:var(--mc-text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px;">
                        <i class="bi bi-chat-left-quote-fill" style="color:var(--mc-teal);"></i> Stated Consultation Purpose / Reason:
                      </div>
                      <div style="color:var(--mc-navy); font-weight:500;">
                        <?= !empty($req['doctor_notes']) ? htmlspecialchars($req['doctor_notes']) : 'Patient consultation, record evaluation, and prescription issuance.' ?>
                      </div>
                    </div>

                    <div style="font-size:11px; color:var(--mc-text-muted); margin-top:8px;">
                      <i class="bi bi-clock-history"></i> Requested: <?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?>
                      · <i class="bi bi-hourglass"></i> Grants <strong>30-Minute Temporary Access</strong>
                    </div>
                  </div>
                </div>

                <!-- Action Buttons: Accept & Decline -->
                <div class="d-flex flex-column gap-2" style="flex-shrink:0; min-width:220px; justify-content:center;">
                  <form method="POST" action="<?= APP_URL ?>/patient/dashboard.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success" style="width:100%; padding:12px 18px; font-weight:700; font-size:14px; display:inline-flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 12px rgba(16,185,129,0.35);">
                      <i class="bi bi-check-circle-fill" style="font-size:1.1rem;"></i> Accept &amp; Grant Access (30m)
                    </button>
                  </form>

                  <form method="POST" action="<?= APP_URL ?>/patient/dashboard.php" style="margin:0;" onsubmit="return confirm('Decline access request from Dr. <?= htmlspecialchars(addslashes($req['doctor_name'])) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                    <input type="hidden" name="action" value="deny">
                    <button type="submit" class="btn btn-secondary" style="width:100%; padding:8px 14px; font-size:13px; color:var(--mc-red); border-color:#FCA5A5; background:#FEF2F2; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                      <i class="bi bi-x-circle-fill"></i> Decline Request
                    </button>
                  </form>
                </div>

              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div style="margin-top:12px; font-size:12px; color:#92400E; display:flex; align-items:center; gap:6px;">
            <i class="bi bi-shield-lock-fill"></i>
            <span><strong>Your Privacy Protected:</strong> Granting access provides time-bounded (30 minutes) clinical permission for this doctor. You can revoke it anytime.</span>
          </div>

        </div>
      </div>
      <?php endif; ?>

      <!-- STATS GRID -->
      <div class="grid-4 mb-8" style="margin-bottom:var(--space-8);">
        <div class="stat-card">
          <div class="stat-icon stat-icon-blue"><i class="bi bi-file-earmark-medical-fill"></i></div>
          <div class="stat-value"><?= $stats['active_prescriptions'] ?></div>
          <div class="stat-label">Active Prescriptions</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-teal"><i class="bi bi-stethoscope"></i></div>
          <div class="stat-value"><?= $stats['total_consultations'] ?></div>
          <div class="stat-label">Total Consultations</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-amber"><i class="bi bi-file-earmark-bar-graph-fill"></i></div>
          <div class="stat-value"><?= $stats['lab_reports'] ?></div>
          <div class="stat-label">Lab Reports</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-green"><i class="bi bi-capsule-pill"></i></div>
          <div class="stat-value"><?= $stats['active_medications'] ?></div>
          <div class="stat-label">Active Medications</div>
        </div>
      </div>

      <!-- PATIENT SUMMARY CARD -->
      <div class="card mb-6">
        <div class="d-flex align-center justify-between mb-5" style="flex-wrap:wrap;gap:12px;">
          <div>
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px;">Personal Health Profile</h3>
            <div style="font-size:12px;color:var(--mc-text-muted);">Your core health information</div>
          </div>
          <a href="<?= APP_URL ?>/patient/profile.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-pencil-fill"></i> Edit Profile
          </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:var(--space-5);">
          <div>
            <div class="form-label">Patient ID</div>
            <div class="fw-600"><?= htmlspecialchars($patient['patient_uid']) ?></div>
          </div>
          <div>
            <div class="form-label">Full Name</div>
            <div class="fw-600"><?= htmlspecialchars($patient['full_name']) ?></div>
          </div>
          <div>
            <div class="form-label">Age / DOB</div>
            <div class="fw-600"><?= $age ?> years · <?= date('d M Y', strtotime($patient['date_of_birth'])) ?></div>
          </div>
          <div>
            <div class="form-label">Gender</div>
            <div class="fw-600"><?= ucfirst(strtolower($patient['gender'])) ?></div>
          </div>
          <div>
            <div class="form-label">Blood Group</div>
            <div class="fw-600">
              <span class="badge badge-error"><?= $patient['blood_group'] ?></span>
            </div>
          </div>
          <div>
            <div class="form-label">NID (Last 4)</div>
            <div class="fw-600">●●●●●● <?= htmlspecialchars($patient['nid_last4']) ?></div>
          </div>
          <div>
            <div class="form-label">Conditions</div>
            <div class="fw-600"><?= $stats['active_conditions'] ?> Active</div>
          </div>
          <div>
            <div class="form-label">Allergies</div>
            <div class="fw-600"><?= $stats['allergies'] ?> Documented</div>
          </div>
        </div>
      </div>

      <!-- TWO COLUMNS: Recent Prescriptions + Recent Consultations -->
      <div class="grid-2">

        <!-- Recent Prescriptions -->
        <div class="card">
          <div class="d-flex align-center justify-between mb-4">
            <h3 style="font-size:1rem;font-weight:700;">Recent Prescriptions</h3>
            <a href="<?= APP_URL ?>/patient/prescriptions.php" style="font-size:13px;">View All</a>
          </div>

          <?php if (empty($recentRx)): ?>
          <div class="empty-state" style="padding:var(--space-8) 0;">
            <div class="empty-state-icon"><i class="bi bi-file-earmark-medical"></i></div>
            <p>No prescriptions yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($recentRx as $rx): ?>
          <a href="<?= APP_URL ?>/patient/prescription-details.php?id=<?= $rx['id'] ?>"
             style="display:block;padding:12px 0;border-bottom:1px solid var(--mc-border);text-decoration:none;color:var(--mc-text);">
            <div class="d-flex align-center justify-between gap-3">
              <div>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($rx['prescription_uid']) ?></div>
                <div style="font-size:12px;color:var(--mc-text-muted);">
                  Dr. <?= htmlspecialchars($rx['doctor_name']) ?> · <?= $rx['medicines_count'] ?> medicine(s)
                </div>
                <div style="font-size:11px;color:var(--mc-text-muted);"><?= date('d M Y', strtotime($rx['created_at'])) ?></div>
              </div>
              <span class="badge badge-<?= $rx['status'] === 'ACTIVE' ? 'success' : 'neutral' ?>">
                <?= $rx['status'] ?>
              </span>
            </div>
          </a>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Recent Consultations -->
        <div class="card">
          <div class="d-flex align-center justify-between mb-4">
            <h3 style="font-size:1rem;font-weight:700;">Recent Consultations</h3>
            <a href="<?= APP_URL ?>/patient/timeline.php" style="font-size:13px;">View Timeline</a>
          </div>

          <?php if (empty($recentConsultations)): ?>
          <div class="empty-state" style="padding:var(--space-8) 0;">
            <div class="empty-state-icon"><i class="bi bi-stethoscope"></i></div>
            <p>No consultations yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($recentConsultations as $cons): ?>
          <div style="padding:12px 0;border-bottom:1px solid var(--mc-border);">
            <div class="d-flex align-center justify-between gap-3">
              <div>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($cons['consultation_uid']) ?></div>
                <div style="font-size:12px;color:var(--mc-text-muted);">
                  Dr. <?= htmlspecialchars($cons['doctor_name']) ?> · <?= htmlspecialchars($cons['hospital_name']) ?>
                </div>
                <?php if ($cons['chief_complaint']): ?>
                <div style="font-size:11px;color:var(--mc-text-secondary);margin-top:2px;">
                  <?= htmlspecialchars(mb_strimwidth($cons['chief_complaint'], 0, 50, '...')) ?>
                </div>
                <?php endif; ?>
              </div>
              <div style="text-align:right;white-space:nowrap;">
                <div style="font-size:11px;color:var(--mc-text-muted);"><?= date('d M Y', strtotime($cons['consultation_date'])) ?></div>
                <?php if ($cons['follow_up_date']): ?>
                <div style="font-size:11px;color:var(--mc-teal);">Follow-up: <?= date('d M', strtotime($cons['follow_up_date'])) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /portal-content -->
  </main>
</div>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
// Sidebar toggle for mobile
document.getElementById('hamburger-btn')?.addEventListener('click', function() {
  const sidebar = document.getElementById('sidebar');
  const expanded = this.getAttribute('aria-expanded') === 'true';
  this.setAttribute('aria-expanded', !expanded);
  sidebar.classList.toggle('open');
});

<?php if (!empty($activeSessions)): ?>
// Initialize countdown timers for active clinical sessions
<?php foreach ($activeSessions as $s): ?>
startAccessTimer('dash-timer-<?= $s['id'] ?>', <?= max(0, (int)$s['seconds_remaining']) ?>, function() { location.reload(); });
<?php endforeach; ?>
<?php endif; ?>
</script>
</body>
</html>
