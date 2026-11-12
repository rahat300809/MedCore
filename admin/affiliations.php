<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();

$hospFilter = (int)($_GET['hospital_id'] ?? 0);
$docFilter  = (int)($_GET['doctor_id'] ?? 0);
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter   = $_GET['type'] ?? 'all';

// Build query
$where = [];
$params = [];

if ($hospFilter > 0) {
    $where[] = "dh.hospital_id = ?";
    $params[] = $hospFilter;
}
if ($docFilter > 0) {
    $where[] = "dh.doctor_id = ?";
    $params[] = $docFilter;
}
if ($statusFilter !== 'all') {
    $where[] = "dh.status = ?";
    $params[] = $statusFilter;
}
if ($typeFilter !== 'all') {
    $where[] = "dh.employment_type = ?";
    $params[] = $typeFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT dh.*,
           d.full_name AS doctor_name, d.doctor_uid, d.medical_registration_id, d.specialization,
           d.verification_status AS doctor_global_status,
           h.name AS hospital_name, h.hospital_uid, h.city AS hospital_city,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN hospitals h ON dh.hospital_id = h.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    {$whereSql}
    ORDER BY d.full_name ASC, dh.joined_at DESC
");
$stmt->execute($params);
$affiliations = $stmt->fetchAll();

// Fetch filter options
$hospitalsList = $db->query("SELECT id, name FROM hospitals ORDER BY name ASC")->fetchAll();
$doctorsList   = $db->query("SELECT id, full_name, medical_registration_id FROM doctors ORDER BY full_name ASC")->fetchAll();

// Multi-hospital statistics
$multiHospitalDoctors = $db->query("
    SELECT d.id, d.full_name, d.medical_registration_id, d.specialization,
           COUNT(dh.id) as hospital_count,
           GROUP_CONCAT(CONCAT(h.name, ' (', dh.employment_type, ')') SEPARATOR ' · ') as hospital_details
    FROM doctors d
    JOIN doctor_hospitals dh ON dh.doctor_id = d.id AND dh.status = 'APPROVED'
    JOIN hospitals h ON dh.hospital_id = h.id
    GROUP BY d.id
    HAVING hospital_count > 1
")->fetchAll();

$csrf = generateCsrfToken();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Cross-Hospital Affiliation Matrix';
$portalSubtitle = 'Multi-facility doctor credential sharing and employment visibility';
$portalNav = mc_admin_nav('affiliations');
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<section class="mc-panel" style="background:linear-gradient(135deg,#1e3a8a,#0f172a);color:#fff;border-color:#1e40af;margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
    <div>
      <span class="lab-status info" style="background:rgba(255,255,255,.16);color:#fff;border-color:rgba(255,255,255,.25);margin-bottom:.5rem;">
        <i class="bi bi-diagram-3-fill"></i> MANY-TO-MANY ARCHITECTURE
      </span>
      <h2 style="font-size:1.2rem;margin:0 0 .35rem;color:#fff;">Doctors Practicing Across Multiple Network Hospitals</h2>
      <p style="font-size:.86rem;color:#bfdbfe;margin:0;max-width:680px;">
        Once a doctor is verified centrally by the Main Admin, they can be affiliated with any number of hospitals concurrently (e.g. Full-Time at one center and Visiting at another).
      </p>
    </div>
    <div style="text-align:center;background:rgba(255,255,255,.12);border-radius:12px;padding:.75rem 1.25rem;">
      <div style="font-family:var(--font-d);font-size:1.75rem;font-weight:700;line-height:1;"><?= count($multiHospitalDoctors) ?></div>
      <div style="font-size:.7rem;color:#bfdbfe;text-transform:uppercase;margin-top:.25rem;letter-spacing:.05em;">Multi-Facility Doctors</div>
    </div>
  </div>

  <?php if (!empty($multiHospitalDoctors)): ?>
    <div style="margin-top:1rem;padding-top:.85rem;border-top:1px solid rgba(255,255,255,.16);display:grid;gap:.45rem;">
      <?php foreach ($multiHospitalDoctors as $mDoc): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;background:rgba(0,0,0,.22);padding:.55rem .85rem;border-radius:8px;font-size:.84rem;">
          <div>
            <strong><?= htmlspecialchars($mDoc['full_name']) ?></strong>
            <span style="color:#93c5fd;font-size:.78rem;margin-left:.35rem;">(<?= htmlspecialchars($mDoc['medical_registration_id']) ?> · <?= htmlspecialchars($mDoc['specialization'] ?? '') ?>)</span>
          </div>
          <div style="color:#6ee7b7;font-size:.78rem;"><i class="bi bi-geo-fill"></i> <?= htmlspecialchars($mDoc['hospital_details']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="mc-panel" style="margin-bottom:1rem;">
  <form method="GET">
    <div class="mc-form-grid-4">
      <label class="mc-field" style="margin-top:0;">
        <span>Filter by Hospital</span>
        <select name="hospital_id">
          <option value="0">All Hospitals</option>
          <?php foreach ($hospitalsList as $hl): ?>
            <option value="<?= $hl['id'] ?>" <?= $hospFilter == $hl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($hl['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Filter by Doctor</span>
        <select name="doctor_id">
          <option value="0">All Doctors</option>
          <?php foreach ($doctorsList as $dl): ?>
            <option value="<?= $dl['id'] ?>" <?= $docFilter == $dl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dl['full_name']) ?> (<?= htmlspecialchars($dl['medical_registration_id']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Employment Type</span>
        <select name="type">
          <option value="all">All Types</option>
          <option value="FULL_TIME" <?= $typeFilter === 'FULL_TIME' ? 'selected' : '' ?>>Full-Time</option>
          <option value="PART_TIME" <?= $typeFilter === 'PART_TIME' ? 'selected' : '' ?>>Part-Time</option>
          <option value="VISITING" <?= $typeFilter === 'VISITING' ? 'selected' : '' ?>>Visiting</option>
          <option value="HONORARY" <?= $typeFilter === 'HONORARY' ? 'selected' : '' ?>>Honorary</option>
        </select>
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Affiliation Status</span>
        <select name="status">
          <option value="all">All Statuses</option>
          <option value="APPROVED" <?= $statusFilter === 'APPROVED' ? 'selected' : '' ?>>Active / Approved</option>
          <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Pending</option>
          <option value="REMOVED" <?= $statusFilter === 'REMOVED' ? 'selected' : '' ?>>Removed</option>
        </select>
      </label>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:.75rem;">
      <a href="<?= APP_URL ?>/admin/affiliations.php" class="mc-btn mc-btn-secondary mc-btn-sm">Reset</a>
      <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm">Apply Filters</button>
    </div>
  </form>
</section>

<section class="mc-panel">
  <div class="mc-panel-h">
    <h2>All Hospital-Doctor Affiliation Links (<?= count($affiliations) ?>)</h2>
  </div>
  <div style="overflow-x:auto;">
    <table class="lab-table">
      <thead>
        <tr>
          <th>Doctor</th>
          <th>BMDC Reg ID</th>
          <th>Hospital Center</th>
          <th>Department</th>
          <th>Designation</th>
          <th>Employment</th>
          <th>Status</th>
          <th>Joined</th>
          <th style="text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($affiliations)): ?>
          <tr>
            <td colspan="9" style="text-align:center;padding:2rem;color:var(--muted);">No affiliation links found matching the selected filters.</td>
          </tr>
        <?php endif; ?>

        <?php foreach ($affiliations as $aff): ?>
          <tr>
            <td>
              <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $aff['doctor_id'] ?>" style="font-weight:700;color:var(--ink);">
                <?= htmlspecialchars($aff['doctor_name']) ?>
              </a>
              <div style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($aff['doctor_uid']) ?> · <?= htmlspecialchars($aff['specialization'] ?? '') ?></div>
            </td>
            <td class="mc-mono" style="color:#1e40af;"><?= htmlspecialchars($aff['medical_registration_id']) ?></td>
            <td>
              <strong><?= htmlspecialchars($aff['hospital_name']) ?></strong>
              <div style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($aff['hospital_city'] ?? '') ?></div>
            </td>
            <td><span class="lab-status info"><?= htmlspecialchars($aff['department_name'] ?? 'General') ?></span></td>
            <td><?= htmlspecialchars($aff['designation'] ?? 'Consultant') ?></td>
            <td><span class="lab-status"><?= htmlspecialchars($aff['employment_type']) ?></span></td>
            <td>
              <?php if ($aff['status'] === 'APPROVED'): ?>
                <span class="lab-status ok"><i class="bi bi-check-circle-fill"></i> ACTIVE</span>
              <?php elseif ($aff['status'] === 'PENDING'): ?>
                <span class="lab-status warn">PENDING</span>
              <?php elseif ($aff['status'] === 'REMOVED'): ?>
                <span class="lab-status">REMOVED</span>
              <?php else: ?>
                <span class="lab-status danger"><?= htmlspecialchars($aff['status']) ?></span>
              <?php endif; ?>
            </td>
            <td style="font-size:.78rem;color:var(--muted);"><?= $aff['joined_at'] ? date('M d, Y', strtotime($aff['joined_at'])) : '-' ?></td>
            <td style="text-align:right;">
              <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $aff['doctor_id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Dossier</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
