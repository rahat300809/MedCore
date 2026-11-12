<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/views/portal/navs.php';

requireAuth('PATIENT');

$db = getDB();
$patientId = (int)$_SESSION['patient_id'];

$stmt = $db->prepare("SELECT p.*, u.email, u.phone FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

$stmtCons = $db->prepare("
    SELECT 'CONSULTATION' AS event_type, c.id, c.consultation_date AS event_date, c.consultation_uid AS event_ref,
           c.diagnosis_summary AS title, c.clinical_notes AS detail,
           d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM consultations c
    JOIN doctors d ON c.doctor_id = d.id
    JOIN hospitals h ON c.hospital_id = h.id
    WHERE c.patient_id = ?
");
$stmtCons->execute([$patientId]);
$consultations = $stmtCons->fetchAll();

$stmtRx = $db->prepare("
    SELECT 'PRESCRIPTION' AS event_type, rx.id, rx.created_at AS event_date, rx.prescription_uid AS event_ref,
           rx.diagnosis AS title, rx.advice AS detail,
           d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM prescriptions rx
    JOIN doctors d ON rx.doctor_id = d.id
    JOIN hospitals h ON rx.hospital_id = h.id
    WHERE rx.patient_id = ?
");
$stmtRx->execute([$patientId]);
$prescriptions = $stmtRx->fetchAll();

$stmtLab = $db->prepare("
    SELECT 'LAB_ORDER' AS event_type, plo.id, plo.created_at AS event_date, plo.lab_token AS event_ref,
           CONCAT('Diagnostic Order (', plo.lab_token, ')') AS title, plo.test_notes AS detail,
           d.full_name AS doctor_name, d.specialization, h.name AS hospital_name
    FROM prescription_lab_orders plo
    JOIN doctors d ON plo.doctor_id = d.id
    JOIN hospitals h ON plo.hospital_id = h.id
    WHERE plo.patient_id = ?
");
$stmtLab->execute([$patientId]);
$labOrders = $stmtLab->fetchAll();

$timeline = array_merge($consultations, $prescriptions, $labOrders);
usort($timeline, function ($a, $b) {
    return strtotime($b['event_date']) - strtotime($a['event_date']);
});

$doctorLabel = static function (string $name): string {
    $clean = preg_replace('/^\s*Dr\.?\s+/i', '', trim($name)) ?: $name;
    return 'Dr. ' . $clean;
};

$portalRole = 'Patient';
$portalRoleBn = 'Patient portal';
$portalUser = $patient['full_name'] ?? ($_SESSION['name'] ?? 'Patient');
$portalMeta = ($patient['patient_uid'] ?? '') . ' · Timeline';
$portalTitle = 'Health timeline';
$portalSubtitle = 'পরামর্শ, প্রেসক্রিপশন ও ল্যাব অর্ডার এক সারিতে';
$portalNav = mc_patient_nav('timeline');
$portalTopAction = '<a class="mc-btn mc-btn-secondary mc-btn-sm" href="' . APP_URL . '/patient/dashboard.php">Dashboard</a>';
require __DIR__ . '/../app/views/layout/portal_shell_start.php';
?>
<style>
.mc-timeline{position:relative;padding:.25rem 0 1rem;margin:0}
.mc-timeline::before{content:"";position:absolute;left:14px;top:.25rem;bottom:.25rem;width:2px;background:var(--line)}
.mc-tl-item{position:relative;padding:0 0 1.15rem 2.75rem}
.mc-tl-dot{position:absolute;left:4px;top:.35rem;width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#fff;border:2px solid var(--accent);color:var(--accent);font-size:.72rem}
.mc-tl-item.is-rx .mc-tl-dot{border-color:#0f766e;color:#0f766e}
.mc-tl-item.is-lab .mc-tl-dot{border-color:#7c3aed;color:#7c3aed}
.mc-tl-meta{font-size:.78rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin:0 0 .35rem}
.mc-tl-item.is-rx .mc-tl-meta{color:#0f766e}
.mc-tl-item.is-lab .mc-tl-meta{color:#7c3aed}
.mc-tl-head{display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;flex-wrap:wrap}
.mc-tl-head h3{margin:0;font-size:1.02rem}
.mc-tl-time{font-size:.82rem;color:var(--muted);white-space:nowrap}
.mc-tl-sub{margin:.35rem 0 0;font-size:.86rem;color:var(--muted)}
.mc-tl-note{margin:.7rem 0 0;padding:.7rem .85rem;border-radius:10px;background:#f8fafc;border:1px solid var(--line);font-size:.88rem;color:#334155}
</style>

<?php if (empty($timeline)): ?>
  <div class="mc-panel" style="text-align:center;padding:2.4rem 1rem">
    <h2>কোনো ইভেন্ট নেই</h2>
    <p style="color:var(--muted);margin:.5rem 0 0">পরামর্শ, প্রেসক্রিপশন ও ল্যাব টেস্ট এখানে আসবে।</p>
  </div>
<?php else: ?>
  <div class="mc-timeline">
    <?php foreach ($timeline as $item):
      $type = $item['event_type'];
      $cls = $type === 'PRESCRIPTION' ? 'is-rx' : ($type === 'LAB_ORDER' ? 'is-lab' : '');
      $icon = $type === 'PRESCRIPTION' ? 'bi-prescription2' : ($type === 'LAB_ORDER' ? 'bi-eyedropper' : 'bi-stethoscope');
      $label = str_replace('_', ' ', $type);
    ?>
      <article class="mc-tl-item <?= $cls ?>">
        <div class="mc-tl-dot"><i class="bi <?= $icon ?>"></i></div>
        <div class="mc-panel" style="margin:0">
          <div class="mc-tl-meta"><?= htmlspecialchars($label) ?></div>
          <div class="mc-tl-head">
            <h3><?= htmlspecialchars($item['title'] ?: 'Clinical note') ?></h3>
            <span class="mc-tl-time"><?= date('d M Y, h:i A', strtotime($item['event_date'])) ?></span>
          </div>
          <p class="mc-tl-sub">
            <?= htmlspecialchars($doctorLabel((string)$item['doctor_name'])) ?>
            · <?= htmlspecialchars($item['specialization'] ?? 'Physician') ?>
            · <?= htmlspecialchars($item['hospital_name']) ?>
          </p>
          <?php if (!empty($item['detail'])): ?>
            <div class="mc-tl-note"><?= nl2br(htmlspecialchars($item['detail'])) ?></div>
          <?php endif; ?>
          <?php if ($type === 'PRESCRIPTION'): ?>
            <div style="margin-top:.85rem">
              <a class="mc-btn mc-btn-secondary mc-btn-sm" href="<?= APP_URL ?>/patient/prescription-report.php?id=<?= (int)$item['id'] ?>">View prescription</a>
            </div>
          <?php elseif ($type === 'LAB_ORDER' && !empty($item['event_ref'])): ?>
            <div style="margin-top:.85rem">
              <span class="lab-status"><?= htmlspecialchars($item['event_ref']) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/layout/portal_shell_end.php'; ?>
