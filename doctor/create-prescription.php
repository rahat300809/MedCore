<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/includes/session_bar.php';

requireDoctorHospitalContext();

$db         = getDB();
$doctorId   = (int)$_SESSION['doctor_id'];
$hospitalId = (int)$_SESSION['hospital_id'];
$patientId  = (int)($_GET['patient_id'] ?? $_GET['patient'] ?? 0);

if (!$patientId) {
    header('Location: ' . APP_URL . '/doctor/patient-search.php');
    exit;
}

// 1. Verify Active Session
$stmtSession = $db->prepare("
    SELECT s.*
    FROM access_sessions s
    WHERE s.doctor_id = ? AND s.patient_id = ? AND s.hospital_id = ?
      AND s.status = 'ACTIVE' AND s.expires_at > NOW()
    ORDER BY s.expires_at DESC
    LIMIT 1
");
$stmtSession->execute([$doctorId, $patientId, $hospitalId]);
$accessSession = $stmtSession->fetch();
if ($accessSession) {
    $accessSession['seconds_remaining'] = max(0, strtotime($accessSession['expires_at']) - time());
}

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
$patientClinicalSummary = getPatientExecutiveClinicalSummary($db, $patientId);

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

    // Lab tests
    $labTestNames   = array_filter(array_map('trim', $_POST['lab_test_name'] ?? []));
    $labTestCats    = $_POST['lab_test_category'] ?? [];
    $labTestUrgency = $_POST['lab_test_urgency'] ?? [];
    $labTestInst    = $_POST['lab_test_instructions'] ?? [];
    $labTestNotes   = trim($_POST['lab_test_notes'] ?? '');
    $hasLabTests    = !empty($labTestNames);

    $hasMedicines   = !empty($medNames) && !empty(trim($medNames[0] ?? ''));

    if (empty($diagnosis)) {
        $error = 'Please provide a clinical diagnosis or observation.';
    } elseif (!$hasMedicines && !$hasLabTests) {
        $error = 'Please prescribe at least one medicine or order at least one lab test.';
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

            // E. Generate Lab Token & Create Lab Order (if lab tests prescribed)
            $labToken = null;
            if ($hasLabTests) {
                // Generate short unique token: LT- + 5 uppercase alphanumeric chars
                do {
                    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                    $rand  = '';
                    for ($k = 0; $k < 5; $k++) $rand .= $chars[random_int(0, strlen($chars)-1)];
                    $labToken = 'LT-' . $rand;
                    $stmtTok = $db->prepare("SELECT id FROM prescription_lab_orders WHERE lab_token = ?");
                    $stmtTok->execute([$labToken]);
                } while ($stmtTok->fetch()); // retry if collision

                $labExpiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                $db->prepare("
                    INSERT INTO prescription_lab_orders
                        (lab_token, prescription_id, patient_id, doctor_id, hospital_id,
                         test_notes, status, expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?)
                ")->execute([
                    $labToken, $prescriptionId, $patientId, $doctorId, $hospitalId, $labTestNotes, $labExpiresAt
                ]);
                $labOrderId = $db->lastInsertId();

                $stmtLabTest = $db->prepare("
                    INSERT INTO lab_order_tests
                        (lab_order_id, test_name, test_category, urgency, instructions)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($labTestNames as $ti => $tname) {
                    $stmtLabTest->execute([
                        $labOrderId,
                        $tname,
                        trim($labTestCats[$ti] ?? ''),
                        in_array($labTestUrgency[$ti] ?? '', ['ROUTINE','URGENT','STAT']) ? $labTestUrgency[$ti] : 'ROUTINE',
                        trim($labTestInst[$ti] ?? '')
                    ]);
                }

                AuditService::log(AuditService::LAB_ORDER_CREATED, [
                    'user_id'    => $_SESSION['user_id'],
                    'doctor_id'  => $doctorId,
                    'patient_id' => $patientId,
                    'hospital_id'=> $hospitalId,
                    'metadata'   => ['lab_token' => $labToken, 'prescription_uid' => $rxUid, 'test_count' => count($labTestNames)],
                ]);
            }

            // F. Notify Patient
            $doctorName = $_SESSION['name'] ?? 'Doctor';
            $hospitalName = $_SESSION['hospital_name'] ?? 'Hospital';
            $labTokenMsg = $labToken ? " Lab Token: {$labToken}" : '';
            $db->prepare("
                INSERT INTO notifications
                    (user_id, type, title, message, reference_type, reference_id, action_url)
                SELECT u.id, 'NEW_PRESCRIPTION',
                       CONCAT('New Prescription from Dr. ', :dname),
                       CONCAT('Dr. ', :dname2, ' at ', :hname, ' has issued prescription #', :rx_uid, :lab_msg),
                       'prescription', :rx_id, :action_url
                FROM patients p JOIN users u ON u.id = p.user_id
                WHERE p.id = :pid
            ")->execute([
                ':dname'      => $doctorName,
                ':dname2'     => $doctorName,
                ':hname'      => $hospitalName,
                ':rx_uid'     => $rxUid,
                ':lab_msg'    => $labTokenMsg,
                ':rx_id'      => $prescriptionId,
                ':action_url' => APP_URL . '/patient/prescriptions.php',
                ':pid'        => $patientId,
            ]);

            // G. Log Audit
            AuditService::log(AuditService::CREATE_PRESCRIPTION, [
                'user_id'    => $_SESSION['user_id'],
                'doctor_id'  => $doctorId,
                'patient_id' => $patientId,
                'hospital_id'=> $hospitalId,
                'target_type'=> 'prescription',
                'target_id'  => $prescriptionId,
                'metadata'   => ['prescription_uid' => $rxUid, 'consultation_uid' => $consUid, 'lab_token' => $labToken],
            ]);

            $db->commit();

            $flashMsg = "Prescription #{$rxUid} created and securely synced to patient record.";
            if ($labToken) {
                $flashMsg .= " Lab Token: <strong>{$labToken}</strong> — patient can show this at any affiliated lab.";
            }
            $_SESSION['flash'] = ['type' => 'success', 'msg' => $flashMsg];
            header('Location: ' . APP_URL . '/doctor/prescriptions.php?rx=' . $rxUid);
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error saving prescription: ' . $e->getMessage();
        }
    }
}
require_once __DIR__ . '/../app/views/portal/navs.php';
$waitingQueueCount = mc_doctor_shell_setup($db, $doctorId, $hospitalId, $accessSession, 'create-prescription');
$portalRole = 'Doctor';
$portalRoleBn = 'Clinical portal';
$portalAccent = '#0f766e';
$portalUser = $_SESSION['name'] ?? 'Doctor';
$portalMeta = ($_SESSION['hospital_name'] ?? '') . ' · Write Rx';
$portalTitle = 'Digital prescription pad';
$portalSubtitle = 'Patient: ' . ($patient['full_name'] ?? '') . ' (' . ($patient['patient_uid'] ?? '') . ')';
$portalNav = mc_doctor_nav('prescriptions', $waitingQueueCount);
$portalTopAction = '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/doctor/medical-record.php?patient_id=' . $patientId . '"><i class="bi bi-arrow-left"></i> Medical record</a>';
$extraHead = <<<'CSS'
<style>
  .med-row {
    background:#fafbfc;border:1px solid var(--line);border-radius:12px;padding:.9rem;margin-bottom:.75rem;
  }
  .med-row:hover { border-color:var(--accent); }
  .rx-row-grid { display:grid; gap:.65rem; grid-template-columns:1fr; align-items:end; }
  @media (min-width:760px) {
    .rx-row-grid { grid-template-columns:2fr 1fr 1fr 1.5fr 1fr auto; }
  }
  .lab-row-grid { display:grid; gap:.65rem; grid-template-columns:1fr; align-items:end; }
  @media (min-width:760px) {
    .lab-row-grid { grid-template-columns:2fr 1.3fr 1fr 1.3fr auto; }
  }
