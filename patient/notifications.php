<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];
$userId    = (int)$_SESSION['user_id'];

// Auto-expire stale access requests
$db->prepare("UPDATE access_requests SET status = 'EXPIRED' WHERE status = 'PENDING' AND expires_at IS NOT NULL AND expires_at < NOW()")->execute();

// Handle notification actions (Mark all read, mark single read, or approve/deny request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'All notifications marked as read.'];
        header('Location: ' . APP_URL . '/patient/notifications.php');
        exit;
    }

    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$notifId, $userId]);
        header('Location: ' . APP_URL . '/patient/notifications.php');
        exit;
    }

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
                    $db->prepare("UPDATE access_requests SET status = 'APPROVED', approved_at = NOW() WHERE id = ?")
                       ->execute([$requestId]);

                    $sessionToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $sessionToken);
                    $db->prepare("
                        INSERT INTO access_sessions
                            (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                        VALUES (?, ?, ?, ?, ?, 'ACTIVE', DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                    ")->execute([$requestId, $request['doctor_id'], $patientId, $request['hospital_id'], $tokenHash]);

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

                    // Mark this notification as read
                    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND reference_type = 'access_request' AND reference_id = ?")->execute([$userId, $requestId]);

                    $db->commit();

                    AuditService::log(AuditService::ACCESS_GRANTED, [
                        'user_id'    => $userId,
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
            } else {
                $db->prepare("UPDATE access_requests SET status = 'DENIED' WHERE id = ?")->execute([$requestId]);
                $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND reference_type = 'access_request' AND reference_id = ?")->execute([$userId, $requestId]);

                AuditService::log(AuditService::ACCESS_DENIED, [
                    'user_id'    => $userId,
                    'patient_id' => $patientId,
                    'doctor_id'  => $request['doctor_id'],
                    'hospital_id'=> $request['hospital_id'],
                    'metadata'   => ['request_id' => $requestId],
                ]);

                $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Access request from Dr. ' . $request['doctor_name'] . ' has been declined.'];
            }
        }

        header('Location: ' . APP_URL . '/patient/notifications.php');
        exit;
    }
}

// Fetch all notifications with linked request details if applicable
$stmt = $db->prepare("
    SELECT n.*,
           ar.status AS request_status, ar.doctor_notes, ar.expires_at AS request_expires_at,
           d.full_name AS doctor_name, d.specialization, d.medical_registration_id,
           h.name AS hospital_name
    FROM notifications n
    LEFT JOIN access_requests ar ON (n.reference_type = 'access_request' AND n.reference_id = ar.id)
    LEFT JOIN doctors d ON ar.doctor_id = d.id
    LEFT JOIN hospitals h ON ar.hospital_id = h.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

// Count unread
$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications — MedCore Patient Portal</title>
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
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-tagline">Patient Portal</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/patient/dashboard.php">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
      </a>
      <div class="sidebar-section-title">My Health</div>
      <a href="<?= APP_URL ?>/patient/medical-history.php"><i class="bi bi-clipboard2-heart-fill"></i> Medical History</a>
      <a href="<?= APP_URL ?>/patient/timeline.php"><i class="bi bi-calendar2-heart-fill"></i> Health Timeline</a>
      <a href="<?= APP_URL ?>/patient/medications.php"><i class="bi bi-capsule-pill"></i> Medications</a>
      <a href="<?= APP_URL ?>/patient/allergies.php"><i class="bi bi-shield-exclamation"></i> Allergies</a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Lab Reports</a>
      <div class="sidebar-section-title">Consultations</div>
      <a href="<?= APP_URL ?>/patient/prescriptions.php"><i class="bi bi-file-earmark-medical-fill"></i> Prescriptions</a>
      <div class="sidebar-section-title">Privacy &amp; Access</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php"><i class="bi bi-person-check-fill"></i> Consent Requests</a>
      <a href="<?= APP_URL ?>/patient/access-history.php"><i class="bi bi-clock-history"></i> Access History</a>
      <a href="<?= APP_URL ?>/patient/notifications.php" class="active" aria-current="page">
        <i class="bi bi-bell-fill"></i> Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="nav-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
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

  <!-- MAIN CONTENT -->
  <main class="portal-main">
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Notifications &amp; Activity</h2>
        <div style="font-size:12px;color:var(--mc-text-muted);">Stay updated with doctor requests and health record activities</div>
      </div>
      <?php if ($unreadCount > 0): ?>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-secondary btn-sm">
          <i class="bi bi-check2-all"></i> Mark All as Read
        </button>
      </form>
      <?php endif; ?>
    </header>

    <div class="portal-content">
      <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?> mb-6" data-auto-dismiss="5000">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle-fill' : 'info-circle-fill' ?>"></i>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="d-flex align-center justify-between gap-3 mb-4" style="flex-wrap:wrap;">
          <div>
            <h3 style="font-size:1.1rem;font-weight:700;margin:0;">Recent Notifications</h3>
            <div style="font-size:12px;color:var(--mc-text-muted);">
              <?= $unreadCount ?> unread notification<?= $unreadCount !== 1 ? 's' : '' ?>
            </div>
          </div>
          <a href="<?= APP_URL ?>/patient/dashboard.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
          </a>
        </div>

        <?php if (empty($notifications)): ?>
        <div class="empty-state" style="padding:var(--space-8) 0;">
          <div class="empty-state-icon"><i class="bi bi-bell-slash"></i></div>
          <p>You have no notifications yet.<br>All doctor requests and prescription alerts will appear here.</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:var(--space-3);">
          <?php foreach ($notifications as $n): ?>
          <?php
          $isPendingRequest = ($n['type'] === 'ACCESS_REQUEST' && $n['request_status'] === 'PENDING' && (empty($n['request_expires_at']) || strtotime($n['request_expires_at']) > time()));
          ?>
          <div style="padding:var(--space-4);border-radius:var(--radius-md);border:1px solid <?= $isPendingRequest ? '#F59E0B' : 'var(--mc-border)' ?>;background:<?= $isPendingRequest ? '#FFFBEB' : ($n['is_read'] ? 'white' : '#F0F9FF') ?>;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
            <div class="d-flex align-start justify-between gap-4 flex-wrap">
              <div class="d-flex align-start gap-3" style="flex:1;min-width:280px;">
                <div style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1rem;<?= $isPendingRequest ? 'background:#FEF3C7;color:#D97706;' : ($n['is_read'] ? 'background:#F1F5F9;color:#64748B;' : 'background:#E0F2FE;color:#0284C7;') ?>">
                  <i class="bi <?= $n['type'] === 'ACCESS_REQUEST' ? 'bi-stethoscope' : ($n['type'] === 'PRESCRIPTION' ? 'bi-file-earmark-medical' : 'bi-bell') ?>"></i>
                </div>
                <div>
                  <div class="d-flex align-center gap-2 flex-wrap">
                    <strong style="font-size:14px;color:var(--mc-navy);"><?= htmlspecialchars($n['title']) ?></strong>
                    <?php if (!$n['is_read']): ?>
                    <span class="badge badge-primary" style="font-size:10px;">NEW</span>
                    <?php endif; ?>
                    <?php if ($isPendingRequest): ?>
                    <span class="badge badge-warning" style="font-size:10px;">WAITING APPROVAL</span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size:13px;color:var(--mc-text-secondary);margin-top:4px;">
                    <?= htmlspecialchars($n['message']) ?>
                  </div>

                  <?php if (!empty($n['doctor_notes'])): ?>
                  <div style="font-size:12px;background:white;padding:6px 10px;border-radius:var(--radius-sm);margin-top:6px;border-left:3px solid var(--mc-teal);">
                    <strong>Doctor's Stated Reason:</strong> <?= htmlspecialchars($n['doctor_notes']) ?>
                  </div>
                  <?php endif; ?>

                  <div style="font-size:11px;color:var(--mc-text-muted);margin-top:6px;">
                    <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div class="d-flex align-center gap-2" style="flex-shrink:0;">
                <?php if ($isPendingRequest && !empty($n['reference_id'])): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="request_id" value="<?= $n['reference_id'] ?>">
                  <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" class="btn btn-success btn-sm" style="font-weight:700;box-shadow:0 2px 6px rgba(16,185,129,0.3);">
                    <i class="bi bi-check-circle-fill"></i> Accept (30m)
                  </button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Decline this doctor access request?');">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="request_id" value="<?= $n['reference_id'] ?>">
                  <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                  <input type="hidden" name="action" value="deny">
                  <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--mc-red);border-color:#FCA5A5;background:#FEF2F2;">
                    <i class="bi bi-x-circle"></i> Decline
                  </button>
                </form>
                <?php elseif (!empty($n['action_url'])): ?>
                <a href="<?= htmlspecialchars($n['action_url']) ?>" class="btn btn-secondary btn-sm">
                  View <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>

                <?php if (!$n['is_read']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                  <input type="hidden" name="action" value="mark_read">
                  <button type="submit" class="btn btn-secondary btn-sm" title="Mark as read">
                    <i class="bi bi-check-lg"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>

            </div>
          </div>
          <?php endforeach; ?>
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
</script>
</body>
</html>
