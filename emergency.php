<?php
require_once __DIR__ . '/app/config/app.php';
require_once __DIR__ . '/app/config/database.php';

$db = getDB();
$uid = trim($_GET['uid'] ?? $_GET['id'] ?? '');

$patient = null;
$allergies = [];
$conditions = [];

if (!empty($uid)) {
    // Normalize UID
    $cleanUid = (stripos($uid, 'PT-') === 0) ? strtoupper($uid) : ('PT-' . str_pad($uid, 6, '0', STR_PAD_LEFT));

    $stmt = $db->prepare("
        SELECT p.id, p.patient_uid, p.full_name, p.date_of_birth, p.gender, p.blood_group,
               p.emergency_contact_name, p.emergency_contact_phone, u.phone
        FROM patients p
        JOIN users u ON u.id = p.user_id
        WHERE p.patient_uid = ? OR p.patient_uid = ? OR u.phone = ?
        LIMIT 1
    ");
    $stmt->execute([$uid, $cleanUid, $uid]);
    $patient = $stmt->fetch();

    if ($patient) {
        $age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;

        // Fetch severe/critical allergies only
        $stmtAllergy = $db->prepare("
            SELECT pa.*, a.name AS allergy_name
            FROM patient_allergies pa
            JOIN allergies a ON a.id = pa.allergy_id
            WHERE pa.patient_id = ? AND pa.severity IN ('SEVERE', 'LIFE_THREATENING')
            ORDER BY pa.severity DESC
        ");
        $stmtAllergy->execute([$patient['id']]);
        $allergies = $stmtAllergy->fetchAll();

        // Fetch active critical medical conditions
        $stmtHist = $db->prepare("
            SELECT condition_name, status, notes
            FROM medical_histories
            WHERE patient_id = ? AND status = 'ACTIVE'
            ORDER BY id DESC
        ");
        $stmtHist->execute([$patient['id']]);
        $conditions = $stmtHist->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Emergency ICE Health Profile — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    body {
      background: #0F172A;
      color: #F8FAFC;
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      margin: 0;
    }
    .ice-card {
      background: #1E293B;
      border: 2px solid #EF4444;
      border-radius: 20px;
      max-width: 540px;
      width: 100%;
      padding: 32px 24px;
      box-shadow: 0 12px 36px rgba(239, 68, 68, 0.2);
    }
    .ice-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      padding-bottom: 18px;
      margin-bottom: 24px;
    }
    .blood-badge {
      background: #EF4444;
      color: white;
      font-size: 1.8rem;
      font-weight: 900;
      padding: 8px 18px;
      border-radius: 12px;
      box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .vital-tile {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 12px;
    }
    .alert-chip {
      background: #7F1D1D;
      border: 1px solid #DC2626;
      color: #FCA5A5;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .btn-call {
      background: #10B981;
      color: white;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      padding: 12px 20px;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      box-shadow: 0 4px 12px rgba(16,185,129,0.3);
      transition: background 0.2s;
    }
    .btn-call:hover { background: #059669; }
  </style>
</head>
<body>

  <div class="ice-card">
    <div class="ice-header">
      <div style="display:flex; align-items:center; gap:12px;">
        <div style="width:40px; height:40px; border-radius:10px; background:#EF4444; color:white; display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
          <i class="bi bi-heart-pulse-fill"></i>
        </div>
        <div>
          <h2 style="font-size:1.2rem; font-weight:900; margin:0; letter-spacing:0.5px;">EMERGENCY ICE PROFILE</h2>
          <div style="font-size:11px; color:#94A3B8; text-transform:uppercase;">Life-Safety Resuscitation Data</div>
        </div>
      </div>
      <span style="font-size:11px; background:rgba(239,68,68,0.2); color:#FCA5A5; padding:4px 10px; border-radius:99px; font-weight:700;">
        MEDCORE VERIFIED
      </span>
    </div>

    <?php if (!$patient): ?>
    <div style="text-align:center; padding:32px 16px;">
      <i class="bi bi-search" style="font-size:2.5rem; color:#64748B;"></i>
      <h3 style="font-size:1.1rem; margin:14px 0 6px;">Patient Identifier Required</h3>
      <p style="font-size:13px; color:#94A3B8; margin-bottom:20px;">Scan patient QR code or pass UID in the link (e.g. ?uid=PT-000001).</p>
      <form method="GET" style="display:flex; gap:8px;">
        <input type="text" name="uid" class="form-control" placeholder="Enter PT-000001 or Phone" style="background:#0F172A; color:white; border-color:#334155;" required>
        <button type="submit" class="btn btn-primary btn-sm">Lookup</button>
      </form>
    </div>
    <?php else: ?>

    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap; gap:16px;">
      <div>
        <div style="font-size:1.35rem; font-weight:800; color:white;"><?= htmlspecialchars($patient['full_name']) ?></div>
        <div style="font-size:13px; color:#94A3B8; margin-top:2px;">
          <?= htmlspecialchars($patient['patient_uid']) ?> · <?= $age ?> Yrs · <?= htmlspecialchars($patient['gender']) ?>
        </div>
      </div>
      <div class="blood-badge">
        <i class="bi bi-droplet-fill" style="font-size:1.2rem;"></i>
        <?= htmlspecialchars($patient['blood_group'] ?: 'UNKNOWN') ?>
      </div>
    </div>

    <!-- SEVERE DRUG ALLERGIES ALERT -->
    <div class="vital-tile" style="border-left:4px solid #EF4444;">
      <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#FCA5A5; letter-spacing:0.8px;">
        <i class="bi bi-exclamation-triangle-fill"></i> Critical / Severe Drug Allergies
      </div>
      <?php if (empty($allergies)): ?>
        <div style="font-size:13px; color:#94A3B8; margin-top:6px;">No severe allergies documented on file.</div>
      <?php else: ?>
        <?php foreach ($allergies as $alg): ?>
        <div class="alert-chip">
          <i class="bi bi-shield-x"></i>
          <div>
            <strong><?= htmlspecialchars($alg['allergy_name']) ?></strong>
            <span style="font-size:11px; opacity:0.85; margin-left:6px;">(<?= htmlspecialchars($alg['severity']) ?>)</span>
            <?php if (!empty($alg['reaction'])): ?>
              <div style="font-size:11px; font-weight:400;"><?= htmlspecialchars($alg['reaction']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- CHRONIC CONDITIONS -->
    <div class="vital-tile" style="border-left:4px solid #38BDF8;">
      <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#38BDF8; letter-spacing:0.8px;">
        <i class="bi bi-clipboard2-pulse-fill"></i> Documented Chronic Conditions
      </div>
      <?php if (empty($conditions)): ?>
        <div style="font-size:13px; color:#94A3B8; margin-top:6px;">None recorded.</div>
      <?php else: ?>
        <ul style="margin:6px 0 0 18px; padding:0; font-size:13px; color:#E2E8F0;">
          <?php foreach ($conditions as $c): ?>
            <li><strong><?= htmlspecialchars($c['condition_name']) ?></strong> <?= !empty($c['notes']) ? '— ' . htmlspecialchars($c['notes']) : '' ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- EMERGENCY CONTACT -->
    <div class="vital-tile" style="border-left:4px solid #10B981; margin-bottom:24px;">
      <div style="font-size:11px; font-weight:800; text-transform:uppercase; color:#6EE7B7; letter-spacing:0.8px; margin-bottom:6px;">
        <i class="bi bi-telephone-inbound-fill"></i> Primary Emergency Contact
      </div>
      <div style="font-size:14px; color:white; font-weight:700;">
        <?= htmlspecialchars($patient['emergency_contact_name'] ?: 'Family / Next of Kin') ?>
      </div>
      <div style="font-size:13px; color:#94A3B8; margin-bottom:12px;">
        Phone: <?= htmlspecialchars($patient['emergency_contact_phone'] ?: ($patient['phone'] ?: 'N/A')) ?>
      </div>
      <?php if (!empty($patient['emergency_contact_phone'])): ?>
        <a href="tel:<?= htmlspecialchars($patient['emergency_contact_phone']) ?>" class="btn-call">
          <i class="bi bi-telephone-fill"></i> Call Emergency Contact Now
        </a>
      <?php endif; ?>
    </div>

    <div style="text-align:center; font-size:11px; color:#64748B;">
      <i class="bi bi-shield-lock"></i> Detailed clinical history protected under patient cryptographic consent.
    </div>

    <?php endif; ?>
  </div>

</body>
</html>

