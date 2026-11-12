<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireAuth('HOSPITAL_ADMIN');

$db         = getDB();
$hospitalId = (int)$_SESSION['hospital_id'];

$stmtHosp = $db->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmtHosp->execute([$hospitalId]);
$hospital = $stmtHosp->fetch();

$success = '';
$error   = '';

// Handle add / affiliate doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    validateCsrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $deptId     = (int)($_POST['department_id'] ?? 0);
    $desig      = trim($_POST['designation'] ?? 'Consultant');
    $empType    = trim($_POST['employment_type'] ?? 'FULL_TIME');

    if (empty($identifier)) {
        $error = 'Please enter Doctor UID (DR-XXXXXX), BMDC Registration number, or Email.';
    } else {
        $stmtDoc = $db->prepare("
            SELECT d.*, u.email
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            WHERE d.doctor_uid = ?
               OR d.medical_registration_id = ?
               OR u.email = ?
            LIMIT 1
        ");
        $stmtDoc->execute([$identifier, $identifier, $identifier]);
        $doc = $stmtDoc->fetch();

        if (!$doc) {
            $error = 'No doctor found matching "' . htmlspecialchars($identifier) . '". The doctor must register on MedCore first.';
        } elseif ($doc['verification_status'] !== 'VERIFIED') {
            $statusName = $doc['verification_status'];
            $error = 'Cannot affiliate Dr. ' . htmlspecialchars($doc['full_name']) . ' (' . htmlspecialchars($doc['medical_registration_id']) . '): Global status is ' . htmlspecialchars($statusName) . '. Doctors must be approved by the website Main Admin before any hospital can add them to their roster.';
        } else {
            // Check if already affiliated with this hospital
            $stmtCheck = $db->prepare("SELECT * FROM doctor_hospitals WHERE doctor_id = ? AND hospital_id = ?");
            $stmtCheck->execute([$doc['id'], $hospitalId]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                if ($existing['status'] === 'APPROVED') {
                    $error = 'Dr. ' . htmlspecialchars($doc['full_name']) . ' is already an active affiliated doctor at this hospital.';
                } else {
                    // Reactivate / update existing affiliation
                    $stmtUpdate = $db->prepare("
                        UPDATE doctor_hospitals
                        SET department_id = ?, designation = ?, employment_type = ?,
                            status = 'APPROVED', joined_at = CURDATE(), approved_by = ?, approved_at = NOW(), rejection_reason = NULL
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$deptId ?: null, $desig, $empType, $_SESSION['user_id'], $existing['id']]);

                    AuditService::log('AFFILIATE_DOCTOR_REACTIVATED', [
                        'user_id'    => $_SESSION['user_id'],
                        'hospital_id'=> $hospitalId,
                        'doctor_id'  => $doc['id'],
                        'metadata'   => ['doctor_name' => $doc['full_name'], 'designation' => $desig, 'type' => $empType],
                    ]);

                    $success = 'Dr. ' . htmlspecialchars($doc['full_name']) . ' has been re-affiliated to ' . htmlspecialchars($hospital['name']) . ' successfully!';
                }
            } else {
                $stmtInsert = $db->prepare("
                    INSERT INTO doctor_hospitals
                        (doctor_id, hospital_id, department_id, designation, employment_type, status, joined_at, approved_by, approved_at)
                    VALUES (?, ?, ?, ?, ?, 'APPROVED', CURDATE(), ?, NOW())
                ");
                $stmtInsert->execute([
                    $doc['id'], $hospitalId, $deptId ?: null, $desig, $empType, $_SESSION['user_id']
                ]);

                AuditService::log('AFFILIATE_DOCTOR', [
                    'user_id'    => $_SESSION['user_id'],
                    'hospital_id'=> $hospitalId,
                    'doctor_id'  => $doc['id'],
                    'metadata'   => ['doctor_name' => $doc['full_name'], 'designation' => $desig, 'type' => $empType],
                ]);

                $success = 'Dr. ' . htmlspecialchars($doc['full_name']) . ' has been successfully added to ' . htmlspecialchars($hospital['name']) . ' (' . htmlspecialchars($empType) . ')!';
            }
        }
    }
}

