<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/media.php';
initSession();

$pageTitle = 'Security & Clinical Privacy Architecture — MedCore';
$pageDescription = 'Zero-knowledge consent architecture, 30-min time-bounded EMR sessions, and immutable DGHS audit logging.';
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';
?>

<!-- 1. SECURITY HERO -->
<section class="mc-hero-intl">
  <div class="mc-wrap">
    <div class="mc-hero-content" style="max-width:820px">
      <div class="mc-hero-badge">
        <span class="status-dot"></span>
        <span>ZERO-KNOWLEDGE CONSENT & CRYPTOGRAPHIC TRUST ARCHITECTURE</span>
      </div>
      <h1 class="mc-hero-title">
        Engineered for<br>
        <span>Absolute Clinical Privacy.</span>
      </h1>
      <p class="mc-hero-sub">
        চিকিৎসাসেবার ক্ষেত্রে নিরাপত্তা কোনো সাধারণ বাধ্যবাধকতা নয় — এটি রোগীর জীবনরক্ষার পূর্বশর্ত। মেডকোর প্ল্যাটফর্ম মাল্টি-লেয়ার ক্রিপ্টোগ্রাফিক সুরক্ষা, ৩০ মিনিটের টাইম-লকড সেশন এবং অপরিবর্তনীয় অডিট ট্রেইলে পরিচালিত।
      </p>

      <div class="mc-hero-actions-intl">
        <a class="mc-btn mc-btn-primary mc-btn-lg" href="<?= APP_URL ?>/role-selection.php">
          <i class="bi bi-shield-lock-fill"></i> Access Secure Portals
        </a>
        <a class="mc-btn mc-btn-ghost-light mc-btn-lg" href="<?= APP_URL ?>/emergency.php?uid=PT-000001">
          <i class="bi bi-lightning-charge-fill"></i> Emergency ICE Protocol
        </a>
      </div>
    </div>
  </div>
</section>

<!-- 2. LIVE SECURITY AUDIT METRICS -->
<div class="mc-trust-strip">
  <div class="mc-wrap">
    <div class="mc-trust-grid">
      <div class="mc-trust-item">
        <div class="mc-trust-icon teal"><i class="bi bi-key-fill"></i></div>
        <div class="mc-trust-label">
          <strong>AES-256 GCM</strong>
          <span>End-to-End Encrypted Records</span>
        </div>
      </div>
      <div class="mc-trust-item">
        <div class="mc-trust-icon blue"><i class="bi bi-clock-history"></i></div>
        <div class="mc-trust-label">
          <strong>30-Min Time-Lock</strong>
          <span>Auto-Expiring OTP Grants</span>
        </div>
      </div>
      <div class="mc-trust-item">
        <div class="mc-trust-icon emerald"><i class="bi bi-shield-check"></i></div>
        <div class="mc-trust-label">
          <strong>Patient Sovereignty</strong>
          <span>Instant Consent Revocation</span>
        </div>
      </div>
      <div class="mc-trust-item">
        <div class="mc-trust-icon amber"><i class="bi bi-file-earmark-lock2-fill"></i></div>
        <div class="mc-trust-label">
          <strong>Immutable Ledger</strong>
          <span>Full Forensic Audit Trail</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 3. CORE ARCHITECTURAL PILLARS -->
