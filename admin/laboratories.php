<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('SYSTEM_ADMIN');

$db = getDB();
$flashSuccess = '';
$flashError   = '';

// Handle laboratory status changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';
    $labId  = (int)($_POST['lab_id'] ?? 0);

    if ($labId > 0) {
        $stmtL = $db->prepare("SELECT l.*, u.id AS uid, u.email FROM laboratories l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = ?");
        $stmtL->execute([$labId]);
        $targetLab = $stmtL->fetch();

        if (!$targetLab) {
            $flashError = "Laboratory record not found.";
        } else {
            if ($action === 'approve') {
                $db->prepare("
                    UPDATE laboratories
                    SET verification_status = 'VERIFIED',
                        verified_by = ?,
                        verified_at = NOW()
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $labId]);

                if ($targetLab['uid']) {
                    $db->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = ?")->execute([$targetLab['uid']]);
                }

                AuditService::log(AuditService::LAB_VERIFIED, [
                    'user_id'  => $_SESSION['user_id'],
                    'metadata' => ['lab_id' => $labId, 'lab_name' => $targetLab['name'], 'action' => 'verified']
                ]);

                $flashSuccess = "✅ \"" . htmlspecialchars($targetLab['name']) . "\" (" . htmlspecialchars($targetLab['lab_uid']) . ") has been APPROVED and VERIFIED. Hospitals can now affiliate this laboratory.";
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? 'Registration credentials or laboratory license could not be verified.');
                $db->prepare("
                    UPDATE laboratories
                    SET verification_status = 'REJECTED',
                        verified_by = ?,
                        verified_at = NOW(),
                        rejection_reason = ?
                    WHERE id = ?
                ")->execute([$_SESSION['user_id'], $reason, $labId]);

                if ($targetLab['uid']) {
                    $db->prepare("UPDATE users SET status = 'SUSPENDED' WHERE id = ?")->execute([$targetLab['uid']]);
                }

                AuditService::log('LAB_REJECTED', [
                    'user_id'  => $_SESSION['user_id'],
                    'metadata' => ['lab_id' => $labId, 'lab_name' => $targetLab['name'], 'reason' => $reason]
                ]);

                $flashSuccess = "Laboratory \"" . htmlspecialchars($targetLab['name']) . "\" application has been REJECTED.";
            } elseif ($action === 'suspend') {
                $db->prepare("
                    UPDATE laboratories
                    SET verification_status = 'SUSPENDED'
                    WHERE id = ?
                ")->execute([$labId]);

                if ($targetLab['uid']) {
                    $db->prepare("UPDATE users SET status = 'SUSPENDED' WHERE id = ?")->execute([$targetLab['uid']]);
                }

                AuditService::log('LAB_SUSPENDED', [
                    'user_id'  => $_SESSION['user_id'],
                    'metadata' => ['lab_id' => $labId, 'lab_name' => $targetLab['name']]
                ]);

                $flashSuccess = "Laboratory \"" . htmlspecialchars($targetLab['name']) . "\" has been SUSPENDED.";
            } elseif ($action === 'reactivate') {
                $db->prepare("
                    UPDATE laboratories
                    SET verification_status = 'VERIFIED'
                    WHERE id = ?
                ")->execute([$labId]);

                if ($targetLab['uid']) {
                    $db->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = ?")->execute([$targetLab['uid']]);
                }

                AuditService::log('LAB_REACTIVATED', [
                    'user_id'  => $_SESSION['user_id'],
                    'metadata' => ['lab_id' => $labId, 'lab_name' => $targetLab['name']]
                ]);

                $flashSuccess = "Laboratory \"" . htmlspecialchars($targetLab['name']) . "\" has been REACTIVATED.";
            }
        }
    }
}

