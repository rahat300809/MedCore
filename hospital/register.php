<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

if (isLoggedIn('HOSPITAL_ADMIN')) {
    header('Location: ' . APP_URL . '/hospital/dashboard.php');
    exit;
}

$db = getDB();
$error   = '';
$success = false;
$applicationId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $hospitalName    = trim($_POST['hospital_name'] ?? '');
    $regNumber       = trim($_POST['registration_number'] ?? '');
    $type            = $_POST['type'] ?? 'GENERAL';
    $address         = trim($_POST['address'] ?? '');
    $city            = trim($_POST['city'] ?? '');
    $district        = trim($_POST['district'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $website         = trim($_POST['website'] ?? '');
    $adminEmail      = trim($_POST['admin_email'] ?? '');
    $adminPhone      = trim($_POST['admin_phone'] ?? '');
    $adminPassword   = $_POST['admin_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $allowedTypes = ['GENERAL','SPECIALIZED','CLINIC','DIAGNOSTIC','TEACHING','OTHER'];

    if (empty($hospitalName) || empty($regNumber) || empty($city) || empty($adminEmail) || empty($adminPassword)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid admin email address.';
    } elseif (strlen($adminPassword) < 8) {
        $error = 'Admin password must be at least 8 characters.';
    } elseif ($adminPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($type, $allowedTypes)) {
        $error = 'Invalid hospital type selected.';
    } else {
        // Check uniqueness
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$adminEmail]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            $stmt = $db->prepare("SELECT id FROM hospitals WHERE registration_number = ?");
            $stmt->execute([$regNumber]);
            if ($stmt->fetch()) {
                $error = 'A hospital with this registration number is already registered in the system.';
            } else {
                try {
                    $db->beginTransaction();

                    // Generate hospital UID
                    $lastId = (int)$db->query("SELECT MAX(id) FROM hospitals")->fetchColumn() + 1;
                    $hospitalUid = sprintf('HOSP-%06d', $lastId);

                    // Create HOSPITAL_ADMIN user (status=PENDING until hospital verified)
                    $passHash = password_hash($adminPassword, PASSWORD_BCRYPT);
                    $stmtUser = $db->prepare("
                        INSERT INTO users (role, email, phone, password_hash, status, email_verified, phone_verified)
                        VALUES ('HOSPITAL_ADMIN', ?, ?, ?, 'PENDING', 0, 0)
                    ");
                    $stmtUser->execute([$adminEmail, $adminPhone ?: null, $passHash]);
                    $userId = (int)$db->lastInsertId();

                    // Create hospital record (status=PENDING)
                    $stmtHosp = $db->prepare("
                        INSERT INTO hospitals
                            (user_id, hospital_uid, name, registration_number, type,
                             address, city, district, phone, email, website, verification_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
                    ");
                    $stmtHosp->execute([
                        $userId, $hospitalUid, $hospitalName, $regNumber, $type,
                        $address ?: null, $city, $district ?: null,
                        $phone ?: null, $adminEmail,
                        ($website && $website !== 'https://') ? $website : null
                    ]);
                    $hospitalId = (int)$db->lastInsertId();

                    $db->commit();

                    AuditService::log('HOSPITAL_APPLICATION_SUBMITTED', [
                        'user_id'     => $userId,
                        'hospital_id' => $hospitalId,
                        'metadata'    => [
                            'hospital_uid'       => $hospitalUid,
                            'hospital_name'      => $hospitalName,
                            'registration_number'=> $regNumber,
                            'city'               => $city,
                        ]
                    ]);

                    $success = true;
                    $applicationId = $hospitalUid;

                } catch (\Throwable $e) {
                    $db->rollBack();
                    $error = 'System error during registration. Please try again. (' . htmlspecialchars($e->getMessage()) . ')';
                }
            }
        }
    }
}

$csrf = generateCsrfToken();

$districts = [
    'Dhaka','Chittagong','Rajshahi','Khulna','Sylhet','Barishal','Rangpur','Mymensingh',
    'Gazipur','Narayanganj','Comilla','Noakhali','Cox\'s Bazar','Bogura','Jessore'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Apply to Join MedCore Network — Hospital Registration</title>
  <meta name="description" content="Register your hospital or clinic to join the MedCore national healthcare network. Get verified and start managing your medical staff digitally.">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    body {
      background: #F8FAFC;
      min-height: 100vh;
    }

    .register-header {
      background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 100%);
      padding: 20px 0;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .register-header-inner {
      max-width: 900px;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .page-container {
      max-width: 900px;
      margin: 40px auto 60px;
      padding: 0 24px;
    }

    .page-intro {
      text-align: center;
      margin-bottom: 40px;
    }

    .page-intro .badge-tag {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #CCFBF1;
      color: #0F766E;
      border: 1px solid #99F6E4;
      border-radius: 20px;
      padding: 5px 14px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 16px;
    }

    .page-intro h1 {
      font-size: clamp(1.6rem, 3vw, 2.2rem);
      font-weight: 800;
      color: #0F172A;
      margin: 0 0 12px;
    }

    .page-intro p {
      font-size: 15px;
      color: #64748B;
      max-width: 560px;
      margin: 0 auto;
      line-height: 1.6;
    }

    .flow-steps {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0;
      margin-bottom: 40px;
      flex-wrap: wrap;
    }

    .flow-step {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      background: white;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      color: #64748B;
    }

    .flow-step.active {
      background: #EFF6FF;
      border-color: #BFDBFE;
      color: #1E40AF;
    }

    .flow-step .step-num {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: #CBD5E1;
      color: white;
      font-size: 11px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .flow-step.active .step-num {
      background: #1E40AF;
    }

    .flow-arrow {
      color: #CBD5E1;
      font-size: 18px;
      margin: 0 4px;
    }

    .form-card {
      background: white;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 24px -8px rgba(15, 23, 42, 0.10);
      margin-bottom: 24px;
    }

    .form-card-header {
      padding: 20px 28px;
      border-bottom: 1px solid #F1F5F9;
      display: flex;
      align-items: center;
      gap: 12px;
      background: #FAFAFA;
    }

    .form-card-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
    }

    .form-card-body {
      padding: 28px;
    }

    .form-grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .form-grid-3 {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 20px;
    }

    @media (max-width: 640px) {
      .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
    }

    .submit-area {
      background: white;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 28px;
      box-shadow: 0 4px 24px -8px rgba(15, 23, 42, 0.10);
    }

    .terms-notice {
      font-size: 12px;
      color: #94A3B8;
      text-align: center;
      margin-top: 16px;
      line-height: 1.6;
    }

    .success-card {
      background: white;
      border: 1px solid #BBF7D0;
      border-radius: 16px;
      padding: 48px 40px;
      text-align: center;
      box-shadow: 0 4px 24px -8px rgba(15, 23, 42, 0.10);
    }

    .success-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, #10B981, #059669);
      color: white;
      font-size: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
      box-shadow: 0 8px 24px -4px rgba(16, 185, 129, 0.4);
    }

    .uid-box {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #F0FDF4;
      border: 1px solid #BBF7D0;
      border-radius: 8px;
      padding: 10px 20px;
      font-family: monospace;
      font-size: 1.1rem;
      font-weight: 800;
      color: #166534;
      margin: 16px 0;
    }

    .next-steps-list {
      list-style: none;
      padding: 0;
      margin: 24px 0 0;
      text-align: left;
      max-width: 440px;
      margin-left: auto;
      margin-right: auto;
    }

    .next-steps-list li {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid #F1F5F9;
      font-size: 14px;
      color: #475569;
    }

    .next-steps-list li:last-child { border-bottom: none; }

    .next-steps-list li i {
      color: #10B981;
      font-size: 16px;
      margin-top: 1px;
      flex-shrink: 0;
    }
  </style>
</head>
<body>

<!-- HEADER -->
<header class="register-header">
  <div class="register-header-inner">
    <a href="<?= APP_URL ?>/" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
      <div class="logo-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8); width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 16px; color: white;">M</div>
      <span style="font-weight: 800; font-size: 1.1rem; color: white;">MedCore</span>
      <span style="font-size: 11px; color: #94A3B8; margin-left: 4px; font-weight: 600;">Central Network</span>
    </a>
    <div style="display: flex; align-items: center; gap: 12px;">
      <a href="<?= APP_URL ?>/hospital/login.php" style="color: #94A3B8; font-size: 13px; text-decoration: none;">
        Already registered? <strong style="color: #60A5FA;">Sign In</strong>
      </a>
    </div>
  </div>
</header>

<div class="page-container">

  <?php if ($success): ?>
  <!-- SUCCESS STATE -->
  <div class="success-card">
    <div class="success-icon">
      <i class="bi bi-building-check"></i>
    </div>
    <h2 style="font-size: 1.6rem; font-weight: 800; color: #0F172A; margin: 0 0 8px;">Application Submitted!</h2>
    <p style="font-size: 15px; color: #64748B; margin: 0 0 16px;">
      Your hospital has been registered in the MedCore system and is <strong>pending central review</strong>.
    </p>

    <div class="uid-box">
      <i class="bi bi-hospital"></i>
      <?= htmlspecialchars($applicationId) ?>
    </div>

    <p style="font-size: 13px; color: #94A3B8; margin: 0;">
      Save this Application ID — you'll need it for status inquiries.
    </p>

    <ul class="next-steps-list">
      <li>
        <i class="bi bi-check-circle-fill"></i>
        <div>
          <strong>Application Received</strong><br>
          Your hospital profile is now in the MedCore verification queue.
        </div>
      </li>
      <li>
        <i class="bi bi-shield-lock-fill" style="color: #3B82F6;"></i>
        <div>
          <strong>Central Admin Review</strong><br>
          MedCore verifies your registration number and credentials against national health authority records.
        </div>
      </li>
      <li>
        <i class="bi bi-envelope-fill" style="color: #8B5CF6;"></i>
        <div>
          <strong>Approval Notification</strong><br>
          You'll receive an email at <strong><?= htmlspecialchars($_POST['admin_email'] ?? '') ?></strong> once approved. Typical review time: 1–3 business days.
        </div>
      </li>
      <li>
        <i class="bi bi-people-fill" style="color: #F59E0B;"></i>
        <div>
          <strong>Manage Your Doctor Roster</strong><br>
          After approval, log into the Hospital Portal to add verified doctors to your network.
        </div>
      </li>
    </ul>

    <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
      <a href="<?= APP_URL ?>/hospital/login.php" class="btn btn-primary">
        <i class="bi bi-box-arrow-in-right"></i> Go to Hospital Login
      </a>
      <a href="<?= APP_URL ?>/" class="btn btn-secondary">
        ← Back to MedCore Home
      </a>
    </div>
  </div>

  <?php else: ?>

  <!-- PAGE INTRO -->
  <div class="page-intro">
    <div class="badge-tag">
      <i class="bi bi-hospital-fill"></i>
      Hospital Network Application
    </div>
    <h1>Join the MedCore Ecosystem</h1>
    <p>
      Register your hospital or clinic to access verified doctors, digital records management, and nationwide interoperability through the MedCore central health network.
    </p>
  </div>

  <!-- FLOW STEPS -->
  <div class="flow-steps">
    <div class="flow-step active">
      <div class="step-num">1</div>
      Submit Application
    </div>
    <span class="flow-arrow"><i class="bi bi-chevron-right"></i></span>
    <div class="flow-step">
      <div class="step-num">2</div>
      Central Admin Verifies
    </div>
    <span class="flow-arrow"><i class="bi bi-chevron-right"></i></span>
    <div class="flow-step">
      <div class="step-num">3</div>
      Account Activated
    </div>
    <span class="flow-arrow"><i class="bi bi-chevron-right"></i></span>
    <div class="flow-step">
      <div class="step-num">4</div>
      Manage Doctor Roster
    </div>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

    <!-- SECTION 1: HOSPITAL INFORMATION -->
    <div class="form-card">
      <div class="form-card-header">
        <div class="form-card-icon" style="background: #EFF6FF; color: #1E40AF;">
          <i class="bi bi-building-fill"></i>
        </div>
        <div>
          <div style="font-weight: 700; font-size: 15px; color: #0F172A;">Hospital Information</div>
          <div style="font-size: 12px; color: #94A3B8;">Legal name & government registration details</div>
        </div>
      </div>
      <div class="form-card-body">

        <div class="form-group">
          <label class="form-label" for="hospital_name">Hospital / Clinic Legal Name <span style="color: #EF4444;">*</span></label>
          <input type="text" id="hospital_name" name="hospital_name" class="form-control"
            placeholder="e.g., Square Hospital Limited"
            value="<?= htmlspecialchars($_POST['hospital_name'] ?? '') ?>"
            required>
          <div class="form-hint">Must match your government registration certificate exactly.</div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label" for="registration_number">Govt. Registration Number <span style="color: #EF4444;">*</span></label>
            <div class="input-with-icon">
              <i class="bi bi-file-earmark-text"></i>
              <input type="text" id="registration_number" name="registration_number" class="form-control"
                placeholder="e.g., DGHS/HOSP/2024/001234"
                value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>"
                required>
            </div>
            <div class="form-hint">DGHS, BMDC or relevant health authority number.</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="type">Hospital Type <span style="color: #EF4444;">*</span></label>
            <select id="type" name="type" class="form-control" required>
              <option value="">— Select type —</option>
              <?php
              $types = [
                'GENERAL'    => 'General Hospital',
                'SPECIALIZED'=> 'Specialized Hospital',
                'CLINIC'     => 'Clinic / Polyclinic',
                'DIAGNOSTIC' => 'Diagnostic Center',
                'TEACHING'   => 'Teaching / Medical College Hospital',
                'OTHER'      => 'Other',
              ];
              foreach ($types as $val => $label):
              ?>
              <option value="<?= $val ?>" <?= (($_POST['type'] ?? '') === $val) ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="address">Full Address</label>
          <input type="text" id="address" name="address" class="form-control"
            placeholder="e.g., 18/F Bir Uttam Qazi Nuruzzaman Sarak, West Panthapath"
            value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
        </div>

        <div class="form-grid-3">
          <div class="form-group">
            <label class="form-label" for="city">City <span style="color: #EF4444;">*</span></label>
            <input type="text" id="city" name="city" class="form-control"
              placeholder="e.g., Dhaka"
              value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
              required>
          </div>

          <div class="form-group">
            <label class="form-label" for="district">District</label>
            <select id="district" name="district" class="form-control">
              <option value="">— Select district —</option>
              <?php foreach ($districts as $d): ?>
              <option value="<?= $d ?>" <?= (($_POST['district'] ?? '') === $d) ? 'selected' : '' ?>>
                <?= $d ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="phone">Hospital Phone</label>
            <div class="input-with-icon">
              <i class="bi bi-telephone"></i>
              <input type="tel" id="phone" name="phone" class="form-control"
                placeholder="+880 2 XXXXXXXX"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="website">Hospital Website (optional)</label>
          <div class="input-with-icon">
            <i class="bi bi-globe"></i>
            <input type="url" id="website" name="website" class="form-control"
              placeholder="https://yourhospital.com.bd"
              value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
          </div>
        </div>

      </div>
    </div>

    <!-- SECTION 2: ADMIN ACCOUNT -->
    <div class="form-card">
      <div class="form-card-header">
        <div class="form-card-icon" style="background: #F0FDF4; color: #059669;">
          <i class="bi bi-person-badge-fill"></i>
        </div>
        <div>
          <div style="font-weight: 700; font-size: 15px; color: #0F172A;">Hospital Administrator Account</div>
          <div style="font-size: 12px; color: #94A3B8;">The primary contact who will manage this hospital portal</div>
        </div>
      </div>
      <div class="form-card-body">

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label" for="admin_email">Admin Email Address <span style="color: #EF4444;">*</span></label>
            <div class="input-with-icon">
              <i class="bi bi-envelope-at"></i>
              <input type="email" id="admin_email" name="admin_email" class="form-control"
                placeholder="admin@yourhospital.com.bd"
                value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
                required autocomplete="email">
            </div>
            <div class="form-hint">This will be the login email for the Hospital Portal.</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="admin_phone">Admin Phone Number</label>
            <div class="input-with-icon">
              <i class="bi bi-phone"></i>
              <input type="tel" id="admin_phone" name="admin_phone" class="form-control"
                placeholder="+880 1X XXXXXXXX"
                value="<?= htmlspecialchars($_POST['admin_phone'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group" style="position:relative;">
            <label class="form-label" for="admin_password">Create Password <span style="color: #EF4444;">*</span></label>
            <div class="input-with-icon">
              <i class="bi bi-key"></i>
              <input type="password" id="admin_password" name="admin_password" class="form-control"
                placeholder="Min. 8 characters"
                required autocomplete="new-password">
            </div>
            <button type="button" onclick="togglePwd('admin_password', 'eye1')"
              style="position:absolute;right:12px;top:38px;background:none;border:none;cursor:pointer;color:#94A3B8;padding:4px;">
              <i class="bi bi-eye" id="eye1"></i>
            </button>
            <div class="form-hint">Use letters, numbers, and symbols. Min 8 characters.</div>
          </div>

          <div class="form-group" style="position:relative;">
            <label class="form-label" for="confirm_password">Confirm Password <span style="color: #EF4444;">*</span></label>
            <div class="input-with-icon">
              <i class="bi bi-key-fill"></i>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                placeholder="Re-enter password"
                required autocomplete="new-password">
            </div>
            <button type="button" onclick="togglePwd('confirm_password', 'eye2')"
              style="position:absolute;right:12px;top:38px;background:none;border:none;cursor:pointer;color:#94A3B8;padding:4px;">
              <i class="bi bi-eye" id="eye2"></i>
            </button>
          </div>
        </div>

        <div style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 10px; padding: 14px 16px; display: flex; gap: 12px; align-items: flex-start;">
          <i class="bi bi-info-circle-fill" style="color: #D97706; margin-top: 1px; flex-shrink: 0;"></i>
          <div style="font-size: 13px; color: #92400E; line-height: 1.6;">
            <strong>Important:</strong> Your hospital account will be <strong>PENDING</strong> until the MedCore central administration team verifies your registration number with national health authority records. This typically takes 1–3 business days.
          </div>
        </div>

      </div>
    </div>

    <!-- SUBMIT -->
    <div class="submit-area">
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div>
          <div style="font-weight: 700; font-size: 15px; color: #0F172A; margin-bottom: 4px;">
            Ready to apply?
          </div>
          <div style="font-size: 13px; color: #64748B;">
            Your application will be reviewed by the MedCore National Administration Team.
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #0D9488, #0F766E); border: none; min-width: 220px;">
          <i class="bi bi-building-check"></i> Submit Hospital Application
        </button>
      </div>

      <div class="terms-notice">
        By submitting, you confirm that all provided information is accurate and your facility is duly registered with the relevant health authority.
        MedCore reserves the right to reject applications that cannot be verified.
      </div>
    </div>

  </form>

  <?php if (APP_ENV === 'development'): ?>
  <div class="alert alert-warning mt-4" style="font-size: 13px;">
    <i class="bi bi-bug-fill"></i>
    <strong>Dev Hint:</strong> After submitting, approve the hospital from the
    <a href="<?= APP_URL ?>/admin/login.php"><strong>Admin Console → Hospital Network</strong></a>
    (admin@medcore.local / Demo123!), then log in at
    <a href="<?= APP_URL ?>/hospital/login.php"><strong>Hospital Portal</strong></a>.
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<script>
function togglePwd(id, eyeId) {
  const input = document.getElementById(id);
  const icon  = document.getElementById(eyeId);
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
