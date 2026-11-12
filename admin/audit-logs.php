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
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Tamper-Evident System Audit Trail';
$portalSubtitle = 'Security, verification & clinical record access events';
$portalNav = mc_admin_nav('audit');
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<section class="mc-panel" style="margin-bottom:1rem;">
  <form method="GET">
    <div class="mc-form-grid-3" style="align-items:end;">
      <label class="mc-field" style="margin-top:0;">
        <span>Action</span>
        <select name="action">
          <option value="all">All Actions</option>
          <?php foreach ($actions as $act): ?>
            <option value="<?= htmlspecialchars($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="mc-field" style="margin-top:0;">
        <span>Severity</span>
        <select name="severity">
          <option value="all">All Severities</option>
          <option value="INFO" <?= $severityFilter === 'INFO' ? 'selected' : '' ?>>INFO</option>
          <option value="WARNING" <?= $severityFilter === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
          <option value="CRITICAL" <?= $severityFilter === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL</option>
        </select>
      </label>

      <div style="display:flex;gap:.5rem;">
        <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm">Filter Logs</button>
        <a href="<?= APP_URL ?>/admin/audit-logs.php" class="mc-btn mc-btn-secondary mc-btn-sm">Reset</a>
      </div>
    </div>
  </form>
</section>

<section class="mc-panel">
  <div style="overflow-x:auto;">
    <table class="lab-table">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Action</th>
          <th>Actor</th>
          <th>Target &amp; Metadata</th>
          <th>Severity</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr>
            <td colspan="6" style="text-align:center;padding:2rem;color:var(--muted);">No audit log entries match the selected filters.</td>
          </tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td class="mc-mono" style="white-space:nowrap;font-size:.76rem;color:var(--muted);"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
            <td><span class="lab-status info mc-mono"><?= htmlspecialchars($log['action']) ?></span></td>
            <td>
              <strong><?= htmlspecialchars($log['actor_email'] ?? 'System / Anonymous') ?></strong>
              <div style="font-size:.75rem;color:var(--muted);"><?= htmlspecialchars($log['actor_role'] ?? '-') ?></div>
            </td>
            <td class="mc-mono" style="max-width:320px;word-break:break-all;font-size:.78rem;"><?= htmlspecialchars($log['metadata'] ?? '-') ?></td>
            <td>
              <?php if ($log['severity'] === 'CRITICAL'): ?>
                <span class="lab-status danger">CRITICAL</span>
              <?php elseif ($log['severity'] === 'WARNING'): ?>
                <span class="lab-status warn">WARNING</span>
              <?php else: ?>
                <span class="lab-status">INFO</span>
              <?php endif; ?>
            </td>
            <td class="mc-mono" style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($log['ip_address']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
