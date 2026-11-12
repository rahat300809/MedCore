<?php
$pageTitle = $pageTitle ?? 'MedCore — One Record. Every Care.';
$pageDescription = $pageDescription ?? 'Consent-driven national EHR for Bangladesh.';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Instrument+Sans:wght@500;600;700&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/competition.css?v=8">
  <style>
    /* critical fallback so layout never renders as raw black/unstyled */
    body{margin:0;background:#f3f5f7;color:#101828;font-family:"Hind Siliguri","Instrument Sans",system-ui,sans-serif}
    .mc-nav{background:#fff;border-bottom:1px solid #e4e7ec}
    .mc-drawer{display:none}
    .mc-drawer.open{display:block}
    .mc-bleed-hero{position:relative;min-height:70vh;background:#0b1f2a;color:#fff;display:flex;align-items:flex-end}
    .mc-bleed-media,.mc-bleed-scrim{position:absolute;inset:0}
    .mc-bleed-media img{width:100%;height:100%;object-fit:cover}
    .mc-field{display:block;margin-top:1rem}
    .mc-field span{display:block;margin-bottom:.35rem;font-weight:700}
    .mc-field input{display:block;width:100%;padding:.8rem;border:1px solid #e4e7ec;border-radius:10px}
    @media(min-width:960px){.mc-nav-links{display:flex!important}.mc-menu-btn{display:none!important}.mc-drawer{display:none!important}}
  </style>
  <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">
