<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

if (isLoggedIn('DOCTOR')) {
    header('Location: ' . APP_URL . '/doctor/dashboard.php');
    exit;
}

$db = getDB();
$error   = '';
$success = false;
$registeredDoc = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $fullName      = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $password      = $_POST['password'] ?? '';
    $regId         = trim($_POST['medical_registration_id'] ?? '');
    $specialization= trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience    = (int)($_POST['experience_years'] ?? 0);

    if (empty($fullName) || empty($email) || empty($password) || empty($regId)) {
        $error = 'Please fill in all required fields (Full Name, Email, Password, and BMDC Registration ID).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check uniqueness
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address is already registered.';
        } else {
            $stmt = $db->prepare("SELECT id FROM doctors WHERE medical_registration_id = ?");
            $stmt->execute([$regId]);
            if ($stmt->fetch()) {
                $error = 'A doctor profile with this BMDC Medical Registration ID already exists.';
            } else {
                try {
                    $db->beginTransaction();

                    $lastId = (int)$db->query("SELECT MAX(id) FROM doctors")->fetchColumn() + 1;
                    $doctorUid = sprintf('DR-%06d', $lastId);

                    $passHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmtUser = $db->prepare("
                        INSERT INTO users (role, email, phone, password_hash, status, email_verified, phone_verified)
                        VALUES ('DOCTOR', ?, ?, ?, 'PENDING', 0, 0)
                    ");
                    $stmtUser->execute([$email, $phone ?: null, $passHash]);
                    $userId = (int)$db->lastInsertId();

                    $stmtDoc = $db->prepare("
                        INSERT INTO doctors
                            (user_id, doctor_uid, medical_registration_id, full_name, specialization, qualification, experience_years, verification_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')
                    ");
                    $stmtDoc->execute([
                        $userId, $doctorUid, $regId, $fullName, $specialization ?: null,
                        $qualification ?: null, $experience
                    ]);
                    $doctorId = (int)$db->lastInsertId();

                    $db->commit();

                    AuditService::log('DOCTOR_SELF_REGISTRATION', [
                        'user_id'   => $userId,
                        'doctor_id' => $doctorId,
                        'metadata'  => ['doctor_uid' => $doctorUid, 'reg_id' => $regId]
                    ]);

                    $success = true;
                    $registeredDoc = [
                        'name'     => $fullName,
                        'uid'      => $doctorUid,
                        'reg_id'   => $regId,
                        'email'    => $email,
                    ];

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Doctor Registration & Verification — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    body {
      background: var(--mc-bg);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .reg-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--space-8) var(--space-4);
    }
    .reg-card {
      background: var(--mc-white);
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-lg);
      padding: var(--space-8);
      width: 100%;
      max-width: 620px;
    }
  </style>
</head>
<body>

<div class="reg-wrapper">
  <div class="reg-card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6);">
      <a href="<?= APP_URL ?>/" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--mc-text);">
        <div class="logo-icon">M</div>
        <span style="font-weight: 800; font-size: 1.1rem;">MedCore</span>
      </a>
      <span class="badge" style="background: #EFF6FF; color: #1E40AF; font-weight: 700;">
        <i class="bi bi-patch-check"></i> Clinical Network
      </span>
    </div>

    <?php if ($success): ?>
      <!-- SUCCESS SCREEN -->
      <div style="text-align: center; padding: 20px 0;">
        <div style="width: 64px; height: 64px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 16px;">
          <i class="bi bi-hourglass-split"></i>
        </div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: #1E293B; margin-bottom: 8px;">
          Application Submitted Successfully!
        </h2>
        <p style="font-size: 14px; color: var(--mc-text-secondary); max-width: 480px; margin: 0 auto 20px;">
          Welcome, <strong>Dr. <?= htmlspecialchars($registeredDoc['name']) ?></strong>. Your credentials have been received and sent to the <strong>MedCore Central Medical Board</strong> for verification.
        </p>

        <div style="background: #F8FAFC; border: 1px solid var(--mc-border); border-radius: var(--radius-lg); padding: 16px; text-align: left; margin-bottom: 24px;">
          <div style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px;">
            Registration Summary
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 13px;">
            <div><strong>Doctor UID:</strong> <code><?= htmlspecialchars($registeredDoc['uid']) ?></code></div>
            <div><strong>BMDC Reg ID:</strong> <code><?= htmlspecialchars($registeredDoc['reg_id']) ?></code></div>
            <div><strong>Email:</strong> <?= htmlspecialchars($registeredDoc['email']) ?></div>
            <div><strong>Status:</strong> <span class="badge" style="background: #FEF3C7; color: #92400E;">PENDING ADMIN APPROVAL</span></div>
          </div>
        </div>

        <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-md); padding: 14px; text-align: left; font-size: 13px; color: #1E40AF; margin-bottom: 24px;">
          <i class="bi bi-info-circle-fill"></i>
          <strong>Next Steps in the Hospitalization Ecosystem:</strong>
          <ol style="margin: 8px 0 0 16px; padding: 0;">
            <li>The website Main Admin reviews and approves your BMDC license.</li>
            <li>Once approved, hospitals (ABC Hospital, XYZ Medical, etc.) can affiliate you to their departments.</li>
            <li>You can log in and select any affiliated hospital to conduct consultations and prescribe medications.</li>
          </ol>
        </div>

        <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-primary w-100 btn-lg">
          Go to Portal Selection
        </a>
      </div>

    <?php else: ?>
      <!-- REGISTRATION FORM -->
      <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: 1.4rem; font-weight: 800; margin: 0 0 6px 0;">Doctor Registration & Verification</h1>
        <p style="font-size: 13px; color: var(--mc-text-secondary); margin: 0;">
          Join the centralized hospitalization ecosystem. Practice across multiple hospitals with a single verified BMDC identity.
        </p>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-error mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group">
            <label class="form-label" for="full_name">Full Name (with Title) <span class="text-danger">*</span></label>
            <input type="text" id="full_name" name="full_name" class="form-control"
              placeholder="e.g. Dr. Tanvir Alam" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="medical_registration_id">BMDC Medical Reg ID <span class="text-danger">*</span></label>
            <input type="text" id="medical_registration_id" name="medical_registration_id" class="form-control"
              placeholder="e.g. BMDC-A-54321" value="<?= htmlspecialchars($_POST['medical_registration_id'] ?? '') ?>" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group">
            <label class="form-label" for="email">Email Address <span class="text-danger">*</span></label>
            <input type="email" id="email" name="email" class="form-control"
              placeholder="dr.tanvir@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" class="form-control"
              placeholder="e.g. 01712345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div class="form-group">
            <label class="form-label" for="specialization">Specialization</label>
            <input type="text" id="specialization" name="specialization" class="form-control"
              placeholder="e.g. Cardiology / General Medicine" value="<?= htmlspecialchars($_POST['specialization'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label class="form-label" for="experience_years">Years of Experience</label>
            <input type="number" id="experience_years" name="experience_years" class="form-control" min="0" max="60"
              value="<?= htmlspecialchars($_POST['experience_years'] ?? '5') ?>">
          </div>
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="qualification">Qualifications & Degrees</label>
          <input type="text" id="qualification" name="qualification" class="form-control"
            placeholder="e.g. MBBS, FCPS (Medicine), MD" value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>">
        </div>

        <div class="form-group mb-4">
          <label class="form-label" for="password">Create Password <span class="text-danger">*</span></label>
          <input type="password" id="password" name="password" class="form-control"
            placeholder="At least 6 characters" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg" style="font-weight: 700;">
          <i class="bi bi-send-check"></i> Submit for Main Admin Verification
        </button>
      </form>

      <div style="text-align: center; margin-top: var(--space-5); font-size: 13px; color: var(--mc-text-muted);">
        Already registered & verified?
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" style="color: var(--mc-blue); font-weight: 600;">
          Doctor Login & Hospital Practice →
        </a>
      </div>
    <?php endif; ?>

  </div>
</div>

</body>
</html>
