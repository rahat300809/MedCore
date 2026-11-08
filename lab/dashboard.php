<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireLabContext();

$db         = getDB();
$labId      = (int)$_SESSION['lab_id'];
$hospitalId = (int)$_SESSION['hospital_id'];

// Lab profile
$stmtLab = $db->prepare("SELECT l.*, h.name AS hospital_name FROM laboratories l JOIN hospital_laboratories hl ON hl.laboratory_id = l.id JOIN hospitals h ON h.id = hl.hospital_id WHERE l.id = ? AND hl.hospital_id = ?");
$stmtLab->execute([$labId, $hospitalId]);
$lab = $stmtLab->fetch();

// Stats
$stmtStats = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM prescription_lab_orders WHERE hospital_id = :h1 AND status = 'PENDING') AS pending_orders,
        (SELECT COUNT(*) FROM prescription_lab_orders WHERE hospital_id = :h2 AND status IN ('IN_PROGRESS','PARTIAL')) AS in_progress,
        (SELECT COUNT(*) FROM prescription_lab_orders WHERE laboratory_id = :l1 AND status = 'COMPLETED') AS completed_by_lab,
        (SELECT COUNT(*) FROM prescription_lab_orders WHERE hospital_id = :h3 AND DATE(created_at) = CURDATE()) AS today_orders,
        (SELECT COUNT(*) FROM lab_test_results WHERE laboratory_id = :l2 AND DATE(uploaded_at) = CURDATE()) AS today_uploads
");
$stmtStats->execute([':h1'=>$hospitalId,':h2'=>$hospitalId,':l1'=>$labId,':h3'=>$hospitalId,':l2'=>$labId]);
$stats = $stmtStats->fetch();