// Handle status change (Remove / Suspend / Reactivate at this hospital)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    validateCsrf();
    $dhId = (int)$_POST['dh_id'];
    $newStatus = $_POST['new_status'];
    $reason = trim($_POST['status_reason'] ?? '');

    if (in_array($newStatus, ['APPROVED', 'SUSPENDED', 'REMOVED'])) {
        $stmtGet = $db->prepare("SELECT dh.*, d.full_name FROM doctor_hospitals dh JOIN doctors d ON dh.doctor_id = d.id WHERE dh.id = ? AND dh.hospital_id = ?");
        $stmtGet->execute([$dhId, $hospitalId]);
        $targetAff = $stmtGet->fetch();

        if ($targetAff) {
            $db->prepare("
                UPDATE doctor_hospitals
                SET status = ?, rejection_reason = ?
                WHERE id = ? AND hospital_id = ?
            ")->execute([$newStatus, $reason ?: null, $dhId, $hospitalId]);

            AuditService::log('HOSPITAL_DOCTOR_STATUS_CHANGED', [
                'user_id'    => $_SESSION['user_id'],
                'hospital_id'=> $hospitalId,
                'doctor_id'  => $targetAff['doctor_id'],
                'metadata'   => ['new_status' => $newStatus, 'reason' => $reason]
            ]);

            if ($newStatus === 'REMOVED') {
                $success = 'Dr. ' . htmlspecialchars($targetAff['full_name']) . ' has been removed from this hospital. (Their affiliations with other hospitals and central verified status remain intact).';
            } elseif ($newStatus === 'SUSPENDED') {
                $success = 'Dr. ' . htmlspecialchars($targetAff['full_name']) . ' has been temporarily suspended from practicing at this hospital.';
            } else {
                $success = 'Dr. ' . htmlspecialchars($targetAff['full_name']) . ' has been set to ACTIVE at this hospital.';
            }
        }
    }
}

// Handle approve/reject of doctor-initiated PENDING requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handle_request'])) {
    validateCsrf();
    $dhId      = (int)($_POST['dh_id'] ?? 0);
    $action    = $_POST['request_action'] ?? '';
    $rejectMsg = trim($_POST['reject_reason'] ?? '');

    if ($dhId && in_array($action, ['approve', 'reject'])) {
        $stmtGet = $db->prepare("SELECT dh.*, d.full_name FROM doctor_hospitals dh JOIN doctors d ON dh.doctor_id = d.id WHERE dh.id = ? AND dh.hospital_id = ? AND dh.status = 'PENDING'");
        $stmtGet->execute([$dhId, $hospitalId]);
        $req = $stmtGet->fetch();

        if ($req) {
            if ($action === 'approve') {
                $db->prepare("UPDATE doctor_hospitals SET status = 'APPROVED', joined_at = CURDATE(), approved_by = ?, approved_at = NOW(), rejection_reason = NULL WHERE id = ?")->execute([$_SESSION['user_id'], $dhId]);
                AuditService::log(AuditService::AFFILIATION_APPROVED, ['user_id'=>$_SESSION['user_id'],'hospital_id'=>$hospitalId,'doctor_id'=>$req['doctor_id'],'metadata'=>['doctor_name'=>$req['full_name'],'source'=>'doctor_request']]);
                $success = 'Dr. ' . htmlspecialchars($req['full_name']) . ' has been approved and is now an active affiliated doctor.';
            } else {
                $db->prepare("UPDATE doctor_hospitals SET status = 'REJECTED', rejection_reason = ? WHERE id = ?")->execute([$rejectMsg ?: 'Request declined by hospital.', $dhId]);
                AuditService::log(AuditService::AFFILIATION_REJECTED, ['user_id'=>$_SESSION['user_id'],'hospital_id'=>$hospitalId,'doctor_id'=>$req['doctor_id'],'metadata'=>['doctor_name'=>$req['full_name'],'reason'=>$rejectMsg]]);
                $success = 'Request from Dr. ' . htmlspecialchars($req['full_name']) . ' has been declined.';
            }
        }
    }
}

