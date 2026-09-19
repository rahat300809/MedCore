<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$doctorId = (int)($_GET['id'] ?? 0);

if ($doctorId <= 0) {
    header('Location: ' . APP_URL . '/admin/doctors.php');
    exit;
}

$flashSuccess = '';
$flashError   = '';

// Handle status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $db->prepare("
            UPDATE doctors d
            JOIN users u ON d.user_id = u.id
            SET d.verification_status = 'VERIFIED',
                d.verified_by = ?,
                d.verified_at = NOW(),
                u.status = 'ACTIVE'
            WHERE d.id = ?
        ")->execute([$_SESSION['user_id'], $doctorId]);

        AuditService::log(AuditService::DOCTOR_VERIFIED, [
            'user_id'   => $_SESSION['user_id'],
            'doctor_id' => $doctorId,
        ]);
        $flashSuccess = "Doctor credentials officially VERIFIED and APPROVED globally.";
    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? 'Credentials failed verification');
        $db->prepare("
            UPDATE doctors d
            SET d.verification_status = 'REJECTED',
                d.verified_by = ?,
                d.verified_at = NOW()
            WHERE d.id = ?
        ")->execute([$_SESSION['user_id'], $doctorId]);

        AuditService::log('DOCTOR_REJECTED', [
            'user_id'   => $_SESSION['user_id'],
            'doctor_id' => $doctorId,
            'metadata'  => ['reason' => $reason]
        ]);
        $flashSuccess = "Doctor status changed to REJECTED.";
    } elseif ($action === 'suspend') {
        $db->prepare("
            UPDATE doctors d
            JOIN users u ON d.user_id = u.id
            SET d.verification_status = 'SUSPENDED',
                u.status = 'SUSPENDED'
            WHERE d.id = ?
        ")->execute([$doctorId]);
        $flashSuccess = "Doctor account SUSPENDED.";
    } elseif ($action === 'reactivate') {
        $db->prepare("
            UPDATE doctors d
            JOIN users u ON d.user_id = u.id
            SET d.verification_status = 'VERIFIED',
                u.status = 'ACTIVE'
            WHERE d.id = ?
        ")->execute([$doctorId]);
        $flashSuccess = "Doctor account REACTIVATED and VERIFIED.";
    }
}

// Fetch doctor details
$stmt = $db->prepare("
    SELECT d.*, u.email, u.phone, u.status AS user_status, u.created_at AS user_created_at,
           u.last_login_at, v.email AS verified_by_email
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN users v ON d.verified_by = v.id
    WHERE d.id = ?
");
$stmt->execute([$doctorId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header('Location: ' . APP_URL . '/admin/doctors.php');
    exit;
}

// Fetch all hospital affiliations for this doctor (Multi-hospital relationship)
$stmt = $db->prepare("
    SELECT dh.*, h.name AS hospital_name, h.hospital_uid, h.type AS hospital_type,
           h.city AS hospital_city, h.district AS hospital_district,
           dept.name AS department_name,
           u_appr.email AS approved_by_email
    FROM doctor_hospitals dh
    JOIN hospitals h ON dh.hospital_id = h.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    LEFT JOIN users u_appr ON dh.approved_by = u_appr.id
    WHERE dh.doctor_id = ?
    ORDER BY FIELD(dh.status, 'APPROVED', 'PENDING', 'SUSPENDED', 'REJECTED', 'REMOVED'), dh.joined_at DESC
");
$stmt->execute([$doctorId]);
$affiliations = $stmt->fetchAll();

// Fetch consultations count & recent activity for this doctor
$stmt = $db->prepare("
    SELECT c.*, p.full_name AS patient_name, p.patient_uid, h.name AS hospital_name
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN hospitals h ON c.hospital_id = h.id
    WHERE c.doctor_id = ?
    ORDER BY c.consultation_date DESC
    LIMIT 5
");
$stmt->execute([$doctorId]);
$recentConsultations = $stmt->fetchAll();

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dr. <?= htmlspecialchars($doctor['full_name']) ?> — Admin Dossier</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-dark: #0F172A;
      --admin-primary: #1E40AF;
    }
    .badge-pending { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .badge-verified { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .badge-rejected { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-suspended { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }

    .dossier-grid {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 24px;
    }
    @media (max-width: 900px) {
      .dossier-grid { grid-template-columns: 1fr; }
    }
    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid var(--mc-border);
      font-size: 13px;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: var(--mc-text-muted); font-weight: 500; }
    .info-val { font-weight: 600; color: var(--mc-text); text-align: right; }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" style="background: var(--admin-dark);">
    <div class="sidebar-brand" style="border-color: rgba(255,255,255,0.1);">
      <div class="brand-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">M</div>
      <div>
        <div class="brand-name" style="color:white;">MedCore</div>
        <div class="brand-sub" style="color: #94A3B8; font-size:11px;">Central Administration</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title" style="color: #64748B;">Central Oversight</div>
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i> System Dashboard
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php" class="nav-item active">
        <i class="bi bi-person-badge-fill"></i> Doctor Verification
      </a>
      <a href="<?= APP_URL ?>/admin/hospitals.php" class="nav-item">
        <i class="bi bi-building-fill-check"></i> Hospital Network
      </a>
      <a href="<?= APP_URL ?>/admin/affiliations.php" class="nav-item">
        <i class="bi bi-diagram-3-fill"></i> Hospital Affiliations
      </a>

      <div class="nav-section-title" style="color: #64748B; margin-top: 16px;">National Registry</div>
      <a href="<?= APP_URL ?>/admin/patients.php" class="nav-item">
        <i class="bi bi-people-fill"></i> Patient Registry
      </a>
      <a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-item">
        <i class="bi bi-shield-check"></i> Audit & Compliance
      </a>
    </nav>

    <div class="sidebar-footer" style="border-color: rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
        <div class="user-avatar" style="background: #3B82F6; color:white; width:36px; height:36px; font-size:13px;">
          SA
        </div>
        <div style="overflow:hidden;">
          <div style="font-weight:700; font-size:13px; color:white;"><?= htmlspecialchars($_SESSION['name'] ?? 'System Admin') ?></div>
          <div style="font-size:11px; color:#94A3B8;"><?= htmlspecialchars($_SESSION['email'] ?? 'admin@medcore.local') ?></div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-secondary btn-sm w-100" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: white;">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="portal-content">
    <header class="top-nav">
      <div class="top-nav-left">
        <a href="<?= APP_URL ?>/admin/doctors.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">
          ← Back to Doctors
        </a>
        <div>
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">DOCTOR CREDENTIAL DOSSIER</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Dr. <?= htmlspecialchars($doctor['full_name']) ?></h1>
        </div>
      </div>
      <div class="top-nav-right">
        <?php if ($doctor['verification_status'] === 'PENDING'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-primary btn-sm" style="background: #166534; border-color: #166534; font-weight: 700;">
              <i class="bi bi-patch-check-fill"></i> Approve & Verify Doctor
            </button>
          </form>
        <?php endif; ?>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <?php if ($flashSuccess): ?>
      <div class="alert alert-success mb-4" data-auto-dismiss="6000">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?>
      </div>
      <?php endif; ?>

      <div class="dossier-grid">
        
        <!-- LEFT COLUMN: PROFILE CARD & ACTIONS -->
        <div>
          <div class="card" style="padding: 24px; text-align: center; border: 1px solid var(--mc-border); margin-bottom: 20px;">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: #DBEAFE; color: #1E40AF; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; margin: 0 auto 16px;">
              <?= strtoupper(substr($doctor['full_name'], 4, 1) ?: substr($doctor['full_name'], 0, 1)) ?>
            </div>

            <h2 style="font-size: 1.25rem; font-weight: 800; margin: 0 0 4px 0;">
              <?= htmlspecialchars($doctor['full_name']) ?>
            </h2>
            <div style="font-size: 13px; color: var(--mc-text-secondary); margin-bottom: 12px;">
              <?= htmlspecialchars($doctor['specialization'] ?? 'General Physician') ?>
            </div>

            <div style="margin-bottom: 16px;">
              <?php if ($doctor['verification_status'] === 'VERIFIED'): ?>
                <span class="badge badge-verified" style="font-size: 12px; padding: 4px 10px;">
                  <i class="bi bi-patch-check-fill"></i> VERIFIED BY ADMIN
                </span>
              <?php elseif ($doctor['verification_status'] === 'PENDING'): ?>
                <span class="badge badge-pending" style="font-size: 12px; padding: 4px 10px;">
                  <i class="bi bi-hourglass-split"></i> AWAITING APPROVAL
                </span>
              <?php elseif ($doctor['verification_status'] === 'REJECTED'): ?>
                <span class="badge badge-rejected" style="font-size: 12px; padding: 4px 10px;">
                  <i class="bi bi-x-circle-fill"></i> REJECTED
                </span>
              <?php else: ?>
                <span class="badge badge-suspended" style="font-size: 12px; padding: 4px 10px;">
                  <i class="bi bi-pause-circle-fill"></i> SUSPENDED
                </span>
              <?php endif; ?>
            </div>

            <!-- REGULATORY CREDENTIALS BOX -->
            <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-md); padding: 12px; text-align: left; margin-bottom: 16px;">
              <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #1E40AF; margin-bottom: 4px;">
                Medical Board Registration
              </div>
              <div style="font-size: 15px; font-weight: 800; color: #1E3A8A; font-family: var(--font-mono);">
                <?= htmlspecialchars($doctor['medical_registration_id']) ?>
              </div>
              <div style="font-size: 11px; color: #3B82F6; margin-top: 2px;">
                Doctor UID: <?= htmlspecialchars($doctor['doctor_uid']) ?>
              </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <?php if ($doctor['verification_status'] === 'PENDING'): ?>
                <form method="POST">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" class="btn btn-primary w-100" style="background: #166534; border-color: #166534; font-weight: 700;">
                    <i class="bi bi-check-circle"></i> Approve Credentials
                  </button>
                </form>
                <button type="button" class="btn btn-danger w-100 btn-sm" onclick="document.getElementById('reject-form-box').style.display = 'block';">
                  <i class="bi bi-x-circle"></i> Reject Application
                </button>
              <?php elseif ($doctor['verification_status'] === 'VERIFIED'): ?>
                <form method="POST" onsubmit="return confirm('Suspend this doctor?');">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="suspend">
                  <button type="submit" class="btn btn-secondary w-100 btn-sm" style="color: #991B1B;">
                    <i class="bi bi-pause-circle"></i> Suspend Doctor
                  </button>
                </form>
              <?php else: ?>
                <form method="POST" onsubmit="return confirm('Reactivate & Verify this doctor?');">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="action" value="reactivate">
                  <button type="submit" class="btn btn-primary w-100 btn-sm" style="background: #166534; border-color: #166534;">
                    <i class="bi bi-arrow-repeat"></i> Reactivate & Verify
                  </button>
                </form>
              <?php endif; ?>
            </div>

            <!-- INLINE REJECT FORM -->
            <div id="reject-form-box" style="display:none; margin-top:12px; padding:12px; background:#FEF2F2; border:1px solid #FECACA; border-radius:var(--radius-md); text-align:left;">
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="reject">
                <label style="font-size:12px; font-weight:700; color:#991B1B;">Reason for rejection:</label>
                <textarea name="rejection_reason" class="form-control mb-2" rows="2" style="font-size:12px;" required placeholder="BMDC license invalid or unconfirmed..."></textarea>
                <div style="display:flex; gap:6px;">
                  <button type="submit" class="btn btn-danger btn-sm w-100">Confirm Reject</button>
                  <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('reject-form-box').style.display='none';">Cancel</button>
                </div>
              </form>
            </div>
          </div>

          <!-- CREDENTIAL DETAILS CARD -->
          <div class="card" style="padding: 20px; border: 1px solid var(--mc-border);">
            <h3 style="font-size: 14px; font-weight: 700; margin: 0 0 12px 0; color: var(--mc-text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">
              Profile Attributes
            </h3>

            <div class="info-row">
              <span class="info-label">Email</span>
              <span class="info-val"><?= htmlspecialchars($doctor['email']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Phone</span>
              <span class="info-val"><?= htmlspecialchars($doctor['phone'] ?? '-') ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Experience</span>
              <span class="info-val"><?= (int)$doctor['experience_years'] ?> Years</span>
            </div>
            <div class="info-row">
              <span class="info-label">Designation</span>
              <span class="info-val"><?= htmlspecialchars($doctor['designation'] ?? '-') ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Qualifications</span>
              <span class="info-val"><?= htmlspecialchars($doctor['qualification'] ?? '-') ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Registered Date</span>
              <span class="info-val"><?= date('M d, Y', strtotime($doctor['user_created_at'])) ?></span>
            </div>
            <?php if ($doctor['verified_at']): ?>
            <div class="info-row">
              <span class="info-label">Verified At</span>
              <span class="info-val" style="color: #166534;"><?= date('M d, Y H:i', strtotime($doctor['verified_at'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RIGHT COLUMN: MULTI-HOSPITAL AFFILIATIONS & CLINICAL FOOTPRINT -->
        <div>
          <!-- AFFILIATIONS CARD -->
          <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border); margin-bottom: 24px;">
            <div style="padding: 16px 20px; background: var(--mc-white); border-bottom: 1px solid var(--mc-border); display: flex; align-items: center; justify-content: space-between;">
              <div>
                <h2 style="font-size: 1.1rem; font-weight: 800; margin: 0;">
                  <i class="bi bi-hospital text-blue"></i> Multi-Hospital Affiliation Roster
                </h2>
                <div style="font-size: 12px; color: var(--mc-text-muted);">
                  Hospitals where Dr. <?= htmlspecialchars($doctor['full_name']) ?> is actively practicing or requested
                </div>
              </div>
              <span class="badge" style="background: #EFF6FF; color: #1E40AF; font-weight: 700;">
                <?= count($affiliations) ?> Hospital Links
              </span>
            </div>

            <div class="table-responsive">
              <table class="data-table" style="font-size: 13px;">
                <thead>
                  <tr>
                    <th>Hospital</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Employment Type</th>
                    <th>Affiliation Status</th>
                    <th>Joined</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($affiliations)): ?>
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 32px 20px; color: var(--mc-text-muted);">
                      <i class="bi bi-building-exclamation" style="font-size: 24px; display: block; margin-bottom: 6px;"></i>
                      No hospital affiliations recorded yet. Once verified, any participating hospital can add this doctor.
                    </td>
                  </tr>
                  <?php endif; ?>

                  <?php foreach ($affiliations as $aff): ?>
                  <tr>
                    <td>
                      <div style="font-weight: 700; color: var(--mc-text);">
                        <?= htmlspecialchars($aff['hospital_name']) ?>
                      </div>
                      <div style="font-size: 11px; color: var(--mc-text-muted);">
                        <?= htmlspecialchars($aff['hospital_uid']) ?> · <?= htmlspecialchars($aff['hospital_city'] ?? '') ?>
                      </div>
                    </td>

                    <td>
                      <?= htmlspecialchars($aff['department_name'] ?? 'General') ?>
                    </td>

                    <td>
                      <?= htmlspecialchars($aff['designation'] ?? 'Consultant') ?>
                    </td>

                    <td>
                      <span class="badge" style="background: #F1F5F9; color: #334155; font-size: 11px;">
                        <?= htmlspecialchars($aff['employment_type']) ?>
                      </span>
                    </td>

                    <td>
                      <?php if ($aff['status'] === 'APPROVED'): ?>
                        <span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> ACTIVE</span>
                      <?php elseif ($aff['status'] === 'PENDING'): ?>
                        <span class="badge badge-pending"><i class="bi bi-clock-fill"></i> PENDING</span>
                      <?php elseif ($aff['status'] === 'REMOVED'): ?>
                        <span class="badge badge-suspended"><i class="bi bi-dash-circle"></i> REMOVED</span>
                      <?php else: ?>
                        <span class="badge badge-rejected"><?= htmlspecialchars($aff['status']) ?></span>
                      <?php endif; ?>
                    </td>

                    <td style="font-size: 12px; color: var(--mc-text-muted);">
                      <?= $aff['joined_at'] ? date('M d, Y', strtotime($aff['joined_at'])) : '-' ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div style="padding: 12px 20px; background: #F8FAFC; border-top: 1px solid var(--mc-border); font-size: 12px; color: var(--mc-text-muted);">
              <i class="bi bi-info-circle-fill text-blue"></i>
              <strong>Hospital Autonomy:</strong> Each hospital administrator independently manages adding, assigning departments, or removing doctors from their hospital without revoking the doctor's nationwide medical license.
            </div>
          </div>

          <!-- RECENT CONSULTATIONS -->
          <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
            <div style="padding: 16px 20px; background: var(--mc-white); border-bottom: 1px solid var(--mc-border);">
              <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0;">
                <i class="bi bi-clipboard2-pulse text-teal"></i> Recent Consultations Across Hospitals
              </h2>
              <div style="font-size: 12px; color: var(--mc-text-muted);">Clinical sessions logged by this doctor</div>
            </div>

            <div class="table-responsive">
              <table class="data-table" style="font-size: 13px;">
                <thead>
                  <tr>
                    <th>Consultation UID</th>
                    <th>Hospital</th>
                    <th>Patient</th>
                    <th>Diagnosis Summary</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentConsultations)): ?>
                  <tr>
                    <td colspan="5" style="text-align: center; padding: 24px; color: var(--mc-text-muted);">
                      No consultations recorded yet.
                    </td>
                  </tr>
                  <?php endif; ?>

                  <?php foreach ($recentConsultations as $c): ?>
                  <tr>
                    <td style="font-family: var(--font-mono); font-size: 11px;">
                      <?= htmlspecialchars($c['consultation_uid']) ?>
                    </td>
                    <td>
                      <span class="badge" style="background: #F0FDFA; color: #0F766E;">
                        <?= htmlspecialchars($c['hospital_name']) ?>
                      </span>
                    </td>
                    <td>
                      <div style="font-weight: 600;"><?= htmlspecialchars($c['patient_name']) ?></div>
                      <div style="font-size: 11px; color: var(--mc-text-muted);"><?= htmlspecialchars($c['patient_uid']) ?></div>
                    </td>
                    <td>
                      <?= htmlspecialchars($c['diagnosis_summary'] ?? '-') ?>
                    </td>
                    <td style="font-size: 12px; color: var(--mc-text-muted);">
                      <?= date('M d, Y', strtotime($c['consultation_date'])) ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

    </div>
  </main>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>
</body>
</html>
