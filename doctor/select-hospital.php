<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/media.php';

initSession();

if (isLoggedIn('DOCTOR') && isset($_SESSION['hospital_id']) && !isset($_GET['switch'])) {
    header('Location: ' . APP_URL . '/doctor/dashboard.php');
    exit;
}
if (isLoggedIn('DOCTOR') && isset($_SESSION['doctor_id'])) {
    $_SESSION['pending_doctor_id'] = $_SESSION['doctor_id'];
    $_SESSION['pending_doctor_name'] = $_SESSION['name'] ?? '';
}

$db = getDB();
$error = $_GET['error'] ?? null;
$stmt = $db->query("
    SELECT id, hospital_uid, name, type, city, district,
           (SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id = hospitals.id AND dh.status = 'APPROVED') AS doctor_count
    FROM hospitals WHERE verification_status = 'VERIFIED' ORDER BY name ASC
");
$hospitals = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hospital_id'])) {
    validateCsrf();
    $hospitalId = (int)$_POST['hospital_id'];
    $stmt = $db->prepare("SELECT * FROM hospitals WHERE id = ? AND verification_status = 'VERIFIED'");
    $stmt->execute([$hospitalId]);
    $hospital = $stmt->fetch();
    if ($hospital) {
        $_SESSION['selected_hospital_id'] = $hospital['id'];
        $_SESSION['selected_hospital_name'] = $hospital['name'];
        header('Location: ' . APP_URL . '/doctor/verify.php');
        exit;
    }
}

$csrf = generateCsrfToken();
$errorMessages = [
    'affiliation_revoked' => 'Affiliation revoked. Select another hospital.',
    'not_verified' => 'Doctor verification failed. Try another hospital.',
];

$loginTitle = 'Doctor login';
$loginTitleBn = 'হাসপাতাল বেছে নিন';
$loginImage = MC_IMG_DOCTOR;
$demoEmail = 'Select ABC General Hospital';
$demoPass = 'then continue';
$demoNote = 'Demo doctor: Dr. Farhana Rahman';
$backUrl = APP_URL . '/role-selection.php';
$registerUrl = '';

ob_start();
?>
<?php if ($error && isset($errorMessages[$error])): ?>
  <div class="mc-error"><?= htmlspecialchars($errorMessages[$error]) ?></div>
<?php endif; ?>

<p style="margin:1rem 0 .55rem;font-size:.88rem;color:var(--muted)">Step 1 of 3 · Choose hospital</p>
<button type="button" class="mc-btn mc-btn-secondary mc-btn-sm mc-btn-block" onclick="selectDemoHospital()">Select ABC General (demo)</button>

<input type="text" id="hospital-search" placeholder="Search hospitals..." style="width:100%;margin-top:.75rem;border:1px solid var(--line);border-radius:12px;padding:.75rem .95rem;font:inherit;background:#f9fafb">

<form method="POST" id="hospital-form" style="margin-top:.75rem">
  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
  <input type="hidden" name="hospital_id" id="selected-hospital-id">
  <div id="hospitals-list" style="max-height:280px;overflow:auto;display:grid;gap:.45rem">
    <?php foreach ($hospitals as $hosp): ?>
      <button type="button" class="hospital-card mc-btn mc-btn-ghost" style="justify-content:flex-start;text-align:left;height:auto;padding:.8rem"
        data-id="<?= (int)$hosp['id'] ?>" data-name="<?= htmlspecialchars($hosp['name']) ?>" onclick="selectHospital(this)">
        <span style="display:block;width:100%">
          <strong style="display:block"><?= htmlspecialchars($hosp['name']) ?></strong>
          <span style="font-size:.8rem;color:var(--muted);font-weight:600"><?= htmlspecialchars($hosp['type']) ?> · <?= htmlspecialchars($hosp['city'] ?? '') ?> · <?= (int)$hosp['doctor_count'] ?> doctors</span>
        </span>
      </button>
    <?php endforeach; ?>
  </div>
  <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg mc-btn-block" style="margin-top:1rem" id="continue-btn" disabled>Continue</button>
</form>
<style>.hospital-card.selected{border-color:var(--accent)!important;background:var(--accent-soft)!important;color:var(--accent)!important}</style>
<script>
function selectDemoHospital(){const c=document.querySelector('.hospital-card[data-id="1"]')||document.querySelector('.hospital-card');if(c)selectHospital(c)}
function selectHospital(card){document.querySelectorAll('.hospital-card').forEach(c=>c.classList.remove('selected'));card.classList.add('selected');document.getElementById('selected-hospital-id').value=card.dataset.id;const b=document.getElementById('continue-btn');b.disabled=false;b.textContent='Continue with '+card.dataset.name}
document.getElementById('hospital-search').addEventListener('input',function(){const q=this.value.toLowerCase();document.querySelectorAll('.hospital-card').forEach(card=>{card.style.display=card.textContent.toLowerCase().includes(q)?'':'none'})})
</script>
<?php
$loginFormHtml = ob_get_clean();
require __DIR__ . '/../app/views/auth/login_shell.php';
