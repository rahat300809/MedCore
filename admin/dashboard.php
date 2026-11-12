<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();

// Quick approval action from dashboard
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action'])) {
    validateCsrf();
    $action = $_POST['quick_action'];
    $docId  = (int)($_POST['doctor_id'] ?? 0);

    if ($docId > 0) {
        if ($action === 'approve_doctor') {
            $stmt = $db->prepare("
                UPDATE doctors d
                JOIN users u ON d.user_id = u.id
                SET d.verification_status = 'VERIFIED',
                    d.verified_by = ?,
                    d.verified_at = NOW(),
                    u.status = 'ACTIVE'
                WHERE d.id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $docId]);

            AuditService::log(AuditService::DOCTOR_VERIFIED, [
                'user_id'   => $_SESSION['user_id'],
                'doctor_id' => $docId,
                'metadata'  => ['source' => 'admin_dashboard_quick_action', 'status' => 'VERIFIED']
            ]);

            $flashSuccess = "Doctor ID #{$docId} has been successfully verified & approved globally.";
        } elseif ($action === 'reject_doctor') {
            $reason = trim($_POST['rejection_reason'] ?? 'Credentials could not be verified by Central Board');
            $stmt = $db->prepare("
                UPDATE doctors d
                SET d.verification_status = 'REJECTED',
                    d.verified_by = ?,
                    d.verified_at = NOW()
                WHERE d.id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $docId]);

            AuditService::log('DOCTOR_REJECTED', [
                'user_id'   => $_SESSION['user_id'],
                'doctor_id' => $docId,
                'metadata'  => ['reason' => $reason]
            ]);

            $flashSuccess = "Doctor ID #{$docId} has been marked as REJECTED.";
        }
    }
}

// Fetch comprehensive ecosystem stats
$stats = [];
$stats['total_doctors']      = (int)$db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$stats['pending_doctors']    = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'PENDING'")->fetchColumn();
$stats['verified_doctors']   = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'VERIFIED'")->fetchColumn();

$stats['total_hospitals']    = (int)$db->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
$stats['verified_hospitals'] = (int)$db->query("SELECT COUNT(*) FROM hospitals WHERE verification_status = 'VERIFIED'")->fetchColumn();

$stats['total_affiliations'] = (int)$db->query("SELECT COUNT(*) FROM doctor_hospitals WHERE status = 'APPROVED'")->fetchColumn();
$stats['pending_affiliations']=(int)$db->query("SELECT COUNT(*) FROM doctor_hospitals WHERE status = 'PENDING'")->fetchColumn();

$stats['total_patients']     = (int)$db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$stats['total_consultations']= (int)$db->query("SELECT COUNT(*) FROM consultations")->fetchColumn();
$stats['total_prescriptions']= (int)$db->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();

$stats['total_labs']         = (int)$db->query("SELECT COUNT(*) FROM laboratories")->fetchColumn();
$stats['pending_labs']       = (int)$db->query("SELECT COUNT(*) FROM laboratories WHERE verification_status = 'PENDING'")->fetchColumn();
$stats['verified_labs']      = (int)$db->query("SELECT COUNT(*) FROM laboratories WHERE verification_status = 'VERIFIED'")->fetchColumn();
$stats['total_lab_orders']   = (int)$db->query("SELECT COUNT(*) FROM prescription_lab_orders")->fetchColumn();

