<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireDoctorHospitalContext();

$db = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];

// Handle quick hospital switch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_hospital_id'])) {
    validateCsrf();
    $targetHospId = (int)$_POST['switch_hospital_id'];

    $stmtSwitch = $db->prepare("
        SELECT dh.*, h.name AS hospital_name
        FROM doctor_hospitals dh
        JOIN hospitals h ON dh.hospital_id = h.id
        WHERE dh.doctor_id = ? AND dh.hospital_id = ? AND dh.status = 'APPROVED'
    ");
    $stmtSwitch->execute([$doctorId, $targetHospId]);
    $switched = $stmtSwitch->fetch();

    if ($switched) {
        $_SESSION['hospital_id']   = $switched['hospital_id'];
        $_SESSION['hospital_name'] = $switched['hospital_name'];
        $_SESSION['selected_hospital_id']   = $switched['hospital_id'];
        $_SESSION['selected_hospital_name'] = $switched['hospital_name'];

        AuditService::log('DOCTOR_SWITCH_HOSPITAL', [
            'user_id'    => $_SESSION['user_id'],
            'doctor_id'  => $doctorId,
            'hospital_id'=> $targetHospId,
            'metadata'   => ['switched_to' => $switched['hospital_name']]
        ]);

        header('Location: ' . APP_URL . '/doctor/dashboard.php?switched=1');
        exit;
    }
}

