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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($hospital['name']) ?> — Hospital Specs — MedCore Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-dark: #0F172A;
      --admin-primary: #1E40AF;
    }
    .badge-verified { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .badge-pending { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .badge-suspended { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" style="background: var(--admin-dark);">
    <div class="sidebar-brand" style="border-color: rgba(255,255,255,0.1);">
      <div class="brand-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">M</div>
      <div>
        <div class="brand-name" style="color:white;">MedCore</div>
        <div class="brand-sub" style="color: #94A3B8; font-size:11px;">Central Administration</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title" style="color: #64748B;">Central Oversight</div>
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i> System Dashboard
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i> Doctor Verification
      </a>
      <a href="<?= APP_URL ?>/admin/hospitals.php" class="nav-item active">
        <i class="bi bi-building-fill-check"></i> Hospital Network
      </a>
      <a href="<?= APP_URL ?>/admin/affiliations.php" class="nav-item">
        <i class="bi bi-diagram-3-fill"></i> Hospital Affiliations
      </a>

      <div class="nav-section-title" style="color: #64748B; margin-top: 16px;">National Registry</div>
      <a href="<?= APP_URL ?>/admin/patients.php" class="nav-item">
        <i class="bi bi-people-fill"></i> Patient Registry
      </a>
      <a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-item">
        <i class="bi bi-shield-check"></i> Audit & Compliance
      </a>
    </nav>

    <div class="sidebar-footer" style="border-color: rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
        <div class="user-avatar" style="background: #3B82F6; color:white; width:36px; height:36px; font-size:13px;">
          SA
        </div>
        <div style="overflow:hidden;">
          <div style="font-weight:700; font-size:13px; color:white;"><?= htmlspecialchars($_SESSION['name'] ?? 'System Admin') ?></div>
          <div style="font-size:11px; color:#94A3B8;"><?= htmlspecialchars($_SESSION['email'] ?? 'admin@medcore.local') ?></div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-secondary btn-sm w-100" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: white;">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="portal-content">
    <header class="top-nav">
      <div class="top-nav-left">
        <a href="<?= APP_URL ?>/admin/hospitals.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">
          ← Back to Hospitals
        </a>
        <div>
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">HOSPITAL PROFILE & ROSTER</span>
          <h1 class="page-title" style="font-size: 1.3rem;"><?= htmlspecialchars($hospital['name']) ?></h1>
        </div>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <!-- HOSPITAL HEADER CARD -->
      <div class="card" style="padding: 24px; border: 1px solid var(--mc-border); margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
          <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
              <h2 style="font-size: 1.3rem; font-weight: 800; margin: 0;"><?= htmlspecialchars($hospital['name']) ?></h2>
              <span class="badge badge-verified"><i class="bi bi-patch-check-fill"></i> <?= htmlspecialchars($hospital['verification_status']) ?></span>
            </div>
            <div style="font-size: 13px; color: var(--mc-text-secondary); margin-bottom: 4px;">
              <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($hospital['address'] ?? '') ?>, <?= htmlspecialchars($hospital['city'] ?? '') ?>
            </div>
            <div style="font-size: 12px; color: var(--mc-text-muted); font-family: var(--font-mono);">
              UID: <?= htmlspecialchars($hospital['hospital_uid']) ?> · Reg: <?= htmlspecialchars($hospital['registration_number']) ?> · Type: <?= htmlspecialchars($hospital['type']) ?>
            </div>
          </div>

          <div style="display: flex; gap: 12px; font-size: 13px;">
            <div style="background: #F8FAFC; border: 1px solid var(--mc-border); border-radius: var(--radius-md); padding: 10px 16px; text-align: center;">
              <div style="font-size: 1.4rem; font-weight: 800; color: #1E40AF;"><?= count($doctors) ?></div>
              <div style="font-size: 11px; color: var(--mc-text-muted);">Affiliated Doctors</div>
            </div>
            <div style="background: #F8FAFC; border: 1px solid var(--mc-border); border-radius: var(--radius-md); padding: 10px 16px; text-align: center;">
              <div style="font-size: 1.4rem; font-weight: 800; color: #0D9488;"><?= count($departments) ?></div>
              <div style="font-size: 11px; color: var(--mc-text-muted);">Departments</div>
            </div>
          </div>
        </div>
      </div>

      <!-- HOSPITAL DOCTOR ROSTER -->
      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border); margin-bottom: 24px;">
        <div style="padding: 16px 20px; background: var(--mc-white); border-bottom: 1px solid var(--mc-border); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;">
              <i class="bi bi-people-fill text-blue"></i> Affiliated Medical Staff Roster
            </h3>
            <div style="font-size: 12px; color: var(--mc-text-muted);">
              Doctors practicing at <?= htmlspecialchars($hospital['name']) ?>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Doctor</th>
                <th>BMDC Reg ID</th>
                <th>Department</th>
                <th>Hospital Designation</th>
                <th>Employment Type</th>
                <th>Affiliation Status</th>
                <th>Joined Date</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($doctors)): ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 32px; color: var(--mc-text-muted);">
                  No doctors currently affiliated with this hospital.
                </td>
              </tr>
              <?php endif; ?>

              <?php foreach ($doctors as $d): ?>
              <tr>
                <td>
                  <div style="font-weight: 700; color: var(--mc-text);">
                    <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $d['doctor_id'] ?>" style="text-decoration:none; color:inherit;">
                      <?= htmlspecialchars($d['full_name']) ?>
                    </a>
                  </div>
                  <div style="font-size: 11px; color: var(--mc-text-muted);">
                    <?= htmlspecialchars($d['doctor_uid']) ?> · <?= htmlspecialchars($d['specialization'] ?? '') ?>
                  </div>
                </td>

                <td>
                  <strong style="color: #1E40AF; font-family: var(--font-mono); font-size: 12px;">
                    <?= htmlspecialchars($d['medical_registration_id']) ?>
                  </strong>
                </td>

                <td>
                  <span class="badge" style="background: #EFF6FF; color: #1E40AF;">
                    <?= htmlspecialchars($d['department_name'] ?? 'General') ?>
                  </span>
                </td>

                <td>
                  <?= htmlspecialchars($d['designation'] ?? 'Consultant') ?>
                </td>

                <td>
                  <span class="badge" style="background: #F1F5F9; color: #334155; font-size: 11px;">
                    <?= htmlspecialchars($d['employment_type']) ?>
                  </span>
                </td>

                <td>
                  <?php if ($d['status'] === 'APPROVED'): ?>
                    <span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> ACTIVE</span>
                  <?php elseif ($d['status'] === 'PENDING'): ?>
                    <span class="badge badge-pending">PENDING</span>
                  <?php elseif ($d['status'] === 'REMOVED'): ?>
                    <span class="badge badge-suspended">REMOVED</span>
                  <?php else: ?>
                    <span class="badge badge-rejected"><?= htmlspecialchars($d['status']) ?></span>
                  <?php endif; ?>
                </td>

                <td style="font-size: 12px; color: var(--mc-text-muted);">
                  <?= $d['joined_at'] ? date('M d, Y', strtotime($d['joined_at'])) : '-' ?>
                </td>

                <td style="text-align: right;">
                  <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $d['doctor_id'] ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 12px;">
                    Doctor Dossier →
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>
</body>
</html>
