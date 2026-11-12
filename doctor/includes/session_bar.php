<?php
/**
 * MedCore Doctor Portal — Active Patient Session Guard, Top Bar & Executive Clinical Summary
 *
 * Ensures:
 * 1. Persistent, sticky top bar across doctor portal when a patient session is active.
 * 2. Unambiguous "End Session" action.
 * 3. Enforces strict single-patient consultation (cannot start another patient without ending active one).
 * 4. Executive Clinical Summary ("kon rog cilo, kon usud dice setar jnno, kotodin age, lab reports").
 */

if (!function_exists('getDoctorActivePatientSession')) {
    function getDoctorActivePatientSession(PDO $db, int $doctorId, int $hospitalId): ?array {
        $stmt = $db->prepare("
            SELECT s.*, p.id AS patient_id, p.patient_uid, p.full_name, p.date_of_birth, p.gender, p.blood_group,
                   p.emergency_contact_name, p.emergency_contact_phone, u.phone, u.email,
                   (SELECT COUNT(*) FROM patient_allergies pa WHERE pa.patient_id = p.id AND pa.severity IN ('SEVERE','LIFE_THREATENING')) AS critical_allergy_count
            FROM access_sessions s
            JOIN patients p ON s.patient_id = p.id
            JOIN users u    ON p.user_id = u.id
            WHERE s.doctor_id = ? AND s.hospital_id = ?
              AND s.status = 'ACTIVE' AND s.expires_at > NOW()
            ORDER BY s.expires_at DESC
            LIMIT 1
        ");
        $stmt->execute([$doctorId, $hospitalId]);
        $sess = $stmt->fetch();
        if ($sess) {
            $sess['seconds_remaining'] = max(0, strtotime($sess['expires_at']) - time());
            $sess['age'] = date_diff(new DateTime($sess['date_of_birth']), new DateTime())->y;
        }
        return $sess ?: null;
    }
}

if (!function_exists('formatClinicalTimeAgo')) {
    function formatClinicalTimeAgo($datetime): string {
        if (!$datetime) return 'N/A';
        $time = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 0) return 'Today (আজকে)';
        if ($diff < 3600) {
            $m = max(1, (int)floor($diff / 60));
            return "{$m}m ago ({$m} মি. আগে)";
        }
        if ($diff < 86400) {
            $h = (int)floor($diff / 3600);
            return "{$h}h ago ({$h} ঘণ্টা আগে)";
        }
        $days = (int)floor($diff / 86400);
        if ($days === 1) return "1 day ago (গতকাল)";
        if ($days < 7)  return "{$days} days ago ({$days} দিন আগে)";
        if ($days < 30) {
            $w = (int)floor($days / 7);
            return "{$w} wk" . ($w > 1 ? 's' : '') . " ago ({$w} সপ্তাহ আগে)";
        }
        if ($days < 365) {
            $mo = (int)floor($days / 30);
            return "{$mo} mo" . ($mo > 1 ? 's' : '') . " ago ({$mo} মাস আগে)";
        }
        $yr = (int)floor($days / 365);
        return "{$yr} yr" . ($yr > 1 ? 's' : '') . " ago ({$yr} বছর আগে)";
    }
}

