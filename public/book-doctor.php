<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

$db = getDB();

// Handle AJAX or POST Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_appointment') {
    header('Content-Type: application/json; charset=utf-8');

    // 1. Patient Authentication check
    $isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'PATIENT';
    $patientId = (int)($_SESSION['patient_id'] ?? 0);

    if (!$isLoggedIn) {
        $loginId = trim($_POST['login_identifier'] ?? '');
        $password = $_POST['login_password'] ?? '';

        if (empty($loginId) || empty($password)) {
            echo json_encode([
                'success' => false,
                'message' => 'Please provide your Patient Email or MedCore ID and password to confirm booking.'
            ]);
            exit;
        }

        // Authenticate patient
        $stmtUser = $db->prepare("
            SELECT u.*, p.id AS p_id, p.full_name, p.patient_uid, p.phone AS p_phone
            FROM users u
            JOIN patients p ON p.user_id = u.id
            WHERE u.role = 'PATIENT' AND (u.email = ? OR p.patient_uid = ? OR u.phone = ?)
            LIMIT 1
        ");
        $stmtUser->execute([$loginId, $loginId, $loginId]);
        $patientUser = $stmtUser->fetch();

        if (!$patientUser || !password_verify($password, $patientUser['password_hash'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid patient credentials. Please check your Email/ID and password.'
            ]);
            exit;
        }

        // Log the patient in
        $_SESSION['user_id']     = (int)$patientUser['id'];
        $_SESSION['role']        = 'PATIENT';
        $_SESSION['patient_id']  = (int)$patientUser['p_id'];
        $_SESSION['name']        = $patientUser['full_name'];
        $_SESSION['patient_uid'] = $patientUser['patient_uid'];
        $_SESSION['email']       = $patientUser['email'];

        $patientId = (int)$patientUser['p_id'];
    }

    // 2. Validate booking inputs
    $doctorId       = (int)($_POST['doctor_id'] ?? 0);
    $hospitalId     = (int)($_POST['hospital_id'] ?? 0);
    $departmentId   = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $dateInput      = trim($_POST['appointment_date'] ?? date('Y-m-d'));
    $timeSlot       = trim($_POST['time_slot'] ?? 'Evening (05:00 PM - 08:30 PM)');
    $priority       = in_array($_POST['priority'] ?? '', ['ROUTINE','URGENT','EMERGENCY']) ? $_POST['priority'] : 'ROUTINE';
    $problemDesc    = trim($_POST['problem_description'] ?? '');

    if ($doctorId <= 0 || $hospitalId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid doctor or hospital selection.']);
        exit;
    }

    // Verify doctor and hospital
    $stmtDoc = $db->prepare("
        SELECT d.*, h.name AS hospital_name, dh.department_id AS dh_dept
        FROM doctors d
        JOIN doctor_hospitals dh ON dh.doctor_id = d.id AND dh.hospital_id = ?
        JOIN hospitals h ON h.id = dh.hospital_id
        WHERE d.id = ? AND dh.status = 'APPROVED'
        LIMIT 1
    ");
    $stmtDoc->execute([$hospitalId, $doctorId]);
    $doc = $stmtDoc->fetch();

    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Doctor is not currently affiliated with this hospital.']);
        exit;
    }

    if (!$departmentId && !empty($doc['dh_dept'])) {
        $departmentId = (int)$doc['dh_dept'];
    }

    $fee = (float)($doc['consultation_fee'] ?? 800.0);

    // Compute next serial number for this doctor and date
    $stmtSerial = $db->prepare("
        SELECT COALESCE(MAX(serial_number), 0) + 1
        FROM appointments
        WHERE doctor_id = ? AND hospital_id = ? AND appointment_date = ?
    ");
    $stmtSerial->execute([$doctorId, $hospitalId, $dateInput]);
    $serialNumber = (int)$stmtSerial->fetchColumn();

    // Generate appointment UID
    $aptUid = 'APT-' . date('Ymd', strtotime($dateInput)) . '-' . strtoupper(substr(uniqid(), -4));

    try {
        $db->beginTransaction();

        $stmtIns = $db->prepare("
            INSERT INTO appointments
                (appointment_uid, serial_number, patient_id, doctor_id, hospital_id, department_id,
                 appointment_date, time_slot, priority, problem_description, consultation_fee, payment_status, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UNPAID', 'CONFIRMED')
        ");
        $stmtIns->execute([
            $aptUid, $serialNumber, $patientId, $doctorId, $hospitalId, $departmentId,
            $dateInput, $timeSlot, $priority, $problemDesc, $fee
        ]);
        $aptId = $db->lastInsertId();

        // Create notification for patient
        $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, reference_type, reference_id, action_url)
            SELECT u.id, 'APPOINTMENT_CONFIRMED', 'Doctor Serial Confirmed',
                   CONCAT('Serial #', ?, ' confirmed with Dr. ', ?, ' at ', ?, ' on ', ?),
                   'appointment', ?, ?
            FROM patients p JOIN users u ON u.id = p.user_id
            WHERE p.id = ?
        ")->execute([
            $serialNumber, $doc['full_name'], $doc['hospital_name'], date('d M Y', strtotime($dateInput)),
            $aptId, APP_URL . '/patient/appointments.php',
            $patientId
        ]);

        // Audit Log
        AuditService::log('BOOK_APPOINTMENT', [
            'user_id'     => $_SESSION['user_id'],
            'patient_id'  => $patientId,
            'doctor_id'   => $doctorId,
            'hospital_id' => $hospitalId,
            'metadata'    => ['uid' => $aptUid, 'serial' => $serialNumber, 'date' => $dateInput, 'priority' => $priority]
        ]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Appointment booked successfully!',
            'appointment' => [
                'uid'           => $aptUid,
                'serial_number' => $serialNumber,
                'doctor_name'   => $doc['full_name'],
                'specialization'=> $doc['specialization'],
                'hospital_name' => $doc['hospital_name'],
                'date'          => date('l, d M Y', strtotime($dateInput)),
                'time_slot'     => $timeSlot,
                'priority'      => $priority,
                'fee'           => $fee,
                'patient_name'  => $_SESSION['name'] ?? 'Patient'
            ]
        ]);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error booking appointment: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// Read & Filter Available Doctors
// -------------------------------------------------------------
$search       = trim($_GET['q'] ?? '');
$category     = trim($_GET['category'] ?? 'all');
$hospitalFilter = (int)($_GET['hospital'] ?? 0);
$sortBy       = trim($_GET['sort'] ?? 'rating');

// Fetch all available specialties for category pills
$stmtCats = $db->query("
    SELECT DISTINCT specialization 
    FROM doctors 
    WHERE verification_status = 'VERIFIED' AND specialization IS NOT NULL AND specialization != ''
    ORDER BY specialization ASC
");
$allCategories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

// Fetch all hospitals for filter dropdown
$stmtHosps = $db->query("SELECT id, name, city FROM hospitals ORDER BY name ASC");
$allHospitals = $stmtHosps->fetchAll();

// Build query for verified doctors and their hospital affiliations
$whereClauses = ["d.verification_status = 'VERIFIED'", "dh.status = 'APPROVED'"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(d.full_name LIKE ? OR d.specialization LIKE ? OR d.qualification LIKE ? OR h.name LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

if ($category !== '' && $category !== 'all') {
    $whereClauses[] = "d.specialization = ?";
    $params[] = $category;
}

if ($hospitalFilter > 0) {
    $whereClauses[] = "dh.hospital_id = ?";
    $params[] = $hospitalFilter;
}

$whereSql = implode(' AND ', $whereClauses);

$orderSql = "d.rating_score DESC, d.rating_count DESC, d.experience_years DESC";
if ($sortBy === 'experience') {
    $orderSql = "d.experience_years DESC, d.rating_score DESC";
} elseif ($sortBy === 'fee_low') {
    $orderSql = "d.consultation_fee ASC, d.rating_score DESC";
} elseif ($sortBy === 'name') {
    $orderSql = "d.full_name ASC";
}

$stmtDocs = $db->prepare("
    SELECT d.*,
           dh.hospital_id, dh.department_id, dh.designation AS hospital_designation,
           dh.employment_type,
           h.name AS hospital_name, h.city AS hospital_city, h.address AS hospital_address,
           dept.name AS department_name
    FROM doctors d
    JOIN doctor_hospitals dh ON dh.doctor_id = d.id
    JOIN hospitals h ON dh.hospital_id = h.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE {$whereSql}
    ORDER BY {$orderSql}
");
$stmtDocs->execute($params);
$doctors = $stmtDocs->fetchAll();

$isPatientLoggedIn = isset($_SESSION['role']) && $_SESSION['role'] === 'PATIENT';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Online Doctor Appointment &amp; Serial Booking — MedCore</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
  <style>
    :root {
      --mc-teal: #0891b2;
      --mc-teal-dark: #0e7490;
    }
    body.booking-page {
      background: #F8FAFC;
      color: #0F172A;
      font-family: 'Inter', sans-serif;
    }
    .booking-hero {
      background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #0F766E 100%);
      color: #fff;
      padding: 50px 0 45px;
      position: relative;
      overflow: hidden;
    }
    .booking-hero::before {
      content: '';
      position: absolute;
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(14,165,233,0.18) 0%, transparent 70%);
      top: -150px; right: -100px;
      pointer-events: none;
    }
    .hero-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.22);
      backdrop-filter: blur(10px);
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      color: #5EEAD4;
      margin-bottom: 16px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }
    .filter-card-bar {
      background: #ffffff;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 20px 24px;
      margin-top: -28px;
      position: relative;
      z-index: 10;
      box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);
    }
    .cat-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
      background: #F1F5F9;
      color: #475569;
      text-decoration: none;
      transition: all 0.2s;
      white-space: nowrap;
      border: 1px solid transparent;
    }
    .cat-pill:hover {
      background: #E2E8F0;
      color: #0F172A;
    }
    .cat-pill.active {
      background: #0F766E;
      color: #ffffff;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(15,118,110,0.25);
    }
    /* Doctor Card */
    .doc-card {
      background: #ffffff;
      border: 1px solid #E2E8F0;
      border-radius: 18px;
      padding: 24px;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      flex-direction: column;
      height: 100%;
      position: relative;
      overflow: hidden;
    }
    .doc-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 32px -8px rgba(15,23,42,0.1);
      border-color: #0F766E;
    }
    .doc-avatar {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      background: linear-gradient(135deg, #0F766E, #0284C7);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      font-weight: 800;
      flex-shrink: 0;
      position: relative;
    }
    .online-indicator {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 14px;
      height: 14px;
      background: #10B981;
      border: 2px solid #ffffff;
      border-radius: 50%;
    }
    .rating-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: #FEF3C7;
      border: 1px solid #FDE68A;
      color: #92400E;
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 800;
    }
    .top-badge {
      background: #ECFDF5;
      color: #065F46;
      border: 1px solid #A7F3D0;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 7px;
      border-radius: 999px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .info-strip {
      background: #F8FAFC;
      border: 1px solid #F1F5F9;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 12px;
      margin: 14px 0;
    }
    .btn-book-doc {
      background: linear-gradient(135deg, #0F766E, #0D9488);
      color: #ffffff;
      font-weight: 700;
      padding: 10px 18px;
      border-radius: 10px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      width: 100%;
      font-size: 14px;
    }
    .btn-book-doc:hover {
      background: linear-gradient(135deg, #0D9488, #115E59);
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(15,118,110,0.3);
    }
    /* Modal Styles */
    .booking-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.65);
      backdrop-filter: blur(6px);
      z-index: 1000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .booking-modal-box {
      background: #ffffff;
      border-radius: 20px;
      max-width: 600px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
      position: relative;
    }
  </style>
</head>
<body class="booking-page">

<!-- NAVBAR -->
<nav class="navbar" style="background:#ffffff; border-bottom:1px solid #E2E8F0; position:sticky; top:0; z-index:100;">
  <div class="navbar-inner" style="max-width:1200px; margin:0 auto; padding:0 20px; display:flex; justify-content:space-between; align-items:center; height:68px;">
    <a href="<?= APP_URL ?>/" class="navbar-logo" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
      <div class="logo-icon" style="background:linear-gradient(135deg, #0F766E, #0284C7); width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff;">
        <i class="bi bi-heart-pulse-fill" style="font-size:16px;"></i>
      </div>
      <div>
        <span style="font-weight:900; font-size:1.25rem; color:#0F172A; font-family:'Plus Jakarta Sans',sans-serif; letter-spacing:-0.5px;">MedCore</span>
        <span style="font-size:10px; display:block; color:#0F766E; font-weight:700; margin-top:-3px; letter-spacing:0.8px;">ONLINE DOCTOR BOOKING</span>
      </div>
    </a>

    <div style="display:flex; align-items:center; gap:12px;">
      <a href="<?= APP_URL ?>/" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        <i class="bi bi-arrow-left"></i> Home
      </a>
      <?php if ($isPatientLoggedIn): ?>
        <a href="<?= APP_URL ?>/patient/dashboard.php" class="btn btn-sm btn-primary" style="background:#0F766E; border-color:#0F766E; border-radius:8px;">
          <i class="bi bi-person-fill"></i> <?= htmlspecialchars($_SESSION['name'] ?? 'My Portal') ?>
        </a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/patient/login.php" class="btn btn-sm btn-outline-primary" style="border-radius:8px;">
          <i class="bi bi-box-arrow-in-right"></i> Patient Login
        </a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- HERO HEADER -->
<section class="booking-hero">
  <div class="container" style="max-width:1200px; margin:0 auto; padding:0 20px;">
    <div class="hero-badge-pill">
      <i class="bi bi-stars"></i> Instant Digital Queue &amp; Token System
    </div>
    <h1 style="font-family:'Plus Jakarta Sans', sans-serif; font-size:clamp(1.8rem, 3.5vw, 2.75rem); font-weight:900; line-height:1.2; margin-bottom:12px;">
      Find Top Specialist Doctors &amp;<br>
      <span style="color:#2DD4BF;">Book Your Consultation Serial Instantly</span>
    </h1>
    <p style="font-size:1.05rem; color:#CBD5E1; max-width:680px; margin-bottom:20px; line-height:1.6;">
      Choose from verified specialists across accredited hospitals in Bangladesh. Browse real patient ratings, transparent fees, and get guaranteed same-day queue tokens.
    </p>
  </div>
</section>

<!-- MAIN CONTENT & FILTERS -->
<main class="container" style="max-width:1200px; margin:0 auto; padding:0 20px 60px;">

  <!-- Filter Card -->
  <div class="filter-card-bar">
    <form method="GET" class="row g-3 align-items-center">
      <!-- Search Input -->
      <div class="col-md-5">
        <label class="form-label" style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase;">Search Doctor / Specialty / Hospital</label>
        <div style="position:relative;">
          <i class="bi bi-search" style="position:absolute; left:14px; top:12px; color:#94A3B8;"></i>
          <input type="text" name="q" class="form-control" placeholder="e.g. Dr. Farhana, Medicine, Cardiology, ABC..."
                 value="<?= htmlspecialchars($search) ?>" style="padding-left:38px; border-radius:10px;">
        </div>
      </div>

      <!-- Hospital Filter -->
      <div class="col-md-4">
        <label class="form-label" style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase;">Hospital / Chamber</label>
        <select name="hospital" class="form-control" style="border-radius:10px;" onchange="this.form.submit()">
          <option value="0">🏥 All Accredited Hospitals</option>
          <?php foreach ($allHospitals as $hosp): ?>
            <option value="<?= $hosp['id'] ?>" <?= $hospitalFilter == $hosp['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($hosp['name']) ?> (<?= htmlspecialchars($hosp['city']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Sort By -->
      <div class="col-md-3">
        <label class="form-label" style="font-size:11px; font-weight:700; color:#64748B; text-transform:uppercase;">Sort By</label>
        <select name="sort" class="form-control" style="border-radius:10px;" onchange="this.form.submit()">
          <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>⭐ Top Rated (সর্বোচ্চ রেটিং)</option>
          <option value="experience" <?= $sortBy === 'experience' ? 'selected' : '' ?>>🎓 Experience (বেশি অভিজ্ঞ)</option>
          <option value="fee_low" <?= $sortBy === 'fee_low' ? 'selected' : '' ?>>৳ Fee (কম থেকে বেশি)</option>
          <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>🔤 Alphabetical (A-Z)</option>
        </select>
      </div>

      <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
    </form>

    <!-- Category Pills -->
    <div style="margin-top:20px; padding-top:16px; border-top:1px solid #F1F5F9; display:flex; align-items:center; gap:8px; overflow-x:auto; padding-bottom:6px;">
      <span style="font-size:12px; font-weight:700; color:#64748B; margin-right:4px; text-transform:uppercase; letter-spacing:0.5px;">Specialty:</span>
      <a href="?category=all&hospital=<?= $hospitalFilter ?>&sort=<?= $sortBy ?>&q=<?= urlencode($search) ?>"
         class="cat-pill <?= $category === 'all' ? 'active' : '' ?>">
        All Specialists
      </a>
      <?php foreach ($allCategories as $cat): ?>
        <a href="?category=<?= urlencode($cat) ?>&hospital=<?= $hospitalFilter ?>&sort=<?= $sortBy ?>&q=<?= urlencode($search) ?>"
           class="cat-pill <?= $category === $cat ? 'active' : '' ?>">
          <?= htmlspecialchars($cat) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Header strip with count -->
  <div style="display:flex; justify-content:space-between; align-items:center; margin:32px 0 20px; flex-wrap:wrap; gap:10px;">
    <div>
      <h2 style="font-size:1.35rem; font-weight:800; color:#0F172A; margin:0;">
        Available Specialist Doctors <span style="font-size:0.9rem; color:#64748B; font-weight:600;">(<?= count($doctors) ?> doctors found)</span>
      </h2>
      <div style="font-size:13px; color:#64748B; margin-top:2px;">Showing confirmed schedules and instant queue tokens</div>
    </div>
    <?php if ($category !== 'all' || $hospitalFilter > 0 || $search !== ''): ?>
      <a href="<?= APP_URL ?>/book-doctor.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
        <i class="bi bi-x-circle"></i> Clear Filters
      </a>
    <?php endif; ?>
  </div>

  <!-- DOCTORS GRID -->
  <?php if (empty($doctors)): ?>
    <div style="background:#ffffff; border:1px solid #E2E8F0; border-radius:16px; padding:60px 20px; text-align:center; color:#64748B;">
      <i class="bi bi-person-x" style="font-size:3.5rem; color:#CBD5E1;"></i>
      <h3 style="font-size:1.25rem; font-weight:800; color:#1E293B; margin-top:12px;">No matching doctors found</h3>
      <p style="max-width:400px; margin:6px auto 18px; font-size:14px;">Try selecting another specialty or clear your search term to see all available specialists.</p>
      <a href="<?= APP_URL ?>/book-doctor.php" class="btn btn-sm btn-primary" style="background:#0F766E; border-color:#0F766E;">View All Doctors</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($doctors as $doc): ?>
        <div class="col-md-6 col-lg-4">
          <div class="doc-card">
            <!-- Top strip: Avatar + Name + Rating -->
            <div style="display:flex; gap:16px; align-items:flex-start;">
              <div class="doc-avatar">
                <?= strtoupper(substr($doc['full_name'], 4, 1) ?: substr($doc['full_name'], 0, 1)) ?>
                <span class="online-indicator" title="Available for booking"></span>
              </div>
              <div style="flex:1; min-width:0;">
                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                  <h3 style="font-size:1.1rem; font-weight:800; color:#0F172A; margin:0;">
                    <?= htmlspecialchars($doc['full_name']) ?>
                  </h3>
                  <?php if (!empty($doc['is_top_rated'])): ?>
                    <span class="top-badge"><i class="bi bi-patch-check-fill"></i> Top Rated</span>
                  <?php endif; ?>
                </div>

                <div style="font-size:12px; font-weight:700; color:#0F766E; margin-top:3px;">
                  <?= htmlspecialchars($doc['specialization'] ?: 'General Physician') ?>
                </div>
                <div style="font-size:11.5px; color:#64748B;">
                  <?= htmlspecialchars($doc['qualification'] ?: 'MBBS') ?>
                </div>

                <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                  <span class="rating-badge">
                    <i class="bi bi-star-fill text-warning"></i> <?= number_format($doc['rating_score'] ?? 4.8, 1) ?>
                  </span>
                  <span style="font-size:11px; color:#64748B;">(<?= $doc['rating_count'] ?? 50 ?> reviews)</span>
                  <span style="font-size:11px; color:#94A3B8;">&bull;</span>
                  <span style="font-size:11px; font-weight:600; color:#334155;"><?= $doc['experience_years'] ?? 5 ?>+ yrs exp</span>
                </div>
              </div>
            </div>

            <!-- Hospital Affiliation -->
            <div class="info-strip">
              <div style="display:flex; align-items:center; gap:6px; color:#1E293B; font-weight:700; font-size:12.5px;">
                <i class="bi bi-building text-primary"></i> <?= htmlspecialchars($doc['hospital_name']) ?>
              </div>
              <div style="font-size:11.5px; color:#64748B; margin-top:2px;">
                <?= htmlspecialchars($doc['hospital_designation'] ?: ($doc['designation'] ?: 'Consultant')) ?>
                <?php if ($doc['department_name']): ?>
                  &bull; <?= htmlspecialchars($doc['department_name']) ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Timing & Fee -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; font-size:12px; padding:0 4px;">
              <div>
                <span style="color:#64748B; display:block; font-size:10.5px; text-transform:uppercase; font-weight:700;">Visiting Hours</span>
                <strong style="color:#0F172A;"><?= htmlspecialchars($doc['available_time'] ?? '05:00 PM - 09:00 PM') ?></strong>
                <div style="font-size:11px; color:#10B981;"><i class="bi bi-calendar2-check"></i> <?= htmlspecialchars($doc['available_days'] ?? 'Sat - Thu') ?></div>
              </div>
              <div style="text-align:right;">
                <span style="color:#64748B; display:block; font-size:10.5px; text-transform:uppercase; font-weight:700;">Consultation Fee</span>
                <span style="font-size:1.25rem; font-weight:900; color:#0F766E;">৳<?= number_format($doc['consultation_fee'] ?? 800) ?></span>
              </div>
            </div>

            <!-- Features -->
            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; font-size:11px;">
              <span class="badge" style="background:#EFF6FF; color:#1E40AF; padding:4px 8px;">⚡ Digital Serial</span>
              <span class="badge" style="background:#F0FDF4; color:#166534; padding:4px 8px;">🛡️ BMDC Verified</span>
              <span class="badge" style="background:#FAF5FF; color:#6B21A8; padding:4px 8px;">📋 Auto EMR Sync</span>
            </div>

            <!-- CTA Button -->
            <div style="margin-top:auto;">
              <button type="button" class="btn-book-doc"
                      onclick="openBookingModal(<?= htmlspecialchars(json_encode([
                          'id' => $doc['id'],
                          'name' => $doc['full_name'],
                          'specialization' => $doc['specialization'],
                          'qualification' => $doc['qualification'],
                          'hospital_id' => $doc['hospital_id'],
                          'hospital_name' => $doc['hospital_name'],
                          'department_id' => $doc['department_id'],
                          'fee' => $doc['consultation_fee'] ?? 800,
                          'time' => $doc['available_time'] ?? '05:00 PM - 09:00 PM'
                      ])) ?>)">
                <i class="bi bi-calendar-plus-fill"></i> Book Appointment (সিরিয়াল নিন)
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<!-- =========================================================================
     BOOKING MODAL
     ========================================================================= -->
