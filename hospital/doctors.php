<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$success = '';
$error   = '';

// Handle add / affiliate doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    validateCsrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $deptId     = (int)($_POST['department_id'] ?? 0);
    $desig      = trim($_POST['designation'] ?? 'Consultant');
    $empType    = trim($_POST['employment_type'] ?? 'FULL_TIME');

    if (empty($identifier)) {
        $error = 'Please enter Doctor UID (DR-XXXXXX), BMDC Registration number, or Email.';
    } else {
        $stmtDoc = $db->prepare("
            SELECT d.*, u.email
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            WHERE d.doctor_uid = ?
               OR d.medical_registration_id = ?
               OR u.email = ?
            LIMIT 1
        ");
        $stmtDoc->execute([$identifier, $identifier, $identifier]);
        $doc = $stmtDoc->fetch();

        if (!$doc) {
            $error = 'No doctor found matching "' . htmlspecialchars($identifier) . '". The doctor must register on MedCore first.';
        } elseif ($doc['verification_status'] !== 'VERIFIED') {
            $statusName = $doc['verification_status'];
            $error = 'Cannot affiliate Dr. ' . htmlspecialchars($doc['full_name']) . ' (' . htmlspecialchars($doc['medical_registration_id']) . '): Global status is ' . htmlspecialchars($statusName) . '. Doctors must be approved by the website Main Admin before any hospital can add them to their roster.';
        } else {
            // Check if already affiliated with this hospital
            $stmtCheck = $db->prepare("SELECT * FROM doctor_hospitals WHERE doctor_id = ? AND hospital_id = ?");
            $stmtCheck->execute([$doc['id'], $hospitalId]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                if ($existing['status'] === 'APPROVED') {
                    $error = 'Dr. ' . htmlspecialchars($doc['full_name']) . ' is already an active affiliated doctor at this hospital.';
                } else {
                    // Reactivate / update existing affiliation
                    $stmtUpdate = $db->prepare("
                        UPDATE doctor_hospitals
                        SET department_id = ?, designation = ?, employment_type = ?,
                            status = 'APPROVED', joined_at = CURDATE(), approved_by = ?, approved_at = NOW(), rejection_reason = NULL
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$deptId ?: null, $desig, $empType, $_SESSION['user_id'], $existing['id']]);

                    AuditService::log('AFFILIATE_DOCTOR_REACTIVATED', [
                        'user_id'    => $_SESSION['user_id'],
                        'hospital_id'=> $hospitalId,
                        'doctor_id'  => $doc['id'],
                        'metadata'   => ['doctor_name' => $doc['full_name'], 'designation' => $desig, 'type' => $empType],
                    ]);

                    $success = 'Dr. ' . htmlspecialchars($doc['full_name']) . ' has been re-affiliated to ' . htmlspecialchars($hospital['name']) . ' successfully!';
                }
            } else {
                $stmtInsert = $db->prepare("
                    INSERT INTO doctor_hospitals
                        (doctor_id, hospital_id, department_id, designation, employment_type, status, joined_at, approved_by, approved_at)
                    VALUES (?, ?, ?, ?, ?, 'APPROVED', CURDATE(), ?, NOW())
                ");
                $stmtInsert->execute([
                    $doc['id'], $hospitalId, $deptId ?: null, $desig, $empType, $_SESSION['user_id']
                ]);

                AuditService::log('AFFILIATE_DOCTOR', [
                    'user_id'    => $_SESSION['user_id'],
                    'hospital_id'=> $hospitalId,
                    'doctor_id'  => $doc['id'],
                    'metadata'   => ['doctor_name' => $doc['full_name'], 'designation' => $desig, 'type' => $empType],
                ]);

                $success = 'Dr. ' . htmlspecialchars($doc['full_name']) . ' has been successfully added to ' . htmlspecialchars($hospital['name']) . ' (' . htmlspecialchars($empType) . ')!';
            }
        }
    }
}

