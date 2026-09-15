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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $dhId   = (int)($_POST['dh_id'] ?? 0);

    if (in_array($action, ['approve', 'reject']) && $dhId) {
        $newStatus = $action === 'approve' ? 'APPROVED' : 'REJECTED';
        $reason = trim($_POST['rejection_reason'] ?? '');

        $stmt = $db->prepare("
            UPDATE doctor_hospitals
            SET status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ?
            WHERE id = ? AND hospital_id = ?
        ");
        $stmt->execute([$newStatus, $_SESSION['user_id'], $reason ?: null, $dhId, $hospitalId]);

        AuditService::log($action === 'approve' ? AuditService::AFFILIATION_APPROVED : AuditService::AFFILIATION_REJECTED, [
            'user_id'    => $_SESSION['user_id'],
            'hospital_id'=> $hospitalId,
            'target_id'  => $dhId,
        ]);

        $success = "Affiliation request has been " . strtolower($newStatus) . " successfully.";
    }
}

// Fetch pending requests
$stmtPending = $db->prepare("
    SELECT dh.*, d.full_name, d.specialization, d.qualification, d.medical_registration_id,
           u.email, u.phone, dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.hospital_id = ? AND dh.status = 'PENDING'
    ORDER BY dh.created_at ASC
");
$stmtPending->execute([$hospitalId]);
$pending = $stmtPending->fetchAll();

// Fetch processed history
$stmtHistory = $db->prepare("
    SELECT dh.*, d.full_name, d.specialization, d.medical_registration_id,
           u.email, dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.hospital_id = ? AND dh.status != 'PENDING'
    ORDER BY dh.updated_at DESC
    LIMIT 20
");
$stmtHistory->execute([$hospitalId]);
$history = $stmtHistory->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Affiliation Approvals — <?= htmlspecialchars($hospital['name']) ?></title>
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
      <a href="<?= APP_URL ?>/hospital/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i>
        <span>Affiliated Doctors</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/affiliations.php" class="nav-item active">
        <i class="bi bi-clock-history"></i>
        <span>Pending Requests</span>
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
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-outline btn-sm" style="width: 100%; margin-top: var(--space-3);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-header">
      <div class="d-flex align-items-center gap-3">
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Doctor Affiliation Approvals</h1>
      </div>
    </header>

    <div class="portal-body">
      <?php if ($success): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Pending Card -->
      <div class="card mb-4" style="border-left: 4px solid var(--mc-warning);">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-hourglass-split"></i> Awaiting Hospital Authorization (<?= count($pending) ?>)</h3>
        </div>
        <div class="card-body">
          <?php if (empty($pending)): ?>
            <div class="text-center py-4 text-muted">
              <i class="bi bi-check-circle" style="font-size: 2.5rem; color: #10B981;"></i>
              <p class="mt-2">No pending doctor affiliation requests. All caught up!</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Doctor</th>
                    <th>BMDC Reg</th>
                    <th>Specialization</th>
                    <th>Requested Dept</th>
                    <th>Requested Designation</th>
                    <th>Date Requested</th>
                    <th>Decision</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pending as $p): ?>
                    <tr>
                      <td>
                        <strong>Dr. <?= htmlspecialchars($p['full_name']) ?></strong>
                        <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($p['email']) ?></div>
                      </td>
                      <td><span class="badge badge-secondary"><?= htmlspecialchars($p['medical_registration_id']) ?></span></td>
                      <td><?= htmlspecialchars($p['specialization'] ?? 'General') ?></td>
                      <td><?= htmlspecialchars($p['department_name'] ?? 'General') ?></td>
                      <td><?= htmlspecialchars($p['designation'] ?? 'Consultant') ?></td>
                      <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                      <td>
                        <div class="d-flex gap-2">
                          <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="dh_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" title="Approve Affiliation">
                              <i class="bi bi-check-lg"></i> Approve
                            </button>
                          </form>
                          <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="dh_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-outline text-danger btn-sm" title="Reject Affiliation" onclick="return confirm('Reject this doctor affiliation?');">
                              <i class="bi bi-x-lg"></i> Reject
                            </button>
                          </form>
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

      <!-- History Card -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><i class="bi bi-clock-history"></i> Affiliation Decision History</h3>
        </div>
        <div class="card-body">
          <?php if (empty($history)): ?>
            <div class="text-muted py-3">No processed affiliation history found.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Doctor</th>
                    <th>BMDC Reg</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Updated At</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($history as $h): ?>
                    <tr>
                      <td>Dr. <?= htmlspecialchars($h['full_name']) ?></td>
                      <td><?= htmlspecialchars($h['medical_registration_id']) ?></td>
                      <td><?= htmlspecialchars($h['department_name'] ?? 'General') ?></td>
                      <td>
                        <?php if ($h['status'] === 'APPROVED'): ?>
                          <span class="badge badge-success">Approved</span>
                        <?php elseif ($h['status'] === 'REJECTED'): ?>
                          <span class="badge badge-danger">Rejected</span>
                        <?php else: ?>
                          <span class="badge badge-secondary"><?= htmlspecialchars($h['status']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td><?= date('d M Y, h:i A', strtotime($h['updated_at'])) ?></td>
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
