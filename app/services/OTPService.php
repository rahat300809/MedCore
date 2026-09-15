<?php
/**
 * MedCore OTP Service
 * Generates, stores, and verifies OTPs
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

class OTPService {

    /**
     * Generate and store a new OTP for the given purpose
     */
    public static function generate(
        string $purpose,
        ?int $userId = null,
        ?string $referenceId = null,
        string $channel = 'EMAIL',
        string $destination = ''
    ): array {
        $db = getDB();

        // Invalidate any existing pending OTPs for same user+purpose
        if ($userId) {
            $stmt = $db->prepare("
                UPDATE otp_verifications
                SET status = 'EXPIRED'
                WHERE user_id = ? AND purpose = ? AND status = 'PENDING'
            ");
            $stmt->execute([$userId, $purpose]);
        }

        // Generate cryptographically secure 6-digit OTP
        $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($otp, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

        // Mask destination for display
        $masked = self::maskDestination($destination, $channel);

        // Store OTP (with plain text only in dev mode)
        $stmt = $db->prepare("
            INSERT INTO otp_verifications
                (user_id, reference_id, purpose, otp_hash, otp_display, channel, destination_masked, expires_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
        ");
        $stmt->execute([
            $userId,
            $referenceId,
            $purpose,
            $hash,
            OTP_DEV_MODE ? $otp : null, // Only store plain in dev
            $channel,
            $masked,
            $expiresAt
        ]);

        $otpId = (int)$db->lastInsertId();

        return [
            'id'         => $otpId,
            'otp'        => OTP_DEV_MODE ? $otp : null, // Return plain only in dev
            'expires_at' => $expiresAt,
            'masked_dest' => $masked,
        ];
    }

    /**
     * Verify an OTP by ID
     */
    public static function verify(int $otpId, string $inputOtp): array {
        $db = getDB();

        $stmt = $db->prepare("
            SELECT * FROM otp_verifications
            WHERE id = ? AND status = 'PENDING'
        ");
        $stmt->execute([$otpId]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'error' => 'OTP not found or already used.'];
        }

        // Check expiry
        if (strtotime($record['expires_at']) < time()) {
            $db->prepare("UPDATE otp_verifications SET status='EXPIRED' WHERE id=?")->execute([$otpId]);
            return ['success' => false, 'error' => 'OTP has expired. Please request a new one.'];
        }

        // Check max attempts
        if ($record['attempt_count'] >= $record['max_attempts']) {
            $db->prepare("UPDATE otp_verifications SET status='EXHAUSTED' WHERE id=?")->execute([$otpId]);
            return ['success' => false, 'error' => 'Too many failed attempts. Please request a new OTP.'];
        }

        // Increment attempt count
        $db->prepare("UPDATE otp_verifications SET attempt_count = attempt_count + 1 WHERE id=?")->execute([$otpId]);

        // Verify OTP
        if (!password_verify($inputOtp, $record['otp_hash'])) {
            $remaining = $record['max_attempts'] - $record['attempt_count'] - 1;
            return [
                'success' => false,
                'error' => "Invalid OTP. {$remaining} attempt(s) remaining.",
                'remaining' => $remaining
            ];
        }

        // Mark as verified
        $db->prepare("
            UPDATE otp_verifications
            SET status='VERIFIED', verified_at=NOW()
            WHERE id=?
        ")->execute([$otpId]);

        return ['success' => true, 'record' => $record];
    }

    /**
     * Mask destination for display (email: r****@example.com, phone: 017****8888)
     */
    private static function maskDestination(string $destination, string $channel): string {
        if ($channel === 'EMAIL') {
            $parts = explode('@', $destination);
            if (count($parts) === 2) {
                return substr($parts[0], 0, 1) . '****@' . $parts[1];
            }
        } elseif ($channel === 'SMS') {
            return substr($destination, 0, 4) . '****' . substr($destination, -4);
        }
        return '****';
    }
}
