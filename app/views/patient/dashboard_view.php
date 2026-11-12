<?php
require_once dirname(__DIR__) . '/portal/navs.php';
$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalAccent = '#0f766e';
$portalUser = $patient['full_name'] ?? ($_SESSION['name'] ?? 'Patient');
$portalMeta = ($patient['patient_uid'] ?? '') . ' · Blood ' . ($patient['blood_group'] ?? '—');
$portalTitle = 'Health overview';
$portalSubtitle = 'আজকের প্রয়োজনীয় কাজ এক নজরে';
$portalNav = mc_patient_nav('dashboard');
$portalTopAction = '<a class="mc-btn mc-btn-primary mc-btn-sm" href="' . APP_URL . '/book-doctor.php"><i class="bi bi-plus-lg"></i> Book serial</a>';
require dirname(__DIR__) . '/layout/portal_shell_start.php';
?>

<?php if (!empty($criticalAllergies)): ?>
  <div class="mc-alert mc-alert-danger">
    Critical allergy:
    <?php foreach ($criticalAllergies as $i => $a): ?>
      <?= $i ? ' · ' : '' ?><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['severity']) ?>)
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($flash): ?>
  <div class="mc-alert <?= ($flash['type'] ?? '') === 'success' ? 'mc-alert-ok' : (($flash['type'] ?? '') === 'info' ? 'mc-alert-info' : 'mc-alert-danger') ?>">
    <?php if (!empty($flash['title'])): ?><strong><?= htmlspecialchars($flash['title']) ?></strong> — <?php endif; ?>
    <?= htmlspecialchars($flash['msg'] ?? '') ?>
  </div>
<?php endif; ?>

<div class="mc-stats">
  <div class="mc-stat"><div class="l">Prescriptions</div><div class="v"><?= (int)($stats['active_prescriptions'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Medicines</div><div class="v"><?= (int)($stats['active_medications'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Lab reports</div><div class="v"><?= (int)($stats['lab_reports'] ?? 0) ?></div></div>
  <div class="mc-stat"><div class="l">Consent waiting</div><div class="v"><?= count($pendingRequests) ?></div></div>
</div>

<div class="mc-panel">
  <div class="mc-panel-h"><h2>Quick actions</h2></div>
  <div class="mc-action-grid">
    <?php
    $actions = [
      [APP_URL.'/patient/prescriptions.php', 'bi-file-earmark-medical', 'Prescriptions', 'ডাক্তারের দেওয়া ওষুধের তালিকা'],
      [APP_URL.'/patient/medications.php', 'bi-capsule', 'Medicines', 'কখন কী খাবেন'],
      [APP_URL.'/patient/lab-reports.php', 'bi-eyedropper', 'Lab tokens', 'টোকেন দেখান ও রিপোর্ট নিন'],
      [APP_URL.'/patient/appointments.php', 'bi-calendar2-check', 'Appointments', 'আসন্ন সিরিয়াল'],
      [APP_URL.'/patient/consent-requests.php', 'bi-shield-check', 'Consent', 'ডাক্তার অ্যাক্সেস অনুমোদন'],
      [APP_URL.'/emergency.php?uid='.urlencode($patient['patient_uid'] ?? ''), 'bi-heart-pulse', 'ICE card', 'জরুরি প্রোফাইল শেয়ার'],
    ];
    foreach ($actions as $a): ?>
      <a class="mc-action" href="<?= $a[0] ?>">
        <span class="mc-action-ico"><i class="bi <?= $a[1] ?>"></i></span>
        <span>
          <h3><?= $a[2] ?></h3>
          <p><?= $a[3] ?></p>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($pendingRequests)): ?>
<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Doctor access requests</h2>
    <span class="mc-btn mc-btn-soft mc-btn-sm"><?= count($pendingRequests) ?> waiting</span>
  </div>
  <div class="mc-list">
    <?php foreach ($pendingRequests as $req): ?>
      <div class="mc-list-item">
        <div>
          <strong>Dr. <?= htmlspecialchars($req['doctor_name']) ?></strong>
          <div style="font-size:.86rem;color:var(--muted)"><?= htmlspecialchars($req['specialization'] ?? '') ?> · <?= htmlspecialchars($req['hospital_name']) ?></div>
        </div>
        <div style="display:flex;gap:.4rem">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
            <button class="mc-btn mc-btn-primary mc-btn-sm" type="submit">Approve</button>
          </form>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="deny">
            <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
            <button class="mc-btn mc-btn-ghost mc-btn-sm" type="submit">Deny</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($activeLabTokens)): ?>
<div class="mc-panel">
  <div class="mc-panel-h"><h2>Active lab tokens</h2></div>
  <div class="mc-list">
    <?php foreach ($activeLabTokens as $tok): ?>
      <div class="mc-list-item">
        <div>
          <div class="mc-mono" style="font-size:1.1rem;color:var(--warn)"><?= htmlspecialchars($tok['lab_token'] ?? 'LT-XXXXX') ?></div>
          <div style="font-size:.86rem;color:var(--muted)"><?= htmlspecialchars($tok['doctor_name']) ?> · <?= htmlspecialchars($tok['hospital_name']) ?></div>
        </div>
        <div style="font-size:.85rem;font-weight:700;color:var(--muted)"><?= (int)($tok['done_tests'] ?? 0) ?>/<?= (int)($tok['total_tests'] ?? 0) ?> done</div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($upcomingAppointments)): ?>
<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Upcoming appointments</h2>
    <a href="<?= APP_URL ?>/patient/appointments.php" style="font-weight:800;color:var(--accent);font-size:.9rem">View all</a>
  </div>
  <div class="mc-list">
    <?php foreach ($upcomingAppointments as $apt): ?>
      <div class="mc-list-item">
        <div>
          <strong>#<?= htmlspecialchars((string)($apt['serial_number'] ?? '-')) ?> · Dr. <?= htmlspecialchars($apt['doctor_name']) ?></strong>
          <div style="font-size:.86rem;color:var(--muted)"><?= htmlspecialchars($apt['specialization'] ?? '') ?> · <?= htmlspecialchars($apt['hospital_name']) ?> · <?= htmlspecialchars($apt['appointment_date']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mc-panel">
  <div class="mc-panel-h">
    <h2>Recent prescriptions</h2>
    <a href="<?= APP_URL ?>/patient/prescriptions.php" style="font-weight:800;color:var(--accent);font-size:.9rem">All prescriptions</a>
  </div>
  <?php if (empty($recentRx)): ?>
    <p style="margin:0;color:var(--muted);font-size:.92rem">এখনো কোনো প্রেসক্রিপশন নেই।</p>
  <?php else: ?>
    <div class="mc-list">
      <?php foreach ($recentRx as $rx): ?>
        <a class="mc-list-item" href="<?= APP_URL ?>/patient/prescriptions.php?rx=<?= urlencode($rx['prescription_uid']) ?>">
          <div>
            <strong class="mc-mono"><?= htmlspecialchars($rx['prescription_uid']) ?></strong>
            <div style="font-size:.86rem;color:var(--muted)">Dr. <?= htmlspecialchars($rx['doctor_name']) ?> · <?= (int)$rx['medicines_count'] ?> medicines</div>
          </div>
          <span style="font-weight:800;color:var(--accent)">Open →</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layout/portal_shell_end.php'; ?>
