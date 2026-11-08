<?php
require_once __DIR__ . '/../app/config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 Not Found — MedCore</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/medcore.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/portal.css">
  <style>
    body {
      background: var(--mc-bg, #F8FAFC);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 20px;
    }
    .error-card {
      background: #FFFFFF;
      border: 1px solid var(--mc-border, #E2E8F0);
      border-radius: 16px;
      padding: 40px;
      max-width: 480px;
      width: 100%;
      text-align: center;
      box-shadow: 0 4px 16px rgba(0,0,0,0.05);
    }
    .error-badge {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: #E0F2FE;
      color: #0284C7;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      margin-bottom: 20px;
    }
    h1 { font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 8px; }
    p { font-size: 14px; color: #64748B; margin: 0 0 24px; line-height: 1.5; }
    .btn-group { display: flex; gap: 12px; justify-content: center; }
  </style>
</head>
<body>
  <div class="error-card">
    <div class="error-badge">
      <i class="bi bi-file-earmark-excel-fill"></i>
    </div>
    <h1>Record Not Found (404)</h1>
    <p>The requested page, patient identifier, or clinical document could not be located on the network.</p>
    <div class="btn-group">
      <a href="javascript:history.back()" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Go Back
      </a>
      <a href="<?= APP_URL ?>/" class="btn btn-primary">
        <i class="bi bi-house-door-fill"></i> Return Home
      </a>
    </div>
  </div>
</body>
</html>

