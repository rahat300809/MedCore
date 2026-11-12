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

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalAccent = '#0b4f6c';
$portalUser = $hospital['name'] ?? 'Hospital';
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Diagnostics';
$portalTitle = 'Diagnostics & imaging';
$portalSubtitle = 'এক্স-রে, এমআরআই, সিটি স্ক্যান ও ল্যাব রিপোর্ট';
$portalNav = mc_hospital_nav('diagnostics');
$portalTopAction = '<button type="button" class="mc-btn mc-btn-primary mc-btn-sm" onclick="openAddDiagModal()"><i class="bi bi-cloud-arrow-up-fill"></i> Add imaging / scan</button>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($success): ?>
  <div class="mc-alert mc-alert-ok"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mc-alert mc-alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Total reports</div><div class="v"><?= (int)($statData['total_count'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">X-Ray scans</div><div class="v"><?= (int)($statData['xray_count'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">MRI &amp; CT</div><div class="v"><?= (int)($statData['mri_ct_count'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Ultrasound &amp; lab</div><div class="v"><?= (int)($statData['usg_count'] ?? 0) ?></div></div>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Filter diagnostic records</h2></div>
  <form method="GET" style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-items:end">
    <label class="mc-field" style="margin:0">
      <span>Search</span>
      <input type="text" name="q" placeholder="Patient, test title, UID..." value="<?= htmlspecialchars($searchQ) ?>">
    </label>
    <label class="mc-field" style="margin:0">
      <span>Modality</span>
      <select name="modality">
        <option value="all">All modalities</option>
        <option value="XRAY" <?= $filterModality === 'XRAY' ? 'selected' : '' ?>>X-Ray</option>
        <option value="MRI" <?= $filterModality === 'MRI' ? 'selected' : '' ?>>MRI</option>
        <option value="CT_SCAN" <?= $filterModality === 'CT_SCAN' ? 'selected' : '' ?>>CT Scan</option>
        <option value="ULTRASOUND" <?= $filterModality === 'ULTRASOUND' ? 'selected' : '' ?>>Ultrasound</option>
        <option value="BLOOD_TEST" <?= $filterModality === 'BLOOD_TEST' ? 'selected' : '' ?>>Blood Test</option>
        <option value="ECG" <?= $filterModality === 'ECG' ? 'selected' : '' ?>>ECG</option>
        <option value="OTHER" <?= $filterModality === 'OTHER' ? 'selected' : '' ?>>Other</option>
      </select>
    </label>
    <label class="mc-field" style="margin:0">
      <span>Status</span>
      <select name="status">
        <option value="all">All statuses</option>
        <option value="VERIFIED" <?= $filterStatus === 'VERIFIED' ? 'selected' : '' ?>>Verified</option>
        <option value="COMPLETED" <?= $filterStatus === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
        <option value="ORDERED" <?= $filterStatus === 'ORDERED' ? 'selected' : '' ?>>Ordered</option>
      </select>
    </label>
    <div style="display:flex;gap:.45rem;flex-wrap:wrap">
      <button type="submit" class="mc-btn mc-btn-primary">Filter</button>
      <?php if ($searchQ || $filterModality !== 'all' || $filterStatus !== 'all'): ?>
        <a href="<?= APP_URL ?>/hospital/diagnostics.php" class="mc-btn mc-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Diagnostic records (<?= count($diagnostics) ?>)</h2>
    <span class="lab-status ok">EMR synced</span>
  </div>
  <?php if (empty($diagnostics)): ?>
    <p style="margin:0;color:var(--muted)">No diagnostic records found. Click "Add imaging / scan" above to assign scans to patients.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>UID &amp; Date</th>
            <th>Patient</th>
            <th>Modality</th>
            <th>Test &amp; Region</th>
            <th>Ordered by</th>
            <th>Findings</th>
            <th>Attachment</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($diagnostics as $diag):
            $sCls = $diag['status'] === 'VERIFIED' ? 'ok' : ($diag['status'] === 'COMPLETED' ? 'info' : 'warn');
          ?>
            <tr>
              <td>
                <div class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($diag['diagnostic_uid']) ?></div>
                <div style="font-size:.8rem;color:var(--muted)"><?= date('M j, Y', strtotime($diag['test_date'] ?: $diag['created_at'])) ?></div>
              </td>
              <td>
                <strong><?= htmlspecialchars($diag['patient_name']) ?></strong>
                <div class="mc-mono" style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($diag['patient_uid']) ?></div>
              </td>
              <td><span class="lab-status info"><?= htmlspecialchars(str_replace('_', ' ', $diag['modality'])) ?></span></td>
              <td>
                <strong><?= htmlspecialchars($diag['title']) ?></strong>
                <?php if ($diag['body_part']): ?><div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($diag['body_part']) ?></div><?php endif; ?>
              </td>
              <td>
                <?= $diag['doctor_name'] ? 'Dr. ' . htmlspecialchars($diag['doctor_name']) : '<span style="color:var(--muted)">Hospital staff</span>' ?>
              </td>
              <td style="max-width:220px;font-size:.84rem;color:var(--ink-2)">
                <?= htmlspecialchars($diag['impression'] ?: $diag['findings'] ?: 'Recorded in patient profile') ?>
              </td>
              <td>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                  <?php if ($diag['file_path']): ?>
                    <a href="<?= APP_URL ?>/<?= htmlspecialchars($diag['file_path']) ?>" target="_blank" class="mc-btn mc-btn-secondary mc-btn-sm">File</a>
                  <?php endif; ?>
                  <?php if ($diag['drive_url']): ?>
                    <a href="<?= htmlspecialchars($diag['drive_url']) ?>" target="_blank" rel="noopener noreferrer" class="mc-btn mc-btn-primary mc-btn-sm">Drive</a>
                  <?php endif; ?>
                  <?php if (!$diag['file_path'] && !$diag['drive_url']): ?>
                    <span style="color:var(--muted);font-size:.82rem">Text</span>
                  <?php endif; ?>
                </div>
              </td>
              <td><span class="lab-status <?= $sCls ?>"><?= htmlspecialchars($diag['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- MODAL: ADD DIAGNOSTIC -->
<div class="mc-modal-backdrop" id="addDiagModal" role="dialog" aria-modal="true" aria-label="Add diagnostic record">
  <div class="mc-modal" style="width:min(740px,100%)">
    <div class="mc-modal-head">
      <h3><i class="bi bi-cloud-arrow-up-fill" style="color:var(--accent)"></i> Add diagnostic / imaging record</h3>
      <button type="button" class="mc-modal-close" onclick="closeAddDiagModal()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="action" value="add_diagnostic">

      <div class="mc-form-grid">
        <label class="mc-field" style="margin:0">
          <span>Patient UID *</span>
          <input type="text" name="patient_id_or_uid" id="patientUidInput" placeholder="e.g. PT-000001" required>
        </label>
        <label class="mc-field" style="margin:0">
          <span>Quick pick patient</span>
          <select onchange="document.getElementById('patientUidInput').value = this.value;">
            <option value="">-- Select registered patient --</option>
            <?php foreach ($patientsList as $p): ?>
              <option value="<?= htmlspecialchars($p['patient_uid']) ?>"><?= htmlspecialchars($p['full_name']) ?> (<?= htmlspecialchars($p['patient_uid']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="mc-form-grid-3" style="margin-top:.85rem">
        <label class="mc-field" style="margin:0">
          <span>Test / scan title *</span>
          <input type="text" name="title" placeholder="e.g. Chest X-Ray PA View" required>
        </label>
        <label class="mc-field" style="margin:0">
          <span>Modality</span>
          <select name="modality">
            <option value="XRAY">X-Ray</option>
            <option value="MRI">MRI Scan</option>
            <option value="CT_SCAN">CT Scan</option>
            <option value="ULTRASOUND">Ultrasound</option>
            <option value="ECG">ECG / EKG</option>
            <option value="ECHOCARDIOGRAM">Echocardiogram</option>
            <option value="BLOOD_TEST">Blood Test</option>
            <option value="URINE_TEST">Urine Analysis</option>
            <option value="BIOPSY">Biopsy</option>
            <option value="ENDOSCOPY">Endoscopy</option>
            <option value="OTHER">Other</option>
          </select>
        </label>
        <label class="mc-field" style="margin:0">
          <span>Category</span>
          <select name="diagnostic_category">
            <option value="RADIOLOGY">Radiology</option>
            <option value="PATHOLOGY">Pathology</option>
            <option value="CARDIOLOGY">Cardiology</option>
            <option value="OTHER">Other</option>
          </select>
        </label>
      </div>

      <div class="mc-form-grid" style="margin-top:.85rem">
        <label class="mc-field" style="margin:0">
          <span>Body part / region</span>
          <input type="text" name="body_part" placeholder="e.g. Chest, Brain, Abdomen">
        </label>
        <label class="mc-field" style="margin:0">
          <span>Ordering doctor</span>
          <select name="ordered_by_doctor_id">
            <option value="0">-- In-House / Radiology Team --</option>
            <?php foreach ($affiliatedDoctors as $ad): ?>
              <option value="<?= (int)$ad['id'] ?>">Dr. <?= htmlspecialchars($ad['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label class="mc-field">
        <span>Clinical indication / symptoms</span>
        <input type="text" name="clinical_indication" placeholder="e.g. Persistent cough, rule out lesion">
      </label>

      <div class="mc-form-grid" style="margin-top:.85rem">
        <label class="mc-field" style="margin:0">
          <span>Findings</span>
          <textarea name="findings" rows="3" placeholder="Detailed radiologist findings..."></textarea>
        </label>
        <label class="mc-field" style="margin:0">
          <span>Impression / conclusion</span>
          <textarea name="impression" rows="3" placeholder="Final diagnostic impression..."></textarea>
        </label>
      </div>

      <div class="mc-form-grid" style="margin-top:.85rem">
        <label class="mc-field" style="margin:0">
          <span>Upload file (Image / PDF / DICOM)</span>
          <input type="file" name="diagnostic_file" accept=".jpg,.jpeg,.png,.webp,.pdf,.dcm,.dicom">
        </label>
        <label class="mc-field" style="margin:0">
          <span>Google Drive / Cloud URL</span>
          <input type="url" name="drive_url" placeholder="https://drive.google.com/...">
        </label>
      </div>

      <div class="mc-form-grid-3" style="margin-top:.85rem">
        <label class="mc-field" style="margin:0">
          <span>Reporting specialist</span>
          <input type="text" name="reporting_specialist" placeholder="e.g. Dr. A. Rahman">
        </label>
        <label class="mc-field" style="margin:0">
          <span>Test date</span>
          <input type="date" name="test_date" value="<?= date('Y-m-d') ?>">
        </label>
        <label class="mc-field" style="margin:0">
          <span>Status</span>
          <select name="status">
            <option value="VERIFIED">Verified Official</option>
            <option value="COMPLETED">Completed</option>
            <option value="ORDERED">Ordered</option>
          </select>
        </label>
      </div>

      <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1.1rem">
        <button type="button" class="mc-btn mc-btn-secondary" onclick="closeAddDiagModal()">Cancel</button>
        <button type="submit" class="mc-btn mc-btn-primary">Save &amp; assign to patient EMR</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddDiagModal() { document.getElementById('addDiagModal')?.classList.add('open'); }
function closeAddDiagModal() { document.getElementById('addDiagModal')?.classList.remove('open'); }
document.getElementById('addDiagModal')?.addEventListener('click', function(e) { if (e.target === this) closeAddDiagModal(); });
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
