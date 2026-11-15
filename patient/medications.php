<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';
require_once __DIR__ . '/../app/services/SidekickService.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmt = $db->prepare("
    SELECT pm.*, m.generic_name, m.brand_name, m.strength, m.dosage_form, m.drug_class, m.description,
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

// Groq / Sidekick: why each medicine was prescribed
$aiMap = [];
if (!empty($medications)) {
    $explain = SidekickService::explainMedicines($medications);
    foreach (($explain['explanations'] ?? []) as $ex) {
        $key = strtolower(trim($ex['name'] ?? ''));
        if ($key !== '') {
            $aiMap[$key] = $ex;
        }
    }
}

$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · Medicines';
$portalTitle = 'My medicines';
$portalSubtitle = 'কোন ওষুধ কেন — Sidekick AI ব্যাখ্যা সহ';
$portalNav = mc_patient_nav('medications');
$portalTopAction = '<span class="mc-btn mc-btn-soft mc-btn-sm">' . $activeCount . ' active</span>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';

$iconMap = [
    'heart' => 'bi-heart-pulse',
    'sugar' => 'bi-droplet-half',
    'infection' => 'bi-shield-plus',
    'pain' => 'bi-bandaid',
    'stomach' => 'bi-emoji-smile',
    'lung' => 'bi-lungs',
    'other' => 'bi-capsule',
];
?>

<div class="mc-med-intro">
  <div>
    <h2 style="margin:0;font-size:1.15rem">ওষুধের উদ্দেশ্য বুঝুন</h2>
    <p style="margin:.35rem 0 0;color:var(--muted);font-size:.92rem">
      ডাক্তার কোন ওষুধ কোন কাজের জন্য দিয়েছেন — Sidekick সহজ ভাষায় ব্যাখ্যা করে।
    </p>
  </div>
</div>

<?php if (empty($medications)): ?>
  <div class="mc-panel" style="text-align:center;padding:2.5rem 1rem">
    <h2>কোনো ওষুধ নেই</h2>
    <p style="color:var(--muted)">প্রেসক্রিপশনের পর এখানে দেখা যাবে।</p>
  </div>
<?php else: ?>
  <div class="mc-med-list">
    <?php foreach ($medications as $m):
      $displayName = $m['brand_name'] ?: $m['generic_name'];
      $lookup = strtolower(trim($displayName));
      $ex = $aiMap[$lookup] ?? null;
      if (!$ex) {
          foreach ($aiMap as $k => $v) {
              if (str_contains($lookup, $k) || str_contains($k, $lookup)) {
                  $ex = $v;
                  break;
              }
          }
      }
      $hint = $ex['icon_hint'] ?? 'other';
      $ico = $iconMap[$hint] ?? 'bi-capsule';
    ?>
      <article class="mc-med-card">
        <div class="mc-med-card-top">
          <span class="mc-med-ico"><i class="bi <?= $ico ?>"></i></span>
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start">
              <h3><?= htmlspecialchars($displayName) ?></h3>
              <span class="lab-status <?= $m['status'] === 'ACTIVE' ? 'ok' : 'info' ?>"><?= htmlspecialchars($m['status']) ?></span>
            </div>
            <?php if ($m['brand_name'] && $m['generic_name']): ?>
              <p class="mc-med-generic"><?= htmlspecialchars($m['generic_name']) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="mc-med-dose">
          <strong><?= htmlspecialchars($m['frequency'] ?? '1+0+1') ?></strong>
          <span><?= htmlspecialchars($m['dosage'] ?? '1 Tab') ?> · <?= htmlspecialchars(trim(($m['strength'] ?? '') . ' ' . ($m['dosage_form'] ?? ''))) ?></span>
        </div>

        <?php if ($ex): ?>
          <div class="mc-med-why">
            <div class="mc-med-why-label">কেন এই ওষুধ?</div>
            <p class="mc-med-purpose"><?= htmlspecialchars($ex['purpose_bn'] ?? '') ?></p>
            <?php if (!empty($ex['purpose_en'])): ?>
              <p class="mc-med-purpose-en"><?= htmlspecialchars($ex['purpose_en']) ?></p>
            <?php endif; ?>
            <?php if (!empty($ex['how_to_take_bn'])): ?>
              <p class="mc-med-how"><i class="bi bi-clock"></i> <?= htmlspecialchars($ex['how_to_take_bn']) ?></p>
            <?php endif; ?>
            <?php if (!empty($ex['caution_bn'])): ?>
              <p class="mc-med-caution"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($ex['caution_bn']) ?></p>
            <?php endif; ?>
          </div>
        <?php elseif (!empty($m['description'])): ?>
          <div class="mc-med-why">
            <div class="mc-med-why-label">বিবরণ</div>
            <p class="mc-med-purpose"><?= htmlspecialchars($m['description']) ?></p>
          </div>
        <?php endif; ?>

        <p class="mc-med-meta">
          <?= $m['doctor_name'] ? 'Dr. ' . htmlspecialchars($m['doctor_name']) : '—' ?>
          · <?= $m['started_at'] ? date('d M Y', strtotime($m['started_at'])) : '—' ?>
          <?php if (!empty($m['prescription_uid'])): ?>
            · <span class="mc-mono"><?= htmlspecialchars($m['prescription_uid']) ?></span>
          <?php endif; ?>
        </p>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
