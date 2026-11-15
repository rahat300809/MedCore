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

$pageTitle = 'Portal login — MedCore';
$bodyClass = 'mc-public';
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';

$portals = [
    [
        'id' => 'patient',
        'name' => 'Patient',
        'bn' => 'নাগরিক ও রোগী',
        'url' => APP_URL . '/patient/login.php',
        'email' => 'rahat@example.com',
        'note' => null,
        'color' => '#1a4d6d',
    ],
    [
        'id' => 'doctor',
        'name' => 'Doctor',
        'bn' => 'চিকিৎসক ডেস্ক',
        'url' => APP_URL . '/doctor/select-hospital.php',
        'email' => null,
        'note' => 'Hospital → verify → OTP 1234',
        'color' => '#0b6e5f',
    ],
    [
        'id' => 'hospital',
        'name' => 'Hospital',
        'bn' => 'হাসপাতাল অ্যাডমিন',
        'url' => APP_URL . '/hospital/login.php',
        'email' => 'admin@abc-hospital.com',
        'note' => null,
        'color' => '#1d4ed8',
    ],
    [
        'id' => 'lab',
        'name' => 'Laboratory',
        'bn' => 'ডায়াগনস্টিক ল্যাব',
        'url' => APP_URL . '/lab/login.php',
        'email' => 'lab@apexdiagnostics.com',
        'note' => null,
        'color' => '#0f766e',
    ],
    [
        'id' => 'admin',
        'name' => 'Admin',
        'bn' => 'সিস্টেম অ্যাডমিন',
        'url' => APP_URL . '/admin/login.php',
        'email' => 'admin@medcore.local',
        'note' => null,
        'color' => '#0c1f1a',
    ],
];
?>

<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal" style="max-width:40rem">
      <p class="mc-brand-hero mc-brand-hero-sm" style="color:var(--ink)">Med<span style="color:var(--accent)">Core</span></p>
      <h2>Choose your portal</h2>
      <p>ভূমিকা অনুযায়ী প্রবেশ করুন। ডেমো পাসওয়ার্ড <code class="mc-mono">Demo123!</code> · Consent OTP <code class="mc-mono">1234</code></p>
    </div>

    <div class="mc-role-grid-5 mc-reveal">
      <?php foreach ($portals as $p): ?>
        <article class="mc-role-box">
          <div class="mc-role-box-top<?= $p['id'] === 'admin' ? ' admin-top' : '' ?>">
            <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:<?= $p['id'] === 'admin' ? '#7dcfb6' : $p['color'] ?>">
              <?= htmlspecialchars($p['name']) ?>
            </div>
            <h3 style="margin:.35rem 0 0;font-family:var(--font-d);font-size:1.25rem;letter-spacing:-.02em;<?= $p['id'] === 'admin' ? 'color:#fff' : '' ?>">
              <?= htmlspecialchars($p['bn']) ?>
            </h3>
          </div>
          <div class="mc-role-box-body">
            <div class="mc-role-cred-box">
              <?php if (!empty($p['note'])): ?>
                <div class="mc-role-cred-label">Demo flow</div>
                <div style="font-weight:650;color:var(--ink)"><?= htmlspecialchars($p['note']) ?></div>
              <?php else: ?>
                <div class="mc-role-cred-label">Demo account</div>
                <div class="mc-role-cred-row">
                  <code class="mc-mono" style="font-weight:700;font-size:.78rem"><?= htmlspecialchars($p['email']) ?></code>
                  <button type="button" class="mc-copy-btn" onclick="copyCred('<?= htmlspecialchars($p['email'], ENT_QUOTES) ?>', this)" aria-label="Copy">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </div>
                <div class="mc-role-cred-row" style="margin-top:.4rem">
                  <code class="mc-mono">Demo123!</code>
                  <button type="button" class="mc-copy-btn" onclick="copyCred('Demo123!', this)" aria-label="Copy">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </div>
              <?php endif; ?>
            </div>
            <a class="mc-btn mc-btn-primary mc-btn-block" href="<?= htmlspecialchars($p['url']) ?>"
               style="<?= $p['id'] === 'admin' ? 'background:#0c1f1a' : 'background:' . $p['color'] ?>">
              Enter <?= htmlspecialchars($p['name']) ?>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<script>
function copyCred(text, btn) {
  var done = function () {
    var html = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i>';
    setTimeout(function () { btn.innerHTML = html; }, 1200);
  };
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(done).catch(function () { prompt('Copy:', text); });
  } else {
    prompt('Copy:', text);
  }
}
</script>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