if (!function_exists('renderActivePatientSessionBar')) {
    function renderActivePatientSessionBar(?array $activeSession, string $currentPage = ''): void {
        if (!$activeSession) return;
        $patientId = (int)$activeSession['patient_id'];
        $full_name = htmlspecialchars($activeSession['full_name']);
        $uid = htmlspecialchars($activeSession['patient_uid']);
        $blood = htmlspecialchars($activeSession['blood_group'] ?? 'Blood ?');
        $age = (int)($activeSession['age'] ?? 0);
        $gender = ucfirst(strtolower($activeSession['gender'] ?? ''));
        $rem = (int)($activeSession['seconds_remaining'] ?? 0);
        $hasCriticalAllergy = ($activeSession['critical_allergy_count'] ?? 0) > 0;
        ?>
        <!-- ACTIVE PATIENT CONSULTATION STICKY TOP BAR -->
        <div class="active-patient-session-bar" id="activePatientSessionBar" role="region" aria-label="Active patient consultation banner">
          <div class="session-bar-inner">
            <div class="session-bar-left">
              <span class="live-pulse-dot" title="Live Consultation Active"></span>
              <div class="session-patient-details">
                <span class="session-kicker">ACTIVE PATIENT IN CONSULTATION:</span>
                <div class="session-patient-heading">
                  <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= $patientId ?>" class="session-patient-name" title="Open patient record">
                    <?= $full_name ?>
                  </a>
                  <span class="session-patient-uid"><?= $uid ?></span>
                  <span class="session-pill blood-pill"><?= $blood ?></span>
                  <span class="session-pill"><?= $age ?>Y · <?= $gender ?></span>
                  <?php if ($hasCriticalAllergy): ?>
                    <span class="session-pill allergy-pill" title="Critical Severe Allergies on file!"><i class="bi bi-exclamation-triangle-fill"></i> Allergy Alert</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="session-bar-right">
              <div class="session-timer-badge" id="sessionTimerBadge" title="Session automatically terminates upon expiry">
                <i class="bi bi-hourglass-split"></i>
                <span>Remaining: <strong id="globalSessionTimerDisplay">--:--</strong></span>
              </div>

              <?php if ($currentPage !== 'medical-record'): ?>
                <a href="<?= APP_URL ?>/doctor/medical-record.php?patient_id=<?= $patientId ?>" class="btn-session btn-session-summary" title="View Patient Clinical Summary">
                  <i class="bi bi-file-earmark-medical-fill"></i>
                  <span>Summary &amp; Record</span>
                </a>
              <?php endif; ?>

              <?php if ($currentPage !== 'create-prescription'): ?>
                <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $patientId ?>" class="btn-session btn-session-rx" title="Write Prescription">
                  <i class="bi bi-prescription2"></i>
                  <span>New Rx</span>
                </a>
              <?php endif; ?>

              <form method="POST" action="<?= APP_URL ?>/doctor/end-session.php" style="display:inline;margin:0;" onsubmit="return confirm('Are you sure you want to END the consultation session with <?= htmlspecialchars(addslashes($activeSession['full_name'])) ?>? You will need to re-verify consent to open this file again.');">
                <?= csrfField() ?>
                <input type="hidden" name="session_id" value="<?= $activeSession['id'] ?>">
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/doctor/patient-search.php') ?>">
                <button type="submit" class="btn-session btn-session-end" title="End Current Consultation Session">
                  <i class="bi bi-x-circle-fill"></i>
                  <span>End Session</span>
                </button>
              </form>
            </div>
          </div>
        </div>

        <script>
        (function() {
          let remaining = <?= $rem ?>;
          const display = document.getElementById('globalSessionTimerDisplay');
          const badge = document.getElementById('sessionTimerBadge');
          if (!display) return;

          function tick() {
            if (remaining <= 0) {
              display.textContent = 'EXPIRED';
              if (badge) badge.classList.add('timer-expired');
              return;
            }
            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            display.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            if (remaining < 300 && badge) {
              badge.classList.add('timer-warning');
            }
            remaining--;
          }
          tick();
          setInterval(tick, 1000);
        })();
        </script>
        <?php
    }
}

