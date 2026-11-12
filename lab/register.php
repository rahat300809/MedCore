<?php
require_once __DIR__ . '/../app/config/app.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/services/AuditService.php';

initSession();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    validateCsrf();

    $labName    = trim($_POST['lab_name'] ?? '');
    $regNum     = trim($_POST['registration_number'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $services   = trim($_POST['services'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if (empty($labName) || empty($regNum) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();

        // Check email uniqueness
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Check reg number uniqueness
            $stmtReg = $db->prepare("SELECT id FROM laboratories WHERE registration_number = ?");
            $stmtReg->execute([$regNum]);
            if ($stmtReg->fetch()) {
                $error = 'A laboratory with this registration number already exists.';
            } else {
                $db->beginTransaction();
                try {
                    // Generate unique lab UID
                    $stmtMax = $db->query("SELECT MAX(CAST(SUBSTRING(lab_uid,5) AS UNSIGNED)) AS mx FROM laboratories");
                    $maxRow   = $stmtMax->fetch();
                    $labUid   = 'LAB-' . str_pad(($maxRow['mx'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);

                    // Create user
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->prepare("
                        INSERT INTO users (role, email, phone, password_hash, status, email_verified)
                        VALUES ('LABORATORY', ?, ?, ?, 'ACTIVE', 0)
                    ")->execute([$email, $phone, $hash]);
                    $userId = $db->lastInsertId();

                    // Create lab record
                    $db->prepare("
                        INSERT INTO laboratories
                            (user_id, lab_uid, name, registration_number, contact_phone,
                             contact_email, address, city, services, verification_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
                    ")->execute([$userId, $labUid, $labName, $regNum, $phone, $email, $address, $city, $services]);
                    $labId = $db->lastInsertId();

                    AuditService::log(AuditService::LAB_REGISTERED, [
                        'user_id'  => $userId,
                        'metadata' => ['lab_uid' => $labUid, 'lab_name' => $labName],
                    ]);

                    $db->commit();
                    $success = "Laboratory registered successfully! Your Lab UID is <strong>{$labUid}</strong>. Please wait for admin verification before you can log in.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
    }
}
require __DIR__ . '/../app/views/lab/register_view.php';

