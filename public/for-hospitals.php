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
  <meta name="description" content="MedCore Hospital Portal — আধুনিক হাসপাতাল প্রশাসন, ওপিডি রিসেপশন ডেস্ক, ডাক্তার রস্টার এবং ডায়াগনস্টিক ল্যাব ইন্টিগ্রেশন প্ল্যাটফর্ম।">
  <title>MedCore Hospital — হাসপাতাল প্রশাসন ও ওপিডি ডেস্ক | Institutional Platform</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Hind+Siliguri:wght@400;500;600;700&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">

  <style>
    :root {
      --hosp-primary: #7C3AED;
      --hosp-navy: #0A2540;
      --hosp-blue: #0284C7;
      --hosp-border: #E2E8F0;
      --hosp-slate: #0F172A;
      --hosp-muted: #64748B;
      --hosp-bg-light: #F8FAFC;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 0;
      background: #FFFFFF;
      color: var(--hosp-slate);
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
    .hosp-navbar {
      background: #FFFFFF;
      border-bottom: 2px solid var(--hosp-primary);
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
    }
    .hosp-nav-inner {
      height: 74px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }
    .hosp-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: inherit;
    }
    .hosp-brand-icon {
      width: 42px; height: 42px;
      border-radius: 12px;
      background: linear-gradient(135deg, #0A2540 0%, #7C3AED 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
    }
    .hosp-brand-title {
      font-size: 20px;
      font-weight: 900;
      color: #0A2540;
      line-height: 1.1;
    }
    .hosp-brand-title span { color: var(--hosp-primary); }
    .hosp-brand-sub {
      font-size: 11px;
      font-weight: 700;
      color: var(--hosp-primary);
    }

    .hosp-nav-links {
      display: flex;
      align-items: center;
      gap: 8px;
      list-style: none;
      margin: 0; padding: 0;
      white-space: nowrap;
    }
    .hosp-nav-link {
      font-size: 13.5px;
      font-weight: 700;
      color: #334155;
      text-decoration: none;
      padding: 8px 12px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }
    .hosp-nav-link:hover {
      color: var(--hosp-primary);
      background: #F5F3FF;
    }

    .hosp-nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
    }
    .btn-hosp-login {
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
    .btn-hosp-login:hover { background: #F8FAFC; border-color: #94A3B8; }
    .btn-hosp-reg {
      background: linear-gradient(135deg, #0A2540 0%, #7C3AED 100%);
      color: #FFFFFF !important;
      padding: 8px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 10px rgba(124, 58, 237, 0.25);
    }

    /* HERO */
    .hosp-hero {
      background: radial-gradient(circle at 50% 0%, #F5F3FF 0%, #FFFFFF 65%, #F8FAFC 100%);
      padding: 60px 0 70px;
      border-bottom: 1px solid #E2E8F0;
    }
    .hosp-hero-layout {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 48px;
    }
    .hosp-hero-content { flex: 1 1 58%; }
    .hosp-hero-card-col { flex: 0 0 440px; max-width: 460px; }

    .hosp-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #F5F3FF;
      border: 1px solid #DDD6FE;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12.5px;
      font-weight: 800;
      color: #6D28D9;
      margin-bottom: 20px;
    }
    .hosp-hero-title {
      font-size: clamp(30px, 3.8vw, 44px);
      font-weight: 900;
      line-height: 1.25;
      color: #0A2540;
      margin: 0 0 16px;
      letter-spacing: -0.02em;
    }
    .hosp-hero-title span { color: var(--hosp-primary); }
    .hosp-hero-subtitle {
      font-size: 16px;
      color: #475569;
      line-height: 1.7;
      margin: 0 0 28px;
      max-width: 620px;
    }

    .hosp-hero-buttons {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 36px;
    }
    .btn-hosp-primary {
      background: linear-gradient(135deg, #0A2540 0%, #7C3AED 100%);
      color: #fff !important;
      font-size: 15px;
      font-weight: 800;
      padding: 14px 28px;
      border-radius: 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 6px 20px rgba(124, 58, 237, 0.25);
    }
    .btn-hosp-secondary {
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

    /* CARD PREVIEW */
    .hosp-card {
      background: #FFFFFF;
      border: 1.5px solid #CBD5E1;
      border-radius: 20px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }
    .hosp-card-top {
      background: linear-gradient(135deg, #0A2540 0%, #6D28D9 100%);
      color: #fff;
      padding: 14px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.8px;
    }
    .hosp-card-body { padding: 22px; }

    /* SECTION */
    .hosp-section {
      padding: 64px 0;
      border-bottom: 1px solid #E2E8F0;
    }
    .hosp-section-header {
      text-align: center;
      max-width: 650px;
      margin: 0 auto 44px;
    }
    .hosp-section-title {
      font-size: 30px;
      font-weight: 900;
      color: #0A2540;
      margin: 8px 0;
    }
    .hosp-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
    }
    .hosp-card-item {
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
    .hosp-card-item:hover {
      border-color: #A78BFA;
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(124, 58, 237, 0.08);
    }
    .hosp-card-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      margin-bottom: 16px;
    }
    .hosp-card-item h3 {
      font-size: 18px;
      font-weight: 800;
      color: #0A2540;
      margin: 0 0 8px;
    }
    .hosp-card-item p {
      font-size: 14px;
      color: #64748B;
      line-height: 1.65;
      margin: 0 0 16px;
    }

    /* DEMO CREDENTIALS BOX */
    .demo-showcase-box {
      background: #F5F3FF;
      border: 2px dashed #DDD6FE;
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
      .hosp-hero-layout { flex-direction: column; }
      .hosp-hero-card-col { width: 100%; max-width: 460px; }
      .hosp-nav-links { display: none; }
    }
  </style>
</head>
<body>

<!-- GOV TOP STRIP -->
<div class="gov-top-bar">
  <div class="gov-container">
    <div class="flag-badge">
      <i class="bi bi-building-fill text-purple" style="color:#C4B5FD;"></i>
      <span>হাসপাতাল ও ক্লিনিক সেন্ট্রালাইজড ওপিডি রিসেপশন নেটওয়ার্ক | Institutional OPD Grid</span>
    </div>
    <div style="font-size: 12px; display: flex; gap: 16px;">
      <span>DGDA Hospital Standards Aligned</span>
      <span style="color:#C4B5FD;">Central Health Registry</span>
    </div>
  </div>
</div>

<!-- HOSPITAL NAVBAR -->
<nav class="hosp-navbar">
  <div class="gov-container hosp-nav-inner">
    <a href="<?= APP_URL ?>/for-hospitals.php" class="hosp-brand">
      <div class="hosp-brand-icon"><i class="bi bi-building-fill-cross"></i></div>
      <div>
        <div class="hosp-brand-title">MedCore <span>Hospital</span></div>
        <div class="hosp-brand-sub">হাসপাতাল প্রশাসন ও ওপিডি ডেস্ক</div>
      </div>
    </a>

    <ul class="hosp-nav-links">
      <li><a href="<?= APP_URL ?>/" class="hosp-nav-link">← মূল ওয়েবসাইট</a></li>
      <li><a href="<?= APP_URL ?>/hospital/login.php" class="hosp-nav-link">ওপিডি অভ্যর্থনা ডেস্ক</a></li>
      <li><a href="<?= APP_URL ?>/hospital/login.php" class="hosp-nav-link">ডাক্তার রস্টার ও শিডিউল</a></li>
      <li><a href="<?= APP_URL ?>/for-patients.php" class="hosp-nav-link">নাগরিক পোর্টাল</a></li>
      <li><a href="<?= APP_URL ?>/role-selection.php" class="hosp-nav-link">অন্যান্য পোর্টাল</a></li>
    </ul>

    <div class="hosp-nav-actions">
      <a href="<?= APP_URL ?>/hospital/login.php" class="btn-hosp-login">
        <i class="bi bi-box-arrow-in-right"></i> ওপিডি ডেস্ক লগইন
      </a>
      <a href="<?= APP_URL ?>/hospital/register.php" class="btn-hosp-reg">
        <i class="bi bi-building-add"></i> নতুন হাসপাতাল নিবন্ধন
      </a>
    </div>
  </div>
</nav>

<!-- HOSPITAL HERO -->
<section class="hosp-hero">
  <div class="gov-container">
    <div class="hosp-hero-layout">
      
      <!-- Left Content -->
      <div class="hosp-hero-content">
        <div class="hosp-badge">
          <i class="bi bi-patch-check-fill"></i> ডিজিটালাইজড ওপিডি অ্যাডমিনিস্ট্রেশন ও পেশেন্ট ফ্লো অটোমেশন
        </div>

        <h1 class="hosp-hero-title">
          হাসপাতালের প্রতিটি ওপিডি সিরিয়াল,<br>
          <span>ডাক্তার রস্টার ও ল্যাব এক প্ল্যাটফর্মে।</span>
        </h1>

        <p class="hosp-hero-subtitle">
          লবি রিসেপশনে দ্রুত পেশেন্ট চেক-ইন, বিভিন্ন ডিপার্টমেন্ট অনুযায়ী বিএমডিসি চিকিৎসকদের চেম্বার সিরিয়াল টোকেন বিতরণ এবং অনুমোদিত ডায়াগনস্টিক ল্যাবরেটরির রিয়েল-টাইম ইন্টিগ্রেশন।
        </p>

        <div class="hosp-hero-buttons">
          <a href="<?= APP_URL ?>/hospital/login.php" class="btn-hosp-primary">
            <i class="bi bi-box-arrow-in-right"></i> হাসপাতাল প্রশাসন লগইন
          </a>
          <a href="<?= APP_URL ?>/hospital/register.php" class="btn-hosp-secondary">
            <i class="bi bi-building-add"></i> প্রাতিষ্ঠানিক অনবোর্ডিং
          </a>
        </div>

        <!-- Hospital Micro Demo Box -->
        <div class="demo-showcase-box">
          <div>
            <div style="font-size:12px;font-weight:800;color:#7C3AED;text-transform:uppercase;">⚡ পরীক্ষামূলক ডেমো হাসপাতাল অ্যাকাউন্ট:</div>
            <div style="font-size:14px;color:#0A2540;font-weight:700;margin-top:2px;">
              হাসপাতাল: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#7C3AED;">ABC General Hospital</code> · 
              Email: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#7C3AED;">admin@abc-hospital.com</code> · 
              Pass: <code style="background:#fff;padding:2px 6px;border-radius:4px;color:#7C3AED;">Demo123!</code>
            </div>
          </div>
          <a href="<?= APP_URL ?>/hospital/login.php" class="btn-hosp-primary" style="padding:10px 18px;font-size:13px;">
            সরাসরি ডেস্কে প্রবেশ &rarr;
          </a>
        </div>

      </div>

      <!-- Right Column: Hospital Desk Preview Card -->
      <div class="hosp-hero-card-col">
        <div class="hosp-card">
          <div class="hosp-card-top">
            <span><i class="bi bi-building"></i> ABC GENERAL HOSPITAL · DHANMONDI</span>
            <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:999px;">● OPD DESK ACTIVE</span>
          </div>

          <div class="hosp-card-body">
            <!-- Institution Summary -->
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9;padding-bottom:14px;margin-bottom:16px;">
              <div>
                <h3 style="margin:0;font-size:17px;font-weight:900;color:#0A2540;">ABC General Hospital</h3>
                <div style="font-size:12px;color:#64748B;">HOSP-000001 · House 15, Road 7, Dhanmondi</div>
              </div>
              <span style="background:#F5F3FF;color:#6D28D9;border:1px solid #DDD6FE;font-size:11px;font-weight:800;padding:3px 8px;border-radius:6px;">
                GOVT VERIFIED
              </span>
            </div>

            <!-- Stats Grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
              <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px;text-align:center;">
                <div style="font-size:22px;font-weight:900;color:#7C3AED;">5</div>
                <div style="font-size:11px;font-weight:700;color:#64748B;">সক্রিয় ডিপার্টমেন্ট</div>
              </div>
              <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px;text-align:center;">
                <div style="font-size:22px;font-weight:900;color:#0284C7;">12</div>
                <div style="font-size:11px;font-weight:700;color:#64748B;">নিবন্ধিত ডাক্তার</div>
              </div>
              <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px;text-align:center;">
                <div style="font-size:22px;font-weight:900;color:#059669;">84</div>
                <div style="font-size:11px;font-weight:700;color:#64748B;">আজকের ওপিডি সিরিয়াল</div>
              </div>
              <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px;text-align:center;">
                <div style="font-size:22px;font-weight:900;color:#D97706;">Apex</div>
                <div style="font-size:11px;font-weight:700;color:#64748B;">পার্টনার ল্যাব নেটওয়ার্ক</div>
              </div>
            </div>

            <!-- Reception Action -->
            <div style="background:#F5F3FF;border:1px solid #DDD6FE;border-radius:12px;padding:12px;font-size:12.5px;">
              <div style="font-weight:800;color:#5B21B6;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                <i class="bi bi-person-check-fill text-purple"></i> ওপিডি ফ্রন্ট ডেস্ক ফিচার:
              </div>
              <div style="color:#475569;line-height:1.7;">
                <div>✔ ১-ক্লিকে রোগীর ফোন নম্বরে লাইভ সিরিয়াল এসএমএস</div>
                <div>✔ বিএমডিসি ডাক্তার চেম্বার ও টাইম-স্লট বরাদ্দ</div>
                <div>✔ ডায়াগনস্টিক অর্ডার ল্যাবরেটরিতে অটো-সিঙ্ক</div>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- HOSPITAL CAPABILITIES -->
<section class="hosp-section" style="background:#F8FAFC;">
  <div class="gov-container">
    <div class="hosp-section-header">
      <span style="font-size:12px;font-weight:800;color:#7C3AED;text-transform:uppercase;background:#F5F3FF;padding:4px 12px;border-radius:999px;">Hospital Administration</span>
      <h2 class="hosp-section-title">হাসপাতাল প্রশাসনের প্রধান সেবাসমূহ</h2>
      <p style="color:#64748B;font-size:15px;margin:0;">কীভাবে MedCore আপনার ওপিডি সার্ভিস ও ক্লিনিক্যাল কমপ্লায়েন্স সহজ করে।</p>
    </div>

    <div class="hosp-cards-grid">
      <div class="hosp-card-item">
        <div>
          <div class="hosp-card-icon" style="background:#F5F3FF;color:#7C3AED;"><i class="bi bi-ticket-perforated-fill"></i></div>
          <h3>ডিজিটাল ওপিডি অভ্যর্থনা ও সিরিয়াল বিতরণ</h3>
          <p>রোগী সরাসরি লবিতে আসলে তার ফোন নম্বর বা এনআইডি দিয়ে তাৎক্ষণিক চেম্বার সিরিয়াল টোকেন জেনারেট করুন।</p>
        </div>
        <a href="<?= APP_URL ?>/hospital/login.php" style="color:#7C3AED;font-weight:800;text-decoration:none;">ওপিডি ডেস্ক &rarr;</a>
      </div>

      <div class="hosp-card-item">
        <div>
          <div class="hosp-card-icon" style="background:#EFF6FF;color:#0284C7;"><i class="bi bi-person-lines-fill"></i></div>
          <h3>ডাক্তার অ্যাফিলিয়েশন ও রস্টার শিডিউলিং</h3>
          <p>অনুমোদিত বিশেষজ্ঞ চিকিৎসকদের বিভাগ নির্ধারণ, চেম্বার রুম বরাদ্দ এবং কনসালটেশন স্লট ম্যানেজমেন্ট।</p>
        </div>
        <a href="<?= APP_URL ?>/hospital/login.php" style="color:#0284C7;font-weight:800;text-decoration:none;">ডাক্তার রস্টার &rarr;</a>
      </div>

      <div class="hosp-card-item">
        <div>
          <div class="hosp-card-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-flask-fill"></i></div>
          <h3>ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক</h3>
          <p>হাসপাতালের নিজস্ব বা পার্টনার ডায়াগনস্টিক ল্যাব যুক্ত করুন, যাতে প্রেসক্রিপশন সরাসরি ল্যাবে পৌঁছায়।</p>
        </div>
        <a href="<?= APP_URL ?>/hospital/login.php" style="color:#059669;font-weight:800;text-decoration:none;">ল্যাব মডিউল &rarr;</a>
      </div>

      <div class="hosp-card-item">
        <div>
          <div class="hosp-card-icon" style="background:#FEF2F2;color:#DC2626;"><i class="bi bi-clipboard2-check-fill"></i></div>
          <h3>ডিজিটাল অডিট ও রেগুলেটরি কমপ্লায়েন্স</h3>
          <p>রোগীর চিকিৎসা সেবা ও টেস্ট প্রক্রিয়াকরণের প্রতিটি ধাপের স্বয়ংক্রিয় টাইমস্ট্যাম্পড অডিট ট্রেইল।</p>
        </div>
        <a href="<?= APP_URL ?>/security.php" style="color:#DC2626;font-weight:800;text-decoration:none;">কমপ্লায়েন্স নীতি &rarr;</a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer style="background:#FFFFFF;border-top:1px solid #E2E8F0;padding:40px 0 24px;font-size:13px;color:#64748B;">
  <div class="gov-container">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
      <div>
        <strong style="color:#0A2540;font-size:15px;">MedCore Hospital Desk</strong> — হাসপাতাল ও ওপিডি সেন্ট্রালাইজড ম্যানেজমেন্ট প্ল্যাটফর্ম।
      </div>
      <div style="display:flex;gap:16px;">
        <a href="<?= APP_URL ?>/" style="color:#7C3AED;text-decoration:none;font-weight:700;">মূল ওয়েবসাইট</a>
        <a href="<?= APP_URL ?>/hospital/login.php" style="color:#64748B;text-decoration:none;">হাসপাতাল লগইন</a>
        <a href="<?= APP_URL ?>/hospital/register.php" style="color:#64748B;text-decoration:none;">নিবন্ধন</a>
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
