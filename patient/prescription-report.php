<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

// Allowed roles: PATIENT (owner), DOCTOR, HOSPITAL_ADMIN, SYSTEM_ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$db = getDB();
$rxId = (int)($_GET['id'] ?? 0);

if ($rxId <= 0) {
    header('Location: ' . APP_URL . '/patient/prescriptions.php');
    exit;
}

// Fetch Prescription details
$stmtRx = $db->prepare("
    SELECT rx.*,
           d.full_name AS doctor_name, d.specialization AS doctor_spec, d.qualification AS doctor_qual,
           d.medical_registration_id, d.doctor_uid,
           h.name AS hospital_name, h.registration_number AS hosp_reg, h.address AS hosp_addr,
           h.city AS hosp_city, h.phone AS hosp_phone, h.email AS hosp_email, h.type AS hosp_type,
           p.full_name AS patient_name, p.patient_uid, p.date_of_birth, p.gender, p.blood_group,
           p.emergency_contact_phone, p.id AS patient_db_id,
           c.chief_complaint, c.symptoms, c.investigation, c.clinical_notes, c.consultation_uid
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    JOIN patients p ON rx.patient_id = p.id
    LEFT JOIN consultations c ON rx.consultation_id = c.id
    WHERE rx.id = ?
");
$stmtRx->execute([$rxId]);
$rx = $stmtRx->fetch();

if (!$rx) {
    echo "Prescription not found.";
    exit;
}

// Authorization check
$userRole  = $_SESSION['role'];
$patientId = $rx['patient_db_id'];

if ($userRole === 'PATIENT') {
    if ((int)$_SESSION['patient_id'] !== $patientId) {
        http_response_code(403);
        exit("Unauthorized access to this prescription.");
    }
} elseif ($userRole === 'HOSPITAL_ADMIN') {
    $stmtH = $db->prepare("SELECT id FROM hospitals WHERE user_id = ?");
    $stmtH->execute([$_SESSION['user_id']]);
    $myHospId = (int)$stmtH->fetchColumn();
    if ($myHospId !== (int)$rx['hospital_id']) {
        http_response_code(403);
        exit("Unauthorized access.");
    }
}

// Fetch prescribed medicines
$stmtMeds = $db->prepare("
    SELECT pm.*, m.generic_name, m.brand_name, m.dosage_form
    FROM prescription_medicines pm
    LEFT JOIN medications m ON pm.medication_id = m.id
    WHERE pm.prescription_id = ?
    ORDER BY pm.id ASC
");
$stmtMeds->execute([$rxId]);
$medicines = $stmtMeds->fetchAll();

// Fetch patient allergy alerts
$stmtAllergies = $db->prepare("
    SELECT pa.severity, pa.reaction, a.name AS allergy_name
    FROM patient_allergies pa
    JOIN allergies a ON pa.allergy_id = a.id
    WHERE pa.patient_id = ?
    ORDER BY FIELD(pa.severity, 'LIFE_THREATENING','SEVERE','MODERATE','MILD')
");
$stmtAllergies->execute([$patientId]);
$allergies = $stmtAllergies->fetchAll();

// Fetch previous prescriptions for clean comprehensive history
$stmtPrev = $db->prepare("
    SELECT rx2.id, rx2.prescription_uid, rx2.created_at, rx2.diagnosis,
           d2.full_name AS doc_name, h2.name AS hosp_name,
           (SELECT COUNT(*) FROM prescription_medicines pm2 WHERE pm2.prescription_id = rx2.id) AS med_cnt
    FROM prescriptions rx2
    JOIN doctors d2 ON rx2.doctor_id = d2.id
    JOIN hospitals h2 ON rx2.hospital_id = h2.id
    WHERE rx2.patient_id = ? AND rx2.id != ?
    ORDER BY rx2.created_at DESC
    LIMIT 5
");
$stmtPrev->execute([$patientId, $rxId]);
$prevPrescriptions = $stmtPrev->fetchAll();

$age = date_diff(new DateTime($rx['date_of_birth']), new DateTime())->y;

AuditService::log('VIEW_PRESCRIPTION_REPORT', [
    'user_id'    => $_SESSION['user_id'],
    'patient_id' => $patientId,
    'metadata'   => ['prescription_uid' => $rx['prescription_uid']]
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prescription — <?= htmlspecialchars($rx['prescription_uid']) ?> — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <style>
    body {
      background: #F1F5F9;
      color: #0F172A;
      font-family: 'Inter', sans-serif;
      padding: 30px 16px;
    }
    .rx-paper {
      background: #fff;
      max-width: 820px;
      margin: 0 auto;
      border-radius: 16px;
      box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
      overflow: hidden;
      border: 1px solid #E2E8F0;
    }
    .rx-header {
      background: linear-gradient(135deg, #0F766E, #0D9488);
      color: #fff;
      padding: 28px 32px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 16px;
    }
    .rx-body {
      padding: 32px;
    }
    .patient-strip {
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 24px;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 12px;
    }
    .med-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 14px;
      font-size: 13px;
    }
    .med-table th {
      background: #F8FAFC;
      color: #475569;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: .5px;
      padding: 10px 12px;
      border-bottom: 2px solid #CBD5E1;
      text-align: left;
    }
    .med-table td {
      padding: 12px;
      border-bottom: 1px solid #E2E8F0;
      vertical-align: top;
    }
    .med-table tr:nth-child(even) {
      background: #FAFAFA;
    }
    .allergy-banner {
      background: #FEF2F2;
      border: 1.5px solid #FECACA;
      border-radius: 8px;
      padding: 10px 14px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      color: #991B1B;
      font-size: 12px;
      font-weight: 600;
    }
    .rx-symbol {
      font-family: serif;
      font-size: 32px;
      font-weight: 800;
      color: #0F766E;
      line-height: 1;
      margin-bottom: 8px;
    }
    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
      .rx-paper {
        border: none;
        box-shadow: none;
        max-width: 100%;
        border-radius: 0;
      }
      .rx-header {
        background: #0F766E !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
    }
  </style>
</head>
<body>

<div style="max-width: 820px; margin: 0 auto 16px; display: flex; justify-content: space-between; align-items: center;" class="no-print">
  <a href="javascript:history.back()" style="color: #475569; font-size: 13px; font-weight: 600; text-decoration: none;">
    <i class="bi bi-arrow-left"></i> Back
  </a>
  <div style="display: flex; gap: 10px;">
    <button onclick="window.print()" class="btn btn-primary btn-sm" style="background: #0F766E; border-color: #0F766E; font-weight: 700;">
      <i class="bi bi-printer-fill"></i> Print / Save as PDF
    </button>
  </div>
</div>

<div class="rx-paper">
  <!-- OFFICIAL HOSPITAL HEADER -->
  <div class="rx-header">
    <div>
      <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.9; font-weight: 700;">Accredited Medical Facility</div>
      <h1 style="font-size: 1.5rem; font-weight: 800; margin: 2px 0 6px 0;"><?= htmlspecialchars($rx['hospital_name']) ?></h1>
      <div style="font-size: 12px; opacity: 0.9; line-height: 1.5;">
        <?= htmlspecialchars($rx['hosp_addr'] ?: $rx['hosp_city']) ?>
        <?php if ($rx['hosp_phone']): ?> · Tel: <?= htmlspecialchars($rx['hosp_phone']) ?><?php endif; ?>
      </div>
      <div style="font-size: 11px; opacity: 0.8; font-family: 'JetBrains Mono', monospace; margin-top: 4px;">
        Facility License: <?= htmlspecialchars($rx['hosp_reg']) ?>
      </div>
    </div>

    <div style="text-align: right; min-width: 180px;">
      <div style="background: rgba(255,255,255,0.18); backdrop-filter: blur(4px); padding: 8px 14px; border-radius: 8px; display: inline-block;">
        <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">Official E-Prescription</div>
        <div style="font-size: 13px; font-family: 'JetBrains Mono', monospace; font-weight: 700;"><?= htmlspecialchars($rx['prescription_uid']) ?></div>
        <div style="font-size: 11px; opacity: 0.9;"><?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?></div>
      </div>
    </div>
  </div>

  <div class="rx-body">
    <!-- DOCTOR CREDENTIAL STRIP -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 16px; border-bottom: 2px solid #E2E8F0; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
      <div>
        <h2 style="font-size: 1.25rem; font-weight: 800; color: #0F172A; margin: 0;">
          Dr. <?= htmlspecialchars($rx['doctor_name']) ?>
        </h2>
        <div style="font-size: 13px; font-weight: 600; color: #0F766E; margin-top: 2px;">
          <?= htmlspecialchars($rx['doctor_spec'] ?? 'Medical Specialist') ?>
        </div>
        <div style="font-size: 12px; color: #475569; margin-top: 2px;">
          <?= htmlspecialchars($rx['doctor_qual'] ?? 'MBBS') ?>
        </div>
      </div>
      <div style="text-align: right; font-size: 12px; color: #475569;">
        <div>BMDC Reg No: <strong style="font-family: 'JetBrains Mono', monospace; color: #0F766E;"><?= htmlspecialchars($rx['medical_registration_id']) ?></strong></div>
        <div style="font-size: 11px; color: var(--mc-text-muted); margin-top: 2px;">Verified Central Physician</div>
      </div>
    </div>

    <!-- PATIENT IDENTIFICATION STRIP -->
    <div class="patient-strip">
      <div>
        <span style="font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase;">Patient Name</span>
        <div style="font-weight: 800; font-size: 14px; color: #0F172A;"><?= htmlspecialchars($rx['patient_name']) ?></div>
      </div>
      <div>
        <span style="font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase;">Patient UID</span>
        <div style="font-weight: 700; font-size: 13px; font-family: 'JetBrains Mono', monospace; color: #1E40AF;"><?= htmlspecialchars($rx['patient_uid']) ?></div>
      </div>
      <div>
        <span style="font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase;">Age / Gender</span>
        <div style="font-weight: 600; font-size: 13px;"><?= $age ?> yrs · <?= ucfirst(strtolower($rx['gender'])) ?></div>
      </div>
      <div>
        <span style="font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase;">Blood Group</span>
        <div style="font-weight: 700; font-size: 13px; color: #DC2626;"><?= htmlspecialchars($rx['blood_group'] ?: 'Unknown') ?></div>
      </div>
      <div>
        <span style="font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase;">Date Issued</span>
        <div style="font-weight: 600; font-size: 13px;"><?= date('d M Y', strtotime($rx['created_at'])) ?></div>
      </div>
    </div>

    <!-- DOCUMENTED ALLERGIES WARNING -->
    <?php if (!empty($allergies)): ?>
    <div class="allergy-banner">
      <i class="bi bi-exclamation-triangle-fill" style="font-size: 16px;"></i>
      <div>
        <strong>Patient Allergy Alert:</strong>
        <?php foreach ($allergies as $idx => $alg): ?>
          <?= htmlspecialchars($alg['allergy_name']) ?> (<?= htmlspecialchars($alg['severity']) ?>)<?= $idx < count($allergies)-1 ? ', ' : '' ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- CLINICAL DIAGNOSIS & REASON -->
    <div style="margin-bottom: 24px; padding: 14px 18px; background: #F0FDFA; border-left: 4px solid #0F766E; border-radius: 6px;">
      <div style="font-size: 11px; font-weight: 700; color: #0F766E; text-transform: uppercase; letter-spacing: .5px;">Clinical Impression / Diagnosis</div>
      <div style="font-size: 15px; font-weight: 800; color: #0F172A; margin-top: 4px;">
        <?= htmlspecialchars($rx['diagnosis'] ?: 'Clinical Assessment & Evaluation') ?>
      </div>
      <?php if ($rx['chief_complaint']): ?>
      <div style="font-size: 12px; color: #475569; margin-top: 4px;">
        <strong>Chief Complaints:</strong> <?= htmlspecialchars($rx['chief_complaint']) ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- RX SECTION -->
    <div style="margin-bottom: 28px;">
      <div class="rx-symbol">℞</div>
      <table class="med-table">
        <thead>
          <tr>
            <th style="width: 40px;">#</th>
            <th>Medicine Name &amp; Generic</th>
            <th>Strength / Form</th>
            <th>Dosage &amp; Schedule</th>
            <th>Instructions</th>
            <th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($medicines)): ?>
          <tr>
            <td colspan="6" style="text-align: center; color: var(--mc-text-muted); padding: 20px;">
              No medicines prescribed in this record.
            </td>
          </tr>
          <?php else: ?>
            <?php foreach ($medicines as $idx => $med): ?>
            <tr>
              <td style="font-weight: 700; color: #64748B;"><?= $idx + 1 ?></td>
              <td>
                <div style="font-weight: 800; font-size: 14px; color: #0F172A;"><?= htmlspecialchars($med['medicine_name']) ?></div>
                <?php if ($med['generic_name']): ?>
                  <div style="font-size: 11px; color: #64748B;"><?= htmlspecialchars($med['generic_name']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span style="font-weight: 600;"><?= htmlspecialchars($med['strength'] ?: '—') ?></span>
                <?php if ($med['dosage_form']): ?>
                  <div style="font-size: 11px; color: #64748B;"><?= htmlspecialchars($med['dosage_form']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight: 700; color: #0F766E; font-size: 13px;"><?= htmlspecialchars($med['dosage'] ?: '1+0+1') ?></div>
                <div style="font-size: 11px; color: #64748B;"><?= htmlspecialchars($med['frequency'] ?: 'Daily') ?></div>
              </td>
              <td>
                <div style="font-size: 12px; color: #334155;"><?= htmlspecialchars($med['instructions'] ?: 'After meals') ?></div>
                <div style="font-size: 10px; color: #64748B;">Route: <?= htmlspecialchars($med['route'] ?: 'ORAL') ?></div>
              </td>
              <td>
                <span class="badge" style="background: #E0F2FE; color: #0369A1; font-weight: 700;">
                  <?= htmlspecialchars($med['duration'] ?: '7 Days') ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ADVICE & LAB INVESTIGATIONS -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 28px;">
      <?php if ($rx['advice']): ?>
      <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 16px;">
        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748B; margin-bottom: 6px;">
          <i class="bi bi-chat-left-text"></i> Doctor's Advice
        </div>
        <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-line;">
          <?= htmlspecialchars($rx['advice']) ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($rx['investigation']): ?>
      <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 16px;">
        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748B; margin-bottom: 6px;">
          <i class="bi bi-eyedropper"></i> Recommended Tests
        </div>
        <div style="font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-line;">
          <?= htmlspecialchars($rx['investigation']) ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- FOLLOW UP & SIGNATURE -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 20px; border-top: 2px dashed #E2E8F0; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
      <div>
        <?php if ($rx['follow_up_date']): ?>
        <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 10px 16px; display: inline-block;">
          <div style="font-size: 11px; font-weight: 700; color: #065F46; text-transform: uppercase;">Next Follow-Up Consultation</div>
          <div style="font-size: 14px; font-weight: 800; color: #047857; margin-top: 2px;">
            <i class="bi bi-calendar-check-fill"></i> <?= date('l, F j, Y', strtotime($rx['follow_up_date'])) ?>
          </div>
        </div>
        <?php else: ?>
          <div style="font-size: 12px; color: #64748B;">Follow up as needed if symptoms persist.</div>
        <?php endif; ?>
      </div>

      <div style="text-align: right; min-width: 200px;">
        <div style="font-family: cursive; font-size: 18px; color: #0F766E; margin-bottom: 4px;">Dr. <?= htmlspecialchars($rx['doctor_name']) ?></div>
        <div style="border-top: 1px solid #94A3B8; padding-top: 4px; font-size: 11px; color: #64748B;">
          Digitally Signed via MedCore Security Token
        </div>
        <div style="font-size: 10px; font-family: 'JetBrains Mono', monospace; color: #94A3B8;">
          HASH: <?= strtoupper(substr(hash('sha256', $rx['prescription_uid'] . $rx['created_at']), 0, 16)) ?>
        </div>
      </div>
    </div>

    <!-- PREVIOUS CARE HISTORY ACCORDION (FOR PATIENT COMPREHENSIVE VIEW) -->
    <?php if (!empty($prevPrescriptions)): ?>
    <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #E2E8F0;">
      <div style="font-weight: 700; font-size: 13px; color: #475569; margin-bottom: 10px;">
        <i class="bi bi-clock-history"></i> Patient's Previous Prescriptions Summary
      </div>
      <div style="display: flex; flex-direction: column; gap: 8px;">
        <?php foreach ($prevPrescriptions as $prx): ?>
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; font-size: 12px;">
          <div>
            <span style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #0F766E;"><?= htmlspecialchars($prx['prescription_uid']) ?></span>
            &nbsp;·&nbsp; <?= date('d M Y', strtotime($prx['created_at'])) ?>
            &nbsp;·&nbsp; <strong><?= htmlspecialchars($prx['diagnosis'] ?: 'General Consultation') ?></strong>
            &nbsp;·&nbsp; <span style="color: #64748B;">Prescribed by Dr. <?= htmlspecialchars($prx['doc_name']) ?> at <?= htmlspecialchars($prx['hosp_name']) ?></span>
          </div>
          <a href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= $prx['id'] ?>" class="btn btn-outline btn-sm no-print" style="padding: 2px 8px; font-size: 11px;">
            View
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

</body>
</html>