</style>
CSS;
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($error): ?>
  <div class="mc-alert mc-alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($patientClinicalSummary)): ?>
  <details class="mc-panel" style="padding:0;overflow:hidden">
    <summary style="padding:.95rem 1.15rem;font-weight:800;color:var(--accent);cursor:pointer;display:flex;align-items:center;justify-content:space-between;user-select:none">
      <span><i class="bi bi-stars"></i> Quick clinical history: past diagnoses, medicines &amp; lab tests</span>
      <span style="font-size:.82rem">Toggle reference</span>
    </summary>
    <div style="padding:1rem;border-top:1px solid var(--line)">
      <?php renderExecutiveClinicalSummary($patientClinicalSummary, true); ?>
    </div>
  </details>
<?php endif; ?>

<div class="mc-panel" style="border-left:4px solid var(--accent)">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
    <div>
      <h2 style="font-size:1.15rem;margin:0 0 .25rem"><?= htmlspecialchars($patient['full_name']) ?></h2>
      <div style="font-size:.86rem;color:var(--muted)">
        ID <strong class="mc-mono"><?= htmlspecialchars($patient['patient_uid']) ?></strong>
        · <?= $age ?> yrs · <?= ucfirst(strtolower($patient['gender'])) ?>
        · Blood <strong style="color:var(--danger)"><?= htmlspecialchars($patient['blood_group'] ?? 'Unknown') ?></strong>
      </div>
    </div>
    <?php if (!empty($allergies)): ?>
      <div style="background:var(--danger-soft);border:1px solid #fecdca;border-radius:10px;padding:.55rem .8rem;font-size:.84rem;color:var(--danger);display:flex;flex-wrap:wrap;gap:.35rem;align-items:center">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> Known allergies:</strong>
        <?php foreach ($allergies as $alg): ?>
          <span class="lab-status danger"><?= htmlspecialchars($alg['allergy_name']) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<form method="POST" id="prescription-form">
  <?= csrfField() ?>
  <input type="hidden" name="save_prescription" value="1">

  <div class="mc-panel">
    <div class="mc-panel-h"><h2><i class="bi bi-speedometer2"></i> Clinical vitals &amp; presentation</h2></div>
    <div class="mc-form-grid-4">
      <label class="mc-field" style="margin:0"><span>Blood pressure (mmHg)</span><input type="text" name="bp" placeholder="120/80"></label>
      <label class="mc-field" style="margin:0"><span>Pulse (bpm)</span><input type="number" name="pulse" placeholder="75"></label>
      <label class="mc-field" style="margin:0"><span>Temperature (°F)</span><input type="text" name="temp" placeholder="98.6"></label>
      <label class="mc-field" style="margin:0"><span>Weight (kg)</span><input type="number" step="0.1" name="weight" placeholder="68.5"></label>
    </div>
    <div class="mc-form-grid" style="margin-top:.85rem">
      <label class="mc-field" style="margin:0">
        <span>Chief complaints / symptoms</span>
        <textarea name="chief_complaint" rows="2" placeholder="e.g. High fever for 3 days, dry cough, body aches"></textarea>
      </label>
      <label class="mc-field" style="margin:0">
        <span>Primary diagnosis *</span>
        <textarea name="diagnosis" rows="2" placeholder="e.g. Acute Viral Upper Respiratory Tract Infection" required></textarea>
      </label>
    </div>
  </div>

  <div class="mc-panel">
    <div class="mc-panel-h">
      <h2><i class="bi bi-prescription2"></i> Prescribed medicines (Rx)</h2>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addMedicineRow()"><i class="bi bi-plus-lg"></i> Add medicine</button>
    </div>
    <div id="medicines-container">
      <div class="med-row" id="med-row-0">
        <div class="rx-row-grid">
          <label class="mc-field" style="margin:0">
            <span>Medicine / Generic *</span>
            <input type="text" name="med_name[]" placeholder="e.g. Paracetamol / Napa Extra" required>
          </label>
          <label class="mc-field" style="margin:0">
            <span>Form</span>
            <select name="med_form[]">
              <option value="Tablet">Tablet</option>
              <option value="Capsule">Capsule</option>
              <option value="Syrup">Syrup</option>
              <option value="Suspension">Suspension</option>
              <option value="Injection">Injection</option>
              <option value="Inhaler">Inhaler</option>
              <option value="Eye Drop">Eye Drop</option>
              <option value="Ointment">Ointment</option>
            </select>
          </label>
          <label class="mc-field" style="margin:0">
            <span>Strength</span>
            <input type="text" name="med_strength[]" placeholder="e.g. 500 mg">
          </label>
          <label class="mc-field" style="margin:0">
            <span>Frequency</span>
            <select name="med_frequency[]">
              <option value="1+0+1 (Morning + Night)">1+0+1 (Morning + Night)</option>
              <option value="1+1+1 (3 times daily)">1+1+1 (3 times daily)</option>
              <option value="1+0+0 (Morning only)">1+0+0 (Morning only)</option>
              <option value="0+0+1 (Night only)">0+0+1 (Night only)</option>
              <option value="1+1+1+1 (4 times daily)">1+1+1+1 (4 times daily)</option>
              <option value="SOS (As needed)">SOS (As needed)</option>
            </select>
          </label>
          <label class="mc-field" style="margin:0">
            <span>Duration</span>
            <input type="text" name="med_duration[]" placeholder="e.g. 7 days" value="7 days">
          </label>
          <button type="button" class="mc-btn mc-btn-ghost mc-btn-sm" onclick="removeMedicineRow(0)" title="Delete row" style="color:var(--danger)">
            <i class="bi bi-trash"></i>
          </button>
        </div>
        <label class="mc-field" style="margin-top:.6rem">
          <span>Instructions</span>
          <input type="text" name="med_instructions[]" placeholder="e.g. After meal, with warm water">
        </label>
      </div>
    </div>
    <button type="button" class="mc-btn mc-btn-soft mc-btn-sm" onclick="addMedicineRow()"><i class="bi bi-plus-circle"></i> Add another medicine</button>
  </div>

  <div class="mc-panel">
    <div class="mc-panel-h"><h2><i class="bi bi-clipboard2-check"></i> Investigations, advice &amp; follow-up</h2></div>
    <div class="mc-form-grid">
      <label class="mc-field" style="margin:0">
        <span>Investigations / diagnostic notes</span>
        <textarea name="investigation" rows="3" placeholder="e.g. CBC, Serum Creatinine, Chest X-Ray P/A view"></textarea>
      </label>
      <label class="mc-field" style="margin:0">
        <span>Special advice &amp; dietary guidelines</span>
        <textarea name="advice" rows="3" placeholder="e.g. Plenty of oral fluids, rest, avoid spicy and oily food"></textarea>
      </label>
    </div>
    <label class="mc-field" style="max-width:260px">
      <span>Follow-up after</span>
      <select name="follow_up_days">
        <option value="0">No follow-up scheduled</option>
        <option value="3">3 Days</option>
        <option value="5">5 Days</option>
        <option value="7" selected>7 Days</option>
        <option value="14">14 Days (2 Weeks)</option>
        <option value="30">1 Month</option>
      </select>
    </label>
  </div>

  <div class="mc-panel" style="border-color:#bae6fd;background:#f8fcff">
    <div class="mc-panel-h">
      <h2><i class="bi bi-eyedropper" style="color:var(--accent-2)"></i> Lab tests &amp; token (optional)</h2>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addLabTestRow()"><i class="bi bi-plus-lg"></i> Add test</button>
    </div>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin-bottom:.85rem">
      <span style="font-size:.8rem;font-weight:800;color:var(--muted)">Quick presets:</span>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('MRI of Brain', 'Radiology', 'ROUTINE', 'Plain and contrast study')">Brain MRI</button>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('Chest X-Ray P/A View', 'Radiology', 'ROUTINE', '')">Chest X-Ray</button>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('Complete Blood Count (CBC)', 'Haematology', 'ROUTINE', '')">CBC</button>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('Fasting Blood Sugar (FBS)', 'Biochemistry', 'ROUTINE', '10-12 hrs fasting')">FBS</button>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('HbA1c', 'Endocrinology', 'ROUTINE', '')">HbA1c</button>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('Lipid Profile', 'Biochemistry', 'ROUTINE', '12 hrs fasting')">Lipid Profile</button>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('Serum Creatinine', 'Biochemistry', 'ROUTINE', '')">Creatinine</button>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('USG of Whole Abdomen', 'Radiology', 'ROUTINE', 'Full bladder')">USG Abdomen</button>
      <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="addPresetTest('12-Lead ECG', 'Cardiac', 'ROUTINE', '')">ECG</button>
    </div>

    <div id="lab-tests-container"></div>
    <div id="lab-tests-placeholder" style="text-align:center;padding:1.25rem;color:var(--muted);font-size:.88rem;border:1px dashed var(--line);border-radius:10px;background:#fff">
      Click <strong>Add test</strong> or select a quick preset above to generate an automatic <strong>Lab Token</strong> on the prescription.
    </div>
    <label class="mc-field" id="lab-notes-wrap" style="display:none">
      <span>Special notes for the lab</span>
      <textarea name="lab_test_notes" rows="2" placeholder="e.g. Patient is fasting. Please collect early morning sample."></textarea>
    </label>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap">
    <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= $patientId ?>" class="mc-btn mc-btn-secondary">Cancel</a>
    <button type="submit" class="mc-btn mc-btn-primary mc-btn-lg"><i class="bi bi-check2-circle"></i> Issue &amp; sign prescription</button>
  </div>
