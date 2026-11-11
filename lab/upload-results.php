<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireLabContext();

$db         = getDB();
$labId      = (int)$_SESSION['lab_id'];
$hospitalId = (int)$_SESSION['hospital_id'];
$orderId    = (int)($_GET['order_id'] ?? 0);
$testId     = (int)($_GET['test_id'] ?? 0);

// Verify the order exists and is accessible to this laboratory
$stmtOrder = $db->prepare("
    SELECT plo.*, p.full_name AS patient_name, p.patient_uid,
           d.full_name AS doctor_name, h.name AS hospital_name,
           rx.prescription_uid
    FROM prescription_lab_orders plo
    JOIN patients p ON plo.patient_id = p.id
    JOIN doctors d ON plo.doctor_id = d.id
    JOIN hospitals h ON plo.hospital_id = h.id
    LEFT JOIN prescriptions rx ON plo.prescription_id = rx.id
    WHERE plo.id = ? AND (plo.laboratory_id = ? OR plo.hospital_id = ? OR plo.status IN ('PENDING','IN_PROGRESS'))
    LIMIT 1
");
$stmtOrder->execute([$orderId, $labId, $hospitalId]);
$order = $stmtOrder->fetch();

if (!$order) {
    header('Location: ' . APP_URL . '/lab/search-token.php?error=not_found');
    exit;
}

// Verify the test belongs to this order
$stmtTest = $db->prepare("SELECT * FROM lab_order_tests WHERE id = ? AND lab_order_id = ?");
$stmtTest->execute([$testId, $orderId]);
$test = $stmtTest->fetch();

if (!$test) {
    header('Location: ' . APP_URL . '/lab/search-token.php?token=' . urlencode($order['lab_token']));
    exit;
}

// Get existing result if any
$stmtExisting = $db->prepare("
    SELECT ltr.*, COUNT(lrf.id) AS file_count
    FROM lab_test_results ltr
    LEFT JOIN lab_result_files lrf ON lrf.lab_test_result_id = ltr.id
    WHERE ltr.lab_order_test_id = ?
    GROUP BY ltr.id
    LIMIT 1
");
$stmtExisting->execute([$testId]);
$existingResult = $stmtExisting->fetch();

// Get existing files
$existingFiles = [];
if ($existingResult) {
    $stmtFiles = $db->prepare("SELECT * FROM lab_result_files WHERE lab_test_result_id = ? ORDER BY uploaded_at ASC");
    $stmtFiles->execute([$existingResult['id']]);
    $existingFiles = $stmtFiles->fetchAll();
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_result'])) {
    validateCsrf();

    $resultSummary  = trim($_POST['result_summary'] ?? '');
    $resultValue    = trim($_POST['result_value'] ?? '');
    $referenceRange = trim($_POST['reference_range'] ?? '');
    $resultStatus   = trim($_POST['result_status'] ?? 'PENDING_REVIEW');
    $technicianName = trim($_POST['technician_name'] ?? '');

    $allowedStatuses = ['NORMAL', 'ABNORMAL', 'CRITICAL', 'PENDING_REVIEW'];
    if (!in_array($resultStatus, $allowedStatuses)) {
        $resultStatus = 'PENDING_REVIEW';
    }

    $db->beginTransaction();
    try {
        // Upsert result
        if ($existingResult) {
            $db->prepare("
                UPDATE lab_test_results
                SET result_summary=?, result_value=?, reference_range=?,
                    result_status=?, technician_name=?, laboratory_id=?, hospital_id=?,
                    uploaded_by=?, uploaded_at=NOW()
                WHERE id=?
            ")->execute([
                $resultSummary, $resultValue, $referenceRange,
                $resultStatus, $technicianName, $labId, $hospitalId,
                $_SESSION['user_id'], $existingResult['id']
            ]);
            $resultId = $existingResult['id'];
        } else {
            $db->prepare("
                INSERT INTO lab_test_results
                    (lab_order_test_id, laboratory_id, hospital_id, result_summary,
                     result_value, reference_range, result_status, technician_name, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $testId, $labId, $hospitalId, $resultSummary,
                $resultValue, $referenceRange, $resultStatus, $technicianName, $_SESSION['user_id']
            ]);
            $resultId = $db->lastInsertId();
        }

        // Handle file uploads (multiple)
        $uploadedFiles = 0;
        if (!empty($_FILES['result_files']['name'][0])) {
            $dir = UPLOAD_PATH . '/lab-results';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $fileCount = count($_FILES['result_files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['result_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($_FILES['result_files']['size'][$i] > MAX_UPLOAD_SIZE_MB * 1024 * 1024) continue;

                $origName = $_FILES['result_files']['name'][$i];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ALLOWED_DOC_EXTENSIONS)) continue;

                $fType = ($ext === 'pdf') ? 'PDF' : (in_array($ext, ['jpg','jpeg','png','webp']) ? 'IMAGE' : 'OTHER');
                $fname = 'res_' . $labId . '_' . $testId . '_' . time() . '_' . $i . '.' . $ext;
                $fpath = $dir . '/' . $fname;

                if (move_uploaded_file($_FILES['result_files']['tmp_name'][$i], $fpath)) {
                    $db->prepare("
                        INSERT INTO lab_result_files
                            (lab_test_result_id, file_path, original_name, file_type, file_size)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([
                        $resultId,
                        'lab-results/' . $fname,
                        $origName,
                        $fType,
                        $_FILES['result_files']['size'][$i]
                    ]);
                    $uploadedFiles++;
                }
            }
        }

        // Update test status to COMPLETED
        $db->prepare("UPDATE lab_order_tests SET status='COMPLETED' WHERE id=?")->execute([$testId]);

        // Check if ALL tests in this order are completed → mark order COMPLETED
        $pendingTests = $db->prepare("
            SELECT COUNT(*) FROM lab_order_tests
            WHERE lab_order_id = ? AND status != 'COMPLETED'
        ");
        $pendingTests->execute([$orderId]);
        $remaining = $pendingTests->fetchColumn();

        $newOrderStatus = ($remaining == 0) ? 'COMPLETED' : 'PARTIAL';
        $db->prepare("UPDATE prescription_lab_orders SET status=?, laboratory_id=? WHERE id=?")->execute([$newOrderStatus, $labId, $orderId]);

        // Get primary result file if any
        $firstFilePath = null;
        $stmtFirstFile = $db->prepare("SELECT file_path FROM lab_result_files WHERE lab_test_result_id = ? ORDER BY id ASC LIMIT 1");
        $stmtFirstFile->execute([$resultId]);
        $firstFilePath = $stmtFirstFile->fetchColumn() ?: null;

        // Auto-sync into lab_reports table for immediate patient and doctor portal display
        $labName = $_SESSION['lab_name'] ?? 'Apex Diagnostics Ltd.';
        $fullSummary = $resultSummary;
        if ($resultValue) {
            $fullSummary = "Value: {$resultValue}" . ($referenceRange ? " (Ref: {$referenceRange})" : "") . ($resultSummary ? " | {$resultSummary}" : "");
        }

        $reportTitle = $test['test_name'] . " (Token: {$order['lab_token']})";
        $currentDate = date('Y-m-d');

        // Check if already synced in lab_reports
        $stmtCheckLR = $db->prepare("SELECT id FROM lab_reports WHERE patient_id = ? AND report_name = ?");
        $stmtCheckLR->execute([$order['patient_id'], $reportTitle]);
        $existingLRId = $stmtCheckLR->fetchColumn();

        if ($existingLRId) {
            $db->prepare("
                UPDATE lab_reports
                SET result_summary = ?, file_path = COALESCE(?, file_path), laboratory_name = ?, verified = 1
                WHERE id = ?
            ")->execute([$fullSummary, $firstFilePath, $labName, $existingLRId]);
        } else {
            $db->prepare("
                INSERT INTO lab_reports
                    (patient_id, report_name, test_date, laboratory_name, result_summary, file_path, uploaded_by, verified)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ")->execute([
                $order['patient_id'],
                $reportTitle,
                $currentDate,
                $labName,
                $fullSummary,
                $firstFilePath,
                $_SESSION['user_id']
            ]);
        }

        // Auto-sync into patient_diagnostics if it is Radiology, Cardiac, or Imaging
        $testNameUpper = strtoupper($test['test_name']);
        $catUpper = strtoupper($test['test_category'] ?? '');
        $modality = null;
        if (strpos($testNameUpper, 'MRI') !== false) {
            $modality = 'MRI';
        } elseif (strpos($testNameUpper, 'X-RAY') !== false || strpos($testNameUpper, 'XRAY') !== false) {
            $modality = 'XRAY';
        } elseif (strpos($testNameUpper, 'CT') !== false) {
            $modality = 'CT_SCAN';
        } elseif (strpos($testNameUpper, 'USG') !== false || strpos($testNameUpper, 'ULTRASOUND') !== false) {
            $modality = 'ULTRASOUND';
        } elseif (strpos($testNameUpper, 'ECG') !== false || strpos($testNameUpper, 'EKG') !== false) {
            $modality = 'ECG';
        } elseif ($catUpper === 'RADIOLOGY') {
            $modality = 'XRAY';
        } elseif ($catUpper === 'CARDIAC') {
            $modality = 'ECG';
        }

        if ($modality) {
            $bodyPart = 'Diagnostic Region';
            if (strpos($testNameUpper, 'BRAIN') !== false) $bodyPart = 'Brain / Head';
            elseif (strpos($testNameUpper, 'CHEST') !== false) $bodyPart = 'Chest / Thorax';
            elseif (strpos($testNameUpper, 'ABDOMEN') !== false) $bodyPart = 'Abdomen & Pelvis';
            elseif (strpos($testNameUpper, 'SPINE') !== false) $bodyPart = 'Spine';

            $diagIndication = "Prescription #{$order['prescription_uid']} · Token: {$order['lab_token']}";
            $diagTitle = $test['test_name'];

            // Check if already in patient_diagnostics
            $stmtCheckDiag = $db->prepare("SELECT id FROM patient_diagnostics WHERE patient_id = ? AND clinical_indication = ?");
            $stmtCheckDiag->execute([$order['patient_id'], $diagIndication]);
            $existingDiagId = $stmtCheckDiag->fetchColumn();

            if ($existingDiagId) {
                $db->prepare("
                    UPDATE patient_diagnostics
                    SET findings = ?, impression = ?, reporting_specialist = ?, file_path = COALESCE(?, file_path), updated_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $resultSummary ?: $fullSummary,
                    $resultStatus . ($resultValue ? " ({$resultValue})" : ""),
                    $technicianName ?: $labName . " Diagnostic Team",
                    $firstFilePath,
                    $existingDiagId
                ]);
            } else {
                $diagUid = 'DIAG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $db->prepare("
                    INSERT INTO patient_diagnostics
                        (diagnostic_uid, patient_id, hospital_id, ordered_by_doctor_id, diagnostic_category,
                         modality, title, body_part, clinical_indication, findings, impression,
                         test_date, report_date, reporting_specialist, status, storage_type,
                         file_path, verified_by, verified_at)
                    VALUES (?, ?, ?, ?, 'RADIOLOGY', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'COMPLETED', 'LOCAL_FILE', ?, ?, NOW())
                ")->execute([
                    $diagUid,
                    $order['patient_id'],
                    $order['hospital_id'],
                    $order['doctor_id'],
                    $modality,
                    $diagTitle,
                    $bodyPart,
                    $diagIndication,
                    $resultSummary ?: $fullSummary,
                    $resultStatus . ($resultValue ? " ({$resultValue})" : ""),
                    $currentDate,
                    $currentDate,
                    $technicianName ?: $labName . " Diagnostic Team",
                    $firstFilePath,
                    $_SESSION['user_id']
                ]);
            }
        }

        // Notify the patient if result is uploaded
        $db->prepare("
            INSERT INTO notifications
                (user_id, type, title, message, reference_type, reference_id, action_url)
            SELECT u.id, 'LAB_RESULT_READY',
                   'Lab Result Ready',
                   CONCAT(?, ' test result is now available from ', ?, '. Token: ', ?),
                   'lab_order', ?, ?
            FROM patients p JOIN users u ON u.id = p.user_id
            WHERE p.id = ?
        ")->execute([
            $test['test_name'], $_SESSION['lab_name'], $order['lab_token'],
            $orderId,
            APP_URL . '/patient/lab-reports.php',
            $order['patient_id']
        ]);

        AuditService::log(AuditService::LAB_RESULT_UPLOADED, [
            'user_id'    => $_SESSION['user_id'],
            'hospital_id'=> $hospitalId,
            'metadata'   => [
                'lab_id' => $labId, 'test_id' => $testId,
                'order_id' => $orderId, 'files_uploaded' => $uploadedFiles,
                'result_status' => $resultStatus,
            ],
        ]);

        $db->commit();
        $success = "Result uploaded successfully!" . ($uploadedFiles > 0 ? " ({$uploadedFiles} file(s) attached)" : '') . " Patient has been notified.";

        // Reload existing result
        $stmtExisting->execute([$testId]);
        $existingResult = $stmtExisting->fetch();
        $stmtFiles->execute([$existingResult['id'] ?? 0]);
        $existingFiles = $stmtFiles->fetchAll();

    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Upload failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Result — <?= htmlspecialchars($test['test_name']) ?> — MedCore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
