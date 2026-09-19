<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();

// Quick approval action from dashboard
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action'])) {
    validateCsrf();
    $action = $_POST['quick_action'];
    $docId  = (int)($_POST['doctor_id'] ?? 0);

    if ($docId > 0) {
        if ($action === 'approve_doctor') {
            $stmt = $db->prepare("
                UPDATE doctors d
                JOIN users u ON d.user_id = u.id
                SET d.verification_status = 'VERIFIED',
                    d.verified_by = ?,
                    d.verified_at = NOW(),
                    u.status = 'ACTIVE'
                WHERE d.id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $docId]);

            AuditService::log(AuditService::DOCTOR_VERIFIED, [
                'user_id'   => $_SESSION['user_id'],
                'doctor_id' => $docId,
                'metadata'  => ['source' => 'admin_dashboard_quick_action', 'status' => 'VERIFIED']
            ]);

            $flashSuccess = "Doctor ID #{$docId} has been successfully verified & approved globally.";
        } elseif ($action === 'reject_doctor') {
            $reason = trim($_POST['rejection_reason'] ?? 'Credentials could not be verified by Central Board');
            $stmt = $db->prepare("
                UPDATE doctors d
                SET d.verification_status = 'REJECTED',
                    d.verified_by = ?,
                    d.verified_at = NOW()
                WHERE d.id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $docId]);

            AuditService::log('DOCTOR_REJECTED', [
                'user_id'   => $_SESSION['user_id'],
                'doctor_id' => $docId,
                'metadata'  => ['reason' => $reason]
            ]);

            $flashSuccess = "Doctor ID #{$docId} has been marked as REJECTED.";
        }
    }
}

// Fetch comprehensive ecosystem stats
$stats = [];
$stats['total_doctors']      = (int)$db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$stats['pending_doctors']    = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'PENDING'")->fetchColumn();
$stats['verified_doctors']   = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'VERIFIED'")->fetchColumn();

$stats['total_hospitals']    = (int)$db->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
$stats['verified_hospitals'] = (int)$db->query("SELECT COUNT(*) FROM hospitals WHERE verification_status = 'VERIFIED'")->fetchColumn();

$stats['total_affiliations'] = (int)$db->query("SELECT COUNT(*) FROM doctor_hospitals WHERE status = 'APPROVED'")->fetchColumn();
$stats['pending_affiliations']=(int)$db->query("SELECT COUNT(*) FROM doctor_hospitals WHERE status = 'PENDING'")->fetchColumn();

$stats['total_patients']     = (int)$db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$stats['total_consultations']= (int)$db->query("SELECT COUNT(*) FROM consultations")->fetchColumn();
$stats['total_prescriptions']= (int)$db->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn();