// Fetch Pending Doctors Queue (Top priority for Admin)
$stmt = $db->prepare("
    SELECT d.*, u.email, u.phone, u.created_at AS registered_at
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.verification_status = 'PENDING'
    ORDER BY d.created_at ASC
    LIMIT 6
");
$stmt->execute();
$pendingDoctors = $stmt->fetchAll();

// Fetch Recently Approved Doctors & their Affiliations count
$stmt = $db->prepare("
    SELECT d.*, u.email,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.doctor_id = d.id AND dh.status = 'APPROVED') AS hospital_count
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.verification_status = 'VERIFIED'
    ORDER BY d.verified_at DESC, d.updated_at DESC
    LIMIT 5
");
$stmt->execute();
$recentApprovedDoctors = $stmt->fetchAll();

// Fetch Hospital Network Overview
$stmt = $db->prepare("
    SELECT h.*,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id = h.id AND dh.status = 'APPROVED') AS doctor_count,
           (SELECT COUNT(*) FROM departments dep WHERE dep.hospital_id = h.id) AS dept_count
    FROM hospitals h
    ORDER BY doctor_count DESC, h.name ASC
    LIMIT 5
");
$stmt->execute();
$topHospitals = $stmt->fetchAll();

// Fetch Recent Audit Activity Stream
$stmt = $db->prepare("
    SELECT al.*, u.email AS actor_email, u.role AS actor_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 8
");
$stmt->execute();
$recentLogs = $stmt->fetchAll();

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Platform Overview & Verification Hub';
$portalSubtitle = 'Central governance, BMDC credential review, hospital network & audit trail';
$portalNav = mc_admin_nav('dashboard', (int)($stats['pending_doctors'] ?? 0));
$portalTopAction = '<a href="' . APP_URL . '/admin/doctor-create.php" class="mc-btn mc-btn-primary mc-btn-sm"><i class="bi bi-plus-circle"></i> Register Doctor</a> '
    . '<a href="' . APP_URL . '/admin/doctors.php?tab=pending" class="mc-btn mc-btn-secondary mc-btn-sm"><i class="bi bi-patch-check"></i> Pending (' . (int)$stats['pending_doctors'] . ')</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flashSuccess): ?>
  <div class="mc-alert mc-alert-ok" data-auto-dismiss="6000">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?>
  </div>
<?php endif; ?>

<?php if ($stats['pending_doctors'] > 0): ?>
  <div class="mc-alert mc-alert-warn" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;align-items:center;gap:12px;">
      <i class="bi bi-exclamation-octagon-fill" style="font-size:1.4rem;"></i>
      <div>
        <strong style="display:block;font-size:.98rem;"><?= $stats['pending_doctors'] ?> Doctor<?= $stats['pending_doctors'] > 1 ? 's' : '' ?> Awaiting BMDC Credential Approval</strong>
        <span style="font-size:.84rem;">Doctors cannot be affiliated with any hospital until verified by the Central Admin board.</span>
      </div>
    </div>
    <a href="<?= APP_URL ?>/admin/doctors.php?tab=pending" class="mc-btn mc-btn-primary mc-btn-sm">
      Review Verification Queue →
    </a>
  </div>
<?php endif; ?>

<section class="mc-stats" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));">
  <div class="mc-stat">
    <div class="l">Total Doctors</div>
    <div class="v"><?= $stats['total_doctors'] ?></div>
    <div style="font-size:.78rem;color:var(--muted);margin-top:.25rem;">
      <span style="color:#027a48;font-weight:700;">✓ <?= $stats['verified_doctors'] ?> Verified</span> ·
      <span style="color:#b54708;font-weight:700;">⏳ <?= $stats['pending_doctors'] ?> Pending</span>
    </div>
  </div>
  <div class="mc-stat">
    <div class="l">Hospitals in Network</div>
    <div class="v"><?= $stats['total_hospitals'] ?></div>
    <div style="font-size:.78rem;color:#027a48;font-weight:600;margin-top:.25rem;">Accredited across districts</div>
  </div>
  <div class="mc-stat">
    <div class="l">Hospital Affiliations</div>
    <div class="v"><?= $stats['total_affiliations'] ?></div>
    <div style="font-size:.78rem;color:var(--muted);margin-top:.25rem;">Active Doctor-Hospital links</div>
  </div>
  <div class="mc-stat">
    <div class="l">Total Consultations</div>
    <div class="v"><?= $stats['total_consultations'] ?></div>
    <div style="font-size:.78rem;color:var(--muted);margin-top:.25rem;">Across all network hospitals</div>
  </div>
  <a href="<?= APP_URL ?>/admin/laboratories.php" class="mc-stat" style="display:block;">
    <div class="l">Diagnostic Labs</div>
    <div class="v"><?= $stats['total_labs'] ?></div>
    <div style="font-size:.78rem;color:var(--muted);margin-top:.25rem;">
      <span style="color:#027a48;font-weight:700;">✓ <?= $stats['verified_labs'] ?> Verified</span> ·
      <span style="color:#b54708;font-weight:700;">⏳ <?= $stats['pending_labs'] ?> Pending</span>
    </div>
  </a>
</section>

