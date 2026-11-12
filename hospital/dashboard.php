<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

// Hospital profile
$stmt = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmt->execute([$hospitalId]);
$hospital = $stmt->fetch();

// Stats
$stmt = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM doctor_hospitals WHERE hospital_id = :h1 AND status = 'APPROVED') AS affiliated_doctors,
        (SELECT COUNT(*) FROM doctor_hospitals WHERE hospital_id = :h2 AND status = 'PENDING') AS pending_affiliations,
        (SELECT COUNT(*) FROM departments WHERE hospital_id = :h3 AND status = 'ACTIVE') AS active_departments,
        (SELECT COUNT(*) FROM consultations WHERE hospital_id = :h4 AND DATE(consultation_date) = CURDATE()) AS today_consultations,
        (SELECT COUNT(*) FROM consultations WHERE hospital_id = :h5) AS total_consultations,
        (SELECT COUNT(*) FROM access_sessions WHERE hospital_id = :h6 AND status = 'ACTIVE' AND expires_at > NOW()) AS active_sessions,
        (SELECT COUNT(*) FROM hospital_laboratories WHERE hospital_id = :h7 AND status = 'APPROVED') AS affiliated_labs,
        (SELECT COUNT(*) FROM appointments WHERE hospital_id = :h8 AND appointment_date = CURDATE() AND status != 'CANCELLED') AS today_appointments
");
$stmt->execute([':h1'=>$hospitalId,':h2'=>$hospitalId,':h3'=>$hospitalId,':h4'=>$hospitalId,':h5'=>$hospitalId,':h6'=>$hospitalId,':h7'=>$hospitalId,':h8'=>$hospitalId]);
$stats = $stmt->fetch();

