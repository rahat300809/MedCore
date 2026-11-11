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
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">

  <style>
    /* ============================================================
       MEDCORE ULTRA-CLEAN WHITE THEME STYLES (SCOPED & BULLETPROOF)
       ============================================================ */
    body.landing-page {
      background: #FFFFFF !important;
      color: #0F172A !important;
      font-family: 'Plus Jakarta Sans', var(--font-main), -apple-system, sans-serif !important;
      margin: 0;
      padding: 0;
      overflow-x: hidden;
    }

    /* --- TOPBAR (NAVBAR) --- */
    .mc-navbar {
      background: #FFFFFF !important;
      border-bottom: 1px solid #E2E8F0 !important;
      position: sticky !important;
      top: 0 !important;
      z-index: 1000 !important;
      height: 70px !important;
      display: flex !important;
      align-items: center !important;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04) !important;
    }
    .mc-navbar-container {
      max-width: 1280px;
      width: 100%;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    .mc-nav-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      flex-shrink: 0;
    }
    .mc-brand-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, #0284C7, #2563EB);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
    }
    .mc-brand-text {
      font-size: 20px;
      font-weight: 800;
      letter-spacing: -0.02em;
      color: #0F172A;
    }
    .mc-brand-text span {
      color: #0284C7;
    }

    /* Center Nav Links (Compact, Only 3 Core Links) */
    .mc-nav-links {
      display: flex;
      align-items: center;
      gap: 12px;
      list-style: none;
      margin: 0;
      padding: 0;
      white-space: nowrap;
    }
    .mc-nav-link {
      font-size: 14px;
      font-weight: 600;
      color: #475569;
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }
    .mc-nav-link:hover {
      color: #0284C7;
      background: #F0F9FF;
    }

    /* Right Action Buttons (Compact, Zero Overlapping, Never Wrapping) */
    .mc-nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .mc-btn-serial {
      background: #EFF6FF;
      color: #1D4ED8;
      border: 1px solid #BFDBFE;
      padding: 7px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .mc-btn-serial:hover {
      background: #DBEAFE;
      color: #1E40AF;
      transform: translateY(-1px);
    }
    .mc-btn-ice {
      background: #FEF2F2;
      color: #DC2626;
      border: 1px solid #FECACA;
      padding: 7px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.2s ease;
    }
    .mc-btn-ice:hover {
      background: #FEE2E2;
      color: #B91C1C;
    }
    .mc-btn-login {
      background: #FFFFFF;
      color: #334155;
      border: 1px solid #CBD5E1;
      padding: 7px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .mc-btn-login:hover {
      background: #F8FAFC;
      border-color: #94A3B8;
      color: #0F172A;
    }
    .mc-btn-register {
      background: linear-gradient(135deg, #0284C7, #2563EB);
      color: #FFFFFF;
      border: none;
      padding: 7px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);
      transition: all 0.2s ease;
    }
    .mc-btn-register:hover {
      background: linear-gradient(135deg, #0369A1, #1D4ED8);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
      color: #FFFFFF;
    }

    .mc-hamburger {
      display: none;
      background: transparent;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 20px;
      color: #0F172A;
      cursor: pointer;
    }

    /* Responsive Breakpoints for Navbar */
    @media (max-width: 1024px) {
      .mc-nav-links {
        display: none;
      }
      .mc-hamburger {
        display: flex;
        align-items: center;
        justify-content: center;
      }
    }
    @media (max-width: 640px) {
      .mc-btn-ice {
        display: none;
      }
      .mc-navbar-container {
        padding: 0 16px;
      }
    }

    /* Mobile Dropdown Drawer */
    .mc-mobile-drawer {
      display: none;
      position: absolute;
      top: 70px;
      left: 0;
      width: 100%;
      background: #FFFFFF;
      border-bottom: 1px solid #E2E8F0;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
      padding: 20px 24px;
      z-index: 999;
      flex-direction: column;
      gap: 12px;
    }
    .mc-mobile-drawer.active {
      display: flex;
    }

    /* --- HERO SECTION (ABSOLUTELY NO OVERLAPPING) --- */
    .mc-hero-section {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: #FFFFFF !important;
      padding: 56px 0 60px !important;
      border-bottom: 1px solid #F1F5F9;
    }
    .mc-hero-container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 24px;
      box-sizing: border-box;
    }
    .mc-hero-layout {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 48px;
      width: 100%;
    }
    .mc-hero-content {
      flex: 1 1 58%;
      min-width: 0;
    }
    .mc-hero-preview {
      flex: 0 0 420px;
      max-width: 440px;
      min-width: 320px;
    }

    /* Trust Pill */
    .mc-trust-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #F0F9FF;
      border: 1px solid #BAE6FD;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      color: #0369A1;
      margin-bottom: 20px;
    }

    /* Hero Heading & Subheading */
    .mc-hero-title {
      font-size: clamp(32px, 4vw, 48px);
      font-weight: 900;
      line-height: 1.18;
      color: #0F172A;
      margin: 0 0 16px;
      letter-spacing: -0.03em;
    }
    .mc-hero-title span {
      color: #0284C7;
    }
    .mc-hero-subtitle {
      font-size: 16px;
      color: #475569;
      line-height: 1.65;
      max-width: 580px;
      margin: 0 0 28px;
    }

    /* Hero Main Buttons */
    .mc-hero-ctas {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 36px;
    }
    .mc-cta-primary {
      background: linear-gradient(135deg, #0284C7 0%, #1D4ED8 100%);
      color: #FFFFFF !important;
      font-weight: 800;
      font-size: 15px;
      padding: 13px 26px;
      border-radius: 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 6px 20px rgba(2, 132, 199, 0.3);
      transition: all 0.2s ease;
    }
    .mc-cta-primary:hover {
      background: linear-gradient(135deg, #0369A1 0%, #1E40AF 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(2, 132, 199, 0.4);
    }
    .mc-cta-secondary {
      background: #FFFFFF;
      color: #0F172A !important;
      font-weight: 700;
      font-size: 15px;
      padding: 13px 22px;
      border-radius: 12px;
      border: 1px solid #CBD5E1;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .mc-cta-secondary:hover {
      background: #F8FAFC;
      border-color: #94A3B8;
      transform: translateY(-2px);
    }

    /* Quick Role Cards */
    .mc-quick-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
    }
    .mc-quick-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 12px;
      padding: 14px 16px;
      text-decoration: none;
      color: inherit;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
      transition: all 0.2s ease;
    }
    .mc-quick-card:hover {
      border-color: #0284C7;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(2, 132, 199, 0.1);
    }
    .mc-quick-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .mc-quick-info h4 {
      margin: 0;
      font-size: 13px;
      font-weight: 800;
      color: #0F172A;
    }
    .mc-quick-info p {
      margin: 2px 0 0;
      font-size: 11px;
      color: #64748B;
    }

    /* Live EHR Interactive Card Preview */
    .mc-live-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 20px;
      padding: 26px;
      box-shadow: 0 16px 40px rgba(15, 23, 42, 0.07);
    }
    .mc-live-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #F1F5F9;
      padding-bottom: 16px;
      margin-bottom: 16px;
    }
    .mc-user-badge {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .mc-user-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0284C7, #2563EB);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      font-weight: 800;
    }
    .mc-user-name {
      font-size: 16px;
      font-weight: 800;
      color: #0F172A;
      margin: 0;
    }
    .mc-user-meta {
      font-size: 12px;
      color: #64748B;
      margin-top: 2px;
    }
    .mc-status-pill {
      background: #ECFDF5;
      color: #047857;
      border: 1px solid #A7F3D0;
      font-size: 11px;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 999px;
      white-space: nowrap;
    }

    /* Allergy Alert Box */
    .mc-alert-box {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      border-radius: 12px;
      padding: 12px 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 16px;
    }
    .mc-alert-icon {
      font-size: 18px;
    }
    .mc-alert-title {
      font-size: 12px;
      font-weight: 800;
      color: #DC2626;
      margin: 0;
    }
    .mc-alert-desc {
      font-size: 11px;
      color: #64748B;
      margin: 1px 0 0;
    }

    /* Live Serial Box */
    .mc-serial-box {
      background: linear-gradient(135deg, #0284C7 0%, #1D4ED8 100%);
      border-radius: 14px;
      padding: 16px;
      color: #FFFFFF;
      margin-bottom: 16px;
    }
    .mc-serial-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11px;
      font-weight: 700;
      opacity: 0.9;
      margin-bottom: 8px;
    }
    .mc-serial-body {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .mc-serial-number {
      background: #FFFFFF;
      color: #1D4ED8;
      font-family: 'JetBrains Mono', monospace;
      font-size: 30px;
      font-weight: 900;
      padding: 4px 12px;
      border-radius: 10px;
      line-height: 1;
    }
    .mc-serial-details h5 {
      margin: 0;
      font-size: 14px;
      font-weight: 800;
      color: #FFFFFF;
    }
    .mc-serial-details p {
      margin: 2px 0 0;
      font-size: 11px;
      color: #DBEAFE;
    }

    /* Lab Token Widget */
    .mc-lab-box {
      background: #FAF5FF;
      border: 1px solid #E9D5FF;
      border-radius: 12px;
      padding: 12px 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .mc-lab-token {
      font-family: 'JetBrains Mono', monospace;
      font-size: 16px;
      font-weight: 800;
      color: #6D28D9;
      margin: 2px 0 0;
    }

    @media (max-width: 991px) {
      .mc-hero-layout {
        flex-direction: column;
        gap: 36px;
      }
      .mc-hero-preview {
        flex: 1 1 auto;
        width: 100%;
        max-width: 460px;
      }
    }

    /* --- CORE SERVICES SECTION --- */
    .mc-services-section {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: #F8FAFC !important;
      padding: 64px 0 !important;
      border-top: 1px solid #E2E8F0;
      border-bottom: 1px solid #E2E8F0;
    }
    .mc-section-header {
      text-align: center;
      max-width: 640px;
      margin: 0 auto 44px;
    }
    .mc-section-badge {
      font-size: 12px;
      font-weight: 800;
      color: #0284C7;
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }
    .mc-section-title {
      font-size: 30px;
      font-weight: 800;
      color: #0F172A;
      margin: 8px 0 10px;
      letter-spacing: -0.02em;
    }
    .mc-section-desc {
      font-size: 15px;
      color: #64748B;
      line-height: 1.6;
      margin: 0;
    }
    .mc-services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
    }
    .mc-feature-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 26px 22px;
      box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
      transition: all 0.25s ease;
    }
    .mc-feature-card:hover {
      border-color: #93C5FD;
      box-shadow: 0 10px 25px rgba(2, 132, 199, 0.08);
      transform: translateY(-3px);
    }
    .mc-feature-icon {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      margin-bottom: 16px;
    }
    .mc-feature-card h3 {
      font-size: 17px;
      font-weight: 800;
      color: #0F172A;
      margin: 0 0 8px;
    }
    .mc-feature-card p {
      font-size: 13.5px;
      color: #64748B;
      line-height: 1.6;
      margin: 0 0 16px;
    }
    .mc-feature-link {
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    /* --- PORTALS SECTION --- */
    .mc-portals-section {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: #FFFFFF !important;
      padding: 64px 0 !important;
      border-bottom: 1px solid #E2E8F0;
    }
    .mc-portals-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
    }
    .mc-portal-box {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 28px 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
      transition: all 0.25s ease;
    }
    .mc-portal-box:hover {
      border-color: #38BDF8;
      box-shadow: 0 12px 30px rgba(2, 132, 199, 0.1);
      transform: translateY(-3px);
    }
    .mc-portal-box h3 {
      font-size: 19px;
      font-weight: 800;
      color: #0F172A;
      margin: 0 0 6px;
    }
    .mc-portal-box p {
      font-size: 13px;
      color: #64748B;
      margin: 0 0 16px;
    }
    .mc-portal-features {
      list-style: none;
      padding: 0;
      margin: 0 0 24px;
      font-size: 13px;
      color: #475569;
      line-height: 1.8;
    }
    .mc-portal-features li {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .mc-portal-btn {
      width: 100%;
      padding: 11px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      text-align: center;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: all 0.2s ease;
    }

    /* --- EMERGENCY BANNER --- */
    .mc-ice-section {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: linear-gradient(180deg, #FEF2F2 0%, #FFF5F5 100%) !important;
      padding: 56px 0 !important;
      border-bottom: 1px solid #FECACA;
    }
    .mc-ice-banner {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      align-items: center;
      gap: 36px;
    }
    .mc-ice-content h3 {
      font-size: 26px;
      font-weight: 900;
      color: #991B1B;
      margin: 8px 0 10px;
      letter-spacing: -0.02em;
    }
    .mc-ice-content p {
      font-size: 14.5px;
      color: #7F1D1D;
      line-height: 1.65;
      margin: 0 0 20px;
    }
    .mc-ice-btn {
      background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
      color: #FFFFFF !important;
      font-weight: 800;
      font-size: 14px;
      padding: 12px 24px;
      border-radius: 10px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
      white-space: nowrap;
      transition: all 0.2s ease;
    }
    .mc-ice-btn:hover {
      background: linear-gradient(135deg, #B91C1C 0%, #991B1B 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(220, 38, 38, 0.4);
    }
    .mc-ice-mini-card {
      background: #FFFFFF;
      border: 2px solid #FECACA;
      border-radius: 20px;
      padding: 22px;
      box-shadow: 0 14px 34px rgba(220, 38, 38, 0.12);
      text-decoration: none;
      color: inherit;
      display: block;
      transition: all 0.25s ease;
    }
    .mc-ice-mini-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 45px rgba(220, 38, 38, 0.18);
      border-color: #F87171;
    }
    @media (max-width: 900px) {
      .mc-ice-banner {
        grid-template-columns: 1fr;
        gap: 28px;
      }
    }

    /* --- FOOTER --- */
    .mc-footer {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: #FFFFFF !important;
      padding: 48px 0 24px !important;
      font-size: 13.5px;
      color: #64748B;
    }
    .mc-footer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 32px;
      margin-bottom: 36px;
    }
    .mc-footer-heading {
      font-size: 13px;
      font-weight: 800;
      color: #0F172A;
      margin: 0 0 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .mc-footer-list {
      list-style: none;
      padding: 0;
      margin: 0;
      line-height: 2.2;
    }
    .mc-footer-list a {
      color: #64748B;
      text-decoration: none;
      transition: color 0.15s ease;
    }
    .mc-footer-list a:hover {
      color: #0284C7;
    }
    .mc-footer-bottom {
      border-top: 1px solid #F1F5F9;
      padding-top: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      font-size: 12px;
      color: #94A3B8;
    }
  </style>
</head>
<body class="landing-page">

<!-- =====================================================
     NAVBAR (PERFECTLY ARRANGED & SPACIOUS — NO WRAPPING)
     ===================================================== -->
<nav class="mc-navbar" id="main-navbar" role="navigation" aria-label="Main navigation">
  <div class="mc-navbar-container">
    
    <!-- Brand Logo -->
    <a href="<?= APP_URL ?>/" class="mc-nav-brand" aria-label="MedCore Home">
      <div class="mc-brand-icon" aria-hidden="true">
        <i class="bi bi-heart-pulse-fill"></i>
      </div>
      <div class="mc-brand-text">Med<span>Core</span></div>
    </a>

    <!-- Center Navigation Links (Compact & Clean) -->
    <ul class="mc-nav-links" id="desktop-nav-menu" role="menubar">
      <li role="none"><a href="#services" class="mc-nav-link" role="menuitem">সেবাসমূহ</a></li>
      <li role="none"><a href="#portals" class="mc-nav-link" role="menuitem">পোর্টালসমূহ</a></li>
      <li role="none"><a href="<?= APP_URL ?>/security.php" class="mc-nav-link" role="menuitem">নিরাপত্তা</a></li>
    </ul>

    <!-- Right Actions (High Contrast, Clearly Arranged, Zero Truncation) -->
    <div class="mc-nav-actions">
      <a href="<?= APP_URL ?>/book-doctor.php" class="mc-btn-serial" title="অনলাইন ডাক্তার সিরিয়াল বুক করুন">
        <i class="bi bi-calendar2-check-fill text-primary"></i> ডাক্তার সিরিয়াল
      </a>
      <a href="<?= APP_URL ?>/emergency.php" class="mc-btn-ice" title="জরুরি পাবলিক হেলথ কার্ড">
        <i class="bi bi-shield-exclamation text-danger"></i> ICE
      </a>
      <a href="<?= APP_URL ?>/role-selection.php" class="mc-btn-login">
        <i class="bi bi-box-arrow-in-right"></i> লগইন
      </a>
      <a href="<?= APP_URL ?>/patient/register.php" class="mc-btn-register">
        <i class="bi bi-person-plus-fill"></i> রেজিস্ট্রেশন
      </a>
      
      <!-- Mobile Toggle -->
      <button class="mc-hamburger" id="mc-mobile-btn" aria-label="Toggle menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
    </div>

  </div>

  <!-- Mobile Drawer -->
  <div class="mc-mobile-drawer" id="mc-mobile-drawer">
    <a href="#services" class="mc-nav-link">সেবাসমূহ (Core Features)</a>
    <a href="#portals" class="mc-nav-link">পোর্টালসমূহ (Access Portals)</a>
    <a href="<?= APP_URL ?>/for-patients.php" class="mc-nav-link">রোগীদের জন্য (For Patients)</a>
    <a href="<?= APP_URL ?>/for-doctors.php" class="mc-nav-link">ডাক্তারদের জন্য (For Doctors)</a>
    <a href="<?= APP_URL ?>/for-hospitals.php" class="mc-nav-link">হাসপাতালের জন্য (For Hospitals)</a>
    <a href="<?= APP_URL ?>/security.php" class="mc-nav-link">নিরাপত্তা ও প্রাইভেসি (Security)</a>
  </div>
</nav>

<!-- =====================================================
     HERO SECTION (WHITE THEME — SCOPED & CLEAN)
     ===================================================== -->
<section class="mc-hero-section" id="hero">
  <div class="mc-hero-container">
    <div class="mc-hero-layout">
      
      <!-- Left Column: Main Message & Quick Actions -->
      <div class="mc-hero-content">
        <div class="mc-trust-pill">
          <i class="bi bi-shield-fill-check text-primary"></i> DGDA স্ট্যান্ডার্ড · AES-256 সুরক্ষিত · ১০০% রোগী-সম্মতি
        </div>

        <h1 class="mc-hero-title">
          আপনার সম্পূর্ণ স্বাস্থ্য ইতিহাস।<br>
          <span>এক সেন্ট্রালাইজড প্ল্যাটফর্মে।</span>
        </h1>

        <p class="mc-hero-subtitle">
          রোগী, বিশেষজ্ঞ ডাক্তার, হাসপাতাল এবং ডায়াগনস্টিক ল্যাব—সবাই একই নেটওয়ার্কে সংযুক্ত। সহজে ডাক্তার সিরিয়াল বুক করুন এবং আজীবন স্বাস্থ্য রেকর্ড রাখুন সম্পূর্ণ নিজের নিয়ন্ত্রণে।
        </p>

        <!-- Main CTAs -->
        <div class="mc-hero-ctas">
          <a href="<?= APP_URL ?>/book-doctor.php" class="mc-cta-primary">
            <i class="bi bi-calendar2-check-fill"></i>
            অনলাইন ডাক্তার সিরিয়াল বুক করুন
          </a>
          <a href="<?= APP_URL ?>/patient/register.php" class="mc-cta-secondary">
            <i class="bi bi-person-plus-fill"></i> ফ্রি অ্যাকাউন্ট খুলুন
          </a>
        </div>

        <!-- Quick 4 Portal Access Pills -->
        <div style="font-size:12px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:12px;">
          সরাসরি লগইন পোর্টালসমূহ
        </div>
        <div class="mc-quick-grid">
          <a href="<?= APP_URL ?>/patient/login.php" class="mc-quick-card">
            <div class="mc-quick-icon" style="background:#EFF6FF; color:#2563EB;">
              <i class="bi bi-person-heart"></i>
            </div>
            <div class="mc-quick-info">
              <h4>Patient Portal</h4>
              <p>আমার স্বাস্থ্য রেকর্ড</p>
            </div>
          </a>

          <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="mc-quick-card">
            <div class="mc-quick-icon" style="background:#ECFDF5; color:#059669;">
              <i class="bi bi-stethoscope"></i>
            </div>
            <div class="mc-quick-info">
              <h4>Doctor Chamber</h4>
              <p>চেম্বার ও সিরিয়াল কিউ</p>
            </div>
          </a>

          <a href="<?= APP_URL ?>/hospital/login.php" class="mc-quick-card">
            <div class="mc-quick-icon" style="background:#F5F3FF; color:#7C3AED;">
              <i class="bi bi-building-fill-cross"></i>
            </div>
            <div class="mc-quick-info">
              <h4>Hospital Desk</h4>
              <p>ওপিডি রিসেপশন ডেস্ক</p>
            </div>
          </a>

          <a href="<?= APP_URL ?>/lab/login.php" class="mc-quick-card">
            <div class="mc-quick-icon" style="background:#FFFBEB; color:#D97706;">
              <i class="bi bi-flask-fill"></i>
            </div>
            <div class="mc-quick-info">
              <h4>Diagnostic Lab</h4>
              <p>টোকেন সার্চ ও রেজাল্ট</p>
            </div>
          </a>
        </div>

      </div>

      <!-- Right Column: Interactive Live Digital Card Preview -->
      <div class="mc-hero-preview">
        <div class="mc-live-card">
          
          <!-- Card Header -->
          <div class="mc-live-header">
            <div class="mc-user-badge">
              <div class="mc-user-avatar">R</div>
              <div>
                <h3 class="mc-user-name">Rahat Mahamud</h3>
                <div class="mc-user-meta">PT-000001 · Age: 28 · <strong style="color:#059669;">O+ Positive</strong></div>
              </div>
            </div>
            <span class="mc-status-pill">● ACTIVE EHR</span>
          </div>

          <!-- Allergy Warning Box -->
          <div class="mc-alert-box">
            <span class="mc-alert-icon">⚠️</span>
            <div>
              <div class="mc-alert-title">Severe Penicillin Allergy</div>
              <div class="mc-alert-desc">Cross-reactive: Amoxicillin (Flagged in all doctor views)</div>
            </div>
          </div>

          <!-- Today's Live Serial Card -->
          <div class="mc-serial-box">
            <div class="mc-serial-header">
              <span>TODAY'S APPOINTMENT SERIAL</span>
              <span style="background:rgba(255,255,255,0.2); padding:2px 8px; border-radius:4px;">CONFIRMED</span>
            </div>
            <div class="mc-serial-body">
              <div class="mc-serial-number">#02</div>
              <div class="mc-serial-details">
                <h5>Dr. Farhana Rahman</h5>
                <p>Cardiology · ABC General Hospital</p>
                <div style="font-size:11px; color:#BFDBFE; margin-top:3px;">Slot: 05:30 PM · Serial Token Ready</div>
              </div>
            </div>
          </div>

          <!-- Active Lab Token Widget -->
          <div class="mc-lab-box">
            <div>
              <div style="font-size:11px; font-weight:800; color:#7C3AED; text-transform:uppercase;">Short Lab Token</div>
              <div class="mc-lab-token">LT-99B2A</div>
              <div style="font-size:11px; color:#6B7280; margin-top:2px;">Test: Complete Blood Count (CBC)</div>
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
     CORE SERVICES (CLEAN WHITE THEME, NO JARGON WALLS)
     ===================================================== -->
<section class="mc-services-section" id="services">
  <div class="mc-hero-container">
    
    <div class="mc-section-header">
      <span class="mc-section-badge">Core Capabilities</span>
      <h2 class="mc-section-title">প্রধান সেবাসমূহ</h2>
      <p class="mc-section-desc">সহজে ডাক্তার সিরিয়াল বুকিং থেকে শুরু করে টেস্ট রিপোর্ট ও আজীবন স্বাস্থ্য রেকর্ড—সব এক প্ল্যাটফর্মে।</p>
    </div>

    <div class="mc-services-grid">
      
      <!-- Feature 1 -->
      <div class="mc-feature-card">
        <div class="mc-feature-icon" style="background:#EFF6FF; color:#0284C7;">
          <i class="bi bi-calendar2-check-fill"></i>
        </div>
        <h3>ডাক্তার সিরিয়াল ও লাইভ কিউ</h3>
        <p>বিভাগ ও হাসপাতাল অনুযায়ী বিশেষজ্ঞ ডাক্তার বাছাই করুন, রেটিং ও ফি দেখুন এবং রিয়েল-টাইম চেম্বার সিরিয়াল টোকেন নিন।</p>
        <a href="<?= APP_URL ?>/book-doctor.php" class="mc-feature-link" style="color:#0284C7;">
          সিরিয়াল বুক করুন &rarr;
        </a>
      </div>

      <!-- Feature 2 -->
      <div class="mc-feature-card">
        <div class="mc-feature-icon" style="background:#ECFDF5; color:#059669;">
          <i class="bi bi-clipboard2-pulse-fill"></i>
        </div>
        <h3>আজীবন মেডিক্যাল রেকর্ড</h3>
        <p>কাগজের প্রেসক্রিপশন হারানোর ভয় নেই। অতীতের প্রেসক্রিপশন, ডায়াগনস্টিক রিপোর্ট ও ওষুধ হিস্ট্রি একটি মাত্র নিরাপদ প্রোফাইলে সংরক্ষিত।</p>
        <a href="<?= APP_URL ?>/for-patients.php" class="mc-feature-link" style="color:#059669;">
          বিস্তারিত জানুন &rarr;
        </a>
      </div>

      <!-- Feature 3 -->
      <div class="mc-feature-card">
        <div class="mc-feature-icon" style="background:#F5F3FF; color:#7C3AED;">
          <i class="bi bi-qr-code-scan"></i>
        </div>
        <h3>শর্ট ডায়াগনস্টিক টোকেন</h3>
        <p>প্রেসক্রিপশনের সাথে তৈরি হওয়া ইউনিক শর্ট টোকেন (LT-XXXXX) যেকোনো অনুমোদিত ল্যাবে দেখালেই অটো-সিঙ্ক হয়ে যাবে রিপোর্ট।</p>
        <a href="<?= APP_URL ?>/for-hospitals.php" class="mc-feature-link" style="color:#7C3AED;">
          ল্যাব নেটওয়ার্ক &rarr;
        </a>
      </div>

      <!-- Feature 4 -->
      <div class="mc-feature-card">
        <div class="mc-feature-icon" style="background:#FEF2F2; color:#DC2626;">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h3>রোগী-নিয়ন্ত্রিত নিরাপত্তা</h3>
        <p>রোগীর ওটিপি (OTP) বা অনুমতি ছাড়া কোনো ডাক্তার রেকর্ড দেখতে পারেন না। প্রতিটি সেশন ৩০ মিনিট পর স্বয়ংক্রিয়ভাবে লক হয়ে যায়।</p>
        <a href="<?= APP_URL ?>/security.php" class="mc-feature-link" style="color:#DC2626;">
          নিরাপত্তা স্ট্যান্ডার্ড &rarr;
        </a>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     PORTALS SECTION (CLEAN CARDS FOR EVERY ROLE)
     ===================================================== -->
<section class="mc-portals-section" id="portals">
  <div class="mc-hero-container">
    
    <div class="mc-section-header">
      <span class="mc-section-badge">Tailored Portals</span>
      <h2 class="mc-section-title">আপনার পোর্টাল নির্বাচন করুন</h2>
      <p class="mc-section-desc">প্রতিটি ভূমিকার জন্য আলাদা ডেডিকেটেড ওয়ার্কস্পেস ও ক্লিনিক্যাল টুলস।</p>
    </div>

    <div class="mc-portals-grid">
      
      <!-- Patient Portal -->
      <div class="mc-portal-box">
        <div>
          <div class="mc-feature-icon" style="background:#EFF6FF; color:#2563EB;">
            <i class="bi bi-person-heart"></i>
          </div>
          <h3>Patient Portal</h3>
          <p>আমার স্বাস্থ্য রেকর্ড, আমার নিয়ন্ত্রণে।</p>
          <ul class="mc-portal-features">
            <li><i class="bi bi-check2 text-primary"></i> আজীবন প্রেসক্রিপশন ও রিপোর্ট হিস্ট্রি</li>
            <li><i class="bi bi-check2 text-primary"></i> লাইভ ডাক্তার সিরিয়াল ট্র্যাকিং</li>
            <li><i class="bi bi-check2 text-primary"></i> ডাক্তারের অ্যাক্সেস অনুমোদন বা বাতিল</li>
            <li><i class="bi bi-check2 text-primary"></i> ল্যাব টোকেন ও ওষুধ রিমাইন্ডার</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/patient/login.php" class="mc-portal-btn" style="background:#2563EB; color:#FFFFFF;">
          <i class="bi bi-box-arrow-in-right"></i> Patient Login
        </a>
      </div>

      <!-- Doctor Chamber -->
      <div class="mc-portal-box" style="border-top:3px solid #059669;">
        <div>
          <div class="mc-feature-icon" style="background:#ECFDF5; color:#059669;">
            <i class="bi bi-stethoscope"></i>
          </div>
          <h3>Doctor Chamber</h3>
          <p>দ্রুত ক্লিনিক্যাল কনসালটেশন ও প্রেসক্রিপশন।</p>
          <ul class="mc-portal-features">
            <li><i class="bi bi-check2 text-success"></i> মাল্টি-হাসপাতাল চেম্বার ম্যানেজমেন্ট</li>
            <li><i class="bi bi-check2 text-success"></i> লাইভ পেশেন্ট কিউ ও সিরিয়াল রস্টার</li>
            <li><i class="bi bi-check2 text-success"></i> এক্সিকিউটিভ পেশেন্ট মেডিক্যাল হিস্ট্রি</li>
            <li><i class="bi bi-check2 text-success"></i> বাংলা ডোজ প্রিসেট সহ ই-প্রেসক্রিপশন</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="mc-portal-btn" style="background:#059669; color:#FFFFFF;">
          <i class="bi bi-box-arrow-in-right"></i> Doctor Login
        </a>
      </div>

      <!-- Hospital Portal -->
      <div class="mc-portal-box">
        <div>
          <div class="mc-feature-icon" style="background:#F5F3FF; color:#7C3AED;">
            <i class="bi bi-building-fill-cross"></i>
          </div>
          <h3>Hospital Desk</h3>
          <p>সম্পূর্ণ প্রাতিষ্ঠানিক ও ওপিডি প্রশাসন।</p>
          <ul class="mc-portal-features">
            <li><i class="bi bi-check2 text-purple" style="color:#7C3AED;"></i> ওপিডি অভ্যর্থনা ও লবি চেক-ইন</li>
            <li><i class="bi bi-check2 text-purple" style="color:#7C3AED;"></i> অ্যাফিলিয়েটেড ডাক্তার ও ডিপার্টমেন্ট রস্টার</li>
            <li><i class="bi bi-check2 text-purple" style="color:#7C3AED;"></i> ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক</li>
            <li><i class="bi bi-check2 text-purple" style="color:#7C3AED;"></i> দৈনিক ওপিডি রেজিস্টার ও অডিট</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/hospital/login.php" class="mc-portal-btn" style="background:#FFFFFF; color:#334155; border:1px solid #CBD5E1;">
          <i class="bi bi-box-arrow-in-right"></i> Hospital Login
        </a>
      </div>

      <!-- Diagnostic Lab Portal -->
      <div class="mc-portal-box">
        <div>
          <div class="mc-feature-icon" style="background:#FFFBEB; color:#D97706;">
            <i class="bi bi-flask-fill"></i>
          </div>
          <h3>Diagnostic Lab</h3>
          <p>দ্রুত টোকেন সার্চ ও সরাসরি রেজাল্ট আপলোড।</p>
          <ul class="mc-portal-features">
            <li><i class="bi bi-check2 text-warning" style="color:#D97706;"></i> ৮ ডিজিটের শর্ট ল্যাব টোকেন সার্চ</li>
            <li><i class="bi bi-check2 text-warning" style="color:#D97706;"></i> ডাক্তারের নির্দেশিত টেস্ট ভেরিফাই</li>
            <li><i class="bi bi-check2 text-warning" style="color:#D97706;"></i> টেস্ট ফাইন্ডিংস এন্ট্রি ও রিপোর্ট আপলোড</li>
            <li><i class="bi bi-check2 text-warning" style="color:#D97706;"></i> রোগীর অ্যাকাউন্টে তাৎক্ষণিক সিঙ্ক</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/lab/login.php" class="mc-portal-btn" style="background:#D97706; color:#FFFFFF;">
          <i class="bi bi-box-arrow-in-right"></i> Lab Login
        </a>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     EMERGENCY ICE BANNER (CRISP & BEAUTIFUL SHOWCASE)
     ===================================================== -->
<section class="mc-ice-section">
  <div class="mc-hero-container">
    <div class="mc-ice-banner">
      
      <!-- Left Content -->
      <div class="mc-ice-content">
        <span style="background:#DC2626; color:#fff; font-size:11px; font-weight:800; padding:4px 12px; border-radius:999px; text-transform:uppercase; letter-spacing:0.6px; display:inline-flex; align-items:center; gap:5px;">
          <i class="bi bi-shield-exclamation"></i> First Responder ICE Profile
        </span>
        <h3>রোড এক্সিডেন্ট বা জরুরি পরিস্থিতিতে ১-ক্লিকে পরিচয়</h3>
        <p>
          লগইন বা পাসওয়ার্ড ছাড়াই দুর্ঘটনা স্থানে উপস্থিত উদ্ধারকারী প্যারামেডিক ও চিকিৎসকরা রোগীর রক্তের গ্রুপ, জীবনঘাতী ওষুধের অ্যালার্জি এবং স্বজনের জরুরি মোবাইল নম্বর দেখতে পারবেন।
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
          <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001" target="_blank" class="mc-ice-btn">
            <i class="bi bi-heart-pulse-fill"></i> ওপেন লাইভ ICE কার্ড ডেমো
          </a>
          <a href="<?= APP_URL ?>/emergency.php" class="btn btn-secondary" style="border-radius:10px; font-weight:700; background:#FFFFFF; color:#991B1B; border:1px solid #FECACA; padding:12px 20px;">
            <i class="bi bi-search"></i> আইডি দিয়ে কার্ড খুঁজুন
          </a>
        </div>
      </div>

      <!-- Right: Realistic Mini ICE Card Preview -->
      <div>
        <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001" target="_blank" class="mc-ice-mini-card">
          <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #FEE2E2; padding-bottom:10px; margin-bottom:12px;">
            <div style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:900; color:#DC2626; text-transform:uppercase; letter-spacing:0.5px;">
              <i class="bi bi-heart-pulse-fill"></i> IN CASE OF EMERGENCY (ICE)
            </div>
            <span style="background:#ECFDF5; color:#047857; border:1px solid #A7F3D0; font-size:10px; font-weight:800; padding:2px 8px; border-radius:999px;">
              VERIFIED EHR
            </span>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px;">
            <div>
              <div style="font-size:17px; font-weight:900; color:#0F172A;">Rahat Mahamud</div>
              <div style="font-size:12px; color:#64748B;">PT-000001 · 31 Yrs · Male</div>
            </div>
            <div style="background:linear-gradient(135deg, #DC2626, #B91C1C); color:#fff; border-radius:12px; padding:6px 14px; text-align:center; box-shadow:0 4px 12px rgba(220,38,38,0.3);">
              <div style="font-size:20px; font-weight:900; line-height:1;">O+</div>
              <div style="font-size:9px; font-weight:800; text-transform:uppercase; color:#FEE2E2;">রক্তের গ্রুপ</div>
            </div>
          </div>

          <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:10px; padding:8px 12px; font-size:11.5px; color:#991B1B; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
            <div><strong>Penicillin Allergy:</strong> Anaphylaxis risk (No beta-lactams)</div>
          </div>

          <div style="display:flex; justify-content:space-between; align-items:center; background:#F0FDF4; border:1px solid #BBF7D0; border-radius:10px; padding:8px 12px; font-size:12px;">
            <span style="color:#166534; font-weight:700;"><i class="bi bi-telephone-fill"></i> Rahim Mahamud (Kin)</span>
            <span style="color:#15803D; font-weight:800; font-family:'JetBrains Mono', monospace;">01888888889</span>
          </div>
        </a>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     FOOTER (CLEAN WHITE THEME)
     ===================================================== -->
<footer class="mc-footer">
  <div class="mc-hero-container">
    
    <div class="mc-footer-grid">
      
      <!-- Brand Col -->
      <div>
        <div style="display:flex; align-items:center; gap:8px; font-weight:800; font-size:18px; color:#0F172A; margin-bottom:12px;">
          <div style="width:30px; height:30px; border-radius:8px; background:linear-gradient(135deg, #0284c7, #2563eb); color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px;">M</div>
          MedCore
        </div>
        <p style="font-size:13px; line-height:1.6; color:#64748B; margin:0 0 14px;">
          One Record. Every Care.<br>
          বাংলাদেশের সেন্ট্রালাইজড ডিজিটাল হেলথকেয়ার নেটওয়ার্ক।
        </p>
        <div style="font-size:12px; color:#94A3B8;">
          Universal Demo OTP: <code style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-weight:700; color:#0F172A;">1234</code>
        </div>
      </div>

      <!-- Quick Portals -->
      <div>
        <h4 class="mc-footer-heading">Portals</h4>
        <ul class="mc-footer-list">
          <li><a href="<?= APP_URL ?>/patient/login.php">Patient Portal</a></li>
          <li><a href="<?= APP_URL ?>/doctor/select-hospital.php">Doctor Chamber</a></li>
          <li><a href="<?= APP_URL ?>/hospital/login.php">Hospital Desk</a></li>
          <li><a href="<?= APP_URL ?>/lab/login.php">Diagnostic Lab</a></li>
        </ul>
      </div>

      <!-- Services -->
      <div>
        <h4 class="mc-footer-heading">Services</h4>
        <ul class="mc-footer-list">
          <li><a href="<?= APP_URL ?>/book-doctor.php" style="color:#0284C7; font-weight:700;">ডাক্তার সিরিয়াল বুকিং</a></li>
          <li><a href="<?= APP_URL ?>/emergency.php" style="color:#DC2626; font-weight:700;">Emergency ICE Profile</a></li>
          <li><a href="<?= APP_URL ?>/security.php">Security & Consent</a></li>
          <li><a href="<?= APP_URL ?>/for-patients.php">For Patients</a></li>
        </ul>
      </div>

      <!-- Compliance -->
      <div>
        <h4 class="mc-footer-heading">Compliance</h4>
        <div style="font-size:13px; color:#64748B; line-height:1.9;">
          <div><i class="bi bi-shield-check text-primary"></i> DGDA Standards Aligned</div>
          <div><i class="bi bi-lock-fill text-primary"></i> AES-256 Encrypted Records</div>
          <div><i class="bi bi-clock-history text-primary"></i> 30-Min Temporary Consent</div>
          <div><i class="bi bi-check-circle-fill text-success"></i> BMDC Licensure Validated</div>
        </div>
      </div>

    </div>

    <!-- Bottom Copyright -->
    <div class="mc-footer-bottom">
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
// Mobile Drawer Toggle
document.getElementById('mc-mobile-btn')?.addEventListener('click', function() {
  const drawer = document.getElementById('mc-mobile-drawer');
  if (drawer) {
    const isExpanded = drawer.classList.toggle('active');
    this.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
  }
});
</script>

<!-- MedCore AI Chatbot Agent -->
<script src="<?= APP_URL ?>/assets/js/chatbot.js" defer></script>

</body>
</html>
