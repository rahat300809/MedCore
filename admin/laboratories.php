<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$flashSuccess = '';
$flashError   = '';

// Handle laboratory status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $labId  = (int)($_POST['lab_id'] ?? 0);

    if ($labId > 0) {
        $stmtL = $db->prepare("SELECT l.*, u.id AS uid, u.email FROM laboratories l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = ?");
        $stmtL->execute([$labId]);
        $targetLab = $stmtL->fetch();

        if (!$targetLab) {
            $flashError = "Laboratory record not found.";
        } else {
            if ($action === 'approve') {
                $db->prepare("
                    UPDATE laboratories
                    SET verification_status = 'VERIFIED',
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $labId]);

                if ($targetLab['uid']) {
                    $db->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = ?")->execute([$targetLab['uid']]);
                }

                AuditService::log(AuditService::LAB_VERIFIED, [
                    'user_id'  => $_SESSION['user_id'],
                    'metadata' => ['lab_id' => $labId, 'lab_name' => $targetLab['name'], 'action' => 'verified']
                ]);

                $flashSuccess = "✅ \"" . htmlspecialchars($targetLab['name']) . "\" (" . htmlspecialchars($targetLab['lab_uid']) . ") has been APPROVED and VERIFIED. Hospitals can now affiliate this laboratory.";
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? 'Registration credentials or laboratory license could not be verified.');
                $db->prepare("
                    UPDATE laboratories
                    SET verification_status = 'REJECTED',
                        verified_by = ?,
                        verified_at = NOW(),
                        rejection_reason = ?
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $reason, $labId]);

                if ($targetLab['uid']) {
                    $db->prepare("UPDATE users SET status = 'SUSPENDED' WHERE id = ?")->execute([$targetLab['uid']]);
                }

                AuditService::log('LAB_REJECTED', [
                    'user_id'  => $_SESSION['user_id'],
                    'metadata' => ['lab_id' => $labId, 'lab_name' => $targetLab['name'], 'reason' => $reason]
                ]);

                $flashSuccess = "Laboratory \"" . htmlspecialchars($targetLab['name']) . "\" application has been REJECTED.";
            } elseif ($action === 'suspend') {
                $db->prepare("
                    UPDATE laboratories
                    SET verification_status = 'SUSPENDED'
                    WHERE id = ?
                ")->execute([$labId]);

                if ($targetLab['uid']) {
                    $db->prepare("UPDATE users SET status = 'SUSPENDED' WHERE id = ?")->execute([$targetLab['uid']]);
                }

                AuditService::log('LAB_SUSPENDED', [
                    'user_id'  => $_SESSION['user_id'],
                    'metadata' => ['lab_id' => $labId, 'lab_name' => $targetLab['name']]
                ]);

                $flashSuccess = "Laboratory \"" . htmlspecialchars($targetLab['name']) . "\" has been SUSPENDED.";
            } elseif ($action === 'reactivate') {
                $db->prepare("
                    UPDATE laboratories
                    SET verification_status = 'VERIFIED'
                    WHERE id = ?
                ")->execute([$labId]);

                if ($targetLab['uid']) {
                    $db->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = ?")->execute([$targetLab['uid']]);
                }

                AuditService::log('LAB_REACTIVATED', [
                    'user_id'  => $_SESSION['user_id'],
                    'metadata' => ['lab_id' => $labId, 'lab_name' => $targetLab['name']]
                ]);

                $flashSuccess = "Laboratory \"" . htmlspecialchars($targetLab['name']) . "\" has been REACTIVATED.";
            }
        }
    }
}

// Statistics
$totalLabs     = (int)$db->query("SELECT COUNT(*) FROM laboratories")->fetchColumn();
$pendingLabs   = (int)$db->query("SELECT COUNT(*) FROM laboratories WHERE verification_status = 'PENDING'")->fetchColumn();
$verifiedLabs  = (int)$db->query("SELECT COUNT(*) FROM laboratories WHERE verification_status = 'VERIFIED'")->fetchColumn();
$suspendedLabs = (int)$db->query("SELECT COUNT(*) FROM laboratories WHERE verification_status = 'SUSPENDED'")->fetchColumn();
$totalResults  = (int)$db->query("SELECT COUNT(*) FROM lab_test_results")->fetchColumn();

