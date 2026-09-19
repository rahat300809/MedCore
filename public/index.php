<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
initSession();

// If already logged in, redirect to their portal
if (isset($_SESSION['role'])) {
    $portals = [
        'PATIENT'        => APP_URL . '/patient/dashboard.php',
        'DOCTOR'         => APP_URL . '/doctor/dashboard.php',
        'HOSPITAL_ADMIN' => APP_URL . '/hospital/dashboard.php',
        'SYSTEM_ADMIN'   => APP_URL . '/admin/dashboard.php',
    ];
    if (isset($portals[$_SESSION['role']])) {
        header('Location: ' . $portals[$_SESSION['role']]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="MedCore — One Record. Every Care. Bangladesh's secure digital medical record ecosystem connecting patients, doctors, and hospitals through consent-based health data management.">
  <meta name="keywords" content="MedCore, digital health, medical records, Bangladesh, EHR, patient portal, doctor portal, ECG, MRI">
  <meta property="og:title" content="MedCore — One Record. Every Care.">
  <meta property="og:description" content="Secure digital healthcare ecosystem connecting patients, doctors and hospitals.">
  <meta name="theme-color" content="#050D1A">
  <title>MedCore — One Record. Every Care.</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
</head>
<body class="landing-page">

<!-- =====================================================
     NAVBAR
     ===================================================== -->
<nav class="navbar" id="main-navbar" role="navigation" aria-label="Main navigation">
  <div class="navbar-inner">
    <a href="<?= APP_URL ?>/" class="navbar-logo" aria-label="MedCore Home">
      <div class="logo-icon" aria-hidden="true">
        <i class="bi bi-heart-pulse-fill" style="font-size:15px;"></i>
      </div>
      <span>MedCore</span>
    </a>

    <ul class="navbar-nav" id="nav-menu" role="menubar">
      <li role="none"><a href="#how-it-works" role="menuitem">How It Works</a></li>
      <li role="none"><a href="<?= APP_URL ?>/for-patients.php" role="menuitem">For Patients</a></li>
      <li role="none"><a href="<?= APP_URL ?>/for-doctors.php" role="menuitem">For Doctors</a></li>
      <li role="none"><a href="<?= APP_URL ?>/for-hospitals.php" role="menuitem">For Hospitals</a></li>
      <li role="none"><a href="<?= APP_URL ?>/security.php" role="menuitem">Security</a></li>
    </ul>

    <div class="navbar-actions">
      <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-secondary btn-sm" id="btn-login">
        <i class="bi bi-box-arrow-in-right"></i> Log In
      </a>
      <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-sm" id="btn-get-started">
        <i class="bi bi-person-plus-fill"></i> Get Started
      </a>
    </div>

    <button class="hamburger" id="hamburger-btn" aria-label="Toggle navigation" aria-expanded="false">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>
  </div>
</nav>

<!-- =====================================================
     HERO SECTION
     ===================================================== -->
<section class="hero" id="hero" aria-labelledby="hero-headline">
  <div class="hero-bg" aria-hidden="true">
    <div class="hero-blob hero-blob-1"></div>
    <div class="hero-blob hero-blob-2"></div>
    <div class="hero-blob hero-blob-3"></div>
    <div class="hero-grid"></div>
    <div class="hero-ecg-bar"></div>
  </div>

  <!-- Full-width ECG line along the bottom of hero -->
  <div class="ecg-line-wrapper" aria-hidden="true">
    <svg class="ecg-svg" viewBox="0 0 1400 80" preserveAspectRatio="none">
      <!-- ECG glow layer -->
      <path class="ecg-path ecg-path-glow ecg-animate"
        d="M0,40 L80,40 L100,40 L110,10 L120,70 L130,5 L145,75 L160,40 L200,40
           L280,40 L300,40 L310,10 L320,70 L330,5 L345,75 L360,40 L400,40
           L480,40 L500,40 L510,10 L520,70 L530,5 L545,75 L560,40 L600,40
           L680,40 L700,40 L710,10 L720,70 L730,5 L745,75 L760,40 L800,40
           L880,40 L900,40 L910,10 L920,70 L930,5 L945,75 L960,40 L1000,40
           L1080,40 L1100,40 L1110,10 L1120,70 L1130,5 L1145,75 L1160,40 L1200,40
           L1280,40 L1300,40 L1310,10 L1320,70 L1330,5 L1345,75 L1360,40 L1400,40"/>
      <!-- ECG sharp line -->
      <path class="ecg-path ecg-animate" style="animation-delay: 0.2s;"
        d="M0,40 L80,40 L100,40 L110,10 L120,70 L130,5 L145,75 L160,40 L200,40
           L280,40 L300,40 L310,10 L320,70 L330,5 L345,75 L360,40 L400,40
           L480,40 L500,40 L510,10 L520,70 L530,5 L545,75 L560,40 L600,40
           L680,40 L700,40 L710,10 L720,70 L730,5 L745,75 L760,40 L800,40
           L880,40 L900,40 L910,10 L920,70 L930,5 L945,75 L960,40 L1000,40
           L1080,40 L1100,40 L1110,10 L1120,70 L1130,5 L1145,75 L1160,40 L1200,40
           L1280,40 L1300,40 L1310,10 L1320,70 L1330,5 L1345,75 L1360,40 L1400,40"/>
    </svg>
  </div>

  <div class="container">
    <div class="hero-grid-layout">
      <div class="hero-content">
        <!-- Trust bar -->
        <div class="hero-trust-bar">
          <span class="security-badge"><i class="bi bi-shield-fill-check"></i> HL7 FHIR R4 Compliant</span>
          <span class="security-badge"><i class="bi bi-lock-fill"></i> AES-256 Encrypted</span>
        </div>

        <h1 id="hero-headline" class="hero-title">
          Your Complete<br>
          Medical Universe.<br>
          <span class="hero-title-accent">One Secure Record.</span>
        </h1>

        <p class="hero-subtitle">
          MedCore unifies patient histories, ECG reports, MRI scans, prescriptions, and lab diagnostics into one cryptographic ecosystem — accessible only through patient consent.
        </p>

        <div class="hero-cta">
          <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-lg" id="hero-get-started-btn">
            <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
            Create Free Account
          </a>
          <a href="#portals" class="btn btn-secondary btn-lg" id="hero-explore-btn">
            <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
            Explore Portals
          </a>
        </div>

        <!-- Quick Login Access -->
        <div class="hero-login-panel">
          <div class="hero-login-label">
            <i class="bi bi-lightning-fill" style="color:#FBBF24;margin-right:6px;"></i>
            Quick Portal Access
          </div>
          <div class="hero-login-grid">
            <a href="<?= APP_URL ?>/patient/login.php" class="hero-login-btn patient-btn" id="quick-patient-login">
              <div class="btn-icon"><i class="bi bi-person-heart"></i></div>
              <span>Patient Login</span>
            </a>
            <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="hero-login-btn doctor-btn" id="quick-doctor-login">
              <div class="btn-icon"><i class="bi bi-stethoscope"></i></div>
              <span>Doctor Login</span>
            </a>
            <a href="<?= APP_URL ?>/hospital/login.php" class="hero-login-btn hospital-btn" id="quick-hospital-login">
              <div class="btn-icon"><i class="bi bi-building-fill-cross"></i></div>
              <span>Hospital Login</span>
            </a>
          </div>
        </div>

        <!-- Trust signals -->
        <div class="hero-trust-row" style="margin-top:24px;">
          <div class="trust-item">
            <i class="bi bi-patch-check-fill"></i>
            <span>DGDA Aligned</span>
          </div>
          <div class="trust-item">
            <i class="bi bi-heart-pulse-fill heartbeat-icon"></i>
            <span>Real-Time EHR Sync</span>
          </div>
          <div class="trust-item">
            <i class="bi bi-clipboard2-pulse-fill"></i>
            <span>Zero Data Leaks</span>
          </div>
        </div>
      </div>

      <div class="hero-visual" aria-hidden="true">
        <div class="hero-visual-inner">
          <!-- Glassmorphism Dashboard Card -->
          <div class="dashboard-preview">
            <div class="preview-topbar">
              <div class="preview-logo">
                <div class="logo-icon" style="width:20px;height:20px;font-size:9px;background:linear-gradient(135deg,#38BDF8,#2DD4BF);">
                  <i class="bi bi-heart-pulse-fill" style="font-size:9px;"></i>
                </div>
                <span style="font-size:12px;font-weight:700;color:#E2E8F0;">MedCore</span>
              </div>
              <span class="badge badge-success" style="font-size:10px;">
                <span class="pulse-dot"></span>Live
              </span>
            </div>

            <!-- Live ECG mini-readout -->
            <div class="preview-ecg">
              <svg class="preview-ecg-svg" viewBox="0 0 300 36" preserveAspectRatio="none">
                <path class="preview-ecg-path preview-ecg-animate"
                  d="M0,18 L30,18 L40,18 L45,4 L50,32 L54,2 L60,34 L66,18 L90,18
                     L120,18 L130,18 L135,4 L140,32 L144,2 L150,34 L156,18 L180,18
                     L210,18 L220,18 L225,4 L230,32 L234,2 L240,34 L246,18 L270,18 L300,18"/>
              </svg>
              <div style="position:absolute;top:4px;right:8px;font-size:9px;color:rgba(52,211,153,0.7);font-weight:600;font-family:monospace;">ECG ♥ 72bpm</div>
            </div>

            <!-- Patient card -->
            <div class="preview-portal-card">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <div class="sidebar-avatar" style="width:40px;height:40px;font-size:15px;background:linear-gradient(135deg,#38BDF8,#2DD4BF);">R</div>
                <div>
                  <div style="font-size:13px;font-weight:700;color:#E2E8F0;">Rahat Mahamud</div>
                  <div style="font-size:10px;color:rgba(226,232,240,0.45);">Patient Portal · PT-000001</div>
                </div>
                <span class="badge badge-success" style="margin-left:auto;font-size:9px;">Active</span>
              </div>

              <div class="preview-stats">
                <div class="preview-stat">
                  <span class="preview-stat-val">3</span>
                  <span class="preview-stat-label">Active Rx</span>
                </div>
                <div class="preview-stat">
                  <span class="preview-stat-val">2</span>
                  <span class="preview-stat-label">Conditions</span>
                </div>
                <div class="preview-stat">
                  <span class="preview-stat-val">8</span>
                  <span class="preview-stat-label">Lab Reports</span>
                </div>
              </div>
            </div>

            <!-- Access request -->
            <div class="preview-event preview-event-blue">
              <i class="bi bi-shield-lock-fill" style="color:#38BDF8;"></i>
              <div>
                <div style="font-size:10.5px;font-weight:700;color:#E2E8F0;">Consent Request</div>
                <div style="font-size:9.5px;color:rgba(226,232,240,0.45);">Dr. Rahman · ABC Hospital · 26:11 left</div>
              </div>
              <div style="margin-left:auto;display:flex;gap:5px;">
                <span class="badge badge-success" style="font-size:9px;padding:3px 8px;">Approve</span>
                <span style="background:rgba(255,255,255,0.06);color:rgba(226,232,240,0.5);font-size:9px;padding:3px 8px;border-radius:999px;cursor:pointer;">Deny</span>
              </div>
            </div>

            <!-- MRI report -->
            <div class="preview-event">
              <i class="bi bi-clipboard2-pulse" style="color:#38BDF8;"></i>
              <div>
                <div style="font-size:10.5px;font-weight:700;color:#E2E8F0;">MRI Brain — Report Ready</div>
                <div style="font-size:9.5px;color:rgba(226,232,240,0.45);">Dhaka Medical Imaging · Today</div>
              </div>
              <span class="badge badge-info" style="font-size:9px;margin-left:auto;">New</span>
            </div>

            <!-- Allergy alert -->
            <div class="preview-event preview-event-red">
              <i class="bi bi-exclamation-triangle-fill" style="color:#F87171;"></i>
              <div>
                <div style="font-size:10.5px;font-weight:700;color:#E2E8F0;">Alert: Penicillin Allergy</div>
                <div style="font-size:9.5px;color:rgba(226,232,240,0.45);">All clinician views flagged</div>
              </div>
              <span class="badge badge-error" style="font-size:9px;margin-left:auto;">Critical</span>
            </div>

            <div class="preview-footer">
              <i class="bi bi-lock-fill" style="color:#38BDF8;"></i>
              <span>180ms sync · AES-256 · 0 unauthorized accesses</span>
            </div>
          </div>
        </div>

        <!-- Floating badge: Hospital verified -->
        <div class="hero-floating-card hero-float-1">
          <i class="bi bi-building-fill-check" style="color:#38BDF8;"></i>
          <div>
            <div style="font-size:11px;font-weight:700;color:#E2E8F0;">ABC General Hospital</div>
            <div style="font-size:9.5px;color:rgba(226,232,240,0.45);">Verified · 12 affiliated doctors</div>
          </div>
        </div>

        <!-- Floating badge: Doctor BMDC -->
        <div class="hero-floating-card hero-float-2">
          <i class="bi bi-patch-check-fill" style="color:#2DD4BF;"></i>
          <div>
            <div style="font-size:11px;font-weight:700;color:#E2E8F0;">Dr. Farhana Rahman</div>
            <div style="font-size:9.5px;color:rgba(226,232,240,0.45);">BMDC Verified · Cardiology</div>
          </div>
        </div>

        <!-- Floating badge: MRI Scan -->
        <div class="hero-floating-card hero-float-3">
          <i class="bi bi-activity" style="color:#A78BFA;font-size:20px;"></i>
          <div>
            <div style="font-size:11px;font-weight:700;color:#E2E8F0;">MRI Scan Uploaded</div>
            <div style="font-size:9.5px;color:rgba(226,232,240,0.45);">Brain · T2 Weighted</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="medical-divider"></div>

<!-- =====================================================
     STATS BAR
     ===================================================== -->
<div class="stats-bar" role="region" aria-label="Platform statistics">
  <div class="container">
    <div class="stats-inner">
      <div class="stat-bar-item">
        <div class="stat-bar-val" data-target="250000">0</div>
        <div class="stat-bar-label">Verified Patient Records</div>
      </div>
      <div class="stat-bar-divider" aria-hidden="true"></div>
      <div class="stat-bar-item">
        <div class="stat-bar-val" data-target="14000">0</div>
        <div class="stat-bar-label">BMDC Certified Doctors</div>
      </div>
      <div class="stat-bar-divider" aria-hidden="true"></div>
      <div class="stat-bar-item">
        <div class="stat-bar-val" data-target="420">0</div>
        <div class="stat-bar-label">Accredited Hospitals</div>
      </div>
      <div class="stat-bar-divider" aria-hidden="true"></div>
      <div class="stat-bar-item">
        <div class="stat-bar-val">100%</div>
        <div class="stat-bar-label">Patient Consent Driven</div>
      </div>
    </div>
  </div>
</div>

<div class="medical-divider"></div>

<!-- =====================================================
     HOW IT WORKS / PILLARS
     ===================================================== -->
<section class="section" id="how-it-works" aria-labelledby="how-title">
  <div class="container text-center">
    <p class="eyebrow">Zero-Trust Clinical Architecture</p>
    <h2 id="how-title" class="section-title" style="color:#F8FAFC;">Pillars of Unified Healthcare</h2>
    <p class="section-subtitle">Built according to HL7 FHIR R4 and GDTS 13058 standards — ECG, MRI, pathology, prescriptions, and consent all in one cryptographic platform.</p>

    <div class="pillars-grid">
      <div class="pillar-card" id="pillar-doctors">
        <div class="pillar-icon pillar-icon-blue">
          <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
        </div>
        <h3>Verified Doctors</h3>
        <p>Direct integration with the National Medical Registry validates BMDC identity, active licence status, specialisation, and hospital affiliations in real time.</p>
        <div class="pillar-footer">
          <span class="security-badge"><i class="bi bi-shield-check"></i> Automated Registry API</span>
        </div>
      </div>

      <div class="pillar-card" id="pillar-records">
        <div class="pillar-icon pillar-icon-teal">
          <i class="bi bi-activity" aria-hidden="true"></i>
        </div>
        <h3>ECG & Diagnostics</h3>
        <p>Upload and access ECG traces, MRI scans, X-rays, ultrasound reports, and full lab panels — all cryptographically sealed and indexed by encounter date.</p>
        <div class="pillar-footer">
          <span class="security-badge"><i class="bi bi-clipboard2-pulse"></i> DICOM Compatible</span>
        </div>
      </div>

      <div class="pillar-card" id="pillar-consent">
        <div class="pillar-icon pillar-icon-green">
          <i class="bi bi-hand-thumbs-up-fill" aria-hidden="true"></i>
        </div>
        <h3>Patient Consent</h3>
        <p>Time-bound access requests with push notifications. Patients approve or revoke doctor access from their phone — no more paperwork, no more privacy leaks.</p>
        <div class="pillar-footer">
          <span class="security-badge"><i class="bi bi-clock"></i> 30-Min Time-Bound Grants</span>
        </div>
      </div>

      <div class="pillar-card" id="pillar-rx">
        <div class="pillar-icon pillar-icon-amber">
          <i class="bi bi-capsule-pill" aria-hidden="true"></i>
        </div>
        <h3>Digital Prescriptions</h3>
        <p>Tamper-proof e-prescriptions with automated drug interaction checks, allergy flags, and dispensing authority for certified hospital and community pharmacies.</p>
        <div class="pillar-footer">
          <span class="security-badge"><i class="bi bi-building"></i> WHO Formulary Verified</span>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="medical-divider"></div>

<!-- =====================================================
     SEAMLESS CONTINUITY
     ===================================================== -->
<section class="section-alt" id="continuity" aria-labelledby="continuity-title">
  <div class="container">
    <div class="continuity-layout">
      <div class="continuity-content">
        <p class="eyebrow">Active Encounter Example</p>
        <h2 id="continuity-title" style="color:#F8FAFC;">Seamless Clinical Continuity in Action</h2>
        <p>
          When patient Rahat visits Dr. Rahman at ABC Hospital, the clinical workstation issues a cryptographic consent challenge. Once approved on Rahat's phone, the complete clinical history — including ECG traces, MRI scans, and vitals — populates in 180 milliseconds.
        </p>

        <div class="perf-metrics">
          <div class="perf-metric">
            <div class="perf-metric-val">180ms</div>
            <div class="perf-metric-label">Average EHR sync speed</div>
          </div>
          <div class="perf-metric">
            <div class="perf-metric-val" style="color:#34D399;text-shadow:0 0 15px rgba(52,211,153,0.5);">0 Leaks</div>
            <div class="perf-metric-label">Zero unauthorized data exposures</div>
          </div>
        </div>

        <div class="audit-feed">
          <div class="audit-item">
            <div class="audit-icon audit-icon-blue"><i class="bi bi-shield-check"></i></div>
            <div class="audit-text">
              <span class="audit-action">Access Grant</span>
              <span class="audit-detail">Rahat Mahamud → Dr. Rahman (Cardiology Follow-up · Rx Dispensed)</span>
            </div>
            <span class="audit-time">35s ago</span>
          </div>
          <div class="audit-item">
            <div class="audit-icon audit-icon-green"><i class="bi bi-activity"></i></div>
            <div class="audit-text">
              <span class="audit-action">ECG Report Synced</span>
              <span class="audit-detail">12-Lead ECG · Rate 72 bpm · Normal Sinus Rhythm</span>
            </div>
            <span class="audit-time">1m ago</span>
          </div>
          <div class="audit-item">
            <div class="audit-icon audit-icon-teal"><i class="bi bi-file-earmark-medical"></i></div>
            <div class="audit-text">
              <span class="audit-action">Rx Issued</span>
              <span class="audit-detail">RX-20260916-0001 · Metformin 500mg · Consultation #CONS-0916</span>
            </div>
            <span class="audit-time">2m ago</span>
          </div>
          <div class="audit-item">
            <div class="audit-icon" style="background:rgba(167,139,250,0.12);color:#A78BFA;"><i class="bi bi-token"></i></div>
            <div class="audit-text">
              <span class="audit-action">Session Expired</span>
              <span class="audit-detail">30-min access token auto-revoked. Clinical data secured.</span>
            </div>
            <span class="audit-time">32m ago</span>
          </div>
        </div>
      </div>

      <div class="continuity-visual" aria-hidden="true">
        <div class="continuity-card">
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;">
            <div class="sidebar-avatar" style="width:50px;height:50px;font-size:18px;background:linear-gradient(135deg,#38BDF8,#2DD4BF);">
              <i class="bi bi-heart-pulse-fill" style="font-size:18px;"></i>
            </div>
            <div>
              <div style="font-weight:800;color:#F1F5F9;font-size:15px;">Active Medical Record</div>
              <div style="font-size:11.5px;color:rgba(226,232,240,0.45);">Session: Time-bound access</div>
            </div>
            <div class="access-timer" style="margin-left:auto;">
              <i class="bi bi-clock-fill"></i>
              <span class="timer-display" id="demo-timer">26:11</span>
            </div>
          </div>

          <!-- Patient summary -->
          <div class="record-patient-info">
            <div style="display:flex;align-items:center;gap:12px;">
              <div class="sidebar-avatar" style="width:38px;height:38px;background:linear-gradient(135deg,#38BDF8,#2DD4BF);">R</div>
              <div>
                <div style="font-weight:700;color:#F1F5F9;">Rahat Mahamud</div>
                <div style="font-size:11px;color:rgba(226,232,240,0.45);">PT-000001 · Age 31 · Male · O+ · 15 Jun 1995</div>
              </div>
            </div>
            <div class="allergy-alert">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <strong>Penicillin Allergy</strong> — Severe/Anaphylaxis · Cross-reactive: Amoxicillin
            </div>
          </div>

          <!-- Vitals -->
          <div class="vitals-row">
            <div class="vital-box">
              <div class="vital-val">125/82</div>
              <div class="vital-label">Blood Pressure</div>
            </div>
            <div class="vital-box">
              <div class="vital-val">72 <i class="bi bi-heart-fill heartbeat-icon" style="font-size:9px;"></i></div>
              <div class="vital-label">Heart Rate</div>
            </div>
            <div class="vital-box">
              <div class="vital-val">118</div>
              <div class="vital-label">FBS mg/dL</div>
            </div>
            <div class="vital-box">
              <div class="vital-val">36.8°</div>
              <div class="vital-label">Temperature</div>
            </div>
          </div>

          <!-- Medications -->
          <div class="mini-table-header">Active Medications</div>
          <div class="mini-med-row">
            <i class="bi bi-capsule" style="color:#38BDF8;"></i>
            <div>
              <div style="font-weight:600;color:#E2E8F0;">Metformin 500mg</div>
              <div style="font-size:11px;color:rgba(226,232,240,0.4);">Twice daily · Ongoing</div>
            </div>
            <span class="badge badge-success" style="margin-left:auto;">Active</span>
          </div>
          <div class="mini-med-row">
            <i class="bi bi-capsule" style="color:#2DD4BF;"></i>
            <div>
              <div style="font-weight:600;color:#E2E8F0;">Omeprazole 20mg</div>
              <div style="font-size:11px;color:rgba(226,232,240,0.4);">Before meals · 4 weeks</div>
            </div>
            <span class="badge badge-success" style="margin-left:auto;">Active</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="medical-divider"></div>

<!-- =====================================================
     PORTAL SELECTOR
     ===================================================== -->
<section class="section" id="portals" aria-labelledby="portal-title">
  <div class="container text-center">
    <p class="eyebrow">Tailored Role Environments</p>
    <h2 id="portal-title" class="section-title" style="color:#F8FAFC;">Choose Your Portal</h2>
    <p class="section-subtitle">MedCore organizes clinical permissions by strict role segregation. Access the exact toolset designed for your clinical position.</p>

    <div class="portals-grid">
      <!-- Patient Portal -->
      <div class="portal-feature-card" id="patient-portal-card">
        <div class="portal-icon patient-portal-icon">
          <i class="bi bi-person-heart" aria-hidden="true"></i>
        </div>
        <h3 class="portal-card-title">Patient Portal</h3>
        <p class="portal-card-desc">"My complete health record, in my hands."</p>
        <ul class="portal-features">
          <li><i class="bi bi-check-circle-fill" style="color:#34D399;"></i>Lifelong ECG, MRI & lab history</li>
          <li><i class="bi bi-check-circle-fill" style="color:#34D399;"></i>Approve / revoke doctor access</li>
          <li><i class="bi bi-check-circle-fill" style="color:#34D399;"></i>Download laboratory diagnostics</li>
          <li><i class="bi bi-check-circle-fill" style="color:#34D399;"></i>View & manage prescriptions</li>
          <li><i class="bi bi-check-circle-fill" style="color:#34D399;"></i>One-tap consent approval</li>
          <li><i class="bi bi-check-circle-fill" style="color:#34D399;"></i>Medication reminders & schedule</li>
        </ul>
        <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary w-100" id="open-patient-portal">
          <i class="bi bi-person-heart"></i> Open Patient Portal
        </a>
        <div style="margin-top:10px;text-align:center;font-size:12px;color:rgba(226,232,240,0.35);">
          Already registered? <a href="<?= APP_URL ?>/patient/login.php" style="color:#38BDF8;">Log in here</a>
        </div>
      </div>

      <!-- Doctor Portal (featured) -->
      <div class="portal-feature-card portal-featured" id="doctor-portal-card">
        <div class="portal-featured-badge">Clinical Workspace</div>
        <div class="portal-icon doctor-portal-icon">
          <i class="bi bi-stethoscope" aria-hidden="true"></i>
        </div>
        <h3 class="portal-card-title">Doctor Portal</h3>
        <p class="portal-card-desc">"I access only what I am authorized to see."</p>
        <ul class="portal-features">
          <li><i class="bi bi-check-circle-fill" style="color:#2DD4BF;"></i>Search patients by NID or MedCore ID</li>
          <li><i class="bi bi-check-circle-fill" style="color:#2DD4BF;"></i>Issue scoped access requests</li>
          <li><i class="bi bi-check-circle-fill" style="color:#2DD4BF;"></i>Review ECG, MRI & vitals history</li>
          <li><i class="bi bi-check-circle-fill" style="color:#2DD4BF;"></i>Write & sign digital prescriptions</li>
          <li><i class="bi bi-check-circle-fill" style="color:#2DD4BF;"></i>Multi-hospital affiliation support</li>
          <li><i class="bi bi-check-circle-fill" style="color:#2DD4BF;"></i>Full consultation audit trail</li>
        </ul>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="btn btn-primary w-100" id="open-doctor-portal">
          <i class="bi bi-stethoscope"></i> Open Doctor Portal
        </a>
        <div style="margin-top:10px;text-align:center;font-size:12px;color:rgba(226,232,240,0.35);">
          New doctor? <a href="<?= APP_URL ?>/doctor/register.php" style="color:#2DD4BF;">Register & verify BMDC</a>
        </div>
      </div>

      <!-- Hospital Portal -->
      <div class="portal-feature-card" id="hospital-portal-card">
        <div class="portal-icon hospital-portal-icon">
          <i class="bi bi-building-fill-cross" aria-hidden="true"></i>
        </div>
        <h3 class="portal-card-title">Hospital Portal</h3>
        <p class="portal-card-desc">"Full institutional visibility and compliance control."</p>
        <ul class="portal-features">
          <li><i class="bi bi-check-circle-fill" style="color:#A78BFA;"></i>Manage affiliated doctor rosters</li>
          <li><i class="bi bi-check-circle-fill" style="color:#A78BFA;"></i>Approve affiliation requests</li>
          <li><i class="bi bi-check-circle-fill" style="color:#A78BFA;"></i>Administer clinical departments</li>
          <li><i class="bi bi-check-circle-fill" style="color:#A78BFA;"></i>Diagnostics & lab management</li>
          <li><i class="bi bi-check-circle-fill" style="color:#A78BFA;"></i>Hospital-wide audit logging</li>
          <li><i class="bi bi-check-circle-fill" style="color:#A78BFA;"></i>Doctor profile verification</li>
        </ul>
        <a href="<?= APP_URL ?>/hospital/login.php" class="btn btn-secondary w-100" id="open-hospital-portal">
          <i class="bi bi-building-fill-cross"></i> Open Hospital Portal
        </a>
        <div style="margin-top:10px;text-align:center;font-size:12px;color:rgba(226,232,240,0.35);">
          New hospital? <a href="<?= APP_URL ?>/hospital/register.php" style="color:#A78BFA;">Register institution</a>
        </div>
      </div>
    </div>

    <p style="margin-top:40px;font-size:13px;color:rgba(226,232,240,0.3);">
      System Administration &amp; Compliance →
      <a href="<?= APP_URL ?>/admin/login.php" style="font-size:13px;color:rgba(226,232,240,0.5);">Admin Console</a>
    </p>
  </div>
</section>

<div class="medical-divider"></div>

<!-- =====================================================
     SECURITY / STANDARDS SECTION
     ===================================================== -->
<section class="section-alt" id="security" aria-labelledby="security-title">
  <div class="container text-center">
    <p class="eyebrow">Institutional-Grade Interoperability</p>
    <h2 id="security-title" class="section-title" style="color:#F8FAFC;">Global Healthcare IT Standards</h2>
    <p class="section-subtitle">Every element of MedCore's architecture conforms to international healthcare data standards for cross-institutional trust and patient safety.</p>

    <div class="standards-grid">
      <div class="standard-card">
        <div class="standard-icon"><i class="bi bi-patch-check-fill"></i></div>
        <div class="standard-name">HL7 FHIR R4</div>
        <div class="standard-desc">Fast Healthcare Interoperability Resources</div>
      </div>
      <div class="standard-card">
        <div class="standard-icon"><i class="bi bi-shield-fill-check"></i></div>
        <div class="standard-name">HIPAA Ready</div>
        <div class="standard-desc">Privacy Rule compliant architecture</div>
      </div>
      <div class="standard-card">
        <div class="standard-icon"><i class="bi bi-file-earmark-lock2-fill"></i></div>
        <div class="standard-name">ISO 27001</div>
        <div class="standard-desc">Information Security Management</div>
      </div>
      <div class="standard-card">
        <div class="standard-icon"><i class="bi bi-cpu-fill"></i></div>
        <div class="standard-name">SOC 2 Type II</div>
        <div class="standard-desc">Trust Services Criteria</div>
      </div>
      <div class="standard-card">
        <div class="standard-icon"><i class="bi bi-globe2"></i></div>
        <div class="standard-name">SNOMED CT</div>
        <div class="standard-desc">Clinical terminology standard</div>
      </div>
      <div class="standard-card">
        <div class="standard-icon"><i class="bi bi-diagram-3-fill"></i></div>
        <div class="standard-name">ICD-11</div>
        <div class="standard-desc">WHO Diagnostic Classification</div>
      </div>
    </div>
  </div>
</section>

<div class="medical-divider"></div>

<!-- =====================================================
     CTA SECTION
     ===================================================== -->
<section class="cta-section" aria-labelledby="cta-title">
  <div class="container">
    <div class="cta-inner">
      <h2 id="cta-title">Ready to Unify Your Healthcare?</h2>
      <p>Join 250,000+ patients who trust MedCore with their lifelong medical record.<br>Free for patients, always.</p>
      <div class="hero-cta" style="justify-content:center;">
        <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-lg" id="cta-register-btn">
          <i class="bi bi-person-plus-fill"></i>
          Create Free Account
        </a>
        <a href="<?= APP_URL ?>/hospital/register.php" class="btn btn-secondary btn-lg" style="border-color:rgba(56,189,248,0.3);color:rgba(226,232,240,0.8);" id="cta-hospital-btn">
          <i class="bi bi-building-fill"></i>
          Register Hospital
        </a>
      </div>
    </div>
  </div>
</section>

<!-- =====================================================
     FOOTER
     ===================================================== -->
<footer class="site-footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="<?= APP_URL ?>/" class="navbar-logo" style="margin-bottom:16px;display:inline-flex;">
          <div class="logo-icon" style="background:linear-gradient(135deg,#38BDF8,#2DD4BF);">
            <i class="bi bi-heart-pulse-fill" style="font-size:14px;"></i>
          </div>
          <span>MedCore</span>
        </a>
        <p style="color:rgba(226,232,240,0.35);font-size:13.5px;max-width:260px;line-height:1.65;margin-top:10px;">
          One Record. Every Care. Bangladesh's premier digital healthcare ecosystem connecting patients, doctors, and hospitals.
        </p>
        <div class="footer-badges">
          <span class="security-badge"><i class="bi bi-shield-fill-check"></i> HIPAA Ready</span>
          <span class="security-badge"><i class="bi bi-patch-check-fill"></i> HL7 FHIR</span>
        </div>
      </div>

      <div class="footer-col">
        <h4>Platform</h4>
        <ul>
          <li><a href="<?= APP_URL ?>/#how-it-works">How It Works</a></li>
          <li><a href="<?= APP_URL ?>/security.php">Security</a></li>
          <li><a href="<?= APP_URL ?>/for-patients.php">For Patients</a></li>
          <li><a href="<?= APP_URL ?>/for-doctors.php">For Doctors</a></li>
          <li><a href="<?= APP_URL ?>/for-hospitals.php">For Hospitals</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Account</h4>
        <ul>
          <li><a href="<?= APP_URL ?>/patient/register.php">Patient Registration</a></li>
          <li><a href="<?= APP_URL ?>/patient/login.php">Patient Login</a></li>
          <li><a href="<?= APP_URL ?>/doctor/select-hospital.php">Doctor Login</a></li>
          <li><a href="<?= APP_URL ?>/hospital/login.php">Hospital Login</a></li>
          <li><a href="<?= APP_URL ?>/doctor/register.php">Doctor Registration</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Legal &amp; Support</h4>
        <ul>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">HIPAA Notice</a></li>
          <li><a href="<?= APP_URL ?>/admin/login.php">Admin Console</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© <?= date('Y') ?> MedCore Health Technologies Ltd. All rights reserved.</p>
      <p>Built with <i class="bi bi-heart-fill heartbeat-icon" style="font-size:11px;"></i> in Bangladesh · BMDC Registered Platform</p>
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
// Demo timer countdown (visual only)
(function() {
  let secs = 26 * 60 + 11;
  const el = document.getElementById('demo-timer');
  if (!el) return;
  setInterval(() => {
    if (secs > 0) secs--;
    const m = Math.floor(secs / 60).toString().padStart(2,'0');
    const s = (secs % 60).toString().padStart(2,'0');
    el.textContent = m + ':' + s;
    if (secs < 300) el.parentElement.classList.add('critical');
  }, 1000);
})();

// Stats counter animation
(function() {
  const formatNum = (n) => n >= 1000 ? (n/1000).toFixed(0)+'K+' : n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.dataset.target);
      if (!target) return;
      let current = 0;
      const step = target / 80;
      const interval = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = formatNum(Math.floor(current));
        if (current >= target) {
          el.textContent = formatNum(target) + '+';
          clearInterval(interval);
        }
      }, 18);
      observer.unobserve(el);
    });
  }, { threshold: 0.4 });
  document.querySelectorAll('[data-target]').forEach(el => observer.observe(el));
})();

// Mobile menu
document.getElementById('hamburger-btn')?.addEventListener('click', function() {
  const menu = document.getElementById('nav-menu');
  const expanded = this.getAttribute('aria-expanded') === 'true';
  this.setAttribute('aria-expanded', !expanded);
  menu.classList.toggle('mobile-open');
});

// Subtle parallax on hero visual
(function() {
  const visual = document.querySelector('.hero-visual-inner');
  if (!visual) return;
  document.addEventListener('mousemove', (e) => {
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;
    const rx = ((e.clientY - cy) / cy) * 4;
    const ry = -((e.clientX - cx) / cx) * 6;
    visual.style.transform = `perspective(1200px) rotateX(${rx}deg) rotateY(${ry}deg)`;
  });
  document.addEventListener('mouseleave', () => {
    visual.style.transform = 'perspective(1200px) rotateY(-8deg) rotateX(3deg)';
  });
})();
</script>
</body>
</html>
