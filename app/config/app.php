<?php
/**
 * MedCore Application Configuration
 */

define('MEDCORE_VERSION', '1.0.0');
define('APP_NAME', 'MedCore');
define('APP_TAGLINE', 'One Record. Every Care.');
// Auto-detect APP_URL for local and cloud environments (Vercel, Render, Railway, localhost)
if (getenv('APP_URL')) {
    define('APP_URL', rtrim(getenv('APP_URL'), '/'));
} elseif (isset($_SERVER['HTTP_HOST'])) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $isHttps ? 'https://' : 'http://';
    $subDir = '';
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/medcore') === 0) {
        $subDir = '/medcore';
    }
    define('APP_URL', $scheme . $_SERVER['HTTP_HOST'] . $subDir);
} else {
    define('APP_URL', 'http://localhost/medcore');
}

// Environment: 'development' | 'production'
define('APP_ENV', 'development');

// Session settings
define('SESSION_NAME', 'medcore_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// OTP settings
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_MAX_ATTEMPTS', 5);
define('OTP_DEV_MODE', true); // Display OTP on screen in development

// Access session (doctor temporary access)
define('ACCESS_SESSION_DURATION_MINUTES', 30);

// Upload settings
define('MAX_UPLOAD_SIZE_MB', 10);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_DOC_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'webp']);

// Paths
define('STORAGE_PATH', dirname(__DIR__, 2) . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');

// Timezone
date_default_timezone_set('Asia/Dhaka');
