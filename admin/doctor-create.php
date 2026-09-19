<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $fullName      = trim($_POST['full_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $password      = $_POST['password'] ?? 'Demo123!';
    $regId         = trim($_POST['medical_registration_id'] ?? '');
    $specialization= trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $designation   = trim($_POST['designation'] ?? 'Consultant');
    $experience    = (int)($_POST['experience_years'] ?? 0);
    $verifyNow     = isset($_POST['verify_now']);

    if (empty($fullName) || empty($email) || empty($regId)) {
        $error = 'Full Name, Email, and BMDC Medical Registration ID are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email or regId exists
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            $error = 'A user with this email address already exists.';
        } else {
            $stmtRegCheck = $db->prepare("SELECT id FROM doctors WHERE medical_registration_id = ?");
            $stmtRegCheck->execute([$regId]);
            if ($stmtRegCheck->fetch()) {
                $error = 'A doctor with this Medical Registration ID already exists.';
            } else {
                try {
                    $db->beginTransaction();

                    // Generate Doctor UID
                    $lastId = (int)$db->query("SELECT MAX(id) FROM doctors")->fetchColumn() + 1;
                    $doctorUid = sprintf('DR-%06d', $lastId);

                    // Insert User
                    $passHash = password_hash($password, PASSWORD_BCRYPT);
                    $userStatus = $verifyNow ? 'ACTIVE' : 'PENDING';
                    $stmtUser = $db->prepare("
                        INSERT INTO users (role, email, phone, password_hash, status, email_verified, phone_verified)
                        VALUES ('DOCTOR', ?, ?, ?, ?, 1, 1)
                    ");
                    $stmtUser->execute([$email, $phone ?: null, $passHash, $userStatus]);
                    $userId = (int)$db->lastInsertId();

                    // Insert Doctor
                    $verStatus  = $verifyNow ? 'VERIFIED' : 'PENDING';
                    $verifiedBy = $verifyNow ? $_SESSION['user_id'] : null;
                    $verifiedAt = $verifyNow ? date('Y-m-d H:i:s') : null;

                    $stmtDoc = $db->prepare("
                        INSERT INTO doctors
                            (user_id, doctor_uid, medical_registration_id, full_name, specialization,
                             qualification, designation, experience_years, verification_status, verified_by, verified_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtDoc->execute([
                        $userId, $doctorUid, $regId, $fullName, $specialization ?: null,
                        $qualification ?: null, $designation ?: null, $experience,
                        $verStatus, $verifiedBy, $verifiedAt
                    ]);
                    $doctorId = (int)$db->lastInsertId();

                    $db->commit();

                    AuditService::log('DOCTOR_CREATED_BY_ADMIN', [
                        'user_id'   => $_SESSION['user_id'],
                        'doctor_id' => $doctorId,
                        'metadata'  => ['doctor_uid' => $doctorUid, 'reg_id' => $regId, 'verified' => $verifyNow]
                    ]);

                    header('Location: ' . APP_URL . '/admin/doctor-detail.php?id=' . $doctorId . '&created=1');
                    exit;

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
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
  <title>Add & Verify Doctor — MedCore Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-dark: #0F172A;
      --admin-primary: #1E40AF;
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
      <a href="<?= APP_URL ?>/admin/doctors.php" class="nav-item active">
        <i class="bi bi-person-badge-fill"></i> Doctor Verification
      </a>
      <a href="<?= APP_URL ?>/admin/hospitals.php" class="nav-item">
        <i class="bi bi-building-fill-check"></i> Hospital Network
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
        <div class="user-avatar" style="background: #3B82F6; color:white; width:36px; height:36px; font-size:13px;">
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
  <main class="portal-content">
    <header class="top-nav">
      <div class="top-nav-left">
        <a href="<?= APP_URL ?>/admin/doctors.php" class="btn btn-secondary btn-sm" style="margin-right: 8px;">
          ← Back to Doctors
        </a>
        <div>
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">DOCTOR REGISTRATION DESK</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Register & Approve New Doctor</h1>
        </div>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6); max-width: 800px;">

      <?php if ($error): ?>
      <div class="alert alert-error mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <div class="card" style="padding: 28px; border: 1px solid var(--mc-border);">
        <h2 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 16px 0;">
          Doctor License & Profile Information
        </h2>

        <form method="POST" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group">
              <label class="form-label" for="full_name">Doctor Full Name <span class="text-danger">*</span></label>
              <input type="text" id="full_name" name="full_name" class="form-control"
                placeholder="e.g. Dr. Sabrina Ahmed" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="medical_registration_id">BMDC / Medical Reg Number <span class="text-danger">*</span></label>
              <input type="text" id="medical_registration_id" name="medical_registration_id" class="form-control"
                placeholder="e.g. BMDC-A-98765" value="<?= htmlspecialchars($_POST['medical_registration_id'] ?? '') ?>" required>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group">
              <label class="form-label" for="email">Doctor Email Address <span class="text-danger">*</span></label>
              <input type="email" id="email" name="email" class="form-control"
                placeholder="doctor@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="phone">Phone Number</label>
              <input type="text" id="phone" name="phone" class="form-control"
                placeholder="e.g. 01712345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1.2fr 1fr 0.8fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group">
              <label class="form-label" for="specialization">Medical Specialization</label>
              <input type="text" id="specialization" name="specialization" class="form-control"
                placeholder="e.g. Cardiology / Internal Medicine" value="<?= htmlspecialchars($_POST['specialization'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label class="form-label" for="designation">Primary Designation</label>
              <input type="text" id="designation" name="designation" class="form-control"
                placeholder="e.g. Senior Consultant" value="<?= htmlspecialchars($_POST['designation'] ?? 'Consultant') ?>">
            </div>

            <div class="form-group">
              <label class="form-label" for="experience_years">Experience (Yrs)</label>
              <input type="number" id="experience_years" name="experience_years" class="form-control" min="0" max="60"
                value="<?= htmlspecialchars($_POST['experience_years'] ?? '5') ?>">
            </div>
          </div>

          <div class="form-group mb-4">
            <label class="form-label" for="qualification">Degrees & Qualifications</label>
            <input type="text" id="qualification" name="qualification" class="form-control"
              placeholder="e.g. MBBS (DMC), FCPS (Medicine), MD (Cardiology)" value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>">
          </div>

          <div class="form-group mb-4">
            <label class="form-label" for="password">Initial Password</label>
            <input type="text" id="password" name="password" class="form-control"
              value="<?= htmlspecialchars($_POST['password'] ?? 'Demo123!') ?>">
            <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 4px;">
              Default demo password is <code>Demo123!</code>
            </div>
          </div>

          <div style="background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: var(--radius-md); padding: 14px; margin-bottom: 24px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; font-weight: 700; color: #166534;">
              <input type="checkbox" name="verify_now" value="1" checked style="width: 18px; height: 18px;">
              <span>Immediately Approve & Verify Doctor (Active Nationwide)</span>
            </label>
            <div style="font-size: 12px; color: #15803D; margin-left: 28px; margin-top: 2px;">
              When checked, this doctor will immediately be marked as <code>VERIFIED</code>, allowing all hospitals in the ecosystem to affiliate them to their medical teams right away.
            </div>
          </div>

          <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="<?= APP_URL ?>/admin/doctors.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary" style="background: var(--admin-primary); border: none; font-weight: 700;">
              <i class="bi bi-check-lg"></i> Register Doctor
            </button>
          </div>
        </form>
      </div>

    </div>
  </main>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>
</body>
</html>