// Handle status change (Remove / Suspend / Reactivate at this hospital)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    validateCsrf();
    $dhId = (int)$_POST['dh_id'];
    $newStatus = $_POST['new_status'];
    $reason = trim($_POST['status_reason'] ?? '');

    if (in_array($newStatus, ['APPROVED', 'SUSPENDED', 'REMOVED'])) {
        $stmtGet = $db->prepare("SELECT dh.*, d.full_name FROM doctor_hospitals dh JOIN doctors d ON dh.doctor_id = d.id WHERE dh.id = ? AND dh.hospital_id = ?");
        $stmtGet->execute([$dhId, $hospitalId]);
        $targetAff = $stmtGet->fetch();

        if ($targetAff) {
            $db->prepare("
                UPDATE doctor_hospitals
                SET status = ?, rejection_reason = ?
                WHERE id = ? AND hospital_id = ?
            ")->execute([$newStatus, $reason ?: null, $dhId, $hospitalId]);

            AuditService::log('HOSPITAL_DOCTOR_STATUS_CHANGED', [
                'user_id'    => $_SESSION['user_id'],
                'hospital_id'=> $hospitalId,
                'doctor_id'  => $targetAff['doctor_id'],
                'metadata'   => ['new_status' => $newStatus, 'reason' => $reason]
            ]);

            if ($newStatus === 'REMOVED') {
                $success = 'Dr. ' . htmlspecialchars($targetAff['full_name']) . ' has been removed from this hospital. (Their affiliations with other hospitals and central verified status remain intact).';
            } elseif ($newStatus === 'SUSPENDED') {
                $success = 'Dr. ' . htmlspecialchars($targetAff['full_name']) . ' has been temporarily suspended from practicing at this hospital.';
            } else {
                $success = 'Dr. ' . htmlspecialchars($targetAff['full_name']) . ' has been set to ACTIVE at this hospital.';
            }
        }
    }
}

// Handle approve/reject of doctor-initiated PENDING requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handle_request'])) {
    validateCsrf();
    $dhId      = (int)($_POST['dh_id'] ?? 0);
    $action    = $_POST['request_action'] ?? '';
    $rejectMsg = trim($_POST['reject_reason'] ?? '');

    if ($dhId && in_array($action, ['approve', 'reject'])) {
        $stmtGet = $db->prepare("SELECT dh.*, d.full_name FROM doctor_hospitals dh JOIN doctors d ON dh.doctor_id = d.id WHERE dh.id = ? AND dh.hospital_id = ? AND dh.status = 'PENDING'");
        $stmtGet->execute([$dhId, $hospitalId]);
        $req = $stmtGet->fetch();

        if ($req) {
            if ($action === 'approve') {
                $db->prepare("UPDATE doctor_hospitals SET status = 'APPROVED', joined_at = CURDATE(), approved_by = ?, approved_at = NOW(), rejection_reason = NULL WHERE id = ?")->execute([$_SESSION['user_id'], $dhId]);
                AuditService::log(AuditService::AFFILIATION_APPROVED, ['user_id'=>$_SESSION['user_id'],'hospital_id'=>$hospitalId,'doctor_id'=>$req['doctor_id'],'metadata'=>['doctor_name'=>$req['full_name'],'source'=>'doctor_request']]);
                $success = 'Dr. ' . htmlspecialchars($req['full_name']) . ' has been approved and is now an active affiliated doctor.';
            } else {
                $db->prepare("UPDATE doctor_hospitals SET status = 'REJECTED', rejection_reason = ? WHERE id = ?")->execute([$rejectMsg ?: 'Request declined by hospital.', $dhId]);
                AuditService::log(AuditService::AFFILIATION_REJECTED, ['user_id'=>$_SESSION['user_id'],'hospital_id'=>$hospitalId,'doctor_id'=>$req['doctor_id'],'metadata'=>['doctor_name'=>$req['full_name'],'reason'=>$rejectMsg]]);
                $success = 'Request from Dr. ' . htmlspecialchars($req['full_name']) . ' has been declined.';
            }
        }
    }
}

