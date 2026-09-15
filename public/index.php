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
  <meta name="keywords" content="MedCore, digital health, medical records, Bangladesh, EHR, patient portal, doctor portal">
  <meta property="og:title" content="MedCore — One Record. Every Care.">
  <meta property="og:description" content="Secure digital healthcare ecosystem connecting patients, doctors and hospitals.">
  <meta name="theme-color" content="#1A73E8">
  <title>MedCore — One Record. Every Care.</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
</head>
<body>

<!-- =====================================================
     NAVBAR
     ===================================================== -->
<nav class="navbar" id="main-navbar" role="navigation" aria-label="Main navigation">
  <div class="navbar-inner">
    <a href="<?= APP_URL ?>/" class="navbar-logo" aria-label="MedCore Home">
      <div class="logo-icon" aria-hidden="true">M</div>
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
      <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-secondary btn-sm" id="btn-login">Log In</a>
      <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-sm" id="btn-get-started">Get Started</a>
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
    <div class="hero-grid"></div>
  </div>

  <div class="container">
    <div class="hero-grid-layout">
      <div class="hero-content">
        <!-- Trust bar -->
        <div class="hero-trust-bar">
          <span class="security-badge"><i class="bi bi-shield-fill-check"></i> HL7 FHIR Compliant</span>
          <span class="security-badge"><i class="bi bi-lock-fill"></i> Zero Data Leaks</span>
        </div>

        <h1 id="hero-headline" class="hero-title">
          Your Medical History.<br>
          <span class="hero-title-accent">Connected Across Every Care.</span>
        </h1>

        <p class="hero-subtitle">
          MedCore securely connects patients, verified doctors, and accredited hospital networks through one cryptographic clinical ecosystem. One synchronized record, universally trusted, entirely controlled by patient consent.
        </p>

        <div class="hero-cta">
          <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-lg" id="hero-get-started-btn">
            <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
            Get Started Free
          </a>
          <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-secondary btn-lg" id="hero-explore-btn">
            <i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i>
            Explore Platform
          </a>
        </div>

        <!-- Trust signals row -->
        <div class="hero-trust-row">
          <div class="trust-item">
            <i class="bi bi-patch-check-fill text-blue"></i>
            <span>DGDA Aligned</span>
          </div>
          <div class="trust-item">
            <i class="bi bi-shield-lock-fill text-green"></i>
            <span>AES-256 Encrypted</span>
          </div>
          <div class="trust-item">
            <i class="bi bi-clipboard2-pulse-fill text-teal"></i>
            <span>Automated Registry Sync</span>
          </div>
        </div>
      </div>

      <div class="hero-visual" aria-hidden="true">
        <!-- Live dashboard preview card -->
        <div class="dashboard-preview">
          <div class="preview-topbar">
            <div class="preview-logo">
              <div class="logo-icon" style="width:20px;height:20px;font-size:10px;">M</div>
              <span style="font-size:12px;font-weight:700;">MedCore</span>
            </div>
            <span class="badge badge-success" style="font-size:10px;">● Live</span>
          </div>

          <!-- Active portal card -->
          <div class="preview-portal-card">
            <div class="d-flex align-center gap-3" style="margin-bottom:12px;">
              <div class="sidebar-avatar" style="width:44px;height:44px;font-size:16px;">R</div>
              <div>
                <div style="font-size:14px;font-weight:700;">Rahat Mahamud</div>
                <div style="font-size:11px;color:var(--mc-text-muted);">Patient Portal</div>
              </div>
              <span class="badge badge-success" style="margin-left:auto;font-size:10px;">Active</span>
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
                <span class="preview-stat-val">5</span>
                <span class="preview-stat-label">Lab Reports</span>
              </div>
            </div>
          </div>

          <!-- Security event -->
          <div class="preview-event preview-event-blue">
            <i class="bi bi-shield-lock-fill"></i>
            <div>
              <div style="font-size:11px;font-weight:600;">Access Request</div>
              <div style="font-size:10px;color:var(--mc-text-muted);">Dr. Rahman · ABC Hospital · 26:11 remaining</div>
            </div>
            <div style="margin-left:auto;display:flex;gap:6px;">
              <span class="btn btn-success btn-sm" style="font-size:10px;padding:3px 8px;">Approve</span>
              <span class="btn btn-secondary btn-sm" style="font-size:10px;padding:3px 8px;">Deny</span>
            </div>
          </div>

          <!-- Latest Rx -->
          <div class="preview-event">
            <i class="bi bi-clipboard2-pulse text-blue"></i>
            <div>
              <div style="font-size:11px;font-weight:600;">RX-20260916-0001 · Dr. Rahman</div>
              <div style="font-size:10px;color:var(--mc-text-muted);">Metformin 500mg · Issued Today</div>
            </div>
            <span class="badge badge-info" style="font-size:10px;margin-left:auto;">Today</span>
          </div>

          <!-- Warning allergy -->
          <div class="preview-event preview-event-red">
            <i class="bi bi-exclamation-triangle-fill text-red"></i>
            <div>
              <div style="font-size:11px;font-weight:600;">Critical: Penicillin Allergy</div>
              <div style="font-size:10px;color:var(--mc-text-muted);">Clinician alert active on all prescriptions</div>
            </div>
            <span class="badge badge-error" style="font-size:10px;margin-left:auto;">Active</span>
          </div>

          <div class="preview-footer">
            <i class="bi bi-lock-fill"></i>
            <span>180ms sync · 0 unauthorized accesses · AES-256</span>
          </div>
        </div>

        <!-- Hospital verified badge floating -->
        <div class="hero-floating-card hero-float-1">
          <i class="bi bi-building-fill-check text-blue"></i>
          <div>
            <div style="font-size:11px;font-weight:700;">ABC General Hospital</div>
            <div style="font-size:10px;color:var(--mc-text-muted);">Verified · 3 affiliated doctors</div>
          </div>
        </div>

        <div class="hero-floating-card hero-float-2">
          <i class="bi bi-patch-check-fill text-green"></i>
          <div>
            <div style="font-size:11px;font-weight:700;">Dr. Farhana Rahman</div>
            <div style="font-size:10px;color:var(--mc-text-muted);">BMDC Verified · Internal Medicine</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- =====================================================
     STATS BAR
     ===================================================== -->
