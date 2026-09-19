<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
initSession();

// If already logged in, redirect
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'SYSTEM_ADMIN') {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'HOSPITAL_ADMIN') {
        header('Location: ' . APP_URL . '/hospital/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'DOCTOR') {
        header('Location: ' . APP_URL . '/doctor/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'PATIENT') {
        header('Location: ' . APP_URL . '/patient/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Choose Your Portal — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    body { background: var(--mc-bg); min-height: 100vh; display: flex; flex-direction: column; }
    .role-page { flex: 1; display: flex; flex-direction: column; }
    .role-header {
      background: var(--mc-white);
      border-bottom: 1px solid var(--mc-border);
      padding: var(--space-4) var(--space-8);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .role-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: var(--space-12) var(--space-6);
    }
    .role-title { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; text-align: center; margin-bottom: var(--space-4); }
    .role-subtitle { font-size: 1rem; color: var(--mc-text-secondary); text-align: center; margin-bottom: var(--space-8); }
    .role-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-5); width: 100%; max-width: 920px; }
    .role-card {
      background: var(--mc-white);
      border: 2px solid var(--mc-border);
      border-radius: var(--radius-xl);
      padding: var(--space-8) var(--space-6);
      text-align: center;
      cursor: pointer;
      transition: var(--transition-slow);
      text-decoration: none;
      color: var(--mc-text);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .role-card:hover { border-color: var(--mc-blue); box-shadow: var(--shadow-blue); transform: translateY(-4px); color: var(--mc-text); }
    .role-card-icon {
      width: 68px; height: 68px; border-radius: var(--radius-xl);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; margin: 0 auto var(--space-4);
    }
    .role-card-icon-patient  { background: var(--mc-blue-light); color: var(--mc-blue); }
    .role-card-icon-doctor   { background: linear-gradient(135deg, var(--mc-blue), var(--mc-blue-dark)); color: white; }
    .role-card-icon-hospital { background: var(--mc-teal-light); color: var(--mc-teal-dark); }
    .role-card h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: var(--space-2); }
    .role-card p { font-size: 13px; color: var(--mc-text-secondary); line-height: 1.5; margin-bottom: var(--space-4); }
    .role-btn {
      display: inline-block; width: 100%; padding: 10px;
      border-radius: var(--radius-md); font-size: 14px; font-weight: 600;
      transition: var(--transition);
      text-decoration: none;
    }
    .role-btn-patient  { background: var(--mc-blue-light); color: var(--mc-blue); }
    .role-btn-doctor   { background: var(--mc-blue); color: white; }
    .role-btn-hospital { background: var(--mc-teal-light); color: var(--mc-teal-dark); }
    .role-card:hover .role-btn-patient  { background: var(--mc-blue); color: white; }
    .role-card:hover .role-btn-hospital { background: var(--mc-teal); color: white; }
    .sub-action {
      font-size: 12px;
      color: var(--mc-text-muted);
      margin-top: 10px;
    }
    .sub-action a {
      color: var(--mc-blue);
      font-weight: 600;
      text-decoration: underline;
    }
    .admin-link {
      margin-top: var(--space-8);
      font-size: 13px;
      color: var(--mc-text-muted);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .admin-link a {
      color: var(--mc-text);
      font-weight: 600;
      padding: 4px 12px;
      background: var(--mc-white);
      border: 1px solid var(--mc-border);
      border-radius: 20px;
      text-decoration: none;
      transition: var(--transition);
    }
    .admin-link a:hover {
      background: #0F172A;
      color: white;
      border-color: #0F172A;
    }
    @media (max-width: 768px) {
      .role-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="role-page">
  <header class="role-header">
    <a href="<?= APP_URL ?>/" class="navbar-logo">
      <div class="logo-icon">M</div>
      <span>MedCore</span>
    </a>
    <a href="<?= APP_URL ?>/" class="btn btn-secondary btn-sm">
      <i class="bi bi-arrow-left"></i> Back to Home
    </a>
  </header>

  <main class="role-main">
    <p class="eyebrow" style="margin-bottom:var(--space-3);">One Record. Every Care.</p>
    <h1 class="role-title">Choose Your Portal</h1>
    <p class="role-subtitle">Select your role to continue. Multi-hospital clinical access is strictly authenticated and audit-logged.</p>

    <div class="role-grid">
      <!-- Patient -->
      <div class="role-card" id="patient-role-card">
        <div>
          <div class="role-card-icon role-card-icon-patient">
            <i class="bi bi-person-heart"></i>
          </div>
          <h3>I'm a Patient</h3>
          <p>Manage your lifelong health record, review prescriptions, and grant temporary consent to doctors.</p>
        </div>
        <div>
          <a href="<?= APP_URL ?>/patient/login.php" class="role-btn role-btn-patient">Patient Login →</a>
          <div class="sub-action">New here? <a href="<?= APP_URL ?>/patient/register.php">Register Free</a></div>
        </div>
      </div>

      <!-- Doctor -->
      <div class="role-card" id="doctor-role-card">
        <div>
          <div class="role-card-icon role-card-icon-doctor">
            <i class="bi bi-stethoscope"></i>
          </div>
          <h3>I'm a Doctor</h3>
          <p>Practice across multiple hospitals with single BMDC verification. Access patient histories and write prescriptions.</p>
        </div>
        <div>
          <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="role-btn role-btn-doctor">Doctor Portal Login →</a>
          <div class="sub-action">New Doctor? <a href="<?= APP_URL ?>/doctor/register.php">Register & Verify</a></div>
        </div>
      </div>

      <!-- Hospital -->
      <div class="role-card" id="hospital-role-card">
        <div>
          <div class="role-card-icon role-card-icon-hospital">
            <i class="bi bi-building-fill-cross"></i>
          </div>
          <h3>I'm a Hospital</h3>
          <p>Manage affiliated doctor rosters, departments, and review hospital-wide clinical consultation records.</p>
        </div>
        <div>
          <a href="<?= APP_URL ?>/hospital/login.php" class="role-btn role-btn-hospital">Hospital Portal →</a>
          <div class="sub-action"><span style="color:var(--mc-text-muted);">Accredited Medical Center Access</span></div>
        </div>
      </div>
    </div>

    <div class="admin-link">
      <span>System Administrator?</span>
      <a href="<?= APP_URL ?>/admin/login.php">
        <i class="bi bi-shield-lock-fill"></i> Central Admin Console
      </a>
    </div>
  </main>
</div>
</body>
</html>
