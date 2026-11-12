<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Handle approve / deny / revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $requestId = (int)($_POST['request_id'] ?? 0);
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    // 1. If revoking an active session
    if ($action === 'revoke' || ($sessionId > 0 && $action === 'deny')) {
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
            $db->prepare("UPDATE access_sessions SET status = 'REVOKED', revoked_at = NOW() WHERE id = ?")
               ->execute([$sessionId]);

            AuditService::log('ACCESS_REVOKED_BY_PATIENT', [
                'user_id'    => $_SESSION['user_id'],
                'patient_id' => $patientId,
                'doctor_id'  => $session['doctor_id'],
                'hospital_id'=> $session['hospital_id'],
                'metadata'   => ['session_id' => $sessionId, 'doctor_name' => $session['doctor_name']],
            ]);

            $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Clinical access for Dr. ' . $session['doctor_name'] . ' has been immediately revoked.'];
        }
    } else {
        // 2. Handling pending request approval or denial
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
                    $sessExpiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $db->prepare("
                        INSERT INTO access_sessions
                            (access_request_id, doctor_id, patient_id, hospital_id, token_hash, status, expires_at)
                        VALUES (?, ?, ?, ?, ?, 'ACTIVE', ?)
                    ")->execute([$requestId, $request['doctor_id'], $patientId, $request['hospital_id'], $tokenHash, $sessExpiresAt]);

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

                    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Access granted to Dr. ' . $request['doctor_name'] . ' for 30 minutes. The doctor can now view your record.'];
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

                $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Access request from Dr. ' . $request['doctor_name'] . ' has been declined.'];
            }
        }
    }

    header('Location: ' . APP_URL . '/patient/consent-requests.php');
    exit;
}

// Auto-expire stale requests
$db->prepare("UPDATE access_requests SET status = 'EXPIRED' WHERE status = 'PENDING' AND expires_at IS NOT NULL AND expires_at < NOW()")->execute();

// Fetch pending requests
$stmt = $db->prepare("
    SELECT ar.*, d.full_name AS doctor_name, d.specialization, d.medical_registration_id,
           h.name AS hospital_name, h.type AS hospital_type, h.city AS hospital_city
    FROM access_requests ar
    JOIN doctors d ON ar.doctor_id = d.id
    JOIN hospitals h ON ar.hospital_id = h.id
    WHERE ar.patient_id = ? AND ar.status = 'PENDING' AND (ar.expires_at IS NULL OR ar.expires_at > NOW())
    ORDER BY ar.requested_at DESC
");
$stmt->execute([$patientId]);
$pending = $stmt->fetchAll();

// Active sessions
$stmt = $db->prepare("
    SELECT acs.*, d.full_name AS doctor_name, d.specialization,
           h.name AS hospital_name
    FROM access_sessions acs
    JOIN doctors d ON acs.doctor_id = d.id
    JOIN hospitals h ON acs.hospital_id = h.id
    WHERE acs.patient_id = ? AND acs.status = 'ACTIVE' AND acs.expires_at > NOW()
    ORDER BY acs.expires_at ASC
");
$stmt->execute([$patientId]);
$activeSessions = $stmt->fetchAll();
foreach ($activeSessions as &$acs) {
    $acs['secs_remaining'] = max(0, strtotime($acs['expires_at']) - time());
}
unset($acs);

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

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · Consent';
$portalTitle = 'Consent & access';
$portalSubtitle = 'ডাক্তার কে রেকর্ড দেখতে পারবেন, আপনিই নিয়ন্ত্রণ করেন';
$portalNav = mc_patient_nav('consent');
$portalTopAction = count($pending) > 0
    ? '<span class="mc-btn mc-btn-soft mc-btn-sm">' . count($pending) . ' waiting</span>'
    : '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/patient/access-history.php">Full history</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flash): ?>
  <div class="mc-alert <?= ($flash['type'] ?? '') === 'success' ? 'mc-alert-ok' : (($flash['type'] ?? '') === 'info' ? 'mc-alert-info' : 'mc-alert-danger') ?>">
    <?= htmlspecialchars($flash['msg'] ?? '') ?>
  </div>
<?php endif; ?>

<?php if (!empty($activeSessions)): ?>
<div class="mc-panel">
  <div class="mc-panel-h"><h2>Active access</h2></div>
  <div class="mc-list">
    <?php foreach ($activeSessions as $s): ?>
      <?php $mins = max(0, (int)ceil($s['secs_remaining'] / 60)); ?>
      <div class="mc-list-item">
        <div>
          <strong>Dr. <?= htmlspecialchars($s['doctor_name']) ?></strong>
          <div style="font-size:.86rem;color:var(--muted)">
            <?= htmlspecialchars($s['specialization'] ?? '') ?> · <?= htmlspecialchars($s['hospital_name']) ?>
            · <?= $mins ?> min left
          </div>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="revoke">
          <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
          <button type="submit" class="mc-btn mc-btn-ghost mc-btn-sm" onclick="return confirm('Revoke access for Dr. <?= htmlspecialchars(addslashes($s['doctor_name'])) ?>?')">Revoke</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Pending requests</h2>
    <?php if (count($pending) > 0): ?>
      <span class="mc-btn mc-btn-soft mc-btn-sm"><?= count($pending) ?></span>
    <?php endif; ?>
  </div>
  <?php if (empty($pending)): ?>
    <p style="margin:0;color:var(--muted)">কোনো pending request নেই — আপনিই নিয়ন্ত্রণে।</p>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($pending as $req): ?>
        <div class="mc-list-item" style="align-items:flex-start;flex-wrap:wrap;gap:.75rem">
          <div style="flex:1;min-width:220px">
            <strong>Dr. <?= htmlspecialchars($req['doctor_name']) ?></strong>
            <div style="font-size:.86rem;color:var(--muted);margin-top:.2rem">
              <?= htmlspecialchars($req['specialization'] ?? 'Physician') ?>
              · <?= htmlspecialchars($req['medical_registration_id']) ?>
              · <?= htmlspecialchars($req['hospital_name']) ?>
            </div>
            <?php if (!empty($req['doctor_notes'])): ?>
              <p style="margin:.45rem 0 0;font-size:.88rem"><?= htmlspecialchars($req['doctor_notes']) ?></p>
            <?php endif; ?>
            <div style="font-size:.78rem;color:var(--muted);margin-top:.35rem">
              <?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?>
            </div>
          </div>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm">Approve 30m</button>
            </form>
            <form method="POST" onsubmit="return confirm('Decline Dr. <?= htmlspecialchars(addslashes($req['doctor_name'])) ?>?');">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
              <input type="hidden" name="action" value="deny">
              <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm">Decline</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Access history</h2>
    <a href="<?= APP_URL ?>/patient/access-history.php" style="font-weight:800;color:var(--accent);font-size:.88rem">Full history</a>
  </div>
  <?php if (empty($history)): ?>
    <p style="margin:0;color:var(--muted)">No past access requests.</p>
  <?php else: ?>
    <div style="overflow:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Doctor</th>
            <th>Hospital</th>
            <th>Requested</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr>
              <td><strong><?= htmlspecialchars($h['doctor_name']) ?></strong></td>
              <td><?= htmlspecialchars($h['hospital_name']) ?></td>
              <td><?= date('d M Y, h:i A', strtotime($h['requested_at'])) ?></td>
              <td><span class="lab-status"><?= htmlspecialchars($h['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
