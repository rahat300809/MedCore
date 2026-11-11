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
  <meta name="description" content="MedCore Lab Portal — ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক, ৮-সংখ্যার প্রেসক্রিপশন টোকেন সার্চ ও সরাসরি টেস্ট রেজাল্ট আপলোড প্ল্যাটফর্ম।">
  <title>MedCore Lab — ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক | Diagnostic Integration</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Hind+Siliguri:wght@400;500;600;700&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">

  <style>
    :root {
      --lab-primary: #D97706;
      --lab-primary-dark: #B45309;
      --lab-navy: #0A2540;
      --lab-blue: #0284C7;
      --lab-border: #E2E8F0;
      --lab-slate: #0F172A;
      --lab-muted: #64748B;
      --lab-bg-light: #F8FAFC;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: #FFFFFF;
      color: var(--lab-slate);
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
    .lab-navbar {
      background: #FFFFFF;
      border-bottom: 2px solid var(--lab-primary);
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    }
    .lab-nav-inner {
      height: 74px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }
    .lab-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: inherit;
    }
    .lab-brand-icon {
      width: 42px; height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, #0A2540 0%, #D97706 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
    }
    .lab-brand-title {
      font-size: 20px;
      font-weight: 900;
      color: #0A2540;
      line-height: 1.1;
    }
    .lab-brand-title span { color: var(--lab-primary); }
    .lab-brand-sub {
      font-size: 11px;
      font-weight: 700;
      color: var(--lab-primary);
    }

    .lab-nav-links {
      display: flex;
      align-items: center;
      gap: 8px;
      list-style: none;
      margin: 0; padding: 0;
      white-space: nowrap;
    }
    .lab-nav-link {
      font-size: 13.5px;
      font-weight: 700;
      color: #334155;
      text-decoration: none;
      padding: 8px 12px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }
    .lab-nav-link:hover {
      color: var(--lab-primary);
      background: #FFFBEB;
    }

    .lab-nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
    }
    .btn-lab-login {
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
    .btn-lab-login:hover { background: #F8FAFC; border-color: #94A3B8; }
    .btn-lab-reg {
      background: linear-gradient(135deg, #0A2540 0%, #D97706 100%);
      color: #FFFFFF !important;
      padding: 8px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 10px rgba(217, 119, 6, 0.25);
    }

    /* HERO */
    .lab-hero {
      background: radial-gradient(circle at 50% 0%, #FFFBEB 0%, #FFFFFF 65%, #F8FAFC 100%);
      padding: 60px 0 70px;
      border-bottom: 1px solid #E2E8F0;
    }
    .lab-hero-layout {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 48px;
    }
    .lab-hero-content { flex: 1 1 58%; }
    .lab-hero-card-col { flex: 0 0 440px; max-width: 460px; }

    .lab-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #FFFBEB;
      border: 1px solid #FDE68A;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12.5px;
      font-weight: 800;
      color: #B45309;
      margin-bottom: 20px;
    }
    .lab-hero-title {
      font-size: clamp(30px, 3.8vw, 44px);
      font-weight: 900;
      line-height: 1.25;
      color: #0A2540;
      margin: 0 0 16px;
      letter-spacing: -0.02em;
    }
    .lab-hero-title span { color: var(--lab-primary); }
    .lab-hero-subtitle {
      font-size: 16px;
      color: #475569;
      line-height: 1.7;
      margin: 0 0 28px;
      max-width: 620px;
    }

    .lab-hero-buttons {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 36px;
    }
    .btn-lab-primary {
      background: linear-gradient(135deg, #0A2540 0%, #D97706 100%);
      color: #fff !important;
      font-size: 15px;
      font-weight: 800;
      padding: 14px 28px;
      border-radius: 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 6px 20px rgba(217, 119, 6, 0.25);
    }
    .btn-lab-secondary {
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

    /* LAB WORKBENCH CARD */
    .lab-card {
      background: #FFFFFF;
      border: 1.5px solid #CBD5E1;
      border-radius: 20px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }
    .lab-card-top {
      background: linear-gradient(135deg, #0A2540 0%, #B45309 100%);
      color: #fff;
      padding: 14px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.8px;
    }
    .lab-card-body { padding: 22px; }

    /* SECTION */
    .lab-section {
      padding: 64px 0;
      border-bottom: 1px solid #E2E8F0;
    }
    .lab-section-header {
      text-align: center;
      max-width: 650px;
      margin: 0 auto 44px;
    }
    .lab-section-title {
      font-size: 30px;
      font-weight: 900;
      color: #0A2540;
      margin: 8px 0;
    }
    .lab-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
    }
    .lab-card-item {
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
    .lab-card-item:hover {
      border-color: #FBBF24;
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(217, 119, 6, 0.08);
    }
    .lab-card-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      margin-bottom: 16px;
    }
    .lab-card-item h3 {
      font-size: 18px;
      font-weight: 800;
      color: #0A2540;
      margin: 0 0 8px;
    }
    .lab-card-item p {
      font-size: 14px;
      color: #64748B;
      line-height: 1.65;
      margin: 0 0 16px;
    }

    /* DEMO CREDENTIALS BOX */
    .demo-showcase-box {
      background: #FFFBEB;
      border: 2px dashed #FDE68A;
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
      .lab-hero-layout { flex-direction: column; }
      .lab-hero-card-col { width: 100%; max-width: 460px; }
      .lab-nav-links { display: none; }
    }
  </style>
</head>
<body>

<!-- GOV TOP STRIP -->
<div class="gov-top-bar">
  <div class="gov-container">
    <div class="flag-badge">
      <i class="bi bi-flask-fill text-warning"></i>
      <span>ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক ও প্রেসক্রিপশন টোকেন ভেরিফিকেশন সিস্টেম | Diagnostic Grid</span>
    </div>
    <div style="font-size: 12px; display: flex; gap: 16px;">
      <span>Fast Token Lookup</span>
      <span style="color:#FDE68A;">Instant EHR Patient Delivery</span>
    </div>
  </div>
</div>

<!-- LAB NAVBAR -->
<nav class="lab-navbar">
  <div class="gov-container lab-nav-inner">
    <a href="<?= APP_URL ?>/for-labs.php" class="lab-brand">
      <div class="lab-brand-icon"><i class="bi bi-flask-fill"></i></div>
      <div>
        <div class="lab-brand-title">MedCore <span>Lab</span></div>
        <div class="lab-brand-sub">ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক</div>
      </div>
    </a>

    <ul class="lab-nav-links">
      <li><a href="<?= APP_URL ?>/" class="lab-nav-link">← মূল ওয়েবসাইট</a></li>
      <li><a href="<?= APP_URL ?>/lab/login.php" class="lab-nav-link">টোকেন ভেরিফিকেশন</a></li>
      <li><a href="<?= APP_URL ?>/lab/login.php" class="lab-nav-link">রেজাল্ট আপলোড ও সিঙ্ক</a></li>
      <li><a href="<?= APP_URL ?>/for-patients.php" class="lab-nav-link">নাগরিক পোর্টাল</a></li>
      <li><a href="<?= APP_URL ?>/role-selection.php" class="lab-nav-link">অন্যান্য পোর্টাল</a></li>
    </ul>

    <div class="lab-nav-actions">
      <a href="<?= APP_URL ?>/lab/login.php" class="btn-lab-login">
        <i class="bi bi-box-arrow-in-right"></i> ল্যাবরেটরি লগইন
      </a>
      <a href="<?= APP_URL ?>/lab/register.php" class="btn-lab-reg">
        <i class="bi bi-patch-plus-fill"></i> পার্টনার ল্যাব অনবোর্ডিং
      </a>
    </div>
  </div>
</nav>

<!-- LAB HERO -->
<section class="lab-hero">
  <div class="gov-container">
    <div class="lab-hero-layout">
      
      <!-- Left Content -->
      <div class="lab-hero-content">
        <div class="lab-badge">
          <i class="bi bi-patch-check-fill"></i> ৮-সংখ্যার শর্ট টোকেন ভিত্তিক সুরক্ষিত ডায়াগনস্টিক রিপোর্ট ডেলিভারি
        </div>

        <h1 class="lab-hero-title">
          প্রেসক্রিপশন টোকেন দিয়ে সরাসরি টেস্ট শনাক্ত ও<br>
          <span>রোগীর প্রোফাইলে তাৎক্ষণিক রেজাল্ট সিঙ্ক।</span>
        </h1>

        <p class="lab-hero-subtitle">
          রোগী ডায়াগনস্টিক সেন্টারে আসলে তার প্রেসক্রিপশনের ৮ ডিজিটের শর্ট টোকেন (LT-XXXXX) দিয়ে তাৎক্ষণিক ডাক্তারের নির্দেশিত টেস্টগুলো ভেরিফাই করুন এবং ফাইন্ডিংস বা পিডিএফ রিপোর্ট আপলোড করে রোগীর আজীবন রেকর্ডে পৌঁছে দিন।
        </p>

        <div class="lab-hero-buttons">
          <a href="<?= APP_URL ?>/lab/login.php" class="btn-lab-primary">
            <i class="bi bi-box-arrow-in-right"></i> ডায়াগনস্টিক ল্যাবরেটরি লগইন
          </a>
          <a href="<?= APP_URL ?>/lab/register.php" class="btn-lab-secondary">
            <i class="bi bi-patch-plus-fill"></i> পার্টনার ল্যাব রেজিস্ট্রেশন
          </a>
        </div>

        <!-- Lab Micro Demo Box -->
        <div class="demo-showcase-box">
          <div>
            <div style="font-size:12px;font-weight:800;color:#D97706;text-transform:uppercase;">⚡ পরীক্ষামূলক ডেমো ল্যাব অ্যাকাউন্ট:</div>
            <div style="font-size:14px;color:#0A2540;font-weight:700;margin-top:2px;">
              ল্যাব: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#D97706;">Apex Diagnostics Ltd.</code> · 
              Email: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#D97706;">lab@apexdiagnostics.com</code> · 
              Pass: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#D97706;">Demo123!</code>
            </div>
          </div>
          <a href="<?= APP_URL ?>/lab/login.php" class="btn-lab-primary" style="padding:10px 18px;font-size:13px;">
            সরাসরি ল্যাব ড্যাশবোর্ড &rarr;
          </a>
        </div>

      </div>

      <!-- Right Column: Lab Search & Result Preview Card -->
      <div class="lab-hero-card-col">
        <div class="lab-card">
          <div class="lab-card-top">
            <span><i class="bi bi-upc-scan"></i> INSTANT TOKEN SEARCH SYSTEM</span>
            <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:999px;">● REALTIME SYNC</span>
          </div>

          <div class="lab-card-body">
            <!-- Token Search Simulation Box -->
            <div style="background:#F8FAFC;border:2px solid #CBD5E1;border-radius:12px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
              <div>
                <div style="font-size:10px;font-weight:800;color:#64748B;text-transform:uppercase;">Prescription Short Token</div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:18px;font-weight:900;color:#0A2540;">LT-99B2A</div>
              </div>
              <span style="background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;font-size:11px;font-weight:800;padding:4px 8px;border-radius:6px;">
                VERIFIED ORDER
              </span>
            </div>

            <!-- Patient Info -->
            <div style="border-bottom:1px solid #F1F5F9;padding-bottom:12px;margin-bottom:14px;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                  <h4 style="margin:0;font-size:15px;font-weight:800;color:#0A2540;">Rahat Mahamud</h4>
                  <div style="font-size:12px;color:#64748B;">PT-000001 · ৩১ বছর · 🩸 O+</div>
                </div>
                <div style="font-size:11.5px;color:#0284C7;font-weight:700;">
                  Dr. Farhana Rahman (ABC Hosp)
                </div>
              </div>
            </div>

            <!-- Prescribed Tests List -->
            <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:12px;padding:12px;margin-bottom:16px;">
              <div style="font-size:11px;font-weight:800;color:#B45309;text-transform:uppercase;margin-bottom:4px;">ডাক্তারের নির্দেশিত টেস্ট:</div>
              <div style="font-size:13.5px;font-weight:800;color:#0A2540;">Complete Blood Count (CBC) with ESR</div>
              <div style="font-size:11.5px;color:#64748B;margin-top:2px;">Routine Blood Hematology Profile</div>
            </div>

            <!-- One-Click Result Action -->
            <div style="display:flex;gap:10px;">
              <a href="<?= APP_URL ?>/lab/login.php" style="flex:1;background:linear-gradient(135deg,#0A2540,#D97706);color:#fff;border-radius:8px;padding:10px;font-size:12.5px;font-weight:800;text-align:center;text-decoration:none;">
                <i class="bi bi-file-earmark-arrow-up-fill"></i> রেজাল্ট ও রিপোর্ট আপলোড
              </a>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- LAB CAPABILITIES -->
<section class="lab-section" style="background:#F8FAFC;">
  <div class="gov-container">
    <div class="lab-section-header">
      <span style="font-size:12px;font-weight:800;color:#D97706;text-transform:uppercase;background:#FFFBEB;padding:4px 12px;border-radius:999px;">Laboratory Integration</span>
      <h2 class="lab-section-title">ডায়াগনস্টিক ল্যাবরেটরির মূল সেবাসমূহ</h2>
      <p style="color:#64748B;font-size:15px;margin:0;">কীভাবে MedCore ল্যাব টেস্ট ভেরিফিকেশন ও রিপোর্ট ডেলিভারি সহজ করে।</p>
    </div>

    <div class="lab-cards-grid">
      <div class="lab-card-item">
        <div>
          <div class="lab-card-icon" style="background:#FFFBEB;color:#D97706;"><i class="bi bi-qr-code"></i></div>
          <h3>৮-সংখ্যার ইউনিক টোকেন সার্চ</h3>
          <p>রোগী চেম্বার থেকে প্রেসক্রিপশন পেলে ল্যাব কাউন্টারে ৮ ডিজিটের শর্ট টোকেন দিলেই স্বয়ংক্রিয় অর্ডার লোড হয়।</p>
        </div>
        <a href="<?= APP_URL ?>/lab/login.php" style="color:#D97706;font-weight:800;text-decoration:none;">টোকেন সার্চ &rarr;</a>
      </div>

      <div class="lab-card-item">
        <div>
          <div class="lab-card-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-file-earmark-pdf-fill"></i></div>
          <h3>সরাসরি টেস্ট ফাইন্ডিংস ও পিডিএফ আপলোড</h3>
          <p>ল্যাবরেটরি টেকনিশিয়ান রেফারেন্স রেঞ্জ সহ রেজাল্ট লিখে অথবা মূল পিডিএফ ও ছবি ফাইল সংযুক্ত করতে পারেন।</p>
        </div>
        <a href="<?= APP_URL ?>/lab/login.php" style="color:#059669;font-weight:800;text-decoration:none;">রেজাল্ট এন্ট্রি &rarr;</a>
      </div>

      <div class="lab-card-item">
        <div>
          <div class="lab-card-icon" style="background:#EFF6FF;color:#0284C7;"><i class="bi bi-bell-fill"></i></div>
          <h3>রোগী ও ডাক্তারের কাছে তাৎক্ষণিক নোটিফিকেশন</h3>
          <p>রিপোর্ট সাবমিট হওয়ার সাথে সাথেই রোগীর মোবাইল ও রেফারকারী চিকিৎসকের চেম্বার ড্যাশবোর্ডে সিঙ্ক হয়ে যায়।</p>
        </div>
        <a href="<?= APP_URL ?>/lab/login.php" style="color:#0284C7;font-weight:800;text-decoration:none;">সিঙ্ক নেটওয়ার্ক &rarr;</a>
      </div>

      <div class="lab-card-item">
        <div>
          <div class="lab-card-icon" style="background:#F5F3FF;color:#7C3AED;"><i class="bi bi-building-check"></i></div>
          <h3>হাসপাতাল অ্যাফিলিয়েশন ও পার্টনারশিপ</h3>
          <p>সরকারি ও শীর্ষস্থানীয় বেসরকারি হাসপাতালের সাথে সরাসরি অনুমোদিত ডায়াগনস্টিক পার্টনার হিসেবে কাজ করার সুযোগ।</p>
        </div>
        <a href="<?= APP_URL ?>/lab/register.php" style="color:#7C3AED;font-weight:800;text-decoration:none;">ল্যাব রেজিস্ট্রেশন &rarr;</a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer style="background:#FFFFFF;border-top:1px solid #E2E8F0;padding:40px 0 24px;font-size:13px;color:#64748B;">
  <div class="gov-container">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
      <div>
        <strong style="color:#0A2540;font-size:15px;">MedCore Diagnostic Lab</strong> — সেন্ট্রালাইজড ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক।
      </div>
      <div style="display:flex;gap:16px;">
        <a href="<?= APP_URL ?>/" style="color:#D97706;text-decoration:none;font-weight:700;">মূল ওয়েবসাইট</a>
        <a href="<?= APP_URL ?>/lab/login.php" style="color:#64748B;text-decoration:none;">ল্যাব লগইন</a>
        <a href="<?= APP_URL ?>/lab/register.php" style="color:#64748B;text-decoration:none;">ল্যাব নিবন্ধন</a>
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
