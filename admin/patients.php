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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Registry — MedCore Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    :root {
      --admin-dark: #0F172A;
      --admin-primary: #1E40AF;
    }
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
      <a href="<?= APP_URL ?>/admin/affiliations.php" class="nav-item">
        <i class="bi bi-diagram-3-fill"></i> Hospital Affiliations
      </a>

      <div class="nav-section-title" style="color: #64748B; margin-top: 16px;">National Registry</div>
      <a href="<?= APP_URL ?>/admin/patients.php" class="nav-item active">
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
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">CENTRAL HEALTH RECORD REGISTRY</span>
          <h1 class="page-title" style="font-size: 1.3rem;">National Patient Directory</h1>
        </div>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <div class="card" style="padding: 16px 20px; border: 1px solid var(--mc-border); margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 10px; max-width: 480px;">
          <div class="search-bar" style="flex: 1;">
            <i class="bi bi-search"></i>
            <input type="text" name="q" placeholder="Search by Patient UID, Name, Email..." value="<?= htmlspecialchars($search) ?>">
          </div>
          <?php if ($search): ?>
            <a href="<?= APP_URL ?>/admin/patients.php" class="btn btn-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
          <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        </form>
      </div>

      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
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
              <?php foreach ($patients as $p): ?>
              <tr>
                <td>
                  <div style="font-weight: 700; color: var(--mc-text);">
                    <?= htmlspecialchars($p['full_name']) ?>
                  </div>
                  <div style="font-size: 11px; color: var(--mc-text-muted);">
                    <?= htmlspecialchars($p['email']) ?> · <?= htmlspecialchars($p['phone'] ?? '') ?>
                  </div>
                </td>
                <td>
                  <strong style="color: #1E40AF; font-family: var(--font-mono); font-size: 12px;">
                    <?= htmlspecialchars($p['patient_uid']) ?>
                  </strong>
                </td>
                <td>
                  <?= ucfirst(strtolower($p['gender'])) ?> ·
                  <?= date_diff(date_create($p['date_of_birth']), date_create('today'))->y ?> yrs
                </td>
                <td>
                  <span class="badge" style="background: #FFF1F2; color: #E11D48; font-weight: 700;">
                    <?= htmlspecialchars($p['blood_group']) ?>
                  </span>
                </td>
                <td><?= $p['total_consultations'] ?></td>
                <td><?= $p['total_prescriptions'] ?></td>
                <td style="font-size: 12px; color: var(--mc-text-muted);">
                  <?= date('M d, Y', strtotime($p['created_at'])) ?>
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
