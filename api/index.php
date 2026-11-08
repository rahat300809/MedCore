<?php
/**
 * MedCore — Vercel Serverless Master Router & Entry Point
 */

// Enable output buffering so headers, cookies, and redirects work cleanly
if (!ob_get_level()) {
    ob_start();
}

// Suppress deprecations so PHP 8.5 notices do not corrupt HTTP response headers
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);

$rootDir = dirname(__DIR__);
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($rawUri, PHP_URL_PATH) ?: '/';

// Diagnostic hook
if (isset($_SERVER['HTTP_X_DEBUG_MEDCORE']) || isset($_GET['diag']) || strpos($rawUri, 'diag') !== false) {
    header('Content-Type: application/json');
    $files = [];
    $checkDirs = ['/var/task', '/var/task/user', '/var/task/user/database', '/var/task/database', '/var/task/api', $rootDir, $rootDir . '/database', $rootDir . '/api', sys_get_temp_dir()];
    foreach ($checkDirs as $d) {
        $files[$d] = file_exists($d) ? @scandir($d) : 'NOT_FOUND';
    }
    echo json_encode([
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
        'PHP_SELF'    => $_SERVER['PHP_SELF'] ?? null,
        'rootDir'     => $rootDir,
        'dir'         => __DIR__,
        'cwd'         => getcwd(),
        'files'       => $files,
    ], JSON_PRETTY_PRINT);
    exit;
}

// Quick Diagnostics & Health Check
if (isset($_GET['health']) || $uri === '/health') {
    header('Content-Type: application/json');
    $dbStatus = 'UNKNOWN';
    $dbError = null;
    $dbDriver = null;
    $userCount = 0;
    try {
        require_once $rootDir . '/app/config/database.php';
        $db = getDB();
        $dbStatus = 'OK';
        $dbDriver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $userCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (\Throwable $e) {
        $dbStatus = 'FAILED';
        $dbError = $e->getMessage();
    }
    echo json_encode([
        'status' => 'OK',
        'php_version' => PHP_VERSION,
        'rootDir' => $rootDir,
        'dir' => __DIR__,
        'db_status' => $dbStatus,
        'db_driver' => $dbDriver,
        'user_count' => $userCount,
        'db_error' => $dbError,
        'scandir_root' => @scandir($rootDir) ?: [],
        'scandir_dir' => @scandir(__DIR__) ?: [],
    ], JSON_PRETTY_PRINT);
    exit;
}

// Normalize subfolder prefix if any
$uri = preg_replace('#^/medcore#', '', $uri);
if ($uri === '' || $uri === '/') {
    $uri = '/index.php';
}

// 1. Static Asset Handler (fall-through for css/js/images/fonts)
if (preg_match('#^/assets/(.+)$#', $uri, $matches)) {
    $assetPath = $rootDir . '/public/assets/' . $matches[1];
    if (is_file($assetPath)) {
        $ext = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=utf-8',
            'js'    => 'application/javascript; charset=utf-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'webp'  => 'image/webp',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'json'  => 'application/json',
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        header('Cache-Control: public, max-age=86400');
        readfile($assetPath);
        exit;
    }
}

// 2. Direct script execution
$targetScript = null;

// Case A: Root index
if ($uri === '/index.php') {
    $targetScript = $rootDir . '/public/index.php';
}

// Case B: Direct match on filesystem
if (!$targetScript) {
    $candidate = $rootDir . $uri;
    if (is_file($candidate) && pathinfo($candidate, PATHINFO_EXTENSION) === 'php') {
        $targetScript = $candidate;
    } elseif (is_file($candidate . '.php')) {
        $targetScript = $candidate . '.php';
    }
}

// Case C: Look inside public/
if (!$targetScript) {
    $candidate = $rootDir . '/public' . $uri;
    if (is_file($candidate) && pathinfo($candidate, PATHINFO_EXTENSION) === 'php') {
        $targetScript = $candidate;
    } elseif (is_file($candidate . '.php')) {
        $targetScript = $candidate . '.php';
    }
}

// Execute script if found
if ($targetScript && is_file($targetScript)) {
    chdir(dirname($targetScript));
    try {
        require $targetScript;
    } catch (\Throwable $e) {
        http_response_code(500);
        error_log("MedCore Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>MedCore Error</title>';
        echo '<style>body{font-family:system-ui,-apple-system,sans-serif;padding:30px;background:#f8fafc;color:#1e293b}.card{background:#fff;padding:24px 30px;border-radius:12px;border:1px solid #e2e8f0;max-width:760px;margin:40px auto;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1)}h1{color:#ef4444;font-size:20px;margin-top:0}pre{background:#f1f5f9;padding:12px;border-radius:8px;overflow:auto;font-size:13px;line-height:1.5}</style></head><body>';
        echo '<div class="card">';
        echo '<h1>MedCore Application Error</h1>';
        echo '<p style="font-weight:600;color:#0f172a;">' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p style="color:#64748b;font-size:13px;">In <code>' . htmlspecialchars($e->getFile()) . '</code> on line <strong>' . $e->getLine() . '</strong></p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '<p style="margin-top:20px;"><a href="/" style="display:inline-block;padding:8px 16px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">&larr; Return to MedCore Home</a></p>';
        echo '</div></body></html>';
    }
    exit;
}

// 3. Fallback: 404 page
http_response_code(404);
if (is_file($rootDir . '/errors/404.php')) {
    chdir($rootDir . '/errors');
    require $rootDir . '/errors/404.php';
} else {
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 — Page Not Found</h1></body></html>';
}
exit;
