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
  <title>For Hospitals &amp; Clinics — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
  <style>
    .page-hero {
      padding: 100px 0 60px;
      text-align: center;
      background: linear-gradient(180deg, #F0FDFA 0%, var(--mc-bg) 100%);
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
      border-color: var(--mc-teal);
    }
    .feature-icon {
      width: 56px;
      height: 56px;
      border-radius: var(--radius-lg);
      background: #F0FDFA;
      color: var(--mc-teal);
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
      <li><a href="<?= APP_URL ?>/for-doctors.php">For Doctors</a></li>
      <li><a href="<?= APP_URL ?>/for-hospitals.php" style="color: var(--mc-blue); font-weight: 700;">For Hospitals</a></li>
      <li><a href="<?= APP_URL ?>/security.php">Security</a></li>
    </ul>
    <div class="navbar-actions">
      <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-secondary btn-sm">Log In</a>
      <a href="<?= APP_URL ?>/hospital/login.php" class="btn btn-primary btn-sm">Hospital Portal</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="page-hero">
  <div class="container">
    <span class="security-badge" style="background: #CCFBF1; color: #0F766E; border-color: #99F6E4;">
      <i class="bi bi-hospital-fill"></i> DGHS &amp; Accreditation-Ready Infrastructure
    </span>
    <h1 style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; margin: 16px 0 12px; color: var(--mc-text);">
      Modernize Your Healthcare Network.<br>
      <span style="color: var(--mc-teal);">Connected Clinical Governance.</span>
    </h1>
    <p style="max-width: 680px; margin: 0 auto 32px; font-size: 1.1rem; color: var(--mc-text-secondary);">
      Manage affiliated physicians, departments, consultations, and accreditation records effortlessly. Eliminate interoperability silos and join Bangladesh's premier medical data network.
    </p>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="<?= APP_URL ?>/hospital/register.php" class="btn btn-primary btn-lg">
        <i class="bi bi-building-add"></i> Register Your Hospital
      </a>
      <a href="<?= APP_URL ?>/hospital/login.php" class="btn btn-secondary btn-lg">
        <i class="bi bi-box-arrow-in-right"></i> Hospital Admin Login
      </a>
    </div>
  </div>
</section>

<!-- FEATURES GRID -->
<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-person-check"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Doctor Credentialing</h3>
          <p class="text-muted">Review, approve, or revoke physician affiliations in real-time. Guarantee that every doctor consulting under your hospital banner holds verified credentials.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-diagram-3"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Departmental Hierarchy</h3>
          <p class="text-muted">Organize outpatient departments (OPDs), specialty wards, and diagnostic units. Track active doctor counts and clinical load per department.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-journal-text"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Institutional Consultations</h3>
          <p class="text-muted">Maintain a comprehensive institutional log of all consultations, digital prescriptions, and patient interactions conducted at your facility.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-file-earmark-lock"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Zero Liability Risk</h3>
          <p class="text-muted">MedCore's explicit consent architecture ensures that access is granted directly by the patient to the attending doctor, shielding the hospital from unauthorized disclosure.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-cpu"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Turnkey Cloud EHR</h3>
          <p class="text-muted">No costly on-premises server racks or complex database upgrades needed. MedCore runs seamlessly across all web browsers and hospital terminals.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
          <h3 style="font-size: 1.25rem; font-weight: 700;">Audit &amp; Compliance Ready</h3>
          <p class="text-muted">Instant exportable audit trails for regulatory inspections, health ministry reviews, and clinical quality compliance assurance.</p>
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
