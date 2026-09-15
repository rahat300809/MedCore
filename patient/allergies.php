<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmt = $db->prepare("
    SELECT pa.*, a.name AS allergy_name, a.type AS allergy_type, a.description AS allergy_desc
    FROM patient_allergies pa
    JOIN allergies a ON a.id = pa.allergy_id
    WHERE pa.patient_id = ?
    ORDER BY FIELD(pa.severity, 'LIFE_THREATENING', 'SEVERE', 'MODERATE', 'MILD')
");
$stmt->execute([$patientId]);
$allergies = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Allergies &amp; Sensitivities — MedCore Patient Portal</title>
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
      <a href="<?= APP_URL ?>/patient/allergies.php" class="nav-item active">
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
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Documented Allergies</h1>
      </div>
    </header>

    <div class="portal-body">
      <div class="alert alert-info mb-4">
        <i class="bi bi-info-circle-fill"></i>
        Allergies documented here are automatically highlighted to authorized doctors whenever they review your medical record to prevent adverse reactions.
      </div>

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-shield-exclamation"></i> Known Allergies &amp; Drug Reactions (<?= count($allergies) ?>)</h3>
        </div>
        <div class="card-body">
          <?php if (empty($allergies)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-shield-check" style="font-size: 3rem; color: #10B981;"></i>
              <p class="mt-2">No known drug or environmental allergies documented.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Allergen / Substance</th>
                    <th>Category</th>
                    <th>Severity Level</th>
                    <th>Reaction &amp; Symptoms</th>
                    <th>Clinical Verification</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allergies as $alg): ?>
                    <tr style="<?= in_array($alg['severity'], ['SEVERE', 'LIFE_THREATENING']) ? 'background: #FEF2F2;' : '' ?>">
                      <td>
                        <strong style="<?= in_array($alg['severity'], ['SEVERE', 'LIFE_THREATENING']) ? 'color: var(--mc-danger);' : '' ?>">
                          <?= htmlspecialchars($alg['allergy_name']) ?>
                        </strong>
                      </td>
                      <td><span class="badge badge-secondary"><?= htmlspecialchars($alg['allergy_type']) ?></span></td>
                      <td>
                        <?php if ($alg['severity'] === 'LIFE_THREATENING'): ?>
                          <span class="badge badge-danger"><i class="bi bi-exclamation-octagon-fill"></i> LIFE THREATENING</span>
                        <?php elseif ($alg['severity'] === 'SEVERE'): ?>
                          <span class="badge badge-danger">SEVERE</span>
                        <?php elseif ($alg['severity'] === 'MODERATE'): ?>
                          <span class="badge badge-warning">MODERATE</span>
                        <?php else: ?>
                          <span class="badge badge-info">MILD</span>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($alg['reaction'] ?? 'General reaction') ?></td>
                      <td>
                        <?php if ($alg['verified']): ?>
                          <span class="badge badge-success"><i class="bi bi-check-circle"></i> Clinically Verified</span>
                        <?php else: ?>
                          <span class="badge badge-secondary">Patient Self-Reported</span>
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
