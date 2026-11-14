<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/media.php';
initSession();

if (isset($_SESSION['role'])) {
    $portals = [
        'PATIENT' => APP_URL . '/patient/dashboard.php',
        'DOCTOR' => APP_URL . '/doctor/dashboard.php',
        'HOSPITAL_ADMIN' => APP_URL . '/hospital/dashboard.php',
        'SYSTEM_ADMIN' => APP_URL . '/admin/dashboard.php',
        'LABORATORY' => APP_URL . '/lab/dashboard.php',
    ];
    if (isset($portals[$_SESSION['role']])) {
        header('Location: ' . $portals[$_SESSION['role']]);
        exit;
    }
}

$pageTitle = 'MedCore — National Unified Digital Health Network | One Record. Every Care.';
$includeChatbot = true;
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';
?>

<!-- 1. INTERNATIONAL CLINICAL HERO -->
<section class="mc-hero-intl">
  <div class="mc-wrap">
    <div class="mc-hero-content">
      <div class="mc-hero-badge">
        <span class="status-dot"></span>
        <span>DGHS COMPLIANT NATIONAL HEALTH GRID · BANGLADESH</span>
      </div>
      <h1 class="mc-hero-title">
        One Citizen Record.<br>
        <span>Every Episode of Care.</span>
      </h1>
      <p class="mc-hero-sub">
        একটিমাত্র জাতীয় স্বাস্থ্য আইডি দিয়ে সারাদেশের সরকারি ও বেসরকারি হাসপাতাল, বিশেষজ্ঞ চিকিৎসক এবং ডায়াগনস্টিক ল্যাবরেটরি থেকে নিরাপদ, সম্মতিকেন্দ্রিক ডিজিটাল স্বাস্থ্যসেবা।
      </p>

      <div class="mc-hero-actions-intl">
        <a class="mc-btn mc-btn-primary mc-btn-xl" href="<?= APP_URL ?>/book-doctor.php">
          <i class="bi bi-calendar2-check-fill"></i> Book Doctor Serial
        </a>
        <a class="mc-btn mc-btn-ghost-light mc-btn-xl" href="<?= APP_URL ?>/role-selection.php">
          <i class="bi bi-grid-fill"></i> Select Clinical Portal
        </a>
        <a class="mc-btn mc-btn-danger mc-btn-xl" href="<?= APP_URL ?>/emergency.php?uid=PT-000001">
          <i class="bi bi-lightning-charge-fill"></i> Emergency ICE
        </a>
      </div>

      <!-- Quick Triage & Search Box -->
      <form class="mc-search-triage" action="<?= APP_URL ?>/book-doctor.php" method="GET">
        <div class="mc-search-triage-field">
          <i class="bi bi-hospital"></i>
          <select name="department" aria-label="Select Clinical Department">
            <option value="">All Specialties (সকল বিভাগ)</option>
            <option value="Cardiology">Cardiology (হৃদরোগ বিভাগ)</option>
            <option value="Medicine">Internal Medicine (মেডিসিন)</option>
            <option value="Pediatrics">Pediatrics (শিশু স্বাস্থ্য)</option>
            <option value="Orthopedics">Orthopedics (হাড় ও জোড়া)</option>
            <option value="Gynecology">Gynecology (স্ত্রী ও প্রসূতি)</option>
            <option value="Neurology">Neurology (নিউরো মেডিসিন)</option>
            <option value="Pathology">Pathology (প্যাথলজি)</option>
          </select>
        </div>
        <div class="mc-search-triage-field">
          <i class="bi bi-search"></i>
          <input type="text" name="q" placeholder="Doctor name, hospital or condition..." aria-label="Search Doctor or Hospital">
        </div>
        <button type="submit" class="mc-search-triage-btn">
          <i class="bi bi-arrow-right-circle-fill"></i> Find Doctor
        </button>
      </form>
    </div>
  </div>
</section>

