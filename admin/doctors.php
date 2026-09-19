<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();

$flashSuccess = '';
$flashError   = '';

// Handle status change actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action   = $_POST['action'] ?? '';
    $doctorId = (int)($_POST['doctor_id'] ?? 0);

    if ($doctorId > 0) {
        $stmtDoc = $db->prepare("SELECT d.*, u.email FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
        $stmtDoc->execute([$doctorId]);
        $targetDoc = $stmtDoc->fetch();

        if (!$targetDoc) {
            $flashError = "Doctor record not found.";
        } else {
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
                    'metadata'  => [
                        'doctor_name' => $targetDoc['full_name'],
                        'bmdc_reg'    => $targetDoc['medical_registration_id'],
                        'new_status'  => 'VERIFIED'
                    ]
                ]);

                $flashSuccess = "Dr. " . htmlspecialchars($targetDoc['full_name']) . " ({$targetDoc['medical_registration_id']}) has been officially APPROVED & VERIFIED. Hospitals can now affiliate this doctor to their practice rosters.";
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? 'Medical credentials could not be verified with the regulatory board.');
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

                $flashSuccess = "Dr. " . htmlspecialchars($targetDoc['full_name']) . " has been REJECTED.";
            } elseif ($action === 'suspend') {
                $db->prepare("
                    UPDATE doctors d
                    JOIN users u ON d.user_id = u.id
                    SET d.verification_status = 'SUSPENDED',
                        u.status = 'SUSPENDED'
                    WHERE d.id = ?
                ")->execute([$doctorId]);

                AuditService::log('DOCTOR_SUSPENDED', [
                    'user_id'   => $_SESSION['user_id'],
                    'doctor_id' => $doctorId,
                ]);

                $flashSuccess = "Dr. " . htmlspecialchars($targetDoc['full_name']) . " has been temporarily SUSPENDED from the network.";
            } elseif ($action === 'reactivate') {
                $db->prepare("
                    UPDATE doctors d
                    JOIN users u ON d.user_id = u.id
                    SET d.verification_status = 'VERIFIED',
                        u.status = 'ACTIVE'
                    WHERE d.id = ?
                ")->execute([$doctorId]);

                AuditService::log('DOCTOR_REACTIVATED', [
                    'user_id'   => $_SESSION['user_id'],
                    'doctor_id' => $doctorId,
                ]);

                $flashSuccess = "Dr. " . htmlspecialchars($targetDoc['full_name']) . " has been REACTIVATED and VERIFIED.";
            }
        }
    }
}

// Filter tab
$currentTab = $_GET['tab'] ?? 'all';
$search     = trim($_GET['q'] ?? '');

// Counts
$countAll       = (int)$db->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$countPending   = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'PENDING'")->fetchColumn();
$countVerified  = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'VERIFIED'")->fetchColumn();
$countRejected  = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'REJECTED'")->fetchColumn();
$countSuspended = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE verification_status = 'SUSPENDED'")->fetchColumn();

// Build query
$whereClauses = [];
$params       = [];

if ($currentTab === 'pending') {
    $whereClauses[] = "d.verification_status = 'PENDING'";
} elseif ($currentTab === 'verified') {
    $whereClauses[] = "d.verification_status = 'VERIFIED'";
} elseif ($currentTab === 'rejected') {
    $whereClauses[] = "d.verification_status = 'REJECTED'";
} elseif ($currentTab === 'suspended') {
    $whereClauses[] = "d.verification_status = 'SUSPENDED'";
}

if ($search !== '') {
    $whereClauses[] = "(d.full_name LIKE ? OR d.medical_registration_id LIKE ? OR d.doctor_uid LIKE ? OR u.email LIKE ? OR d.specialization LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $db->prepare("
    SELECT d.*, u.email, u.phone, u.status AS user_status, u.created_at AS registered_at,
           v_user.email AS verified_by_email,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.doctor_id = d.id AND dh.status = 'APPROVED') AS active_hospital_count,
           (SELECT GROUP_CONCAT(h.name SEPARATOR ', ') FROM doctor_hospitals dh JOIN hospitals h ON dh.hospital_id = h.id WHERE dh.doctor_id = d.id AND dh.status = 'APPROVED') AS affiliated_hospitals_list
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN users v_user ON d.verified_by = v_user.id
    {$whereSql}
    ORDER BY FIELD(d.verification_status, 'PENDING', 'VERIFIED', 'SUSPENDED', 'REJECTED'), d.created_at DESC
