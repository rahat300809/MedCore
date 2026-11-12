<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireLabContext();

$db         = getDB();
$labId      = (int)$_SESSION['lab_id'];
$hospitalId = (int)$_SESSION['hospital_id'];

$stmtLab = $db->prepare("
    SELECT l.*, h.name AS hospital_name
    FROM laboratories l
    JOIN hospital_laboratories hl ON hl.laboratory_id = l.id
    JOIN hospitals h ON h.id = hl.hospital_id
    WHERE l.id = ? AND hl.hospital_id = ?
");
$stmtLab->execute([$labId, $hospitalId]);
$lab = $stmtLab->fetch();

$stmtStats = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM prescription_lab_orders WHERE hospital_id = :h1 AND status = 'PENDING') AS pending_orders,
        (SELECT COUNT(*) FROM prescription_lab_orders WHERE hospital_id = :h2 AND status IN ('IN_PROGRESS','PARTIAL')) AS in_progress,
        (SELECT COUNT(*) FROM prescription_lab_orders WHERE laboratory_id = :l1 AND status = 'COMPLETED') AS completed_by_lab,
        (SELECT COUNT(*) FROM prescription_lab_orders WHERE hospital_id = :h3 AND DATE(created_at) = CURDATE()) AS today_orders,
        (SELECT COUNT(*) FROM lab_test_results WHERE laboratory_id = :l2 AND DATE(uploaded_at) = CURDATE()) AS today_uploads
");
$stmtStats->execute([':h1'=>$hospitalId,':h2'=>$hospitalId,':l1'=>$labId,':h3'=>$hospitalId,':l2'=>$labId]);
$stats = $stmtStats->fetch() ?: [];

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

$portalRole = 'Laboratory';
$portalRoleBn = 'Lab portal';
$portalUser = $lab['name'] ?? ($_SESSION['lab_name'] ?? 'Lab');
$portalMeta = ($_SESSION['lab_uid'] ?? '') . ' · ' . ($_SESSION['hospital_name'] ?? '');
$portalTitle = 'Lab overview';
$portalSubtitle = 'টোকেন খুঁজুন, টেস্ট দেখুন, রিপোর্ট আপলোড করুন';
$portalNav = mc_lab_nav('dashboard');
$portalTopAction = '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/lab/search-token.php">Search token</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Pending</div><div class="v"><?= (int)($stats['pending_orders'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">In progress</div><div class="v"><?= (int)($stats['in_progress'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Completed</div><div class="v"><?= (int)($stats['completed_by_lab'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Uploads today</div><div class="v"><?= (int)($stats['today_uploads'] ?? 0) ?></div></div>
</div>

<div class="lab-token-bar">
  <label for="dash-token">Open order by token</label>
  <form method="GET" action="<?= APP_URL ?>/lab/search-token.php" class="lab-token-form">
    <input id="dash-token" type="text" name="token" required maxlength="12" placeholder="LT-XXXXX">
    <button class="mc-btn mc-btn-primary" type="submit">Look up</button>
    <a class="mc-btn mc-btn-secondary" href="<?= APP_URL ?>/lab/reports.php">All orders</a>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Open orders</h2>
    <a href="<?= APP_URL ?>/lab/reports.php" style="font-weight:800;color:var(--accent);font-size:.88rem">View all</a>
  </div>
  <?php if (empty($pendingOrders)): ?>
    <p style="color:var(--muted);margin:0">No open orders right now.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Token</th>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Tests</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingOrders as $order):
            $oCls = $order['status'] === 'COMPLETED' ? 'ok' : ($order['status'] === 'IN_PROGRESS' ? 'info' : 'warn');
          ?>
            <tr>
              <td class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($order['lab_token']) ?></td>
              <td>
                <strong><?= htmlspecialchars($order['patient_name']) ?></strong>
                <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($order['patient_uid']) ?></div>
              </td>
              <td>Dr. <?= htmlspecialchars(preg_replace('/^Dr\.?\s+/i', '', (string)$order['doctor_name'])) ?></td>
              <td><?= (int)$order['test_count'] ?></td>
              <td><span class="lab-status <?= $oCls ?>"><?= htmlspecialchars(str_replace('_', ' ', $order['status'])) ?></span></td>
              <td><a class="mc-btn mc-btn-secondary mc-btn-sm" href="<?= APP_URL ?>/lab/search-token.php?token=<?= urlencode($order['lab_token']) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Recent uploads</h2>
  </div>
  <?php if (empty($recentUploads)): ?>
    <p style="color:var(--muted);margin:0">No uploads yet.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Test</th>
            <th>Patient</th>
            <th>Token</th>
            <th>Status</th>
            <th>When</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentUploads as $upload):
            $rs = $upload['result_status'] ?? '';
            $rCls = $rs === 'NORMAL' ? 'ok' : ($rs === 'CRITICAL' ? 'danger' : ($rs === 'ABNORMAL' ? 'warn' : 'info'));
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($upload['test_name']) ?></strong></td>
              <td><?= htmlspecialchars($upload['patient_name']) ?></td>
              <td class="mc-mono"><?= htmlspecialchars($upload['lab_token']) ?></td>
              <td><span class="lab-status <?= $rCls ?>"><?= htmlspecialchars(str_replace('_', ' ', $rs ?: '—')) ?></span></td>
              <td style="color:var(--muted);font-size:.85rem"><?= date('d M, g:i A', strtotime($upload['uploaded_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