<div class="stats-bar" role="region" aria-label="Platform statistics">
  <div class="container">
    <div class="stats-inner">
      <div class="stat-bar-item">
        <div class="stat-bar-val" data-target="250000">0</div>
        <div class="stat-bar-label">Verified Records</div>
      </div>
      <div class="stat-bar-divider" aria-hidden="true"></div>
      <div class="stat-bar-item">
        <div class="stat-bar-val" data-target="14000">0</div>
        <div class="stat-bar-label">Certified Doctors</div>
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

<!-- =====================================================
     HOW IT WORKS
     ===================================================== -->
<section class="section" id="how-it-works" aria-labelledby="how-title">
  <div class="container text-center">
    <p class="eyebrow">Zero-Trust Clinical Architecture</p>
    <h2 id="how-title" class="section-title">Pillars of Unified Care</h2>
    <p class="section-subtitle">Built according to HL7 FHIR US Core and GDTS 13058 standards to guarantee institutional portability, diagnostic integrity, and zero friction.</p>

    <div class="pillars-grid">
      <div class="pillar-card" id="pillar-doctors">
        <div class="pillar-icon pillar-icon-blue">
          <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
        </div>
        <h3>Verified Doctors</h3>
        <p>Direct integration with the National Medical Registry ensures each doctor's identity validation, active licence status, specialisation check, and longitudinal health insurance checks.</p>
        <div class="pillar-footer">
          <span class="security-badge"><i class="bi bi-shield-check"></i> Automated Registry API</span>
        </div>
      </div>

      <div class="pillar-card" id="pillar-records">
        <div class="pillar-icon pillar-icon-teal">
          <i class="bi bi-shield-fill-lock" aria-hidden="true"></i>
        </div>
        <h3>Secure Records</h3>
        <p>Granular cryptographic patient records ensure each read of your data — protecting diagnostic charts, clinical lab panels, and longitudinal health datasets.</p>
        <div class="pillar-footer">
          <span class="security-badge"><i class="bi bi-lock"></i> SHA-256 Audit Trail</span>
        </div>
      </div>

      <div class="pillar-card" id="pillar-consent">
        <div class="pillar-icon pillar-icon-green">
          <i class="bi bi-hand-thumbs-up-fill" aria-hidden="true"></i>
        </div>
        <h3>Patient Consent</h3>
        <p>Timed, scoped access requests triggered by clinical staff. Patients receive real-time push notifications and can approve or remotely revoke clearance at any time.</p>
        <div class="pillar-footer">
          <span class="security-badge"><i class="bi bi-clock"></i> 30 Min Time-Bound Grants</span>
        </div>
      </div>

      <div class="pillar-card" id="pillar-rx">
        <div class="pillar-icon pillar-icon-amber">
          <i class="bi bi-capsule-pill" aria-hidden="true"></i>
        </div>
        <h3>Digital Prescriptions</h3>
        <p>Tamper-proof algorithmically drug safety checks, contraindication flags, and immediate dispensing authority to certified hospital or community pharmacies.</p>
        <div class="pillar-footer">
          <span class="security-badge"><i class="bi bi-building"></i> FDA & WHO Formulary Books</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- =====================================================
     SEAMLESS CONTINUITY
     ===================================================== -->
