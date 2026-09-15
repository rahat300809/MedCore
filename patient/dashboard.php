<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Patient profile
$stmt = $db->prepare("
    SELECT p.*, u.email, u.phone, u.last_login_at, u.status AS account_status
    FROM patients p
    JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

// Age calculation
$age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;

// Stats
$stmtStats = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM prescriptions WHERE patient_id = ? AND status = 'ACTIVE') AS active_prescriptions,
        (SELECT COUNT(*) FROM consultations WHERE patient_id = ?) AS total_consultations,
        (SELECT COUNT(*) FROM lab_reports WHERE patient_id = ?) AS lab_reports,
        (SELECT COUNT(*) FROM medical_histories WHERE patient_id = ? AND status = 'ACTIVE') AS active_conditions,
        (SELECT COUNT(*) FROM patient_medications WHERE patient_id = ? AND status = 'ACTIVE') AS active_medications,
        (SELECT COUNT(*) FROM patient_allergies WHERE patient_id = ?) AS allergies
");
$stmtStats->execute(array_fill(0, 6, $patientId));
$stats = $stmtStats->fetch();

// Pending access requests (for consent)
$stmtPending = $db->prepare("
    SELECT ar.*, d.full_name AS doctor_name, d.specialization,
           h.name AS hospital_name
    FROM access_requests ar
    JOIN doctors d ON ar.doctor_id = d.id
    JOIN hospitals h ON ar.hospital_id = h.id
    WHERE ar.patient_id = ? AND ar.status = 'PENDING'
    ORDER BY ar.requested_at DESC
    LIMIT 5
");
$stmtPending->execute([$patientId]);
$pendingRequests = $stmtPending->fetchAll();

// Recent prescriptions
$stmtRx = $db->prepare("
    SELECT rx.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name,
           COUNT(pm.id) AS medicines_count
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    WHERE rx.patient_id = ?
    GROUP BY rx.id
    ORDER BY rx.created_at DESC
    LIMIT 5
");
$stmtRx->execute([$patientId]);
$recentRx = $stmtRx->fetchAll();

// Recent consultations
$stmtCons = $db->prepare("
    SELECT c.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN hospitals h ON c.hospital_id = h.id
    WHERE c.patient_id = ?
    ORDER BY c.consultation_date DESC
    LIMIT 4
");
$stmtCons->execute([$patientId]);
$recentConsultations = $stmtCons->fetchAll();

// Allergies (critical display)
$stmtAllergy = $db->prepare("
    SELECT pa.*, a.name, a.type
    FROM patient_allergies pa
    JOIN allergies a ON a.id = pa.allergy_id
    WHERE pa.patient_id = ? AND pa.severity IN ('SEVERE','LIFE_THREATENING')
    LIMIT 5
");
$stmtAllergy->execute([$patientId]);
$criticalAllergies = $stmtAllergy->fetchAll();

// Unread notifications
$stmtNotif = $db->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$stmtNotif->execute([$_SESSION['user_id']]);
$unreadCount = (int)$stmtNotif->fetch()['cnt'];

// Welcome flag
$isNewUser = isset($_GET['welcome']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Dashboard — MedCore Patient Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body>

<div class="portal-layout">
  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="sidebar" role="navigation" aria-label="Patient portal navigation">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-tagline">Patient Portal</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/patient/dashboard.php" class="active" aria-current="page">
        <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i> Dashboard
      </a>

      <div class="sidebar-section-title">My Health</div>
      <a href="<?= APP_URL ?>/patient/medical-history.php">
        <i class="bi bi-clipboard2-heart-fill" aria-hidden="true"></i> Medical History
      </a>
      <a href="<?= APP_URL ?>/patient/timeline.php">
        <i class="bi bi-calendar2-heart-fill" aria-hidden="true"></i> Health Timeline
      </a>
      <a href="<?= APP_URL ?>/patient/medications.php">
        <i class="bi bi-capsule-pill" aria-hidden="true"></i> Medications
      </a>
      <a href="<?= APP_URL ?>/patient/allergies.php">
        <i class="bi bi-shield-exclamation" aria-hidden="true"></i> Allergies
      </a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php">
        <i class="bi bi-file-earmark-bar-graph-fill" aria-hidden="true"></i> Lab Reports
      </a>

      <div class="sidebar-section-title">Consultations</div>
      <a href="<?= APP_URL ?>/patient/prescriptions.php">
        <i class="bi bi-file-earmark-medical-fill" aria-hidden="true"></i> Prescriptions
      </a>

      <div class="sidebar-section-title">Privacy & Access</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php">
        <i class="bi bi-person-check-fill" aria-hidden="true"></i> Consent Requests
        <?php if (count($pendingRequests) > 0): ?>
          <span class="nav-badge"><?= count($pendingRequests) ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/patient/access-history.php">
        <i class="bi bi-clock-history" aria-hidden="true"></i> Access History
      </a>
      <a href="<?= APP_URL ?>/patient/notifications.php">
        <i class="bi bi-bell-fill" aria-hidden="true"></i> Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="nav-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>

      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/patient/profile.php">
        <i class="bi bi-person-circle" aria-hidden="true"></i> My Profile
      </a>
      <a href="<?= APP_URL ?>/patient/settings.php">
        <i class="bi bi-gear-fill" aria-hidden="true"></i> Settings
      </a>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="Log out of your session?">
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log Out
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar" aria-hidden="true">
          <?= strtoupper(substr($patient['full_name'], 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
          <div class="name"><?= htmlspecialchars($patient['full_name']) ?></div>
          <div class="role"><?= htmlspecialchars($patient['patient_uid']) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <main class="portal-main">
    <!-- Top Bar -->
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>

      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">
          Welcome back, <?= htmlspecialchars(explode(' ', $patient['full_name'])[0]) ?> 👋
        </h2>
        <div style="font-size:12px;color:var(--mc-text-muted);">
          <?= $patient['patient_uid'] ?> · <?= date('D, d M Y') ?>
        </div>
      </div>

      <!-- Consent badge alert -->
      <?php if (count($pendingRequests) > 0): ?>
      <a href="<?= APP_URL ?>/patient/consent-requests.php" class="btn btn-sm" style="background:var(--mc-amber-light);color:#92400E;border:1px solid var(--mc-amber);">
        <i class="bi bi-bell-fill"></i>
        <?= count($pendingRequests) ?> Consent <?= count($pendingRequests) > 1 ? 'Requests' : 'Request' ?>
      </a>
      <?php endif; ?>

      <a href="<?= APP_URL ?>/patient/notifications.php" class="btn btn-secondary btn-sm" style="position:relative;">
        <i class="bi bi-bell"></i>
        <?php if ($unreadCount > 0): ?>
          <span style="position:absolute;top:-4px;right:-4px;background:var(--mc-red);color:white;width:18px;height:18px;border-radius:50%;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700;"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
    </header>

    <!-- Content -->
    <div class="portal-content">

      <!-- Welcome banner (new user) -->
      <?php if ($isNewUser): ?>
      <div class="alert alert-success mb-6" data-auto-dismiss="8000" role="status">
        <i class="bi bi-check-circle-fill" style="font-size:1.2rem;"></i>
        <div>
          <strong>Account created successfully! 🎉</strong><br>
          Welcome to MedCore, <?= htmlspecialchars(explode(' ', $patient['full_name'])[0]) ?>. Your patient ID is
          <strong><?= $patient['patient_uid'] ?></strong>. Start building your health record!
        </div>
      </div>
      <?php endif; ?>

      <!-- CRITICAL ALLERGY WARNINGS -->
      <?php if ($criticalAllergies): ?>
      <div class="alert alert-critical mb-6" role="alert" aria-live="assertive">
        <i class="bi bi-exclamation-triangle-fill" style="color:var(--mc-red);font-size:1.2rem;flex-shrink:0;"></i>
        <div>
          <strong>Critical Allergy Alert — Active on all consultations</strong><br>
          <?php foreach ($criticalAllergies as $a): ?>
            <span class="badge badge-error" style="margin-right:6px;margin-top:4px;">
              <?= htmlspecialchars($a['name']) ?> — <?= ucfirst(str_replace('_',' ',$a['severity'])) ?>
            </span>
          <?php endforeach; ?>
          <span style="font-size:12px;color:var(--mc-text-secondary);display:block;margin-top:6px;">
            Clinicians accessing your record will see a mandatory warning.
          </span>
        </div>
        <a href="<?= APP_URL ?>/patient/allergies.php" class="btn btn-sm btn-secondary" style="flex-shrink:0;">
          Manage Allergies
        </a>
      </div>
      <?php endif; ?>

      <!-- Pending Consent Requests -->
      <?php if ($pendingRequests): ?>
      <div class="mb-6">
        <div class="d-flex align-center justify-between mb-4">
          <h3 style="font-size:1rem;font-weight:700;">
            <i class="bi bi-bell-fill" style="color:var(--mc-amber);"></i>
            Pending Access Requests
          </h3>
          <a href="<?= APP_URL ?>/patient/consent-requests.php" style="font-size:13px;">View All</a>
        </div>
        <?php foreach ($pendingRequests as $req): ?>
        <div class="card" style="margin-bottom:var(--space-3);border-left:3px solid var(--mc-blue);">
          <div class="d-flex align-center justify-between gap-4">
            <div class="d-flex align-center gap-3">
              <div class="stat-icon stat-icon-blue">
                <i class="bi bi-stethoscope"></i>
              </div>
              <div>
                <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($req['doctor_name']) ?></div>
                <div style="font-size:12px;color:var(--mc-text-muted);">
                  <?= htmlspecialchars($req['specialization'] ?? 'Physician') ?> · <?= htmlspecialchars($req['hospital_name']) ?>
                </div>
                <div style="font-size:11px;color:var(--mc-text-muted);">
                  Requested: <?= date('d M Y, h:i A', strtotime($req['requested_at'])) ?>
                </div>
              </div>
            </div>
            <div class="d-flex gap-2">
              <form method="POST" action="<?= APP_URL ?>/patient/consent-requests.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-success btn-sm">
                  <i class="bi bi-check-lg"></i> Approve
                </button>
              </form>
              <form method="POST" action="<?= APP_URL ?>/patient/consent-requests.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="action" value="deny">
                <button type="submit" class="btn btn-secondary btn-sm">
                  <i class="bi bi-x-lg"></i> Deny
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- STATS GRID -->
      <div class="grid-4 mb-8" style="margin-bottom:var(--space-8);">
        <div class="stat-card">
          <div class="stat-icon stat-icon-blue"><i class="bi bi-file-earmark-medical-fill"></i></div>
          <div class="stat-value"><?= $stats['active_prescriptions'] ?></div>
          <div class="stat-label">Active Prescriptions</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-teal"><i class="bi bi-stethoscope"></i></div>
          <div class="stat-value"><?= $stats['total_consultations'] ?></div>
          <div class="stat-label">Total Consultations</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-amber"><i class="bi bi-file-earmark-bar-graph-fill"></i></div>
          <div class="stat-value"><?= $stats['lab_reports'] ?></div>
          <div class="stat-label">Lab Reports</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon stat-icon-green"><i class="bi bi-capsule-pill"></i></div>
          <div class="stat-value"><?= $stats['active_medications'] ?></div>
          <div class="stat-label">Active Medications</div>
        </div>
      </div>

      <!-- PATIENT SUMMARY CARD -->
      <div class="card mb-6">
        <div class="d-flex align-center justify-between mb-5" style="flex-wrap:wrap;gap:12px;">
          <div>
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px;">Personal Health Profile</h3>
            <div style="font-size:12px;color:var(--mc-text-muted);">Your core health information</div>
          </div>
          <a href="<?= APP_URL ?>/patient/profile.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-pencil-fill"></i> Edit Profile
          </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:var(--space-5);">
          <div>
            <div class="form-label">Patient ID</div>
            <div class="fw-600"><?= htmlspecialchars($patient['patient_uid']) ?></div>
          </div>
          <div>
            <div class="form-label">Full Name</div>
            <div class="fw-600"><?= htmlspecialchars($patient['full_name']) ?></div>
          </div>
          <div>
            <div class="form-label">Age / DOB</div>
            <div class="fw-600"><?= $age ?> years · <?= date('d M Y', strtotime($patient['date_of_birth'])) ?></div>
          </div>
          <div>
            <div class="form-label">Gender</div>
            <div class="fw-600"><?= ucfirst(strtolower($patient['gender'])) ?></div>
          </div>
          <div>
            <div class="form-label">Blood Group</div>
            <div class="fw-600">
              <span class="badge badge-error"><?= $patient['blood_group'] ?></span>
            </div>
          </div>
          <div>
            <div class="form-label">NID (Last 4)</div>
            <div class="fw-600">●●●●●● <?= htmlspecialchars($patient['nid_last4']) ?></div>
          </div>
          <div>
            <div class="form-label">Conditions</div>
            <div class="fw-600"><?= $stats['active_conditions'] ?> Active</div>
          </div>
          <div>
            <div class="form-label">Allergies</div>
            <div class="fw-600"><?= $stats['allergies'] ?> Documented</div>
          </div>
        </div>
      </div>

      <!-- TWO COLUMNS: Recent Prescriptions + Recent Consultations -->
      <div class="grid-2">

        <!-- Recent Prescriptions -->
        <div class="card">
          <div class="d-flex align-center justify-between mb-4">
            <h3 style="font-size:1rem;font-weight:700;">Recent Prescriptions</h3>
            <a href="<?= APP_URL ?>/patient/prescriptions.php" style="font-size:13px;">View All</a>
          </div>

          <?php if (empty($recentRx)): ?>
          <div class="empty-state" style="padding:var(--space-8) 0;">
            <div class="empty-state-icon"><i class="bi bi-file-earmark-medical"></i></div>
            <p>No prescriptions yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($recentRx as $rx): ?>
          <a href="<?= APP_URL ?>/patient/prescription-details.php?id=<?= $rx['id'] ?>"
             style="display:block;padding:12px 0;border-bottom:1px solid var(--mc-border);text-decoration:none;color:var(--mc-text);">
            <div class="d-flex align-center justify-between gap-3">
              <div>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($rx['prescription_uid']) ?></div>
                <div style="font-size:12px;color:var(--mc-text-muted);">
                  Dr. <?= htmlspecialchars($rx['doctor_name']) ?> · <?= $rx['medicines_count'] ?> medicine(s)
                </div>
                <div style="font-size:11px;color:var(--mc-text-muted);"><?= date('d M Y', strtotime($rx['created_at'])) ?></div>
              </div>
              <span class="badge badge-<?= $rx['status'] === 'ACTIVE' ? 'success' : 'neutral' ?>">
                <?= $rx['status'] ?>
              </span>
            </div>
          </a>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Recent Consultations -->
        <div class="card">
          <div class="d-flex align-center justify-between mb-4">
            <h3 style="font-size:1rem;font-weight:700;">Recent Consultations</h3>
            <a href="<?= APP_URL ?>/patient/timeline.php" style="font-size:13px;">View Timeline</a>
          </div>

          <?php if (empty($recentConsultations)): ?>
          <div class="empty-state" style="padding:var(--space-8) 0;">
            <div class="empty-state-icon"><i class="bi bi-stethoscope"></i></div>
            <p>No consultations yet.</p>
          </div>
          <?php else: ?>
          <?php foreach ($recentConsultations as $cons): ?>
          <div style="padding:12px 0;border-bottom:1px solid var(--mc-border);">
            <div class="d-flex align-center justify-between gap-3">
              <div>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($cons['consultation_uid']) ?></div>
                <div style="font-size:12px;color:var(--mc-text-muted);">
                  Dr. <?= htmlspecialchars($cons['doctor_name']) ?> · <?= htmlspecialchars($cons['hospital_name']) ?>
                </div>
                <?php if ($cons['chief_complaint']): ?>
                <div style="font-size:11px;color:var(--mc-text-secondary);margin-top:2px;">
                  <?= htmlspecialchars(mb_strimwidth($cons['chief_complaint'], 0, 50, '...')) ?>
                </div>
                <?php endif; ?>
              </div>
              <div style="text-align:right;white-space:nowrap;">
                <div style="font-size:11px;color:var(--mc-text-muted);"><?= date('d M Y', strtotime($cons['consultation_date'])) ?></div>
                <?php if ($cons['follow_up_date']): ?>
                <div style="font-size:11px;color:var(--mc-teal);">Follow-up: <?= date('d M', strtotime($cons['follow_up_date'])) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /portal-content -->
  </main>
</div>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
// Sidebar toggle for mobile
document.getElementById('hamburger-btn')?.addEventListener('click', function() {
  const sidebar = document.getElementById('sidebar');
  const expanded = this.getAttribute('aria-expanded') === 'true';
  this.setAttribute('aria-expanded', !expanded);
  sidebar.classList.toggle('open');
});
</script>
</body>
</html>
