<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

// Fetch patient info
$stmtPatient = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmtPatient->execute([$patientId]);
$patient = $stmtPatient->fetch();

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    validateCsrf();
    $aptId = (int)($_POST['appointment_id'] ?? 0);
    $stmtCancel = $db->prepare("UPDATE appointments SET status = 'CANCELLED' WHERE id = ? AND patient_id = ? AND status IN ('CONFIRMED', 'WAITING')");
    $stmtCancel->execute([$aptId, $patientId]);

    if ($stmtCancel->rowCount() > 0) {
        AuditService::log('CANCEL_APPOINTMENT', [
            'user_id' => $_SESSION['user_id'],
            'patient_id' => $patientId,
            'appointment_id' => $aptId
        ]);
        $_SESSION['flash'] = [
            'type' => 'success',
            'title' => 'Appointment Cancelled',
            'msg' => 'Your doctor appointment serial has been successfully cancelled.'
        ];
    }
    header('Location: ' . APP_URL . '/patient/appointments.php');
    exit;
}

$tab = $_GET['tab'] ?? 'upcoming';

// Fetch appointments
$query = "
    SELECT a.*, d.full_name AS doctor_name, d.specialization, d.qualification, d.rating_score, d.rating_count,
           h.name AS hospital_name, h.address AS hospital_address, h.phone AS hospital_phone,
           dept.name AS department_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN hospitals h ON a.hospital_id = h.id
    LEFT JOIN departments dept ON a.department_id = dept.id
    WHERE a.patient_id = ?
";

if ($tab === 'upcoming') {
    $query .= " AND a.status IN ('CONFIRMED', 'WAITING', 'IN_CONSULTATION') AND a.appointment_date >= CURDATE()";
    $query .= " ORDER BY a.appointment_date ASC, a.serial_number ASC";
} elseif ($tab === 'past') {
    $query .= " AND (a.status IN ('COMPLETED', 'CANCELLED', 'NO_SHOW') OR a.appointment_date < CURDATE())";
    $query .= " ORDER BY a.appointment_date DESC, a.serial_number ASC";
} else {
    $query .= " ORDER BY a.appointment_date DESC, a.serial_number ASC";
}

$stmt = $db->prepare($query);
$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

// Counts for tabs
$stmtCountUpcoming = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ('CONFIRMED', 'WAITING', 'IN_CONSULTATION') AND appointment_date >= CURDATE()");
$stmtCountUpcoming->execute([$patientId]);
$countUpcoming = (int)$stmtCountUpcoming->fetchColumn();

$stmtCountAll = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
$stmtCountAll->execute([$patientId]);
$countAll = (int)$stmtCountAll->fetchColumn();

