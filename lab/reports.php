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
:root{--accent:#0ea5e9;--lab:#6366f1;--border:rgba(255,255,255,0.1)}
*{box-sizing:border-box}
body{background:#0f172a;font-family:'Inter',sans-serif;color:#f1f5f9;margin:0}
.nav{background:rgba(15,23,42,0.95);border-bottom:1px solid var(--border);padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;backdrop-filter:blur(16px)}
.nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
.nav-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--lab));display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.nav-title{font-size:1rem;font-weight:700;color:#f1f5f9}
.nav-subtitle{font-size:.7rem;color:#64748b}
.nav-back{font-size:.8rem;color:#94a3b8;text-decoration:none;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;padding:.35rem .75rem}
.page{max-width:1100px;margin:0 auto;padding:2rem 1.5rem}
.page-header{margin-bottom:1.5rem}
.page-header h1{font-size:1.5rem;font-weight:800;margin:0 0 .25rem}
.page-header p{color:#94a3b8;font-size:.875rem;margin:0}
.filter-row{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap}
.filter-btn{font-size:.8rem;font-weight:500;border-radius:8px;padding:.4rem .875rem;text-decoration:none;border:1px solid var(--border);color:#94a3b8;transition:all .15s;background:rgba(255,255,255,0.03)}
.filter-btn:hover{border-color:rgba(255,255,255,0.2);color:#f1f5f9}
.filter-btn.active{background:rgba(14,165,233,0.15);border-color:var(--accent);color:var(--accent)}
/* TABLE */
.table-wrap{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:16px;overflow:hidden}
table{width:100%;border-collapse:collapse}
th{padding:.75rem 1rem;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#64748b;text-align:left;border-bottom:1px solid var(--border)}
td{padding:.875rem 1rem;font-size:.875rem;border-bottom:1px solid rgba(255,255,255,0.04)}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,0.03)}
.token-code{font-family:monospace;font-size:.85rem;font-weight:700;color:var(--accent);background:rgba(14,165,233,0.08);border:1px solid rgba(14,165,233,0.15);border-radius:6px;padding:.15rem .5rem}
.patient-name{font-weight:600;color:#f1f5f9}
.patient-uid{font-size:.7rem;color:#64748b;display:block}
.status-chip{font-size:.7rem;font-weight:600;border-radius:6px;padding:.2rem .5rem;white-space:nowrap}
.sc-pending{background:rgba(245,158,11,0.15);color:#fbbf24}
.sc-progress{background:rgba(14,165,233,0.12);color:var(--accent)}
.sc-partial{background:rgba(168,85,247,0.12);color:#c084fc}
.sc-completed{background:rgba(34,197,94,0.12);color:#4ade80}
.sc-expired{background:rgba(100,116,139,0.12);color:#64748b}
.progress-bar{height:6px;background:rgba(255,255,255,0.07);border-radius:3px;overflow:hidden;min-width:60px}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--lab));border-radius:3px;transition:width .3s}
.action-btn{font-size:.75rem;color:var(--accent);text-decoration:none;border:1px solid rgba(14,165,233,0.25);border-radius:7px;padding:.3rem .65rem;transition:background .15s}
.action-btn:hover{background:rgba(14,165,233,0.1)}
.empty-state{text-align:center;padding:3rem 1.5rem;color:#475569}
.empty-state .empty-icon{font-size:3rem;margin-bottom:.75rem}
/* PAGINATION */
.pagination{display:flex;align-items:center;justify-content:space-between;margin-top:1.25rem;font-size:.8125rem;color:#64748b;flex-wrap:wrap;gap:.5rem}
.page-links{display:flex;gap:.35rem}
.page-link{border:1px solid var(--border);border-radius:7px;padding:.35rem .65rem;text-decoration:none;color:#94a3b8;transition:all .15s}
.page-link:hover{border-color:rgba(255,255,255,0.2);color:#f1f5f9}
.page-link.current{background:rgba(14,165,233,0.15);border-color:var(--accent);color:var(--accent);font-weight:600}
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
