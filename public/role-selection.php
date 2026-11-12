<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/media.php';
initSession();

if (isset($_SESSION['role'])) {
    $map = [
        'SYSTEM_ADMIN' => '/admin/dashboard.php',
        'HOSPITAL_ADMIN' => '/hospital/dashboard.php',
        'DOCTOR' => '/doctor/dashboard.php',
        'PATIENT' => '/patient/dashboard.php',
        'LABORATORY' => '/lab/dashboard.php',
    ];
    if (isset($map[$_SESSION['role']])) {
        header('Location: ' . APP_URL . $map[$_SESSION['role']]);
        exit;
    }
}

$pageTitle = 'Choose portal — MedCore';
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';

$roles = [
    [APP_URL.'/patient/login.php', MC_IMG_PATIENT, 'Patient', 'নাগরিক লগইন', 'rahat@example.com'],
    [APP_URL.'/doctor/select-hospital.php', MC_IMG_DOCTOR, 'Doctor', 'ডাক্তার লগইন', 'dr.rahman@medcore.local'],
    [APP_URL.'/hospital/login.php', MC_IMG_HOSPITAL, 'Hospital', 'হাসপাতাল লগইন', 'admin@abc-hospital.com'],
    [APP_URL.'/lab/login.php', MC_IMG_LAB, 'Laboratory', 'ল্যাব লগইন', 'lab@apexdiagnostics.com'],
];
?>

<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker">Portal access</p>
      <h2>আগে লগইন, তারপর পোর্টাল</h2>
      <p>Password <strong>Demo123!</strong> · OTP <strong>1234</strong></p>
    </div>
    <div class="mc-grid-2">
      <?php foreach ($roles as $r): ?>
        <a class="mc-tile" href="<?= $r[0] ?>">
          <div class="mc-tile-img"><img src="<?= $r[1] ?>" alt=""></div>
          <div class="mc-tile-body">
            <div class="en"><?= $r[2] ?></div>
            <h3><?= $r[3] ?></h3>
            <p><?= $r[4] ?></p>
            <div class="go">Open login →</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
