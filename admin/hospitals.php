<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$flashSuccess = '';
$flashError   = '';

// Handle hospital status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action   = $_POST['action'] ?? '';
    $hospId   = (int)($_POST['hospital_id'] ?? 0);

    if ($hospId > 0) {
        // Fetch hospital + admin user to give good feedback
        $stmtH = $db->prepare("SELECT h.*, u.id AS uid FROM hospitals h LEFT JOIN users u ON h.user_id = u.id WHERE h.id = ?");
        $stmtH->execute([$hospId]);
        $targetHosp = $stmtH->fetch();

        if (!$targetHosp) {
            $flashError = "Hospital record not found.";
        } else {
            if ($action === 'approve') {
                // Approve hospital: set VERIFIED + activate admin user
                $db->prepare("
                    UPDATE hospitals
                    SET verification_status = 'VERIFIED',
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $hospId]);

                if ($targetHosp['uid']) {
                    $db->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = ?")->execute([$targetHosp['uid']]);
                }

                AuditService::log(AuditService::HOSPITAL_VERIFIED, [
                    'user_id'     => $_SESSION['user_id'],
                    'hospital_id' => $hospId,
                    'metadata'    => ['action' => 'admin_approved', 'hospital_name' => $targetHosp['name']]
                ]);

                $flashSuccess = "✅ " . htmlspecialchars($targetHosp['name']) . " has been APPROVED & ACCREDITED. The hospital admin can now log in.";

            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? 'Registration could not be verified with health authority records.');
                $db->prepare("
                    UPDATE hospitals
                    SET verification_status = 'REJECTED',
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $hospId]);

                if ($targetHosp['uid']) {
                    $db->prepare("UPDATE users SET status = 'SUSPENDED' WHERE id = ?")->execute([$targetHosp['uid']]);
                }

                AuditService::log('HOSPITAL_REJECTED', [
                    'user_id'     => $_SESSION['user_id'],
                    'hospital_id' => $hospId,
                    'metadata'    => ['reason' => $reason, 'hospital_name' => $targetHosp['name']]
                ]);

                $flashSuccess = "Hospital application for \"" . htmlspecialchars($targetHosp['name']) . "\" has been REJECTED.";

            } elseif ($action === 'verify') {
                $db->prepare("
                    UPDATE hospitals
                    SET verification_status = 'VERIFIED',
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $hospId]);

                if ($targetHosp['uid']) {
                    $db->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = ?")->execute([$targetHosp['uid']]);
                }

                AuditService::log(AuditService::HOSPITAL_VERIFIED, [
                    'user_id'     => $_SESSION['user_id'],
                    'hospital_id' => $hospId,
                ]);

                $flashSuccess = "Hospital has been VERIFIED & Accredited.";

            } elseif ($action === 'suspend') {
                $db->prepare("
                    UPDATE hospitals
                    SET verification_status = 'SUSPENDED'
                    WHERE id = ?
                ")->execute([$hospId]);

                $flashSuccess = "Hospital license SUSPENDED.";
            }
        }
    }
}

// Fetch PENDING hospital applications
$pendingHospitals = $db->query("
    SELECT h.*, u.email AS admin_email, u.phone AS admin_phone, u.created_at AS applied_at
    FROM hospitals h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.verification_status = 'PENDING'
    ORDER BY h.created_at DESC
")->fetchAll();

// Fetch all non-pending hospitals with aggregate stats
$stmt = $db->query("
    SELECT h.*, u.email AS admin_email,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id = h.id AND dh.status = 'APPROVED') AS active_doctors_count,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id = h.id AND dh.status = 'PENDING') AS pending_doctors_count,
           (SELECT COUNT(*) FROM departments dep WHERE dep.hospital_id = h.id) AS dept_count,
           (SELECT COUNT(*) FROM consultations c WHERE c.hospital_id = h.id) AS total_consultations
    FROM hospitals h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.verification_status != 'PENDING'
    ORDER BY FIELD(h.verification_status, 'VERIFIED', 'SUSPENDED', 'REJECTED'), h.name ASC
");
$hospitals = $stmt->fetchAll();

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Hospital Network Oversight';
$portalSubtitle = 'Accreditation applications, institutional verification & roster monitoring';
$portalNav = mc_admin_nav('hospitals', 0, count($pendingHospitals));
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flashSuccess): ?>
  <div class="mc-alert mc-alert-ok" data-auto-dismiss="5000">
    <i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?>
  </div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="mc-alert mc-alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?>
  </div>
<?php endif; ?>

