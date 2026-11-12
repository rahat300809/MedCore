<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmt = $db->prepare("
    SELECT pm.*, m.generic_name, m.brand_name, m.strength, m.dosage_form,
           d.full_name AS doctor_name, rx.prescription_uid
    FROM patient_medications pm
    JOIN medications m ON pm.medication_id = m.id
    LEFT JOIN doctors d ON pm.prescribed_by = d.id
    LEFT JOIN prescriptions rx ON pm.prescription_id = rx.id
    WHERE pm.patient_id = ?
    ORDER BY FIELD(pm.status, 'ACTIVE', 'COMPLETED', 'DISCONTINUED'), pm.started_at DESC
");
$stmt->execute([$patientId]);
$medications = $stmt->fetchAll();

$activeCount = 0;
foreach ($medications as $m) {
    if ($m['status'] === 'ACTIVE') $activeCount++;
}

$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · Medicines';
$portalTitle = 'Medicine schedule';
$portalSubtitle = 'কখন কোন ওষুধ খাবেন';
$portalNav = mc_patient_nav('medications');
$portalTopAction = '<span class="mc-btn mc-btn-soft mc-btn-sm">' . $activeCount . ' active</span>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if (empty($medications)): ?>
  <div class="mc-panel" style="text-align:center;padding:2.5rem 1rem">
    <h2>কোনো ওষুধ নেই</h2>
    <p style="color:var(--muted)">প্রেসক্রিপশনের পর এখানে দেখা যাবে।</p>
  </div>
<?php else: ?>
  <div class="mc-action-grid">
    <?php foreach ($medications as $m): ?>
      <div class="mc-panel" style="margin:0">
        <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start">
          <h3 style="font-size:1.05rem"><?= htmlspecialchars($m['brand_name'] ?: $m['generic_name']) ?></h3>
          <span class="mc-btn mc-btn-sm <?= $m['status'] === 'ACTIVE' ? 'mc-btn-soft' : 'mc-btn-ghost' ?>"><?= htmlspecialchars($m['status']) ?></span>
        </div>
        <?php if ($m['brand_name'] && $m['generic_name']): ?>
          <p style="margin:.35rem 0 0;color:var(--muted);font-size:.88rem"><?= htmlspecialchars($m['generic_name']) ?></p>
        <?php endif; ?>
        <p style="margin:.85rem 0 0;font-weight:800;font-size:1.05rem"><?= htmlspecialchars($m['frequency'] ?? '1+0+1') ?></p>
        <p style="margin:.25rem 0 0;color:var(--muted);font-size:.88rem">
          <?= htmlspecialchars($m['dosage'] ?? '1 Tab') ?> · <?= htmlspecialchars($m['strength'] ?? '') ?> <?= htmlspecialchars($m['dosage_form'] ?? '') ?>
        </p>
        <p style="margin:.75rem 0 0;font-size:.8rem;color:var(--muted)">
          <?= $m['doctor_name'] ? 'Dr. ' . htmlspecialchars($m['doctor_name']) : '—' ?>
          · <?= $m['started_at'] ? date('d M Y', strtotime($m['started_at'])) : '—' ?>
        </p>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
