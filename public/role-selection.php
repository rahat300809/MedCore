<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
initSession();

// If already logged in, redirect
if (isset($_SESSION['role'])) {
    header('Location: ' . APP_URL . '/');
    exit;
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
    .role-subtitle { font-size: 1rem; color: var(--mc-text-secondary); text-align: center; margin-bottom: var(--space-10); }
    .role-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-5); width: 100%; max-width: 860px; }
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
      display: block;
    }
    .role-card:hover { border-color: var(--mc-blue); box-shadow: var(--shadow-blue); transform: translateY(-4px); color: var(--mc-text); }
    .role-card-icon {
      width: 72px; height: 72px; border-radius: var(--radius-xl);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem; margin: 0 auto var(--space-5);
    }
    .role-card-icon-patient  { background: var(--mc-blue-light); color: var(--mc-blue); }
    .role-card-icon-doctor   { background: linear-gradient(135deg, var(--mc-blue), var(--mc-blue-dark)); color: white; }
    .role-card-icon-hospital { background: var(--mc-teal-light); color: var(--mc-teal-dark); }
    .role-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: var(--space-3); }
    .role-card p { font-size: 13px; color: var(--mc-text-secondary); line-height: 1.5; margin-bottom: var(--space-5); }
    .role-btn {
      display: inline-block; width: 100%; padding: 10px;
      border-radius: var(--radius-md); font-size: 14px; font-weight: 600;
      transition: var(--transition);
    }
    .role-btn-patient  { background: var(--mc-blue-light); color: var(--mc-blue); }
    .role-btn-doctor   { background: var(--mc-blue); color: white; }
    .role-btn-hospital { background: var(--mc-teal-light); color: var(--mc-teal-dark); }
    .role-card:hover .role-btn-patient  { background: var(--mc-blue); color: white; }
    .role-card:hover .role-btn-hospital { background: var(--mc-teal); color: white; }
    .admin-link { margin-top: var(--space-8); font-size: 13px; color: var(--mc-text-muted); }
    .admin-link a { color: var(--mc-text-secondary); }
    @media (max-width: 700px) {
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
    <p class="role-subtitle">Select your role to continue. Access is strictly controlled and audit-logged.</p>

    <div class="role-grid">
      <!-- Patient -->
      <a href="<?= APP_URL ?>/patient/login.php" class="role-card" id="patient-role-card">
        <div class="role-card-icon role-card-icon-patient">
          <i class="bi bi-person-heart"></i>
        </div>
        <h3>I'm a Patient</h3>
        <p>Manage your lifelong health record, review prescriptions, and control doctor access.</p>
        <span class="role-btn role-btn-patient">Patient Portal →</span>
      </a>

      <!-- Doctor -->
      <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="role-card" id="doctor-role-card">
        <div class="role-card-icon role-card-icon-doctor">
          <i class="bi bi-stethoscope"></i>
        </div>
        <h3>I'm a Doctor</h3>
        <p>Access authorized patient records, conduct consultations, and issue digital prescriptions.</p>
        <span class="role-btn role-btn-doctor">Doctor Portal →</span>
      </a>

      <!-- Hospital -->
      <a href="<?= APP_URL ?>/hospital/login.php" class="role-card" id="hospital-role-card">
        <div class="role-card-icon role-card-icon-hospital">
          <i class="bi bi-building-fill-cross"></i>
        </div>
        <h3>I'm a Hospital</h3>
        <p>Manage affiliated doctors, departments, and review all hospital-wide consultation records.</p>
        <span class="role-btn role-btn-hospital">Hospital Portal →</span>
      </a>
    </div>

    <p class="admin-link">
      System administrator?
      <a href="<?= APP_URL ?>/admin/login.php">Admin Console</a>
    </p>
  </main>
</div>
</body>
</html>
