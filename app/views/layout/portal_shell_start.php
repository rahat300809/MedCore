<?php
require_once dirname(__DIR__) . '/media.php';
$pageTitle = $pageTitle ?? (($portalTitle ?? 'Portal') . ' — MedCore');
$bodyClass = trim(($bodyClass ?? '') . ' mc-app-body');
require __DIR__ . '/head.php';
?>
<div class="mc-app">
  <aside class="mc-rail">
    <div class="mc-rail-brand">
      <a class="mc-brand" href="<?= APP_URL ?>/">
        <span class="mc-brand-mark">M</span>
        <span>
          <span class="mc-brand-name" style="font-size:1rem">Med<span>Core</span></span>
          <span class="mc-brand-sub"><?= htmlspecialchars($portalRoleBn ?? $portalRole ?? 'Portal') ?></span>
        </span>
      </a>
    </div>

    <div class="mc-rail-user" style="margin-bottom:.85rem;padding:.75rem;border:1px solid var(--line);border-radius:12px;background:#f9fafb">
      <strong><?= htmlspecialchars($portalUser ?? '') ?></strong>
      <?= htmlspecialchars($portalMeta ?? '') ?>
    </div>

    <nav aria-label="Portal">
      <?php foreach (($portalNav ?? []) as $item):
        $active = !empty($item['active']);
      ?>
        <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $active ? 'active' : '' ?>">
          <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
          <?= htmlspecialchars($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <a href="<?= APP_URL ?>/app/helpers/logout.php" class="mc-btn mc-btn-ghost mc-btn-sm mc-btn-block" style="margin-top:1rem">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </aside>

  <div class="mc-app-main">
    <div class="mc-app-top">
      <div>
        <h1><?= htmlspecialchars($portalTitle ?? '') ?></h1>
        <?php if (!empty($portalSubtitle)): ?>
          <p><?= htmlspecialchars($portalSubtitle) ?></p>
        <?php endif; ?>
      </div>
      <?php if (!empty($portalTopAction)) echo $portalTopAction; ?>
    </div>
