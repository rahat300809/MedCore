<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireDoctorHospitalContext();

$db       = getDB();
$doctorId = (int)$_SESSION['doctor_id'];
$search   = trim($_GET['search'] ?? '');

$query = "
    SELECT c.*, p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth,
           h.name AS hospital_name, dept.name AS department_name,
           rx.prescription_uid
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN hospitals h ON c.hospital_id = h.id
    LEFT JOIN departments dept ON c.department_id = dept.id
    LEFT JOIN prescriptions rx ON rx.consultation_id = c.id
    WHERE c.doctor_id = ?
";
$params = [$doctorId];

if ($search) {
    $query .= " AND (c.consultation_uid LIKE ? OR p.full_name LIKE ? OR p.patient_uid LIKE ?)";
    $term = "%{$search}%";
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
  <title>Consultations History — MedCore Doctor Portal</title>
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
        <div class="brand-sub">Doctor Portal</div>
      </div>
    </div>

    <div class="doctor-context-badge" style="margin: 0 var(--space-4) var(--space-4); padding: var(--space-3); background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-md);">
      <div style="font-size: 0.75rem; color: #1E40AF; font-weight: 700; text-transform: uppercase;">Active Practice Context</div>
      <div style="font-weight: 600; font-size: 0.88rem; color: #1E3A8A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($_SESSION['hospital_name']) ?></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Clinical Care</div>
      <a href="<?= APP_URL ?>/doctor/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/patient-search.php" class="nav-item">
        <i class="bi bi-search"></i>
        <span>Search Patient</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/consultations.php" class="nav-item active">
        <i class="bi bi-chat-heart-fill"></i>
        <span>Consultations</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php" class="nav-item">
        <i class="bi bi-prescription2"></i>
        <span>Prescriptions</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'D', 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Doctor') ?></div>
          <div class="user-role">BMDC Verified</div>
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
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Consultation History</h1>
      </div>
      <div class="header-actions">
        <a href="<?= APP_URL ?>/doctor/patient-search.php" class="btn btn-primary btn-sm">
          <i class="bi bi-search"></i> Start New Consultation
        </a>
      </div>
    </header>

    <div class="portal-body">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
          <h3 class="card-title"><i class="bi bi-journal-medical"></i> Consultations Log (<?= count($consultations) ?>)</h3>
          <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Patient, ID..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
              <a href="<?= APP_URL ?>/doctor/consultations.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
          </form>
        </div>
        <div class="card-body">
          <?php if (empty($consultations)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-chat-square-text" style="font-size: 3rem; color: #CBD5E1;"></i>
              <p class="mt-2">No consultations found.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Consultation ID</th>
                    <th>Date</th>
                    <th>Patient Name</th>
                    <th>Patient ID</th>
                    <th>Hospital</th>
                    <th>Chief Complaint</th>
                    <th>Diagnosis</th>
                    <th>Prescription</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($consultations as $c): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($c['consultation_uid']) ?></strong></td>
                      <td><?= date('d M Y, h:i A', strtotime($c['consultation_date'])) ?></td>
                      <td><strong><?= htmlspecialchars($c['patient_name']) ?></strong></td>
                      <td><span class="badge badge-secondary"><?= htmlspecialchars($c['patient_uid']) ?></span></td>
                      <td><?= htmlspecialchars($c['hospital_name']) ?></td>
                      <td><?= htmlspecialchars($c['chief_complaint'] ?? 'General') ?></td>
                      <td><span class="badge badge-info"><?= htmlspecialchars($c['diagnosis_summary'] ?? 'Under Observation') ?></span></td>
                      <td>
                        <?php if ($c['prescription_uid']): ?>
                          <a href="<?= APP_URL ?>/doctor/prescriptions.php?rx=<?= urlencode($c['prescription_uid']) ?>" class="badge badge-success" style="text-decoration: none;">
                            <i class="bi bi-file-earmark-medical"></i> <?= htmlspecialchars($c['prescription_uid']) ?>
                          </a>
                        <?php else: ?>
                          <span class="text-muted">No Rx</span>
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
