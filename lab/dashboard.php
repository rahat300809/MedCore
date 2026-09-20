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
<link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/portal.css">
<style>
:root{--accent:#0ea5e9;--accent-dark:#0284c7;--lab:#6366f1;--border:rgba(255,255,255,0.1)}
*{box-sizing:border-box}
body{background:#0f172a;font-family:'Inter',sans-serif;color:#f1f5f9;margin:0}
/* NAV */
.nav{background:rgba(15,23,42,0.95);border-bottom:1px solid var(--border);padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;backdrop-filter:blur(16px)}
.nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
.nav-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--lab));display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.nav-title{font-size:1rem;font-weight:700;color:#f1f5f9}
.nav-subtitle{font-size:.7rem;color:#64748b}
.nav-right{display:flex;align-items:center;gap:.75rem}
.nav-hospital{font-size:.8rem;color:#94a3b8;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;padding:.35rem .75rem}
.btn-logout{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:.35rem .75rem;color:#f87171;font-size:.8rem;text-decoration:none;transition:background .2s}
.btn-logout:hover{background:rgba(239,68,68,0.2)}
/* LAYOUT */
.page{max-width:1280px;margin:0 auto;padding:2rem 1.5rem}
.page-header{margin-bottom:2rem}
.page-header h1{font-size:1.75rem;font-weight:800;margin:0 0 .25rem}
.page-header p{color:#94a3b8;margin:0;font-size:.9rem}
/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:2rem}
.stat-card{background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:16px;padding:1.25rem;text-align:center;transition:transform .2s,border-color .2s}
.stat-card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,0.2)}
.stat-icon{font-size:1.5rem;margin-bottom:.5rem}
.stat-val{font-size:1.75rem;font-weight:800;line-height:1}
.stat-label{font-size:.75rem;color:#64748b;margin-top:.25rem}
.stat-pending .stat-val{color:#f59e0b}
.stat-progress .stat-val{color:var(--accent)}
.stat-done .stat-val{color:#22c55e}
/* ACTION CARD */
.action-hero{background:linear-gradient(135deg,rgba(14,165,233,0.15),rgba(99,102,241,0.15));border:1px solid rgba(14,165,233,0.25);border-radius:20px;padding:2rem;margin-bottom:2rem;display:flex;align-items:center;justify-content:space-between;gap:1.5rem}
.action-text h2{font-size:1.25rem;font-weight:700;margin:0 0 .5rem}
.action-text p{color:#94a3b8;margin:0;font-size:.875rem}
.btn-primary{display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,var(--accent),var(--lab));border:none;border-radius:12px;padding:.875rem 1.75rem;color:#fff;font-size:.9375rem;font-weight:600;text-decoration:none;cursor:pointer;transition:opacity .2s,transform .15s;white-space:nowrap}
.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
/* TWO COLUMN */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
.panel{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:16px;overflow:hidden}
.panel-head{padding:1rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.panel-head h3{font-size:.9375rem;font-weight:700;margin:0}
.panel-link{font-size:.8rem;color:var(--accent);text-decoration:none}
.panel-link:hover{text-decoration:underline}
/* ORDER ROW */
.order-row{padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.05);transition:background .15s;display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.order-row:last-child{border-bottom:none}
.order-row:hover{background:rgba(255,255,255,0.03)}
.order-info{flex:1;min-width:0}
.order-token{font-size:.75rem;font-weight:700;color:var(--accent);background:rgba(14,165,233,0.1);border:1px solid rgba(14,165,233,0.2);border-radius:6px;padding:.15rem .5rem;font-family:monospace;display:inline-block;margin-bottom:.25rem}
.order-patient{font-size:.875rem;font-weight:600;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.order-meta{font-size:.75rem;color:#64748b}
.order-badge{font-size:.7rem;font-weight:600;border-radius:6px;padding:.2rem .5rem;flex-shrink:0}
.badge-pending{background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3)}
.badge-progress{background:rgba(14,165,233,0.12);color:var(--accent);border:1px solid rgba(14,165,233,0.25)}
.badge-partial{background:rgba(168,85,247,0.12);color:#c084fc;border:1px solid rgba(168,85,247,0.25)}
.btn-sm{font-size:.75rem;padding:.3rem .65rem;border-radius:7px;text-decoration:none;border:1px solid var(--border);color:#94a3b8;transition:background .15s,color .15s}
.btn-sm:hover{background:rgba(255,255,255,0.08);color:#f1f5f9}
/* UPLOAD ROW */
.upload-row{padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:flex-start;gap:.75rem}
.upload-row:last-child{border-bottom:none}
.upload-icon{width:36px;height:36px;border-radius:9px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.upload-body{flex:1;min-width:0}
.upload-test{font-size:.875rem;font-weight:600;color:#f1f5f9}
.upload-patient{font-size:.75rem;color:#64748b}
.upload-time{font-size:.7rem;color:#475569;margin-top:.15rem}
.result-badge{font-size:.65rem;font-weight:600;border-radius:5px;padding:.15rem .45rem;flex-shrink:0}
.rb-normal{background:rgba(34,197,94,0.12);color:#4ade80;border:1px solid rgba(34,197,94,0.2)}
.rb-abnormal{background:rgba(245,158,11,0.12);color:#fbbf24;border:1px solid rgba(245,158,11,0.2)}
.rb-critical{background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.2)}
.rb-pending{background:rgba(100,116,139,0.12);color:#94a3b8;border:1px solid rgba(100,116,139,0.2)}
.empty-state{padding:2.5rem 1.5rem;text-align:center;color:#475569;font-size:.875rem}
.empty-state .empty-icon{font-size:2rem;margin-bottom:.5rem}
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
      <h2>🔑 Enter Lab Token</h2>
      <p>A patient brings a prescription with a Lab Token (e.g. <code style="background:rgba(14,165,233,0.1);padding:.1rem .4rem;border-radius:4px;font-family:monospace">LT-XK9F2</code>). Search it to see their test orders and upload results.</p>
    </div>
    <a href="<?= APP_URL ?>/lab/search-token.php" class="btn-primary">🔍 Search Token</a>
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
