<?php
$pageTitle = ($sectorTitle ?? 'MedCore') . ' — MedCore';
require __DIR__ . '/../layout/head.php';
require __DIR__ . '/../layout/public_header.php';
?>

<!-- 1. SECTOR HERO -->
<section class="mc-bleed-hero" style="min-height:56vh">
  <div class="mc-bleed-media" aria-hidden="true">
    <img src="<?= htmlspecialchars($sectorImage) ?>" alt="" style="filter:brightness(0.7) contrast(1.1)">
  </div>
  <div class="mc-bleed-scrim"></div>
  <div class="mc-wrap mc-bleed-copy" style="max-width:760px">
    <div class="mc-hero-badge" style="margin-bottom:0.85rem">
      <span class="status-dot"></span>
      <span><?= htmlspecialchars($sectorKicker) ?></span>
    </div>
    <h1 style="font-size:clamp(2rem, 4.5vw, 3rem);line-height:1.15;color:#fff;margin:0 0 0.85rem">
      <?= htmlspecialchars($sectorTitle) ?>
    </h1>
    <p style="font-size:1.05rem;line-height:1.6;color:rgba(255,255,255,0.88);margin:0 0 1.5rem">
      <?= htmlspecialchars($sectorSubtitle) ?>
    </p>
    <div class="mc-hero-actions-intl">
      <a class="mc-btn mc-btn-primary mc-btn-xl" href="<?= htmlspecialchars($sectorLogin) ?>">
        <i class="bi bi-box-arrow-in-right"></i> <?= htmlspecialchars($sectorLoginLabel) ?>
      </a>
      <?php if (!empty($sectorSecondary)): ?>
        <a class="mc-btn mc-btn-ghost-light mc-btn-xl" href="<?= htmlspecialchars($sectorSecondary) ?>">
          <?= htmlspecialchars($sectorSecondaryLabel) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- 2. SECTOR CAPABILITIES & FEATURES -->
<section class="mc-section">
  <div class="mc-wrap">
    <div class="mc-section-head">
      <p class="mc-kicker"><i class="bi bi-stars"></i> Clinical Capabilities</p>
      <h2>আপনার দায়িত্ব পালনে আধুনিক সুযোগ-সুবিধা</h2>
      <p>মেডকোর প্ল্যাটফর্মে আপনার ক্লিনিক্যাল ও প্রশাসনিক কাজ সহজে ও দ্রুত করার জন্য বিশেষায়িত ফিচারসমূহ।</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;margin-top:1.5rem">
      <?php foreach ($sectorFeatures as $i => $f): ?>
        <div class="mc-pillar-card" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between">
          <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.85rem">
              <span class="mc-pillar-num" style="font-size:1.6rem;color:#cbd5e1"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <div style="width:38px;height:38px;border-radius:10px;background:#ecfdf8;color:#0f766e;display:grid;place-items:center;font-size:1.2rem">
                <i class="bi bi-check2-circle"></i>
              </div>
            </div>
            <h3 style="font-size:1.15rem;color:#0f172a;margin:0 0 0.45rem"><?= htmlspecialchars($f['title']) ?></h3>
            <p style="font-size:0.88rem;line-height:1.6;color:#64748b;margin:0"><?= htmlspecialchars($f['body']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 3. CLINICAL CTA BANNER -->
<section class="mc-section" style="padding-top:0">
  <div class="mc-wrap">
    <div style="background:linear-gradient(135deg,#071927 0%,#0b253a 100%);color:#fff;border-radius:20px;padding:clamp(1.8rem,4vw,2.5rem);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1.5rem;box-shadow:0 16px 36px rgba(11,37,58,0.18)">
      <div style="max-width:580px">
        <span class="mc-pill" style="background:rgba(255,255,255,0.1);color:#7dd3fc;border:1px solid rgba(255,255,255,0.2);font-size:0.75rem;margin-bottom:0.5rem">
          <i class="bi bi-shield-check"></i> 100% Consent & Privacy Protection
        </span>
        <h2 style="font-size:clamp(1.4rem,3vw,1.8rem);color:#fff;margin:0.25rem 0 0.5rem">পোর্টালে এখনই প্রবেশ করুন</h2>
        <p style="font-size:0.92rem;color:rgba(255,255,255,0.8);margin:0;line-height:1.55">
          লগইনের পর সরাসরি আপনার ড্যাশবোর্ডে গিয়ে প্রেসক্রিপশন, রিপোর্ট বা রোগীর স্বাস্থ্য তথ্য অ্যাক্সেস করুন।
        </p>
      </div>
      <div>
        <a class="mc-btn mc-btn-primary mc-btn-xl" href="<?= htmlspecialchars($sectorLogin) ?>">
          <?= htmlspecialchars($sectorLoginLabel) ?> <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../layout/public_footer.php'; ?>
