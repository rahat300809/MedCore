<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();

$flashSuccess = '';
$flashError   = '';

// Handle status change actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action   = $_POST['action'] ?? '';
    $doctorId = (int)($_POST['doctor_id'] ?? 0);

    if ($doctorId > 0) {
        $stmtDoc = $db->prepare("SELECT d.*, u.email FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
        $stmtDoc->execute([$doctorId]);
        $targetDoc = $stmtDoc->fetch();

        if (!$targetDoc) {
            $flashError = "Doctor record not found.";
        } else {
            if ($action === 'approve') {
                $db->prepare("
                    UPDATE doctors d
                    JOIN users u ON d.user_id = u.id
                    SET d.verification_status = 'VERIFIED',
                        d.verified_by = ?,
                        d.verified_at = NOW(),
                        u.status = 'ACTIVE'
                    WHERE d.id = ?
                ")->execute([$_SESSION['user_id'], $doctorId]);

                AuditService::log(AuditService::DOCTOR_VERIFIED, [
                    'user_id'   => $_SESSION['user_id'],
                    'doctor_id' => $doctorId,
                    'metadata'  => [
                        'doctor_name' => $targetDoc['full_name'],
                        'bmdc_reg'    => $targetDoc['medical_registration_id'],
                        'new_status'  => 'VERIFIED'
                    ]
                ]);

                $flashSuccess = "Dr. " . htmlspecialchars($targetDoc['full_name']) . " ({$targetDoc['medical_registration_id']}) has been officially APPROVED & VERIFIED. Hospitals can now affiliate this doctor to their practice rosters.";
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? 'Medical credentials could not be verified with the regulatory board.');
                $db->prepare("
                    UPDATE doctors d
                    SET d.verification_status = 'REJECTED',
                        d.verified_by = ?,
                        d.verified_at = NOW()
                    WHERE d.id = ?
                ")->execute([$_SESSION['user_id'], $doctorId]);

                AuditService::log('DOCTOR_REJECTED', [
                    'user_id'   => $_SESSION['user_id'],
                    'doctor_id' => $doctorId,
                    'metadata'  => ['reason' => $reason]
                ]);

                $flashSuccess = "Dr. " . htmlspecialchars($targetDoc['full_name']) . " has been REJECTED.";
            } elseif ($action === 'suspend') {
                $db->prepare("
                    UPDATE doctors d
                    JOIN users u ON d.user_id = u.id
                    SET d.verification_status = 'SUSPENDED',
                        u.status = 'SUSPENDED'
                    WHERE d.id = ?
                ")->execute([$doctorId]);

                AuditService::log('DOCTOR_SUSPENDED', [
                    'user_id'   => $_SESSION['user_id'],
                    'doctor_id' => $doctorId,
                ]);

                $flashSuccess = "Dr. " . htmlspecialchars($targetDoc['full_name']) . " has been temporarily SUSPENDED from the network.";
            } elseif ($action === 'reactivate') {
                $db->prepare("
                    UPDATE doctors d
                    JOIN users u ON d.user_id = u.id
                    SET d.verification_status = 'VERIFIED',
                        u.status = 'ACTIVE'
                    WHERE d.id = ?
                ")->execute([$doctorId]);

                AuditService::log('DOCTOR_REACTIVATED', [
                    'user_id'   => $_SESSION['user_id'],
                    'doctor_id' => $doctorId,
                ]);

                $flashSuccess = "Dr. " . htmlspecialchars($targetDoc['full_name']) . " has been REACTIVATED and VERIFIED.";
            }
        }
    }
}

// Filter tab
$currentTab = $_GET['tab'] ?? 'all';
$search     = trim($_GET['q'] ?? '');

// Counts
$countAll       = (int)$db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$countPending   = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'PENDING'")->fetchColumn();
$countVerified  = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'VERIFIED'")->fetchColumn();
$countRejected  = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'REJECTED'")->fetchColumn();
$countSuspended = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'SUSPENDED'")->fetchColumn();

// Build query
$whereClauses = [];
$params       = [];

if ($currentTab === 'pending') {
    $whereClauses[] = "d.verification_status = 'PENDING'";
} elseif ($currentTab === 'verified') {
    $whereClauses[] = "d.verification_status = 'VERIFIED'";
} elseif ($currentTab === 'rejected') {
    $whereClauses[] = "d.verification_status = 'REJECTED'";
} elseif ($currentTab === 'suspended') {
    $whereClauses[] = "d.verification_status = 'SUSPENDED'";
}

if ($search !== '') {
    $whereClauses[] = "(d.full_name LIKE ? OR d.medical_registration_id LIKE ? OR d.doctor_uid LIKE ? OR u.email LIKE ? OR d.specialization LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $db->prepare("
    SELECT d.*, u.email, u.phone, u.status AS user_status, u.created_at AS registered_at,
           v_user.email AS verified_by_email,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.doctor_id = d.id AND dh.status = 'APPROVED') AS active_hospital_count,
           (SELECT GROUP_CONCAT(h.name SEPARATOR ', ') FROM doctor_hospitals dh JOIN hospitals h ON dh.hospital_id = h.id WHERE dh.doctor_id = d.id AND dh.status = 'APPROVED') AS affiliated_hospitals_list
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN users v_user ON d.verified_by = v_user.id
    {$whereSql}
    ORDER BY FIELD(d.verification_status, 'PENDING', 'VERIFIED', 'SUSPENDED', 'REJECTED'), d.created_at DESC
");
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Doctor Credential Review & Registry';
$portalSubtitle = 'BMDC verification authority and nationwide licensing registry';
$portalNav = mc_admin_nav('doctors', (int)$countPending);
$portalTopAction = '<a href="' . APP_URL . '/admin/doctor-create.php" class="mc-btn mc-btn-primary mc-btn-sm"><i class="bi bi-person-plus-fill"></i> Add &amp; Verify Doctor</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flashSuccess): ?>
  <div class="mc-alert mc-alert-ok" data-auto-dismiss="7000">
    <i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?>
  </div>
<?php endif; ?>

<?php if ($flashError): ?>
  <div class="mc-alert mc-alert-danger" data-auto-dismiss="5000">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?>
  </div>
<?php endif; ?>

<section class="mc-panel" style="margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:.75rem;">
    <div class="mc-tabs" style="margin-bottom:0;">
      <a href="<?= APP_URL ?>/admin/doctors.php?tab=all<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $currentTab === 'all' ? 'active' : '' ?>">
        All <span class="mc-tab-count"><?= $countAll ?></span>
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php?tab=pending<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $currentTab === 'pending' ? 'active' : '' ?>">
        Pending <span class="mc-tab-count"><?= $countPending ?></span>
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php?tab=verified<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $currentTab === 'verified' ? 'active' : '' ?>">
        Verified <span class="mc-tab-count"><?= $countVerified ?></span>
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php?tab=rejected<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $currentTab === 'rejected' ? 'active' : '' ?>">
        Rejected <span class="mc-tab-count"><?= $countRejected ?></span>
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php?tab=suspended<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $currentTab === 'suspended' ? 'active' : '' ?>">
        Suspended <span class="mc-tab-count"><?= $countSuspended ?></span>
      </a>
    </div>

    <form method="GET" class="lab-token-form" style="flex:1;max-width:380px;">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($currentTab) ?>">
      <input type="text" name="q" placeholder="Search BMDC Reg, Name, UID..." value="<?= htmlspecialchars($search) ?>">
      <?php if ($search): ?>
        <a href="<?= APP_URL ?>/admin/doctors.php?tab=<?= htmlspecialchars($currentTab) ?>" class="mc-btn mc-btn-secondary mc-btn-sm" title="Clear search"><i class="bi bi-x-lg"></i></a>
      <?php endif; ?>
      <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm">Search</button>
    </form>
  </div>
  <div style="font-size:.82rem;color:var(--muted);">
    <i class="bi bi-info-circle"></i>
    <strong>Governance Rule:</strong> Approving a doctor here marks them <code>VERIFIED</code> nationwide so hospitals can affiliate them independently.
  </div>
</section>

<section class="mc-panel">
  <div style="overflow-x:auto;">
    <table class="lab-table">
      <thead>
        <tr>
          <th>Doctor</th>
          <th>BMDC / Reg ID</th>
          <th>Specialization</th>
          <th>Experience</th>
          <th>Affiliations</th>
          <th>Status</th>
          <th>Dates</th>
          <th style="text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($doctors)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:2.5rem 1rem;color:var(--muted);">
              <i class="bi bi-person-x" style="font-size:1.8rem;display:block;margin-bottom:.5rem;"></i>
              <strong>No doctors found</strong><br>
              <?= $search ? 'No results matching "' . htmlspecialchars($search) . '".' : 'There are no doctors in this category.' ?>
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($doctors as $doc): ?>
          <tr>
            <td>
              <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $doc['id'] ?>" style="font-weight:700;color:var(--ink);">
                <?= htmlspecialchars($doc['full_name']) ?>
              </a>
              <div class="mc-mono" style="font-size:.75rem;color:var(--muted);">
                <?= htmlspecialchars($doc['doctor_uid']) ?> · <?= htmlspecialchars($doc['email']) ?>
              </div>
            </td>
            <td class="mc-mono" style="color:#1e40af;"><?= htmlspecialchars($doc['medical_registration_id']) ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($doc['specialization'] ?? 'General Practitioner') ?></div>
              <div style="font-size:.76rem;color:var(--muted);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars($doc['qualification'] ?? '-') ?>
              </div>
            </td>
            <td><?= (int)$doc['experience_years'] ?> yrs</td>
            <td>
              <?php if ($doc['active_hospital_count'] > 0): ?>
                <span class="lab-status info"><i class="bi bi-hospital"></i> <?= $doc['active_hospital_count'] ?> Hospital<?= $doc['active_hospital_count'] > 1 ? 's' : '' ?></span>
                <div style="font-size:.75rem;color:var(--muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:.2rem;" title="<?= htmlspecialchars($doc['affiliated_hospitals_list'] ?? '') ?>">
                  <?= htmlspecialchars($doc['affiliated_hospitals_list'] ?? '') ?>
                </div>
              <?php else: ?>
                <span style="font-size:.8rem;color:var(--muted);">None yet</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($doc['verification_status'] === 'VERIFIED'): ?>
                <span class="lab-status ok"><i class="bi bi-patch-check-fill"></i> VERIFIED</span>
              <?php elseif ($doc['verification_status'] === 'PENDING'): ?>
                <span class="lab-status warn"><i class="bi bi-hourglass-split"></i> PENDING</span>
              <?php elseif ($doc['verification_status'] === 'REJECTED'): ?>
                <span class="lab-status danger"><i class="bi bi-x-circle-fill"></i> REJECTED</span>
              <?php else: ?>
                <span class="lab-status"><i class="bi bi-pause-circle-fill"></i> SUSPENDED</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.75rem;color:var(--muted);">
              <div>Reg: <?= date('M d, Y', strtotime($doc['registered_at'])) ?></div>
              <?php if ($doc['verified_at']): ?>
                <div style="color:#027a48;">Ver: <?= date('M d, Y', strtotime($doc['verified_at'])) ?></div>
              <?php endif; ?>
            </td>
            <td style="text-align:right;white-space:nowrap;">
              <div style="display:inline-flex;gap:.35rem;align-items:center;">
                <?php if ($doc['verification_status'] === 'PENDING'): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Approve Dr. <?= addslashes($doc['full_name']) ?> globally? Hospitals will be able to affiliate this doctor.');">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm" style="background:#027a48;"><i class="bi bi-check-lg"></i> Approve</button>
                  </form>
                  <button type="button" class="mc-btn mc-btn-danger mc-btn-sm" onclick="openRejectModal(<?= $doc['id'] ?>, '<?= addslashes($doc['full_name']) ?>')">
                    <i class="bi bi-x-lg"></i> Reject
                  </button>
                <?php elseif ($doc['verification_status'] === 'VERIFIED'): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend Dr. <?= addslashes($doc['full_name']) ?> from practicing across all hospitals?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                    <input type="hidden" name="action" value="suspend">
                    <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm" style="color:var(--danger);"><i class="bi bi-pause-circle"></i> Suspend</button>
                  </form>
                <?php elseif ($doc['verification_status'] === 'SUSPENDED' || $doc['verification_status'] === 'REJECTED'): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Reactivate & Verify Dr. <?= addslashes($doc['full_name']) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                    <input type="hidden" name="action" value="reactivate">
                    <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm" style="background:#027a48;"><i class="bi bi-arrow-repeat"></i> Reactivate</button>
                  </form>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $doc['id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm" title="View Dossier"><i class="bi bi-eye"></i></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- REJECTION REASON MODAL -->
<div id="reject-modal" class="mc-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
  <div class="mc-modal">
    <div class="mc-modal-head">
      <h3 id="rejectModalTitle" style="color:var(--danger);"><i class="bi bi-x-circle-fill"></i> Reject Doctor Application</h3>
      <button type="button" class="mc-modal-close" onclick="closeRejectModal()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <p style="font-size:.88rem;color:var(--muted);margin:.5rem 0 1rem;">
      Provide a formal regulatory reason for rejecting <strong id="modal-doctor-name"></strong>.
    </p>
    <form method="POST" id="modal-reject-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="doctor_id" id="modal-doctor-id">
      <input type="hidden" name="action" value="reject">

      <label class="mc-field" style="margin-top:0;">
        <span>Rejection Reason</span>
        <textarea name="rejection_reason" id="rejection_reason" rows="3" required
          placeholder="e.g. BMDC Registration number could not be authenticated against the national medical database."></textarea>
      </label>

      <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem;">
        <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="closeRejectModal()">Cancel</button>
        <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm">Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(docId, docName) {
  document.getElementById('modal-doctor-id').value = docId;
  document.getElementById('modal-doctor-name').textContent = docName;
  document.getElementById('reject-modal').classList.add('open');
}
function closeRejectModal() {
  document.getElementById('reject-modal').classList.remove('open');
}
document.getElementById('reject-modal')?.addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