</form>

<script>
let medCount = 1;
let labTestCount = 0;

function addMedicineRow() {
  const id = medCount++;
  const div = document.createElement('div');
  div.className = 'med-row';
  div.id = 'med-row-' + id;
  div.innerHTML = `
    <div class="rx-row-grid">
      <label class="mc-field" style="margin:0">
        <span>Medicine / Generic *</span>
        <input type="text" name="med_name[]" placeholder="e.g. Amoxicillin / Moxaclav" required>
      </label>
      <label class="mc-field" style="margin:0">
        <span>Form</span>
        <select name="med_form[]">
          <option value="Tablet">Tablet</option>
          <option value="Capsule">Capsule</option>
          <option value="Syrup">Syrup</option>
          <option value="Suspension">Suspension</option>
          <option value="Injection">Injection</option>
          <option value="Inhaler">Inhaler</option>
          <option value="Eye Drop">Eye Drop</option>
          <option value="Ointment">Ointment</option>
        </select>
      </label>
      <label class="mc-field" style="margin:0">
        <span>Strength</span>
        <input type="text" name="med_strength[]" placeholder="e.g. 625 mg">
      </label>
      <label class="mc-field" style="margin:0">
        <span>Frequency</span>
        <select name="med_frequency[]">
          <option value="1+0+1 (Morning + Night)">1+0+1 (Morning + Night)</option>
          <option value="1+1+1 (3 times daily)">1+1+1 (3 times daily)</option>
          <option value="1+0+0 (Morning only)">1+0+0 (Morning only)</option>
          <option value="0+0+1 (Night only)">0+0+1 (Night only)</option>
          <option value="1+1+1+1 (4 times daily)">1+1+1+1 (4 times daily)</option>
          <option value="SOS (As needed)">SOS (As needed)</option>
        </select>
      </label>
      <label class="mc-field" style="margin:0">
        <span>Duration</span>
        <input type="text" name="med_duration[]" placeholder="e.g. 5 days" value="5 days">
      </label>
      <button type="button" class="mc-btn mc-btn-ghost mc-btn-sm" onclick="removeMedicineRow(${id})" title="Delete row" style="color:var(--danger)">
        <i class="bi bi-trash"></i>
      </button>
    </div>
    <label class="mc-field" style="margin-top:.6rem">
      <span>Instructions</span>
      <input type="text" name="med_instructions[]" placeholder="e.g. After meal, complete full course">
    </label>
  `;
  document.getElementById('medicines-container').appendChild(div);
}

