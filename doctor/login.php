<?php
/**
 * Doctor Login Gateway
 * Routes doctor to select hospital affiliation.
 */
require_once __DIR__ . '/../app/config/app.php';
header('Location: ' . APP_URL . '/doctor/select-hospital.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0;url=<?= APP_URL ?>/doctor/select-hospital.php">
  <title>Doctor Login — MedCore</title>
</head>
<body>
  <p>Redirecting to <a href="<?= APP_URL ?>/doctor/select-hospital.php">Doctor Portal Authentication</a>...</p>
</body>
</html>
<?php
exit;
