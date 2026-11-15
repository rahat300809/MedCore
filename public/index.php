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

$pageTitle = 'MedCore — International Clinical Health Platform';
$pageDescription = 'Consent-driven EHR with Code Blue ACLS audio scribe and Sidekick patient advocate.';
$includeChatbot = true;
$bodyClass = 'mc-public mc-intl';
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';
?>

<section class="mc-stage" aria-label="MedCore">
  <div class="mc-stage-media" aria-hidden="true">
    <img src="<?= MC_IMG_HOME ?>" alt="">
  </div>
  <div class="mc-stage-veil" aria-hidden="true"></div>
  <div class="mc-wrap mc-stage-copy">
    <p class="mc-brand-hero">Med<span>Core</span></p>
    <h1>Clinical clarity.<br>National reach.</h1>
    <p class="mc-stage-lede">একটি UID · OTP সম্মতি · হাসপাতাল, চেম্বার ও ল্যাব এক নিরাপদ গ্রিডে।</p>
    <div class="mc-stage-cta">
      <a class="mc-btn mc-btn-primary mc-btn-lg" href="<?= APP_URL ?>/book-doctor.php">Book serial</a>
      <a class="mc-btn mc-btn-ghost-light mc-btn-lg" href="<?= APP_URL ?>/role-selection.php">Clinical portals</a>
    </div>
  </div>
</section>

<section class="mc-section mc-paths">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal">
      <h2>Care pathways</h2>
      <p>International-grade portals — role-separated, record-unified.</p>
    </div>
    <div class="mc-path-row mc-reveal">
      <a class="mc-path" href="<?= APP_URL ?>/for-patients.php">
        <span class="mc-path-label">Patients</span>
        <strong>Citizen EHR</strong>
        <span>Medicines explained · Sidekick · ICE</span>
      </a>
      <a class="mc-path" href="<?= APP_URL ?>/for-doctors.php">
        <span class="mc-path-label">Doctors</span>
        <strong>Clinical desk</strong>
        <span>OTP EMR · Queue · Code Blue</span>
      </a>
      <a class="mc-path" href="<?= APP_URL ?>/for-hospitals.php">
        <span class="mc-path-label">Hospitals</span>
        <strong>OPD & ER</strong>
        <span>Reception · ACLS scribe</span>
      </a>
      <a class="mc-path" href="<?= APP_URL ?>/for-labs.php">
        <span class="mc-path-label">Labs</span>
        <strong>Diagnostics</strong>
        <span>LT tokens · EMR sync</span>
      </a>
    </div>
  </div>
</section>

<section class="mc-section mc-feature-pro" id="code-blue">
  <div class="mc-wrap">
    <div class="mc-pro-grid mc-reveal">
      <div class="mc-pro-copy">
        <p class="mc-pro-tag danger">Code Blue · ACLS</p>
        <h2>Cardiac arrest documentation, hands-free</h2>
        <p class="mc-pro-problem">
          <strong>সমস্যা:</strong> Hospital-এ cardiac arrest-এ ৮–১৫ জনের টিম কাজ করে। কে কখন কোন ওষুধ দিল, কখন CPR/Shock হলো — সব রেকর্ড করা কঠিন।
        </p>
        <ul class="mc-pro-list">
          <li>Whisper AudioToText দিয়ে রুমের অডিও → টেক্সট</li>
          <li>Groq দিয়ে টেক্সট পরিষ্কার ও intervention সাজানো</li>
          <li>Epi / Pulse check টাইমার ও ACLS রিমাইন্ডার</li>
          <li>AHA ACLS guideline অনুযায়ী run sheet</li>
        </ul>
        <div class="mc-stage-cta">
          <a class="mc-btn mc-btn-danger" href="<?= APP_URL ?>/hospital/login.php?redirect=<?= urlencode('/hospital/code-blue.php') ?>">Hospital ER</a>
          <a class="mc-btn mc-btn-secondary" href="<?= APP_URL ?>/doctor/select-hospital.php?redirect=<?= urlencode('/doctor/code-blue.php') ?>">Doctor desk</a>
        </div>
      </div>
      <div class="mc-pro-visual" aria-hidden="true">
        <img src="<?= MC_IMG_HOSPITAL ?>" alt="">
      </div>
    </div>
  </div>
