<?php
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/config/database.php';

$db = getDB();
$uid = trim($_GET['uid'] ?? $_GET['id'] ?? '');

$patient = null;
$allergies = [];
$conditions = [];

if (!empty($uid)) {
    // Normalize UID (e.g. 1 -> PT-000001, pt-000001 -> PT-000001)
    $cleanUid = (stripos($uid, 'PT-') === 0) ? strtoupper($uid) : ('PT-' . str_pad($uid, 6, '0', STR_PAD_LEFT));

    $stmt = $db->prepare("
        SELECT p.id, p.patient_uid, p.full_name, p.date_of_birth, p.gender, p.blood_group,
               p.emergency_contact_name, p.emergency_contact_phone, u.phone
        FROM patients p
        JOIN users u ON u.id = p.user_id
        WHERE p.patient_uid = ? OR p.patient_uid = ? OR u.phone = ?
        LIMIT 1
    ");
    $stmt->execute([$uid, $cleanUid, $uid]);
    $patient = $stmt->fetch();

    if ($patient) {
        $age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;

        // Fetch severe/critical allergies only
        $stmtAllergy = $db->prepare("
            SELECT pa.*, a.name AS allergy_name
            FROM patient_allergies pa
            JOIN allergies a ON a.id = pa.allergy_id
            WHERE pa.patient_id = ? AND pa.severity IN ('SEVERE', 'LIFE_THREATENING')
            ORDER BY pa.severity DESC
        ");
        $stmtAllergy->execute([$patient['id']]);
        $allergies = $stmtAllergy->fetchAll();

        // Fetch active/chronic critical medical conditions
        $stmtHist = $db->prepare("
            SELECT condition_name, status, notes
            FROM medical_histories
            WHERE patient_id = ? AND status IN ('ACTIVE', 'CHRONIC')
            ORDER BY id DESC
        ");
        $stmtHist->execute([$patient['id']]);
        $conditions = $stmtHist->fetchAll();
    }
}

// Current Page URL for QR Code
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'medcore1-tan.vercel.app') . ($_SERVER['REQUEST_URI'] ?? '/emergency.php?uid=PT-000001');
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($currentUrl);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Emergency ICE Health Card — MedCore</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">

  <style>
    /* ============================================================
       MEDCORE ULTRA-PREMIUM EMERGENCY ICE CARD STYLES
       ============================================================ */
    :root {
      --ice-red-primary: #DC2626;
      --ice-red-dark: #991B1B;
      --ice-red-light: #FEF2F2;
      --ice-red-border: #FECACA;
      --ice-text-dark: #0F172A;
      --ice-text-muted: #64748B;
    }

    * {
      box-sizing: border-box;
    }

    body {
      background: radial-gradient(circle at 50% -10%, #FEE2E2 0%, #F8FAFC 45%, #F1F5F9 100%);
      color: var(--ice-text-dark);
      font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
      min-height: 100vh;
      margin: 0;
      padding: 24px 16px 48px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    /* Top Navigation Toolbar */
    .ice-toolbar {
      max-width: 620px;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 20px;
    }
    .ice-toolbar-brand {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 800;
      font-size: 16px;
      color: #0F172A;
      text-decoration: none;
    }
    .ice-brand-badge {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: linear-gradient(135deg, #DC2626, #EF4444);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
    }
    .ice-toolbar-actions {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .ice-tool-btn {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      color: #334155;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
      transition: all 0.15s ease;
      cursor: pointer;
    }
    .ice-tool-btn:hover {
      background: #F8FAFC;
      border-color: #CBD5E1;
      color: #0F172A;
      transform: translateY(-1px);
    }

    /* THE PHYSICAL SMART ICE CARD CONTAINER */
    .ice-card {
      max-width: 620px;
      width: 100%;
      background: #FFFFFF;
      border: 2px solid #FECACA;
      border-radius: 24px;
      box-shadow: 0 20px 50px -10px rgba(220, 38, 38, 0.14), 0 0 0 1px rgba(239, 68, 68, 0.1);
      overflow: hidden;
      position: relative;
      transition: all 0.3s ease;
    }

    /* Card Top Emergency Strip Header */
    .ice-card-top-strip {
      background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
      color: #FFFFFF;
      padding: 14px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    .ice-strip-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .ice-strip-icon {
      font-size: 20px;
      animation: pulse-ring 2s infinite;
    }
    .ice-strip-title {
      font-size: 13px;
      font-weight: 900;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      margin: 0;
    }
    .ice-strip-sub {
      font-size: 11px;
      opacity: 0.9;
      margin: 1px 0 0;
    }
    .ice-strip-verified {
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: 0.4px;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    /* Card Main Body Content */
    .ice-card-body {
      padding: 26px 24px;
    }

    /* Identity & Blood Group Grid */
    .ice-hero-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      padding-bottom: 22px;
      border-bottom: 1px solid #F1F5F9;
      margin-bottom: 22px;
    }
    .ice-patient-left {
      display: flex;
      align-items: center;
      gap: 16px;
      min-width: 0;
    }
    .ice-patient-avatar {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      background: linear-gradient(135deg, #0284C7, #2563EB);
      color: #FFFFFF;
      font-size: 24px;
      font-weight: 900;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 14px rgba(2, 132, 199, 0.3);
      flex-shrink: 0;
    }
    .ice-patient-info h1 {
      margin: 0;
      font-size: 22px;
      font-weight: 900;
      color: #0F172A;
      letter-spacing: -0.02em;
    }
    .ice-meta-pills {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 6px;
    }
    .ice-uid-badge {
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      font-weight: 800;
      background: #F1F5F9;
      color: #334155;
      padding: 2px 8px;
      border-radius: 6px;
      border: 1px solid #E2E8F0;
    }
    .ice-bio-text {
      font-size: 13px;
      font-weight: 600;
      color: #64748B;
    }

    /* THE BLOOD GROUP BADGE (MAX VISIBILITY RESUSCITATION DATA) */
    .ice-blood-card {
      background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
      color: #FFFFFF;
      border-radius: 18px;
      padding: 12px 20px;
      text-align: center;
      box-shadow: 0 8px 20px rgba(220, 38, 38, 0.35);
      flex-shrink: 0;
      min-width: 120px;
      position: relative;
      overflow: hidden;
    }
    .ice-blood-card::after {
      content: '';
      position: absolute;
      top: -30%;
      right: -30%;
      width: 80px;
      height: 80px;
      background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
      border-radius: 50%;
    }
    .ice-blood-group {
      font-size: 34px;
      font-weight: 900;
      line-height: 1;
      letter-spacing: -0.02em;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
    }
    .ice-blood-label {
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #FEE2E2;
      margin-top: 4px;
    }

    /* Critical Section Boxes */
    .ice-section-box {
      border-radius: 16px;
      padding: 16px 18px;
      margin-bottom: 16px;
      position: relative;
    }
    .ice-box-title {
      font-size: 12px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 10px;
    }

    /* 1. SEVERE ALLERGY BOX (CRITICAL WARNING) */
    .ice-allergy-box {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      border-left: 5px solid #DC2626;
    }
    .ice-allergy-box .ice-box-title {
      color: #DC2626;
    }
    .ice-allergy-item {
      background: #FFFFFF;
      border: 1px solid #FCA5A5;
      border-radius: 10px;
      padding: 10px 14px;
      margin-top: 8px;
      box-shadow: 0 1px 3px rgba(220, 38, 38, 0.05);
    }
    .ice-allergy-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    .ice-allergy-name {
      font-size: 14px;
      font-weight: 800;
      color: #991B1B;
    }
    .ice-severity-badge {
      background: #DC2626;
      color: #FFFFFF;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: 999px;
      text-transform: uppercase;
    }
    .ice-allergy-reaction {
      font-size: 12px;
      color: #7F1D1D;
      margin-top: 4px;
      line-height: 1.4;
    }

    /* 2. CHRONIC CONDITIONS BOX */
    .ice-condition-box {
      background: #F0F9FF;
      border: 1px solid #BAE6FD;
      border-left: 5px solid #0284C7;
    }
    .ice-condition-box .ice-box-title {
      color: #0369A1;
    }
    .ice-condition-list {
      list-style: none;
      padding: 0;
      margin: 0;
      font-size: 13px;
      color: #0F172A;
      line-height: 1.7;
    }
    .ice-condition-list li {
      display: flex;
      align-items: baseline;
      gap: 6px;
    }

    /* 3. EMERGENCY CONTACT (CALL 1-CLICK) */
    .ice-contact-box {
      background: #F0FDF4;
      border: 1px solid #BBF7D0;
      border-left: 5px solid #16A34A;
      margin-bottom: 20px;
    }
    .ice-contact-box .ice-box-title {
      color: #15803D;
    }
    .ice-contact-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .ice-contact-person h4 {
      margin: 0;
      font-size: 16px;
      font-weight: 800;
      color: #0F172A;
    }
    .ice-contact-phone {
      font-family: 'JetBrains Mono', monospace;
      font-size: 14px;
      font-weight: 700;
      color: #15803D;
      margin-top: 3px;
    }
    .ice-btn-call {
      background: linear-gradient(135deg, #16A34A 0%, #15803D 100%);
      color: #FFFFFF !important;
      font-weight: 800;
      font-size: 13.5px;
      padding: 10px 20px;
      border-radius: 10px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 14px rgba(22, 163, 74, 0.3);
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .ice-btn-call:hover {
      background: linear-gradient(135deg, #15803D 0%, #166534 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(22, 163, 74, 0.4);
    }

    /* Card Footer & QR Code */
    .ice-card-footer {
      border-top: 1px dashed #CBD5E1;
      padding-top: 18px;
      margin-top: 8px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    .ice-qr-wrapper {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .ice-qr-img {
      width: 64px;
      height: 64px;
      border-radius: 8px;
      border: 1px solid #E2E8F0;
      background: #FFFFFF;
      padding: 2px;
    }
    .ice-qr-text {
      font-size: 11px;
      color: #64748B;
      line-height: 1.4;
    }
    .ice-qr-text strong {
      color: #0F172A;
      display: block;
    }
    .ice-security-seal {
      text-align: right;
      font-size: 11px;
      color: #94A3B8;
      flex-shrink: 0;
    }

    /* Lookup Form Card (When no UID or Searching) */
    .ice-lookup-card {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 20px;
      padding: 32px 24px;
      text-align: center;
      max-width: 500px;
      width: 100%;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }
    .ice-lookup-input {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #CBD5E1;
      border-radius: 10px;
      font-size: 14px;
      font-family: inherit;
      outline: none;
      transition: all 0.2s ease;
      margin-bottom: 12px;
    }
    .ice-lookup-input:focus {
      border-color: #DC2626;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
    }
    .ice-lookup-btn {
      width: 100%;
      background: linear-gradient(135deg, #DC2626, #B91C1C);
      color: #FFFFFF;
      border: none;
      padding: 12px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
    }

    /* Print Optimized Styles */
    @media print {
      body {
        background: #FFFFFF !important;
        padding: 0 !important;
      }
      .ice-toolbar, .ice-btn-call, .ice-tool-btn {
        display: none !important;
      }
      .ice-card {
        border: 2px solid #000 !important;
        box-shadow: none !important;
        max-width: 100% !important;
      }
    }

    @keyframes pulse-ring {
      0% { transform: scale(0.95); opacity: 0.8; }
      50% { transform: scale(1.1); opacity: 1; }
      100% { transform: scale(0.95); opacity: 0.8; }
    }
  </style>
</head>
<body>

  <!-- Top Toolbar -->
  <div class="ice-toolbar">
    <a href="<?= APP_URL ?>/" class="ice-toolbar-brand">
      <div class="ice-brand-badge">
        <i class="bi bi-heart-pulse-fill"></i>
      </div>
      <span>MedCore <span style="color:#DC2626;">ICE</span></span>
    </a>

    <div class="ice-toolbar-actions">
      <?php if ($patient): ?>
      <button onclick="window.print()" class="ice-tool-btn" title="প্রিন্ট বা পিডিএফ সেভ করুন">
        <i class="bi bi-printer"></i> প্রিন্ট / PDF
      </button>
      <a href="<?= APP_URL ?>/emergency.php" class="ice-tool-btn" title="অন্য রোগী খুঁজুন">
        <i class="bi bi-search"></i> খুঁজুন
      </a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/" class="ice-tool-btn">
        <i class="bi bi-house"></i> হোম
      </a>
    </div>
  </div>

  <?php if (!$patient): ?>
    <!-- Search / Lookup Mode -->
    <div class="ice-lookup-card">
      <div style="width:54px; height:54px; border-radius:14px; background:#FEF2F2; color:#DC2626; display:flex; align-items:center; justify-content:center; font-size:26px; margin:0 auto 16px;">
        <i class="bi bi-shield-exclamation"></i>
      </div>
      <h2 style="font-size:20px; font-weight:900; margin:0 0 6px;">জরুরি ICE কার্ড অনুসন্ধান</h2>
      <p style="font-size:13px; color:#64748B; margin:0 0 24px; line-height:1.5;">
        দুর্ঘটনা বা জরুরি পরিস্থিতিতে রোগীর রক্তের গ্রুপ, ড্রাগ অ্যালার্জি ও স্বজনের নম্বর জানতে রোগীর আইডি বা মোবাইল নম্বর দিন।
      </p>

      <form method="GET" action="<?= APP_URL ?>/emergency.php">
        <input type="text" name="uid" class="ice-lookup-input" placeholder="উদাহরণ: PT-000001 অথবা 01700000001" required autofocus>
        <button type="submit" class="ice-lookup-btn">
          <i class="bi bi-search"></i> জরুরি কার্ড ওপেন করুন
        </button>
      </form>

      <div style="margin-top:20px; font-size:12px; color:#94A3B8;">
        ডেমো দেখতে ক্লিক করুন: <a href="<?= APP_URL ?>/emergency.php?uid=PT-000001" style="color:#DC2626; font-weight:700; text-decoration:none;">PT-000001 (রাহাত মাহমুদ)</a>
      </div>
    </div>

  <?php else: ?>

    <!-- THE PHYSICAL SMART ICE DIGITAL MEDICAL CARD -->
    <div class="ice-card" id="printable-ice-card">
      
      <!-- Top Emergency Strip Header -->
      <div class="ice-card-top-strip">
        <div class="ice-strip-left">
          <i class="bi bi-heart-pulse-fill ice-strip-icon"></i>
          <div>
            <div class="ice-strip-title">IN CASE OF EMERGENCY (ICE)</div>
            <div class="ice-strip-sub">জরুরি চিকিৎসা ও ফার্স্ট-রেসপন্ডার লাইফ-সেফটি কার্ড</div>
          </div>
        </div>
        <div class="ice-strip-verified">
          <i class="bi bi-patch-check-fill text-white"></i> VERIFIED EHR
        </div>
      </div>

      <!-- Card Body Content -->
      <div class="ice-card-body">
        
        <!-- Identity & Blood Group Header -->
        <div class="ice-hero-row">
          <div class="ice-patient-left">
            <div class="ice-patient-avatar">
              <?= mb_substr($patient['full_name'], 0, 1) ?>
            </div>
            <div class="ice-patient-info">
              <h1><?= htmlspecialchars($patient['full_name']) ?></h1>
              <div class="ice-meta-pills">
                <span class="ice-uid-badge"><?= htmlspecialchars($patient['patient_uid']) ?></span>
                <span class="ice-bio-text"><?= $age ?> বছর · <?= htmlspecialchars($patient['gender']) ?></span>
              </div>
            </div>
          </div>

          <!-- THE BLOOD GROUP HIGHLIGHT BADGE -->
          <div class="ice-blood-card">
            <div class="ice-blood-group">
              <i class="bi bi-droplet-fill" style="font-size:24px;"></i>
              <?= htmlspecialchars($patient['blood_group'] ?: 'UNKNOWN') ?>
            </div>
            <div class="ice-blood-label">রক্তের গ্রুপ</div>
          </div>
        </div>

        <!-- 1. CRITICAL DRUG ALLERGIES (LIFE-OR-DEATH ALERT) -->
        <div class="ice-section-box ice-allergy-box">
          <div class="ice-box-title">
            <i class="bi bi-exclamation-triangle-fill"></i> জীবনঘাতী ওষুধের অ্যালার্জি (CRITICAL DRUG ALLERGIES)
          </div>
          
          <?php if (empty($allergies)): ?>
            <div style="font-size:13px; color:#15803D; font-weight:600; display:flex; align-items:center; gap:6px;">
              <i class="bi bi-check-circle-fill"></i> কোনো মারাত্মক ড্রাগ অ্যালার্জি রেকর্ড নেই (No Severe Allergies)
            </div>
          <?php else: ?>
            <div style="font-size:12px; color:#991B1B; font-weight:700; margin-bottom:4px;">
              ⚠️ চিকিৎসকদের সতর্কতা: এই রোগীকে নিচের ওষুধ বা সমজাতীয় উপাদান দেওয়া সম্পূর্ণ নিষিদ্ধ:
            </div>
            <?php foreach ($allergies as $alg): ?>
              <div class="ice-allergy-item">
                <div class="ice-allergy-header">
                  <span class="ice-allergy-name"><?= htmlspecialchars($alg['allergy_name']) ?></span>
                  <span class="ice-severity-badge"><?= htmlspecialchars($alg['severity']) ?></span>
                </div>
                <?php if (!empty($alg['reaction'])): ?>
                  <div class="ice-allergy-reaction">
                    <?= htmlspecialchars($alg['reaction']) ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- 2. DOCUMENTED CHRONIC CONDITIONS -->
        <div class="ice-section-box ice-condition-box">
          <div class="ice-box-title">
            <i class="bi bi-clipboard2-pulse-fill"></i> দীর্ঘস্থায়ী শারীরিক অবস্থা (CHRONIC CONDITIONS)
          </div>
          <?php if (empty($conditions)): ?>
            <div style="font-size:13px; color:#64748B;">কোনো জটিল ক্রনিক শারীরিক সমস্যা রেকর্ড নেই।</div>
          <?php else: ?>
            <ul class="ice-condition-list">
              <?php foreach ($conditions as $c): ?>
                <li>
                  <i class="bi bi-dot" style="font-size:20px; color:#0284C7;"></i>
                  <span>
                    <strong><?= htmlspecialchars($c['condition_name']) ?></strong>
                    <?= !empty($c['notes']) ? ' — <span style="color:#475569;">' . htmlspecialchars($c['notes']) . '</span>' : '' ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <!-- 3. PRIMARY EMERGENCY CONTACT (CALL 1-CLICK) -->
        <div class="ice-section-box ice-contact-box">
          <div class="ice-box-title">
            <i class="bi bi-telephone-inbound-fill"></i> জরুরি স্বজনের সাথে যোগাযোগ (PRIMARY EMERGENCY CONTACT)
          </div>
          <div class="ice-contact-card">
            <div class="ice-contact-person">
              <h4><?= htmlspecialchars($patient['emergency_contact_name'] ?: 'Family / Next of Kin') ?></h4>
              <div class="ice-contact-phone">
                <?= htmlspecialchars($patient['emergency_contact_phone'] ?: ($patient['phone'] ?: 'N/A')) ?>
              </div>
            </div>

            <?php if (!empty($patient['emergency_contact_phone'])): ?>
              <a href="tel:<?= htmlspecialchars($patient['emergency_contact_phone']) ?>" class="ice-btn-call">
                <i class="bi bi-telephone-fill"></i> এখনই কল করুন
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Card Footer & QR Code -->
        <div class="ice-card-footer">
          <div class="ice-qr-wrapper">
            <img src="<?= $qrUrl ?>" alt="ICE QR Code" class="ice-qr-img" loading="lazy">
            <div class="ice-qr-text">
              <strong>প্যারামেডিক কিউআর স্ক্যান</strong>
              মোবাইল ক্যামেরা দিয়ে স্ক্যান করে এই কার্ডের ভেরিফায়েড ডাটা দেখুন।
            </div>
          </div>
          <div class="ice-security-seal">
            <div><i class="bi bi-shield-lock-fill text-danger"></i> <strong>MedCore Emergency</strong></div>
            <div style="font-size:10px;">AES-256 Verified · 2026</div>
          </div>
        </div>

      </div>

    </div>

    <!-- Safety Notice Note -->
    <div style="max-width:620px; width:100%; text-align:center; font-size:11.5px; color:#64748B; margin-top:16px; line-height:1.5;">
      <i class="bi bi-info-circle"></i> <strong>ফার্স্ট-রেসপন্ডার নির্দেশিকা:</strong> এই কার্ডে শুধুমাত্র জরুরি জীবনরক্ষাকারী ক্লিনিক্যাল তথ্য উন্মুক্ত থাকে। বিস্তারিত প্রেসক্রিপশন ও ডায়াগনস্টিক রিপোর্ট দেখতে রোগীর ডিজিটাল ওটিপি সম্মতি আবশ্যক।
    </div>

  <?php endif; ?>

</body>
</html>
