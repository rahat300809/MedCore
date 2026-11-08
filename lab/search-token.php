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
    // Token search — must belong to the same hospital (security scoping)
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
        WHERE plo.lab_token = ? AND plo.hospital_id = ?
        LIMIT 1
    ");
    $stmtOrder->execute([$token, $hospitalId]);
    $order = $stmtOrder->fetch();

    if (!$order) {
        $error = "No order found for token <strong>" . htmlspecialchars($token) . "</strong> at your affiliated hospital. Verify the token or contact the prescribing doctor.";
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

        // Mark order as in_progress if still pending
        if ($order['status'] === 'PENDING') {
            $db->prepare("UPDATE prescription_lab_orders SET status='IN_PROGRESS', laboratory_id=? WHERE id=?")->execute([$labId, $order['id']]);
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
:root{--accent:#0ea5e9;--lab:#6366f1;--border:rgba(255,255,255,0.1)}
*{box-sizing:border-box}
body{background:#0f172a;font-family:'Inter',sans-serif;color:#f1f5f9;margin:0}
.nav{background:rgba(15,23,42,0.95);border-bottom:1px solid var(--border);padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;backdrop-filter:blur(16px)}
.nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
.nav-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--lab));display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.nav-title{font-size:1rem;font-weight:700;color:#f1f5f9}
.nav-subtitle{font-size:.7rem;color:#64748b}
.nav-right{display:flex;align-items:center;gap:.75rem}
.nav-back{font-size:.8rem;color:#94a3b8;text-decoration:none;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;padding:.35rem .75rem;transition:background .15s}
.nav-back:hover{background:rgba(255,255,255,0.1)}
.page{max-width:900px;margin:0 auto;padding:2rem 1.5rem}
/* TOKEN SEARCH */
.search-card{background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:20px;padding:2rem;margin-bottom:2rem;text-align:center}
.search-card h2{font-size:1.25rem;font-weight:700;margin:0 0 .5rem}
.search-card p{color:#94a3b8;font-size:.875rem;margin:0 0 1.5rem}
.token-form{display:flex;gap:.75rem;max-width:500px;margin:0 auto}
.token-input{flex:1;background:rgba(255,255,255,0.07);border:2px solid var(--border);border-radius:12px;padding:.875rem 1.25rem;color:#f1f5f9;font-size:1.1rem;font-weight:700;font-family:monospace;letter-spacing:.1em;outline:none;text-transform:uppercase;transition:border-color .2s,box-shadow .2s;text-align:center}
.token-input::placeholder{font-weight:400;letter-spacing:.02em;font-family:'Inter',sans-serif;font-size:.9rem}
.token-input:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(14,165,233,0.15)}
.btn-search{background:linear-gradient(135deg,var(--accent),var(--lab));border:none;border-radius:12px;padding:.875rem 1.5rem;color:#fff;font-size:.9375rem;font-weight:600;cursor:pointer;transition:opacity .2s,transform .15s;white-space:nowrap}
.btn-search:hover{opacity:.9;transform:translateY(-1px)}
/* ALERTS */
.alert{border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:.75rem;font-size:.875rem}
.alert-error{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);color:#fca5a5}
/* PATIENT CARD */
.patient-card{background:linear-gradient(135deg,rgba(14,165,233,0.12),rgba(99,102,241,0.12));border:1px solid rgba(14,165,233,0.2);border-radius:20px;padding:1.5rem;margin-bottom:1.5rem}
.patient-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:1rem}
.patient-name{font-size:1.3rem;font-weight:800}
.token-badge{background:rgba(14,165,233,0.15);border:2px solid rgba(14,165,233,0.35);border-radius:10px;padding:.5rem 1rem;font-family:monospace;font-size:1.1rem;font-weight:800;color:var(--accent);letter-spacing:.08em}
.patient-meta{display:flex;flex-wrap:wrap;gap:.75rem}
.meta-pill{background:rgba(255,255,255,0.06);border:1px solid var(--border);border-radius:8px;padding:.3rem .75rem;font-size:.8rem;color:#94a3b8}
.meta-pill strong{color:#f1f5f9}
/* ORDER STATUS */
.order-status-row{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
.status-chip{font-size:.8125rem;font-weight:600;border-radius:8px;padding:.35rem .85rem}
.sc-pending{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)}
.sc-progress{background:rgba(14,165,233,0.12);color:var(--accent);border:1px solid rgba(14,165,233,0.25)}
.sc-partial{background:rgba(168,85,247,0.12);color:#c084fc;border:1px solid rgba(168,85,247,0.25)}
.sc-completed{background:rgba(34,197,94,0.12);color:#4ade80;border:1px solid rgba(34,197,94,0.25)}
.expiry-note{font-size:.8rem;color:#64748b}
/* TESTS */
.tests-section-title{font-size:1rem;font-weight:700;margin:0 0 1rem;display:flex;align-items:center;gap:.5rem}
.test-card{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:16px;margin-bottom:1rem;overflow:hidden;transition:border-color .2s}
.test-card:hover{border-color:rgba(255,255,255,0.2)}
.test-head{padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap}
.test-name-wrap{flex:1;min-width:0}
.test-name{font-size:1rem;font-weight:700;color:#f1f5f9}
.test-cat{font-size:.75rem;color:#64748b;margin-top:.15rem}
.urgency-badge{font-size:.7rem;font-weight:700;border-radius:6px;padding:.2rem .55rem;flex-shrink:0}
.ub-routine{background:rgba(100,116,139,0.15);color:#94a3b8;border:1px solid rgba(100,116,139,0.25)}
.ub-urgent{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)}
.ub-stat{background:rgba(239,68,68,0.15);color:#f87171;border:1px solid rgba(239,68,68,0.3)}
.test-status{font-size:.75rem;font-weight:600;border-radius:6px;padding:.2rem .55rem}
.ts-pending{background:rgba(245,158,11,0.12);color:#fbbf24}
.ts-progress{background:rgba(14,165,233,0.12);color:var(--accent)}
.ts-done{background:rgba(34,197,94,0.12);color:#4ade80}
/* RESULT SECTION */
.result-section{padding:1rem 1.25rem;background:rgba(34,197,94,0.04);border-top:1px solid var(--border)}
.result-label{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.4rem}
.result-summary-text{font-size:.875rem;color:#e2e8f0;margin-bottom:.5rem}
.result-meta{display:flex;gap:1rem;flex-wrap:wrap;font-size:.75rem;color:#64748b}
.result-files-count{font-size:.75rem;color:var(--accent)}
/* UPLOAD BUTTON */
.test-footer{padding:.875rem 1.25rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.btn-upload{display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,var(--accent),var(--lab));border:none;border-radius:9px;padding:.6rem 1.25rem;color:#fff;font-size:.8375rem;font-weight:600;text-decoration:none;cursor:pointer;transition:opacity .2s,transform .15s}
.btn-upload:hover{opacity:.9;transform:translateY(-1px)}
.btn-view-result{display:inline-flex;align-items:center;gap:.4rem;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);border-radius:9px;padding:.55rem 1.1rem;color:#4ade80;font-size:.8rem;font-weight:600;text-decoration:none;margin-right:.5rem;transition:background .2s}
.btn-view-result:hover{background:rgba(34,197,94,0.18)}
/* INSTRUCTIONS */
.instructions-box{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem}
.instructions-box strong{color:#f1f5f9}
.instructions-box p{color:#94a3b8;margin:.25rem 0 0}
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
      <a href="<?= APP_URL ?>/lab/upload-results.php?order_id=<?= $order['id'] ?>&test_id=<?= $test['id'] ?>" class="btn-view-result">✏️ Update Result</a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/lab/upload-results.php?order_id=<?= $order['id'] ?>&test_id=<?= $test['id'] ?>" class="btn-upload">
        <?= $test['result_id'] ? '📤 Add More Files' : '📤 Upload Result' ?>
      </a>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; ?>

</div>
</body>
</html>