<section class="section-alt" id="continuity" aria-labelledby="continuity-title">
  <div class="container">
    <div class="continuity-layout">
      <div class="continuity-content">
        <p class="eyebrow">Active Encounter Example</p>
        <h2 id="continuity-title">Seamless Clinical Continuity in Action</h2>
        <p style="color:var(--mc-text-secondary);margin:var(--space-4) 0;">
          When patient Rahat visits Dr. Rahman at ABC Hospital, the clinical workstation issues a cryptographic challenge. Once approved on Rahat's personal phone, lifetime vitals and cardiology history populate in 180 milliseconds.
        </p>

        <!-- Performance metrics -->
        <div class="perf-metrics">
          <div class="perf-metric">
            <div class="perf-metric-val">180ms</div>
            <div class="perf-metric-label">Average EHR sync speed</div>
          </div>
          <div class="perf-metric">
            <div class="perf-metric-val" style="color:var(--mc-green);">0 Leaks</div>
            <div class="perf-metric-label">Zero control data exposures</div>
          </div>
        </div>

        <!-- Audit feed -->
        <div class="audit-feed">
          <div class="audit-item">
            <div class="audit-icon audit-icon-blue"><i class="bi bi-shield-check"></i></div>
            <div class="audit-text">
              <span class="audit-action">ACCESS GRANT</span>
              <span class="audit-detail">Rahat Mahamud → Dr. Rahman (Cardiology Follow-up · Prescription Dispensed)</span>
            </div>
            <span class="audit-time">35s ago</span>
          </div>
          <div class="audit-item">
            <div class="audit-icon audit-icon-green"><i class="bi bi-file-earmark-medical"></i></div>
            <div class="audit-text">
              <span class="audit-action">RX ISSUED</span>
              <span class="audit-detail">RX-20260916-0001 · Consultation #CONS-20260916</span>
            </div>
            <span class="audit-time">2m ago</span>
          </div>
          <div class="audit-item">
            <div class="audit-icon audit-icon-teal"><i class="bi bi-token"></i></div>
            <div class="audit-text">
              <span class="audit-action">TOKEN EXPIRED</span>
              <span class="audit-detail">30-min access session auto-closed. Clinical document secure.</span>
            </div>
            <span class="audit-time">32m ago</span>
          </div>
        </div>
      </div>

      <div class="continuity-visual" aria-hidden="true">
        <div class="continuity-card">
          <div class="d-flex align-center gap-3 mb-4">
            <div class="sidebar-avatar" style="width:52px;height:52px;font-size:20px;background:var(--mc-teal);">
              <i class="bi bi-activity" style="font-size:20px;"></i>
            </div>
            <div>
              <div style="font-weight:700;">Active Medical Record</div>
              <div style="font-size:12px;color:var(--mc-text-muted);">Session: 26:11 remaining</div>
            </div>
            <div class="access-timer" style="margin-left:auto;">
              <i class="bi bi-clock-fill"></i>
              <span class="timer-display" id="demo-timer">26:11</span>
            </div>
          </div>

          <!-- Patient info summary -->
          <div class="record-patient-info">
            <div class="d-flex align-center gap-3">
              <div class="sidebar-avatar" style="width:40px;height:40px;">R</div>
              <div>
                <div style="font-weight:700;">Rahat Mahamud</div>
                <div style="font-size:12px;color:var(--mc-text-muted);">PT-000001 · Age: 31 · Male · O+ · DOB: 15 Jun 1995</div>
              </div>
            </div>
            <div class="allergy-alert">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <strong>Penicillin Allergy</strong> — Severe / Anaphylaxis · Cross-reactive: Amoxicillin
            </div>
          </div>

          <!-- Vitals mini -->
          <div class="vitals-row">
            <div class="vital-box">
              <div class="vital-val">125/82</div>
              <div class="vital-label">Blood Pressure</div>
            </div>
            <div class="vital-box">
              <div class="vital-val">72</div>
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

          <!-- Active Rx -->
          <div class="mini-table-header">Active Medications</div>
          <div class="mini-med-row">
            <i class="bi bi-capsule text-blue"></i>
            <div>
              <div style="font-size:13px;font-weight:600;">Metformin 500mg</div>
              <div style="font-size:11px;color:var(--mc-text-muted);">Twice daily with meals · Ongoing</div>
            </div>
            <span class="badge badge-success">Active</span>
          </div>
          <div class="mini-med-row">
            <i class="bi bi-capsule text-teal"></i>
            <div>
              <div style="font-size:13px;font-weight:600;">Omeprazole 20mg</div>
              <div style="font-size:11px;color:var(--mc-text-muted);">Before meals · 4 weeks</div>
            </div>
            <span class="badge badge-success">Active</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- =====================================================
     PORTAL SELECTOR
     ===================================================== -->
