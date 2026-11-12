<?php
/**
 * Professional prescription pad.
 * Expected:
 *  $rx (array), $medicines (array),
 *  optional: $allergies, $labOrder, $labTests, $showToolbar, $backUrl
 */
$allergies = $allergies ?? [];
$labOrder  = $labOrder ?? null;
$labTests  = $labTests ?? [];
$showToolbar = $showToolbar ?? true;
$backUrl = $backUrl ?? 'javascript:history.back()';

$age = '';
if (!empty($rx['date_of_birth'])) {
    try {
        $age = date_diff(new DateTime($rx['date_of_birth']), new DateTime())->y . ' yrs';
    } catch (Exception $e) {
        $age = '—';
    }
}

$hospitalName = $rx['hospital_name'] ?? 'MedCore Affiliated Facility';
$hospitalAddr = $rx['hospital_address'] ?? ($rx['hosp_addr'] ?? ($rx['hosp_city'] ?? ''));
$hospitalPhone = $rx['hospital_phone'] ?? ($rx['hosp_phone'] ?? '');
$hospitalReg = $rx['hosp_reg'] ?? ($rx['registration_number'] ?? '');
$doctorName = preg_replace('/^Dr\.?\s+/i', '', (string)($rx['doctor_name'] ?? 'Physician'));
$doctorSpec = $rx['specialization'] ?? ($rx['doctor_spec'] ?? '');
$doctorQual = $rx['qualification'] ?? ($rx['doctor_qual'] ?? '');
$bmdc = $rx['medical_registration_id'] ?? '';
$investigation = $rx['investigation'] ?? '';
$filename = preg_replace('/[^A-Za-z0-9\-]/', '_', (string)($rx['prescription_uid'] ?? 'prescription'));
$verifyHash = strtoupper(substr(hash('sha256', ($rx['prescription_uid'] ?? '') . ($rx['created_at'] ?? '')), 0, 16));
?>

<?php if ($showToolbar): ?>
<div class="rx-toolbar no-print">
  <a href="<?= htmlspecialchars($backUrl) ?>" class="mc-btn mc-btn-ghost mc-btn-sm">
    <i class="bi bi-arrow-left"></i> Back
  </a>
  <div class="rx-toolbar-actions">
    <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick="mcRxPrint()">
      <i class="bi bi-printer-fill"></i> Print
    </button>
    <button type="button" id="rx-download-btn" class="mc-btn mc-btn-primary mc-btn-sm" onclick="mcRxDownloadPdf()">
      <i class="bi bi-download"></i> Download PDF
    </button>
  </div>
</div>
<?php endif; ?>

