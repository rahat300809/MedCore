<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireDoctorHospitalContext();

$db        = getDB();
$doctorId  = (int)$_SESSION['doctor_id'];
$activeHospId = (int)$_SESSION['hospital_id'];

// Get doctor profile details
$stmtDoc = $db->prepare("SELECT * FROM doctors WHERE id = ?");
$stmtDoc->execute([$doctorId]);
$doctor = $stmtDoc->fetch();

// Hospital filter: default to all hospitals or active hospital
$selectedHospFilter = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : 0;
$searchQ = trim($_GET['q'] ?? '');

// Fetch all affiliated hospitals for this doctor
$stmtAffils = $db->prepare("
    SELECT h.id, h.name, h.hospital_uid, h.type, h.city, dh.designation, dh.employment_type, dh.status,
           (SELECT COUNT(DISTINCT c.patient_id) FROM consultations c WHERE c.doctor_id = ? AND c.hospital_id = h.id) AS patient_count,
           (SELECT COUNT(*) FROM prescriptions rx WHERE rx.doctor_id = ? AND rx.hospital_id = h.id) AS rx_count,
           (SELECT COUNT(*) FROM consultations c WHERE c.doctor_id = ? AND c.hospital_id = h.id) AS consultation_count
    FROM doctor_hospitals dh
    JOIN hospitals h ON dh.hospital_id = h.id
    WHERE dh.doctor_id = ? AND dh.status = 'APPROVED'
    ORDER BY h.name ASC
");
$stmtAffils->execute([$doctorId, $doctorId, $doctorId, $doctorId]);
$affiliatedHospitals = $stmtAffils->fetchAll();

// Build where clause for analytics
$rxWhere = ["rx.doctor_id = ?"];
$rxParams = [$doctorId];

if ($selectedHospFilter > 0) {
    $rxWhere[] = "rx.hospital_id = ?";
    $rxParams[] = $selectedHospFilter;
}

$rxWhereSql = implode(' AND ', $rxWhere);

// Top Overview Metrics
$stmtTotals = $db->prepare("
    SELECT
        COUNT(DISTINCT rx.patient_id) AS total_patients,
        COUNT(rx.id) AS total_prescriptions,
        (SELECT COUNT(*) FROM consultations c WHERE c.doctor_id = ? " . ($selectedHospFilter > 0 ? "AND c.hospital_id = {$selectedHospFilter}" : "") . ") AS total_consultations
    FROM prescriptions rx
    WHERE {$rxWhereSql}
");
$stmtTotals->execute($rxParams);
$totals = $stmtTotals->fetch();

// Medicine Breakdown: which medicines provided and how many times
$stmtMeds = $db->prepare("
    SELECT
        pm.medicine_name,
        pm.strength,
        pm.dosage,
        pm.frequency,
        pm.route,
        COUNT(pm.id) AS prescribe_count,
        COUNT(DISTINCT rx.patient_id) AS unique_patients
    FROM prescription_medicines pm
    JOIN prescriptions rx ON pm.prescription_id = rx.id
    WHERE {$rxWhereSql}
    GROUP BY pm.medicine_name, pm.strength
    ORDER BY prescribe_count DESC
    LIMIT 50
");
$stmtMeds->execute($rxParams);
$medicineStats = $stmtMeds->fetchAll();

// Total distinct medicines
$totalDistinctMeds = count($medicineStats);

// Prescriptions History Log
$histWhere = ["rx.doctor_id = ?"];
$histParams = [$doctorId];

if ($selectedHospFilter > 0) {
    $histWhere[] = "rx.hospital_id = ?";
    $histParams[] = $selectedHospFilter;
}

if (!empty($searchQ)) {
    $histWhere[] = "(p.full_name LIKE ? OR p.patient_uid LIKE ? OR rx.diagnosis LIKE ? OR pm.medicine_name LIKE ?)";
    $like = "%{$searchQ}%";
    $histParams = array_merge($histParams, [$like, $like, $like, $like]);
}

$histWhereSql = implode(' AND ', $histWhere);

$stmtHistory = $db->prepare("
    SELECT rx.id, rx.prescription_uid, rx.diagnosis, rx.advice, rx.follow_up_date, rx.created_at,
           p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           h.name AS hospital_name,
           GROUP_CONCAT(DISTINCT CONCAT(pm.medicine_name, ' (', IFNULL(pm.dosage, ''), ' ', IFNULL(pm.frequency, ''), ')') SEPARATOR ' • ') AS medicines_summary
    FROM prescriptions rx
    JOIN patients p ON rx.patient_id = p.id
    JOIN hospitals h ON rx.hospital_id = h.id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    WHERE {$histWhereSql}
    GROUP BY rx.id
    ORDER BY rx.created_at DESC
    LIMIT 60
");
$stmtHistory->execute($histParams);
$prescriptionHistory = $stmtHistory->fetchAll();

AuditService::log('REPORTS_VIEW', [
    'user_id'   => $_SESSION['user_id'],
    'doctor_id' => $doctorId,
    'hospital_id' => $activeHospId,
    'metadata'  => ['filter_hospital' => $selectedHospFilter]
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Practice Analytics & Reports — Dr. <?= htmlspecialchars($doctor['full_name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .metric-card {
      background: #fff;
      border: 1px solid var(--mc-border);
      border-radius: 12px;
      padding: 18px 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .metric-title {
      font-size: 11px;
      font-weight: 700;
      color: var(--mc-text-muted);
      text-transform: uppercase;
      letter-spacing: .5px;
    }
    .metric-val {
      font-size: 2rem;
      font-weight: 800;
      margin-top: 4px;
      line-height: 1.1;
    }
    .hosp-section-card {
      background: #fff;
      border: 1.5px solid var(--mc-border);
      border-radius: 14px;
      padding: 16px 18px;
      transition: all 0.2s;
    }
    .hosp-section-card:hover {
      border-color: #3B82F6;
      box-shadow: 0 4px 14px rgba(59,130,246,0.08);
    }
    .hosp-section-card.active-context {
      border-color: #10B981;
      background: #F0FDF4;
    }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar no-print" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Doctor Portal</div>
      </div>
    </div>

    <div class="doctor-context-badge" style="margin: 0 var(--space-4) var(--space-4); padding: var(--space-3); background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-md);">
      <div style="font-size: 0.75rem; color: #1E40AF; font-weight: 700; text-transform: uppercase;">Active Hospital Practice</div>
      <div style="font-weight: 600; font-size: 0.88rem; color: #1E3A8A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($_SESSION['hospital_name']) ?></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Clinical Care</div>
      <a href="<?= APP_URL ?>/doctor/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/patient-search.php" class="nav-item">
        <i class="bi bi-search"></i>
        <span>Search Patient</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/consultations.php" class="nav-item">
        <i class="bi bi-chat-heart-fill"></i>
        <span>Consultations</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php" class="nav-item">
        <i class="bi bi-prescription2"></i>
        <span>Prescriptions</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/reports.php" class="nav-item active">
        <i class="bi bi-graph-up-arrow"></i>
        <span>Practice Analytics &amp; Reports</span>
      </a>

      <div class="nav-section-title">Multi-Hospital Ecosystem</div>
      <a href="<?= APP_URL ?>/doctor/request-affiliation.php" class="nav-item">
        <i class="bi bi-building-add"></i>
        <span>Affiliation Requests</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="nav-item">
        <i class="bi bi-arrow-left-right"></i>
        <span>Switch Hospital (<?= count($affiliatedHospitals) ?>)</span>
      </a>

      <div class="nav-section-title">Account</div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="nav-item text-danger">
        <i class="bi bi-box-arrow-right"></i>
        <span>End Session</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar" style="background: var(--mc-teal);"><?= strtoupper(substr($doctor['full_name'], 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name">Dr. <?= htmlspecialchars(mb_strimwidth($doctor['full_name'], 0, 18, '...')) ?></div>
          <div class="user-role"><?= htmlspecialchars($doctor['doctor_uid']) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-header">
      <div>
        <span class="eyebrow" style="color: var(--mc-teal-dark); font-size: 11px; font-weight: 700;">CLINICAL AUDIT &amp; DETAILED PRACTICE REPORTS</span>
        <h1 class="portal-title" style="font-size: 1.3rem; margin: 0;">Practice Analytics &amp; Prescribed Medicines</h1>
      </div>
      <div class="header-actions no-print">
        <button onclick="window.print()" class="btn btn-outline btn-sm">
          <i class="bi bi-printer"></i> Print / Export Report
        </button>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <!-- HOSPITAL FILTER BAR -->
      <div class="card mb-4 no-print" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
        <form method="GET" style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; flex: 1;">
            <label style="font-size: 13px; font-weight: 700; color: #1E293B;"><i class="bi bi-hospital"></i> Hospital Scope:</label>
            <select name="hospital_id" class="form-control" style="width: auto; font-size: 13px; font-weight: 600;" onchange="this.form.submit()">
              <option value="0">All Affiliated Hospitals Combined</option>
              <?php foreach ($affiliatedHospitals as $ah): ?>
                <option value="<?= $ah['id'] ?>" <?= $selectedHospFilter == $ah['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ah['name']) ?> (<?= htmlspecialchars($ah['city'] ?? 'Dhaka') ?>)
                </option>
              <?php endforeach; ?>
            </select>

            <div class="search-bar" style="max-width: 320px;">
              <i class="bi bi-search"></i>
              <input type="text" name="q" placeholder="Search medicine, patient, diagnosis..." value="<?= htmlspecialchars($searchQ) ?>">
            </div>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 14px;">Apply</button>
            <?php if ($selectedHospFilter || $searchQ): ?>
              <a href="<?= APP_URL ?>/doctor/reports.php" class="btn btn-ghost btn-sm" style="font-size: 12px;">Clear Filters</a>
            <?php endif; ?>
          </div>

          <div style="font-size: 12px; color: var(--mc-text-muted);">
            Logged-in Doctor: <strong>Dr. <?= htmlspecialchars($doctor['full_name']) ?></strong> (<?= htmlspecialchars($doctor['medical_registration_id']) ?>)
          </div>
        </form>
      </div>

      <!-- OVERVIEW STATS -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="metric-card">
          <div class="metric-title">Unique Patients Prescribed</div>
          <div class="metric-val" style="color: #0F766E;"><?= number_format($totals['total_patients'] ?? 0) ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); margin-top: 4px;">Verified patient encounters</div>
        </div>
        <div class="metric-card">
          <div class="metric-title">Prescriptions Issued</div>
          <div class="metric-val" style="color: #1E40AF;"><?= number_format($totals['total_prescriptions'] ?? 0) ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); margin-top: 4px;">With digital signature</div>
        </div>
        <div class="metric-card">
          <div class="metric-title">Consultations Conducted</div>
          <div class="metric-val" style="color: #7C3AED;"><?= number_format($totals['total_consultations'] ?? 0) ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); margin-top: 4px;">Clinical consultations logged</div>
        </div>
        <div class="metric-card">
          <div class="metric-title">Distinct Medicines Prescribed</div>
          <div class="metric-val" style="color: #D97706;"><?= number_format($totalDistinctMeds) ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); margin-top: 4px;">In active formulary catalog</div>
        </div>
      </div>

      <!-- HOSPITAL SECTION BREAKDOWN -->
      <div style="margin-bottom: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <div>
            <h2 style="font-size: 1.1rem; font-weight: 800; color: #0F172A; margin: 0;">Multi-Hospital Performance &amp; Patients Treated</h2>
            <div style="font-size: 12px; color: var(--mc-text-muted);">Compare patient volume and prescriptions across all hospitals you are accredited with</div>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px;">
          <?php foreach ($affiliatedHospitals as $hosp): ?>
          <div class="hosp-section-card <?= $hosp['id'] == $activeHospId ? 'active-context' : '' ?>">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
              <div>
                <div style="font-weight: 800; font-size: 14px; color: #0F172A;"><?= htmlspecialchars($hosp['name']) ?></div>
                <div style="font-size: 11px; color: var(--mc-text-muted);">
                  <?= htmlspecialchars($hosp['city'] ?? 'Dhaka') ?> · <?= htmlspecialchars($hosp['type']) ?>
                </div>
              </div>
              <?php if ($hosp['id'] == $activeHospId): ?>
                <span class="badge badge-success" style="font-size: 10px;"><i class="bi bi-circle-fill" style="font-size: 8px;"></i> Active Now</span>
              <?php else: ?>
                <span class="badge" style="background:#F1F5F9;color:#475569;font-size:10px;">Accredited</span>
              <?php endif; ?>
            </div>

            <div style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px; background: #F8FAFC; border-radius: 8px; padding: 10px;">
              <div>
                <span style="color: var(--mc-text-muted); display: block; font-size: 11px;">Patients Treated</span>
                <strong style="font-size: 16px; color: #0F766E;"><?= $hosp['patient_count'] ?></strong>
              </div>
              <div>
                <span style="color: var(--mc-text-muted); display: block; font-size: 11px;">Prescriptions</span>
                <strong style="font-size: 16px; color: #1E40AF;"><?= $hosp['rx_count'] ?></strong>
              </div>
            </div>

            <div style="margin-top: 10px; font-size: 11px; color: var(--mc-text-secondary); display: flex; justify-content: space-between;">
              <span>Role: <strong><?= htmlspecialchars($hosp['designation'] ?? 'Consultant') ?></strong></span>
              <span><?= ucfirst(strtolower(str_replace('_',' ',$hosp['employment_type']))) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- PRESCRIBED MEDICINES IN-DEPTH REPORT -->
      <div class="card mb-4" style="border: 1px solid var(--mc-border); padding: 0; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--mc-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
          <div>
            <h3 style="font-size: 15px; font-weight: 800; color: #0F172A; margin: 0;">Prescribed Medicines Formulary Report</h3>
            <div style="font-size: 12px; color: var(--mc-text-muted);">Detailed frequency, dosage, route, and unique patient counts for all prescribed pharmaceuticals</div>
          </div>
          <span class="badge" style="background:#E0E7FF;color:#3730A3;font-weight:700;font-size:12px;">
            <?= count($medicineStats) ?> Medicines Cataloged
          </span>
        </div>

        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Medicine Name &amp; Strength</th>
                <th>Prescribed Count</th>
                <th>Unique Patients</th>
                <th>Common Dosage</th>
                <th>Frequency Pattern</th>
                <th>Route</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($medicineStats)): ?>
              <tr>
                <td colspan="6" style="text-align: center; padding: 30px; color: var(--mc-text-muted);">
                  No medicines have been prescribed yet in this practice scope.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($medicineStats as $med): ?>
                <tr>
                  <td>
                    <strong style="color: #0F172A;"><?= htmlspecialchars($med['medicine_name']) ?></strong>
                    <?php if ($med['strength']): ?>
                      <span style="color: var(--mc-text-muted); font-size: 12px; margin-left: 4px;"><?= htmlspecialchars($med['strength']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge" style="background: #DCFCE7; color: #166534; font-weight: 700;">
                      <?= $med['prescribe_count'] ?> times
                    </span>
                  </td>
                  <td>
                    <span style="font-weight: 600; color: #1E40AF;"><?= $med['unique_patients'] ?> patients</span>
                  </td>
                  <td><?= htmlspecialchars($med['dosage'] ?: 'As needed') ?></td>
                  <td><?= htmlspecialchars($med['frequency'] ?: 'Daily') ?></td>
                  <td><span class="badge badge-secondary"><?= htmlspecialchars($med['route'] ?: 'ORAL') ?></span></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- PREVIOUS PATIENT PRECRIPTIONS LOG -->
      <div class="card" style="border: 1px solid var(--mc-border); padding: 0; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--mc-border); background: #F8FAFC; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
          <div>
            <h3 style="font-size: 15px; font-weight: 800; color: #0F172A; margin: 0;">Prescription Log &amp; Clinical Audit History</h3>
            <div style="font-size: 12px; color: var(--mc-text-muted);">Comprehensive record of all prescriptions issued with diagnosis and medications provided</div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Date &amp; RX UID</th>
                <th>Patient</th>
                <th>Hospital</th>
                <th>Diagnosis</th>
                <th>Medicines Provided</th>
                <th>Follow-up</th>
                <th class="no-print">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($prescriptionHistory)): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 30px; color: var(--mc-text-muted);">
                  No prescription history matching the current filter.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($prescriptionHistory as $rx): ?>
                <tr>
                  <td>
                    <span style="font-family: monospace; font-weight: 700; color: #0F766E;"><?= htmlspecialchars($rx['prescription_uid']) ?></span>
                    <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 2px;">
                      <?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 700;"><?= htmlspecialchars($rx['patient_name']) ?></div>
                    <div style="font-size: 11px; color: var(--mc-text-muted);">
                      <span style="font-family: monospace;"><?= htmlspecialchars($rx['patient_uid']) ?></span>
                      <?php if ($rx['gender']): ?> · <?= $rx['gender'] ?><?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-size: 12px; font-weight: 600; color: #1E3A8A;"><?= htmlspecialchars($rx['hospital_name']) ?></div>
                  </td>
                  <td>
                    <div style="max-width: 160px; font-weight: 600; font-size: 12px; color: #334155;">
                      <?= htmlspecialchars($rx['diagnosis'] ?: 'General Consultation') ?>
                    </div>
                  </td>
                  <td>
                    <div style="max-width: 280px; font-size: 12px; color: #475569; line-height: 1.4;">
                      <?= htmlspecialchars($rx['medicines_summary'] ?: 'See prescription details') ?>
                    </div>
                  </td>
                  <td>
                    <?php if ($rx['follow_up_date']): ?>
                      <span style="font-size: 11px; color: #059669;"><i class="bi bi-calendar-event"></i> <?= date('d M Y', strtotime($rx['follow_up_date'])) ?></span>
                    <?php else: ?>
                      <span style="color: var(--mc-text-muted); font-size: 11px;">PRN (As needed)</span>
                    <?php endif; ?>
                  </td>
                  <td class="no-print">
                    <a href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= $rx['id'] ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 11px; padding: 3px 8px; white-space: nowrap;">
                      <i class="bi bi-file-earmark-text"></i> View Report
                    </a>
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
