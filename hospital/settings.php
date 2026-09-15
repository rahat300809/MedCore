<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

$success = '';
$error   = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hospital'])) {
    validateCsrf();
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $website = trim($_POST['website'] ?? '');

    try {
        $stmt = $db->prepare("
            UPDATE hospitals
            SET phone = ?, email = ?, address = ?, city = ?, website = ?
            WHERE id = ?
        ");
        $stmt->execute([$phone, $email, $address, $city, $website, $hospitalId]);

        AuditService::log('UPDATE_HOSPITAL_PROFILE', [
            'user_id'    => $_SESSION['user_id'],
            'hospital_id'=> $hospitalId,
        ]);

        $success = 'Hospital settings updated successfully.';
    } catch (Exception $e) {
        $error = 'Failed to update hospital info: ' . $e->getMessage();
    }
}

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hospital Settings — <?= htmlspecialchars($hospital['name']) ?></title>
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
      <div class="brand-icon" style="background: var(--mc-teal);">H</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Hospital Admin</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Hospital Ops</div>
      <a href="<?= APP_URL ?>/hospital/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i>
        <span>Affiliated Doctors</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/affiliations.php" class="nav-item">
        <i class="bi bi-clock-history"></i>
        <span>Pending Requests</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/departments.php" class="nav-item">
        <i class="bi bi-building"></i>
        <span>Departments</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/consultations.php" class="nav-item">
        <i class="bi bi-stethoscope"></i>
        <span>Consultations</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/settings.php" class="nav-item active">
        <i class="bi bi-gear-fill"></i>
        <span>Hospital Settings</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar" style="background: var(--mc-teal);"><?= strtoupper(substr($hospital['name'], 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars(mb_strimwidth($hospital['name'], 0, 20, '...')) ?></div>
          <div class="user-role"><?= htmlspecialchars($hospital['hospital_uid']) ?></div>
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
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Hospital Profile &amp; Settings</h1>
      </div>
    </header>

    <div class="portal-body" style="max-width: 900px;">
      <?php if ($success): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Verification Card -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-shield-check"></i> Institutional Credential &amp; Verification</h3>
          <span class="badge badge-success"><i class="bi bi-patch-check-fill"></i> <?= htmlspecialchars($hospital['verification_status']) ?></span>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <label class="text-muted" style="font-size: 0.85rem;">Hospital Identifier (UID)</label>
              <div style="font-size: 1.2rem; font-weight: 700; color: var(--mc-blue);"><?= htmlspecialchars($hospital['hospital_uid']) ?></div>
            </div>
            <div class="col-md-6">
              <label class="text-muted" style="font-size: 0.85rem;">DGHS / Government Reg Number</label>
              <div style="font-size: 1.1rem; font-weight: 600;"><?= htmlspecialchars($hospital['registration_number']) ?></div>
            </div>
            <div class="col-md-6">
              <label class="text-muted" style="font-size: 0.85rem;">Institution Name</label>
              <div style="font-weight: 700; font-size: 1.05rem;"><?= htmlspecialchars($hospital['name']) ?></div>
            </div>
            <div class="col-md-6">
              <label class="text-muted" style="font-size: 0.85rem;">Facility Classification</label>
              <div><span class="badge badge-secondary"><?= htmlspecialchars($hospital['type']) ?></span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Form -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="bi bi-pencil-square"></i> Contact &amp; Public Details</h3>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="update_hospital" value="1">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Official Phone / Hotline</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($hospital['phone'] ?? '') ?>" placeholder="e.g. +880 2-9876543">
              </div>
              <div class="col-md-6">
                <label class="form-label">Official Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($hospital['email'] ?? '') ?>" placeholder="info@hospital.org">
              </div>
              <div class="col-md-6">
                <label class="form-label">City / Region</label>
                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($hospital['city'] ?? '') ?>" placeholder="Dhaka">
              </div>
              <div class="col-md-6">
                <label class="form-label">Official Website</label>
                <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($hospital['website'] ?? '') ?>" placeholder="https://hospital.org">
              </div>
              <div class="col-12">
                <label class="form-label">Street Address &amp; Premises</label>
                <textarea name="address" rows="2" class="form-control" placeholder="Plot, Road, Area, Postal Code"><?= htmlspecialchars($hospital['address'] ?? '') ?></textarea>
              </div>
              <div class="col-12 text-end mt-4">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check2"></i> Save Hospital Profile
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
