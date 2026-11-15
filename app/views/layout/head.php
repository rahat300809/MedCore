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
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/competition.css?v=62">
  <style>
    body{margin:0;background:#f4f7fb;color:#0a1628;font-family:"Source Sans 3","Hind Siliguri",sans-serif;-webkit-font-smoothing:antialiased}
    .mc-drawer{display:none}.mc-drawer.open{display:block}
    .mc-field{display:block;margin-top:1rem}
    .mc-field span{display:block;margin-bottom:.35rem;font-weight:700}
    .mc-field input{display:block;width:100%;padding:.8rem;border:1px solid #d5e0db;border-radius:10px}
    @media(min-width:960px){.mc-nav-links{display:flex!important}.mc-menu-btn{display:none!important}.mc-drawer{display:none!important}}
  </style>
  <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">
