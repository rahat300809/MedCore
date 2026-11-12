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
           p.full_name AS patient_name, p.patient_uid, p.gender, p.date_of_birth, u.phone AS patient_phone,
           d.full_name AS doctor_name, d.specialization AS doctor_spec,
           dept.name AS department_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON u.id = p.user_id
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
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalAccent = '#0b4f6c';
$portalUser = $hospital['name'] ?? ($_SESSION['hospital_name'] ?? 'Hospital');
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Reception';
$portalTitle = 'Reception desk';
$portalSubtitle = 'সিরিয়াল চেক-ইন ও কিউ ম্যানেজমেন্ট';
$portalNav = mc_hospital_nav('appointments');
$portalTopAction = '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/book-doctor.php?hospital=' . $hospitalId . '" target="_blank">New booking</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($message): ?><div class="mc-alert mc-alert-ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mc-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Serials</div><div class="v"><?= (int)$totalCount ?></div></div>
  <div class="mc-stat"><div class="l">Waiting</div><div class="v"><?= (int)$waitingCount ?></div></div>
  <div class="mc-stat"><div class="l">In consult</div><div class="v"><?= (int)$inConsultCount ?></div></div>
  <div class="mc-stat"><div class="l">Emergency</div><div class="v"><?= (int)$emergencyCount ?></div></div>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Filters</h2></div>
  <form method="GET" style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:end">
    <label class="mc-field" style="margin:0">
      <span>Doctor</span>
      <select name="doctor">
        <option value="0">All doctors</option>
        <?php foreach ($affiliatedDoctors as $ad): ?>
          <option value="<?= (int)$ad['id'] ?>" <?= $selectedDoctor == $ad['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($ad['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="mc-field" style="margin:0">
      <span>Date</span>
      <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
    </label>
    <label class="mc-field" style="margin:0">
      <span>Status</span>
      <select name="status">
        <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>All</option>
        <option value="CONFIRMED" <?= $selectedStatus === 'CONFIRMED' ? 'selected' : '' ?>>Confirmed</option>
        <option value="WAITING" <?= $selectedStatus === 'WAITING' ? 'selected' : '' ?>>Waiting</option>
        <option value="IN_CONSULTATION" <?= $selectedStatus === 'IN_CONSULTATION' ? 'selected' : '' ?>>In consultation</option>
        <option value="COMPLETED" <?= $selectedStatus === 'COMPLETED' ? 'selected' : '' ?>>Completed</option>
        <option value="CANCELLED" <?= $selectedStatus === 'CANCELLED' ? 'selected' : '' ?>>Cancelled</option>
      </select>
    </label>
    <label class="mc-field" style="margin:0">
      <span>Search</span>
      <input type="text" name="search" placeholder="Name / UID / token" value="<?= htmlspecialchars($searchQuery) ?>">
    </label>
    <div style="display:flex;gap:.45rem">
      <button type="submit" class="mc-btn mc-btn-primary">Filter</button>
      <a class="mc-btn mc-btn-secondary" href="<?= APP_URL ?>/hospital/appointments.php">Reset</a>
    </div>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Appointments (<?= count($appointments) ?>)</h2></div>
  <?php if (empty($appointments)): ?>
    <p style="color:var(--muted);margin:0">No appointments match these filters.</p>
  <?php else: ?>
    <div style="overflow:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Token</th>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Slot</th>
            <th>Fee</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appointments as $apt): ?>
            <?php $st = $apt['status']; ?>
            <tr>
              <td><strong>#<?= str_pad((string)$apt['serial_number'], 2, '0', STR_PAD_LEFT) ?></strong></td>
              <td><code class="mc-mono"><?= htmlspecialchars($apt['appointment_uid']) ?></code></td>
              <td>
                <strong><?= htmlspecialchars($apt['patient_name']) ?></strong>
                <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($apt['patient_uid']) ?> · <?= htmlspecialchars($apt['patient_phone'] ?? '—') ?></div>
              </td>
              <td>
                <?= htmlspecialchars($apt['doctor_name']) ?>
                <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($apt['doctor_spec']) ?></div>
              </td>
              <td>
                <?= date('d M Y', strtotime($apt['appointment_date'])) ?>
                <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($apt['time_slot']) ?></div>
              </td>
              <td>
                <?php if ($apt['payment_status'] === 'PAID'): ?>
                  <span class="lab-status">Paid ৳<?= number_format((float)$apt['consultation_fee']) ?></span>
                <?php else: ?>
                  <form method="POST" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="appointment_id" value="<?= (int)$apt['id'] ?>">
                    <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm">৳<?= number_format((float)$apt['consultation_fee']) ?> pay</button>
                  </form>
                <?php endif; ?>
              </td>
              <td><span class="lab-status"><?= htmlspecialchars(str_replace('_', ' ', $st)) ?></span></td>
              <td>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                  <?php if ($st === 'CONFIRMED'): ?>
                    <form method="POST">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="check_in">
                      <input type="hidden" name="appointment_id" value="<?= (int)$apt['id'] ?>">
                      <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm">Check-in</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($st !== 'COMPLETED' && $st !== 'CANCELLED'): ?>
                    <form method="POST">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="mark_completed">
                      <input type="hidden" name="appointment_id" value="<?= (int)$apt['id'] ?>">
                      <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm">Done</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Cancel this appointment?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="appointment_id" value="<?= (int)$apt['id'] ?>">
                      <button type="submit" class="mc-btn mc-btn-ghost mc-btn-sm">Cancel</button>
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

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>