");
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Doctor Verification & Management — MedCore Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-dark: #0F172A;
      --admin-primary: #1E40AF;
    }
    .filter-tabs {
      display: flex;
      gap: 6px;
      border-bottom: 1px solid var(--mc-border);
      padding-bottom: 0;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .filter-tab {
      padding: 10px 18px;
      font-size: 13px;
      font-weight: 600;
      color: var(--mc-text-secondary);
      text-decoration: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -1px;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
    }
    .filter-tab:hover { color: var(--mc-blue); }
    .filter-tab.active {
      color: var(--admin-primary);
      border-bottom-color: var(--admin-primary);
      font-weight: 700;
    }
    .badge-pending { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .badge-verified { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .badge-rejected { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-suspended { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }

    .action-btn-group {
      display: flex;
      gap: 6px;
      align-items: center;
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
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i> System Dashboard
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php" class="nav-item active">
        <i class="bi bi-person-badge-fill"></i> Doctor Verification
        <?php if ($countPending > 0): ?>
          <span class="badge badge-pending" style="margin-left:auto; font-size:11px; padding:2px 6px;">
            <?= $countPending ?>
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
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">DOCTOR VERIFICATION AUTHORITY</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Doctor Credential Review & Global Registry</h1>
        </div>
      </div>
      <div class="top-nav-right">
        <a href="<?= APP_URL ?>/admin/doctor-create.php" class="btn btn-primary btn-sm" style="background: var(--admin-primary); border: none;">
          <i class="bi bi-person-plus-fill"></i> Add & Verify Doctor
        </a>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <?php if ($flashSuccess): ?>
      <div class="alert alert-success mb-4" data-auto-dismiss="7000">
        <i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?>
      </div>
      <?php endif; ?>

      <?php if ($flashError): ?>
      <div class="alert alert-error mb-4" data-auto-dismiss="5000">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?>
      </div>
      <?php endif; ?>

      <!-- Search & Tab Bar -->
      <div style="background: var(--mc-white); border: 1px solid var(--mc-border); border-radius: var(--radius-lg); padding: 16px 20px; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 12px;">
          <!-- FILTER TABS -->
          <div class="filter-tabs" style="margin-bottom:0; border-bottom:none;">
            <a href="<?= APP_URL ?>/admin/doctors.php?tab=all<?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="filter-tab <?= $currentTab === 'all' ? 'active' : '' ?>">
              All Registered (<?= $countAll ?>)
            </a>
            <a href="<?= APP_URL ?>/admin/doctors.php?tab=pending<?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="filter-tab <?= $currentTab === 'pending' ? 'active' : '' ?>">
              <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#F59E0B;"></span>
              Pending Approval (<?= $countPending ?>)
            </a>
            <a href="<?= APP_URL ?>/admin/doctors.php?tab=verified<?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="filter-tab <?= $currentTab === 'verified' ? 'active' : '' ?>">
              <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10B981;"></span>
              Verified (<?= $countVerified ?>)
            </a>
            <a href="<?= APP_URL ?>/admin/doctors.php?tab=rejected<?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="filter-tab <?= $currentTab === 'rejected' ? 'active' : '' ?>">
              Rejected (<?= $countRejected ?>)
            </a>
            <a href="<?= APP_URL ?>/admin/doctors.php?tab=suspended<?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="filter-tab <?= $currentTab === 'suspended' ? 'active' : '' ?>">
              Suspended (<?= $countSuspended ?>)
            </a>
          </div>

          <!-- SEARCH FORM -->
          <form method="GET" style="display: flex; gap: 8px; min-width: 320px;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($currentTab) ?>">
            <div class="search-bar" style="flex: 1;">
              <i class="bi bi-search"></i>
              <input type="text" name="q" placeholder="Search by BMDC Reg, Name, UID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <?php if ($search): ?>
              <a href="<?= APP_URL ?>/admin/doctors.php?tab=<?= htmlspecialchars($currentTab) ?>" class="btn btn-secondary btn-sm" title="Clear search">
                <i class="bi bi-x-lg"></i>
              </a>
            <?php endif; ?>
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
          </form>
        </div>

        <div style="font-size: 12px; color: var(--mc-text-muted);">
          <i class="bi bi-info-circle"></i>
          <strong>Governance Rule:</strong> When you approve a doctor here, their status changes to <code>VERIFIED</code> nationwide. Any hospital admin can then add them to their hospital's departments without needing central approval for each hospital.
        </div>
      </div>

      <!-- DOCTORS TABLE -->
      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Doctor</th>
                <th>BMDC / Reg ID</th>
                <th>Specialization & Qualification</th>
                <th>Experience</th>
                <th>Hospital Affiliations</th>
                <th>Global Status</th>
                <th>Registered / Verified</th>
                <th style="text-align: right;">Central Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($doctors)): ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 48px 20px;">
                  <div style="font-size: 32px; color: var(--mc-text-muted); margin-bottom: 8px;"><i class="bi bi-person-x"></i></div>
                  <div style="font-weight: 700; font-size: 15px;">No doctors found</div>
                  <div style="font-size: 13px; color: var(--mc-text-muted);">
                    <?= $search ? 'No results matching "' . htmlspecialchars($search) . '". Try a different search query.' : 'There are no doctors in this category.' ?>
                  </div>
                </td>
              </tr>
              <?php endif; ?>

              <?php foreach ($doctors as $doc): ?>
              <tr>
                <!-- Doctor Profile -->
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #EFF6FF; color: #1E40AF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0;">
                      <?= strtoupper(substr($doc['full_name'], 4, 1) ?: substr($doc['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                      <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $doc['id'] ?>" style="font-weight: 700; color: var(--mc-text); text-decoration: none;">
                        <?= htmlspecialchars($doc['full_name']) ?>
                      </a>
                      <div style="font-size: 11px; color: var(--mc-text-muted); font-family: var(--font-mono);">
                        <?= htmlspecialchars($doc['doctor_uid']) ?> · <?= htmlspecialchars($doc['email']) ?>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Medical Reg ID -->
                <td>
                  <strong style="color: #1E40AF; font-family: var(--font-mono); font-size: 13px;">
                    <?= htmlspecialchars($doc['medical_registration_id']) ?>
                  </strong>
                </td>

                <!-- Specialization -->
                <td>
                  <div style="font-weight: 600;"><?= htmlspecialchars($doc['specialization'] ?? 'General Practitioner') ?></div>
                  <div style="font-size: 11px; color: var(--mc-text-muted); max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?= htmlspecialchars($doc['qualification'] ?? '-') ?>
                  </div>
                </td>

                <!-- Experience -->
                <td>
                  <?= (int)$doc['experience_years'] ?> yrs
                </td>

                <!-- Affiliations -->
                <td>
                  <?php if ($doc['active_hospital_count'] > 0): ?>
                    <span class="badge" style="background: #EFF6FF; color: #1E40AF; font-weight: 700;">
                      <i class="bi bi-hospital"></i> <?= $doc['active_hospital_count'] ?> Hospital<?= $doc['active_hospital_count'] > 1 ? 's' : '' ?>
                    </span>
                    <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 2px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($doc['affiliated_hospitals_list'] ?? '') ?>">
                      <?= htmlspecialchars($doc['affiliated_hospitals_list'] ?? '') ?>
                    </div>
                  <?php else: ?>
                    <span style="font-size: 12px; color: var(--mc-text-muted);">None yet</span>
                  <?php endif; ?>
                </td>

                <!-- Global Status -->
                <td>
                  <?php if ($doc['verification_status'] === 'VERIFIED'): ?>
                    <span class="badge badge-verified"><i class="bi bi-patch-check-fill"></i> VERIFIED</span>
                  <?php elseif ($doc['verification_status'] === 'PENDING'): ?>
                    <span class="badge badge-pending"><i class="bi bi-hourglass-split"></i> PENDING</span>
                  <?php elseif ($doc['verification_status'] === 'REJECTED'): ?>
                    <span class="badge badge-rejected"><i class="bi bi-x-circle-fill"></i> REJECTED</span>
                  <?php else: ?>
                    <span class="badge badge-suspended"><i class="bi bi-pause-circle-fill"></i> SUSPENDED</span>
                  <?php endif; ?>
                </td>

                <!-- Dates -->
                <td style="font-size: 11px; color: var(--mc-text-muted);">
                  <div>Reg: <?= date('M d, Y', strtotime($doc['registered_at'])) ?></div>
                  <?php if ($doc['verified_at']): ?>
                    <div style="color: #166534;">Ver: <?= date('M d, Y', strtotime($doc['verified_at'])) ?></div>
                  <?php endif; ?>
                </td>

                <!-- Actions -->
                <td style="text-align: right;">
                  <div class="action-btn-group" style="justify-content: flex-end;">
                    
                    <?php if ($doc['verification_status'] === 'PENDING'): ?>
                      <!-- Direct Approve -->
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Approve Dr. <?= addslashes($doc['full_name']) ?> globally? Hospitals will be able to affiliate this doctor.');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-primary btn-sm" style="background: #166534; border-color: #166534; font-weight: 700; padding: 4px 10px; font-size: 12px;">
                          <i class="bi bi-check-lg"></i> Approve
                        </button>
                      </form>

                      <!-- Reject Button -->
                      <button type="button" class="btn btn-danger btn-sm" style="padding: 4px 10px; font-size: 12px;" onclick="openRejectModal(<?= $doc['id'] ?>, '<?= addslashes($doc['full_name']) ?>')">
                        <i class="bi bi-x-lg"></i> Reject
                      </button>

                    <?php elseif ($doc['verification_status'] === 'VERIFIED'): ?>
                      <!-- Suspend -->
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend Dr. <?= addslashes($doc['full_name']) ?> from practicing across all hospitals?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                        <input type="hidden" name="action" value="suspend">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #991B1B; padding: 4px 8px; font-size: 12px;" title="Suspend Doctor">
                          <i class="bi bi-pause-circle"></i> Suspend
                        </button>
                      </form>

                    <?php elseif ($doc['verification_status'] === 'SUSPENDED' || $doc['verification_status'] === 'REJECTED'): ?>
                      <!-- Reactivate / Approve -->
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Reactivate & Verify Dr. <?= addslashes($doc['full_name']) ?>?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                        <input type="hidden" name="action" value="reactivate">
                        <button type="submit" class="btn btn-primary btn-sm" style="background: #166534; border-color: #166534; padding: 4px 8px; font-size: 12px;">
                          <i class="bi bi-arrow-repeat"></i> Reactivate
                        </button>
                      </form>
                    <?php endif; ?>

                    <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $doc['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 12px;" title="View Dossier">
                      <i class="bi bi-eye"></i>
                    </a>
                  </div>
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

<!-- REJECTION REASON MODAL -->
<div id="reject-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
  <div style="background: white; border-radius: var(--radius-xl); max-width: 480px; width: 100%; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
    <h3 style="font-size: 1.2rem; font-weight: 800; color: #991B1B; margin: 0 0 8px 0;">
      <i class="bi bi-x-circle-fill"></i> Reject Doctor Application
    </h3>
    <p style="font-size: 13px; color: var(--mc-text-secondary); margin: 0 0 16px 0;">
      Provide a formal regulatory reason for rejecting <strong id="modal-doctor-name"></strong>.
    </p>

    <form method="POST" id="modal-reject-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="doctor_id" id="modal-doctor-id">
      <input type="hidden" name="action" value="reject">

      <div class="form-group mb-4">
        <label class="form-label" for="rejection_reason">Rejection Reason</label>
        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" required
          placeholder="e.g. BMDC Registration number could not be authenticated against the national medical database."></textarea>
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeRejectModal()">Cancel</button>
        <button type="submit" class="btn btn-danger btn-sm" style="font-weight: 700;">Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

function openRejectModal(docId, docName) {
  document.getElementById('modal-doctor-id').value = docId;
  document.getElementById('modal-doctor-name').textContent = docName;
  const modal = document.getElementById('reject-modal');
  modal.style.display = 'flex';
}

function closeRejectModal() {
  document.getElementById('reject-modal').style.display = 'none';
}
</script>
</body>
</html>
