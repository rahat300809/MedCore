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
    SELECT s.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM access_sessions s
    JOIN doctors d ON s.doctor_id = d.id
    JOIN hospitals h ON s.hospital_id = h.id
    WHERE s.patient_id = ? AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ORDER BY s.expires_at DESC
");
$stmtActive->execute([$patientId]);
$activeSessions = $stmtActive->fetchAll();
foreach ($activeSessions as &$s) {
    $s['seconds_left'] = max(0, strtotime($s['expires_at']) - time());
}
unset($s);

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

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · Privacy log';
$portalTitle = 'Access history';
$portalSubtitle = 'কে কখন রেকর্ড দেখেছে — পূর্ণ অডিট';
$portalNav = mc_patient_nav('consent');
$portalTopAction = '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/patient/consent-requests.php">Consent inbox</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flash): ?>
  <div class="mc-alert <?= ($flash['type'] ?? '') === 'success' ? 'mc-alert-ok' : 'mc-alert-info' ?>">
    <?= htmlspecialchars($flash['msg'] ?? '') ?>
  </div>
<?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Active sessions</h2>
    <span class="mc-btn mc-btn-soft mc-btn-sm"><?= count($activeSessions) ?> live</span>
  </div>
  <?php if (empty($activeSessions)): ?>
    <p style="margin:0;color:var(--muted)">কোনো ডাক্তার এখন অ্যাক্টিভ সেশনে নেই।</p>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($activeSessions as $s): ?>
        <div class="mc-list-item">
          <div>
            <strong>Dr. <?= htmlspecialchars($s['doctor_name']) ?></strong>
            <div style="font-size:.86rem;color:var(--muted)">
              <?= htmlspecialchars($s['specialization'] ?? '') ?> · <?= htmlspecialchars($s['hospital_name']) ?>
              · <?= (int)ceil($s['seconds_left'] / 60) ?> min left
            </div>
          </div>
          <form method="POST" onsubmit="return confirm('Revoke this doctor access now?');">
            <?= csrfField() ?>
            <input type="hidden" name="revoke_session" value="1">
            <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
            <button type="submit" class="mc-btn mc-btn-ghost mc-btn-sm">Revoke</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Past sessions</h2></div>
  <?php if (empty($pastSessions)): ?>
    <p style="margin:0;color:var(--muted)">No past sessions.</p>
  <?php else: ?>
    <div style="overflow:auto">
      <table class="lab-table">
        <thead>
          <tr><th>Doctor</th><th>Hospital</th><th>Window</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($pastSessions as $s): ?>
            <tr>
              <td>Dr. <?= htmlspecialchars($s['doctor_name']) ?></td>
              <td><?= htmlspecialchars($s['hospital_name']) ?></td>
              <td><?= date('d M Y, h:i A', strtotime($s['started_at'])) ?> → <?= date('h:i A', strtotime($s['expires_at'])) ?></td>
              <td><span class="lab-status"><?= $s['status'] === 'REVOKED' ? 'Revoked' : 'Expired' ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Consent audit</h2></div>
  <?php if (empty($allRequests)): ?>
    <p style="margin:0;color:var(--muted)">No consent requests recorded.</p>
  <?php else: ?>
    <div style="overflow:auto">
      <table class="lab-table">
        <thead>
          <tr><th>Date</th><th>Doctor</th><th>Hospital</th><th>Reason</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($allRequests as $req): ?>
            <tr>
              <td><?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?></td>
              <td>Dr. <?= htmlspecialchars($req['doctor_name']) ?></td>
              <td><?= htmlspecialchars($req['hospital_name']) ?></td>
              <td><?= htmlspecialchars($req['patient_message'] ?? $req['doctor_notes'] ?? 'Clinical evaluation') ?></td>
              <td><span class="lab-status"><?= htmlspecialchars($req['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