// Doctor profile
$stmt = $db->prepare("
    SELECT d.*, u.email, u.phone, u.last_login_at,
           h.name AS hospital_name, h.type AS hospital_type, h.city AS hospital_city,
           dh.designation AS affiliation_designation, dh.department_id, dh.employment_type,
           dept.name AS department_name
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    JOIN doctor_hospitals dh ON dh.doctor_id = d.id AND dh.hospital_id = ?
    JOIN hospitals h ON h.id = ?
    LEFT JOIN departments dept ON dept.id = dh.department_id
    WHERE d.id = ?
");
$stmt->execute([$hospitalId, $hospitalId, $doctorId]);
$doctor = $stmt->fetch();

// Fetch all active hospital affiliations for this doctor
$stmtAllAffils = $db->prepare("
    SELECT dh.*, h.name AS hospital_name, h.hospital_uid, h.type AS hospital_type, h.city AS hospital_city,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN hospitals h ON dh.hospital_id = h.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.doctor_id = ? AND dh.status = 'APPROVED'
    ORDER BY h.name ASC
");
$stmtAllAffils->execute([$doctorId]);
$affiliatedHospitals = $stmtAllAffils->fetchAll();

// Stats
$stmt = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM consultations WHERE doctor_id = :d1 AND hospital_id = :h1 AND DATE(consultation_date) = CURDATE()) AS today_consultations,
        (SELECT COUNT(*) FROM consultations WHERE doctor_id = :d2 AND hospital_id = :h2) AS total_consultations,
        (SELECT COUNT(*) FROM prescriptions WHERE doctor_id = :d3 AND hospital_id = :h3 AND status = 'ACTIVE') AS active_prescriptions,
        (SELECT COUNT(*) FROM access_sessions WHERE doctor_id = :d4 AND status = 'ACTIVE' AND expires_at > NOW()) AS active_sessions,
        (SELECT COUNT(*) FROM access_requests WHERE doctor_id = :d5 AND status = 'PENDING') AS pending_requests
");
$stmt->execute([
    ':d1' => $doctorId, ':h1' => $hospitalId,
    ':d2' => $doctorId, ':h2' => $hospitalId,
    ':d3' => $doctorId, ':h3' => $hospitalId,
    ':d4' => $doctorId,
    ':d5' => $doctorId,
]);
$stats = $stmt->fetch();

// Recent consultations
$stmt = $db->prepare("
    SELECT c.*, p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           (SELECT COUNT(*) FROM patient_allergies pa WHERE pa.patient_id = p.id AND pa.severity IN ('SEVERE','LIFE_THREATENING')) AS critical_allergy_count
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    WHERE c.doctor_id = ? AND c.hospital_id = ?
    ORDER BY c.consultation_date DESC
    LIMIT 6
");
$stmt->execute([$doctorId, $hospitalId]);
$recentConsultations = $stmt->fetchAll();

// Active access sessions
$stmt = $db->prepare("
    SELECT acs.*, p.full_name AS patient_name, p.patient_uid,
           TIMESTAMPDIFF(SECOND, NOW(), acs.expires_at) AS seconds_remaining
    FROM access_sessions acs
    JOIN patients p ON acs.patient_id = p.id
    WHERE acs.doctor_id = ? AND acs.status = 'ACTIVE' AND acs.expires_at > NOW()
    ORDER BY acs.expires_at ASC
    LIMIT 3
");
$stmt->execute([$doctorId]);
$activeSessions = $stmt->fetchAll();

// Recent prescriptions issued
$stmt = $db->prepare("
    SELECT rx.*, p.full_name AS patient_name, p.patient_uid,
           COUNT(pm.id) AS med_count
    FROM prescriptions rx
    JOIN patients p ON rx.patient_id = p.id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    WHERE rx.doctor_id = ? AND rx.hospital_id = ?
    GROUP BY rx.id
    ORDER BY rx.created_at DESC
    LIMIT 5
");
$stmt->execute([$doctorId, $hospitalId]);
$recentPrescriptions = $stmt->fetchAll();

// Log dashboard access
AuditService::log('DASHBOARD_VIEW', [
    'user_id'    => $_SESSION['user_id'],
    'doctor_id'  => $doctorId,
    'hospital_id'=> $hospitalId,
]);

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Doctor Dashboard — MedCore Clinical Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .hospital-switch-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: var(--radius-md);
      font-size: 12px;
      font-weight: 600;
      background: var(--mc-blue-light);
      color: var(--mc-blue);
      border: 1px solid rgba(37,99,235,0.2);
      cursor: pointer;
      transition: var(--transition);
    }
    .hospital-switch-btn:hover {
      background: var(--mc-blue);
      color: white;
    }
    .affil-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
      border-radius: var(--radius-md);
      border: 1px solid var(--mc-border);
      background: var(--mc-white);
      transition: var(--transition);
    }
    .affil-card.active {
      border-color: var(--mc-blue);
      background: var(--mc-blue-light);
    }
  </style>
</head>
<body>
<div class="portal-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" role="navigation" aria-label="Doctor portal navigation">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-tagline" style="color:var(--mc-teal);">Clinical Portal</div>
      </div>
    </div>

    <!-- Hospital context badge -->
    <div style="padding:var(--space-3) var(--space-5);background:var(--mc-blue-50);border-bottom:1px solid var(--mc-border);">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:10px;color:var(--mc-text-muted);text-transform:uppercase;letter-spacing:1px;">Active Hospital</span>
        <?php if (count($affiliatedHospitals) > 1): ?>
          <a href="javascript:void(0)" onclick="openHospitalModal()" style="font-size:11px; font-weight:700; color:var(--mc-blue); text-decoration:none;">
            Switch (<?= count($affiliatedHospitals) ?>)
          </a>
        <?php endif; ?>
      </div>
      <div style="font-size:13px;font-weight:700;color:var(--mc-blue);margin-top:2px;">
        <i class="bi bi-building-fill-check"></i>
        <?= htmlspecialchars($doctor['hospital_name']) ?>
      </div>
      <div style="font-size:11px; color:var(--mc-text-muted); margin-top:1px;">
        <?= htmlspecialchars($doctor['affiliation_designation'] ?? 'Consultant') ?> · <?= htmlspecialchars($doctor['employment_type'] ?? 'FULL_TIME') ?>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/doctor/dashboard.php" class="active" aria-current="page">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
      </a>

      <div class="sidebar-section-title">Patient Care</div>
      <a href="<?= APP_URL ?>/doctor/patient-search.php">
        <i class="bi bi-search-heart-fill"></i> Patient Search
      </a>
      <a href="<?= APP_URL ?>/doctor/consultations.php">
        <i class="bi bi-stethoscope"></i> My Consultations
      </a>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php">
        <i class="bi bi-file-earmark-medical-fill"></i> My Prescriptions
      </a>
      <a href="<?= APP_URL ?>/doctor/reports.php">
        <i class="bi bi-graph-up-arrow"></i> Practice Analytics &amp; Reports
      </a>

      <div class="sidebar-section-title">Access & Multi-Hospital</div>
      <a href="<?= APP_URL ?>/doctor/request-affiliation.php">
        <i class="bi bi-building-add"></i> Request Hospital Affiliation
      </a>
      <a href="javascript:void(0)" onclick="openHospitalModal()">
        <i class="bi bi-hospital"></i> Switch Hospital (<?= count($affiliatedHospitals) ?>)
      </a>

      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="End your clinical session?">
        <i class="bi bi-box-arrow-right"></i> End Session
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar" style="background:var(--mc-teal);">
          <?= strtoupper(substr($doctor['full_name'], 4, 1) ?: substr($doctor['full_name'], 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
          <div class="name"><?= htmlspecialchars($doctor['full_name']) ?></div>
          <div class="role"><?= htmlspecialchars($doctor['specialization'] ?? 'Physician') ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>

      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">
          <?= htmlspecialchars($doctor['full_name']) ?>
          <span class="verified-badge" style="margin-left:8px;vertical-align:middle;">
            <i class="bi bi-patch-check-fill"></i> BMDC Verified
          </span>
        </h2>
        <div style="font-size:12px;color:var(--mc-text-muted);">
          <?= htmlspecialchars($doctor['department_name'] ?? $doctor['specialization'] ?? '') ?>
          · <strong style="color:var(--mc-blue);"><?= htmlspecialchars($doctor['hospital_name']) ?></strong>
          · <?= date('D, d M Y') ?>
        </div>
      </div>

      <!-- Multi-hospital switcher button in topbar -->
      <?php if (count($affiliatedHospitals) > 1): ?>
        <button type="button" class="hospital-switch-btn" onclick="openHospitalModal()">
          <i class="bi bi-arrow-left-right"></i>
          <span>Switch Hospital (<?= count($affiliatedHospitals) ?>)</span>
        </button>
      <?php endif; ?>

      <!-- Active session indicator -->
      <?php if (!empty($activeSessions)): ?>
      <div class="access-timer">
        <i class="bi bi-shield-lock-fill"></i>
        <span><?= count($activeSessions) ?> active session<?= count($activeSessions) > 1 ? 's' : '' ?></span>
      </div>
      <?php endif; ?>

      <a href="<?= APP_URL ?>/doctor/patient-search.php" class="btn btn-primary btn-sm">
        <i class="bi bi-search-heart-fill"></i> Find Patient
      </a>
    </header>

    <div class="portal-content">

      <?php if (isset($_GET['switched'])): ?>
      <div class="alert alert-success mb-4" data-auto-dismiss="5000">
        <i class="bi bi-check-circle-fill"></i> Switched practice location to <strong><?= htmlspecialchars($doctor['hospital_name']) ?></strong>.
      </div>
      <?php endif; ?>

      <!-- ACTIVE ACCESS SESSIONS BANNER -->
      <?php if (!empty($activeSessions)): ?>
      <div style="margin-bottom:var(--space-6);">
        <?php foreach ($activeSessions as $s): ?>
        <?php $mins = max(0, (int)ceil($s['seconds_remaining'] / 60)); ?>
        <div class="alert" style="background:var(--mc-blue-50);border:1px solid rgba(26,115,232,0.3);margin-bottom:var(--space-3);">
          <i class="bi bi-shield-lock-fill text-blue" style="font-size:1.2rem;flex-shrink:0;"></i>
          <div style="flex:1;">
            <strong>Active Medical Record Session</strong> —
            <?= htmlspecialchars($s['patient_name']) ?> (<?= $s['patient_uid'] ?>)
          </div>
          <div class="access-timer" style="<?= $mins <= 5 ? 'background:var(--mc-red-light);border-color:var(--mc-red);' : '' ?>">
            <i class="bi bi-clock-fill"></i>
            <span class="timer-display" id="timer-<?= $s['id'] ?>"><?= str_pad($mins, 2, '0', STR_PAD_LEFT) ?>:00</span>
          </div>
          <a href="<?= APP_URL ?>/doctor/medical-record.php?patient=<?= $s['patient_id'] ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-folder2-open"></i> Open Record
          </a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- STATS -->
      <div class="grid-4 mb-8">
        <div class="stat-card">
          <div class="stat-icon stat-icon-blue"><i class="bi bi-calendar-check-fill"></i></div>
          <div class="stat-value"><?= $stats['today_consultations'] ?></div>
          <div class="stat-label">Today's Consultations</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-teal"><i class="bi bi-stethoscope"></i></div>
          <div class="stat-value"><?= $stats['total_consultations'] ?></div>
          <div class="stat-label">Total at This Hospital</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-green"><i class="bi bi-file-earmark-medical-fill"></i></div>
          <div class="stat-value"><?= $stats['active_prescriptions'] ?></div>
          <div class="stat-label">Active Prescriptions</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-amber"><i class="bi bi-building-fill-check"></i></div>
          <div class="stat-value"><?= count($affiliatedHospitals) ?></div>
          <div class="stat-label">Affiliated Hospitals</div>
        </div>
      </div>

      <!-- MULTI-HOSPITAL AFFILIATIONS SUMMARY BAR -->
      <div class="card mb-6" style="padding: 16px 20px; border: 1px solid var(--mc-border); background: linear-gradient(to right, #F8FAFC, #FFFFFF);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <div>
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">
              <i class="bi bi-diagram-3-fill text-blue"></i> My Hospital Practice Network
            </h3>
            <div style="font-size: 12px; color: var(--mc-text-muted);">
              You are authorized to practice across these verified medical centers
            </div>
          </div>
          <span class="badge" style="background: #EFF6FF; color: #1E40AF; font-weight: 700;">
            <?= count($affiliatedHospitals) ?> Active Hospital<?= count($affiliatedHospitals) > 1 ? 's' : '' ?>
          </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
          <?php foreach ($affiliatedHospitals as $aff): ?>
          <?php $isCurrent = ($aff['hospital_id'] == $hospitalId); ?>
          <div class="affil-card <?= $isCurrent ? 'active' : '' ?>">
            <div>
              <div style="font-weight: 700; font-size: 13px; color: var(--mc-text);">
                <?= htmlspecialchars($aff['hospital_name']) ?>
                <?php if ($isCurrent): ?>
                  <span class="badge badge-verified" style="font-size: 10px; margin-left: 4px;">CURRENT</span>
                <?php endif; ?>
              </div>
              <div style="font-size: 11px; color: var(--mc-text-muted);">
                <?= htmlspecialchars($aff['department_name'] ?? 'General') ?>
                · <?= htmlspecialchars($aff['employment_type']) ?>
              </div>
            </div>

            <?php if (!$isCurrent): ?>
              <form method="POST" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="switch_hospital_id" value="<?= $aff['hospital_id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 10px;">
                  Switch Here
                </button>
              </form>
            <?php else: ?>
              <span style="font-size: 12px; color: var(--mc-blue); font-weight: 700;"><i class="bi bi-check2-circle"></i> In Session</span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- QUICK PATIENT LOOKUP -->
      <div class="card mb-6">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:var(--space-4);">
          <i class="bi bi-search-heart-fill text-blue"></i> Quick Patient Lookup
        </h3>
        <form action="<?= APP_URL ?>/doctor/patient-search.php" method="GET" style="display:flex;gap:var(--space-3);">
          <div class="search-bar" style="flex:1;">
            <i class="bi bi-search"></i>
            <input type="text" name="q" placeholder="Search by Patient ID (PT-000001) or NID..." autocomplete="off">
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Search
          </button>
        </form>
        <p style="font-size:12px;color:var(--mc-text-muted);margin-top:var(--space-3);">
          <i class="bi bi-shield-lock-fill" style="color:var(--mc-green);"></i>
          All searches are audit-logged. Patient records require explicit consent before access.
        </p>
      </div>

      <div class="grid-2">

        <!-- Recent Consultations -->
        <div class="card">
          <div class="d-flex align-center justify-between mb-4">
            <h3 style="font-size:1rem;font-weight:700;">Today's Patient Queue &amp; Consultations</h3>
            <a href="<?= APP_URL ?>/doctor/consultations.php" style="font-size:13px;">View All</a>
          </div>

          <?php if (empty($recentConsultations)): ?>
          <div class="empty-state" style="padding:var(--space-6) 0;">
            <div class="empty-state-icon"><i class="bi bi-calendar2-heart"></i></div>
            <p>No consultations at <?= htmlspecialchars($doctor['hospital_name']) ?> yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($recentConsultations as $cons): ?>
          <?php $age = date_diff(new DateTime($cons['date_of_birth']), new DateTime())->y; ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--mc-border);">
            <div class="d-flex align-center gap-3">
              <div class="sidebar-avatar" style="width:36px;height:36px;font-size:13px;background:var(--mc-bg-alt);color:var(--mc-text);">
                <?= strtoupper(substr($cons['patient_name'], 0, 1)) ?>
              </div>
              <div style="flex:1;">
                <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($cons['patient_name']) ?></div>
                <div style="font-size:11px;color:var(--mc-text-muted);">
                  <?= $cons['patient_uid'] ?> · Age <?= $age ?> · <?= ucfirst(strtolower($cons['gender'])) ?>
                  <?php if ($cons['critical_allergy_count'] > 0): ?>
                    · <span style="color:var(--mc-red);font-weight:700;"><i class="bi bi-exclamation-triangle-fill"></i> Allergy</span>
                  <?php endif; ?>
                </div>
                <div style="font-size:11px;color:var(--mc-text-muted);"><?= date('d M, h:i A', strtotime($cons['consultation_date'])) ?></div>
              </div>
              <a href="<?= APP_URL ?>/doctor/patient-search.php?q=<?= urlencode($cons['patient_uid']) ?>"
                 class="btn btn-secondary btn-sm">
                <i class="bi bi-folder2-open"></i>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

          <a href="<?= APP_URL ?>/doctor/patient-search.php" class="btn btn-secondary w-100" style="margin-top:var(--space-4);">
            <i class="bi bi-search-heart-fill"></i> Find New Patient
          </a>
        </div>

        <!-- Recent Prescriptions -->
        <div class="card">
          <div class="d-flex align-center justify-between mb-4">
            <h3 style="font-size:1rem;font-weight:700;">Recent Prescriptions</h3>
            <a href="<?= APP_URL ?>/doctor/prescriptions.php" style="font-size:13px;">View All</a>
          </div>

          <?php if (empty($recentPrescriptions)): ?>
          <div class="empty-state" style="padding:var(--space-6) 0;">
            <div class="empty-state-icon"><i class="bi bi-file-earmark-medical"></i></div>
            <p>No prescriptions issued yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($recentPrescriptions as $rx): ?>
          <div style="padding:10px 0;border-bottom:1px solid var(--mc-border);">
            <div class="d-flex align-center justify-between gap-3">
              <div>
                <div style="font-size:13px;font-weight:700;font-family:var(--font-mono);"><?= htmlspecialchars($rx['prescription_uid']) ?></div>
                <div style="font-size:12px;color:var(--mc-text-muted);">
                  <?= htmlspecialchars($rx['patient_name']) ?> · <?= $rx['med_count'] ?> med(s)
                </div>
                <div style="font-size:11px;color:var(--mc-text-muted);"><?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?></div>
              </div>
              <span class="badge badge-<?= $rx['status'] === 'ACTIVE' ? 'success' : 'neutral' ?>">
                <?= $rx['status'] ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- HOSPITAL SWITCH MODAL -->
<div id="hospital-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; padding:20px;">
  <div style="background:white; border-radius:var(--radius-xl); max-width:480px; width:100%; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h3 style="font-size:1.2rem; font-weight:800; color:#0F172A; margin:0;">
        <i class="bi bi-hospital text-blue"></i> Switch Clinical Hospital
      </h3>
      <button type="button" onclick="closeHospitalModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--mc-text-muted);">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <p style="font-size:13px; color:var(--mc-text-secondary); margin:0 0 16px 0;">
      Select the hospital location where you are conducting patient consultations right now:
    </p>

    <div style="display:flex; flex-direction:column; gap:10px;">
      <?php foreach ($affiliatedHospitals as $aff): ?>
      <?php $isCurrent = ($aff['hospital_id'] == $hospitalId); ?>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="switch_hospital_id" value="<?= $aff['hospital_id'] ?>">
        <button type="submit" class="affil-card w-100 <?= $isCurrent ? 'active' : '' ?>" style="text-align:left; cursor:pointer; width:100%; border:1.5px solid <?= $isCurrent ? 'var(--mc-blue)' : 'var(--mc-border)' ?>;">
          <div>
            <div style="font-weight:700; font-size:14px; color:var(--mc-text);">
              <?= htmlspecialchars($aff['hospital_name']) ?>
            </div>
            <div style="font-size:12px; color:var(--mc-text-muted);">
              <?= htmlspecialchars($aff['department_name'] ?? 'General') ?>
              · <?= htmlspecialchars($aff['employment_type']) ?>
              · <?= htmlspecialchars($aff['hospital_city'] ?? '') ?>
            </div>
          </div>
          <?php if ($isCurrent): ?>
            <span class="badge badge-verified" style="font-size:11px;"><i class="bi bi-check-lg"></i> Active</span>
          <?php else: ?>
            <span class="btn btn-secondary btn-sm" style="font-size:11px;">Switch →</span>
          <?php endif; ?>
        </button>
      </form>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
// Sidebar toggle
document.getElementById('hamburger-btn')?.addEventListener('click', function() {
  const sidebar = document.getElementById('sidebar');
  const expanded = this.getAttribute('aria-expanded') === 'true';
  this.setAttribute('aria-expanded', !expanded);
  sidebar.classList.toggle('open');
});

function openHospitalModal() {
  document.getElementById('hospital-modal').style.display = 'flex';
}

function closeHospitalModal() {
  document.getElementById('hospital-modal').style.display = 'none';
}

// Access session countdown timers
<?php foreach ($activeSessions as $s): ?>
startAccessTimer('timer-<?= $s['id'] ?>', <?= max(0, (int)$s['seconds_remaining']) ?>, function() {
  location.reload();
});
<?php endforeach; ?>
</script>
</body>
</html>
