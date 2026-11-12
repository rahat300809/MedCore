<?php
$pageTitle = ($sectorTitle ?? 'MedCore') . ' — MedCore';
require __DIR__ . '/../layout/head.php';
require __DIR__ . '/../layout/public_header.php';
?>

<section class="mc-bleed-hero">
  <div class="mc-bleed-media" aria-hidden="true">
    <img src="<?= htmlspecialchars($sectorImage) ?>" alt="">
  </div>
  <div class="mc-bleed-scrim"></div>
  <div class="mc-wrap mc-bleed-copy">
    <p class="mc-bleed-kicker"><?= htmlspecialchars($sectorKicker) ?></p>
    <h1><?= htmlspecialchars($sectorTitle) ?></h1>
    <p><?= htmlspecialchars($sectorSubtitle) ?></p>
    <div class="mc-hero-actions">
      <a class="mc-btn mc-btn-primary mc-btn-xl" href="<?= htmlspecialchars($sectorLogin) ?>"><?= htmlspecialchars($sectorLoginLabel) ?></a>
      <?php if (!empty($sectorSecondary)): ?>
        <a class="mc-btn mc-btn-ghost-light mc-btn-xl" href="<?= htmlspecialchars($sectorSecondary) ?>"><?= htmlspecialchars($sectorSecondaryLabel) ?></a>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker">What you get</p>
      <h2>কাজের জন্য যা লাগে</h2>
    </div>
    <div class="mc-feature-rows">
      <?php foreach ($sectorFeatures as $i => $f): ?>
        <div class="mc-feature-row">
          <span class="mc-feature-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <div>
            <h3><?= htmlspecialchars($f['title']) ?></h3>
            <p><?= htmlspecialchars($f['body']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="mc-section" style="padding-top:0">
  <div class="mc-wrap mc-cta-strip">
    <div>
      <h2 style="font-size:1.35rem">Portal এ ঢুকুন</h2>
      <p style="margin:.35rem 0 0;color:var(--muted)">লগইনের পর শুধু প্রয়োজনীয় স্ক্রিন।</p>
    </div>
    <a class="mc-btn mc-btn-primary mc-btn-lg" href="<?= htmlspecialchars($sectorLogin) ?>"><?= htmlspecialchars($sectorLoginLabel) ?></a>
  </div>
</section>

<?php require __DIR__ . '/../layout/public_footer.php'; ?>