<!-- 2. NATIONAL HEALTH GRID LIVE METRICS -->
<div class="mc-trust-strip">
  <div class="mc-wrap">
    <div class="mc-trust-grid">
      <div class="mc-trust-item">
        <div class="mc-trust-icon teal"><i class="bi bi-person-check-fill"></i></div>
        <div class="mc-trust-label">
          <strong>4,800+ Verified</strong>
          <span>BMDC Registered Doctors</span>
        </div>
      </div>
      <div class="mc-trust-item">
        <div class="mc-trust-icon blue"><i class="bi bi-hospital-fill"></i></div>
        <div class="mc-trust-label">
          <strong>120+ Accredited</strong>
          <span>Tertiary & District Hospitals</span>
        </div>
      </div>
      <div class="mc-trust-item">
        <div class="mc-trust-icon emerald"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="mc-trust-label">
          <strong>100% Consent-Locked</strong>
          <span>30-Min Time-Expiring OTP</span>
        </div>
      </div>
      <div class="mc-trust-item">
        <div class="mc-trust-icon amber"><i class="bi bi-patch-check-fill"></i></div>
        <div class="mc-trust-label">
          <strong>Zero Data Leaks</strong>
          <span>Immutable DGHS Audit Trail</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 3. UNIFIED 5 CLINICAL PORTALS -->
