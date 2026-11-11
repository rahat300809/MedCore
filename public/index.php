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
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="MedCore — গণপ্রজাতন্ত্রী বাংলাদেশ সরকারের ডিজিটাল স্বাস্থ্য ফ্রেমওয়ার্ক অনুমোদিত সেন্ট্রালাইজড হেলথকেয়ার নেটওয়ার্ক। নাগরিক স্বাস্থ্য রেকর্ড, ডাক্তার সিরিয়াল বুকিং ও ডায়াগনস্টিক ল্যাব ইন্টিগ্রেশন।">
  <title>MedCore — জাতীয় ডিজিটাল স্বাস্থ্য সেবা নেটওয়ার্ক | National Unified Health Record</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Hind+Siliguri:wght@400;500;600;700&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/landing.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">

  <style>
    /* ============================================================
       GOVERNMENT-GRADE PROFESSIONAL DIGITAL HEALTHCARE UI SYSTEM
       ============================================================ */
    :root {
      --gov-navy: #0A2540;
      --gov-blue: #0284C7;
      --gov-blue-dark: #0369A1;
      --gov-green: #059669;
      --gov-red: #DC2626;
      --gov-slate: #0F172A;
      --gov-muted: #475569;
      --gov-border: #E2E8F0;
      --gov-bg-light: #F8FAFC;
    }

    * {
      box-sizing: border-box;
    }

    body.landing-page {
      background: #FFFFFF !important;
      color: var(--gov-slate) !important;
      font-family: 'Plus Jakarta Sans', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
      margin: 0;
      padding: 0;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    h1, h2, h3, h4, h5, h6 {
      font-family: 'Plus Jakarta Sans', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    }

    p, span, a, li, div, button {
      font-family: 'Plus Jakarta Sans', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .gov-mono {
      font-family: 'JetBrains Mono', monospace !important;
    }

    /* ── 1. OFFICIAL TOP AUTHORITY STRIP ── */
    .gov-top-bar {
      background: #0A2540;
      color: #E2E8F0;
      font-size: 12px;
      padding: 7px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .gov-top-container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .gov-top-left {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      letter-spacing: 0.2px;
    }
    .gov-flag-icon {
      display: inline-block;
      width: 18px;
      height: 12px;
      background: #006A4E;
      position: relative;
      border-radius: 2px;
      flex-shrink: 0;
    }
    .gov-flag-icon::after {
      content: '';
      position: absolute;
      width: 7px;
      height: 7px;
      background: #F42A41;
      border-radius: 50%;
      top: 50%;
      left: 45%;
      transform: translate(-50%, -50%);
    }
    .gov-top-right {
      display: flex;
      align-items: center;
      gap: 16px;
      font-size: 11.5px;
    }
    .gov-helpline-pill {
      color: #93C5FD;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    /* ── 2. MAIN NAVBAR ── */
    .gov-navbar {
      background: #FFFFFF !important;
      border-bottom: 2px solid #0284C7 !important;
      position: sticky !important;
      top: 0 !important;
      z-index: 1000 !important;
      height: 74px !important;
      display: flex !important;
      align-items: center !important;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05) !important;
    }
    .gov-navbar-container {
      max-width: 1280px;
      width: 100%;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }
    .gov-nav-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      flex-shrink: 0;
    }
    .gov-brand-emblem {
      width: 42px;
      height: 42px;
      border-radius: 10px;
      background: linear-gradient(135deg, #0A2540 0%, #0284C7 100%);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.3);
    }
    .gov-brand-titles {
      display: flex;
      flex-direction: column;
    }
    .gov-brand-name {
      font-size: 20px;
      font-weight: 900;
      letter-spacing: -0.03em;
      color: #0A2540;
      line-height: 1.1;
    }
    .gov-brand-name span {
      color: #0284C7;
    }
    .gov-brand-sub {
      font-size: 11px;
      color: #64748B;
      font-weight: 700;
      letter-spacing: 0.3px;
    }

    /* Nav Links */
    .gov-nav-links {
      display: flex;
      align-items: center;
      gap: 8px;
      list-style: none;
      margin: 0;
      padding: 0;
      white-space: nowrap;
    }
    .gov-nav-link {
      font-size: 14px;
      font-weight: 700;
      color: #334155;
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 8px;
      transition: all 0.15s ease;
    }
    .gov-nav-link:hover {
      color: #0284C7;
      background: #F0F9FF;
    }

    /* Action Buttons */
    .gov-nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .gov-btn-serial {
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
      transition: all 0.2s ease;
    }
    .gov-btn-serial:hover {
      background: #DBEAFE;
      color: #1E40AF;
      transform: translateY(-1px);
    }
    .gov-btn-ice {
      background: #FEF2F2;
      color: #DC2626;
      border: 1px solid #FECACA;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .gov-btn-ice:hover {
      background: #FEE2E2;
      color: #B91C1C;
    }
    .gov-btn-login {
      background: #FFFFFF;
      color: #0F172A;
      border: 1px solid #CBD5E1;
      padding: 8px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .gov-btn-login:hover {
      background: #F8FAFC;
      border-color: #94A3B8;
    }
    .gov-btn-register {
      background: linear-gradient(135deg, #0A2540 0%, #0284C7 100%);
      color: #FFFFFF !important;
      border: none;
      padding: 8px 18px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 2px 10px rgba(10, 37, 64, 0.25);
      transition: all 0.2s ease;
    }
    .gov-btn-register:hover {
      background: linear-gradient(135deg, #031B33 0%, #0369A1 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(10, 37, 64, 0.35);
    }

    .gov-hamburger {
      display: none;
      background: transparent;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 20px;
      color: #0F172A;
      cursor: pointer;
    }

    @media (max-width: 1180px) {
      .gov-nav-links { display: none; }
      .gov-hamburger { display: flex; align-items: center; justify-content: center; }
    }
    @media (max-width: 768px) {
      .gov-btn-serial { display: none; }
      .gov-btn-ice { display: none; }
      .gov-top-right span:last-child { display: none; }
      .gov-navbar-container { padding: 0 16px; }
    }
    @media (max-width: 480px) {
      .gov-brand-sub { display: none; }
      .gov-btn-login { padding: 6px 10px; font-size: 12px; }
      .gov-btn-register { padding: 6px 12px; font-size: 12px; }
    }

    /* Mobile Drawer */
    .gov-mobile-drawer {
      display: none;
      position: absolute;
      top: 74px;
      left: 0;
      width: 100%;
      background: #FFFFFF;
      border-bottom: 2px solid #0284C7;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.1);
      padding: 20px 24px;
      z-index: 999;
      flex-direction: column;
      gap: 12px;
    }
    .gov-mobile-drawer.active { display: flex; }

    /* ── 3. HERO SECTION (GOVERNMENT GATEWAY) ── */
    .gov-hero-section {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: radial-gradient(circle at 50% 0%, #F0F9FF 0%, #FFFFFF 65%, #F8FAFC 100%) !important;
      padding: 56px 0 64px !important;
      border-bottom: 1px solid #E2E8F0;
    }
    .gov-hero-container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 24px;
      box-sizing: border-box;
    }
    .gov-hero-layout {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 48px;
      width: 100%;
    }
    .gov-hero-content {
      flex: 1 1 58%;
      min-width: 0;
    }
    .gov-hero-preview {
      flex: 0 0 430px;
      max-width: 450px;
      min-width: 320px;
    }

    /* Authority Pill */
    .gov-auth-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #FFFFFF;
      border: 1px solid #BAE6FD;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 800;
      color: #0369A1;
      margin-bottom: 20px;
      box-shadow: 0 1px 4px rgba(2, 132, 199, 0.08);
    }

    /* Hero Heading & Subheading */
    .gov-hero-title {
      font-size: clamp(32px, 4vw, 46px);
      font-weight: 900;
      line-height: 1.2;
      color: #0A2540;
      margin: 0 0 18px;
      letter-spacing: -0.03em;
    }
    .gov-hero-title span {
      color: #0284C7;
    }
    .gov-hero-subtitle {
      font-size: 16px;
      color: #475569;
      line-height: 1.7;
      max-width: 600px;
      margin: 0 0 30px;
    }

    /* Hero CTAs */
    .gov-hero-ctas {
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 38px;
    }
    .gov-cta-primary {
      background: linear-gradient(135deg, #0A2540 0%, #0284C7 100%);
      color: #FFFFFF !important;
      font-weight: 800;
      font-size: 15px;
      padding: 14px 28px;
      border-radius: 12px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 6px 20px rgba(10, 37, 64, 0.25);
      transition: all 0.2s ease;
    }
    .gov-cta-primary:hover {
      background: linear-gradient(135deg, #031B33 0%, #0369A1 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(10, 37, 64, 0.35);
    }
    .gov-cta-secondary {
      background: #FFFFFF;
      color: #0F172A !important;
      font-weight: 700;
      font-size: 15px;
      padding: 14px 24px;
      border-radius: 12px;
      border: 1px solid #CBD5E1;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }
    .gov-cta-secondary:hover {
      background: #F8FAFC;
      border-color: #94A3B8;
      transform: translateY(-2px);
    }

    /* 4 Official Portals Access Grid */
    .gov-portals-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 12px;
    }
    .gov-quick-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 14px;
      padding: 14px 16px;
      text-decoration: none;
      color: inherit;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
      transition: all 0.2s ease;
    }
    .gov-quick-card:hover {
      border-color: #0284C7;
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(2, 132, 199, 0.1);
    }
    .gov-quick-icon {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 19px;
      flex-shrink: 0;
    }
    .gov-quick-info h4 {
      margin: 0;
      font-size: 13.5px;
      font-weight: 800;
      color: #0F172A;
    }
    .gov-quick-info p {
      margin: 2px 0 0;
      font-size: 11px;
      color: #64748B;
    }

    /* National Smart Health Card Preview */
    .gov-id-card {
      background: #FFFFFF;
      border: 1.5px solid #CBD5E1;
      border-radius: 20px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(15, 23, 42, 0.02);
      overflow: hidden;
      position: relative;
    }
    .gov-card-top-header {
      background: linear-gradient(135deg, #0A2540 0%, #0369A1 100%);
      color: #FFFFFF;
      padding: 14px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .gov-card-title {
      font-size: 11px;
      font-weight: 900;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .gov-card-verified {
      background: rgba(16, 185, 129, 0.2);
      border: 1px solid rgba(16, 185, 129, 0.4);
      color: #A7F3D0;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 999px;
    }
    .gov-card-inner {
      padding: 22px;
    }
    .gov-card-person {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #F1F5F9;
      padding-bottom: 16px;
      margin-bottom: 16px;
    }
    .gov-avatar {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: linear-gradient(135deg, #0A2540, #0284C7);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      font-weight: 900;
    }

    @media (max-width: 991px) {
      .gov-hero-layout {
        flex-direction: column;
        gap: 36px;
      }
      .gov-hero-preview {
        flex: 1 1 auto;
        width: 100%;
        max-width: 460px;
      }
    }

    /* ── 4. NATIONAL CAPABILITIES / CORE SERVICES SECTION ── */
    .gov-services-section {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: #F8FAFC !important;
      padding: 70px 0 !important;
      border-top: 1px solid #E2E8F0;
      border-bottom: 1px solid #E2E8F0;
    }
    .gov-section-header {
      text-align: center;
      max-width: 680px;
      margin: 0 auto 48px;
    }
    .gov-section-badge {
      font-size: 12px;
      font-weight: 800;
      color: #0284C7;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      background: #EFF6FF;
      border: 1px solid #DBEAFE;
      padding: 4px 12px;
      border-radius: 999px;
    }
    .gov-section-title {
      font-size: 32px;
      font-weight: 900;
      color: #0A2540;
      margin: 12px 0 10px;
      letter-spacing: -0.02em;
    }
    .gov-section-desc {
      font-size: 15px;
      color: #64748B;
      line-height: 1.6;
      margin: 0;
    }
    .gov-services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
    }
    .gov-feature-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 28px 24px;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
      transition: all 0.25s ease;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .gov-feature-card:hover {
      border-color: #93C5FD;
      box-shadow: 0 12px 30px rgba(2, 132, 199, 0.08);
      transform: translateY(-4px);
    }
    .gov-feature-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      margin-bottom: 18px;
    }
    .gov-feature-card h3 {
      font-size: 18px;
      font-weight: 800;
      color: #0A2540;
      margin: 0 0 10px;
    }
    .gov-feature-card p {
      font-size: 14px;
      color: #475569;
      line-height: 1.65;
      margin: 0 0 18px;
    }
    .gov-feature-link {
      font-size: 13.5px;
      font-weight: 800;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    /* ── 5. INSTITUTIONAL PORTALS SECTION ── */
    .gov-portals-section {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: #FFFFFF !important;
      padding: 70px 0 !important;
      border-bottom: 1px solid #E2E8F0;
    }
    .gov-portals-grid-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
    }
    .gov-portal-box {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 16px;
      padding: 30px 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
      transition: all 0.25s ease;
    }
    .gov-portal-box:hover {
      border-color: #38BDF8;
      box-shadow: 0 14px 34px rgba(2, 132, 199, 0.1);
      transform: translateY(-4px);
    }
    .gov-portal-box h3 {
      font-size: 20px;
      font-weight: 800;
      color: #0A2540;
      margin: 0 0 6px;
    }
    .gov-portal-box p {
      font-size: 13px;
      color: #64748B;
      margin: 0 0 18px;
    }
    .gov-portal-features {
      list-style: none;
      padding: 0;
      margin: 0 0 24px;
      font-size: 13px;
      color: #475569;
      line-height: 1.85;
    }
    .gov-portal-features li {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .gov-portal-btn {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      font-size: 13.5px;
      font-weight: 800;
      text-align: center;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: all 0.2s ease;
    }

    /* ── 6. EMERGENCY ICE FIRST-RESPONDER SECTION ── */
    .gov-ice-section {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: linear-gradient(180deg, #FEF2F2 0%, #FFF5F5 100%) !important;
      padding: 56px 0 !important;
      border-bottom: 1px solid #FECACA;
    }
    .gov-ice-banner {
      display: grid;
      grid-template-columns: 1.15fr 0.85fr;
      align-items: center;
      gap: 36px;
    }
    .gov-ice-content h3 {
      font-size: 26px;
      font-weight: 900;
      color: #991B1B;
      margin: 8px 0 10px;
      letter-spacing: -0.02em;
    }
    .gov-ice-content p {
      font-size: 14.5px;
      color: #7F1D1D;
      line-height: 1.65;
      margin: 0 0 22px;
    }
    .gov-ice-btn {
      background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
      color: #FFFFFF !important;
      font-weight: 800;
      font-size: 14px;
      padding: 13px 26px;
      border-radius: 10px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 16px rgba(220, 38, 38, 0.3);
      white-space: nowrap;
      transition: all 0.2s ease;
    }
    .gov-ice-btn:hover {
      background: linear-gradient(135deg, #B91C1C 0%, #991B1B 100%);
      transform: translateY(-2px);
      box-shadow: 0 8px 22px rgba(220, 38, 38, 0.4);
    }
    .gov-ice-mini-card {
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
    .gov-ice-mini-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 45px rgba(220, 38, 38, 0.18);
      border-color: #F87171;
    }
    @media (max-width: 900px) {
      .gov-ice-banner {
        grid-template-columns: 1fr;
        gap: 28px;
      }
    }

    /* ── 7. GOVERNMENT PORTAL FOOTER ── */
    .gov-footer {
      position: relative !important;
      display: block !important;
      clear: both !important;
      width: 100% !important;
      background: #FFFFFF !important;
      padding: 56px 0 28px !important;
      font-size: 13.5px;
      color: #475569;
      border-top: 1px solid #E2E8F0;
    }
    .gov-footer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 36px;
      margin-bottom: 40px;
    }
    .gov-footer-heading {
      font-size: 13px;
      font-weight: 900;
      color: #0A2540;
      margin: 0 0 16px;
      text-transform: uppercase;
      letter-spacing: 0.6px;
    }
    .gov-footer-list {
      list-style: none;
      padding: 0;
      margin: 0;
      line-height: 2.2;
    }
    .gov-footer-list a {
      color: #475569;
      text-decoration: none;
      transition: color 0.15s ease;
      font-weight: 600;
    }
    .gov-footer-list a:hover {
      color: #0284C7;
    }
    .gov-footer-bottom {
      border-top: 1px solid #F1F5F9;
      padding-top: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
      font-size: 12px;
      color: #64748B;
    }
  </style>
</head>
<body class="landing-page">

<!-- =====================================================
     1. OFFICIAL NATIONAL GOVERNMENT AUTHORITY STRIP
     ===================================================== -->
<div class="gov-top-bar" role="banner" aria-label="Official Government Notification">
  <div class="gov-top-container">
    <div class="gov-top-left">
      <span class="gov-flag-icon" aria-hidden="true"></span>
      <span>গণপ্রজাতন্ত্রী বাংলাদেশ সরকার অনুমোদিত ডিজিটাল স্বাস্থ্যসেবা ফ্রেমওয়ার্ক | National Digital Health Grid</span>
    </div>
    <div class="gov-top-right">
      <span class="gov-helpline-pill"><i class="bi bi-telephone-fill"></i> স্বাস্থ্য বাতায়ন: ১৬২৬৩</span>
      <span class="gov-helpline-pill" style="color:#FCA5A5;"><i class="bi bi-shield-fill-exclamation"></i> জরুরি কল: ৯৯৯</span>
      <span>DGDA & DGHS Standards Aligned</span>
    </div>
  </div>
</div>

<!-- =====================================================
     2. MAIN NAVBAR (AUTHORITATIVE & CLEAN)
     ===================================================== -->
<nav class="gov-navbar" id="main-navbar" role="navigation" aria-label="Main navigation">
  <div class="gov-navbar-container">
    
    <!-- Brand Logo -->
    <a href="<?= APP_URL ?>/" class="gov-nav-brand" aria-label="MedCore Home">
      <div class="gov-brand-emblem" aria-hidden="true">
        <i class="bi bi-heart-pulse-fill"></i>
      </div>
      <div class="gov-brand-titles">
        <div class="gov-brand-name">Med<span>Core</span></div>
        <div class="gov-brand-sub">জাতীয় স্বাস্থ্য সেবা নেটওয়ার্ক · EHR</div>
      </div>
    </a>

    <!-- Center Navigation Links -->
    <ul class="gov-nav-links" id="desktop-nav-menu" role="menubar">
      <li role="none"><a href="#services" class="gov-nav-link" role="menuitem">নাগরিক সেবাসমূহ</a></li>
      <li role="none"><a href="<?= APP_URL ?>/book-doctor.php" class="gov-nav-link" role="menuitem">ডাক্তার ও হাসপাতাল</a></li>
      <li role="none"><a href="#portals" class="gov-nav-link" role="menuitem">পোর্টালসমূহ</a></li>
      <li role="none"><a href="<?= APP_URL ?>/security.php" class="gov-nav-link" role="menuitem">নিরাপত্তা ও স্ট্যান্ডার্ড</a></li>
    </ul>

    <!-- Right Actions -->
    <div class="gov-nav-actions">
      <a href="<?= APP_URL ?>/book-doctor.php" class="gov-btn-serial" title="অনলাইন ডাক্তার সিরিয়াল বুক করুন">
        <i class="bi bi-calendar2-check-fill text-primary"></i> ডাক্তার সিরিয়াল
      </a>
      <a href="<?= APP_URL ?>/emergency.php" class="gov-btn-ice" title="জরুরি পাবলিক হেলথ কার্ড">
        <i class="bi bi-shield-exclamation text-danger"></i> ICE কার্ড
      </a>
      <a href="<?= APP_URL ?>/role-selection.php" class="gov-btn-login">
        <i class="bi bi-box-arrow-in-right"></i> নাগরিক লগইন
      </a>
      <a href="<?= APP_URL ?>/patient/register.php" class="gov-btn-register">
        <i class="bi bi-person-plus-fill"></i> নতুন রোগী নিবন্ধন
      </a>
      
      <!-- Mobile Toggle -->
      <button class="gov-hamburger" id="mc-mobile-btn" aria-label="Toggle menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
    </div>

  </div>

  <!-- Mobile Drawer -->
  <div class="gov-mobile-drawer" id="mc-mobile-drawer">
    <a href="#services" class="gov-nav-link">নাগরিক সেবাসমূহ (Services)</a>
    <a href="<?= APP_URL ?>/book-doctor.php" class="gov-nav-link">ডাক্তার ও হাসপাতাল (Doctors & Hospitals)</a>
    <a href="#portals" class="gov-nav-link">পোর্টালসমূহ (Access Portals)</a>
    <a href="<?= APP_URL ?>/for-patients.php" class="gov-nav-link">রোগীদের জন্য (For Patients)</a>
    <a href="<?= APP_URL ?>/for-doctors.php" class="gov-nav-link">ডাক্তারদের জন্য (For Doctors)</a>
    <a href="<?= APP_URL ?>/for-hospitals.php" class="gov-nav-link">হাসপাতালের জন্য (For Hospitals)</a>
    <a href="<?= APP_URL ?>/security.php" class="gov-nav-link">নিরাপত্তা ও প্রাইভেসি (Security)</a>
  </div>
</nav>

<!-- =====================================================
     3. HERO SECTION (NATIONAL HEALTHCARE GATEWAY)
     ===================================================== -->
<section class="gov-hero-section" id="hero">
  <div class="gov-hero-container">
    <div class="gov-hero-layout">
      
      <!-- Left Column -->
      <div class="gov-hero-content">
        <div class="gov-auth-pill">
          <i class="bi bi-patch-check-fill text-primary"></i> DGDA ও MOHFW স্ট্যান্ডার্ডস অনুমোদিত · AES-256 বিট সুরক্ষিত · ১০০% নাগরিক সম্মতি
        </div>

        <h1 class="gov-hero-title">
          একটি নাগরিক স্বাস্থ্য পরিচয়।<br>
          <span>সারা দেশের সব হাসপাতালে এক ডিজিটাল রেকর্ড।</span>
        </h1>

        <p class="gov-hero-subtitle">
          কাগজের প্রেসক্রিপশন হারানোর দিন শেষ। দেশের যেকোনো সরকারি বা বেসরকারি হাসপাতাল, চেম্বার ও ডায়াগনস্টিক ল্যাবে আপনার ডিজিটাল স্বাস্থ্য ইতিহাস এখন একটিমাত্র কেন্দ্রীয় ও সুরক্ষিত প্রোফাইলে সংরক্ষিত।
        </p>

        <!-- Main CTAs -->
        <div class="gov-hero-ctas">
          <a href="<?= APP_URL ?>/book-doctor.php" class="gov-cta-primary">
            <i class="bi bi-calendar2-check-fill"></i>
            অনলাইন ডাক্তার সিরিয়াল বুক করুন
          </a>
          <a href="<?= APP_URL ?>/patient/register.php" class="gov-cta-secondary">
            <i class="bi bi-person-plus-fill"></i> বিনামূল্যে নাগরিক অ্যাকাউন্ট খুলুন
          </a>
        </div>

        <!-- 4 Official Portals Access Grid -->
        <div style="font-size:12px; font-weight:800; color:#64748B; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:12px;">
          সরাসরি প্রাতিষ্ঠানিক ও নাগরিক লগইন
        </div>
        <div class="gov-portals-grid">
          <a href="<?= APP_URL ?>/patient/login.php" class="gov-quick-card">
            <div class="gov-quick-icon" style="background:#EFF6FF; color:#2563EB;">
              <i class="bi bi-person-heart"></i>
            </div>
            <div class="gov-quick-info">
              <h4>Citizen Portal</h4>
              <p>আমার স্বাস্থ্য রেকর্ড</p>
            </div>
          </a>

          <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="gov-quick-card">
            <div class="gov-quick-icon" style="background:#ECFDF5; color:#059669;">
              <i class="bi bi-stethoscope"></i>
            </div>
            <div class="gov-quick-info">
              <h4>Doctor Chamber</h4>
              <p>বিএমডিসি ডাক্তার কিউ</p>
            </div>
          </a>

          <a href="<?= APP_URL ?>/hospital/login.php" class="gov-quick-card">
            <div class="gov-quick-icon" style="background:#F5F3FF; color:#7C3AED;">
              <i class="bi bi-building-fill-cross"></i>
            </div>
            <div class="gov-quick-info">
              <h4>Hospital Desk</h4>
              <p>ওপিডি রিসেপশন ডেস্ক</p>
            </div>
          </a>

          <a href="<?= APP_URL ?>/lab/login.php" class="gov-quick-card">
            <div class="gov-quick-icon" style="background:#FFFBEB; color:#D97706;">
              <i class="bi bi-flask-fill"></i>
            </div>
            <div class="gov-quick-info">
              <h4>Diagnostic Lab</h4>
              <p>টোকেন সার্চ ও রেজাল্ট</p>
            </div>
          </a>
        </div>

      </div>

      <!-- Right Column: National Smart Health Card Preview -->
      <div class="gov-hero-preview">
        <div class="gov-id-card">
          
          <div class="gov-card-top-header">
            <div class="gov-card-title">
              <i class="bi bi-shield-check"></i> NATIONAL HEALTH RECORD · EHR
            </div>
            <span class="gov-card-verified">● ACTIVE CITIZEN</span>
          </div>

          <div class="gov-card-inner">
            
            <div class="gov-card-person">
              <div style="display:flex; align-items:center; gap:12px;">
                <div class="gov-avatar">R</div>
                <div>
                  <h3 style="margin:0; font-size:16px; font-weight:800; color:#0A2540;">Rahat Mahamud</h3>
                  <div style="font-size:12px; color:#64748B;">PT-000001 · ৩১ বছর · পুরুষ</div>
                </div>
              </div>
              <div style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA; padding:4px 10px; border-radius:8px; font-weight:900; font-size:13px;">
                🩸 O+
              </div>
            </div>

            <!-- Allergy Alert -->
            <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:10px; padding:10px 12px; display:flex; align-items:center; gap:8px; margin-bottom:14px; font-size:12px;">
              <span style="font-size:16px;">⚠️</span>
              <div>
                <strong style="color:#DC2626;">Severe Penicillin Allergy</strong>
                <div style="color:#64748B; font-size:11px;">Cross-reactive: Amoxicillin (Flagged in all doctor views)</div>
              </div>
            </div>

            <!-- Today's Serial Card -->
            <div style="background:linear-gradient(135deg, #0A2540 0%, #0284C7 100%); border-radius:12px; padding:14px; color:#FFFFFF; margin-bottom:14px;">
              <div style="display:flex; justify-content:space-between; font-size:10px; font-weight:800; letter-spacing:0.5px; opacity:0.9; margin-bottom:6px;">
                <span>TODAY'S APPOINTMENT SERIAL</span>
                <span style="background:rgba(255,255,255,0.2); padding:1px 6px; border-radius:4px;">CONFIRMED</span>
              </div>
              <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:#FFFFFF; color:#0A2540; font-family:'JetBrains Mono', monospace; font-size:26px; font-weight:900; padding:4px 10px; border-radius:8px; line-height:1;">
                  #02
                </div>
                <div>
                  <h4 style="margin:0 0 2px; font-size:14px; font-weight:800; color:#FFFFFF;">Dr. Farhana Rahman</h4>
                  <div style="font-size:11.5px; opacity:0.9;">Cardiology · ABC General Hospital</div>
                  <div style="font-size:10.5px; color:#BAE6FD; margin-top:2px;">Slot: 05:30 PM · Serial Token Ready</div>
                </div>
              </div>
            </div>

            <!-- Lab Token -->
            <div style="background:#F5F3FF; border:1px solid #E9D5FF; border-radius:10px; padding:10px 12px; display:flex; justify-content:space-between; align-items:center;">
              <div>
                <div style="font-size:10px; font-weight:800; color:#7C3AED; text-transform:uppercase;">Short Lab Token</div>
                <div style="font-family:'JetBrains Mono', monospace; font-size:15px; font-weight:800; color:#5B21B6; margin-top:1px;">LT-99B2A</div>
                <div style="font-size:10.5px; color:#6B7280;">Test: Complete Blood Count (CBC)</div>
              </div>
              <span style="background:#EDE9FE; color:#6D28D9; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:6px;">
                Show at Lab Desk
              </span>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     4. NATIONAL CITIZEN SERVICES (GOVERNMENT-GRADE)
     ===================================================== -->
<section class="gov-services-section" id="services">
  <div class="gov-hero-container">
    
    <div class="gov-section-header">
      <span class="gov-section-badge">National Framework Capabilities</span>
      <h2 class="gov-section-title">নাগরিক স্বাস্থ্য সেবাসমূহ</h2>
      <p class="gov-section-desc">নাগরিকদের জন্য নির্ভরযোগ্য, কাগজবিহীন ও দ্রুত ডিজিটাল স্বাস্থ্যসেবার পূর্ণাঙ্গ নেটওয়ার্ক।</p>
    </div>

    <div class="gov-services-grid">
      
      <!-- Feature 1 -->
      <div class="gov-feature-card">
        <div>
          <div class="gov-feature-icon" style="background:#EFF6FF; color:#0284C7;">
            <i class="bi bi-calendar2-check-fill"></i>
          </div>
          <h3>সমন্বিত ডাক্তার সিরিয়াল ও কিউ ট্র্যাকিং</h3>
          <p>হাসপাতাল ও বিশেষত্ব অনুযায়ী বিএমডিসি রেজিস্টার্ড ডাক্তার বাছাই, রেটিং ও ফি পর্যালোচনা এবং চেম্বার সিরিয়াল টোকেন গ্রহণ।</p>
        </div>
        <a href="<?= APP_URL ?>/book-doctor.php" class="gov-feature-link" style="color:#0284C7;">
          সিরিয়াল গ্রহণ করুন &rarr;
        </a>
      </div>

      <!-- Feature 2 -->
      <div class="gov-feature-card">
        <div>
          <div class="gov-feature-icon" style="background:#ECFDF5; color:#059669;">
            <i class="bi bi-clipboard2-pulse-fill"></i>
          </div>
          <h3>আজীবন ডিজিটাল রেকর্ড (Unified EHR)</h3>
          <p>কাগজের প্রেসক্রিপশন হারানোর ভয় নেই। অতীতের প্রেসক্রিপশন, ডায়াগনস্টিক রিপোর্ট ও ওষুধ হিস্ট্রি একটিমাত্র সুরক্ষিত প্রোফাইলে সংরক্ষিত।</p>
        </div>
        <a href="<?= APP_URL ?>/for-patients.php" class="gov-feature-link" style="color:#059669;">
          নাগরিক গাইড দেখুন &rarr;
        </a>
      </div>

      <!-- Feature 3 -->
      <div class="gov-feature-card">
        <div>
          <div class="gov-feature-icon" style="background:#F5F3FF; color:#7C3AED;">
            <i class="bi bi-qr-code-scan"></i>
          </div>
          <h3>৮-সংখ্যার ইউনিক ডায়াগনস্টিক টোকেন</h3>
          <p>প্রেসক্রিপশনের সাথে তৈরি হওয়া ইউনিক শর্ট টোকেন (LT-XXXXX) যেকোনো অনুমোদিত ল্যাবে দেখালেই সরাসরি রিপোর্ট সিঙ্ক হয়ে যাবে।</p>
        </div>
        <a href="<?= APP_URL ?>/for-hospitals.php" class="gov-feature-link" style="color:#7C3AED;">
          ল্যাব নেটওয়ার্ক দেখুন &rarr;
        </a>
      </div>

      <!-- Feature 4 -->
      <div class="gov-feature-card">
        <div>
          <div class="gov-feature-icon" style="background:#FEF2F2; color:#DC2626;">
            <i class="bi bi-shield-lock-fill"></i>
          </div>
          <h3>নাগরিক-নিয়ন্ত্রিত গোপনীয়তা (OTP)</h3>
          <p>নাগরিকের নিজস্ব ওটিপি (OTP) বা সম্মতি ছাড়া কোনো ডাক্তার রেকর্ড দেখতে পারেন না। প্রতিটি সেশন ৩০ মিনিট পর স্বয়ংক্রিয়ভাবে লক হয়ে যায়।</p>
        </div>
        <a href="<?= APP_URL ?>/security.php" class="gov-feature-link" style="color:#DC2626;">
          নিরাপত্তা স্ট্যান্ডার্ড &rarr;
        </a>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     5. INSTITUTIONAL PORTALS SECTION
     ===================================================== -->
<section class="gov-portals-section" id="portals">
  <div class="gov-hero-container">
    
    <div class="gov-section-header">
      <span class="gov-section-badge">Dedicated Clinical Portals</span>
      <h2 class="gov-section-title">আপনার নির্ধারিত পোর্টাল বেছে নিন</h2>
      <p class="gov-section-desc">প্রতিটি পেশাদার ভূমিকার জন্য আলাদা ডেডিকেটেড সুরক্ষিত ক্লিনিক্যাল ড্যাশবোর্ড।</p>
    </div>

    <div class="gov-portals-grid-cards">
      
      <!-- Patient Portal -->
      <div class="gov-portal-box">
        <div>
          <div class="gov-feature-icon" style="background:#EFF6FF; color:#2563EB;">
            <i class="bi bi-person-heart"></i>
          </div>
          <h3>Citizen Portal</h3>
          <p>আমার স্বাস্থ্য রেকর্ড, আমার নিয়ন্ত্রণে।</p>
          <ul class="gov-portal-features">
            <li><i class="bi bi-check2 text-primary"></i> আজীবন প্রেসক্রিপশন ও রিপোর্ট হিস্ট্রি</li>
            <li><i class="bi bi-check2 text-primary"></i> লাইভ ডাক্তার সিরিয়াল ট্র্যাকিং</li>
            <li><i class="bi bi-check2 text-primary"></i> ডাক্তারের অ্যাক্সেস অনুমোদন বা বাতিল</li>
            <li><i class="bi bi-check2 text-primary"></i> ল্যাব টোকেন ও ওষুধ রিমাইন্ডার</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/patient/login.php" class="gov-portal-btn" style="background:#2563EB; color:#FFFFFF;">
          <i class="bi bi-box-arrow-in-right"></i> Citizen Login
        </a>
      </div>

      <!-- Doctor Chamber -->
      <div class="gov-portal-box" style="border-top:3px solid #059669;">
        <div>
          <div class="gov-feature-icon" style="background:#ECFDF5; color:#059669;">
            <i class="bi bi-stethoscope"></i>
          </div>
          <h3>Doctor Chamber</h3>
          <p>বিএমডিসি অনুমোদিত কনসালটেশন ওয়ার্কস্পেস।</p>
          <ul class="gov-portal-features">
            <li><i class="bi bi-check2 text-success"></i> মাল্টি-হাসপাতাল চেম্বার ম্যানেজমেন্ট</li>
            <li><i class="bi bi-check2 text-success"></i> লাইভ পেশেন্ট কিউ ও সিরিয়াল রস্টার</li>
            <li><i class="bi bi-check2 text-success"></i> এক্সিকিউটিভ পেশেন্ট মেডিক্যাল হিস্ট্রি</li>
            <li><i class="bi bi-check2 text-success"></i> বাংলা ডোজ প্রিসেট সহ ই-প্রেসক্রিপশন</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/doctor/select-hospital.php" class="gov-portal-btn" style="background:#059669; color:#FFFFFF;">
          <i class="bi bi-box-arrow-in-right"></i> Doctor Login
        </a>
      </div>

      <!-- Hospital Portal -->
      <div class="gov-portal-box">
        <div>
          <div class="gov-feature-icon" style="background:#F5F3FF; color:#7C3AED;">
            <i class="bi bi-building-fill-cross"></i>
          </div>
          <h3>Hospital Desk</h3>
          <p>সম্পূর্ণ প্রাতিষ্ঠানিক ও ওপিডি প্রশাসন।</p>
          <ul class="gov-portal-features">
            <li><i class="bi bi-check2 text-purple" style="color:#7C3AED;"></i> ওপিডি অভ্যর্থনা ও লবি চেক-ইন</li>
            <li><i class="bi bi-check2 text-purple" style="color:#7C3AED;"></i> অ্যাফিলিয়েটেড ডাক্তার ও ডিপার্টমেন্ট রস্টার</li>
            <li><i class="bi bi-check2 text-purple" style="color:#7C3AED;"></i> ডায়াগনস্টিক ল্যাবরেটরি নেটওয়ার্ক</li>
            <li><i class="bi bi-check2 text-purple" style="color:#7C3AED;"></i> দৈনিক ওপিডি রেজিস্টার ও অডিট</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/hospital/login.php" class="gov-portal-btn" style="background:#FFFFFF; color:#0A2540; border:1.5px solid #CBD5E1;">
          <i class="bi bi-box-arrow-in-right"></i> Hospital Login
        </a>
      </div>

      <!-- Diagnostic Lab Portal -->
      <div class="gov-portal-box">
        <div>
          <div class="gov-feature-icon" style="background:#FFFBEB; color:#D97706;">
            <i class="bi bi-flask-fill"></i>
          </div>
          <h3>Diagnostic Lab</h3>
          <p>দ্রুত টোকেন সার্চ ও সরাসরি রেজাল্ট আপলোড।</p>
          <ul class="gov-portal-features">
            <li><i class="bi bi-check2 text-warning" style="color:#D97706;"></i> ৮ ডিজিটের শর্ট ল্যাব টোকেন সার্চ</li>
            <li><i class="bi bi-check2 text-warning" style="color:#D97706;"></i> ডাক্তারের নির্দেশিত টেস্ট ভেরিফাই</li>
            <li><i class="bi bi-check2 text-warning" style="color:#D97706;"></i> টেস্ট ফাইন্ডিংস এন্ট্রি ও রিপোর্ট আপলোড</li>
            <li><i class="bi bi-check2 text-warning" style="color:#D97706;"></i> রোগীর অ্যাকাউন্টে তাৎক্ষণিক সিঙ্ক</li>
          </ul>
        </div>
        <a href="<?= APP_URL ?>/lab/login.php" class="gov-portal-btn" style="background:#D97706; color:#FFFFFF;">
          <i class="bi bi-box-arrow-in-right"></i> Lab Login
        </a>
      </div>

    </div>
  </div>
</section>

<!-- =====================================================
     6. EMERGENCY ICE FIRST-RESPONDER SECTION
     ===================================================== -->
<section class="gov-ice-section" id="emergency-ice">
  <div class="gov-hero-container">
    <div class="gov-ice-banner">
      
      <!-- Left Content -->
      <div class="gov-ice-content">
        <span style="background:#DC2626; color:#fff; font-size:11px; font-weight:800; padding:4px 12px; border-radius:999px; text-transform:uppercase; letter-spacing:0.6px; display:inline-flex; align-items:center; gap:5px;">
          <i class="bi bi-shield-exclamation"></i> First Responder ICE Protocol
        </span>
        <h3>রোড এক্সিডেন্ট বা জরুরি পরিস্থিতিতে ১-ক্লিকে পরিচয়</h3>
        <p>
          লগইন বা পাসওয়ার্ড ছাড়াই দুর্ঘটনা স্থানে উপস্থিত উদ্ধারকারী প্যারামেডিক ও চিকিৎসকরা রোগীর রক্তের গ্রুপ, জীবনঘাতী ওষুধের অ্যালার্জি এবং স্বজনের জরুরি মোবাইল নম্বর দেখতে পারবেন।
        </p>
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
          <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001" target="_blank" class="gov-ice-btn">
            <i class="bi bi-heart-pulse-fill"></i> ওপেন লাইভ ICE কার্ড ডেমো
          </a>
          <a href="<?= APP_URL ?>/emergency.php" class="btn btn-secondary" style="border-radius:10px; font-weight:700; background:#FFFFFF; color:#991B1B; border:1px solid #FECACA; padding:13px 22px;">
            <i class="bi bi-search"></i> আইডি দিয়ে কার্ড খুঁজুন
          </a>
        </div>
      </div>

      <!-- Right: Mini ICE Card Preview -->
      <div>
        <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001" target="_blank" class="gov-ice-mini-card">
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
              <div style="font-size:12px; color:#64748B;">PT-000001 · ৩১ বছর · পুরুষ</div>
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
     7. GOVERNMENT STANDARD FOOTER
     ===================================================== -->
<footer class="gov-footer">
  <div class="gov-hero-container">
    
    <div class="gov-footer-grid">
      
      <!-- Brand Col -->
      <div>
        <div style="display:flex; align-items:center; gap:8px; font-weight:900; font-size:19px; color:#0A2540; margin-bottom:12px;">
          <div style="width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg, #0A2540, #0284C7); color:#fff; display:flex; align-items:center; justify-content:center; font-size:15px;">M</div>
          MedCore
        </div>
        <p style="font-size:13px; line-height:1.7; color:#475569; margin:0 0 14px;">
          গণপ্রজাতন্ত্রী বাংলাদেশ সরকারের ডিজিটাল স্বাস্থ্য ফ্রেমওয়ার্ক অনুমোদিত কেন্দ্রীয় ডিজিটাল নেটওয়ার্ক।
        </p>
        <div style="font-size:12px; color:#64748B;">
          Universal Demo OTP: <code style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-weight:700; color:#0A2540;">1234</code>
        </div>
      </div>

      <!-- Quick Portals -->
      <div>
        <h4 class="gov-footer-heading">Portals</h4>
        <ul class="gov-footer-list">
          <li><a href="<?= APP_URL ?>/patient/login.php">নাগরিক পোর্টাল (Citizen Portal)</a></li>
          <li><a href="<?= APP_URL ?>/doctor/select-hospital.php">ডাক্তার চেম্বার (Doctor Chamber)</a></li>
          <li><a href="<?= APP_URL ?>/hospital/login.php">হাসপাতাল প্রশাসন (Hospital Desk)</a></li>
          <li><a href="<?= APP_URL ?>/lab/login.php">ডায়াগনস্টিক ল্যাব (Diagnostic Lab)</a></li>
        </ul>
      </div>

      <!-- Services -->
      <div>
        <h4 class="gov-footer-heading">Services</h4>
        <ul class="gov-footer-list">
          <li><a href="<?= APP_URL ?>/book-doctor.php" style="color:#0284C7; font-weight:700;">অনলাইন ডাক্তার সিরিয়াল বুকিং</a></li>
          <li><a href="<?= APP_URL ?>/emergency.php" style="color:#DC2626; font-weight:700;">Emergency ICE Profile</a></li>
          <li><a href="<?= APP_URL ?>/security.php">Security & Consent</a></li>
          <li><a href="<?= APP_URL ?>/for-patients.php">For Patients</a></li>
        </ul>
      </div>

      <!-- Compliance & National Alignment -->
      <div>
        <h4 class="gov-footer-heading">National Standards</h4>
        <div style="font-size:13px; color:#475569; line-height:1.95;">
          <div><i class="bi bi-shield-check text-primary"></i> DGDA Standards Aligned</div>
          <div><i class="bi bi-lock-fill text-primary"></i> AES-256 Bit Govt Encryption</div>
          <div><i class="bi bi-clock-history text-primary"></i> 30-Min Temporary Consent</div>
          <div><i class="bi bi-check-circle-fill text-success"></i> BMDC Licensure Validated</div>
        </div>
      </div>

    </div>

    <!-- Bottom Copyright -->
    <div class="gov-footer-bottom">
      <div>&copy; <?= date('Y') ?> MedCore Healthcare Network. গণপ্রজাতন্ত্রী বাংলাদেশ সরকারের ডিজিটাল রূপকল্পের আওতায় পরিচালিত।</div>
      <div style="display:flex; gap:16px;">
        <a href="<?= APP_URL ?>/security.php" style="color:#64748B; text-decoration:none;">Privacy Policy</a>
        <a href="<?= APP_URL ?>/security.php" style="color:#64748B; text-decoration:none;">Terms of Service</a>
        <a href="<?= APP_URL ?>/security.php" style="color:#64748B; text-decoration:none;">Security Whitepaper</a>
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
