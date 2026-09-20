<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Handle lab report upload by patient
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_report'])) {
    validateCsrf();

    $reportName     = trim($_POST['report_name'] ?? '');
    $laboratoryName = trim($_POST['laboratory_name'] ?? '');
    $testDate       = trim($_POST['test_date'] ?? date('Y-m-d'));
    $summary        = trim($_POST['result_summary'] ?? '');

    if (empty($reportName)) {
        $error = 'Please provide the report or test name.';
    } else {
        $filePath = null;
        if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['report_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ALLOWED_DOC_EXTENSIONS)) {
                $dir = UPLOAD_PATH . '/lab-reports';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = 'lab_' . $patientId . '_' . time() . '.' . $ext;
                $target = $dir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $filePath = 'lab-reports/' . $filename;
                }
            }
        }

        $stmt = $db->prepare("
            INSERT INTO lab_reports
                (patient_id, report_name, test_date, laboratory_name, result_summary, file_path, uploaded_by, verified)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $patientId, $reportName, $testDate, $laboratoryName, $summary, $filePath, $_SESSION['user_id']
        ]);

        AuditService::log('UPLOAD_LAB_REPORT', [
            'user_id'    => $_SESSION['user_id'],
            'patient_id' => $patientId,
            'metadata'   => ['report_name' => $reportName, 'laboratory' => $laboratoryName],
        ]);

        $success = 'Diagnostic report submitted successfully.';
    }
}

