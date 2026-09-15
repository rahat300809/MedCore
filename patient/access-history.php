<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Handle revoke active session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_session'])) {
    validateCsrf();
    $sessionId = (int)$_POST['session_id'];

    $stmt = $db->prepare("
        UPDATE access_sessions
        SET status = 'REVOKED', revoked_at = NOW()
        WHERE id = ? AND patient_id = ? AND status = 'ACTIVE'
    ");
    $stmt->execute([$sessionId, $patientId]);

    AuditService::log('ACCESS_REVOKED_BY_PATIENT', [
        'user_id'    => $_SESSION['user_id'],
        'patient_id' => $patientId,
        'metadata'   => ['session_id' => $sessionId],
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Doctor access session has been immediately revoked.'];
    header('Location: ' . APP_URL . '/patient/access-history.php');
    exit;
}

// 1. Active sessions
$stmtActive = $db->prepare("
    SELECT s.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name,
           TIMESTAMPDIFF(SECOND, NOW(), s.expires_at) AS seconds_left
    FROM access_sessions s
    JOIN doctors d ON s.doctor_id = d.id
    JOIN hospitals h ON s.hospital_id = h.id
    WHERE s.patient_id = ? AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ORDER BY s.expires_at DESC
");
$stmtActive->execute([$patientId]);
$activeSessions = $stmtActive->fetchAll();

// 2. Past Access Sessions
$stmtPast = $db->prepare("
    SELECT s.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM access_sessions s
    JOIN doctors d ON s.doctor_id = d.id
    JOIN hospitals h ON s.hospital_id = h.id
    WHERE s.patient_id = ? AND (s.status != 'ACTIVE' OR s.expires_at <= NOW())
    ORDER BY s.started_at DESC
    LIMIT 20
");
$stmtPast->execute([$patientId]);
$pastSessions = $stmtPast->fetchAll();

// 3. All Access Requests
$stmtReqs = $db->prepare("
    SELECT ar.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM access_requests ar
    JOIN doctors d ON ar.doctor_id = d.id
    JOIN hospitals h ON ar.hospital_id = h.id
    WHERE ar.patient_id = ?
    ORDER BY ar.requested_at DESC
    LIMIT 25
");
$stmtReqs->execute([$patientId]);
$allRequests = $stmtReqs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access History &amp; Privacy Log — MedCore Patient Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Patient Portal</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">My Health Record</div>
      <a href="<?= APP_URL ?>/patient/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/patient/prescriptions.php" class="nav-item">
        <i class="bi bi-prescription2"></i>
        <span>Prescriptions</span>
      </a>
      <a href="<?= APP_URL ?>/patient/medical-history.php" class="nav-item">
        <i class="bi bi-heart-pulse-fill"></i>
        <span>Medical History</span>
      </a>
      <a href="<?= APP_URL ?>/patient/medications.php" class="nav-item">
        <i class="bi bi-capsule-pill"></i>
        <span>Medications</span>
      </a>
      <a href="<?= APP_URL ?>/patient/allergies.php" class="nav-item">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span>Allergies</span>
      </a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php" class="nav-item">
        <i class="bi bi-file-earmark-medical"></i>
        <span>Lab Reports</span>
      </a>

      <div class="nav-section-title">Privacy &amp; Security</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php" class="nav-item">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Consent Requests</span>
      </a>
      <a href="<?= APP_URL ?>/patient/access-history.php" class="nav-item active">
        <i class="bi bi-clock-history"></i>
        <span>Access History</span>
      </a>
      <a href="<?= APP_URL ?>/patient/profile.php" class="nav-item">
        <i class="bi bi-person-circle"></i>
        <span>My Profile</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'P', 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Patient') ?></div>
          <div class="user-role">Verified Patient</div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-outline btn-sm" style="width: 100%; margin-top: var(--space-3);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-header">
      <div class="d-flex align-items-center gap-3">
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Access History &amp; Privacy Audit</h1>
      </div>
    </header>

    <div class="portal-body">

      <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?> mb-4">
          <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
      <?php endif; ?>

      <!-- 1. Live Active Sessions -->
      <div class="card mb-4" style="border-left: 5px solid var(--mc-blue);">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-broadcast"></i> Currently Active Access Sessions</h3>
          <span class="badge badge-success"><?= count($activeSessions) ?> Active</span>
        </div>
        <div class="card-body">
          <?php if (empty($activeSessions)): ?>
            <div class="text-muted py-3">
              <i class="bi bi-shield-check text-success"></i> No doctor currently has an active viewing session to your health record.
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Doctor</th>
                    <th>Hospital</th>
                    <th>Started</th>
                    <th>Expires In</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($activeSessions as $s): ?>
                    <tr>
                      <td>
                        <strong>Dr. <?= htmlspecialchars($s['doctor_name']) ?></strong>
                        <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($s['specialization'] ?? '') ?></div>
                      </td>
                      <td><?= htmlspecialchars($s['hospital_name']) ?></td>
                      <td><?= date('h:i A', strtotime($s['started_at'])) ?></td>
                      <td>
                        <span class="badge badge-warning">
                          <i class="bi bi-hourglass-split"></i> <?= ceil($s['seconds_left'] / 60) ?> mins remaining
                        </span>
                      </td>
                      <td>
                        <form method="POST" onsubmit="return confirm('Immediately revoke this doctor\'s access?');">
                          <?= csrfField() ?>
                          <input type="hidden" name="revoke_session" value="1">
                          <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                          <button type="submit" class="btn btn-outline text-danger btn-sm">
                            <i class="bi bi-shield-x"></i> Revoke Now
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 2. Past Access Sessions Log -->
      <div class="card mb-4">
        <div class="card-header">
          <h3 class="card-title"><i class="bi bi-clock-history"></i> Past Record Access Sessions (<?= count($pastSessions) ?>)</h3>
        </div>
        <div class="card-body">
          <?php if (empty($pastSessions)): ?>
            <div class="text-muted py-3">No past access sessions recorded.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Doctor</th>
                    <th>Hospital</th>
                    <th>Access Window</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pastSessions as $s): ?>
                    <tr>
                      <td>Dr. <?= htmlspecialchars($s['doctor_name']) ?></td>
                      <td><?= htmlspecialchars($s['hospital_name']) ?></td>
                      <td>
                        <?= date('d M Y, h:i A', strtotime($s['started_at'])) ?> &rarr;
                        <?= date('h:i A', strtotime($s['expires_at'])) ?>
                      </td>
                      <td>
                        <?php if ($s['status'] === 'REVOKED'): ?>
                          <span class="badge badge-danger">Revoked by Patient</span>
                        <?php else: ?>
                          <span class="badge badge-secondary">Expired Normally</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 3. All Access Requests Audit -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="bi bi-shield-lock"></i> Consent Requests Audit Log (<?= count($allRequests) ?>)</h3>
        </div>
        <div class="card-body">
          <?php if (empty($allRequests)): ?>
            <div class="text-muted py-3">No consent requests recorded.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Hospital</th>
                    <th>Reason Given</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allRequests as $req): ?>
                    <tr>
                      <td><?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?></td>
                      <td>Dr. <?= htmlspecialchars($req['doctor_name']) ?></td>
                      <td><?= htmlspecialchars($req['hospital_name']) ?></td>
                      <td><?= htmlspecialchars($req['patient_message'] ?? $req['doctor_notes'] ?? 'Clinical evaluation') ?></td>
                      <td>
                        <?php if ($req['status'] === 'APPROVED'): ?>
                          <span class="badge badge-success">Approved</span>
                        <?php elseif ($req['status'] === 'PENDING'): ?>
                          <span class="badge badge-warning">Pending</span>
                        <?php elseif ($req['status'] === 'DENIED'): ?>
                          <span class="badge badge-danger">Denied</span>
                        <?php else: ?>
                          <span class="badge badge-secondary"><?= htmlspecialchars($req['status']) ?></span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>
