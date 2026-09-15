<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];
$userId    = (int)$_SESSION['user_id'];

$success = '';
$error   = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    validateCsrf();

    $phone             = trim($_POST['phone'] ?? '');
    $address           = trim($_POST['address'] ?? '');
    $emergencyName     = trim($_POST['emergency_name'] ?? '');
    $emergencyPhone    = trim($_POST['emergency_phone'] ?? '');

    try {
        $db->beginTransaction();

        $stmtUser = $db->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $stmtUser->execute([$phone, $userId]);

        $stmtPat = $db->prepare("
            UPDATE patients
            SET address = ?, emergency_contact_name = ?, emergency_contact_phone = ?
            WHERE id = ?
        ");
        $stmtPat->execute([$address, $emergencyName, $emergencyPhone, $patientId]);

        AuditService::log(AuditService::UPDATE_PROFILE, [
            'user_id'    => $userId,
            'patient_id' => $patientId,
        ]);

        $db->commit();
        $success = 'Profile details updated successfully.';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Failed to update profile: ' . $e->getMessage();
    }
}

// Fetch patient & user record
$stmt = $db->prepare("
    SELECT p.*, u.email, u.phone, u.status AS account_status, u.created_at AS member_since
    FROM patients p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();
$age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — MedCore Patient Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Patient Portal</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">My Health Record</div>
      <a href="<?= APP_URL ?>/patient/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/patient/prescriptions.php" class="nav-item">
        <i class="bi bi-prescription2"></i>
        <span>Prescriptions</span>
      </a>
      <a href="<?= APP_URL ?>/patient/medical-history.php" class="nav-item">
        <i class="bi bi-heart-pulse-fill"></i>
        <span>Medical History</span>
      </a>
      <a href="<?= APP_URL ?>/patient/medications.php" class="nav-item">
        <i class="bi bi-capsule-pill"></i>
        <span>Medications</span>
      </a>
      <a href="<?= APP_URL ?>/patient/allergies.php" class="nav-item">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <span>Allergies</span>
      </a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php" class="nav-item">
        <i class="bi bi-file-earmark-medical"></i>
        <span>Lab Reports</span>
      </a>

      <div class="nav-section-title">Privacy &amp; Security</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php" class="nav-item">
        <i class="bi bi-shield-lock-fill"></i>
        <span>Consent Requests</span>
      </a>
      <a href="<?= APP_URL ?>/patient/access-history.php" class="nav-item">
        <i class="bi bi-clock-history"></i>
        <span>Access History</span>
      </a>
      <a href="<?= APP_URL ?>/patient/profile.php" class="nav-item active">
        <i class="bi bi-person-circle"></i>
        <span>My Profile</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'P', 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Patient') ?></div>
          <div class="user-role">Verified Patient</div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-outline btn-sm" style="width: 100%; margin-top: var(--space-3);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-header">
      <div class="d-flex align-items-center gap-3">
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Patient Identity &amp; Profile</h1>
      </div>
    </header>

    <div class="portal-body" style="max-width: 900px;">
      <?php if ($success): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Identity Card -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-person-vcard"></i> National Health Identifier</h3>
          <span class="badge badge-success"><i class="bi bi-shield-check"></i> NID Verified</span>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <label class="text-muted" style="font-size: 0.85rem;">Patient Unique ID</label>
              <div style="font-size: 1.2rem; font-weight: 700; color: var(--mc-blue);"><?= htmlspecialchars($patient['patient_uid']) ?></div>
            </div>
            <div class="col-md-6">
              <label class="text-muted" style="font-size: 0.85rem;">National ID (Masked)</label>
              <div style="font-size: 1.1rem; font-weight: 600; color: #334155;">&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; <?= htmlspecialchars($patient['nid_last4']) ?></div>
            </div>
            <div class="col-md-4">
              <label class="text-muted" style="font-size: 0.85rem;">Full Legal Name</label>
              <div style="font-weight: 600;"><?= htmlspecialchars($patient['full_name']) ?></div>
            </div>
            <div class="col-md-4">
              <label class="text-muted" style="font-size: 0.85rem;">Date of Birth</label>
              <div style="font-weight: 600;"><?= date('d F Y', strtotime($patient['date_of_birth'])) ?> (<?= $age ?> yrs)</div>
            </div>
            <div class="col-md-4">
              <label class="text-muted" style="font-size: 0.85rem;">Blood Group</label>
              <div style="font-weight: 700; color: var(--mc-danger); font-size: 1.1rem;"><?= htmlspecialchars($patient['blood_group'] ?? 'Unknown') ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Editable Contact Details -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="bi bi-pencil-square"></i> Contact &amp; Emergency Details</h3>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="update_profile" value="1">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Email Address (Login Account)</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($patient['email']) ?>" disabled>
                <small class="text-muted">Email is linked to account authentication.</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>" placeholder="+880 1700-000000">
              </div>
              <div class="col-12">
                <label class="form-label">Residential Address</label>
                <textarea name="address" rows="2" class="form-control" placeholder="House/Flat, Road, Sector, City"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" name="emergency_name" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_name'] ?? '') ?>" placeholder="e.g. Spouse / Sibling / Parent Name">
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Emergency Contact Phone</label>
                <input type="text" name="emergency_phone" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_phone'] ?? '') ?>" placeholder="e.g. 01711-223344">
              </div>
              <div class="col-12 mt-4 text-end">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check2"></i> Save Profile Changes
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>
