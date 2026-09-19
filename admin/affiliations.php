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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cross-Hospital Affiliation Matrix — MedCore Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-dark: #0F172A;
      --admin-primary: #1E40AF;
    }
    .badge-verified { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
    .badge-pending { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
    .badge-suspended { background: #F3F4F6; color: #4B5563; border: 1px solid #E5E7EB; }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar" style="background: var(--admin-dark);">
    <div class="sidebar-brand" style="border-color: rgba(255,255,255,0.1);">
      <div class="brand-icon" style="background: linear-gradient(135deg, #3B82F6, #1D4ED8);">M</div>
      <div>
        <div class="brand-name" style="color:white;">MedCore</div>
        <div class="brand-sub" style="color: #94A3B8; font-size:11px;">Central Administration</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title" style="color: #64748B;">Central Oversight</div>
      <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i> System Dashboard
      </a>
      <a href="<?= APP_URL ?>/admin/doctors.php" class="nav-item">
        <i class="bi bi-person-badge-fill"></i> Doctor Verification
      </a>
      <a href="<?= APP_URL ?>/admin/hospitals.php" class="nav-item">
        <i class="bi bi-building-fill-check"></i> Hospital Network
      </a>
      <a href="<?= APP_URL ?>/admin/affiliations.php" class="nav-item active">
        <i class="bi bi-diagram-3-fill"></i> Hospital Affiliations
      </a>

      <div class="nav-section-title" style="color: #64748B; margin-top: 16px;">National Registry</div>
      <a href="<?= APP_URL ?>/admin/patients.php" class="nav-item">
        <i class="bi bi-people-fill"></i> Patient Registry
      </a>
      <a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-item">
        <i class="bi bi-shield-check"></i> Audit & Compliance
      </a>
    </nav>

    <div class="sidebar-footer" style="border-color: rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
        <div class="user-avatar" style="background: #3B82F6; color:white; width:36px; height:36px; font-size:13px;">
          SA
        </div>
        <div style="overflow:hidden;">
          <div style="font-weight:700; font-size:13px; color:white;"><?= htmlspecialchars($_SESSION['name'] ?? 'System Admin') ?></div>
          <div style="font-size:11px; color:#94A3B8;"><?= htmlspecialchars($_SESSION['email'] ?? 'admin@medcore.local') ?></div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-secondary btn-sm w-100" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: white;">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="portal-content">
    <header class="top-nav">
      <div class="top-nav-left">
        <button class="mobile-sidebar-toggle btn btn-sm btn-ghost d-md-none" onclick="toggleSidebar()">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">MULTI-HOSPITAL ECOSYSTEM</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Cross-Hospital Doctor Affiliation Matrix</h1>
        </div>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <!-- MULTI-HOSPITAL HIGHLIGHT CAROUSEL/BANNER -->
      <div style="background: linear-gradient(135deg, #1E40AF, #1E293B); color: white; border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
          <div>
            <span class="badge" style="background: rgba(255,255,255,0.2); color: white; font-size: 11px; margin-bottom: 8px;">
              <i class="bi bi-diagram-3-fill"></i> MANY-TO-MANY ARCHITECTURE
            </span>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: white; margin: 0 0 6px 0;">
              Doctors Practicing Across Multiple Network Hospitals
            </h2>
            <p style="font-size: 13px; color: #BFDBFE; margin: 0; max-width: 680px;">
              Once a doctor is verified centrally by the Main Admin, they can be affiliated with any number of hospitals concurrently (e.g. Full-Time at ABC Hospital and Visiting at XYZ Medical).
            </p>
          </div>
          <div style="text-align: center; background: rgba(255,255,255,0.1); border-radius: var(--radius-md); padding: 12px 20px;">
            <div style="font-size: 1.8rem; font-weight: 800; line-height: 1;"><?= count($multiHospitalDoctors) ?></div>
            <div style="font-size: 11px; color: #BFDBFE; text-transform: uppercase; margin-top: 4px;">Multi-Facility Doctors</div>
          </div>
        </div>

        <?php if (!empty($multiHospitalDoctors)): ?>
        <div style="margin-top: 16px; border-top: 1px solid rgba(255,255,255,0.15); padding-top: 12px; display: flex; flex-direction: column; gap: 8px;">
          <?php foreach ($multiHospitalDoctors as $mDoc): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.2); padding: 8px 14px; border-radius: var(--radius-sm); font-size: 13px;">
            <div>
              <strong style="color: white;"><?= htmlspecialchars($mDoc['full_name']) ?></strong>
              <span style="color: #93C5FD; font-size: 12px; margin-left: 6px;">(<?= htmlspecialchars($mDoc['medical_registration_id']) ?> · <?= htmlspecialchars($mDoc['specialization'] ?? '') ?>)</span>
            </div>
            <div style="color: #6EE7B7; font-size: 12px;">
              <i class="bi bi-geo-fill"></i> <?= htmlspecialchars($mDoc['hospital_details']) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- FILTER BAR -->
      <div class="card" style="padding: 16px 20px; border: 1px solid var(--mc-border); margin-bottom: 20px;">
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 100px; gap: 12px; align-items: flex-end;">
          <div>
            <label class="form-label" style="font-size: 11px;">Filter by Hospital</label>
            <select name="hospital_id" class="form-control" style="font-size: 13px; padding: 6px 10px;">
              <option value="0">All Hospitals</option>
              <?php foreach ($hospitalsList as $hl): ?>
                <option value="<?= $hl['id'] ?>" <?= $hospFilter == $hl['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($hl['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label" style="font-size: 11px;">Filter by Doctor</label>
            <select name="doctor_id" class="form-control" style="font-size: 13px; padding: 6px 10px;">
              <option value="0">All Doctors</option>
              <?php foreach ($doctorsList as $dl): ?>
                <option value="<?= $dl['id'] ?>" <?= $docFilter == $dl['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($dl['full_name']) ?> (<?= htmlspecialchars($dl['medical_registration_id']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label" style="font-size: 11px;">Employment Type</label>
            <select name="type" class="form-control" style="font-size: 13px; padding: 6px 10px;">
              <option value="all">All Types</option>
              <option value="FULL_TIME" <?= $typeFilter === 'FULL_TIME' ? 'selected' : '' ?>>Full-Time</option>
              <option value="PART_TIME" <?= $typeFilter === 'PART_TIME' ? 'selected' : '' ?>>Part-Time</option>
              <option value="VISITING" <?= $typeFilter === 'VISITING' ? 'selected' : '' ?>>Visiting</option>
              <option value="HONORARY" <?= $typeFilter === 'HONORARY' ? 'selected' : '' ?>>Honorary</option>
            </select>
          </div>

          <div>
            <label class="form-label" style="font-size: 11px;">Affiliation Status</label>
            <select name="status" class="form-control" style="font-size: 13px; padding: 6px 10px;">
              <option value="all">All Statuses</option>
              <option value="APPROVED" <?= $statusFilter === 'APPROVED' ? 'selected' : '' ?>>Active / Approved</option>
              <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Pending</option>
              <option value="REMOVED" <?= $statusFilter === 'REMOVED' ? 'selected' : '' ?>>Removed</option>
            </select>
          </div>

          <div>
            <button type="submit" class="btn btn-secondary btn-sm w-100" style="padding: 7px 12px;">Filter</button>
          </div>
        </form>
      </div>

      <!-- AFFILIATIONS TABLE -->
      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
        <div style="padding: 16px 20px; background: var(--mc-white); border-bottom: 1px solid var(--mc-border); display: flex; justify-content: space-between; align-items: center;">
          <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;">
            All Hospital-Doctor Affiliation Links (<?= count($affiliations) ?>)
          </h3>
        </div>

        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Doctor</th>
                <th>BMDC Reg ID</th>
                <th>Hospital Center</th>
                <th>Department</th>
                <th>Hospital Designation</th>
                <th>Employment Type</th>
                <th>Status</th>
                <th>Joined Date</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($affiliations)): ?>
              <tr>
                <td colspan="9" style="text-align: center; padding: 36px; color: var(--mc-text-muted);">
                  No affiliation links found matching the selected filters.
                </td>
              </tr>
              <?php endif; ?>

              <?php foreach ($affiliations as $aff): ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $aff['doctor_id'] ?>" style="font-weight: 700; color: var(--mc-text); text-decoration: none;">
                    <?= htmlspecialchars($aff['doctor_name']) ?>
                  </a>
                  <div style="font-size: 11px; color: var(--mc-text-muted);">
                    <?= htmlspecialchars($aff['doctor_uid']) ?> · <?= htmlspecialchars($aff['specialization'] ?? '') ?>
                  </div>
                </td>

                <td>
                  <strong style="color: #1E40AF; font-family: var(--font-mono); font-size: 12px;">
                    <?= htmlspecialchars($aff['medical_registration_id']) ?>
                  </strong>
                </td>

                <td>
                  <div style="font-weight: 600; color: #0F172A;">
                    <?= htmlspecialchars($aff['hospital_name']) ?>
                  </div>
                  <div style="font-size: 11px; color: var(--mc-text-muted);">
                    <?= htmlspecialchars($aff['hospital_city'] ?? '') ?>
                  </div>
                </td>

                <td>
                  <span class="badge" style="background: #EFF6FF; color: #1E40AF;">
                    <?= htmlspecialchars($aff['department_name'] ?? 'General') ?>
                  </span>
                </td>

                <td>
                  <?= htmlspecialchars($aff['designation'] ?? 'Consultant') ?>
                </td>

                <td>
                  <span class="badge" style="background: #F1F5F9; color: #334155; font-size: 11px;">
                    <?= htmlspecialchars($aff['employment_type']) ?>
                  </span>
                </td>

                <td>
                  <?php if ($aff['status'] === 'APPROVED'): ?>
                    <span class="badge badge-verified"><i class="bi bi-check-circle-fill"></i> ACTIVE</span>
                  <?php elseif ($aff['status'] === 'PENDING'): ?>
                    <span class="badge badge-pending">PENDING</span>
                  <?php elseif ($aff['status'] === 'REMOVED'): ?>
                    <span class="badge badge-suspended">REMOVED</span>
                  <?php else: ?>
                    <span class="badge badge-rejected"><?= htmlspecialchars($aff['status']) ?></span>
                  <?php endif; ?>
                </td>

                <td style="font-size: 12px; color: var(--mc-text-muted);">
                  <?= $aff['joined_at'] ? date('M d, Y', strtotime($aff['joined_at'])) : '-' ?>
                </td>

                <td style="text-align: right;">
                  <a href="<?= APP_URL ?>/admin/doctor-detail.php?id=<?= $aff['doctor_id'] ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 12px;">
                    Dossier
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
</script>
</body>
</html>
