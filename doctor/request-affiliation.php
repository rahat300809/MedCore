<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Request Hospital Affiliation — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .hosp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;}
    .req-card{background:#fff;border:1.5px solid var(--mc-border);border-radius:14px;padding:20px;transition:var(--transition);position:relative;}
    .req-card:hover{border-color:var(--mc-blue);box-shadow:0 4px 20px -6px rgba(26,115,232,.18);transform:translateY(-1px);}
    .req-card.st-pending{border-color:#FDE68A;background:#FFFBEB;}
    .req-card.st-approved{border-color:#BBF7D0;background:#F0FDF4;}
    .req-card.st-rejected{border-color:#FECACA;background:#FFF5F5;}
    .spill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
    .sp-pending{background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;}
    .sp-approved{background:#DCFCE7;color:#166534;border:1px solid #BBF7D0;}
    .sp-rejected{background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;}
    .sp-suspended{background:#F1F5F9;color:#475569;border:1px solid #CBD5E1;}
    .sp-removed{background:#F1F5F9;color:#94A3B8;border:1px solid #E2E8F0;}
    .req-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px;}
    .req-modal.open{display:flex;}
    .req-modal-box{background:#fff;border-radius:18px;padding:32px;max-width:520px;width:100%;box-shadow:0 20px 60px -10px rgba(0,0,0,.3);max-height:90vh;overflow-y:auto;}
    .divider{display:flex;align-items:center;gap:12px;margin:28px 0 20px;}
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--mc-border);}
    .divider span{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--mc-text-muted);white-space:nowrap;}
  </style>
</head>
<body>
<div class="portal-layout">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo-icon" style="width:32px;height:32px;font-size:16px;">M</div>
      <div>
        <div style="font-weight:800;font-size:14px;">MedCore</div>
        <div style="font-size:11px;color:var(--mc-text-muted);">Doctor Portal</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-title">Clinical</div>
      <?php if (isset($_SESSION['hospital_id'])): ?>
      <a href="<?= APP_URL ?>/doctor/dashboard.php" class="sidebar-nav a" style="display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--mc-text-secondary);font-size:14px;font-weight:500;">
        <i class="bi bi-grid-1x2-fill" style="color:var(--mc-blue);"></i> Dashboard
      </a>
      <?php endif; ?>
      <div class="sidebar-section-title" style="margin-top:16px;">Affiliations</div>
      <a href="<?= APP_URL ?>/doctor/request-affiliation.php" style="display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--mc-blue);background:var(--mc-blue-50);font-size:14px;font-weight:600;text-decoration:none;">
        <i class="bi bi-building-add"></i> Request Hospital
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($doctor['full_name'],0,2)) ?></div>
        <div class="sidebar-user-info">
          <div class="name"><?= htmlspecialchars($doctor['full_name']) ?></div>
          <div class="role"><?= htmlspecialchars($doctor['specialization']??'Doctor') ?></div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-secondary btn-sm w-100" style="margin-top:12px;">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <main class="portal-content">
    <header class="top-nav">
      <div class="top-nav-left">
        <div>
          <span class="eyebrow" style="color:var(--mc-blue);">Doctor Portal</span>
          <h1 class="page-title">Request Hospital Affiliation</h1>
        </div>
      </div>
      <div class="top-nav-right">
        <?php if (isset($_SESSION['hospital_id'])): ?>
        <a href="<?= APP_URL ?>/doctor/dashboard.php" class="btn btn-secondary btn-sm">
          <i class="bi bi-arrow-left"></i> Dashboard
        </a>
        <?php endif; ?>
      </div>
    </header>

    <div class="portal-body" style="padding:24px;">

      <?php if ($flashSuccess): ?>
      <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill"></i> <?= $flashSuccess ?></div>
      <?php endif; ?>
      <?php if ($flashError): ?>
      <div class="alert alert-error mb-4"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?></div>
      <?php endif; ?>

      <!-- Doctor identity banner -->
      <div style="background:linear-gradient(135deg,#1E3A5F,#0F172A);border-radius:14px;padding:20px 24px;color:#fff;margin-bottom:28px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <div style="width:52px;height:52px;background:rgba(255,255,255,.12);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;flex-shrink:0;">
          <?= strtoupper(substr($doctor['full_name'],0,2)) ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:800;font-size:1.1rem;">Dr. <?= htmlspecialchars($doctor['full_name']) ?></div>
          <div style="font-size:13px;color:rgba(255,255,255,.7);margin-top:2px;">
            <?= htmlspecialchars($doctor['specialization']??'General Practice') ?>
            &nbsp;·&nbsp;
            <span style="font-family:monospace;font-size:12px;background:rgba(255,255,255,.12);padding:2px 8px;border-radius:4px;"><?= htmlspecialchars($doctor['medical_registration_id']) ?></span>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;background:#DCFCE7;color:#166534;border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700;flex-shrink:0;">
          <i class="bi bi-patch-check-fill"></i> Globally Verified
        </div>
      </div>

      <!-- My affiliations section -->
      <?php if (!empty($myAffs)): ?>
      <h2 style="font-size:1rem;font-weight:800;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
        <i class="bi bi-diagram-3-fill" style="color:var(--mc-blue);"></i>
        My Affiliations &amp; Pending Requests
      </h2>
      <div class="hosp-grid" style="margin-bottom:28px;">
        <?php foreach ($myAffs as $aff):
          $sc  = strtolower($aff['status']);
          $pcs = ['PENDING'=>'sp-pending','APPROVED'=>'sp-approved','REJECTED'=>'sp-rejected','SUSPENDED'=>'sp-suspended','REMOVED'=>'sp-removed'];
          $pic = ['PENDING'=>'bi-hourglass-split','APPROVED'=>'bi-check-circle-fill','REJECTED'=>'bi-x-circle-fill','SUSPENDED'=>'bi-pause-circle-fill','REMOVED'=>'bi-dash-circle-fill'];
          $pc  = $pcs[$aff['status']] ?? 'sp-removed';
          $pi  = $pic[$aff['status']] ?? 'bi-circle';
        ?>
        <div class="req-card st-<?= $sc ?>">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:12px;">
            <div style="flex:1;min-width:0;">
              <div style="font-weight:700;font-size:14px;color:#0F172A;margin-bottom:3px;"><?= htmlspecialchars($aff['hname']) ?></div>
              <div style="font-size:12px;color:var(--mc-text-muted);">
                <?= htmlspecialchars($aff['hcity']??'') ?><?= $aff['hdistrict'] ? ', '.htmlspecialchars($aff['hdistrict']) : '' ?>
                &nbsp;·&nbsp;<?= htmlspecialchars(str_replace('_',' ',$aff['htype'])) ?>
              </div>
            </div>
            <span class="spill <?= $pc ?>"><i class="bi <?= $pi ?>"></i> <?= $aff['status'] ?></span>
          </div>
          <div style="font-size:12px;color:var(--mc-text-secondary);display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
            <?php if ($aff['designation']): ?><span><i class="bi bi-briefcase"></i> <?= htmlspecialchars($aff['designation']) ?></span><?php endif; ?>
            <?php if ($aff['employment_type']): ?><span><i class="bi bi-clock"></i> <?= str_replace('_',' ',$aff['employment_type']) ?></span><?php endif; ?>
            <?php if ($aff['deptname']): ?><span><i class="bi bi-hospital"></i> <?= htmlspecialchars($aff['deptname']) ?></span><?php endif; ?>
          </div>
          <?php if ($aff['status']==='REJECTED' && $aff['rejection_reason']): ?>
          <div style="font-size:12px;background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:8px 12px;color:#991B1B;margin-bottom:12px;">
            <strong>Reason:</strong> <?= htmlspecialchars($aff['rejection_reason']) ?>
          </div>
          <?php endif; ?>
          <?php if ($aff['status']==='PENDING'): ?>
          <div style="display:flex;gap:8px;">
            <button type="button" onclick="openModal(<?= $aff['hospital_id'] ?>,'<?= htmlspecialchars(addslashes($aff['hname'])) ?>','<?= htmlspecialchars(addslashes($aff['designation']??'')) ?>','<?= $aff['employment_type'] ?>',<?= $aff['department_id']?:0 ?>)"
              class="btn btn-sm" style="background:#EFF6FF;color:#1E40AF;border:1px solid #BFDBFE;font-size:12px;padding:5px 12px;">
              <i class="bi bi-pencil"></i> Edit
            </button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Withdraw this request?')">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="dh_id" value="<?= $aff['id'] ?>">
              <button type="submit" name="withdraw_request" value="1" class="btn btn-sm" style="background:#FEE2E2;color:#991B1B;border:1px solid #FECACA;font-size:12px;padding:5px 12px;">
                <i class="bi bi-x-circle"></i> Withdraw
              </button>
            </form>
          </div>
          <?php elseif ($aff['status']==='APPROVED'): ?>
          <div style="font-size:12px;color:#166534;font-weight:600;">
            <i class="bi bi-check-circle-fill"></i> Active since <?= $aff['joined_at'] ? date('M j, Y',strtotime($aff['joined_at'])) : '—' ?>
          </div>
          <?php elseif (in_array($aff['status'],['REJECTED','REMOVED'])): ?>
          <button type="button" onclick="openModal(<?= $aff['hospital_id'] ?>,'<?= htmlspecialchars(addslashes($aff['hname'])) ?>','Consultant','FULL_TIME',0)"
            class="btn btn-sm" style="background:#EFF6FF;color:#1E40AF;border:1px solid #BFDBFE;font-size:12px;padding:5px 12px;">
            <i class="bi bi-arrow-repeat"></i> Re-apply
          </button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- All hospitals -->
      <div class="divider"><span>All Accredited Hospitals — Select to Apply</span></div>

      <div class="search-bar" style="margin-bottom:20px;">
        <i class="bi bi-search"></i>
        <input type="text" id="hosp-search" placeholder="Search by name, city, or type…">
      </div>

      <div class="hosp-grid" id="hospital-grid">
        <?php foreach ($allHosp as $h):
          $ms = $myStatus[$h['id']] ?? null;
        ?>
        <div class="req-card" data-search="<?= strtolower($h['name'].' '.($h['city']??'').' '.$h['type']) ?>">
          <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;">
            <div style="width:44px;height:44px;border-radius:10px;background:#F0FDFA;color:#0D9488;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
              <i class="bi bi-building-fill"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:700;font-size:14px;color:#0F172A;margin-bottom:3px;"><?= htmlspecialchars($h['name']) ?></div>
              <div style="font-size:12px;color:var(--mc-text-muted);">
                <?= htmlspecialchars(str_replace('_',' ',$h['type'])) ?> &nbsp;·&nbsp;
                <?= htmlspecialchars($h['city']??'') ?><?= $h['district'] ? ', '.htmlspecialchars($h['district']) : '' ?>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:16px;font-size:12px;color:var(--mc-text-muted);margin-bottom:14px;flex-wrap:wrap;">
            <span><i class="bi bi-people-fill" style="color:var(--mc-blue);"></i> <?= $h['adocs'] ?> Doctors</span>
            <span><i class="bi bi-diagram-3" style="color:var(--mc-teal);"></i> <?= $h['depts'] ?> Departments</span>
            <span class="verified-badge"><i class="bi bi-patch-check-fill"></i> Accredited</span>
          </div>
          <?php if ($ms==='APPROVED'): ?>
            <div style="font-size:12px;background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:8px 12px;color:#166534;font-weight:600;">
              <i class="bi bi-check-circle-fill"></i> You are an active doctor here
            </div>
          <?php elseif ($ms==='PENDING'): ?>
            <div style="font-size:12px;background:#FEF3C7;border:1px solid #FDE68A;border-radius:8px;padding:8px 12px;color:#92400E;font-weight:600;">
              <i class="bi bi-hourglass-split"></i> Request pending review
            </div>
          <?php elseif ($ms==='SUSPENDED'): ?>
            <div style="font-size:12px;background:#F1F5F9;border:1px solid #CBD5E1;border-radius:8px;padding:8px 12px;color:#64748B;font-weight:600;">
              <i class="bi bi-pause-circle-fill"></i> Access suspended at this hospital
            </div>
          <?php else: ?>
            <button type="button" class="btn btn-sm w-100"
              style="background:linear-gradient(135deg,#1E40AF,#1A73E8);color:#fff;border:none;font-weight:700;padding:9px;border-radius:10px;"
              onclick="openModal(<?= $h['id'] ?>,'<?= htmlspecialchars(addslashes($h['name'])) ?>','Consultant','FULL_TIME',0)">
              <i class="bi bi-building-add"></i>
              <?= $ms==='REJECTED' ? 'Re-apply to this Hospital' : 'Request Affiliation' ?>
            </button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($allHosp)): ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <div class="empty-state-icon"><i class="bi bi-building"></i></div>
          <p>No accredited hospitals found.</p>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<!-- MODAL -->
<div class="req-modal" id="reqModal">
  <div class="req-modal-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
      <div style="width:40px;height:40px;background:#EFF6FF;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;color:#1E40AF;flex-shrink:0;">
        <i class="bi bi-building-add"></i>
      </div>
      <div>
        <h3 style="font-size:1rem;font-weight:800;margin:0;" id="modal-title">Request Affiliation</h3>
        <div style="font-size:12px;color:var(--mc-text-muted);" id="modal-sub">Submit your application</div>
      </div>
    </div>
    <div style="background:#F8FAFC;border:1px solid var(--mc-border);border-radius:10px;padding:12px 16px;margin:16px 0;font-size:13px;color:var(--mc-text-secondary);display:flex;gap:10px;align-items:flex-start;">
      <i class="bi bi-info-circle-fill" style="color:var(--mc-blue);margin-top:1px;flex-shrink:0;"></i>
      <span>The hospital will review your request and approve or decline it. Hospitals can also add you directly using your BMDC registration number.</span>
    </div>
    <form method="POST" id="affForm">
      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
      <input type="hidden" name="hospital_id" id="f-hid">
      <div class="form-group">
        <label class="form-label" for="f-desig">Designation <span style="color:#EF4444;">*</span></label>
        <input type="text" id="f-desig" name="designation" class="form-control" placeholder="e.g. Senior Consultant, Resident" value="Consultant" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group">
          <label class="form-label" for="f-emp">Employment Type</label>
          <select id="f-emp" name="employment_type" class="form-control">
            <option value="FULL_TIME">Full Time</option>
            <option value="PART_TIME">Part Time</option>
            <option value="VISITING">Visiting</option>
            <option value="HONORARY">Honorary</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="f-dept">Preferred Department</label>
          <select id="f-dept" name="department_id" class="form-control">
            <option value="0">— Any / General —</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="f-note">Cover Message (Optional)</label>
        <textarea id="f-note" name="cover_note" class="form-control" rows="3" placeholder="Briefly introduce yourself or explain your interest…" style="resize:vertical;"></textarea>
        <div class="form-hint">Visible to the hospital admin reviewing your request.</div>
      </div>
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="submit" name="submit_request" value="1" class="btn btn-primary" style="flex:1;background:linear-gradient(135deg,#1E40AF,#1A73E8);border:none;font-weight:700;">
          <i class="bi bi-send-fill"></i> Submit Request
        </button>
        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const deptCache={};
async function openModal(hid,hname,desig,empType,deptId){
  document.getElementById('f-hid').value=hid;
  document.getElementById('modal-title').textContent='Request — '+hname;
  document.getElementById('modal-sub').textContent='Apply to join '+hname;
  document.getElementById('f-desig').value=desig||'Consultant';
  document.getElementById('f-emp').value=empType||'FULL_TIME';
  document.getElementById('f-note').value='';
  const ds=document.getElementById('f-dept');
  ds.innerHTML='<option value="0">Loading…</option>';
  try{
    let d=deptCache[hid];
    if(!d){const r=await fetch(`<?= APP_URL ?>/api/departments.php?hospital_id=${hid}`);d=await r.json();deptCache[hid]=d;}
    ds.innerHTML='<option value="0">— Any / General —</option>';
    d.forEach(x=>{const o=document.createElement('option');o.value=x.id;o.textContent=x.name;if(x.id==deptId)o.selected=true;ds.appendChild(o);});
  }catch(e){ds.innerHTML='<option value="0">— Any / General —</option>';}
  document.getElementById('reqModal').classList.add('open');
  document.getElementById('f-desig').focus();
}
function closeModal(){document.getElementById('reqModal').classList.remove('open');}
document.getElementById('reqModal').addEventListener('click',e=>{if(e.target===document.getElementById('reqModal'))closeModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
document.getElementById('hosp-search').addEventListener('input',function(){
  const q=this.value.toLowerCase();
  document.querySelectorAll('#hospital-grid .req-card').forEach(c=>{
    c.style.display=((c.dataset.search||'')+c.textContent.toLowerCase()).includes(q)?'':'none';
  });
});
</script>
</body>
</html>