// Fetch doctor-initiated PENDING join requests
$stmtPendingReqs = $db->prepare("
    SELECT dh.*, d.full_name, d.doctor_uid, d.specialization, d.qualification,
           d.medical_registration_id, d.verification_status AS doc_global_status,
           u.email, u.phone, dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.hospital_id = ? AND dh.status = 'PENDING'
    ORDER BY dh.created_at ASC
");
$stmtPendingReqs->execute([$hospitalId]);
$pendingRequests = $stmtPendingReqs->fetchAll();

// Fetch departments for dropdown & filters
$stmtDepts = $db->prepare("SELECT * FROM departments WHERE hospital_id = ? AND status = 'ACTIVE' ORDER BY name");
$stmtDepts->execute([$hospitalId]);
$departments = $stmtDepts->fetchAll();

// Filter parameters
$filterDept = (int)($_GET['department_id'] ?? 0);
$filterType = $_GET['employment_type'] ?? 'all';
$search     = trim($_GET['q'] ?? '');

$whereClauses = ["dh.hospital_id = ?", "dh.status != 'PENDING'"];
$params       = [$hospitalId];

if ($filterDept > 0) {
    $whereClauses[] = "dh.department_id = ?";
    $params[] = $filterDept;
}
if ($filterType !== 'all') {
    $whereClauses[] = "dh.employment_type = ?";
    $params[] = $filterType;
}
if ($search !== '') {
    $whereClauses[] = "(d.full_name LIKE ? OR d.medical_registration_id LIKE ? OR d.doctor_uid LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$whereSql = implode(' AND ', $whereClauses);

// Fetch affiliated doctors (excluding PENDING — shown separately above)
$stmtDocs = $db->prepare("
    SELECT dh.*, d.full_name, d.doctor_uid, d.specialization, d.qualification,
           d.medical_registration_id, d.verification_status AS doctor_global_status,
           u.email, u.phone,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE {$whereSql}
    ORDER BY FIELD(dh.status, 'APPROVED', 'SUSPENDED', 'REJECTED', 'REMOVED'), dh.joined_at DESC
");
$stmtDocs->execute($params);
$doctors = $stmtDocs->fetchAll();

// Stats for this hospital
$countActive   = 0;
$countFullTime = 0;
$countPartTime = 0;
$countVisiting = 0;

foreach ($doctors as $docItem) {
    if ($docItem['status'] === 'APPROVED') {
        $countActive++;
        if ($docItem['employment_type'] === 'FULL_TIME') $countFullTime++;
        elseif ($docItem['employment_type'] === 'PART_TIME') $countPartTime++;
        elseif ($docItem['employment_type'] === 'VISITING') $countVisiting++;
    }
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Affiliated Doctors — <?= htmlspecialchars($hospital['name']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .badge-verified { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .badge-pending { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .badge-suspended { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }
    .badge-removed { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon" style="background: var(--mc-teal);">H</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Hospital Admin</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Hospital Ops</div>
      <a href="<?= APP_URL ?>/hospital/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/doctors.php" class="nav-item active">
        <i class="bi bi-person-badge-fill"></i>
        <span>Affiliated Doctors</span>
        <?php if (!empty($pendingRequests)): ?>
        <span style="margin-left:auto;background:#EF4444;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:999px;min-width:18px;text-align:center;"><?= count($pendingRequests) ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/hospital/diagnostics.php" class="nav-item">
        <i class="bi bi-file-earmark-medical-fill"></i>
        <span>Diagnostics & Imaging</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/affiliations.php" class="nav-item">
        <i class="bi bi-clock-history"></i>
        <span>Affiliation History</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/departments.php" class="nav-item">
        <i class="bi bi-building"></i>
        <span>Departments</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/consultations.php" class="nav-item">
        <i class="bi bi-stethoscope"></i>
        <span>Consultations</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/settings.php" class="nav-item">
        <i class="bi bi-gear-fill"></i>
        <span>Hospital Settings</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar" style="background: var(--mc-teal);"><?= strtoupper(substr($hospital['name'], 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars(mb_strimwidth($hospital['name'], 0, 20, '...')) ?></div>
          <div class="user-role"><?= htmlspecialchars($hospital['hospital_uid']) ?></div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-secondary btn-sm w-100" style="margin-top:var(--space-2);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="portal-content">
    <header class="top-nav">
      <div class="top-nav-left">
        <button class="mobile-sidebar-toggle btn btn-sm btn-ghost d-md-none" onclick="toggleSidebar()">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <span class="eyebrow" style="color: var(--mc-teal-dark); font-size: 11px; font-weight: 700;">STAFF & DEPARTMENT ROSTER</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Affiliated Doctors at <?= htmlspecialchars($hospital['name']) ?></h1>
        </div>
      </div>
      <div class="top-nav-right">
        <button type="button" class="btn btn-primary btn-sm" style="background: var(--mc-teal); border-color: var(--mc-teal);" onclick="openAddDoctorModal()">
          <i class="bi bi-person-plus-fill"></i> Affiliate Approved Doctor
        </button>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <?php if ($success): ?>
      <div class="alert alert-success mb-4" data-auto-dismiss="6000">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="alert alert-error mb-4" data-auto-dismiss="8000">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <!-- ============================================================
           DOCTOR-INITIATED JOIN REQUESTS QUEUE
           ============================================================ -->
      <?php if (!empty($pendingRequests)): ?>
      <div style="background:#FFFBEB;border:2px solid #FDE68A;border-radius:14px;padding:20px;margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:38px;height:38px;background:#FEF3C7;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#92400E;flex-shrink:0;">
              <i class="bi bi-person-exclamation-fill"></i>
            </div>
            <div>
              <div style="font-weight:800;font-size:15px;color:#78350F;">Doctor Join Requests</div>
              <div style="font-size:12px;color:#92400E;"><?= count($pendingRequests) ?> doctor<?= count($pendingRequests)>1?'s':'' ?> requesting affiliation with <?= htmlspecialchars($hospital['name']) ?></div>
            </div>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach ($pendingRequests as $req): ?>
          <div style="background:#fff;border:1px solid #FDE68A;border-radius:10px;padding:16px;display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
            <!-- Doctor avatar & info -->
            <div style="width:44px;height:44px;background:#FEF3C7;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;color:#92400E;flex-shrink:0;">
              <?= strtoupper(substr($req['full_name'],0,2)) ?>
            </div>
            <div style="flex:1;min-width:180px;">
              <div style="font-weight:700;font-size:14px;color:#0F172A;">Dr. <?= htmlspecialchars($req['full_name']) ?></div>
              <div style="font-size:12px;color:var(--mc-text-muted);margin-top:2px;">
                <?= htmlspecialchars($req['specialization']??'General') ?>
                &nbsp;·&nbsp;
                <span style="font-family:monospace;"><?= htmlspecialchars($req['medical_registration_id']) ?></span>
                &nbsp;·&nbsp; <?= htmlspecialchars($req['email']) ?>
              </div>
              <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;font-size:12px;color:var(--mc-text-secondary);">
                <span><i class="bi bi-briefcase"></i> <?= htmlspecialchars($req['designation']??'Consultant') ?></span>
                <span><i class="bi bi-clock"></i> <?= htmlspecialchars(str_replace('_',' ',$req['employment_type'])) ?></span>
                <?php if ($req['department_name']): ?>
                <span><i class="bi bi-hospital"></i> <?= htmlspecialchars($req['department_name']) ?></span>
                <?php endif; ?>
                <span style="color:var(--mc-text-muted);"><i class="bi bi-calendar3"></i> Applied <?= date('M j, Y',strtotime($req['created_at'])) ?></span>
              </div>
              <?php if ($req['rejection_reason']): ?>
              <div style="margin-top:8px;font-size:12px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:6px 10px;color:#1E40AF;">
                <i class="bi bi-chat-quote"></i> <em><?= htmlspecialchars($req['rejection_reason']) ?></em>
              </div>
              <?php endif; ?>
            </div>
            <!-- Action buttons -->
            <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="dh_id" value="<?= $req['id'] ?>">
                <input type="hidden" name="request_action" value="approve">
                <button type="submit" name="handle_request" value="1" class="btn btn-sm" style="background:#DCFCE7;color:#166534;border:1px solid #BBF7D0;font-weight:700;white-space:nowrap;width:100%;">
                  <i class="bi bi-check-circle-fill"></i> Approve
                </button>
              </form>
              <button type="button"
                onclick="document.getElementById('reject-form-<?= $req['id'] ?>').style.display=document.getElementById('reject-form-<?= $req['id'] ?>').style.display==='none'?'block':'none'"
                class="btn btn-sm" style="background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;font-weight:700;white-space:nowrap;">
                <i class="bi bi-x-circle"></i> Decline
              </button>
            </div>
          </div>
          <!-- Reject form (hidden until Decline clicked) -->
          <div id="reject-form-<?= $req['id'] ?>" style="display:none;margin-top:-6px;">
            <form method="POST" style="background:#FFF5F5;border:1px solid #FECACA;border-radius:8px;padding:14px 16px;">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="dh_id" value="<?= $req['id'] ?>">
              <input type="hidden" name="request_action" value="reject">
              <label style="font-size:12px;font-weight:600;color:#991B1B;display:block;margin-bottom:6px;">Decline Reason (optional):</label>
              <div style="display:flex;gap:8px;">
                <input type="text" name="reject_reason" class="form-control" placeholder="e.g. Roster full, specialization not needed…" style="font-size:13px;">
                <button type="submit" name="handle_request" value="1" class="btn btn-sm" style="background:#EF4444;color:#fff;border:none;font-weight:700;white-space:nowrap;">Confirm Decline</button>
              </div>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ECOSYSTEM NOTICE BOX -->
      <div style="background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: var(--radius-lg); padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <i class="bi bi-shield-check" style="font-size: 24px; color: #166534;"></i>
          <div>
            <div style="font-weight: 700; font-size: 13px; color: #166534;">Centralized Doctor Verification Ecosystem</div>
            <div style="font-size: 12px; color: #15803D;">
              You can affiliate any doctor who has been verified by the MedCore Central Admin. You have full autonomy to add, assign departments, or remove doctors from your hospital at any time.
            </div>
          </div>
        </div>
        <span class="badge badge-verified" style="font-size: 11px;">
          <i class="bi bi-check-all"></i> Multi-Hospital Enabled
        </span>
      </div>

      <!-- STATS BAR -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 600; color: var(--mc-text-muted); text-transform: uppercase;">Total Active Staff</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: #0F766E; margin-top: 4px;"><?= $countActive ?></div>
        </div>
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 600; color: var(--mc-text-muted); text-transform: uppercase;">Full-Time Doctors</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: #1E40AF; margin-top: 4px;"><?= $countFullTime ?></div>
        </div>
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 600; color: var(--mc-text-muted); text-transform: uppercase;">Part-Time Doctors</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: #7C3AED; margin-top: 4px;"><?= $countPartTime ?></div>
        </div>
        <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border);">
          <div style="font-size: 11px; font-weight: 600; color: var(--mc-text-muted); text-transform: uppercase;">Visiting Consultants</div>
          <div style="font-size: 1.6rem; font-weight: 800; color: #D97706; margin-top: 4px;"><?= $countVisiting ?></div>
        </div>
      </div>

      <!-- FILTER & SEARCH BAR -->
      <div class="card" style="padding: 14px 18px; border: 1px solid var(--mc-border); margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
          <div style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
            <div class="search-bar" style="min-width: 260px; max-width: 360px;">
              <i class="bi bi-search"></i>
              <input type="text" name="q" placeholder="Search doctor by name, BMDC, email..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <select name="department_id" class="form-control" style="width: auto; font-size: 13px;">
              <option value="0">All Departments</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= $dept['id'] ?>" <?= $filterDept == $dept['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($dept['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <select name="employment_type" class="form-control" style="width: auto; font-size: 13px;">
              <option value="all">All Employment Types</option>
              <option value="FULL_TIME" <?= $filterType === 'FULL_TIME' ? 'selected' : '' ?>>Full-Time</option>
              <option value="PART_TIME" <?= $filterType === 'PART_TIME' ? 'selected' : '' ?>>Part-Time</option>
              <option value="VISITING" <?= $filterType === 'VISITING' ? 'selected' : '' ?>>Visiting</option>
              <option value="HONORARY" <?= $filterType === 'HONORARY' ? 'selected' : '' ?>>Honorary</option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 14px;">Filter</button>
            <?php if ($search || $filterDept || $filterType !== 'all'): ?>
              <a href="<?= APP_URL ?>/hospital/doctors.php" class="btn btn-ghost btn-sm" style="font-size: 12px;">Clear</a>
            <?php endif; ?>
          </div>

          <button type="button" class="btn btn-primary btn-sm" style="background: var(--mc-teal); border-color: var(--mc-teal);" onclick="openAddDoctorModal()">
            <i class="bi bi-plus-lg"></i> Add Doctor
          </button>
        </form>
      </div>

      <!-- DOCTOR ROSTER TABLE -->
      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Doctor</th>
                <th>BMDC Reg ID</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Type</th>
                <th>Hospital Status</th>
                <th>Joined Date</th>
                <th style="text-align: right;">Manage Doctor</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($doctors)): ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 40px 20px; color: var(--mc-text-muted);">
                  <div style="font-size: 32px; margin-bottom: 8px;"><i class="bi bi-person-x"></i></div>
                  <div style="font-weight: 700; font-size: 15px;">No doctors found</div>
                  <div style="font-size: 13px;">
                    <?= $search ? 'No affiliated doctors matched your filter.' : 'Click "Affiliate Approved Doctor" above to add verified doctors to your hospital.' ?>
                  </div>
                </td>
              </tr>
              <?php endif; ?>

              <?php foreach ($doctors as $d): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 50%; background: #EFF6FF; color: #1E40AF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0;">
                      <?= strtoupper(substr($d['full_name'], 4, 1) ?: substr($d['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                      <div style="font-weight: 700; color: var(--mc-text);">
                        <?= htmlspecialchars($d['full_name']) ?>
                      </div>
                      <div style="font-size: 11px; color: var(--mc-text-muted);">
                        <?= htmlspecialchars($d['doctor_uid']) ?> · <?= htmlspecialchars($d['specialization'] ?? '') ?>
                      </div>
                    </div>
                  </div>
                </td>

                <td>
                  <strong style="color: #1E40AF; font-family: var(--font-mono); font-size: 12px;">
                    <?= htmlspecialchars($d['medical_registration_id']) ?>
                  </strong>
                </td>

                <td>
                  <span class="badge" style="background: #F0FDFA; color: #0F766E;">
                    <?= htmlspecialchars($d['department_name'] ?? 'General') ?>
                  </span>
                </td>

                <td>
                  <?= htmlspecialchars($d['designation'] ?? 'Consultant') ?>
                </td>

                <td>
                  <span class="badge" style="background: #F1F5F9; color: #334155; font-size: 11px;">
                    <?= htmlspecialchars($d['employment_type']) ?>
                  </span>
                </td>

                <td>
                  <?php if ($d['status'] === 'APPROVED'): ?>
                    <span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> ACTIVE</span>
                  <?php elseif ($d['status'] === 'PENDING'): ?>
                    <span class="badge badge-pending"><i class="bi bi-hourglass-split"></i> PENDING</span>
                  <?php elseif ($d['status'] === 'SUSPENDED'): ?>
                    <span class="badge badge-suspended"><i class="bi bi-pause-circle-fill"></i> SUSPENDED</span>
                  <?php elseif ($d['status'] === 'REMOVED'): ?>
                    <span class="badge badge-removed"><i class="bi bi-dash-circle-fill"></i> REMOVED</span>
                  <?php else: ?>
                    <span class="badge badge-rejected"><?= htmlspecialchars($d['status']) ?></span>
                  <?php endif; ?>
                </td>

                <td style="font-size: 12px; color: var(--mc-text-muted);">
                  <?= $d['joined_at'] ? date('M d, Y', strtotime($d['joined_at'])) : '-' ?>
                </td>

                <td style="text-align: right;">
                  <div style="display: flex; gap: 6px; justify-content: flex-end;">
                    <a href="<?= APP_URL ?>/hospital/doctor-profile.php?id=<?= $d['doctor_id'] ?>" class="btn btn-secondary btn-sm" style="padding: 3px 8px; font-size: 11px; background: #F0FDF4; border-color: #BBF7D0; color: #166534;" title="View clinical stats & prescriptions">
                      <i class="bi bi-person-lines-fill"></i> Details
                    </a>
                    <?php if ($d['status'] === 'APPROVED'): ?>
                      <!-- Suspend button -->
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend Dr. <?= addslashes($d['full_name']) ?> from practicing at this hospital?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="dh_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="new_status" value="SUSPENDED">
                        <input type="hidden" name="update_status" value="1">
                        <button type="submit" class="btn btn-secondary btn-sm" style="padding: 3px 8px; font-size: 11px;" title="Suspend at this hospital">
                          <i class="bi bi-pause-circle"></i> Suspend
                        </button>
                      </form>

                      <!-- Remove button -->
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Remove Dr. <?= addslashes($d['full_name']) ?> from <?= addslashes($hospital['name']) ?> staff roster? (Doctor will remain verified and active at other hospitals)');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="dh_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="new_status" value="REMOVED">
                        <input type="hidden" name="update_status" value="1">
                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 3px 8px; font-size: 11px;" title="Remove from hospital roster">
                          <i class="bi bi-trash3"></i> Remove
                        </button>
                      </form>

                    <?php elseif ($d['status'] === 'SUSPENDED' || $d['status'] === 'REMOVED'): ?>
                      <!-- Reactivate button -->
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Re-activate Dr. <?= addslashes($d['full_name']) ?> at this hospital?');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="dh_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="new_status" value="APPROVED">
                        <input type="hidden" name="update_status" value="1">
                        <button type="submit" class="btn btn-primary btn-sm" style="background: var(--mc-teal); border-color: var(--mc-teal); padding: 3px 8px; font-size: 11px;">
                          <i class="bi bi-arrow-repeat"></i> Re-activate
                        </button>
                      </form>
                    <?php endif; ?>
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

<!-- ADD DOCTOR MODAL -->
<div id="add-doctor-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
  <div style="background: white; border-radius: var(--radius-xl); max-width: 520px; width: 100%; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="font-size: 1.2rem; font-weight: 800; color: #0F172A; margin: 0;">
        <i class="bi bi-person-plus-fill" style="color: var(--mc-teal);"></i> Affiliate Doctor to Hospital
      </h3>
      <button type="button" onclick="closeAddDoctorModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--mc-text-muted);">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <p style="font-size: 13px; color: var(--mc-text-secondary); margin: 0 0 16px 0;">
      Add any <strong>Admin-Verified Doctor</strong> to <?= htmlspecialchars($hospital['name']) ?>.
    </p>

    <form method="POST" id="add-doctor-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="add_doctor" value="1">

      <div class="form-group mb-3">
        <label class="form-label" for="identifier">Doctor Identifier <span class="text-danger">*</span></label>
        <input type="text" id="identifier" name="identifier" class="form-control" required
          placeholder="BMDC Reg (e.g. BMDC-A-12345), UID (DR-000001), or Email">
        <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 4px;">
          * Doctor must be approved by the website Main Admin.
        </div>
      </div>

      <div class="form-group mb-3">
        <label class="form-label" for="department_id">Hospital Department</label>
        <select name="department_id" id="department_id" class="form-control">
          <option value="0">General / None Assigned</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div class="form-group">
          <label class="form-label" for="designation">Hospital Designation</label>
          <input type="text" id="designation" name="designation" class="form-control"
            placeholder="e.g. Senior Consultant" value="Consultant">
        </div>

        <div class="form-group">
          <label class="form-label" for="employment_type">Employment Type</label>
          <select name="employment_type" id="employment_type" class="form-control">
            <option value="FULL_TIME">Full-Time</option>
            <option value="PART_TIME">Part-Time</option>
            <option value="VISITING">Visiting</option>
            <option value="HONORARY">Honorary</option>
          </select>
        </div>
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeAddDoctorModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm" style="background: var(--mc-teal); border-color: var(--mc-teal); font-weight: 700;">
          <i class="bi bi-check-lg"></i> Confirm Affiliation
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

function openAddDoctorModal() {
  document.getElementById('add-doctor-modal').style.display = 'flex';
}

function closeAddDoctorModal() {
  document.getElementById('add-doctor-modal').style.display = 'none';
}
</script>
</body>
</html>
