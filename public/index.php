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
        'LABORATORY'     => APP_URL . '/lab/dashboard.php',
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
  <meta name="description" content="MedCore — Centralized Digital Healthcare Network. Seamless doctor booking, lifetime medical records, and diagnostic lab sync across Bangladesh.">
  <title>MedCore — One Record. Every Care.</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
  <style>
    /* Clean White Theme Custom Styles */
    body.landing-page {
      background: #FFFFFF !important;
      color: #0F172A !important;
      font-family: 'Plus Jakarta Sans', var(--font-main);
    }
    .hero-clean {
      padding: 70px 0 60px;
      background: radial-gradient(circle at 85% 15%, rgba(2, 132, 199, 0.07) 0%, #FFFFFF 65%, #F8FAFC 100%);
      border-bottom: 1px solid #E2E8F0;
    }
    .hero-grid {
      display: grid;
      grid-template-columns: 1.15fr 0.85fr;
      gap: 48px;
      align-items: center;
    }
    .role-quick-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 14px;
      padding: 16px;
      text-decoration: none;
      color: inherit;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
      transition: all 0.2s ease;
    }
    .role-quick-card:hover {
      border-color: #0284C7;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(2, 132, 199, 0.12);
    }
    .feature-card-clean {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 18px;
      padding: 28px 24px;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.03);
      transition: all 0.25s ease;
    }
    .feature-card-clean:hover {
      border-color: #93C5FD;
      box-shadow: 0 10px 25px rgba(37, 99, 235, 0.08);
      transform: translateY(-4px);
    }
    .portal-box-clean {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 18px;
      padding: 30px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
      transition: all 0.25s ease;
    }
    .portal-box-clean:hover {
      border-color: #38BDF8;
      box-shadow: 0 12px 30px rgba(2, 132, 199, 0.12);
      transform: translateY(-4px);
    }
    @media (max-width: 980px) {
      .hero-grid {
        grid-template-columns: 1fr;
        gap: 36px;
      }
      .hero-visual-col {
        order: -1;
      }
    }
  </style>
</head>
<body class="landing-page">

<!-- =====================================================
     NAVBAR (CLEAN, SPACIOUS & PROPERLY ARRANGED)
     ===================================================== -->
