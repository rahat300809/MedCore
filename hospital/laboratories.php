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

$success = $error = '';

// ── Add / affiliate lab ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lab'])) {
    validateCsrf();
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        $error = 'Please enter the Lab UID (LAB-XXXXXX), registration number, or email.';
    } else {
        $stmtFind = $db->prepare("
            SELECT l.*, u.email
            FROM laboratories l
            JOIN users u ON l.user_id = u.id
            WHERE l.lab_uid = ? OR l.registration_number = ? OR u.email = ?
            LIMIT 1
        ");
        $stmtFind->execute([$identifier, $identifier, $identifier]);
        $lab = $stmtFind->fetch();

        if (!$lab) {
            $error = 'No laboratory found matching "' . htmlspecialchars($identifier) . '". The laboratory must register on MedCore first.';
        } elseif ($lab['verification_status'] !== 'VERIFIED') {
            $error = 'Cannot affiliate "' . htmlspecialchars($lab['name']) . '": status is ' . $lab['verification_status'] . '. Labs must be verified by the system admin first.';
        } else {
            $stmtCheck = $db->prepare("SELECT * FROM hospital_laboratories WHERE laboratory_id = ? AND hospital_id = ?");
            $stmtCheck->execute([$lab['id'], $hospitalId]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                if ($existing['status'] === 'APPROVED') {
                    $error = '"' . htmlspecialchars($lab['name']) . '" is already affiliated with your hospital.';
                } else {
                    // Re-approve
                    $db->prepare("UPDATE hospital_laboratories SET status='APPROVED', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $existing['id']]);
                    $success = '"' . htmlspecialchars($lab['name']) . '" has been re-approved.';
                }
            } else {
                $db->prepare("
                    INSERT INTO hospital_laboratories (laboratory_id, hospital_id, status, approved_by, approved_at)
                    VALUES (?, ?, 'APPROVED', ?, NOW())
                ")->execute([$lab['id'], $hospitalId, $_SESSION['user_id']]);

                AuditService::log(AuditService::LAB_AFFILIATED, [
                    'user_id'    => $_SESSION['user_id'],
                    'hospital_id'=> $hospitalId,
                    'metadata'   => ['lab_id' => $lab['id'], 'lab_uid' => $lab['lab_uid'], 'lab_name' => $lab['name']],
                ]);
                $success = '"' . htmlspecialchars($lab['name']) . '" has been affiliated with your hospital successfully.';
            }
        }
    }
}

// ── Suspend / Remove ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    validateCsrf();
    $afflId  = (int)$_POST['affiliation_id'];
    $newSt   = $_POST['new_status'] ?? '';
    $allowed = ['APPROVED','SUSPENDED','REMOVED'];
    if (in_array($newSt, $allowed)) {
        $db->prepare("UPDATE hospital_laboratories SET status=?, approved_by=?, approved_at=NOW() WHERE id=? AND hospital_id=?")->execute([$newSt, $_SESSION['user_id'], $afflId, $hospitalId]);
        $success = 'Laboratory status updated to ' . $newSt . '.';
    }
}

// ── Fetch affiliated labs ─────────────────────────────────────────────────
$stmtLabs = $db->prepare("
    SELECT hl.*, l.lab_uid, l.name AS lab_name, l.registration_number,
           l.contact_phone, l.contact_email, l.city, l.services,
           l.verification_status AS lab_v_status,
           u.email AS user_email, u.last_login_at,
           (SELECT COUNT(*) FROM prescription_lab_orders plo WHERE plo.laboratory_id = l.id AND plo.hospital_id = :h2) AS total_orders,
           (SELECT COUNT(*) FROM prescription_lab_orders plo WHERE plo.laboratory_id = l.id AND plo.hospital_id = :h3 AND plo.status = 'PENDING') AS pending_orders
    FROM hospital_laboratories hl
    JOIN laboratories l ON hl.laboratory_id = l.id
    JOIN users u ON l.user_id = u.id
    WHERE hl.hospital_id = :h1
    ORDER BY hl.status = 'APPROVED' DESC, hl.created_at DESC
");
$stmtLabs->execute([':h1'=>$hospitalId,':h2'=>$hospitalId,':h3'=>$hospitalId]);
$labs = $stmtLabs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laboratories — <?= htmlspecialchars($hospital['name']) ?> — MedCore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/portal.css">
<style>
:root{--accent:#0284c7;--border:#e2e8f0;--hosp:#7c3aed;--text:#0f172a;--muted:#64748b}
*{box-sizing:border-box}
body{background:#f8fafc;font-family:'Plus Jakarta Sans','Inter',-apple-system,sans-serif;color:var(--text);margin:0}
.nav{background:#ffffff;border-bottom:2px solid var(--hosp);padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;box-shadow:0 1px 3px rgba(15,23,42,0.05)}
.nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
.nav-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--hosp),#0284c7);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#ffffff}
.nav-title{font-size:1rem;font-weight:800;color:#0a2540}
.nav-subtitle{font-size:.72rem;color:var(--muted);font-weight:600}
.nav-right a{font-size:.8rem;color:#334155;text-decoration:none;background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;padding:.4rem .85rem;margin-left:.5rem;font-weight:600;transition:all .15s}
.nav-right a:hover{background:#f1f5f9;color:#0f172a;border-color:#94a3b8}
.page{max-width:1200px;margin:0 auto;padding:2rem 1.5rem}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-header h1{font-size:1.5rem;font-weight:900;color:#0a2540;margin:0}
.page-header p{color:var(--muted);font-size:.875rem;margin:.25rem 0 0}
/* ADD FORM */
.add-card{background:linear-gradient(135deg,#f5f3ff 0%,#f0f9ff 100%);border:1.5px solid #ddd6fe;border-radius:20px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 2px 8px rgba(124,58,237,0.04)}
.add-card h2{font-size:1.05rem;font-weight:800;color:#0a2540;margin:0 0 1rem}
.add-form{display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap}
.add-form .form-group{flex:1;min-width:220px;margin:0}
label{display:block;color:#334155;font-size:.8125rem;font-weight:700;margin-bottom:.4rem}
input{width:100%;background:#ffffff;border:1.5px solid #cbd5e1;border-radius:10px;padding:.75rem 1rem;color:#0f172a;font-size:.9rem;outline:none;transition:border-color .2s;box-sizing:border-box}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(2,132,199,0.15)}
.btn-add{background:linear-gradient(135deg,var(--hosp),#0284c7);border:none;border-radius:10px;padding:.75rem 1.4rem;color:#fff;font-size:.875rem;font-weight:800;cursor:pointer;white-space:nowrap;transition:all .15s;box-shadow:0 2px 8px rgba(124,58,237,0.2)}
.btn-add:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(124,58,237,0.3)}
/* ALERTS */
.alert{border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-weight:600}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-weight:700}
/* LAB CARDS */
.labs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:1.25rem}
.lab-card{background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 2px 8px rgba(15,23,42,0.03);transition:all .2s}
.lab-card:hover{border-color:#38bdf8;transform:translateY(-2px);box-shadow:0 8px 20px rgba(2,132,199,0.08)}
.lab-card-head{padding:1.25rem;display:flex;align-items:flex-start;gap:.875rem}
.lab-avatar{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#7c3aed,#0284c7);display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#ffffff;flex-shrink:0}
.lab-main{flex:1;min-width:0}
.lab-name{font-size:1.05rem;font-weight:800;color:#0a2540;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lab-uid{font-size:.72rem;color:#64748b;font-family:'JetBrains Mono',monospace;margin-top:.1rem;font-weight:700}
.lab-badge{font-size:.7rem;font-weight:800;border-radius:6px;padding:.2rem .55rem;margin-top:.35rem;display:inline-block}
.lb-approved{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.lb-suspended{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.lb-removed{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.lb-pending{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}
.lab-card-body{padding:0 1.25rem 1rem;border-top:1px solid #f1f5f9}
.lab-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:.4rem .75rem;margin-top:.75rem}
.meta-item{font-size:.75rem;color:#64748b}
.meta-item strong{color:#334155;display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.1rem;font-weight:700}
.lab-services{font-size:.75rem;color:#64748b;margin-top:.65rem;padding-top:.65rem;border-top:1px solid #f1f5f9}
.lab-services span{display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:5px;padding:.15rem .45rem;margin:.15rem .1rem;font-size:.7rem;color:#475569;font-weight:600}
.lab-stats{display:flex;gap:.75rem;margin-top:.75rem}
.stat-pill{flex:1;text-align:center;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.5rem}
.stat-pill .sv{font-size:1.15rem;font-weight:800;color:#0a2540}
.stat-pill .sl{font-size:.68rem;color:#64748b;font-weight:600}
.lab-actions{padding:.875rem 1.25rem;border-top:1px solid #f1f5f9;display:flex;gap:.5rem;flex-wrap:wrap;background:#fafafa}
.act-btn{flex:1;font-size:.78rem;font-weight:700;border-radius:8px;padding:.45rem .75rem;text-align:center;border:1px solid #cbd5e1;cursor:pointer;transition:all .15s;background:#ffffff;color:#475569;font-family:inherit}
.act-btn:hover{background:#f1f5f9;color:#0f172a;border-color:#94a3b8}
.act-btn.danger:hover{background:#fef2f2;border-color:#fca5a5;color:#dc2626}
.act-btn.success:hover{background:#ecfdf5;border-color:#a7f3d0;color:#059669}
.empty-state{grid-column:1/-1;text-align:center;padding:3rem;color:#64748b}
.empty-icon{font-size:3rem;margin-bottom:.75rem}
@media(max-width:600px){.labs-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<nav class="nav">
  <a href="<?= APP_URL ?>/hospital/dashboard.php" class="nav-brand">
    <div class="nav-icon">🏥</div>
    <div>
      <div class="nav-title"><?= htmlspecialchars($hospital['name']) ?></div>
      <div class="nav-subtitle">Hospital Admin</div>
    </div>
  </a>
  <div class="nav-right">
    <a href="<?= APP_URL ?>/hospital/doctors.php">Doctors</a>
    <a href="<?= APP_URL ?>/hospital/dashboard.php">Dashboard</a>
    <a href="?logout=1" style="color:#f87171;border-color:rgba(239,68,68,0.2)">Logout</a>
  </div>
</nav>

<?php if (isset($_GET['logout'])) { logout(); } ?>

<div class="page">
  <div class="page-header">
    <div>
      <h1>🔬 Affiliated Laboratories</h1>
      <p>Manage laboratories affiliated with <?= htmlspecialchars($hospital['name']) ?></p>
    </div>
  </div>

  <!-- Add Lab -->
  <div class="add-card">
    <h2>➕ Affiliate a Laboratory</h2>
    <form method="POST" class="add-form">
      <?= csrfField() ?>
      <div class="form-group">
        <label>Lab UID, Registration Number, or Email</label>
        <input type="text" name="identifier" placeholder="LAB-000001 / REG-2026-XXXX / lab@example.com"
               value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
      </div>
      <button type="submit" name="add_lab" class="btn-add">Affiliate Lab</button>
    </form>
    <p style="font-size:.75rem;color:#64748b;margin:.75rem 0 0">The laboratory must be registered on MedCore and verified by the system administrator before you can affiliate them.</p>
  </div>

  <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Lab Cards -->
  <div class="labs-grid">
    <?php if (empty($labs)): ?>
    <div class="empty-state">
      <div class="empty-icon">🔬</div>
      <div style="font-size:1rem;font-weight:600;margin-bottom:.25rem">No labs affiliated yet</div>
      <div>Use the form above to add a laboratory by their Lab UID, registration number, or email.</div>
    </div>
    <?php else: ?>
    <?php foreach ($labs as $lab): ?>
    <?php
      $st = $lab['status'];
      $badgeCls = match($st) { 'APPROVED'=>'lb-approved','SUSPENDED'=>'lb-suspended','REMOVED'=>'lb-removed', default=>'lb-pending' };
    ?>
    <div class="lab-card">
      <div class="lab-card-head">
        <div class="lab-avatar">🔬</div>
        <div class="lab-main">
          <div class="lab-name"><?= htmlspecialchars($lab['lab_name']) ?></div>
          <div class="lab-uid"><?= htmlspecialchars($lab['lab_uid']) ?></div>
          <span class="lab-badge <?= $badgeCls ?>"><?= $st ?></span>
        </div>
      </div>
      <div class="lab-card-body">
        <div class="lab-meta-grid">
          <div class="meta-item"><strong>Registration</strong><?= htmlspecialchars($lab['registration_number']) ?></div>
          <div class="meta-item"><strong>City</strong><?= htmlspecialchars($lab['city'] ?: '—') ?></div>
          <div class="meta-item"><strong>Phone</strong><?= htmlspecialchars($lab['contact_phone'] ?: '—') ?></div>
          <div class="meta-item"><strong>Email</strong><?= htmlspecialchars($lab['user_email']) ?></div>
          <div class="meta-item"><strong>Affiliated</strong><?= $lab['approved_at'] ? date('M j, Y', strtotime($lab['approved_at'])) : '—' ?></div>
          <div class="meta-item"><strong>Last Login</strong><?= $lab['last_login_at'] ? date('M j', strtotime($lab['last_login_at'])) : 'Never' ?></div>
        </div>
        <?php if ($lab['services']): ?>
        <div class="lab-services">
          <?php foreach (explode(',', $lab['services']) as $svc): ?>
          <span><?= htmlspecialchars(trim($svc)) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="lab-stats">
          <div class="stat-pill"><div class="sv" style="color:#94a3b8"><?= $lab['total_orders'] ?></div><div class="sl">Total Orders</div></div>
          <div class="stat-pill"><div class="sv" style="color:#f59e0b"><?= $lab['pending_orders'] ?></div><div class="sl">Pending</div></div>
        </div>
      </div>
      <div class="lab-actions">
        <form method="POST" style="display:contents">
          <?= csrfField() ?>
          <input type="hidden" name="affiliation_id" value="<?= $lab['id'] ?>">
          <?php if ($st === 'APPROVED'): ?>
          <button type="submit" name="update_status" value="update_status" class="act-btn danger"
                  onclick="this.form.elements['new_status'].value='SUSPENDED';return confirm('Suspend this lab?')">⏸ Suspend</button>
          <button type="submit" name="update_status" value="update_status" class="act-btn danger"
                  onclick="this.form.elements['new_status'].value='REMOVED';return confirm('Remove this lab from your hospital?')">🗑 Remove</button>
          <?php elseif ($st === 'SUSPENDED'): ?>
          <button type="submit" name="update_status" value="update_status" class="act-btn success"
                  onclick="this.form.elements['new_status'].value='APPROVED'">✅ Reactivate</button>
          <button type="submit" name="update_status" value="update_status" class="act-btn danger"
                  onclick="this.form.elements['new_status'].value='REMOVED';return confirm('Remove?')">🗑 Remove</button>
          <?php else: ?>
          <button type="submit" name="update_status" value="update_status" class="act-btn success"
                  onclick="this.form.elements['new_status'].value='APPROVED'">✅ Restore</button>
          <?php endif; ?>
          <input type="hidden" name="new_status" value="">
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