<div class="booking-modal-overlay" id="booking-modal-overlay" onclick="handleOverlayClick(event)">
  <div class="booking-modal-box" id="booking-modal-box">
    
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg, #0F766E, #0D9488); color:#fff; padding:20px 24px; border-radius:20px 20px 0 0; display:flex; justify-content:space-between; align-items:center;">
      <div style="display:flex; align-items:center; gap:12px;">
        <span style="font-size:24px;">🩺</span>
        <div>
          <h3 style="font-size:1.15rem; font-weight:800; margin:0;" id="m-doctor-name">Doctor Appointment</h3>
          <div style="font-size:12px; color:#CCFBF1;" id="m-doctor-spec">Consultation Booking</div>
        </div>
      </div>
      <button type="button" onclick="closeBookingModal()" style="background:rgba(255,255,255,0.2); border:none; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer;">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <!-- Booking Form Container -->
    <div id="booking-form-wrap" style="padding:24px;">
      
      <!-- Doctor Snapshot -->
      <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:12px 16px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
          <div style="font-size:11px; text-transform:uppercase; color:#64748B; font-weight:700;">Chamber / Hospital</div>
          <strong style="color:#0F172A; font-size:13px;" id="m-hospital-name">ABC General Hospital</strong>
        </div>
        <div style="text-align:right;">
          <div style="font-size:11px; text-transform:uppercase; color:#64748B; font-weight:700;">Consultation Fee</div>
          <strong style="color:#0F766E; font-size:15px;" id="m-fee">৳800</strong>
        </div>
      </div>

      <form id="booking-form" onsubmit="submitBooking(event)">
        <input type="hidden" name="action" value="book_appointment">
        <input type="hidden" name="doctor_id" id="f-doctor-id">
        <input type="hidden" name="hospital_id" id="f-hospital-id">
        <input type="hidden" name="department_id" id="f-department-id">

        <!-- Appointment Date & Slot -->
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label" style="font-size:12px; font-weight:700; color:#334155;">Select Date (তারিখ) <span class="text-danger">*</span></label>
            <input type="date" name="appointment_date" class="form-control" id="f-appointment-date"
                   value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required style="border-radius:10px;">
          </div>
          <div class="col-md-6">
            <label class="form-label" style="font-size:12px; font-weight:700; color:#334155;">Time Slot (শিফট)</label>
            <select name="time_slot" class="form-control" style="border-radius:10px;">
              <option value="Evening (05:00 PM - 08:30 PM)">Evening (05:00 PM - 08:30 PM)</option>
              <option value="Morning (10:00 AM - 01:00 PM)">Morning (10:00 AM - 01:00 PM)</option>
              <option value="Afternoon (02:00 PM - 04:30 PM)">Afternoon (02:00 PM - 04:30 PM)</option>
            </select>
          </div>
        </div>

        <!-- Priority / Emergency Option -->
        <div class="mb-3">
          <label class="form-label" style="font-size:12px; font-weight:700; color:#334155;">Consultation Priority (রোগীর ধরন)</label>
          <div style="display:flex; gap:12px;">
            <label style="flex:1; border:1.5px solid #CBD5E1; border-radius:10px; padding:10px 14px; cursor:pointer; display:flex; align-items:center; gap:8px;" id="priority-routine-lbl">
              <input type="radio" name="priority" value="ROUTINE" checked onchange="updatePriorityUI()">
              <div>
                <strong style="font-size:13px; color:#0F172A; display:block;">Normal Serial</strong>
                <span style="font-size:11px; color:#64748B;">Regular Consultation</span>
              </div>
            </label>
            <label style="flex:1; border:1.5px solid #FCA5A5; border-radius:10px; padding:10px 14px; cursor:pointer; display:flex; align-items:center; gap:8px; background:#FEF2F2;" id="priority-emergency-lbl">
              <input type="radio" name="priority" value="EMERGENCY" onchange="updatePriorityUI()">
              <div>
                <strong style="font-size:13px; color:#DC2626; display:block;">🚨 Emergency / STAT</strong>
                <span style="font-size:11px; color:#991B1B;">Fast Track Immediate</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Problem / Symptoms -->
        <div class="mb-3">
          <label class="form-label" style="font-size:12px; font-weight:700; color:#334155;">Chief Problem / Symptoms (সমস্যা বা লক্ষণ)</label>
          <textarea name="problem_description" rows="2" class="form-control" placeholder="e.g. Severe headache and high fever for 2 days, or regular diabetic follow-up" style="border-radius:10px;"></textarea>
        </div>

        <!-- PATIENT AUTHENTICATION SECTION -->
        <?php if ($isPatientLoggedIn): ?>
          <div style="background:#F0FDF4; border:1px solid #BBF7D0; border-radius:12px; padding:12px 16px; margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:10px;">
              <i class="bi bi-check-circle-fill text-success" style="font-size:18px;"></i>
              <div>
                <div style="font-size:12px; color:#166534; font-weight:700;">Logged in as: <?= htmlspecialchars($_SESSION['name'] ?? 'Patient') ?></div>
                <div style="font-size:11px; color:#15803D;">MedCore Patient ID: <strong><?= htmlspecialchars($_SESSION['patient_uid'] ?? '') ?></strong> &bull; Token will automatically link to your records.</div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:14px; padding:16px; margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
              <span style="font-size:12px; font-weight:800; color:#1E40AF; text-transform:uppercase; letter-spacing:0.5px;">
                <i class="bi bi-person-lock"></i> Patient Login Required
              </span>
              <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:11px; padding:2px 8px;" onclick="fillDemoPatient()">
                ⚡ Demo Patient (Rahat)
              </button>
            </div>
            <p style="font-size:11.5px; color:#3B82F6; margin-bottom:12px;">
              To attach this serial ticket to your verified MedCore Health Record, enter your Patient credentials:
            </p>

            <div class="row g-2">
              <div class="col-md-6">
                <input type="text" name="login_identifier" id="f-login-id" class="form-control form-control-sm"
                       placeholder="Email or Patient UID (e.g. rahat@example.com)" required style="border-radius:8px;">
              </div>
              <div class="col-md-6">
                <input type="password" name="login_password" id="f-login-pass" class="form-control form-control-sm"
                       placeholder="Password (e.g. Demo123!)" required style="border-radius:8px;">
              </div>
            </div>
            <div style="font-size:11px; color:#64748B; margin-top:8px;">
              Don't have an account? <a href="<?= APP_URL ?>/patient/register.php" target="_blank" style="color:#0284C7; font-weight:600;">Create free account</a>
            </div>
          </div>
        <?php endif; ?>

        <div id="booking-error-box" style="display:none; margin-bottom:15px;" class="alert alert-danger py-2" role="alert"></div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary w-100 btn-lg" id="btn-confirm-booking"
                style="background:linear-gradient(135deg, #0F766E, #0D9488); border:none; border-radius:12px; font-weight:800; padding:12px;">
          <i class="bi bi-ticket-perforated-fill"></i> Confirm Serial &amp; Generate Token (বুকিং নিশ্চিত করুন)
        </button>
      </form>
    </div>

    <!-- Confirmation Ticket View (Shown after booking) -->
    <div id="booking-success-wrap" style="display:none; padding:32px 24px; text-align:center;">
      <div style="width:64px; height:64px; background:#DCFCE7; color:#15803D; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; margin:0 auto 16px;">
        <i class="bi bi-check-lg"></i>
      </div>
      <h3 style="font-size:1.35rem; font-weight:800; color:#0F172A; margin:0;">Serial Booked Successfully!</h3>
      <p style="font-size:13px; color:#64748B; margin-top:4px;">আপনার ডাক্তারের সিরিয়াল নিশ্চিত হয়েছে এবং হাসপাতালের পোর্টালে যুক্ত হয়েছে।</p>

      <!-- Printable Ticket Card -->
      <div style="background:#F8FAFC; border:2px dashed #0F766E; border-radius:16px; padding:20px; margin:20px auto; max-width:440px; text-align:left;" id="printable-ticket">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E2E8F0; padding-bottom:12px; margin-bottom:14px;">
          <div>
            <div style="font-size:10px; font-weight:700; color:#0F766E; text-transform:uppercase; letter-spacing:1px;">Doctor Consultation Token</div>
            <div style="font-family:monospace; font-size:15px; font-weight:800; color:#0F172A;" id="t-apt-uid">APT-0000</div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:10px; color:#64748B; text-transform:uppercase;">Queue Serial (সিরিয়াল নং)</div>
            <div style="font-size:1.85rem; font-weight:900; color:#0F766E; line-height:1;" id="t-serial-no">#01</div>
          </div>
        </div>

        <div style="font-size:13px; line-height:1.7; color:#334155;">
          <div>Doctor: <strong style="color:#0F172A;" id="t-doc-name">Dr. Name</strong> (<span id="t-doc-spec">Specialty</span>)</div>
          <div>Hospital: <strong style="color:#0F172A;" id="t-hosp-name">Hospital Name</strong></div>
          <div>Date: <strong style="color:#0F172A;" id="t-date">Today</strong> &bull; <span id="t-slot">Evening</span></div>
          <div>Patient: <strong style="color:#0F172A;" id="t-patient-name">Patient</strong></div>
          <div>Consultation Fee: <strong style="color:#0F766E;" id="t-fee">৳800</strong> (Pay at Hospital Counter)</div>
        </div>

        <div style="margin-top:14px; padding-top:10px; border-top:1px solid #E2E8F0; font-size:11.5px; color:#0F766E;">
          <i class="bi bi-info-circle-fill"></i> Please arrive 15 minutes before your slot and show this Serial Number at the reception.
        </div>
      </div>

      <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:20px;">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
          <i class="bi bi-printer"></i> Print Slip
        </button>
        <a href="<?= APP_URL ?>/patient/dashboard.php" class="btn btn-primary btn-sm" style="background:#0F766E; border-color:#0F766E;">
          <i class="bi bi-person-fill"></i> Go to Patient Portal
        </a>
        <button type="button" class="btn btn-light btn-sm" onclick="closeBookingModal()">Close</button>
      </div>
    </div>

  </div>