</section>

<section class="mc-section mc-feature-pro mc-feature-alt" id="sidekick">
  <div class="mc-wrap">
    <div class="mc-pro-grid mc-pro-grid-rev mc-reveal">
      <div class="mc-pro-copy">
        <p class="mc-pro-tag">Sidekick · Patient advocate</p>
        <h2>Never leave the chamber confused</h2>
        <p class="mc-pro-problem">
          <strong>সমস্যা:</strong> ডাক্তারের সঙ্গে অ্যাপয়েন্টমেন্টে রোগীরা গুরুত্বপূর্ণ তথ্য ভুলে যান।
        </p>
        <ul class="mc-pro-list">
          <li>কথোপকথন শোনে (Whisper + Groq)</li>
          <li>মেডিকেল টার্ম সহজ বাংলা/ইংরেজিতে</li>
          <li>ডাক্তারকে কী জিজ্ঞেস করবেন — সাজেশন</li>
          <li>শেষে সারাংশ, next steps ও ওষুধের উদ্দেশ্য</li>
        </ul>
        <div class="mc-stage-cta">
          <a class="mc-btn mc-btn-primary" href="<?= APP_URL ?>/patient/login.php?redirect=<?= urlencode('/patient/sidekick.php') ?>">Open Sidekick</a>
          <a class="mc-btn mc-btn-secondary" href="<?= APP_URL ?>/patient/login.php?redirect=<?= urlencode('/patient/medications.php') ?>">Medicine guide</a>
        </div>
      </div>
      <div class="mc-pro-visual" aria-hidden="true">
        <img src="<?= MC_IMG_PATIENT ?>" alt="">
      </div>
    </div>
  </div>
</section>

<section class="mc-band mc-band-photo">
  <div class="mc-band-media" aria-hidden="true">
    <img src="<?= MC_IMG_DOCTOR ?>" alt="">
  </div>
  <div class="mc-wrap mc-band-copy mc-reveal">
    <h2>Consent before care</h2>
    <p>রেকর্ড খুলতে OTP · সেশন সর্বোচ্চ ৩০ মিনিট · তারপর স্বয়ংক্রিয় বন্ধ।</p>
    <a class="mc-btn mc-btn-primary" href="<?= APP_URL ?>/patient/login.php">Patient login</a>
  </div>
</section>

<section class="mc-section mc-spec-sec">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal">
      <h2>Find a specialty</h2>
      <p>বিভাগ বেছে সিরিয়াল বুক করুন।</p>
    </div>
    <div class="mc-spec-flow mc-reveal">
      <?php
      $specs = [
        ['Cardiology', 'হৃদরোগ'],
        ['Medicine', 'মেডিসিন'],
        ['Pediatrics', 'শিশু'],
        ['Orthopedics', 'অর্থো'],
        ['Gynecology', 'স্ত্রী ও প্রসূতি'],
        ['Neurology', 'নিউরোলজি'],
      ];
      foreach ($specs as $s): ?>
        <a href="<?= APP_URL ?>/book-doctor.php?department=<?= urlencode($s[0]) ?>"><?= htmlspecialchars($s[0]) ?> <em><?= htmlspecialchars($s[1]) ?></em></a>
      <?php endforeach; ?>
      <a class="mc-spec-all" href="<?= APP_URL ?>/book-doctor.php">All departments →</a>
    </div>
  </div>
</section>

<section class="mc-ice-stage">
  <div class="mc-wrap mc-ice-inner mc-reveal">
    <div>
      <p class="mc-brand-hero mc-brand-hero-sm">ICE</p>
      <h2>Emergency profile — no login</h2>
      <p>রক্তের গ্রুপ, অ্যালার্জি, জরুরি যোগাযোগ। ER ও অ্যাম্বুলেন্সের জন্য।</p>
    </div>
    <div class="mc-stage-cta">
      <a class="mc-btn mc-btn-danger mc-btn-lg" href="<?= APP_URL ?>/emergency.php?uid=PT-000001">Open ICE demo</a>
      <a class="mc-btn mc-btn-ghost-light mc-btn-lg" href="tel:999">Call 999</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
