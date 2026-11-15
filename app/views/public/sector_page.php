<?php
$pageTitle = ($sectorTitle ?? 'MedCore') . ' — MedCore';
$bodyClass = 'mc-public';
require __DIR__ . '/../layout/head.php';
require __DIR__ . '/../layout/public_header.php';
?>

<section class="mc-stage mc-stage-sector">
  <div class="mc-stage-media" aria-hidden="true">
    <img src="<?= htmlspecialchars($sectorImage) ?>" alt="">
  </div>
  <div class="mc-stage-veil" aria-hidden="true"></div>
  <div class="mc-wrap mc-stage-copy">
    <p class="mc-brand-hero">Med<span>Core</span></p>
    <p class="mc-sector-kicker"><?= htmlspecialchars($sectorKicker) ?></p>
    <h1><?= htmlspecialchars($sectorTitle) ?></h1>
    <p class="mc-stage-lede"><?= htmlspecialchars($sectorSubtitle) ?></p>
    <div class="mc-stage-cta">
      <a class="mc-btn mc-btn-primary mc-btn-lg" href="<?= htmlspecialchars($sectorLogin) ?>">
        <?= htmlspecialchars($sectorLoginLabel) ?>
      </a>
      <?php if (!empty($sectorSecondary)): ?>
        <a class="mc-btn mc-btn-ghost-light mc-btn-lg" href="<?= htmlspecialchars($sectorSecondary) ?>">
          <?= htmlspecialchars($sectorSecondaryLabel) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head mc-reveal">
      <h2>What you can do</h2>
      <p>এক পোর্টালে প্রয়োজনীয় কাজগুলো পরিষ্কারভাবে সাজানো।</p>
    </div>
    <div class="mc-feature-lines mc-reveal">
      <?php foreach ($sectorFeatures as $i => $f): ?>
        <div class="mc-feature-line">
          <span class="mc-feature-idx"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <div>
            <h3><?= htmlspecialchars($f['title']) ?></h3>
            <p><?= htmlspecialchars($f['body']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mc-reveal" style="margin-top:2rem">
      <a class="mc-btn mc-btn-primary mc-btn-lg" href="<?= htmlspecialchars($sectorLogin) ?>">
        <?= htmlspecialchars($sectorLoginLabel) ?>
      </a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../layout/public_footer.php'; ?>