<section class="mc-section" id="portals">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker"><i class="bi bi-grid-3x3-gap-fill"></i> Clinical Portals</p>
      <h2>Unified National Gateways</h2>
      <p>দায়িত্ব অনুযায়ী পৃথক ৫টি বিশেষায়িত সুরক্ষিত ক্লিনিক্যাল পোর্টাল — অনুমোদিত কাজের বাইরে কোনো তথ্য প্রকাশ পায় না।</p>
    </div>

    <div class="mc-portal-grid-5">
      <!-- Portal 1: Citizen & Patient -->
      <a class="mc-portal-card-intl patient-card" href="<?= APP_URL ?>/patient/login.php">
        <div>
          <div class="mc-portal-head">
            <span class="mc-portal-badge">Citizen / Patient</span>
            <div class="mc-portal-icon-wrap"><i class="bi bi-person-vcard-fill"></i></div>
          </div>
          <div class="mc-portal-body">
            <h3>Patient Health Portal</h3>
            <div class="mc-portal-bn">নাগরিক ও রোগী পোর্টাল</div>
            <p>আজীবন ডিজিটাল স্বাস্থ্য ইতিহাস, নিরাপদ প্রেসক্রিপশন ভল্ট এবং ডাক্তারের জন্য তাৎক্ষণিক ওটিপি সম্মতি তৈরি।</p>
            <ul class="mc-portal-features">
              <li><i class="bi bi-check2-circle"></i> Universal Health ID (UID)</li>
              <li><i class="bi bi-check2-circle"></i> Dynamic Allergy & Blood Profile</li>
              <li><i class="bi bi-check2-circle"></i> 30-Minute Consent OTP Generation</li>
              <li><i class="bi bi-check2-circle"></i> Downloadable Digital Rx & Lab PDFs</li>
            </ul>
          </div>
        </div>
        <div class="mc-portal-cta">
          <span>Open Patient Portal</span>
          <i class="bi bi-arrow-right"></i>
        </div>
      </a>

      <!-- Portal 2: Doctor Clinical Desk -->
      <a class="mc-portal-card-intl doctor-card" href="<?= APP_URL ?>/doctor/select-hospital.php">
        <div>
          <div class="mc-portal-head">
            <span class="mc-portal-badge">Clinician Desk</span>
            <div class="mc-portal-icon-wrap"><i class="bi bi-clipboard2-pulse-fill"></i></div>
          </div>
          <div class="mc-portal-body">
            <h3>Doctor Clinical Desk</h3>
            <div class="mc-portal-bn">চিকিৎসক ক্লিনিক্যাল ডেস্ক</div>
            <p>রোগীর ওটিপি সম্মতিক্রমে ৩০ মিনিটের ফুল EMR হিস্ট্রি এক্সেস, লাইভ ওপিডি কিউ এবং প্রেসক্রিপশন জেনারেটর।</p>
            <ul class="mc-portal-features">
              <li><i class="bi bi-check2-circle"></i> OTP-Protected Clinical Unlock</li>
              <li><i class="bi bi-check2-circle"></i> 30-Min Realtime Session Countdown</li>
              <li><i class="bi bi-check2-circle"></i> Longitudinal EMR Timeline & Vitals</li>
              <li><i class="bi bi-check2-circle"></i> Structured Prescription Pad & Lab Order</li>
            </ul>
          </div>
        </div>
        <div class="mc-portal-cta">
          <span>Doctor Login</span>
          <i class="bi bi-arrow-right"></i>
        </div>
      </a>

      <!-- Portal 3: Hospital Operations -->
      <a class="mc-portal-card-intl hospital-card" href="<?= APP_URL ?>/hospital/login.php">
        <div>
          <div class="mc-portal-head">
            <span class="mc-portal-badge">Hospital Operations</span>
            <div class="mc-portal-icon-wrap"><i class="bi bi-building-fill-check"></i></div>
          </div>
          <div class="mc-portal-body">
            <h3>Hospital Operations</h3>
            <div class="mc-portal-bn">হাসপাতাল ও রিসেপশন পোর্টাল</div>
            <p>রোগী রেজিস্ট্রেশন, ওপিডি সিরিয়াল টিকিট ইস্যু, ডাক্তারদের ডিউটি রোস্টার এবং ডিপার্টমেন্টাল ট্রাফিক ম্যানেজমেন্ট।</p>
            <ul class="mc-portal-features">
              <li><i class="bi bi-check2-circle"></i> Fast Patient OPD Registration</li>
              <li><i class="bi bi-check2-circle"></i> Doctor Roster & Shift Scheduling</li>
              <li><i class="bi bi-check2-circle"></i> Live Queue Dispatch Board</li>
              <li><i class="bi bi-check2-circle"></i> Bed Occupancy & Admission Records</li>
            </ul>
          </div>
        </div>
        <div class="mc-portal-cta">
          <span>Hospital Login</span>
          <i class="bi bi-arrow-right"></i>
        </div>
      </a>

      <!-- Portal 4: Diagnostic Laboratory -->
      <a class="mc-portal-card-intl lab-card" href="<?= APP_URL ?>/lab/login.php">
        <div>
          <div class="mc-portal-head">
            <span class="mc-portal-badge">Diagnostics</span>
            <div class="mc-portal-icon-wrap"><i class="bi bi-eyedropper"></i></div>
          </div>
          <div class="mc-portal-body">
            <h3>Laboratory Portal</h3>
            <div class="mc-portal-bn">ডায়াগনস্টিক ল্যাব পোর্টাল</div>
            <p>টোকেন সার্চ (LT-XXXXX), স্পেসিমেন ট্র্যাকিং, টেস্ট রেজাল্ট আপলোড এবং স্বয়ংক্রিয় রোগীর EMR সিঙ্ক।</p>
            <ul class="mc-portal-features">
              <li><i class="bi bi-check2-circle"></i> Direct Lab Token (LT) Lookup</li>
              <li><i class="bi bi-check2-circle"></i> Pathology & Biochemistry Entry</li>
              <li><i class="bi bi-check2-circle"></i> Abnormal & Critical Flag Alerts</li>
              <li><i class="bi bi-check2-circle"></i> Instant Sync to Patient Health Profile</li>
            </ul>
          </div>
        </div>
        <div class="mc-portal-cta">
          <span>Laboratory Login</span>
          <i class="bi bi-arrow-right"></i>
        </div>
      </a>

      <!-- Portal 5: Central System Admin -->
      <a class="mc-portal-card-intl admin-card" href="<?= APP_URL ?>/admin/login.php">
        <div>
          <div class="mc-portal-head">
            <span class="mc-portal-badge" style="background:#0b253a;color:#7dd3fc">National Governance</span>
            <div class="mc-portal-icon-wrap"><i class="bi bi-shield-lock-fill"></i></div>
          </div>
          <div class="mc-portal-body">
            <h3>System Admin Console</h3>
            <div class="mc-portal-bn">সিস্টেম অ্যাডমিন ও গভর্ন্যান্স</div>
            <p>সারাদেশের ডাক্তার ভেরিফিকেশন, হাসপাতাল লাইসেন্স অনুমোদন, সিস্টেম সিকিউরিটি অডিট এবং লাইভ ডেটাবেজ টেলিমেট্রি।</p>
            <ul class="mc-portal-features">
              <li><i class="bi bi-check2-circle"></i> Doctor BMDC Verification & Approval</li>
              <li><i class="bi bi-check2-circle"></i> Hospital Accreditation Control</li>
              <li><i class="bi bi-check2-circle"></i> Cross-Entity Security Audit Trail</li>
              <li><i class="bi bi-check2-circle"></i> System Health & Database Monitoring</li>
            </ul>
          </div>
        </div>
        <div class="mc-portal-cta">
          <span>Admin Console</span>
          <i class="bi bi-arrow-right"></i>
        </div>
      </a>
    </div>
  </div>
