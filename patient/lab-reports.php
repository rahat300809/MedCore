<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Handle lab report upload
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

        $success = 'Diagnostic lab report uploaded successfully.';
    }
}

// Fetch all lab reports
$stmt = $db->prepare("
    SELECT * FROM lab_reports
    WHERE patient_id = ?
    ORDER BY test_date DESC, id DESC
");
$stmt->execute([$patientId]);
$reports = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lab Reports — MedCore Patient Portal</title>
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
      <a href="<?= APP_URL ?>/patient/prescriptions.php" class="nav-item">
        <i class="bi bi-prescription2"></i>
        <span>Prescriptions</span>
      </a>
      <a href="<?= APP_URL ?>/patient/medical-history.php" class="nav-item">
        <i class="bi bi-heart-pulse-fill"></i>
        <span>Medical History</span>
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
        <i class="bi bi-file-earmark-medical"></i>
        <span>Lab Reports</span>
      </a>

      <div class="nav-section-title">Privacy &amp; Security</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php" class="nav-item">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Consent Requests</span>
      </a>
      <a href="<?= APP_URL ?>/patient/access-history.php" class="nav-item">
        <i class="bi bi-clock-history"></i>
        <span>Access History</span>
      </a>
      <a href="<?= APP_URL ?>/patient/profile.php" class="nav-item">
        <i class="bi bi-person-circle"></i>
        <span>My Profile</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'P', 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Patient') ?></div>
          <div class="user-role">Verified Patient</div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-outline btn-sm" style="width: 100%; margin-top: var(--space-3);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-header">
      <div class="d-flex align-items-center gap-3">
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Diagnostic Lab Reports</h1>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('upload-modal').style.display='block'">
          <i class="bi bi-cloud-arrow-up-fill"></i> Upload New Report
        </button>
      </div>
    </header>

    <div class="portal-body">
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- Upload Modal/Card -->
      <div id="upload-modal" class="card mb-4" style="display: none; border: 2px solid var(--mc-blue);">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-cloud-upload"></i> Upload Diagnostic Test / Lab Report</h3>
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

      <!-- Lab Reports List -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-file-earmark-medical"></i> Diagnostic Reports Log (<?= count($reports) ?>)</h3>
        </div>
        <div class="card-body">
          <?php if (empty($reports)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-file-earmark-ruled" style="font-size: 3rem; color: #CBD5E1;"></i>
              <p class="mt-2">No diagnostic lab reports uploaded yet.</p>
              <button class="btn btn-primary btn-sm mt-2" onclick="document.getElementById('upload-modal').style.display='block'">
                <i class="bi bi-cloud-arrow-up"></i> Upload Your First Report
              </button>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Report / Test Name</th>
                    <th>Date</th>
                    <th>Laboratory Name</th>
                    <th>Results Summary</th>
                    <th>Verification</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($reports as $r): ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($r['report_name']) ?></strong>
                        <?php if ($r['file_path']): ?>
                          <span class="badge badge-info ms-1"><i class="bi bi-paperclip"></i> Attached</span>
                        <?php endif; ?>
                      </td>
                      <td><?= $r['test_date'] ? date('d M Y', strtotime($r['test_date'])) : '—' ?></td>
                      <td><?= htmlspecialchars($r['laboratory_name'] ?? 'Diagnostic Centre') ?></td>
                      <td><?= htmlspecialchars($r['result_summary'] ?? 'Diagnostic result on file') ?></td>
                      <td>
                        <?php if ($r['verified']): ?>
                          <span class="badge badge-success"><i class="bi bi-check-circle"></i> Clinically Verified</span>
                        <?php else: ?>
                          <span class="badge badge-secondary">Patient Uploaded</span>
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
  </main>
</div>

</body>
</html>
