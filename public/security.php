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
  <title>Security &amp; Privacy Architecture — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
  <style>
    .page-hero {
      padding: 100px 0 60px;
      text-align: center;
      background: linear-gradient(180deg, #F8FAFC 0%, var(--mc-bg) 100%);
    }
    .sec-card {
      background: #fff;
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-xl);
      padding: var(--space-8);
      height: 100%;
      box-shadow: var(--shadow-sm);
    }
    .sec-badge-icon {
      width: 50px;
      height: 50px;
      border-radius: var(--radius-lg);
      background: #EFF6FF;
      color: var(--mc-blue);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
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
      <li><a href="<?= APP_URL ?>/for-doctors.php">For Doctors</a></li>
      <li><a href="<?= APP_URL ?>/for-hospitals.php">For Hospitals</a></li>
      <li><a href="<?= APP_URL ?>/security.php" style="color: var(--mc-blue); font-weight: 700;">Security</a></li>
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
    <span class="security-badge" style="background: #E0E7FF; color: #3730A3; border-color: #C7D2FE;">
      <i class="bi bi-shield-lock-fill"></i> Zero-Knowledge Consent &amp; Cryptographic Architecture
    </span>
    <h1 style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; margin: 16px 0 12px; color: var(--mc-text);">
      Built on Cryptographic Trust.<br>
      <span style="color: var(--mc-blue);">Engineered for Absolute Clinical Privacy.</span>
    </h1>
    <p style="max-width: 680px; margin: 0 auto 32px; font-size: 1.1rem; color: var(--mc-text-secondary);">
      In healthcare, security is not a compliance checkbox — it is patient safety. MedCore employs multi-layered cryptographic safeguards, scoped temporal sessions, and tamper-evident audit trails.
    </p>
  </div>
</section>

<!-- PILLARS -->
<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-6">
        <div class="sec-card">
          <div class="sec-badge-icon"><i class="bi bi-key-fill"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">AES-256 GCM Encryption at Rest</h3>
          <p class="text-muted">Sensitive national identifiers (NID), diagnostic results, and clinical impressions are encrypted in storage using industry-standard AES-256 with distinct rotating keys.</p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="sec-card">
          <div class="sec-badge-icon" style="background:#ECFDF5;color:#10B981;"><i class="bi bi-stopwatch-fill"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Time-Bounded Access Sessions</h3>
          <p class="text-muted">No physician receives perpetual access to records. Every authorization creates a cryptographic session token that automatically expires after 30 minutes unless re-approved.</p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="sec-card">
          <div class="sec-badge-icon" style="background:#FEF3C7;color:#D97706;"><i class="bi bi-shield-check"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Active Revocation Override</h3>
          <p class="text-muted">Patients retain absolute sovereignty. At any moment during an active consultation, the patient can terminate access with a single click, instantly invalidating the doctor's active token.</p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="sec-card">
          <div class="sec-badge-icon" style="background:#F3E8FF;color:#9333EA;"><i class="bi bi-file-earmark-lock2-fill"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Immutable Audit Ledger</h3>
          <p class="text-muted">Every search query, medical record view, prescription issuance, and login attempt writes to an append-only audit trail recording user IP, user agent, timestamps, and context.</p>
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
