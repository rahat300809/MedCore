<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmtPatient = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmtPatient->execute([$patientId]);
$patient = $stmtPatient->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    validateCsrf();
    $aptId = (int)($_POST['appointment_id'] ?? 0);
    $stmtCancel = $db->prepare("UPDATE appointments SET status = 'CANCELLED' WHERE id = ? AND patient_id = ? AND status IN ('CONFIRMED', 'WAITING')");
    $stmtCancel->execute([$aptId, $patientId]);
    if ($stmtCancel->rowCount() > 0) {
        AuditService::log('CANCEL_APPOINTMENT', [
            'user_id' => $_SESSION['user_id'],
            'patient_id' => $patientId,
            'appointment_id' => $aptId,
        ]);
        $_SESSION['flash'] = [
            'type' => 'success',
            'title' => 'Appointment cancelled',
            'msg' => 'Your doctor serial has been cancelled.',
        ];
    }
    header('Location: ' . APP_URL . '/patient/appointments.php');
    exit;
}

$tab = $_GET['tab'] ?? 'upcoming';
$query = "
    SELECT a.*, d.full_name AS doctor_name, d.specialization, d.qualification, d.rating_score,
           h.name AS hospital_name, dept.name AS department_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN hospitals h ON a.hospital_id = h.id
    LEFT JOIN departments dept ON a.department_id = dept.id
    WHERE a.patient_id = ?
";
if ($tab === 'upcoming') {
    $query .= " AND a.status IN ('CONFIRMED', 'WAITING', 'IN_CONSULTATION') AND a.appointment_date >= CURDATE()";
    $query .= " ORDER BY a.appointment_date ASC, a.serial_number ASC";
} elseif ($tab === 'past') {
    $query .= " AND (a.status IN ('COMPLETED', 'CANCELLED', 'NO_SHOW') OR a.appointment_date < CURDATE())";
    $query .= " ORDER BY a.appointment_date DESC, a.serial_number ASC";
} else {
    $query .= " ORDER BY a.appointment_date DESC, a.serial_number ASC";
}
$stmt = $db->prepare($query);
$stmt->execute([$patientId]);
$appointments = $stmt->fetchAll();

$stmtCountUpcoming = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ('CONFIRMED', 'WAITING', 'IN_CONSULTATION') AND appointment_date >= CURDATE()");
$stmtCountUpcoming->execute([$patientId]);
$countUpcoming = (int)$stmtCountUpcoming->fetchColumn();

$stmtCountAll = $db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
$stmtCountAll->execute([$patientId]);
$countAll = (int)$stmtCountAll->fetchColumn();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = generateCsrfToken();

$doctorLabel = static function (string $name): string {
    $clean = preg_replace('/^\s*Dr\.?\s+/i', '', trim($name)) ?: $name;
    return 'Dr. ' . $clean;
};

$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $patient['full_name'] ?? ($_SESSION['name'] ?? 'Patient');
$portalMeta = ($patient['patient_uid'] ?? '') . ' · Appointments';
$portalTitle = 'My appointments';
$portalSubtitle = 'সিরিয়াল, টোকেন ও বুকিং স্ট্যাটাস';
$portalNav = mc_patient_nav('appointments');
$portalTopAction = '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/book-doctor.php">Book serial</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>

<?php if ($flash): ?>
  <div class="mc-alert mc-alert-ok">
    <?php if (!empty($flash['title'])): ?><strong><?= htmlspecialchars($flash['title']) ?></strong> — <?php endif; ?>
    <?= htmlspecialchars($flash['msg'] ?? '') ?>
  </div>
<?php endif; ?>

<div class="mc-panel" style="background:linear-gradient(135deg,#0b3a4a,#0f766e);color:#fff;border:0">
  <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap">
    <div>
      <p class="mc-kicker" style="color:#a7f3d0;margin:0">Online directory</p>
      <h2 style="margin:.35rem 0;color:#fff;font-size:1.2rem">বিশেষজ্ঞ ডাক্তার খুঁজে সিরিয়াল নিন</h2>
      <p style="margin:0;opacity:.9;font-size:.9rem">ডিপার্টমেন্ট, ফি ও রেটিং দেখে ইনস্ট্যান্ট বুকিং।</p>
    </div>
    <a class="mc-btn mc-btn-secondary" href="<?= APP_URL ?>/book-doctor.php">Book doctor serial</a>
  </div>
</div>

<div class="mc-panel">
  <div style="display:flex;gap:.35rem;flex-wrap:wrap">
    <?php
    $tabs = [
      'upcoming' => "Upcoming ($countUpcoming)",
      'past' => 'Past',
      'all' => "All ($countAll)",
    ];
    foreach ($tabs as $key => $label): ?>
      <a class="mc-btn mc-btn-sm <?= $tab === $key ? 'mc-btn-primary' : 'mc-btn-ghost' ?>" href="?tab=<?= $key ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($appointments)): ?>
  <div class="mc-panel" style="text-align:center;padding:2.4rem 1rem">
    <h2>কোনো অ্যাপয়েন্টমেন্ট নেই</h2>
    <p style="color:var(--muted);margin:.5rem 0 1rem">এই ট্যাবে কোনো সিরিয়াল পাওয়া যায়নি।</p>
    <a class="mc-btn mc-btn-primary" href="<?= APP_URL ?>/book-doctor.php">Book doctor serial</a>
  </div>
