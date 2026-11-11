<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireLabContext();

$db         = getDB();
$labId      = (int)$_SESSION['lab_id'];
$hospitalId = (int)$_SESSION['hospital_id'];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;
$status  = $_GET['status'] ?? 'all';

$where = "(plo.laboratory_id = :l OR plo.hospital_id = :h)";
$params = [':l' => $labId, ':h' => $hospitalId];

if ($status !== 'all') {
    $where .= " AND plo.status = :st";
    $params[':st'] = strtoupper($status);
}

$stmtCount = $db->prepare("
    SELECT COUNT(DISTINCT plo.id) FROM prescription_lab_orders plo WHERE {$where}
");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmtOrders = $db->prepare("
    SELECT plo.*,
           p.full_name AS patient_name, p.patient_uid,
           d.full_name AS doctor_name,
           l.name AS lab_name,
           COUNT(DISTINCT lot.id) AS total_tests,
           COUNT(DISTINCT CASE WHEN lot.status = 'COMPLETED' THEN lot.id END) AS done_tests
    FROM prescription_lab_orders plo
    JOIN patients p ON plo.patient_id = p.id
    JOIN doctors d ON plo.doctor_id = d.id
    LEFT JOIN laboratories l ON plo.laboratory_id = l.id
    LEFT JOIN lab_order_tests lot ON lot.lab_order_id = plo.id
    WHERE {$where}
    GROUP BY plo.id
    ORDER BY plo.created_at DESC
    LIMIT :lim OFFSET :off
");
$params[':lim'] = $perPage;
$params[':off'] = $offset;
$stmtOrders->execute($params);
$orders = $stmtOrders->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lab Reports History — MedCore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
.page{max-width:1160px;margin:0 auto;padding:2rem 1.5rem}
.page-header{margin-bottom:1.5rem}
.page-header h1{font-size:1.5rem;font-weight:900;color:#0a2540;margin:0 0 .25rem}
.page-header p{color:var(--muted);font-size:.875rem;margin:0}
.filter-row{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap}
.filter-btn{font-size:.8rem;font-weight:700;border-radius:8px;padding:.45rem .95rem;text-decoration:none;border:1px solid #cbd5e1;color:#475569;transition:all .15s;background:#ffffff}
.filter-btn:hover{border-color:#94a3b8;color:#0f172a;background:#f8fafc}
.filter-btn.active{background:#eff6ff;border-color:#38bdf8;color:#0284c7}
/* TABLE */
.table-wrap{background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(15,23,42,0.03)}
table{width:100%;border-collapse:collapse}
th{padding:.85rem 1rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;text-align:left;background:#f8fafc;border-bottom:1px solid #e2e8f0}
td{padding:.875rem 1rem;font-size:.875rem;border-bottom:1px solid #f1f5f9;color:#1e293b}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f8fafc}
.token-code{font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:800;color:#0284c7;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:.2rem .5rem}
.patient-name{font-weight:700;color:#0f172a;display:block}
.patient-uid{font-size:.72rem;color:#64748b;font-weight:600}
.status-chip{font-size:.72rem;font-weight:800;border-radius:6px;padding:.25rem .6rem;white-space:nowrap;display:inline-block}
.sc-pending{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.sc-progress{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
.sc-partial{background:#faf5ff;color:#6b21a8;border:1px solid #e9d5ff}
.sc-completed{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.sc-expired{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}
.progress-bar{height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;min-width:60px}
.progress-fill{height:100%;background:linear-gradient(90deg,#0284c7,#7c3aed);border-radius:3px;transition:width .3s}
.action-btn{font-size:.75rem;font-weight:800;color:#0284c7;text-decoration:none;border:1px solid #bae6fd;border-radius:7px;padding:.35rem .75rem;background:#f0f9ff;transition:all .15s}
.action-btn:hover{background:#0284c7;color:#ffffff;border-color:#0284c7}
.empty-state{text-align:center;padding:3rem 1.5rem;color:#64748b}
.empty-state .empty-icon{font-size:3rem;margin-bottom:.75rem}
/* PAGINATION */
.pagination{display:flex;align-items:center;justify-content:space-between;margin-top:1.25rem;font-size:.8125rem;color:#64748b;flex-wrap:wrap;gap:.5rem}
.page-links{display:flex;gap:.35rem}
.page-link{border:1px solid #cbd5e1;border-radius:7px;padding:.35rem .65rem;text-decoration:none;color:#475569;transition:all .15s;background:#ffffff;font-weight:600}
.page-link:hover{border-color:#94a3b8;color:#0f172a;background:#f8fafc}
.page-link.current{background:#eff6ff;border-color:#0284c7;color:#0284c7;font-weight:800}
</style>
</head>
<body>
<nav class="nav">
  <a href="<?= APP_URL ?>/lab/dashboard.php" class="nav-brand">
    <div class="nav-icon">🔬</div>
    <div>
      <div class="nav-title"><?= htmlspecialchars($_SESSION['lab_name'] ?? 'Lab Portal') ?></div>
      <div class="nav-subtitle">Reports History</div>
    </div>
  </a>
  <div class="nav-right">
    <a href="<?= APP_URL ?>/lab/dashboard.php" class="nav-back">← Dashboard</a>
    <a href="<?= APP_URL ?>/lab/search-token.php" class="nav-back" style="color:var(--accent);border-color:rgba(14,165,233,0.3)">🔍 Search Token</a>
  </div>
</nav>

<div class="page">
  <div class="page-header">
    <h1>📋 Lab Orders — <?= htmlspecialchars($_SESSION['hospital_name'] ?? '') ?></h1>
    <p>All lab test orders for your affiliated hospital · <?= $total ?> total</p>
  </div>

  <!-- Filters -->
  <div class="filter-row">
    <?php
    $statuses = ['all' => 'All', 'pending' => 'Pending', 'in_progress' => 'In Progress', 'partial' => 'Partial', 'completed' => 'Completed', 'expired' => 'Expired'];
    foreach ($statuses as $sv => $sl):
    ?>
    <a href="?status=<?= $sv ?>" class="filter-btn <?= $status === $sv ? 'active' : '' ?>"><?= $sl ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <?php if (empty($orders)): ?>
    <div class="empty-state">
      <div class="empty-icon">📂</div>
      <div>No orders found<?= $status !== 'all' ? ' with status "'.htmlspecialchars($status).'"' : '' ?>.</div>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Token</th>
          <th>Patient</th>
          <th>Doctor</th>
          <th>Tests</th>
          <th>Status</th>
          <th>Expires</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $ord): ?>
        <?php
          $st = $ord['status'];
          $stcls = match($st) {
              'PENDING'     => 'sc-pending',
              'IN_PROGRESS' => 'sc-progress',
              'PARTIAL'     => 'sc-partial',
              'COMPLETED'   => 'sc-completed',
              default       => 'sc-expired',
          };
          $pct = $ord['total_tests'] > 0 ? round(($ord['done_tests'] / $ord['total_tests']) * 100) : 0;
        ?>
        <tr>
          <td><span class="token-code"><?= htmlspecialchars($ord['lab_token']) ?></span></td>
          <td>
            <span class="patient-name"><?= htmlspecialchars($ord['patient_name']) ?></span>
            <span class="patient-uid"><?= htmlspecialchars($ord['patient_uid']) ?></span>
          </td>
          <td style="color:#94a3b8;font-size:.8rem">Dr. <?= htmlspecialchars($ord['doctor_name']) ?></td>
          <td>
            <div style="font-size:.8rem;margin-bottom:.3rem"><?= $ord['done_tests'] ?>/<?= $ord['total_tests'] ?> done</div>
            <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
          </td>
          <td><span class="status-chip <?= $stcls ?>"><?= str_replace('_',' ',$st) ?></span></td>
          <td style="font-size:.78rem;color:<?= strtotime($ord['expires_at']) < time() ? '#f87171' : '#64748b' ?>">
            <?= date('M j, Y', strtotime($ord['expires_at'])) ?>
          </td>
          <td style="font-size:.78rem;color:#64748b"><?= date('M j', strtotime($ord['created_at'])) ?></td>
          <td>
            <a href="<?= APP_URL ?>/lab/search-token.php?token=<?= urlencode($ord['lab_token']) ?>" class="action-btn">Open →</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <span>Showing <?= $offset+1 ?>–<?= min($offset+$perPage, $total) ?> of <?= $total ?></span>
    <div class="page-links">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>" class="page-link <?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
