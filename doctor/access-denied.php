<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

initSession();

$reason = $_GET['reason'] ?? 'unknown';
$doctorId = $_SESSION['denied_doctor_id'] ?? null;

// Fetch doctor's approved hospitals to guide them
$approvedHospitals = [];
if ($doctorId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT h.name, h.city, h.type, dh.designation, dh.employment_type
        FROM doctor_hospitals dh
        JOIN hospitals h ON dh.hospital_id = h.id
        WHERE dh.doctor_id = ? AND dh.status = 'APPROVED' AND h.verification_status = 'VERIFIED'
        ORDER BY h.name
    ");
    $stmt->execute([$doctorId]);
    $approvedHospitals = $stmt->fetchAll();
}

$reasonMessages = [
    'no_affiliation'    => ['title' => 'No Approved Affiliation', 'msg' => 'You do not have an approved affiliation with the selected hospital. Please choose a hospital you are affiliated with, or contact your hospital administrator to request affiliation.', 'icon' => 'bi-building-x', 'color' => 'var(--mc-amber)'],
    'doctor_not_verified' => ['title' => 'Pending Doctor Verification', 'msg' => 'Your doctor profile is pending verification by the MedCore admin team. You will be notified once your BMDC registration is verified.', 'icon' => 'bi-clock-history', 'color' => 'var(--mc-blue)'],
    'account_suspended' => ['title' => 'Account Suspended', 'msg' => 'Your account has been suspended. Please contact MedCore support for assistance.', 'icon' => 'bi-slash-circle', 'color' => 'var(--mc-red)'],
    'account_blocked'   => ['title' => 'Account Blocked', 'msg' => 'Your account has been blocked due to security policy violations. Please contact MedCore support.', 'icon' => 'bi-shield-x', 'color' => 'var(--mc-red)'],
];

$info = $reasonMessages[$reason] ?? ['title' => 'Access Denied', 'msg' => 'Access to this portal could not be granted. Please try again or contact support.', 'icon' => 'bi-lock-fill', 'color' => 'var(--mc-red)'];

// Clear denied session vars
unset($_SESSION['denied_doctor_id'], $_SESSION['denied_doctor_name']);
unset($_SESSION['selected_hospital_id'], $_SESSION['selected_hospital_name']);
unset($_SESSION['pending_doctor_id'], $_SESSION['pending_otp_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Denied — MedCore Doctor Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
</head>
<body style="background:var(--mc-bg);min-height:100vh;display:flex;flex-direction:column;">

<div class="auth-flow-container" style="flex-direction:column;gap:var(--space-8);">
  <div style="text-align:center;">
    <a href="<?= APP_URL ?>/" class="navbar-logo" style="justify-content:center;">
      <div class="logo-icon" style="width:36px;height:36px;font-size:18px;">M</div>
      <span>MedCore</span>
    </a>
  </div>

  <div class="doctor-verify-card" style="max-width:560px;">
    <!-- Icon -->
    <div style="text-align:center;margin-bottom:var(--space-6);">
      <div style="width:72px;height:72px;border-radius:50%;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-4);font-size:2rem;color:<?= $info['color'] ?>;">
        <i class="<?= $info['icon'] ?>"></i>
      </div>
      <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:var(--space-3);"><?= htmlspecialchars($info['title']) ?></h1>
      <p style="font-size:14px;color:var(--mc-text-secondary);line-height:1.6;"><?= htmlspecialchars($info['msg']) ?></p>
    </div>

    <!-- Approved hospitals (if applicable) -->
    <?php if (!empty($approvedHospitals)): ?>
    <div style="margin-bottom:var(--space-6);">
      <div class="form-label" style="margin-bottom:var(--space-3);">Your Approved Affiliations</div>
      <?php foreach ($approvedHospitals as $h): ?>
      <div class="hospital-card" style="margin-bottom:var(--space-2);cursor:default;">
        <div class="hospital-logo"><i class="bi bi-hospital-fill" style="color:var(--mc-green);"></i></div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($h['name']) ?></div>
          <div style="font-size:12px;color:var(--mc-text-muted);"><?= htmlspecialchars($h['designation'] ?? '') ?> · <?= ucfirst(strtolower($h['employment_type'])) ?> · <?= htmlspecialchars($h['city'] ?? '') ?></div>
        </div>
        <span class="badge badge-success"><i class="bi bi-check-circle-fill"></i> Approved</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-3">
      <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="btn btn-primary flex-1">
        <i class="bi bi-building-fill-check"></i> Select Different Hospital
      </a>
      <a href="<?= APP_URL ?>/" class="btn btn-secondary">
        <i class="bi bi-house-fill"></i>
      </a>
    </div>

    <div style="margin-top:var(--space-6);padding-top:var(--space-5);border-top:1px solid var(--mc-border);font-size:12px;color:var(--mc-text-muted);text-align:center;">
      <i class="bi bi-shield-fill-check" style="color:var(--mc-green);"></i>
      This access attempt has been audit-logged with your IP address and timestamp.
    </div>
  </div>
</div>
</body>
</html>
