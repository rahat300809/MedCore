<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
initSession();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="MedCore Citizen Portal — নাগরিকের নিজস্ব ডিজিটাল স্বাস্থ্য রেকর্ড, অনলাইন ডাক্তার সিরিয়াল বুকিং এবং জরুরি ICE কার্ড।">
  <title>MedCore Citizen — নাগরিক স্বাস্থ্য সেবা পোর্টাল | One Patient, Unified Record</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Hind+Siliguri:wght@400;500;600;700&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">

  <style>
    :root {
      --pt-primary: #0284C7;
      --pt-navy: #0A2540;
      --pt-blue-light: #EFF6FF;
      --pt-green: #059669;
      --pt-red: #DC2626;
      --pt-border: #E2E8F0;
      --pt-slate: #0F172A;
      --pt-muted: #64748B;
      --pt-bg-light: #F8FAFC;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: #FFFFFF;
      color: var(--pt-slate);
      font-family: 'Plus Jakarta Sans', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    /* TOP STRIP */
    .gov-top-bar {
      background: #0A2540;
      color: #E2E8F0;
      font-size: 12px;
      padding: 7px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .gov-container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .flag-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
    }
    .flag-icon {
      width: 18px; height: 12px;
      background: #006A4E;
      position: relative;
      border-radius: 2px;
      display: inline-block;
    }
    .flag-icon::after {
      content: '';
      position: absolute;
      width: 6px; height: 6px;
      background: #F42A41;
      border-radius: 50%;
      top: 50%; left: 45%;
      transform: translate(-50%, -50%);
    }

    /* NAVBAR */
    .pt-navbar {
      background: #FFFFFF;
      border-bottom: 2px solid #0284C7;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    }
    .pt-nav-inner {
      height: 74px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }
    .pt-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: inherit;
    }
    .pt-brand-icon {
      width: 42px; height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, #0A2540 0%, #0284C7 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
    }
    .pt-brand-title {
      font-size: 20px;
      font-weight: 900;
      color: #0A2540;
      line-height: 1.1;
    }
    .pt-brand-title span { color: #0284C7; }
    .pt-brand-sub {
      font-size: 11px;
      font-weight: 700;
      color: #059669;
    }

    .pt-nav-links {
      display: flex;
      align-items: center;
      gap: 8px;
      list-style: none;
      margin: 0; padding: 0;
      white-space: nowrap;
    }
    .pt-nav-link {
      font-size: 13.5px;
      font-weight: 700;
      color: #334155;
      text-decoration: none;
      padding: 8px 12px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }
    .pt-nav-link:hover {
      color: #0284C7;
      background: #F0F9FF;
    }

    .pt-nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
    }
    .btn-pt-serial {
      background: #EFF6FF;
      color: #1D4ED8;
      border: 1px solid #BFDBFE;
      padding: 8px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .btn-pt-login {
      background: #FFFFFF;
      color: #0A2540;
      border: 1.5px solid #CBD5E1;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .btn-pt-login:hover { background: #F8FAFC; border-color: #94A3B8; }
    .btn-pt-reg {
      background: linear-gradient(135deg, #0A2540 0%, #0284C7 100%);
      color: #FFFFFF !important;
      padding: 8px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 10px rgba(10, 37, 64, 0.25);
    }

    /* HERO */
    .pt-hero {
      background: radial-gradient(circle at 50% 0%, #F0F9FF 0%, #FFFFFF 65%, #F8FAFC 100%);
      padding: 60px 0 70px;
      border-bottom: 1px solid #E2E8F0;
    }
    .pt-hero-layout {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 48px;
    }
    .pt-hero-content {
      flex: 1 1 58%;
    }
    .pt-hero-card-col {
      flex: 0 0 430px;
      max-width: 450px;
    }
    .pt-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #ECFDF5;
      border: 1px solid #A7F3D0;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12.5px;
      font-weight: 800;
      color: #065F46;
      margin-bottom: 20px;
    }
    .pt-hero-title {
      font-size: clamp(30px, 3.8vw, 44px);
      font-weight: 900;
      line-height: 1.25;
      color: #0A2540;
      margin: 0 0 16px;
      letter-spacing: -0.02em;
    }
    .pt-hero-title span { color: #0284C7; }
    .pt-hero-subtitle {
      font-size: 16px;
      color: #475569;
      line-height: 1.7;
      margin: 0 0 28px;
      max-width: 620px;
    }

    .pt-hero-buttons {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 36px;
    }
    .btn-main-primary {
      background: linear-gradient(135deg, #0A2540 0%, #0284C7 100%);
      color: #fff !important;
      font-size: 15px;
      font-weight: 800;
      padding: 14px 28px;
      border-radius: 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 6px 20px rgba(10, 37, 64, 0.25);
    }
    .btn-main-secondary {
      background: #FFFFFF;
      color: #0F172A !important;
      font-size: 15px;
      font-weight: 800;
      padding: 14px 24px;
      border-radius: 12px;
      border: 1.5px solid #CBD5E1;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    /* CITIZEN SMART CARD */
    .smart-card {
      background: #FFFFFF;
      border: 1.5px solid #CBD5E1;
      border-radius: 20px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }
    .card-top {
      background: linear-gradient(135deg, #0A2540 0%, #0369A1 100%);
      color: #fff;
      padding: 14px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.8px;
    }
    .card-body { padding: 22px; }

    /* SECTION */
    .pt-section {
      padding: 64px 0;
      border-bottom: 1px solid #E2E8F0;
    }
    .pt-section-header {
      text-align: center;
      max-width: 650px;
      margin: 0 auto 44px;
    }
    .pt-section-title {
      font-size: 30px;
      font-weight: 900;
      color: #0A2540;
      margin: 8px 0;
    }
    .pt-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
    }
    .pt-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 26px;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
      transition: all 0.2s ease;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .pt-card:hover {
      border-color: #38BDF8;
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(2, 132, 199, 0.08);
    }
    .pt-card-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      margin-bottom: 16px;
    }
    .pt-card h3 {
      font-size: 18px;
      font-weight: 800;
      color: #0A2540;
      margin: 0 0 8px;
    }
    .pt-card p {
      font-size: 14px;
      color: #64748B;
      line-height: 1.65;
      margin: 0 0 16px;
    }

    /* DEMO CREDENTIALS BOX */
    .demo-showcase-box {
      background: #F0F9FF;
      border: 2px dashed #BAE6FD;
      border-radius: 18px;
      padding: 24px;
      margin-top: 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      flex-wrap: wrap;
    }

    @media (max-width: 991px) {
      .pt-hero-layout { flex-direction: column; }
      .pt-hero-card-col { width: 100%; max-width: 460px; }
      .pt-nav-links { display: none; }
    }
  </style>
</head>
<body>

<!-- GOV TOP STRIP -->
<div class="gov-top-bar">
  <div class="gov-container">
    <div class="flag-badge">
      <span class="flag-icon"></span>
      <span>গণপ্রজাতন্ত্রী বাংলাদেশ সরকার অনুমোদিত নাগরিক স্বাস্থ্য পোর্টাল | Citizen Healthcare Portal</span>
    </div>
    <div style="font-size: 12px; display: flex; gap: 16px;">
      <span style="color:#93C5FD; font-weight:700;"><i class="bi bi-telephone-fill"></i> স্বাস্থ্য বাতায়ন: ১৬২৬৩</span>
      <span style="color:#FCA5A5; font-weight:700;"><i class="bi bi-shield-fill-exclamation"></i> জরুরি কল: ৯৯৯</span>
    </div>
  </div>
</div>

<!-- PATIENT NAVBAR -->
<nav class="pt-navbar">
  <div class="gov-container pt-nav-inner">
    <a href="<?= APP_URL ?>/for-patients.php" class="pt-brand">
      <div class="pt-brand-icon"><i class="bi bi-person-heart"></i></div>
      <div>
        <div class="pt-brand-title">MedCore <span>Citizen</span></div>
        <div class="pt-brand-sub">নাগরিক স্বাস্থ্য সেবা পোর্টাল</div>
      </div>
    </a>

    <ul class="pt-nav-links">
      <li><a href="<?= APP_URL ?>/" class="pt-nav-link">← মূল ওয়েবসাইট</a></li>
      <li><a href="<?= APP_URL ?>/book-doctor.php" class="pt-nav-link">ডাক্তার সিরিয়াল বুকিং</a></li>
      <li><a href="<?= APP_URL ?>/emergency.php" class="pt-nav-link">জরুরি ICE কার্ড</a></li>
      <li><a href="<?= APP_URL ?>/security.php" class="pt-nav-link">প্রাইভেসি ও OTP</a></li>
      <li><a href="<?= APP_URL ?>/role-selection.php" class="pt-nav-link">অন্যান্য পোর্টাল</a></li>
    </ul>

    <div class="pt-nav-actions">
      <a href="<?= APP_URL ?>/book-doctor.php" class="btn-pt-serial">
        <i class="bi bi-calendar2-check-fill text-primary"></i> ডাক্তার সিরিয়াল
      </a>
      <a href="<?= APP_URL ?>/patient/login.php" class="btn-pt-login">
        <i class="bi bi-box-arrow-in-right"></i> নাগরিক লগইন
      </a>
      <a href="<?= APP_URL ?>/patient/register.php" class="btn-pt-reg">
        <i class="bi bi-person-plus-fill"></i> ফ্রি অ্যাকাউন্ট খুলুন
      </a>
    </div>
  </div>
</nav>

<!-- PATIENT HERO -->
<section class="pt-hero">
  <div class="gov-container">
    <div class="pt-hero-layout">
      
      <!-- Left Content -->
      <div class="pt-hero-content">
        <div class="pt-badge">
          <i class="bi bi-patch-check-fill"></i> ১০০% নাগরিক সম্মতি ও ওটিপি নিয়ন্ত্রিত ডিজিটাল স্বাস্থ্য রেকর্ড
        </div>

        <h1 class="pt-hero-title">
          আপনার আজীবন প্রেসক্রিপশন ও মেডিকেল হিস্ট্রি<br>
          <span>এখন একক সুরক্ষিত নাগরিক প্রোফাইলে।</span>
        </h1>

        <p class="pt-hero-subtitle">
          কাগজের প্রেসক্রিপশন এবং ফাইলের বোঝা টানার দিন শেষ। দেশের যেকোনো সরকারি বা বেসরকারি হাসপাতালে ডাক্তার দেখান—আপনার অতীতের প্রেসক্রিপশন, ডায়াগনস্টিক রিপোর্ট ও ওষুধ ইতিহাস সংরক্ষিত থাকবে আপনার একক ডিজিটাল আইডিতে।
        </p>

        <div class="pt-hero-buttons">
          <a href="<?= APP_URL ?>/book-doctor.php" class="btn-main-primary">
            <i class="bi bi-calendar2-check-fill"></i> অনলাইন ডাক্তার সিরিয়াল বুক করুন
          </a>
          <a href="<?= APP_URL ?>/patient/login.php" class="btn-main-secondary">
            <i class="bi bi-box-arrow-in-right"></i> নাগরিক পোর্টালে লগইন করুন
          </a>
          <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001" target="_blank" class="btn-main-secondary" style="color:#DC2626 !important; border-color:#FECACA;">
            <i class="bi bi-shield-exclamation text-danger"></i> আমার লাইভ ICE কার্ড
          </a>
        </div>

        <!-- Citizen Micro Demo Box -->
        <div class="demo-showcase-box">
          <div>
            <div style="font-size:12px;font-weight:800;color:#0284C7;text-transform:uppercase;">⚡ পরীক্ষামূলক ডেমো রোগী অ্যাকাউন্ট:</div>
            <div style="font-size:14px;color:#0A2540;font-weight:700;margin-top:2px;">
              ইমেইল: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#0284C7;">rahat@example.com</code> · 
              পাসওয়ার্ড: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#0284C7;">Demo123!</code> · 
              OTP: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#059669;">1234</code>
            </div>
          </div>
          <a href="<?= APP_URL ?>/patient/login.php" class="btn-main-primary" style="padding:10px 18px;font-size:13px;">
            সরাসরি ডেমো লগইন &rarr;
          </a>
        </div>

      </div>

      <!-- Right Column: Interactive Citizen Health Card -->
      <div class="pt-hero-card-col">
        <div class="smart-card">
          <div class="card-top">
            <span><i class="bi bi-shield-check"></i> NATIONAL HEALTH RECORD · EHR</span>
            <span style="background:rgba(16,185,129,0.2);padding:2px 8px;border-radius:999px;color:#A7F3D0;">● ACTIVE CITIZEN</span>
          </div>

          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9;padding-bottom:16px;margin-bottom:16px;">
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#0A2540,#0284C7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;">R</div>
                <div>
                  <h3 style="margin:0;font-size:16px;font-weight:800;color:#0A2540;">Rahat Mahamud</h3>
                  <div style="font-size:12px;color:#64748B;">PT-000001 · ৩১ বছর · পুরুষ</div>
                </div>
              </div>
              <div style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:4px 10px;border-radius:8px;font-weight:900;font-size:13px;">
                🩸 O+
              </div>
            </div>

            <!-- Allergy -->
            <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:10px 12px;display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:12px;">
              <span style="font-size:16px;">⚠️</span>
              <div>
                <strong style="color:#DC2626;">Severe Penicillin Allergy</strong>
                <div style="color:#64748B;font-size:11px;">Cross-reactive: Amoxicillin (Flagged in all doctor views)</div>
              </div>
            </div>

            <!-- Today's Serial Card -->
            <div style="background:linear-gradient(135deg, #0A2540 0%, #0284C7 100%);border-radius:12px;padding:14px;color:#FFFFFF;margin-bottom:14px;">
              <div style="display:flex;justify-content:space-between;font-size:10px;font-weight:800;margin-bottom:6px;opacity:0.9;">
                <span>TODAY'S APPOINTMENT SERIAL</span>
                <span style="background:rgba(255,255,255,0.2);padding:1px 6px;border-radius:4px;">CONFIRMED</span>
              </div>
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="background:#FFFFFF;color:#0A2540;font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:900;padding:4px 10px;border-radius:8px;">
                  #02
                </div>
                <div>
                  <h4 style="margin:0 0 2px;font-size:14px;font-weight:800;color:#FFFFFF;">Dr. Farhana Rahman</h4>
                  <div style="font-size:11.5px;opacity:0.9;">Cardiology · ABC General Hospital</div>
                  <div style="font-size:10.5px;color:#BAE6FD;margin-top:2px;">Slot: 05:30 PM · Serial Token Ready</div>
                </div>
              </div>
            </div>

            <!-- Lab Token -->
            <div style="background:#F5F3FF;border:1px solid #E9D5FF;border-radius:10px;padding:10px 12px;display:flex;justify-content:space-between;align-items:center;">
              <div>
                <div style="font-size:10px;font-weight:800;color:#7C3AED;text-transform:uppercase;">Short Lab Token</div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:800;color:#5B21B6;margin-top:1px;">LT-99B2A</div>
                <div style="font-size:10.5px;color:#6B7280;">Test: Complete Blood Count (CBC)</div>
              </div>
              <span style="background:#EDE9FE;color:#6D28D9;font-size:10.5px;font-weight:700;padding:3px 8px;border-radius:6px;">
                Show at Lab Desk
              </span>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- CITIZEN FEATURES -->
<section class="pt-section" style="background:#F8FAFC;">
  <div class="gov-container">
    <div class="pt-section-header">
      <span style="font-size:12px;font-weight:800;color:#0284C7;text-transform:uppercase;background:#EFF6FF;padding:4px 12px;border-radius:999px;">Citizen Empowerment</span>
      <h2 class="pt-section-title">নাগরিকদের জন্য অনন্য সুবিধাসমূহ</h2>
      <p style="color:#64748B;font-size:15px;margin:0;">কীভাবে MedCore আপনার প্রতিদিনের চিকিৎসা সেবা সহজ ও নিরাপদ করে তোলে।</p>
    </div>

    <div class="pt-cards-grid">
      <div class="pt-card">
        <div>
          <div class="pt-card-icon" style="background:#EFF6FF;color:#0284C7;"><i class="bi bi-journal-medical"></i></div>
          <h3>আজীবন ডিজিটাল রেকর্ড (Unified EHR)</h3>
          <p>সব প্রেসক্রিপশন ও টেস্ট রিপোর্ট সুরক্ষিত ক্লাউডে সংরক্ষিত। যেকোনো সময় স্মার্টফোন থেকে দেখতে পারবেন।</p>
        </div>
        <a href="<?= APP_URL ?>/patient/login.php" style="color:#0284C7;font-weight:800;text-decoration:none;">রেকর্ড দেখুন &rarr;</a>
      </div>

      <div class="pt-card">
        <div>
          <div class="pt-card-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-calendar2-check-fill"></i></div>
          <h3>সহজ ডাক্তার সিরিয়াল ও কিউ ট্র্যাকিং</h3>
          <p>হাসপাতাল বা ডাক্তারের চেম্বারে না গিয়ে ঘরে বসেই রিয়েল-টাইম সিরিয়াল নিন ও লাইভ কিউ মনিটর করুন।</p>
        </div>
        <a href="<?= APP_URL ?>/book-doctor.php" style="color:#059669;font-weight:800;text-decoration:none;">সিরিয়াল বুক করুন &rarr;</a>
      </div>

      <div class="pt-card">
        <div>
          <div class="pt-card-icon" style="background:#FEF2F2;color:#DC2626;"><i class="bi bi-shield-exclamation"></i></div>
          <h3>জরুরি ফার্স্ট রেসপন্ডার ICE কার্ড</h3>
          <p>দুর্ঘটনা বা অজ্ঞান অবস্থায় পাসওয়ার্ড ছাড়াই উদ্ধারকারীরা আপনার রক্তের গ্রুপ, অ্যালার্জি ও স্বজনের নম্বর পাবেন।</p>
        </div>
        <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001" target="_blank" style="color:#DC2626;font-weight:800;text-decoration:none;">ICE কার্ড দেখুন &rarr;</a>
      </div>

      <div class="pt-card">
        <div>
          <div class="pt-card-icon" style="background:#F5F3FF;color:#7C3AED;"><i class="bi bi-shield-lock-fill"></i></div>
          <h3>৪-সংখ্যার ওটিপি (OTP) প্রাইভেসি</h3>
          <p>আপনার সম্মতি ছাড়া কোনো ডাক্তার হিস্ট্রি দেখতে পারবেন না। ওটিপি দেওয়ার পর সেশন ৩০ মিনিট পর স্বয়ংক্রিয় লক হবে।</p>
        </div>
        <a href="<?= APP_URL ?>/security.php" style="color:#7C3AED;font-weight:800;text-decoration:none;">নিরাপত্তা নীতি &rarr;</a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer style="background:#FFFFFF;border-top:1px solid #E2E8F0;padding:40px 0 24px;font-size:13px;color:#64748B;">
  <div class="gov-container">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
      <div>
        <strong style="color:#0A2540;font-size:15px;">MedCore Citizen Portal</strong> — গণপ্রজাতন্ত্রী বাংলাদেশ সরকারের ডিজিটাল স্বাস্থ্য ফ্রেমওয়ার্ক অনুমোদিত।
      </div>
      <div style="display:flex;gap:16px;">
        <a href="<?= APP_URL ?>/" style="color:#0284C7;text-decoration:none;font-weight:700;">মূল ওয়েবসাইট</a>
        <a href="<?= APP_URL ?>/book-doctor.php" style="color:#64748B;text-decoration:none;">ডাক্তার সিরিয়াল</a>
        <a href="<?= APP_URL ?>/patient/login.php" style="color:#64748B;text-decoration:none;">লগইন</a>
        <a href="<?= APP_URL ?>/security.php" style="color:#64748B;text-decoration:none;">প্রাইভেসি</a>
      </div>
    </div>
    <div style="margin-top:20px;font-size:12px;color:#94A3B8;">
      &copy; <?= date('Y') ?> MedCore Healthcare Network. All rights reserved.
    </div>
  </div>
</footer>

<!-- AI Chatbot Agent -->
<script src="<?= APP_URL ?>/assets/js/chatbot.js" defer></script>

</body>
</html>