// Fetch Pending Doctors Queue (Top priority for Admin)
$stmt = $db->prepare("
    SELECT d.*, u.email, u.phone, u.created_at AS registered_at
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.verification_status = 'PENDING'
    ORDER BY d.created_at ASC
    LIMIT 6
");
$stmt->execute();
$pendingDoctors = $stmt->fetchAll();

// Fetch Recently Approved Doctors & their Affiliations count
$stmt = $db->prepare("
    SELECT d.*, u.email,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.doctor_id = d.id AND dh.status = 'APPROVED') AS hospital_count
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.verification_status = 'VERIFIED'
    ORDER BY d.verified_at DESC, d.updated_at DESC
    LIMIT 5
");
$stmt->execute();
$recentApprovedDoctors = $stmt->fetchAll();

// Fetch Hospital Network Overview
$stmt = $db->prepare("
    SELECT h.*,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id = h.id AND dh.status = 'APPROVED') AS doctor_count,
           (SELECT COUNT(*) FROM departments dep WHERE dep.hospital_id = h.id) AS dept_count
    FROM hospitals h
    ORDER BY doctor_count DESC, h.name ASC
    LIMIT 5
");
$stmt->execute();
$topHospitals = $stmt->fetchAll();

// Fetch Recent Audit Activity Stream
$stmt = $db->prepare("
    SELECT al.*, u.email AS actor_email, u.role AS actor_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 8
");
$stmt->execute();
$recentLogs = $stmt->fetchAll();

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — MedCore Central Administration</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-primary: #1E40AF;
      --admin-dark: #0F172A;
      --admin-surface: #1E293B;
    }
    .badge-pending { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .badge-verified { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .badge-rejected { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-suspended { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }

    .stat-card-admin {
      background: var(--mc-white);
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-lg);
      padding: var(--space-5);
      transition: var(--transition-slow);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .stat-card-admin:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }
    .stat-card-admin .stat-num {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--mc-text);
      line-height: 1;
      margin: 8px 0 4px;
    }
    .stat-card-admin .stat-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--mc-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .stat-icon {
      width: 42px;
      height: 42px;
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
    }

    .doctor-pending-card {
      background: #FFFBEB;
      border: 1px solid #FDE68A;
      border-radius: var(--radius-md);
      padding: var(--space-4);
      margin-bottom: var(--space-3);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      transition: var(--transition);
    }
    .doctor-pending-card:hover {
      background: #FEF3C7;
      border-color: #FCD34D;
    }
    .doctor-avatar-circle {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #DBEAFE;
      color: #1E40AF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 16px;
      flex-shrink: 0;
    }
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
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item active">
        <i class="bi bi-grid-1x2-fill"></i> System Dashboard
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i> Doctor Verification
        <?php if ($stats['pending_doctors'] > 0): ?>
          <span class="badge badge-pending" style="margin-left:auto; font-size:11px; padding:2px 6px;">
            <?= $stats['pending_doctors'] ?>
          </span>
        <?php endif; ?>
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
    <!-- Top Nav -->
    <header class="top-nav">
      <div class="top-nav-left">
        <button class="mobile-sidebar-toggle btn btn-sm btn-ghost d-md-none" onclick="toggleSidebar()">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">CENTRAL GOVERNANCE ENGINE</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Platform Overview & Verification Hub</h1>
        </div>
      </div>
      <div class="top-nav-right" style="display: flex; gap: 10px;">
        <a href="<?= APP_URL ?>/admin/doctor-create.php" class="btn btn-primary btn-sm" style="background: var(--admin-primary); border: none;">
          <i class="bi bi-plus-circle"></i> Register Doctor
        </a>
        <a href="<?= APP_URL ?>/admin/doctors.php?tab=pending" class="btn btn-secondary btn-sm">
          <i class="bi bi-patch-check"></i> Pending Approvals (<?= $stats['pending_doctors'] ?>)
        </a>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <?php if ($flashSuccess): ?>
      <div class="alert alert-success mb-5" data-auto-dismiss="6000">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?>
      </div>
      <?php endif; ?>

      <?php if ($stats['pending_doctors'] > 0): ?>
      <!-- ACTION CALLOUT BANNER -->
      <div style="background: linear-gradient(135deg, #FEF3C7, #FDE68A); border: 1.5px solid #F59E0B; border-radius: var(--radius-lg); padding: 18px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 16px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #F59E0B; color: white; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">
            <i class="bi bi-exclamation-octagon-fill"></i>
          </div>
          <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; color: #78350F; margin: 0 0 2px 0;">
              <?= $stats['pending_doctors'] ?> Doctor<?= $stats['pending_doctors'] > 1 ? 's' : '' ?> Awaiting BMDC Credential Approval
            </h3>
            <p style="font-size: 13px; color: #92400E; margin: 0;">
              Doctors cannot be affiliated with any hospital until verified by the Central Admin board.
            </p>
          </div>
        </div>
        <a href="<?= APP_URL ?>/admin/doctors.php?tab=pending" class="btn btn-primary btn-sm" style="background: #D97706; border-color: #B45309; font-weight: 700; white-space: nowrap;">
          Review Verification Queue →
        </a>
      </div>
      <?php endif; ?>

      <!-- ECOSYSTEM STATS GRID -->
      <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
        <!-- Stat 1 -->
        <div class="stat-card-admin">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <span class="stat-label">Total Doctors</span>
            <div class="stat-icon" style="background: #EFF6FF; color: #2563EB;">
              <i class="bi bi-person-badge"></i>
            </div>
          </div>
          <div class="stat-num"><?= $stats['total_doctors'] ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted); display: flex; gap: 8px;">
            <span style="color: #166534; font-weight: 600;">✓ <?= $stats['verified_doctors'] ?> Verified</span>
            <span>·</span>
            <span style="color: #92400E; font-weight: 600;">⏳ <?= $stats['pending_doctors'] ?> Pending</span>
          </div>
        </div>

        <!-- Stat 2 -->
        <div class="stat-card-admin">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <span class="stat-label">Hospitals in Network</span>
            <div class="stat-icon" style="background: #ECFDF5; color: #059669;">
              <i class="bi bi-building"></i>
            </div>
          </div>
          <div class="stat-num"><?= $stats['total_hospitals'] ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted);">
            <span style="color: #059669; font-weight: 600;">100% Accredited</span> across districts
          </div>
        </div>

        <!-- Stat 3 -->
        <div class="stat-card-admin">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <span class="stat-label">Multi-Hospital Affiliations</span>
            <div class="stat-icon" style="background: #F5F3FF; color: #7C3AED;">
              <i class="bi bi-diagram-3"></i>
            </div>
          </div>
          <div class="stat-num"><?= $stats['total_affiliations'] ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted);">
            Active Doctor-Hospital links
          </div>
        </div>

        <!-- Stat 4 -->
        <div class="stat-card-admin">
          <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <span class="stat-label">Total Consultations</span>
            <div class="stat-icon" style="background: #FFF1F2; color: #E11D48;">
              <i class="bi bi-clipboard2-pulse"></i>
            </div>
          </div>
          <div class="stat-num"><?= $stats['total_consultations'] ?></div>
          <div style="font-size: 12px; color: var(--mc-text-muted);">
            Across all network hospitals
          </div>
        </div>
      </div>

      <!-- TWO COLUMN LAYOUT: PENDING QUEUE + HOSPITAL ECOSYSTEM -->
      <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 24px; margin-bottom: 28px;">
        
        <!-- PENDING VERIFICATIONS / RECENT DOCTORS -->
        <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
          <div style="padding: 16px 20px; background: var(--mc-white); border-bottom: 1px solid var(--mc-border); display: flex; align-items: center; justify-content: space-between;">
            <div>
              <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0;">
                <i class="bi bi-patch-question-fill text-amber-500" style="color: #F59E0B;"></i>
                Doctor Verification Queue
              </h2>
              <div style="font-size: 12px; color: var(--mc-text-muted);">Review and approve BMDC license credentials</div>
            </div>
            <a href="<?= APP_URL ?>/admin/doctors.php" class="btn btn-secondary btn-sm" style="font-size: 12px;">View All Doctors</a>
          </div>

          <div style="padding: 16px 20px;">
            <?php if (empty($pendingDoctors)): ?>
              <div class="empty-state" style="padding: 24px; text-align: center;">
                <div style="font-size: 32px; color: #10B981; margin-bottom: 8px;"><i class="bi bi-check-circle-fill"></i></div>
                <div style="font-weight: 700; font-size: 14px;">All Doctor Verifications Up to Date!</div>
                <div style="font-size: 12px; color: var(--mc-text-muted);">No pending applications currently in queue.</div>
              </div>
            <?php else: ?>
              <?php foreach ($pendingDoctors as $doc): ?>
              <div class="doctor-pending-card">
                <div style="display: flex; align-items: center; gap: 14px;">
                  <div class="doctor-avatar-circle">
                    <?= strtoupper(substr($doc['full_name'], 4, 1) ?: substr($doc['full_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-weight: 700; font-size: 14px; color: #0F172A;">
                      <?= htmlspecialchars($doc['full_name']) ?>
                    </div>
                    <div style="font-size: 12px; color: #64748B;">
                      <strong style="color: #1E40AF;"><?= htmlspecialchars($doc['medical_registration_id']) ?></strong>
                      · <?= htmlspecialchars($doc['specialization'] ?? 'General') ?>
                      · <?= (int)$doc['experience_years'] ?> yrs exp
                    </div>
                    <div style="font-size: 11px; color: #94A3B8; margin-top: 2px;">
                      Applied: <?= date('M d, Y h:i A', strtotime($doc['registered_at'])) ?>
                    </div>
                  </div>
                </div>

                <div style="display: flex; gap: 8px; flex-shrink: 0;">
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                    <input type="hidden" name="quick_action" value="approve_doctor">
                    <button type="submit" class="btn btn-primary btn-sm" style="background: #166534; border-color: #166534; padding: 5px 12px; font-size: 12px;">
                      <i class="bi bi-check-lg"></i> Approve
                    </button>
                  </form>
                  <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $doc['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 5px 10px; font-size: 12px;">
                    Inspect
                  </a>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <!-- RECENTLY VERIFIED -->
            <div style="margin-top: 20px; border-top: 1px solid var(--mc-border); padding-top: 16px;">
              <div style="font-weight: 700; font-size: 13px; color: var(--mc-text-secondary); margin-bottom: 10px;">
                Recently Verified Doctors & Multi-Hospital Practice
              </div>
              <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($recentApprovedDoctors as $rd): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--mc-bg); border-radius: var(--radius-md); font-size: 13px;">
                  <div>
                    <strong><?= htmlspecialchars($rd['full_name']) ?></strong>
                    <span style="font-size: 11px; color: var(--mc-text-muted); margin-left: 6px;">(<?= htmlspecialchars($rd['medical_registration_id']) ?>)</span>
                    <div style="font-size: 11px; color: var(--mc-text-muted);"><?= htmlspecialchars($rd['specialization']) ?></div>
                  </div>
                  <div style="text-align: right;">
                    <span class="badge badge-verified" style="font-size: 11px;">
                      <i class="bi bi-patch-check-fill"></i> Verified
                    </span>
                    <div style="font-size: 11px; color: var(--mc-text-secondary); margin-top: 2px;">
                      Practicing at <strong><?= $rd['hospital_count'] ?></strong> hospital<?= $rd['hospital_count'] != 1 ? 's' : '' ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- HOSPITAL ECOSYSTEM OVERVIEW -->
        <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
          <div style="padding: 16px 20px; background: var(--mc-white); border-bottom: 1px solid var(--mc-border); display: flex; align-items: center; justify-content: space-between;">
            <div>
              <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0;">
                <i class="bi bi-hospital text-blue"></i> Hospital Ecosystem
              </h2>
              <div style="font-size: 12px; color: var(--mc-text-muted);">Participating medical centers</div>
            </div>
            <a href="<?= APP_URL ?>/admin/hospitals.php" class="btn btn-secondary btn-sm" style="font-size: 12px;">Manage</a>
          </div>

          <div style="padding: 16px 20px;">
            <?php foreach ($topHospitals as $hosp): ?>
            <div style="padding: 12px; border: 1px solid var(--mc-border); border-radius: var(--radius-md); margin-bottom: 12px; background: var(--mc-white);">
              <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 6px;">
                <div>
                  <div style="font-weight: 700; font-size: 14px;"><?= htmlspecialchars($hosp['name']) ?></div>
                  <div style="font-size: 12px; color: var(--mc-text-muted);">
                    <?= htmlspecialchars($hosp['city'] ?? 'Dhaka') ?> · <?= ucfirst(strtolower($hosp['type'])) ?>
                  </div>
                </div>
                <span class="badge badge-verified" style="font-size: 10px;">Accredited</span>
              </div>
              <div style="display: flex; gap: 16px; font-size: 12px; color: var(--mc-text-secondary); margin-top: 8px; border-top: 1px dashed var(--mc-border); padding-top: 6px;">
                <div><i class="bi bi-people-fill text-blue"></i> <strong><?= $hosp['doctor_count'] ?></strong> Affiliated Doctors</div>
                <div><i class="bi bi-grid-fill text-teal"></i> <strong><?= $hosp['dept_count'] ?></strong> Departments</div>
              </div>
            </div>
            <?php endforeach; ?>

            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: var(--radius-md); padding: 12px; margin-top: 16px; font-size: 12px; color: #166534;">
              <i class="bi bi-info-circle-fill"></i>
              <strong>Hospitalization Flow:</strong> Once you verify a doctor, all these hospitals can independently add, schedule, or remove the doctor as needed.
            </div>
          </div>
        </div>

      </div>

      <!-- AUDIT STREAM -->
      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
        <div style="padding: 16px 20px; background: var(--mc-white); border-bottom: 1px solid var(--mc-border); display: flex; align-items: center; justify-content: space-between;">
          <div>
            <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0;">
              <i class="bi bi-shield-shaded text-slate-600"></i> Central Audit & Security Trail
            </h2>
            <div style="font-size: 12px; color: var(--mc-text-muted);">Live stream of verifications, doctor affiliations, and clinical record access</div>
          </div>
          <a href="<?= APP_URL ?>/admin/audit-logs.php" class="btn btn-secondary btn-sm" style="font-size: 12px;">Full Log Stream</a>
        </div>

        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Action</th>
                <th>Actor</th>
                <th>Details / Target</th>
                <th>Severity</th>
                <th>IP Address</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentLogs as $log): ?>
              <tr>
                <td style="white-space:nowrap; font-family: var(--font-mono); font-size: 11px; color: var(--mc-text-muted);">
                  <?= date('M d H:i:s', strtotime($log['created_at'])) ?>
                </td>
                <td>
                  <span class="badge" style="background: #F1F5F9; color: #334155; font-family: var(--font-mono); font-size: 11px;">
                    <?= htmlspecialchars($log['action']) ?>
                  </span>
                </td>
                <td>
                  <div style="font-weight: 600;"><?= htmlspecialchars($log['actor_email'] ?? 'System') ?></div>
                  <div style="font-size: 11px; color: var(--mc-text-muted);"><?= htmlspecialchars($log['actor_role'] ?? 'SYSTEM') ?></div>
                </td>
                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  <?= htmlspecialchars($log['metadata'] ?? '-') ?>
                </td>
                <td>
                  <?php if ($log['severity'] === 'CRITICAL'): ?>
                    <span class="badge badge-rejected">CRITICAL</span>
                  <?php elseif ($log['severity'] === 'WARNING'): ?>
                    <span class="badge badge-pending">WARNING</span>
                  <?php else: ?>
                    <span class="badge" style="background: #F8FAFC; color: #64748B;">INFO</span>
                  <?php endif; ?>
                </td>
                <td style="font-family: var(--font-mono); font-size: 11px; color: var(--mc-text-muted);">
                  <?= htmlspecialchars($log['ip_address']) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
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
