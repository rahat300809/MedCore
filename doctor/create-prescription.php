<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

requireDoctorHospitalContext();

$db         = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];
$patientId  = (int)($_GET['patient_id'] ?? 0);

if (!$patientId) {
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

// 1. Verify Active Session
$stmtSession = $db->prepare("
    SELECT s.*, TIMESTAMPDIFF(SECOND, NOW(), s.expires_at) AS seconds_remaining
    FROM access_sessions s
    WHERE s.doctor_id = ? AND s.patient_id = ? AND s.hospital_id = ?
      AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ORDER BY s.expires_at DESC
    LIMIT 1
");
$stmtSession->execute([$doctorId, $patientId, $hospitalId]);
$accessSession = $stmtSession->fetch();

if (!$accessSession) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Active consent required before prescribing medications.'];
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

// 2. Fetch Patient Profile
$stmtPatient = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmtPatient->execute([$patientId]);
$patient = $stmtPatient->fetch();
if (!$patient) {
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}
$age = date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y;

// 3. Fetch Patient Allergies for Warning
$stmtAllergies = $db->prepare("
    SELECT pa.*, a.name AS allergy_name
    FROM patient_allergies pa
    JOIN allergies a ON a.id = pa.allergy_id
    WHERE pa.patient_id = ?
");
$stmtAllergies->execute([$patientId]);
$allergies = $stmtAllergies->fetchAll();

// 4. Handle Prescription Submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prescription'])) {
    validateCsrf();

    $chiefComplaint = trim($_POST['chief_complaint'] ?? '');
    $diagnosis      = trim($_POST['diagnosis'] ?? '');
    $investigation  = trim($_POST['investigation'] ?? '');
    $advice         = trim($_POST['advice'] ?? '');
    $followUpDays   = (int)($_POST['follow_up_days'] ?? 0);
    $bp             = trim($_POST['bp'] ?? '');
    $pulse          = trim($_POST['pulse'] ?? '');
    $temp           = trim($_POST['temp'] ?? '');
    $weight         = trim($_POST['weight'] ?? '');

    $medNames       = $_POST['med_name'] ?? [];
    $medForms       = $_POST['med_form'] ?? [];
    $medStrengths   = $_POST['med_strength'] ?? [];
    $medDosages     = $_POST['med_dosage'] ?? [];
    $medFreqs       = $_POST['med_frequency'] ?? [];
    $medDurations   = $_POST['med_duration'] ?? [];
    $medInsts       = $_POST['med_instructions'] ?? [];

    if (empty($diagnosis)) {
        $error = 'Please provide a clinical diagnosis or observation.';
    } elseif (empty($medNames) || empty(trim($medNames[0] ?? ''))) {
        $error = 'Please prescribe at least one medicine.';
    } else {
        $db->beginTransaction();
        try {
            // A. Create Consultation
            $consUid = 'CONS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $clinicalNotes = "Vitals: BP: {$bp} mmHg | Pulse: {$pulse} bpm | Temp: {$temp} °F | Weight: {$weight} kg";
            $followUpDate = $followUpDays > 0 ? date('Y-m-d', strtotime("+{$followUpDays} days")) : null;

            $stmtCons = $db->prepare("
                INSERT INTO consultations
                    (consultation_uid, patient_id, doctor_id, hospital_id, department_id,
                     consultation_type, chief_complaint, clinical_notes, diagnosis_summary,
                     investigation, advice, follow_up_date)
                VALUES (?, ?, ?, ?, ?, 'IN_PERSON', ?, ?, ?, ?, ?, ?)
            ");
            $stmtCons->execute([
                $consUid, $patientId, $doctorId, $hospitalId,
                $_SESSION['department_id'] ?? null,
                $chiefComplaint, $clinicalNotes, $diagnosis,
                $investigation, $advice, $followUpDate
            ]);
            $consultationId = $db->lastInsertId();

            // B. Create Diagnosis Record
            $stmtDiag = $db->prepare("
                INSERT INTO diagnoses (consultation_id, diagnosis_name, diagnosis_type)
                VALUES (?, ?, 'PRIMARY')
            ");
            $stmtDiag->execute([$consultationId, $diagnosis]);

            // C. Create Prescription
            $rxUid = 'RX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $stmtRx = $db->prepare("
                INSERT INTO prescriptions
                    (prescription_uid, consultation_id, patient_id, doctor_id, hospital_id,
                     diagnosis, advice, follow_up_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')
            ");
            $stmtRx->execute([
                $rxUid, $consultationId, $patientId, $doctorId, $hospitalId,
                $diagnosis, $advice, $followUpDate
            ]);
            $prescriptionId = $db->lastInsertId();

            // D. Insert Prescription Medicines
            $stmtMedInsert = $db->prepare("
                INSERT INTO prescription_medicines
                    (prescription_id, medicine_name, strength, dosage, frequency, duration, instructions)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($medNames as $i => $name) {
                $name = trim($name);
                if ($name === '') continue;

                $form     = trim($medForms[$i] ?? 'Tablet');
                $strength = trim($medStrengths[$i] ?? '');
                $dosage   = trim($medDosages[$i] ?? '1');
                $freq     = trim($medFreqs[$i] ?? '1+0+1');
                $dur      = trim($medDurations[$i] ?? '7 days');
                $inst     = trim($medInsts[$i] ?? 'After meal');

                $stmtMedInsert->execute([
                    $prescriptionId, $name, $strength, "{$dosage} {$form}", $freq, $dur, $inst
                ]);
            }

            // E. Notify Patient
            $doctorName = $_SESSION['name'] ?? 'Doctor';
            $hospitalName = $_SESSION['hospital_name'] ?? 'Hospital';
            $db->prepare("
                INSERT INTO notifications
                    (user_id, type, title, message, reference_type, reference_id, action_url)
                SELECT u.id, 'NEW_PRESCRIPTION',
                       CONCAT('New Prescription from Dr. ', :dname),
                       CONCAT('Dr. ', :dname2, ' at ', :hname, ' has issued prescription #', :rx_uid),
                       'prescription', :rx_id, :action_url
                FROM patients p JOIN users u ON u.id = p.user_id
                WHERE p.id = :pid
            ")->execute([
                ':dname'      => $doctorName,
                ':dname2'     => $doctorName,
                ':hname'      => $hospitalName,
                ':rx_uid'     => $rxUid,
                ':rx_id'      => $prescriptionId,
                ':action_url' => APP_URL . '/patient/prescriptions.php',
                ':pid'        => $patientId,
            ]);

            // F. Log Audit
            AuditService::log(AuditService::CREATE_PRESCRIPTION, [
                'user_id'    => $_SESSION['user_id'],
                'doctor_id'  => $doctorId,
                'patient_id' => $patientId,
                'hospital_id'=> $hospitalId,
                'target_type'=> 'prescription',
                'target_id'  => $prescriptionId,
                'metadata'   => ['prescription_uid' => $rxUid, 'consultation_uid' => $consUid],
            ]);

            $db->commit();

            $_SESSION['flash'] = [
                'type' => 'success',
                'msg'  => "Prescription #{$rxUid} created and securely synced to patient record."
            ];
            header('Location: ' . APP_URL . '/doctor/prescriptions.php?rx=' . $rxUid);
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error saving prescription: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Write Prescription — MedCore Doctor Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal.css">
  <style>
    .med-row {
      background: #F8FAFC;
      border: 1px solid var(--mc-border);
      border-radius: var(--radius-lg);
      padding: var(--space-4);
      margin-bottom: var(--space-3);
      position: relative;
      transition: var(--transition-fast);
    }
    .med-row:hover {
      border-color: var(--mc-blue);
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .vitals-bar {
      background: #F1F5F9;
      border-radius: var(--radius-lg);
      padding: var(--space-4);
      margin-bottom: var(--space-6);
    }
  </style>
</head>
<body>

<div class="portal-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">M</div>
      <div>
        <div class="brand-name">MedCore</div>
        <div class="brand-sub">Doctor Portal</div>
      </div>
    </div>

    <div class="doctor-context-badge" style="margin: 0 var(--space-4) var(--space-4); padding: var(--space-3); background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-md);">
      <div style="font-size: 0.75rem; color: #1E40AF; font-weight: 700; text-transform: uppercase;">Active Practice Context</div>
      <div style="font-weight: 600; font-size: 0.88rem; color: #1E3A8A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($_SESSION['hospital_name']) ?></div>
      <div style="font-size: 0.78rem; color: #3B82F6;"><?= htmlspecialchars($_SESSION['department_name'] ?? 'General Practitioner') ?></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Clinical Care</div>
      <a href="<?= APP_URL ?>/doctor/dashboard.php" class="nav-item">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Dashboard</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/patient-search.php" class="nav-item">
        <i class="bi bi-search"></i>
        <span>Search Patient</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= $patientId ?>" class="nav-item">
        <i class="bi bi-heart-pulse-fill"></i>
        <span>Patient Record</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="nav-item active">
        <i class="bi bi-file-earmark-medical-fill"></i>
        <span>Write Prescription</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/consultations.php" class="nav-item">
        <i class="bi bi-chat-heart-fill"></i>
        <span>Consultations</span>
      </a>
      <a href="<?= APP_URL ?>/doctor/prescriptions.php" class="nav-item">
        <i class="bi bi-prescription2"></i>
        <span>Prescriptions</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'D', 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'Doctor') ?></div>
          <div class="user-role">BMDC Verified</div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="btn btn-outline btn-sm" style="width: 100%; margin-top: var(--space-3);">
        <i class="bi bi-box-arrow-right"></i> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="portal-main">
    <header class="portal-header">
      <div class="d-flex align-items-center gap-3">
        <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= $patientId ?>" class="btn btn-secondary btn-sm">
          <i class="bi bi-arrow-left"></i> Medical Record
        </a>
        <h1 class="portal-title" style="font-size: 1.25rem; margin: 0;">Digital Prescription Pad</h1>
      </div>
      <div class="header-actions">
        <span class="badge badge-success"><i class="bi bi-shield-check"></i> Session Active</span>
      </div>
    </header>

    <div class="portal-body" style="max-width: 1080px;">

      <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Patient Summary Card -->
      <div class="card mb-4" style="border-left: 4px solid var(--mc-blue);">
        <div class="card-body py-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div>
            <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 4px;"><?= htmlspecialchars($patient['full_name']) ?></h3>
            <div class="text-muted" style="font-size: 0.88rem;">
              ID: <strong><?= htmlspecialchars($patient['patient_uid']) ?></strong> &bull;
              <?= $age ?> yrs &bull; <?= ucfirst(strtolower($patient['gender'])) ?> &bull;
              Blood: <strong><?= htmlspecialchars($patient['blood_group'] ?? 'Unknown') ?></strong>
            </div>
          </div>
          <?php if (!empty($allergies)): ?>
            <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); font-size: 0.85rem; color: #991B1B;">
              <strong><i class="bi bi-exclamation-triangle-fill"></i> Known Allergies:</strong>
              <?php foreach ($allergies as $alg): ?>
                <span class="badge badge-danger" style="margin-left: 4px;"><?= htmlspecialchars($alg['allergy_name']) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Prescription Form -->
      <form method="POST" id="prescription-form">
        <?= csrfField() ?>
        <input type="hidden" name="save_prescription" value="1">

        <!-- 1. Vitals -->
        <div class="card mb-4">
          <div class="card-header"><h4 class="card-title" style="font-size: 1rem;"><i class="bi bi-speedometer2"></i> Clinical Vitals &amp; Patient Presentation</h4></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Blood Pressure (mmHg)</label>
                <input type="text" name="bp" class="form-control" placeholder="120/80">
              </div>
              <div class="col-md-3">
                <label class="form-label">Pulse (bpm)</label>
                <input type="number" name="pulse" class="form-control" placeholder="75">
              </div>
              <div class="col-md-3">
                <label class="form-label">Temperature (°F)</label>
                <input type="text" name="temp" class="form-control" placeholder="98.6">
              </div>
              <div class="col-md-3">
                <label class="form-label">Weight (kg)</label>
                <input type="number" step="0.1" name="weight" class="form-control" placeholder="68.5">
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Chief Complaints / Presenting Symptoms</label>
                <textarea name="chief_complaint" rows="2" class="form-control" placeholder="e.g. High fever for 3 days, dry cough, body aches"></textarea>
              </div>
              <div class="col-md-6 mt-3">
                <label class="form-label">Primary Diagnosis <span class="text-danger">*</span></label>
                <textarea name="diagnosis" rows="2" class="form-control" placeholder="e.g. Acute Viral Upper Respiratory Tract Infection" required></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- 2. Medicines / Rx -->
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title" style="font-size: 1rem;"><i class="bi bi-prescription2"></i> Prescribed Medicines (Rx)</h4>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addMedicineRow()">
              <i class="bi bi-plus-lg"></i> Add Medicine
            </button>
          </div>
          <div class="card-body">
            <div id="medicines-container">
              <!-- Medicine Row 1 -->
              <div class="med-row" id="med-row-0">
                <div class="row g-2 align-items-center">
                  <div class="col-md-3">
                    <label class="form-label" style="font-size: 0.8rem;">Medicine Name / Generic <span class="text-danger">*</span></label>
                    <input type="text" name="med_name[]" class="form-control form-control-sm" placeholder="e.g. Paracetamol / Napa Extra" required>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label" style="font-size: 0.8rem;">Form</label>
                    <select name="med_form[]" class="form-control form-control-sm">
                      <option value="Tablet">Tablet</option>
                      <option value="Capsule">Capsule</option>
                      <option value="Syrup">Syrup</option>
                      <option value="Suspension">Suspension</option>
                      <option value="Injection">Injection</option>
                      <option value="Inhaler">Inhaler</option>
                      <option value="Eye Drop">Eye Drop</option>
                      <option value="Ointment">Ointment</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label" style="font-size: 0.8rem;">Strength</label>
                    <input type="text" name="med_strength[]" class="form-control form-control-sm" placeholder="e.g. 500 mg">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label" style="font-size: 0.8rem;">Frequency</label>
                    <select name="med_frequency[]" class="form-control form-control-sm">
                      <option value="1+0+1 (Morning + Night)">1+0+1 (Morning + Night)</option>
                      <option value="1+1+1 (3 times daily)">1+1+1 (3 times daily)</option>
                      <option value="1+0+0 (Morning only)">1+0+0 (Morning only)</option>
                      <option value="0+0+1 (Night only)">0+0+1 (Night only)</option>
                      <option value="1+1+1+1 (4 times daily)">1+1+1+1 (4 times daily)</option>
                      <option value="SOS (As needed)">SOS (As needed)</option>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <label class="form-label" style="font-size: 0.8rem;">Duration</label>
                    <input type="text" name="med_duration[]" class="form-control form-control-sm" placeholder="e.g. 7 days" value="7 days">
                  </div>
                  <div class="col-md-1 text-end" style="padding-top: 18px;">
                    <button type="button" class="btn btn-outline text-danger btn-sm" onclick="removeMedicineRow(0)" title="Delete row">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                  <div class="col-12 mt-2">
                    <input type="text" name="med_instructions[]" class="form-control form-control-sm" placeholder="Instructions: e.g. After meal, with warm water">
                  </div>
                </div>
              </div>
            </div>

            <div class="text-center mt-3">
              <button type="button" class="btn btn-outline btn-sm" onclick="addMedicineRow()">
                <i class="bi bi-plus-circle"></i> Add Another Medicine
              </button>
            </div>
          </div>
        </div>

        <!-- 3. Investigations & Advice -->
        <div class="card mb-4">
          <div class="card-header"><h4 class="card-title" style="font-size: 1rem;"><i class="bi bi-clipboard2-check"></i> Investigations, Advice &amp; Follow-up</h4></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Investigations / Diagnostic Tests Recommended</label>
                <textarea name="investigation" rows="3" class="form-control" placeholder="e.g. CBC, Serum Creatinine, Chest X-Ray P/A view"></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label">Special Advice &amp; Dietary Guidelines</label>
                <textarea name="advice" rows="3" class="form-control" placeholder="e.g. Plenty of oral fluids, rest, avoid spicy and oily food"></textarea>
              </div>
              <div class="col-md-4">
                <label class="form-label">Follow-up After</label>
                <select name="follow_up_days" class="form-control">
                  <option value="0">No follow-up scheduled</option>
                  <option value="3">3 Days</option>
                  <option value="5">5 Days</option>
                  <option value="7" selected>7 Days</option>
                  <option value="14">14 Days (2 Weeks)</option>
                  <option value="30">1 Month</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Bar -->
        <div class="d-flex justify-content-between align-items-center mb-5">
          <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= $patientId ?>" class="btn btn-secondary">
            Cancel
          </a>
          <div class="d-flex gap-3">
            <button type="submit" class="btn btn-primary btn-lg" style="padding: 12px 32px;">
              <i class="bi bi-check2-circle"></i> Issue &amp; Sign Prescription
            </button>
          </div>
        </div>

      </form>
    </div>
  </main>
</div>

<script>
let medCount = 1;

function addMedicineRow() {
  const id = medCount++;
  const div = document.createElement('div');
  div.className = 'med-row';
  div.id = 'med-row-' + id;
  div.innerHTML = `
    <div class="row g-2 align-items-center">
      <div class="col-md-3">
        <label class="form-label" style="font-size: 0.8rem;">Medicine Name / Generic <span class="text-danger">*</span></label>
        <input type="text" name="med_name[]" class="form-control form-control-sm" placeholder="e.g. Amoxicillin / Moxaclav" required>
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size: 0.8rem;">Form</label>
        <select name="med_form[]" class="form-control form-control-sm">
          <option value="Tablet">Tablet</option>
          <option value="Capsule">Capsule</option>
          <option value="Syrup">Syrup</option>
          <option value="Suspension">Suspension</option>
          <option value="Injection">Injection</option>
          <option value="Inhaler">Inhaler</option>
          <option value="Eye Drop">Eye Drop</option>
          <option value="Ointment">Ointment</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size: 0.8rem;">Strength</label>
        <input type="text" name="med_strength[]" class="form-control form-control-sm" placeholder="e.g. 625 mg">
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size: 0.8rem;">Frequency</label>
        <select name="med_frequency[]" class="form-control form-control-sm">
          <option value="1+0+1 (Morning + Night)">1+0+1 (Morning + Night)</option>
          <option value="1+1+1 (3 times daily)">1+1+1 (3 times daily)</option>
          <option value="1+0+0 (Morning only)">1+0+0 (Morning only)</option>
          <option value="0+0+1 (Night only)">0+0+1 (Night only)</option>
          <option value="1+1+1+1 (4 times daily)">1+1+1+1 (4 times daily)</option>
          <option value="SOS (As needed)">SOS (As needed)</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" style="font-size: 0.8rem;">Duration</label>
        <input type="text" name="med_duration[]" class="form-control form-control-sm" placeholder="e.g. 5 days" value="5 days">
      </div>
      <div class="col-md-1 text-end" style="padding-top: 18px;">
        <button type="button" class="btn btn-outline text-danger btn-sm" onclick="removeMedicineRow(${id})" title="Delete row">
          <i class="bi bi-trash"></i>
        </button>
      </div>
      <div class="col-12 mt-2">
        <input type="text" name="med_instructions[]" class="form-control form-control-sm" placeholder="Instructions: e.g. After meal, full course complete">
      </div>
    </div>
  `;
  document.getElementById('medicines-container').appendChild(div);
}

function removeMedicineRow(id) {
  const row = document.getElementById('med-row-' + id);
  if (row) {
    const totalRows = document.querySelectorAll('.med-row').length;
    if (totalRows <= 1) {
      alert('Prescription must have at least one medicine row.');
      return;
    }
    row.remove();
  }
}
</script>

</body>
</html>
