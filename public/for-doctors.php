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
  <title>For Doctors — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
  <style>
    .page-hero {
      padding: 100px 0 60px;
      text-align: center;
      background: linear-gradient(180deg, #EFF6FF 0%, var(--mc-bg) 100%);
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
      border-color: var(--mc-blue);
    }
    .feature-icon {
      width: 56px;
      height: 56px;
      border-radius: var(--radius-lg);
      background: #EFF6FF;
      color: var(--mc-blue);
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
      <li><a href="<?= APP_URL ?>/for-patients.php">For Patients</a></li>
      <li><a href="<?= APP_URL ?>/for-doctors.php" style="color: var(--mc-blue); font-weight: 700;">For Doctors</a></li>
      <li><a href="<?= APP_URL ?>/for-hospitals.php">For Hospitals</a></li>
      <li><a href="<?= APP_URL ?>/security.php">Security</a></li>
    </ul>
    <div class="navbar-actions">
      <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-secondary btn-sm">Log In</a>
      <a href="<?= APP_URL ?>/doctor/verify.php" class="btn btn-primary btn-sm">Doctor Sign Up</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="page-hero">
  <div class="container">
    <span class="security-badge" style="background: #DBEAFE; color: #1E40AF; border-color: #BFDBFE;">
      <i class="bi bi-patch-check-fill"></i> BMDC-Verified Clinical Network
    </span>
    <h1 style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; margin: 16px 0 12px; color: var(--mc-text);">
      Instant Patient History.<br>
      <span style="color: var(--mc-blue);">Streamlined Clinical Prescriptions.</span>
    </h1>
    <p style="max-width: 680px; margin: 0 auto 32px; font-size: 1.1rem; color: var(--mc-text-secondary);">
      Practice across multiple affiliated hospitals with a single login. Access complete patient timelines with consent, review past medications, and generate digital tamper-proof prescriptions in seconds.
    </p>
    <div class="d-flex justify-content-center gap-3">
      <a href="<?= APP_URL ?>/doctor/verify.php" class="btn btn-primary btn-lg">Doctor Verification Flow</a>
      <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-secondary btn-lg">Doctor Portal Login</a>
    </div>
  </div>
</section>

<!-- FEATURES GRID -->
<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-search"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Instant Patient Lookup</h3>
          <p class="text-muted">Search by MedCore Patient UID (PT-000001) or last 4 digits of National ID. Send a 1-click access request directly to patient's mobile device.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-hospital"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Multi-Hospital Context</h3>
          <p class="text-muted">Consult at Square Hospital in the morning and Evercare Hospital in the evening. MedCore automatically applies institutional letterheads and verification rules.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-prescription2"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Intelligent Rx Pad</h3>
          <p class="text-muted">Dynamic medicine rows with dosage forms, strength suggestions, frequency abbreviations (1+0+1), duration, and instructions tailored for Bangladeshi practice.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-shield-slash"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Adverse Drug Interaction Prevention</h3>
          <p class="text-muted">Receive prominent red banners if a patient has documented severe or life-threatening reactions to penicillin, NSAIDs, or other prescribed compounds.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-qr-code"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Print &amp; QR Authentication</h3>
          <p class="text-muted">Generate beautiful printable prescriptions that pharmacies and diagnostic centers can verify with a single camera scan, preventing prescription fraud.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Medico-Legal Protection</h3>
          <p class="text-muted">Every access and note is time-stamped and cryptographic. Protect your clinical decisions with verifiable documentation and explicit patient consent logs.</p>
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