// Filtering and Search
$search = trim($_GET['q'] ?? '');
$statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(l.name LIKE ? OR l.lab_uid LIKE ? OR l.registration_number LIKE ? OR u.email LIKE ? OR l.city LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($statusFilter !== 'ALL' && in_array($statusFilter, ['PENDING', 'VERIFIED', 'SUSPENDED', 'REJECTED'])) {
    $whereClauses[] = "l.verification_status = ?";
    $params[] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $db->prepare("
    SELECT l.*, u.email, u.last_login_at,
           (SELECT COUNT(*) FROM hospital_laboratories hl WHERE hl.laboratory_id = l.id AND hl.status = 'APPROVED') AS hospital_count,
           (SELECT COUNT(*) FROM lab_test_results ltr WHERE ltr.laboratory_id = l.id) AS test_results_count
    FROM laboratories l
    JOIN users u ON l.user_id = u.id
    {$whereSql}
    ORDER BY
        CASE l.verification_status
            WHEN 'PENDING' THEN 1
            WHEN 'VERIFIED' THEN 2
            WHEN 'SUSPENDED' THEN 3
            ELSE 4
        END,
        l.created_at DESC
");
$stmt->execute($params);
$laboratories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Diagnostic Laboratories Management — Central Admin — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-dark: #0F172A;
      --admin-darker: #020617;
    }
    body { background: #F8FAFC; }
    .portal-layout { display: flex; min-height: 100vh; }
    .stat-card-admin {
      background: #fff;
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-lg);
      padding: var(--space-4) var(--space-5);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.15s, box-shadow 0.15s;
    }
    .stat-card-admin:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }
    .stat-num {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--mc-text);
      line-height: 1.1;
      margin: 8px 0 4px;
    }
    .stat-lbl {
      font-size: 11px;
      font-weight: 700;
      color: var(--mc-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-pending { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .badge-verified { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .badge-suspended { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-rejected { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }
    .service-tag {
      display: inline-block;
      background: #EFF6FF;
      color: #1E40AF;
      border: 1px solid #DBEAFE;
      border-radius: 4px;
      padding: 2px 6px;
      font-size: 10px;
      font-weight: 600;
      margin: 2px 2px 2px 0;
    }
    /* Rejection modal */
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.6);
      backdrop-filter: blur(4px);
      z-index: 999;
      display: none;
      align-items: center;
      justify-content: center;
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
      <a href="<?= APP_URL ?>/admin/hospitals.php" class="nav-item">
        <i class="bi bi-building-fill-check"></i> Hospital Network
      </a>
      <a href="<?= APP_URL ?>/admin/laboratories.php" class="nav-item active">
        <i class="bi bi-flask-fill"></i> Diagnostic Laboratories
        <?php if ($pendingLabs > 0): ?>
          <span class="badge badge-pending" style="margin-left:auto; font-size:11px; padding:2px 6px;">
            <?= $pendingLabs ?>
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
        <i class="bi bi-shield-check"></i> Audit &amp; Compliance
      </a>
    </nav>

    <div class="sidebar-footer" style="border-color: rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
        <div class="user-avatar" style="background: #3B82F6; color:white; width:36px; height:36px; font-size:13px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700;">
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
  <main class="portal-main" style="flex: 1; padding: 24px 32px;">
    <!-- HEADER -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
      <div>
        <div style="font-size: 11px; font-weight: 700; color: #4F46E5; text-transform: uppercase; letter-spacing: 1px;">Clinical Diagnostics Oversight</div>
        <h1 style="font-size: 1.6rem; font-weight: 800; color: #0F172A; margin: 2px 0 4px 0;">Diagnostic Laboratories</h1>
        <p style="font-size: 13px; color: #64748B; margin: 0;">Verify licensed diagnostic facilities, manage hospital affiliations, and oversee pathology report issuance.</p>
      </div>
    </div>

    <!-- FLASH MESSAGES -->
    <?php if ($flashSuccess): ?>
      <div class="alert alert-success mb-4" style="border-radius: 10px;"><?= $flashSuccess ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="alert alert-danger mb-4" style="border-radius: 10px;">⚠️ <?= $flashError ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
      <div class="stat-card-admin">
        <div class="stat-lbl">Total Registered</div>
        <div class="stat-num" style="color: #1E293B;"><?= $totalLabs ?></div>
        <div style="font-size: 11px; color: #64748B;">Pathology &amp; Labs</div>
      </div>
      <div class="stat-card-admin" style="border-color: #FDE68A; background: #FFFBEB;">
        <div class="stat-lbl" style="color: #B45309;">Pending Approval</div>
        <div class="stat-num" style="color: #D97706;"><?= $pendingLabs ?></div>
        <div style="font-size: 11px; color: #B45309;">Requires verification</div>
      </div>
      <div class="stat-card-admin" style="border-color: #BBF7D0; background: #F0FDF4;">
        <div class="stat-lbl" style="color: #15803D;">Verified Facilities</div>
        <div class="stat-num" style="color: #16A34A;"><?= $verifiedLabs ?></div>
        <div style="font-size: 11px; color: #15803D;">Active on network</div>
      </div>
      <div class="stat-card-admin">
        <div class="stat-lbl">Suspended</div>
        <div class="stat-num" style="color: #DC2626;"><?= $suspendedLabs ?></div>
        <div style="font-size: 11px; color: #64748B;">Deactivated accounts</div>
      </div>
      <div class="stat-card-admin" style="border-color: #C7D2FE; background: #EEF2FF;">
        <div class="stat-lbl" style="color: #4338CA;">Tests Completed</div>
        <div class="stat-num" style="color: #4F46E5;"><?= $totalResults ?></div>
        <div style="font-size: 11px; color: #4338CA;">Token-fulfilled reports</div>
      </div>
    </div>

    <!-- FILTER AND SEARCH BAR -->
    <div class="card mb-4" style="border-radius: 12px; padding: 16px 20px;">
      <form method="GET" style="display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
          <a href="?status=ALL<?= $search ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'ALL' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px;">
            All (<?= $totalLabs ?>)
          </a>
          <a href="?status=PENDING<?= $search ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'PENDING' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px;">
            Pending (<?= $pendingLabs ?>)
          </a>
          <a href="?status=VERIFIED<?= $search ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'VERIFIED' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px;">
            Verified (<?= $verifiedLabs ?>)
          </a>
          <a href="?status=SUSPENDED<?= $search ? '&q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter === 'SUSPENDED' ? 'btn-primary' : 'btn-outline' ?>" style="font-size: 12px;">
            Suspended (<?= $suspendedLabs ?>)
          </a>
        </div>

        <div style="display: flex; gap: 8px; flex: 1; max-width: 380px; min-width: 240px;">
          <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by name, UID, reg #, email..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-sm btn-secondary"><i class="bi bi-search"></i></button>
          <?php if ($search): ?>
            <a href="?status=<?= htmlspecialchars($statusFilter) ?>" class="btn btn-sm btn-outline"><i class="bi bi-x"></i></a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- LABORATORIES TABLE -->
    <div class="card" style="border-radius: 12px; overflow: hidden;">
      <div class="table-responsive">
        <table class="table" style="margin: 0; font-size: 13px; vertical-align: middle;">
          <thead style="background: #F8FAFC; border-bottom: 2px solid #E2E8F0;">
            <tr>
              <th style="padding: 12px 16px;">Facility &amp; ID</th>
              <th style="padding: 12px 16px;">License &amp; Contact</th>
              <th style="padding: 12px 16px;">Offered Services</th>
              <th style="padding: 12px 16px; text-align: center;">Hospitals</th>
              <th style="padding: 12px 16px; text-align: center;">Reports Issued</th>
              <th style="padding: 12px 16px;">Status</th>
              <th style="padding: 12px 16px; text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($laboratories)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 48px; color: #94A3B8;">
                <i class="bi bi-flask" style="font-size: 2.5rem; display: block; margin-bottom: 8px; opacity: 0.6;"></i>
                <div style="font-size: 15px; font-weight: 700; color: #334155;">No laboratories found</div>
                <div style="font-size: 12px; color: #64748B;">No laboratory records match the specified search filters.</div>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($laboratories as $lab): ?>
            <tr style="border-bottom: 1px solid #F1F5F9;">
              <td style="padding: 14px 16px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div style="width: 38px; height: 38px; border-radius: 8px; background: linear-gradient(135deg, #6366F1, #4F46E5); color: white; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;">
                    🔬
                  </div>
                  <div>
                    <div style="font-weight: 800; color: #0F172A; font-size: 14px;"><?= htmlspecialchars($lab['name']) ?></div>
                    <div style="font-size: 11px; font-family: 'JetBrains Mono', monospace; color: #4338CA;">
                      <?= htmlspecialchars($lab['lab_uid']) ?>
                    </div>
                    <?php if ($lab['city']): ?>
                      <div style="font-size: 11px; color: #64748B;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($lab['city']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <td style="padding: 14px 16px;">
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 700; color: #334155;">
                  Reg: <?= htmlspecialchars($lab['registration_number']) ?>
                </div>
                <div style="font-size: 12px; color: #64748B; margin-top: 2px;">
                  <i class="bi bi-envelope"></i> <?= htmlspecialchars($lab['email']) ?>
                </div>
                <?php if ($lab['contact_phone']): ?>
                <div style="font-size: 11px; color: #64748B;">
                  <i class="bi bi-telephone"></i> <?= htmlspecialchars($lab['contact_phone']) ?>
                </div>
                <?php endif; ?>
              </td>

              <td style="padding: 14px 16px; max-width: 220px;">
                <?php if ($lab['services']): ?>
                  <?php foreach (array_slice(explode(',', $lab['services']), 0, 3) as $srv): ?>
                    <span class="service-tag"><?= htmlspecialchars(trim($srv)) ?></span>
                  <?php endforeach; ?>
                  <?php if (count(explode(',', $lab['services'])) > 3): ?>
                    <span style="font-size: 10px; color: #64748B;">+<?= count(explode(',', $lab['services'])) - 3 ?> more</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="font-size: 11px; color: #94A3B8;">General Pathology</span>
                <?php endif; ?>
              </td>

              <td style="padding: 14px 16px; text-align: center;">
                <span class="badge" style="background: #EEF2FF; color: #4338CA; font-weight: 700;">
                  <?= $lab['hospital_count'] ?> hospital<?= $lab['hospital_count'] == 1 ? '' : 's' ?>
                </span>
              </td>

              <td style="padding: 14px 16px; text-align: center;">
                <strong style="color: #0F172A; font-size: 13px;"><?= $lab['test_results_count'] ?></strong>
              </td>

              <td style="padding: 14px 16px;">
                <?php
                  $st = $lab['verification_status'];
                  $badgeCls = match($st) {
                      'VERIFIED'  => 'badge-verified',
                      'PENDING'   => 'badge-pending',
                      'SUSPENDED' => 'badge-suspended',
                      'REJECTED'  => 'badge-rejected',
                      default     => 'badge-secondary'
                  };
                ?>
                <span class="badge <?= $badgeCls ?>" style="font-size: 11px; font-weight: 700; padding: 4px 8px;">
                  <?= $st ?>
                </span>
                <?php if ($st === 'REJECTED' && !empty($lab['rejection_reason'])): ?>
                  <div style="font-size: 10px; color: #991B1B; margin-top: 3px; max-width: 140px;" title="<?= htmlspecialchars($lab['rejection_reason']) ?>">
                    Reason: <?= htmlspecialchars(mb_strimwidth($lab['rejection_reason'], 0, 30, '...')) ?>
                  </div>
                <?php endif; ?>
              </td>

              <td style="padding: 14px 16px; text-align: right; white-space: nowrap;">
                <div style="display: inline-flex; gap: 6px;">
                  <?php if ($lab['verification_status'] === 'PENDING'): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Verify and approve <?= htmlspecialchars(addslashes($lab['name'])) ?>?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="lab_id" value="<?= $lab['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="btn btn-sm btn-success" style="font-size: 11px; padding: 4px 10px;">
                        <i class="bi bi-check-lg"></i> Approve
                      </button>
                    </form>

                    <button type="button" class="btn btn-sm btn-danger" style="font-size: 11px; padding: 4px 10px;" onclick="openRejectModal(<?= $lab['id'] ?>, '<?= htmlspecialchars(addslashes($lab['name'])) ?>')">
                      <i class="bi bi-x-lg"></i> Reject
                    </button>
                  <?php elseif ($lab['verification_status'] === 'VERIFIED'): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Suspend <?= htmlspecialchars(addslashes($lab['name'])) ?>?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="lab_id" value="<?= $lab['id'] ?>">
                      <input type="hidden" name="action" value="suspend">
                      <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size: 11px; padding: 4px 10px;">
                        <i class="bi bi-pause-circle"></i> Suspend
                      </button>
                    </form>
                  <?php elseif ($lab['verification_status'] === 'SUSPENDED' || $lab['verification_status'] === 'REJECTED'): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Reactivate <?= htmlspecialchars(addslashes($lab['name'])) ?>?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="lab_id" value="<?= $lab['id'] ?>">
                      <input type="hidden" name="action" value="reactivate">
                      <button type="submit" class="btn btn-sm btn-primary" style="font-size: 11px; padding: 4px 10px;">
                        <i class="bi bi-arrow-repeat"></i> Reactivate
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- REJECTION MODAL -->
<div id="reject-modal" class="modal-overlay">
  <div style="background: #fff; border-radius: 14px; max-width: 440px; width: 90%; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
    <h3 style="font-size: 16px; font-weight: 800; color: #0F172A; margin: 0 0 8px 0;">Reject Laboratory Application</h3>
    <p style="font-size: 13px; color: #64748B; margin-bottom: 16px;" id="modal-lab-name">Provide the verification reason for rejection:</p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="lab_id" id="modal-lab-id" value="0">
      <input type="hidden" name="action" value="reject">
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 6px;">Rejection Reason</label>
        <textarea name="rejection_reason" rows="3" class="form-control" placeholder="e.g. Could not verify clinical license with health directorate..." required></textarea>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="closeRejectModal()">Cancel</button>
        <button type="submit" class="btn btn-sm btn-danger">Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(id, name) {
  document.getElementById('modal-lab-id').value = id;
  document.getElementById('modal-lab-name').innerText = 'Specify rejection reason for ' + name + ':';
  document.getElementById('reject-modal').style.display = 'flex';
}
function closeRejectModal() {
  document.getElementById('reject-modal').style.display = 'none';
}
</script>

</body>
</html>
