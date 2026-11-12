<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmt = $db->prepare("
    SELECT pa.*, a.name AS allergy_name, a.type AS allergy_type
    FROM patient_allergies pa
    JOIN allergies a ON a.id = pa.allergy_id
    WHERE pa.patient_id = ?
    ORDER BY FIELD(pa.severity, 'LIFE_THREATENING', 'SEVERE', 'MODERATE', 'MILD')
");
$stmt->execute([$patientId]);
$allergies = $stmt->fetchAll();

$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · Allergies';
$portalTitle = 'Allergies';
$portalSubtitle = 'ডাক্তার ভিউতে অটো-হাইলাইট হয়';
$portalNav = mc_patient_nav('profile');
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="mc-alert mc-alert-info">এখানকার অ্যালার্জি অনুমোদিত ডাক্তার রেকর্ড খুললে স্বয়ংক্রিয়ভাবে দেখাবে।</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Known allergies (<?= count($allergies) ?>)</h2></div>
  <?php if (empty($allergies)): ?>
    <p style="margin:0;color:var(--muted)">কোনো documented allergy নেই।</p>
  <?php else: ?>
    <div style="overflow:auto">
      <table class="lab-table">
        <thead>
          <tr><th>Allergen</th><th>Type</th><th>Severity</th><th>Reaction</th><th>Verified</th></tr>
        </thead>
        <tbody>
          <?php foreach ($allergies as $alg): ?>
            <tr style="<?= in_array($alg['severity'], ['SEVERE', 'LIFE_THREATENING'], true) ? 'background:#fef2f2' : '' ?>">
              <td><strong><?= htmlspecialchars($alg['allergy_name']) ?></strong></td>
              <td><?= htmlspecialchars($alg['allergy_type']) ?></td>
              <td><span class="lab-status"><?= htmlspecialchars($alg['severity']) ?></span></td>
              <td><?= htmlspecialchars($alg['reaction'] ?? 'General reaction') ?></td>
              <td><?= !empty($alg['verified']) ? 'Verified' : 'Self-reported' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
