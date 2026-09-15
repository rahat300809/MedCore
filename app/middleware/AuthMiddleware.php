<?php
/**
 * MedCore Authentication Middleware
 * Role-based access control for all portals
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AuditService.php';

/**
 * Initialize session securely
 */
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false, // Set to true with HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Require authentication as a specific role
 * Redirects to appropriate login if not authenticated
 */
function requireAuth(string $role): void {
    initSession();

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        AuditService::log(AuditService::UNAUTHORIZED_ACCESS, [
            'metadata' => ['required_role' => $role, 'url' => $_SERVER['REQUEST_URI'] ?? '']
        ]);
        redirectToLogin($role);
    }

    if ($_SESSION['role'] !== $role) {
        AuditService::log(AuditService::UNAUTHORIZED_ACCESS, [
            'user_id' => $_SESSION['user_id'],
            'metadata' => ['required_role' => $role, 'actual_role' => $_SESSION['role']]
        ]);
        http_response_code(403);
        include __DIR__ . '/../../errors/403.php';
        exit;
    }
}

/**
 * Require doctor to have active hospital context
 * CRITICAL: Called on every doctor portal page
 */
function requireDoctorHospitalContext(): void {
    requireAuth('DOCTOR');

    if (!isset($_SESSION['doctor_id']) || !isset($_SESSION['hospital_id'])) {
        session_destroy();
        header('Location: ' . APP_URL . '/doctor/select-hospital.php');
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("
        SELECT dh.id FROM doctor_hospitals dh
        JOIN doctors d ON dh.doctor_id = d.id
        JOIN hospitals h ON dh.hospital_id = h.id
        WHERE dh.doctor_id = ?
          AND dh.hospital_id = ?
          AND dh.status = 'APPROVED'
          AND d.verification_status = 'VERIFIED'
          AND h.verification_status = 'VERIFIED'
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['doctor_id'], $_SESSION['hospital_id']]);

    if (!$stmt->fetch()) {
        // Affiliation no longer valid — force re-authentication
        session_destroy();
        header('Location: ' . APP_URL . '/doctor/select-hospital.php?error=affiliation_revoked');
        exit;
    }
}

/**
 * Require valid patient ownership
 * Patient can only access their own records
 */
function requirePatientOwnership(int $patientId): void {
    requireAuth('PATIENT');

    if (!isset($_SESSION['patient_id']) || (int)$_SESSION['patient_id'] !== $patientId) {
        http_response_code(403);
        include __DIR__ . '/../../errors/403.php';
        exit;
    }
}

/**
 * Require valid doctor access session for a specific patient
 * Checks active access_session with time validity
 */
function requireDoctorAccessSession(int $patientId): array {
    requireDoctorHospitalContext();

    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM access_sessions
        WHERE doctor_id = ?
          AND patient_id = ?
          AND hospital_id = ?
          AND status = 'ACTIVE'
          AND expires_at > NOW()
        ORDER BY started_at DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['doctor_id'], $patientId, $_SESSION['hospital_id']]);
    $session = $stmt->fetch();

    if (!$session) {
        header('Location: ' . APP_URL . '/doctor/patient-result.php?error=no_access&patient=' . $patientId);
        exit;
    }

    return $session;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output CSRF hidden input HTML
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/**
 * Validate CSRF token
 */
function validateCsrf(): void {
    initSession();
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(419);
        die(json_encode(['error' => 'CSRF token mismatch. Please refresh and try again.']));
    }
    // Regenerate token after use
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Redirect based on role
 */
function redirectToLogin(string $role): void {
    $routes = [
        'PATIENT'       => APP_URL . '/patient/login.php',
        'DOCTOR'        => APP_URL . '/doctor/select-hospital.php',
        'HOSPITAL_ADMIN'=> APP_URL . '/hospital/login.php',
        'SYSTEM_ADMIN'  => APP_URL . '/admin/login.php',
    ];
    header('Location: ' . ($routes[$role] ?? APP_URL . '/role-selection.php'));
    exit;
}

/**
 * Logout the current user
 */
function logout(): void {
    initSession();
    $userId = $_SESSION['user_id'] ?? null;
    AuditService::log(AuditService::LOGOUT, ['user_id' => $userId]);
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/');
    exit;
}

/**
 * Check if user is logged in as specific role (no redirect)
 */
function isLoggedIn(string $role): bool {
    initSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === $role;
}
