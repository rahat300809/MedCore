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

$pageTitle = 'Unified Portals Gateway — MedCore National Grid';
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';

$portals = [
    [
        'id' => 'patient',
        'name' => 'Citizen & Patient Portal',
        'bn' => 'নাগরিক ও রোগী লগইন',
        'url' => APP_URL . '/patient/login.php',
        'icon' => 'bi-person-vcard-fill',
        'color' => '#0284c7',
        'bg_soft' => '#eff6ff',
        'email' => 'rahat@example.com',
        'role_tag' => 'Citizen Access',
        'features' => 'Lifelong Health UID (PT-000001), Sidekick AI Advocate, Digital Rx Vault, 30-Min Consent OTP Grant',
        'btn_class' => 'mc-btn-primary'
    ],
    [
        'id' => 'doctor',
        'name' => 'Doctor Clinical Desk',
        'bn' => 'চিকিৎসক ক্লিনিক্যাল ডেস্ক',
        'url' => APP_URL . '/doctor/select-hospital.php',
        'icon' => 'bi-clipboard2-pulse-fill',
        'color' => '#0f766e',
        'bg_soft' => '#ecfdf8',
        'email' => 'dr.rahman@medcore.local',
        'role_tag' => 'BMDC Verified',
        'features' => 'OTP Consent EMR Unlock, Code Blue ACLS Assistant, Digital Rx Builder, Live OPD Queue',
        'btn_class' => 'mc-btn-primary'
    ],
    [
        'id' => 'hospital',
        'name' => 'Hospital Operations',
        'bn' => 'হাসপাতাল অ্যাডমিন ও রিসেপশন',
        'url' => APP_URL . '/hospital/login.php',
        'icon' => 'bi-building-fill-check',
        'color' => '#1d4ed8',
        'bg_soft' => '#eff6ff',
        'email' => 'admin@abc-hospital.com',
        'role_tag' => 'DGHS Accredited',
        'features' => 'Fast-track OPD Registration, Code Blue ER Co-Pilot, Doctor Rosters, Queue Dispatch & Beds',
        'btn_class' => 'mc-btn-primary'
    ],
    [
        'id' => 'lab',
        'name' => 'Diagnostic Laboratory',
        'bn' => 'ডায়াগনস্টিক ল্যাব পোর্টাল',
        'url' => APP_URL . '/lab/login.php',
        'icon' => 'bi-eyedropper',
        'color' => '#7c3aed',
        'bg_soft' => '#faf5ff',
        'email' => 'lab@apexdiagnostics.com',
        'role_tag' => 'ISO Certified Lab',
        'features' => 'Lab Token Search (LT-XXXXX), Specimen Triage, Test Report Entry & Critical Alert EMR Sync',
        'btn_class' => 'mc-btn-primary'
    ],
    [
        'id' => 'admin',
        'name' => 'Central System Admin',
        'bn' => 'সিস্টেম অ্যাডমিন ও জাতীয় গভর্ন্যান্স',
        'url' => APP_URL . '/admin/login.php',
        'icon' => 'bi-shield-lock-fill',
        'color' => '#0b253a',
        'bg_soft' => '#f1f5f9',
        'email' => 'admin@medcore.local',
        'role_tag' => 'National Governance',
        'features' => 'Doctor BMDC Verification, Hospital Licensing, Security Audit Trail, System Health & DB Monitoring',
        'btn_class' => 'mc-btn-secondary'
    ],
];
?>

<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head" style="max-width:52rem">
      <p class="mc-kicker"><i class="bi bi-shield-check"></i> Identity & Access Management</p>
      <h2>Unified Clinical Gateways (৫টি পৃথক প্রবেশপথ)</h2>
      <p>
        ভূমিকা অনুযায়ী আপনার নির্দিষ্ট পোর্টালে প্রবেশ করুন। পরীক্ষামূলক ডেমো ক্রেডেনশিয়াল নিচে দেওয়া আছে (Password: <code class="mc-mono">Demo123!</code> · Consent OTP: <code class="mc-mono">1234</code>)।
      </p>
    </div>

    <!-- 5 Portals Grid -->
    <div class="mc-role-grid-5">
      <?php foreach ($portals as $p): ?>
        <div class="mc-role-box" id="portal-<?= $p['id'] ?>">
          <div class="mc-role-box-top <?= $p['id'] === 'admin' ? 'admin-top' : '' ?>">
            <div class="mc-role-identity">
              <div class="mc-role-avatar" style="color:<?= $p['color'] ?>;background:<?= $p['bg_soft'] ?>">
                <i class="bi <?= $p['icon'] ?>"></i>
              </div>
              <div>
                <span class="mc-role-type" style="<?= $p['id'] === 'admin' ? 'color:#7dd3fc' : 'color:' . $p['color'] ?>">
                  <?= $p['role_tag'] ?>
                </span>
                <h3 style="<?= $p['id'] === 'admin' ? 'color:#ffffff' : '' ?>"><?= $p['name'] ?></h3>
                <div style="font-size:0.85rem;color:<?= $p['id'] === 'admin' ? '#94a3b8' : 'var(--muted)' ?>">
                  <?= $p['bn'] ?>
                </div>
              </div>
            </div>
          </div>

          <div class="mc-role-box-body">
            <div>
              <p style="font-size:0.86rem;line-height:1.5;color:var(--muted);margin:0 0 1rem">
                <?= $p['features'] ?>
              </p>

              <!-- Credentials Box -->
              <div class="mc-role-cred-box">
                <div class="mc-role-cred-label">Demo Credentials (ক্লিক করে কপি করুন)</div>
                <div class="mc-role-cred-row">
                  <span style="color:var(--muted)">Account:</span>
                  <div style="display:flex;align-items:center;gap:0.35rem">
                    <code class="mc-mono" style="font-weight:700"><?= $p['email'] ?></code>
                    <button type="button" class="mc-copy-btn" onclick="copyCred('<?= $p['email'] ?>', this)" title="Copy Email">
                      <i class="bi bi-clipboard"></i>
                    </button>
                  </div>
                </div>
                <div class="mc-role-cred-row">
                  <span style="color:var(--muted)">Password:</span>
                  <div style="display:flex;align-items:center;gap:0.35rem">
                    <code class="mc-mono">Demo123!</code>
                    <button type="button" class="mc-copy-btn" onclick="copyCred('Demo123!', this)" title="Copy Password">
                      <i class="bi bi-clipboard"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div>
              <a class="mc-btn mc-btn-block <?= $p['btn_class'] ?>" href="<?= $p['url'] ?>" style="<?= $p['id'] === 'admin' ? 'background:#0b253a;color:#ffffff;border:none' : '' ?>">
                <span>Login to <?= $p['name'] ?></span>
                <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Copy Notification Toast Script -->
<script>
function copyCred(text, btn) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(function() {
      const originalHTML = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check2" style="color:#10b981"></i>';
      setTimeout(function() {
        btn.innerHTML = originalHTML;
      }, 1500);
    }).catch(function() {
      prompt("Copy to clipboard:", text);
    });
  } else {
    prompt("Copy to clipboard:", text);
  }
}
</script>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
