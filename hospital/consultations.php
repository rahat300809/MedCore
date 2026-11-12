<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$search = trim($_GET['search'] ?? '');

$query = "
    SELECT c.*, d.full_name AS doctor_name, d.specialization,
           p.full_name AS patient_name, p.patient_uid,
           dept.name AS department_name, rx.prescription_uid
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN patients p ON c.patient_id = p.id
    LEFT JOIN departments dept ON c.department_id = dept.id
    LEFT JOIN prescriptions rx ON rx.consultation_id = c.id
    WHERE c.hospital_id = ?
";
$params = [$hospitalId];

if ($search) {
    $query .= " AND (c.consultation_uid LIKE ? OR d.full_name LIKE ? OR p.full_name LIKE ? OR p.patient_uid LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY c.consultation_date DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$consultations = $stmt->fetchAll();

$doctorLabel = static function (?string $name): string {
    if (!$name) return '—';
    $clean = preg_replace('/^\s*Dr\.?\s+/i', '', trim($name)) ?: $name;
    return 'Dr. ' . $clean;
};

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalUser = $hospital['name'] ?? 'Hospital';
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Consultations';
$portalTitle = 'Consultations';
$portalSubtitle = 'হাসপাতালের পরামর্শ রেকর্ড';
$portalNav = mc_hospital_nav('dashboard');
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="mc-panel">
  <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search doctor / patient / UID" style="flex:1;min-width:200px;padding:.7rem .85rem;border:1px solid var(--line);border-radius:10px;font:inherit">
    <button class="mc-btn mc-btn-primary" type="submit">Search</button>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Records (<?= count($consultations) ?>)</h2></div>
  <?php if (empty($consultations)): ?>
    <p style="margin:0;color:var(--muted)">কোনো consultation নেই।</p>
  <?php else: ?>
    <div style="overflow:auto">
      <table class="lab-table">
        <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Dept</th><th>Rx</th></tr></thead>
        <tbody>
          <?php foreach ($consultations as $c): ?>
            <tr>
              <td><?= date('d M Y', strtotime($c['consultation_date'])) ?></td>
              <td><strong><?= htmlspecialchars($c['patient_name']) ?></strong><div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($c['patient_uid']) ?></div></td>
              <td><?= htmlspecialchars($doctorLabel($c['doctor_name'] ?? null)) ?><div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($c['specialization'] ?? '') ?></div></td>
              <td><?= htmlspecialchars($c['department_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($c['prescription_uid'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>