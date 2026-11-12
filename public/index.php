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

$pageTitle = 'MedCore — One Record. Every Care.';
$includeChatbot = true;
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';
?>

<section class="mc-bleed-hero mc-bleed-hero-tall">
  <div class="mc-bleed-media" aria-hidden="true">
    <img src="<?= MC_IMG_HOME ?>" alt="">
  </div>
  <div class="mc-bleed-scrim"></div>
  <div class="mc-wrap mc-bleed-copy">
    <p class="mc-bleed-brand">Med<span>Core</span></p>
    <p class="mc-bleed-kicker">National digital health network</p>
    <h1>One Record. Every Care.</h1>
    <p>এক নাগরিক স্বাস্থ্য রেকর্ড — সম্মতির মাধ্যমে ডাক্তার, হাসপাতাল ও ল্যাব একই নিরাপদ প্রোফাইলে।</p>
    <div class="mc-hero-actions">
      <a class="mc-btn mc-btn-primary mc-btn-xl" href="<?= APP_URL ?>/book-doctor.php">Book doctor serial</a>
      <a class="mc-btn mc-btn-ghost-light mc-btn-xl" href="<?= APP_URL ?>/role-selection.php">Portal login</a>
    </div>
  </div>
</section>

<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker">Portals</p>
      <h2>যার যা দরকার, সেই প্রবেশপথ</h2>
      <p>চারটি আলাদা লগইন — ভিতরে শুধু প্রয়োজনীয় কাজ।</p>
    </div>
    <div class="mc-portal-list">
      <?php
      $portals = [
        [APP_URL.'/patient/login.php', 'Patients', 'নাগরিক রেকর্ড ও প্রেসক্রিপশন'],
        [APP_URL.'/doctor/select-hospital.php', 'Doctors', 'OTP সম্মতি, কিউ, প্রেসক্রাইব'],
        [APP_URL.'/hospital/login.php', 'Hospitals', 'রিসেপশন ও রোস্টার'],
        [APP_URL.'/lab/login.php', 'Labs', 'টোকেন সার্চ ও রিপোর্ট সিঙ্ক'],
      ];
      foreach ($portals as $p): ?>
        <a class="mc-portal-row" href="<?= $p[0] ?>">
          <span>
            <strong><?= $p[1] ?></strong>
            <span><?= $p[2] ?></span>
          </span>
          <span class="go">Login →</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mc-section" style="padding-top:0">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker">Flow</p>
      <h2>ক্লিনিক্যাল ধাপ</h2>
    </div>
    <div class="mc-feature-rows">
      <?php
      $steps = [
        ['Citizen record', 'এক UID-তে অ্যালার্জি ও ইতিহাস'],
        ['Consent OTP', '৪ ডিজিট কোডে ৩০ মিনিট সেশন'],
        ['Prescribe + queue', 'ডিজিটাল প্যাড ও লাইভ OPD'],
        ['Lab token sync', 'LT-XXXXX → EMR আপডেট'],
      ];
      foreach ($steps as $i => $s): ?>
        <div class="mc-feature-row">
          <span class="mc-feature-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <div>
            <h3><?= $s[0] ?></h3>
            <p><?= $s[1] ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mc-bleed-hero mc-bleed-hero-compact">
  <div class="mc-bleed-media" aria-hidden="true">
    <img src="<?= MC_IMG_EMERGENCY ?>" alt="">
  </div>
  <div class="mc-bleed-scrim mc-bleed-scrim-danger"></div>
  <div class="mc-wrap mc-bleed-copy">
    <p class="mc-bleed-kicker" style="color:#fecaca">Emergency ICE</p>
    <h1 style="font-size:clamp(1.6rem,3vw,2.2rem)">লগইন ছাড়া জীবনরক্ষী তথ্য</h1>
    <p>রক্তের গ্রুপ, অ্যালার্জি ও জরুরি যোগাযোগ।</p>
    <div class="mc-hero-actions">
      <a class="mc-btn mc-btn-danger mc-btn-xl" href="<?= APP_URL ?>/emergency.php?uid=PT-000001">Open ICE demo</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
