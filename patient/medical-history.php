<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmt = $db->prepare("
    SELECT mh.*, d.full_name AS doctor_name, d.specialization
    FROM medical_histories mh
    LEFT JOIN doctors d ON mh.created_by = d.id
    WHERE mh.patient_id = ?
    ORDER BY FIELD(mh.status, 'CHRONIC', 'ACTIVE', 'RESOLVED', 'UNKNOWN'), mh.diagnosed_date DESC
");
$stmt->execute([$patientId]);
$conditions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medical History — MedCore Patient Portal</title>
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
      <a href="<?= APP_URL ?>/patient/medical-history.php" class="nav-item active">
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
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Lifelong Medical History</h1>
      </div>
    </header>

    <div class="portal-body">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-heart-pulse"></i> Documented Medical Conditions (<?= count($conditions) ?>)</h3>
          <span class="badge badge-info">Verified Clinical Records</span>
        </div>
        <div class="card-body">
          <?php if (empty($conditions)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-heartbreak" style="font-size: 3rem; color: #CBD5E1;"></i>
              <p class="mt-2">No past illnesses or conditions documented in your record.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Condition / Illness</th>
                    <th>ICD Code</th>
                    <th>Status</th>
                    <th>Diagnosed Date</th>
                    <th>Doctor / Specialist</th>
                    <th>Clinical Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($conditions as $c): ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($c['condition_name']) ?></strong>
                        <?php if ($c['description']): ?>
                          <div class="text-muted" style="font-size: 0.85rem;"><?= htmlspecialchars($c['description']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td><span class="badge badge-secondary"><?= htmlspecialchars($c['icd_code'] ?? 'N/A') ?></span></td>
                      <td>
                        <?php if ($c['status'] === 'CHRONIC'): ?>
                          <span class="badge badge-warning"><i class="bi bi-arrow-repeat"></i> Chronic</span>
                        <?php elseif ($c['status'] === 'ACTIVE'): ?>
                          <span class="badge badge-danger">Active</span>
                        <?php else: ?>
                          <span class="badge badge-success"><i class="bi bi-check2"></i> Resolved</span>
                        <?php endif; ?>
                      </td>
                      <td><?= $c['diagnosed_date'] ? date('d M Y', strtotime($c['diagnosed_date'])) : '—' ?></td>
                      <td><?= htmlspecialchars($c['doctor_name'] ? 'Dr. ' . $c['doctor_name'] : 'Hospital Record') ?></td>
                      <td><?= htmlspecialchars($c['notes'] ?? '—') ?></td>
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
