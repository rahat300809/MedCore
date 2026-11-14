<?php require_once dirname(__DIR__) . '/media.php'; ?>
<div class="mc-strip">
  <div class="mc-wrap">
    <span><i class="bi bi-shield-check" style="color:#38bdf8;margin-right:.35rem"></i>গণপ্রজাতন্ত্রী বাংলাদেশ · National Digital Health Grid (DGHS Standards)</span>
    <span><i class="bi bi-telephone-inbound" style="color:#7dd3fc;margin-right:.35rem"></i>Helpline <strong>16263</strong> · Emergency <strong>999</strong> · Telemedicine <strong>333</strong></span>
  </div>
</div>

<header class="mc-nav" id="mc-nav">
  <div class="mc-wrap mc-nav-inner">
    <a class="mc-brand" href="<?= APP_URL ?>/">
      <span class="mc-brand-mark"><i class="bi bi-heart-pulse-fill"></i></span>
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
      <li><a href="<?= APP_URL ?>/book-doctor.php"><i class="bi bi-calendar2-check" style="color:var(--accent);margin-right:.25rem"></i>Find Doctor</a></li>
      <li><a href="<?= APP_URL ?>/admin/login.php" style="color:var(--muted);font-size:.85rem"><i class="bi bi-shield-lock" style="margin-right:.2rem"></i>Admin</a></li>
    </ul>

    <div class="mc-nav-actions">
      <a class="mc-btn mc-btn-danger mc-btn-sm" href="<?= APP_URL ?>/emergency.php" title="Instant Emergency In-Case-of-Emergency Profile">
        <i class="bi bi-lightning-charge-fill"></i> ICE Emergency
      </a>
      <a class="mc-btn mc-btn-primary mc-btn-sm" href="<?= APP_URL ?>/role-selection.php">
        <i class="bi bi-grid-fill"></i> Portals Login
      </a>
      <button type="button" class="mc-menu-btn" id="mc-menu-btn" aria-label="Menu"><i class="bi bi-list"></i></button>
    </div>
  </div>
  <div class="mc-drawer" id="mc-menu" hidden>
    <a href="<?= APP_URL ?>/for-patients.php"><i class="bi bi-person"></i> For Patients</a>
    <a href="<?= APP_URL ?>/for-doctors.php"><i class="bi bi-stethoscope"></i> For Doctors</a>
    <a href="<?= APP_URL ?>/for-hospitals.php"><i class="bi bi-hospital"></i> For Hospitals</a>
    <a href="<?= APP_URL ?>/for-labs.php"><i class="bi bi-eyedropper"></i> For Diagnostic Labs</a>
    <a href="<?= APP_URL ?>/hospital/code-blue.php" style="color:#ef4444;font-weight:700"><i class="bi bi-lightning-charge-fill"></i> Code Blue ACLS AI</a>
    <a href="<?= APP_URL ?>/patient/sidekick.php" style="color:#0f766e;font-weight:700"><i class="bi bi-shield-heart-fill"></i> Sidekick Advocate AI</a>
    <a href="<?= APP_URL ?>/book-doctor.php"><i class="bi bi-calendar2-check"></i> Book Doctor Serial</a>
    <a href="<?= APP_URL ?>/admin/login.php"><i class="bi bi-shield-lock"></i> Central Admin Console</a>
    <a href="<?= APP_URL ?>/emergency.php" style="color:var(--danger);font-weight:700"><i class="bi bi-lightning-charge-fill"></i> Emergency ICE</a>
    <a href="<?= APP_URL ?>/role-selection.php" class="mc-btn mc-btn-primary mc-btn-sm" style="margin-top:.5rem;text-align:center"><i class="bi bi-grid-fill"></i> Select Portal</a>
  </div>
</header>