</section>

<!-- 4. CLINICAL WORKFLOW ARCHITECTURE (4 PILLARS) -->
<section class="mc-section" style="background:#ffffff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker"><i class="bi bi-diagram-3-fill"></i> Clinical Workflow</p>
      <h2>The Interoperable Grid</h2>
      <p>নিরাপদ, সময়োপযোগী এবং নির্ভরযোগ্য স্বাস্থ্যসেবার ৪টি মূল স্তম্ভ।</p>
    </div>

    <div class="mc-pillars-grid">
      <div class="mc-pillar-card">
        <div class="mc-pillar-num">01</div>
        <h3>Universal Citizen UID</h3>
        <p>নাগরিকের আজীবন স্বাস্থ্যের এক একক ডিজিটালি সুরক্ষিত পরিচয়পত্র (PT-000001)। একাধিক হাসপাতালের ভিন্ন ভিন্ন কাগজের ফাইল দূর করে এক ছাতার নিচে সব রেকর্ড।</p>
      </div>

      <div class="mc-pillar-card">
        <div class="mc-pillar-num">02</div>
        <h3>Time-Locked Consent OTP</h3>
        <p>রোগীর অনুমতি ছাড়া কোনো ডাক্তার বা হাসপাতাল পূর্ববর্তী রেকর্ড দেখতে পারে না। ৪-সংখ্যার ওটিপি দিলে সর্বোচ্চ ৩০ মিনিটের জন্য নিরাপদ সেশন ওপেন হয়।</p>
      </div>

      <div class="mc-pillar-card">
        <div class="mc-pillar-num">03</div>
        <h3>Digital Prescription & OPD</h3>
        <p>লাইভ ওপিডি কিউ থেকে রোগী ডেকে ড্রপডাউন ভিত্তিক সুনির্দিষ্ট ঔষধ ও পরীক্ষা প্রেসক্রাইব। প্রেসক্রিপশন সাথে সাথে রোগীর নিজস্ব পোর্টালে জমা হয়।</p>
      </div>

      <div class="mc-pillar-card">
        <div class="mc-pillar-num">04</div>
        <h3>Interoperable Lab Sync</h3>
        <p>ল্যাবরেটরি থেকে পরীক্ষার ফলাফল (LT-XXXXX) সাবমিট করলেই তা স্বয়ংক্রিয়ভাবে রোগীর সেন্ট্রাল ইএমআর এবং চিকিৎসকের পোর্টালে সিঙ্ক হয়ে যায়।</p>
      </div>
    </div>
  </div>
</section>