<section class="section" id="portals" aria-labelledby="portal-title">
  <div class="container text-center">
    <p class="eyebrow">Tailored Role Environments</p>
    <h2 id="portal-title" class="section-title">Choose Your Portal</h2>
    <p class="section-subtitle">MedCore organizes clinical permissions according to strict role segregation. Access the exact toolset designed for your clinical position.</p>

    <div class="portals-grid">
      <!-- Patient Portal -->
      <div class="portal-feature-card" id="patient-portal-card">
        <div class="portal-icon patient-portal-icon">
          <i class="bi bi-person-heart" aria-hidden="true"></i>
        </div>
        <h3 class="portal-card-title">Patient Portal</h3>
        <p class="portal-card-desc">"My medical world belongs to me."</p>
        <ul class="portal-features">
          <li><i class="bi bi-check-circle-fill text-green"></i>Manage personal health timeline</li>
          <li><i class="bi bi-check-circle-fill text-green"></i>Approve doctor access requests</li>
          <li><i class="bi bi-check-circle-fill text-green"></i>Access laboratory diagnostics</li>
          <li><i class="bi bi-check-circle-fill text-green"></i>View & save family prescriptions</li>
          <li><i class="bi bi-check-circle-fill text-green"></i>One-tap consent approval &amp; revoke</li>
          <li><i class="bi bi-check-circle-fill text-green"></i>Global medication schedule &amp; reminders</li>
        </ul>
        <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary w-100" id="open-patient-portal">
          Open Patient Portal
        </a>
      </div>

      <!-- Doctor Portal -->
      <div class="portal-feature-card portal-featured" id="doctor-portal-card">
        <div class="portal-featured-badge">Clinical Workspace</div>
        <div class="portal-icon doctor-portal-icon">
          <i class="bi bi-stethoscope" aria-hidden="true"></i>
        </div>
        <h3 class="portal-card-title">Doctor Portal</h3>
        <p class="portal-card-desc">"I can access the information I am authorized to."</p>
        <ul class="portal-features">
          <li><i class="bi bi-check-circle-fill text-blue"></i>Search patients securely by NID or MedCore ID</li>
          <li><i class="bi bi-check-circle-fill text-blue"></i>Issue scoped access requests, receive real-time consent</li>
          <li><i class="bi bi-check-circle-fill text-blue"></i>Review consolidated vitals, and draft verified digital prescriptions</li>
          <li><i class="bi bi-check-circle-fill text-blue"></i>Global database of doctor visits &amp; scans</li>
          <li><i class="bi bi-check-circle-fill text-blue"></i>Coordinate follow-up scheduling and reminders</li>
          <li><i class="bi bi-check-circle-fill text-blue"></i>Record-level patient pharmacy views</li>
        </ul>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="btn btn-primary w-100" id="open-doctor-portal">
          Open Doctor Portal
        </a>
      </div>

      <!-- Hospital Portal -->
      <div class="portal-feature-card" id="hospital-portal-card">
        <div class="portal-icon hospital-portal-icon">
          <i class="bi bi-building-fill-cross" aria-hidden="true"></i>
        </div>
        <h3 class="portal-card-title">Hospital Portal</h3>
        <p class="portal-card-desc">"I control which doctors are affiliated with my institution."</p>
        <ul class="portal-features">
          <li><i class="bi bi-check-circle-fill text-teal"></i>Administer clinical departments, verify visiting physician rosters</li>
          <li><i class="bi bi-check-circle-fill text-teal"></i>Manage inpatient admissions and review hospital-wide cryptographic compliance audits</li>
          <li><i class="bi bi-check-circle-fill text-teal"></i>Full complete-through scheduling delegation</li>
          <li><i class="bi bi-check-circle-fill text-teal"></i>Coordinate inpatient-outpatient crossover flows</li>
          <li><i class="bi bi-check-circle-fill text-teal"></i>Record internal census billing sitting views</li>
          <li><i class="bi bi-check-circle-fill text-teal"></i>Minimal internal census billing sitting audit logging</li>
        </ul>
        <a href="<?= APP_URL ?>/hospital/login.php" class="btn btn-secondary w-100" id="open-hospital-portal">
          Open Hospital Portal
        </a>
      </div>
    </div>

    <p style="margin-top:var(--space-8);font-size:13px;color:var(--mc-text-muted);">
      System Administration &amp; Compliance Access →
      <a href="<?= APP_URL ?>/admin/login.php" style="font-size:13px;">Admin Console</a>
    </p>
  </div>
