<?php
require_once dirname(__DIR__) . '/media.php';
$pageTitle = ($loginTitle ?? 'Login') . ' — MedCore';
require dirname(__DIR__) . '/layout/head.php';
?>
<div class="mc-auth">
  <div class="mc-auth-bg" aria-hidden="true"><img src="<?= htmlspecialchars($loginImage) ?>" alt=""></div>
  <div class="mc-auth-card">
    <a href="<?= htmlspecialchars($backUrl ?? (APP_URL . '/role-selection.php')) ?>" style="font-size:.85rem;font-weight:700;color:var(--muted)">← All portals</a>
    <p class="mc-kicker" style="margin-top:1rem"><?= htmlspecialchars($loginTitle ?? 'Login') ?></p>
    <h1><?= htmlspecialchars($loginTitleBn ?? 'Login') ?></h1>
    <p class="sub">লগইন করে পোর্টালে প্রবেশ করুন।</p>

    <?php if (!empty($error)): ?><div class="mc-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="mc-alert mc-alert-ok" style="margin-top:.85rem"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?= $loginFormHtml ?>

    <div class="mc-demo">
      <strong>Demo</strong><br>
      <?= htmlspecialchars($demoEmail) ?><br>
      Password: <?= htmlspecialchars($demoPass) ?>
      <?php if (!empty($demoNote)): ?><br><?= htmlspecialchars($demoNote) ?><?php endif; ?>
    </div>

    <?php if (!empty($registerUrl)): ?>
      <p style="margin:1rem 0 0;text-align:center;font-size:.9rem;color:var(--muted)">
        No account? <a style="color:var(--accent);font-weight:800" href="<?= htmlspecialchars($registerUrl) ?>">Register</a>
      </p>
    <?php endif; ?>
  </div>
</div>
<script>
function mcFillDemo(email, pass) {
  const e = document.getElementById('email');
  const p = document.getElementById('password');
  if (e) e.value = email;
  if (p) p.value = pass;
}
function mcTogglePass() {
  const p = document.getElementById('password');
  if (p) p.type = p.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