</div>

<script>
let currentDoc = null;

function openBookingModal(doc) {
  currentDoc = doc;
  document.getElementById('m-doctor-name').textContent = doc.name;
  document.getElementById('m-doctor-spec').textContent = doc.specialization + ' · ' + doc.qualification;
  document.getElementById('m-hospital-name').textContent = doc.hospital_name;
  document.getElementById('m-fee').textContent = '৳' + doc.fee;

  document.getElementById('f-doctor-id').value = doc.id;
  document.getElementById('f-hospital-id').value = doc.hospital_id;
  document.getElementById('f-department-id').value = doc.department_id || '';

  document.getElementById('booking-form-wrap').style.display = 'block';
  document.getElementById('booking-success-wrap').style.display = 'none';
  document.getElementById('booking-error-box').style.display = 'none';

  document.getElementById('booking-modal-overlay').style.display = 'flex';
}

function closeBookingModal() {
  document.getElementById('booking-modal-overlay').style.display = 'none';
}

function handleOverlayClick(e) {
  if (e.target.id === 'booking-modal-overlay') {
    closeBookingModal();
  }
}

function fillDemoPatient() {
  const idInput = document.getElementById('f-login-id');
  const passInput = document.getElementById('f-login-pass');
  if (idInput) idInput.value = 'rahat@example.com';
  if (passInput) passInput.value = 'Demo123!';
}

