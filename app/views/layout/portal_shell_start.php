<?php
require_once dirname(__DIR__) . '/media.php';
$pageTitle = $pageTitle ?? (($portalTitle ?? 'Portal') . ' — MedCore');
$bodyClass = trim(($bodyClass ?? '') . ' mc-app-body');
$accentStyle = !empty($portalAccent) ? '--accent:' . htmlspecialchars($portalAccent) . ';' : '';
$userStr = trim((string)($portalUser ?? 'M'));
$initials = strtoupper(mb_substr(preg_replace('/^(Dr\.?\s+)/i', '', $userStr) ?: $userStr, 0, 1));
require __DIR__ . '/head.php';
?>
<div class="mc-app"<?= $accentStyle ? ' style="' . $accentStyle . '"' : '' ?>>
  <aside class="mc-rail" id="mcRail">
    <div class="mc-rail-brand">
      <a class="mc-brand" href="<?= APP_URL ?>/">
        <span class="mc-brand-mark">M</span>
        <span>
          <span class="mc-brand-name" style="font-size:1rem">Med<span>Core</span></span>
          <span class="mc-brand-sub"><?= htmlspecialchars($portalRoleBn ?? $portalRole ?? 'Portal') ?></span>
        </span>
      </a>
      <button type="button" class="mc-rail-toggle" id="mcRailToggle" aria-label="Toggle portal menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
    </div>

    <div class="mc-rail-body" id="mcRailBody">
      <div class="mc-rail-user">
        <span class="mc-rail-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
        <div style="min-width:0;flex:1">
          <strong style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($portalUser ?? '') ?></strong>
          <div style="font-size:.78rem;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($portalMeta ?? '') ?></div>
        </div>
      </div>

      <?php if (!empty($portalRailContext)) echo $portalRailContext; ?>

      <nav aria-label="Portal">
        <?php foreach (($portalNav ?? []) as $item):
          $active = !empty($item['active']);
        ?>
          <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
            <span style="flex:1;min-width:0"><?= htmlspecialchars($item['label']) ?></span>
            <?php if (!empty($item['badge'])): ?>
              <span class="mc-nav-badge"><?= htmlspecialchars((string)$item['badge']) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <a href="<?= APP_URL ?>/app/helpers/logout.php" class="mc-btn mc-btn-ghost mc-btn-sm mc-btn-block" style="margin-top:1rem">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </aside>

  <div class="mc-app-main">
    <?php if (!empty($portalTopBanner)) echo $portalTopBanner; ?>

    <div class="mc-app-top">
      <div>
        <h1><?= htmlspecialchars($portalTitle ?? '') ?></h1>
        <?php if (!empty($portalSubtitle)): ?>
          <p><?= htmlspecialchars($portalSubtitle) ?></p>
        <?php endif; ?>
      </div>
      <?php if (!empty($portalTopAction)): ?>
        <div class="mc-app-top-actions"><?= $portalTopAction ?></div>
      <?php endif; ?>
    </div>
