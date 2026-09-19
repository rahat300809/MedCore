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

$success = '';
$error   = '';

// Handle New Diagnostic / Imaging Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_diagnostic') {
    validateCsrf();

    $patientSearch = trim($_POST['patient_id_or_uid'] ?? '');
    $title         = trim($_POST['title'] ?? '');
    $modality      = trim($_POST['modality'] ?? 'XRAY');
    $category      = trim($_POST['diagnostic_category'] ?? 'RADIOLOGY');
    $bodyPart      = trim($_POST['body_part'] ?? '');
    $indication    = trim($_POST['clinical_indication'] ?? '');
    $findings      = trim($_POST['findings'] ?? '');
    $impression    = trim($_POST['impression'] ?? '');
    $specialist    = trim($_POST['reporting_specialist'] ?? '');
    $testDate      = trim($_POST['test_date'] ?? date('Y-m-d'));
    $reportDate    = trim($_POST['report_date'] ?? date('Y-m-d'));
    $doctorAssigned= (int)($_POST['ordered_by_doctor_id'] ?? 0);
    $status        = trim($_POST['status'] ?? 'VERIFIED');
    $driveUrl      = trim($_POST['drive_url'] ?? '');

    // Resolve patient
    $targetPatientId = 0;
    if (!empty($patientSearch)) {
        $stmtP = $db->prepare("SELECT id, patient_uid, full_name FROM patients WHERE patient_uid = ? OR id = ? OR full_name LIKE ? LIMIT 1");
        $stmtP->execute([$patientSearch, (int)$patientSearch, "%{$patientSearch}%"]);
        $pRow = $stmtP->fetch();
        if ($pRow) {
            $targetPatientId = (int)$pRow['id'];
        }
    }

    if ($targetPatientId <= 0) {
        $error = 'Patient not found. Please enter a valid Patient UID (e.g. PT-000001) or select an existing patient.';
    } elseif (empty($title)) {
        $error = 'Please enter a diagnostic test or scan title.';
    } else {
        // Handle file upload if present
        $filePath    = null;
        $fileName    = null;
        $fileType    = null;
        $fileSize    = null;
        $storageType = 'LOCAL_FILE';

        if (!empty($driveUrl)) {
            $storageType = 'DRIVE_URL';
        }

        if (isset($_FILES['diagnostic_file']) && $_FILES['diagnostic_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedTmp = $_FILES['diagnostic_file']['tmp_name'];
            $origName    = $_FILES['diagnostic_file']['name'];
            $fileSize    = (int)$_FILES['diagnostic_file']['size'];
            $fileExt     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'dcm', 'dicom'];
            if (!in_array($fileExt, $allowedExts)) {
                $error = 'Invalid file format. Allowed: JPG, PNG, WEBP, PDF, DICOM.';
            } elseif ($fileSize > 35 * 1024 * 1024) {
                $error = 'File exceeds maximum upload limit of 35MB.';
            } else {
                $destDir = __DIR__ . "/../public/uploads/diagnostics/{$hospitalId}/{$targetPatientId}";
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                $newFilename = 'DIAG_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                $destPath    = $destDir . '/' . $newFilename;

                if (move_uploaded_file($uploadedTmp, $destPath)) {
                    $filePath = "uploads/diagnostics/{$hospitalId}/{$targetPatientId}/{$newFilename}";
                    $fileName = $origName;
                    $fileType = mime_content_type($destPath) ?: ('application/' . $fileExt);
                    $storageType = !empty($driveUrl) ? 'BOTH' : 'LOCAL_FILE';
                } else {
                    $error = 'Failed to save uploaded file to server storage.';
                }
            }
        }

        if (empty($error)) {
            // Generate unique UID
            $diagUid = 'DIAG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            $stmtIns = $db->prepare("
                INSERT INTO patient_diagnostics
                (diagnostic_uid, patient_id, hospital_id, ordered_by_doctor_id, diagnostic_category,
                 modality, title, body_part, clinical_indication, findings, impression,
                 test_date, report_date, reporting_specialist, status, storage_type,
                 file_path, file_name, file_type, file_size, drive_url, verified_by, verified_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $docIdVal = ($doctorAssigned > 0) ? $doctorAssigned : null;
            $stmtIns->execute([
                $diagUid, $targetPatientId, $hospitalId, $docIdVal, $category,
                $modality, $title, $bodyPart, $indication, $findings, $impression,
                $testDate, $reportDate, $specialist, $status, $storageType,
                $filePath, $fileName, $fileType, $fileSize, $driveUrl, $userId
            ]);

            AuditService::log('DIAGNOSTIC_UPLOAD', [
                'user_id'     => $userId,
                'hospital_id' => $hospitalId,
                'patient_id'  => $targetPatientId,
                'metadata'    => ['uid' => $diagUid, 'modality' => $modality, 'title' => $title, 'storage' => $storageType]
            ]);

            $success = "Diagnostic record <strong>{$diagUid}</strong> ({$title}) has been verified and assigned to the patient database successfully!";
        }
    }
}