<?php if (count($pendingHospitals) > 0): ?>
  <section class="mc-panel" style="border-color:#fedf89;background:#fffcf5;margin-bottom:1.25rem;">
    <div class="mc-panel-h">
      <div>
        <h2 style="color:#b54708;"><i class="bi bi-hourglass-split"></i> Pending Hospital Applications</h2>
        <div style="font-size:.82rem;color:#b54708;">
          <?= count($pendingHospitals) ?> hospital<?= count($pendingHospitals) > 1 ? 's' : '' ?> awaiting review &amp; accreditation approval
        </div>
      </div>
      <span class="lab-status warn"><?= count($pendingHospitals) ?> Pending</span>
    </div>

    <div class="mc-list">
      <?php foreach ($pendingHospitals as $ph): ?>
        <div class="mc-panel" style="background:#fff;border-color:#fedf89;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;flex-wrap:wrap;margin-bottom:.75rem;">
            <div>
              <strong style="font-size:1rem;"><?= htmlspecialchars($ph['name']) ?></strong>
              <div style="font-size:.82rem;color:var(--muted);margin-top:.15rem;">
                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ph['city'] ?? '') ?><?= $ph['district'] ? ' · ' . htmlspecialchars($ph['district']) : '' ?>
                · <strong><?= htmlspecialchars(str_replace('_', ' ', $ph['type'])) ?></strong>
              </div>
              <div class="mc-mono" style="font-size:.76rem;color:var(--muted);margin-top:.15rem;">
                Applied: <?= date('M j, Y g:i A', strtotime($ph['created_at'])) ?> · UID: <?= htmlspecialchars($ph['hospital_uid']) ?>
              </div>
            </div>
            <span class="lab-status warn">AWAITING REVIEW</span>
          </div>

          <div class="mc-form-grid-3" style="margin-bottom:.85rem;font-size:.84rem;">
            <div><span style="color:var(--muted);display:block;font-size:.74rem;font-weight:700;">REG. NUMBER</span><strong class="mc-mono"><?= htmlspecialchars($ph['registration_number']) ?></strong></div>
            <div><span style="color:var(--muted);display:block;font-size:.74rem;font-weight:700;">ADMIN EMAIL</span><strong><?= htmlspecialchars($ph['admin_email'] ?? '-') ?></strong></div>
            <div><span style="color:var(--muted);display:block;font-size:.74rem;font-weight:700;">PHONE</span><strong><?= htmlspecialchars($ph['phone'] ?? '-') ?></strong></div>
            <?php if ($ph['website']): ?>
              <div><span style="color:var(--muted);display:block;font-size:.74rem;font-weight:700;">WEBSITE</span><a href="<?= htmlspecialchars($ph['website']) ?>" target="_blank" rel="noopener noreferrer" style="color:#1e40af;font-weight:600;"><?= htmlspecialchars($ph['website']) ?></a></div>
            <?php endif; ?>
            <?php if ($ph['address']): ?>
              <div><span style="color:var(--muted);display:block;font-size:.74rem;font-weight:700;">ADDRESS</span><span><?= htmlspecialchars($ph['address']) ?></span></div>
            <?php endif; ?>
          </div>

          <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            <form method="POST" style="display:inline;" onsubmit="return confirm('APPROVE and ACCREDIT \"<?= htmlspecialchars(addslashes($ph['name'])) ?>\"?\n\nThis will activate their admin account and allow them to manage doctors.');">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="hospital_id" value="<?= $ph['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm" style="background:#027a48;"><i class="bi bi-patch-check-fill"></i> Approve &amp; Accredit</button>
            </form>
            <button type="button" class="mc-btn mc-btn-danger mc-btn-sm" onclick="openRejectModal(<?= $ph['id'] ?>, '<?= htmlspecialchars(addslashes($ph['name'])) ?>')">
              <i class="bi bi-x-circle-fill"></i> Reject
            </button>
            <a href="<?= APP_URL ?>/admin/hospital-detail.php?id=<?= $ph['id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">
              <i class="bi bi-eye"></i> Full Details
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php else: ?>
  <div class="mc-alert mc-alert-ok">
    <i class="bi bi-check-circle-fill"></i>
    <strong>No pending applications.</strong> All hospital applications have been reviewed.
  </div>
<?php endif; ?>