function removeMedicineRow(id) {
  const row = document.getElementById('med-row-' + id);
  if (row) {
    if (document.querySelectorAll('.med-row').length <= 1) {
      alert('Prescription must have at least one medicine row.');
      return;
    }
    row.remove();
  }
}

function addLabTestRow() {
  const id = labTestCount++;
  document.getElementById('lab-tests-placeholder').style.display = 'none';
  document.getElementById('lab-notes-wrap').style.display = 'block';
  const div = document.createElement('div');
  div.className = 'lab-test-row med-row';
  div.id = 'lab-test-row-' + id;
  div.style.background = '#fff';
  div.innerHTML = `
    <div class="lab-row-grid">
      <label class="mc-field" style="margin:0">
        <span>Test name *</span>
        <input type="text" name="lab_test_name[]" placeholder="e.g. CBC, HbA1c, Chest X-Ray" required>
      </label>
      <label class="mc-field" style="margin:0">
        <span>Category</span>
        <select name="lab_test_category[]">
          <option value="">— Select —</option>
          <option value="Haematology">Haematology</option>
          <option value="Biochemistry">Biochemistry</option>
          <option value="Microbiology">Microbiology</option>
          <option value="Radiology">Radiology</option>
          <option value="Urine Analysis">Urine Analysis</option>
          <option value="Histopathology">Histopathology</option>
          <option value="Serology">Serology</option>
          <option value="Cardiac">Cardiac</option>
          <option value="Endocrinology">Endocrinology</option>
          <option value="Other">Other</option>
        </select>
      </label>
      <label class="mc-field" style="margin:0">
        <span>Urgency</span>
        <select name="lab_test_urgency[]">
          <option value="ROUTINE">Routine</option>
          <option value="URGENT">Urgent</option>
          <option value="STAT">STAT</option>
        </select>
      </label>
      <label class="mc-field" style="margin:0">
        <span>Instructions</span>
        <input type="text" name="lab_test_instructions[]" placeholder="e.g. Fasting">
      </label>
      <button type="button" class="mc-btn mc-btn-ghost mc-btn-sm" onclick="removeLabTestRow(${id})" title="Remove" style="color:var(--danger)">
        <i class="bi bi-trash"></i>
      </button>
    </div>
  `;
  document.getElementById('lab-tests-container').appendChild(div);
}

function removeLabTestRow(id) {
  const row = document.getElementById('lab-test-row-' + id);
  if (row) row.remove();
  if (document.querySelectorAll('.lab-test-row').length === 0) {
    document.getElementById('lab-tests-placeholder').style.display = 'block';
    document.getElementById('lab-notes-wrap').style.display = 'none';
  }
}

function addPresetTest(name, category, urgency, instructions) {
  addLabTestRow();
  const rows = document.querySelectorAll('.lab-test-row');
  const lastRow = rows[rows.length - 1];
  if (lastRow) {
    const nameInput = lastRow.querySelector('input[name="lab_test_name[]"]');
    const catSelect = lastRow.querySelector('select[name="lab_test_category[]"]');
    const urgSelect = lastRow.querySelector('select[name="lab_test_urgency[]"]');
    const instInput = lastRow.querySelector('input[name="lab_test_instructions[]"]');
    if (nameInput) nameInput.value = name;
    if (catSelect) catSelect.value = category;
    if (urgSelect) urgSelect.value = urgency || 'ROUTINE';
    if (instInput) instInput.value = instructions || '';
  }
}
</script>
<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