<nav class="navbar" id="main-navbar" role="navigation" aria-label="Main navigation">
  <div class="navbar-inner">
    
    <!-- Logo -->
    <a href="<?= APP_URL ?>/" class="navbar-logo" aria-label="MedCore Home">
      <div class="logo-icon" aria-hidden="true">
        <i class="bi bi-heart-pulse-fill"></i>
      </div>
      <span>MedCore</span>
    </a>

    <!-- Clean Navigation Links (No wrapping, no duplicate Book Doctor) -->
    <ul class="navbar-nav" id="nav-menu" role="menubar">
      <li role="none"><a href="#services" role="menuitem">সেবাসমূহ (Services)</a></li>
      <li role="none"><a href="#portals" role="menuitem">পোর্টালসমূহ (Portals)</a></li>
      <li role="none"><a href="<?= APP_URL ?>/for-patients.php" role="menuitem">For Patients</a></li>
      <li role="none"><a href="<?= APP_URL ?>/for-doctors.php" role="menuitem">For Doctors</a></li>
      <li role="none"><a href="<?= APP_URL ?>/for-hospitals.php" role="menuitem">For Hospitals</a></li>
      <li role="none"><a href="<?= APP_URL ?>/security.php" role="menuitem">Security</a></li>
    </ul>

    <!-- Action Buttons (Neatly Arranged & Aligned) -->
    <div class="navbar-actions">
      <a href="<?= APP_URL ?>/book-doctor.php" class="btn btn-sm" id="btn-book-nav" style="background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
        <i class="bi bi-calendar2-check-fill text-primary"></i> ডাক্তার সিরিয়াল
      </a>
      <a href="<?= APP_URL ?>/emergency.php" class="btn btn-sm" id="btn-emergency-nav" style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA; font-weight:700;" title="Emergency Profile">
        <i class="bi bi-shield-exclamation text-danger"></i> ICE
      </a>
      <a href="<?= APP_URL ?>/role-selection.php" class="btn btn-secondary btn-sm" id="btn-login" style="font-weight:600; border-color:#CBD5E1;">
        <i class="bi bi-box-arrow-in-right"></i> Log In
      </a>
      <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-primary btn-sm" id="btn-get-started" style="font-weight:700; background:linear-gradient(135deg, #0284c7, #2563eb);">
        <i class="bi bi-person-plus-fill"></i> Get Started
      </a>
    </div>

    <!-- Mobile Hamburger Toggle -->
    <button class="hamburger" id="hamburger-btn" aria-label="Toggle navigation" aria-expanded="false">
      <i class="bi bi-list" aria-hidden="true" style="font-size:22px;"></i>
    </button>
  </div>
</nav>

<!-- =====================================================
     HERO SECTION (WHITE THEME — CLEAN & HIGH IMPACT)
     ===================================================== -->
<section class="hero-clean" id="hero">
  <div class="container" style="max-width:1320px; margin:0 auto; padding:0 24px;">
    <div class="hero-grid">
      
      <!-- Left Column: Copy & Actions -->
      <div>
        <div style="display:inline-flex; align-items:center; gap:8px; background:#EFF6FF; border:1px solid #DBEAFE; padding:5px 14px; border-radius:999px; font-size:12px; font-weight:700; color:#1D4ED8; margin-bottom:16px;">
          <i class="bi bi-shield-fill-check text-primary"></i> DGDA Aligned · AES-256 Encrypted · 100% Patient Consent
        </div>

        <h1 style="font-size:clamp(32px, 4.2vw, 52px); font-weight:900; line-height:1.15; color:#0F172A; margin:0 0 16px; letter-spacing:-0.03em;">
          Your Complete Medical History.<br>
          <span style="color:#0284C7;">One Unified Digital Record.</span>
        </h1>

        <p style="font-size:16px; color:#475569; line-height:1.6; max-width:580px; margin:0 0 28px;">
          রোগী, বিশেষজ্ঞ ডাক্তার, হাসপাতাল এবং ডায়াগনস্টিক ল্যাব—সবাই একই সেন্ট্রালাইজড প্ল্যাটফর্মে সংযুক্ত। সহজে ডাক্তার সিরিয়াল বুক করুন এবং আজীবন স্বাস্থ্য ইতিহাস নিরাপদে সংরক্ষণ করুন।
        </p>

        <!-- Main Hero CTAs -->
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:36px;">
          <a href="<?= APP_URL ?>/book-doctor.php" class="btn btn-lg" style="background:linear-gradient(135deg, #0284c7 0%, #1e40af 100%); color:#fff; font-weight:800; border-radius:12px; padding:14px 26px; box-shadow:0 8px 24px rgba(37,99,235,0.3); display:inline-flex; align-items:center; gap:8px;">
            <i class="bi bi-calendar2-check-fill"></i>
            অনলাইন ডাক্তার সিরিয়াল বুক করুন
          </a>
          <a href="<?= APP_URL ?>/patient/register.php" class="btn btn-secondary btn-lg" style="background:#fff; color:#0F172A; font-weight:700; border:1px solid #CBD5E1; border-radius:12px; padding:14px 22px;">
            <i class="bi bi-person-plus-fill"></i> ফ্রি অ্যাকাউন্ট খুলুন
          </a>
        </div>

        <!-- Quick 4 Portal Access Cards -->
        <div style="font-size:12px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">
          Quick Portal Access / সরাসরি লগইন করুন
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
          <a href="<?= APP_URL ?>/patient/login.php" class="role-quick-card">
            <div style="width:36px; height:36px; border-radius:10px; background:#EFF6FF; color:#2563EB; display:flex; align-items:center; justify-content:center; font-size:18px;">
              <i class="bi bi-person-heart"></i>
            </div>
            <div>
              <div style="font-size:13px; font-weight:800; color:#0F172A;">Patient Portal</div>
              <div style="font-size:11px; color:#64748B;">আমার স্বাস্থ্য রেকর্ড</div>
            </div>
          </a>

          <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="role-quick-card">
            <div style="width:36px; height:36px; border-radius:10px; background:#ECFDF5; color:#059669; display:flex; align-items:center; justify-content:center; font-size:18px;">
              <i class="bi bi-stethoscope"></i>
            </div>
            <div>
              <div style="font-size:13px; font-weight:800; color:#0F172A;">Doctor Chamber</div>
              <div style="font-size:11px; color:#64748B;">চেম্বার ও সিরিয়াল কিউ</div>
            </div>
          </a>

          <a href="<?= APP_URL ?>/hospital/login.php" class="role-quick-card">
            <div style="width:36px; height:36px; border-radius:10px; background:#F5F3FF; color:#7C3AED; display:flex; align-items:center; justify-content:center; font-size:18px;">
              <i class="bi bi-building-fill-cross"></i>
            </div>
            <div>
              <div style="font-size:13px; font-weight:800; color:#0F172A;">Hospital Desk</div>
              <div style="font-size:11px; color:#64748B;">ওপিডি রিসেপশন ডেস্ক</div>
            </div>
          </a>

          <a href="<?= APP_URL ?>/lab/login.php" class="role-quick-card">
            <div style="width:36px; height:36px; border-radius:10px; background:#FFFBEB; color:#D97706; display:flex; align-items:center; justify-content:center; font-size:18px;">
              <i class="bi bi-flask-fill"></i>
            </div>
            <div>
              <div style="font-size:13px; font-weight:800; color:#0F172A;">Diagnostic Lab</div>
              <div style="font-size:11px; color:#64748B;">টোকেন সার্চ ও রেজাল্ট</div>
            </div>
          </a>
        </div>

      </div>

      <!-- Right Column: Visual Live Interactive Preview Card -->
      <div class="hero-visual-col">
        <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:22px; padding:28px; box-shadow:0 20px 48px rgba(15,23,42,0.08); position:relative;">
          
          <!-- Card Header -->
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #F1F5F9; padding-bottom:16px; margin-bottom:18px;">
            <div style="display:flex; align-items:center; gap:12px;">
              <div style="width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg, #0284c7, #2563eb); color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800;">
                R
              </div>
              <div>
                <h3 style="margin:0; font-size:16px; font-weight:800; color:#0F172A;">Rahat Mahamud</h3>
                <div style="font-size:12px; color:#64748B;">PT-000001 · Age: 28 · <strong style="color:#059669;">O+ Positive</strong></div>
              </div>
            </div>
            <span style="background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; font-size:11px; font-weight:800; padding:3px 10px; border-radius:999px;">
              ● ACTIVE EHR
            </span>
          </div>

          <!-- Allergy Warning Box -->
          <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:12px; padding:12px; display:flex; align-items:center; gap:10px; margin-bottom:16px;">
            <span style="font-size:20px;">⚠️</span>
            <div style="font-size:12px;">
              <strong style="color:#DC2626; display:block;">Severe Penicillin Allergy</strong>
              <span style="color:#64748B;">Cross-reactive: Amoxicillin (Flagged in all doctor views)</span>
            </div>
          </div>

          <!-- Today's Live Serial Card -->
          <div style="background:linear-gradient(135deg, #0284c7 0%, #1e40af 100%); border-radius:16px; padding:18px; color:#fff; margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:700; opacity:0.9; margin-bottom:8px;">
              <span>TODAY'S APPOINTMENT SERIAL</span>
              <span style="background:rgba(255,255,255,0.2); padding:2px 8px; border-radius:4px;">CONFIRMED</span>
            </div>
            <div style="display:flex; align-items:center; gap:16px;">
              <div style="background:#fff; color:#1e40af; font-family:'JetBrains Mono', monospace; font-size:32px; font-weight:900; padding:6px 14px; border-radius:10px; line-height:1;">
                #02
              </div>
              <div>
                <h4 style="margin:0 0 2px; font-size:15px; font-weight:800;">Dr. Farhana Rahman</h4>
                <div style="font-size:12px; opacity:0.9;">Cardiology · ABC General Hospital</div>
                <div style="font-size:11px; color:#BFDBFE; margin-top:3px;">Slot: 05:30 PM · Serial Token Ready</div>
              </div>
            </div>
          </div>

          <!-- Active Lab Token Widget -->
          <div style="background:#FAF5FF; border:1px solid #E9D5FF; border-radius:14px; padding:14px; display:flex; justify-content:space-between; align-items:center;">
            <div>
              <div style="font-size:11px; font-weight:800; color:#7C3AED; text-transform:uppercase;">Short Lab Token</div>
              <div style="font-family:'JetBrains Mono', monospace; font-size:18px; font-weight:800; color:#5B21B6; margin-top:2px;">LT-99B2A</div>
              <div style="font-size:11px; color:#6B7280;">Test: Complete Blood Count (CBC)</div>
            </div>
            <span style="background:#EDE9FE; color:#6D28D9; font-size:11px; font-weight:700; padding:4px 10px; border-radius:6px;">
              Show at Lab Desk
            </span>
          </div>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     CORE SERVICES (CLEAN, NO UNWANTED TEXT WALLS)
     ===================================================== -->
<section id="services" style="padding:70px 0; background:#FFFFFF;">
  <div class="container" style="max-width:1320px; margin:0 auto; padding:0 24px;">
    
    <div style="text-align:center; max-width:640px; margin:0 auto 48px;">
      <span style="font-size:12px; font-weight:800; color:#0284C7; text-transform:uppercase; letter-spacing:0.8px;">Core Capabilities</span>
      <h2 style="font-size:32px; font-weight:800; color:#0F172A; margin:8px 0 10px;">প্রধান সেবাসমূহ</h2>
      <p style="font-size:15px; color:#64748B; margin:0;">সহজে ডাক্তার সিরিয়াল বুকিং থেকে শুরু করে টেস্ট রিপোর্ট ও আজীবন স্বাস্থ্য রেকর্ড—সব এক প্ল্যাটফর্মে।</p>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:24px;">
      
      <!-- Feature 1 -->
      <div class="feature-card-clean">
        <div style="width:48px; height:48px; border-radius:12px; background:#EFF6FF; color:#0284C7; display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:18px;">
          <i class="bi bi-calendar2-check-fill"></i>
        </div>
        <h3 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 8px;">ডাক্তার সিরিয়াল ও লাইভ কিউ</h3>
        <p style="font-size:14px; color:#64748B; line-height:1.6; margin:0 0 16px;">
          বিভাগ ও হাসপাতাল অনুযায়ী বিশেষজ্ঞ ডাক্তার বাছাই করুন, রেটিং ও ফি দেখুন এবং রিয়েল-টাইম চেম্বার সিরিয়াল টোকেন নিন।
        </p>
        <a href="<?= APP_URL ?>/book-doctor.php" style="font-size:13px; font-weight:700; color:#0284C7; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
          সিরিয়াল বুক করুন &rarr;
        </a>
      </div>

      <!-- Feature 2 -->
      <div class="feature-card-clean">
        <div style="width:48px; height:48px; border-radius:12px; background:#ECFDF5; color:#059669; display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:18px;">
          <i class="bi bi-clipboard2-pulse-fill"></i>
        </div>
        <h3 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 8px;">আজীবন মেডিক্যাল রেকর্ড</h3>
        <p style="font-size:14px; color:#64748B; line-height:1.6; margin:0 0 16px;">
          কাগজের প্রেসক্রিপশন হারানোর ভয় নেই। অতীতের প্রেসক্রিপশন, ডায়াগনস্টিক রিপোর্ট ও ওষুধ হিস্ট্রি একটি মাত্র নিরাপদ প্রোফাইলে সংরক্ষিত।
        </p>
        <a href="<?= APP_URL ?>/for-patients.php" style="font-size:13px; font-weight:700; color:#059669; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
          বিস্তারিত জানুন &rarr;
        </a>
      </div>

      <!-- Feature 3 -->
      <div class="feature-card-clean">
        <div style="width:48px; height:48px; border-radius:12px; background:#F5F3FF; color:#7C3AED; display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:18px;">
          <i class="bi bi-qr-code-scan"></i>
        </div>
        <h3 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 8px;">শর্ট ডায়াগনস্টিক টোকেন</h3>
        <p style="font-size:14px; color:#64748B; line-height:1.6; margin:0 0 16px;">
          প্রেসক্রিপশনের সাথে তৈরি হওয়া ইউনিক শর্ট টোকেন (LT-XXXXX) যেকোনো অনুমোদিত ল্যাবে দেখালেই অটো-সিঙ্ক হয়ে যাবে রিপোর্ট।
        </p>
        <a href="<?= APP_URL ?>/for-hospitals.php" style="font-size:13px; font-weight:700; color:#7C3AED; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
          ল্যাব নেটওয়ার্ক &rarr;
        </a>
      </div>

      <!-- Feature 4 -->
      <div class="feature-card-clean">
        <div style="width:48px; height:48px; border-radius:12px; background:#FEF2F2; color:#DC2626; display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:18px;">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h3 style="font-size:18px; font-weight:800; color:#0F172A; margin:0 0 8px;">রোগী-নিয়ন্ত্রিত নিরাপত্তা</h3>
        <p style="font-size:14px; color:#64748B; line-height:1.6; margin:0 0 16px;">
          রোগীর ওটিপি (OTP) বা অনুমতি ছাড়া কোনো ডাক্তার রেকর্ড দেখতে পারেন না। প্রতিটি সেশন ৩০ মিনিট পর স্বয়ংক্রিয়ভাবে লক হয়ে যায়।
        </p>
        <a href="<?= APP_URL ?>/security.php" style="font-size:13px; font-weight:700; color:#DC2626; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
          নিরাপত্তা স্ট্যান্ডার্ড &rarr;
        </a>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     PORTALS SECTION (CLEAN WHITE CARDS FOR EVERY ROLE)
     ===================================================== -->
<section id="portals" style="padding:70px 0; background:#F8FAFC; border-top:1px solid #E2E8F0; border-bottom:1px solid #E2E8F0;">
  <div class="container" style="max-width:1320px; margin:0 auto; padding:0 24px;">
    
    <div style="text-align:center; max-width:640px; margin:0 auto 48px;">
      <span style="font-size:12px; font-weight:800; color:#0284C7; text-transform:uppercase; letter-spacing:0.8px;">Tailored Access</span>
      <h2 style="font-size:32px; font-weight:800; color:#0F172A; margin:8px 0 10px;">আপনার পোর্টাল নির্বাচন করুন</h2>
      <p style="font-size:15px; color:#64748B; margin:0;">প্রতিটি ভূমিকার জন্য আলাদা ডেডিকেটেড ওয়ার্কস্পেস ও ক্লিনিক্যাল টুলস।</p>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(270px, 1fr)); gap:24px;">
      
      <!-- Patient Portal -->
      <div class="portal-box-clean">
        <div>
          <div style="width:48px; height:48px; border-radius:14px; background:#EFF6FF; color:#2563EB; display:flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:18px;">
            <i class="bi bi-person-heart"></i>
          </div>
          <h3 style="font-size:20px; font-weight:800; color:#0F172A; margin:0 0 6px;">Patient Portal</h3>
          <p style="font-size:13px; color:#64748B; margin:0 0 18px;">আমার স্বাস্থ্য রেকর্ড, আমার নিয়ন্ত্রণে।</p>
          <ul style="padding-left:18px; font-size:13px; color:#475569; line-height:1.8; margin-bottom:24px;">
            <li>আজীবন প্রেসক্রিপশন ও রিপোর্ট হিস্ট্রি</li>
            <li>লাইভ ডাক্তার সিরিয়াল ট্র্যাকিং</li>
            <li>ডাক্তারের অ্যাক্সেস অনুমোদন বা বাতিল</li>
            <li>ল্যাব টোকেন ও ওষুধ গ্রহণের রিমাইন্ডার</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/patient/login.php" class="btn btn-primary w-100" style="padding:12px; border-radius:10px; font-weight:700; background:#2563EB;">
          <i class="bi bi-box-arrow-in-right"></i> Patient Login
        </a>
      </div>

      <!-- Doctor Portal -->
      <div class="portal-box-clean" style="border-top:3px solid #059669;">
        <div>
          <div style="width:48px; height:48px; border-radius:14px; background:#ECFDF5; color:#059669; display:flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:18px;">
            <i class="bi bi-stethoscope"></i>
          </div>
          <h3 style="font-size:20px; font-weight:800; color:#0F172A; margin:0 0 6px;">Doctor Chamber</h3>
          <p style="font-size:13px; color:#64748B; margin:0 0 18px;">দ্রুত ক্লিনিক্যাল কনসালটেশন ও প্রেসক্রিপশন।</p>
          <ul style="padding-left:18px; font-size:13px; color:#475569; line-height:1.8; margin-bottom:24px;">
            <li>মাল্টি-হাসপাতাল চেম্বার ম্যানেজমেন্ট</li>
            <li>লাইভ পেশেন্ট কিউ ও ইমার্জেন্সি অগ্রাধিকার</li>
            <li>এক্সিকিউটিভ ক্লিনিক্যাল হিস্ট্রি সারসংক্ষেপ</li>
            <li>বাংলা ডোজ প্রিসেট সহ ডিজিটাল প্রেসক্রিপশন</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="btn w-100" style="padding:12px; border-radius:10px; font-weight:700; background:#059669; color:#fff;">
          <i class="bi bi-box-arrow-in-right"></i> Doctor Login
        </a>
      </div>

      <!-- Hospital Portal -->
      <div class="portal-box-clean">
        <div>
          <div style="width:48px; height:48px; border-radius:14px; background:#F5F3FF; color:#7C3AED; display:flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:18px;">
            <i class="bi bi-building-fill-cross"></i>
          </div>
          <h3 style="font-size:20px; font-weight:800; color:#0F172A; margin:0 0 6px;">Hospital Portal</h3>
          <p style="font-size:13px; color:#64748B; margin:0 0 18px;">সম্পূর্ণ প্রাতিষ্ঠানিক ও ওপিডি প্রশাসন।</p>
          <ul style="padding-left:18px; font-size:13px; color:#475569; line-height:1.8; margin-bottom:24px;">
            <li>ওপিডি অভ্যর্থনা ও লবি চেক-ইন ডেস্ক</li>
            <li>অ্যাফিলিয়েটেড ডাক্তার ও ডিপার্টমেন্ট রস্টার</li>
            <li>ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক</li>
            <li>দৈনিক ওপিডি রোগী রেজিস্টার ও অডিট</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/hospital/login.php" class="btn btn-secondary w-100" style="padding:12px; border-radius:10px; font-weight:700; border-color:#CBD5E1;">
          <i class="bi bi-box-arrow-in-right"></i> Hospital Login
        </a>
      </div>

      <!-- Diagnostic Lab Portal -->
      <div class="portal-box-clean">
        <div>
          <div style="width:48px; height:48px; border-radius:14px; background:#FFFBEB; color:#D97706; display:flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:18px;">
            <i class="bi bi-flask-fill"></i>
          </div>
          <h3 style="font-size:20px; font-weight:800; color:#0F172A; margin:0 0 6px;">Diagnostic Lab</h3>
          <p style="font-size:13px; color:#64748B; margin:0 0 18px;">দ্রুত টোকেন সার্চ ও সরাসরি রেজাল্ট আপলোড।</p>
          <ul style="padding-left:18px; font-size:13px; color:#475569; line-height:1.8; margin-bottom:24px;">
            <li>৮ ডিজিটের শর্ট ল্যাব টোকেন ওমনিবার সার্চ</li>
            <li>ডাক্তারের নির্দেশিত টেস্ট ও প্রায়োরিটি ভেরিফাই</li>
            <li>টেস্ট ফাইন্ডিংস এন্ট্রি ও পিডিএফ আপলোড</li>
            <li>রোগীর মোবাইল রেকর্ডে তাৎক্ষণিক সিঙ্ক</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/lab/login.php" class="btn w-100" style="padding:12px; border-radius:10px; font-weight:700; background:#D97706; color:#fff;">
          <i class="bi bi-box-arrow-in-right"></i> Lab Login
        </a>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     EMERGENCY ICE BANNER (CRISP & PURPOSEFUL)
     ===================================================== -->
