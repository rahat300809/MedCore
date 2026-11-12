<?php
require_once dirname(__DIR__) . '/portal/navs.php';
$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · Prescriptions';
$portalTitle = 'Prescriptions';
$portalSubtitle = 'Official e-prescriptions — view, print & download';
$portalNav = mc_patient_nav('prescriptions');
require dirname(__DIR__) . '/layout/portal_shell_start.php';
?>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,700&family=Source+Sans+3:wght@400;600;700;800&family=Great+Vibes&family=JetBrains+Mono:wght@500;700&display=swap">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/rx-pad.css?v=1">

<form method="GET" class="search-bar no-print" style="display:flex;gap:.55rem;flex-wrap:wrap;margin-bottom:1rem">
  <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search doctor, hospital, RX ID..."
         style="flex:1;min-width:220px;border:1px solid var(--line);border-radius:12px;padding:.75rem 1rem;font:inherit;background:#fff">
  <button class="mc-btn mc-btn-primary" type="submit">Search</button>
</form>

<?php if ($viewRx): ?>
  <?php
    $rx = $viewRx;
    $medicines = $viewMeds;
    $allergies = $viewAllergies ?? [];
    $labOrder = $viewLabOrder ?? null;
    $labTests = $viewLabTests ?? [];
    $showToolbar = true;
    $backUrl = APP_URL . '/patient/prescriptions.php';
    require dirname(__DIR__) . '/partials/rx_pad.php';
  ?>
  <div class="no-print" style="max-width:860px;margin:12px auto 1.5rem;text-align:center">
    <a class="mc-btn mc-btn-ghost mc-btn-sm" href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= (int)$viewRx['id'] ?>" target="_blank">
      Open full report page
    </a>
  </div>
<?php endif; ?>

<div class="rx-list-wrap no-print">
<?php if (empty($prescriptions)): ?>
  <div class="mc-panel" style="text-align:center;padding:2.5rem 1rem">
    <h2>কোনো প্রেসক্রিপশন নেই</h2>
    <p style="color:var(--muted)">ডাক্তার ভিজিটের পর এখানে দেখা যাবে।</p>
    <a href="<?= APP_URL ?>/book-doctor.php" class="mc-btn mc-btn-primary" style="margin-top:1rem">Book doctor</a>
  </div>
<?php else: ?>
  <div class="mc-list">
    <?php foreach ($prescriptions as $rxItem): ?>
      <a class="mc-list-item" href="<?= APP_URL ?>/patient/prescriptions.php?rx=<?= urlencode($rxItem['prescription_uid']) ?>" style="background:#fff">
        <div>
          <div class="mc-mono" style="color:var(--accent);font-size:.8rem"><?= htmlspecialchars($rxItem['prescription_uid']) ?></div>
          <strong>Dr. <?= htmlspecialchars($rxItem['doctor_name']) ?></strong>
          <div style="font-size:.86rem;color:var(--muted)"><?= htmlspecialchars($rxItem['hospital_name']) ?> · <?= htmlspecialchars(substr((string)$rxItem['created_at'], 0, 10)) ?></div>
        </div>
        <span class="mc-btn mc-btn-soft mc-btn-sm"><?= (int)$rxItem['medicine_count'] ?> medicines</span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/rx-pad.js?v=1"></script>
<?php require dirname(__DIR__) . '/layout/portal_shell_end.php'; ?>