<section class="mc-panel">
  <div class="mc-panel-h">
    <div>
      <h2>Ecosystem Hospital Nodes</h2>
      <div style="font-size:.82rem;color:var(--muted);">Verified doctors can practice across any of these authorized hospital centers</div>
    </div>
    <div style="display:flex;gap:.4rem;align-items:center;">
      <span class="lab-status ok"><?= count(array_filter($hospitals, fn($h) => $h['verification_status'] === 'VERIFIED')) ?> Accredited</span>
      <span class="lab-status info"><?= count($hospitals) ?> Total</span>
    </div>
  </div>

  <div style="overflow-x:auto;">
    <table class="lab-table">
      <thead>
        <tr>
          <th>Hospital Center</th>
          <th>Registration No.</th>
          <th>Type &amp; Location</th>
          <th>Affiliated Doctors</th>
          <th>Depts</th>
          <th>Consultations</th>
          <th>Status</th>
          <th style="text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($hospitals)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:2rem;color:var(--muted);">No approved or suspended hospitals yet.</td>
          </tr>
        <?php endif; ?>
        <?php foreach ($hospitals as $hosp): ?>
          <tr>
            <td>
              <a href="<?= APP_URL ?>/admin/hospital-detail.php?id=<?= $hosp['id'] ?>" style="font-weight:700;color:var(--ink);">
                <?= htmlspecialchars($hosp['name']) ?>
              </a>
              <div class="mc-mono" style="font-size:.75rem;color:var(--muted);">
                <?= htmlspecialchars($hosp['hospital_uid']) ?> · <?= htmlspecialchars($hosp['admin_email'] ?? '') ?>
              </div>
            </td>
            <td class="mc-mono" style="font-size:.8rem;"><?= htmlspecialchars($hosp['registration_number']) ?></td>
            <td>
              <div><?= ucwords(strtolower(str_replace('_', ' ', $hosp['type']))) ?></div>
              <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($hosp['city'] ?? '') ?><?= $hosp['district'] ? ', ' . htmlspecialchars($hosp['district']) : '' ?></div>
            </td>
            <td>
              <strong style="color:#1e40af;"><?= $hosp['active_doctors_count'] ?> Active</strong>
              <?php if ($hosp['pending_doctors_count'] > 0): ?>
                <span style="font-size:.76rem;color:#b54708;">(+<?= $hosp['pending_doctors_count'] ?> req)</span>
              <?php endif; ?>
            </td>
            <td><?= $hosp['dept_count'] ?> depts</td>
            <td><?= $hosp['total_consultations'] ?></td>
            <td>
              <?php if ($hosp['verification_status'] === 'VERIFIED'): ?>
                <span class="lab-status ok"><i class="bi bi-patch-check-fill"></i> ACCREDITED</span>
              <?php elseif ($hosp['verification_status'] === 'REJECTED'): ?>
                <span class="lab-status danger"><i class="bi bi-x-circle-fill"></i> REJECTED</span>
              <?php else: ?>
                <span class="lab-status">SUSPENDED</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;white-space:nowrap;">
              <div style="display:inline-flex;gap:.35rem;">
                <a href="<?= APP_URL ?>/admin/hospital-detail.php?id=<?= $hosp['id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Roster →</a>
                <?php if ($hosp['verification_status'] === 'VERIFIED'): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('SUSPEND this hospital? Their admin cannot log in while suspended.');">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="hospital_id" value="<?= $hosp['id'] ?>">
                    <input type="hidden" name="action" value="suspend">
                    <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm">Suspend</button>
                  </form>
                <?php elseif (in_array($hosp['verification_status'], ['SUSPENDED','REJECTED'])): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Re-APPROVE and ACCREDIT this hospital?');">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="hospital_id" value="<?= $hosp['id'] ?>">
                    <input type="hidden" name="action" value="verify">
                    <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm" style="background:#027a48;">Re-Approve</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- REJECT MODAL -->
<div class="mc-modal-backdrop" id="rejectModal" role="dialog" aria-modal="true">
  <div class="mc-modal">
    <div class="mc-modal-head">
      <h3 style="color:var(--danger);">Reject Hospital Application</h3>
      <button type="button" class="mc-modal-close" onclick="closeRejectModal()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <p id="rejectModalDesc" style="font-size:.88rem;color:var(--muted);margin:.5rem 0 1rem;"></p>

    <form method="POST" id="rejectForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="hospital_id" id="rejectHospId" value="">
      <input type="hidden" name="action" value="reject">

      <label class="mc-field" style="margin-top:0;">
        <span>Rejection Reason *</span>
        <textarea id="rejection_reason" name="rejection_reason" rows="3"
          placeholder="e.g., Registration number could not be verified with DGHS records." required></textarea>
      </label>
      <div style="font-size:.76rem;color:var(--muted);margin-top:.35rem;">This reason will be logged in the audit trail.</div>

      <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem;">
        <button type="button" onclick="closeRejectModal()" class="mc-btn mc-btn-secondary mc-btn-sm">Cancel</button>
        <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm"><i class="bi bi-x-circle-fill"></i> Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(hospId, hospName) {
  document.getElementById('rejectHospId').value = hospId;
  document.getElementById('rejectModalDesc').textContent = 'Provide a reason for rejecting the application from "' + hospName + '".';
  document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() {
  document.getElementById('rejectModal').classList.remove('open');
}
document.getElementById('rejectModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[data-auto-dismiss]').forEach(function(el) {
    const ms = parseInt(el.dataset.autoDismiss) || 5000;
    setTimeout(() => el.remove(), ms);
  });
});
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
