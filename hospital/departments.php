<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireAuth('HOSPITAL_ADMIN');

$db = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dept'])) {
    validateCsrf();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') {
        $error = 'Department name is required.';
    } else {
        $db->prepare("INSERT INTO departments (hospital_id, name, description, status) VALUES (?, ?, ?, 'ACTIVE')")
            ->execute([$hospitalId, $name, $desc]);
        $success = "Department '{$name}' created.";
    }
}

$stmt = $db->prepare("
    SELECT dep.*, COUNT(dh.id) AS doctor_count
    FROM departments dep
    LEFT JOIN doctor_hospitals dh ON dh.department_id = dep.id AND dh.status = 'APPROVED'
    WHERE dep.hospital_id = ?
    GROUP BY dep.id
    ORDER BY dep.name ASC
");
$stmt->execute([$hospitalId]);
$departments = $stmt->fetchAll();

$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalUser = $hospital['name'] ?? 'Hospital';
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Departments';
$portalTitle = 'Departments';
$portalSubtitle = 'ক্লিনিক্যাল বিভাগ ও ডাক্তার সংখ্যা';
$portalNav = mc_hospital_nav('departments');
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($success): ?><div class="mc-alert mc-alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mc-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Add department</h2></div>
  <form method="POST" style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:end">
    <?= csrfField() ?>
    <input type="hidden" name="add_dept" value="1">
    <label class="mc-field" style="margin:0"><span>Name</span><input type="text" name="name" required placeholder="Cardiology"></label>
    <label class="mc-field" style="margin:0"><span>Description</span><input type="text" name="description" placeholder="OPD / inpatient"></label>
    <button type="submit" class="mc-btn mc-btn-primary">Create</button>
  </form>
</div>

<div class="mc-action-grid">
  <?php foreach ($departments as $d): ?>
    <div class="mc-panel" style="margin:0">
      <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start">
        <h3 style="margin:0;font-size:1.05rem"><?= htmlspecialchars($d['name']) ?></h3>
        <span class="lab-status"><?= htmlspecialchars($d['status']) ?></span>
      </div>
      <p style="margin:.55rem 0 0;color:var(--muted);font-size:.88rem"><?= htmlspecialchars($d['description'] ?? 'Clinical services') ?></p>
      <p style="margin:.85rem 0 0;font-weight:800"><?= (int)$d['doctor_count'] ?> doctors</p>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
