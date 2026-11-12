<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireAuth('HOSPITAL_ADMIN');

$db = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hospital'])) {
    validateCsrf();
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $website = trim($_POST['website'] ?? '');
    try {
        $db->prepare("UPDATE hospitals SET phone = ?, email = ?, address = ?, city = ?, website = ? WHERE id = ?")
            ->execute([$phone, $email, $address, $city, $website, $hospitalId]);
        AuditService::log('UPDATE_HOSPITAL_PROFILE', [
            'user_id' => $_SESSION['user_id'],
            'hospital_id' => $hospitalId,
        ]);
        $success = 'Hospital settings updated.';
    } catch (Exception $e) {
        $error = 'Update failed: ' . $e->getMessage();
    }
}

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalUser = $hospital['name'] ?? 'Hospital';
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Settings';
$portalTitle = 'Hospital settings';
$portalSubtitle = 'যোগাযোগ ও পাবলিক তথ্য';
$portalNav = mc_hospital_nav('settings');
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($success): ?><div class="mc-alert mc-alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mc-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Institution</h2>
    <span class="lab-status"><?= htmlspecialchars($hospital['verification_status'] ?? '') ?></span>
  </div>
  <p style="margin:0"><strong><?= htmlspecialchars($hospital['name']) ?></strong></p>
  <p style="margin:.35rem 0 0;color:var(--muted);font-size:.88rem">
    <?= htmlspecialchars($hospital['hospital_uid']) ?> · <?= htmlspecialchars($hospital['registration_number'] ?? '') ?> · <?= htmlspecialchars($hospital['type'] ?? '') ?>
  </p>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Contact details</h2></div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="update_hospital" value="1">
    <label class="mc-field"><span>Phone</span><input type="text" name="phone" value="<?= htmlspecialchars($hospital['phone'] ?? '') ?>"></label>
    <label class="mc-field"><span>Email</span><input type="email" name="email" value="<?= htmlspecialchars($hospital['email'] ?? '') ?>"></label>
    <label class="mc-field"><span>City</span><input type="text" name="city" value="<?= htmlspecialchars($hospital['city'] ?? '') ?>"></label>
    <label class="mc-field"><span>Website</span><input type="url" name="website" value="<?= htmlspecialchars($hospital['website'] ?? '') ?>"></label>
    <label class="mc-field"><span>Address</span><textarea name="address" rows="2"><?= htmlspecialchars($hospital['address'] ?? '') ?></textarea></label>
    <button type="submit" class="mc-btn mc-btn-primary" style="margin-top:1rem">Save</button>
  </form>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