// Affiliated doctors
$stmt = $db->prepare("
    SELECT d.*, u.email, u.last_login_at, dh.designation, dh.employment_type, dh.status AS affil_status,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON u.id = d.user_id
    LEFT JOIN departments dept ON dept.id = dh.department_id
    WHERE dh.hospital_id = ? AND dh.status = 'APPROVED'
    ORDER BY d.full_name
    LIMIT 10
");
$stmt->execute([$hospitalId]);
$doctors = $stmt->fetchAll();

// Pending affiliation requests
$stmt = $db->prepare("
    SELECT dh.*, d.full_name, d.specialization, d.medical_registration_id, u.email
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON u.id = d.user_id
    WHERE dh.hospital_id = ? AND dh.status = 'PENDING'
    ORDER BY dh.joined_at ASC
");
$stmt->execute([$hospitalId]);
$pendingAffiliations = $stmt->fetchAll();

// Recent consultations
$stmt = $db->prepare("
    SELECT c.*, d.full_name AS doctor_name, d.specialization,
           p.full_name AS patient_name, p.patient_uid
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN patients p ON c.patient_id = p.id
    WHERE c.hospital_id = ?
    ORDER BY c.consultation_date DESC
    LIMIT 5
");
$stmt->execute([$hospitalId]);
$recentConsultations = $stmt->fetchAll();

// Departments
$stmt = $db->prepare("
    SELECT dep.*, COUNT(dh.id) AS doctor_count
    FROM departments dep
    LEFT JOIN doctor_hospitals dh ON dh.department_id = dep.id AND dh.status = 'APPROVED'
    WHERE dep.hospital_id = ?
    GROUP BY dep.id
    ORDER BY dep.name
");
$stmt->execute([$hospitalId]);
$departments = $stmt->fetchAll();

// Handle affiliation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $dhId   = (int)($_POST['dh_id'] ?? 0);

    if (in_array($action, ['approve', 'reject']) && $dhId) {
        $newStatus = $action === 'approve' ? 'APPROVED' : 'REJECTED';
        $stmt = $db->prepare("
            UPDATE doctor_hospitals SET status = ? WHERE id = ? AND hospital_id = ?
        ");
        $stmt->execute([$newStatus, $dhId, $hospitalId]);
        header('Location: ' . APP_URL . '/hospital/dashboard.php#affiliations');
        exit;
    }
}

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalUser = $hospital['name'] ?? ($_SESSION['hospital_name'] ?? 'Hospital');
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · ' . ($hospital['city'] ?? '');
$portalTitle = 'Hospital overview';
$portalSubtitle = 'রিসেপশন, ডাক্তার রোস্টার ও ল্যাব নেটওয়ার্ক';
$portalNav = mc_hospital_nav('dashboard');
$portalTopAction = '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/hospital/appointments.php">Open reception</a>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Doctors</div><div class="v"><?= (int)($stats['affiliated_doctors'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Departments</div><div class="v"><?= (int)($stats['active_departments'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Today consults</div><div class="v"><?= (int)($stats['today_consultations'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Today appointments</div><div class="v"><?= (int)($stats['today_appointments'] ?? 0) ?></div></div>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Reception desk</h2></div>
  <p style="color:var(--muted);margin:0 0 1rem;font-size:.9rem">OPD বুকিং চেক-ইন ও সিরিয়াল কিউ ম্যানেজ করুন।</p>
  <a class="mc-btn mc-btn-primary" href="<?= APP_URL ?>/hospital/appointments.php">Open appointments &amp; queue</a>
</div>

<?php if (!empty($pendingAffiliations)): ?>
<div class="mc-panel" id="affiliations">
  <div class="mc-panel-h">
    <h2>Pending doctor affiliations</h2>
    <span class="lab-status"><?= count($pendingAffiliations) ?> pending</span>
  </div>
  <div class="mc-list">
    <?php foreach ($pendingAffiliations as $aff): ?>
      <div class="mc-list-item" style="background:#fff">
        <div>
          <strong><?= htmlspecialchars($aff['full_name']) ?></strong>
          <div style="font-size:.86rem;color:var(--muted)">
            <?= htmlspecialchars($aff['specialization'] ?? '') ?>
            · <?= htmlspecialchars($aff['medical_registration_id'] ?? '') ?>
          </div>
        </div>
        <div style="display:flex;gap:.4rem">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="dh_id" value="<?= (int)$aff['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button class="mc-btn mc-btn-primary mc-btn-sm" type="submit">Approve</button>
          </form>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="dh_id" value="<?= (int)$aff['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <button class="mc-btn mc-btn-secondary mc-btn-sm" type="submit">Reject</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Affiliated doctors</h2>
    <a href="<?= APP_URL ?>/hospital/doctors.php" style="font-weight:800;color:var(--accent);font-size:.88rem">Manage</a>
  </div>
  <?php if (empty($doctors)): ?>
    <p style="color:var(--muted);margin:0">No doctors affiliated yet.</p>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($doctors as $doc): ?>
        <div class="mc-list-item" style="background:#fff">
          <div>
            <strong><?= htmlspecialchars($doc['full_name']) ?></strong>
            <div style="font-size:.86rem;color:var(--muted)">
              <?= htmlspecialchars($doc['specialization'] ?? '') ?>
              <?php if (!empty($doc['department_name'])): ?> · <?= htmlspecialchars($doc['department_name']) ?><?php endif; ?>
            </div>
          </div>
          <span class="lab-status">Active</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Recent consultations</h2>
    <a href="<?= APP_URL ?>/hospital/consultations.php" style="font-weight:800;color:var(--accent);font-size:.88rem">View all</a>
  </div>
  <?php if (empty($recentConsultations)): ?>
    <p style="color:var(--muted);margin:0">No consultations yet.</p>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($recentConsultations as $cons): ?>
        <div class="mc-list-item" style="background:#fff">
          <div>
            <strong><?= htmlspecialchars($cons['patient_name']) ?></strong>
            <div style="font-size:.86rem;color:var(--muted)">
              <?= htmlspecialchars($cons['doctor_name']) ?>
              · <?= date('d M Y', strtotime($cons['consultation_date'])) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>