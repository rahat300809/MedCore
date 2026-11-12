<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(p.full_name LIKE ? OR p.patient_uid LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like];
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT p.*, u.email, u.phone, u.status AS user_status,
           (SELECT COUNT(*) FROM consultations c WHERE c.patient_id = p.id) AS total_consultations,
           (SELECT COUNT(*) FROM prescriptions rx WHERE rx.patient_id = p.id) AS total_prescriptions
    FROM patients p
    JOIN users u ON p.user_id = u.id
    {$whereSql}
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$patients = $stmt->fetchAll();

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'National Patient Directory';
$portalSubtitle = 'Central health record registry and patient summary metrics';
$portalNav = mc_admin_nav('patients');
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<section class="mc-panel" style="margin-bottom:1rem;">
  <form method="GET" class="lab-token-form" style="max-width:520px;">
    <input type="text" name="q" placeholder="Search by Patient UID, Name, Email..." value="<?= htmlspecialchars($search) ?>">
    <?php if ($search): ?>
      <a href="<?= APP_URL ?>/admin/patients.php" class="mc-btn mc-btn-secondary mc-btn-sm"><i class="bi bi-x-lg"></i></a>
    <?php endif; ?>
    <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm"><i class="bi bi-search"></i> Search</button>
  </form>
</section>

<section class="mc-panel">
  <div style="overflow-x:auto;">
    <table class="lab-table">
      <thead>
        <tr>
          <th>Patient</th>
          <th>Patient UID</th>
          <th>Gender / Age</th>
          <th>Blood Group</th>
          <th>Consultations</th>
          <th>Prescriptions</th>
          <th>Registered</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($patients)): ?>
          <tr>
            <td colspan="7" style="text-align:center;padding:2rem;color:var(--muted);">No patient records found.</td>
          </tr>
        <?php endif; ?>
        <?php foreach ($patients as $p): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($p['full_name']) ?></strong>
              <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($p['email']) ?> · <?= htmlspecialchars($p['phone'] ?? '') ?></div>
            </td>
            <td class="mc-mono" style="color:#1e40af;"><?= htmlspecialchars($p['patient_uid']) ?></td>
            <td><?= ucfirst(strtolower($p['gender'])) ?> · <?= date_diff(date_create($p['date_of_birth']), date_create('today'))->y ?> yrs</td>
            <td><span class="lab-status danger"><?= htmlspecialchars($p['blood_group']) ?></span></td>
            <td><?= $p['total_consultations'] ?></td>
            <td><?= $p['total_prescriptions'] ?></td>
            <td style="font-size:.78rem;color:var(--muted);"><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
