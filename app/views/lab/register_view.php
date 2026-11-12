<?php
require_once dirname(__DIR__) . '/media.php';
$pageTitle = 'Register Laboratory — MedCore';
require dirname(__DIR__) . '/layout/head.php';
?>
<div class="mc-auth" style="align-items:flex-start;padding:2rem 1rem 3rem">
  <div class="mc-auth-bg" aria-hidden="true"><img src="<?= htmlspecialchars(MC_IMG_LAB) ?>" alt=""></div>
  <div class="mc-auth-card" style="max-width:640px;width:100%">
    <a href="<?= APP_URL ?>/lab/login.php" style="font-size:.85rem;font-weight:700;color:var(--muted)">← Lab login</a>
    <p class="mc-kicker" style="margin-top:1rem">Laboratory registration</p>
    <h1>ল্যাব রেজিস্টার</h1>
    <p class="sub">MedCore হাসপাতাল নেটওয়ার্কের সাথে যুক্ত হতে অ্যাকাউন্ট তৈরি করুন।</p>

    <?php if (!empty($error)): ?><div class="mc-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="mc-alert mc-alert-ok" style="margin-top:.85rem"><?= $success ?></div>
      <p style="margin-top:1rem;text-align:center">
        <a class="mc-btn mc-btn-primary" href="<?= APP_URL ?>/lab/login.php">Go to login</a>
      </p>
    <?php else: ?>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="register" value="1">

        <p style="margin:1.1rem 0 .35rem;font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--accent)">Laboratory</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <label class="mc-field" style="margin-top:0">
            <span>Lab name *</span>
            <input type="text" name="lab_name" required value="<?= htmlspecialchars($_POST['lab_name'] ?? '') ?>">
          </label>
          <label class="mc-field" style="margin-top:0">
            <span>License / reg no *</span>
            <input type="text" name="registration_number" required value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>">
          </label>
          <label class="mc-field" style="margin-top:0">
            <span>City</span>
            <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
          </label>
          <label class="mc-field" style="margin-top:0">
            <span>Phone</span>
            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </label>
        </div>

        <label class="mc-field">
          <span>Address</span>
          <textarea name="address" rows="2" style="width:100%;border:1px solid var(--line);border-radius:12px;padding:.75rem 1rem;font:inherit;background:#fff"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
        </label>

        <label class="mc-field">
          <span>Services</span>
          <input type="text" name="services" placeholder="CBC, Blood Sugar, X-Ray…" value="<?= htmlspecialchars($_POST['services'] ?? '') ?>">
        </label>

        <p style="margin:1.1rem 0 .35rem;font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--accent)">Account</p>
        <label class="mc-field" style="margin-top:0">
          <span>Email *</span>
          <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <label class="mc-field" style="margin-top:0">
            <span>Password *</span>
            <input type="password" name="password" required minlength="8">
          </label>
          <label class="mc-field" style="margin-top:0">
            <span>Confirm password *</span>
            <input type="password" name="confirm_password" required minlength="8">
          </label>
        </div>

        <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg mc-btn-block" style="margin-top:1.1rem">Register laboratory</button>
      </form>

      <p style="margin:1rem 0 0;text-align:center;font-size:.9rem;color:var(--muted)">
        Already registered? <a style="color:var(--accent);font-weight:800" href="<?= APP_URL ?>/lab/login.php">Login</a>
      </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
