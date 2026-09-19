<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('HOSPITAL_ADMIN');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Get hospital profile
$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE user_id = ?");
$stmtHosp->execute([$userId]);
$hospital = $stmtHosp->fetch();

if (!$hospital) {
    header('Location: ' . APP_URL . '/hospital/login.php');
    exit;
}
$hospitalId = (int)$hospital['id'];

$targetDoctorId = (int)($_GET['id'] ?? 0);
if ($targetDoctorId <= 0) {
    header('Location: ' . APP_URL . '/hospital/doctors.php');
    exit;
}

// Fetch doctor details and their specific affiliation with this hospital
$stmtDoc = $db->prepare("
    SELECT d.*, u.email, u.phone,
           dh.id AS affiliation_id, dh.designation AS hosp_designation, dh.employment_type AS hosp_emp_type,
           dh.status AS affiliation_status, dh.joined_at, dh.created_at AS affiliation_date,
           dept.name AS department_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    JOIN doctor_hospitals dh ON dh.doctor_id = d.id AND dh.hospital_id = ?
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE d.id = ?
");
$stmtDoc->execute([$hospitalId, $targetDoctorId]);
$doctor = $stmtDoc->fetch();

if (!$doctor) {
    header('Location: ' . APP_URL . '/hospital/doctors.php?error=doctor_not_found');
    exit;
}

// Statistics for this doctor at this hospital
$stmtStats = $db->prepare("
    SELECT
        COUNT(DISTINCT rx.patient_id) AS total_patients_treated,
        COUNT(rx.id) AS total_prescriptions,
        (SELECT COUNT(*) FROM consultations c WHERE c.doctor_id = ? AND c.hospital_id = ?) AS total_consultations
    FROM prescriptions rx
    WHERE rx.doctor_id = ? AND rx.hospital_id = ?
");
$stmtStats->execute([$targetDoctorId, $hospitalId, $targetDoctorId, $hospitalId]);
$stats = $stmtStats->fetch();

// Top medicines prescribed by this doctor at this hospital
$stmtMeds = $db->prepare("
    SELECT
        pm.medicine_name,
        pm.strength,
        COUNT(pm.id) AS times_prescribed,
        COUNT(DISTINCT rx.patient_id) AS patients_count
    FROM prescription_medicines pm
    JOIN prescriptions rx ON pm.prescription_id = rx.id
    WHERE rx.doctor_id = ? AND rx.hospital_id = ?
    GROUP BY pm.medicine_name, pm.strength
    ORDER BY times_prescribed DESC
    LIMIT 15
");
$stmtMeds->execute([$targetDoctorId, $hospitalId]);
$topMeds = $stmtMeds->fetchAll();

// Prescriptions issued by this doctor at this hospital
$searchQ = trim($_GET['q'] ?? '');
$rxWhere = ["rx.doctor_id = ?", "rx.hospital_id = ?"];
$rxParams = [$targetDoctorId, $hospitalId];

if (!empty($searchQ)) {
    $rxWhere[] = "(p.full_name LIKE ? OR p.patient_uid LIKE ? OR rx.diagnosis LIKE ? OR pm.medicine_name LIKE ?)";
    $like = "%{$searchQ}%";
    $rxParams = array_merge($rxParams, [$like, $like, $like, $like]);
}

$rxWhereSql = implode(' AND ', $rxWhere);

$stmtRxList = $db->prepare("
    SELECT rx.*,
           p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           GROUP_CONCAT(DISTINCT CONCAT(pm.medicine_name, ' (', IFNULL(pm.dosage, ''), ')') SEPARATOR ', ') AS medicines_list
    FROM prescriptions rx
    JOIN patients p ON rx.patient_id = p.id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    WHERE {$rxWhereSql}
    GROUP BY rx.id
    ORDER BY rx.created_at DESC
    LIMIT 50
");
$stmtRxList->execute($rxParams);
$prescriptions = $stmtRxList->fetchAll();

AuditService::log('HOSPITAL_DOCTOR_AUDIT', [
    'user_id'     => $userId,
    'hospital_id' => $hospitalId,
    'doctor_id'   => $targetDoctorId,
    'metadata'    => ['view' => 'doctor_clinical_profile']
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dr. <?= htmlspecialchars($doctor['full_name']) ?> — Clinical Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon" style="background: var(--mc-teal);">H</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Hospital Admin</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Hospital Ops</div>
      <a href="<?= APP_URL ?>/hospital/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/doctors.php" class="nav-item active">
        <i class="bi bi-person-badge-fill"></i>
        <span>Affiliated Doctors</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/diagnostics.php" class="nav-item">
        <i class="bi bi-file-earmark-medical-fill"></i>
        <span>Diagnostics &amp; Imaging</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/affiliations.php" class="nav-item">
        <i class="bi bi-clock-history"></i>
        <span>Affiliation History</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/departments.php" class="nav-item">
        <i class="bi bi-building"></i>
        <span>Departments</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/consultations.php" class="nav-item">
        <i class="bi bi-stethoscope"></i>
        <span>Consultations</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/settings.php" class="nav-item">
        <i class="bi bi-gear-fill"></i>
        <span>Hospital Settings</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar" style="background: var(--mc-teal);"><?= strtoupper(substr($hospital['name'], 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars(mb_strimwidth($hospital['name'], 0, 20, '...')) ?></div>
          <div class="user-role"><?= htmlspecialchars($hospital['hospital_uid']) ?></div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-secondary btn-sm w-100" style="margin-top:var(--space-2);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-content">
    <header class="top-nav">
      <div class="top-nav-left">
        <div>
          <a href="<?= APP_URL ?>/hospital/doctors.php" style="font-size: 12px; font-weight: 700; color: var(--mc-teal-dark); text-decoration: none;">
            <i class="bi bi-arrow-left"></i> Back to Affiliated Doctors
          </a>
          <h1 class="page-title" style="font-size: 1.3rem; margin-top: 4px;">Doctor Clinical Profile &amp; Audit</h1>
        </div>
      </div>
      <div class="top-nav-right">
        <a href="<?= APP_URL ?>/hospital/doctors.php" class="btn btn-secondary btn-sm">
          <i class="bi bi-people"></i> Staff Roster
        </a>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <!-- DOCTOR HERO HEADER CARD -->
      <div class="card mb-4" style="border: 1px solid var(--mc-border); padding: 24px; background: linear-gradient(135deg, #F8FAFC, #F0FDFA); border-radius: 16px;">
        <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
          <div style="width: 72px; height: 72px; border-radius: 50%; background: #0F766E; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; flex-shrink: 0; box-shadow: 0 4px 12px rgba(15,118,110,0.25);">
            <?= strtoupper(substr($doctor['full_name'], 0, 2)) ?>
          </div>

          <div style="flex: 1; min-width: 260px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
              <h2 style="font-size: 1.4rem; font-weight: 800; color: #0F172A; margin: 0;">
                Dr. <?= htmlspecialchars($doctor['full_name']) ?>
              </h2>
              <?php if ($doctor['affiliation_status'] === 'APPROVED'): ?>
                <span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> Active Staff</span>
              <?php else: ?>
                <span class="badge badge-pending"><?= htmlspecialchars($doctor['affiliation_status']) ?></span>
              <?php endif; ?>
            </div>

            <div style="font-size: 13px; color: #475569; margin-top: 4px;">
              <strong><?= htmlspecialchars($doctor['specialization'] ?? 'Physician') ?></strong>
              &nbsp;·&nbsp; BMDC Reg: <span style="font-family: monospace; font-weight: 700; color: #0F766E;"><?= htmlspecialchars($doctor['medical_registration_id']) ?></span>
              &nbsp;·&nbsp; UID: <span style="font-family: monospace;"><?= htmlspecialchars($doctor['doctor_uid']) ?></span>
            </div>

            <div style="margin-top: 10px; display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: var(--mc-text-secondary);">
              <span><i class="bi bi-briefcase"></i> Hospital Role: <strong><?= htmlspecialchars($doctor['hosp_designation'] ?? 'Consultant') ?></strong></span>
              <span><i class="bi bi-clock"></i> <strong><?= htmlspecialchars(str_replace('_',' ',$doctor['hosp_emp_type'] ?? 'FULL_TIME')) ?></strong></span>
              <?php if ($doctor['department_name']): ?>
                <span><i class="bi bi-building"></i> Dept: <strong><?= htmlspecialchars($doctor['department_name']) ?></strong></span>
              <?php endif; ?>
              <?php if ($doctor['joined_at']): ?>
                <span><i class="bi bi-calendar3"></i> Joined: <strong><?= date('M j, Y', strtotime($doctor['joined_at'])) ?></strong></span>
              <?php endif; ?>
            </div>

            <?php if ($doctor['qualification']): ?>
            <div style="margin-top: 10px; font-size: 12px; color: #334155; background: #fff; border: 1px solid #E2E8F0; padding: 6px 12px; border-radius: 8px; display: inline-block;">
              <i class="bi bi-mortarboard"></i> <?= htmlspecialchars($doctor['qualification']) ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- DOCTOR PERFORMANCE STATS AT THIS HOSPITAL -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="padding: 16px 20px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 700; color: var(--mc-text-muted); text-transform: uppercase;">Patients Treated Here</div>
          <div style="font-size: 1.8rem; font-weight: 800; color: #0F766E; margin-top: 4px;"><?= number_format($stats['total_patients_treated'] ?? 0) ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); margin-top: 2px;">At <?= htmlspecialchars($hospital['name']) ?></div>
        </div>
        <div class="card" style="padding: 16px 20px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 700; color: var(--mc-text-muted); text-transform: uppercase;">Prescriptions Issued</div>
          <div style="font-size: 1.8rem; font-weight: 800; color: #1E40AF; margin-top: 4px;"><?= number_format($stats['total_prescriptions'] ?? 0) ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); margin-top: 2px;">With digital signature</div>
        </div>
        <div class="card" style="padding: 16px 20px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 700; color: var(--mc-text-muted); text-transform: uppercase;">Consultations Recorded</div>
          <div style="font-size: 1.8rem; font-weight: 800; color: #7C3AED; margin-top: 4px;"><?= number_format($stats['total_consultations'] ?? 0) ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); margin-top: 2px;">Clinical encounters</div>
        </div>
        <div class="card" style="padding: 16px 20px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 700; color: var(--mc-text-muted); text-transform: uppercase;">Top Medicine Formulations</div>
          <div style="font-size: 1.8rem; font-weight: 800; color: #D97706; margin-top: 4px;"><?= count($topMeds) ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); margin-top: 2px;">Cataloged in practice</div>
        </div>
      </div>

      <!-- TOP MEDICINES PRESCRIBED BY THIS DOCTOR -->
      <?php if (!empty($topMeds)): ?>
      <div class="card mb-4" style="border: 1px solid var(--mc-border); padding: 0; overflow: hidden;">
        <div style="padding: 14px 20px; background: #F8FAFC; border-bottom: 1px solid var(--mc-border); font-weight: 800; font-size: 14px; color: #0F172A;">
          <i class="bi bi-capsule"></i> Frequently Prescribed Medicines at <?= htmlspecialchars($hospital['name']) ?>
        </div>
        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Medicine Name &amp; Strength</th>
                <th>Prescribed Count</th>
                <th>Unique Patients Treated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topMeds as $m): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($m['medicine_name']) ?></strong>
                  <?php if ($m['strength']): ?><span style="color:var(--mc-text-muted);margin-left:4px;"><?= htmlspecialchars($m['strength']) ?></span><?php endif; ?>
                </td>
                <td><span class="badge" style="background:#DCFCE7;color:#166534;font-weight:700;"><?= $m['times_prescribed'] ?> times</span></td>
                <td><span style="font-weight:600;color:#1E40AF;"><?= $m['patients_count'] ?> patients</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- PRESCRIPTIONS LOG BY THIS DOCTOR -->
      <div class="card" style="border: 1px solid var(--mc-border); padding: 0; overflow: hidden;">
        <div style="padding: 16px 20px; background: #F8FAFC; border-bottom: 1px solid var(--mc-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
          <div>
            <h3 style="font-size: 14px; font-weight: 800; color: #0F172A; margin: 0;">Prescriptions Issued by Dr. <?= htmlspecialchars($doctor['full_name']) ?></h3>
            <div style="font-size: 12px; color: var(--mc-text-muted);">Auditable clinical history at this hospital facility</div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Date &amp; RX UID</th>
                <th>Patient</th>
                <th>Diagnosis</th>
                <th>Prescribed Medicines</th>
                <th>Follow-up</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($prescriptions)): ?>
              <tr>
                <td colspan="5" style="text-align: center; padding: 30px; color: var(--mc-text-muted);">
                  No prescriptions recorded for this doctor at this hospital yet.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($prescriptions as $p): ?>
                <tr>
                  <td>
                    <span style="font-family: monospace; font-weight: 700; color: #0F766E;"><?= htmlspecialchars($p['prescription_uid']) ?></span>
                    <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 2px;">
                      <?= date('M j, Y, h:i A', strtotime($p['created_at'])) ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 700;"><?= htmlspecialchars($p['patient_name']) ?></div>
                    <div style="font-size: 11px; color: var(--mc-text-muted);"><span style="font-family: monospace;"><?= htmlspecialchars($p['patient_uid']) ?></span></div>
                  </td>
                  <td>
                    <div style="font-weight: 600; font-size: 12px; color: #334155;"><?= htmlspecialchars($p['diagnosis'] ?: 'General Consultation') ?></div>
                  </td>
                  <td>
                    <div style="max-width: 320px; font-size: 12px; color: #475569; line-height: 1.4;">
                      <?= htmlspecialchars($p['medicines_list'] ?: 'See prescription record') ?>
                    </div>
                  </td>
                  <td>
                    <?php if ($p['follow_up_date']): ?>
                      <span style="font-size: 11px; color: #059669;"><i class="bi bi-calendar-event"></i> <?= date('M j, Y', strtotime($p['follow_up_date'])) ?></span>
                    <?php else: ?>
                      <span style="color: var(--mc-text-muted); font-size: 11px;">PRN</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>