// Fetch official hospital-assigned diagnostics & imaging
$stmtDiag = $db->prepare("
    SELECT pd.*, h.name AS hospital_name, h.type AS hospital_type,
           d.full_name AS doctor_name, d.specialization AS doctor_spec
    FROM patient_diagnostics pd
    JOIN hospitals h ON pd.hospital_id = h.id
    LEFT JOIN doctors d ON pd.ordered_by_doctor_id = d.id
    WHERE pd.patient_id = ?
    ORDER BY pd.test_date DESC, pd.id DESC
");
$stmtDiag->execute([$patientId]);
$diagnostics = $stmtDiag->fetchAll();

// Fetch self-uploaded or legacy lab reports
$stmtLab = $db->prepare("
    SELECT * FROM lab_reports
    WHERE patient_id = ?
    ORDER BY test_date DESC, id DESC
");
$stmtLab->execute([$patientId]);
$legacyReports = $stmtLab->fetchAll();

// Fetch token-tracked prescription lab orders and their lab test results
$stmtLabOrders = $db->prepare("
    SELECT plo.*,
           h.name AS hospital_name, h.city AS hospital_city,
           d.full_name AS doctor_name, d.specialization AS doctor_spec,
           l.name AS assigned_lab_name, l.lab_uid AS assigned_lab_uid,
           rx.prescription_uid
    FROM prescription_lab_orders plo
    JOIN hospitals h ON plo.hospital_id = h.id
    JOIN doctors d ON plo.doctor_id = d.id
    LEFT JOIN laboratories l ON plo.laboratory_id = l.id
    LEFT JOIN prescriptions rx ON plo.prescription_id = rx.id
    WHERE plo.patient_id = ?
    ORDER BY plo.created_at DESC
");
$stmtLabOrders->execute([$patientId]);
$prescriptionLabOrders = $stmtLabOrders->fetchAll();

$labOrdersWithDetails = [];
$completedOrdersCount = 0;
foreach ($prescriptionLabOrders as $ord) {
    $stmtTests = $db->prepare("
        SELECT lot.*,
               ltr.id AS result_id, ltr.result_summary, ltr.result_value, ltr.reference_range,
               ltr.result_status, ltr.technician_name, ltr.uploaded_at AS result_uploaded_at,
               res_lab.name AS result_lab_name, res_lab.lab_uid AS result_lab_uid,
               res_hosp.name AS result_hospital_name
        FROM lab_order_tests lot
        LEFT JOIN lab_test_results ltr ON ltr.lab_order_test_id = lot.id
        LEFT JOIN laboratories res_lab ON ltr.laboratory_id = res_lab.id
        LEFT JOIN hospitals res_hosp ON ltr.hospital_id = res_hosp.id
        WHERE lot.lab_order_id = ?
        ORDER BY lot.id ASC
    ");
    $stmtTests->execute([$ord['id']]);
    $tests = $stmtTests->fetchAll();

    foreach ($tests as &$t) {
        $t['files'] = [];
        if (!empty($t['result_id'])) {
            $stmtFiles = $db->prepare("SELECT * FROM lab_result_files WHERE lab_test_result_id = ? ORDER BY id ASC");
            $stmtFiles->execute([$t['result_id']]);
            $t['files'] = $stmtFiles->fetchAll();
        }
    }
    unset($t);

    $ord['tests'] = $tests;
    if ($ord['status'] === 'COMPLETED') $completedOrdersCount++;
    $labOrdersWithDetails[] = $ord;
}

$ordersCount  = count($labOrdersWithDetails);
$totalCount   = count($diagnostics) + count($legacyReports) + $ordersCount;
$imagingCount = 0;
$pathCount    = count($legacyReports);

foreach ($diagnostics as $d) {
    if (in_array($d['modality'], ['XRAY','MRI','CT_SCAN','ULTRASOUND','ECG','ECHOCARDIOGRAM','ENDOSCOPY'])) {
        $imagingCount++;
    } else {
        $pathCount++;
    }
}

$activeTab = $_GET['tab'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Diagnostic Imaging & Lab Reports — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .tab-btn {
      padding: 8px 16px;
      font-size: 13px;
      font-weight: 600;
      border-radius: 8px;
      border: 1px solid transparent;
      background: transparent;
      color: var(--mc-text-secondary);
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .tab-btn.active {
      background: #EFF6FF;
      color: #1E40AF;
      border-color: #BFDBFE;
      font-weight: 700;
    }
    .diag-card {
      border: 1px solid var(--mc-border);
      border-radius: 12px;
      background: #fff;
      padding: 18px;
      transition: box-shadow 0.2s;
    }
    .diag-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .modality-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
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
    .token-order-card {
      border: 1.5px solid #818CF8;
      border-radius: 14px;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(99,102,241,0.06);
      transition: box-shadow 0.2s;
    }
    .token-order-card:hover {
      box-shadow: 0 8px 25px rgba(99,102,241,0.12);
    }
    .token-header {
      background: linear-gradient(135deg, #4338CA, #6366F1);
      color: #fff;
      padding: 14px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }
    .token-pill {
      font-family: 'JetBrains Mono', monospace;
      font-size: 15px;
      font-weight: 800;
      background: rgba(255,255,255,0.22);
      padding: 3px 10px;
      border-radius: 6px;
      letter-spacing: 1px;
    }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Patient Portal</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">My Health Record</div>
      <a href="<?= APP_URL ?>/patient/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/patient/medical-history.php" class="nav-item">
        <i class="bi bi-clock-history"></i>
        <span>Medical History</span>
      </a>
      <a href="<?= APP_URL ?>/patient/prescriptions.php" class="nav-item">
        <i class="bi bi-file-earmark-medical-fill"></i>
        <span>Prescriptions</span>
      </a>
      <a href="<?= APP_URL ?>/patient/medications.php" class="nav-item">
        <i class="bi bi-capsule-pill"></i>
        <span>Medications</span>
      </a>
      <a href="<?= APP_URL ?>/patient/allergies.php" class="nav-item">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span>Allergies</span>
      </a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php" class="nav-item active">
        <i class="bi bi-file-earmark-ruled-fill"></i>
        <span>Diagnostics &amp; Imaging</span>
      </a>
      <div class="nav-section-title">Privacy &amp; Security</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php" class="nav-item">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Access Requests</span>
      </a>
      <a href="<?= APP_URL ?>/patient/access-history.php" class="nav-item">
        <i class="bi bi-eye-fill"></i>
        <span>Access History</span>
      </a>
      <div class="nav-section-title">Account</div>
      <a href="<?= APP_URL ?>/patient/profile.php" class="nav-item">
        <i class="bi bi-person-fill"></i>
        <span>My Profile</span>
      </a>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="nav-item text-danger" style="margin-top: var(--space-4);">
        <i class="bi bi-box-arrow-right"></i>
        <span>Sign Out</span>
      </a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-header">
      <div>
        <span class="eyebrow" style="color: var(--mc-blue); font-size: 11px; font-weight: 700;">CENTRALIZED MEDICAL RECORD</span>
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Diagnostic Imaging &amp; Lab Reports</h1>
      </div>
      <div class="header-actions">
        <button class="btn btn-outline btn-sm" onclick="document.getElementById('upload-modal').style.display='block'">
          <i class="bi bi-cloud-arrow-up"></i> Self Upload Report
        </button>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- ECOSYSTEM NOTICE -->
      <div style="background: linear-gradient(135deg, #F0FDF4, #EFF6FF); border: 1.5px solid #86EFAC; border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <i class="bi bi-shield-check" style="font-size: 26px; color: #166534;"></i>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #166534;">Hospital Verified Medical Imaging & Diagnostics</div>
            <div style="font-size: 12px; color: #15803D;">
              Official X-Rays, MRIs, and CT Scans assigned by verified hospitals appear here directly with specialist radiologist findings, downloadable scans, and Google Drive links.
            </div>
          </div>
        </div>
        <span class="badge badge-success" style="font-size: 11px;"><i class="bi bi-lock-fill"></i> Encrypted EMR</span>
      </div>

      <!-- TABS BAR -->
      <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--mc-border); padding-bottom: 12px; flex-wrap: wrap;">
        <a href="?tab=all" class="tab-btn <?= $activeTab === 'all' ? 'active' : '' ?>">
          <i class="bi bi-collection-fill"></i> All Records (<?= $totalCount ?>)
        </a>
        <a href="?tab=orders" class="tab-btn <?= $activeTab === 'orders' ? 'active' : '' ?>" style="<?= $ordersCount > 0 ? 'color: #4338CA;' : '' ?>">
          <i class="bi bi-flask-fill"></i> Prescription Lab Orders &amp; Results (<?= $ordersCount ?>)
        </a>
        <a href="?tab=imaging" class="tab-btn <?= $activeTab === 'imaging' ? 'active' : '' ?>">
          <i class="bi bi-disc-fill"></i> Radiology &amp; Imaging (<?= $imagingCount ?>)
        </a>
        <a href="?tab=pathology" class="tab-btn <?= $activeTab === 'pathology' ? 'active' : '' ?>">
          <i class="bi bi-droplet-half"></i> Pathology &amp; Lab (<?= $pathCount ?>)
        </a>
      </div>

      <!-- Upload Modal/Card -->
      <div id="upload-modal" class="card mb-4" style="display: none; border: 2px solid var(--mc-blue);">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-cloud-upload"></i> Self-Upload Diagnostic Test / Lab Report</h3>
          <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('upload-modal').style.display='none'">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="upload_report" value="1">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Report / Test Name <span class="text-danger">*</span></label>
                <input type="text" name="report_name" class="form-control" placeholder="e.g. Complete Blood Count (CBC)" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Laboratory / Hospital Name</label>
                <input type="text" name="laboratory_name" class="form-control" placeholder="e.g. Popular Diagnostic Centre">
              </div>
              <div class="col-md-3">
                <label class="form-label">Test Date</label>
                <input type="date" name="test_date" class="form-control" value="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Key Findings / Result Summary</label>
                <textarea name="result_summary" rows="2" class="form-control" placeholder="e.g. Hemoglobin: 13.5 g/dL (Normal), WBC: 7,200 /mcL"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">Attach Report Document (PDF, JPG, PNG)</label>
                <input type="file" name="report_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
              </div>
              <div class="col-12 mt-3 text-end">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('upload-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save &amp; Upload Report</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- HOSPITAL DIAGNOSTICS & IMAGING LIST -->
      <div style="display: flex; flex-direction: column; gap: 18px; margin-bottom: 30px;">

        <!-- TOKEN-TRACKED PRESCRIPTION LAB ORDERS -->
        <?php if ($activeTab === 'all' || $activeTab === 'orders'): ?>
          <?php foreach ($labOrdersWithDetails as $lorder): $shownCount = ($shownCount ?? 0) + 1; ?>
          <div class="token-order-card">
            <div class="token-header">
              <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <span style="font-size: 22px;">🔬</span>
                <div>
                  <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; font-weight: 700;">Lab Order Token</div>
                  <span class="token-pill"><?= htmlspecialchars($lorder['lab_token']) ?></span>
                </div>
                <div style="font-size: 12px; opacity: 0.95;">
                  Ordered on <?= date('d M Y, h:i A', strtotime($lorder['created_at'])) ?>
                </div>
              </div>
              <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; <?= $lorder['status'] === 'COMPLETED' ? 'background:#DCFCE7; color:#166534;' : ($lorder['status'] === 'PARTIAL' ? 'background:#FEF3C7; color:#92400E;' : 'background:rgba(255,255,255,0.25); color:#fff;') ?>">
                  <i class="bi <?= $lorder['status'] === 'COMPLETED' ? 'bi-check-circle-fill' : 'bi-clock-fill' ?>"></i> <?= htmlspecialchars($lorder['status']) ?>
                </span>
                <?php if ($lorder['prescription_uid']): ?>
                <a href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= $lorder['prescription_id'] ?>" target="_blank" style="color: #fff; font-size: 11px; text-decoration: underline; opacity: 0.9;">
                  Rx #<?= htmlspecialchars($lorder['prescription_uid']) ?>
                </a>
                <?php endif; ?>
              </div>
            </div>

            <div style="padding: 18px 20px;">
              <!-- HOSPITAL & DOCTOR ORIGIN STRIP -->
              <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid #EEF2F6; margin-bottom: 14px; font-size: 12px; color: #475569;">
                <div>
                  <i class="bi bi-building text-primary"></i> Hospital: <strong style="color:#0F172A;"><?= htmlspecialchars($lorder['hospital_name']) ?></strong>
                  &nbsp;·&nbsp; Prescribed by: <strong style="color:#0F172A;">Dr. <?= htmlspecialchars($lorder['doctor_name']) ?></strong> (<?= htmlspecialchars($lorder['doctor_spec'] ?? 'Specialist') ?>)
                </div>
                <div style="font-size: 11px; color: #64748B;">
                  Token expires: <?= date('d M Y', strtotime($lorder['expires_at'])) ?>
                </div>
              </div>

              <?php if (!empty($lorder['test_notes'])): ?>
              <div style="font-size: 12px; background: #F8FAFC; border-left: 3px solid #6366F1; padding: 8px 12px; border-radius: 0 6px 6px 0; margin-bottom: 14px; color: #334155;">
                <strong>Doctor's Instructions for Lab:</strong> <?= htmlspecialchars($lorder['test_notes']) ?>
              </div>
              <?php endif; ?>

              <!-- TESTS & RESULTS LIST -->
              <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($lorder['tests'] as $test): ?>
                <div style="border: 1px solid <?= $test['status'] === 'COMPLETED' ? '#BBF7D0' : '#E2E8F0' ?>; background: <?= $test['status'] === 'COMPLETED' ? '#F0FDF4' : '#F8FAFC' ?>; border-radius: 10px; padding: 14px 16px;">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
                    <div>
                      <div style="font-weight: 800; font-size: 14px; color: #0F172A;">
                        <?= htmlspecialchars($test['test_name']) ?>
                        <?php if ($test['test_category']): ?>
                          <span style="font-size: 11px; font-weight: 600; color: #64748B; margin-left: 6px;">(<?= htmlspecialchars($test['test_category']) ?>)</span>
                        <?php endif; ?>
                        <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-left: 6px; <?= $test['urgency'] === 'STAT' ? 'background:#FEE2E2;color:#991B1B;' : ($test['urgency'] === 'URGENT' ? 'background:#FEF3C7;color:#92400E;' : 'background:#E0E7FF;color:#3730A3;') ?>">
                          <?= htmlspecialchars($test['urgency']) ?>
                        </span>
                      </div>
                    </div>
                    <div>
                      <?php if ($test['status'] === 'COMPLETED'): ?>
                        <span class="badge badge-success" style="font-size: 11px;"><i class="bi bi-check-circle-fill"></i> Test Completed</span>
                      <?php else: ?>
                        <span class="badge" style="background:#EEF2FF; color:#4338CA; font-size: 11px;"><i class="bi bi-hourglass-split"></i> Awaiting Sample / Testing</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php if ($test['status'] === 'COMPLETED'): ?>
                    <!-- LABORATORY & HOSPITAL ISSUANCE TRACKING -->
                    <div style="background: #fff; border: 1px solid #D1FAE5; border-radius: 8px; padding: 12px; margin-top: 8px;">
                      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #065F46; margin-bottom: 8px;">
                        <div>
                          <i class="bi bi-patch-check-fill" style="color: #10B981; font-size: 14px;"></i>
                          Issued by Laboratory: <strong><?= htmlspecialchars($test['result_lab_name'] ?: 'Affiliated Laboratory') ?></strong>
                          <?php if ($test['result_lab_uid']): ?>
                            (<code><?= htmlspecialchars($test['result_lab_uid']) ?></code>)
                          <?php endif; ?>
                          &nbsp;·&nbsp; Under Hospital: <strong><?= htmlspecialchars($test['result_hospital_name'] ?: $lorder['hospital_name']) ?></strong>
                        </div>
                        <?php if ($test['result_uploaded_at']): ?>
                        <div style="font-size: 11px; color: #64748B;">
                          <i class="bi bi-clock"></i> <?= date('d M Y, h:i A', strtotime($test['result_uploaded_at'])) ?>
                        </div>
                        <?php endif; ?>
                      </div>

                      <?php if ($test['result_value'] || $test['reference_range']): ?>
                      <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 8px; font-size: 12px;">
                        <?php if ($test['result_value']): ?>
                        <div>
                          <span style="color:#64748B;">Result Value:</span>
                          <strong style="color:#0F172A; font-size: 13px;"><?= htmlspecialchars($test['result_value']) ?></strong>
                        </div>
                        <?php endif; ?>
                        <?php if ($test['reference_range']): ?>
                        <div>
                          <span style="color:#64748B;">Reference Range:</span>
                          <span style="color:#334155; font-family: monospace;"><?= htmlspecialchars($test['reference_range']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($test['result_status']): ?>
                        <div>
                          <span style="font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; <?= $test['result_status'] === 'NORMAL' ? 'background:#DCFCE7;color:#166534;' : ($test['result_status'] === 'CRITICAL' ? 'background:#FEE2E2;color:#991B1B;' : 'background:#FEF3C7;color:#92400E;') ?>">
                            <?= htmlspecialchars($test['result_status']) ?>
                          </span>
                        </div>
                        <?php endif; ?>
                      </div>
                      <?php endif; ?>

                      <?php if ($test['result_summary']): ?>
                      <div style="font-size: 12px; color: #334155; margin-bottom: 8px; line-height: 1.5;">
                        <strong>Findings / Summary:</strong> <?= nl2br(htmlspecialchars($test['result_summary'])) ?>
                      </div>
                      <?php endif; ?>

                      <?php if ($test['technician_name']): ?>
                      <div style="font-size: 11px; color: #64748B; margin-bottom: 8px;">
                        <i class="bi bi-person-badge"></i> Certified Lab Technician: <?= htmlspecialchars($test['technician_name']) ?>
                      </div>
                      <?php endif; ?>

                      <!-- ATTACHED RESULT FILES -->
                      <?php if (!empty($test['files'])): ?>
                      <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #D1D5DB;">
                        <?php foreach ($test['files'] as $rf): ?>
                        <a href="<?= APP_URL ?>/uploads/<?= htmlspecialchars($rf['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="font-size: 11px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none;">
                          <i class="bi <?= $rf['file_type'] === 'PDF' ? 'bi-file-pdf-fill text-danger' : 'bi-file-image-fill text-primary' ?>"></i>
                          <span><?= htmlspecialchars($rf['original_name'] ?: 'Lab Result File') ?></span>
                          <?php if ($rf['file_size']): ?>
                            <span style="opacity: 0.7; font-size: 10px;">(<?= round($rf['file_size'] / 1024, 1) ?> KB)</span>
                          <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                      </div>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <!-- PENDING TEST CALLOUT -->
                    <div style="font-size: 12px; color: #6366F1; display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                      <i class="bi bi-info-circle"></i>
                      <span>Present Token <strong><?= htmlspecialchars($lorder['lab_token']) ?></strong> at any affiliated lab of <?= htmlspecialchars($lorder['hospital_name']) ?> to complete this test.</span>
                    </div>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php
        $shownCount = $shownCount ?? 0;
        foreach ($diagnostics as $diag):
          $isImaging = in_array($diag['modality'], ['XRAY','MRI','CT_SCAN','ULTRASOUND','ECG','ECHOCARDIOGRAM','ENDOSCOPY']);
          if ($activeTab === 'orders') continue;
          if ($activeTab === 'imaging' && !$isImaging) continue;
          if ($activeTab === 'pathology' && $isImaging) continue;
          $shownCount++;
        ?>
        <div class="diag-card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <span class="modality-pill modality-<?= htmlspecialchars($diag['modality']) ?>">
                <i class="bi bi-disc"></i> <?= htmlspecialchars(str_replace('_',' ',$diag['modality'])) ?>
              </span>
              <span style="font-family: monospace; font-size: 11px; font-weight: 700; color: #0F766E;">
                <?= htmlspecialchars($diag['diagnostic_uid']) ?>
              </span>
              <span style="font-size: 12px; color: var(--mc-text-muted);">
                <i class="bi bi-calendar3"></i> <?= date('d M Y', strtotime($diag['test_date'] ?: $diag['created_at'])) ?>
              </span>
            </div>
            <div>
              <?php if ($diag['status'] === 'VERIFIED'): ?>
                <span class="badge badge-success" style="font-size: 11px;"><i class="bi bi-patch-check-fill"></i> Verified by <?= htmlspecialchars($diag['hospital_name']) ?></span>
              <?php else: ?>
                <span class="badge badge-info" style="font-size: 11px;"><i class="bi bi-clock"></i> <?= htmlspecialchars($diag['status']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
            <div style="flex: 1; min-width: 260px;">
              <h3 style="font-size: 15px; font-weight: 800; color: #0F172A; margin: 0 0 6px 0;">
                <?= htmlspecialchars($diag['title']) ?>
              </h3>
              <div style="font-size: 12px; color: var(--mc-text-secondary); margin-bottom: 8px;">
                <i class="bi bi-building"></i> <?= htmlspecialchars($diag['hospital_name']) ?>
                <?php if ($diag['body_part']): ?>
                  &nbsp;·&nbsp; <i class="bi bi-pin-map"></i> Region: <strong><?= htmlspecialchars($diag['body_part']) ?></strong>
                <?php endif; ?>
                <?php if ($diag['doctor_name']): ?>
                  &nbsp;·&nbsp; Ordered by: Dr. <?= htmlspecialchars($diag['doctor_name']) ?>
                <?php endif; ?>
              </div>

              <?php if ($diag['clinical_indication']): ?>
              <div style="font-size: 12px; background: #F8FAFC; border-left: 3px solid #94A3B8; padding: 6px 10px; margin-bottom: 8px; color: #475569;">
                <strong>Clinical Indication:</strong> <?= htmlspecialchars($diag['clinical_indication']) ?>
              </div>
              <?php endif; ?>

              <?php if ($diag['findings']): ?>
              <div style="font-size: 12px; color: #334155; margin-bottom: 6px;">
                <strong>Findings:</strong> <?= htmlspecialchars($diag['findings']) ?>
              </div>
              <?php endif; ?>

              <?php if ($diag['impression']): ?>
              <div style="font-size: 12px; color: #0F766E; background: #F0FDFA; border: 1px solid #CCFBF1; border-radius: 6px; padding: 8px 12px; margin-bottom: 8px;">
                <strong>Impression:</strong> <?= htmlspecialchars($diag['impression']) ?>
              </div>
              <?php endif; ?>

              <?php if ($diag['reporting_specialist']): ?>
              <div style="font-size: 11px; color: var(--mc-text-muted);">
                <i class="bi bi-person-badge"></i> Reported by: <?= htmlspecialchars($diag['reporting_specialist']) ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- Action / View / Drive Buttons -->
            <div style="display: flex; flex-direction: column; gap: 8px; align-items: flex-end; flex-shrink: 0;">
              <?php if ($diag['file_path']): ?>
                <a href="<?= APP_URL ?>/<?= htmlspecialchars($diag['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary" style="font-size: 12px; font-weight: 700; white-space: nowrap;">
                  <i class="bi bi-file-earmark-arrow-down-fill"></i> View / Download Scan
                </a>
              <?php endif; ?>

              <?php if ($diag['drive_url']): ?>
                <a href="<?= htmlspecialchars($diag['drive_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-secondary" style="font-size: 12px; font-weight: 700; color: #1A73E8; border-color: #BFDBFE; background: #EFF6FF; white-space: nowrap;">
                  <i class="bi bi-google"></i> Open Cloud / Drive Viewer
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Legacy Reports (under All or Pathology) -->
        <?php if ($activeTab === 'all' || $activeTab === 'pathology'): ?>
          <?php foreach ($legacyReports as $r): $shownCount++; ?>
          <div class="diag-card" style="border-left: 4px solid #CBD5E1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="badge badge-secondary" style="font-size: 11px;">Patient Upload</span>
                <span style="font-size: 12px; color: var(--mc-text-muted);"><i class="bi bi-calendar3"></i> <?= $r['test_date'] ? date('d M Y', strtotime($r['test_date'])) : '—' ?></span>
              </div>
              <span class="badge <?= $r['verified'] ? 'badge-success' : 'badge-secondary' ?>">
                <?= $r['verified'] ? 'Clinically Verified' : 'Self Uploaded' ?>
              </span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
              <div>
                <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 4px 0;"><?= htmlspecialchars($r['report_name']) ?></h4>
                <div style="font-size: 12px; color: var(--mc-text-secondary); margin-bottom: 6px;">
                  Laboratory: <?= htmlspecialchars($r['laboratory_name'] ?? 'Diagnostic Centre') ?>
                </div>
                <div style="font-size: 12px; color: #334155;">
                  <?= htmlspecialchars($r['result_summary'] ?? 'Diagnostic report on file.') ?>
                </div>
              </div>
              <?php if ($r['file_path']): ?>
                <a href="<?= APP_URL ?>/uploads/<?= htmlspecialchars($r['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 12px; white-space: nowrap;">
                  <i class="bi bi-paperclip"></i> View File
                </a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($shownCount === 0): ?>
          <div class="card text-center py-5 text-muted" style="padding: 40px;">
            <i class="bi bi-file-earmark-medical" style="font-size: 3rem; color: #CBD5E1;"></i>
            <h3 style="font-size: 16px; font-weight: 700; margin: 12px 0 6px 0; color: #334155;">No diagnostic reports found in this category</h3>
            <p style="font-size: 13px; color: var(--mc-text-muted); max-width: 400px; margin: 0 auto 16px auto;">
              When your hospital uploads an X-Ray, MRI, or blood test, it will automatically appear here with verified specialist impressions.
            </p>
            <button class="btn btn-primary btn-sm" onclick="document.getElementById('upload-modal').style.display='block'">
              <i class="bi bi-cloud-arrow-up"></i> Upload Self Report
            </button>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

</body>
</html>