// Pending requests count for sidebar badge
$stmtPending = $db->prepare("SELECT COUNT(*) FROM access_requests WHERE patient_id = ? AND status = 'PENDING'");
$stmtPending->execute([$patientId]);
$pendingRequestsCount = (int)$stmtPending->fetchColumn();

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Appointments &amp; Doctor Serials — MedCore Patient Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .apt-card {
      background: #ffffff;
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-xl);
      padding: 24px;
      margin-bottom: 20px;
      box-shadow: var(--shadow-sm);
      transition: all 0.2s ease;
      position: relative;
    }
    .apt-card:hover {
      box-shadow: var(--shadow-md);
      border-color: #CBD5E1;
    }
    .token-box {
      background: linear-gradient(135deg, #0284c7, #2563eb);
      color: #fff;
      border-radius: var(--radius-lg);
      padding: 14px 20px;
      text-align: center;
      min-width: 90px;
      flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(37,99,235,0.25);
    }
    .token-box.emergency {
      background: linear-gradient(135deg, #EF4444, #DC2626);
      box-shadow: 0 4px 12px rgba(239,68,68,0.3);
    }
    .token-num {
      font-family: 'JetBrains Mono', monospace;
      font-size: 26px;
      font-weight: 800;
      line-height: 1;
    }
    .token-lbl {
      font-size: 11px;
      text-transform: uppercase;
      font-weight: 700;
      letter-spacing: 0.5px;
      opacity: 0.9;
      margin-top: 4px;
    }
    .badge-status {
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .status-confirmed { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
    .status-waiting { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; animation: pulse 2s infinite; }
    .status-consultation { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-weight: 800; }
    .status-completed { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }
    .status-cancelled { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.75; } }

    @media print {
      body * { visibility: hidden !important; }
      #print-slip, #print-slip * { visibility: visible !important; }
      #print-slip { position: absolute; left: 0; top: 0; width: 100%; display: block !important; }
    }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" role="navigation" aria-label="Patient portal navigation">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:14px;">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-tagline">Patient Portal</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="<?= APP_URL ?>/patient/dashboard.php">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
      </a>

      <div class="sidebar-section-title">Appointments &amp; Consultations</div>
      <a href="<?= APP_URL ?>/patient/appointments.php" class="active" aria-current="page">
        <i class="bi bi-calendar-check-fill"></i> My Appointments
        <?php if ($countUpcoming > 0): ?>
          <span class="nav-badge"><?= $countUpcoming ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/book-doctor.php" target="_blank" style="color:var(--primary);font-weight:600;">
        <i class="bi bi-plus-circle-fill"></i> Book Doctor Serial
      </a>
      <a href="<?= APP_URL ?>/patient/prescriptions.php">
        <i class="bi bi-file-earmark-medical-fill"></i> Prescriptions
      </a>

      <div class="sidebar-section-title">My Health</div>
      <a href="<?= APP_URL ?>/patient/medical-history.php">
        <i class="bi bi-clipboard2-heart-fill"></i> Medical History
      </a>
      <a href="<?= APP_URL ?>/patient/timeline.php">
        <i class="bi bi-calendar2-heart-fill"></i> Health Timeline
      </a>
      <a href="<?= APP_URL ?>/patient/medications.php">
        <i class="bi bi-capsule-pill"></i> Medications
      </a>
      <a href="<?= APP_URL ?>/patient/allergies.php">
        <i class="bi bi-shield-exclamation"></i> Allergies
      </a>
      <a href="<?= APP_URL ?>/patient/lab-reports.php">
        <i class="bi bi-file-earmark-bar-graph-fill"></i> Lab Reports
      </a>

      <div class="sidebar-section-title">Privacy &amp; Access</div>
      <a href="<?= APP_URL ?>/patient/consent-requests.php">
        <i class="bi bi-person-check-fill"></i> Consent Requests
        <?php if ($pendingRequestsCount > 0): ?>
          <span class="nav-badge"><?= $pendingRequestsCount ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= APP_URL ?>/patient/access-history.php">
        <i class="bi bi-clock-history"></i> Access History
      </a>

      <div class="sidebar-section-title">Account</div>
      <a href="<?= APP_URL ?>/patient/profile.php">
        <i class="bi bi-person-circle"></i> My Profile
      </a>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" data-confirm="Log out of your session?">
        <i class="bi bi-box-arrow-right"></i> Log Out
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($patient['full_name'] ?? 'P', 0, 1)) ?></div>
        <div class="sidebar-user-info">
          <div class="name"><?= htmlspecialchars($patient['full_name'] ?? '') ?></div>
          <div class="role"><?= htmlspecialchars($patient['patient_uid'] ?? '') ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="portal-main">
    <header class="portal-topbar">
      <button class="hamburger" id="hamburger-btn" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
      <div style="flex:1;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">My Doctor Appointments &amp; Serials (অনলাইন সিরিয়াল)</h2>
        <div style="font-size:12px;color:var(--mc-text-muted);"><?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_uid']) ?>)</div>
      </div>
      <a href="<?= APP_URL ?>/book-doctor.php" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
        <i class="bi bi-plus-lg"></i> Book New Doctor Serial
      </a>
    </header>

    <div class="portal-content">
      <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] === 'error' ? 'danger' : 'success' ?>" style="margin-bottom:20px;border-radius:var(--radius-lg);padding:14px 18px;display:flex;align-items:center;gap:12px;">
          <i class="bi bi-check-circle-fill" style="font-size:18px;"></i>
          <div>
            <strong><?= htmlspecialchars($_SESSION['flash']['title']) ?></strong>
            <div><?= htmlspecialchars($_SESSION['flash']['msg']) ?></div>
          </div>
        </div>
        <?php unset($_SESSION['flash']); ?>
      <?php endif; ?>

      <!-- Quick Action Banner -->
      <div style="background:linear-gradient(135deg, #0284c7 0%, #1e40af 100%);color:#fff;border-radius:var(--radius-xl);padding:24px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;box-shadow:0 8px 24px rgba(37,99,235,0.2);">
        <div>
          <span style="background:rgba(255,255,255,0.2);color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;text-transform:uppercase;letter-spacing:0.5px;">Online Appointment Directory</span>
          <h3 style="margin:8px 0 4px;font-size:20px;font-weight:800;color:#fff;">Need to consult a Specialist Doctor?</h3>
          <p style="margin:0;font-size:13px;opacity:0.9;max-width:550px;">Find specialist doctors by department and hospital, check ratings &amp; fees, and reserve your queue serial number instantly.</p>
        </div>
        <a href="<?= APP_URL ?>/book-doctor.php" class="btn btn-lg" style="background:#fff;color:#1e40af;font-weight:700;border:none;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
          <i class="bi bi-calendar2-check-fill" style="margin-right:6px;"></i> Book Doctor Serial Now
        </a>
      </div>

      <!-- Navigation Tabs -->
      <div style="display:flex;gap:8px;border-bottom:1px solid var(--mc-border);margin-bottom:20px;padding-bottom:2px;">
        <a href="?tab=upcoming" style="padding:10px 16px;font-size:14px;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $tab === 'upcoming' ? 'var(--primary)' : 'transparent' ?>;color:<?= $tab === 'upcoming' ? 'var(--primary)' : 'var(--mc-text-muted)' ?>;">
          <i class="bi bi-clock-history"></i> Upcoming &amp; Active (<?= $countUpcoming ?>)
        </a>
        <a href="?tab=past" style="padding:10px 16px;font-size:14px;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $tab === 'past' ? 'var(--primary)' : 'transparent' ?>;color:<?= $tab === 'past' ? 'var(--primary)' : 'var(--mc-text-muted)' ?>;">
          <i class="bi bi-check2-all"></i> Past &amp; Completed
        </a>
        <a href="?tab=all" style="padding:10px 16px;font-size:14px;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $tab === 'all' ? 'var(--primary)' : 'transparent' ?>;color:<?= $tab === 'all' ? 'var(--primary)' : 'var(--mc-text-muted)' ?>;">
          <i class="bi bi-list-ul"></i> All Appointments (<?= $countAll ?>)
        </a>
      </div>

      <?php if (empty($appointments)): ?>
        <div style="background:#fff;border:1px dashed var(--mc-border);border-radius:var(--radius-xl);padding:60px 20px;text-align:center;">
          <div style="width:64px;height:64px;background:#EFF6FF;color:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px;">
            <i class="bi bi-calendar2-x"></i>
          </div>
          <h4 style="font-size:17px;font-weight:700;margin-bottom:6px;">No appointments found</h4>
          <p style="color:var(--mc-text-muted);font-size:14px;max-width:400px;margin:0 auto 20px;">You don't have any <?= htmlspecialchars($tab) ?> doctor appointments in your MedCore record.</p>
          <a href="<?= APP_URL ?>/book-doctor.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Book Doctor Serial Now
          </a>
        </div>
      <?php else: ?>
        <div class="appointments-list">
          <?php foreach ($appointments as $apt): ?>
            <?php
              $isEmergency = ($apt['priority'] === 'EMERGENCY');
              $status = $apt['status'];
              $statusClass = 'status-confirmed';
              $statusText = 'বুকিং নিশ্চিত (Confirmed)';
              if ($status === 'WAITING') {
                  $statusClass = 'status-waiting';
                  $statusText = 'লবিতে অপেক্ষমান (Waiting in Lobby)';
              } elseif ($status === 'IN_CONSULTATION') {
                  $statusClass = 'status-consultation';
                  $statusText = 'ডাক্তারের রুমে সেশন চলছে (In Consultation)';
              } elseif ($status === 'COMPLETED') {
                  $statusClass = 'status-completed';
                  $statusText = 'সম্পন্ন হয়েছে (Completed)';
              } elseif ($status === 'CANCELLED') {
                  $statusClass = 'status-cancelled';
                  $statusText = 'বাতিল (Cancelled)';
              }
            ?>
            <div class="apt-card">
              <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
                
                <!-- Serial Token Box -->
                <div class="token-box <?= $isEmergency ? 'emergency' : '' ?>">
                  <div class="token-num">#<?= str_pad((string)$apt['serial_number'], 2, '0', STR_PAD_LEFT) ?></div>
                  <div class="token-lbl"><?= $isEmergency ? 'STAT EMS' : 'SERIAL' ?></div>
                </div>

                <!-- Info Details -->
                <div style="flex:1;min-width:240px;">
                  <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                    <span class="badge-status <?= $statusClass ?>">
                      <i class="bi <?= $status === 'WAITING' ? 'bi-hourglass-split' : ($status === 'IN_CONSULTATION' ? 'bi-broadcast' : ($status === 'COMPLETED' ? 'bi-check2' : 'bi-check-circle-fill')) ?>"></i>
                      <?= $statusText ?>
                    </span>
                    <span style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--mc-text-muted);background:#F3F4F6;padding:2px 8px;border-radius:4px;">
                      <?= htmlspecialchars($apt['appointment_uid']) ?>
                    </span>
                    <?php if ($isEmergency): ?>
                      <span style="background:#FEE2E2;color:#DC2626;font-size:11px;font-weight:800;padding:2px 8px;border-radius:4px;border:1px solid #FECACA;">
                        <i class="bi bi-shield-fill-exclamation"></i> STAT EMERGENCY
                      </span>
                    <?php endif; ?>
                  </div>

                  <h3 style="font-size:18px;font-weight:700;margin:0 0 4px;color:var(--mc-text);">
                    Dr. <?= htmlspecialchars($apt['doctor_name']) ?>
                  </h3>
                  <div style="font-size:13px;color:var(--mc-text-muted);margin-bottom:10px;">
                    <span style="color:var(--primary);font-weight:600;"><?= htmlspecialchars($apt['specialization']) ?></span> · <?= htmlspecialchars($apt['qualification'] ?? '') ?>
                    <?php if (!empty($apt['rating_score'])): ?>
                      · <span style="color:#D97706;font-weight:700;">★ <?= number_format((float)$apt['rating_score'], 1) ?></span>
                    <?php endif; ?>
                  </div>

                  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:8px;font-size:13px;background:#F8FAFC;padding:12px;border-radius:var(--radius-md);border:1px solid #E2E8F0;">
                    <div>
                      <i class="bi bi-building text-primary" style="margin-right:6px;"></i>
                      <strong><?= htmlspecialchars($apt['hospital_name']) ?></strong>
                      <?php if ($apt['department_name']): ?> (<?= htmlspecialchars($apt['department_name']) ?>)<?php endif; ?>
                    </div>
                    <div>
                      <i class="bi bi-calendar3 text-primary" style="margin-right:6px;"></i>
                      <strong><?= date('D, d M Y', strtotime($apt['appointment_date'])) ?></strong> · <?= htmlspecialchars($apt['time_slot']) ?>
                    </div>
                    <div>
                      <i class="bi bi-cash-stack text-success" style="margin-right:6px;"></i>
                      Consultation Fee: <strong>৳<?= number_format((float)$apt['consultation_fee']) ?></strong>
                      <span style="font-size:11px;padding:1px 6px;border-radius:4px;background:<?= $apt['payment_status'] === 'PAID' ? '#DCFCE7;color:#166534' : '#FEF3C7;color:#92400E' ?>;">
                        <?= $apt['payment_status'] === 'PAID' ? 'PAID' : 'PAY AT COUNTER' ?>
                      </span>
                    </div>
                    <?php if (!empty($apt['problem_description'])): ?>
                      <div style="grid-column: 1 / -1;">
                        <i class="bi bi-chat-left-dots" style="margin-right:6px;color:var(--mc-text-muted);"></i>
                        <em>"<?= htmlspecialchars($apt['problem_description']) ?>"</em>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Action Controls -->
                <div style="display:flex;flex-direction:column;gap:8px;align-self:center;min-width:140px;">
                  <button type="button" class="btn btn-secondary btn-sm" onclick="printSlip(<?= htmlspecialchars(json_encode($apt)) ?>)">
                    <i class="bi bi-printer"></i> Print Token Slip
                  </button>

                  <?php if (in_array($status, ['CONFIRMED', 'WAITING'])): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment serial?');">
                      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                      <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                      <button type="submit" name="cancel_appointment" class="btn btn-outline-danger btn-sm" style="width:100%;">
                        <i class="bi bi-x-circle"></i> Cancel Serial
                      </button>
                    </form>
                  <?php endif; ?>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<!-- Print Token Modal / Div -->
