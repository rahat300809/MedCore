<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$hospId = (int)($_GET['id'] ?? 0);

if ($hospId <= 0) {
    header('Location: ' . APP_URL . '/admin/hospitals.php');
    exit;
}

// Fetch hospital
$stmt = $db->prepare("
    SELECT h.*, u.email AS admin_email, u.phone AS admin_phone
    FROM hospitals h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.id = ?
");
$stmt->execute([$hospId]);
$hospital = $stmt->fetch();

if (!$hospital) {
    header('Location: ' . APP_URL . '/admin/hospitals.php');
    exit;
}

// Fetch departments
$stmt = $db->prepare("SELECT * FROM departments WHERE hospital_id = ? ORDER BY name ASC");
$stmt->execute([$hospId]);
$departments = $stmt->fetchAll();

// Fetch affiliated doctors for this hospital
$stmt = $db->prepare("
    SELECT dh.*, d.full_name, d.doctor_uid, d.medical_registration_id, d.specialization,
           d.qualification, d.verification_status AS doctor_global_status,
           u.email AS doctor_email, u.phone AS doctor_phone,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.hospital_id = ?
    ORDER BY FIELD(dh.status, 'APPROVED', 'PENDING', 'SUSPENDED', 'REJECTED', 'REMOVED'), d.full_name ASC
");
$stmt->execute([$hospId]);
$doctors = $stmt->fetchAll();

$csrf = generateCsrfToken();
?>
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = $hospital['name'];
$portalSubtitle = 'Hospital profile, departments & affiliated medical staff roster';
$portalNav = mc_admin_nav('hospitals');
$portalTopAction = '<a href="' . APP_URL . '/admin/hospitals.php" class="mc-btn mc-btn-secondary mc-btn-sm">← Back to Hospitals</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<section class="mc-panel" style="margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
    <div>
      <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.35rem;">
        <h2 style="font-size:1.25rem;margin:0;"><?= htmlspecialchars($hospital['name']) ?></h2>
        <span class="lab-status <?= $hospital['verification_status'] === 'VERIFIED' ? 'ok' : ($hospital['verification_status'] === 'PENDING' ? 'warn' : 'danger') ?>">
          <i class="bi bi-patch-check-fill"></i> <?= htmlspecialchars($hospital['verification_status']) ?>
        </span>
      </div>
      <div style="font-size:.88rem;color:var(--ink-2);margin-bottom:.25rem;">
        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($hospital['address'] ?? '') ?>, <?= htmlspecialchars($hospital['city'] ?? '') ?>
      </div>
      <div class="mc-mono" style="font-size:.78rem;color:var(--muted);">
        UID: <?= htmlspecialchars($hospital['hospital_uid']) ?> · Reg: <?= htmlspecialchars($hospital['registration_number']) ?> · Type: <?= htmlspecialchars($hospital['type']) ?>
      </div>
    </div>

    <div class="mc-stats" style="margin-bottom:0;grid-template-columns:repeat(2,minmax(120px,1fr));">
      <div class="mc-stat" style="text-align:center;">
        <div class="l">Affiliated Doctors</div>
        <div class="v" style="color:#1e40af;"><?= count($doctors) ?></div>
      </div>
      <div class="mc-stat" style="text-align:center;">
        <div class="l">Departments</div>
        <div class="v" style="color:var(--accent);"><?= count($departments) ?></div>
      </div>
    </div>
  </div>
</section>

<section class="mc-panel">
  <div class="mc-panel-h">
    <div>
      <h2><i class="bi bi-people-fill" style="color:#1e40af;"></i> Affiliated Medical Staff Roster</h2>
      <div style="font-size:.82rem;color:var(--muted);">Doctors practicing at <?= htmlspecialchars($hospital['name']) ?></div>
    </div>
  </div>

  <div style="overflow-x:auto;">
    <table class="lab-table">
      <thead>
        <tr>
          <th>Doctor</th>
          <th>BMDC Reg ID</th>
          <th>Department</th>
          <th>Designation</th>
          <th>Employment</th>
          <th>Status</th>
          <th>Joined</th>
          <th style="text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($doctors)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:2rem;color:var(--muted);">No doctors currently affiliated with this hospital.</td>
          </tr>
        <?php endif; ?>

        <?php foreach ($doctors as $d): ?>
          <tr>
            <td>
              <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $d['doctor_id'] ?>" style="font-weight:700;color:var(--ink);">
                <?= htmlspecialchars($d['full_name']) ?>
              </a>
              <div style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($d['doctor_uid']) ?> · <?= htmlspecialchars($d['specialization'] ?? '') ?></div>
            </td>
            <td class="mc-mono" style="color:#1e40af;"><?= htmlspecialchars($d['medical_registration_id']) ?></td>
            <td><span class="lab-status info"><?= htmlspecialchars($d['department_name'] ?? 'General') ?></span></td>
            <td><?= htmlspecialchars($d['designation'] ?? 'Consultant') ?></td>
            <td><span class="lab-status"><?= htmlspecialchars($d['employment_type']) ?></span></td>
            <td>
              <?php if ($d['status'] === 'APPROVED'): ?>
                <span class="lab-status ok"><i class="bi bi-check-circle-fill"></i> ACTIVE</span>
              <?php elseif ($d['status'] === 'PENDING'): ?>
                <span class="lab-status warn">PENDING</span>
              <?php elseif ($d['status'] === 'REMOVED'): ?>
                <span class="lab-status">REMOVED</span>
              <?php else: ?>
                <span class="lab-status danger"><?= htmlspecialchars($d['status']) ?></span>
              <?php endif; ?>
            </td>
            <td style="font-size:.78rem;color:var(--muted);"><?= $d['joined_at'] ? date('M d, Y', strtotime($d['joined_at'])) : '-' ?></td>
            <td style="text-align:right;">
              <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $d['doctor_id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Doctor Dossier →</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
