<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$success = '';
$error   = '';

// Handle add department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dept'])) {
    validateCsrf();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $error = 'Department name is required.';
    } else {
        $stmt = $db->prepare("INSERT INTO departments (hospital_id, name, description, status) VALUES (?, ?, ?, 'ACTIVE')");
        $stmt->execute([$hospitalId, $name, $desc]);
        $success = "Department '{$name}' created successfully.";
    }
}

// Fetch departments with doctor count
$stmt = $db->prepare("
    SELECT dep.*, COUNT(dh.id) AS doctor_count
    FROM departments dep
    LEFT JOIN doctor_hospitals dh ON dh.department_id = dep.id AND dh.status = 'APPROVED'
    WHERE dep.hospital_id = ?
    GROUP BY dep.id
    ORDER BY dep.name ASC
");
$stmt->execute([$hospitalId]);
$departments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Departments — <?= htmlspecialchars($hospital['name']) ?></title>
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
      <a href="<?= APP_URL ?>/hospital/departments.php" class="nav-item active">
        <i class="bi bi-building"></i>
        <span>Departments</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/consultations.php" class="nav-item">
        <i class="bi bi-stethoscope"></i>
        <span>Consultations</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/settings.php" class="nav-item">
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
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Clinical Departments</h1>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('dept-modal').style.display='block'">
          <i class="bi bi-plus-lg"></i> Add Department
        </button>
      </div>
    </header>

    <div class="portal-body">
      <?php if ($success): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Add Dept Modal -->
      <div id="dept-modal" class="card mb-4" style="display: none; border: 2px solid var(--mc-blue);">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-building-add"></i> Create New Department</h3>
          <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('dept-modal').style.display='none'">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="add_dept" value="1">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Department Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Department of Cardiology" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Description / Scope</label>
                <input type="text" name="description" class="form-control" placeholder="e.g. Cardiac OPD, Inpatient Care, ECG">
              </div>
              <div class="col-12 text-end mt-3">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('dept-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Create Department</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Departments Grid -->
      <div class="row g-4">
        <?php foreach ($departments as $d): ?>
          <div class="col-md-4">
            <div class="card h-100" style="border-top: 3px solid var(--mc-teal);">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h4 style="font-size: 1.1rem; font-weight: 700; margin: 0;"><?= htmlspecialchars($d['name']) ?></h4>
                  <span class="badge badge-success"><?= htmlspecialchars($d['status']) ?></span>
                </div>
                <p class="text-muted" style="font-size: 0.88rem; min-height: 40px; margin-bottom: 16px;">
                  <?= htmlspecialchars($d['description'] ?? 'Specialized clinical services.') ?>
                </p>
                <div style="background: #F8FAFC; border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
                  <span style="font-size: 0.85rem; color: #64748B;"><i class="bi bi-person-badge"></i> Active Doctors:</span>
                  <strong style="font-size: 1.1rem; color: var(--mc-blue);"><?= $d['doctor_count'] ?></strong>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </main>
</div>

</body>
</html>
