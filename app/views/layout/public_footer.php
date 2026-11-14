<footer class="mc-footer">
  <div class="mc-wrap mc-footer-grid">
    <div>
      <div class="mc-brand-name" style="margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem">
        <i class="bi bi-heart-pulse-fill" style="color:var(--accent)"></i>
        Med<span style="color:var(--accent)">Core</span>
      </div>
      <p style="margin:0 0 1rem;color:var(--muted);font-size:.92rem;line-height:1.6">
        Bangladesh National Unified Digital Health Infrastructure. Secure, consent-driven healthcare records uniting citizens, clinicians, hospitals, and diagnostic networks.
      </p>
      <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
        <span class="mc-pill" style="font-size:.72rem;background:#0b253a;color:#7dd3fc;border:1px solid #1e40af">
          <i class="bi bi-patch-check-fill" style="color:#38bdf8"></i> DGHS Compliant
        </span>
        <span class="mc-pill" style="font-size:.72rem;background:#ecfdf8;color:#0f766e;border:1px solid #a7f3d0">
          <i class="bi bi-shield-lock-fill"></i> 30-Min OTP Lock
        </span>
      </div>
    </div>
    <div>
      <h4>Clinical Portals</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/patient/login.php"><i class="bi bi-person"></i> Citizen & Patient</a></li>
        <li><a href="<?= APP_URL ?>/doctor/select-hospital.php"><i class="bi bi-stethoscope"></i> Doctor Clinical Desk</a></li>
        <li><a href="<?= APP_URL ?>/hospital/login.php"><i class="bi bi-hospital"></i> Hospital Operations</a></li>
        <li><a href="<?= APP_URL ?>/lab/login.php"><i class="bi bi-eyedropper"></i> Diagnostic Lab</a></li>
        <li><a href="<?= APP_URL ?>/admin/login.php" style="color:#0f766e;font-weight:700"><i class="bi bi-shield-lock"></i> System Admin</a></li>
      </ul>
    </div>
    <div>
      <h4>Clinical Services</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/book-doctor.php"><i class="bi bi-calendar2-check"></i> Book Doctor Serial</a></li>
        <li><a href="<?= APP_URL ?>/hospital/code-blue.php" style="color:#ef4444;font-weight:700"><i class="bi bi-lightning-charge-fill"></i> Code Blue ACLS AI</a></li>
        <li><a href="<?= APP_URL ?>/patient/sidekick.php" style="color:#0f766e;font-weight:700"><i class="bi bi-shield-heart-fill"></i> Sidekick Advocate AI</a></li>
        <li><a href="<?= APP_URL ?>/emergency.php" style="color:var(--danger)"><i class="bi bi-shield-fill-exclamation"></i> Emergency ICE Portal</a></li>
        <li><a href="<?= APP_URL ?>/security.php"><i class="bi bi-shield-check"></i> Security Architecture</a></li>
      </ul>
    </div>
    <div>
      <h4>Hotlines & Demo</h4>
      <div style="margin-bottom:.75rem">
        <div style="font-size:.85rem;color:var(--muted);margin-bottom:.25rem">National Emergency:</div>
        <div style="font-size:1.05rem;font-weight:800;color:var(--danger)">
          <i class="bi bi-telephone-fill"></i> 999 &nbsp;·&nbsp; 16263
        </div>
      </div>
      <div style="background:#f8fafc;padding:.75rem;border-radius:10px;border:1px solid var(--line);font-size:.85rem">
        <div style="font-weight:700;color:var(--ink-2);margin-bottom:.2rem">Demo Credentials</div>
        <div>Password: <code class="mc-mono" style="background:#e2e8f0;padding:1px 5px;border-radius:4px">Demo123!</code></div>
        <div>Consent OTP: <code class="mc-mono" style="background:#e2e8f0;padding:1px 5px;border-radius:4px">1234</code></div>
      </div>
    </div>
  </div>
  <div class="mc-footer-bottom">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;width:100%">
      <div>© <?= date('Y') ?> MedCore National Digital Health Network · Directorate General of Health Services (DGHS) Standards.</div>
      <div style="display:flex;gap:1.25rem;font-size:.85rem">
        <a href="<?= APP_URL ?>/security.php" style="color:var(--muted)">Security & Privacy</a>
        <a href="<?= APP_URL ?>/role-selection.php" style="color:var(--accent);font-weight:600">Portals Gateway</a>
      </div>
    </div>
  </div>
</footer>
<script src="<?= APP_URL ?>/assets/js/competition.js" defer></script>
<?php if (!empty($includeChatbot)): ?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/chatbot.css">
<script src="<?= APP_URL ?>/assets/js/chatbot.js" defer></script>
<?php endif; ?>
</body>
</html>
