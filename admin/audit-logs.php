<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$actionFilter   = $_GET['action'] ?? 'all';
$severityFilter = $_GET['severity'] ?? 'all';

$where = [];
$params = [];
if ($actionFilter !== 'all') {
    $where[] = "al.action = ?";
    $params[] = $actionFilter;
}
if ($severityFilter !== 'all') {
    $where[] = "al.severity = ?";
    $params[] = $severityFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT al.*, u.email AS actor_email, u.role AS actor_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    {$whereSql}
    ORDER BY al.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions
$actions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit & Compliance Trail — MedCore Admin</title>
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
      <a href="<?= APP_URL ?>/admin/patients.php" class="nav-item">
        <i class="bi bi-people-fill"></i> Patient Registry
      </a>
      <a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-item active">
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
          <span class="eyebrow" style="color: var(--admin-primary); font-size: 11px; font-weight: 700;">SECURITY & COMPLIANCE</span>
          <h1 class="page-title" style="font-size: 1.3rem;">Tamper-Evident System Audit Trail</h1>
        </div>
      </div>
    </header>

    <div class="portal-body" style="padding: var(--space-6);">

      <!-- Filter bar -->
      <div class="card" style="padding: 16px 20px; border: 1px solid var(--mc-border); margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
          <div>
            <label class="form-label" style="font-size: 11px;">Action</label>
            <select name="action" class="form-control" style="font-size: 13px; padding: 6px 10px;">
              <option value="all">All Actions</option>
              <?php foreach ($actions as $act): ?>
                <option value="<?= htmlspecialchars($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>>
                  <?= htmlspecialchars($act) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label" style="font-size: 11px;">Severity</label>
            <select name="severity" class="form-control" style="font-size: 13px; padding: 6px 10px;">
              <option value="all">All Severities</option>
              <option value="INFO" <?= $severityFilter === 'INFO' ? 'selected' : '' ?>>INFO</option>
              <option value="WARNING" <?= $severityFilter === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
              <option value="CRITICAL" <?= $severityFilter === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL</option>
            </select>
          </div>

          <div>
            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 7px 16px;">Filter Logs</button>
          </div>
        </form>
      </div>

      <!-- TABLE -->
      <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--mc-border);">
        <div class="table-responsive">
          <table class="data-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>Action</th>
                <th>Actor</th>
                <th>Target & Metadata</th>
                <th>Severity</th>
                <th>IP Address</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $log): ?>
              <tr>
                <td style="white-space:nowrap; font-family: var(--font-mono); font-size: 11px; color: var(--mc-text-muted);">
                  <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
                </td>
                <td>
                  <span class="badge" style="background: #F1F5F9; color: #1E293B; font-family: var(--font-mono); font-size: 11px;">
                    <?= htmlspecialchars($log['action']) ?>
                  </span>
                </td>
                <td>
                  <div style="font-weight: 600;"><?= htmlspecialchars($log['actor_email'] ?? 'System / Anonymous') ?></div>
                  <div style="font-size: 11px; color: var(--mc-text-muted);"><?= htmlspecialchars($log['actor_role'] ?? '-') ?></div>
                </td>
                <td style="max-width: 320px; word-break: break-all; font-size: 12px; font-family: var(--font-mono);">
                  <?= htmlspecialchars($log['metadata'] ?? '-') ?>
                </td>
                <td>
                  <?php if ($log['severity'] === 'CRITICAL'): ?>
                    <span class="badge badge-rejected">CRITICAL</span>
                  <?php elseif ($log['severity'] === 'WARNING'): ?>
                    <span class="badge badge-pending">WARNING</span>
                  <?php else: ?>
                    <span class="badge" style="background: #F8FAFC; color: #64748B;">INFO</span>
                  <?php endif; ?>
                </td>
                <td style="font-family: var(--font-mono); font-size: 11px; color: var(--mc-text-muted);">
                  <?= htmlspecialchars($log['ip_address']) ?>
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