// Recent pending orders (hospital-scoped, available to this lab)
$stmtOrders = $db->prepare("
    SELECT plo.*, p.full_name AS patient_name, p.patient_uid,
           d.full_name AS doctor_name,
           COUNT(lot.id) AS test_count
    FROM prescription_lab_orders plo
    JOIN patients p ON plo.patient_id = p.id
    JOIN doctors d ON plo.doctor_id = d.id
    LEFT JOIN lab_order_tests lot ON lot.lab_order_id = plo.id
    WHERE plo.hospital_id = ? AND plo.status IN ('PENDING','IN_PROGRESS','PARTIAL')
      AND plo.expires_at > NOW()
    GROUP BY plo.id
    ORDER BY plo.created_at DESC
    LIMIT 8
");
$stmtOrders->execute([$hospitalId]);
$pendingOrders = $stmtOrders->fetchAll();

// Recent uploads by this lab
$stmtRecent = $db->prepare("
    SELECT ltr.*, lot.test_name, lot.test_category,
           p.full_name AS patient_name, p.patient_uid,
           plo.lab_token
    FROM lab_test_results ltr
    JOIN lab_order_tests lot ON ltr.lab_order_test_id = lot.id
    JOIN prescription_lab_orders plo ON lot.lab_order_id = plo.id
    JOIN patients p ON plo.patient_id = p.id
    WHERE ltr.laboratory_id = ?
    ORDER BY ltr.uploaded_at DESC
    LIMIT 6
");
$stmtRecent->execute([$labId]);
$recentUploads = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lab Dashboard — <?= htmlspecialchars($lab['name'] ?? 'Lab') ?> — MedCore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
<style>
:root{--accent:#2563eb;--lab:#7c3aed;--border:#e2e8f0}
*{box-sizing:border-box}
body{background:#f8fafc;font-family:'Inter',sans-serif;color:#0f172a;margin:0}
/* NAV */
.nav{background:#ffffff;border-bottom:1px solid #e2e8f0;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;box-shadow:0 1px 3px rgba(0,0,0,0.04)}
.nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
.nav-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:white}
.nav-title{font-size:1rem;font-weight:700;color:#0f172a}
.nav-subtitle{font-size:.75rem;color:#64748b}
.nav-right{display:flex;align-items:center;gap:.75rem}
.nav-hospital{font-size:.8rem;color:#2563eb;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.35rem .75rem;font-weight:600}
.btn-logout{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.35rem .75rem;color:#dc2626;font-size:.8rem;text-decoration:none;font-weight:600;transition:background .2s}
.btn-logout:hover{background:#fee2e2}
/* LAYOUT */
.page{max-width:1280px;margin:0 auto;padding:2rem 1.5rem}
.page-header{margin-bottom:2rem}
.page-header h1{font-size:1.6rem;font-weight:800;color:#0f172a;margin:0 0 .25rem}
.page-header p{color:#64748b;margin:0;font-size:.9rem}
/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:2rem}
.stat-card{background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:1.25rem;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.03);transition:transform .2s,box-shadow .2s}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.06)}
.stat-icon{font-size:1.5rem;margin-bottom:.5rem}
.stat-val{font-size:1.85rem;font-weight:800;line-height:1;color:#0f172a}
.stat-label{font-size:.78rem;color:#64748b;margin-top:.35rem;font-weight:600}
.stat-pending .stat-val{color:#d97706}
.stat-progress .stat-val{color:#2563eb}
.stat-done .stat-val{color:#16a34a}
/* ACTION CARD */
.action-hero{background:#ffffff;border:1px solid #e2e8f0;border-left:4px solid #7c3aed;border-radius:16px;padding:1.75rem;margin-bottom:2rem;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.04)}
.action-text h2{font-size:1.2rem;font-weight:800;color:#0f172a;margin:0 0 .35rem}
.action-text p{color:#64748b;margin:0;font-size:.875rem}
.btn-primary{display:inline-flex;align-items:center;gap:.5rem;background:#2563eb;border:none;border-radius:10px;padding:.75rem 1.5rem;color:#fff;font-size:.9rem;font-weight:600;text-decoration:none;cursor:pointer;transition:background .2s;white-space:nowrap}
.btn-primary:hover{background:#1d4ed8}
/* TWO COLUMN */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
.panel{background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.03)}
.panel-head{padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc}
.panel-head h3{font-size:.9375rem;font-weight:700;color:#0f172a;margin:0}
.panel-link{font-size:.8rem;color:#2563eb;text-decoration:none;font-weight:600}
.panel-link:hover{text-decoration:underline}
/* ORDER ROW */
.order-row{padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;transition:background .15s;display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.order-row:last-child{border-bottom:none}
.order-row:hover{background:#f8fafc}
.order-info{flex:1;min-width:0}
.order-token{font-size:.78rem;font-weight:700;color:#6d28d9;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;padding:.2rem .5rem;font-family:monospace;display:inline-block;margin-bottom:.25rem}
.order-patient{font-size:.9rem;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.order-meta{font-size:.78rem;color:#64748b}
.order-badge{font-size:.7rem;font-weight:700;border-radius:6px;padding:.2rem .5rem;flex-shrink:0}
.badge-pending{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.badge-progress{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
.badge-partial{background:#faf5ff;color:#6b21a8;border:1px solid #e9d5ff}
.btn-sm{font-size:.75rem;padding:.35rem .75rem;border-radius:7px;text-decoration:none;border:1px solid #cbd5e1;color:#334155;background:#ffffff;font-weight:600;transition:background .15s}
.btn-sm:hover{background:#f1f5f9;color:#0f172a}
/* UPLOAD ROW */
.upload-row{padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:flex-start;gap:.75rem}
.upload-row:last-child{border-bottom:none}
.upload-icon{width:36px;height:36px;border-radius:9px;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.upload-body{flex:1;min-width:0}
.upload-test{font-size:.875rem;font-weight:700;color:#0f172a}
.upload-patient{font-size:.78rem;color:#64748b}
.upload-time{font-size:.72rem;color:#94a3b8;margin-top:.15rem}
.result-badge{font-size:.68rem;font-weight:700;border-radius:5px;padding:.15rem .5rem;flex-shrink:0}
.rb-normal{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.rb-abnormal{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.rb-critical{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.rb-pending{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}
.empty-state{padding:2.5rem 1.5rem;text-align:center;color:#64748b;font-size:.875rem}
.empty-state .empty-icon{font-size:2rem;margin-bottom:.5rem;color:#94a3b8}
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(3,1fr)}.two-col{grid-template-columns:1fr}.action-hero{flex-direction:column;text-align:center}}
@media(max-width:560px){.stats-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<!-- Navigation -->
<nav class="nav">
  <a href="<?= APP_URL ?>/lab/dashboard.php" class="nav-brand">
    <div class="nav-icon">🔬</div>
    <div>
      <div class="nav-title"><?= htmlspecialchars($lab['name'] ?? 'Lab Portal') ?></div>
      <div class="nav-subtitle"><?= htmlspecialchars($lab['lab_uid'] ?? '') ?></div>
    </div>
  </a>
  <div class="nav-right">
    <span class="nav-hospital">🏥 <?= htmlspecialchars($_SESSION['hospital_name'] ?? '') ?></span>
    <a href="?logout=1" class="btn-logout">Logout</a>
  </div>
</nav>

<?php
if (isset($_GET['logout'])) {
    logout();
}
?>

<div class="page">
  <!-- Page Header -->
  <div class="page-header">
    <h1>Good <?= (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening')) ?> 👋</h1>
    <p>Here's your lab overview for today, <?= date('l, F j, Y') ?></p>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card stat-pending">
      <div class="stat-icon">⏳</div>
      <div class="stat-val"><?= $stats['pending_orders'] ?></div>
      <div class="stat-label">Pending Orders</div>
    </div>
    <div class="stat-card stat-progress">
      <div class="stat-icon">🔄</div>
      <div class="stat-val"><?= $stats['in_progress'] ?></div>
      <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-card stat-done">
      <div class="stat-icon">✅</div>
      <div class="stat-val"><?= $stats['completed_by_lab'] ?></div>
      <div class="stat-label">Completed by Lab</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📋</div>
      <div class="stat-val" style="color:#c084fc"><?= $stats['today_orders'] ?></div>
      <div class="stat-label">Today's Orders</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📤</div>
      <div class="stat-val" style="color:#38bdf8"><?= $stats['today_uploads'] ?></div>
      <div class="stat-label">Uploads Today</div>
    </div>
  </div>

  <!-- Action Hero -->
  <div class="action-hero">
    <div class="action-text">
      <h2><i class="bi bi-qr-code-scan" style="color:#7c3aed;"></i> Fast Lab Token Search</h2>
      <p>Enter the patient's short token (e.g. <strong>LT-8K2A9</strong>) to retrieve ordered tests and upload results immediately.</p>
    </div>
    <form method="GET" action="<?= APP_URL ?>/lab/search-token.php" style="display:flex; gap:10px; flex-wrap:wrap; margin:0;">
      <input type="text" name="token" class="form-control" placeholder="Enter Token (LT-XXXXX)..." style="width:220px; height:44px; font-family:var(--font-mono); font-weight:700; text-transform:uppercase; letter-spacing:1px;" required>
      <button type="submit" class="btn-primary" style="height:44px;">
        <i class="bi bi-search"></i> Look Up Token
      </button>
    </form>
  </div>

  <!-- Two Column -->
  <div class="two-col">

    <!-- Pending Orders -->
    <div class="panel">
      <div class="panel-head">
        <h3>⏳ Pending Orders (Hospital)</h3>
        <a href="<?= APP_URL ?>/lab/reports.php" class="panel-link">View All →</a>
      </div>
      <?php if (empty($pendingOrders)): ?>
      <div class="empty-state"><div class="empty-icon">🎉</div>No pending orders right now</div>
      <?php else: ?>
      <?php foreach ($pendingOrders as $order): ?>
      <div class="order-row">
        <div class="order-info">
          <div class="order-token"><?= htmlspecialchars($order['lab_token']) ?></div>
          <div class="order-patient"><?= htmlspecialchars($order['patient_name']) ?> <span style="color:#64748b;font-weight:400">(<?= htmlspecialchars($order['patient_uid']) ?>)</span></div>
          <div class="order-meta">Dr. <?= htmlspecialchars($order['doctor_name']) ?> · <?= (int)$order['test_count'] ?> test<?= $order['test_count'] != 1 ? 's' : '' ?> · <?= date('M j', strtotime($order['created_at'])) ?></div>
        </div>
        <?php
          $st = $order['status'];
          $cls = $st === 'PENDING' ? 'badge-pending' : ($st === 'IN_PROGRESS' ? 'badge-progress' : 'badge-partial');
        ?>
        <span class="order-badge <?= $cls ?>"><?= $st ?></span>
        <a href="<?= APP_URL ?>/lab/search-token.php?token=<?= urlencode($order['lab_token']) ?>" class="btn-sm">Open →</a>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Recent Uploads -->
    <div class="panel">
      <div class="panel-head">
        <h3>📤 Recent Uploads</h3>
        <a href="<?= APP_URL ?>/lab/reports.php" class="panel-link">History →</a>
      </div>
      <?php if (empty($recentUploads)): ?>
      <div class="empty-state"><div class="empty-icon">📂</div>No uploads yet. Start by searching a token.</div>
      <?php else: ?>
      <?php foreach ($recentUploads as $upload): ?>
      <?php
        $rs = $upload['result_status'];
        $rbClass = $rs === 'NORMAL' ? 'rb-normal' : ($rs === 'ABNORMAL' ? 'rb-abnormal' : ($rs === 'CRITICAL' ? 'rb-critical' : 'rb-pending'));
      ?>
      <div class="upload-row">
        <div class="upload-icon">📊</div>
        <div class="upload-body">
          <div class="upload-test"><?= htmlspecialchars($upload['test_name']) ?> <span class="result-badge <?= $rbClass ?>"><?= $rs ?></span></div>
          <div class="upload-patient"><?= htmlspecialchars($upload['patient_name']) ?> · Token: <span style="font-family:monospace;color:var(--accent)"><?= htmlspecialchars($upload['lab_token']) ?></span></div>
          <div class="upload-time"><?= date('M j, g:i A', strtotime($upload['uploaded_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

</body>
</html>