<style>
:root{--accent:#0284c7;--lab:#7c3aed;--border:#e2e8f0;--text:#0f172a;--muted:#64748b}
*{box-sizing:border-box}
body{background:#f8fafc;font-family:'Plus Jakarta Sans','Inter',-apple-system,sans-serif;color:var(--text);margin:0}
.nav{background:#ffffff;border-bottom:2px solid #0284c7;padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;box-shadow:0 1px 3px rgba(15,23,42,0.05)}
.nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
.nav-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#0a2540,#0284c7);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#ffffff}
.nav-title{font-size:1rem;font-weight:800;color:#0a2540}
.nav-subtitle{font-size:.72rem;color:var(--muted);font-weight:600}
.nav-back{font-size:.8rem;color:#334155;text-decoration:none;background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;padding:.4rem .85rem;font-weight:600;transition:all .15s}
.nav-back:hover{background:#f1f5f9;border-color:#94a3b8;color:#0f172a}
.page{max-width:800px;margin:0 auto;padding:2rem 1.5rem}
/* BREADCRUMB */
.breadcrumb{display:flex;align-items:center;gap:.5rem;font-size:.82rem;color:#64748b;margin-bottom:1.5rem;flex-wrap:wrap;font-weight:600}
.breadcrumb a{color:var(--accent);text-decoration:none}
.breadcrumb a:hover{text-decoration:underline}
.breadcrumb span{color:#94a3b8}
/* CONTEXT */
.context-banner{background:linear-gradient(135deg,#f0f9ff 0%,#ffffff 100%);border:1.5px solid #bae6fd;border-radius:16px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 2px 8px rgba(2,132,199,0.05)}
.ctx-row{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap}
.ctx-left h2{font-size:1.3rem;font-weight:900;color:#0a2540;margin:0 0 .5rem}
.ctx-pills{display:flex;flex-wrap:wrap;gap:.5rem}
.ctx-pill{background:#ffffff;border:1px solid #cbd5e1;border-radius:7px;padding:.3rem .75rem;font-size:.78rem;color:#475569}
.ctx-pill strong{color:#0a2540}
.token-chip{background:#0284c7;border-radius:9px;padding:.4rem .95rem;font-family:'JetBrains Mono',monospace;font-size:1rem;font-weight:800;color:#ffffff;box-shadow:0 2px 8px rgba(2,132,199,0.25)}
/* FORM */
.form-card{background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:2rem;margin-bottom:1.5rem;box-shadow:0 2px 8px rgba(15,23,42,0.03)}
.section-label{font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--accent);margin-bottom:1.25rem;padding-bottom:.5rem;border-bottom:1px solid #f1f5f9}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{margin-bottom:1.25rem}
.form-group:last-child{margin-bottom:0}
label{display:block;color:#334155;font-size:.82rem;font-weight:700;margin-bottom:.4rem}
label .hint{color:#94a3b8;font-weight:400}
input[type=text],input[type=number],textarea,select{width:100%;background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:.75rem 1rem;color:#0f172a;font-size:.9rem;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;font-family:inherit}
textarea{resize:vertical;min-height:100px}
select option{background:#ffffff;color:#0f172a}
input:focus,textarea:focus,select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(2,132,199,0.15)}
/* FILE UPLOAD */
.file-drop{border:2px dashed #cbd5e1;background:#f8fafc;border-radius:14px;padding:2.5rem;text-align:center;transition:all .2s;cursor:pointer;position:relative}
.file-drop:hover,.file-drop.dragover{border-color:var(--accent);background:#f0f9ff}
.file-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.file-drop-icon{font-size:2.5rem;margin-bottom:.75rem}
.file-drop h3{font-size:1rem;font-weight:800;color:#0a2540;margin:0 0 .25rem}
.file-drop p{font-size:.82rem;color:#64748b;margin:0}
.file-drop .allowed{font-size:.75rem;color:#94a3b8;margin-top:.5rem}
/* FILE LIST */
.file-list{display:flex;flex-direction:column;gap:.5rem;margin-top:.75rem}
.file-item{display:flex;align-items:center;gap:.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:.6rem .875rem}
.file-icon{font-size:1.1rem;flex-shrink:0}
.file-info{flex:1;min-width:0}
.file-name{font-size:.82rem;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.file-size{font-size:.72rem;color:#64748b}
/* EXISTING FILES */
.existing-files{margin-top:.75rem}
.ex-file{display:flex;align-items:center;gap:.75rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:9px;padding:.6rem .875rem;margin-bottom:.4rem}
.ex-file-type{font-size:1rem}
.ex-file-name{flex:1;font-size:.82rem;color:#166534;font-weight:600}
.ex-file-link{font-size:.78rem;color:#0284c7;font-weight:700;text-decoration:none}
/* SUBMIT */
.btn-submit{width:100%;background:linear-gradient(135deg,#0a2540,#0284c7);border:none;border-radius:12px;padding:1rem;color:#fff;font-size:1rem;font-weight:800;cursor:pointer;transition:all .15s;margin-top:.5rem;box-shadow:0 4px 14px rgba(10,37,64,0.2)}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(10,37,64,0.3)}
/* ALERTS */
.alert{border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:.75rem;font-size:.875rem}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-weight:600}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-weight:700}
/* STATUS SELECT */
.status-opt{display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;margin-top:.4rem}
.status-opt label{cursor:pointer;display:block;border:1px solid #cbd5e1;background:#ffffff;border-radius:8px;padding:.55rem .5rem;text-align:center;font-size:.78rem;font-weight:600;transition:all .15s;color:#475569}
.status-opt input[type=radio]{display:none}
.status-opt input[type=radio]:checked+label{font-weight:800}
.status-opt .opt-normal input:checked+label{background:#ecfdf5;border-color:#10b981;color:#065f46}
.status-opt .opt-abnormal input:checked+label{background:#fef3c7;border-color:#f59e0b;color:#92400e}
.status-opt .opt-critical input:checked+label{background:#fef2f2;border-color:#ef4444;color:#991b1b}
.status-opt .opt-pending input:checked+label{background:#f1f5f9;border-color:#94a3b8;color:#0f172a}
@media(max-width:560px){.grid-2{grid-template-columns:1fr}.status-opt{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<nav class="nav">
  <a href="<?= APP_URL ?>/lab/dashboard.php" class="nav-brand">
    <div class="nav-icon">🔬</div>
    <div>
      <div class="nav-title"><?= htmlspecialchars($_SESSION['lab_name'] ?? 'Lab Portal') ?></div>
      <div class="nav-subtitle">Upload Result</div>
    </div>
  </a>
  <div class="nav-right">
    <a href="<?= APP_URL ?>/lab/search-token.php?token=<?= urlencode($order['lab_token']) ?>" class="nav-back">← Back to Order</a>
  </div>
</nav>

<div class="page">
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/lab/dashboard.php">Dashboard</a>
    <span>›</span>
    <a href="<?= APP_URL ?>/lab/search-token.php?token=<?= urlencode($order['lab_token']) ?>">Token <?= htmlspecialchars($order['lab_token']) ?></a>
    <span>›</span>
    <span><?= htmlspecialchars($test['test_name']) ?></span>
  </div>

  <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Context Banner -->
  <div class="context-banner">
    <div class="ctx-row">
      <div class="ctx-left">
        <h2>🧪 <?= htmlspecialchars($test['test_name']) ?></h2>
        <div class="ctx-pills">
          <div class="ctx-pill">Patient: <strong><?= htmlspecialchars($order['patient_name']) ?></strong></div>
          <div class="ctx-pill">UID: <strong><?= htmlspecialchars($order['patient_uid']) ?></strong></div>
          <div class="ctx-pill">Doctor: <strong>Dr. <?= htmlspecialchars($order['doctor_name']) ?></strong></div>
          <?php if ($test['test_category']): ?>
          <div class="ctx-pill">Category: <strong><?= htmlspecialchars($test['test_category']) ?></strong></div>
          <?php endif; ?>
          <div class="ctx-pill">Urgency: <strong><?= $test['urgency'] ?></strong></div>
        </div>
      </div>
      <div class="token-chip"><?= htmlspecialchars($order['lab_token']) ?></div>
    </div>
  </div>

  <!-- Upload Form -->
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="form-card">
      <div class="section-label">Result Details</div>

      <!-- Result Status -->
      <div class="form-group">
        <label>Result Status</label>
        <div class="status-opt">
          <div class="opt-normal">
            <input type="radio" name="result_status" id="rs_normal" value="NORMAL" <?= ($existingResult['result_status'] ?? '') === 'NORMAL' ? 'checked' : '' ?>>
            <label for="rs_normal">✅ Normal</label>
          </div>
          <div class="opt-abnormal">
            <input type="radio" name="result_status" id="rs_abnormal" value="ABNORMAL" <?= ($existingResult['result_status'] ?? '') === 'ABNORMAL' ? 'checked' : '' ?>>
            <label for="rs_abnormal">⚠️ Abnormal</label>
          </div>
          <div class="opt-critical">
            <input type="radio" name="result_status" id="rs_critical" value="CRITICAL" <?= ($existingResult['result_status'] ?? '') === 'CRITICAL' ? 'checked' : '' ?>>
            <label for="rs_critical">🚨 Critical</label>
          </div>
          <div class="opt-pending">
            <input type="radio" name="result_status" id="rs_pending" value="PENDING_REVIEW" <?= (($existingResult['result_status'] ?? 'PENDING_REVIEW') === 'PENDING_REVIEW') ? 'checked' : '' ?>>
            <label for="rs_pending">⏳ Pending</label>
          </div>
        </div>
      </div>

      <div class="grid-2">
        <div class="form-group">
          <label>Result Value <span class="hint">(optional)</span></label>
          <input type="text" name="result_value" placeholder="e.g. 5.2 mmol/L, 14g/dL"
                 value="<?= htmlspecialchars($existingResult['result_value'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Reference Range <span class="hint">(optional)</span></label>
          <input type="text" name="reference_range" placeholder="e.g. 3.9–5.6 mmol/L"
                 value="<?= htmlspecialchars($existingResult['reference_range'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Result Summary / Interpretation</label>
        <textarea name="result_summary" placeholder="Describe the findings, observations, or interpretation of the test result..."><?= htmlspecialchars($existingResult['result_summary'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label>Technician Name <span class="hint">(optional)</span></label>
        <input type="text" name="technician_name" placeholder="Name of the lab technician who conducted the test"
               value="<?= htmlspecialchars($existingResult['technician_name'] ?? '') ?>">
      </div>
    </div>

    <!-- File Upload -->
    <div class="form-card">
      <div class="section-label">Attach Files <span style="font-size:.75rem;font-weight:400;text-transform:none;letter-spacing:0;color:#64748b">(PDF, images — multiple allowed)</span></div>

      <?php if (!empty($existingFiles)): ?>
      <div style="margin-bottom:1rem">
        <div style="font-size:.8rem;color:#94a3b8;margin-bottom:.5rem">Previously uploaded files:</div>
        <div class="existing-files">
          <?php foreach ($existingFiles as $ef): ?>
          <?php $isImg = $ef['file_type'] === 'IMAGE'; $isPdf = $ef['file_type'] === 'PDF'; ?>
          <div class="ex-file">
            <span class="ex-file-type"><?= $isPdf ? '📄' : ($isImg ? '🖼️' : '📎') ?></span>
            <span class="ex-file-name"><?= htmlspecialchars($ef['original_name'] ?? basename($ef['file_path'])) ?></span>
            <?php if ($ef['file_size']): ?><span style="font-size:.7rem;color:#64748b"><?= round($ef['file_size']/1024, 1) ?> KB</span><?php endif; ?>
            <a href="<?= APP_URL ?>/storage/uploads/<?= htmlspecialchars($ef['file_path']) ?>" target="_blank" class="ex-file-link">View ↗</a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="file-drop" id="fileDrop">
        <input type="file" name="result_files[]" id="fileInput" multiple
               accept=".pdf,.jpg,.jpeg,.png,.webp">
        <div class="file-drop-icon">📂</div>
        <h3>Drop files here or click to browse</h3>
        <p>Upload report files, scan images, or result documents</p>
        <div class="allowed">Supported: PDF, JPG, PNG, WEBP · Max <?= MAX_UPLOAD_SIZE_MB ?>MB per file</div>
      </div>
      <div class="file-list" id="fileList"></div>
    </div>

    <button type="submit" name="upload_result" class="btn-submit">
      <?= $existingResult ? '📤 Update Result & Add Files' : '📤 Submit Result' ?>
    </button>
  </form>
</div>

<script>
const input = document.getElementById('fileInput');
const list  = document.getElementById('fileList');
const drop  = document.getElementById('fileDrop');

function renderFiles(files) {
    list.innerHTML = '';
    Array.from(files).forEach((f, i) => {
        const ext = f.name.split('.').pop().toLowerCase();
        const icon = ext === 'pdf' ? '📄' : ['jpg','jpeg','png','webp'].includes(ext) ? '🖼️' : '📎';
        const size = f.size > 1024*1024 ? (f.size/1024/1024).toFixed(1)+' MB' : Math.round(f.size/1024)+' KB';
        list.innerHTML += `<div class="file-item"><span class="file-icon">${icon}</span><div class="file-info"><div class="file-name">${f.name}</div><div class="file-size">${size}</div></div></div>`;
    });
}

input.addEventListener('change', () => renderFiles(input.files));

drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('dragover'); });
drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
drop.addEventListener('drop', e => {
    e.preventDefault();
    drop.classList.remove('dragover');
    const dt = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
    input.files = dt.files;
    renderFiles(input.files);
});
</script>
</body>
</html>