<section style="padding:60px 0; background:#FFFFFF;">
  <div class="container" style="max-width:1320px; margin:0 auto; padding:0 24px;">
    <div style="background:linear-gradient(135deg, #FEF2F2 0%, #FFF5F5 100%); border:1px solid #FECACA; border-radius:20px; padding:36px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:24px;">
      <div style="max-width:680px;">
        <span style="background:#DC2626; color:#fff; font-size:11px; font-weight:800; padding:3px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:0.5px;">First Responder ICE</span>
        <h3 style="font-size:24px; font-weight:800; color:#991B1B; margin:10px 0 6px;">রোড এক্সিডেন্ট ও জরুরি পরিস্থিতি?</h3>
        <p style="font-size:14px; color:#7F1D1D; line-height:1.6; margin:0;">
          লগইন ছাড়াই ফার্স্ট-রেসপন্ডার ও প্যারামেডিকদের জন্য ১-ক্লিকে রক্তের গ্রুপ দেখা, মারাত্মক ওষুধের অ্যালার্জি জানা এবং স্বজনকে তাৎক্ষণিক কল করার পাবলিক প্রোফাইল।
        </p>
      </div>
      <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001" target="_blank" class="btn btn-lg" style="background:#DC2626; color:#fff; font-weight:800; border-radius:12px; padding:14px 26px; box-shadow:0 6px 18px rgba(220,38,38,0.25); text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
        <i class="bi bi-heart-pulse-fill"></i> ওপেন ইমার্জেন্সি ICE কার্ড
      </a>
    </div>
  </div>
