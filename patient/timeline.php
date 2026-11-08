<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Patient profile
$stmt = $db->prepare("SELECT p.*, u.email, u.phone FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

// Fetch timeline events: Consultations
$stmtCons = $db->prepare("
    SELECT 'CONSULTATION' AS event_type, c.id, c.consultation_date AS event_date, c.consultation_uid AS event_ref,
           c.diagnosis_summary AS title, c.clinical_notes AS detail,
           d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN hospitals h ON c.hospital_id = h.id
    WHERE c.patient_id = ?
");
$stmtCons->execute([$patientId]);
$consultations = $stmtCons->fetchAll();

// Fetch timeline events: Prescriptions
$stmtRx = $db->prepare("
    SELECT 'PRESCRIPTION' AS event_type, rx.id, rx.created_at AS event_date, rx.prescription_uid AS event_ref,
           rx.diagnosis AS title, rx.advice AS detail,
           d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    WHERE rx.patient_id = ?
");
$stmtRx->execute([$patientId]);
$prescriptions = $stmtRx->fetchAll();

// Fetch timeline events: Lab Orders
$stmtLab = $db->prepare("
    SELECT 'LAB_ORDER' AS event_type, plo.id, plo.created_at AS event_date, plo.lab_token AS event_ref,
           CONCAT('Diagnostic Order (', plo.lab_token, ')') AS title, plo.test_notes AS detail,
           d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM prescription_lab_orders plo
    JOIN doctors d ON plo.doctor_id = d.id
    JOIN hospitals h ON plo.hospital_id = h.id
    WHERE plo.patient_id = ?
");
$stmtLab->execute([$patientId]);
$labOrders = $stmtLab->fetchAll();

// Combine and sort chronologically descending
$timeline = array_merge($consultations, $prescriptions, $labOrders);
usort($timeline, function($a, $b) {
    return strtotime($b['event_date']) - strtotime($a['event_date']);
});

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Health Timeline — MedCore Patient Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .timeline-container { position: relative; padding: 20px 0; max-width: 800px; margin: 0 auto; }
    .timeline-container::before {
      content: ''; position: absolute; left: 24px; top: 0; bottom: 0;
      width: 2px; background: var(--mc-border);
    }
    .timeline-item { position: relative; padding-left: 60px; margin-bottom: 32px; }
    .timeline-icon {
      position: absolute; left: 10px; top: 0; width: 30px; height: 30px;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      background: white; border: 2px solid var(--mc-blue); color: var(--mc-blue); font-size: 13px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }
    .timeline-item.PRESCRIPTION .timeline-icon { border-color: #10B981; color: #10B981; }
    .timeline-item.LAB_ORDER .timeline-icon { border-color: #8B5CF6; color: #8B5CF6; }
    .timeline-card {
      background: white; border: 1px solid var(--mc-border); border-radius: 12px;
      padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
  </style>
</head>
<body>
<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-tagline">Patient Portal</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/patient/dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
      <div class="sidebar-section-title">My Health</div>
      <a href="<?= APP_URL ?>/patient/medical-history.php"><i class="bi bi-clipboard2-heart-fill"></i> Medical History</a>
      <a href="<?= APP_URL ?>/patient/timeline.php" class="active"><i class="bi bi-calendar2-heart-fill"></i> Health Timeline</a>
      <a href="<?= APP_URL ?>/patient/medications.php"><i class="bi bi-capsule-pill"></i> Medications</a>
      <a href="<?= APP_URL ?>/patient/allergies.php"><i class="bi bi-shield-exclamation"></i> Allergies</a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Lab Reports</a>
      <div class="sidebar-section-title">Consultations</div>
      <a href="<?= APP_URL ?>/patient/prescriptions.php"><i class="bi bi-file-earmark-medical-fill"></i> Prescriptions</a>
      <div class="sidebar-section-title">Privacy & Access</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php"><i class="bi bi-person-check-fill"></i> Consent Requests</a>
      <a href="<?= APP_URL ?>/patient/access-history.php"><i class="bi bi-clock-history"></i> Access History</a>
      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/patient/profile.php"><i class="bi bi-person-circle"></i> My Profile</a>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="Log out?"><i class="bi bi-box-arrow-right"></i> Log Out</a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($patient['full_name'], 0, 1)) ?></div>
        <div class="sidebar-user-info">
          <div class="name"><?= htmlspecialchars($patient['full_name']) ?></div>
          <div class="role"><?= htmlspecialchars($patient['patient_uid']) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Longitudinal Health Timeline</h2>
        <div style="font-size:12px;color:var(--mc-text-muted);">Chronological care trajectory across affiliated medical centers</div>
      </div>
      <a href="<?= APP_URL ?>/patient/dashboard.php" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Dashboard
      </a>
    </header>

    <div class="portal-content">
      <div class="timeline-container">
        <?php if (empty($timeline)): ?>
        <div class="card text-center" style="padding:48px 24px;">
          <div style="font-size:3rem;color:var(--mc-text-muted);"><i class="bi bi-calendar-x"></i></div>
          <h3 style="margin-top:12px;font-size:16px;">No Health Events Recorded</h3>
          <p style="color:var(--mc-text-muted);font-size:13px;">Your consultations, prescriptions, and lab tests will automatically appear here chronologically.</p>
        </div>
        <?php else: ?>
          <?php foreach ($timeline as $item): ?>
          <div class="timeline-item <?= $item['event_type'] ?>">
            <div class="timeline-icon">
              <?php if ($item['event_type'] === 'PRESCRIPTION'): ?>
                <i class="bi bi-prescription2"></i>
              <?php elseif ($item['event_type'] === 'LAB_ORDER'): ?>
                <i class="bi bi-flask-fill"></i>
              <?php else: ?>
                <i class="bi bi-stethoscope"></i>
              <?php endif; ?>
            </div>
            <div class="timeline-card">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
                <div>
                  <span class="badge" style="font-size:10px;text-transform:uppercase;background:<?= $item['event_type'] === 'PRESCRIPTION' ? 'var(--mc-green-light);color:var(--mc-green)' : ($item['event_type'] === 'LAB_ORDER' ? '#EDE9FE;color:#7C3AED' : 'var(--mc-blue-light);color:var(--mc-blue)') ?>;">
                    <?= str_replace('_', ' ', $item['event_type']) ?>
                  </span>
                  <strong style="margin-left:6px;font-size:14px;color:var(--mc-text);"><?= htmlspecialchars($item['title'] ?: 'Clinical Note') ?></strong>
                </div>
                <span style="font-size:12px;color:var(--mc-text-muted);white-space:nowrap;">
                  <i class="bi bi-clock"></i> <?= date('d M Y, h:i A', strtotime($item['event_date'])) ?>
                </span>
              </div>
              <div style="font-size:12px;color:var(--mc-text-secondary);margin-bottom:8px;">
                <i class="bi bi-person-fill-check"></i> Dr. <?= htmlspecialchars($item['doctor_name']) ?> (<?= htmlspecialchars($item['specialization'] ?? 'Physician') ?>)
                · <i class="bi bi-building"></i> <?= htmlspecialchars($item['hospital_name']) ?>
              </div>
              <?php if (!empty($item['detail'])): ?>
              <div style="font-size:12px;background:var(--mc-bg);padding:8px 12px;border-radius:8px;color:var(--mc-text-secondary);">
                <?= nl2br(htmlspecialchars($item['detail'])) ?>
              </div>
              <?php endif; ?>
              <?php if ($item['event_type'] === 'PRESCRIPTION'): ?>
              <div style="margin-top:10px;">
                <a href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary btn-sm" style="font-size:11px;padding:4px 10px;">
                  <i class="bi bi-printer"></i> View Official Prescription
                </a>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
</body>
</html>

