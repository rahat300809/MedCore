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

$search = trim($_GET['search'] ?? '');

$query = "
    SELECT c.*, d.full_name AS doctor_name, d.specialization,
           p.full_name AS patient_name, p.patient_uid,
           dept.name AS department_name, rx.prescription_uid
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN patients p ON c.patient_id = p.id
    LEFT JOIN departments dept ON c.department_id = dept.id
    LEFT JOIN prescriptions rx ON rx.consultation_id = c.id
    WHERE c.hospital_id = ?
";
$params = [$hospitalId];

if ($search) {
    $query .= " AND (c.consultation_uid LIKE ? OR d.full_name LIKE ? OR p.full_name LIKE ? OR p.patient_uid LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY c.consultation_date DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$consultations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consultation Records — <?= htmlspecialchars($hospital['name']) ?></title>
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
      <a href="<?= APP_URL ?>/hospital/consultations.php" class="nav-item active">
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
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Hospital Consultations Ledger</h1>
      </div>
    </header>

    <div class="portal-body">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
          <h3 class="card-title"><i class="bi bi-journal-medical"></i> Consultations Conducted (<?= count($consultations) ?>)</h3>
          <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Doctor, Patient..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
              <a href="<?= APP_URL ?>/hospital/consultations.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
          </form>
        </div>
        <div class="card-body">
          <?php if (empty($consultations)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-journal-x" style="font-size: 3rem; color: #CBD5E1;"></i>
              <p class="mt-2">No consultations recorded for this hospital yet.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Consultation ID</th>
                    <th>Date &amp; Time</th>
                    <th>Attending Doctor</th>
                    <th>Department</th>
                    <th>Patient Name</th>
                    <th>Patient UID</th>
                    <th>Diagnosis</th>
                    <th>Prescription</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($consultations as $c): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($c['consultation_uid']) ?></strong></td>
                      <td><?= date('d M Y, h:i A', strtotime($c['consultation_date'])) ?></td>
                      <td>Dr. <?= htmlspecialchars($c['doctor_name']) ?></td>
                      <td><?= htmlspecialchars($c['department_name'] ?? 'General OPD') ?></td>
                      <td><strong><?= htmlspecialchars($c['patient_name']) ?></strong></td>
                      <td><span class="badge badge-secondary"><?= htmlspecialchars($c['patient_uid']) ?></span></td>
                      <td><span class="badge badge-info"><?= htmlspecialchars($c['diagnosis_summary'] ?? 'Evaluation') ?></span></td>
                      <td>
                        <?php if ($c['prescription_uid']): ?>
                          <span class="badge badge-success"><?= htmlspecialchars($c['prescription_uid']) ?></span>
                        <?php else: ?>
                          <span class="text-muted">None</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

</body>
</html>