</section>

<!-- =====================================================
     FOOTER (CLEAN WHITE THEME)
     ===================================================== -->
<footer style="background:#FFFFFF; border-top:1px solid #E2E8F0; padding:60px 0 30px; font-size:14px; color:#64748B;">
  <div class="container" style="max-width:1320px; margin:0 auto; padding:0 24px;">
    
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:36px; margin-bottom:48px;">
      
      <!-- Brand Col -->
      <div>
        <div style="display:flex; align-items:center; gap:10px; font-weight:800; font-size:18px; color:#0F172A; margin-bottom:12px;">
          <div style="width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg, #0284c7, #2563eb); color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px;">M</div>
          MedCore
        </div>
        <p style="font-size:13px; line-height:1.6; color:#64748B; margin:0 0 14px;">
          One Record. Every Care.<br>
          A unified, consent-driven digital healthcare network for Bangladesh.
        </p>
        <div style="font-size:12px; color:#94A3B8;">
          Universal Demo OTP: <code style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-weight:700; color:#0F172A;">1234</code>
        </div>
      </div>

      <!-- Quick Portals -->
      <div>
        <h4 style="font-size:14px; font-weight:800; color:#0F172A; margin:0 0 14px; text-transform:uppercase; letter-spacing:0.5px;">Portals</h4>
        <ul style="list-style:none; padding:0; margin:0; line-height:2.2; font-size:13px;">
          <li><a href="<?= APP_URL ?>/patient/login.php" style="color:#64748B; text-decoration:none;">Patient Portal</a></li>
          <li><a href="<?= APP_URL ?>/doctor/select-hospital.php" style="color:#64748B; text-decoration:none;">Doctor Chamber</a></li>
          <li><a href="<?= APP_URL ?>/hospital/login.php" style="color:#64748B; text-decoration:none;">Hospital Desk</a></li>
          <li><a href="<?= APP_URL ?>/lab/login.php" style="color:#64748B; text-decoration:none;">Diagnostic Lab</a></li>
        </ul>
      </div>

      <!-- Online Booking -->
      <div>
        <h4 style="font-size:14px; font-weight:800; color:#0F172A; margin:0 0 14px; text-transform:uppercase; letter-spacing:0.5px;">Services</h4>
        <ul style="list-style:none; padding:0; margin:0; line-height:2.2; font-size:13px;">
          <li><a href="<?= APP_URL ?>/book-doctor.php" style="color:#0284C7; font-weight:700; text-decoration:none;">ডাক্তার সিরিয়াল বুকিং</a></li>
          <li><a href="<?= APP_URL ?>/emergency.php" style="color:#DC2626; font-weight:700; text-decoration:none;">Emergency ICE Profile</a></li>
          <li><a href="<?= APP_URL ?>/security.php" style="color:#64748B; text-decoration:none;">Security & Consent</a></li>
          <li><a href="<?= APP_URL ?>/for-patients.php" style="color:#64748B; text-decoration:none;">For Patients</a></li>
        </ul>
      </div>

      <!-- Security & Trust -->
      <div>
        <h4 style="font-size:14px; font-weight:800; color:#0F172A; margin:0 0 14px; text-transform:uppercase; letter-spacing:0.5px;">Compliance</h4>
        <div style="font-size:13px; color:#64748B; line-height:1.8;">
          <div><i class="bi bi-shield-check text-primary"></i> DGDA Standards Aligned</div>
          <div><i class="bi bi-lock-fill text-primary"></i> AES-256 Encrypted Records</div>
          <div><i class="bi bi-clock-history text-primary"></i> 30-Min Temporary Consent</div>
          <div><i class="bi bi-check-circle-fill text-success"></i> BMDC Licensure Validation</div>
        </div>
      </div>

    </div>

    <!-- Bottom Copyright -->
    <div style="border-top:1px solid #F1F5F9; padding-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; font-size:12px; color:#94A3B8;">
      <div>&copy; <?= date('Y') ?> MedCore Healthcare Network. All rights reserved.</div>
      <div style="display:flex; gap:16px;">
        <a href="<?= APP_URL ?>/security.php" style="color:#94A3B8; text-decoration:none;">Privacy Policy</a>
        <a href="<?= APP_URL ?>/security.php" style="color:#94A3B8; text-decoration:none;">Terms of Service</a>
        <a href="<?= APP_URL ?>/security.php" style="color:#94A3B8; text-decoration:none;">Security Whitepaper</a>
      </div>
    </div>

  </div>
</footer>

<!-- Scripts -->
<script>
// Mobile Hamburger Toggle
document.getElementById('hamburger-btn')?.addEventListener('click', function() {
  const menu = document.getElementById('nav-menu');
  if (menu) {
    if (menu.style.display === 'flex') {
      menu.style.display = 'none';
      this.setAttribute('aria-expanded', 'false');
    } else {
      menu.style.display = 'flex';
      menu.style.flexDirection = 'column';
      menu.style.position = 'absolute';
      menu.style.top = '72px';
      menu.style.left = '0';
      menu.style.width = '100%';
      menu.style.background = '#FFFFFF';
      menu.style.padding = '16px 24px';
      menu.style.boxShadow = '0 10px 25px rgba(0,0,0,0.1)';
      menu.style.borderBottom = '1px solid #E2E8F0';
      menu.style.zIndex = '999';
      this.setAttribute('aria-expanded', 'true');
    }
  }
});
</script>

</body>
</html>
