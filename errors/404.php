<?php
require_once __DIR__ . '/../app/config/app.php';
$pageTitle = '404 — MedCore';
require __DIR__ . '/../app/views/layout/head.php';
?>
<div class="mc-wrap" style="min-height:70vh;display:grid;place-items:center;padding:2rem 0">
  <div class="mc-panel" style="max-width:420px;width:100%;text-align:center">
    <div style="width:56px;height:56px;border-radius:16px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;margin:0 auto 1rem;font-size:1.5rem">
      <i class="bi bi-compass"></i>
    </div>
    <p class="mc-kicker" style="justify-content:center">404</p>
    <h1 style="font-size:1.5rem;margin:.35rem 0">Page not found</h1>
    <p style="color:var(--muted);margin:0 0 1.25rem">এই ঠিকানায় কোনো পেজ নেই। হোম বা পোর্টাল থেকে আবার চেষ্টা করুন।</p>
    <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap">
      <a class="mc-btn mc-btn-primary" href="<?= APP_URL ?>/">Home</a>
      <a class="mc-btn mc-btn-secondary" href="<?= APP_URL ?>/role-selection.php">Portal login</a>
    </div>
  </div>
</div>
</body></html>
