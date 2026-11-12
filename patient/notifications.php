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
                    $sessExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $db->prepare("
                        INSERT INTO access_sessions
                            (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                        VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?)
                    ")->execute([$requestId, $request['doctor_id'], $patientId, $request['hospital_id'], $tokenHash, $sessExpiresAt]);

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

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalAccent = '#0f766e';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · Notifications';
$portalTitle = 'Notifications & activity';
$portalSubtitle = 'ডাক্তার রিকোয়েস্ট ও রিপোর্ট আপডেট (' . $unreadCount . ' unread)';
$portalNav = mc_patient_nav('notifications');
$portalTopAction = $unreadCount > 0
    ? '<form method="POST" style="margin:0"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf) . '"><input type="hidden" name="action" value="mark_all_read"><button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm"><i class="bi bi-check2-all"></i> Mark all read</button></form>'
    : '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/patient/dashboard.php">Dashboard</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flash): ?>
  <div class="mc-alert <?= ($flash['type'] ?? '') === 'success' ? 'mc-alert-ok' : (($flash['type'] ?? '') === 'info' ? 'mc-alert-info' : 'mc-alert-danger') ?>">
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Recent notifications</h2>
    <span class="lab-status <?= $unreadCount > 0 ? 'info' : '' ?>"><?= $unreadCount ?> unread</span>
  </div>

  <?php if (empty($notifications)): ?>
    <div style="text-align:center;padding:2.2rem 1rem">
      <p style="margin:0;color:var(--muted)">You have no notifications yet. Doctor requests and lab alerts will appear here.</p>
    </div>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($notifications as $n):
        $isPendingRequest = ($n['type'] === 'ACCESS_REQUEST' && $n['request_status'] === 'PENDING' && (empty($n['request_expires_at']) || strtotime($n['request_expires_at']) > time()));
      ?>
        <div class="mc-list-item" style="align-items:flex-start;flex-wrap:wrap;gap:.85rem;background:<?= $isPendingRequest ? '#fffbeb' : ($n['is_read'] ? '#fff' : '#eff8ff') ?>;border-color:<?= $isPendingRequest ? '#fedf89' : 'var(--line)' ?>">
          <div style="flex:1;min-width:240px">
            <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
              <strong><?= htmlspecialchars($n['title']) ?></strong>
              <?php if (!$n['is_read']): ?><span class="lab-status info">NEW</span><?php endif; ?>
              <?php if ($isPendingRequest): ?><span class="lab-status warn">WAITING APPROVAL</span><?php endif; ?>
            </div>
            <div style="font-size:.88rem;color:var(--ink-2);margin-top:.3rem"><?= htmlspecialchars($n['message']) ?></div>
            <?php if (!empty($n['doctor_notes'])): ?>
              <div style="font-size:.82rem;background:#fff;padding:.45rem .7rem;border-radius:8px;margin-top:.45rem;border-left:3px solid var(--accent)">
                <strong>Doctor's reason:</strong> <?= htmlspecialchars($n['doctor_notes']) ?>
              </div>
            <?php endif; ?>
            <div style="font-size:.78rem;color:var(--muted);margin-top:.35rem">
              <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap">
            <?php if ($isPendingRequest && !empty($n['reference_id'])): ?>
              <form method="POST" style="display:inline;margin:0">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="request_id" value="<?= (int)$n['reference_id'] ?>">
                <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm"><i class="bi bi-check-circle-fill"></i> Accept (30m)</button>
              </form>
              <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Decline this doctor access request?');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="request_id" value="<?= (int)$n['reference_id'] ?>">
                <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
                <input type="hidden" name="action" value="deny">
                <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm">Decline</button>
              </form>
            <?php elseif (!empty($n['action_url'])): ?>
              <a href="<?= htmlspecialchars($n['action_url']) ?>" class="mc-btn mc-btn-secondary mc-btn-sm">View →</a>
            <?php endif; ?>

            <?php if (!$n['is_read']): ?>
              <form method="POST" style="display:inline;margin:0">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>">
                <input type="hidden" name="action" value="mark_read">
                <button type="submit" class="mc-btn mc-btn-ghost mc-btn-sm" title="Mark as read"><i class="bi bi-check-lg"></i></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
