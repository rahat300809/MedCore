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
    require_once __DIR__ . '/../app/views/portal/navs.php';
    $portalRole = 'Laboratory';
    $portalRoleBn = 'Lab portal';
    $portalUser = $_SESSION['lab_name'] ?? 'Lab';
    $portalMeta = ($_SESSION['lab_uid'] ?? '') . ' · Upload';
    $portalTitle = 'Upload results';
    $portalSubtitle = 'আগে টোকেন খুঁজে টেস্ট সিলেক্ট করুন';
    $portalNav = mc_lab_nav('upload');
    require __DIR__ . '/../app/views/layout/portal_shell_start.php';
    echo '<div class="mc-panel" style="text-align:center;padding:2.2rem 1rem"><h2>Select a test first</h2><p style="color:var(--muted)">রিপোর্ট আপলোড করতে টোকেন সার্চ করে টেস্ট খুলুন।</p><a class="mc-btn mc-btn-primary" style="margin-top:1rem" href="' . APP_URL . '/lab/search-token.php">Search token</a></div>';
    require __DIR__ . '/../app/views/layout/portal_shell_end.php';
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
        if (!$pendingTests) {
            throw new RuntimeException('Could not check remaining tests.');
        }
        $pendingTests->execute([$orderId]);
        $remaining = (int)$pendingTests->fetchColumn();

        $newOrderStatus = ($remaining === 0) ? 'COMPLETED' : 'PARTIAL';
        $stmtOrderStatus = $db->prepare("UPDATE prescription_lab_orders SET status=?, laboratory_id=? WHERE id=?");
        if (!$stmtOrderStatus) {
            throw new RuntimeException('Could not update order status.');
        }
        $stmtOrderStatus->execute([$newOrderStatus, $labId, $orderId]);

        $db->commit();

        // Best-effort sync / notify after successful save
        $labName = $_SESSION['lab_name'] ?? 'Apex Diagnostics Ltd.';
        $fullSummary = $resultSummary;
        if ($resultValue) {
            $fullSummary = "Value: {$resultValue}"
                . ($referenceRange ? " (Ref: {$referenceRange})" : '')
                . ($resultSummary ? " | {$resultSummary}" : '');
        }
        $reportTitle = $test['test_name'] . " (Token: {$order['lab_token']})";
        $currentDate = date('Y-m-d');
        $firstFilePath = null;

        try {
            $stmtFirstFile = $db->prepare("SELECT file_path FROM lab_result_files WHERE lab_test_result_id = ? ORDER BY id ASC LIMIT 1");
            if ($stmtFirstFile) {
                $stmtFirstFile->execute([$resultId]);
                $firstFilePath = $stmtFirstFile->fetchColumn() ?: null;
            }

            $stmtCheckLR = $db->prepare("SELECT id FROM lab_reports WHERE patient_id = ? AND report_name = ?");
            if ($stmtCheckLR) {
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
                        $order['patient_id'], $reportTitle, $currentDate, $labName,
                        $fullSummary, $firstFilePath, $_SESSION['user_id']
                    ]);
                }
            }

            $notifyMsg = $test['test_name'] . ' test result is now available from '
                . $labName . '. Token: ' . $order['lab_token'];
            $stmtNotify = $db->prepare("
                INSERT INTO notifications
                    (user_id, type, title, message, reference_type, reference_id, action_url)
                SELECT u.id, 'LAB_RESULT_READY', 'Lab Result Ready', ?, 'lab_order', ?, ?
                FROM patients p JOIN users u ON u.id = p.user_id
                WHERE p.id = ?
            ");
            if ($stmtNotify) {
                $stmtNotify->execute([
                    $notifyMsg,
                    $orderId,
                    APP_URL . '/patient/lab-reports.php',
                    $order['patient_id']
                ]);
            }
        } catch (Throwable $sideErr) {
            error_log('Lab post-upload sync failed: ' . $sideErr->getMessage());
        }

        AuditService::log(AuditService::LAB_RESULT_UPLOADED, [
            'user_id'     => $_SESSION['user_id'],
            'hospital_id' => $hospitalId,
            'metadata'    => [
                'lab_id' => $labId, 'test_id' => $testId,
                'order_id' => $orderId, 'files_uploaded' => $uploadedFiles,
                'result_status' => $resultStatus,
            ],
        ]);

        $_SESSION['flash'] = [
            'type' => 'success',
            'msg'  => 'Result saved' . ($uploadedFiles > 0 ? " ({$uploadedFiles} file(s))" : '') . '.',
        ];
        header('Location: ' . APP_URL . '/lab/search-token.php?token=' . urlencode($order['lab_token']));
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'Upload failed: ' . $e->getMessage();
    }
}

// Fresh reload for the form view (never reuse stale statements)
$stmtExisting = $db->prepare("
    SELECT ltr.*, COUNT(lrf.id) AS file_count
    FROM lab_test_results ltr
    LEFT JOIN lab_result_files lrf ON lrf.lab_test_result_id = ltr.id
    WHERE ltr.lab_order_test_id = ?
    GROUP BY ltr.id
    LIMIT 1
");
$existingResult = null;
$existingFiles = [];
if ($stmtExisting) {
    $stmtExisting->execute([$testId]);
    $existingResult = $stmtExisting->fetch() ?: null;
    if ($existingResult) {
        $stmtFiles = $db->prepare("SELECT * FROM lab_result_files WHERE lab_test_result_id = ? ORDER BY uploaded_at ASC");
        if ($stmtFiles) {
            $stmtFiles->execute([(int)$existingResult['id']]);
            $existingFiles = $stmtFiles->fetchAll() ?: [];
        }
    }
}

require __DIR__ . '/../app/views/lab/upload_view.php';
