<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$success = $error = '';

// ── Add / affiliate lab ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lab'])) {
    validateCsrf();
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        $error = 'Please enter the Lab UID (LAB-XXXXXX), registration number, or email.';
    } else {
        $stmtFind = $db->prepare("
            SELECT l.*, u.email
            FROM laboratories l
            JOIN users u ON l.user_id = u.id
            WHERE l.lab_uid = ? OR l.registration_number = ? OR u.email = ?
            LIMIT 1
        ");
        $stmtFind->execute([$identifier, $identifier, $identifier]);
        $lab = $stmtFind->fetch();

        if (!$lab) {
            $error = 'No laboratory found matching "' . htmlspecialchars($identifier) . '". The laboratory must register on MedCore first.';
        } elseif ($lab['verification_status'] !== 'VERIFIED') {
            $error = 'Cannot affiliate "' . htmlspecialchars($lab['name']) . '": status is ' . $lab['verification_status'] . '. Labs must be verified by the system admin first.';
        } else {
            $stmtCheck = $db->prepare("SELECT * FROM hospital_laboratories WHERE laboratory_id = ? AND hospital_id = ?");
            $stmtCheck->execute([$lab['id'], $hospitalId]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                if ($existing['status'] === 'APPROVED') {
                    $error = '"' . htmlspecialchars($lab['name']) . '" is already affiliated with your hospital.';
                } else {
                    // Re-approve
                    $db->prepare("UPDATE hospital_laboratories SET status='APPROVED', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $existing['id']]);
                    $success = '"' . htmlspecialchars($lab['name']) . '" has been re-approved.';
                }
            } else {
                $db->prepare("
                    INSERT INTO hospital_laboratories (laboratory_id, hospital_id, status, approved_by, approved_at)
                    VALUES (?, ?, 'APPROVED', ?, NOW())
                ")->execute([$lab['id'], $hospitalId, $_SESSION['user_id']]);

                AuditService::log(AuditService::LAB_AFFILIATED, [
                    'user_id'    => $_SESSION['user_id'],
                    'hospital_id'=> $hospitalId,
                    'metadata'   => ['lab_id' => $lab['id'], 'lab_uid' => $lab['lab_uid'], 'lab_name' => $lab['name']],
                ]);
                $success = '"' . htmlspecialchars($lab['name']) . '" has been affiliated with your hospital successfully.';
            }
        }
    }
}

// ── Suspend / Remove ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    validateCsrf();
    $afflId  = (int)$_POST['affiliation_id'];
    $newSt   = $_POST['new_status'] ?? '';
    $allowed = ['APPROVED','SUSPENDED','REMOVED'];
    if (in_array($newSt, $allowed)) {
        $db->prepare("UPDATE hospital_laboratories SET status=?, approved_by=?, approved_at=NOW() WHERE id=? AND hospital_id=?")->execute([$newSt, $_SESSION['user_id'], $afflId, $hospitalId]);
        $success = 'Laboratory status updated to ' . $newSt . '.';
    }
}

// ── Fetch affiliated labs ─────────────────────────────────────────────────
$stmtLabs = $db->prepare("
    SELECT hl.*, l.lab_uid, l.name AS lab_name, l.registration_number,
           l.contact_phone, l.contact_email, l.city, l.services,
           l.verification_status AS lab_v_status,
           u.email AS user_email, u.last_login_at,
           (SELECT COUNT(*) FROM prescription_lab_orders plo WHERE plo.laboratory_id = l.id AND plo.hospital_id = :h2) AS total_orders,
           (SELECT COUNT(*) FROM prescription_lab_orders plo WHERE plo.laboratory_id = l.id AND plo.hospital_id = :h3 AND plo.status = 'PENDING') AS pending_orders
    FROM hospital_laboratories hl
    JOIN laboratories l ON hl.laboratory_id = l.id
    JOIN users u ON l.user_id = u.id
    WHERE hl.hospital_id = :h1
    ORDER BY hl.status = 'APPROVED' DESC, hl.created_at DESC
");
$stmtLabs->execute([':h1'=>$hospitalId,':h2'=>$hospitalId,':h3'=>$hospitalId]);
$labs = $stmtLabs->fetchAll();

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalAccent = '#0b4f6c';
$portalUser = $hospital['name'] ?? 'Hospital';
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Labs';
$portalTitle = 'Laboratories';
$portalSubtitle = 'অনুমোদিত ডায়াগনস্টিক নেটওয়ার্ক';
$portalNav = mc_hospital_nav('labs');
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($success): ?><div class="mc-alert mc-alert-ok"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="mc-error"><?= $error ?></div><?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Affiliate lab</h2></div>
  <form method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end">
    <?= csrfField() ?>
    <input type="hidden" name="add_lab" value="1">
    <label class="mc-field" style="margin:0;flex:1;min-width:220px"><span>Lab UID / email / reg</span>
      <input type="text" name="identifier" required placeholder="LAB-000001 / lab@...">
    </label>
    <button class="mc-btn mc-btn-primary" type="submit">Add lab</button>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Affiliated labs (<?= count($labs) ?>)</h2></div>
  <?php if (empty($labs)): ?>
    <p style="margin:0;color:var(--muted)">কোনো lab affiliated নেই।</p>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($labs as $lab): ?>
        <div class="mc-list-item" style="align-items:flex-start;flex-wrap:wrap;gap:.75rem">
          <div style="flex:1;min-width:220px">
            <strong><?= htmlspecialchars($lab['lab_name']) ?></strong>
            <div style="font-size:.86rem;color:var(--muted)">
              <?= htmlspecialchars($lab['lab_uid']) ?> · <?= htmlspecialchars($lab['city'] ?? '') ?>
              · Orders <?= (int)$lab['total_orders'] ?> / pending <?= (int)$lab['pending_orders'] ?>
            </div>
          </div>
          <span class="lab-status"><?= htmlspecialchars($lab['status']) ?></span>
          <form method="POST" style="display:flex;gap:.35rem">
            <?= csrfField() ?>
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="affiliation_id" value="<?= (int)$lab['id'] ?>">
            <?php if ($lab['status'] !== 'APPROVED'): ?>
              <button class="mc-btn mc-btn-secondary mc-btn-sm" name="new_status" value="APPROVED">Approve</button>
            <?php endif; ?>
            <button class="mc-btn mc-btn-ghost mc-btn-sm" name="new_status" value="SUSPENDED">Suspend</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>