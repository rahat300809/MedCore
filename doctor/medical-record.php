<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireDoctorHospitalContext();

$db         = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];
$patientId  = (int)($_GET['patient_id'] ?? 0);

if (!$patientId) {
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

// 1. Verify Active Access Session (CRITICAL SECURITY CHECK)
$stmtSession = $db->prepare("
    SELECT s.*, TIMESTAMPDIFF(SECOND, NOW(), s.expires_at) AS seconds_remaining
    FROM access_sessions s
    WHERE s.doctor_id = ? AND s.patient_id = ? AND s.hospital_id = ?
      AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ORDER BY s.expires_at DESC
    LIMIT 1
");
$stmtSession->execute([$doctorId, $patientId, $hospitalId]);
$accessSession = $stmtSession->fetch();

if (!$accessSession) {
    AuditService::log(AuditService::UNAUTHORIZED_ACCESS, [
        'user_id'    => $_SESSION['user_id'],
        'doctor_id'  => $doctorId,
        'patient_id' => $patientId,
        'hospital_id'=> $hospitalId,
        'severity'   => 'WARNING',
        'metadata'   => ['reason' => 'No active consent session when accessing medical record'],
    ]);
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Active consent required. Please search and request patient access first.'];
    header('Location: ' . APP_URL . '/doctor/patient-search.php?q=PT-' . str_pad($patientId, 6, '0', STR_PAD_LEFT));
    exit;
}

// Handle revoke session from doctor side
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_session'])) {
    validateCsrf();
    $db->prepare("UPDATE access_sessions SET status = 'EXPIRED', revoked_at = NOW() WHERE id = ?")
       ->execute([$accessSession['id']]);
    AuditService::log('SESSION_ENDED_BY_DOCTOR', [
        'user_id'    => $_SESSION['user_id'],
        'doctor_id'  => $doctorId,
        'patient_id' => $patientId,
        'hospital_id'=> $hospitalId,
    ]);
    $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Medical record access session ended safely.'];
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

// 2. Fetch Patient Details
$stmtPatient = $db->prepare("
    SELECT p.*, u.email, u.phone, u.last_login_at
    FROM patients p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
");
$stmtPatient->execute([$patientId]);
$patient = $stmtPatient->fetch();

if (!$patient) {
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

$age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;

// Log authorized access
AuditService::log(AuditService::VIEW_MEDICAL_HISTORY, [
    'user_id'    => $_SESSION['user_id'],
    'doctor_id'  => $doctorId,
    'patient_id' => $patientId,
    'hospital_id'=> $hospitalId,
    'metadata'   => ['session_id' => $accessSession['id']],
]);

// 3. Fetch Medical History (Conditions)
$stmtHistory = $db->prepare("
    SELECT mh.*, d.full_name AS doctor_name
    FROM medical_histories mh
    LEFT JOIN doctors d ON mh.created_by = d.id
    WHERE mh.patient_id = ?
    ORDER BY mh.diagnosed_date DESC, mh.id DESC
");
$stmtHistory->execute([$patientId]);
$conditions = $stmtHistory->fetchAll();

// 4. Fetch Allergies
$stmtAllergies = $db->prepare("
    SELECT pa.*, a.name AS allergy_name, a.type AS allergy_type, a.description AS allergy_desc
    FROM patient_allergies pa
    JOIN allergies a ON a.id = pa.allergy_id
    WHERE pa.patient_id = ?
    ORDER BY FIELD(pa.severity, 'LIFE_THREATENING', 'SEVERE', 'MODERATE', 'MILD')
");
$stmtAllergies->execute([$patientId]);
$allergies = $stmtAllergies->fetchAll();

// 5. Fetch Active & Past Medications
$stmtMeds = $db->prepare("
    SELECT pm.*, m.generic_name, m.brand_name, m.strength, m.dosage_form,
           d.full_name AS doctor_name
    FROM patient_medications pm
    JOIN medications m ON pm.medication_id = m.id
    LEFT JOIN doctors d ON pm.prescribed_by = d.id
    WHERE pm.patient_id = ?
    ORDER BY FIELD(pm.status, 'ACTIVE', 'COMPLETED', 'DISCONTINUED'), pm.started_at DESC
");
$stmtMeds->execute([$patientId]);
$medications = $stmtMeds->fetchAll();

// 6. Fetch Prescriptions
$stmtRx = $db->prepare("
    SELECT rx.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name,
           (SELECT COUNT(*) FROM prescription_medicines pm WHERE pm.prescription_id = rx.id) AS medicine_count
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    WHERE rx.patient_id = ?
    ORDER BY rx.created_at DESC
");
$stmtRx->execute([$patientId]);
$prescriptions = $stmtRx->fetchAll();

// 7. Fetch Lab Reports
$stmtLabs = $db->prepare("
    SELECT * FROM lab_reports
    WHERE patient_id = ?
    ORDER BY test_date DESC
");
$stmtLabs->execute([$patientId]);
$labReports = $stmtLabs->fetchAll();

// 8. Fetch Consultations History
$stmtCons = $db->prepare("
    SELECT c.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name,
           dept.name AS department_name
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN hospitals h ON c.hospital_id = h.id
    LEFT JOIN departments dept ON c.department_id = dept.id
    WHERE c.patient_id = ?
    ORDER BY c.consultation_date DESC
");
$stmtCons->execute([$patientId]);
$consultations = $stmtCons->fetchAll();

$hasCriticalAllergy = false;
foreach ($allergies as $alg) {
    if (in_array($alg['severity'], ['SEVERE', 'LIFE_THREATENING'])) {
        $hasCriticalAllergy = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($patient['full_name']) ?> — Medical Record — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .record-header-banner {
      background: linear-gradient(135deg, #0B57D0 0%, #1A73E8 100%);
      color: #fff;
      border-radius: var(--radius-xl);
      padding: var(--space-6) var(--space-8);
      margin-bottom: var(--space-6);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: var(--space-4);
      box-shadow: 0 4px 14px rgba(26, 115, 232, 0.25);
    }
    .session-badge {
      background: rgba(255,255,255,0.18);
      border: 1px solid rgba(255,255,255,0.3);
      backdrop-filter: blur(8px);
      padding: var(--space-2) var(--space-4);
      border-radius: var(--radius-full);
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      font-size: 0.9rem;
      font-weight: 600;
    }
    .critical-alert-box {
      background: #FEF2F2;
      border: 1px solid #FCA5A5;
      border-left: 5px solid var(--mc-danger);
      border-radius: var(--radius-lg);
      padding: var(--space-4) var(--space-5);
      margin-bottom: var(--space-6);
      display: flex;
      align-items: center;
      gap: var(--space-4);
      color: #991B1B;
    }
    .tab-nav {
      display: flex;
      gap: var(--space-2);
      border-bottom: 2px solid var(--mc-border);
      margin-bottom: var(--space-6);
      overflow-x: auto;
    }
    .tab-btn {
      padding: var(--space-3) var(--space-5);
      border: none;
      background: none;
      font-weight: 600;
      color: var(--mc-text-muted);
      cursor: pointer;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      transition: var(--transition-fast);
      white-space: nowrap;
    }
    .tab-btn:hover {
      color: var(--mc-blue);
    }
    .tab-btn.active {
      color: var(--mc-blue);
      border-bottom-color: var(--mc-blue);
    }
    .tab-pane {
      display: none;
    }
    .tab-pane.active {
      display: block;
    }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" role="navigation">
    <div class="sidebar-brand">
      <div class="brand-icon">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Doctor Portal</div>
      </div>
    </div>

    <div class="doctor-context-badge" style="margin: 0 var(--space-4) var(--space-4); padding: var(--space-3); background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-md);">
      <div style="font-size: 0.75rem; color: #1E40AF; font-weight: 700; text-transform: uppercase;">Active Practice Context</div>
      <div style="font-weight: 600; font-size: 0.88rem; color: #1E3A8A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($_SESSION['hospital_name']) ?></div>
      <div style="font-size: 0.78rem; color: #3B82F6;"><?= htmlspecialchars($_SESSION['department_name'] ?? 'General Practitioner') ?></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Clinical Care</div>
      <a href="<?= APP_URL ?>/doctor/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/patient-search.php" class="nav-item active">
        <i class="bi bi-search"></i>
        <span>Search Patient</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="nav-item">
        <i class="bi bi-file-earmark-medical-fill"></i>
        <span>Write Prescription</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/consultations.php" class="nav-item">
        <i class="bi bi-chat-heart-fill"></i>
        <span>Consultations</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php" class="nav-item">
        <i class="bi bi-prescription2"></i>
        <span>Prescriptions</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'D', 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Doctor') ?></div>
          <div class="user-role">BMDC Verified</div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-outline btn-sm" style="width: 100%; margin-top: var(--space-3);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="portal-main">
    <!-- Top Header -->
    <header class="portal-header">
      <div class="d-flex align-items-center gap-3">
        <a href="<?= APP_URL ?>/doctor/patient-search.php" class="btn btn-secondary btn-sm">
          <i class="bi bi-arrow-left"></i> Back to Search
        </a>
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Authorized Health Record</h1>
      </div>
      <div class="header-actions d-flex align-items-center gap-3">
        <div id="session-countdown" class="session-badge" style="background: #FEF3C7; border-color: #FCD34D; color: #92400E;">
          <i class="bi bi-hourglass-split"></i>
          <span>Session: <strong id="timer-text">--:--</strong></span>
        </div>
        <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-circle-fill"></i> New Prescription
        </a>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to end this medical record session?');">
          <?= csrfField() ?>
          <input type="hidden" name="end_session" value="1">
          <button type="submit" class="btn btn-outline btn-sm text-danger" title="Close access session">
            <i class="bi bi-x-circle"></i> End Session
          </button>
        </form>
      </div>
    </header>

    <div class="portal-body">

      <!-- Critical Allergies Warning if present -->
      <?php if ($hasCriticalAllergy): ?>
        <div class="critical-alert-box">
          <div style="font-size: 2rem;"><i class="bi bi-exclamation-triangle-fill"></i></div>
          <div>
            <strong style="font-size: 1.05rem;">CRITICAL CLINICAL ALERT: Severe Allergies Documented</strong>
            <div style="font-size: 0.9rem; margin-top: var(--space-1);">
              Patient has documented severe/life-threatening allergic reactions. Review the Allergies tab before prescribing any medications!
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Patient Header Banner -->
      <div class="record-header-banner">
        <div>
          <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-2);">
            <h2 style="font-size: 1.6rem; font-weight: 800; margin: 0; color: #fff;"><?= htmlspecialchars($patient['full_name']) ?></h2>
            <span class="badge" style="background: rgba(255,255,255,0.25); color: #fff; border: 1px solid rgba(255,255,255,0.4);"><?= htmlspecialchars($patient['patient_uid']) ?></span>
            <span class="badge" style="background: #10B981; color: #fff;"><i class="bi bi-shield-check"></i> Consent Verified</span>
          </div>
          <div style="display: flex; flex-wrap: wrap; gap: var(--space-6); font-size: 0.92rem; opacity: 0.95;">
            <div><i class="bi bi-person"></i> <?= $age ?> yrs (<?= date('d M Y', strtotime($patient['date_of_birth'])) ?>) &bull; <?= ucfirst(strtolower($patient['gender'])) ?></div>
            <div><i class="bi bi-droplet-half"></i> Blood Group: <strong><?= htmlspecialchars($patient['blood_group'] ?? 'Unknown') ?></strong></div>
            <div><i class="bi bi-person-vcard"></i> NID: &bull;&bull;&bull;&bull; <?= htmlspecialchars($patient['nid_last4']) ?></div>
            <div><i class="bi bi-telephone"></i> Emergency: <?= htmlspecialchars($patient['emergency_contact_name'] ?? 'N/A') ?> (<?= htmlspecialchars($patient['emergency_contact_phone'] ?? 'N/A') ?>)</div>
          </div>
        </div>
        <div>
          <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="btn btn-sm" style="background: #fff; color: var(--mc-blue); font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
            <i class="bi bi-prescription2"></i> Start Consultation &amp; Rx
          </a>
        </div>
      </div>

      <!-- Quick Stats Strip -->
      <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom: var(--space-6);">
        <div class="stat-card" style="padding: var(--space-4);">
          <div class="stat-value" style="font-size: 1.5rem; color: var(--mc-blue);"><?= count($conditions) ?></div>
          <div class="stat-label">Diagnosed Conditions</div>
        </div>
        <div class="stat-card" style="padding: var(--space-4);">
          <div class="stat-value" style="font-size: 1.5rem; color: #00C49F;"><?= count($medications) ?></div>
          <div class="stat-label">Medications Record</div>
        </div>
        <div class="stat-card" style="padding: var(--space-4);">
          <div class="stat-value" style="font-size: 1.5rem; color: <?= $hasCriticalAllergy ? 'var(--mc-danger)' : '#10B981' ?>;"><?= count($allergies) ?></div>
          <div class="stat-label">Known Allergies</div>
        </div>
        <div class="stat-card" style="padding: var(--space-4);">
          <div class="stat-value" style="font-size: 1.5rem; color: #8B5CF6;"><?= count($prescriptions) ?></div>
          <div class="stat-label">Past Prescriptions</div>
        </div>
        <div class="stat-card" style="padding: var(--space-4);">
          <div class="stat-value" style="font-size: 1.5rem; color: #F59E0B;"><?= count($labReports) ?></div>
          <div class="stat-label">Lab Reports</div>
        </div>
      </div>

      <!-- TAB NAVIGATION -->
      <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('tab-conditions', this)">
          <i class="bi bi-heart-pulse-fill"></i> Medical History (<?= count($conditions) ?>)
        </button>
        <button class="tab-btn" onclick="switchTab('tab-allergies', this)">
          <i class="bi bi-exclamation-octagon-fill"></i> Allergies (<?= count($allergies) ?>)
        </button>
        <button class="tab-btn" onclick="switchTab('tab-medications', this)">
          <i class="bi bi-capsule-pill"></i> Medications (<?= count($medications) ?>)
        </button>
        <button class="tab-btn" onclick="switchTab('tab-prescriptions', this)">
          <i class="bi bi-prescription2"></i> Prescriptions (<?= count($prescriptions) ?>)
        </button>
        <button class="tab-btn" onclick="switchTab('tab-labs', this)">
          <i class="bi bi-file-earmark-medical"></i> Lab Reports (<?= count($labReports) ?>)
        </button>
        <button class="tab-btn" onclick="switchTab('tab-consultations', this)">
          <i class="bi bi-journal-medical"></i> Consultations (<?= count($consultations) ?>)
        </button>
      </div>

      <!-- TAB 1: MEDICAL HISTORY / CONDITIONS -->
      <div id="tab-conditions" class="tab-pane active">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-heart-pulse"></i> Chronic Illnesses &amp; Diagnosed Conditions</h3>
            <span class="badge badge-info"><?= count($conditions) ?> Records</span>
          </div>
          <div class="card-body">
            <?php if (empty($conditions)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-clipboard-check" style="font-size: 3rem; color: #CBD5E1;"></i>
                <p class="mt-2">No prior chronic conditions or diagnoses recorded for this patient.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Condition / Illness</th>
                      <th>ICD Code</th>
                      <th>Status</th>
                      <th>Diagnosed Date</th>
                      <th>Diagnosed By</th>
                      <th>Clinical Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($conditions as $cond): ?>
                      <tr>
                        <td>
                          <strong><?= htmlspecialchars($cond['condition_name']) ?></strong>
                          <?php if ($cond['description']): ?>
                            <div class="text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($cond['description']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td><span class="badge badge-secondary"><?= htmlspecialchars($cond['icd_code'] ?? 'N/A') ?></span></td>
                        <td>
                          <?php if ($cond['status'] === 'ACTIVE' || $cond['status'] === 'CHRONIC'): ?>
                            <span class="badge badge-warning"><?= htmlspecialchars($cond['status']) ?></span>
                          <?php else: ?>
                            <span class="badge badge-success"><?= htmlspecialchars($cond['status']) ?></span>
                          <?php endif; ?>
                        </td>
                        <td><?= $cond['diagnosed_date'] ? date('d M Y', strtotime($cond['diagnosed_date'])) : 'Undated' ?></td>
                        <td><?= htmlspecialchars($cond['doctor_name'] ? 'Dr. ' . $cond['doctor_name'] : 'System Record') ?></td>
                        <td><?= htmlspecialchars($cond['notes'] ?? '—') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TAB 2: ALLERGIES -->
      <div id="tab-allergies" class="tab-pane">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-shield-exclamation"></i> Patient Allergies &amp; Adverse Drug Reactions</h3>
            <span class="badge badge-danger"><?= count($allergies) ?> Documented</span>
          </div>
          <div class="card-body">
            <?php if (empty($allergies)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-shield-check" style="font-size: 3rem; color: #10B981;"></i>
                <p class="mt-2">No known drug, food, or environmental allergies documented.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Allergen / Substance</th>
                      <th>Category</th>
                      <th>Severity</th>
                      <th>Reaction Details</th>
                      <th>Verification</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($allergies as $alg): ?>
                      <tr style="<?= in_array($alg['severity'], ['SEVERE', 'LIFE_THREATENING']) ? 'background: #FEF2F2;' : '' ?>">
                        <td>
                          <strong style="<?= in_array($alg['severity'], ['SEVERE', 'LIFE_THREATENING']) ? 'color: var(--mc-danger);' : '' ?>">
                            <?= htmlspecialchars($alg['allergy_name']) ?>
                          </strong>
                        </td>
                        <td><span class="badge badge-secondary"><?= htmlspecialchars($alg['allergy_type']) ?></span></td>
                        <td>
                          <?php if ($alg['severity'] === 'LIFE_THREATENING'): ?>
                            <span class="badge badge-danger"><i class="bi bi-exclamation-octagon-fill"></i> LIFE THREATENING</span>
                          <?php elseif ($alg['severity'] === 'SEVERE'): ?>
                            <span class="badge badge-danger">SEVERE</span>
                          <?php elseif ($alg['severity'] === 'MODERATE'): ?>
                            <span class="badge badge-warning">MODERATE</span>
                          <?php else: ?>
                            <span class="badge badge-info">MILD</span>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($alg['reaction'] ?? 'General sensitivity') ?></td>
                        <td>
                          <?php if ($alg['verified']): ?>
                            <span class="badge badge-success"><i class="bi bi-check-circle"></i> Verified</span>
                          <?php else: ?>
                            <span class="badge badge-secondary">Patient Reported</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TAB 3: MEDICATIONS -->
      <div id="tab-medications" class="tab-pane">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-capsule"></i> Current &amp; Past Medications</h3>
            <span class="badge badge-info"><?= count($medications) ?> Total</span>
          </div>
          <div class="card-body">
            <?php if (empty($medications)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-capsule" style="font-size: 3rem; color: #CBD5E1;"></i>
                <p class="mt-2">No medications currently recorded for this patient.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Medicine Name</th>
                      <th>Dosage &amp; Strength</th>
                      <th>Frequency</th>
                      <th>Route</th>
                      <th>Status</th>
                      <th>Started</th>
                      <th>Prescribing Doctor</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($medications as $med): ?>
                      <tr>
                        <td>
                          <strong><?= htmlspecialchars($med['brand_name'] ?: $med['generic_name']) ?></strong>
                          <?php if ($med['brand_name'] && $med['generic_name']): ?>
                            <div class="text-muted" style="font-size: 0.82rem;"><?= htmlspecialchars($med['generic_name']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($med['dosage'] ?? $med['strength'] ?? 'Standard') ?> &bull; <small class="text-muted"><?= htmlspecialchars($med['dosage_form']) ?></small></td>
                        <td><span class="badge badge-secondary"><?= htmlspecialchars($med['frequency'] ?? 'As directed') ?></span></td>
                        <td><?= htmlspecialchars($med['route']) ?></td>
                        <td>
                          <?php if ($med['status'] === 'ACTIVE'): ?>
                            <span class="badge badge-success"><i class="bi bi-check2"></i> Active</span>
                          <?php elseif ($med['status'] === 'COMPLETED'): ?>
                            <span class="badge badge-secondary">Completed</span>
                          <?php else: ?>
                            <span class="badge badge-danger">Discontinued</span>
                          <?php endif; ?>
                        </td>
                        <td><?= $med['started_at'] ? date('d M Y', strtotime($med['started_at'])) : '—' ?></td>
                        <td><?= htmlspecialchars($med['doctor_name'] ? 'Dr. ' . $med['doctor_name'] : '—') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TAB 4: PRESCRIPTIONS -->
      <div id="tab-prescriptions" class="tab-pane">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-file-earmark-medical"></i> Prescriptions History</h3>
            <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-plus-lg"></i> Issue New Prescription
            </a>
          </div>
          <div class="card-body">
            <?php if (empty($prescriptions)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-prescription2" style="font-size: 3rem; color: #CBD5E1;"></i>
                <p class="mt-2">No past prescriptions found for this patient.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Prescription ID</th>
                      <th>Date</th>
                      <th>Doctor</th>
                      <th>Hospital</th>
                      <th>Diagnosis</th>
                      <th>Medicines</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($prescriptions as $rx): ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($rx['prescription_uid']) ?></strong></td>
                        <td><?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?></td>
                        <td>Dr. <?= htmlspecialchars($rx['doctor_name']) ?> <small class="text-muted">(<?= htmlspecialchars($rx['specialization'] ?? '') ?>)</small></td>
                        <td><?= htmlspecialchars($rx['hospital_name']) ?></td>
                        <td><?= htmlspecialchars($rx['diagnosis'] ?? 'Clinical Evaluation') ?></td>
                        <td><span class="badge badge-info"><?= $rx['medicine_count'] ?> items</span></td>
                        <td><span class="badge badge-success"><?= htmlspecialchars($rx['status']) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TAB 5: LAB REPORTS -->
      <div id="tab-labs" class="tab-pane">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-clipboard2-pulse"></i> Diagnostic Lab Reports</h3>
            <span class="badge badge-info"><?= count($labReports) ?> Reports</span>
          </div>
          <div class="card-body">
            <?php if (empty($labReports)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-ruled" style="font-size: 3rem; color: #CBD5E1;"></i>
                <p class="mt-2">No diagnostic lab reports uploaded for this patient.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Report / Test Name</th>
                      <th>Date</th>
                      <th>Laboratory</th>
                      <th>Result Summary</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($labReports as $lab): ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($lab['report_name']) ?></strong></td>
                        <td><?= $lab['test_date'] ? date('d M Y', strtotime($lab['test_date'])) : '—' ?></td>
                        <td><?= htmlspecialchars($lab['laboratory_name'] ?? 'Diagnostic Center') ?></td>
                        <td><?= htmlspecialchars($lab['result_summary'] ?? 'Attached Document') ?></td>
                        <td>
                          <?php if ($lab['verified']): ?>
                            <span class="badge badge-success"><i class="bi bi-check-circle"></i> Verified</span>
                          <?php else: ?>
                            <span class="badge badge-secondary">Pending Verification</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TAB 6: CONSULTATIONS -->
      <div id="tab-consultations" class="tab-pane">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="bi bi-journal-medical"></i> Past Clinical Consultations</h3>
            <span class="badge badge-info"><?= count($consultations) ?> Records</span>
          </div>
          <div class="card-body">
            <?php if (empty($consultations)): ?>
              <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-medical" style="font-size: 3rem; color: #CBD5E1;"></i>
                <p class="mt-2">No prior consultations recorded.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Consultation ID</th>
                      <th>Date</th>
                      <th>Doctor</th>
                      <th>Hospital / Dept</th>
                      <th>Chief Complaint</th>
                      <th>Advice</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($consultations as $cons): ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($cons['consultation_uid']) ?></strong></td>
                        <td><?= date('d M Y', strtotime($cons['consultation_date'])) ?></td>
                        <td>Dr. <?= htmlspecialchars($cons['doctor_name']) ?></td>
                        <td><?= htmlspecialchars($cons['hospital_name']) ?> &bull; <?= htmlspecialchars($cons['department_name'] ?? 'OPD') ?></td>
                        <td><?= htmlspecialchars($cons['chief_complaint'] ?? 'General checkup') ?></td>
                        <td><?= htmlspecialchars($cons['advice'] ?? '—') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
// Session countdown timer
let secondsLeft = <?= (int)($accessSession['seconds_remaining'] ?? 1800) ?>;
const timerEl = document.getElementById('timer-text');

function updateTimer() {
  if (secondsLeft <= 0) {
    timerEl.textContent = 'Expired';
    alert('Your 30-minute authorized access session has expired. Please re-request consent if needed.');
    window.location.href = '<?= APP_URL ?>/doctor/patient-search.php';
    return;
  }
  const mins = Math.floor(secondsLeft / 60);
  const secs = secondsLeft % 60;
  timerEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  secondsLeft--;
}
updateTimer();
setInterval(updateTimer, 1000);

// Tab switching
function switchTab(tabId, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
  btn.classList.add('active');
}
</script>

</body>
</html>
