<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireLabContext();

$db         = getDB();
$labId      = (int)$_SESSION['lab_id'];
$hospitalId = (int)$_SESSION['hospital_id'];

$token  = strtoupper(trim($_GET['token'] ?? $_POST['token'] ?? ''));
$order  = null;
$tests  = [];
$error  = '';
$searched = !empty($token);

if ($searched) {
    // Token search — unique token across the diagnostic network
    $stmtOrder = $db->prepare("
        SELECT plo.*,
               p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
               p.blood_group,
               d.full_name AS doctor_name, d.specialization,
               h.name AS hospital_name,
               rx.prescription_uid
        FROM prescription_lab_orders plo
        JOIN patients p ON plo.patient_id = p.id
        JOIN doctors d ON plo.doctor_id = d.id
        JOIN hospitals h ON plo.hospital_id = h.id
        LEFT JOIN prescriptions rx ON plo.prescription_id = rx.id
        WHERE plo.lab_token = ?
        LIMIT 1
    ");
    $stmtOrder->execute([$token]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        $error = "No diagnostic order found for token <strong>" . htmlspecialchars($token) . "</strong>. Verify the token or contact the prescribing doctor.";
    } elseif ($order['status'] === 'EXPIRED' || strtotime($order['expires_at']) < time()) {
        $error = "This token has expired. The lab order is no longer valid.";
        $order = null;
    } elseif ($order['status'] === 'CANCELLED') {
        $error = "This lab order has been cancelled by the doctor.";
        $order = null;
    } else {
        // Load tests with result info
        $stmtTests = $db->prepare("
            SELECT lot.*,
                   ltr.id AS result_id, ltr.result_summary, ltr.result_value,
                   ltr.reference_range, ltr.result_status, ltr.technician_name,
                   ltr.uploaded_at AS result_uploaded_at,
                   l.name AS uploaded_by_lab,
                   (SELECT COUNT(*) FROM lab_result_files lrf WHERE lrf.lab_test_result_id = ltr.id) AS file_count
            FROM lab_order_tests lot
            LEFT JOIN lab_test_results ltr ON ltr.lab_order_test_id = lot.id
            LEFT JOIN laboratories l ON ltr.laboratory_id = l.id
            WHERE lot.lab_order_id = ?
            ORDER BY lot.id ASC
        ");
        $stmtTests->execute([$order['id']]);
        $tests = $stmtTests->fetchAll();

        // Mark order as in_progress if still pending and assign to current lab
        if ($order['status'] === 'PENDING') {
            $db->prepare("UPDATE prescription_lab_orders SET status='IN_PROGRESS', laboratory_id=? WHERE id=?")->execute([$labId, $order['id']]);
            $order['status'] = 'IN_PROGRESS';
        } elseif (empty($order['laboratory_id'])) {
            $db->prepare("UPDATE prescription_lab_orders SET laboratory_id=? WHERE id=?")->execute([$labId, $order['id']]);
            $order['laboratory_id'] = $labId;
        }

        AuditService::log(AuditService::LAB_TOKEN_SEARCHED, [
            'user_id'    => $_SESSION['user_id'],
            'hospital_id'=> $hospitalId,
            'metadata'   => ['lab_token' => $token, 'lab_id' => $labId, 'order_id' => $order['id']],
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search Lab Token — MedCore</title>
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
.nav-right{display:flex;align-items:center;gap:.75rem}
.nav-back{font-size:.8rem;color:#334155;text-decoration:none;background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;padding:.4rem .85rem;font-weight:600;transition:all .15s}
.nav-back:hover{background:#f1f5f9;border-color:#94a3b8;color:#0f172a}
.page{max-width:960px;margin:0 auto;padding:2rem 1.5rem}
/* TOKEN SEARCH */
.search-card{background:#ffffff;border:1.5px solid #e2e8f0;border-radius:20px;padding:2rem;margin-bottom:2rem;text-align:center;box-shadow:0 2px 10px rgba(15,23,42,0.04)}
.search-card h2{font-size:1.35rem;font-weight:900;color:#0a2540;margin:0 0 .5rem}
.search-card p{color:var(--muted);font-size:.875rem;margin:0 0 1.5rem}
.token-form{display:flex;gap:.75rem;max-width:520px;margin:0 auto}
.token-input{flex:1;background:#ffffff;border:2px solid #cbd5e1;border-radius:12px;padding:.875rem 1.25rem;color:#0a2540;font-size:1.2rem;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:.1em;outline:none;text-transform:uppercase;transition:all .2s;text-align:center}
.token-input::placeholder{font-weight:500;letter-spacing:.02em;font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;color:#94a3b8}
.token-input:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(2,132,199,0.15)}
.btn-search{background:linear-gradient(135deg,#0a2540,#0284c7);border:none;border-radius:12px;padding:.875rem 1.6rem;color:#fff;font-size:.95rem;font-weight:800;cursor:pointer;transition:all .15s;white-space:nowrap;box-shadow:0 2px 8px rgba(10,37,64,0.2)}
.btn-search:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(10,37,64,0.3)}
/* ALERTS */
.alert{border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:.75rem;font-size:.875rem}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-weight:600}
/* PATIENT CARD */
.patient-card{background:linear-gradient(135deg,#f0f9ff 0%,#ffffff 100%);border:1.5px solid #bae6fd;border-radius:20px;padding:1.75rem;margin-bottom:1.5rem;box-shadow:0 4px 14px rgba(2,132,199,0.06)}
.patient-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem}
.patient-name{font-size:1.35rem;font-weight:900;color:#0a2540}
.token-badge{background:#0284c7;border-radius:10px;padding:.5rem 1.1rem;font-family:'JetBrains Mono',monospace;font-size:1.15rem;font-weight:900;color:#ffffff;letter-spacing:.08em;box-shadow:0 2px 8px rgba(2,132,199,0.25)}
.patient-meta{display:flex;flex-wrap:wrap;gap:.75rem}
.meta-pill{background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;padding:.4rem .85rem;font-size:.82rem;color:#475569;box-shadow:0 1px 2px rgba(0,0,0,0.02)}
.meta-pill strong{color:#0a2540}
/* ORDER STATUS */
.order-status-row{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
.status-chip{font-size:.8125rem;font-weight:800;border-radius:8px;padding:.4rem .9rem}
.sc-pending{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.sc-progress{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
.sc-partial{background:#faf5ff;color:#6b21a8;border:1px solid #e9d5ff}
.sc-completed{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.expiry-note{font-size:.82rem;color:#64748b;font-weight:600}
/* TESTS */
.tests-section-title{font-size:1.1rem;font-weight:900;color:#0a2540;margin:0 0 1rem;display:flex;align-items:center;gap:.5rem}
.test-card{background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:1.25rem;overflow:hidden;box-shadow:0 1px 4px rgba(15,23,42,0.03);transition:border-color .2s}
.test-card:hover{border-color:#38bdf8;box-shadow:0 4px 14px rgba(2,132,199,0.08)}
.test-head{padding:1.1rem 1.35rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;background:#ffffff}
.test-name-wrap{flex:1;min-width:0}
.test-name{font-size:1.05rem;font-weight:800;color:#0a2540}
.test-cat{font-size:.78rem;color:#64748b;margin-top:.15rem;font-weight:600}
.urgency-badge{font-size:.72rem;font-weight:800;border-radius:6px;padding:.25rem .6rem;flex-shrink:0}
.ub-routine{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}
.ub-urgent{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.ub-stat{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.test-status{font-size:.75rem;font-weight:800;border-radius:6px;padding:.25rem .6rem}
.ts-pending{background:#fef3c7;color:#92400e}
.ts-progress{background:#eff6ff;color:#1e40af}
.ts-done{background:#ecfdf5;color:#065f46}
/* RESULT SECTION */
.result-section{padding:1.1rem 1.35rem;background:#f0fdf4;border-top:1px solid #bbf7d0}
.result-label{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#166534;margin-bottom:.4rem}
.result-summary-text{font-size:.9rem;color:#14532d;margin-bottom:.5rem;font-weight:600}
.result-meta{display:flex;gap:1.2rem;flex-wrap:wrap;font-size:.78rem;color:#166534}
.result-files-count{font-size:.78rem;color:#0284c7;font-weight:800}
/* UPLOAD BUTTON */
.test-footer{padding:1rem 1.35rem;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;background:#fafafa}
.btn-upload{display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,#0a2540,#0284c7);border:none;border-radius:10px;padding:.65rem 1.4rem;color:#fff !important;font-size:.875rem;font-weight:800;text-decoration:none;cursor:pointer;transition:all .15s;box-shadow:0 2px 6px rgba(2,132,199,0.2)}
.btn-upload:hover{transform:translateY(-1px);box-shadow:0 4px 10px rgba(2,132,199,0.3)}
.btn-view-result{display:inline-flex;align-items:center;gap:.5rem;background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:.6rem 1.2rem;color:#0f172a;font-size:.85rem;font-weight:800;text-decoration:none;margin-right:.65rem;transition:all .15s}
.btn-view-result:hover{background:#f1f5f9;border-color:#94a3b8}
/* INSTRUCTIONS */
.instructions-box{background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #0284c7;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem}
.instructions-box strong{color:#0a2540}
.instructions-box p{color:#475569;margin:.25rem 0 0}
</style>
</head>
<body>
<nav class="nav">
  <a href="<?= APP_URL ?>/lab/dashboard.php" class="nav-brand">
    <div class="nav-icon">🔬</div>
    <div>
      <div class="nav-title"><?= htmlspecialchars($_SESSION['lab_name'] ?? 'Lab Portal') ?></div>
      <div class="nav-subtitle">Token Search</div>
    </div>
  </a>
  <div class="nav-right">
    <a href="<?= APP_URL ?>/lab/dashboard.php" class="nav-back">← Dashboard</a>
  </div>
</nav>

<div class="page">

  <!-- Token Search Box -->
  <div class="search-card">
    <h2>🔑 Search Lab Token</h2>
    <p>Enter the token printed on the patient's prescription to view their test orders</p>
    <form method="GET" class="token-form">
      <input type="text" name="token" class="token-input" placeholder="e.g. LT-XK9F2"
             value="<?= htmlspecialchars($token) ?>" maxlength="12" autocomplete="off" autofocus>
      <button type="submit" class="btn-search">Search →</button>
    </form>
  </div>

  <?php if ($searched && $error): ?>
  <div class="alert alert-error">⚠️ <?= $error ?></div>
  <?php endif; ?>

  <?php if ($order): ?>

  <!-- Patient Info Card -->
  <div class="patient-card">
    <div class="patient-top">
      <div>
        <div style="font-size:.75rem;color:#94a3b8;margin-bottom:.25rem">PATIENT</div>
        <div class="patient-name"><?= htmlspecialchars($order['patient_name']) ?></div>
      </div>
      <div class="token-badge"><?= htmlspecialchars($order['lab_token']) ?></div>
    </div>
    <div class="patient-meta">
      <div class="meta-pill">ID: <strong><?= htmlspecialchars($order['patient_uid']) ?></strong></div>
      <div class="meta-pill">Gender: <strong><?= htmlspecialchars($order['gender']) ?></strong></div>
      <?php $age = date_diff(new DateTime($order['date_of_birth']), new DateTime())->y; ?>
      <div class="meta-pill">Age: <strong><?= $age ?> yrs</strong></div>
      <div class="meta-pill">Blood Group: <strong><?= htmlspecialchars($order['blood_group']) ?></strong></div>
      <div class="meta-pill">Doctor: <strong>Dr. <?= htmlspecialchars($order['doctor_name']) ?></strong></div>
      <div class="meta-pill">Hospital: <strong><?= htmlspecialchars($order['hospital_name']) ?></strong></div>
      <?php if ($order['prescription_uid']): ?>
      <div class="meta-pill">Rx: <strong><?= htmlspecialchars($order['prescription_uid']) ?></strong></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Order Status -->
  <div class="order-status-row">
    <?php
      $st = $order['status'];
      $cls = $st === 'PENDING' ? 'sc-pending' : ($st === 'IN_PROGRESS' ? 'sc-progress' : ($st === 'PARTIAL' ? 'sc-partial' : 'sc-completed'));
    ?>
    <span class="status-chip <?= $cls ?>">Order: <?= str_replace('_', ' ', $st) ?></span>
    <span class="expiry-note">⏰ Token expires: <?= date('M j, Y', strtotime($order['expires_at'])) ?></span>
  </div>

  <?php if ($order['test_notes']): ?>
  <div class="instructions-box">
    <strong>📋 Doctor's Notes for Lab:</strong>
    <p><?= nl2br(htmlspecialchars($order['test_notes'])) ?></p>
  </div>
  <?php endif; ?>

  <!-- Tests -->
  <div class="tests-section-title">🧪 Test Orders (<?= count($tests) ?>)</div>

  <?php foreach ($tests as $test): ?>
  <div class="test-card">
    <div class="test-head">
      <div class="test-name-wrap">
        <div class="test-name"><?= htmlspecialchars($test['test_name']) ?></div>
        <?php if ($test['test_category']): ?>
        <div class="test-cat">📂 <?= htmlspecialchars($test['test_category']) ?></div>
        <?php endif; ?>
        <?php if ($test['instructions']): ?>
        <div class="test-cat" style="color:#94a3b8;margin-top:.2rem">📌 <?= htmlspecialchars($test['instructions']) ?></div>
        <?php endif; ?>
      </div>
      <?php
        $urg = $test['urgency'];
        $ucls = $urg === 'ROUTINE' ? 'ub-routine' : ($urg === 'URGENT' ? 'ub-urgent' : 'ub-stat');
      ?>
      <span class="urgency-badge <?= $ucls ?>"><?= $urg ?></span>
      <?php
        $ts = $test['status'];
        $tcls = $ts === 'PENDING' ? 'ts-pending' : ($ts === 'IN_PROGRESS' ? 'ts-progress' : 'ts-done');
      ?>
      <span class="test-status <?= $tcls ?>"><?= str_replace('_', ' ', $ts) ?></span>
    </div>

    <?php if ($test['result_id']): ?>
    <div class="result-section">
      <div class="result-label">Result Uploaded</div>
      <?php if ($test['result_summary']): ?>
      <div class="result-summary-text"><?= nl2br(htmlspecialchars($test['result_summary'])) ?></div>
      <?php endif; ?>
      <div class="result-meta">
        <?php if ($test['result_value']): ?><span>Value: <strong style="color:#f1f5f9"><?= htmlspecialchars($test['result_value']) ?></strong><?php if ($test['reference_range']): ?> (Ref: <?= htmlspecialchars($test['reference_range']) ?>)<?php endif; ?></span><?php endif; ?>
        <span>By: <?= htmlspecialchars($test['uploaded_by_lab'] ?? 'Lab') ?></span>
        <span><?= date('M j, g:i A', strtotime($test['result_uploaded_at'])) ?></span>
        <?php if ($test['file_count'] > 0): ?>
        <span class="result-files-count">📎 <?= $test['file_count'] ?> file<?= $test['file_count'] != 1 ? 's' : '' ?> attached</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="test-footer">
      <?php if ($test['result_id']): ?>
      <a href="<?= APP_URL ?>/lab/upload-results.php?order_id=<?= $order['id'] ?>&test_id=<?= $test['id'] ?>" class="btn-view-result">✏️ Edit / Update Findings</a>
      <a href="<?= APP_URL ?>/lab/upload-results.php?order_id=<?= $order['id'] ?>&test_id=<?= $test['id'] ?>" class="btn-upload">
        📎 Add More Files
      </a>
      <?php else: ?>
      <a href="<?= APP_URL ?>/lab/upload-results.php?order_id=<?= $order['id'] ?>&test_id=<?= $test['id'] ?>" class="btn-upload" style="background: linear-gradient(135deg, #10b981, #059669);">
        ✍️ Enter Test Findings / রেজাল্ট এন্ট্রি করুন &rarr;
      </a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; ?>

</div>
</body>
</html>
