<footer class="mc-footer">
  <div class="mc-wrap mc-footer-grid">
    <div>
      <div class="mc-brand-name" style="margin-bottom:.5rem">Med<span style="color:var(--accent)">Core</span></div>
      <p style="margin:0;color:var(--muted);font-size:.92rem;line-height:1.6">One citizen health record. Consent-driven care across Bangladesh.</p>
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
      <h4>Services</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/book-doctor.php">Doctor booking</a></li>
        <li><a href="<?= APP_URL ?>/emergency.php">Emergency ICE</a></li>
        <li><a href="<?= APP_URL ?>/security.php">Security</a></li>
      </ul>
    </div>
    <div>
      <h4>Demo</h4>
      <p style="margin:0;color:var(--muted);font-size:.9rem">OTP <code class="mc-mono">1234</code><br>Password <code class="mc-mono">Demo123!</code></p>
    </div>
  </div>
  <div class="mc-footer-bottom">© <?= date('Y') ?> MedCore · One Record. Every Care.</div>
</footer>
<script src="<?= APP_URL ?>/assets/js/competition.js" defer></script>
<?php if (!empty($includeChatbot)): ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">
<script src="<?= APP_URL ?>/assets/js/chatbot.js" defer></script>
<?php endif; ?>
</body>
</html>
