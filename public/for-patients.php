<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
initSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>For Patients — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
  <style>
    .page-hero {
      padding: 100px 0 60px;
      text-align: center;
      background: linear-gradient(180deg, #F0FDF4 0%, var(--mc-bg) 100%);
    }
    .feature-card {
      background: #fff;
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-xl);
      padding: var(--space-8);
      height: 100%;
      box-shadow: var(--shadow-sm);
      transition: var(--transition-slow);
    }
    .feature-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-md);
      border-color: #10B981;
    }
    .feature-icon {
      width: 56px;
      height: 56px;
      border-radius: var(--radius-lg);
      background: #ECFDF5;
      color: #10B981;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      margin-bottom: var(--space-4);
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="main-navbar">
  <div class="navbar-inner">
    <a href="<?= APP_URL ?>/" class="navbar-logo">
      <div class="logo-icon">M</div>
      <span>MedCore</span>
    </a>
    <ul class="navbar-nav" id="nav-menu">
      <li><a href="<?= APP_URL ?>/#how-it-works">How It Works</a></li>
      <li><a href="<?= APP_URL ?>/for-patients.php" style="color: var(--mc-blue); font-weight: 700;">For Patients</a></li>
      <li><a href="<?= APP_URL ?>/for-doctors.php">For Doctors</a></li>
      <li><a href="<?= APP_URL ?>/for-hospitals.php">For Hospitals</a></li>
      <li><a href="<?= APP_URL ?>/security.php">Security</a></li>
    </ul>
    <div class="navbar-actions">
      <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-secondary btn-sm">Log In</a>
      <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-sm">Get Started</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="page-hero">
  <div class="container">
    <span class="security-badge" style="background: #D1FAE5; color: #065F46; border-color: #A7F3D0;">
      <i class="bi bi-shield-heart-fill"></i> 100% Consent-Controlled Health Identity
    </span>
    <h1 style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; margin: 16px 0 12px; color: var(--mc-text);">
      Your Lifelong Health History.<br>
      <span style="color: #10B981;">In Your Hands, Anywhere in Bangladesh.</span>
    </h1>
    <p style="max-width: 680px; margin: 0 auto 32px; font-size: 1.1rem; color: var(--mc-text-secondary);">
      No more losing old paper prescriptions, repeating costly medical tests, or guessing past drug dosages. MedCore provides a secure digital repository under your complete authority.
    </p>
    <div class="d-flex justify-content-center gap-3">
      <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-lg">Create Free Health Record</a>
      <a href="<?= APP_URL ?>/patient/login.php" class="btn btn-secondary btn-lg">Access Patient Portal</a>
    </div>
  </div>
</section>

<!-- FEATURES GRID -->
<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-fingerprint"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Zero-Leak Consent Engine</h3>
          <p class="text-muted">No physician or hospital can view a single line of your history without your explicit authorization. Doctors submit an access request; you approve via your phone.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-clock-history"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Automatic 30-Min Windows</h3>
          <p class="text-muted">Doctor sessions auto-expire after 30 minutes. You can also press "Revoke Now" at any second from your live access dashboard.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-prescription2"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Digital Prescriptions</h3>
          <p class="text-muted">All prescriptions issued by verified doctors are automatically stored in high-resolution, print-ready format with QR tamper verification.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-shield-exclamation"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Allergy Safeguards</h3>
          <p class="text-muted">Document drug, food, or latex allergies once. Every doctor who opens your file sees an unmissable clinical alert before prescribing.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-file-earmark-medical"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Diagnostic Lab Vault</h3>
          <p class="text-muted">Store your CBC, blood sugar, MRI, and imaging reports in one place. Never worry about damp paper records or lost clinic envelopes.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-eye"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Complete Transparency</h3>
          <p class="text-muted">An immutable audit trail shows the exact name of every doctor, hospital, and timestamp that ever inspected your medical record.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer style="background: #0F172A; color: #94A3B8; padding: 50px 0; margin-top: 80px; text-align: center;">
  <div class="container">
    <div style="font-weight: 800; font-size: 1.4rem; color: #fff; margin-bottom: 8px;">MedCore</div>
    <p style="margin-bottom: 24px;">One Record. Every Care. Bangladesh's unified digital healthcare backbone.</p>
    <div style="font-size: 0.85rem;">&copy; <?= date('Y') ?> MedCore Platform. All rights reserved.</div>
  </div>
</footer>

</body>
</html>
