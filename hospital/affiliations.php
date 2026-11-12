<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$success = '';
$error   = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $dhId   = (int)($_POST['dh_id'] ?? 0);

    if (in_array($action, ['approve', 'reject']) && $dhId) {
        $newStatus = $action === 'approve' ? 'APPROVED' : 'REJECTED';
        $reason = trim($_POST['rejection_reason'] ?? '');

        $stmt = $db->prepare("
            UPDATE doctor_hospitals
            SET status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ?
            WHERE id = ? AND hospital_id = ?
        ");
        $stmt->execute([$newStatus, $_SESSION['user_id'], $reason ?: null, $dhId, $hospitalId]);

        AuditService::log($action === 'approve' ? AuditService::AFFILIATION_APPROVED : AuditService::AFFILIATION_REJECTED, [
            'user_id'    => $_SESSION['user_id'],
            'hospital_id'=> $hospitalId,
            'target_id'  => $dhId,
        ]);

        $success = "Affiliation request has been " . strtolower($newStatus) . " successfully.";
    }
}

// Fetch pending requests
$stmtPending = $db->prepare("
    SELECT dh.*, d.full_name, d.specialization, d.qualification, d.medical_registration_id,
           u.email, u.phone, dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.hospital_id = ? AND dh.status = 'PENDING'
    ORDER BY dh.created_at ASC
");
$stmtPending->execute([$hospitalId]);
$pending = $stmtPending->fetchAll();

// Fetch processed history
$stmtHistory = $db->prepare("
    SELECT dh.*, d.full_name, d.specialization, d.medical_registration_id,
           u.email, dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.hospital_id = ? AND dh.status != 'PENDING'
    ORDER BY dh.updated_at DESC
    LIMIT 20
");
$stmtHistory->execute([$hospitalId]);
$history = $stmtHistory->fetchAll();

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalAccent = '#0b4f6c';
$portalUser = $hospital['name'] ?? 'Hospital';
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Affiliations';
$portalTitle = 'Doctor affiliation approvals';
$portalSubtitle = 'ডাক্তার যোগদানের আবেদন ও সিদ্ধান্তের ইতিহাস';
$portalNav = mc_hospital_nav('affiliations', count($pending));
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($success): ?>
  <div class="mc-alert mc-alert-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mc-alert mc-alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Awaiting hospital authorization (<?= count($pending) ?>)</h2>
    <?php if (count($pending) > 0): ?><span class="lab-status warn"><?= count($pending) ?> pending</span><?php endif; ?>
  </div>
  <?php if (empty($pending)): ?>
    <p style="margin:0;color:var(--muted)">No pending doctor affiliation requests. All caught up.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Doctor</th>
            <th>BMDC Reg</th>
            <th>Specialization</th>
            <th>Requested dept</th>
            <th>Designation</th>
            <th>Date</th>
            <th>Decision</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $p): ?>
            <tr>
              <td>
                <strong>Dr. <?= htmlspecialchars($p['full_name']) ?></strong>
                <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($p['email']) ?></div>
              </td>
              <td class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($p['medical_registration_id']) ?></td>
              <td><?= htmlspecialchars($p['specialization'] ?? 'General') ?></td>
              <td><?= htmlspecialchars($p['department_name'] ?? 'General') ?></td>
              <td><?= htmlspecialchars($p['designation'] ?? 'Consultant') ?></td>
              <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                  <form method="POST" style="display:inline;margin:0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="dh_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm">Approve</button>
                  </form>
                  <form method="POST" style="display:inline;margin:0">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="dh_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm" onclick="return confirm('Reject this doctor affiliation?');">Reject</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Affiliation decision history</h2></div>
  <?php if (empty($history)): ?>
    <p style="margin:0;color:var(--muted)">No processed affiliation history found.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Doctor</th>
            <th>BMDC Reg</th>
            <th>Department</th>
            <th>Status</th>
            <th>Updated at</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h):
            $sCls = $h['status'] === 'APPROVED' ? 'ok' : ($h['status'] === 'REJECTED' ? 'danger' : '');
          ?>
            <tr>
              <td><strong>Dr. <?= htmlspecialchars($h['full_name']) ?></strong></td>
              <td class="mc-mono"><?= htmlspecialchars($h['medical_registration_id']) ?></td>
              <td><?= htmlspecialchars($h['department_name'] ?? 'General') ?></td>
              <td><span class="lab-status <?= $sCls ?>"><?= htmlspecialchars($h['status']) ?></span></td>
              <td style="font-size:.84rem;color:var(--muted)"><?= date('d M Y, h:i A', strtotime($h['updated_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
