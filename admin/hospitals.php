<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$flashSuccess = '';
$flashError   = '';

// Handle hospital status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action   = $_POST['action'] ?? '';
    $hospId   = (int)($_POST['hospital_id'] ?? 0);

    if ($hospId > 0) {
        // Fetch hospital + admin user to give good feedback
        $stmtH = $db->prepare("SELECT h.*, u.id AS uid FROM hospitals h LEFT JOIN users u ON h.user_id = u.id WHERE h.id = ?");
        $stmtH->execute([$hospId]);
        $targetHosp = $stmtH->fetch();

        if (!$targetHosp) {
            $flashError = "Hospital record not found.";
        } else {
            if ($action === 'approve') {
                // Approve hospital: set VERIFIED + activate admin user
                $db->prepare("
                    UPDATE hospitals
                    SET verification_status = 'VERIFIED',
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $hospId]);

                if ($targetHosp['uid']) {
                    $db->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = ?")->execute([$targetHosp['uid']]);
                }

                AuditService::log(AuditService::HOSPITAL_VERIFIED, [
                    'user_id'     => $_SESSION['user_id'],
                    'hospital_id' => $hospId,
                    'metadata'    => ['action' => 'admin_approved', 'hospital_name' => $targetHosp['name']]
                ]);

                $flashSuccess = "✅ " . htmlspecialchars($targetHosp['name']) . " has been APPROVED & ACCREDITED. The hospital admin can now log in.";

            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? 'Registration could not be verified with health authority records.');
                $db->prepare("
                    UPDATE hospitals
                    SET verification_status = 'REJECTED',
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $hospId]);

                if ($targetHosp['uid']) {
                    $db->prepare("UPDATE users SET status = 'SUSPENDED' WHERE id = ?")->execute([$targetHosp['uid']]);
                }

                AuditService::log('HOSPITAL_REJECTED', [
                    'user_id'     => $_SESSION['user_id'],
                    'hospital_id' => $hospId,
                    'metadata'    => ['reason' => $reason, 'hospital_name' => $targetHosp['name']]
                ]);

                $flashSuccess = "Hospital application for \"" . htmlspecialchars($targetHosp['name']) . "\" has been REJECTED.";

            } elseif ($action === 'verify') {
                $db->prepare("
                    UPDATE hospitals
                    SET verification_status = 'VERIFIED',
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $hospId]);

                if ($targetHosp['uid']) {
                    $db->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = ?")->execute([$targetHosp['uid']]);
                }

                AuditService::log(AuditService::HOSPITAL_VERIFIED, [
                    'user_id'     => $_SESSION['user_id'],
                    'hospital_id' => $hospId,
                ]);

                $flashSuccess = "Hospital has been VERIFIED & Accredited.";

            } elseif ($action === 'suspend') {
                $db->prepare("
                    UPDATE hospitals
                    SET verification_status = 'SUSPENDED'
                    WHERE id = ?
                ")->execute([$hospId]);

                $flashSuccess = "Hospital license SUSPENDED.";
            }
        }
    }
}

// Fetch PENDING hospital applications
$pendingHospitals = $db->query("
    SELECT h.*, u.email AS admin_email, u.phone AS admin_phone, u.created_at AS applied_at
    FROM hospitals h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.verification_status = 'PENDING'
    ORDER BY h.created_at DESC
")->fetchAll();

// Fetch all non-pending hospitals with aggregate stats
$stmt = $db->query("
    SELECT h.*, u.email AS admin_email,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id = h.id AND dh.status = 'APPROVED') AS active_doctors_count,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id = h.id AND dh.status = 'PENDING') AS pending_doctors_count,
           (SELECT COUNT(*) FROM departments dep WHERE dep.hospital_id = h.id) AS dept_count,
           (SELECT COUNT(*) FROM consultations c WHERE c.hospital_id = h.id) AS total_consultations
    FROM hospitals h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.verification_status != 'PENDING'
    ORDER BY FIELD(h.verification_status, 'VERIFIED', 'SUSPENDED', 'REJECTED'), h.name ASC
