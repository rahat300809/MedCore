<?php
/**
 * MedCore Application Configuration
 */

define('MEDCORE_VERSION', '1.0.0');
define('APP_NAME', 'MedCore');
define('APP_TAGLINE', 'One Record. Every Care.');
define('APP_URL', 'http://localhost/medcore');

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