// Fetch doctor-initiated PENDING join requests
$stmtPendingReqs = $db->prepare("
    SELECT dh.*, d.full_name, d.doctor_uid, d.specialization, d.qualification,
           d.medical_registration_id, d.verification_status AS doc_global_status,
           u.email, u.phone, dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE dh.hospital_id = ? AND dh.status = 'PENDING'
    ORDER BY dh.created_at ASC
");
$stmtPendingReqs->execute([$hospitalId]);
$pendingRequests = $stmtPendingReqs->fetchAll();

// Fetch departments for dropdown & filters
$stmtDepts = $db->prepare("SELECT * FROM departments WHERE hospital_id = ? AND status = 'ACTIVE' ORDER BY name");
$stmtDepts->execute([$hospitalId]);
$departments = $stmtDepts->fetchAll();

// Filter parameters
$filterDept = (int)($_GET['department_id'] ?? 0);
$filterType = $_GET['employment_type'] ?? 'all';
$search     = trim($_GET['q'] ?? '');

$whereClauses = ["dh.hospital_id = ?", "dh.status != 'PENDING'"];
$params       = [$hospitalId];

if ($filterDept > 0) {
    $whereClauses[] = "dh.department_id = ?";
    $params[] = $filterDept;
}
if ($filterType !== 'all') {
    $whereClauses[] = "dh.employment_type = ?";
    $params[] = $filterType;
}
if ($search !== '') {
    $whereClauses[] = "(d.full_name LIKE ? OR d.medical_registration_id LIKE ? OR d.doctor_uid LIKE ? OR u.email LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$whereSql = implode(' AND ', $whereClauses);

// Fetch affiliated doctors (excluding PENDING — shown separately above)
$stmtDocs = $db->prepare("
    SELECT dh.*, d.full_name, d.doctor_uid, d.specialization, d.qualification,
           d.medical_registration_id, d.verification_status AS doctor_global_status,
           u.email, u.phone,
           dept.name AS department_name
    FROM doctor_hospitals dh
    JOIN doctors d ON dh.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN departments dept ON dh.department_id = dept.id
    WHERE {$whereSql}
    ORDER BY FIELD(dh.status, 'APPROVED', 'SUSPENDED', 'REJECTED', 'REMOVED'), dh.joined_at DESC
");
$stmtDocs->execute($params);
$doctors = $stmtDocs->fetchAll();

// Stats for this hospital
$countActive   = 0;
$countFullTime = 0;
$countPartTime = 0;
$countVisiting = 0;

foreach ($doctors as $docItem) {
    if ($docItem['status'] === 'APPROVED') {
        $countActive++;
        if ($docItem['employment_type'] === 'FULL_TIME') $countFullTime++;
        elseif ($docItem['employment_type'] === 'PART_TIME') $countPartTime++;
        elseif ($docItem['employment_type'] === 'VISITING') $countVisiting++;
    }
}

$csrf = generateCsrfToken();

require_once __DIR__ . '/../app/views/portal/navs.php';
$portalRole = 'Hospital';
$portalRoleBn = 'Hospital portal';
$portalAccent = '#0b4f6c';
$portalUser = $hospital['name'] ?? 'Hospital';
$portalMeta = ($hospital['hospital_uid'] ?? '') . ' · Doctors roster';
$portalTitle = 'Affiliated doctors';
$portalSubtitle = 'স্টাফ রোস্টার, ডিপার্টমেন্ট ও অনুমোদন';
$portalNav = mc_hospital_nav('doctors', count($pendingRequests));
$portalTopAction = '<button type="button" class="mc-btn mc-btn-primary mc-btn-sm" onclick="openAddDoctorModal()"><i class="bi bi-person-plus-fill"></i> Affiliate doctor</button>';
$bodyClass = 'lab-desk';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($success): ?>
  <div class="mc-alert mc-alert-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mc-alert mc-alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($pendingRequests)): ?>
  <div class="mc-panel" style="border-color:#fedf89;background:#fffbeb">
    <div class="mc-panel-h">
      <h2><i class="bi bi-person-exclamation-fill" style="color:var(--warn)"></i> Doctor join requests (<?= count($pendingRequests) ?>)</h2>
      <span class="lab-status warn"><?= count($pendingRequests) ?> pending</span>
    </div>
    <div class="mc-list">
      <?php foreach ($pendingRequests as $req): ?>
        <div class="mc-list-item" style="background:#fff;align-items:flex-start;flex-wrap:wrap;gap:.85rem">
          <div style="flex:1;min-width:220px">
            <strong>Dr. <?= htmlspecialchars($req['full_name']) ?></strong>
            <div style="font-size:.84rem;color:var(--muted);margin-top:.15rem">
              <?= htmlspecialchars($req['specialization'] ?? 'General') ?>
              · <span class="mc-mono"><?= htmlspecialchars($req['medical_registration_id']) ?></span>
              · <?= htmlspecialchars($req['email']) ?>
            </div>
            <div style="font-size:.82rem;color:var(--ink-2);margin-top:.35rem;display:flex;flex-wrap:wrap;gap:.6rem">
              <span><?= htmlspecialchars($req['designation'] ?? 'Consultant') ?></span>
              <span>· <?= htmlspecialchars(str_replace('_', ' ', $req['employment_type'])) ?></span>
              <?php if ($req['department_name']): ?><span>· <?= htmlspecialchars($req['department_name']) ?></span><?php endif; ?>
              <span>· Applied <?= date('M j, Y', strtotime($req['created_at'])) ?></span>
            </div>
            <?php if ($req['rejection_reason']): ?>
              <div style="margin-top:.45rem;font-size:.82rem;color:var(--accent)"><em>"<?= htmlspecialchars($req['rejection_reason']) ?>"</em></div>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <form method="POST" style="margin:0">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="dh_id" value="<?= (int)$req['id'] ?>">
              <input type="hidden" name="request_action" value="approve">
              <button type="submit" name="handle_request" value="1" class="mc-btn mc-btn-primary mc-btn-sm">Approve</button>
            </form>
            <button type="button" class="mc-btn mc-btn-danger mc-btn-sm"
              onclick="const el=document.getElementById('reject-form-<?= (int)$req['id'] ?>');el.style.display=el.style.display==='none'?'block':'none'">
              Decline
            </button>
          </div>
        </div>
        <div id="reject-form-<?= (int)$req['id'] ?>" style="display:none">
          <form method="POST" style="background:#fef3f2;border:1px solid #fecdca;border-radius:10px;padding:.75rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="dh_id" value="<?= (int)$req['id'] ?>">
            <input type="hidden" name="request_action" value="reject">
            <input type="text" name="reject_reason" placeholder="Decline reason (optional)..."
                   style="flex:1;min-width:200px;border:1px solid var(--line);border-radius:8px;padding:.48rem .75rem;font:inherit;background:#fff">
            <button type="submit" name="handle_request" value="1" class="mc-btn mc-btn-danger mc-btn-sm">Confirm decline</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Active staff</div><div class="v"><?= (int)$countActive ?></div></div>
  <div class="mc-stat"><div class="l">Full-time</div><div class="v"><?= (int)$countFullTime ?></div></div>
  <div class="mc-stat"><div class="l">Part-time</div><div class="v"><?= (int)$countPartTime ?></div></div>
  <div class="mc-stat"><div class="l">Visiting</div><div class="v"><?= (int)$countVisiting ?></div></div>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Filter roster</h2></div>
  <form method="GET" style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-items:end">
    <label class="mc-field" style="margin:0">
      <span>Search doctor</span>
      <input type="text" name="q" placeholder="Name, BMDC, email..." value="<?= htmlspecialchars($search) ?>">
    </label>
    <label class="mc-field" style="margin:0">
      <span>Department</span>
      <select name="department_id">
        <option value="0">All departments</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= (int)$dept['id'] ?>" <?= $filterDept == $dept['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($dept['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="mc-field" style="margin:0">
      <span>Employment type</span>
      <select name="employment_type">
        <option value="all">All types</option>
        <option value="FULL_TIME" <?= $filterType === 'FULL_TIME' ? 'selected' : '' ?>>Full-Time</option>
        <option value="PART_TIME" <?= $filterType === 'PART_TIME' ? 'selected' : '' ?>>Part-Time</option>
        <option value="VISITING" <?= $filterType === 'VISITING' ? 'selected' : '' ?>>Visiting</option>
        <option value="HONORARY" <?= $filterType === 'HONORARY' ? 'selected' : '' ?>>Honorary</option>
      </select>
    </label>
    <div style="display:flex;gap:.45rem;flex-wrap:wrap">
      <button type="submit" class="mc-btn mc-btn-primary">Filter</button>
      <?php if ($search || $filterDept || $filterType !== 'all'): ?>
        <a href="<?= APP_URL ?>/hospital/doctors.php" class="mc-btn mc-btn-secondary">Clear</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Doctor roster (<?= count($doctors) ?>)</h2>
    <span class="lab-status ok">Multi-hospital verified</span>
  </div>
  <?php if (empty($doctors)): ?>
    <p style="margin:0;color:var(--muted)">No doctors found. Use "Affiliate doctor" above to add verified doctors to your hospital.</p>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="lab-table">
        <thead>
          <tr>
            <th>Doctor</th>
            <th>BMDC Reg</th>
            <th>Department</th>
            <th>Designation</th>
            <th>Type</th>
            <th>Status</th>
            <th>Joined</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($doctors as $d):
            $st = $d['status'];
            $sCls = $st === 'APPROVED' ? 'ok' : ($st === 'PENDING' ? 'warn' : ($st === 'REMOVED' || $st === 'REJECTED' ? 'danger' : ''));
          ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($d['full_name']) ?></strong>
                <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($d['doctor_uid']) ?> · <?= htmlspecialchars($d['specialization'] ?? '') ?></div>
              </td>
              <td class="mc-mono" style="color:var(--accent)"><?= htmlspecialchars($d['medical_registration_id']) ?></td>
              <td><span class="lab-status info"><?= htmlspecialchars($d['department_name'] ?? 'General') ?></span></td>
              <td><?= htmlspecialchars($d['designation'] ?? 'Consultant') ?></td>
              <td><span class="lab-status"><?= htmlspecialchars($d['employment_type']) ?></span></td>
              <td><span class="lab-status <?= $sCls ?>"><?= $st === 'APPROVED' ? 'ACTIVE' : htmlspecialchars($st) ?></span></td>
              <td style="white-space:nowrap;font-size:.84rem;color:var(--muted)"><?= $d['joined_at'] ? date('M d, Y', strtotime($d['joined_at'])) : '—' ?></td>
              <td style="text-align:right">
                <div style="display:flex;gap:.35rem;justify-content:flex-end;flex-wrap:wrap">
                  <a href="<?= APP_URL ?>/hospital/doctor-profile.php?id=<?= (int)$d['doctor_id'] ?>" class="mc-btn mc-btn-secondary mc-btn-sm">Details</a>
                  <?php if ($st === 'APPROVED'): ?>
                    <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Suspend Dr. <?= addslashes($d['full_name']) ?> from practicing at this hospital?');">
                      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                      <input type="hidden" name="dh_id" value="<?= (int)$d['id'] ?>">
                      <input type="hidden" name="new_status" value="SUSPENDED">
                      <input type="hidden" name="update_status" value="1">
                      <button type="submit" class="mc-btn mc-btn-secondary mc-btn-sm">Suspend</button>
                    </form>
                    <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Remove Dr. <?= addslashes($d['full_name']) ?> from staff roster?');">
                      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                      <input type="hidden" name="dh_id" value="<?= (int)$d['id'] ?>">
                      <input type="hidden" name="new_status" value="REMOVED">
                      <input type="hidden" name="update_status" value="1">
                      <button type="submit" class="mc-btn mc-btn-danger mc-btn-sm">Remove</button>
                    </form>
                  <?php elseif ($st === 'SUSPENDED' || $st === 'REMOVED'): ?>
                    <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Re-activate Dr. <?= addslashes($d['full_name']) ?> at this hospital?');">
                      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                      <input type="hidden" name="dh_id" value="<?= (int)$d['id'] ?>">
                      <input type="hidden" name="new_status" value="APPROVED">
                      <input type="hidden" name="update_status" value="1">
                      <button type="submit" class="mc-btn mc-btn-primary mc-btn-sm">Re-activate</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ADD DOCTOR MODAL -->
<div id="add-doctor-modal" class="mc-modal-backdrop" role="dialog" aria-modal="true" aria-label="Affiliate doctor">
  <div class="mc-modal">
    <div class="mc-modal-head">
      <h3><i class="bi bi-person-plus-fill" style="color:var(--accent)"></i> Affiliate doctor to hospital</h3>
      <button type="button" class="mc-modal-close" onclick="closeAddDoctorModal()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <p style="font-size:.88rem;color:var(--muted);margin:0 0 1rem">
      Add any <strong>Admin-Verified Doctor</strong> to <?= htmlspecialchars($hospital['name']) ?>.
    </p>
    <form method="POST" id="add-doctor-form">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="add_doctor" value="1">
      <label class="mc-field" style="margin-top:0">
        <span>Doctor identifier (BMDC Reg, UID, or Email) *</span>
        <input type="text" id="identifier" name="identifier" required placeholder="BMDC-A-12345, DR-000001, or email">
      </label>
      <label class="mc-field">
        <span>Hospital department</span>
        <select name="department_id" id="department_id">
          <option value="0">General / None assigned</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?= (int)$dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="mc-form-grid" style="margin-top:.85rem">
        <label class="mc-field" style="margin:0">
          <span>Designation</span>
          <input type="text" id="designation" name="designation" value="Consultant" placeholder="e.g. Senior Consultant">
        </label>
        <label class="mc-field" style="margin:0">
          <span>Employment type</span>
          <select name="employment_type" id="employment_type">
            <option value="FULL_TIME">Full-Time</option>
            <option value="PART_TIME">Part-Time</option>
            <option value="VISITING">Visiting</option>
            <option value="HONORARY">Honorary</option>
          </select>
        </label>
      </div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1.1rem">
        <button type="button" class="mc-btn mc-btn-secondary" onclick="closeAddDoctorModal()">Cancel</button>
        <button type="submit" class="mc-btn mc-btn-primary">Confirm affiliation</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddDoctorModal() { document.getElementById('add-doctor-modal')?.classList.add('open'); }
function closeAddDoctorModal() { document.getElementById('add-doctor-modal')?.classList.remove('open'); }
document.getElementById('add-doctor-modal')?.addEventListener('click', e => { if (e.target.id === 'add-doctor-modal') closeAddDoctorModal(); });
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