");
$hospitals = $stmt->fetchAll();

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hospital Network Oversight — MedCore Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-dark: #0F172A;
      --admin-primary: #1E40AF;
    }
    .badge-verified  { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .badge-pending   { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .badge-suspended { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }
    .badge-rejected  { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }

    .approval-card {
      background: #FFFBEB;
      border: 2px solid #FDE68A;
      border-radius: 12px;
      margin-bottom: 16px;
      overflow: hidden;
    }

    .approval-card-header {
      padding: 16px 20px;
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .approval-card-body {
      padding: 0 20px 20px;
    }

    .approval-meta-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 12px;
      margin-bottom: 16px;
    }

    .approval-meta-item {
      background: white;
      border: 1px solid #FDE68A;
      border-radius: 8px;
      padding: 10px 14px;
    }

    .approval-meta-item .label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #92400E;
      margin-bottom: 3px;
    }

    .approval-meta-item .value {
      font-size: 13px;
      font-weight: 600;
      color: #1E293B;
      word-break: break-all;
    }

    .approval-actions {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }

    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: white;
      border-radius: 16px;
      padding: 28px;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 20px 60px -10px rgba(0,0,0,0.4);
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
      <a href="<?= APP_URL ?>/admin/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i> Doctor Verification
      </a>
      <a href="<?= APP_URL ?>/admin/hospitals.php" class="nav-item active">
        <i class="bi bi-building-fill-check"></i> Hospital Network
        <?php if (count($pendingHospitals) > 0): ?>
        <span style="background: #EF4444; color: white; border-radius: 10px; padding: 1px 7px; font-size: 11px; font-weight: 800; margin-left: auto;">
          <?= count($pendingHospitals) ?>
        </span>
        <?php endif; ?>
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
        <div class="user-avatar" style="background: #3B82F6; color:white; width:36px; height:36px; font-size:13px;">SA</div>
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
        <button class="mobile-sidebar-toggle btn btn-sm btn-ghost d-md-none" onclick="toggleSidebar()">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">HOSPITALIZATION NETWORK</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Hospital Network Oversight</h1>
        </div>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <?php if ($flashSuccess): ?>
      <div class="alert alert-success mb-4" data-auto-dismiss="5000">
        <i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?>
      </div>
      <?php endif; ?>
      <?php if ($flashError): ?>
      <div class="alert alert-error mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?>
      </div>
      <?php endif; ?>

      <!-- ============================================================ -->
      <!-- PENDING APPLICATIONS QUEUE -->
      <!-- ============================================================ -->
      <?php if (count($pendingHospitals) > 0): ?>
      <div class="card" style="border: 2px solid #FDE68A; border-radius: 12px; overflow: hidden; margin-bottom: 28px;">
        <div style="padding: 18px 22px; background: #FFFBEB; border-bottom: 1px solid #FDE68A; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 36px; height: 36px; background: #F59E0B; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
              <h2 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: #92400E;">
                Pending Hospital Applications
              </h2>
              <div style="font-size: 12px; color: #B45309;">
                <?= count($pendingHospitals) ?> hospital<?= count($pendingHospitals) > 1 ? 's' : '' ?> awaiting your review &amp; accreditation approval
              </div>
            </div>
          </div>
          <span style="background: #EF4444; color: white; border-radius: 20px; padding: 4px 14px; font-size: 13px; font-weight: 800;">
            <?= count($pendingHospitals) ?> Pending
          </span>
        </div>

        <div style="padding: 20px 22px; background: white;">
          <?php foreach ($pendingHospitals as $ph): ?>
          <div class="approval-card">
            <div class="approval-card-header">
              <div style="display: flex; align-items: flex-start; gap: 12px;">
                <div style="width: 44px; height: 44px; background: #FEF3C7; border: 2px solid #FDE68A; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #D97706; flex-shrink: 0;">
                  <i class="bi bi-building"></i>
                </div>
                <div>
                  <div style="font-weight: 800; font-size: 15px; color: #1E293B;">
                    <?= htmlspecialchars($ph['name']) ?>
                  </div>
                  <div style="font-size: 12px; color: #92400E; margin-top: 2px;">
                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ph['city'] ?? '') ?>
                    <?= $ph['district'] ? ' · ' . htmlspecialchars($ph['district']) : '' ?>
                    &nbsp;·&nbsp;
                    <strong><?= htmlspecialchars(str_replace('_', ' ', $ph['type'])) ?></strong>
                  </div>
                  <div style="font-size: 11px; color: #B45309; margin-top: 2px;">
                    Applied: <?= date('M j, Y g:i A', strtotime($ph['created_at'])) ?>
                    &nbsp;·&nbsp;
                    UID: <code style="font-size: 11px;"><?= htmlspecialchars($ph['hospital_uid']) ?></code>
                  </div>
                </div>
              </div>
              <span class="badge badge-pending">AWAITING REVIEW</span>
            </div>

            <div class="approval-card-body">
              <div class="approval-meta-grid">
                <div class="approval-meta-item">
                  <div class="label"><i class="bi bi-file-earmark-text"></i> Reg. Number</div>
                  <div class="value"><?= htmlspecialchars($ph['registration_number']) ?></div>
                </div>
                <div class="approval-meta-item">
                  <div class="label"><i class="bi bi-envelope-at"></i> Admin Email</div>
                  <div class="value"><?= htmlspecialchars($ph['admin_email'] ?? '-') ?></div>
                </div>
                <div class="approval-meta-item">
                  <div class="label"><i class="bi bi-telephone"></i> Hospital Phone</div>
                  <div class="value"><?= htmlspecialchars($ph['phone'] ?? '-') ?></div>
                </div>
                <?php if ($ph['website']): ?>
                <div class="approval-meta-item">
                  <div class="label"><i class="bi bi-globe"></i> Website</div>
                  <div class="value">
                    <a href="<?= htmlspecialchars($ph['website']) ?>" target="_blank" rel="noopener noreferrer" style="color: #1E40AF;">
                      <?= htmlspecialchars($ph['website']) ?>
                    </a>
                  </div>
                </div>
                <?php endif; ?>
                <?php if ($ph['address']): ?>
                <div class="approval-meta-item">
                  <div class="label"><i class="bi bi-geo"></i> Address</div>
                  <div class="value"><?= htmlspecialchars($ph['address']) ?></div>
                </div>
                <?php endif; ?>
              </div>

              <div class="approval-actions">
                <!-- APPROVE -->
                <form method="POST" style="display:inline;" onsubmit="return confirm('APPROVE and ACCREDIT \"<?= htmlspecialchars(addslashes($ph['name'])) ?>\"?\n\nThis will activate their admin account and allow them to manage doctors.');">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <input type="hidden" name="hospital_id" value="<?= $ph['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" class="btn btn-sm" style="background: linear-gradient(135deg, #10B981, #059669); color: white; border: none; padding: 7px 18px; font-weight: 700; border-radius: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="bi bi-patch-check-fill"></i> Approve & Accredit
                  </button>
                </form>

                <!-- REJECT -->
                <button type="button" class="btn btn-sm" onclick="openRejectModal(<?= $ph['id'] ?>, '<?= htmlspecialchars(addslashes($ph['name'])) ?>')"
                  style="background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; padding: 7px 16px; font-weight: 700; border-radius: 8px; display: flex; align-items: center; gap: 6px;">
                  <i class="bi bi-x-circle-fill"></i> Reject
                </button>

                <a href="<?= APP_URL ?>/admin/hospital-detail.php?id=<?= $ph['id'] ?>" class="btn btn-secondary btn-sm">
                  <i class="bi bi-eye"></i> Full Details
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px; padding: 14px 18px; display: flex; gap: 10px; align-items: center; margin-bottom: 24px; font-size: 14px; color: #166534;">
        <i class="bi bi-check-circle-fill" style="font-size: 18px;"></i>
        <span><strong>No pending applications.</strong> All hospital applications have been reviewed.</span>
      </div>
      <?php endif; ?>


      <!-- ============================================================ -->
      <!-- ACTIVE / ALL HOSPITALS TABLE -->
      <!-- ============================================================ -->
      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
        <div style="padding: 16px 20px; background: var(--mc-white); border-bottom: 1px solid var(--mc-border); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Ecosystem Hospital Nodes</h2>
            <div style="font-size: 12px; color: var(--mc-text-muted);">
              Verified doctors can practice across any of these authorized hospital centers
            </div>
          </div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <span class="badge" style="background: #DCFCE7; color: #166534; font-weight: 700;">
              <?= count(array_filter($hospitals, fn($h) => $h['verification_status'] === 'VERIFIED')) ?> Accredited
            </span>
            <span class="badge" style="background: #EFF6FF; color: #1E40AF; font-weight: 700;">
              <?= count($hospitals) ?> Total
            </span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Hospital Center</th>
                <th>Registration No.</th>
                <th>Type & Location</th>
                <th>Affiliated Doctors</th>
                <th>Departments</th>
                <th>Consultations</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($hospitals)): ?>
              <tr>
                <td colspan="8" style="text-align: center; padding: 32px; color: var(--mc-text-muted);">
                  No approved or suspended hospitals yet.
                </td>
              </tr>
              <?php endif; ?>
              <?php foreach ($hospitals as $hosp): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: #F0FDFA; color: #0D9488; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                      <i class="bi bi-building"></i>
                    </div>
                    <div>
                      <a href="<?= APP_URL ?>/admin/hospital-detail.php?id=<?= $hosp['id'] ?>" style="font-weight: 700; color: var(--mc-text); text-decoration: none;">
                        <?= htmlspecialchars($hosp['name']) ?>
                      </a>
                      <div style="font-size: 11px; color: var(--mc-text-muted); font-family: var(--font-mono);">
                        <?= htmlspecialchars($hosp['hospital_uid']) ?> · <?= htmlspecialchars($hosp['admin_email'] ?? '') ?>
                      </div>
                    </div>
                  </div>
                </td>

                <td>
                  <span style="font-family: var(--font-mono); font-size: 12px; color: #334155;">
                    <?= htmlspecialchars($hosp['registration_number']) ?>
                  </span>
                </td>

                <td>
                  <div><?= ucwords(strtolower(str_replace('_', ' ', $hosp['type']))) ?></div>
                  <div style="font-size: 11px; color: var(--mc-text-muted);">
                    <?= htmlspecialchars($hosp['city'] ?? '') ?><?= $hosp['district'] ? ', ' . htmlspecialchars($hosp['district']) : '' ?>
                  </div>
                </td>

                <td>
                  <strong style="color: #1E40AF;"><?= $hosp['active_doctors_count'] ?> Active</strong>
                  <?php if ($hosp['pending_doctors_count'] > 0): ?>
                    <span style="font-size: 11px; color: #D97706;">(+<?= $hosp['pending_doctors_count'] ?> req)</span>
                  <?php endif; ?>
                </td>

                <td><?= $hosp['dept_count'] ?> depts</td>

                <td><?= $hosp['total_consultations'] ?></td>

                <td>
                  <?php if ($hosp['verification_status'] === 'VERIFIED'): ?>
                    <span class="badge badge-verified"><i class="bi bi-patch-check-fill"></i> ACCREDITED</span>
                  <?php elseif ($hosp['verification_status'] === 'REJECTED'): ?>
                    <span class="badge badge-rejected"><i class="bi bi-x-circle-fill"></i> REJECTED</span>
                  <?php else: ?>
                    <span class="badge badge-suspended">SUSPENDED</span>
                  <?php endif; ?>
                </td>

                <td style="text-align: right;">
                  <div style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap;">
                    <a href="<?= APP_URL ?>/admin/hospital-detail.php?id=<?= $hosp['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 12px;">
                      Roster →
                    </a>
                    <?php if ($hosp['verification_status'] === 'VERIFIED'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('SUSPEND this hospital? Their admin cannot log in while suspended.');">
                      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                      <input type="hidden" name="hospital_id" value="<?= $hosp['id'] ?>">
                      <input type="hidden" name="action" value="suspend">
                      <button type="submit" class="btn btn-sm" style="padding: 4px 10px; font-size: 12px; background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA;">
                        Suspend
                      </button>
                    </form>
                    <?php elseif (in_array($hosp['verification_status'], ['SUSPENDED','REJECTED'])): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Re-APPROVE and ACCREDIT this hospital?');">
                      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                      <input type="hidden" name="hospital_id" value="<?= $hosp['id'] ?>">
                      <input type="hidden" name="action" value="verify">
                      <button type="submit" class="btn btn-sm" style="padding: 4px 10px; font-size: 12px; background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0;">
                        Re-Approve
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

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box">
    <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 8px;">Reject Hospital Application</h3>
    <p id="rejectModalDesc" style="font-size: 13px; color: #64748B; margin: 0 0 20px;"></p>

    <form method="POST" id="rejectForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="hospital_id" id="rejectHospId" value="">
      <input type="hidden" name="action" value="reject">

      <div class="form-group">
        <label class="form-label" for="rejection_reason">Rejection Reason <span style="color: #EF4444;">*</span></label>
        <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="3"
          placeholder="e.g., Registration number could not be verified with DGHS records."
          style="resize: vertical;" required></textarea>
        <div class="form-hint">This reason will be logged in the audit trail.</div>
      </div>

      <div style="display: flex; gap: 10px; margin-top: 16px;">
        <button type="submit" class="btn btn-sm" style="background: #EF4444; color: white; border: none; padding: 8px 20px; font-weight: 700; border-radius: 8px;">
          <i class="bi bi-x-circle-fill"></i> Confirm Rejection
        </button>
        <button type="button" onclick="closeRejectModal()" class="btn btn-secondary btn-sm">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

function openRejectModal(hospId, hospName) {
  document.getElementById('rejectHospId').value = hospId;
  document.getElementById('rejectModalDesc').textContent =
    'Provide a reason for rejecting the application from "' + hospName + '".';
  document.getElementById('rejectModal').classList.add('open');
}

function closeRejectModal() {
  document.getElementById('rejectModal').classList.remove('open');
}

// Close modal on overlay click
document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});

// Auto-dismiss flash
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('[data-auto-dismiss]');
  alerts.forEach(function(el) {
    const ms = parseInt(el.dataset.autoDismiss) || 5000;
    setTimeout(() => el.remove(), ms);
  });
});
</script>
</body>
</html>
