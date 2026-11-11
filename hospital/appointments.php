<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

// Hospital details
$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$message = $error = '';

// Handle Reception Status Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validateCsrf();
    $action = $_POST['action'];
    $aptId  = (int)($_POST['appointment_id'] ?? 0);

    $allowedStatuses = [
        'check_in'          => 'WAITING',
        'start_consult'     => 'IN_CONSULTATION',
        'mark_completed'    => 'COMPLETED',
        'cancel'            => 'CANCELLED'
    ];

    if (isset($allowedStatuses[$action]) && $aptId > 0) {
        $newStatus = $allowedStatuses[$action];
        $stmtUpd = $db->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ? AND hospital_id = ?");
        $stmtUpd->execute([$newStatus, $aptId, $hospitalId]);

        AuditService::log('HOSPITAL_UPDATE_APPOINTMENT', [
            'user_id'     => $_SESSION['user_id'],
            'hospital_id' => $hospitalId,
            'metadata'    => ['appointment_id' => $aptId, 'status' => $newStatus]
        ]);

        $message = "Appointment #{$aptId} status updated to " . str_replace('_', ' ', $newStatus);
    } elseif ($action === 'mark_paid' && $aptId > 0) {
        $db->prepare("UPDATE appointments SET payment_status = 'PAID', updated_at = NOW() WHERE id = ? AND hospital_id = ?")->execute([$aptId, $hospitalId]);
        $message = "Payment marked as PAID at hospital counter.";
    }
}

