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
:root{--accent:#0ea5e9;--border:rgba(255,255,255,0.1);--hosp:#7c3aed}
*{box-sizing:border-box}
body{background:#0f172a;font-family:'Inter',sans-serif;color:#f1f5f9;margin:0}
.nav{background:rgba(15,23,42,0.95);border-bottom:1px solid var(--border);padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:100;backdrop-filter:blur(16px)}
.nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
.nav-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--hosp),#0ea5e9);display:flex;align-items:center;justify-content:center;font-size:1.1rem}
.nav-title{font-size:1rem;font-weight:700;color:#f1f5f9}
.nav-subtitle{font-size:.7rem;color:#64748b}
.nav-right a{font-size:.8rem;color:#94a3b8;text-decoration:none;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:8px;padding:.35rem .75rem;margin-left:.5rem;transition:background .15s}
.nav-right a:hover{background:rgba(255,255,255,0.1)}
.page{max-width:1200px;margin:0 auto;padding:2rem 1.5rem}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
.page-header h1{font-size:1.5rem;font-weight:800;margin:0}
.page-header p{color:#94a3b8;font-size:.875rem;margin:.25rem 0 0}
/* ADD FORM */
.add-card{background:linear-gradient(135deg,rgba(99,102,241,0.1),rgba(14,165,233,0.1));border:1px solid rgba(14,165,233,0.2);border-radius:20px;padding:1.5rem;margin-bottom:2rem}
.add-card h2{font-size:1rem;font-weight:700;margin:0 0 1rem}
.add-form{display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap}
.add-form .form-group{flex:1;min-width:220px;margin:0}
label{display:block;color:#cbd5e1;font-size:.8125rem;font-weight:500;margin-bottom:.4rem}
input{width:100%;background:rgba(255,255,255,0.07);border:1px solid var(--border);border-radius:10px;padding:.7rem 1rem;color:#f1f5f9;font-size:.9rem;outline:none;transition:border-color .2s;box-sizing:border-box}
input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(14,165,233,0.15)}
.btn-add{background:linear-gradient(135deg,var(--accent),#6366f1);border:none;border-radius:10px;padding:.7rem 1.25rem;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:opacity .2s}
.btn-add:hover{opacity:.9}
/* ALERTS */
.alert{border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.875rem}
.alert-error{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);color:#fca5a5}
.alert-success{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.2);color:#86efac}
/* LAB CARDS */
.labs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:1.25rem}
.lab-card{background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:18px;overflow:hidden;transition:border-color .2s,transform .2s}
.lab-card:hover{border-color:rgba(255,255,255,0.18);transform:translateY(-2px)}
.lab-card-head{padding:1.25rem;display:flex;align-items:flex-start;gap:.875rem}
.lab-avatar{width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#0ea5e9);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0}
.lab-main{flex:1;min-width:0}
.lab-name{font-size:1rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lab-uid{font-size:.7rem;color:#64748b;font-family:monospace;margin-top:.1rem}
.lab-badge{font-size:.7rem;font-weight:600;border-radius:6px;padding:.2rem .55rem;margin-top:.35rem;display:inline-block}
.lb-approved{background:rgba(34,197,94,0.12);color:#4ade80;border:1px solid rgba(34,197,94,0.2)}
.lb-suspended{background:rgba(245,158,11,0.12);color:#fbbf24;border:1px solid rgba(245,158,11,0.25)}
.lb-removed{background:rgba(239,68,68,0.1);color:#f87171;border:1px solid rgba(239,68,68,0.2)}
.lb-pending{background:rgba(100,116,139,0.12);color:#94a3b8;border:1px solid rgba(100,116,139,0.2)}
.lab-card-body{padding:0 1.25rem 1rem;border-top:1px solid var(--border)}
.lab-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:.4rem .75rem;margin-top:.75rem}
.meta-item{font-size:.75rem;color:#64748b}
.meta-item strong{color:#94a3b8;display:block;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.1rem}
.lab-services{font-size:.75rem;color:#64748b;margin-top:.65rem;padding-top:.65rem;border-top:1px solid rgba(255,255,255,0.05)}
.lab-services span{display:inline-block;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:5px;padding:.15rem .45rem;margin:.15rem .1rem;font-size:.7rem;color:#94a3b8}
.lab-stats{display:flex;gap:.75rem;margin-top:.75rem}
.stat-pill{flex:1;text-align:center;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:10px;padding:.5rem}
.stat-pill .sv{font-size:1.1rem;font-weight:800}
.stat-pill .sl{font-size:.65rem;color:#64748b}
.lab-actions{padding:.875rem 1.25rem;border-top:1px solid rgba(255,255,255,0.05);display:flex;gap:.5rem;flex-wrap:wrap}
.act-btn{flex:1;font-size:.78rem;font-weight:500;border-radius:8px;padding:.45rem .75rem;text-align:center;border:1px solid var(--border);cursor:pointer;transition:all .15s;background:none;color:#94a3b8;font-family:inherit}
.act-btn:hover{background:rgba(255,255,255,0.08);color:#f1f5f9}
.act-btn.danger:hover{background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);color:#f87171}
.act-btn.success:hover{background:rgba(34,197,94,0.1);border-color:rgba(34,197,94,0.3);color:#4ade80}
.empty-state{grid-column:1/-1;text-align:center;padding:3rem;color:#475569}
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
