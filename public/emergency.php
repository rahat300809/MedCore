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

if ($uid !== '') {
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

$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
    ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = APP_URL . '/emergency.php' . ($patient ? ('?uid=' . urlencode($patient['patient_uid'])) : ($uid !== '' ? ('?uid=' . urlencode($uid)) : ''));
$cardUrl = $scheme . '://' . $host . parse_url($path, PHP_URL_PATH) . (parse_url($path, PHP_URL_QUERY) ? ('?' . parse_url($path, PHP_URL_QUERY)) : '');
if (str_starts_with($path, 'http')) {
    $cardUrl = $path;
}
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($cardUrl);

$pageTitle = 'Emergency ICE Card — MedCore';
$bodyClass = 'ice-page';
require __DIR__ . '/../app/views/layout/head.php';
require __DIR__ . '/../app/views/layout/public_header.php';
?>
<style>
.ice-card{overflow:hidden;border:1px solid #fecaca;border-radius:18px;background:#fff;box-shadow:0 18px 40px rgba(180,35,24,.08)}
.ice-card-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;padding:1.25rem 1.35rem;background:linear-gradient(135deg,#b42318,#7f1d1d);color:#fff}
.ice-blood{min-width:5.2rem;padding:.85rem .7rem;border-radius:14px;background:rgba(255,255,255,.14);text-align:center}
.ice-blood strong{display:block;font-size:1.8rem;line-height:1;font-family:var(--font-d)}
.ice-blood span{display:block;margin-top:.35rem;font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;opacity:.85}
.ice-body{padding:1.25rem 1.35rem;display:grid;gap:1rem}
.ice-block{border:1px solid var(--line);border-radius:14px;padding:1rem;background:#fafbfc}
.ice-block.danger{border-color:#fecaca;background:var(--danger-soft)}
.ice-block h3{margin:0 0 .55rem;font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
.ice-block.danger h3{color:var(--danger)}
.ice-block ul{margin:0;padding-left:1.1rem}
.ice-block li{margin:.25rem 0;font-weight:700}
.ice-call{display:flex;justify-content:space-between;gap:.75rem;align-items:center;flex-wrap:wrap;padding:1rem 1.1rem;border-radius:14px;border:1px solid #a7f3d0;background:#ecfdf5;color:#065f46;font-weight:800;text-decoration:none}
.ice-qr{display:flex;gap:1rem;align-items:center;flex-wrap:wrap;padding-top:.35rem;border-top:1px solid var(--line)}
.ice-qr img{width:120px;height:120px;border-radius:12px;border:1px solid var(--line);background:#fff}
@media print{
  .mc-strip,.mc-nav,.mc-footer,.ice-search,.no-print{display:none!important}
  .ice-card{box-shadow:none!important}
}
</style>

<section class="mc-bleed-hero mc-bleed-hero-compact">
  <div class="mc-bleed-media" aria-hidden="true">
    <img src="<?= MC_IMG_EMERGENCY ?>" alt="">
  </div>
  <div class="mc-bleed-scrim mc-bleed-scrim-danger"></div>
  <div class="mc-wrap mc-bleed-copy">
    <p class="mc-bleed-brand">Med<span>Core</span></p>
    <p class="mc-bleed-kicker">In Case of Emergency</p>
    <h1>ICE Health Card</h1>
    <p>লগইন ছাড়াই জরুরি তথ্য — first responders এর জন্য।</p>
  </div>
</section>

<section class="mc-section ice-search" style="padding-top:1.5rem">
  <div class="mc-wrap" style="max-width:720px">
    <div class="mc-panel">
      <form method="GET" style="display:flex;gap:.55rem;flex-wrap:wrap;align-items:end">
        <label class="mc-field" style="margin:0;flex:1;min-width:220px">
          <span>Patient UID / phone</span>
          <input type="text" name="uid" value="<?= htmlspecialchars($uid ?: 'PT-000001') ?>" placeholder="PT-000001" required>
        </label>
        <button class="mc-btn mc-btn-danger" type="submit">Open ICE</button>
        <a class="mc-btn mc-btn-secondary no-print" href="?uid=PT-000001">Demo PT-000001</a>
      </form>
    </div>

    <?php if (!$patient && $uid !== ''): ?>
      <div class="mc-panel" style="text-align:center;padding:2rem 1rem">
        <h2>Patient not found</h2>
        <p style="color:var(--muted);margin:.5rem 0 0">Demo UID: <code class="mc-mono">PT-000001</code></p>
      </div>
    <?php elseif ($patient): ?>
      <article class="ice-card" id="ice-card">
        <div class="ice-card-top">
          <div>
            <p style="margin:0;font-size:.75rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;opacity:.85">Verified EHR · Emergency</p>
            <h2 style="margin:.35rem 0 0;font-size:clamp(1.5rem,4vw,2rem)"><?= htmlspecialchars($patient['full_name']) ?></h2>
            <p style="margin:.35rem 0 0;opacity:.9;font-size:.92rem">
              <?= htmlspecialchars($patient['patient_uid']) ?>
              · <?= (int)$age ?> yrs
              · <?= htmlspecialchars($patient['gender'] ?? '') ?>
            </p>
          </div>
          <div class="ice-blood">
            <strong><?= htmlspecialchars($patient['blood_group'] ?? '—') ?></strong>
            <span>Blood</span>
          </div>
        </div>

        <div class="ice-body">
          <?php if (!empty($allergies)): ?>
            <div class="ice-block danger">
              <h3>Critical allergies</h3>
              <ul>
                <?php foreach ($allergies as $a): ?>
                  <li><?= htmlspecialchars($a['allergy_name']) ?> (<?= htmlspecialchars($a['severity']) ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php else: ?>
            <div class="ice-block">
              <h3>Critical allergies</h3>
              <p style="margin:0;color:var(--muted)">No severe / life-threatening allergies on record.</p>
            </div>
          <?php endif; ?>

          <?php if (!empty($conditions)): ?>
            <div class="ice-block">
              <h3>Active conditions</h3>
              <ul>
                <?php foreach ($conditions as $c): ?>
                  <li><?= htmlspecialchars($c['condition_name']) ?> <span style="color:var(--muted);font-weight:600">(<?= htmlspecialchars($c['status']) ?>)</span></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($patient['emergency_contact_phone'])): ?>
            <a class="ice-call" href="tel:<?= htmlspecialchars($patient['emergency_contact_phone']) ?>">
              <span><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($patient['emergency_contact_name'] ?: 'Emergency contact') ?></span>
              <span class="mc-mono"><?= htmlspecialchars($patient['emergency_contact_phone']) ?></span>
            </a>
          <?php endif; ?>

          <?php if (!empty($patient['phone'])): ?>
            <div class="ice-block">
              <h3>Patient phone</h3>
              <p style="margin:0;font-weight:800"><a href="tel:<?= htmlspecialchars($patient['phone']) ?>" style="color:inherit;text-decoration:none"><?= htmlspecialchars($patient['phone']) ?></a></p>
            </div>
          <?php endif; ?>

          <div class="ice-qr">
            <img src="<?= htmlspecialchars($qrUrl) ?>" alt="ICE QR code">
            <div>
              <p style="margin:0;font-weight:800">Scan to reopen this ICE card</p>
              <p style="margin:.35rem 0 0;color:var(--muted);font-size:.88rem;line-height:1.5">No login required. Share with paramedics / ER staff.</p>
              <button type="button" class="mc-btn mc-btn-secondary mc-btn-sm no-print" style="margin-top:.75rem" onclick="window.print()">Print card</button>
            </div>
          </div>
        </div>
      </article>
    <?php else: ?>
      <div class="mc-panel" style="text-align:center;padding:2rem 1rem">
        <h2>Enter a Patient UID</h2>
        <p style="color:var(--muted);margin:.5rem 0 1rem">জরুরি কার্ড খুলতে UID দিন।</p>
        <a class="mc-btn mc-btn-danger" href="?uid=PT-000001">Open demo ICE (PT-000001)</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../app/views/layout/public_footer.php'; ?>