// Statistics
$totalLabs     = (int)$db->query("SELECT COUNT(*) FROM laboratories")->fetchColumn();
$pendingLabs   = (int)$db->query("SELECT COUNT(*) FROM laboratories WHERE verification_status = 'PENDING'")->fetchColumn();
$verifiedLabs  = (int)$db->query("SELECT COUNT(*) FROM laboratories WHERE verification_status = 'VERIFIED'")->fetchColumn();
$suspendedLabs = (int)$db->query("SELECT COUNT(*) FROM laboratories WHERE verification_status = 'SUSPENDED'")->fetchColumn();
$totalResults  = (int)$db->query("SELECT COUNT(*) FROM lab_test_results")->fetchColumn();

// Filtering and Search
$search = trim($_GET['q'] ?? '');
$statusFilter = strtoupper(trim($_GET['status'] ?? 'ALL'));

$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(l.name LIKE ? OR l.lab_uid LIKE ? OR l.registration_number LIKE ? OR u.email LIKE ? OR l.city LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($statusFilter !== 'ALL' && in_array($statusFilter, ['PENDING', 'VERIFIED', 'SUSPENDED', 'REJECTED'])) {
    $whereClauses[] = "l.verification_status = ?";
    $params[] = $statusFilter;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $db->prepare("
    SELECT l.*, u.email, u.last_login_at,
           (SELECT COUNT(*) FROM hospital_laboratories hl WHERE hl.laboratory_id = l.id AND hl.status = 'APPROVED') AS hospital_count,
           (SELECT COUNT(*) FROM lab_test_results ltr WHERE ltr.laboratory_id = l.id) AS test_results_count
    FROM laboratories l
    JOIN users u ON l.user_id = u.id
    {$whereSql}
    ORDER BY
        CASE l.verification_status
            WHEN 'PENDING' THEN 1
            WHEN 'VERIFIED' THEN 2
            WHEN 'SUSPENDED' THEN 3
            ELSE 4
        END,
        l.created_at DESC
");
$stmt->execute($params);
$laboratories = $stmt->fetchAll();
require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Admin';
$portalRoleBn = 'Central Administration';
$portalAccent = '#1e40af';
$portalUser = $_SESSION['name'] ?? 'System Admin';
$portalMeta = $_SESSION['email'] ?? 'admin@medcore.local';
$portalTitle = 'Diagnostic Laboratories';
$portalSubtitle = 'Verify licensed diagnostic facilities, hospital affiliations, and pathology reporting';
$portalNav = mc_admin_nav('laboratories');
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flashSuccess): ?>
  <div class="mc-alert mc-alert-ok"><?= $flashSuccess ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="mc-alert mc-alert-danger"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<section class="mc-stats" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));">
  <div class="mc-stat">
    <div class="l">Total Registered</div>
    <div class="v"><?= $totalLabs ?></div>
    <div style="font-size:.76rem;color:var(--muted);">Pathology &amp; Labs</div>
  </div>
  <div class="mc-stat" style="border-color:#fedf89;background:#fffcf5;">
    <div class="l" style="color:#b54708;">Pending Approval</div>
    <div class="v" style="color:#b54708;"><?= $pendingLabs ?></div>
    <div style="font-size:.76rem;color:#b54708;">Requires verification</div>
  </div>
  <div class="mc-stat" style="border-color:#abefc6;background:#ecfdf3;">
    <div class="l" style="color:#027a48;">Verified Facilities</div>
    <div class="v" style="color:#027a48;"><?= $verifiedLabs ?></div>
    <div style="font-size:.76rem;color:#027a48;">Active on network</div>
  </div>
  <div class="mc-stat">
    <div class="l">Suspended</div>
    <div class="v" style="color:var(--danger);"><?= $suspendedLabs ?></div>
    <div style="font-size:.76rem;color:var(--muted);">Deactivated accounts</div>
  </div>
  <div class="mc-stat">
    <div class="l">Tests Completed</div>
    <div class="v" style="color:#1e40af;"><?= $totalResults ?></div>
    <div style="font-size:.76rem;color:var(--muted);">Token-fulfilled reports</div>
  </div>
