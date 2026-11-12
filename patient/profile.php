<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];
$userId = (int)$_SESSION['user_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    validateCsrf();
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergencyName = trim($_POST['emergency_name'] ?? '');
    $emergencyPhone = trim($_POST['emergency_phone'] ?? '');
    try {
        $db->beginTransaction();
        $db->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$phone, $userId]);
        $db->prepare("UPDATE patients SET address = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE id = ?")
            ->execute([$address, $emergencyName, $emergencyPhone, $patientId]);
        AuditService::log(AuditService::UPDATE_PROFILE, ['user_id' => $userId, 'patient_id' => $patientId]);
        $db->commit();
        $success = 'Profile updated.';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Update failed: ' . $e->getMessage();
    }
}

$stmt = $db->prepare("
    SELECT p.*, u.email, u.phone, u.status AS account_status
    FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();
$age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;

$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $patient['full_name'] ?? 'Patient';
$portalMeta = ($patient['patient_uid'] ?? '') . ' · Profile';
$portalTitle = 'My profile';
$portalSubtitle = 'পরিচয় ও জরুরি যোগাযোগ';
$portalNav = mc_patient_nav('profile');
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($success): ?><div class="mc-alert mc-alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mc-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Patient ID</div><div class="v" style="font-size:1rem"><?= htmlspecialchars($patient['patient_uid']) ?></div></div>
  <div class="mc-stat"><div class="l">Age</div><div class="v"><?= (int)$age ?></div></div>
  <div class="mc-stat"><div class="l">Blood</div><div class="v" style="font-size:1.1rem"><?= htmlspecialchars($patient['blood_group'] ?? '—') ?></div></div>
  <div class="mc-stat"><div class="l">NID</div><div class="v" style="font-size:.95rem">•••• <?= htmlspecialchars($patient['nid_last4'] ?? '') ?></div></div>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Identity</h2></div>
  <p style="margin:0"><strong><?= htmlspecialchars($patient['full_name']) ?></strong></p>
  <p style="margin:.35rem 0 0;color:var(--muted);font-size:.9rem">
    <?= date('d F Y', strtotime($patient['date_of_birth'])) ?> · <?= htmlspecialchars($patient['gender'] ?? '') ?>
  </p>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Contact & emergency</h2></div>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="update_profile" value="1">
    <label class="mc-field"><span>Email (login)</span>
      <input type="email" value="<?= htmlspecialchars($patient['email']) ?>" disabled>
    </label>
    <label class="mc-field"><span>Phone</span>
      <input type="text" name="phone" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
    </label>
    <label class="mc-field"><span>Address</span>
      <textarea name="address" rows="2"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
    </label>
    <label class="mc-field"><span>Emergency contact name</span>
      <input type="text" name="emergency_name" value="<?= htmlspecialchars($patient['emergency_contact_name'] ?? '') ?>">
    </label>
    <label class="mc-field"><span>Emergency contact phone</span>
      <input type="text" name="emergency_phone" value="<?= htmlspecialchars($patient['emergency_contact_phone'] ?? '') ?>">
    </label>
    <button type="submit" class="mc-btn mc-btn-primary" style="margin-top:1rem">Save changes</button>
  </form>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