// Filter parameters
$filterModality = trim($_GET['modality'] ?? 'all');
$filterStatus   = trim($_GET['status'] ?? 'all');
$searchQ        = trim($_GET['q'] ?? '');

$where = ["pd.hospital_id = ?"];
$params = [$hospitalId];

if ($filterModality !== 'all') {
    $where[] = "pd.modality = ?";
    $params[] = $filterModality;
}
if ($filterStatus !== 'all') {
    $where[] = "pd.status = ?";
    $params[] = $filterStatus;
}
if (!empty($searchQ)) {
    $where[] = "(p.full_name LIKE ? OR p.patient_uid LIKE ? OR pd.title LIKE ? OR pd.body_part LIKE ? OR pd.diagnostic_uid LIKE ?)";
    $like = "%{$searchQ}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$whereClause = implode(' AND ', $where);

// Fetch diagnostic records
$stmtList = $db->prepare("
    SELECT pd.*,
           p.full_name AS patient_name, p.patient_uid, p.gender AS patient_gender, p.date_of_birth,
           d.full_name AS doctor_name, d.specialization AS doctor_spec
    FROM patient_diagnostics pd
    JOIN patients p ON pd.patient_id = p.id
    LEFT JOIN doctors d ON pd.ordered_by_doctor_id = d.id
    WHERE {$whereClause}
    ORDER BY pd.created_at DESC
");
$stmtList->execute($params);
$diagnostics = $stmtList->fetchAll();

// Statistics counts
$stmtStats = $db->prepare("
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN modality = 'XRAY' THEN 1 ELSE 0 END) AS xray_count,
        SUM(CASE WHEN modality IN ('MRI','CT_SCAN') THEN 1 ELSE 0 END) AS mri_ct_count,
        SUM(CASE WHEN modality = 'ULTRASOUND' THEN 1 ELSE 0 END) AS usg_count,
        SUM(CASE WHEN status = 'VERIFIED' THEN 1 ELSE 0 END) AS verified_count
    FROM patient_diagnostics
    WHERE hospital_id = ?
");
$stmtStats->execute([$hospitalId]);
$statData = $stmtStats->fetch();

// Fetch affiliated doctors for dropdown
$stmtDocs = $db->prepare("
    SELECT d.id, d.full_name, d.specialization, dh.designation
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    WHERE dh.hospital_id = ? AND dh.status = 'APPROVED'
    ORDER BY d.full_name ASC
");
$stmtDocs->execute([$hospitalId]);
$affiliatedDoctors = $stmtDocs->fetchAll();

// Fetch patients list for easy picker
$patientsList = $db->query("SELECT id, patient_uid, full_name, date_of_birth, gender FROM patients ORDER BY full_name ASC LIMIT 50")->fetchAll();

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Diagnostic Imaging & Lab Portal — <?= htmlspecialchars($hospital['name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .modality-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 999px;
      text-transform: uppercase;
    }
    .modality-XRAY { background: #E0E7FF; color: #3730A3; border: 1px solid #C7D2FE; }
    .modality-MRI { background: #EDE9FE; color: #5B21B6; border: 1px solid #DDD6FE; }
    .modality-CT_SCAN { background: #CFFAFE; color: #155E75; border: 1px solid #A5F3FC; }
    .modality-ULTRASOUND { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .modality-BLOOD_TEST { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .modality-ECG { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .modality-OTHER { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }

    .modal-backdrop-custom {
      display: none;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(4px);
      z-index: 1050;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-backdrop-custom.active {
      display: flex;
    }
    .modal-card-custom {
      background: #fff;
      border-radius: 16px;
      width: 100%;
      max-width: 760px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    }
  </style>
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
      <a href="<?= APP_URL ?>/hospital/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i>
        <span>Affiliated Doctors</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/diagnostics.php" class="nav-item active">
        <i class="bi bi-file-earmark-medical-fill"></i>
        <span>Diagnostics & Imaging</span>
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

  <!-- MAIN CONTENT -->
  <main class="portal-content">
    <header class="top-nav">
      <div class="top-nav-left">
        <div>
          <span class="eyebrow" style="color: var(--mc-teal-dark); font-size: 11px; font-weight: 700;">RADIOLOGY & DIAGNOSTIC SERVICES</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Diagnostics & Imaging Center</h1>
        </div>
      </div>
      <div class="top-nav-right">
        <button type="button" class="btn btn-primary btn-sm" style="background: var(--mc-teal); border-color: var(--mc-teal);" onclick="openAddDiagModal()">
          <i class="bi bi-cloud-arrow-up-fill"></i> Add Imaging / Lab Scan
        </button>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <?php if ($success): ?>
      <div class="alert alert-success mb-4" data-auto-dismiss="7000">
        <i class="bi bi-check-circle-fill"></i> <?= $success ?>
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="alert alert-error mb-4" data-auto-dismiss="9000">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <!-- ECOSYSTEM NOTICE BOX -->
      <div style="background: linear-gradient(135deg, #F0FDF4, #EFF6FF); border: 1.5px solid #86EFAC; border-radius: var(--radius-lg); padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div style="width: 40px; height: 40px; border-radius: 10px; background: #DCFCE7; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #166534;">
            <i class="bi bi-hospital"></i>
          </div>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #166534;">Hospital Diagnostic Assignment & Patient EMR Integration</div>
            <div style="font-size: 12px; color: #15803D;">
              All X-Rays, MRIs, CT Scans, and pathology lab reports uploaded here are cryptographically linked to the patient's verified central profile. Supports high-res image uploads, PDFs, and Google Drive links.
            </div>
          </div>
        </div>
        <span class="badge badge-success" style="font-size: 11px;">
          <i class="bi bi-shield-check"></i> EMR Synced
        </span>
      </div>

      <!-- STATS BAR -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 600; color: var(--mc-text-muted); text-transform: uppercase;">Total Reports Issued</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: #0F766E; margin-top: 4px;"><?= (int)($statData['total_count'] ?? 0) ?></div>
        </div>
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 600; color: var(--mc-text-muted); text-transform: uppercase;">X-Ray Scans</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: #3730A3; margin-top: 4px;"><?= (int)($statData['xray_count'] ?? 0) ?></div>
        </div>
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 600; color: var(--mc-text-muted); text-transform: uppercase;">MRI & CT Scans</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: #5B21B6; margin-top: 4px;"><?= (int)($statData['mri_ct_count'] ?? 0) ?></div>
        </div>
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 600; color: var(--mc-text-muted); text-transform: uppercase;">Ultrasound & Lab</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: #166534; margin-top: 4px;"><?= (int)($statData['usg_count'] ?? 0) ?></div>
        </div>
      </div>

      <!-- FILTER & SEARCH BAR -->
      <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border); margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
          <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
            <div class="search-bar" style="min-width: 260px; max-width: 360px;">
              <i class="bi bi-search"></i>
              <input type="text" name="q" placeholder="Search patient, test title, UID..." value="<?= htmlspecialchars($searchQ) ?>">
            </div>

            <select name="modality" class="form-control" style="width: auto; font-size: 13px;">
              <option value="all">All Modalities</option>
              <option value="XRAY" <?= $filterModality === 'XRAY' ? 'selected' : '' ?>>X-Ray</option>
              <option value="MRI" <?= $filterModality === 'MRI' ? 'selected' : '' ?>>MRI</option>
              <option value="CT_SCAN" <?= $filterModality === 'CT_SCAN' ? 'selected' : '' ?>>CT Scan</option>
              <option value="ULTRASOUND" <?= $filterModality === 'ULTRASOUND' ? 'selected' : '' ?>>Ultrasound</option>
              <option value="BLOOD_TEST" <?= $filterModality === 'BLOOD_TEST' ? 'selected' : '' ?>>Blood Test</option>
              <option value="ECG" <?= $filterModality === 'ECG' ? 'selected' : '' ?>>ECG</option>
              <option value="OTHER" <?= $filterModality === 'OTHER' ? 'selected' : '' ?>>Other Modalities</option>
            </select>

            <select name="status" class="form-control" style="width: auto; font-size: 13px;">
              <option value="all">All Statuses</option>
              <option value="VERIFIED" <?= $filterStatus === 'VERIFIED' ? 'selected' : '' ?>>Verified</option>
              <option value="COMPLETED" <?= $filterStatus === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
              <option value="ORDERED" <?= $filterStatus === 'ORDERED' ? 'selected' : '' ?>>Ordered</option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 14px;">Filter</button>
            <?php if ($searchQ || $filterModality !== 'all' || $filterStatus !== 'all'): ?>
              <a href="<?= APP_URL ?>/hospital/diagnostics.php" class="btn btn-ghost btn-sm" style="font-size: 12px;">Clear</a>
            <?php endif; ?>
          </div>

          <button type="button" class="btn btn-primary btn-sm" style="background: var(--mc-teal); border-color: var(--mc-teal);" onclick="openAddDiagModal()">
            <i class="bi bi-plus-lg"></i> Add New Record
          </button>
        </form>
      </div>

      <!-- DIAGNOSTIC RECORDS TABLE -->
      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Diagnostic UID & Date</th>
                <th>Patient</th>
                <th>Modality</th>
                <th>Test Title & Body Part</th>
                <th>Ordered By</th>
                <th>Findings / Impression</th>
                <th>File / Drive Link</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($diagnostics)): ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 40px 20px; color: var(--mc-text-muted);">
                  <div style="font-size: 32px; margin-bottom: 8px;"><i class="bi bi-file-earmark-medical"></i></div>
                  <div style="font-weight: 700; font-size: 15px;">No diagnostic records found</div>
                  <div style="font-size: 13px;">
                    <?= $searchQ ? 'No records match your filter criteria.' : 'Click "Add Imaging / Lab Scan" above to assign diagnostic tests, X-Rays, or MRIs to patients.' ?>
                  </div>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($diagnostics as $diag): ?>
                <tr>
                  <td>
                    <div style="font-weight: 700; font-family: monospace; color: #0F766E;"><?= htmlspecialchars($diag['diagnostic_uid']) ?></div>
                    <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 2px;">
                      <?= date('M j, Y', strtotime($diag['test_date'] ?: $diag['created_at'])) ?>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight: 700; color: #0F172A;"><?= htmlspecialchars($diag['patient_name']) ?></div>
                    <div style="font-size: 11px; color: var(--mc-text-muted);">
                      <span style="font-family: monospace;"><?= htmlspecialchars($diag['patient_uid']) ?></span>
                      <?php if ($diag['patient_gender']): ?> · <?= htmlspecialchars($diag['patient_gender']) ?><?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <span class="modality-pill modality-<?= htmlspecialchars($diag['modality']) ?>">
                      <i class="bi bi-disc"></i> <?= htmlspecialchars(str_replace('_', ' ', $diag['modality'])) ?>
                    </span>
                  </td>
                  <td>
                    <div style="font-weight: 700;"><?= htmlspecialchars($diag['title']) ?></div>
                    <?php if ($diag['body_part']): ?>
                    <div style="font-size: 11px; color: var(--mc-text-muted);"><i class="bi bi-pin-map"></i> <?= htmlspecialchars($diag['body_part']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($diag['doctor_name']): ?>
                      <div style="font-weight: 600; font-size: 12px;">Dr. <?= htmlspecialchars($diag['doctor_name']) ?></div>
                      <div style="font-size: 11px; color: var(--mc-text-muted);"><?= htmlspecialchars($diag['doctor_spec'] ?? 'Consultant') ?></div>
                    <?php else: ?>
                      <span style="color: var(--mc-text-muted); font-size: 12px;">Hospital Radiology Staff</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="max-width: 220px; font-size: 12px; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                      <?= htmlspecialchars($diag['impression'] ?: $diag['findings'] ?: 'Recorded in patient profile') ?>
                    </div>
                    <?php if ($diag['reporting_specialist']): ?>
                    <div style="font-size: 10px; color: var(--mc-text-muted); margin-top: 2px;">
                      By <?= htmlspecialchars($diag['reporting_specialist']) ?>
                    </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                      <?php if ($diag['file_path']): ?>
                        <a href="<?= APP_URL ?>/<?= htmlspecialchars($diag['file_path']) ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 3px 8px; font-size: 11px;">
                          <i class="bi bi-file-earmark-arrow-down"></i> File
                        </a>
                      <?php endif; ?>
                      <?php if ($diag['drive_url']): ?>
                        <a href="<?= htmlspecialchars($diag['drive_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary" style="padding: 3px 8px; font-size: 11px; background: #1A73E8; border-color: #1A73E8;">
                          <i class="bi bi-google"></i> Drive Link
                        </a>
                      <?php endif; ?>
                      <?php if (!$diag['file_path'] && !$diag['drive_url']): ?>
                        <span style="color: var(--mc-text-muted); font-size: 11px;">Text Record</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <?php if ($diag['status'] === 'VERIFIED'): ?>
                      <span class="badge badge-verified" style="font-size: 11px;"><i class="bi bi-check-circle-fill"></i> Verified</span>
                    <?php elseif ($diag['status'] === 'COMPLETED'): ?>
                      <span class="badge" style="background:#E0F2FE;color:#0369A1;border:1px solid #BAE6FD;font-size:11px;"><i class="bi bi-check2"></i> Completed</span>
                    <?php else: ?>
                      <span class="badge badge-pending" style="font-size: 11px;"><?= htmlspecialchars($diag['status']) ?></span>
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

<!-- ============================================================
     MODAL: ADD DIAGNOSTIC / IMAGING RECORD
     ============================================================ -->
<div class="modal-backdrop-custom" id="addDiagModal">
  <div class="modal-card-custom">
    <div style="padding: 20px 24px; border-bottom: 1px solid var(--mc-border); display: flex; align-items: center; justify-content: space-between; background: #F8FAFC; border-top-left-radius: 16px; border-top-right-radius: 16px;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="width: 36px; height: 36px; border-radius: 9px; background: #E0F2FE; color: #0284C7; display: flex; align-items: center; justify-content: center; font-size: 18px;">
          <i class="bi bi-cloud-arrow-up-fill"></i>
        </div>
        <div>
          <h2 style="font-size: 1.1rem; font-weight: 800; color: #0F172A; margin: 0;">Add Diagnostic / Imaging Record</h2>
          <div style="font-size: 12px; color: var(--mc-text-muted);">Assign X-Ray, MRI, CT, or Lab tests directly to patient EMR</div>
        </div>
      </div>
      <button type="button" onclick="closeAddDiagModal()" style="background: none; border: none; font-size: 20px; color: var(--mc-text-muted); cursor: pointer;">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <form method="POST" action="" enctype="multipart/form-data" style="padding: 24px;">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="add_diagnostic">

      <!-- Patient Selection -->
      <div style="margin-bottom: 16px;">
        <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">
          Assign to Patient <span style="color: red;">*</span>
        </label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
          <div>
            <input type="text" name="patient_id_or_uid" id="patientUidInput" class="form-control" placeholder="Enter Patient UID (e.g. PT-000001)" required style="font-size: 13px;">
          </div>
          <div>
            <select class="form-control" onchange="document.getElementById('patientUidInput').value = this.value;" style="font-size: 13px;">
              <option value="">-- Quick Pick Registered Patient --</option>
              <?php foreach ($patientsList as $p): ?>
                <option value="<?= htmlspecialchars($p['patient_uid']) ?>">
                  <?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_uid']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 4px;">
          The report will be automatically synced with the patient's centralized MedCore medical history.
        </div>
      </div>

      <!-- Test Title & Modality -->
      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">
            Test / Scan Title <span style="color: red;">*</span>
          </label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Chest X-Ray PA View, Brain MRI" required style="font-size: 13px;">
        </div>
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Modality</label>
          <select name="modality" class="form-control" style="font-size: 13px;">
            <option value="XRAY">X-Ray (Digital)</option>
            <option value="MRI">MRI Scan</option>
            <option value="CT_SCAN">CT Scan</option>
            <option value="ULTRASOUND">Ultrasound (USG)</option>
            <option value="ECG">ECG / EKG</option>
            <option value="ECHOCARDIOGRAM">Echocardiogram</option>
            <option value="BLOOD_TEST">Blood Test (Pathology)</option>
            <option value="URINE_TEST">Urine Analysis</option>
            <option value="BIOPSY">Biopsy / Histopathology</option>
            <option value="ENDOSCOPY">Endoscopy</option>
            <option value="OTHER">Other</option>
          </select>
        </div>
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Category</label>
          <select name="diagnostic_category" class="form-control" style="font-size: 13px;">
            <option value="RADIOLOGY">Radiology</option>
            <option value="PATHOLOGY">Pathology</option>
            <option value="CARDIOLOGY">Cardiology</option>
            <option value="OTHER">Other</option>
          </select>
        </div>
      </div>

      <!-- Body Part & Ordered by Doctor -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Body Part / Anatomical Region</label>
          <input type="text" name="body_part" class="form-control" placeholder="e.g. Chest, Brain, Abdomen, Knee" style="font-size: 13px;">
        </div>
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Referring / Ordering Doctor</label>
          <select name="ordered_by_doctor_id" class="form-control" style="font-size: 13px;">
            <option value="0">-- In-House / Radiology Team --</option>
            <?php foreach ($affiliatedDoctors as $ad): ?>
              <option value="<?= $ad['id'] ?>">Dr. <?= htmlspecialchars($ad['full_name']) ?> (<?= htmlspecialchars($ad['specialization'] ?? 'Doctor') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Clinical Indication -->
      <div style="margin-bottom: 16px;">
        <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Clinical Indication / Symptoms</label>
        <input type="text" name="clinical_indication" class="form-control" placeholder="e.g. Persistent cough, suspected fracture, rule out intracranial lesion" style="font-size: 13px;">
      </div>

      <!-- Findings & Impression -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Radiological / Lab Findings</label>
          <textarea name="findings" class="form-control" rows="3" placeholder="Detailed radiologist findings..." style="font-size: 12px;"></textarea>
        </div>
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Impression / Conclusion</label>
          <textarea name="impression" class="form-control" rows="3" placeholder="Final conclusion / diagnostic impression..." style="font-size: 12px;"></textarea>
        </div>
      </div>

      <!-- UPLOAD OPTIONS: FILE AND GOOGLE DRIVE -->
      <div style="background: #F8FAFC; border: 1.5px dashed #CBD5E1; border-radius: 12px; padding: 18px; margin-bottom: 18px;">
        <div style="font-weight: 700; font-size: 13px; color: #0F172A; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
          <i class="bi bi-paperclip" style="font-size: 16px; color: #0284C7;"></i>
          Document & Imaging Upload Options (Local File and/or Cloud Link)
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <!-- Option A: File Upload -->
          <div style="background: #fff; border: 1px solid var(--mc-border); border-radius: 8px; padding: 12px;">
            <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 4px;">
              <i class="bi bi-file-earmark-arrow-up"></i> Upload File (Image / PDF / DICOM)
            </label>
            <input type="file" name="diagnostic_file" class="form-control" style="font-size: 12px; padding: 6px;" accept=".jpg,.jpeg,.png,.webp,.pdf,.dcm,.dicom">
            <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 4px;">Max 35MB. Supports high-res X-Ray/MRI images, scans, and PDFs.</div>
          </div>

          <!-- Option B: Cloud / Drive Link -->
          <div style="background: #fff; border: 1px solid var(--mc-border); border-radius: 8px; padding: 12px;">
            <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 4px;">
              <i class="bi bi-google"></i> Google Drive / Cloud URL
            </label>
            <input type="url" name="drive_url" class="form-control" placeholder="https://drive.google.com/file/d/..." style="font-size: 12px;">
            <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 4px;">Direct viewable link from Drive, Dropbox, or Hospital PACS.</div>
          </div>
        </div>
      </div>

      <!-- Reporting Specialist & Date & Status -->
      <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; margin-bottom: 20px;">
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Reporting Radiologist / Pathologist</label>
          <input type="text" name="reporting_specialist" class="form-control" placeholder="e.g. Dr. A. Rahman, MD Radiologist" style="font-size: 13px;">
        </div>
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Test Date</label>
          <input type="date" name="test_date" class="form-control" value="<?= date('Y-m-d') ?>" style="font-size: 13px;">
        </div>
        <div>
          <label style="font-size: 12px; font-weight: 700; color: #1E293B; display: block; margin-bottom: 6px;">Status</label>
          <select name="status" class="form-control" style="font-size: 13px;">
            <option value="VERIFIED">Verified Official</option>
            <option value="COMPLETED">Completed</option>
            <option value="ORDERED">Ordered / Pending Scan</option>
          </select>
        </div>
      </div>

      <!-- Action buttons -->
      <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid var(--mc-border); padding-top: 16px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddDiagModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background: var(--mc-teal); border-color: var(--mc-teal); font-weight: 700; padding: 10px 24px;">
          <i class="bi bi-check2-circle"></i> Save & Assign to Patient EMR
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddDiagModal() {
  document.getElementById('addDiagModal').classList.add('active');
}
function closeAddDiagModal() {
  document.getElementById('addDiagModal').classList.remove('active');
}
document.getElementById('addDiagModal').addEventListener('click', function(e) {
  if (e.target === this) closeAddDiagModal();
});
</script>

</body>
</html>