function updatePriorityUI() {
  const isEmergency = document.querySelector('input[name="priority"]:checked').value === 'EMERGENCY';
  const submitBtn = document.getElementById('btn-confirm-booking');
  if (isEmergency) {
    submitBtn.style.background = 'linear-gradient(135deg, #DC2626, #B91C1C)';
    submitBtn.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Confirm STAT Emergency Booking (জরুরি সিরিয়াল)';
  } else {
    submitBtn.style.background = 'linear-gradient(135deg, #0F766E, #0D9488)';
    submitBtn.innerHTML = '<i class="bi bi-ticket-perforated-fill"></i> Confirm Serial &amp; Generate Token (বুকিং নিশ্চিত করুন)';
  }
}

async function submitBooking(e) {
  e.preventDefault();
  const form = document.getElementById('booking-form');
  const btn = document.getElementById('btn-confirm-booking');
  const errBox = document.getElementById('booking-error-box');

  errBox.style.display = 'none';
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing Booking...';

  const formData = new FormData(form);

  try {
    const res = await fetch('<?= APP_URL ?>/book-doctor.php', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();

    if (data.success) {
      document.getElementById('booking-form-wrap').style.display = 'none';
      document.getElementById('booking-success-wrap').style.display = 'block';

      document.getElementById('t-apt-uid').textContent = data.appointment.uid;
      document.getElementById('t-serial-no').textContent = '#' + String(data.appointment.serial_number).padStart(2, '0');
      document.getElementById('t-doc-name').textContent = data.appointment.doctor_name;
      document.getElementById('t-doc-spec').textContent = data.appointment.specialization;
      document.getElementById('t-hosp-name').textContent = data.appointment.hospital_name;
      document.getElementById('t-date').textContent = data.appointment.date;
      document.getElementById('t-slot').textContent = data.appointment.time_slot;
      document.getElementById('t-patient-name').textContent = data.appointment.patient_name;
      document.getElementById('t-fee').textContent = '৳' + data.appointment.fee;
    } else {
      errBox.textContent = data.message || 'Failed to complete booking.';
      errBox.style.display = 'block';
    }
  } catch (err) {
    errBox.textContent = 'Network or server error. Please try again.';
    errBox.style.display = 'block';
  } finally {
    btn.disabled = false;
    updatePriorityUI();
  }
}
</script>

</body>
</html>
