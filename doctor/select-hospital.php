<?php
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/middleware/AuthMiddleware.php';

initSession();

// If already authenticated as doctor, go to dashboard
if (isLoggedIn('DOCTOR') && isset($_SESSION['hospital_id'])) {
    header('Location: ' . APP_URL . '/doctor/dashboard.php');
    exit;
}

$db = getDB();
$error = $_GET['error'] ?? null;

// Load verified hospitals only
$stmt = $db->query("
    SELECT id, hospital_uid, name, type, city, district,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id = hospitals.id AND dh.status = 'APPROVED') AS doctor_count
    FROM hospitals
    WHERE verification_status = 'VERIFIED'
    ORDER BY name ASC
");
$hospitals = $stmt->fetchAll();

$searchQuery = trim($_GET['q'] ?? '');
if ($searchQuery) {
    $hospitals = array_filter($hospitals, function($h) use ($searchQuery) {
        return stripos($h['name'], $searchQuery) !== false
            || stripos($h['city'], $searchQuery) !== false;
    });
}

// Handle hospital selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hospital_id'])) {
    validateCsrf();
    $hospitalId = (int)$_POST['hospital_id'];

    $stmt = $db->prepare("SELECT * FROM hospitals WHERE id = ? AND verification_status = 'VERIFIED'");
    $stmt->execute([$hospitalId]);
    $hospital = $stmt->fetch();

    if ($hospital) {
        $_SESSION['selected_hospital_id']   = $hospital['id'];
        $_SESSION['selected_hospital_name'] = $hospital['name'];
        header('Location: ' . APP_URL . '/doctor/verify.php');
        exit;
    }
}

$csrf = generateCsrfToken();
$errorMessages = [
    'affiliation_revoked' => 'Your affiliation with the previously selected hospital has been revoked. Please select another hospital.',
    'not_verified' => 'Doctor verification failed. Please contact admin or try a different hospital.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Select Hospital — Doctor Login — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
</head>
<body style="background:var(--mc-bg);">

<div class="auth-flow-container" style="flex-direction:column;gap:var(--space-8);">
  <!-- Logo bar -->
  <div style="text-align:center;">
    <a href="<?= APP_URL ?>/" class="navbar-logo" style="justify-content:center;font-size:1.2rem;">
      <div class="logo-icon" style="width:36px;height:36px;font-size:18px;">M</div>
      <span>MedCore</span>
    </a>
  </div>

  <div class="doctor-verify-card">
    <!-- Step indicator -->
    <div class="step-indicator mb-8">
      <div class="step-dot active" aria-label="Step 1: Select Hospital">1</div>
      <div class="step-line"></div>
      <div class="step-dot" aria-label="Step 2: Verify Identity">2</div>
      <div class="step-line"></div>
      <div class="step-dot" aria-label="Step 3: OTP Verification">3</div>
    </div>

    <h1 style="font-size:1.4rem;font-weight:800;margin-bottom:var(--space-2);">
      <i class="bi bi-building-fill-check text-blue"></i>
      Select Your Hospital
    </h1>
    <p style="font-size:14px;color:var(--mc-text-secondary);margin-bottom:var(--space-6);">
      Choose the hospital you are currently affiliated with and intend to practice at. Your affiliation will be verified automatically.
    </p>

    <?php if ($error && isset($errorMessages[$error])): ?>
    <div class="alert alert-warning mb-4" role="alert">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?= htmlspecialchars($errorMessages[$error]) ?>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="search-bar mb-6" style="margin-bottom:var(--space-5);">
      <i class="bi bi-search" aria-hidden="true"></i>
      <input type="text" id="hospital-search" placeholder="Search hospitals by name or city..."
        value="<?= htmlspecialchars($searchQuery) ?>"
        aria-label="Search hospitals">
    </div>

    <form method="POST" id="hospital-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="hospital_id" id="selected-hospital-id">

      <div class="hospitals-list" id="hospitals-list" role="list">
        <?php foreach ($hospitals as $hosp): ?>
        <button type="button"
          class="hospital-card"
          data-id="<?= $hosp['id'] ?>"
          data-name="<?= htmlspecialchars($hosp['name']) ?>"
          onclick="selectHospital(this)"
          role="listitem"
          aria-label="Select <?= htmlspecialchars($hosp['name']) ?>">
          <div class="hospital-logo" aria-hidden="true">
            <i class="bi bi-hospital-fill" style="color:var(--mc-blue);"></i>
          </div>
          <div style="flex:1;text-align:left;">
            <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($hosp['name']) ?></div>
            <div style="font-size:12px;color:var(--mc-text-muted);">
              <?= ucfirst(strtolower($hosp['type'])) ?>
              · <?= htmlspecialchars($hosp['city'] ?? '') ?>
              <?= $hosp['district'] ? ', ' . htmlspecialchars($hosp['district']) : '' ?>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:12px;color:var(--mc-text-muted);"><?= $hosp['doctor_count'] ?> doctors</div>
            <span class="verified-badge"><i class="bi bi-patch-check-fill"></i> Verified</span>
          </div>
        </button>
        <?php endforeach; ?>

        <?php if (empty($hospitals)): ?>
        <div class="empty-state">
          <div class="empty-state-icon"><i class="bi bi-building"></i></div>
          <p>No verified hospitals found<?= $searchQuery ? ' for "' . htmlspecialchars($searchQuery) . '"' : '' ?>.</p>
        </div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg"
        id="continue-btn"
        style="margin-top:var(--space-6);"
        disabled>
        Continue to Verification <i class="bi bi-arrow-right"></i>
      </button>
    </form>

    <p style="text-align:center;margin-top:var(--space-5);font-size:13px;color:var(--mc-text-muted);">
      <a href="<?= APP_URL ?>/role-selection.php">← Back to Portal Selection</a>
    </p>
  </div>
</div>

<script>
function selectHospital(card) {
  document.querySelectorAll('.hospital-card').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');
  document.getElementById('selected-hospital-id').value = card.dataset.id;
  document.getElementById('continue-btn').disabled = false;
  document.getElementById('continue-btn').textContent = card.dataset.name + ' — Continue →';
}

// Live search filter
document.getElementById('hospital-search').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.hospital-card').forEach(card => {
    const text = card.textContent.toLowerCase();
    card.style.display = text.includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>
