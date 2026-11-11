<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('PATIENT');

$db        = getDB();
$patientId = (int)$_SESSION['patient_id'];
$search    = trim($_GET['search'] ?? '');
$targetRx  = trim($_GET['rx'] ?? '');

$query = "
    SELECT rx.*, d.full_name AS doctor_name, d.specialization, d.qualification,
           h.name AS hospital_name,
           (SELECT COUNT(*) FROM prescription_medicines pm WHERE pm.prescription_id = rx.id) AS medicine_count
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    WHERE rx.patient_id = ?
";
$params = [$patientId];

if ($search) {
    $query .= " AND (rx.prescription_uid LIKE ? OR d.full_name LIKE ? OR h.name LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY rx.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// If viewing a single prescription modal / detail
$viewRx = null;
$viewMeds = [];
if ($targetRx) {
    $stmtView = $db->prepare("
        SELECT rx.*, p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth, p.blood_group,
               h.name AS hospital_name, h.address AS hospital_address, h.phone AS hospital_phone,
               d.full_name AS doctor_name, d.specialization, d.medical_registration_id, d.qualification,
               c.chief_complaint, c.clinical_notes, c.investigation
        FROM prescriptions rx
        JOIN patients p ON rx.patient_id = p.id
        JOIN hospitals h ON rx.hospital_id = h.id
        JOIN doctors d ON rx.doctor_id = d.id
        LEFT JOIN consultations c ON rx.consultation_id = c.id
        WHERE rx.prescription_uid = ? AND rx.patient_id = ?
        LIMIT 1
    ");
    $stmtView->execute([$targetRx, $patientId]);
    $viewRx = $stmtView->fetch();

    if ($viewRx) {
        $stmtMeds = $db->prepare("SELECT * FROM prescription_medicines WHERE prescription_id = ? ORDER BY id ASC");
        $stmtMeds->execute([$viewRx['id']]);
        $viewMeds = $stmtMeds->fetchAll();

        // Fetch Lab Order and Tests
        $stmtLabOrd = $db->prepare("
            SELECT plo.*, l.name AS assigned_lab_name, l.lab_uid AS assigned_lab_uid
            FROM prescription_lab_orders plo
            LEFT JOIN laboratories l ON plo.laboratory_id = l.id
            WHERE plo.prescription_id = ?
            LIMIT 1
        ");
        $stmtLabOrd->execute([$viewRx['id']]);
        $viewLabOrder = $stmtLabOrd->fetch();

        $viewLabTests = [];
        if ($viewLabOrder) {
            $stmtLT = $db->prepare("
                SELECT lot.*, ltr.result_status, ltr.result_summary, ltr.result_value, ltr.reference_range,
                       ltr.technician_name, ltr.uploaded_at AS result_uploaded_at,
                       rl.name AS result_lab_name
                FROM lab_order_tests lot
                LEFT JOIN lab_test_results ltr ON ltr.lab_order_test_id = lot.id
                LEFT JOIN laboratories rl ON ltr.laboratory_id = rl.id
                WHERE lot.lab_order_id = ?
                ORDER BY lot.id ASC
            ");
            $stmtLT->execute([$viewLabOrder['id']]);
            $viewLabTests = $stmtLT->fetchAll();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Prescriptions — MedCore Patient Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    @media print {
      body * { visibility: hidden; }
      #printable-prescription, #printable-prescription * { visibility: visible; }
      #printable-prescription { position: absolute; left: 0; top: 0; width: 100%; }
      .no-print { display: none !important; }
    }
    .rx-paper {
      background: #fff;
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-xl);
      padding: 40px;
      box-shadow: var(--shadow-md);
      position: relative;
    }
    .rx-badge-watermark {
      position: absolute;
      right: 40px;
      top: 40px;
      opacity: 0.08;
      font-size: 8rem;
      pointer-events: none;
    }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar no-print" id="sidebar">
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
      <a href="<?= APP_URL ?>/patient/prescriptions.php" class="nav-item active">
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
    <header class="portal-header no-print">
      <div class="d-flex align-items-center gap-3">
        <button class="hamburger" id="hamburger-btn" aria-label="Toggle navigation">
          <span></span><span></span><span></span>
        </button>
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Digital Prescriptions</h1>
      </div>
    </header>

    <div class="portal-body">

      <!-- Printable Prescription View if ?rx=... -->
      <?php if ($viewRx): ?>
        <div class="mb-5" id="printable-prescription">
          <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <a href="<?= APP_URL ?>/patient/prescriptions.php" class="btn btn-secondary btn-sm">
              <i class="bi bi-arrow-left"></i> Back to Prescriptions
            </a>
            <div class="d-flex gap-2">
              <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="bi bi-printer"></i> Print / Download PDF
              </button>
            </div>
          </div>

          <div class="rx-paper">
            <i class="bi bi-hospital rx-badge-watermark"></i>
            <div style="border-bottom: 2px solid var(--mc-blue); padding-bottom: 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start;">
              <div>
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--mc-blue); margin: 0;"><?= htmlspecialchars($viewRx['hospital_name']) ?></h2>
                <div class="text-muted" style="font-size: 0.88rem; margin-top: 4px;"><?= htmlspecialchars($viewRx['hospital_address'] ?? 'Dhaka, Bangladesh') ?></div>
                <div class="text-muted" style="font-size: 0.85rem;">Emergency Hotline: <?= htmlspecialchars($viewRx['hospital_phone'] ?? '16263') ?></div>
              </div>
              <div style="text-align: right;">
                <div style="font-weight: 700; font-size: 1.15rem; color: #0F172A;">Dr. <?= htmlspecialchars($viewRx['doctor_name']) ?></div>
                <div style="font-size: 0.88rem; color: #475569;"><?= htmlspecialchars($viewRx['qualification'] ?? 'MBBS, FCPS') ?></div>
                <div style="font-size: 0.85rem; color: #2563EB; font-weight: 600;"><?= htmlspecialchars($viewRx['specialization'] ?? 'Consultant') ?></div>
                <div style="font-size: 0.8rem; color: #64748B;">BMDC Reg: <?= htmlspecialchars($viewRx['medical_registration_id']) ?></div>
              </div>
            </div>

            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 20px; margin-bottom: 28px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 0.9rem;">
              <div>Patient: <strong><?= htmlspecialchars($viewRx['patient_name']) ?></strong></div>
              <div>ID: <strong><?= htmlspecialchars($viewRx['patient_uid']) ?></strong></div>
              <div>Age: <strong><?= date_diff(new DateTime($viewRx['date_of_birth']), new DateTime())->y ?> yrs</strong></div>
              <div>Gender: <strong><?= ucfirst(strtolower($viewRx['gender'])) ?></strong></div>
              <div>Date: <strong><?= date('d M Y', strtotime($viewRx['created_at'])) ?></strong></div>
              <div>Rx #: <strong><?= htmlspecialchars($viewRx['prescription_uid']) ?></strong></div>
            </div>

            <div class="row g-4 mb-4">
              <div class="col-md-4" style="border-right: 1px solid #E2E8F0;">
                <div class="mb-3">
                  <div style="font-weight: 700; font-size: 0.85rem; color: #64748B; text-transform: uppercase;">Diagnosis</div>
                  <div style="font-size: 1rem; font-weight: 600; color: #0F172A; margin-top: 4px;"><?= nl2br(htmlspecialchars($viewRx['diagnosis'])) ?></div>
                </div>
                <?php if ($viewRx['investigation']): ?>
                  <div class="mb-3">
                    <div style="font-weight: 700; font-size: 0.85rem; color: #64748B; text-transform: uppercase;">Investigations Advised</div>
                    <div style="font-size: 0.9rem; margin-top: 4px; color: #1E40AF;"><?= nl2br(htmlspecialchars($viewRx['investigation'])) ?></div>
                  </div>
                <?php endif; ?>
              </div>

              <div class="col-md-8">
                <div style="font-family: Georgia, serif; font-size: 2.4rem; font-weight: bold; color: var(--mc-blue); line-height: 1; margin-bottom: 16px;">℞</div>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                  <tbody>
                    <?php foreach ($viewMeds as $idx => $m): ?>
                      <tr style="border-bottom: 1px dashed #CBD5E1;">
                        <td style="padding: 12px 6px; vertical-align: top; width: 30px; font-weight: 700; color: #94A3B8;"><?= $idx + 1 ?>.</td>
                        <td style="padding: 12px 6px; vertical-align: top;">
                          <div style="font-weight: 700; font-size: 1rem; color: #0F172A;"><?= htmlspecialchars($m['medicine_name']) ?> <small style="color: #64748B;"><?= htmlspecialchars($m['strength'] ?? '') ?></small></div>
                          <div style="font-size: 0.85rem; color: #475569; margin-top: 2px;">
                            <span class="badge" style="background: #E2E8F0; color: #1E293B;"><?= htmlspecialchars($m['dosage'] ?? '1 Tab') ?></span> &bull;
                            <strong><?= htmlspecialchars($m['frequency']) ?></strong> &bull;
                            <span><?= htmlspecialchars($m['duration']) ?></span>
                          </div>
                          <?php if ($m['instructions']): ?>
                            <div style="font-size: 0.83rem; color: #2563EB; margin-top: 3px;"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($m['instructions']) ?></div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>

                <?php if ($viewRx['advice']): ?>
                  <div style="background: #F8FAFC; border-radius: 8px; padding: 14px; margin-top: 20px;">
                    <strong style="font-size: 0.88rem; color: #334155;"><i class="bi bi-chat-left-quote"></i> Doctor's Advice:</strong>
                    <div style="font-size: 0.9rem; margin-top: 4px; color: #475569;"><?= nl2br(htmlspecialchars($viewRx['advice'])) ?></div>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($viewLabOrder && !empty($viewLabTests)): ?>
              <!-- Diagnostic Lab Order & Unique Token for Patient -->
              <div style="margin-top: 28px; border: 2px solid #6366F1; border-radius: 12px; overflow: hidden; background: #FAF5FF;">
                <div style="background: linear-gradient(135deg, #4F46E5, #6366F1); color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 22px;">🔬</span>
                    <div>
                      <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; font-weight: 700;">Prescribed Lab Investigations</div>
                      <div style="font-size: 14px; font-weight: 800;">Official Lab Order Token (ল্যাব টোকেন)</div>
                    </div>
                  </div>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="font-size: 11px; opacity: 0.85; text-transform: uppercase;">Unique Token:</div>
                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 900; background: rgba(255,255,255,0.22); padding: 4px 12px; border-radius: 6px; letter-spacing: 1.5px;">
                      <?= htmlspecialchars($viewLabOrder['lab_token']) ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-light no-print" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($viewLabOrder['lab_token']) ?>'); alert('Lab Token copied: <?= htmlspecialchars($viewLabOrder['lab_token']) ?>');" title="Copy Token" style="padding: 4px 8px; font-size: 12px;">
                      <i class="bi bi-clipboard"></i> Copy
                    </button>
                  </div>
                </div>

                <div style="padding: 16px 20px;">
                  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; font-size: 12px; color: #4B5563;">
                    <div>
                      Order Status:
                      <span class="badge" style="background: <?= $viewLabOrder['status'] === 'COMPLETED' ? '#DCFCE7; color: #166534;' : ($viewLabOrder['status'] === 'IN_PROGRESS' ? '#E0F2FE; color: #0369A1;' : '#EEF2FF; color: #4338CA;') ?> font-weight: 700; padding: 3px 8px; border-radius: 6px;">
                        <?= htmlspecialchars($viewLabOrder['status']) ?>
                      </span>
                      &nbsp;·&nbsp; Valid 30 days (Expires <?= date('d M Y', strtotime($viewLabOrder['expires_at'])) ?>)
                    </div>
                    <div>
                      <a href="<?= APP_URL ?>/patient/lab-reports.php?tab=orders" class="btn btn-sm btn-outline-primary no-print" style="font-size: 11px; text-decoration: none;">
                        <i class="bi bi-file-earmark-ruled"></i> View in Diagnostics &amp; Imaging Portal &rarr;
                      </a>
                    </div>
                  </div>

                  <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; color: #1E40AF;">
                    <i class="bi bi-info-circle-fill"></i> <strong>How it works:</strong> Show this <strong>Lab Token (<?= htmlspecialchars($viewLabOrder['lab_token']) ?>)</strong> to the reception of any diagnostic center (e.g. Apex Diagnostics, Popular, Ibn Sina, or hospital lab). Once your tests are completed, the lab uploads results and they will automatically appear in your MedCore account!
                  </div>

                  <table style="width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #E5E7EB;">
                    <thead>
                      <tr style="background: #F3F4F6; text-align: left; color: #374151;">
                        <th style="padding: 8px 12px; width: 35px;">#</th>
                        <th style="padding: 8px 12px;">Test Name</th>
                        <th style="padding: 8px 12px;">Category</th>
                        <th style="padding: 8px 12px;">Urgency</th>
                        <th style="padding: 8px 12px;">Instructions</th>
                        <th style="padding: 8px 12px;">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($viewLabTests as $tidx => $lt): ?>
                      <tr style="border-top: 1px solid #E5E7EB;">
                        <td style="padding: 8px 12px; color: #9CA3AF;"><?= $tidx + 1 ?></td>
                        <td style="padding: 8px 12px; font-weight: 700; color: #1F2937;">
                          <?= htmlspecialchars($lt['test_name']) ?>
                          <?php if (!empty($lt['result_summary'])): ?>
                            <div style="margin-top: 4px; font-size: 11px; color: #047857; font-weight: 500;">
                              <i class="bi bi-clipboard2-check"></i> Findings: <?= htmlspecialchars($lt['result_summary']) ?>
                              <?php if ($lt['result_value']): ?> (Value: <?= htmlspecialchars($lt['result_value']) ?>)<?php endif; ?>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td style="padding: 8px 12px; color: #6B7280;"><?= htmlspecialchars($lt['test_category'] ?: 'General') ?></td>
                        <td style="padding: 8px 12px;">
                          <span style="font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; <?= $lt['urgency'] === 'STAT' ? 'background: #FEE2E2; color: #991B1B;' : ($lt['urgency'] === 'URGENT' ? 'background: #FEF3C7; color: #92400E;' : 'background: #F3F4F6; color: #374151;') ?>">
                            <?= htmlspecialchars($lt['urgency']) ?>
                          </span>
                        </td>
                        <td style="padding: 8px 12px; color: #64748B; font-size: 11px;"><?= htmlspecialchars($lt['instructions'] ?: '—') ?></td>
                        <td style="padding: 8px 12px;">
                          <?php if ($lt['status'] === 'COMPLETED'): ?>
                            <span style="color: #16A34A; font-weight: 700;"><i class="bi bi-check-circle-fill"></i> Completed</span>
                          <?php elseif ($lt['status'] === 'IN_PROGRESS'): ?>
                            <span style="color: #0284C7; font-weight: 600;"><i class="bi bi-gear-wide-connected"></i> In Progress</span>
                          <?php else: ?>
                            <span style="color: #6366F1; font-weight: 600;"><i class="bi bi-hourglass-split"></i> Awaiting Lab</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>

                  <?php if (!empty($viewLabOrder['test_notes'])): ?>
                  <div style="margin-top: 10px; font-size: 11px; color: #4B5563; background: #fff; border: 1px dashed #D1D5DB; border-radius: 6px; padding: 8px 12px;">
                    <strong>Doctor Instructions for Lab:</strong> <?= htmlspecialchars($viewLabOrder['test_notes']) ?>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>

            <div style="border-top: 1px solid #E2E8F0; padding-top: 24px; margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end;">
              <div>
                <div style="font-size: 0.82rem; color: #64748B;">Follow-up:</div>
                <div style="font-weight: 700; color: #0F172A; font-size: 0.95rem;">
                  <?= $viewRx['follow_up_date'] ? date('d M Y', strtotime($viewRx['follow_up_date'])) : 'As needed' ?>
                </div>
                <div style="margin-top: 12px; display: inline-flex; align-items: center; gap: 6px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 4px 10px; border-radius: 6px; font-size: 0.78rem; font-weight: 600;">
                  <i class="bi bi-patch-check-fill"></i> Verified MedCore Digital Record
                </div>
              </div>
              <div style="text-align: center;">
                <div style="border-bottom: 1.5px solid #0F172A; width: 180px; margin-bottom: 6px; font-family: cursive; font-size: 1.1rem; color: #1E40AF;">
                  Dr. <?= htmlspecialchars($viewRx['doctor_name']) ?>
                </div>
                <div style="font-size: 0.8rem; color: #64748B;">Doctor Signature</div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Prescriptions Table -->
      <div class="card no-print">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
          <h3 class="card-title"><i class="bi bi-prescription2"></i> My Prescription History (<?= count($prescriptions) ?>)</h3>
          <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by Doctor, Hospital..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
              <a href="<?= APP_URL ?>/patient/prescriptions.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
          </form>
        </div>
        <div class="card-body">
          <?php if (empty($prescriptions)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-journal-x" style="font-size: 3rem; color: #CBD5E1;"></i>
              <p class="mt-2">No prescriptions recorded yet.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Prescription ID</th>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Hospital</th>
                    <th>Diagnosis</th>
                    <th>Medicines</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($prescriptions as $rx): ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($rx['prescription_uid']) ?></strong></td>
                      <td><?= date('d M Y', strtotime($rx['created_at'])) ?></td>
                      <td>Dr. <?= htmlspecialchars($rx['doctor_name']) ?> <small class="text-muted">(<?= htmlspecialchars($rx['specialization'] ?? '') ?>)</small></td>
                      <td><?= htmlspecialchars($rx['hospital_name']) ?></td>
                      <td><?= htmlspecialchars($rx['diagnosis'] ?? 'Consultation') ?></td>
                      <td><span class="badge badge-info"><?= $rx['medicine_count'] ?> items</span></td>
                      <td><span class="badge badge-success"><?= htmlspecialchars($rx['status']) ?></span></td>
                      <td>
                        <div style="display: flex; gap: 6px;">
                          <a href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= $rx['id'] ?>" target="_blank" class="btn btn-primary btn-sm" style="background: #0F766E; border-color: #0F766E; font-weight: 700; white-space: nowrap;">
                            <i class="bi bi-file-earmark-medical-fill"></i> Full Report
                          </a>
                          <a href="<?= APP_URL ?>/patient/prescriptions.php?rx=<?= urlencode($rx['prescription_uid']) ?>" class="btn btn-outline btn-sm" style="white-space: nowrap;">
                            <i class="bi bi-eye"></i> Quick View
                          </a>
                        </div>
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
