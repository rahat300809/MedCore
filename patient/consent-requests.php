<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Handle approve / deny
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $requestId = (int)($_POST['request_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    // Verify the request belongs to this patient
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
                // Update access request status
                $db->prepare("UPDATE access_requests SET status = 'APPROVED', approved_at = NOW() WHERE id = ?")
                   ->execute([$requestId]);

                // Create access session (30 minutes)
                $sessionToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $sessionToken);
                $db->prepare("
                    INSERT INTO access_sessions
                        (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                    VALUES (?, ?, ?, ?, ?, 'ACTIVE', DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                ")->execute([$requestId, $request['doctor_id'], $patientId, $request['hospital_id'], $tokenHash]);

                // Notify doctor
                $db->prepare("
                    INSERT INTO notifications (user_id, type, title, message, reference_type, reference_id, action_url)
                    SELECT u.id, 'ACCESS_GRANTED',
                           'Access Granted by Patient',
                           'Your request to access a patient record has been approved. 30-minute session started.',
                           'access_session', :req_id, :action_url
                    FROM doctors d JOIN users u ON u.id = d.user_id
                    WHERE d.id = :did
                ")->execute([
                    ':req_id'     => $requestId,
                    ':action_url' => APP_URL . '/doctor/medical-record.php?patient_id=' . $patientId,
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

                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Access granted to Dr. ' . $request['doctor_name'] . ' for 30 minutes.'];
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Failed to grant access: ' . $e->getMessage()];
            }

        } elseif ($action === 'deny') {
            $db->prepare("UPDATE access_requests SET status = 'DENIED' WHERE id = ?")
               ->execute([$requestId]);

            AuditService::log(AuditService::ACCESS_DENIED, [
                'user_id'    => $_SESSION['user_id'],
                'patient_id' => $patientId,
                'doctor_id'  => $request['doctor_id'],
                'hospital_id'=> $request['hospital_id'],
                'metadata'   => ['request_id' => $requestId],
            ]);

            $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Access request from Dr. ' . $request['doctor_name'] . ' has been denied.'];
        }
    }

    header('Location: ' . APP_URL . '/patient/consent-requests.php');
    exit;
}

// Fetch pending requests
$stmt = $db->prepare("
    SELECT ar.*, d.full_name AS doctor_name, d.specialization, d.medical_registration_id,
           h.name AS hospital_name, h.type AS hospital_type, h.city AS hospital_city
    FROM access_requests ar
    JOIN doctors d ON ar.doctor_id = d.id
    JOIN hospitals h ON ar.hospital_id = h.id
    WHERE ar.patient_id = ? AND ar.status = 'PENDING'
    ORDER BY ar.requested_at DESC
");
$stmt->execute([$patientId]);
$pending = $stmt->fetchAll();

// Active sessions
$stmt = $db->prepare("
    SELECT acs.*, d.full_name AS doctor_name, d.specialization,
           h.name AS hospital_name,
           TIMESTAMPDIFF(SECOND, NOW(), acs.expires_at) AS secs_remaining
    FROM access_sessions acs
    JOIN doctors d ON acs.doctor_id = d.id
    JOIN hospitals h ON acs.hospital_id = h.id
    WHERE acs.patient_id = ? AND acs.status = 'ACTIVE' AND acs.expires_at > NOW()
    ORDER BY acs.expires_at ASC
");
$stmt->execute([$patientId]);
$activeSessions = $stmt->fetchAll();

// Historical requests
$stmt = $db->prepare("
    SELECT ar.*, d.full_name AS doctor_name, h.name AS hospital_name
    FROM access_requests ar
    JOIN doctors d ON ar.doctor_id = d.id
    JOIN hospitals h ON ar.hospital_id = h.id
    WHERE ar.patient_id = ? AND ar.status != 'PENDING'
    ORDER BY ar.requested_at DESC
    LIMIT 15
");
$stmt->execute([$patientId]);
$history = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consent Requests — MedCore Patient Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body>
<div class="portal-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;">M</div>
      <div><div class="brand-name">MedCore</div><div class="brand-tagline">Patient Portal</div></div>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/patient/dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
      <div class="sidebar-section-title">My Health</div>
      <a href="<?= APP_URL ?>/patient/medical-history.php"><i class="bi bi-clipboard2-heart-fill"></i> Medical History</a>
      <a href="<?= APP_URL ?>/patient/medications.php"><i class="bi bi-capsule-pill"></i> Medications</a>
      <a href="<?= APP_URL ?>/patient/prescriptions.php"><i class="bi bi-file-earmark-medical-fill"></i> Prescriptions</a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Lab Reports</a>
      <div class="sidebar-section-title">Privacy</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php" class="active" aria-current="page">
        <i class="bi bi-person-check-fill"></i> Consent Requests
        <?php if (count($pending) > 0): ?><span class="nav-badge"><?= count($pending) ?></span><?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/patient/access-history.php"><i class="bi bi-clock-history"></i> Access History</a>
      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/patient/profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="Log out?"><i class="bi bi-box-arrow-right"></i> Log Out</a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'P', 0, 1)) ?></div>
        <div class="sidebar-user-info">
          <div class="name"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></div>
          <div class="role"><?= htmlspecialchars($_SESSION['patient_uid'] ?? '') ?></div>
        </div>
      </div>
    </div>
  </aside>

  <main class="portal-main">
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Consent &amp; Access Control</h2>
        <div style="font-size:12px;color:var(--mc-text-muted);">You control who sees your medical record</div>
      </div>
    </header>

    <div class="portal-content">

      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?> mb-4" data-auto-dismiss="5000">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : ($flash['type'] === 'info' ? 'info-circle-fill' : 'exclamation-circle-fill') ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <!-- Active sessions -->
      <?php if (!empty($activeSessions)): ?>
      <div class="card mb-6" style="border-left:3px solid var(--mc-green);">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:var(--space-4);">
          <i class="bi bi-shield-lock-fill text-green"></i>
          Currently Active Access Sessions
        </h3>
        <?php foreach ($activeSessions as $s): ?>
        <?php $mins = max(0, (int)ceil($s['secs_remaining'] / 60)); ?>
        <div style="display:flex;align-items:center;gap:var(--space-4);padding:12px 0;border-bottom:1px solid var(--mc-border);flex-wrap:wrap;">
          <div style="flex:1;">
            <div style="font-weight:700;font-size:14px;">Dr. <?= htmlspecialchars($s['doctor_name']) ?></div>
            <div style="font-size:12px;color:var(--mc-text-muted);"><?= htmlspecialchars($s['specialization'] ?? '') ?> · <?= htmlspecialchars($s['hospital_name']) ?></div>
          </div>
          <div class="access-timer" style="<?= $mins <= 5 ? 'background:var(--mc-red-light);border-color:var(--mc-red);' : '' ?>">
            <i class="bi bi-clock-fill"></i>
            <span class="timer-display" id="stimer-<?= $s['id'] ?>"><?= str_pad($mins, 2, '0', STR_PAD_LEFT) ?>:00</span>
          </div>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="deny">
            <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn btn-secondary btn-sm" data-confirm="Revoke access for Dr. <?= htmlspecialchars($s['doctor_name']) ?>?">
              <i class="bi bi-x-circle-fill"></i> Revoke
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Pending requests -->
      <div class="card mb-6">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:var(--space-4);">
          Pending Consent Requests
          <?php if (count($pending) > 0): ?><span class="badge badge-warning" style="margin-left:8px;"><?= count($pending) ?></span><?php endif; ?>
        </h3>

        <?php if (empty($pending)): ?>
        <div class="empty-state" style="padding:var(--space-8) 0;">
          <div class="empty-state-icon"><i class="bi bi-person-check"></i></div>
          <p>No pending access requests.<br>You're in full control.</p>
        </div>
        <?php else: ?>
        <?php foreach ($pending as $req): ?>
        <div style="padding:var(--space-5) 0;border-bottom:1px solid var(--mc-border);">
          <div class="d-flex align-center justify-between gap-4" style="flex-wrap:wrap;">
            <div class="d-flex align-center gap-4">
              <div class="stat-icon stat-icon-blue" style="flex-shrink:0;">
                <i class="bi bi-stethoscope"></i>
              </div>
              <div>
                <div style="font-weight:700;font-size:14px;">Dr. <?= htmlspecialchars($req['doctor_name']) ?></div>
                <div style="font-size:12px;color:var(--mc-text-muted);">
                  <?= htmlspecialchars($req['specialization'] ?? 'Physician') ?>
                  · Reg: <code><?= htmlspecialchars($req['medical_registration_id']) ?></code>
                </div>
                <div style="font-size:12px;color:var(--mc-text-muted);">
                  <i class="bi bi-building-fill-check"></i>
                  <?= htmlspecialchars($req['hospital_name']) ?> (<?= ucfirst(strtolower($req['hospital_type'])) ?>)
                </div>
                <?php if ($req['access_reason']): ?>
                <div style="font-size:12px;background:var(--mc-bg);padding:4px 10px;border-radius:var(--radius-sm);margin-top:6px;">
                  <i class="bi bi-chat-quote-fill" style="color:var(--mc-blue);"></i>
                  <em><?= htmlspecialchars($req['access_reason']) ?></em>
                </div>
                <?php endif; ?>
                <div style="font-size:11px;color:var(--mc-text-muted);margin-top:4px;">
                  Requested: <?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?>
                  · Expires: <?= date('h:i A', strtotime($req['expires_at'])) ?>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2" style="flex-shrink:0;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-success">
                  <i class="bi bi-check-circle-fill"></i> Approve (30 min)
                </button>
              </form>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="action" value="deny">
                <button type="submit" class="btn btn-secondary">
                  <i class="bi bi-x-circle-fill"></i> Deny
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Access history -->
      <div class="card">
        <div class="d-flex align-center justify-between mb-4">
          <h3 style="font-size:1rem;font-weight:700;">Access History</h3>
          <a href="<?= APP_URL ?>/patient/access-history.php" style="font-size:13px;">Full History</a>
        </div>

        <?php if (empty($history)): ?>
        <div class="empty-state" style="padding:var(--space-6) 0;">
          <div class="empty-state-icon"><i class="bi bi-clock-history"></i></div>
          <p>No past access requests.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Doctor</th><th>Hospital</th><th>Requested</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
              <td><strong><?= htmlspecialchars($h['doctor_name']) ?></strong></td>
              <td><?= htmlspecialchars($h['hospital_name']) ?></td>
              <td style="white-space:nowrap;font-size:12px;"><?= date('d M Y, h:i A', strtotime($h['requested_at'])) ?></td>
              <td>
                <?php
                $statusClass = match($h['status']) {
                    'APPROVED' => 'success', 'DENIED' => 'error',
                    'EXPIRED' => 'neutral', default => 'warning'
                };
                ?>
                <span class="badge badge-<?= $statusClass ?>"><?= $h['status'] ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
document.getElementById('hamburger-btn')?.addEventListener('click', function() {
  document.getElementById('sidebar').classList.toggle('open');
  this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') !== 'true');
});
<?php foreach ($activeSessions as $s): ?>
startAccessTimer('stimer-<?= $s['id'] ?>', <?= max(0, (int)$s['secs_remaining']) ?>, function() { location.reload(); });
<?php endforeach; ?>
</script>
</body>
</html>
