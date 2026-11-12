<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmt = $db->prepare("
    SELECT mh.*, d.full_name AS doctor_name
    FROM medical_histories mh
    LEFT JOIN doctors d ON mh.created_by = d.id
    WHERE mh.patient_id = ?
    ORDER BY FIELD(mh.status, 'CHRONIC', 'ACTIVE', 'RESOLVED', 'UNKNOWN'), mh.diagnosed_date DESC
");
$stmt->execute([$patientId]);
$conditions = $stmt->fetchAll();

$doctorLabel = static function (?string $name): string {
    if (!$name) return 'Hospital record';
    $clean = preg_replace('/^\s*Dr\.?\s+/i', '', trim($name)) ?: $name;
    return 'Dr. ' . $clean;
};

$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $_SESSION['name'] ?? 'Patient';
$portalMeta = ($_SESSION['patient_uid'] ?? '') . ' · History';
$portalTitle = 'Medical history';
$portalSubtitle = 'দীর্ঘমেয়াদি ও অতীত শর্তসমূহ';
$portalNav = mc_patient_nav('timeline');
$portalTopAction = '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/patient/timeline.php">Timeline</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Conditions (<?= count($conditions) ?>)</h2></div>
  <?php if (empty($conditions)): ?>
    <p style="margin:0;color:var(--muted)">কোনো documented condition নেই।</p>
  <?php else: ?>
    <div style="overflow:auto">
      <table class="lab-table">
        <thead>
          <tr><th>Condition</th><th>ICD</th><th>Status</th><th>Diagnosed</th><th>Doctor</th><th>Notes</th></tr>
        </thead>
        <tbody>
          <?php foreach ($conditions as $c): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($c['condition_name']) ?></strong>
                <?php if (!empty($c['description'])): ?>
                  <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($c['description']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($c['icd_code'] ?? '—') ?></td>
              <td><span class="lab-status"><?= htmlspecialchars($c['status']) ?></span></td>
              <td><?= $c['diagnosed_date'] ? date('d M Y', strtotime($c['diagnosed_date'])) : '—' ?></td>
              <td><?= htmlspecialchars($doctorLabel($c['doctor_name'] ?? null)) ?></td>
              <td><?= htmlspecialchars($c['notes'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
