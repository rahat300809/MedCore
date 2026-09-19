<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

// Hospital profile
$stmt = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmt->execute([$hospitalId]);
$hospital = $stmt->fetch();

// Stats
$stmt = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM doctor_hospitals WHERE hospital_id = :h1 AND status = 'APPROVED') AS affiliated_doctors,
        (SELECT COUNT(*) FROM doctor_hospitals WHERE hospital_id = :h2 AND status = 'PENDING') AS pending_affiliations,
        (SELECT COUNT(*) FROM departments WHERE hospital_id = :h3 AND status = 'ACTIVE') AS active_departments,
        (SELECT COUNT(*) FROM consultations WHERE hospital_id = :h4 AND DATE(consultation_date) = CURDATE()) AS today_consultations,
        (SELECT COUNT(*) FROM consultations WHERE hospital_id = :h5) AS total_consultations,
        (SELECT COUNT(*) FROM access_sessions WHERE hospital_id = :h6 AND status = 'ACTIVE' AND expires_at > NOW()) AS active_sessions
");
$stmt->execute([':h1'=>$hospitalId,':h2'=>$hospitalId,':h3'=>$hospitalId,':h4'=>$hospitalId,':h5'=>$hospitalId,':h6'=>$hospitalId]);
$stats = $stmt->fetch();

