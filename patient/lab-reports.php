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


$activeTab = $_GET['tab'] ?? 'orders';
$csrf = generateCsrfToken();
$doctorLabel = static function (?string $name): string {
    if (!$name) return '—';
    $clean = preg_replace('/^\s*Dr\.?\s+/i', '', trim($name)) ?: $name;
    return 'Dr. ' . $clean;
};

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · Labs';
$portalTitle = 'Lab reports';
$portalSubtitle = 'টোকেন অর্ডার ও ডায়াগনস্টিক রেজাল্ট';
$portalNav = mc_patient_nav('labs');
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($success): ?><div class="mc-alert mc-alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mc-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Lab tokens</div><div class="v"><?= (int)$ordersCount ?></div></div>
  <div class="mc-stat"><div class="l">Completed</div><div class="v"><?= (int)$completedOrdersCount ?></div></div>
  <div class="mc-stat"><div class="l">Imaging</div><div class="v"><?= (int)$imagingCount ?></div></div>
  <div class="mc-stat"><div class="l">Reports</div><div class="v"><?= (int)$totalCount ?></div></div>
</div>

<div class="mc-panel">
  <div style="display:flex;gap:.35rem;flex-wrap:wrap">
    <?php foreach (['orders' => 'Token orders', 'diagnostics' => 'Hospital diagnostics', 'uploads' => 'My uploads'] as $k => $lbl): ?>
      <a class="mc-btn mc-btn-sm <?= $activeTab === $k ? 'mc-btn-primary' : 'mc-btn-ghost' ?>" href="?tab=<?= $k ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($activeTab === 'orders' || $activeTab === 'all'): ?>
<div class="mc-panel">
  <div class="mc-panel-h"><h2>Prescription lab tokens</h2></div>
  <?php if (empty($labOrdersWithDetails)): ?>
    <p style="margin:0;color:var(--muted)">কোনো ল্যাব টোকেন নেই।</p>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($labOrdersWithDetails as $ord): ?>
        <div class="mc-list-item" style="align-items:flex-start;flex-wrap:wrap;gap:.75rem">
          <div style="flex:1;min-width:220px">
            <strong><code class="mc-mono"><?= htmlspecialchars($ord['lab_token']) ?></code></strong>
            <div style="font-size:.86rem;color:var(--muted);margin-top:.25rem">
              <?= htmlspecialchars($doctorLabel($ord['doctor_name'] ?? null)) ?>
              · <?= htmlspecialchars($ord['hospital_name']) ?>
              · <?= date('d M Y', strtotime($ord['created_at'])) ?>
            </div>
            <?php if (!empty($ord['tests'])): ?>
              <div style="margin-top:.55rem;display:grid;gap:.35rem">
                <?php foreach ($ord['tests'] as $t): ?>
                  <div style="font-size:.86rem;padding:.45rem .65rem;border:1px solid var(--line);border-radius:8px;background:#fafbfc">
                    <strong><?= htmlspecialchars($t['test_name']) ?></strong>
                    <span class="lab-status" style="margin-left:.35rem"><?= htmlspecialchars($t['status'] ?? ($t['result_status'] ?? 'PENDING')) ?></span>
                    <?php if (!empty($t['result_summary'])): ?>
                      <div style="color:var(--muted);margin-top:.2rem"><?= htmlspecialchars($t['result_summary']) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <span class="lab-status"><?= htmlspecialchars($ord['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($activeTab === 'diagnostics'): ?>
<div class="mc-panel">
  <div class="mc-panel-h"><h2>Hospital diagnostics</h2></div>
  <?php if (empty($diagnostics)): ?>
    <p style="margin:0;color:var(--muted)">কোনো hospital diagnostic নেই।</p>
  <?php else: ?>
    <div style="overflow:auto">
      <table class="lab-table">
        <thead><tr><th>Test</th><th>Modality</th><th>Hospital</th><th>Date</th><th>Doctor</th></tr></thead>
        <tbody>
          <?php foreach ($diagnostics as $d): ?>
            <tr>
              <td><strong><?= htmlspecialchars($d['test_name'] ?? $d['modality'] ?? 'Diagnostic') ?></strong></td>
              <td><?= htmlspecialchars($d['modality'] ?? '—') ?></td>
              <td><?= htmlspecialchars($d['hospital_name']) ?></td>
              <td><?= !empty($d['test_date']) ? date('d M Y', strtotime($d['test_date'])) : '—' ?></td>
              <td><?= htmlspecialchars($doctorLabel($d['doctor_name'] ?? null)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($activeTab === 'uploads'): ?>
<div class="mc-panel">
  <div class="mc-panel-h"><h2>Upload a report</h2></div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="upload_report" value="1">
    <label class="mc-field"><span>Report name</span><input type="text" name="report_name" required></label>
    <label class="mc-field"><span>Laboratory</span><input type="text" name="laboratory_name"></label>
    <label class="mc-field"><span>Test date</span><input type="date" name="test_date" value="<?= date('Y-m-d') ?>"></label>
    <label class="mc-field"><span>Summary</span><textarea name="result_summary" rows="3"></textarea></label>
    <label class="mc-field"><span>File (PDF/JPG/PNG)</span><input type="file" name="report_file" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
    <button type="submit" class="mc-btn mc-btn-primary" style="margin-top:1rem">Upload</button>
  </form>
</div>
<div class="mc-panel">
  <div class="mc-panel-h"><h2>Uploaded reports</h2></div>
  <?php if (empty($legacyReports)): ?>
    <p style="margin:0;color:var(--muted)">কোনো uploaded report নেই।</p>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($legacyReports as $r): ?>
        <div class="mc-list-item">
          <div>
            <strong><?= htmlspecialchars($r['report_name']) ?></strong>
            <div style="font-size:.86rem;color:var(--muted)">
              <?= htmlspecialchars($r['laboratory_name'] ?? '—') ?>
              · <?= !empty($r['test_date']) ? date('d M Y', strtotime($r['test_date'])) : '—' ?>
            </div>
          </div>
          <?php if (!empty($r['file_path'])): ?>
            <a class="mc-btn mc-btn-secondary mc-btn-sm" href="<?= APP_URL ?>/download.php?f=<?= urlencode($r['file_path']) ?>">Open</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>