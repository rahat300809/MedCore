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
  <meta name="description" content="MedCore Doctor Portal — বিএমডিসি নিবন্ধিত চিকিৎসকদের সেন্ট্রালাইজড চেম্বার ওয়ার্কস্পেস, লাইভ কিউ ও আধুনিক ই-প্রেসক্রিপশন প্ল্যাটফর্ম।">
  <title>MedCore Doctor — বিএমডিসি ডাক্তার ও চেম্বার পোর্টাল | Clinical Workspace</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Hind+Siliguri:wght@400;500;600;700&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">

  <style>
    :root {
      --doc-primary: #059669;
      --doc-primary-dark: #047857;
      --doc-navy: #0A2540;
      --doc-blue: #0284C7;
      --doc-border: #E2E8F0;
      --doc-slate: #0F172A;
      --doc-muted: #64748B;
      --doc-bg-light: #F8FAFC;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: #FFFFFF;
      color: var(--doc-slate);
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

    /* NAVBAR */
    .doc-navbar {
      background: #FFFFFF;
      border-bottom: 2px solid var(--doc-primary);
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    }
    .doc-nav-inner {
      height: 74px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }
    .doc-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: inherit;
    }
    .doc-brand-icon {
      width: 42px; height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, #0A2540 0%, #059669 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    }
    .doc-brand-title {
      font-size: 20px;
      font-weight: 900;
      color: #0A2540;
      line-height: 1.1;
    }
    .doc-brand-title span { color: var(--doc-primary); }
    .doc-brand-sub {
      font-size: 11px;
      font-weight: 700;
      color: var(--doc-primary);
    }

    .doc-nav-links {
      display: flex;
      align-items: center;
      gap: 8px;
      list-style: none;
      margin: 0; padding: 0;
      white-space: nowrap;
    }
    .doc-nav-link {
      font-size: 13.5px;
      font-weight: 700;
      color: #334155;
      text-decoration: none;
      padding: 8px 12px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }
    .doc-nav-link:hover {
      color: var(--doc-primary);
      background: #ECFDF5;
    }

    .doc-nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
    }
    .btn-doc-login {
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
    .btn-doc-login:hover { background: #F8FAFC; border-color: #94A3B8; }
    .btn-doc-reg {
      background: linear-gradient(135deg, #0A2540 0%, #059669 100%);
      color: #FFFFFF !important;
      padding: 8px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 10px rgba(5, 150, 105, 0.25);
    }

    /* HERO */
    .doc-hero {
      background: radial-gradient(circle at 50% 0%, #ECFDF5 0%, #FFFFFF 65%, #F8FAFC 100%);
      padding: 60px 0 70px;
      border-bottom: 1px solid #E2E8F0;
    }
    .doc-hero-layout {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 48px;
    }
    .doc-hero-content { flex: 1 1 58%; }
    .doc-hero-card-col { flex: 0 0 440px; max-width: 460px; }

    .doc-badge {
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
    .doc-hero-title {
      font-size: clamp(30px, 3.8vw, 44px);
      font-weight: 900;
      line-height: 1.25;
      color: #0A2540;
      margin: 0 0 16px;
      letter-spacing: -0.02em;
    }
    .doc-hero-title span { color: var(--doc-primary); }
    .doc-hero-subtitle {
      font-size: 16px;
      color: #475569;
      line-height: 1.7;
      margin: 0 0 28px;
      max-width: 620px;
    }

    .doc-hero-buttons {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 36px;
    }
    .btn-doc-primary {
      background: linear-gradient(135deg, #0A2540 0%, #059669 100%);
      color: #fff !important;
      font-size: 15px;
      font-weight: 800;
      padding: 14px 28px;
      border-radius: 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 6px 20px rgba(5, 150, 105, 0.25);
    }
    .btn-doc-secondary {
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

    /* DOCTOR WORKSPACE CARD */
    .doc-card {
      background: #FFFFFF;
      border: 1.5px solid #CBD5E1;
      border-radius: 20px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }
    .doc-card-top {
      background: linear-gradient(135deg, #0A2540 0%, #047857 100%);
      color: #fff;
      padding: 14px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.8px;
    }
    .doc-card-body { padding: 22px; }

    /* SECTION */
    .doc-section {
      padding: 64px 0;
      border-bottom: 1px solid #E2E8F0;
    }
    .doc-section-header {
      text-align: center;
      max-width: 650px;
      margin: 0 auto 44px;
    }
    .doc-section-title {
      font-size: 30px;
      font-weight: 900;
      color: #0A2540;
      margin: 8px 0;
    }
    .doc-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
    }
    .doc-card-item {
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
    .doc-card-item:hover {
      border-color: #34D399;
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(5, 150, 105, 0.08);
    }
    .doc-card-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      margin-bottom: 16px;
    }
    .doc-card-item h3 {
      font-size: 18px;
      font-weight: 800;
      color: #0A2540;
      margin: 0 0 8px;
    }
    .doc-card-item p {
      font-size: 14px;
      color: #64748B;
      line-height: 1.65;
      margin: 0 0 16px;
    }

    /* DEMO CREDENTIALS BOX */
    .demo-showcase-box {
      background: #ECFDF5;
      border: 2px dashed #A7F3D0;
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
      .doc-hero-layout { flex-direction: column; }
      .doc-hero-card-col { width: 100%; max-width: 460px; }
      .doc-nav-links { display: none; }
    }
  </style>
</head>
<body>

<!-- GOV TOP STRIP -->
<div class="gov-top-bar">
  <div class="gov-container">
    <div class="flag-badge">
      <i class="bi bi-shield-check text-success"></i>
      <span>বিএমডিসি নিবন্ধিত চিকিৎসকদের সেন্ট্রালাইজড ক্লিনিক্যাল ওয়ার্কস্পেস | Verified Medical Practitioners Portal</span>
    </div>
    <div style="font-size: 12px; display: flex; gap: 16px;">
      <span>BMDC Standards Aligned</span>
      <span style="color:#A7F3D0;">DGDA Prescription Guidelines</span>
    </div>
  </div>
</div>

<!-- DOCTOR NAVBAR -->
<nav class="doc-navbar">
  <div class="gov-container doc-nav-inner">
    <a href="<?= APP_URL ?>/for-doctors.php" class="doc-brand">
      <div class="doc-brand-icon"><i class="bi bi-stethoscope"></i></div>
      <div>
        <div class="doc-brand-title">MedCore <span>Doctor</span></div>
        <div class="doc-brand-sub">বিএমডিসি কনসালটেন্ট ও চেম্বার পোর্টাল</div>
      </div>
    </a>

    <ul class="doc-nav-links">
      <li><a href="<?= APP_URL ?>/" class="doc-nav-link">← মূল ওয়েবসাইট</a></li>
      <li><a href="<?= APP_URL ?>/doctor/select-hospital.php" class="doc-nav-link">লাইভ চেম্বার কিউ</a></li>
      <li><a href="<?= APP_URL ?>/doctor/select-hospital.php" class="doc-nav-link">ই-প্রেসক্রিপশন বিল্ডার</a></li>
      <li><a href="<?= APP_URL ?>/for-patients.php" class="doc-nav-link">নাগরিক পোর্টাল</a></li>
      <li><a href="<?= APP_URL ?>/role-selection.php" class="doc-nav-link">অন্যান্য পোর্টাল</a></li>
    </ul>

    <div class="doc-nav-actions">
      <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="btn-doc-login">
        <i class="bi bi-box-arrow-in-right"></i> চেম্বার লগইন
      </a>
      <a href="<?= APP_URL ?>/doctor/register.php" class="btn-doc-reg">
        <i class="bi bi-person-plus-fill"></i> ডাক্তার রেজিস্ট্রেশন
      </a>
    </div>
  </div>
</nav>

<!-- DOCTOR HERO -->
<section class="doc-hero">
  <div class="gov-container">
    <div class="doc-hero-layout">
      
      <!-- Left Content -->
      <div class="doc-hero-content">
        <div class="doc-badge">
          <i class="bi bi-patch-check-fill"></i> বিএমডিসি ভেরিফাইড চিকিৎসকদের জন্য এক্সক্লুসিভ ডিজিটাল চেম্বার প্ল্যাটফর্ম
        </div>

        <h1 class="doc-hero-title">
          সেন্ট্রালাইজড চেম্বার ম্যানেজমেন্ট ও<br>
          <span>আধুনিক ই-প্রেসক্রিপশন সিস্টেম।</span>
        </h1>

        <p class="doc-hero-subtitle">
          একই সাথে একাধিক হাসপাতালে চেম্বার পরিচালনা, রিয়েল-টাইম পেশেন্ট সিরিয়াল কিউ ট্র্যাকিং এবং মাত্র ২ মিনিটে ড্রাগ-ইন্টারেকশন সতর্কবার্তা সহ স্বয়ংক্রিয় প্রেসক্রিপশন তৈরি করুন।
        </p>

        <div class="doc-hero-buttons">
          <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="btn-doc-primary">
            <i class="bi bi-box-arrow-in-right"></i> ডাক্তার চেম্বার লগইন করুন
          </a>
          <a href="<?= APP_URL ?>/doctor/register.php" class="btn-doc-secondary">
            <i class="bi bi-person-plus-fill"></i> বিএমডিসি ভেরিফিকেশন আবেদন
          </a>
        </div>

        <!-- Doctor Micro Demo Box -->
        <div class="demo-showcase-box">
          <div>
            <div style="font-size:12px;font-weight:800;color:#059669;text-transform:uppercase;">⚡ পরীক্ষামূলক ডেমো ডাক্তার অ্যাকাউন্ট:</div>
            <div style="font-size:14px;color:#0A2540;font-weight:700;margin-top:2px;">
              হাসপাতাল: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#059669;">ABC General Hospital</code> · 
              BMDC ID: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#059669;">BMDC-A-12345</code> · 
              Email: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#059669;">dr.rahman@medcore.local</code> · 
              OTP: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#059669;">1234</code>
            </div>
          </div>
          <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="btn-doc-primary" style="padding:10px 18px;font-size:13px;">
            সরাসরি চেম্বার খুলুন &rarr;
          </a>
        </div>

      </div>

      <!-- Right Column: Doctor Chamber Live Queue Card -->
      <div class="doc-hero-card-col">
        <div class="doc-card">
          <div class="doc-card-top">
            <span><i class="bi bi-hospital"></i> ABC GENERAL HOSPITAL · CHAMBER</span>
            <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:999px;">● QUEUE ACTIVE</span>
          </div>

          <div class="doc-card-body">
            <!-- Doctor Profile Header -->
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9;padding-bottom:14px;margin-bottom:16px;">
              <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#0A2540,#059669);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;">
                  <i class="bi bi-stethoscope"></i>
                </div>
                <div>
                  <h3 style="margin:0;font-size:16px;font-weight:900;color:#0A2540;">Dr. Farhana Rahman</h3>
                  <div style="font-size:12px;color:#059669;font-weight:700;">BMDC-A-12345 · Senior Consultant</div>
                </div>
              </div>
              <span style="background:#ECFDF5;color:#047857;border:1px solid #A7F3D0;font-size:11px;font-weight:800;padding:3px 8px;border-radius:6px;">
                VERIFIED
              </span>
            </div>

            <!-- Queue Counter -->
            <div style="background:linear-gradient(135deg,#0A2540,#059669);border-radius:14px;padding:16px;color:#fff;margin-bottom:16px;">
              <div style="display:flex;justify-content:space-between;font-size:11px;font-weight:800;opacity:0.9;margin-bottom:8px;">
                <span>TODAY'S PATIENT QUEUE</span>
                <span>TOTAL: 18 PATIENTS</span>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                  <div style="font-size:11px;color:#A7F3D0;">CURRENTLY IN CONSULTATION:</div>
                  <div style="font-size:18px;font-weight:900;margin-top:2px;">Rahat Mahamud (PT-000001)</div>
                </div>
                <div style="background:#fff;color:#0A2540;font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:900;padding:4px 12px;border-radius:10px;">
                  #02
                </div>
              </div>
            </div>

            <!-- Rx Tools Snippet -->
            <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:12px;padding:12px;font-size:12.5px;">
              <div style="font-weight:800;color:#0A2540;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                <i class="bi bi-file-earmark-medical-fill text-success"></i> Smart Rx Builder Highlights:
              </div>
              <div style="color:#475569;line-height:1.7;">
                <div>✔ ৩,০০০+ বাংলাদেশি ব্র্যান্ড ও জেনেরিক ড্রাগ সাজেস্ট</div>
                <div>✔ ১+০+১ খাবারের পর বাংলা ডোজ প্রিসেট</div>
                <div>✔ ৮-সংখ্যার ডায়াগনস্টিক ল্যাব টোকেন জেনারেটর</div>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- DOCTOR CAPABILITIES -->
<section class="doc-section" style="background:#F8FAFC;">
  <div class="gov-container">
    <div class="doc-section-header">
      <span style="font-size:12px;font-weight:800;color:#059669;text-transform:uppercase;background:#ECFDF5;padding:4px 12px;border-radius:999px;">Doctor Chamber Excellence</span>
      <h2 class="doc-section-title">চিকিৎসকদের জন্য স্মার্ট সুবিধাসমূহ</h2>
      <p style="color:#64748B;font-size:15px;margin:0;">কীভাবে MedCore আপনার চেম্বার কনসালটেশনকে নির্ভুল ও দ্রুত করে তোলে।</p>
    </div>

    <div class="doc-cards-grid">
      <div class="doc-card-item">
        <div>
          <div class="doc-card-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-people-fill"></i></div>
          <h3>স্মার্ট ওপিডি চেম্বার কিউ</h3>
          <p>হাসপাতালের অভ্যর্থনা ডেস্ক থেকে সিরিয়াল হওয়ার সাথে সাথে আপনার ড্যাশবোর্ডে লাইভ কিউ তৈরি হয়।</p>
        </div>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" style="color:#059669;font-weight:800;text-decoration:none;">কিউ দেখুন &rarr;</a>
      </div>

      <div class="doc-card-item">
        <div>
          <div class="doc-card-icon" style="background:#EFF6FF;color:#0284C7;"><i class="bi bi-journal-check"></i></div>
          <h3>রোগীর পূর্ববর্তী মেডিকেল হিস্ট্রি</h3>
          <p>রোগীর দেওয়া ৪-সংখ্যার ওটিপি দিয়ে অতীতের প্রেসক্রিপশন, ইসিজি, এমআরআই এবং ডায়াগনস্টিক রিপোর্ট এক নজরে পর্যালোচনা।</p>
        </div>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" style="color:#0284C7;font-weight:800;text-decoration:none;">হিস্ট্রি সিস্টেম &rarr;</a>
      </div>

      <div class="doc-card-item">
        <div>
          <div class="doc-card-icon" style="background:#F5F3FF;color:#7C3AED;"><i class="bi bi-building"></i></div>
          <h3>মাল্টি-হাসপাতাল চেম্বার শিডিউল</h3>
          <p>একাধিক হাসপাতালে আপনার প্র্যাকটিস থাকলে এক ক্লিকেই হাসপাতাল সুইচ করে নির্ধারিত চেম্বার পরিচালনা করুন।</p>
        </div>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" style="color:#7C3AED;font-weight:800;text-decoration:none;">অ্যাফিলিয়েশন &rarr;</a>
      </div>

      <div class="doc-card-item">
        <div>
          <div class="doc-card-icon" style="background:#FEF2F2;color:#DC2626;"><i class="bi bi-shield-exclamation"></i></div>
          <h3>ক্রস-অ্যালার্জি সতর্কবার্তা</h3>
          <p>প্রেসক্রিপশনে ওষুধ লেখার সাথে সাথেই রোগীর অ্যালার্জি হিস্ট্রি (যেমন পেনিসিলিন) থাকলে স্বয়ংক্রিয় ওয়ার্নিং শো করবে।</p>
        </div>
        <a href="<?= APP_URL ?>/security.php" style="color:#DC2626;font-weight:800;text-decoration:none;">নিরাপত্তা নীতি &rarr;</a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer style="background:#FFFFFF;border-top:1px solid #E2E8F0;padding:40px 0 24px;font-size:13px;color:#64748B;">
  <div class="gov-container">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
      <div>
        <strong style="color:#0A2540;font-size:15px;">MedCore Doctor Portal</strong> — বিএমডিসি নিবন্ধিত চিকিৎসকদের জন্য সমন্বিত ডিজিটাল প্ল্যাটফর্ম।
      </div>
      <div style="display:flex;gap:16px;">
        <a href="<?= APP_URL ?>/" style="color:#059669;text-decoration:none;font-weight:700;">মূল ওয়েবসাইট</a>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" style="color:#64748B;text-decoration:none;">চেম্বার লগইন</a>
        <a href="<?= APP_URL ?>/doctor/register.php" style="color:#64748B;text-decoration:none;">নিবন্ধন</a>
        <a href="<?= APP_URL ?>/for-patients.php" style="color:#64748B;text-decoration:none;">নাগরিক পোর্টাল</a>
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
