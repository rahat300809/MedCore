<footer class="mc-footer mc-footer-v2">
  <div class="mc-wrap mc-footer-grid">
    <div>
      <div class="mc-brand-name" style="margin-bottom:.65rem">Med<span>Core</span></div>
      <p class="mc-footer-lede">Consent-driven national health records for Bangladesh — citizens, clinicians, hospitals, labs.</p>
    </div>
    <div>
      <h4>Portals</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/patient/login.php">Patient</a></li>
        <li><a href="<?= APP_URL ?>/doctor/select-hospital.php">Doctor</a></li>
        <li><a href="<?= APP_URL ?>/hospital/login.php">Hospital</a></li>
        <li><a href="<?= APP_URL ?>/lab/login.php">Laboratory</a></li>
      </ul>
    </div>
    <div>
      <h4>Care</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/book-doctor.php">Book serial</a></li>
        <li><a href="<?= APP_URL ?>/emergency.php">ICE emergency</a></li>
        <li><a href="<?= APP_URL ?>/security.php">Security</a></li>
        <li><a href="<?= APP_URL ?>/role-selection.php">All gateways</a></li>
      </ul>
    </div>
    <div>
      <h4>Hotlines</h4>
      <p class="mc-footer-hotline">999 · 16263</p>
      <p class="mc-footer-demo">Demo password <code>Demo123!</code> · OTP <code>1234</code></p>
    </div>
  </div>
  <div class="mc-footer-bottom">
    <div class="mc-wrap" style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;width:100%">
      <span>© <?= date('Y') ?> MedCore</span>
      <span>DGHS-aligned digital health grid</span>
    </div>
  </div>
</footer>
<script src="<?= APP_URL ?>/assets/js/competition.js?v=60" defer></script>
<?php if (!empty($includeChatbot)): ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">
<script src="<?= APP_URL ?>/assets/js/chatbot.js" defer></script>
<?php endif; ?>
</body>
</html>