<?php else: ?>
  <div class="mc-action-grid">
    <?php foreach ($appointments as $apt):
      $status = $apt['status'];
      $serial = str_pad((string)$apt['serial_number'], 2, '0', STR_PAD_LEFT);
    ?>
      <div class="mc-panel" style="margin:0">
        <div style="display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;flex-wrap:wrap">
          <div style="display:flex;gap:.85rem;align-items:flex-start">
            <div style="min-width:4.2rem;padding:.7rem .55rem;border-radius:12px;background:<?= $apt['priority'] === 'EMERGENCY' ? '#dc2626' : 'var(--accent)' ?>;color:#fff;text-align:center">
              <div style="font-weight:900;font-size:1.25rem;font-family:var(--mono),monospace">#<?= $serial ?></div>
              <div style="font-size:.68rem;font-weight:800;letter-spacing:.04em">SERIAL</div>
            </div>
            <div>
              <span class="lab-status"><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></span>
              <h3 style="margin:.4rem 0 0;font-size:1.05rem"><?= htmlspecialchars($doctorLabel((string)$apt['doctor_name'])) ?></h3>
              <p style="margin:.25rem 0 0;color:var(--muted);font-size:.86rem">
                <?= htmlspecialchars($apt['specialization']) ?>
                · <?= htmlspecialchars($apt['hospital_name']) ?>
                <?php if (!empty($apt['department_name'])): ?> · <?= htmlspecialchars($apt['department_name']) ?><?php endif; ?>
              </p>
              <p style="margin:.45rem 0 0;font-size:.88rem">
                <?= date('D, d M Y', strtotime($apt['appointment_date'])) ?> · <?= htmlspecialchars($apt['time_slot']) ?>
              </p>
              <p style="margin:.25rem 0 0;font-size:.82rem;color:var(--muted)">
                <code class="mc-mono"><?= htmlspecialchars($apt['appointment_uid']) ?></code>
                · ৳<?= number_format((float)$apt['consultation_fee']) ?>
                · <?= $apt['payment_status'] === 'PAID' ? 'Paid' : 'Pay at counter' ?>
              </p>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:.4rem">
            <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm" onclick='printSlip(<?= json_encode([
              'serial_number' => $apt['serial_number'],
              'priority' => $apt['priority'],
              'appointment_uid' => $apt['appointment_uid'],
              'doctor_name' => $apt['doctor_name'],
              'specialization' => $apt['specialization'],
              'hospital_name' => $apt['hospital_name'],
              'appointment_date' => $apt['appointment_date'],
              'time_slot' => $apt['time_slot'],
              'consultation_fee' => $apt['consultation_fee'],
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>Print slip</button>
            <?php if (in_array($status, ['CONFIRMED', 'WAITING'], true)): ?>
              <form method="POST" onsubmit="return confirm('Cancel this serial?');">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="appointment_id" value="<?= (int)$apt['id'] ?>">
                <button type="submit" name="cancel_appointment" class="mc-btn mc-btn-ghost mc-btn-sm">Cancel</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div id="print-slip" style="display:none">
  <div style="padding:1.5rem;max-width:420px;margin:0 auto;border:2px dashed var(--accent);border-radius:12px;font-family:inherit">
    <h2 style="margin:0 0 .5rem;text-align:center;color:var(--accent)">MedCore serial slip</h2>
    <div id="slip-token" style="text-align:center;font-size:2rem;font-weight:900"></div>
    <div id="slip-priority" style="text-align:center;font-size:.8rem;margin-bottom:1rem"></div>
    <p><strong>UID:</strong> <span id="slip-uid"></span></p>
    <p><strong>Patient:</strong> <?= htmlspecialchars($patient['full_name'] ?? '') ?> (<?= htmlspecialchars($patient['patient_uid'] ?? '') ?>)</p>
    <p><strong>Doctor:</strong> <span id="slip-doctor"></span></p>
    <p><strong>Hospital:</strong> <span id="slip-hospital"></span></p>
    <p><strong>When:</strong> <span id="slip-time"></span></p>
    <p><strong>Fee:</strong> <span id="slip-fee"></span></p>
  </div>
</div>
<style>@media print{body *{visibility:hidden!important}#print-slip,#print-slip *{visibility:visible!important}#print-slip{display:block!important;position:absolute;left:0;top:0;width:100%}}</style>
<script>
function printSlip(apt){
  document.getElementById('slip-token').textContent='#'+String(apt.serial_number).padStart(2,'0');
  document.getElementById('slip-priority').textContent=apt.priority==='EMERGENCY'?'STAT EMERGENCY':'ROUTINE SERIAL';
  document.getElementById('slip-uid').textContent=apt.appointment_uid;
  document.getElementById('slip-doctor').textContent='Dr. '+String(apt.doctor_name||'').replace(/^\s*Dr\.?\s+/i,'')+' ('+apt.specialization+')';
  document.getElementById('slip-hospital').textContent=apt.hospital_name;
  document.getElementById('slip-time').textContent=apt.appointment_date+' · '+apt.time_slot;
  document.getElementById('slip-fee').textContent='৳'+Number(apt.consultation_fee).toLocaleString();
  window.print();
}
</script>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