</section>

<!-- =====================================================
     SECURITY SECTION
     ===================================================== -->
<section class="section-alt" id="security" aria-labelledby="security-title">
  <div class="container text-center">
    <p class="eyebrow">Institutional-Grade Interoperability</p>
    <h2 id="security-title" class="section-title">Adhering strictly to global healthcare IT</h2>
    <p class="section-subtitle">Every element of MedCore's architecture conforms to international healthcare data standards for cross-institutional trust.</p>

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

<!-- =====================================================
     CTA SECTION
     ===================================================== -->
<section class="cta-section" aria-labelledby="cta-title">
  <div class="container">
    <div class="cta-inner">
      <div class="cta-content">
        <h2 id="cta-title">Ready to Unify Your Healthcare?</h2>
        <p>Join 250,000+ patients who already trust MedCore with their lifelong medical record. Completely free for patients.</p>
        <div class="hero-cta">
          <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-lg" id="cta-register-btn">
            <i class="bi bi-person-plus-fill"></i>
            Create Free Account
          </a>
          <a href="<?= APP_URL ?>/for-hospitals.php" class="btn btn-secondary btn-lg" style="background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.4);color:white;" id="cta-hospital-btn">
            <i class="bi bi-building-fill"></i>
            Register Hospital
          </a>
        </div>
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
        <a href="<?= APP_URL ?>/" class="navbar-logo mb-4" style="margin-bottom:var(--space-4);">
          <div class="logo-icon">M</div>
          <span>MedCore</span>
        </a>
        <p style="color:var(--mc-text-muted);font-size:14px;max-width:260px;">
          One Record. Every Care. Bangladesh's premier digital healthcare ecosystem.
        </p>
        <div class="footer-badges">
          <span class="security-badge"><i class="bi bi-shield-fill-check"></i> HIPAA Ready</span>
          <span class="security-badge"><i class="bi bi-patch-check-fill"></i> HL7 FHIR</span>
        </div>
      </div>

      <div class="footer-col">
        <h4>Platform</h4>
        <ul>
          <li><a href="<?= APP_URL ?>/how-it-works.php">How It Works</a></li>
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
        </ul>
      </div>

      <div class="footer-col">
        <h4>Legal & Support</h4>
        <ul>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">HIPAA Notice</a></li>
          <li><a href="<?= APP_URL ?>/contact.php">Contact Us</a></li>
          <li><a href="<?= APP_URL ?>/about.php">About MedCore</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© <?= date('Y') ?> MedCore Health Technologies Ltd. All rights reserved.</p>
      <p>Built with ❤️ in Bangladesh · BMDC Registered Platform</p>
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/assets/js/medcore.js"></script>
<script>
// Demo timer countdown (just visual)
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
      const step = target / 60;
      const interval = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = formatNum(Math.floor(current));
        if (current >= target) {
          el.textContent = formatNum(target) + '+';
          clearInterval(interval);
        }
      }, 20);
      observer.unobserve(el);
    });
  }, { threshold: 0.5 });

  document.querySelectorAll('[data-target]').forEach(el => observer.observe(el));
})();

// Mobile menu
document.getElementById('hamburger-btn')?.addEventListener('click', function() {
  const menu = document.getElementById('nav-menu');
  const expanded = this.getAttribute('aria-expanded') === 'true';
  this.setAttribute('aria-expanded', !expanded);
  menu.classList.toggle('mobile-open');
});
</script>
</body>
</html>
