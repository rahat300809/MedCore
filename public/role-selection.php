<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
initSession();

// If already logged in, redirect
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'SYSTEM_ADMIN') {
        header('Location: ' . APP_URL . '/admin/dashboard.php'); exit;
    } elseif ($_SESSION['role'] === 'HOSPITAL_ADMIN') {
        header('Location: ' . APP_URL . '/hospital/dashboard.php'); exit;
    } elseif ($_SESSION['role'] === 'DOCTOR') {
        header('Location: ' . APP_URL . '/doctor/dashboard.php'); exit;
    } elseif ($_SESSION['role'] === 'PATIENT') {
        header('Location: ' . APP_URL . '/patient/dashboard.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access MedCore — Choose Your Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    :root {
      --l-blue:        #2563EB;
      --l-teal:        #0891B2;
      --l-violet:      #7C3AED;
      --l-emerald:     #10B981;
      --l-text:        #0F172A;
      --l-text-sub:    #334155;
      --l-text-muted:  #64748B;
      --l-text-dim:    #94A3B8;
      --l-bg:          #F8FAFE;
      --l-blue-soft:   #EFF6FF;
      --l-teal-soft:   #ECFEFF;
      --l-violet-soft: #F5F3FF;
      --glass-bg:      rgba(255,255,255,0.78);
      --glass-border:  rgba(255,255,255,0.92);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(150deg, #F0F6FF 0%, #FAFCFF 50%, #F5FFFC 100%);
      color: #0F172A;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── ANIMATED BACKGROUND ── */
    .bg-canvas {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      overflow: hidden;
    }

    .bg-blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(120px);
      opacity: 0.5;
    }

    .bg-blob-1 {
      width: 800px; height: 800px;
      background: radial-gradient(circle, rgba(37,99,235,0.2) 0%, rgba(96,165,250,0.08) 50%, transparent 70%);
      top: -300px; left: -200px;
      animation: bgdrift 18s ease-in-out infinite;
    }
    .bg-blob-2 {
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(8,145,178,0.15) 0%, transparent 70%);
      bottom: -200px; right: -150px;
      animation: bgdrift 22s ease-in-out infinite reverse;
    }
    .bg-blob-3 {
      width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(124,58,237,0.08) 0%, transparent 70%);
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      animation: bgdrift 26s ease-in-out infinite 4s;
    }

    @keyframes bgdrift {
      0%, 100% { transform: translate(0,0) scale(1); }
      33% { transform: translate(40px,-30px) scale(1.05); }
      66% { transform: translate(-25px,20px) scale(0.96); }
    }

    .bg-dots {
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(37,99,235,0.1) 1.2px, transparent 1.2px);
      background-size: 36px 36px;
    }

    /* ECG background line */
    .bg-ecg {
      position: absolute;
      bottom: 60px;
      left: 0; right: 0;
      height: 60px;
      opacity: 0.2;
    }

    .bg-ecg-path {
      fill: none;
      stroke: #2563EB;
      stroke-width: 1.5;
      stroke-dasharray: 2000;
      stroke-dashoffset: 2000;
      animation: ecg-run 4s linear infinite;
      opacity: 0.2;
    }

    @keyframes ecg-run {
      0%   { stroke-dashoffset: 2000; }
      100% { stroke-dashoffset: 0; }
    }

    /* ── PAGE LAYOUT ── */
    .role-page {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Header */
    .role-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 32px;
      border-bottom: 1px solid rgba(37,99,235,0.08);
      background: rgba(248,250,254,0.9);
      backdrop-filter: blur(20px);
    }

    .role-header-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: #0F172A;
      font-size: 1.15rem;
      font-weight: 800;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .role-header-logo .logo-icon {
      width: 32px; height: 32px;
      background: linear-gradient(135deg, #38BDF8, #2DD4BF);
      border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; color: white;
      box-shadow: 0 0 20px rgba(56,189,248,0.4);
    }

    .role-header-back {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 18px;
      border: 1.5px solid rgba(37,99,235,0.15);
      border-radius: 10px;
      color: #64748B;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      font-family: 'Plus Jakarta Sans', sans-serif;
      transition: all 0.2s;
      background: white;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .role-header-back:hover {
      border-color: rgba(37,99,235,0.4);
      color: #2563EB;
      background: #EFF6FF;
      text-decoration: none;
    }

    /* Main content */
    .role-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 60px 24px;
    }

    .role-eyebrow {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      color: #0891B2;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .role-title {
      font-size: clamp(1.8rem, 4vw, 2.8rem);
      font-weight: 900;
      text-align: center;
      color: #0F172A;
      margin-bottom: 12px;
      letter-spacing: -0.03em;
      line-height: 1.1;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .role-subtitle {
      font-size: 14px;
      color: #64748B;
      text-align: center;
      margin-bottom: 50px;
      max-width: 460px;
      line-height: 1.65;
    }

    /* Grid */
    .role-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 22px;
      width: 100%;
      max-width: 980px;
    }

    /* Role card */
    .role-card {
      background: white;
      border: 1.5px solid rgba(37,99,235,0.1);
      border-radius: 22px;
      padding: 32px 26px 26px;
      cursor: pointer;
      transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
      text-decoration: none;
      color: #0F172A;
      display: flex;
      flex-direction: column;
      gap: 0;
      position: relative;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(37,99,235,0.06);
    }

    .role-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      border-radius: 22px 22px 0 0;
      opacity: 0;
      transition: opacity 0.3s;
    }
    .role-card.patient::before  { background: linear-gradient(90deg, #2563EB, #06B6D4); }
    .role-card.doctor::before   { background: linear-gradient(90deg, #0891B2, #10B981); }
    .role-card.hospital::before { background: linear-gradient(90deg, #7C3AED, #0891B2); }

    .role-card:hover {
      background: white;
      border-color: rgba(37,99,235,0.2);
      transform: translateY(-8px) scale(1.01);
      box-shadow: 0 24px 60px rgba(37,99,235,0.12), 0 8px 24px rgba(0,0,0,0.06);
      color: #0F172A;
      text-decoration: none;
    }
    .role-card:hover::before { opacity: 1; }

    /* Card icon */
    .role-card-icon {
      width: 68px; height: 68px;
      border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      font-size: 2rem;
      margin-bottom: 20px;
      position: relative;
      z-index: 1;
      transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1);
    }

    .role-card:hover .role-card-icon { transform: scale(1.1) rotate(-3deg); }

    .icon-patient  { background: #EFF6FF; color: #2563EB; }
    .icon-doctor   { background: linear-gradient(135deg, #EFF6FF, #ECFEFF); color: #0891B2; }
    .icon-hospital { background: #F5F3FF; color: #7C3AED; }

    .role-card h3 {
      font-size: 1.25rem;
      font-weight: 800;
      color: #0F172A;
      margin-bottom: 8px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      letter-spacing: -0.02em;
      position: relative; z-index: 1;
    }

    .role-card p {
      font-size: 13px;
      color: #64748B;
      line-height: 1.65;
      margin-bottom: 22px;
      position: relative; z-index: 1;
    }

    /* Feature list */
    .role-features {
      list-style: none;
      margin-bottom: 24px;
      position: relative; z-index: 1;
    }

    .role-features li {
      display: flex;
      align-items: center;
      gap: 9px;
      font-size: 12.5px;
      color: #475569;
      padding: 4px 0;
      font-weight: 500;
    }

    .role-features li i { font-size: 13px; flex-shrink: 0; }

    /* CTA button */
    .role-cta {
      display: block;
      text-align: center;
      padding: 14px 20px;
      border-radius: 13px;
      font-size: 14px;
      font-weight: 700;
      font-family: 'Plus Jakarta Sans', sans-serif;
      transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);
      text-decoration: none;
      position: relative; z-index: 1;
      letter-spacing: 0.1px;
    }

    .cta-patient {
      background: linear-gradient(135deg, #2563EB, #06B6D4);
      color: white;
      box-shadow: 0 6px 24px rgba(37,99,235,0.3);
      border: none;
    }
    .cta-patient:hover {
      box-shadow: 0 10px 36px rgba(37,99,235,0.4);
      transform: translateY(-2px);
      color: white; text-decoration: none;
    }

    .cta-doctor {
      background: linear-gradient(135deg, #0891B2, #10B981);
      color: white;
      box-shadow: 0 6px 24px rgba(8,145,178,0.3);
      border: none;
    }
    .cta-doctor:hover {
      box-shadow: 0 10px 36px rgba(8,145,178,0.4);
      transform: translateY(-2px);
      color: white; text-decoration: none;
    }

    .cta-hospital {
      background: linear-gradient(135deg, #7C3AED, #0891B2);
      color: white;
      box-shadow: 0 6px 24px rgba(124,58,237,0.3);
      border: none;
    }
    .cta-hospital:hover {
      box-shadow: 0 10px 36px rgba(124,58,237,0.35);
      transform: translateY(-2px);
      color: white; text-decoration: none;
    }

    /* Sub actions */
    .role-sub {
      margin-top: 10px;
      text-align: center;
      font-size: 12px;
      color: #94A3B8;
      position: relative; z-index: 1;
    }

    .patient  .role-sub a { color: #2563EB; font-weight: 600; }
    .doctor   .role-sub a { color: #0891B2; font-weight: 600; }
    .hospital .role-sub a { color: #7C3AED; font-weight: 600; }

    /* Admin link */
    .admin-access {
      margin-top: 44px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 13px;
      color: #94A3B8;
    }

    .admin-access-btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 8px 20px;
      background: white;
      border: 1.5px solid rgba(37,99,235,0.12);
      border-radius: 999px;
      color: #475569;
      font-size: 12.5px;
      font-weight: 700;
      font-family: 'Plus Jakarta Sans', sans-serif;
      text-decoration: none;
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .admin-access-btn:hover {
      background: #EFF6FF;
      border-color: rgba(37,99,235,0.3);
      color: #2563EB;
      text-decoration: none;
    }

    /* Security note */
    .security-note {
      margin-top: 24px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #94A3B8;
    }

    .security-note i { color: #10B981; font-size: 13px; }

    /* Responsive */
    @media (max-width: 900px) {
      .role-grid { grid-template-columns: 1fr; max-width: 480px; }
    }
    @media (max-width: 480px) {
      .role-header { padding: 16px 20px; }
      .role-main { padding: 40px 16px; }
    }
  </style>
</head>
<body>
<!-- Animated background -->
<div class="bg-canvas" aria-hidden="true">
  <div class="bg-blob bg-blob-1"></div>
  <div class="bg-blob bg-blob-2"></div>
  <div class="bg-blob bg-blob-3"></div>
  <div class="bg-dots"></div>
  <svg class="bg-ecg" viewBox="0 0 1400 60" preserveAspectRatio="none">
    <path class="bg-ecg-path"
      d="M0,30 L100,30 L110,30 L116,8 L122,52 L126,2 L134,58 L140,30 L200,30
         L300,30 L310,30 L316,8 L322,52 L326,2 L334,58 L340,30 L400,30
         L500,30 L510,30 L516,8 L522,52 L526,2 L534,58 L540,30 L600,30
         L700,30 L710,30 L716,8 L722,52 L726,2 L734,58 L740,30 L800,30
         L900,30 L910,30 L916,8 L922,52 L926,2 L934,58 L940,30 L1000,30
         L1100,30 L1110,30 L1116,8 L1122,52 L1126,2 L1134,58 L1140,30 L1200,30
         L1300,30 L1310,30 L1316,8 L1322,52 L1326,2 L1334,58 L1340,30 L1400,30"/>
  </svg>
</div>

<div class="role-page">
  <header class="role-header">
    <a href="<?= APP_URL ?>/" class="role-header-logo">
      <div class="logo-icon"><i class="bi bi-heart-pulse-fill" style="font-size:13px;"></i></div>
      <span>MedCore</span>
    </a>
    <a href="<?= APP_URL ?>/" class="role-header-back">
      <i class="bi bi-arrow-left"></i> Back to Home
    </a>
  </header>

  <main class="role-main">
    <div class="role-eyebrow">
      <i class="bi bi-shield-lock-fill"></i>
      One Record. Every Care.
    </div>
    <h1 class="role-title">Who Are You Accessing As?</h1>
    <p class="role-subtitle">All access is cryptographically authenticated and audit-logged. Select your role to continue to your portal.</p>

    <div class="role-grid">
      <!-- Patient -->
      <div class="role-card patient" id="patient-role-card">
        <div class="role-card-icon icon-patient">
          <i class="bi bi-person-heart"></i>
        </div>
        <h3>I'm a Patient</h3>
        <p>Access your complete health record — prescriptions, ECG reports, MRI scans, lab results, and consent management.</p>
        <ul class="role-features">
          <li><i class="bi bi-activity" style="color:#38BDF8;"></i> ECG & diagnostic reports</li>
          <li><i class="bi bi-shield-check" style="color:#38BDF8;"></i> Consent-based doctor access</li>
          <li><i class="bi bi-capsule" style="color:#38BDF8;"></i> Prescription history</li>
        </ul>
        <a href="<?= APP_URL ?>/patient/login.php" class="role-cta cta-patient" id="patient-login-btn">
          <i class="bi bi-box-arrow-in-right"></i> Patient Login
        </a>
        <div class="role-sub">New here? <a href="<?= APP_URL ?>/patient/register.php">Create free account</a></div>
      </div>

      <!-- Doctor -->
      <div class="role-card doctor" id="doctor-role-card">
        <div class="role-card-icon icon-doctor">
          <i class="bi bi-stethoscope"></i>
        </div>
        <h3>I'm a Doctor</h3>
        <p>Practice across multiple hospitals with BMDC verification. Access patient histories and issue verified digital prescriptions.</p>
        <ul class="role-features">
          <li><i class="bi bi-clipboard2-pulse" style="color:#2DD4BF;"></i> Patient ECG & MRI access</li>
          <li><i class="bi bi-building" style="color:#2DD4BF;"></i> Multi-hospital affiliation</li>
          <li><i class="bi bi-file-earmark-medical" style="color:#2DD4BF;"></i> Digital prescriptions</li>
        </ul>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="role-cta cta-doctor" id="doctor-login-btn">
          <i class="bi bi-box-arrow-in-right"></i> Doctor Portal Login
        </a>
        <div class="role-sub">New doctor? <a href="<?= APP_URL ?>/doctor/register.php">Register & BMDC verify</a></div>
      </div>

      <!-- Hospital -->
      <div class="role-card hospital" id="hospital-role-card">
        <div class="role-card-icon icon-hospital">
          <i class="bi bi-building-fill-cross"></i>
        </div>
        <h3>I'm a Hospital</h3>
        <p>Manage your doctor roster, departments, affiliation approvals, and hospital-wide clinical compliance audits.</p>
        <ul class="role-features">
          <li><i class="bi bi-people" style="color:#A78BFA;"></i> Doctor roster management</li>
          <li><i class="bi bi-graph-up" style="color:#A78BFA;"></i> Diagnostics & lab panels</li>
          <li><i class="bi bi-journal-check" style="color:#A78BFA;"></i> Compliance audit logs</li>
        </ul>
        <a href="<?= APP_URL ?>/hospital/login.php" class="role-cta cta-hospital" id="hospital-login-btn">
          <i class="bi bi-box-arrow-in-right"></i> Hospital Portal Login
        </a>
        <div class="role-sub">New hospital? <a href="<?= APP_URL ?>/hospital/register.php">Register institution</a></div>
      </div>
    </div>

    <div class="admin-access">
      <span>System Administrator?</span>
      <a href="<?= APP_URL ?>/admin/login.php" class="admin-access-btn" id="admin-console-link">
        <i class="bi bi-shield-lock-fill"></i> Central Admin Console
      </a>
    </div>

    <div class="security-note">
      <i class="bi bi-shield-fill-check"></i>
      <span>All sessions are AES-256 encrypted · BMDC registry verified · Audit-logged</span>
    </div>
  </main>
</div>
</body>
</html>