</section>

<section class="mc-panel" style="margin-bottom:1rem;">
  <div style="display:flex;gap:.75rem;align-items:center;justify-content:space-between;flex-wrap:wrap;">
    <div class="mc-tabs" style="margin-bottom:0;">
      <a href="?status=ALL<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $statusFilter === 'ALL' ? 'active' : '' ?>">All <span class="mc-tab-count"><?= $totalLabs ?></span></a>
      <a href="?status=PENDING<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $statusFilter === 'PENDING' ? 'active' : '' ?>">Pending <span class="mc-tab-count"><?= $pendingLabs ?></span></a>
      <a href="?status=VERIFIED<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $statusFilter === 'VERIFIED' ? 'active' : '' ?>">Verified <span class="mc-tab-count"><?= $verifiedLabs ?></span></a>
      <a href="?status=SUSPENDED<?= $search ? '&q=' . urlencode($search) : '' ?>" class="mc-tab <?= $statusFilter === 'SUSPENDED' ? 'active' : '' ?>">Suspended <span class="mc-tab-count"><?= $suspendedLabs ?></span></a>
    </div>

    <form method="GET" class="lab-token-form" style="flex:1;max-width:380px;">
      <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
      <input type="text" name="q" placeholder="Search by name, UID, reg #, email..." value="<?= htmlspecialchars($search) ?>">
      <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm"><i class="bi bi-search"></i></button>
      <?php if ($search): ?>
        <a href="?status=<?= htmlspecialchars($statusFilter) ?>" class="mc-btn mc-btn-secondary mc-btn-sm"><i class="bi bi-x"></i></a>
      <?php endif; ?>
    </form>
  </div>
</section>

