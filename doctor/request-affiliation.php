<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/includes/session_bar.php';

requireAuth('DOCTOR');

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

// Fetch doctor profile
$stmtDoc = $db->prepare("SELECT d.* FROM doctors d WHERE d.user_id = ?");
$stmtDoc->execute([$userId]);
$doctor = $stmtDoc->fetch();

if (!$doctor || $doctor['verification_status'] !== 'VERIFIED') {
    header('Location: ' . APP_URL . '/role-selection.php');
    exit;
}
$doctorId = (int)$doctor['id'];

$flashSuccess = '';
$flashError   = '';

// Handle withdraw
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_request'])) {
    validateCsrf();
    $dhId = (int)($_POST['dh_id'] ?? 0);
    $stmtDH = $db->prepare("SELECT dh.*, h.name AS hname FROM doctor_hospitals dh JOIN hospitals h ON dh.hospital_id=h.id WHERE dh.id=? AND dh.doctor_id=? AND dh.status='PENDING'");
    $stmtDH->execute([$dhId, $doctorId]);
    $dhRow = $stmtDH->fetch();
    if ($dhRow) {
        $db->prepare("DELETE FROM doctor_hospitals WHERE id=? AND doctor_id=? AND status='PENDING'")->execute([$dhRow['id'], $doctorId]);
        AuditService::log('DOCTOR_AFFILIATION_WITHDRAWN', ['user_id'=>$userId,'doctor_id'=>$doctorId,'hospital_id'=>$dhRow['hospital_id'],'metadata'=>['hospital_name'=>$dhRow['hname']]]);
        $flashSuccess = 'Request to ' . htmlspecialchars($dhRow['hname']) . ' withdrawn.';
    }
}

// Handle new / re-submit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    validateCsrf();
    $hospitalId  = (int)($_POST['hospital_id'] ?? 0);
    $deptId      = (int)($_POST['department_id'] ?? 0);
    $designation = trim($_POST['designation'] ?? 'Consultant');
    $empType     = trim($_POST['employment_type'] ?? 'FULL_TIME');
    $coverNote   = trim($_POST['cover_note'] ?? '');
    $allowed     = ['FULL_TIME','PART_TIME','VISITING','HONORARY'];
    if (!in_array($empType, $allowed)) $empType = 'FULL_TIME';

    if ($hospitalId <= 0 || empty($designation)) {
        $flashError = 'Please fill in all required fields.';
    } else {
        $stmtH = $db->prepare("SELECT * FROM hospitals WHERE id=? AND verification_status='VERIFIED'");
        $stmtH->execute([$hospitalId]);
        $targetH = $stmtH->fetch();

        if (!$targetH) {
            $flashError = 'Hospital not found or not accredited.';
        } else {
            $stmtEx = $db->prepare("SELECT * FROM doctor_hospitals WHERE doctor_id=? AND hospital_id=?");
            $stmtEx->execute([$doctorId, $hospitalId]);
            $ex = $stmtEx->fetch();

            if ($ex) {
                if ($ex['status'] === 'APPROVED') {
                    $flashError = 'You are already an active doctor at ' . htmlspecialchars($targetH['name']) . '.';
                } elseif ($ex['status'] === 'PENDING') {
                    // Update the existing pending request
                    $db->prepare("UPDATE doctor_hospitals SET department_id=?,designation=?,employment_type=?,rejection_reason=? WHERE id=?")->execute([$deptId?:null,$designation,$empType,$coverNote?:null,$ex['id']]);
                    $flashSuccess = 'Your pending request at ' . htmlspecialchars($targetH['name']) . ' has been updated.';
                } else {
                    // REJECTED / REMOVED / SUSPENDED — resubmit
                    $db->prepare("UPDATE doctor_hospitals SET status='PENDING',department_id=?,designation=?,employment_type=?,rejection_reason=?,joined_at=NULL,approved_by=NULL,approved_at=NULL,created_at=NOW() WHERE id=?")->execute([$deptId?:null,$designation,$empType,$coverNote?:null,$ex['id']]);
                    AuditService::log('DOCTOR_AFFILIATION_REQUEST',['user_id'=>$userId,'doctor_id'=>$doctorId,'hospital_id'=>$hospitalId,'metadata'=>['action'=>'resubmit','designation'=>$designation]]);
                    $flashSuccess = 'Re-application sent to ' . htmlspecialchars($targetH['name']) . '.';
                }
            } else {
                $db->prepare("INSERT INTO doctor_hospitals (doctor_id,hospital_id,department_id,designation,employment_type,status,rejection_reason) VALUES (?,?,?,?,?,'PENDING',?)")->execute([$doctorId,$hospitalId,$deptId?:null,$designation,$empType,$coverNote?:null]);
                AuditService::log('DOCTOR_AFFILIATION_REQUEST',['user_id'=>$userId,'doctor_id'=>$doctorId,'hospital_id'=>$hospitalId,'metadata'=>['action'=>'new','designation'=>$designation,'hospital_name'=>$targetH['name']]]);
                $flashSuccess = 'Affiliation request sent to <strong>' . htmlspecialchars($targetH['name']) . '</strong>. You will be notified once reviewed.';
            }
        }
    }
}

