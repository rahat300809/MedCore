<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmt = $db->prepare("
    SELECT pm.*, m.generic_name, m.brand_name, m.strength, m.dosage_form,
           d.full_name AS doctor_name, rx.prescription_uid
    FROM patient_medications pm
    JOIN medications m ON pm.medication_id = m.id
    LEFT JOIN doctors d ON pm.prescribed_by = d.id
    LEFT JOIN prescriptions rx ON pm.prescription_id = rx.id
    WHERE pm.patient_id = ?
    ORDER BY FIELD(pm.status, 'ACTIVE', 'COMPLETED', 'DISCONTINUED'), pm.started_at DESC
");
$stmt->execute([$patientId]);
$medications = $stmt->fetchAll();

$activeCount = 0;
foreach ($medications as $m) {
    if ($m['status'] === 'ACTIVE') $activeCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medications — MedCore Patient Portal</title>
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
      <a href="<?= APP_URL ?>/patient/medications.php" class="nav-item active">
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
      <a href="<?= APP_URL ?>/patient/profile.php" class="nav-item">
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
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Medication Tracker</h1>
      </div>
      <div class="header-actions">
        <span class="badge badge-success" style="font-size: 0.9rem; padding: 6px 14px;">
          <?= $activeCount ?> Active Medications
        </span>
      </div>
    </header>

    <div class="portal-body">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-capsule"></i> Prescription Medications (<?= count($medications) ?>)</h3>
        </div>
        <div class="card-body">
          <?php if (empty($medications)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-capsule" style="font-size: 3rem; color: #CBD5E1;"></i>
              <p class="mt-2">No medications currently recorded.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Medicine Name</th>
                    <th>Strength &amp; Form</th>
                    <th>Dosage / Schedule</th>
                    <th>Route</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Prescribed By</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($medications as $m): ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($m['brand_name'] ?: $m['generic_name']) ?></strong>
                        <?php if ($m['brand_name'] && $m['generic_name']): ?>
                          <div class="text-muted" style="font-size: 0.82rem;"><?= htmlspecialchars($m['generic_name']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($m['strength'] ?? 'Standard') ?> &bull; <small class="text-muted"><?= htmlspecialchars($m['dosage_form']) ?></small></td>
                      <td>
                        <strong><?= htmlspecialchars($m['frequency'] ?? '1+0+1') ?></strong>
                        <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($m['dosage'] ?? '1 Tab') ?></div>
                      </td>
                      <td><?= htmlspecialchars($m['route']) ?></td>
                      <td>
                        <?php if ($m['status'] === 'ACTIVE'): ?>
                          <span class="badge badge-success"><i class="bi bi-check2"></i> Active</span>
                        <?php elseif ($m['status'] === 'COMPLETED'): ?>
                          <span class="badge badge-secondary">Completed</span>
                        <?php else: ?>
                          <span class="badge badge-danger">Discontinued</span>
                        <?php endif; ?>
                      </td>
                      <td><?= $m['started_at'] ? date('d M Y', strtotime($m['started_at'])) : '—' ?></td>
                      <td><?= htmlspecialchars($m['doctor_name'] ? 'Dr. ' . $m['doctor_name'] : '—') ?></td>
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