<div id="print-slip" style="display:none;background:#fff;padding:30px;max-width:450px;margin:0 auto;border:2px dashed #0284c7;border-radius:12px;font-family:Inter,sans-serif;color:#1e293b;">
  <div style="text-align:center;border-bottom:1px solid #e2e8f0;padding-bottom:12px;margin-bottom:14px;">
    <h2 style="font-size:22px;font-weight:800;color:#0284c7;margin:0 0 4px;">MedCore Health Network</h2>
    <div style="font-size:12px;color:#64748b;">Universal Healthcare Serial Slip</div>
  </div>
  <div style="background:#0284c7;color:#fff;border-radius:8px;padding:14px;text-align:center;margin-bottom:16px;">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;">QUEUE SERIAL NUMBER</div>
    <div id="slip-token" style="font-family:'JetBrains Mono',monospace;font-size:38px;font-weight:800;line-height:1.2;">#01</div>
    <div id="slip-priority" style="font-size:11px;font-weight:700;margin-top:4px;">ROUTINE VISIT</div>
  </div>
  <table style="width:100%;font-size:13px;border-collapse:collapse;margin-bottom:16px;">
    <tr><td style="padding:4px 0;color:#64748b;">UID:</td><td id="slip-uid" style="padding:4px 0;font-weight:700;font-family:monospace;">APT-0000</td></tr>
    <tr><td style="padding:4px 0;color:#64748b;">Patient:</td><td id="slip-patient" style="padding:4px 0;font-weight:700;"><?= htmlspecialchars($patient['full_name']) ?> (<?= htmlspecialchars($patient['patient_uid']) ?>)</td></tr>
    <tr><td style="padding:4px 0;color:#64748b;">Doctor:</td><td id="slip-doctor" style="padding:4px 0;font-weight:700;">Dr. Name</td></tr>
    <tr><td style="padding:4px 0;color:#64748b;">Hospital:</td><td id="slip-hospital" style="padding:4px 0;font-weight:700;">Hospital Name</td></tr>
    <tr><td style="padding:4px 0;color:#64748b;">Date &amp; Time:</td><td id="slip-time" style="padding:4px 0;font-weight:700;">Date Slot</td></tr>
    <tr><td style="padding:4px 0;color:#64748b;">Fee:</td><td id="slip-fee" style="padding:4px 0;font-weight:700;">৳0</td></tr>
  </table>
  <div style="background:#F1F5F9;padding:10px;border-radius:6px;font-size:11px;color:#475569;text-align:center;">
    অনুগ্রহ করে নির্ধারিত সময়ের ১৫ মিনিট পূর্বে হাসপাতালের অভ্যর্থনা (Reception) ডেস্কে টোকেনটি প্রদর্শন করুন।
  </div>
</div>

<script>
function printSlip(apt) {
  document.getElementById('slip-token').textContent = '#' + String(apt.serial_number).padStart(2, '0');
  document.getElementById('slip-priority').textContent = apt.priority === 'EMERGENCY' ? '⚠ STAT EMERGENCY' : 'ROUTINE SERIAL';
  document.getElementById('slip-uid').textContent = apt.appointment_uid;
  document.getElementById('slip-doctor').textContent = 'Dr. ' + apt.doctor_name + ' (' + apt.specialization + ')';
  document.getElementById('slip-hospital').textContent = apt.hospital_name;
  document.getElementById('slip-time').textContent = apt.appointment_date + ' (' + apt.time_slot + ')';
  document.getElementById('slip-fee').textContent = '৳' + Number(apt.consultation_fee).toLocaleString();
  window.print();
}

// Mobile sidebar toggle
const hamburger = document.getElementById('hamburger-btn');
const sidebar = document.getElementById('sidebar');
if (hamburger && sidebar) {
  hamburger.addEventListener('click', () => {
    sidebar.classList.toggle('open');
  });
}
</script>

</body>
</html>

