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

$token  = strtoupper(trim($_GET['token'] ?? $_POST['token'] ?? ''));
$order  = null;
$tests  = [];
$error  = '';
$searched = !empty($token);

if (!empty($_GET['error']) && $_GET['error'] === 'not_found') {
    $error = 'Order not found or not accessible.';
}

if ($searched) {
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
        $error = 'No diagnostic order found for token ' . htmlspecialchars($token) . '. Verify the token or contact the prescribing doctor.';
    } elseif ($order['status'] === 'EXPIRED' || strtotime($order['expires_at']) < time()) {
        $error = 'This token has expired. The lab order is no longer valid.';
        $order = null;
    } elseif ($order['status'] === 'CANCELLED') {
        $error = 'This lab order has been cancelled by the doctor.';
        $order = null;
    } else {
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

        if ($order['status'] === 'PENDING') {
            $db->prepare("UPDATE prescription_lab_orders SET status='IN_PROGRESS', laboratory_id=? WHERE id=?")->execute([$labId, $order['id']]);
            $order['status'] = 'IN_PROGRESS';
        } elseif (empty($order['laboratory_id'])) {
            $db->prepare("UPDATE prescription_lab_orders SET laboratory_id=? WHERE id=?")->execute([$labId, $order['id']]);
            $order['laboratory_id'] = $labId;
        }

        AuditService::log(AuditService::LAB_TOKEN_SEARCHED, [
            'user_id'     => $_SESSION['user_id'],
            'hospital_id' => $hospitalId,
            'metadata'    => ['lab_token' => $token, 'lab_id' => $labId, 'order_id' => $order['id']],
        ]);
    }
}

$portalRole = 'Laboratory';
$portalRoleBn = 'Lab portal';
$portalUser = $_SESSION['lab_name'] ?? 'Lab';
$portalMeta = ($_SESSION['lab_uid'] ?? '') . ' · Token search';
$portalTitle = 'Token search';
$portalSubtitle = 'LT টোকেন দিয়ে টেস্ট অর্ডার খুলুন';
$portalNav = mc_lab_nav('search');
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>

<?php if ($flash): ?>
  <div class="mc-alert <?= ($flash['type'] ?? '') === 'success' ? 'mc-alert-ok' : 'mc-alert-danger' ?>">
    <?= htmlspecialchars($flash['msg'] ?? '') ?>
  </div>
<?php endif; ?>

<div class="lab-token-bar">
  <label for="token">Lab token</label>
  <form method="GET" class="lab-token-form">
    <input id="token" type="text" name="token" maxlength="12" autocomplete="off" autofocus
           value="<?= htmlspecialchars($token) ?>" placeholder="LT-XXXXX">
    <button class="mc-btn mc-btn-primary" type="submit">Search</button>
  </form>
</div>

<?php if ($searched && $error): ?>
  <div class="mc-alert mc-alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if ($order):
  $age = !empty($order['date_of_birth']) ? date_diff(new DateTime($order['date_of_birth']), new DateTime())->y : '—';
  $doc = preg_replace('/^Dr\.?\s+/i', '', (string)$order['doctor_name']);
?>
  <div class="mc-panel">
    <div class="mc-panel-h">
      <div>
        <div class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($order['lab_token']) ?></div>
        <h2 style="margin-top:.15rem;font-size:1.2rem"><?= htmlspecialchars($order['patient_name']) ?></h2>
      </div>
      <span class="lab-status"><?= htmlspecialchars(str_replace('_', ' ', $order['status'])) ?></span>
    </div>

    <div class="lab-meta-row">
      <span>UID <strong><?= htmlspecialchars($order['patient_uid']) ?></strong></span>
      <span><?= htmlspecialchars((string)$age) ?> yrs · <?= htmlspecialchars($order['gender'] ?? '—') ?></span>
      <span>Blood <strong style="color:#b42318"><?= htmlspecialchars($order['blood_group'] ?: '—') ?></strong></span>
      <span>Dr. <?= htmlspecialchars($doc) ?></span>
      <span><?= htmlspecialchars($order['hospital_name']) ?></span>
      <span>Expires <?= date('d M Y', strtotime($order['expires_at'])) ?></span>
      <?php if (!empty($order['prescription_uid'])): ?>
        <span>Rx <strong class="mc-mono"><?= htmlspecialchars($order['prescription_uid']) ?></strong></span>
      <?php endif; ?>
    </div>

    <?php if (!empty($order['test_notes'])): ?>
      <p style="margin:0 0 1rem;font-size:.9rem"><strong>Doctor notes:</strong> <?= nl2br(htmlspecialchars($order['test_notes'])) ?></p>
    <?php endif; ?>

    <?php if (empty($tests)): ?>
      <p style="color:var(--muted);margin:0">No tests on this order.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="lab-table">
          <thead>
            <tr>
              <th>Test</th>
              <th>Urgency</th>
              <th>Status</th>
              <th>Result</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tests as $test): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($test['test_name']) ?></strong>
                  <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($test['test_category'] ?: 'General') ?></div>
                  <?php if (!empty($test['instructions'])): ?>
                    <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($test['instructions']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($test['urgency'] ?? 'ROUTINE') ?></td>
                <td><span class="lab-status"><?= htmlspecialchars(str_replace('_', ' ', $test['status'] ?? 'PENDING')) ?></span></td>
                <td style="max-width:220px">
                  <?php if (!empty($test['result_id'])): ?>
                    <?= htmlspecialchars($test['result_status'] ?? '') ?>
                    <?php if (!empty($test['result_value'])): ?> · <?= htmlspecialchars($test['result_value']) ?><?php endif; ?>
                  <?php else: ?>
                    <span style="color:var(--muted)">—</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap">
                  <?php if (!empty($test['result_id'])): ?>
                    <a class="mc-btn mc-btn-secondary mc-btn-sm" href="<?= APP_URL ?>/lab/upload-results.php?order_id=<?= (int)$order['id'] ?>&test_id=<?= (int)$test['id'] ?>">Update</a>
                  <?php else: ?>
                    <a class="mc-btn mc-btn-primary mc-btn-sm" href="<?= APP_URL ?>/lab/upload-results.php?order_id=<?= (int)$order['id'] ?>&test_id=<?= (int)$test['id'] ?>">Enter result</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