// Load my affiliations
$stmtMy = $db->prepare("SELECT dh.*,h.name AS hname,h.hospital_uid,h.type AS htype,h.city AS hcity,h.district AS hdistrict,dept.name AS deptname FROM doctor_hospitals dh JOIN hospitals h ON dh.hospital_id=h.id LEFT JOIN departments dept ON dh.department_id=dept.id WHERE dh.doctor_id=? ORDER BY FIELD(dh.status,'PENDING','APPROVED','REJECTED','SUSPENDED','REMOVED'),h.name ASC");
$stmtMy->execute([$doctorId]);
$myAffs = $stmtMy->fetchAll();
$myStatus = [];
foreach ($myAffs as $a) $myStatus[$a['hospital_id']] = $a['status'];

// Load all verified hospitals
$allHosp = $db->query("SELECT h.*,(SELECT COUNT(*) FROM doctor_hospitals dh WHERE dh.hospital_id=h.id AND dh.status='APPROVED') AS adocs,(SELECT COUNT(*) FROM departments dep WHERE dep.hospital_id=h.id AND dep.status='ACTIVE') AS depts FROM hospitals h WHERE h.verification_status='VERIFIED' ORDER BY h.name ASC")->fetchAll();

$csrf = generateCsrfToken();

require_once __DIR__ . '/../app/views/portal/navs.php';
$hospIdSess = (int)($_SESSION['hospital_id'] ?? 0);
$activeSession = $hospIdSess > 0 ? getDoctorActivePatientSession($db, $doctorId, $hospIdSess) : null;
$waitingQueueCount = $hospIdSess > 0 ? mc_doctor_shell_setup($db, $doctorId, $hospIdSess, $activeSession, 'request-affiliation') : 0;

