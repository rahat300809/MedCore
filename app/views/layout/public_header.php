<?php require_once dirname(__DIR__) . '/media.php'; ?>
<div class="mc-strip">
  <div class="mc-wrap">
    <span>গণপ্রজাতন্ত্রী বাংলাদেশ · National Digital Health Grid</span>
    <span>Helpline <strong>16263</strong> · Emergency <strong>999</strong></span>
  </div>
</div>

<header class="mc-nav" id="mc-nav">
  <div class="mc-wrap mc-nav-inner">
    <a class="mc-brand" href="<?= APP_URL ?>/">
      <span class="mc-brand-mark">M</span>
      <span>
        <span class="mc-brand-name">Med<span>Core</span></span>
        <span class="mc-brand-sub">One Record · Every Care</span>
      </span>
    </a>

    <ul class="mc-nav-links">
      <li><a href="<?= APP_URL ?>/for-patients.php">Patients</a></li>
      <li><a href="<?= APP_URL ?>/for-doctors.php">Doctors</a></li>
      <li><a href="<?= APP_URL ?>/for-hospitals.php">Hospitals</a></li>
      <li><a href="<?= APP_URL ?>/for-labs.php">Labs</a></li>
      <li><a href="<?= APP_URL ?>/book-doctor.php">Book serial</a></li>
    </ul>

    <div class="mc-nav-actions">
      <a class="mc-btn mc-btn-ghost mc-btn-sm" href="<?= APP_URL ?>/emergency.php">ICE</a>
      <a class="mc-btn mc-btn-primary mc-btn-sm" href="<?= APP_URL ?>/role-selection.php">Portal login</a>
      <button type="button" class="mc-menu-btn" id="mc-menu-btn" aria-label="Menu"><i class="bi bi-list"></i></button>
    </div>
  </div>
  <div class="mc-drawer" id="mc-menu" hidden>
    <a href="<?= APP_URL ?>/for-patients.php">Patients</a>
    <a href="<?= APP_URL ?>/for-doctors.php">Doctors</a>
    <a href="<?= APP_URL ?>/for-hospitals.php">Hospitals</a>
    <a href="<?= APP_URL ?>/for-labs.php">Labs</a>
    <a href="<?= APP_URL ?>/book-doctor.php">Book serial</a>
    <a href="<?= APP_URL ?>/role-selection.php">Portal login</a>
  </div>
</header>
