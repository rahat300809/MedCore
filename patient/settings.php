<?php
require_once __DIR__ . '/../app/config/app.php';
header('Location: ' . APP_URL . '/patient/profile.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0;url=<?= APP_URL ?>/patient/profile.php">
  <title>Redirecting — MedCore</title>
</head>
<body>
  <p>Redirecting to <a href="<?= APP_URL ?>/patient/profile.php">Patient Profile</a>...</p>
</body>
</html>
<?php
exit;