if (!function_exists('getPatientExecutiveClinicalSummary')) {
    function getPatientExecutiveClinicalSummary(PDO $db, int $patientId): array {
        // 1. Patient basic profile
        $stmtPatient = $db->prepare("
            SELECT p.*, u.email, u.phone
            FROM patients p
            JOIN users u ON u.id = p.user_id
            WHERE p.id = ?
        ");
        $stmtPatient->execute([$patientId]);
        $patient = $stmtPatient->fetch() ?: [];
        $age = !empty($patient['date_of_birth']) ? date_diff(new DateTime($patient['date_of_birth']), new DateTime())->y : 0;
        $patient['age'] = $age;

        // 2. Prescriptions with full medicines list (Illness -> Medicines -> Date)
        $stmtRx = $db->prepare("
            SELECT rx.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
            FROM prescriptions rx
            LEFT JOIN doctors d ON rx.doctor_id = d.id
            LEFT JOIN hospitals h ON rx.hospital_id = h.id
            WHERE rx.patient_id = ?
            ORDER BY rx.created_at DESC
        ");
        $stmtRx->execute([$patientId]);
        $prescriptions = $stmtRx->fetchAll() ?: [];

        foreach ($prescriptions as &$rx) {
            $stmtMeds = $db->prepare("
                SELECT pm.*, m.generic_name, m.brand_name
                FROM prescription_medicines pm
                LEFT JOIN medications m ON pm.medication_id = m.id
                WHERE pm.prescription_id = ?
                ORDER BY pm.id ASC
            ");
            $stmtMeds->execute([$rx['id']]);
            $rx['medicines'] = $stmtMeds->fetchAll() ?: [];
            $rx['time_ago'] = formatClinicalTimeAgo($rx['created_at']);
        }
        unset($rx);

        // 3. Consultations History
        $stmtCons = $db->prepare("
            SELECT c.*, d.full_name AS doctor_name, d.specialization, h.name AS hospital_name,
                   dept.name AS department_name
            FROM consultations c
            LEFT JOIN doctors d ON c.doctor_id = d.id
            LEFT JOIN hospitals h ON c.hospital_id = h.id
            LEFT JOIN departments dept ON c.department_id = dept.id
            WHERE c.patient_id = ?
            ORDER BY c.consultation_date DESC
        ");
        $stmtCons->execute([$patientId]);
        $consultations = $stmtCons->fetchAll() ?: [];
        foreach ($consultations as &$c) {
            $c['time_ago'] = formatClinicalTimeAgo($c['consultation_date']);
        }
        unset($c);

        // 4. Medical Histories / Chronic Conditions
        $stmtHistory = $db->prepare("
            SELECT mh.*, d.full_name AS doctor_name
            FROM medical_histories mh
            LEFT JOIN doctors d ON mh.created_by = d.id
            WHERE mh.patient_id = ?
            ORDER BY mh.diagnosed_date DESC
        ");
        $stmtHistory->execute([$patientId]);
        $conditions = $stmtHistory->fetchAll() ?: [];
        foreach ($conditions as &$cond) {
            $cond['time_ago'] = formatClinicalTimeAgo($cond['diagnosed_date']);
        }
        unset($cond);

        // 5. Lab Reports
        $stmtLabs = $db->prepare("
            SELECT * FROM lab_reports
            WHERE patient_id = ?
            ORDER BY test_date DESC
        ");
        $stmtLabs->execute([$patientId]);
        $labReports = $stmtLabs->fetchAll() ?: [];
        foreach ($labReports as &$lr) {
            $lr['time_ago'] = formatClinicalTimeAgo($lr['test_date']);
            $summaryLower = strtolower($lr['result_summary'] ?? '');
            $isAbnormal = (strpos($summaryLower, 'elevated') !== false ||
                           strpos($summaryLower, 'abnormal') !== false ||
                           strpos($summaryLower, 'high') !== false ||
                           strpos($summaryLower, 'borderline') !== false ||
                           strpos($summaryLower, 'positive') !== false);
            $lr['is_abnormal'] = $isAbnormal;
        }
        unset($lr);

        // 6. Allergies
        $stmtAllergies = $db->prepare("
            SELECT pa.*, a.name AS allergy_name, a.type AS allergy_type
            FROM patient_allergies pa
            JOIN allergies a ON a.id = pa.allergy_id
            WHERE pa.patient_id = ?
            ORDER BY FIELD(pa.severity, 'LIFE_THREATENING', 'SEVERE', 'MODERATE', 'MILD')
        ");
        $stmtAllergies->execute([$patientId]);
        $allergies = $stmtAllergies->fetchAll() ?: [];

        // 7. Active ongoing medications
        $stmtActiveMeds = $db->prepare("
            SELECT pm.*, m.generic_name, m.brand_name, m.strength
            FROM patient_medications pm
            JOIN medications m ON pm.medication_id = m.id
            WHERE pm.patient_id = ? AND pm.status = 'ACTIVE'
            ORDER BY pm.started_at DESC
        ");
        $stmtActiveMeds->execute([$patientId]);
        $activeMeds = $stmtActiveMeds->fetchAll() ?: [];

        return [
            'patient'       => $patient,
            'prescriptions' => $prescriptions,
            'consultations' => $consultations,
            'conditions'    => $conditions,
            'lab_reports'   => $labReports,
            'allergies'     => $allergies,
            'active_meds'   => $activeMeds,
        ];
    }
}

if (!function_exists('renderExecutiveClinicalSummary')) {
    function renderExecutiveClinicalSummary(array $summary, bool $compact = false): void {
        $p = $summary['patient'] ?? [];
        $rxs = $summary['prescriptions'] ?? [];
        $labs = $summary['lab_reports'] ?? [];
        $conds = $summary['conditions'] ?? [];
        $allergies = $summary['allergies'] ?? [];
        $activeMeds = $summary['active_meds'] ?? [];

        $hasCriticalAllergy = false;
        foreach ($allergies as $a) {
            if (in_array($a['severity'] ?? '', ['SEVERE', 'LIFE_THREATENING'])) {
                $hasCriticalAllergy = true;
                break;
            }
        }
        ?>
        <div class="clinical-summary-box <?= $compact ? 'summary-compact' : '' ?>">
          <div class="clinical-summary-header">
            <div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge" style="background:#1E40AF;color:#fff;font-weight:700;">
                  <i class="bi bi-stars"></i> EXECUTIVE CLINICAL SUMMARY
                </span>
                <span style="font-size:12px;color:var(--mc-text-muted);font-weight:600;">
                  রোগীর অতীত রোগ, ওষুধ ও ল্যাব রিপোর্ট সারাংশ
                </span>
              </div>
              <h2 style="font-size:<?= $compact ? '18px' : '22px' ?>;font-weight:800;color:var(--mc-text-dark);margin:4px 0 0;">
                <?= htmlspecialchars($p['full_name'] ?? 'Patient') ?>
                <span style="font-size:13px;font-weight:600;color:var(--mc-blue);background:#EFF6FF;padding:2px 8px;border-radius:12px;margin-left:6px;">
                  <?= htmlspecialchars($p['patient_uid'] ?? '') ?>
                </span>
              </h2>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="session-pill blood-pill" style="font-size:13px;padding:4px 10px;">
                <i class="bi bi-droplet-half"></i> <?= htmlspecialchars($p['blood_group'] ?? 'Blood ?') ?>
              </span>
              <span class="session-pill" style="font-size:13px;padding:4px 10px;">
                <?= $p['age'] ?? 0 ?>Y · <?= ucfirst(strtolower($p['gender'] ?? '')) ?>
              </span>
              <?php if (!empty($p['id'])): ?>
                <a href="<?= APP_URL ?>/doctor/create-prescription.php?patient_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" style="font-weight:700;">
                  <i class="bi bi-prescription2"></i> New Prescription
                </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Alert Bar if critical allergies -->
          <?php if ($hasCriticalAllergy): ?>
            <div class="alert alert-danger d-flex align-items-center gap-3 mb-4" style="background:#FEF2F2;border:1px solid #F87171;color:#991B1B;border-radius:10px;padding:12px 16px;">
              <i class="bi bi-exclamation-triangle-fill" style="font-size:24px;color:#DC2626;"></i>
              <div>
                <strong>SEVERE ALLERGY WARNING / গুরুতর অ্যালার্জি সতর্কতা:</strong>
                <div style="font-size:13px;margin-top:2px;">
                  Patient has life-threatening/severe allergies:
                  <?php foreach ($allergies as $alg): ?>
                    <?php if (in_array($alg['severity'], ['SEVERE', 'LIFE_THREATENING'])): ?>
                      <span class="badge" style="background:#DC2626;color:#fff;margin-right:4px;">
                        <?= htmlspecialchars($alg['allergy_name']) ?> (<?= htmlspecialchars($alg['severity']) ?>)
                      </span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Section 1: Previous Illnesses & Medications Prescribed ("kon rog cilo .. kon usud dice setr jnno kotodin age") -->
          <div class="summary-section mb-4">
            <div class="section-title-bar d-flex justify-content-between align-items-center mb-3">
              <h3 style="font-size:16px;font-weight:700;color:var(--mc-text-dark);margin:0;display:flex;align-items:center;gap:8px;">
                <i class="bi bi-clipboard2-pulse-fill" style="color:var(--mc-blue);"></i>
                Past Diagnoses &amp; Prescribed Medicines (কোন রোগ ছিল এবং কী ওষুধ দেওয়া হয়েছিল)
              </h3>
              <span class="badge" style="background:#E0E7FF;color:#3730A3;font-weight:600;">
                <?= count($rxs) ?> Past Prescriptions
              </span>
            </div>

            <?php if (empty($rxs)): ?>
              <div class="p-3 text-center text-muted" style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:8px;">
                <i class="bi bi-info-circle"></i> No prior prescriptions found for this patient in the network.
              </div>
            <?php else: ?>
              <div class="clinical-timeline-list">
                <?php
                $displayRxs = $compact ? array_slice($rxs, 0, 2) : $rxs;
                foreach ($displayRxs as $rx):
                ?>
                  <div class="clinical-timeline-card">
                    <div class="timeline-header">
                      <div class="timeline-disease">
                        <span class="disease-tag">DIAGNOSIS</span>
                        <h4 class="disease-name"><?= htmlspecialchars($rx['diagnosis'] ?: 'General Consultation / Clinical Follow-up') ?></h4>
                        <div class="doctor-badge">
                          <i class="bi bi-person-check-fill"></i>
                          <span>Dr. <?= htmlspecialchars($rx['doctor_name'] ?? 'Attending Physician') ?></span>
                          <span class="text-muted">·</span>
                          <span><?= htmlspecialchars($rx['hospital_name'] ?? 'MedCore Affiliated Center') ?></span>
                        </div>
                      </div>
                      <div class="timeline-time">
                        <span class="clinical-elapsed-badge" title="<?= date('d M Y, h:i A', strtotime($rx['created_at'])) ?>">
                          <i class="bi bi-clock-history"></i> <?= $rx['time_ago'] ?>
                        </span>
                        <div class="exact-date"><?= date('d M Y', strtotime($rx['created_at'])) ?></div>
                      </div>
                    </div>

                    <!-- Medicines Prescribed for this Illness -->
                    <div class="timeline-meds">
                      <div class="meds-subheading">
                        <i class="bi bi-capsule"></i>
                        <span>Medications Prescribed for this condition (প্রদত্ত ওষুধসমূহ):</span>
                      </div>
                      <?php if (empty($rx['medicines'])): ?>
                        <div class="text-muted" style="font-size:12px;font-style:italic;">
                          No specific medications listed (Advice / Lab orders only).
                        </div>
                      <?php else: ?>
                        <div class="table-responsive">
                          <table class="meds-mini-table">
                            <thead>
                              <tr>
                                <th>Medicine &amp; Strength</th>
                                <th>Dosage &amp; Frequency</th>
                                <th>Duration</th>
                                <th>Instructions / খাবার নিয়ম</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($rx['medicines'] as $m): ?>
                                <tr>
                                  <td>
                                    <div class="med-name">
                                      <i class="bi bi-capsule-pill" style="color:var(--mc-teal);margin-right:4px;"></i>
                                      <strong><?= htmlspecialchars($m['medicine_name']) ?></strong>
                                      <?php if (!empty($m['strength'])): ?>
                                        <span class="med-strength-tag"><?= htmlspecialchars($m['strength']) ?></span>
                                      <?php endif; ?>
                                    </div>
                                    <?php if (!empty($m['generic_name']) && strtolower($m['generic_name']) !== strtolower($m['medicine_name'])): ?>
                                      <div class="med-generic"><?= htmlspecialchars($m['generic_name']) ?></div>
                                    <?php endif; ?>
                                  </td>
                                  <td>
                                    <span class="dose-tag"><?= htmlspecialchars($m['frequency'] ?: $m['dosage'] ?: 'As directed') ?></span>
                                  </td>
                                  <td>
                                    <span class="duration-tag"><?= htmlspecialchars($m['duration'] ?: 'N/A') ?></span>
                                  </td>
                                  <td>
                                    <span class="instructions-text"><?= htmlspecialchars($m['instructions'] ?: 'Standard administration') ?></span>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>

                    <?php if (!empty($rx['advice'])): ?>
                      <div class="timeline-advice">
                        <strong><i class="bi bi-chat-quote-fill"></i> Doctor's Advice &amp; Instructions:</strong>
                        <p><?= nl2br(htmlspecialchars($rx['advice'])) ?></p>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Section 2: Previous Lab Test Reports ("easily prev report dkehte pare") -->
          <div class="summary-section mb-4">
            <div class="section-title-bar d-flex justify-content-between align-items-center mb-3">
              <h3 style="font-size:16px;font-weight:700;color:var(--mc-text-dark);margin:0;display:flex;align-items:center;gap:8px;">
                <i class="bi bi-file-earmark-medical-fill" style="color:#059669;"></i>
                Previous Diagnostic Lab Reports (পূর্ববর্তী ল্যাব টেস্ট ও রিপোর্ট)
              </h3>
              <span class="badge" style="background:#D1FAE5;color:#065F46;font-weight:600;">
                <?= count($labs) ?> Test Records
              </span>
            </div>

            <?php if (empty($labs)): ?>
              <div class="p-3 text-center text-muted" style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:8px;">
                <i class="bi bi-info-circle"></i> No prior laboratory test reports found.
              </div>
            <?php else: ?>
              <div class="lab-reports-grid">
                <?php
                $displayLabs = $compact ? array_slice($labs, 0, 3) : $labs;
                foreach ($displayLabs as $lr):
                ?>
                  <div class="lab-alert-card <?= $lr['is_abnormal'] ? 'card-abnormal' : 'card-normal' ?>">
                    <div class="lab-card-top">
                      <div class="lab-test-title">
                        <h4><?= htmlspecialchars($lr['report_name']) ?></h4>
                        <span class="lab-center"><i class="bi bi-hospital"></i> <?= htmlspecialchars($lr['laboratory_name'] ?? 'Diagnostic Lab') ?></span>
                      </div>
                      <div class="lab-status-badge">
                        <?php if ($lr['is_abnormal']): ?>
                          <span class="badge badge-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> Abnormal / Flagged
                          </span>
                        <?php else: ?>
                          <span class="badge badge-success">
                            <i class="bi bi-check-circle-fill"></i> Normal
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="lab-result-body">
                      <div class="lab-result-text">
                        <?= htmlspecialchars($lr['result_summary'] ?: 'Results verified by laboratory pathologist.') ?>
                      </div>
                    </div>

                    <div class="lab-card-footer">
                      <span class="clinical-elapsed-badge">
                        <i class="bi bi-calendar3"></i> <?= $lr['time_ago'] ?>
                      </span>
                      <span class="exact-date"><?= date('d M Y', strtotime($lr['test_date'])) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Section 3: Chronic Conditions & Active Continuous Medications -->
          <div class="summary-section">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="chronic-cond-card">
                  <h4 style="font-size:14px;font-weight:700;color:var(--mc-text-dark);margin:0 0 10px;display:flex;align-items:center;gap:6px;">
                    <i class="bi bi-heart-pulse-fill" style="color:#DC2626;"></i>
                    Documented Chronic Illnesses (দীর্ঘস্থায়ী রোগ)
                  </h4>
                  <?php if (empty($conds)): ?>
                    <p class="text-muted" style="font-size:13px;margin:0;">No chronic conditions registered.</p>
                  <?php else: ?>
                    <ul class="clean-cond-list">
                      <?php foreach ($conds as $cd): ?>
                        <li>
                          <div class="d-flex justify-content-between align-items-center">
                            <strong><?= htmlspecialchars($cd['condition_name']) ?></strong>
                            <span class="clinical-elapsed-badge" style="font-size:11px;"><?= $cd['time_ago'] ?></span>
                          </div>
                          <?php if (!empty($cd['notes'])): ?>
                            <div class="text-muted" style="font-size:12px;margin-top:2px;"><?= htmlspecialchars($cd['notes']) ?></div>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              </div>

              <div class="col-md-6">
                <div class="chronic-cond-card">
                  <h4 style="font-size:14px;font-weight:700;color:var(--mc-text-dark);margin:0 0 10px;display:flex;align-items:center;gap:6px;">
                    <i class="bi bi-capsule-pill" style="color:#0284C7;"></i>
                    Active Ongoing Medications (চলমান নিয়মিত ওষুধ)
                  </h4>
                  <?php if (empty($activeMeds)): ?>
                    <p class="text-muted" style="font-size:13px;margin:0;">No active ongoing continuous medications recorded.</p>
                  <?php else: ?>
                    <ul class="clean-cond-list">
                      <?php foreach ($activeMeds as $am): ?>
                        <li>
                          <div class="d-flex justify-content-between align-items-center">
                            <strong><?= htmlspecialchars($am['brand_name'] ?: $am['generic_name']) ?> (<?= htmlspecialchars($am['strength'] ?? '') ?>)</strong>
                            <span class="badge" style="background:#E0F2FE;color:#0369A1;"><?= htmlspecialchars($am['dosage'] ?: $am['frequency'] ?: 'Daily') ?></span>
                          </div>
                          <div class="text-muted" style="font-size:12px;margin-top:2px;">
                            Started: <?= date('d M Y', strtotime($am['started_at'] ?? 'now')) ?> (<?= formatClinicalTimeAgo($am['started_at'] ?? 'now') ?>)
                          </div>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php
    }
}


if (!function_exists('renderDoctorSessionBar')) {
    function renderDoctorSessionBar(?array $activeSession, string $currentPage = ''): void {
        renderActivePatientSessionBar($activeSession, $currentPage);
    }
}

if (!function_exists('mc_doctor_shell_setup')) {
    function mc_doctor_shell_setup(PDO $db, int $doctorId, int $hospitalId, ?array $activeSession, string $currentPage = ''): int {
        $stmtCount = $db->prepare("
            SELECT COUNT(*) FROM appointments
            WHERE doctor_id = ? AND hospital_id = ? AND appointment_date = ? AND status IN ('CONFIRMED','WAITING','IN_CONSULTATION')
        ");
        $waitingQueueCount = 0;
        if ($stmtCount) {
            $stmtCount->execute([$doctorId, $hospitalId, date('Y-m-d')]);
            $waitingQueueCount = (int)$stmtCount->fetchColumn();
        }

        ob_start();
        renderActivePatientSessionBar($activeSession, $currentPage);
        $GLOBALS['portalTopBanner'] = ob_get_clean();

        $hospName = $_SESSION['hospital_name'] ?? 'Hospital';
        $deptName = $_SESSION['department_name'] ?? '';
        ob_start();
        ?>
        <div class="mc-rail-context">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:.4rem">
            <span class="mc-rail-context-label">Active hospital</span>
            <a href="<?= APP_URL ?>/doctor/select-hospital.php?switch=1" style="font-size:.74rem;font-weight:800;color:var(--accent)">Switch</a>
          </div>
          <div class="mc-rail-context-val"><i class="bi bi-building-fill-check"></i> <?= htmlspecialchars($hospName) ?></div>
          <?php if ($deptName !== ''): ?>
            <div style="font-size:.75rem;color:var(--muted);margin-top:.15rem"><?= htmlspecialchars($deptName) ?></div>
          <?php endif; ?>
        </div>
        <?php
        $GLOBALS['portalRailContext'] = ob_get_clean();

        return $waitingQueueCount;
    }
}