<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker"><i class="bi bi-shield-shaded"></i> Trust Fabric</p>
      <h2>চারটি মৌলিক নিরাপত্তা স্তম্ভ</h2>
      <p>মেডকোর কীভাবে জাতীয় স্বাস্থ্য ডেটার শতভাগ গোপনীয়তা এবং রোগীর নিজস্ব মালিকানা নিশ্চিত করে।</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;margin-top:1.5rem">
      <!-- Pillar 1 -->
      <div class="mc-pillar-card" style="padding:1.75rem">
        <div style="width:48px;height:48px;border-radius:14px;background:#ecfdf8;color:#0f766e;display:grid;place-items:center;font-size:1.4rem;margin-bottom:1rem">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h3 style="font-size:1.2rem;color:#0f172a;margin:0 0 0.5rem">Time-Bounded Access Sessions (৩০ মিনিট ওটিপি)</h3>
        <p style="font-size:0.88rem;line-height:1.6;color:#64748b;margin:0">
          কোনো ডাক্তার বা হাসপাতাল স্থায়ীভাবে রোগীর রেকর্ড দেখতে পারেন না। প্রতিটি এক্সেসের জন্য রোগীর মোবাইলে ৪-ডিজিটের ওটিপি যায় যা অনুমোদন দিলে সর্বোচ্চ ৩০ মিনিটের জন্য সেশন সচল থাকে।
        </p>
      </div>

      <!-- Pillar 2 -->
      <div class="mc-pillar-card" style="padding:1.75rem">
        <div style="width:48px;height:48px;border-radius:14px;background:#eff6ff;color:#1d4ed8;display:grid;place-items:center;font-size:1.4rem;margin-bottom:1rem">
          <i class="bi bi-x-octagon-fill"></i>
        </div>
        <h3 style="font-size:1.2rem;color:#0f172a;margin:0 0 0.5rem">Active Revocation Override (তাৎক্ষণিক প্রত্যাহার)</h3>
        <p style="font-size:0.88rem;line-height:1.6;color:#64748b;margin:0">
          রোগীর নিজস্ব সার্বভৌমত্ব সর্বোচ্চ। কনসালটেশন চলাকালীন রোগী চাইলে ১-ক্লিকে যেকোনো ডাক্তারের চলমান সেশন বাতিল করে দিতে পারেন, যা সাথে সাথে চিকিৎসকের স্ক্রিন লক করে দেয়।
        </p>
      </div>

      <!-- Pillar 3 -->
      <div class="mc-pillar-card" style="padding:1.75rem">
        <div style="width:48px;height:48px;border-radius:14px;background:#fef2f2;color:#dc2626;display:grid;place-items:center;font-size:1.4rem;margin-bottom:1rem">
          <i class="bi bi-database-lock"></i>
        </div>
        <h3 style="font-size:1.2rem;color:#0f172a;margin:0 0 0.5rem">AES-256 GCM Storage Encryption</h3>
        <p style="font-size:0.88rem;line-height:1.6;color:#64748b;margin:0">
          জাতীয় পরিচয়পত্র (NID), ফোন নম্বর, ড্রাগ অ্যালার্জি এবং ডায়াগনস্টিক রিপোর্ট ডেটাবেজে সংরক্ষিত হওয়ার আগেই সামরিক গ্রেডের ক্রিপ্টোগ্রাফিক অ্যালগরিদমে এনক্রিপ্ট হয়ে থাকে।
        </p>
      </div>

      <!-- Pillar 4 -->
      <div class="mc-pillar-card" style="padding:1.75rem">
        <div style="width:48px;height:48px;border-radius:14px;background:#fffbeb;color:#b45309;display:grid;place-items:center;font-size:1.4rem;margin-bottom:1rem">
          <i class="bi bi-journal-code"></i>
        </div>
        <h3 style="font-size:1.2rem;color:#0f172a;margin:0 0 0.5rem">Immutable Forensics Audit Trail</h3>
        <p style="font-size:0.88rem;line-height:1.6;color:#64748b;margin:0">
          প্ল্যাটফর্মে কারোর কোনো অ্যাকশন গোপন থাকে না। কে কখন কোন রোগীর ফাইল খুলেছেন, কোন প্রেসক্রিপশন দিয়েছেন বা কোন রিপোর্ট সার্চ করেছেন—তা আইপি ও ডিভাইস সহ অপরিবর্তনীয় লগ ফাইলে সংরক্ষিত হয়।
        </p>
      </div>
    </div>
  </div>
</section>

<!-- 4. DGHS & BMDC COMPLIANCE STRIP -->
<section class="mc-section" style="background:#ffffff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)">
  <div class="mc-wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.5rem">
      <div style="max-width:580px">
        <h3 style="font-size:1.25rem;color:#0f172a;margin:0 0 0.35rem">National Healthcare Regulatory Standards</h3>
        <p style="font-size:0.88rem;color:#64748b;margin:0;line-height:1.6">
          Directorate General of Health Services (DGHS) Digital Health Blueprint, Bangladesh Medical and Dental Council (BMDC) licensing, and ISO 27001 data center privacy standards.
        </p>
      </div>
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap">
        <span class="mc-pill" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;padding:0.4rem 0.8rem">
          <i class="bi bi-patch-check-fill" style="color:#0f766e"></i> DGHS Compliant
        </span>
        <span class="mc-pill" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;padding:0.4rem 0.8rem">
          <i class="bi bi-shield-check" style="color:#1d4ed8"></i> BMDC Verified
        </span>
        <span class="mc-pill" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;padding:0.4rem 0.8rem">
          <i class="bi bi-lock-fill" style="color:#059669"></i> ISO 27001 Ready
        </span>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
