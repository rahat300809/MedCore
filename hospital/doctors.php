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

// Handle add affiliation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    validateCsrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $deptId     = (int)($_POST['department_id'] ?? 0);
    $desig      = trim($_POST['designation'] ?? 'Consultant');
    $empType    = trim($_POST['employment_type'] ?? 'FULL_TIME');

    if (empty($identifier)) {
        $error = 'Please enter Doctor UID (DR-XXXXXX) or BMDC Registration number.';
    } else {
        $stmtDoc = $db->prepare("
            SELECT * FROM doctors
            WHERE doctor_uid = ? OR medical_registration_id = ?
            LIMIT 1
        ");
        $stmtDoc->execute([$identifier, $identifier]);
        $doc = $stmtDoc->fetch();

        if (!$doc) {
            $error = 'No verified doctor found matching "' . htmlspecialchars($identifier) . '".';
        } else {
            // Check if already affiliated
            $stmtCheck = $db->prepare("SELECT * FROM doctor_hospitals WHERE doctor_id = ? AND hospital_id = ?");
            $stmtCheck->execute([$doc['id'], $hospitalId]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $error = 'Dr. ' . htmlspecialchars($doc['full_name']) . ' is already associated with this hospital (Status: ' . $existing['status'] . ').';
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
                    'metadata'   => ['doctor_name' => $doc['full_name'], 'designation' => $desig],
                ]);

                $success = 'Dr. ' . htmlspecialchars($doc['full_name']) . ' has been affiliated and approved successfully!';
            }
        }
    }
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    validateCsrf();
    $dhId = (int)$_POST['dh_id'];
    $newStatus = $_POST['new_status'];
    if (in_array($newStatus, ['APPROVED', 'SUSPENDED', 'REMOVED'])) {
        $db->prepare("UPDATE doctor_hospitals SET status = ? WHERE id = ? AND hospital_id = ?")
           ->execute([$newStatus, $dhId, $hospitalId]);
        $success = "Doctor affiliation status updated to {$newStatus}.";
    }
}

// Fetch departments for dropdown
$stmtDepts = $db->prepare("SELECT * FROM departments WHERE hospital_id = ? AND status = 'ACTIVE' ORDER BY name");
$stmtDepts->execute([$hospitalId]);
$departments = $stmtDepts->fetchAll();

// Fetch affiliated doctors
$stmtDocs = $db->prepare("
    SELECT dh.*, d.full_name, d.doctor_uid, d.specialization, d.qualification,
           d.medical_registration_id, u.email, u.phone,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.hospital_id = ?
    ORDER BY FIELD(dh.status, 'APPROVED', 'PENDING', 'SUSPENDED', 'REJECTED', 'REMOVED'), dh.joined_at DESC
");
$stmtDocs->execute([$hospitalId]);
$doctors = $stmtDocs->fetchAll();
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
      </a>
      <a href="<?= APP_URL ?>/hospital/affiliations.php" class="nav-item">
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
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Affiliated Doctors Directory</h1>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('affiliate-modal').style.display='block'">
          <i class="bi bi-person-plus-fill"></i> Affiliate New Doctor
        </button>
      </div>
    </header>

    <div class="portal-body">
      <?php if ($success): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Affiliate Modal -->
      <div id="affiliate-modal" class="card mb-4" style="display: none; border: 2px solid var(--mc-blue);">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-person-plus"></i> Add Verified Doctor to Hospital</h3>
          <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('affiliate-modal').style.display='none'">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="add_doctor" value="1">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Doctor UID or BMDC Registration ID <span class="text-danger">*</span></label>
                <input type="text" name="identifier" class="form-control" placeholder="e.g. DR-000001 or A-12345" required>
                <small class="text-muted">Doctor must already have an account on MedCore.</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Assign Department</label>
                <select name="department_id" class="form-control">
                  <option value="">-- Select Department --</option>
                  <?php foreach ($departments as $dept): ?>
                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Designation / Role in Hospital</label>
                <input type="text" name="designation" class="form-control" placeholder="e.g. Senior Consultant, Cardiologist" value="Senior Consultant">
              </div>
              <div class="col-md-6">
                <label class="form-label">Employment Type</label>
                <select name="employment_type" class="form-control">
                  <option value="FULL_TIME">Full Time</option>
                  <option value="PART_TIME">Part Time</option>
                  <option value="VISITING">Visiting Consultant</option>
                  <option value="HONORARY">Honorary</option>
                </select>
              </div>
              <div class="col-12 text-end mt-4">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('affiliate-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Confirm Affiliation</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Doctors Table -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-people-fill"></i> Affiliated Medical Practitioners (<?= count($doctors) ?>)</h3>
        </div>
        <div class="card-body">
          <?php if (empty($doctors)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-person-x" style="font-size: 3rem; color: #CBD5E1;"></i>
              <p class="mt-2">No doctors affiliated with this hospital yet.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Doctor Name</th>
                    <th>BMDC Reg &amp; ID</th>
                    <th>Specialization</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($doctors as $d): ?>
                    <tr>
                      <td>
                        <strong>Dr. <?= htmlspecialchars($d['full_name']) ?></strong>
                        <div class="text-muted" style="font-size: 0.8rem;"><?= htmlspecialchars($d['email']) ?></div>
                      </td>
                      <td>
                        <span class="badge badge-secondary"><?= htmlspecialchars($d['medical_registration_id']) ?></span>
                        <div class="text-muted" style="font-size: 0.78rem;"><?= htmlspecialchars($d['doctor_uid']) ?></div>
                      </td>
                      <td><?= htmlspecialchars($d['specialization'] ?? 'General') ?></td>
                      <td><?= htmlspecialchars($d['department_name'] ?? 'General OPD') ?></td>
                      <td><?= htmlspecialchars($d['designation'] ?? 'Consultant') ?></td>
                      <td><small class="text-muted"><?= str_replace('_', ' ', $d['employment_type']) ?></small></td>
                      <td>
                        <?php if ($d['status'] === 'APPROVED'): ?>
                          <span class="badge badge-success"><i class="bi bi-check2"></i> Approved</span>
                        <?php elseif ($d['status'] === 'PENDING'): ?>
                          <span class="badge badge-warning">Pending</span>
                        <?php elseif ($d['status'] === 'SUSPENDED'): ?>
                          <span class="badge badge-danger">Suspended</span>
                        <?php else: ?>
                          <span class="badge badge-secondary"><?= htmlspecialchars($d['status']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <form method="POST" style="display:inline;">
                          <?= csrfField() ?>
                          <input type="hidden" name="update_status" value="1">
                          <input type="hidden" name="dh_id" value="<?= $d['id'] ?>">
                          <?php if ($d['status'] === 'APPROVED'): ?>
                            <input type="hidden" name="new_status" value="SUSPENDED">
                            <button type="submit" class="btn btn-outline btn-sm text-danger" title="Suspend Affiliation" onclick="return confirm('Suspend this doctor?');">
                              <i class="bi bi-pause-circle"></i>
                            </button>
                          <?php else: ?>
                            <input type="hidden" name="new_status" value="APPROVED">
                            <button type="submit" class="btn btn-outline btn-sm text-success" title="Approve Affiliation">
                              <i class="bi bi-play-circle"></i>
                            </button>
                          <?php endif; ?>
                        </form>
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