<section class="mc-panel">
  <div style="overflow-x:auto;">
    <table class="lab-table">
      <thead>
        <tr>
          <th>Facility &amp; ID</th>
          <th>License &amp; Contact</th>
          <th>Offered Services</th>
          <th style="text-align:center;">Hospitals</th>
          <th style="text-align:center;">Reports Issued</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($laboratories)): ?>
          <tr>
            <td colspan="7" style="text-align:center;padding:2.5rem 1rem;color:var(--muted);">
              <i class="bi bi-flask" style="font-size:2rem;display:block;margin-bottom:.4rem;"></i>
              <strong>No laboratories found</strong><br>
              No laboratory records match the specified search filters.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($laboratories as $lab): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($lab['name']) ?></strong>
                <div class="mc-mono" style="font-size:.76rem;color:#1e40af;"><?= htmlspecialchars($lab['lab_uid']) ?></div>
                <?php if ($lab['city']): ?>
                  <div style="font-size:.76rem;color:var(--muted);"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($lab['city']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="mc-mono" style="font-size:.76rem;font-weight:700;">Reg: <?= htmlspecialchars($lab['registration_number']) ?></div>
                <div style="font-size:.78rem;color:var(--muted);margin-top:.15rem;"><i class="bi bi-envelope"></i> <?= htmlspecialchars($lab['email']) ?></div>
                <?php if ($lab['contact_phone']): ?>
                  <div style="font-size:.76rem;color:var(--muted);"><i class="bi bi-telephone"></i> <?= htmlspecialchars($lab['contact_phone']) ?></div>
                <?php endif; ?>
              </td>
              <td style="max-width:220px;">
                <?php if ($lab['services']): ?>
                  <div style="display:flex;flex-wrap:wrap;gap:.25rem;">
                    <?php foreach (array_slice(explode(',', $lab['services']), 0, 3) as $srv): ?>
                      <span class="lab-status info"><?= htmlspecialchars(trim($srv)) ?></span>
                    <?php endforeach; ?>
                    <?php if (count(explode(',', $lab['services'])) > 3): ?>
                      <span style="font-size:.75rem;color:var(--muted);">+<?= count(explode(',', $lab['services'])) - 3 ?> more</span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <span style="font-size:.78rem;color:var(--muted);">General Pathology</span>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <span class="lab-status info"><?= $lab['hospital_count'] ?> hospital<?= $lab['hospital_count'] == 1 ? '' : 's' ?></span>
              </td>
              <td style="text-align:center;"><strong><?= $lab['test_results_count'] ?></strong></td>
              <td>
                <?php
                  $st = $lab['verification_status'];
                  $pillCls = match($st) {
                      'VERIFIED'  => 'ok',
                      'PENDING'   => 'warn',
                      'SUSPENDED', 'REJECTED' => 'danger',
                      default     => ''
                  };
                ?>
                <span class="lab-status <?= $pillCls ?>"><?= $st ?></span>
                <?php if ($st === 'REJECTED' && !empty($lab['rejection_reason'])): ?>
                  <div style="font-size:.74rem;color:var(--danger);margin-top:.2rem;max-width:140px;" title="<?= htmlspecialchars($lab['rejection_reason']) ?>">
                    Reason: <?= htmlspecialchars(mb_strimwidth($lab['rejection_reason'], 0, 30, '...')) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td style="text-align:right;white-space:nowrap;">
                <div style="display:inline-flex;gap:.35rem;">
                  <?php if ($lab['verification_status'] === 'PENDING'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Verify and approve <?= htmlspecialchars(addslashes($lab['name'])) ?>?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="lab_id" value="<?= $lab['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm" style="background:#027a48;"><i class="bi bi-check-lg"></i> Approve</button>
                    </form>
                    <button type="button" class="mc-btn mc-btn-danger mc-btn-sm" onclick="openRejectModal(<?= $lab['id'] ?>, '<?= htmlspecialchars(addslashes($lab['name'])) ?>')">
                      <i class="bi bi-x-lg"></i> Reject
                    </button>
                  <?php elseif ($lab['verification_status'] === 'VERIFIED'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend <?= htmlspecialchars(addslashes($lab['name'])) ?>?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="lab_id" value="<?= $lab['id'] ?>">
                      <input type="hidden" name="action" value="suspend">
                      <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm" style="color:var(--danger);"><i class="bi bi-pause-circle"></i> Suspend</button>
                    </form>
                  <?php elseif ($lab['verification_status'] === 'SUSPENDED' || $lab['verification_status'] === 'REJECTED'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Reactivate <?= htmlspecialchars(addslashes($lab['name'])) ?>?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="lab_id" value="<?= $lab['id'] ?>">
                      <input type="hidden" name="action" value="reactivate">
                      <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm" style="background:#027a48;"><i class="bi bi-arrow-repeat"></i> Reactivate</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<!-- REJECTION MODAL -->
<div id="reject-modal" class="mc-modal-backdrop" role="dialog" aria-modal="true">
  <div class="mc-modal">
    <div class="mc-modal-head">
      <h3 style="color:var(--danger);">Reject Laboratory Application</h3>
      <button type="button" class="mc-modal-close" onclick="closeRejectModal()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <p style="font-size:.88rem;color:var(--muted);margin:.5rem 0 1rem;" id="modal-lab-name">Provide the verification reason for rejection:</p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="lab_id" id="modal-lab-id" value="0">
      <input type="hidden" name="action" value="reject">
      <label class="mc-field" style="margin-top:0;">
        <span>Rejection Reason</span>
        <textarea name="rejection_reason" rows="3" placeholder="e.g. Could not verify clinical license with health directorate..." required></textarea>
      </label>
      <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem;">
        <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="closeRejectModal()">Cancel</button>
        <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm">Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(id, name) {
  document.getElementById('modal-lab-id').value = id;
  document.getElementById('modal-lab-name').innerText = 'Specify rejection reason for ' + name + ':';
  document.getElementById('reject-modal').classList.add('open');
}
function closeRejectModal() {
  document.getElementById('reject-modal').classList.remove('open');
}
document.getElementById('reject-modal')?.addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