<article class="rx-pad" id="rx-printable" data-filename="MedCore-Rx-<?= htmlspecialchars($filename) ?>">
  <div class="rx-pad-inner">
    <header class="rx-letterhead">
      <div>
        <div class="rx-hospital-meta" style="font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--rx-accent);margin-bottom:2px">
          Official Medical Prescription
        </div>
        <h1 class="rx-hospital-name"><?= htmlspecialchars($hospitalName) ?></h1>
        <div class="rx-hospital-meta">
          <?php if ($hospitalAddr): ?><?= htmlspecialchars($hospitalAddr) ?><?php endif; ?>
          <?php if ($hospitalPhone): ?><?= $hospitalAddr ? ' · ' : '' ?>Tel: <?= htmlspecialchars($hospitalPhone) ?><?php endif; ?>
          <?php if ($hospitalReg): ?><br>Facility Reg: <?= htmlspecialchars($hospitalReg) ?><?php endif; ?>
        </div>
      </div>
      <div class="rx-doc-block">
        <h2 class="rx-doc-name">Dr. <?= htmlspecialchars($doctorName) ?></h2>
        <?php if ($doctorSpec): ?><div class="rx-doc-spec"><?= htmlspecialchars($doctorSpec) ?></div><?php endif; ?>
        <?php if ($doctorQual): ?><div class="rx-hospital-meta"><?= htmlspecialchars($doctorQual) ?></div><?php endif; ?>
        <?php if ($bmdc): ?><div class="rx-bmdc">BMDC: <?= htmlspecialchars($bmdc) ?></div><?php endif; ?>
      </div>
    </header>

    <div class="rx-meta-bar">
      <div>Rx ID: <strong><?= htmlspecialchars($rx['prescription_uid'] ?? '') ?></strong></div>
      <div>Issued: <strong><?= !empty($rx['created_at']) ? date('d M Y, h:i A', strtotime($rx['created_at'])) : '—' ?></strong></div>
      <div>Status: <strong><?= htmlspecialchars($rx['status'] ?? 'ACTIVE') ?></strong></div>
    </div>

    <section class="rx-patient" aria-label="Patient details">
      <div>
        <span class="k">Patient</span>
        <span class="v"><?= htmlspecialchars($rx['patient_name'] ?? '') ?></span>
      </div>
      <div>
        <span class="k">Patient UID</span>
        <span class="v" style="font-family:JetBrains Mono,monospace;font-size:.82rem"><?= htmlspecialchars($rx['patient_uid'] ?? '') ?></span>
      </div>
      <div>
        <span class="k">Age / Sex</span>
        <span class="v"><?= htmlspecialchars($age ?: '—') ?> · <?= htmlspecialchars(ucfirst(strtolower($rx['gender'] ?? '—'))) ?></span>
      </div>
      <div>
        <span class="k">Blood Group</span>
        <span class="v" style="color:#b42318"><?= htmlspecialchars($rx['blood_group'] ?: '—') ?></span>
      </div>
    </section>

    <?php if (!empty($allergies)): ?>
      <div class="rx-allergy">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Allergy alert:
        <?php
        $bits = [];
        foreach ($allergies as $alg) {
            $bits[] = htmlspecialchars(($alg['allergy_name'] ?? 'Allergy') . (!empty($alg['severity']) ? ' (' . $alg['severity'] . ')' : ''));
        }
        echo implode(', ', $bits);
        ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($rx['diagnosis']) || !empty($rx['chief_complaint'])): ?>
      <section class="rx-clinical">
        <div class="label">Clinical impression / Diagnosis</div>
        <div class="diag"><?= htmlspecialchars($rx['diagnosis'] ?: 'Clinical assessment') ?></div>
        <?php if (!empty($rx['chief_complaint'])): ?>
          <div class="sub"><strong>Chief complaint:</strong> <?= htmlspecialchars($rx['chief_complaint']) ?></div>
        <?php endif; ?>
        <?php if (!empty($rx['clinical_notes'])): ?>
          <div class="sub"><strong>Notes:</strong> <?= htmlspecialchars($rx['clinical_notes']) ?></div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <div class="rx-body">
      <div class="rx-symbol" aria-hidden="true">℞</div>

      <table class="rx-meds">
        <thead>
          <tr>
            <th style="width:36px">#</th>
            <th>Medicine</th>
            <th>Strength</th>
            <th>Dosage / Schedule</th>
            <th>Duration</th>
            <th>Instructions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($medicines)): ?>
            <tr><td colspan="6" class="rx-empty">No medicines listed on this prescription.</td></tr>
          <?php else: foreach ($medicines as $i => $med): ?>
            <tr>
              <td class="num"><?= $i + 1 ?>.</td>
              <td>
                <div class="rx-med-name"><?= htmlspecialchars($med['medicine_name'] ?? $med['name'] ?? 'Medicine') ?></div>
                <?php if (!empty($med['generic_name'])): ?>
                  <div class="rx-med-generic"><?= htmlspecialchars($med['generic_name']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?= htmlspecialchars($med['strength'] ?: '—') ?>
                <?php if (!empty($med['dosage_form'])): ?>
                  <div class="rx-med-generic"><?= htmlspecialchars($med['dosage_form']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="rx-dose"><?= htmlspecialchars($med['dosage'] ?: '—') ?></div>
                <div class="rx-med-generic"><?= htmlspecialchars($med['frequency'] ?: '') ?></div>
              </td>
              <td><span class="rx-chip"><?= htmlspecialchars($med['duration'] ?: 'As advised') ?></span></td>
              <td>
                <?= htmlspecialchars($med['instructions'] ?: '—') ?>
                <?php if (!empty($med['route'])): ?>
                  <div class="rx-med-generic">Route: <?= htmlspecialchars($med['route']) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <?php if ($labOrder && !empty($labTests)): ?>
        <div class="rx-lab">
          <div class="rx-lab-h">
            <div>
              <div style="font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;opacity:.85;font-weight:800">Laboratory order</div>
              <div style="font-weight:800;font-size:.95rem">Present this token at diagnostic lab</div>
            </div>
            <div class="token"><?= htmlspecialchars($labOrder['lab_token'] ?? '') ?></div>
          </div>
          <div class="rx-lab-body">
            <div style="margin-bottom:8px;color:var(--rx-muted)">
              Status: <strong><?= htmlspecialchars($labOrder['status'] ?? 'PENDING') ?></strong>
              <?php if (!empty($labOrder['expires_at'])): ?>
                · Valid until <?= date('d M Y', strtotime($labOrder['expires_at'])) ?>
              <?php endif; ?>
            </div>
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Test</th>
                  <th>Category</th>
                  <th>Urgency</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($labTests as $ti => $lt): ?>
                  <tr>
                    <td><?= $ti + 1 ?></td>
                    <td><strong><?= htmlspecialchars($lt['test_name'] ?? '') ?></strong></td>
                    <td><?= htmlspecialchars($lt['test_category'] ?? 'General') ?></td>
                    <td><?= htmlspecialchars($lt['urgency'] ?? 'ROUTINE') ?></td>
                    <td><?= htmlspecialchars($lt['status'] ?? ($lt['result_status'] ?? 'PENDING')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php elseif ($labOrder && !empty($labOrder['lab_token'])): ?>
        <div class="rx-lab" style="margin-top:18px">
          <div class="rx-lab-h">
            <div style="font-weight:800">Lab token</div>
            <div class="token"><?= htmlspecialchars($labOrder['lab_token']) ?></div>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($rx['advice']) || !empty($investigation)): ?>
        <div class="rx-notes">
          <?php if (!empty($rx['advice'])): ?>
            <div class="rx-note">
              <div class="label">Doctor’s advice</div>
              <div class="text"><?= htmlspecialchars($rx['advice']) ?></div>
            </div>
          <?php endif; ?>
          <?php if (!empty($investigation)): ?>
            <div class="rx-note">
              <div class="label">Investigations advised</div>
              <div class="text"><?= htmlspecialchars($investigation) ?></div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="rx-footer">
        <div>
          <?php if (!empty($rx['follow_up_date'])): ?>
            <div class="rx-follow">
              <div class="label">Follow-up</div>
              <div class="date"><?= date('l, d M Y', strtotime($rx['follow_up_date'])) ?></div>
            </div>
          <?php else: ?>
            <div style="font-size:.8rem;color:var(--rx-muted)">Follow up if symptoms persist or worsen.</div>
          <?php endif; ?>
        </div>
        <div class="rx-sign">
          <div class="rx-sign-line">Dr. <?= htmlspecialchars($doctorName) ?></div>
          <div class="rx-sign-rule">Digitally signed · MedCore e-Prescription</div>
          <div class="rx-hash">VERIFY: <?= htmlspecialchars($verifyHash) ?></div>
        </div>
      </div>
    </div>

    <footer class="rx-pad-foot">
      <div>Generated by MedCore National Health Network</div>
      <div>Not valid without physician credentials · For clinical use only</div>
    </footer>
  </div>
</article>