<!-- 5. MEDICAL SPECIALTIES DIRECTORY -->
<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker"><i class="bi bi-heart-pulse"></i> Specialties</p>
      <h2>Departmental Care Directory</h2>
      <p>অভিজ্ঞ চিকিৎসকদের তালিকা থেকে আপনার প্রয়োজনীয় বিভাগের সিরিয়াল সরাসরি বুক করুন।</p>
    </div>

    <div class="mc-specialty-grid">
      <a class="mc-specialty-item" href="<?= APP_URL ?>/book-doctor.php?department=Cardiology">
        <div class="mc-specialty-icon"><i class="bi bi-heart-pulse-fill"></i></div>
        <strong>Cardiology</strong>
        <span>হৃদরোগ ও ভাস্কুলার</span>
      </a>

      <a class="mc-specialty-item" href="<?= APP_URL ?>/book-doctor.php?department=Medicine">
        <div class="mc-specialty-icon"><i class="bi bi-capsule-pill"></i></div>
        <strong>Internal Medicine</strong>
        <span>মেডিসিন ও ডায়াবেটিস</span>
      </a>

      <a class="mc-specialty-item" href="<?= APP_URL ?>/book-doctor.php?department=Pediatrics">
        <div class="mc-specialty-icon"><i class="bi bi-emoji-smile-fill"></i></div>
        <strong>Pediatrics</strong>
        <span>নবজাতক ও শিশু বিশেষজ্ঞ</span>
      </a>

      <a class="mc-specialty-item" href="<?= APP_URL ?>/book-doctor.php?department=Orthopedics">
        <div class="mc-specialty-icon"><i class="bi bi-bandaid-fill"></i></div>
        <strong>Orthopedics</strong>
        <span>হাড়, জোড়া ও ট্রমা</span>
      </a>

      <a class="mc-specialty-item" href="<?= APP_URL ?>/book-doctor.php?department=Gynecology">
        <div class="mc-specialty-icon"><i class="bi bi-gender-female"></i></div>
        <strong>Gynecology</strong>
        <span>স্ত্রী ও প্রসূতি বিশেষজ্ঞ</span>
      </a>

      <a class="mc-specialty-item" href="<?= APP_URL ?>/book-doctor.php?department=Neurology">
        <div class="mc-specialty-icon"><i class="bi bi-activity"></i></div>
        <strong>Neurology</strong>
        <span>ব্রেইন, নার্ভ ও স্পাইন</span>
      </a>

      <a class="mc-specialty-item" href="<?= APP_URL ?>/book-doctor.php?department=Pathology">
        <div class="mc-specialty-icon"><i class="bi bi-eyedropper"></i></div>
        <strong>Diagnostic Lab</strong>
        <span>প্যাথলজি ও মাইক্রোবায়োলজি</span>
      </a>

      <a class="mc-specialty-item" href="<?= APP_URL ?>/book-doctor.php">
        <div class="mc-specialty-icon"><i class="bi bi-plus-circle-fill"></i></div>
        <strong>View All Care</strong>
        <span>সকল বিভাগ দেখুন</span>
      </a>
    </div>
  </div>
</section>

<!-- 6. EMERGENCY ICE (IN CASE OF EMERGENCY) CALLOUT -->
<section class="mc-section" style="padding-top:0">
  <div class="mc-wrap">
    <div class="mc-ice-banner">
      <div class="mc-ice-left">
        <div class="mc-ice-kicker">
          <i class="bi bi-lightning-charge-fill"></i> Immediate Emergency Care (ICE)
        </div>
        <h2 class="mc-ice-title">লগইন ছাড়া জীবনরক্ষী জরুরি স্বাস্থ্য তথ্য</h2>
        <p class="mc-ice-sub">
          জরুরি দুর্ঘটনা বা অচেতন অবস্থায় চিকিৎসকদের জন্য রোগীর রক্তের গ্রুপ, তীব্র ড্রাগ অ্যালার্জি এবং জরুরি অভিভাবকের ফোন নম্বর তাৎক্ষণিক প্রদর্শন।
        </p>
      </div>
      <div class="mc-ice-actions">
        <a class="mc-btn mc-btn-primary mc-btn-lg" href="<?= APP_URL ?>/emergency.php?uid=PT-000001" style="background:#ffffff;color:#991b1b;border:none">
          <i class="bi bi-shield-fill-exclamation"></i> Open ICE Demo (PT-000001)
        </a>
        <a class="mc-btn mc-btn-ghost-light mc-btn-lg" href="tel:999">
          <i class="bi bi-telephone-fill"></i> Call 999
        </a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