// Fetch all affiliated doctors for filter
$stmtDocs = $db->prepare("
    SELECT d.id, d.full_name, d.specialization
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    WHERE dh.hospital_id = ? AND dh.status = 'APPROVED'
    ORDER BY d.full_name ASC
");
$stmtDocs->execute([$hospitalId]);
$affiliatedDoctors = $stmtDocs->fetchAll();

// Filter parameters
$selectedDoctor = (int)($_GET['doctor'] ?? 0);
$selectedDate   = trim($_GET['date'] ?? date('Y-m-d'));
$selectedStatus = trim($_GET['status'] ?? 'all');
$searchQuery    = trim($_GET['search'] ?? '');

$where = ["a.hospital_id = ?"];
$params = [$hospitalId];

if ($selectedDoctor > 0) {
    $where[] = "a.doctor_id = ?";
    $params[] = $selectedDoctor;
}

if ($selectedDate !== 'all' && $selectedDate !== '') {
    $where[] = "a.appointment_date = ?";
    $params[] = $selectedDate;
}

if ($selectedStatus !== 'all' && $selectedStatus !== '') {
    $where[] = "a.status = ?";
    $params[] = $selectedStatus;
}

if ($searchQuery !== '') {
    $where[] = "(a.appointment_uid LIKE ? OR p.full_name LIKE ? OR p.patient_uid LIKE ? OR d.full_name LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

$whereSql = implode(' AND ', $where);

$stmtAppointments = $db->prepare("
    SELECT a.*,
           p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth, p.phone AS patient_phone,
           d.full_name AS doctor_name, d.specialization AS doctor_spec,
           dept.name AS department_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN departments dept ON a.department_id = dept.id
    WHERE {$whereSql}
    ORDER BY 
        a.appointment_date DESC,
        CASE WHEN a.priority = 'EMERGENCY' THEN 1 WHEN a.priority = 'URGENT' THEN 2 ELSE 3 END ASC,
        a.serial_number ASC
");
$stmtAppointments->execute($params);
$appointments = $stmtAppointments->fetchAll();

// Stats calculation
$totalCount = count($appointments);
$waitingCount = 0;
$inConsultCount = 0;
$completedCount = 0;
$emergencyCount = 0;

foreach ($appointments as $apt) {
    if ($apt['status'] === 'WAITING' || $apt['status'] === 'CONFIRMED') $waitingCount++;
    if ($apt['status'] === 'IN_CONSULTATION') $inConsultCount++;
    if ($apt['status'] === 'COMPLETED') $completedCount++;
    if ($apt['priority'] === 'EMERGENCY') $emergencyCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Doctor Appointments &amp; Queue — <?= htmlspecialchars($hospital['name'] ?? 'Hospital') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .status-badge-confirmed { background:#FEF3C7; color:#92400E; }
    .status-badge-waiting   { background:#E0F2FE; color:#0369A1; font-weight:700; }
    .status-badge-consult   { background:#DCFCE7; color:#166534; font-weight:700; }
    .status-badge-completed { background:#F1F5F9; color:#475569; }
    .status-badge-cancelled { background:#FEE2E2; color:#991B1B; }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Hospital Admin</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Overview</div>
      <a href="<?= APP_URL ?>/hospital/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>

      <div class="nav-section-title">Appointments &amp; Queue</div>
      <a href="<?= APP_URL ?>/hospital/appointments.php" class="nav-item active">
        <i class="bi bi-calendar2-check-fill"></i>
        <span>Appointments &amp; Queue</span>
      </a>
      <a href="<?= APP_URL ?>/book-doctor.php?hospital=<?= $hospitalId ?>" target="_blank" class="nav-item" style="color:#0F766E;">
        <i class="bi bi-box-arrow-up-right"></i>
        <span>Public Booking Page</span>
      </a>

      <div class="nav-section-title">Staff &amp; Doctors</div>
      <a href="<?= APP_URL ?>/hospital/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i>
        <span>Affiliated Doctors</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/departments.php" class="nav-item">
        <i class="bi bi-diagram-3-fill"></i>
        <span>Departments</span>
      </a>
      <a href="<?= APP_URL ?>/hospital/laboratories.php" class="nav-item">
        <i class="bi bi-flask-fill"></i>
        <span>Laboratories</span>
      </a>

      <div class="nav-section-title">Account</div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="nav-item text-danger">
        <i class="bi bi-box-arrow-right"></i>
        <span>Sign Out</span>
      </a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-header">
      <div>
        <span class="eyebrow" style="color:var(--mc-blue);font-size:11px;font-weight:700;">HOSPITAL CONSULTATION MANAGEMENT</span>
        <h1 class="portal-title" style="font-size:1.35rem; margin:0;">
          Doctor Appointments &amp; Patient Queue (সিরিয়াল ও অ্যাপয়েন্টমেন্ট)
        </h1>
      </div>

      <div class="header-actions">
        <a href="<?= APP_URL ?>/book-doctor.php?hospital=<?= $hospitalId ?>" target="_blank" class="btn btn-primary btn-sm" style="background:#0F766E; border-color:#0F766E;">
          <i class="bi bi-plus-circle"></i> New Patient Booking
        </a>
      </div>
    </header>

    <div class="portal-body" style="padding:var(--space-6);">

      <?php if ($message): ?>
        <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- STATS BAR -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="card p-3 text-center" style="border-left:4px solid #0F766E;">
            <div style="font-size:11px; color:#64748B; font-weight:700; text-transform:uppercase;">Total Serials</div>
            <div style="font-size:1.75rem; font-weight:900; color:#0F172A;"><?= $totalCount ?></div>
            <div style="font-size:11px; color:#64748B;">Date: <?= $selectedDate === 'all' ? 'All Dates' : date('d M Y', strtotime($selectedDate)) ?></div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card p-3 text-center" style="border-left:4px solid #F59E0B;">
            <div style="font-size:11px; color:#64748B; font-weight:700; text-transform:uppercase;">Waiting in Lobby</div>
            <div style="font-size:1.75rem; font-weight:900; color:#D97706;"><?= $waitingCount ?></div>
            <div style="font-size:11px; color:#64748B;">Checked in by reception</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card p-3 text-center" style="border-left:4px solid #0EA5E9;">
            <div style="font-size:11px; color:#64748B; font-weight:700; text-transform:uppercase;">In Consultation</div>
            <div style="font-size:1.75rem; font-weight:900; color:#0284C7;"><?= $inConsultCount ?></div>
            <div style="font-size:11px; color:#64748B;">With doctor right now</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card p-3 text-center" style="border-left:4px solid #EF4444;">
            <div style="font-size:11px; color:#64748B; font-weight:700; text-transform:uppercase;">🚨 STAT / Emergency</div>
            <div style="font-size:1.75rem; font-weight:900; color:#DC2626;"><?= $emergencyCount ?></div>
            <div style="font-size:11px; color:#DC2626; font-weight:600;">Fast Track Priority</div>
          </div>
        </div>
      </div>

      <!-- FILTER CONTROLS -->
      <div class="card mb-4">
        <div class="card-body">
          <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label" style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase;">Select Doctor</label>
              <select name="doctor" class="form-control form-control-sm">
                <option value="0">All Affiliated Doctors</option>
                <?php foreach ($affiliatedDoctors as $ad): ?>
                  <option value="<?= $ad['id'] ?>" <?= $selectedDoctor == $ad['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ad['full_name']) ?> (<?= htmlspecialchars($ad['specialization']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label" style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase;">Date</label>
              <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($selectedDate) ?>">
            </div>

            <div class="col-md-2">
              <label class="form-label" style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase;">Status</label>
              <select name="status" class="form-control form-control-sm">
                <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="CONFIRMED" <?= $selectedStatus === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option>
                <option value="WAITING" <?= $selectedStatus === 'WAITING' ? 'selected' : '' ?>>Waiting (Checked-In)</option>
                <option value="IN_CONSULTATION" <?= $selectedStatus === 'IN_CONSULTATION' ? 'selected' : '' ?>>In Consultation</option>
                <option value="COMPLETED" <?= $selectedStatus === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
                <option value="CANCELLED" <?= $selectedStatus === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label" style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase;">Search Patient / Token</label>
              <input type="text" name="search" class="form-control form-control-sm" placeholder="Patient Name, UID, APT-Token..." value="<?= htmlspecialchars($searchQuery) ?>">
            </div>

            <div class="col-md-2 d-flex gap-2">
              <button type="submit" class="btn btn-secondary btn-sm flex-fill">
                <i class="bi bi-filter"></i> Filter
              </button>
              <a href="<?= APP_URL ?>/hospital/appointments.php" class="btn btn-outline btn-sm">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <!-- APPOINTMENTS TABLE -->
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title"><i class="bi bi-ticket-detailed-fill text-primary"></i> Patient Appointments Roster (<?= count($appointments) ?>)</h3>
        </div>
        <div class="card-body p-0">
          <?php if (empty($appointments)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-calendar2-x" style="font-size:3rem; color:#CBD5E1;"></i>
              <h4 style="font-size:1.1rem; color:#334155; margin-top:10px;">No appointments found</h4>
              <p style="font-size:13px; color:#64748B;">No patient appointments match the selected filters.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table mb-0">
                <thead>
                  <tr>
                    <th style="width:50px;">Serial</th>
                    <th>Token ID</th>
                    <th>Patient Details</th>
                    <th>Consulting Doctor</th>
                    <th>Date &amp; Slot</th>
                    <th>Priority</th>
                    <th>Fee Status</th>
                    <th>Status</th>
                    <th style="text-align:right;">Reception Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($appointments as $apt): ?>
                    <tr style="<?= $apt['priority'] === 'EMERGENCY' ? 'background:#FEF2F2;' : '' ?>">
                      <td>
                        <strong style="font-size:1.2rem; color:<?= $apt['priority'] === 'EMERGENCY' ? '#DC2626' : '#0F766E' ?>;">
                          #<?= str_pad($apt['serial_number'], 2, '0', STR_PAD_LEFT) ?>
                        </strong>
                      </td>
                      <td>
                        <code style="font-weight:700; color:#4F46E5;"><?= htmlspecialchars($apt['appointment_uid']) ?></code>
                      </td>
                      <td>
                        <strong style="color:#0F172A;"><?= htmlspecialchars($apt['patient_name']) ?></strong>
                        <div style="font-size:11px; color:#64748B;">
                          ID: <code><?= htmlspecialchars($apt['patient_uid']) ?></code> &bull; Phone: <?= htmlspecialchars($apt['patient_phone'] ?? '—') ?>
                        </div>
                        <?php if ($apt['problem_description']): ?>
                          <div style="font-size:11px; color:#334155; margin-top:2px;">
                            <em><?= htmlspecialchars(substr($apt['problem_description'], 0, 50)) ?>...</em>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <strong style="color:#0F172A;">Dr. <?= htmlspecialchars($apt['doctor_name']) ?></strong>
                        <div style="font-size:11px; color:#0F766E;"><?= htmlspecialchars($apt['doctor_spec']) ?></div>
                      </td>
                      <td>
                        <div style="font-size:12px; font-weight:700;"><?= date('d M Y', strtotime($apt['appointment_date'])) ?></div>
                        <div style="font-size:11px; color:#64748B;"><?= htmlspecialchars($apt['time_slot']) ?></div>
                      </td>
                      <td>
                        <span class="badge" style="font-size:10px; <?= $apt['priority'] === 'EMERGENCY' ? 'background:#FEE2E2; color:#991B1B;' : ($apt['priority'] === 'URGENT' ? 'background:#FEF3C7; color:#92400E;' : 'background:#F1F5F9; color:#334155;') ?>">
                          <?= htmlspecialchars($apt['priority']) ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($apt['payment_status'] === 'PAID'): ?>
                          <span class="badge badge-success"><i class="bi bi-check-circle"></i> Paid ৳<?= number_format($apt['consultation_fee']) ?></span>
                        <?php else: ?>
                          <form method="POST" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="mark_paid">
                            <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm text-primary" style="font-size:10px; padding:2px 6px;">
                              ৳<?= number_format($apt['consultation_fee']) ?> (Mark Paid)
                            </button>
                          </form>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php
                          $st = $apt['status'];
                          $badgeCls = $st === 'WAITING' ? 'status-badge-waiting' : ($st === 'IN_CONSULTATION' ? 'status-badge-consult' : ($st === 'COMPLETED' ? 'status-badge-completed' : ($st === 'CANCELLED' ? 'status-badge-cancelled' : 'status-badge-confirmed')));
                        ?>
                        <span class="badge <?= $badgeCls ?>" style="font-size:11px; padding:4px 8px;">
                          <?= str_replace('_', ' ', $st) ?>
                        </span>
                      </td>
                      <td style="text-align:right;">
                        <div style="display:flex; gap:4px; justify-content:flex-end;">
                          <?php if ($st === 'CONFIRMED'): ?>
                            <form method="POST">
                              <?= csrfField() ?>
                              <input type="hidden" name="action" value="check_in">
                              <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-primary" style="background:#0284C7; border-color:#0284C7; font-size:11px; padding:4px 8px;" title="Patient arrived at reception">
                                <i class="bi bi-person-check-fill"></i> Check-In
                              </button>
                            </form>
                          <?php endif; ?>

                          <?php if ($st !== 'COMPLETED' && $st !== 'CANCELLED'): ?>
                            <form method="POST">
                              <?= csrfField() ?>
                              <input type="hidden" name="action" value="mark_completed">
                              <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-outline-success" style="font-size:11px; padding:4px 8px;" title="Mark Completed">
                                <i class="bi bi-check-lg"></i>
                              </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Cancel this appointment?');">
                              <?= csrfField() ?>
                              <input type="hidden" name="action" value="cancel">
                              <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:11px; padding:4px 8px;" title="Cancel">
                                <i class="bi bi-x-lg"></i>
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
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>

