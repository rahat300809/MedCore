<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$doctorId = (int)($_GET['id'] ?? 0);

if ($doctorId <= 0) {
    header('Location: ' . APP_URL . '/admin/doctors.php');
    exit;
}

$flashSuccess = '';
$flashError   = '';

// Handle status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

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
        ]);
        $flashSuccess = "Doctor credentials officially VERIFIED and APPROVED globally.";
    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? 'Credentials failed verification');
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
        $flashSuccess = "Doctor status changed to REJECTED.";
    } elseif ($action === 'suspend') {
        $db->prepare("
            UPDATE doctors d
            JOIN users u ON d.user_id = u.id
            SET d.verification_status = 'SUSPENDED',
                u.status = 'SUSPENDED'
            WHERE d.id = ?
        ")->execute([$doctorId]);
        $flashSuccess = "Doctor account SUSPENDED.";
    } elseif ($action === 'reactivate') {
        $db->prepare("
            UPDATE doctors d
            JOIN users u ON d.user_id = u.id
            SET d.verification_status = 'VERIFIED',
                u.status = 'ACTIVE'
            WHERE d.id = ?
        ")->execute([$doctorId]);
        $flashSuccess = "Doctor account REACTIVATED and VERIFIED.";
    }
}

// Fetch doctor details
$stmt = $db->prepare("
    SELECT d.*, u.email, u.phone, u.status AS user_status, u.created_at AS user_created_at,
           u.last_login_at, v.email AS verified_by_email
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN users v ON d.verified_by = v.id
    WHERE d.id = ?
");
$stmt->execute([$doctorId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header('Location: ' . APP_URL . '/admin/doctors.php');
    exit;
}

// Fetch all hospital affiliations for this doctor (Multi-hospital relationship)
$stmt = $db->prepare("
    SELECT dh.*, h.name AS hospital_name, h.hospital_uid, h.type AS hospital_type,
           h.city AS hospital_city, h.district AS hospital_district,
           dept.name AS department_name,
           u_appr.email AS approved_by_email
    FROM doctor_hospitals dh
    JOIN hospitals h ON dh.hospital_id = h.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    LEFT JOIN users u_appr ON dh.approved_by = u_appr.id
    WHERE dh.doctor_id = ?
    ORDER BY FIELD(dh.status, 'APPROVED', 'PENDING', 'SUSPENDED', 'REJECTED', 'REMOVED'), dh.joined_at DESC
");
$stmt->execute([$doctorId]);
$affiliations = $stmt->fetchAll();

// Fetch consultations count & recent activity for this doctor
$stmt = $db->prepare("
    SELECT c.*, p.full_name AS patient_name, p.patient_uid, h.name AS hospital_name
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN hospitals h ON c.hospital_id = h.id
    WHERE c.doctor_id = ?
    ORDER BY c.consultation_date DESC
    LIMIT 5
");
$stmt->execute([$doctorId]);
$recentConsultations = $stmt->fetchAll();

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Doctor Credential Dossier';
$portalSubtitle = $doctor['full_name'] . ' · ' . ($doctor['medical_registration_id'] ?? '');
$portalNav = mc_admin_nav('doctors');
$topActions = '<a href="' . APP_URL . '/admin/doctors.php" class="mc-btn mc-btn-secondary mc-btn-sm">← Back to Doctors</a>';
if ($doctor['verification_status'] === 'PENDING') {
    $topActions .= '<form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="' . $csrf . '"><input type="hidden" name="action" value="approve"><button type="submit" class="mc-btn mc-btn-primary mc-btn-sm" style="background:#027a48;"><i class="bi bi-patch-check-fill"></i> Approve &amp; Verify</button></form>';
}
$portalTopAction = $topActions;
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<style>
  .dossier-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; align-items: start; }
  @media (min-width: 960px) { .dossier-grid { grid-template-columns: 320px 1fr; } }
  .info-row { display: flex; justify-content: space-between; gap: .75rem; padding: .55rem 0; border-bottom: 1px solid var(--line); font-size: .86rem; }
  .info-row:last-child { border-bottom: 0; }
  .info-label { color: var(--muted); font-weight: 600; }
  .info-val { font-weight: 700; color: var(--ink); text-align: right; }
</style>

<?php if ($flashSuccess): ?>
  <div class="mc-alert mc-alert-ok" data-auto-dismiss="6000">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?>
  </div>
<?php endif; ?>

<div class="dossier-grid">
  <!-- LEFT COLUMN: PROFILE CARD & ACTIONS -->
  <div style="display:grid;gap:1rem;">
    <section class="mc-panel" style="text-align:center;">
      <div style="width:68px;height:68px;border-radius:50%;background:#dbeafe;color:#1e40af;display:grid;place-items:center;font-size:1.5rem;font-weight:800;margin:0 auto .85rem;">
        <?= strtoupper(substr($doctor['full_name'], 4, 1) ?: substr($doctor['full_name'], 0, 1)) ?>
      </div>
      <h2 style="font-size:1.15rem;margin:0 0 .2rem;"><?= htmlspecialchars($doctor['full_name']) ?></h2>
      <div style="font-size:.86rem;color:var(--muted);margin-bottom:.75rem;"><?= htmlspecialchars($doctor['specialization'] ?? 'General Physician') ?></div>

      <div style="margin-bottom:.9rem;">
        <?php if ($doctor['verification_status'] === 'VERIFIED'): ?>
          <span class="lab-status ok"><i class="bi bi-patch-check-fill"></i> VERIFIED BY ADMIN</span>
        <?php elseif ($doctor['verification_status'] === 'PENDING'): ?>
          <span class="lab-status warn"><i class="bi bi-hourglass-split"></i> AWAITING APPROVAL</span>
        <?php elseif ($doctor['verification_status'] === 'REJECTED'): ?>
          <span class="lab-status danger"><i class="bi bi-x-circle-fill"></i> REJECTED</span>
        <?php else: ?>
          <span class="lab-status"><i class="bi bi-pause-circle-fill"></i> SUSPENDED</span>
        <?php endif; ?>
      </div>

      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:.75rem;text-align:left;margin-bottom:.9rem;">
        <div style="font-size:.7rem;text-transform:uppercase;font-weight:800;color:#1e40af;margin-bottom:.2rem;">Medical Board Registration</div>
        <div class="mc-mono" style="font-size:.96rem;font-weight:800;color:#1e3a8a;"><?= htmlspecialchars($doctor['medical_registration_id']) ?></div>
        <div class="mc-mono" style="font-size:.75rem;color:#3b82f6;margin-top:.15rem;">Doctor UID: <?= htmlspecialchars($doctor['doctor_uid']) ?></div>
      </div>

      <div style="display:grid;gap:.5rem;">
        <?php if ($doctor['verification_status'] === 'PENDING'): ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="mc-btn mc-btn-primary mc-btn-block" style="background:#027a48;"><i class="bi bi-check-circle"></i> Approve Credentials</button>
          </form>
          <button type="button" class="mc-btn mc-btn-danger mc-btn-sm mc-btn-block" onclick="document.getElementById('reject-form-box').style.display='block';">
            <i class="bi bi-x-circle"></i> Reject Application
          </button>
        <?php elseif ($doctor['verification_status'] === 'VERIFIED'): ?>
          <form method="POST" onsubmit="return confirm('Suspend this doctor?');">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="suspend">
            <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm mc-btn-block"><i class="bi bi-pause-circle"></i> Suspend Doctor</button>
          </form>
        <?php else: ?>
          <form method="POST" onsubmit="return confirm('Reactivate & Verify this doctor?');">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="reactivate">
            <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm mc-btn-block" style="background:#027a48;"><i class="bi bi-arrow-repeat"></i> Reactivate &amp; Verify</button>
          </form>
        <?php endif; ?>
      </div>

      <div id="reject-form-box" style="display:none;margin-top:.85rem;padding:.75rem;background:var(--danger-soft);border:1px solid #fecdca;border-radius:10px;text-align:left;">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="reject">
          <label class="mc-field" style="margin-top:0;">
            <span style="color:var(--danger);">Reason for rejection:</span>
            <textarea name="rejection_reason" rows="2" required placeholder="BMDC license invalid or unconfirmed..."></textarea>
          </label>
          <div style="display:flex;gap:.4rem;margin-top:.6rem;">
            <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm" style="flex:1;">Confirm Reject</button>
            <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="document.getElementById('reject-form-box').style.display='none';">Cancel</button>
          </div>
        </form>
      </div>
    </section>

    <section class="mc-panel">
      <div class="mc-panel-h"><h2>Profile Attributes</h2></div>
      <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?= htmlspecialchars($doctor['email']) ?></span></div>
      <div class="info-row"><span class="info-label">Phone</span><span class="info-val"><?= htmlspecialchars($doctor['phone'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Experience</span><span class="info-val"><?= (int)$doctor['experience_years'] ?> Years</span></div>
      <div class="info-row"><span class="info-label">Designation</span><span class="info-val"><?= htmlspecialchars($doctor['designation'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Qualifications</span><span class="info-val"><?= htmlspecialchars($doctor['qualification'] ?? '-') ?></span></div>
      <div class="info-row"><span class="info-label">Registered Date</span><span class="info-val"><?= date('M d, Y', strtotime($doctor['user_created_at'])) ?></span></div>
      <?php if ($doctor['verified_at']): ?>
        <div class="info-row"><span class="info-label">Verified At</span><span class="info-val" style="color:#027a48;"><?= date('M d, Y H:i', strtotime($doctor['verified_at'])) ?></span></div>
      <?php endif; ?>
    </section>
  </div>

  <!-- RIGHT COLUMN: AFFILIATIONS & CONSULTATIONS -->
  <div style="display:grid;gap:1rem;">
    <section class="mc-panel">
      <div class="mc-panel-h">
        <div>
          <h2><i class="bi bi-hospital" style="color:#1e40af;"></i> Multi-Hospital Affiliation Roster</h2>
          <div style="font-size:.82rem;color:var(--muted);">Hospitals where <?= htmlspecialchars($doctor['full_name']) ?> is actively practicing or requested</div>
        </div>
        <span class="lab-status info"><?= count($affiliations) ?> Hospital Links</span>
      </div>

      <div style="overflow-x:auto;">
        <table class="lab-table">
          <thead>
            <tr>
              <th>Hospital</th>
              <th>Department</th>
              <th>Designation</th>
              <th>Employment</th>
              <th>Status</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($affiliations)): ?>
              <tr>
                <td colspan="6" style="text-align:center;padding:2rem;color:var(--muted);">
                  No hospital affiliations recorded yet. Once verified, any participating hospital can add this doctor.
                </td>
              </tr>
            <?php endif; ?>
            <?php foreach ($affiliations as $aff): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($aff['hospital_name']) ?></strong>
                  <div style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($aff['hospital_uid']) ?> · <?= htmlspecialchars($aff['hospital_city'] ?? '') ?></div>
                </td>
                <td><?= htmlspecialchars($aff['department_name'] ?? 'General') ?></td>
                <td><?= htmlspecialchars($aff['designation'] ?? 'Consultant') ?></td>
                <td><span class="lab-status"><?= htmlspecialchars($aff['employment_type']) ?></span></td>
                <td>
                  <?php if ($aff['status'] === 'APPROVED'): ?>
                    <span class="lab-status ok"><i class="bi bi-check-circle-fill"></i> ACTIVE</span>
                  <?php elseif ($aff['status'] === 'PENDING'): ?>
                    <span class="lab-status warn"><i class="bi bi-clock-fill"></i> PENDING</span>
                  <?php elseif ($aff['status'] === 'REMOVED'): ?>
                    <span class="lab-status"><i class="bi bi-dash-circle"></i> REMOVED</span>
                  <?php else: ?>
                    <span class="lab-status danger"><?= htmlspecialchars($aff['status']) ?></span>
                  <?php endif; ?>
                </td>
                <td style="font-size:.78rem;color:var(--muted);"><?= $aff['joined_at'] ? date('M d, Y', strtotime($aff['joined_at'])) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mc-alert mc-alert-info" style="margin-top:.9rem;margin-bottom:0;font-size:.82rem;">
        <i class="bi bi-info-circle-fill"></i>
        <strong>Hospital Autonomy:</strong> Each hospital administrator independently manages adding, assigning departments, or removing doctors from their hospital without revoking the doctor's nationwide license.
      </div>
    </section>

    <section class="mc-panel">
      <div class="mc-panel-h">
        <div>
          <h2><i class="bi bi-clipboard2-pulse" style="color:var(--accent);"></i> Recent Consultations Across Hospitals</h2>
          <div style="font-size:.82rem;color:var(--muted);">Clinical sessions logged by this doctor</div>
        </div>
      </div>

      <div style="overflow-x:auto;">
        <table class="lab-table">
          <thead>
            <tr>
              <th>Consultation UID</th>
              <th>Hospital</th>
              <th>Patient</th>
              <th>Diagnosis Summary</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentConsultations)): ?>
              <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--muted);">No consultations recorded yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentConsultations as $c): ?>
              <tr>
                <td class="mc-mono" style="font-size:.76rem;"><?= htmlspecialchars($c['consultation_uid']) ?></td>
                <td><span class="lab-status info"><?= htmlspecialchars($c['hospital_name']) ?></span></td>
                <td>
                  <strong><?= htmlspecialchars($c['patient_name']) ?></strong>
                  <div style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($c['patient_uid']) ?></div>
                </td>
                <td><?= htmlspecialchars($c['diagnosis_summary'] ?? '-') ?></td>
                <td style="font-size:.78rem;color:var(--muted);"><?= date('M d, Y', strtotime($c['consultation_date'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