// Affiliated doctors
$stmt = $db->prepare("
    SELECT d.*, u.email, u.last_login_at, dh.designation, dh.employment_type, dh.status AS affil_status,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON u.id = d.user_id
    LEFT JOIN departments dept ON dept.id = dh.department_id
    WHERE dh.hospital_id = ? AND dh.status = 'APPROVED'
    ORDER BY d.full_name
    LIMIT 10
");
$stmt->execute([$hospitalId]);
$doctors = $stmt->fetchAll();

// Pending affiliation requests
$stmt = $db->prepare("
    SELECT dh.*, d.full_name, d.specialization, d.medical_registration_id, u.email
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON u.id = d.user_id
    WHERE dh.hospital_id = ? AND dh.status = 'PENDING'
    ORDER BY dh.joined_at ASC
");
$stmt->execute([$hospitalId]);
$pendingAffiliations = $stmt->fetchAll();

// Recent consultations
$stmt = $db->prepare("
    SELECT c.*, d.full_name AS doctor_name, d.specialization,
           p.full_name AS patient_name, p.patient_uid
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN patients p ON c.patient_id = p.id
    WHERE c.hospital_id = ?
    ORDER BY c.consultation_date DESC
    LIMIT 5
");
$stmt->execute([$hospitalId]);
$recentConsultations = $stmt->fetchAll();

// Departments
$stmt = $db->prepare("
    SELECT dep.*, COUNT(dh.id) AS doctor_count
    FROM departments dep
    LEFT JOIN doctor_hospitals dh ON dh.department_id = dep.id AND dh.status = 'APPROVED'
    WHERE dep.hospital_id = ?
    GROUP BY dep.id
    ORDER BY dep.name
");
$stmt->execute([$hospitalId]);
$departments = $stmt->fetchAll();

// Handle affiliation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $dhId   = (int)($_POST['dh_id'] ?? 0);

    if (in_array($action, ['approve', 'reject']) && $dhId) {
        $newStatus = $action === 'approve' ? 'APPROVED' : 'REJECTED';
        $stmt = $db->prepare("
            UPDATE doctor_hospitals SET status = ? WHERE id = ? AND hospital_id = ?
        ");
        $stmt->execute([$newStatus, $dhId, $hospitalId]);
        header('Location: ' . APP_URL . '/hospital/dashboard.php#affiliations');
        exit;
    }
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($hospital['name']) ?> — Hospital Dashboard — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body>
<div class="portal-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;background:linear-gradient(135deg,var(--mc-teal),var(--mc-teal-dark));">H</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-tagline" style="color:var(--mc-teal);">Hospital Portal</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/hospital/dashboard.php" class="active" aria-current="page">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
      </a>

      <div class="sidebar-section-title">Management</div>
      <a href="<?= APP_URL ?>/hospital/doctors.php">
        <i class="bi bi-person-badge-fill"></i> Affiliated Doctors
        <?php if ($stats['pending_affiliations'] > 0): ?>
        <span class="nav-badge"><?= $stats['pending_affiliations'] ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/hospital/departments.php">
        <i class="bi bi-diagram-3-fill"></i> Departments
      </a>

      <div class="sidebar-section-title">Clinical</div>
      <a href="<?= APP_URL ?>/hospital/consultations.php">
        <i class="bi bi-stethoscope"></i> Consultations
      </a>
      <a href="<?= APP_URL ?>/hospital/diagnostics.php">
        <i class="bi bi-file-earmark-medical-fill"></i> Diagnostics & Imaging
      </a>
      <a href="<?= APP_URL ?>/hospital/affiliations.php">
        <i class="bi bi-clock-history"></i> Affiliation History
      </a>

      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/hospital/settings.php">
        <i class="bi bi-gear-fill"></i> Hospital Settings
      </a>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="Log out?">
        <i class="bi bi-box-arrow-right"></i> Log Out
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar" style="background:var(--mc-teal);border-radius:var(--radius-md);">
          <i class="bi bi-building-fill" style="font-size:16px;"></i>
        </div>
        <div class="sidebar-user-info">
          <div class="name" style="font-size:11px;"><?= htmlspecialchars(mb_strimwidth($hospital['name'], 0, 22, '...')) ?></div>
          <div class="role"><?= htmlspecialchars($hospital['hospital_uid']) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <main class="portal-main">
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">
          <?= htmlspecialchars($hospital['name']) ?>
          <span class="verified-badge" style="margin-left:8px;vertical-align:middle;">
            <i class="bi bi-patch-check-fill"></i> Verified
          </span>
        </h2>
        <div style="font-size:12px;color:var(--mc-text-muted);">
          <?= ucfirst(strtolower($hospital['type'] ?? '')) ?>
          <?= $hospital['city'] ? ' · ' . htmlspecialchars($hospital['city']) : '' ?>
          · <?= date('D, d M Y') ?>
        </div>
      </div>
      <?php if ($stats['active_sessions'] > 0): ?>
      <div class="access-timer">
        <i class="bi bi-shield-lock-fill"></i>
        <span><?= $stats['active_sessions'] ?> active session<?= $stats['active_sessions'] > 1 ? 's' : '' ?></span>
      </div>
      <?php endif; ?>
    </header>

    <div class="portal-content">

      <!-- Stats -->
      <div class="grid-4 mb-8">
        <div class="stat-card">
          <div class="stat-icon stat-icon-teal"><i class="bi bi-person-badge-fill"></i></div>
          <div class="stat-value"><?= $stats['affiliated_doctors'] ?></div>
          <div class="stat-label">Affiliated Doctors</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-blue"><i class="bi bi-diagram-3-fill"></i></div>
          <div class="stat-value"><?= $stats['active_departments'] ?></div>
          <div class="stat-label">Active Departments</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-amber"><i class="bi bi-calendar-check-fill"></i></div>
          <div class="stat-value"><?= $stats['today_consultations'] ?></div>
          <div class="stat-label">Today's Consultations</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-green"><i class="bi bi-stethoscope"></i></div>
          <div class="stat-value"><?= $stats['total_consultations'] ?></div>
          <div class="stat-label">Total Consultations</div>
        </div>
      </div>

      <!-- Pending Affiliations -->
      <?php if (!empty($pendingAffiliations)): ?>
      <div class="card mb-6" id="affiliations">
        <div class="d-flex align-center justify-between mb-4">
          <h3 style="font-size:1rem;font-weight:700;">
            <i class="bi bi-person-plus-fill text-amber"></i>
            Pending Affiliation Requests
          </h3>
          <span class="badge badge-warning"><?= count($pendingAffiliations) ?> Pending</span>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Doctor</th>
                <th>Reg ID</th>
                <th>Specialization</th>
                <th>Email</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingAffiliations as $aff): ?>
            <tr>
              <td><strong><?= htmlspecialchars($aff['full_name']) ?></strong></td>
              <td><code><?= htmlspecialchars($aff['medical_registration_id']) ?></code></td>
              <td><?= htmlspecialchars($aff['specialization'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($aff['email']) ?></td>
              <td>
                <div class="action-group">
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="dh_id" value="<?= $aff['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success btn-sm">
                      <i class="bi bi-check-lg"></i> Approve
                    </button>
                  </form>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="dh_id" value="<?= $aff['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-secondary btn-sm" data-confirm="Reject this affiliation?">
                      <i class="bi bi-x-lg"></i> Reject
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <div class="grid-2">

        <!-- Affiliated Doctors -->
        <div class="card">
          <div class="d-flex align-center justify-between mb-4">
            <h3 style="font-size:1rem;font-weight:700;">Affiliated Doctors</h3>
            <a href="<?= APP_URL ?>/hospital/doctors.php" style="font-size:13px;">Manage All</a>
          </div>
          <?php if (empty($doctors)): ?>
          <div class="empty-state" style="padding:var(--space-6) 0;">
            <div class="empty-state-icon"><i class="bi bi-person-badge"></i></div>
            <p>No doctors affiliated yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($doctors as $doc): ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--mc-border);">
            <div class="d-flex align-center gap-3">
              <div class="sidebar-avatar" style="width:36px;height:36px;font-size:13px;background:var(--mc-teal-light);color:var(--mc-teal-dark);">
                <?= strtoupper(substr(str_replace('Dr. ', '', $doc['full_name']), 0, 1)) ?>
              </div>
              <div style="flex:1;">
                <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($doc['full_name']) ?></div>
                <div style="font-size:11px;color:var(--mc-text-muted);">
                  <?= htmlspecialchars($doc['specialization'] ?? '') ?>
                  <?= $doc['department_name'] ? ' · ' . htmlspecialchars($doc['department_name']) : '' ?>
                </div>
              </div>
              <span class="badge badge-success" style="font-size:10px;">Active</span>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Departments + Recent Consultations -->
        <div class="card">
          <div class="d-flex align-center justify-between mb-4">
            <h3 style="font-size:1rem;font-weight:700;">Recent Consultations</h3>
            <a href="<?= APP_URL ?>/hospital/consultations.php" style="font-size:13px;">View All</a>
          </div>
          <?php if (empty($recentConsultations)): ?>
          <div class="empty-state" style="padding:var(--space-6) 0;">
            <div class="empty-state-icon"><i class="bi bi-stethoscope"></i></div>
            <p>No consultations yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($recentConsultations as $cons): ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--mc-border);">
            <div class="d-flex align-center justify-between gap-3">
              <div>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($cons['patient_name']) ?></div>
                <div style="font-size:11px;color:var(--mc-text-muted);">
                  <?= htmlspecialchars($cons['doctor_name']) ?> · <?= htmlspecialchars($cons['specialization'] ?? '') ?>
                </div>
              </div>
              <div style="font-size:11px;color:var(--mc-text-muted);text-align:right;white-space:nowrap;">
                <?= date('d M Y', strtotime($cons['consultation_date'])) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

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
