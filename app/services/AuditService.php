<?php
/**
 * MedCore Audit Service
 * Logs all sensitive operations
 */

require_once __DIR__ . '/../config/database.php';

class AuditService {

    const LOGIN                      = 'LOGIN';
    const LOGOUT                     = 'LOGOUT';
    const PATIENT_SEARCH             = 'PATIENT_SEARCH';
    const ACCESS_REQUEST             = 'ACCESS_REQUEST';
    const ACCESS_GRANTED             = 'ACCESS_GRANTED';
    const ACCESS_DENIED              = 'ACCESS_DENIED';
    const VIEW_MEDICAL_HISTORY       = 'VIEW_MEDICAL_HISTORY';
    const VIEW_LAB_REPORT            = 'VIEW_LAB_REPORT';
    const CREATE_CONSULTATION        = 'CREATE_CONSULTATION';
    const CREATE_PRESCRIPTION        = 'CREATE_PRESCRIPTION';
    const UPDATE_PROFILE             = 'UPDATE_PROFILE';
    const DOWNLOAD_REPORT            = 'DOWNLOAD_REPORT';
    const DOCTOR_VERIFIED            = 'DOCTOR_VERIFIED';
    const HOSPITAL_VERIFIED          = 'HOSPITAL_VERIFIED';
    const AFFILIATION_APPROVED       = 'DOCTOR_AFFILIATION_APPROVED';
    const AFFILIATION_REJECTED       = 'DOCTOR_AFFILIATION_REJECTED';
    const UNAUTHORIZED_ACCESS        = 'UNAUTHORIZED_ACCESS';
    const FAILED_LOGIN               = 'FAILED_LOGIN';
    const FAILED_OTP                 = 'FAILED_OTP';
    // Laboratory audit events
    const LAB_REGISTERED             = 'LAB_REGISTERED';
    const LAB_VERIFIED               = 'LAB_VERIFIED';
    const LAB_AFFILIATED             = 'LAB_AFFILIATED';
    const LAB_TOKEN_SEARCHED         = 'LAB_TOKEN_SEARCHED';
    const LAB_RESULT_UPLOADED        = 'LAB_RESULT_UPLOADED';
    const LAB_ORDER_CREATED          = 'LAB_ORDER_CREATED';
    const LAB_ORDER_COMPLETED        = 'LAB_ORDER_COMPLETED';
    const VIEW_LAB_RESULT            = 'VIEW_LAB_RESULT';

    public static function log(
        string $action,
        array $context = []
    ): void {
        try {
            $db = getDB();

            $stmt = $db->prepare("
                INSERT INTO audit_logs
                    (user_id, action, target_type, target_id, patient_id, doctor_id, hospital_id,
                     ip_address, user_agent, severity, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                return;
            }

            $severity = $context['severity'] ?? 'INFO';
            $metadata = isset($context['metadata']) ? json_encode($context['metadata']) : null;

            $stmt->execute([
                $context['user_id']     ?? null,
                $action,
                $context['target_type'] ?? null,
                $context['target_id']   ?? null,
                $context['patient_id']  ?? null,
                $context['doctor_id']   ?? null,
                $context['hospital_id'] ?? null,
                self::getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $severity,
                $metadata,
            ]);
        } catch (Throwable $e) {
            // Never let audit log failure break the application
            error_log('AuditService error: ' . $e->getMessage());
        }
    }

    private static function getClientIP(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return explode(',', $_SERVER[$key])[0];
            }
        }
        return '0.0.0.0';
    }
}