<div class="mc-grid-2" style="margin-bottom:1rem;align-items:start;">
  <!-- DOCTOR VERIFICATION QUEUE -->
  <section class="mc-panel">
    <div class="mc-panel-h">
      <div>
        <h2><i class="bi bi-patch-question-fill" style="color:#d97706;"></i> Doctor Verification Queue</h2>
        <div style="font-size:.82rem;color:var(--muted);">Review and approve BMDC license credentials</div>
      </div>
      <a href="<?= APP_URL ?>/admin/doctors.php" class="mc-btn mc-btn-secondary mc-btn-sm">View All</a>
    </div>

    <?php if (empty($pendingDoctors)): ?>
      <div class="mc-empty">
        <i class="bi bi-check-circle-fill" style="color:#027a48;"></i>
        <strong>All Doctor Verifications Up to Date!</strong>
        <span>No pending applications currently in queue.</span>
      </div>
    <?php else: ?>
      <div class="mc-list">
        <?php foreach ($pendingDoctors as $doc): ?>
          <div class="mc-list-item" style="background:#fffaeb;border-color:#fedf89;">
            <div style="min-width:0;flex:1;">
              <strong><?= htmlspecialchars($doc['full_name']) ?></strong>
              <span>
                <span class="mc-mono" style="color:#1e40af;"><?= htmlspecialchars($doc['medical_registration_id']) ?></span>
                · <?= htmlspecialchars($doc['specialization'] ?? 'General') ?> · <?= (int)$doc['experience_years'] ?> yrs exp
              </span>
              <span style="font-size:.76rem;color:var(--muted);">Applied: <?= date('M d, Y h:i A', strtotime($doc['registered_at'])) ?></span>
            </div>
            <div style="display:flex;gap:.4rem;flex-shrink:0;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                <input type="hidden" name="quick_action" value="approve_doctor">
                <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm" style="background:#027a48;">
                  <i class="bi bi-check-lg"></i> Approve
                </button>
              </form>
              <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $doc['id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Inspect</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="margin-top:1.15rem;padding-top:1rem;border-top:1px solid var(--line);">
      <div style="font-size:.82rem;font-weight:700;color:var(--ink-2);margin-bottom:.6rem;">Recently Verified &amp; Multi-Hospital Practice</div>
      <div class="mc-list">
        <?php foreach ($recentApprovedDoctors as $rd): ?>
          <div class="mc-list-item">
            <div style="min-width:0;">
              <strong><?= htmlspecialchars($rd['full_name']) ?></strong>
              <span class="mc-mono" style="font-size:.78rem;"><?= htmlspecialchars($rd['medical_registration_id']) ?> · <?= htmlspecialchars($rd['specialization']) ?></span>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <span class="lab-status ok"><i class="bi bi-patch-check-fill"></i> Verified</span>
              <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem;">Practicing at <strong><?= $rd['hospital_count'] ?></strong> hospital<?= $rd['hospital_count'] != 1 ? 's' : '' ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- HOSPITAL ECOSYSTEM -->
  <section class="mc-panel">
    <div class="mc-panel-h">
      <div>
        <h2><i class="bi bi-hospital" style="color:#1e40af;"></i> Hospital Ecosystem</h2>
        <div style="font-size:.82rem;color:var(--muted);">Participating medical centers</div>
      </div>
      <a href="<?= APP_URL ?>/admin/hospitals.php" class="mc-btn mc-btn-secondary mc-btn-sm">Manage</a>
    </div>

    <div class="mc-list">
      <?php foreach ($topHospitals as $hosp): ?>
        <div class="mc-list-item" style="display:block;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
            <div>
              <strong><?= htmlspecialchars($hosp['name']) ?></strong>
              <span><?= htmlspecialchars($hosp['city'] ?? 'Dhaka') ?> · <?= ucfirst(strtolower($hosp['type'])) ?></span>
            </div>
            <span class="lab-status ok">Accredited</span>
          </div>
          <div style="display:flex;gap:1rem;font-size:.8rem;color:var(--ink-2);margin-top:.55rem;padding-top:.5rem;border-top:1px dashed var(--line);">
            <span><i class="bi bi-people-fill"></i> <strong><?= $hosp['doctor_count'] ?></strong> Affiliated Doctors</span>
            <span><i class="bi bi-grid-fill"></i> <strong><?= $hosp['dept_count'] ?></strong> Departments</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mc-alert mc-alert-ok" style="margin-top:1rem;margin-bottom:0;font-size:.84rem;">
      <i class="bi bi-info-circle-fill"></i>
      <strong>Hospitalization Flow:</strong> Once you verify a doctor, all these hospitals can independently add, schedule, or remove the doctor as needed.
    </div>
  </section>
</div>

<!-- AUDIT STREAM -->
<section class="mc-panel">
  <div class="mc-panel-h">
    <div>
      <h2><i class="bi bi-shield-shaded"></i> Central Audit &amp; Security Trail</h2>
      <div style="font-size:.82rem;color:var(--muted);">Live stream of verifications, doctor affiliations, and clinical record access</div>
    </div>
    <a href="<?= APP_URL ?>/admin/audit-logs.php" class="mc-btn mc-btn-secondary mc-btn-sm">Full Log Stream</a>
  </div>

  <div style="overflow-x:auto;">
    <table class="lab-table">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Action</th>
          <th>Actor</th>
          <th>Details / Target</th>
          <th>Severity</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentLogs as $log): ?>
          <tr>
            <td class="mc-mono" style="white-space:nowrap;font-size:.78rem;color:var(--muted);"><?= date('M d H:i:s', strtotime($log['created_at'])) ?></td>
            <td><span class="lab-status info mc-mono"><?= htmlspecialchars($log['action']) ?></span></td>
            <td>
              <div style="font-weight:700;"><?= htmlspecialchars($log['actor_email'] ?? 'System') ?></div>
              <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($log['actor_role'] ?? 'SYSTEM') ?></div>
            </td>
            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($log['metadata'] ?? '-') ?></td>
            <td>
              <?php if ($log['severity'] === 'CRITICAL'): ?>
                <span class="lab-status danger">CRITICAL</span>
              <?php elseif ($log['severity'] === 'WARNING'): ?>
                <span class="lab-status warn">WARNING</span>
              <?php else: ?>
                <span class="lab-status">INFO</span>
              <?php endif; ?>
            </td>
            <td class="mc-mono" style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($log['ip_address']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
