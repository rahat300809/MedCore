<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

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
$totalPages = max(1, (int)ceil($total / $perPage));

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

$portalRole = 'Laboratory';
$portalRoleBn = 'Lab portal';
$portalUser = $_SESSION['lab_name'] ?? 'Lab';
$portalMeta = ($_SESSION['lab_uid'] ?? '') . ' · Orders';
$portalTitle = 'Lab orders';
$portalSubtitle = ($_SESSION['hospital_name'] ?? 'Hospital') . ' · ' . $total . ' total';
$portalNav = mc_lab_nav('reports');
$portalTopAction = '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/lab/search-token.php">Search token</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';

$statuses = [
    'all' => 'All',
    'pending' => 'Pending',
    'in_progress' => 'In progress',
    'partial' => 'Partial',
    'completed' => 'Completed',
    'expired' => 'Expired',
];
?>

<div class="mc-tabs">
  <?php foreach ($statuses as $sv => $sl): ?>
    <a href="?status=<?= urlencode($sv) ?>"
       class="mc-tab <?= $status === $sv ? 'active' : '' ?>"><?= htmlspecialchars($sl) ?></a>
  <?php endforeach; ?>
</div>

<?php if (empty($orders)): ?>
  <div class="mc-panel" style="text-align:center;padding:2.2rem 1rem">
    <h2>No orders found</h2>
    <p style="color:var(--muted)">এই ফিল্টারে কোনো ল্যাব অর্ডার নেই।</p>
  </div>
<?php else: ?>
  <div class="mc-list">
    <?php foreach ($orders as $ord):
      $doc = preg_replace('/^Dr\.?\s+/i', '', (string)$ord['doctor_name']);
      $pct = $ord['total_tests'] > 0 ? round(($ord['done_tests'] / $ord['total_tests']) * 100) : 0;
      $oCls = $ord['status'] === 'COMPLETED' ? 'ok' : ($ord['status'] === 'EXPIRED' ? 'danger' : ($ord['status'] === 'IN_PROGRESS' ? 'info' : 'warn'));
    ?>
      <a class="mc-list-item" href="<?= APP_URL ?>/lab/search-token.php?token=<?= urlencode($ord['lab_token']) ?>" style="background:#fff">
        <div style="flex:1;min-width:0">
          <div class="mc-mono" style="color:var(--accent);font-size:.8rem"><?= htmlspecialchars($ord['lab_token']) ?></div>
          <strong><?= htmlspecialchars($ord['patient_name']) ?></strong>
          <div style="font-size:.86rem;color:var(--muted)">
            <?= htmlspecialchars($ord['patient_uid']) ?>
            · Dr. <?= htmlspecialchars($doc) ?>
            · <?= (int)$ord['done_tests'] ?>/<?= (int)$ord['total_tests'] ?> tests
            · <?= date('d M Y', strtotime($ord['created_at'])) ?>
          </div>
          <div style="margin-top:.45rem;height:6px;background:#e8eef3;border-radius:99px;overflow:hidden;max-width:220px">
            <div style="height:100%;width:<?= (int)$pct ?>%;background:var(--accent)"></div>
          </div>
        </div>
        <span class="lab-status <?= $oCls ?>"><?= htmlspecialchars(str_replace('_', ' ', $ord['status'])) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
      <span style="font-size:.85rem;color:var(--muted)">
        Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?>
      </span>
      <div style="display:flex;gap:.35rem;flex-wrap:wrap">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>"
             class="mc-btn <?= $i === $page ? 'mc-btn-primary' : 'mc-btn-secondary' ?> mc-btn-sm"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