$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $doctor['full_name'] ?? 'Doctor';
$portalMeta = ($doctor['medical_registration_id'] ?? '') . ' · Affiliations';
$portalTitle = 'Hospital affiliations';
$portalSubtitle = 'অনুমোদিত হাসপাতালে অনুমোদনের জন্য আবেদন করুন';
$portalNav = mc_doctor_nav('affiliation', $waitingQueueCount);
$portalTopAction = $hospIdSess > 0
    ? '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/doctor/dashboard.php"><i class="bi bi-arrow-left"></i> Dashboard</a>'
    : '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/doctor/select-hospital.php"><i class="bi bi-hospital"></i> Select hospital</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flashSuccess): ?>
  <div class="mc-alert mc-alert-ok"><i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="mc-alert mc-alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="mc-panel" style="background:linear-gradient(135deg,#0b2538,#0f766e);color:#fff;border:0">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
      <h2 style="font-size:1.2rem;color:#fff;margin:0">Dr. <?= htmlspecialchars($doctor['full_name']) ?></h2>
      <div style="font-size:.88rem;opacity:.88;margin-top:.2rem">
        <?= htmlspecialchars($doctor['specialization'] ?? 'General Practice') ?>
        · <span class="mc-mono"><?= htmlspecialchars($doctor['medical_registration_id']) ?></span>
      </div>
    </div>
    <span class="lab-status ok"><i class="bi bi-patch-check-fill"></i> Globally verified</span>
  </div>
</div>

<?php if (!empty($myAffs)): ?>
  <div class="mc-panel">
    <div class="mc-panel-h"><h2>My affiliations &amp; pending requests</h2></div>
    <div class="mc-grid-3">
      <?php foreach ($myAffs as $aff):
        $st = $aff['status'];
        $sCls = $st === 'APPROVED' ? 'ok' : ($st === 'PENDING' ? 'warn' : ($st === 'REJECTED' ? 'danger' : ''));
      ?>
        <div class="mc-panel" style="margin:0;background:<?= $st === 'APPROVED' ? '#f3faf8' : ($st === 'PENDING' ? '#fffbeb' : '#fff') ?>">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
            <div>
              <strong><?= htmlspecialchars($aff['hname']) ?></strong>
              <div style="font-size:.82rem;color:var(--muted)">
                <?= htmlspecialchars($aff['hcity'] ?? '') ?><?= $aff['hdistrict'] ? ', ' . htmlspecialchars($aff['hdistrict']) : '' ?>
                · <?= htmlspecialchars(str_replace('_', ' ', $aff['htype'])) ?>
              </div>
            </div>
            <span class="lab-status <?= $sCls ?>"><?= htmlspecialchars($st) ?></span>
          </div>
          <div style="font-size:.82rem;color:var(--ink-2);margin-top:.6rem;display:flex;flex-wrap:wrap;gap:.5rem">
            <?php if ($aff['designation']): ?><span><?= htmlspecialchars($aff['designation']) ?></span><?php endif; ?>
            <?php if ($aff['employment_type']): ?><span>· <?= str_replace('_', ' ', $aff['employment_type']) ?></span><?php endif; ?>
            <?php if ($aff['deptname']): ?><span>· <?= htmlspecialchars($aff['deptname']) ?></span><?php endif; ?>
          </div>
          <?php if ($st === 'REJECTED' && $aff['rejection_reason']): ?>
            <div class="mc-alert mc-alert-danger" style="margin:.65rem 0 0;padding:.5rem .7rem;font-size:.82rem">
              Reason: <?= htmlspecialchars($aff['rejection_reason']) ?>
            </div>
          <?php endif; ?>
          <div style="margin-top:.75rem;display:flex;gap:.4rem;flex-wrap:wrap">
            <?php if ($st === 'PENDING'): ?>
              <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm"
                onclick="openModal(<?= (int)$aff['hospital_id'] ?>,'<?= htmlspecialchars(addslashes($aff['hname'])) ?>','<?= htmlspecialchars(addslashes($aff['designation'] ?? '')) ?>','<?= htmlspecialchars($aff['employment_type']) ?>',<?= (int)($aff['department_id'] ?: 0) ?>)">
                Edit
              </button>
              <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('Withdraw this request?')">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="dh_id" value="<?= (int)$aff['id'] ?>">
                <button type="submit" name="withdraw_request" value="1" class="mc-btn mc-btn-danger mc-btn-sm">Withdraw</button>
              </form>
            <?php elseif ($st === 'APPROVED'): ?>
              <span style="font-size:.8rem;color:#027a48;font-weight:700">
                Active since <?= $aff['joined_at'] ? date('M j, Y', strtotime($aff['joined_at'])) : '—' ?>
              </span>
            <?php elseif (in_array($st, ['REJECTED', 'REMOVED'], true)): ?>
              <button type="button" class="mc-btn mc-btn-primary mc-btn-sm"
                onclick="openModal(<?= (int)$aff['hospital_id'] ?>,'<?= htmlspecialchars(addslashes($aff['hname'])) ?>','Consultant','FULL_TIME',0)">
                Re-apply
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="lab-token-bar">
  <label for="hosp-search">All accredited hospitals — select to apply</label>
  <div class="lab-token-form">
    <input type="text" id="hosp-search" placeholder="Search by hospital name, city, or type..."
           style="text-transform:none;letter-spacing:normal;font-family:inherit;font-size:.95rem">
  </div>
</div>

<div class="mc-grid-3" id="hospital-grid">
  <?php foreach ($allHosp as $h):
    $ms = $myStatus[$h['id']] ?? null;
  ?>
    <div class="mc-panel req-card" style="margin:0;display:flex;flex-direction:column;justify-content:space-between;gap:.85rem"
         data-search="<?= strtolower($h['name'] . ' ' . ($h['city'] ?? '') . ' ' . $h['type']) ?>">
      <div>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
          <div>
            <h3 style="font-size:1.02rem;margin:0"><?= htmlspecialchars($h['name']) ?></h3>
            <div style="font-size:.82rem;color:var(--muted);margin-top:.2rem">
              <?= htmlspecialchars(str_replace('_', ' ', $h['type'])) ?> ·
              <?= htmlspecialchars($h['city'] ?? '') ?><?= $h['district'] ? ', ' . htmlspecialchars($h['district']) : '' ?>
            </div>
          </div>
          <span class="lab-status ok">Accredited</span>
        </div>
        <div style="display:flex;gap:.85rem;font-size:.82rem;color:var(--muted);margin-top:.65rem">
          <span><strong style="color:var(--ink)"><?= (int)$h['adocs'] ?></strong> Doctors</span>
          <span><strong style="color:var(--ink)"><?= (int)$h['depts'] ?></strong> Departments</span>
        </div>
      </div>

      <div>
        <?php if ($ms === 'APPROVED'): ?>
          <div class="lab-status ok" style="width:100%;text-align:center;padding:.45rem">Active doctor here</div>
        <?php elseif ($ms === 'PENDING'): ?>
          <div class="lab-status warn" style="width:100%;text-align:center;padding:.45rem">Request pending review</div>
        <?php elseif ($ms === 'SUSPENDED'): ?>
          <div class="lab-status danger" style="width:100%;text-align:center;padding:.45rem">Access suspended</div>
        <?php else: ?>
          <button type="button" class="mc-btn mc-btn-primary mc-btn-sm mc-btn-block"
            onclick="openModal(<?= (int)$h['id'] ?>,'<?= htmlspecialchars(addslashes($h['name'])) ?>','Consultant','FULL_TIME',0)">
            <i class="bi bi-building-add"></i> <?= $ms === 'REJECTED' ? 'Re-apply' : 'Request affiliation' ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (empty($allHosp)): ?>
  <div class="mc-panel" style="text-align:center;padding:2rem">No accredited hospitals found.</div>
<?php endif; ?>

<!-- MODAL -->
<div class="mc-modal-backdrop" id="reqModal" role="dialog" aria-modal="true" aria-label="Request affiliation">
  <div class="mc-modal">
    <div class="mc-modal-head">
      <div>
        <h3 id="modal-title">Request affiliation</h3>
        <div style="font-size:.82rem;color:var(--muted)" id="modal-sub">Submit your application</div>
      </div>
      <button type="button" class="mc-modal-close" onclick="closeModal()" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>

    <form method="POST" id="affForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="hospital_id" id="f-hid">
      <label class="mc-field" style="margin-top:0">
        <span>Designation *</span>
        <input type="text" id="f-desig" name="designation" placeholder="e.g. Senior Consultant, Resident" value="Consultant" required>
      </label>
      <div class="mc-form-grid" style="margin-top:.85rem">
        <label class="mc-field" style="margin:0">
          <span>Employment type</span>
          <select id="f-emp" name="employment_type">
            <option value="FULL_TIME">Full Time</option>
            <option value="PART_TIME">Part Time</option>
            <option value="VISITING">Visiting</option>
            <option value="HONORARY">Honorary</option>
          </select>
        </label>
        <label class="mc-field" style="margin:0">
          <span>Preferred department</span>
          <select id="f-dept" name="department_id">
            <option value="0">— Any / General —</option>
          </select>
        </label>
      </div>
      <label class="mc-field">
        <span>Cover message (optional)</span>
        <textarea id="f-note" name="cover_note" rows="3" placeholder="Briefly introduce yourself..."></textarea>
      </label>
      <div style="display:flex;gap:.5rem;margin-top:1rem;justify-content:flex-end">
        <button type="button" onclick="closeModal()" class="mc-btn mc-btn-secondary">Cancel</button>
        <button type="submit" name="submit_request" value="1" class="mc-btn mc-btn-primary"><i class="bi bi-send-fill"></i> Submit request</button>
      </div>
    </form>
  </div>
</div>

<script>
const deptCache = {};
async function openModal(hid, hname, desig, empType, deptId) {
  document.getElementById('f-hid').value = hid;
  document.getElementById('modal-title').textContent = 'Request — ' + hname;
  document.getElementById('modal-sub').textContent = 'Apply to join ' + hname;
  document.getElementById('f-desig').value = desig || 'Consultant';
  document.getElementById('f-emp').value = empType || 'FULL_TIME';
  document.getElementById('f-note').value = '';
  const ds = document.getElementById('f-dept');
  ds.innerHTML = '<option value="0">Loading…</option>';
  try {
    let d = deptCache[hid];
    if (!d) {
      const r = await fetch(`<?= APP_URL ?>/api/departments.php?hospital_id=${hid}`);
      d = await r.json();
      deptCache[hid] = d;
    }
    ds.innerHTML = '<option value="0">— Any / General —</option>';
    d.forEach(x => {
      const o = document.createElement('option');
      o.value = x.id;
      o.textContent = x.name;
      if (x.id == deptId) o.selected = true;
      ds.appendChild(o);
    });
  } catch (e) {
    ds.innerHTML = '<option value="0">— Any / General —</option>';
  }
  document.getElementById('reqModal').classList.add('open');
  document.getElementById('f-desig').focus();
}
function closeModal() { document.getElementById('reqModal').classList.remove('open'); }
document.getElementById('reqModal')?.addEventListener('click', e => { if (e.target.id === 'reqModal') closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
document.getElementById('hosp-search')?.addEventListener('input', function () {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#hospital-grid .req-card').forEach(c => {
    c.style.display = ((c.dataset.search || '') + c.textContent.toLowerCase()).includes(q) ? '' : 'none';
  });
});
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
