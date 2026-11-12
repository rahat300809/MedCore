<?php
if (file_exists(__DIR__ . '/app/config/app.php')) {
    require_once __DIR__ . '/app/config/app.php';
    require_once __DIR__ . '/app/config/database.php';
} else {
    require_once __DIR__ . '/../app/config/app.php';
    require_once __DIR__ . '/../app/config/database.php';
}
require_once __DIR__ . '/../app/views/media.php';

$db = getDB();
$uid = trim($_GET['uid'] ?? $_GET['id'] ?? '');

$patient = null;
$allergies = [];
$conditions = [];
$age = null;

if (!empty($uid)) {
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
        $stmtAllergy = $db->prepare("
            SELECT pa.*, a.name AS allergy_name
            FROM patient_allergies pa
            JOIN allergies a ON a.id = pa.allergy_id
            WHERE pa.patient_id = ? AND pa.severity IN ('SEVERE', 'LIFE_THREATENING')
            ORDER BY pa.severity DESC
        ");
        $stmtAllergy->execute([$patient['id']]);
        $allergies = $stmtAllergy->fetchAll();

        $stmtHist = $db->prepare("
            SELECT condition_name, status, notes
            FROM medical_histories
            WHERE patient_id = ? AND status IN ('ACTIVE', 'CHRONIC')
            ORDER BY id DESC
        ");
        $stmtHist->execute([$patient['id']]);
        $conditions = $stmtHist->fetchAll();
    }
}

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/emergency.php');
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($currentUrl);

$pageTitle = 'Emergency ICE Card — MedCore';
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';
?>

<section class="relative overflow-hidden py-14">
  <div class="absolute inset-0 mc-hero-bg" style="background-image:url('<?= MC_IMG_EMERGENCY ?>')"></div>
  <div class="absolute inset-0 bg-[#3f0a0a]/82"></div>
  <div class="relative z-10 mx-auto w-[min(720px,calc(100%-1.5rem))]">
    <p class="mb-3 inline-flex rounded-full bg-red-600 px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] text-white">In Case of Emergency</p>
    <h1 class="font-display text-4xl font-bold text-white">ICE Health Card</h1>
    <p class="mt-2 text-white/80">লগইন ছাড়াই জরুরি তথ্য — first responders এর জন্য।</p>

    <form method="GET" class="mt-6 flex gap-2">
      <input type="text" name="uid" value="<?= htmlspecialchars($uid ?: 'PT-000001') ?>" placeholder="Patient UID e.g. PT-000001"
             class="flex-1 rounded-2xl border-0 px-4 py-3 text-sm font-semibold outline-none">
      <button class="mc-btn mc-btn-alert mc-btn-lg" type="submit">Search</button>
    </form>
  </div>
</section>

<section class="mx-auto w-[min(720px,calc(100%-1.5rem))] -mt-6 pb-16">
  <?php if (!$patient && $uid !== ''): ?>
    <div class="rounded-[24px] border border-rose-200 bg-white p-8 text-center shadow-soft">
      <p class="font-display text-xl font-bold">Patient not found</p>
      <p class="mt-2 text-sm text-ink-soft/70">Try demo UID: PT-000001</p>
    </div>
  <?php elseif ($patient): ?>
    <article class="overflow-hidden rounded-[28px] border border-rose-200 bg-white shadow-lift">
      <div class="bg-gradient-to-r from-red-600 to-red-800 px-6 py-5 text-white">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-red-100">Verified EHR · Emergency</p>
            <h2 class="mt-1 font-display text-3xl font-bold"><?= htmlspecialchars($patient['full_name']) ?></h2>
            <p class="text-sm text-red-100"><?= htmlspecialchars($patient['patient_uid']) ?> · <?= (int)$age ?> yrs · <?= htmlspecialchars($patient['gender'] ?? '') ?></p>
          </div>
          <div class="grid h-20 w-20 place-items-center rounded-2xl bg-white/15 text-center backdrop-blur">
            <p class="font-display text-3xl font-bold leading-none"><?= htmlspecialchars($patient['blood_group'] ?? '—') ?></p>
            <p class="text-[10px] uppercase tracking-wide text-red-100">Blood</p>
          </div>
        </div>
      </div>

      <div class="space-y-4 p-6">
        <?php if (!empty($allergies)): ?>
          <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs font-bold uppercase tracking-[0.1em] text-rose-800">Critical allergies</p>
            <ul class="mt-2 space-y-1 text-sm font-semibold text-rose-900">
              <?php foreach ($allergies as $a): ?>
                <li>• <?= htmlspecialchars($a['allergy_name']) ?> (<?= htmlspecialchars($a['severity']) ?>)</li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!empty($conditions)): ?>
          <div class="rounded-2xl border border-[#d7e3ea] bg-mist p-4">
            <p class="text-xs font-bold uppercase tracking-[0.1em] text-ink-soft/70">Active conditions</p>
            <ul class="mt-2 space-y-1 text-sm font-semibold">
              <?php foreach ($conditions as $c): ?>
                <li>• <?= htmlspecialchars($c['condition_name']) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!empty($patient['emergency_contact_phone'])): ?>
          <a href="tel:<?= htmlspecialchars($patient['emergency_contact_phone']) ?>"
             class="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-900">
            <span><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($patient['emergency_contact_name'] ?: 'Emergency contact') ?></span>
            <span class="font-mono"><?= htmlspecialchars($patient['emergency_contact_phone']) ?></span>
          </a>
        <?php endif; ?>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-[#d7e3ea] pt-4">
          <img src="<?= htmlspecialchars($qrUrl) ?>" alt="ICE QR" class="h-24 w-24 rounded-xl border border-[#d7e3ea]">
          <p class="max-w-xs text-xs text-ink-soft/70">Scan to reopen this ICE card on any device. No login required.</p>
        </div>
      </div>
    </article>
  <?php else: ?>
    <div class="rounded-[24px] border border-[#d7e3ea] bg-white p-8 text-center shadow-soft">
      <p class="font-display text-xl font-bold">Enter a Patient UID</p>
      <a class="mc-btn mc-btn-alert mc-btn-lg mt-4 inline-flex" href="?uid=PT-000001">Open demo ICE (PT-000001)</a>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
